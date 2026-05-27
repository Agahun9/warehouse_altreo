<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Models\SettingRepository;
use RuntimeException;

class TemuService
{
    /** @var SettingRepository */
    private $settings;

    public function __construct()
    {
        $this->settings = new SettingRepository(Database::instance());
        $this->settings->ensureSchema();
    }

    public function connectionSettings(): array
    {
        return array(
            'api_url' => $this->settings->get('temu_api_url', ''),
            'app_key' => $this->settings->get('temu_app_key', ''),
            'app_secret' => $this->settings->get('temu_app_secret', ''),
            'access_token' => $this->settings->get('temu_access_token', ''),
            'shop_id' => $this->settings->get('temu_shop_id', ''),
            'region' => $this->settings->get('temu_region', 'PL'),
        );
    }

    public function saveConnectionSettings(array $input): array
    {
        $apiUrl = rtrim(trim((string) ($input['api_url'] ?? '')), '/');
        $appKey = trim((string) ($input['app_key'] ?? ''));
        $appSecret = trim((string) ($input['app_secret'] ?? ''));
        $accessToken = trim((string) ($input['access_token'] ?? ''));
        $shopId = trim((string) ($input['shop_id'] ?? ''));
        $region = strtoupper(trim((string) ($input['region'] ?? 'PL')));

        if ($apiUrl !== '' && filter_var($apiUrl, FILTER_VALIDATE_URL) === false) {
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
}
