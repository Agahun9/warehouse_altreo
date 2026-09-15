<?php

declare(strict_types=1);
namespace App\Services;

use App\Core\Config;
use RuntimeException;

final class OrderSecretBox
{
    public static function encrypt(array $secret): string
    {
        if (!function_exists('openssl_encrypt')) { throw new RuntimeException('Brak OpenSSL potrzebnego do bezpiecznego zapisania danych przewoźnika.'); }
        $config=Config::get('app');
        $key=hash('sha256',(string)($config['jwt_secret']??''),true);
        if (trim((string)($config['jwt_secret']??''))==='') { throw new RuntimeException('Brak klucza aplikacji potrzebnego do szyfrowania danych przewoźnika.'); }
        $iv=random_bytes(12); $tag='';
        $plain=json_encode($secret,JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR);
        $cipher=openssl_encrypt($plain,'aes-256-gcm',$key,OPENSSL_RAW_DATA,$iv,$tag);
        if ($cipher===false) { throw new RuntimeException('Nie udało się zaszyfrować danych przewoźnika.'); }
        return base64_encode($iv.$tag.$cipher);
    }

    public static function decrypt(string $encoded): array
    {
        $raw=base64_decode($encoded,true);
        if ($raw===false || strlen($raw)<29) { throw new RuntimeException('Uszkodzona konfiguracja przewoźnika.'); }
        $config=Config::get('app'); $key=hash('sha256',(string)($config['jwt_secret']??''),true);
        $plain=openssl_decrypt(substr($raw,28),'aes-256-gcm',$key,OPENSSL_RAW_DATA,substr($raw,0,12),substr($raw,12,16));
        if ($plain===false) { throw new RuntimeException('Nie można odszyfrować konfiguracji przewoźnika.'); }
        return json_decode($plain,true,512,JSON_THROW_ON_ERROR);
    }
}
