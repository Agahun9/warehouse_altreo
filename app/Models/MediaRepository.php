<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

class MediaRepository
{
    /** @var Database */
    private $database;

    public function __construct(Database $database)
    {
        $this->database = $database;
    }

    public function ensureSchema(): void
    {
        $this->database->query(
            "CREATE TABLE IF NOT EXISTS media_library (\n"
            . "id INT UNSIGNED NOT NULL AUTO_INCREMENT,\n"
            . "file_name VARCHAR(255) NOT NULL,\n"
            . "original_name VARCHAR(255) NOT NULL,\n"
            . "relative_path VARCHAR(500) NOT NULL,\n"
            . "mime_type VARCHAR(120) NOT NULL,\n"
            . "media_type VARCHAR(20) NOT NULL,\n"
            . "file_size BIGINT UNSIGNED NOT NULL DEFAULT 0,\n"
            . "uploaded_by INT UNSIGNED DEFAULT NULL,\n"
            . "created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,\n"
            . "updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,\n"
            . "PRIMARY KEY (id),\n"
            . "UNIQUE KEY ux_media_relative_path (relative_path),\n"
            . "KEY idx_media_type (media_type),\n"
            . "KEY idx_media_created_at (created_at)\n"
            . ") ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
        $this->database->query(
            "CREATE TABLE IF NOT EXISTS media_folders (\n"
            . "id INT UNSIGNED NOT NULL AUTO_INCREMENT,\n"
            . "path VARCHAR(500) NOT NULL,\n"
            . "created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,\n"
            . "PRIMARY KEY (id),\n"
            . "UNIQUE KEY ux_media_folder_path (path)\n"
            . ") ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
    }

    public function all(string $query = '', string $type = ''): array
    {
        $where = array('1 = 1');
        $params = array();
        if ($query !== '') {
            $where[] = '(file_name LIKE :query OR original_name LIKE :query)';
            $params['query'] = '%' . $query . '%';
        }
        if (in_array($type, array('image', 'video'), true)) {
            $where[] = 'media_type = :media_type';
            $params['media_type'] = $type;
        }
        return $this->database->fetchAll(
            'SELECT * FROM media_library WHERE ' . implode(' AND ', $where) . ' ORDER BY created_at DESC, id DESC',
            $params
        );
    }

    public function find(int $id): ?array
    {
        $row = $this->database->fetch('SELECT * FROM media_library WHERE id = :id LIMIT 1', array('id' => $id));
        return is_array($row) ? $row : null;
    }

    public function create(array $data): int
    {
        return (int) $this->database->insert('media_library', $data);
    }

    public function rename(int $id, string $fileName, string $relativePath): void
    {
        $this->database->update('media_library', array(
            'file_name' => $fileName,
            'relative_path' => $relativePath,
        ), 'id = :id', array('id' => $id));
    }

    public function delete(int $id): void
    {
        $this->database->delete('media_library', 'id = :id', array('id' => $id));
    }

    public function allFolders(): array
    {
        return $this->database->fetchAll('SELECT * FROM media_folders ORDER BY path ASC');
    }

    public function createFolder(string $path): void
    {
        if ($path === '') {
            return;
        }
        $exists = (int) $this->database->fetchColumn(
            'SELECT COUNT(*) FROM media_folders WHERE path = :path',
            array('path' => $path)
        );
        if ($exists === 0) {
            $this->database->insert('media_folders', array('path' => $path));
        }
    }

    public function renameFolderTree(string $oldPath, string $newPath): void
    {
        $oldFilePrefix = 'uploads/media/' . $oldPath . '/';
        $newFilePrefix = 'uploads/media/' . $newPath . '/';
        foreach ($this->all('', '') as $item) {
            $relativePath = (string) ($item['relative_path'] ?? '');
            if (strpos($relativePath, $oldFilePrefix) !== 0) {
                continue;
            }
            $this->database->update('media_library', array(
                'relative_path' => $newFilePrefix . substr($relativePath, strlen($oldFilePrefix)),
            ), 'id = :id', array('id' => (int) $item['id']));
        }
        foreach ($this->allFolders() as $folder) {
            $path = (string) ($folder['path'] ?? '');
            if ($path !== $oldPath && strpos($path, $oldPath . '/') !== 0) {
                continue;
            }
            $replacement = $newPath . substr($path, strlen($oldPath));
            $this->database->update('media_folders', array('path' => $replacement), 'id = :id', array('id' => (int) $folder['id']));
        }
        $this->createFolder($newPath);
    }

    public function deleteFolderTree(string $path): void
    {
        $filePrefix = 'uploads/media/' . $path . '/';
        foreach ($this->all('', '') as $item) {
            if (strpos((string) ($item['relative_path'] ?? ''), $filePrefix) === 0) {
                $this->delete((int) $item['id']);
            }
        }
        foreach ($this->allFolders() as $folder) {
            $folderPath = (string) ($folder['path'] ?? '');
            if ($folderPath === $path || strpos($folderPath, $path . '/') === 0) {
                $this->database->delete('media_folders', 'id = :id', array('id' => (int) $folder['id']));
            }
        }
    }

    public function copyFolderMedia(string $oldPath, string $newPath, ?int $userId): void
    {
        $oldPrefix = 'uploads/media/' . $oldPath . '/';
        $newPrefix = 'uploads/media/' . $newPath . '/';
        foreach ($this->all('', '') as $item) {
            $relativePath = (string) ($item['relative_path'] ?? '');
            if (strpos($relativePath, $oldPrefix) !== 0) {
                continue;
            }
            $copy = $item;
            unset($copy['id'], $copy['created_at'], $copy['updated_at']);
            $copy['relative_path'] = $newPrefix . substr($relativePath, strlen($oldPrefix));
            $copy['uploaded_by'] = $userId;
            $this->create($copy);
        }
        foreach ($this->allFolders() as $folder) {
            $folderPath = (string) ($folder['path'] ?? '');
            if ($folderPath === $oldPath || strpos($folderPath, $oldPath . '/') === 0) {
                $this->createFolder($newPath . substr($folderPath, strlen($oldPath)));
            }
        }
        $this->createFolder($newPath);
    }
}
