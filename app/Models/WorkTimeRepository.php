<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Config;
use App\Core\Database;
use RuntimeException;

class WorkTimeRepository
{
    /** @var Database */
    private $database;

    /** @var bool */
    private static $schemaEnsured = false;

    public function __construct(Database $database)
    {
        $this->database = $database;
    }

    public function ensureSchema(): void
    {
        if (self::$schemaEnsured) {
            return;
        }

        $config = Config::get('database');
        if (!isset($config['driver']) || (string) $config['driver'] !== 'mysql') {
            self::$schemaEnsured = true;
            return;
        }

        $this->database->query(
            "CREATE TABLE IF NOT EXISTS work_time_entries (\n"
            . "id INT UNSIGNED NOT NULL AUTO_INCREMENT,\n"
            . "user_id INT UNSIGNED NOT NULL,\n"
            . "work_date DATE NOT NULL,\n"
            . "start_time TIME DEFAULT NULL,\n"
            . "end_time TIME DEFAULT NULL,\n"
            . "hours DECIMAL(5,2) NOT NULL,\n"
            . "note VARCHAR(500) DEFAULT NULL,\n"
            . "created_by INT UNSIGNED NOT NULL,\n"
            . "updated_by INT UNSIGNED NOT NULL,\n"
            . "created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,\n"
            . "updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,\n"
            . "PRIMARY KEY (id),\n"
            . "KEY idx_work_time_user_date (user_id, work_date),\n"
            . "KEY idx_work_time_date (work_date)\n"
            . ") ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );

        $this->database->query(
            "CREATE TABLE IF NOT EXISTS work_time_audit_logs (\n"
            . "id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,\n"
            . "entry_id INT UNSIGNED NOT NULL,\n"
            . "owner_user_id INT UNSIGNED DEFAULT NULL,\n"
            . "actor_user_id INT UNSIGNED NOT NULL,\n"
            . "action VARCHAR(20) NOT NULL,\n"
            . "old_data_json LONGTEXT DEFAULT NULL,\n"
            . "new_data_json LONGTEXT DEFAULT NULL,\n"
            . "created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,\n"
            . "PRIMARY KEY (id),\n"
            . "KEY idx_work_time_audit_entry (entry_id, created_at),\n"
            . "KEY idx_work_time_audit_owner (owner_user_id, created_at),\n"
            . "KEY idx_work_time_audit_actor (actor_user_id, created_at)\n"
            . ") ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );

        $this->ensureColumn('work_time_entries', 'start_time', "ALTER TABLE work_time_entries ADD COLUMN start_time TIME DEFAULT NULL AFTER work_date");
        $this->ensureColumn('work_time_entries', 'end_time', "ALTER TABLE work_time_entries ADD COLUMN end_time TIME DEFAULT NULL AFTER start_time");
        $this->ensureColumn('work_time_audit_logs', 'owner_user_id', "ALTER TABLE work_time_audit_logs ADD COLUMN owner_user_id INT UNSIGNED DEFAULT NULL AFTER entry_id");

        self::$schemaEnsured = true;
    }

    public function entriesForMonth(string $month, ?int $userId): array
    {
        $params = array('month_start' => $month . '-01', 'month_end' => date('Y-m-d', strtotime($month . '-01 +1 month')));
        $where = 'entries.work_date >= :month_start AND entries.work_date < :month_end';
        if ($userId !== null) {
            $where .= ' AND entries.user_id = :user_id';
            $params['user_id'] = $userId;
        }

        return $this->database->fetchAll(
            'SELECT entries.*, DATE_FORMAT(entries.start_time, \'%H:%i\') AS start_time_display,'
            . ' DATE_FORMAT(entries.end_time, \'%H:%i\') AS end_time_display, users.email, users.first_name, users.last_name'
            . ' FROM work_time_entries entries'
            . ' INNER JOIN users ON users.id = entries.user_id'
            . ' WHERE ' . $where
            . ' ORDER BY entries.work_date DESC, entries.id DESC',
            $params
        );
    }

    public function monthlySummaries(string $month, ?int $userId): array
    {
        $params = array('month_start' => $month . '-01', 'month_end' => date('Y-m-d', strtotime($month . '-01 +1 month')));
        $where = 'entries.work_date >= :month_start AND entries.work_date < :month_end';
        if ($userId !== null) {
            $where .= ' AND entries.user_id = :user_id';
            $params['user_id'] = $userId;
        }

        return $this->database->fetchAll(
            'SELECT entries.user_id, users.email, users.first_name, users.last_name,'
            . ' ROUND(SUM(entries.hours), 2) AS total_hours, COUNT(*) AS entry_count,'
            . ' COUNT(DISTINCT entries.work_date) AS work_days'
            . ' FROM work_time_entries entries'
            . ' INNER JOIN users ON users.id = entries.user_id'
            . ' WHERE ' . $where
            . ' GROUP BY entries.user_id, users.email, users.first_name, users.last_name'
            . ' ORDER BY users.last_name, users.first_name, users.email',
            $params
        );
    }

    public function find(int $id)
    {
        return $this->database->fetch('SELECT * FROM work_time_entries WHERE id = :id LIMIT 1', array('id' => $id));
    }

    public function create(array $data, int $actorUserId): int
    {
        return (int) $this->database->transaction(function () use ($data, $actorUserId): int {
            $entryId = (int) $this->database->insert('work_time_entries', array(
                'user_id' => (int) $data['user_id'],
                'work_date' => (string) $data['work_date'],
                'start_time' => (string) $data['start_time'],
                'end_time' => (string) $data['end_time'],
                'hours' => (string) $data['hours'],
                'note' => (string) ($data['note'] ?? ''),
                'created_by' => $actorUserId,
                'updated_by' => $actorUserId,
            ));
            $entry = $this->find($entryId);
            $this->writeAudit($entryId, $actorUserId, 'create', null, is_array($entry) ? $entry : $data);
            return $entryId;
        });
    }

    public function updateEntry(int $id, array $data, int $actorUserId): void
    {
        $this->database->transaction(function () use ($id, $data, $actorUserId): void {
            $old = $this->find($id);
            if (!is_array($old)) {
                throw new RuntimeException('Wpis czasu pracy nie istnieje.');
            }
            $this->database->update('work_time_entries', array(
                'user_id' => (int) $data['user_id'],
                'work_date' => (string) $data['work_date'],
                'start_time' => (string) $data['start_time'],
                'end_time' => (string) $data['end_time'],
                'hours' => (string) $data['hours'],
                'note' => (string) ($data['note'] ?? ''),
                'updated_by' => $actorUserId,
                'updated_at' => date('Y-m-d H:i:s'),
            ), 'id = :id', array('id' => $id));
            $new = $this->find($id);
            $this->writeAudit($id, $actorUserId, 'update', $old, is_array($new) ? $new : $data);
        });
    }

    public function deleteEntry(int $id, int $actorUserId): void
    {
        $this->database->transaction(function () use ($id, $actorUserId): void {
            $old = $this->find($id);
            if (!is_array($old)) {
                throw new RuntimeException('Wpis czasu pracy nie istnieje.');
            }
            $this->writeAudit($id, $actorUserId, 'delete', $old, $old);
            $this->database->delete('work_time_entries', 'id = :id', array('id' => $id));
        });
    }

    public function auditLogs(?int $userId, int $limit = 200): array
    {
        $limit = max(1, min(500, $limit));
        $where = '';
        $params = array();
        if ($userId !== null) {
            $where = ' WHERE COALESCE(entries.user_id, logs.owner_user_id) = :user_id';
            $params['user_id'] = $userId;
        }
        return $this->database->fetchAll(
            'SELECT logs.*, COALESCE(entries.user_id, logs.owner_user_id) AS user_id, actor.email AS actor_email,'
            . ' owner.email AS owner_email, owner.first_name AS owner_first_name, owner.last_name AS owner_last_name'
            . ' FROM work_time_audit_logs logs'
            . ' LEFT JOIN work_time_entries entries ON entries.id = logs.entry_id'
            . ' INNER JOIN users actor ON actor.id = logs.actor_user_id'
            . ' LEFT JOIN users owner ON owner.id = COALESCE(entries.user_id, logs.owner_user_id)'
            . $where . ' ORDER BY logs.id DESC LIMIT ' . $limit,
            $params
        );
    }

    private function writeAudit(int $entryId, int $actorUserId, string $action, ?array $old, array $new): void
    {
        $ownerUserId = (int) ($new['user_id'] ?? ($old['user_id'] ?? 0));
        $this->database->insert('work_time_audit_logs', array(
            'entry_id' => $entryId,
            'owner_user_id' => $ownerUserId > 0 ? $ownerUserId : null,
            'actor_user_id' => $actorUserId,
            'action' => $action,
            'old_data_json' => $old === null ? null : json_encode($old, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'new_data_json' => json_encode($new, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        ));
    }

    private function ensureColumn(string $table, string $column, string $alterSql): void
    {
        $config = Config::get('database');
        $databaseName = isset($config['database']) ? (string) $config['database'] : '';
        if ($databaseName === '') {
            return;
        }

        $exists = (int) $this->database->fetchColumn(
            'SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = :schema_name AND TABLE_NAME = :table_name AND COLUMN_NAME = :column_name',
            array('schema_name' => $databaseName, 'table_name' => $table, 'column_name' => $column)
        );

        if ($exists === 0) {
            $this->database->query($alterSql);
        }
    }
}
