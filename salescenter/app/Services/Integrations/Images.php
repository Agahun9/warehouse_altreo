<?php

declare(strict_types=1);
namespace App\Services\Integrations;

/** Wyszukiwanie adresu zdjęcia produktu w odpowiedziach API (także adresy względne Mirakl). */
final class Images
{
    /** Pola, które same oznaczają zdjęcie. */
    private const IMAGE_KEYS = ['image_url', 'imageUrl', 'media_url', 'mediaUrl', 'thumbnail_url', 'thumbnailUrl', 'thumbnail', 'thumbUrl', 'image', 'images', 'product_medias', 'product_media', 'media', 'medias', 'mainImage', 'primaryImage', 'primary_image_url', 'photos', 'pictures'];

    public static function first($value, string $base = '', bool $imageContext = true, int $depth = 0): string
    {
        if ($depth > 6) { return ''; }
        if (is_scalar($value)) { return $imageContext ? self::absolute((string) $value, $base) : ''; }
        if (!is_array($value)) { return ''; }
        foreach (self::IMAGE_KEYS as $key) {
            if (array_key_exists($key, $value)) {
                $url = self::first($value[$key], $base, true, $depth + 1);
                if ($url !== '') { return $url; }
            }
        }
        // „url”/„src” tylko wewnątrz pola zdjęcia (np. images[0].url), nigdy np. link do oferty.
        if ($imageContext) {
            foreach (['url', 'src', 'href'] as $key) {
                if (isset($value[$key]) && is_scalar($value[$key])) {
                    $url = self::absolute((string) $value[$key], $base);
                    if ($url !== '') { return $url; }
                }
            }
        }
        foreach (['product', 'products', 'offer', 'offers'] as $key) {
            if (isset($value[$key])) {
                $url = self::first($value[$key], $base, false, $depth + 1);
                if ($url !== '') { return $url; }
            }
        }
        if ($value !== [] && array_keys($value) === range(0, count($value) - 1)) {
            foreach ($value as $child) {
                $url = self::first($child, $base, $imageContext, $depth + 1);
                if ($url !== '') { return $url; }
            }
        }
        return '';
    }

    private static function absolute(string $url, string $base): string
    {
        $url = trim($url);
        if (strpos($url, '//') === 0) { $url = 'https:'.$url; }
        if ($url !== '' && $url[0] === '/' && $base !== '') { $url = rtrim($base, '/').$url; }
        return preg_match('#^https://[^\s"<>]+$#i', $url) ? $url : '';
    }
}
