<?php

declare(strict_types=1);

namespace App\Core;

final class PerformanceLogger
{
    public static function start(): float
    {
        return microtime(true);
    }

    public static function logIfSlow(string $name, float $startedAt, array $context = array(), float $thresholdMs = 1000.0): void
    {
        $durationMs = (microtime(true) - $startedAt) * 1000;
        if ($durationMs < $thresholdMs) {
            return;
        }

        self::log($name, $durationMs, $context, 'WARNING');
    }

    public static function log(string $name, float $durationMs, array $context = array(), string $level = 'INFO'): void
    {
        if (!function_exists('app_log')) {
            return;
        }

        $payload = array_merge(
            array(
                'operation' => $name,
                'duration_ms' => round($durationMs, 2),
            ),
            self::normalizeContext($context)
        );

        app_log('PERF ' . json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), $level);
    }

    private static function normalizeContext(array $context): array
    {
        $normalized = array();

        foreach ($context as $key => $value) {
            if (is_scalar($value) || $value === null) {
                $normalized[(string) $key] = $value;
                continue;
            }

            if (is_array($value)) {
                $normalized[(string) $key] = self::normalizeArray($value);
                continue;
            }

            $normalized[(string) $key] = gettype($value);
        }

        return $normalized;
    }

    private static function normalizeArray(array $value): array
    {
        $result = array();
        $maxItems = 10;
        $index = 0;

        foreach ($value as $key => $item) {
            if ($index >= $maxItems) {
                $result['truncated'] = true;
                break;
            }

            if (is_scalar($item) || $item === null) {
                $result[(string) $key] = $item;
            } elseif (is_array($item)) {
                $result[(string) $key] = '[array:' . count($item) . ']';
            } else {
                $result[(string) $key] = '[' . gettype($item) . ']';
            }

            $index++;
        }

        return $result;
    }
}
