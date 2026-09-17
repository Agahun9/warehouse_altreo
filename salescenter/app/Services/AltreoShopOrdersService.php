<?php

declare(strict_types=1);
namespace App\Services;

use App\Models\SettingRepository;
use RuntimeException;

final class AltreoShopOrdersService
{
    private $settings;
    public function __construct(SettingRepository $settings) { $this->settings=$settings; }

    public function configured(): bool
    {
        return $this->settings->get('altreo_shop_url')!=='' && $this->settings->get('altreo_shop_token')!=='';
    }

    public function readPage(string $since,int $afterId): array
    {
        $base=rtrim($this->settings->get('altreo_shop_url'),'/');
        $token=$this->settings->get('altreo_shop_token');
        if (!preg_match('#^https://[^/?#]+(?:/[^?#]*)?$#i',$base) || strlen($token)<32) {
            throw new RuntimeException('Skonfiguruj adres HTTPS sklepu i token API (minimum 32 znaki).');
        }
        $url=$base.'/api/warehouse/orders?'.http_build_query(['since'=>$since,'after_id'=>$afterId]);
        $curl=curl_init($url);
        curl_setopt_array($curl,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_CONNECTTIMEOUT=>8,CURLOPT_TIMEOUT=>30,CURLOPT_HTTPHEADER=>['Authorization: Bearer '.$token,'Accept: application/json'],CURLOPT_FOLLOWLOCATION=>false]);
        $body=curl_exec($curl); $status=(int)curl_getinfo($curl,CURLINFO_HTTP_CODE); $error=curl_error($curl); curl_close($curl);
        if ($body===false || $status!==200) { throw new RuntimeException('Sklep nie zwrócił zamówień (HTTP '.$status.($error!==''?', '.$error:'').').'); }
        $data=json_decode($body,true);
        if (!is_array($data) || !is_array($data['orders']??null)) { throw new RuntimeException('Nieprawidłowa odpowiedź API sklepu.'); }
        return $data;
    }
}
