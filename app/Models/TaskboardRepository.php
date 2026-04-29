<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Config;
use App\Core\Database;

class TaskboardRepository
{
    private const BOARD_TABLE = 'task_boards';
    private const TASK_TABLE = 'task_items';
    private const SUBTASK_TABLE = 'task_subtasks';
    private const NOTE_TABLE = 'task_notes';
    private const ATTACHMENT_TABLE = 'task_attachments';

    /** @var bool */
    private static $schemaEnsured = false;

    /** @var Database */
    private $database;

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
            "CREATE TABLE IF NOT EXISTS " . self::BOARD_TABLE . " (\n"
            . "id INT UNSIGNED NOT NULL AUTO_INCREMENT,\n"
            . "name VARCHAR(120) NOT NULL,\n"
            . "description TEXT DEFAULT NULL,\n"
            . "accent_color VARCHAR(20) NOT NULL DEFAULT '#0d6efd',\n"
            . "statuses_json LONGTEXT DEFAULT NULL,\n"
            . "status_span TINYINT UNSIGNED NOT NULL DEFAULT 3,\n"
            . "is_archived TINYINT(1) NOT NULL DEFAULT 0,\n"
            . "created_by INT UNSIGNED DEFAULT NULL,\n"
            . "created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,\n"
            . "updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,\n"
            . "PRIMARY KEY (id),\n"
            . "KEY idx_task_boards_archived (is_archived),\n"
            . "KEY idx_task_boards_created_by (created_by)\n"
            . ") ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );

