<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Models\SettingRepository;
use RuntimeException;

class TemuService
{
    private const DEFAULT_API_URL = 'https://openapi-b-eu.temu.com/openapi/router';

    /** @var SettingRepository */
    private $settings;

    public function __construct()
    {
        $this->settings = new SettingRepository(Database::instance());
        $this->settings->ensureSchema();
    }

    public function connectionSettings(): array
    {
        $apiUrl = trim($this->settings->get('temu_api_url', ''));
        if ($apiUrl === '') {
            $apiUrl = self::DEFAULT_API_URL;
        }
        return array(
            'api_url' => $apiUrl,
            'app_key' => $this->settings->get('temu_app_key', ''),
            'app_secret' => $this->settings->get('temu_app_secret', ''),
            // Backwards compatibility for partner/ISV applications. A seller's
            // self-developed application can be configured without this field.
            'access_token' => $this->settings->get('temu_access_token', ''),
            'shop_id' => $this->settings->get('temu_shop_id', ''),
            'region' => $this->settings->get('temu_region', 'PL'),
        );
    }

    public function saveConnectionSettings(array $input): array
    {
        $apiUrl = rtrim(trim((string) ($input['api_url'] ?? self::DEFAULT_API_URL)), '/');
        $appKey = trim((string) ($input['app_key'] ?? ''));
        $appSecret = trim((string) ($input['app_secret'] ?? ''));
        if ($appSecret === '' && $appKey !== '') {
            $appSecret = trim($this->settings->get('temu_app_secret', ''));
        }
        $accessToken = trim((string) ($input['access_token'] ?? ''));
        if ($accessToken === '' && $appKey !== '') {
            $accessToken = trim($this->settings->get('temu_access_token', ''));
        }
        $shopId = trim((string) ($input['shop_id'] ?? ''));
        $region = strtoupper(trim((string) ($input['region'] ?? 'PL')));

        if ($apiUrl === '') {
            $apiUrl = self::DEFAULT_API_URL;
        }
        if (filter_var($apiUrl, FILTER_VALIDATE_URL) === false) {
            throw new RuntimeException('Adres API Temu musi byc poprawnym URL.');
        }
        if (($appKey !== '' && $appSecret === '') || ($appKey === '' && $appSecret !== '')) {
            throw new RuntimeException('Dla Temu uzupelnij jednoczesnie App Key i App Secret.');
        }
        if ($shopId !== '' && preg_match('/^[A-Za-z0-9\-_]+$/', $shopId) !== 1) {
            throw new RuntimeException('Shop ID Temu moze zawierac tylko litery, cyfry, myslnik i podkreslenie.');
        }
        if ($region === '') {
            $region = 'PL';
        }

        $this->settings->set('temu_api_url', $apiUrl);
        $this->settings->set('temu_app_key', $appKey);
        $this->settings->set('temu_app_secret', $appSecret);
        $this->settings->set('temu_access_token', $accessToken);
        $this->settings->set('temu_shop_id', $shopId);
        $this->settings->set('temu_region', $region);

        return $this->connectionSettings();
    }

    public function testConnection(): array
    {
        $payload = $this->request('bg.local.goods.cats.get', array('parentCatId' => 0));
        return array(
            'success' => true,
            'request_id' => (string) ($payload['requestId'] ?? ''),
            'categories_count' => count($this->categoryListFromPayload($payload)),
        );
    }

