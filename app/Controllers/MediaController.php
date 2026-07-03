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
        $currentFolder = $this->normalizeMediaFolder((string) $this->input('folder', ''));
        $items = array_values(array_filter($this->media->all($query, $type), function (array $item) use ($currentFolder): bool {
            return $this->mediaItemFolder((string) ($item['relative_path'] ?? '')) === $currentFolder;
        }));
        $folderMap = array();
        $allFolderPaths = array();
        foreach ($this->media->all('', '') as $item) {
            $itemFolder = $this->mediaItemFolder((string) ($item['relative_path'] ?? ''));
            $ancestor = $itemFolder;
            while ($ancestor !== '') {
                $allFolderPaths[$ancestor] = true;
                $ancestor = $this->parentMediaFolder($ancestor);
            }
            $childFolder = $this->directChildFolder($currentFolder, $itemFolder);
            if ($childFolder === null) {
                continue;
            }
            if (!isset($folderMap[$childFolder])) {
                $folderMap[$childFolder] = array(
                    'name' => basename($childFolder),
                    'path' => $childFolder,
                    'file_count' => 0,
                );
            }
            $folderMap[$childFolder]['file_count']++;
        }
        foreach ($this->media->allFolders() as $folder) {
            $path = $this->normalizeMediaFolder((string) ($folder['path'] ?? ''));
            if ($path === '') {
                continue;
            }
            $allFolderPaths[$path] = true;
            $childFolder = $this->directChildFolder($currentFolder, $path);
            if ($childFolder !== null && !isset($folderMap[$childFolder])) {
                $folderMap[$childFolder] = array(
                    'name' => basename($childFolder),
                    'path' => $childFolder,
                    'file_count' => 0,
                );
            }
        }
        $folderOptions = array_keys($allFolderPaths);
        natcasesort($folderOptions);
        $targetFolderOptions = array_values(array_filter($folderOptions, static function (string $path) use ($currentFolder): bool {
            return $currentFolder === '' || ($path !== $currentFolder && strpos($path, $currentFolder . '/') !== 0);
        }));
        uasort($folderMap, static function (array $left, array $right): int {
            return strnatcasecmp((string) $left['name'], (string) $right['name']);
        });
        foreach ($items as $index => $item) {
            $items[$index]['public_url'] = $this->publicUrl((string) $item['relative_path']);
            $items[$index]['size_label'] = $this->fileSizeLabel((int) $item['file_size']);
            $items[$index]['base_name'] = (string) pathinfo((string) $item['file_name'], PATHINFO_FILENAME);
        }
        $errorMessage = trim((string) ($this->getFlash('error') ?? ''));

        $this->render('media/index', array(
            'pageTitle' => 'Media',
            'contentTitle' => 'Biblioteka mediów',
            'pageDescription' => 'Zdjęcia i filmy z publicznym adresem URL.',
            'breadcrumbCurrent' => 'Media',
            'currentUser' => $currentUser,
            'success' => $this->getFlash('success') ?? '',
            'errors' => $errorMessage !== '' ? array($errorMessage) : array(),
            'items' => $items,
            'folders' => array_values($folderMap),
            'currentFolder' => $currentFolder,
            'currentFolderName' => $currentFolder !== '' ? basename($currentFolder) : '',
            'parentFolder' => $currentFolder !== '' ? $this->parentMediaFolder($currentFolder) : '',
            'folderOptions' => array_values($folderOptions),
            'targetFolderOptions' => $targetFolderOptions,
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
        $folderMode = (string) $this->input('upload_mode', '') === 'folder';
        $targetFolder = $this->normalizeMediaFolder((string) $this->input('target_folder', ''));
        $uploadField = $folderMode ? 'folder_files' : 'media_files';
        $folderPaths = $folderMode ? (array) $this->input('folder_paths', array()) : array();
        $files = $this->normalizedUploads(
            isset($_FILES[$uploadField]) ? (array) $_FILES[$uploadField] : array(),
            $folderPaths
        );
        $uploaded = 0;
        $errors = array();
        foreach ($files as $file) {
            try {
                $this->storeFile($file, (int) ($user['id'] ?? 0), $folderMode, $targetFolder);
                $uploaded++;
            } catch (Throwable $exception) {
                $errors[] = (string) ($file['relative_path'] ?? $file['name'] ?? 'Plik') . ': ' . $exception->getMessage();
            }
        }
        if ((string) $this->input('format', '') === 'json') {
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(array(
                'uploaded' => $uploaded,
                'errors' => $errors,
            ), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            return;
        }
        if ($uploaded > 0) {
            $this->setFlash('success', 'Dodano plików: ' . $uploaded . '.');
        }
        if ($errors !== array()) {
            $this->setFlash('error', implode(' ', $errors));
        }
        $this->redirect('./index.php?controller=media&action=index' . ($targetFolder !== '' ? '&folder=' . rawurlencode($targetFolder) : ''));
    }

    public function rename(): void
    {
        $this->requireModuleWrite('media');
        $this->assertPost();
        $targetFolder = $this->normalizeMediaFolder((string) $this->input('folder', ''));
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
        $this->redirect('./index.php?controller=media&action=index' . ($targetFolder !== '' ? '&folder=' . rawurlencode($targetFolder) : ''));
    }

    public function delete(): void
    {
        $this->requireModuleWrite('media');
        $this->assertPost();
        $targetFolder = $this->normalizeMediaFolder((string) $this->input('folder', ''));
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
        $this->redirect('./index.php?controller=media&action=index' . ($targetFolder !== '' ? '&folder=' . rawurlencode($targetFolder) : ''));
    }

    public function createfolder(): void
    {
        $this->requireModuleWrite('media');
        $this->assertPost();
        $parent = $this->normalizeMediaFolder((string) $this->input('folder', ''));
        try {
            $name = $this->safeFolderName((string) $this->input('name', ''));
            $path = $parent !== '' ? $parent . '/' . $name : $name;
            $absolutePath = $this->absoluteFolderPath($path);
            if (is_dir($absolutePath)) {
                throw new RuntimeException('Folder o tej nazwie już istnieje.');
            }
            if (!mkdir($absolutePath, 0775, true) && !is_dir($absolutePath)) {
                throw new RuntimeException('Nie udało się utworzyć folderu.');
            }
            $this->media->createFolder($path);
            $this->setFlash('success', 'Folder został utworzony.');
        } catch (Throwable $exception) {
            $this->setFlash('error', $exception->getMessage());
        }
        $this->redirectToMediaFolder($parent);
    }

    public function renamefolder(): void
    {
        $this->requireModuleWrite('media');
        $this->assertPost();
        $source = $this->normalizeMediaFolder((string) $this->input('folder', ''));
        $parent = $this->parentMediaFolder($source);
        try {
            if ($source === '') {
                throw new RuntimeException('Nie można zmienić nazwy katalogu głównego.');
            }
            $name = $this->safeFolderName((string) $this->input('name', ''));
            $destination = $parent !== '' ? $parent . '/' . $name : $name;
            $this->moveMediaFolder($source, $destination);
            $this->setFlash('success', 'Nazwa folderu została zmieniona.');
            $this->redirectToMediaFolder($destination);
        } catch (Throwable $exception) {
            $this->setFlash('error', $exception->getMessage());
        }
        $this->redirectToMediaFolder($source);
    }

    public function movefolder(): void
    {
        $this->requireModuleWrite('media');
        $this->assertPost();
        $source = $this->normalizeMediaFolder((string) $this->input('folder', ''));
        try {
            $targetParent = $this->normalizeMediaFolder((string) $this->input('target_parent', ''));
            if ($source === '' || $targetParent === $source || strpos($targetParent, $source . '/') === 0) {
                throw new RuntimeException('Nie można przenieść folderu do niego samego ani jego podfolderu.');
            }
            $destination = ($targetParent !== '' ? $targetParent . '/' : '') . basename($source);
            $this->moveMediaFolder($source, $destination);
            $this->setFlash('success', 'Folder został przeniesiony.');
            $this->redirectToMediaFolder($destination);
        } catch (Throwable $exception) {
            $this->setFlash('error', $exception->getMessage());
        }
        $this->redirectToMediaFolder($source);
    }

    public function copyfolder(): void
    {
        $user = $this->requireModuleWrite('media');
        $this->assertPost();
        $source = $this->normalizeMediaFolder((string) $this->input('folder', ''));
        try {
            if ($source === '') {
                throw new RuntimeException('Nie można kopiować katalogu głównego.');
            }
            $targetParent = $this->normalizeMediaFolder((string) $this->input('target_parent', ''));
            $requestedName = trim((string) $this->input('name', ''));
            $name = $requestedName !== '' ? $this->safeFolderName($requestedName) : basename($source) . '-kopia';
            $destination = ($targetParent !== '' ? $targetParent . '/' : '') . $name;
            if ($destination === $source || strpos($destination, $source . '/') === 0) {
                throw new RuntimeException('Nie można kopiować folderu do jego wnętrza.');
            }
            $sourcePath = $this->absoluteFolderPath($source);
            $destinationPath = $this->absoluteFolderPath($destination);
            if (!is_dir($sourcePath)) {
                throw new RuntimeException('Folder źródłowy nie istnieje.');
            }
            if (file_exists($destinationPath)) {
                throw new RuntimeException('Folder docelowy już istnieje.');
            }
            $this->copyDirectory($sourcePath, $destinationPath);
            $this->media->copyFolderMedia($source, $destination, (int) ($user['id'] ?? 0) ?: null);
            $this->setFlash('success', 'Folder został skopiowany.');
        } catch (Throwable $exception) {
            $this->setFlash('error', $exception->getMessage());
        }
        $this->redirectToMediaFolder($source);
    }

    public function deletefolder(): void
    {
        $this->requireModuleWrite('media');
        $this->assertPost();
        $source = $this->normalizeMediaFolder((string) $this->input('folder', ''));
        $parent = $this->parentMediaFolder($source);
        try {
            if ($source === '') {
                throw new RuntimeException('Nie można usunąć katalogu głównego.');
            }
            $this->deleteDirectory($this->absoluteFolderPath($source));
            $this->media->deleteFolderTree($source);
            $this->setFlash('success', 'Folder i cała jego zawartość zostały usunięte.');
        } catch (Throwable $exception) {
            $this->setFlash('error', $exception->getMessage());
        }
        $this->redirectToMediaFolder($parent);
    }

    private function storeFile(array $file, int $userId, bool $folderMode = false, string $targetFolder = ''): void
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
        $directory = $folderMode
            ? $this->folderUploadDirectory((string) ($file['relative_path'] ?? ''), $targetFolder)
            : ($targetFolder !== '' ? 'uploads/media/' . $targetFolder : 'uploads/media/' . date('Y/m'));
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
        $folderPath = substr($directory, strlen('uploads/media/'));
        if ($folderPath !== false && $folderPath !== '') {
            $this->media->createFolder($folderPath);
        }
    }

    private function normalizedUploads(array $files, array $relativePaths = array()): array
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
                'relative_path' => (string) ($relativePaths[$index] ?? $files['full_path'][$index] ?? ''),
            );
        }
        return $result;
    }

    private function folderUploadDirectory(string $relativePath, string $targetFolder = ''): string
    {
        $relativePath = trim(str_replace('\\', '/', $relativePath), '/');
        $segments = array_values(array_filter(explode('/', $relativePath), static function (string $segment): bool {
            return trim($segment) !== '';
        }));
        if (count($segments) < 2) {
            throw new RuntimeException('Nie udało się odczytać nazwy wybranego folderu.');
        }

        array_pop($segments);
        $safeSegments = array();
        foreach ($segments as $segment) {
            if ($segment === '.' || $segment === '..') {
                throw new RuntimeException('Nieprawidłowa ścieżka folderu.');
            }
            $safeSegments[] = $this->safeFolderName($segment);
        }

        return 'uploads/media/' . ($targetFolder !== '' ? $targetFolder . '/' : '') . implode('/', $safeSegments);
    }

    private function normalizeMediaFolder(string $folder): string
    {
        $folder = trim(str_replace('\\', '/', $folder), '/');
        if ($folder === '') {
            return '';
        }
        $segments = explode('/', $folder);
        foreach ($segments as $segment) {
            if ($segment === '' || $segment === '.' || $segment === '..' || preg_match('/^[A-Za-z0-9_-]+$/', $segment) !== 1) {
                throw new RuntimeException('Nieprawidłowa ścieżka folderu mediów.');
            }
        }
        return implode('/', $segments);
    }

    private function safeFolderName(string $name): string
    {
        $name = trim($name);
        $ascii = function_exists('iconv') ? @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $name) : $name;
        $name = is_string($ascii) ? $ascii : $name;
        $name = trim((string) preg_replace('/[^A-Za-z0-9_-]+/', '-', $name), '-_');
        if ($name === '') {
            throw new RuntimeException('Podaj prawidłową nazwę folderu.');
        }
        return substr($name, 0, 180);
    }

    private function mediaItemFolder(string $relativePath): string
    {
        $relativePath = ltrim(str_replace('\\', '/', $relativePath), '/');
        if (strpos($relativePath, 'uploads/media/') !== 0) {
            return '';
        }
        $insideRoot = substr($relativePath, strlen('uploads/media/'));
        $directory = str_replace('\\', '/', dirname($insideRoot));
        return $directory === '.' ? '' : trim($directory, '/');
    }

    private function directChildFolder(string $currentFolder, string $itemFolder): ?string
    {
        if ($currentFolder === '') {
            if ($itemFolder === '') {
                return null;
            }
            $firstSegment = explode('/', $itemFolder)[0] ?? '';
            return $firstSegment !== '' ? $firstSegment : null;
        }

        $prefix = $currentFolder !== '' ? $currentFolder . '/' : '';
        if ($itemFolder === $currentFolder || strpos($itemFolder, $prefix) !== 0) {
            return null;
        }
        $remainder = substr($itemFolder, strlen($prefix));
        $firstSegment = explode('/', $remainder)[0] ?? '';
        return $firstSegment !== '' ? $prefix . $firstSegment : null;
    }

    private function parentMediaFolder(string $folder): string
    {
        $parent = str_replace('\\', '/', dirname($folder));
        return $parent === '.' ? '' : trim($parent, '/');
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

    private function absoluteFolderPath(string $folder): string
    {
        $folder = $this->normalizeMediaFolder($folder);
        if ($folder === '') {
            throw new RuntimeException('Nie można wykonać tej operacji na katalogu głównym.');
        }
        return BASE_PATH . '/uploads/media/' . $folder;
    }

    private function moveMediaFolder(string $source, string $destination): void
    {
        if ($source === $destination) {
            return;
        }
        $sourcePath = $this->absoluteFolderPath($source);
        $destinationPath = $this->absoluteFolderPath($destination);
        if (!is_dir($sourcePath)) {
            throw new RuntimeException('Folder źródłowy nie istnieje.');
        }
        if (file_exists($destinationPath)) {
            throw new RuntimeException('Folder docelowy już istnieje.');
        }
        $destinationParent = dirname($destinationPath);
        if (!is_dir($destinationParent) && !mkdir($destinationParent, 0775, true) && !is_dir($destinationParent)) {
            throw new RuntimeException('Nie udało się utworzyć folderu docelowego.');
        }
        if (!rename($sourcePath, $destinationPath)) {
            throw new RuntimeException('Nie udało się przenieść folderu.');
        }
        $this->media->renameFolderTree($source, $destination);
    }

    private function copyDirectory(string $source, string $destination): void
    {
        if (!mkdir($destination, 0775, true) && !is_dir($destination)) {
            throw new RuntimeException('Nie udało się utworzyć kopii folderu.');
        }
        $entries = scandir($source);
        if (!is_array($entries)) {
            throw new RuntimeException('Nie udało się odczytać folderu źródłowego.');
        }
        foreach ($entries as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }
            $sourceEntry = $source . DIRECTORY_SEPARATOR . $entry;
            $destinationEntry = $destination . DIRECTORY_SEPARATOR . $entry;
            if (is_link($sourceEntry)) {
                continue;
            }
            if (is_dir($sourceEntry)) {
                $this->copyDirectory($sourceEntry, $destinationEntry);
            } elseif (!copy($sourceEntry, $destinationEntry)) {
                throw new RuntimeException('Nie udało się skopiować pliku: ' . $entry);
            }
        }
    }

    private function deleteDirectory(string $directory): void
    {
        if (!file_exists($directory)) {
            return;
        }
        $entries = scandir($directory);
        if (!is_array($entries)) {
            throw new RuntimeException('Nie udało się odczytać folderu.');
        }
        foreach ($entries as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }
            $path = $directory . DIRECTORY_SEPARATOR . $entry;
            if (is_dir($path) && !is_link($path)) {
                $this->deleteDirectory($path);
            } elseif (!unlink($path)) {
                throw new RuntimeException('Nie udało się usunąć pliku: ' . $entry);
            }
        }
        if (!rmdir($directory)) {
            throw new RuntimeException('Nie udało się usunąć folderu.');
        }
    }

    private function redirectToMediaFolder(string $folder): void
    {
        $this->redirect('./index.php?controller=media&action=index' . ($folder !== '' ? '&folder=' . rawurlencode($folder) : ''));
    }

    private function assertPost(): void
    {
        if (!$this->isPost()) {
            $this->redirect('./index.php?controller=media&action=index');
        }
    }
}
