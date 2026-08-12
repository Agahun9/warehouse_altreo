<?php

declare(strict_types=1);

namespace App\Core;

use PDO;
use PDOException;
use PDOStatement;
use RuntimeException;
use Throwable;

class Database
{
    /** @var self|null */
    private static $instance = null;

    /** @var PDO */
    private $pdo;

    /** @var array */
    private $config;

    private function __construct()
    {
        $this->config = Config::get('database');
        $this->connect();
    }

    private function connect(): void
    {
        $driver = isset($this->config['driver']) ? (string) $this->config['driver'] : 'mysql';

        try {
            $this->pdo = new PDO(
                $this->buildDsn($this->config),
                isset($this->config['username']) ? (string) $this->config['username'] : '',
                isset($this->config['password']) ? (string) $this->config['password'] : '',
                $this->buildOptions($this->config)
            );
        } catch (PDOException $exception) {
            throw new RuntimeException(
                'Nie mozna polaczyc sie z baza danych dla sterownika "' . $driver . '". ' . $exception->getMessage(),
                0,
                $exception
            );
        }
    }

    public static function instance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    public function pdo(): PDO
    {
        return $this->pdo;
    }

    public function query(string $sql, array $params = array()): PDOStatement
    {
        try {
            $statement = $this->pdo->prepare($sql);
            $statement->execute($params);

            return $statement;
        } catch (PDOException $exception) {
            if (!$this->isGoneAwayException($exception) || $this->pdo->inTransaction()) {
                throw $exception;
            }

            $this->connect();

            $statement = $this->pdo->prepare($sql);
            $statement->execute($params);

            return $statement;
        }
    }

    private function isGoneAwayException(PDOException $exception): bool
    {
        $driverCode = isset($exception->errorInfo[1]) ? (int) $exception->errorInfo[1] : 0;

        return in_array($driverCode, array(2006, 2013), true);
    }

    public function fetchAll(string $sql, array $params = array()): array
    {
        return $this->query($sql, $params)->fetchAll();
    }

    public function fetch(string $sql, array $params = array())
    {
        return $this->query($sql, $params)->fetch();
    }

    public function fetchColumn(string $sql, array $params = array(), int $column = 0)
    {
        return $this->query($sql, $params)->fetchColumn($column);
    }

    public function execute(string $sql, array $params = array()): bool
    {
        return $this->query($sql, $params)->rowCount() >= 0;
    }

    /**
     * Acquires a connection-scoped MySQL advisory lock without waiting. MySQL releases
     * this lock automatically when the request/connection dies, which makes it suitable
     * for web workers that may be killed by PHP-FPM before a finally block can run.
     */
    public function acquireAdvisoryLock(string $name): bool
    {
        $driver = isset($this->config['driver']) ? (string) $this->config['driver'] : 'mysql';
        if ($driver !== 'mysql') {
            return true;
        }

        $result = $this->fetchColumn(
            'SELECT GET_LOCK(:lock_name, 0)',
            array('lock_name' => substr($name, 0, 64))
        );

        return (int) $result === 1;
    }

    public function releaseAdvisoryLock(string $name): void
    {
        $driver = isset($this->config['driver']) ? (string) $this->config['driver'] : 'mysql';
        if ($driver !== 'mysql') {
            return;
        }

        $this->fetchColumn(
            'SELECT RELEASE_LOCK(:lock_name)',
            array('lock_name' => substr($name, 0, 64))
        );
    }

    public function advisoryLockOwner(string $name): ?int
    {
        $driver = isset($this->config['driver']) ? (string) $this->config['driver'] : 'mysql';
        if ($driver !== 'mysql') {
            return null;
        }

        $owner = $this->fetchColumn(
            'SELECT IS_USED_LOCK(:lock_name)',
            array('lock_name' => substr($name, 0, 64))
        );

        return $owner === false || $owner === null ? null : (int) $owner;
    }

    /** Interrupts the active statement without dropping/reconnecting the owning worker. */
    public function interruptConnectionQuery(int $connectionId): bool
    {
        if ($connectionId <= 0) {
            return false;
        }

        $currentId = (int) $this->fetchColumn('SELECT CONNECTION_ID()');
        if ($currentId === $connectionId) {
            return false;
        }

        $this->pdo->exec('KILL QUERY ' . $connectionId);
        return true;
    }