    public function searchCategories(string $search): array
    {
        $search = trim($search);
        if ($search === '') {
            return array();
        }

        $payload = $this->request('bg.local.goods.category.recommend', array('goodsName' => $search));
        $candidates = array();
        $this->collectCategoryCandidates($payload['result'] ?? array(), $candidates);

        $items = array();
        foreach ($candidates as $candidate) {
            $item = $this->normalizeCategory($candidate);
            if ($item !== null) {
                $items[$item['id']] = $item;
            }
        }

        // The recommendation API normally returns only catId and catIdList, without
        // category names. When catIdList represents the navigation chain, resolve its
        // labels through bg.local.goods.cats.get. Even if that lookup is unavailable,
        // the recommended ID remains selectable and its template can still be loaded.
        $resolved = null;
        try {
            $resolved = $this->resolveRecommendedCategoryPath(
                isset($payload['result']) && is_array($payload['result']) ? $payload['result'] : array()
            );
            if ($resolved !== null) {
                $resolved['recommended'] = true;
                $resolved['match_kind'] = 'best';
                $items[$resolved['id']] = $resolved;
                $this->cacheCategory($resolved);
            }
        } catch (RuntimeException $exception) {
            // Keep the ID-only recommendation returned by Temu.
        }

        if ($resolved === null) {
            $recommendedId = trim((string) ($payload['result']['catId'] ?? $payload['result']['categoryId'] ?? ''));
            if ($recommendedId === '' && $items !== array()) {
                $recommendedId = (string) array_key_first($items);
            }
            if ($recommendedId !== '' && ctype_digit($recommendedId)) {
                try {
                    $lookedUp = $this->lookupCategoryById($recommendedId);
                    if ($lookedUp !== null) {
                        $lookedUp['recommended'] = true;
                        $lookedUp['match_kind'] = 'best';
                        $items[$lookedUp['id']] = $lookedUp;
                        $resolved = $lookedUp;
                    }
                } catch (RuntimeException $exception) {
                    // The ID-only result is still usable for loading the category template.
                }
            }
        }

        if ($resolved !== null) {
            try {
                $recommendation = isset($payload['result']) && is_array($payload['result'])
                    ? $payload['result']
                    : array();
                foreach ($this->matchingCategoriesFromTaxonomy($recommendation, $resolved, $search, 39) as $matched) {
                    $this->cacheCategory($matched);
                    if (!isset($items[$matched['id']])) {
                        $items[$matched['id']] = $matched;
                    }
                }
                foreach ($this->relatedCategories($recommendation, $resolved, $search) as $related) {
                    $this->cacheCategory($related);
                    if (!isset($items[$related['id']])) {
                        $items[$related['id']] = $related;
                    }
                }
            } catch (RuntimeException $exception) {
                // The main recommendation remains available when sibling lookup fails.
            }
        }

        if ($items === array() && ctype_digit($search)) {
            $items[$search] = array(
                'id' => $search,
                'name' => 'Temu category ' . $search,
                'path' => 'Temu category ' . $search,
                'leaf' => true,
            );
        }

        $items = array_values($items);
        usort($items, static function (array $a, array $b): int {
            $recommendedDiff = ((int) !empty($b['recommended'])) - ((int) !empty($a['recommended']));
            if ($recommendedDiff !== 0) {
                return $recommendedDiff;
            }
            $scoreDiff = ((int) ($b['match_score'] ?? 0)) - ((int) ($a['match_score'] ?? 0));
            return $scoreDiff !== 0 ? $scoreDiff : strcmp((string) ($a['path'] ?? ''), (string) ($b['path'] ?? ''));
        });
        return array_slice($items, 0, 40);
    }

    public function categoryById(string $categoryId): array
    {
        $categoryId = trim($categoryId);
        if ($categoryId === '' || !ctype_digit($categoryId)) {
            throw new RuntimeException('Brak poprawnego ID kategorii Temu. Wpisz same cyfry.');
        }

        $category = $this->lookupCategoryById($categoryId);
        if ($category === null || (string) ($category['id'] ?? '') !== $categoryId) {
            throw new RuntimeException('Nie znaleziono kategorii Temu o ID ' . $categoryId . '.');
        }

        $this->cacheCategory($category);
        return $category;
    }