        $this->database->query(
            "CREATE TABLE IF NOT EXISTS " . self::TASK_TABLE . " (\n"
            . "id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,\n"
            . "board_id INT UNSIGNED NOT NULL,\n"
            . "title VARCHAR(190) NOT NULL,\n"
            . "description LONGTEXT DEFAULT NULL,\n"
            . "status VARCHAR(32) NOT NULL DEFAULT 'todo',\n"
            . "priority VARCHAR(20) NOT NULL DEFAULT 'medium',\n"
            . "position INT UNSIGNED NOT NULL DEFAULT 0,\n"
            . "assigned_user_id INT UNSIGNED DEFAULT NULL,\n"
            . "due_at DATETIME DEFAULT NULL,\n"
            . "completed_at DATETIME DEFAULT NULL,\n"
            . "archived_from_status VARCHAR(32) DEFAULT NULL,\n"
            . "archived_from_position INT UNSIGNED DEFAULT NULL,\n"
            . "created_by INT UNSIGNED DEFAULT NULL,\n"
            . "updated_by INT UNSIGNED DEFAULT NULL,\n"
            . "created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,\n"
            . "updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,\n"
            . "PRIMARY KEY (id),\n"
            . "KEY idx_task_items_board_status_position (board_id, status, position),\n"
            . "KEY idx_task_items_due_at (due_at),\n"
            . "KEY idx_task_items_assigned_user (assigned_user_id)\n"
            . ") ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );

        $this->database->query(
            "CREATE TABLE IF NOT EXISTS " . self::SUBTASK_TABLE . " (\n"
            . "id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,\n"
            . "task_id BIGINT UNSIGNED NOT NULL,\n"
            . "label VARCHAR(255) NOT NULL,\n"
            . "position INT UNSIGNED NOT NULL DEFAULT 0,\n"
            . "is_done TINYINT(1) NOT NULL DEFAULT 0,\n"
            . "done_at DATETIME DEFAULT NULL,\n"
            . "created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,\n"
            . "updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,\n"
            . "PRIMARY KEY (id),\n"
            . "KEY idx_task_subtasks_task_position (task_id, position),\n"
            . "KEY idx_task_subtasks_done (is_done)\n"
            . ") ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );

        $this->database->query(
            "CREATE TABLE IF NOT EXISTS " . self::NOTE_TABLE . " (\n"
            . "id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,\n"
            . "task_id BIGINT UNSIGNED NOT NULL,\n"
            . "note LONGTEXT NOT NULL,\n"
            . "created_by INT UNSIGNED DEFAULT NULL,\n"
            . "created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,\n"
            . "PRIMARY KEY (id),\n"
            . "KEY idx_task_notes_task_created (task_id, created_at)\n"
            . ") ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );

        $this->database->query(
            "CREATE TABLE IF NOT EXISTS " . self::ATTACHMENT_TABLE . " (\n"
            . "id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,\n"
            . "task_id BIGINT UNSIGNED NOT NULL,\n"
            . "file_name VARCHAR(255) NOT NULL,\n"
            . "file_path VARCHAR(255) NOT NULL,\n"
            . "mime_type VARCHAR(120) DEFAULT NULL,\n"
            . "file_size INT UNSIGNED NOT NULL DEFAULT 0,\n"
            . "uploaded_by INT UNSIGNED DEFAULT NULL,\n"
            . "created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,\n"
            . "PRIMARY KEY (id),\n"
            . "KEY idx_task_attachments_task_created (task_id, created_at)\n"
            . ") ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );

        $this->ensureBoardColumn('statuses_json', "ALTER TABLE " . self::BOARD_TABLE . " ADD COLUMN statuses_json LONGTEXT DEFAULT NULL AFTER accent_color");
        $this->ensureBoardColumn('status_span', "ALTER TABLE " . self::BOARD_TABLE . " ADD COLUMN status_span TINYINT UNSIGNED NOT NULL DEFAULT 3 AFTER statuses_json");
        $this->ensureTaskColumn('archived_from_status', "ALTER TABLE " . self::TASK_TABLE . " ADD COLUMN archived_from_status VARCHAR(32) DEFAULT NULL AFTER completed_at");
        $this->ensureTaskColumn('archived_from_position', "ALTER TABLE " . self::TASK_TABLE . " ADD COLUMN archived_from_position INT UNSIGNED DEFAULT NULL AFTER archived_from_status");

        self::$schemaEnsured = true;
    }

    public function boards(bool $includeArchived = false): array
    {
        $sql = 'SELECT boards.*,'
            . ' COUNT(DISTINCT tasks.id) AS tasks_count,'
            . ' SUM(CASE WHEN tasks.status = "done" THEN 1 ELSE 0 END) AS done_count'
            . ' FROM ' . self::BOARD_TABLE . ' boards'
            . ' LEFT JOIN ' . self::TASK_TABLE . ' tasks ON tasks.board_id = boards.id';

        if (!$includeArchived) {
            $sql .= ' WHERE boards.is_archived = 0';
        }

        $sql .= ' GROUP BY boards.id'
            . ' ORDER BY boards.is_archived ASC, boards.updated_at DESC, boards.id DESC';

        return $this->database->fetchAll($sql);
    }

    public function boardById(int $boardId)
    {
        return $this->database->fetch(
            'SELECT * FROM ' . self::BOARD_TABLE . ' WHERE id = :id LIMIT 1',
            array('id' => $boardId)
        );
    }

    public function createBoard(array $data): int
    {
        return (int) $this->database->insert(self::BOARD_TABLE, $data);
    }

    public function updateBoard(int $boardId, array $data): int
    {
        return $this->database->update(self::BOARD_TABLE, $data, 'id = :id', array('id' => $boardId));
    }

    public function deleteBoard(int $boardId): int
    {
        return (int) $this->database->transaction(function () use ($boardId) {
            $taskIds = array_map('intval', array_column($this->tasksForBoard($boardId), 'id'));
            if ($taskIds !== array()) {
                $params = array();
                $placeholders = $this->buildIntegerPlaceholders('task_id', $taskIds, $params);
                $inClause = implode(', ', $placeholders);

                $this->database->query(
                    'DELETE FROM ' . self::SUBTASK_TABLE . ' WHERE task_id IN (' . $inClause . ')',
                    $params
                );
                $this->database->query(
                    'DELETE FROM ' . self::NOTE_TABLE . ' WHERE task_id IN (' . $inClause . ')',
                    $params
                );
                $this->database->query(
                    'DELETE FROM ' . self::ATTACHMENT_TABLE . ' WHERE task_id IN (' . $inClause . ')',
                    $params
                );
                $this->database->query(
                    'DELETE FROM ' . self::TASK_TABLE . ' WHERE id IN (' . $inClause . ')',
                    $params
                );
            }

            return $this->database->delete(self::BOARD_TABLE, 'id = :id', array('id' => $boardId));
        });
    }

    public function createTask(array $data): int
    {
        return (int) $this->database->insert(self::TASK_TABLE, $data);
    }

    public function updateTask(int $taskId, array $data): int
    {
        return $this->database->update(self::TASK_TABLE, $data, 'id = :id', array('id' => $taskId));
    }

    public function taskById(int $taskId)
    {
        return $this->database->fetch(
            'SELECT tasks.*, boards.name AS board_name, boards.accent_color AS board_accent_color,'
            . ' assigned.email AS assigned_user_email, assigned.first_name AS assigned_user_first_name, assigned.last_name AS assigned_user_last_name,'
            . ' creator.email AS created_by_email, creator.first_name AS created_by_first_name, creator.last_name AS created_by_last_name'
            . ' FROM ' . self::TASK_TABLE . ' tasks'
            . ' INNER JOIN ' . self::BOARD_TABLE . ' boards ON boards.id = tasks.board_id'
            . ' LEFT JOIN users assigned ON assigned.id = tasks.assigned_user_id'
            . ' LEFT JOIN users creator ON creator.id = tasks.created_by'
            . ' WHERE tasks.id = :id LIMIT 1',
            array('id' => $taskId)
        );
    }

    public function tasksForBoard(int $boardId): array
    {
        return $this->database->fetchAll(
            'SELECT tasks.*,'
            . ' assigned.email AS assigned_user_email, assigned.first_name AS assigned_user_first_name, assigned.last_name AS assigned_user_last_name,'
            . ' creator.email AS created_by_email, creator.first_name AS created_by_first_name, creator.last_name AS created_by_last_name'
            . ' FROM ' . self::TASK_TABLE . ' tasks'
            . ' LEFT JOIN users assigned ON assigned.id = tasks.assigned_user_id'
            . ' LEFT JOIN users creator ON creator.id = tasks.created_by'
            . ' WHERE tasks.board_id = :board_id'
            . ' ORDER BY tasks.position ASC, tasks.id ASC',
            array('board_id' => $boardId)
        );
    }

    public function subtasksForTaskIds(array $taskIds): array
    {
        $taskIds = $this->normalizeIntegerIds($taskIds);
        if ($taskIds === array()) {
            return array();
        }

        $params = array();
        $placeholders = $this->buildIntegerPlaceholders('task_id', $taskIds, $params);

        return $this->database->fetchAll(
            'SELECT * FROM ' . self::SUBTASK_TABLE
            . ' WHERE task_id IN (' . implode(', ', $placeholders) . ')'
            . ' ORDER BY task_id ASC, position ASC, id ASC',
            $params
        );
    }

    public function notesForTaskIds(array $taskIds): array
    {
        $taskIds = $this->normalizeIntegerIds($taskIds);
        if ($taskIds === array()) {
            return array();
        }

        $params = array();
        $placeholders = $this->buildIntegerPlaceholders('note_task_id', $taskIds, $params);

        return $this->database->fetchAll(
            'SELECT notes.*,'
            . ' users.email AS created_by_email, users.first_name AS created_by_first_name, users.last_name AS created_by_last_name'
            . ' FROM ' . self::NOTE_TABLE . ' notes'
            . ' LEFT JOIN users ON users.id = notes.created_by'
            . ' WHERE notes.task_id IN (' . implode(', ', $placeholders) . ')'
            . ' ORDER BY notes.created_at DESC, notes.id DESC',
            $params
        );
    }

    public function attachmentsForTaskIds(array $taskIds): array
    {
        $taskIds = $this->normalizeIntegerIds($taskIds);
        if ($taskIds === array()) {
            return array();
        }

        $params = array();
        $placeholders = $this->buildIntegerPlaceholders('attachment_task_id', $taskIds, $params);

        return $this->database->fetchAll(
            'SELECT attachments.*,'
            . ' CASE WHEN attachments.mime_type LIKE "image/%" THEN 1 ELSE 0 END AS is_image,'
            . ' users.email AS uploaded_by_email, users.first_name AS uploaded_by_first_name, users.last_name AS uploaded_by_last_name'
            . ' FROM ' . self::ATTACHMENT_TABLE . ' attachments'
            . ' LEFT JOIN users ON users.id = attachments.uploaded_by'
            . ' WHERE attachments.task_id IN (' . implode(', ', $placeholders) . ')'
            . ' ORDER BY attachments.created_at DESC, attachments.id DESC',
            $params
        );
    }

    public function createSubtask(array $data): int
    {
        return (int) $this->database->insert(self::SUBTASK_TABLE, $data);
    }

    public function subtaskById(int $subtaskId)
    {
        return $this->database->fetch(
            'SELECT * FROM ' . self::SUBTASK_TABLE . ' WHERE id = :id LIMIT 1',
            array('id' => $subtaskId)
        );
    }

    public function updateSubtask(int $subtaskId, array $data): int
    {
        return $this->database->update(self::SUBTASK_TABLE, $data, 'id = :id', array('id' => $subtaskId));
    }

    public function deleteSubtask(int $subtaskId): int
    {
        return $this->database->delete(self::SUBTASK_TABLE, 'id = :id', array('id' => $subtaskId));
    }

    public function addNote(array $data): int
    {
        return (int) $this->database->insert(self::NOTE_TABLE, $data);
    }

    public function noteById(int $noteId)
    {
        return $this->database->fetch(
            'SELECT * FROM ' . self::NOTE_TABLE . ' WHERE id = :id LIMIT 1',
            array('id' => $noteId)
        );
    }

    public function deleteNote(int $noteId): int
    {
        return $this->database->delete(self::NOTE_TABLE, 'id = :id', array('id' => $noteId));
    }

    public function addAttachment(array $data): int
    {
        return (int) $this->database->insert(self::ATTACHMENT_TABLE, $data);
    }

    public function attachmentById(int $attachmentId)
    {
        return $this->database->fetch(
            'SELECT * FROM ' . self::ATTACHMENT_TABLE . ' WHERE id = :id LIMIT 1',
            array('id' => $attachmentId)
        );
    }

    public function attachmentsForBoard(int $boardId): array
    {
        return $this->database->fetchAll(
            'SELECT attachments.*'
            . ' FROM ' . self::ATTACHMENT_TABLE . ' attachments'
            . ' INNER JOIN ' . self::TASK_TABLE . ' tasks ON tasks.id = attachments.task_id'
            . ' WHERE tasks.board_id = :board_id',
            array('board_id' => $boardId)
        );
    }

    public function deleteAttachment(int $attachmentId): int
    {
        return $this->database->delete(self::ATTACHMENT_TABLE, 'id = :id', array('id' => $attachmentId));
    }

    public function nextTaskPosition(int $boardId, string $status): int
    {
        $value = $this->database->fetchColumn(
            'SELECT COALESCE(MAX(position), 0) FROM ' . self::TASK_TABLE . ' WHERE board_id = :board_id AND status = :status',
            array(
                'board_id' => $boardId,
                'status' => $status,
            )
        );

        return max(0, (int) $value) + 1;
    }

    public function nextSubtaskPosition(int $taskId): int
    {
        $value = $this->database->fetchColumn(
            'SELECT COALESCE(MAX(position), 0) FROM ' . self::SUBTASK_TABLE . ' WHERE task_id = :task_id',
            array('task_id' => $taskId)
        );

        return max(0, (int) $value) + 1;
    }

    public function reorderTaskColumn(int $boardId, string $status, array $orderedTaskIds): void
    {
        $orderedTaskIds = $this->normalizeIntegerIds($orderedTaskIds);
        $position = 1;
        foreach ($orderedTaskIds as $taskId) {
            $this->database->update(
                self::TASK_TABLE,
                array(
                    'status' => $status,
                    'position' => $position,
                ),
                'id = :id AND board_id = :board_id',
                array(
                    'id' => $taskId,
                    'board_id' => $boardId,
                )
            );
            $position++;
        }
    }

    public function boardProgress(int $boardId): array
    {
        $rows = $this->database->fetchAll(
            'SELECT status, COUNT(*) AS total FROM ' . self::TASK_TABLE
            . ' WHERE board_id = :board_id'
            . ' GROUP BY status',
            array('board_id' => $boardId)
        );

        $result = array(
            'todo' => 0,
            'in_progress' => 0,
            'review' => 0,
            'done' => 0,
        );

        foreach ($rows as $row) {
            $status = (string) ($row['status'] ?? '');
            if (isset($result[$status])) {
                $result[$status] = (int) ($row['total'] ?? 0);
            }
        }

        return $result;
    }

    private function normalizeIntegerIds(array $ids): array
    {
        return array_values(array_unique(array_filter(array_map('intval', $ids), static function (int $value): bool {
            return $value > 0;
        })));
    }

    private function ensureBoardColumn(string $columnName, string $ddl): void
    {
        $exists = (int) $this->database->fetchColumn(
            'SELECT COUNT(*) FROM information_schema.COLUMNS'
            . ' WHERE TABLE_SCHEMA = DATABASE()'
            . ' AND TABLE_NAME = :table_name'
            . ' AND COLUMN_NAME = :column_name',
            array(
                'table_name' => self::BOARD_TABLE,
                'column_name' => $columnName,
            )
        );

        if ($exists <= 0) {
            $this->database->query($ddl);
        }
    }

    private function ensureTaskColumn(string $columnName, string $ddl): void
    {
        $exists = (int) $this->database->fetchColumn(
            'SELECT COUNT(*) FROM information_schema.COLUMNS'
            . ' WHERE TABLE_SCHEMA = DATABASE()'
            . ' AND TABLE_NAME = :table_name'
            . ' AND COLUMN_NAME = :column_name',
            array(
                'table_name' => self::TASK_TABLE,
                'column_name' => $columnName,
            )
        );

        if ($exists <= 0) {
            $this->database->query($ddl);
        }
    }

    private function buildIntegerPlaceholders(string $prefix, array $values, array &$params): array
    {
        $placeholders = array();
        foreach ($values as $index => $value) {
            $key = $prefix . '_' . $index;
            $placeholders[] = ':' . $key;
            $params[$key] = (int) $value;
        }

        return $placeholders;
    }
}
