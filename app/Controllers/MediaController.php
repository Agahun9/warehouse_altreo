<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Config;
use App\Core\Controller;
use App\Models\MediaRepository;
use RuntimeException;
use Throwable;

class MediaController extends Controller
{
    /** @var MediaRepository */
    private $media;

    public function __construct()
    {
        $this->media = new MediaRepository($this->db());
        $this->media->ensureSchema();
    }

    public function index(): void
    {
        $currentUser = $this->requireModule('media');
        $query = trim((string) $this->input('q', ''));
        $type = trim((string) $this->input('type', ''));
        $items = $this->media->all($query, $type);
        foreach ($items as $index => $item) {
            $items[$index]['public_url'] = $this->publicUrl((string) $item['relative_path']);
            $items[$index]['size_label'] = $this->fileSizeLabel((int) $item['file_size']);
            $items[$index]['base_name'] = (string) pathinfo((string) $item['file_name'], PATHINFO_FILENAME);
        }

        $this->render('media/index', array(
            'pageTitle' => 'Media',
            'contentTitle' => 'Biblioteka mediów',
            'pageDescription' => 'Zdjęcia i filmy z publicznym adresem URL.',
            'breadcrumbCurrent' => 'Media',
            'currentUser' => $currentUser,
            'items' => $items,
            'query' => $query,
            'type' => $type,
            'canWrite' => (string) ($currentUser['role'] ?? '') === 'admin'
                || (string) ($currentUser['module_permissions']['media'] ?? '') === 'edit',
        ));
    }

    public function upload(): void
    {
        $user = $this->requireModuleWrite('media');
        $this->assertPost();
        $files = $this->normalizedUploads(isset($_FILES['media_files']) ? (array) $_FILES['media_files'] : array());
        $uploaded = 0;
        $errors = array();
        foreach ($files as $file) {
            try {
                $this->storeFile($file, (int) ($user['id'] ?? 0));
                $uploaded++;
            } catch (Throwable $exception) {
                $errors[] = (string) ($file['name'] ?? 'Plik') . ': ' . $exception->getMessage();
            }
        }
        if ($uploaded > 0) {
            $this->setFlash('success', 'Dodano plików: ' . $uploaded . '.');
        }
        if ($errors !== array()) {
            $this->setFlash('error', implode(' ', $errors));
        }
        $this->redirect('./index.php?controller=media&action=index');
    }

    public function rename(): void
    {
        $this->requireModuleWrite('media');
        $this->assertPost();
        try {
            $item = $this->requiredItem((int) $this->input('id', 0));
            $oldPath = $this->absolutePath((string) $item['relative_path']);
            $extension = strtolower((string) pathinfo((string) $item['file_name'], PATHINFO_EXTENSION));
            $baseName = $this->safeBaseName((string) $this->input('name', ''));
            $newFileName = $baseName . '.' . $extension;
            $relativeDirectory = str_replace('\\', '/', (string) dirname((string) $item['relative_path']));
            $newRelativePath = $relativeDirectory . '/' . $newFileName;
            $newPath = $this->absolutePath($newRelativePath);
            if ($newPath !== $oldPath && is_file($newPath)) {
                throw new RuntimeException('Plik o tej nazwie już istnieje.');
            }
            if ($newPath !== $oldPath && !rename($oldPath, $newPath)) {
                throw new RuntimeException('Nie udało się zmienić nazwy pliku.');
            }
            $this->media->rename((int) $item['id'], $newFileName, $newRelativePath);
            $this->setFlash('success', 'Nazwa pliku została zmieniona. Publiczny URL również został zaktualizowany.');
        } catch (Throwable $exception) {
            $this->setFlash('error', $exception->getMessage());
        }
        $this->redirect('./index.php?controller=media&action=index');
    }

    public function delete(): void
    {
        $this->requireModuleWrite('media');
        $this->assertPost();
        try {
            $item = $this->requiredItem((int) $this->input('id', 0));
            $path = $this->absolutePath((string) $item['relative_path']);
            if (is_file($path) && !unlink($path)) {
                throw new RuntimeException('Nie udało się usunąć pliku.');
            }
            $this->media->delete((int) $item['id']);
            $this->setFlash('success', 'Plik został usunięty.');
        } catch (Throwable $exception) {
            $this->setFlash('error', $exception->getMessage());
        }
        $this->redirect('./index.php?controller=media&action=index');
    }