    public function categoryParameters(string $categoryId): array
    {
        $categoryId = trim($categoryId);
        if ($categoryId === '' || !ctype_digit($categoryId)) {
            throw new RuntimeException('Brak poprawnego ID kategorii Temu.');
        }

        $payload = $this->request('bg.local.goods.template.get', array('catId' => (int) $categoryId));
        $template = $payload['result']['templateInfo'] ?? array();
        if (!is_array($template)) {
            return array();
        }

        $source = array();
        foreach (array('goodsSpecProperties', 'goodsProperties') as $key) {
            if (!empty($template[$key]) && is_array($template[$key])) {
                foreach ($template[$key] as $item) {
                    if (is_array($item)) {
                        $item['_temu_group'] = $key === 'goodsSpecProperties' ? 'sale' : 'property';
                        $source[] = $item;
                    }
                }
            }
        }

        $definitions = array();
        foreach ($source as $item) {
            $definition = $this->normalizeParameter($item);
            if ($definition !== null) {
                $definitions[$definition['id']] = $definition;
            }
        }

        uasort($definitions, static function (array $a, array $b): int {
            $required = ((int) !empty($b['required'])) - ((int) !empty($a['required']));
            return $required !== 0 ? $required : strcmp((string) $a['name'], (string) $b['name']);
        });

        return array_values($definitions);
    }

    private function request(string $type, array $parameters): array
    {
        $config = $this->connectionSettings();
        $apiUrl = trim((string) $config['api_url']);
        $appKey = trim((string) $config['app_key']);
        $appSecret = trim((string) $config['app_secret']);
        if ($apiUrl === '' || $appKey === '' || $appSecret === '') {
            throw new RuntimeException('Najpierw skonfiguruj URL, App Key i App Secret Temu w Administracji.');
        }
        if (!function_exists('curl_init')) {
            throw new RuntimeException('Rozszerzenie cURL jest wymagane do polaczenia z Temu.');
        }

        $payload = array_merge(array(
            'app_key' => $appKey,
            'data_type' => 'JSON',
            'timestamp' => time(),
            'type' => $type,
            'version' => 'V1',
        ), $parameters);
        $accessToken = trim((string) ($config['access_token'] ?? ''));
        if ($accessToken === '') {
            throw new RuntimeException(
                'Brak Access Token Temu. W Seller Center otworz Authorization Management, '
                . 'autoryzuj aplikacje dla sklepu i wklej wygenerowany token w Administracji.'
            );
        }
        $payload['access_token'] = $accessToken;
        $payload['sign'] = $this->signature($payload, $appSecret);

        $body = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($body === false) {
            throw new RuntimeException('Nie udalo sie przygotowac zapytania Temu.');
        }

        for ($attempt = 0; $attempt < 3; $attempt++) {
            $ch = curl_init($apiUrl);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
            curl_setopt($ch, CURLOPT_HTTPHEADER, array('Accept: application/json', 'Content-Type: application/json'));
            curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
            curl_setopt($ch, CURLOPT_TIMEOUT, 30);
            curl_setopt($ch, CURLOPT_USERAGENT, 'ALTREO-Temu/1.0');

            $raw = curl_exec($ch);
            $curlError = curl_error($ch);
            $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            if ($raw === false) {
                throw new RuntimeException('Blad polaczenia z Temu: ' . ($curlError !== '' ? $curlError : 'nieznany blad cURL'));
            }
            $decoded = json_decode((string) $raw, true);
            if (!is_array($decoded)) {
                throw new RuntimeException('Temu zwrocilo niepoprawna odpowiedz (HTTP ' . $status . ').');
            }
            if ($status >= 200 && $status < 300 && !empty($decoded['success'])) {
                return $decoded;
            }

            $message = trim((string) ($decoded['errorMsg'] ?? $decoded['message'] ?? $decoded['error_msg'] ?? ''));
            $code = trim((string) ($decoded['errorCode'] ?? $decoded['code'] ?? ''));
            if ($code === '4000000' && $attempt < 2) {
                usleep(($attempt + 1) * 300000);
                continue;
            }
            if ($message === '') {
                $message = 'Nieznany blad API';
            }
            throw new RuntimeException('Temu API: ' . $message . ($code !== '' ? ' (' . $code . ')' : ''));
        }

        throw new RuntimeException('Temu API: tymczasowy blad systemu (4000000). Sprobuj ponownie.');
    }

