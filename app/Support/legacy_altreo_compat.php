<?php

declare(strict_types=1);

use App\Core\Database;

if (!defined('_DB_PREFIX_')) {
    define('_DB_PREFIX_', 'pr_');
}

if (!function_exists('pSQL')) {
    function pSQL(string $value): string
    {
        return $value;
    }
}

if (!class_exists('Db')) {
    class Db
    {
        public static function getInstance(): DbCompat
        {
            return new DbCompat(Database::instance());
        }
    }
}

if (!class_exists('DB')) {
    class DB extends Db
    {
    }
}

if (!class_exists('DbCompat')) {
    class DbCompat
    {
        /** @var Database */
        private $db;

        public function __construct(Database $db)
        {
            $this->db = $db;
        }

        public function executeS(string $sql): array
        {
            return $this->db->fetchAll($sql);
        }

        public function getRow(string $sql)
        {
            return $this->db->fetch($sql);
        }

        public function getValue(string $sql)
        {
            return $this->db->fetchColumn($sql);
        }

        public function execute(string $sql): bool
        {
            return $this->db->execute($sql);
        }

        public function query(string $sql)
        {
            return $this->db->query($sql);
        }
    }
}
