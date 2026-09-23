<?php

declare(strict_types=1);
namespace App\Services\Messages;

use App\Core\Tenant;

/**
 * Tymczasowy log diagnostyczny centrum komunikacji Morele: surowe żądania, odpowiedzi API
 * i decyzje synchronizacji. Służy do ustalenia prawdziwych nazw pól, których Morele nie dokumentuje.
 * Nagłówki (a z nimi token) nie są zapisywane. Plik jest przycinany do ostatnich 512 KB.
 */
final class MoreleLog
{
    private const MAX_BYTES = 524288;
    private const MAX_ENTRY = 20000;

    public static function path(): string
    {
        return BASE_PATH.'/app/Storage/logs/morele-messages-t'.Tenant::id().'.log';
    }

    /** @param mixed $data */
    public static function write(string $event, $data = null): void
    {
        try {
            $directory = dirname(self::path());
            if (!is_dir($directory) && !mkdir($directory, 0775, true) && !is_dir($directory)) { return; }
            $payload = $data === null ? '' : (string) json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PARTIAL_OUTPUT_ON_ERROR);
            $line = gmdate('Y-m-d H:i:s').' UTC  '.$event.($payload !== '' ? '  '.mb_substr($payload, 0, self::MAX_ENTRY, 'UTF-8') : '')."\n";
            file_put_contents(self::path(), $line, FILE_APPEND | LOCK_EX);
            self::trim();
        } catch (\Throwable $e) { /* Log diagnostyczny nigdy nie przerywa synchronizacji. */ }
    }

    /** Ostatnie $bytes bajtów logu (od pełnej linii). */
    public static function tail(int $bytes = 200000): string
    {
        $path = self::path();
        if (!is_file($path)) { return ''; }
        $size = (int) filesize($path);
        $content = (string) file_get_contents($path, false, null, max(0, $size - $bytes));
        return $size > $bytes ? (string) preg_replace('/^[^\n]*\n/', '', $content) : $content;
    }

    public static function size(): int
    {
        return is_file(self::path()) ? (int) filesize(self::path()) : 0;
    }

    public static function clear(): void
    {
        if (is_file(self::path())) { @unlink(self::path()); }
    }

    /** Zostawia ostatnią połowę pliku, gdy przekroczy limit. */
    private static function trim(): void
    {
        $path = self::path();
        if (!is_file($path) || (int) filesize($path) <= self::MAX_BYTES) { return; }
        $keep = (string) file_get_contents($path, false, null, (int) filesize($path) - (int) (self::MAX_BYTES / 2));
        file_put_contents($path, (string) preg_replace('/^[^\n]*\n/', '', $keep), LOCK_EX);
    }
}