    private function signature(array $parameters, string $appSecret): string
    {
        unset($parameters['sign']);
        ksort($parameters, SORT_STRING);
        $plain = $appSecret;
        foreach ($parameters as $key => $value) {
            if (is_array($value) || is_object($value)) {
                $encoded = json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                $value = $encoded !== false ? $encoded : '';
            } elseif (is_bool($value)) {
                $value = $value ? 'true' : 'false';
            }
            $plain .= (string) $key . (string) $value;
        }
        return strtoupper(md5($plain . $appSecret));
    }

    private function categoryListFromPayload(array $payload): array
    {
        $list = $payload['result']['goodsCatsList'] ?? array();
        return is_array($list) ? $list : array();
    }

    private function collectCategoryCandidates($value, array &$result): void
    {
        if (!is_array($value)) {
            return;
        }
        $hasDirectCategoryId = isset($value['catId']) || isset($value['categoryId']);
        if ($hasDirectCategoryId) {
            $result[] = $value;
        }
        foreach ($value as $key => $child) {
            if (($key === 'catIdList' || $key === 'categoryIdList') && is_array($child)) {
                if (!$hasDirectCategoryId) {
                    for ($index = count($child) - 1; $index >= 0; $index--) {
                        $categoryId = $child[$index];
                        if (is_scalar($categoryId) && trim((string) $categoryId) !== '') {
                            $result[] = array('catId' => (string) $categoryId, 'leaf' => true);
                            break;
                        }
                    }
                }
                continue;
            }
            if (is_array($child)) {
                $this->collectCategoryCandidates($child, $result);
            }
        }
    }

    private function resolveRecommendedCategoryPath(array $result): ?array
    {
        $rawIds = $result['catIdList'] ?? $result['categoryIdList'] ?? array();
        $ids = array();
        if (is_array($rawIds)) {
            foreach ($rawIds as $rawId) {
                $id = is_scalar($rawId) ? trim((string) $rawId) : '';
                if ($id !== '' && ctype_digit($id) && !in_array($id, $ids, true)) {
                    $ids[] = $id;
                }
            }
        }
        $recommendedId = trim((string) ($result['catId'] ?? $result['categoryId'] ?? ''));
        if ($recommendedId !== '' && ctype_digit($recommendedId) && !in_array($recommendedId, $ids, true)) {
            $ids[] = $recommendedId;
        }
        if ($ids === array()) {
            return null;
        }

        $parentId = 0;
        $path = array();
        $selected = null;
        foreach ($ids as $id) {
            $payload = $this->request('bg.local.goods.cats.get', array('parentCatId' => $parentId));
            $found = null;
            foreach ($this->categoryListFromPayload($payload) as $category) {
                if (is_array($category) && (string) ($category['catId'] ?? '') === $id) {
                    $found = $category;
                    break;
                }
            }
            if ($found === null) {
                return null;
            }
            $name = trim((string) ($found['catName'] ?? ''));
            if ($name !== '') {
                $path[] = $name;
            }
            $selected = $found;
            $parentId = (int) $id;
        }

        if ($selected === null) {
            return null;
        }
        $normalized = $this->normalizeCategory($selected);
        if ($normalized === null) {
            return null;
        }
        if ($path !== array()) {
            $normalized['path'] = implode(' > ', $path);
        }
        return $normalized;
    }

