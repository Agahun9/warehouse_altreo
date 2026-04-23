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

    private function __construct()
    {
        $config = Config::get('database');
        $driver = isset($config['driver']) ? (string) $config['driver'] : 'mysql';

        try {
            $this->pdo = new PDO(
                $this->buildDsn($config),
                isset($config['username']) ? (string) $config['username'] : '',
                isset($config['password']) ? (string) $config['password'] : '',
                $this->buildOptions($config)
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
        $statement = $this->pdo->prepare($sql);
        $statement->execute($params);

        return $statement;
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