    private function storeFile(array $file, int $userId): void
    {
        if ((int) ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            throw new RuntimeException('Błąd przesyłania pliku.');
        }
        $tmp = (string) ($file['tmp_name'] ?? '');
        if ($tmp === '' || !is_uploaded_file($tmp)) {
            throw new RuntimeException('Nieprawidłowy plik tymczasowy.');
        }
        $size = (int) ($file['size'] ?? 0);
        if ($size <= 0 || $size > 250 * 1024 * 1024) {
            throw new RuntimeException('Plik musi mieć od 1 B do 250 MB.');
        }
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime = $finfo ? (string) finfo_file($finfo, $tmp) : '';
        if ($finfo) {
            finfo_close($finfo);
        }
        $allowed = array(
            'image/jpeg' => array('jpg', 'image'),
            'image/png' => array('png', 'image'),
            'image/webp' => array('webp', 'image'),
            'image/gif' => array('gif', 'image'),
            'image/avif' => array('avif', 'image'),
            'video/mp4' => array('mp4', 'video'),
            'video/webm' => array('webm', 'video'),
            'video/quicktime' => array('mov', 'video'),
            'video/x-matroska' => array('mkv', 'video'),
        );
        if (!isset($allowed[$mime])) {
            throw new RuntimeException('Dozwolone są zdjęcia oraz filmy MP4, WebM, MOV i MKV.');
        }
        $directory = 'uploads/media/' . date('Y/m');
        $absoluteDirectory = BASE_PATH . '/' . $directory;
        if (!is_dir($absoluteDirectory) && !mkdir($absoluteDirectory, 0775, true) && !is_dir($absoluteDirectory)) {
            throw new RuntimeException('Nie udało się utworzyć katalogu mediów.');
        }
        $original = trim((string) ($file['name'] ?? 'media'));
        $baseName = $this->safeBaseName((string) pathinfo($original, PATHINFO_FILENAME));
        $fileName = $this->uniqueFileName($absoluteDirectory, $baseName, $allowed[$mime][0]);
        $relativePath = $directory . '/' . $fileName;
        if (!move_uploaded_file($tmp, $absoluteDirectory . '/' . $fileName)) {
            throw new RuntimeException('Nie udało się zapisać pliku.');
        }
        $this->media->create(array(
            'file_name' => $fileName,
            'original_name' => $original,
            'relative_path' => $relativePath,
            'mime_type' => $mime,
            'media_type' => $allowed[$mime][1],
            'file_size' => $size,
            'uploaded_by' => $userId > 0 ? $userId : null,
        ));
    }

    private function normalizedUploads(array $files): array
    {
        $result = array();
        $names = isset($files['name']) && is_array($files['name']) ? $files['name'] : array();
        foreach ($names as $index => $name) {
            $result[] = array(
                'name' => $name,
                'type' => $files['type'][$index] ?? '',
                'tmp_name' => $files['tmp_name'][$index] ?? '',
                'error' => $files['error'][$index] ?? UPLOAD_ERR_NO_FILE,
                'size' => $files['size'][$index] ?? 0,
            );
        }
        return $result;
    }

    private function safeBaseName(string $name): string
    {
        $name = trim((string) pathinfo($name, PATHINFO_FILENAME));
        $ascii = function_exists('iconv') ? @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $name) : $name;
        $name = is_string($ascii) ? $ascii : $name;
        $name = strtolower(trim((string) preg_replace('/[^A-Za-z0-9_-]+/', '-', $name), '-_'));
        if ($name === '') {
            throw new RuntimeException('Podaj prawidłową nazwę pliku.');
        }
        return substr($name, 0, 180);
    }

    private function uniqueFileName(string $directory, string $baseName, string $extension): string
    {
        $candidate = $baseName . '.' . $extension;
        $index = 2;
        while (is_file($directory . '/' . $candidate)) {
            $candidate = $baseName . '-' . $index . '.' . $extension;
            $index++;
        }
        return $candidate;
    }

    private function requiredItem(int $id): array
    {
        $item = $this->media->find($id);
        if (!$item) {
            throw new RuntimeException('Nie znaleziono pliku.');
        }
        return $item;
    }

    private function absolutePath(string $relativePath): string
    {
        $relativePath = ltrim(str_replace('\\', '/', $relativePath), '/');
        if (strpos($relativePath, 'uploads/media/') !== 0 || strpos($relativePath, '..') !== false) {
            throw new RuntimeException('Nieprawidłowa ścieżka pliku.');
        }
        return BASE_PATH . '/' . $relativePath;
    }

    private function publicUrl(string $relativePath): string
    {
        $config = Config::get('app');
        $base = preg_replace('#/index\.php(?:\?.*)?$#', '', rtrim((string) ($config['public_base_url'] ?? ''), '/'));
        $segments = array_map('rawurlencode', explode('/', ltrim(str_replace('\\', '/', $relativePath), '/')));
        return rtrim((string) $base, '/') . '/' . implode('/', $segments);
    }

    private function fileSizeLabel(int $bytes): string
    {
        if ($bytes >= 1024 * 1024 * 1024) return round($bytes / (1024 * 1024 * 1024), 2) . ' GB';
        if ($bytes >= 1024 * 1024) return round($bytes / (1024 * 1024), 2) . ' MB';
        if ($bytes >= 1024) return round($bytes / 1024, 1) . ' KB';
        return $bytes . ' B';
    }

    private function assertPost(): void
    {
        if (!$this->isPost()) {
            $this->redirect('./index.php?controller=media&action=index');
        }
    }
}