    private function lookupCategoryById(string $targetId): ?array
    {
        $cacheKey = 'temu_category_lookup_v1_' . $targetId;
        $cached = json_decode($this->settings->get($cacheKey, ''), true);
        if (is_array($cached) && (string) ($cached['id'] ?? '') === $targetId) {
            return $cached;
        }

        $target = (int) $targetId;
        $parentId = 0;
        $path = array();
        $visited = array();

        for ($depth = 0; $depth < 12; $depth++) {
            if (isset($visited[$parentId])) {
                return null;
            }
            $visited[$parentId] = true;

            $payload = $this->request('bg.local.goods.cats.get', array('parentCatId' => $parentId));
            $categories = $this->categoryListFromPayload($payload);
            if ($categories === array()) {
                return null;
            }

            $exact = null;
            $branch = null;
            foreach ($categories as $category) {
                if (!is_array($category)) {
                    continue;
                }
                $categoryId = (int) ($category['catId'] ?? 0);
                if ($categoryId === $target) {
                    $exact = $category;
                    break;
                }
                if ($categoryId > 0 && $categoryId < $target && ($branch === null || $categoryId > (int) ($branch['catId'] ?? 0))) {
                    $branch = $category;
                }
            }

            if ($exact !== null) {
                $name = trim((string) ($exact['catName'] ?? ''));
                if ($name !== '') {
                    $path[] = $name;
                }
                $normalized = $this->normalizeCategory($exact);
                if ($normalized === null) {
                    return null;
                }
                $normalized['path'] = $path !== array() ? implode(' > ', $path) : $normalized['path'];
                $encoded = json_encode($normalized, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                if ($encoded !== false) {
                    $this->settings->set($cacheKey, $encoded);
                }
                return $normalized;
            }

            if ($branch === null || !empty($branch['leaf'])) {
                return null;
            }
            $branchName = trim((string) ($branch['catName'] ?? ''));
            if ($branchName !== '') {
                $path[] = $branchName;
            }
            $parentId = (int) ($branch['catId'] ?? 0);
            if ($parentId <= 0) {
                return null;
            }
        }

        return null;
    }

    private function cacheCategory(array $category): void
    {
        $categoryId = trim((string) ($category['id'] ?? ''));
        if ($categoryId === '' || !ctype_digit($categoryId)) {
            return;
        }
        $encoded = json_encode($category, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($encoded !== false) {
            $this->settings->set('temu_category_lookup_v1_' . $categoryId, $encoded);
        }
    }

    private function relatedCategories(array $recommendation, array $primary, string $search): array
    {
        $ids = array();
        $rawIds = $recommendation['catIdList'] ?? $recommendation['categoryIdList'] ?? array();
        if (is_array($rawIds)) {
            foreach ($rawIds as $rawId) {
                $id = is_scalar($rawId) ? trim((string) $rawId) : '';
                if ($id !== '' && ctype_digit($id) && !in_array($id, $ids, true)) {
                    $ids[] = $id;
                }
            }
        }
        $primaryId = (string) ($primary['id'] ?? '');
        if ($primaryId !== '' && !in_array($primaryId, $ids, true)) {
            $ids[] = $primaryId;
        }
        if (count($ids) < 2) {
            return array();
        }

        $parentId = (int) $ids[count($ids) - 2];
        $payload = $this->request('bg.local.goods.cats.get', array('parentCatId' => $parentId));
        $primaryPath = (string) ($primary['path'] ?? '');
        $pathParts = array_values(array_filter(array_map('trim', explode('>', $primaryPath))));
        if ($pathParts !== array()) {
            array_pop($pathParts);
        }
        $tokens = $this->searchTokens($search);
        $result = array();

        foreach ($this->categoryListFromPayload($payload) as $category) {
            if (!is_array($category) || empty($category['leaf'])) {
                continue;
            }
            if (array_key_exists('availableStatus', $category) && (int) $category['availableStatus'] !== 0) {
                continue;
            }
            $item = $this->normalizeCategory($category);
            if ($item === null || $item['id'] === $primaryId) {
                continue;
            }
            $itemPath = $pathParts;
            $itemPath[] = (string) $item['name'];
            $item['path'] = implode(' > ', $itemPath);
            $item['recommended'] = false;
            $item['match_kind'] = 'related';
            $item['match_score'] = $this->categoryMatchScore($item, $tokens);
            $result[] = $item;
        }

        usort($result, static function (array $a, array $b): int {
            $scoreDiff = ((int) ($b['match_score'] ?? 0)) - ((int) ($a['match_score'] ?? 0));
            return $scoreDiff !== 0 ? $scoreDiff : strcmp((string) $a['path'], (string) $b['path']);
        });
        return array_slice($result, 0, 29);
    }

    private function matchingCategoriesFromTaxonomy(
        array $recommendation,
        array $primary,
        string $search,
        int $limit
    ): array {
        $rawIds = $recommendation['catIdList'] ?? $recommendation['categoryIdList'] ?? array();
        $recommendedIds = array();
        if (is_array($rawIds)) {
            foreach ($rawIds as $rawId) {
                $id = is_scalar($rawId) ? trim((string) $rawId) : '';
                if ($id !== '' && ctype_digit($id) && !in_array($id, $recommendedIds, true)) {
                    $recommendedIds[] = $id;
                }
            }
        }
        if ($recommendedIds === array() || $limit < 1) {
            return array();
        }

        $pathParts = array_values(array_filter(array_map('trim', explode('>', (string) ($primary['path'] ?? '')))));
        $rootName = (string) ($pathParts[0] ?? ('Temu category ' . $recommendedIds[0]));
        $tokens = $this->searchTokens($search);
        if ($tokens === array()) {
            return array();
        }

        $queue = array(array(
            'id' => $recommendedIds[0],
            'path' => $rootName,
            'depth' => 1,
            'priority' => 100000,
        ));
        $visited = array();
        $matches = array();
        $requests = 0;

        while ($queue !== array() && count($matches) < $limit && $requests < 60) {
            usort($queue, static function (array $a, array $b): int {
                return ((int) $b['priority']) <=> ((int) $a['priority']);
            });
            $node = array_shift($queue);
            $parentId = (string) ($node['id'] ?? '');
            if ($parentId === '' || isset($visited[$parentId])) {
                continue;
            }
            $visited[$parentId] = true;

            try {
                $categories = $this->categoryChildren((int) $parentId);
                $requests++;
            } catch (RuntimeException $exception) {
                continue;
            }

            foreach ($categories as $category) {
                if (!is_array($category)) {
                    continue;
                }
                $item = $this->normalizeCategory($category);
                if ($item === null) {
                    continue;
                }
                $item['path'] = trim((string) $node['path']) . ' > ' . (string) $item['name'];
                $score = $this->categoryMatchScore($item, $tokens);
                $item['match_score'] = $score;
                $item['recommended'] = false;
                $item['match_kind'] = 'taxonomy';
                $isRecommendedPath = in_array((string) $item['id'], $recommendedIds, true);

                if (!empty($item['leaf'])) {
                    if ($score > 0 && (string) $item['id'] !== (string) ($primary['id'] ?? '')) {
                        if (!array_key_exists('availableStatus', $category) || (int) $category['availableStatus'] === 0) {
                            $matches[$item['id']] = $item;
                        }
                    }
                    continue;
                }

                $queue[] = array(
                    'id' => (string) $item['id'],
                    'path' => (string) $item['path'],
                    'depth' => (int) $node['depth'] + 1,
                    'priority' => ($isRecommendedPath ? 100000 : 0) + ($score * 1000) - (int) $node['depth'],
                );
            }
        }

        $matches = array_values($matches);
        usort($matches, static function (array $a, array $b): int {
            $scoreDiff = ((int) ($b['match_score'] ?? 0)) - ((int) ($a['match_score'] ?? 0));
            return $scoreDiff !== 0 ? $scoreDiff : strcmp((string) $a['path'], (string) $b['path']);
        });
        return array_slice($matches, 0, $limit);
    }

    private function categoryChildren(int $parentId): array
    {
        $cacheKey = 'temu_category_children_v1_' . $parentId;
        $cached = json_decode($this->settings->get($cacheKey, ''), true);
        if (is_array($cached)
            && isset($cached['cached_at'], $cached['items'])
            && is_array($cached['items'])
            && (int) $cached['cached_at'] >= time() - 43200
        ) {
            return $cached['items'];
        }

        $payload = $this->request('bg.local.goods.cats.get', array('parentCatId' => $parentId));
        $items = $this->categoryListFromPayload($payload);
        $encoded = json_encode(array('cached_at' => time(), 'items' => $items), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($encoded !== false) {
            $this->settings->set($cacheKey, $encoded);
        }
        return $items;
    }

    private function searchTokens(string $search): array
    {
        $normalized = mb_strtolower(trim($search), 'UTF-8');
        $parts = preg_split('/[^\p{L}\p{N}]+/u', $normalized) ?: array();
        return array_values(array_unique(array_filter($parts, static function ($part): bool {
            return mb_strlen((string) $part, 'UTF-8') >= 2;
        })));
    }

    private function categoryMatchScore(array $item, array $tokens): int
    {
        $name = mb_strtolower((string) ($item['name'] ?? ''), 'UTF-8');
        $path = mb_strtolower((string) ($item['path'] ?? ''), 'UTF-8');
        $score = 0;
        foreach ($tokens as $token) {
            if ($name !== '' && mb_strpos($name, $token, 0, 'UTF-8') !== false) {
                $score += 10;
            } elseif ($path !== '' && mb_strpos($path, $token, 0, 'UTF-8') !== false) {
                $score += 3;
            }
        }
        return $score;
    }

    private function normalizeCategory(array $item): ?array
    {
        $id = trim((string) ($item['catId'] ?? $item['categoryId'] ?? $item['id'] ?? ''));
        if ($id === '') {
            return null;
        }
        $name = trim((string) ($item['catName'] ?? $item['categoryName'] ?? $item['name'] ?? ''));
        $path = trim((string) ($item['catPath'] ?? $item['categoryPath'] ?? $item['path'] ?? ''));
        foreach (array('catNameList', 'categoryNameList', 'pathNames') as $key) {
            if ($path === '' && isset($item[$key]) && is_array($item[$key])) {
                $path = implode(' > ', array_values(array_filter(array_map('strval', $item[$key]))));
            }
        }
        if ($path === '') {
            $path = $name !== '' ? $name : ('Temu category ' . $id);
        }
        if ($name === '') {
            $parts = array_values(array_filter(array_map('trim', explode('>', $path))));
            $name = $parts !== array() ? (string) end($parts) : ('Temu category ' . $id);
        }
        return array(
            'id' => $id,
            'name' => $name,
            'path' => $path,
            'leaf' => array_key_exists('leaf', $item) ? (bool) $item['leaf'] : true,
        );
    }

    private function normalizeParameter(array $item): ?array
    {
        $id = trim((string) ($item['templatePid'] ?? $item['pid'] ?? ''));
        if ($id === '') {
            return null;
        }
        $name = trim((string) ($item['name'] ?? $item['propertyChooseTitle'] ?? $id));
        $dictionary = array();
        $values = isset($item['values']) && is_array($item['values']) ? $item['values'] : array();
        foreach ($values as $value) {
            if (!is_array($value)) {
                continue;
            }
            $optionId = trim((string) ($value['vid'] ?? $value['specId'] ?? $value['value'] ?? ''));
            $optionName = trim((string) ($value['value'] ?? $optionId));
            if ($optionId !== '') {
                $dictionary[$optionId] = array('id' => $optionId, 'value' => $optionName !== '' ? $optionName : $optionId);
            }
        }
        $multiple = isset($item['chooseMaxNum']) && (int) $item['chooseMaxNum'] > 1;
        return array(
            'id' => $id,
            'name' => $name !== '' ? $name : $id,
            'type' => $dictionary !== array() ? 'dictionary' : 'string',
            'required' => !empty($item['required']),
            'multiple' => $multiple,
            'dictionary' => array_values($dictionary),
            'restrictions' => array(
                'control_type' => isset($item['controlType']) ? (int) $item['controlType'] : null,
                'choose_max_num' => isset($item['chooseMaxNum']) ? (int) $item['chooseMaxNum'] : null,
                'min_value' => $item['minValue'] ?? null,
                'max_value' => $item['maxValue'] ?? null,
                'value_precision' => $item['valuePrecision'] ?? null,
                'is_sale' => !empty($item['isSale']),
                'temu_group' => (string) ($item['_temu_group'] ?? 'property'),
                'pid' => isset($item['pid']) ? (string) $item['pid'] : null,
                'ref_pid' => isset($item['refPid']) ? (string) $item['refPid'] : null,
                'template_pid' => isset($item['templatePid']) ? (string) $item['templatePid'] : null,
            ),
        );
    }
}