    /**
     * Runs $callback with a hard MySQL-side cap on how long any single statement inside it
     * may run, then restores the previous (unlimited) setting. PHP's own set_time_limit()
     * cannot help here: it only fires between opcodes, never while execution is blocked
     * inside a single query - a query that turns out to be much more expensive than expected
     * (missing index, unexpectedly large table, ...) can otherwise tie up a web worker, and
     * on shared hosting with a small worker pool / shared MySQL instance, that alone can make
     * the whole site feel frozen for everyone. Requires MySQL 5.7.8+/MariaDB 10.1.1+; on
     * older servers the SET is a no-op (silently ignored) and $callback just runs unbounded.
     */
    public function withStatementTimeoutMs(int $milliseconds, callable $callback)
    {
        $timeoutMode = '';

        try {
            $this->pdo->exec('SET SESSION MAX_EXECUTION_TIME = ' . max(0, $milliseconds));
            $timeoutMode = 'mysql';
        } catch (PDOException $exception) {
            // MariaDB uses max_statement_time (seconds) instead of MySQL's
            // MAX_EXECUTION_TIME (milliseconds). Without this fallback, the Empik target
            // selection query could remain inside MariaDB long after PHP's time limit.
            try {
                $seconds = max(0, $milliseconds) / 1000;
                $this->pdo->exec('SET SESSION max_statement_time = ' . number_format($seconds, 3, '.', ''));
                $timeoutMode = 'mariadb';
            } catch (PDOException $mariaDbException) {
                // Older/other database: proceed without a server-side statement timeout.
            }
        }

        try {
            return $callback();
        } finally {
            if ($timeoutMode !== '') {
                try {
                    $this->pdo->exec($timeoutMode === 'mysql'
                        ? 'SET SESSION MAX_EXECUTION_TIME = 0'
                        : 'SET SESSION max_statement_time = 0');
                } catch (PDOException $exception) {
                    // Best-effort restore; nothing sensible to do if this fails too.
                }
            }
        }
    }

    public function insert(string $table, array $data): string
    {
        if ($data === array()) {
            throw new RuntimeException('Insert wymaga danych do zapisania.');
        }

        $columns = array_keys($data);
        $placeholders = array();

        foreach ($columns as $column) {
            $placeholders[] = ':' . $column;
        }

        $sql = sprintf(
            'INSERT INTO %s (%s) VALUES (%s)',
            $table,
            implode(', ', $columns),
            implode(', ', $placeholders)
        );

        $this->query($sql, $data);

        return $this->pdo->lastInsertId();
    }

    public function update(string $table, array $data, string $where, array $whereParams = array()): int
    {
        if ($data === array()) {
            throw new RuntimeException('Update wymaga danych do zapisania.');
        }

        $setParts = array();
        $params = array();

        foreach ($data as $column => $value) {
            $paramName = 'set_' . $column;
            $setParts[] = $column . ' = :' . $paramName;
            $params[$paramName] = $value;
        }

        $sql = sprintf('UPDATE %s SET %s WHERE %s', $table, implode(', ', $setParts), $where);
        $statement = $this->query($sql, array_merge($params, $whereParams));

        return $statement->rowCount();
    }

    public function delete(string $table, string $where, array $params = array()): int
    {
        $sql = sprintf('DELETE FROM %s WHERE %s', $table, $where);
        $statement = $this->query($sql, $params);

        return $statement->rowCount();
    }

    public function transaction(callable $callback)
    {
        $maxAttempts = 3;

        for ($attempt = 1; $attempt <= $maxAttempts; $attempt++) {
            $this->pdo->beginTransaction();

            try {
                $result = $callback($this);
                $this->pdo->commit();

                return $result;
            } catch (Throwable $exception) {
                if ($this->pdo->inTransaction()) {
                    $this->pdo->rollBack();
                }

                if (!$this->shouldRetryTransaction($exception) || $attempt >= $maxAttempts) {
                    throw $exception;
                }

                usleep($this->transactionRetryDelayMicroseconds($attempt));
            }
        }

        throw new RuntimeException('Nie udalo sie zakonczyc transakcji.');
    }

    private function shouldRetryTransaction(Throwable $exception): bool
    {
        if (!$exception instanceof PDOException) {
            return false;
        }

        $sqlState = isset($exception->errorInfo[0]) ? (string) $exception->errorInfo[0] : (string) $exception->getCode();
        $driverCode = isset($exception->errorInfo[1]) ? (int) $exception->errorInfo[1] : 0;

        if ($sqlState === '40001') {
            return true;
        }

        return in_array($driverCode, array(1205, 1213), true);
    }

    private function transactionRetryDelayMicroseconds(int $attempt): int
    {
        $baseDelay = 100000;

        return $baseDelay * $attempt;
    }

    private function buildDsn(array $config): string
    {
        $driver = isset($config['driver']) ? (string) $config['driver'] : 'mysql';

        if ($driver === 'sqlite') {
            $path = isset($config['path']) ? (string) $config['path'] : BASE_PATH . '/database.sqlite';
            return 'sqlite:' . $path;
        }

        if ($driver === 'pgsql') {
            return sprintf(
                'pgsql:host=%s;port=%s;dbname=%s',
                isset($config['host']) ? (string) $config['host'] : '127.0.0.1',
                isset($config['port']) ? (string) $config['port'] : '5432',
                isset($config['database']) ? (string) $config['database'] : ''
            );
        }

        return sprintf(
            'mysql:host=%s;port=%s;dbname=%s;charset=%s',
            isset($config['host']) ? (string) $config['host'] : '127.0.0.1',
            isset($config['port']) ? (string) $config['port'] : '3306',
            isset($config['database']) ? (string) $config['database'] : '',
            isset($config['charset']) ? (string) $config['charset'] : 'utf8mb4'
        );
    }

    private function buildOptions(array $config): array
    {
        $defaultOptions = array(
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        );

        $customOptions = isset($config['options']) && is_array($config['options']) ? $config['options'] : array();

        return $defaultOptions + $customOptions;
    }
}
