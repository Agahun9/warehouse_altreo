<?php

declare(strict_types=1);
namespace App\Services\Shipping;

use App\Models\OrderRepository;
use InvalidArgumentException;

/** Rejestr operatorów przesyłek. Kolejność = kolejność kart w zakładce Przesyłki. */
final class ShippingProviders
{
    public const CLASSES=[
        'allegro_wza'=>AllegroWzaProvider::class,
        'erli_shipping'=>ErliShippingProvider::class,
        'inpost_shipx'=>InpostShipxProvider::class,
        'apaczka'=>ApaczkaProvider::class,
    ];

    /** @var array<string,ShippingProvider> Podmiany w testach. */
    public static $overrides=[];

    public static function exists(string $key): bool { return isset(self::CLASSES[$key]); }

    public static function get(OrderRepository $repo,string $key): ShippingProvider
    {
        if (isset(self::$overrides[$key])) { return self::$overrides[$key]; }
        if (!self::exists($key)) { throw new InvalidArgumentException('Nieobsługiwany operator przesyłek.'); }
        $class=self::CLASSES[$key];
        return new $class($repo);
    }

    public static function definition(string $key): array
    {
        if (!self::exists($key)) { return ['key'=>$key,'label'=>$key,'description'=>'','badge'=>['text'=>'?','tone'=>''],'color'=>'#64748b','ink'=>'#fff','group'=>'Przewoźnicy i brokerzy','steps'=>[],'source_platform'=>'','capabilities'=>['valuation'=>false,'cancel'=>false,'label'=>true,'tracking'=>true,'cod'=>'none','source_tracking'=>'manual','pickup_protocol'=>false],'info'=>[],'docs'=>'','fields'=>[]]; }
        $class=self::CLASSES[$key];
        return $class::definition();
    }

    /** Definicje wszystkich operatorów z podłączonymi kontami – dane do kart w zakładce Przesyłki. */
    public static function catalog(array $carrierAccounts,array $orderAccounts): array
    {
        $catalog=[];
        foreach (array_keys(self::CLASSES) as $key) {
            $definition=self::definition($key);
            $caps=$definition['capabilities'];
            $definition['features']=array_values(array_filter([
                !empty($caps['label'])?'Etykiety PDF':'', !empty($caps['tracking'])?'Śledzenie':'', !empty($caps['cancel'])?'Anulowanie':'', !empty($caps['valuation'])?'Wycena na żywo':'',
                ($caps['cod']??'')==='form'?'Pobranie z formularza':(($caps['cod']??'')==='order'?'Pobranie z zamówienia':''),
                ($caps['source_tracking']??'')==='auto'?'Numer trafia do źródła sam':'', !empty($caps['pickup_protocol'])?'Protokół odbioru':'',
            ]));
            $definition['accounts']=[];
            foreach ($carrierAccounts as $account) {
                if ((string)$account['provider']!==$key) { continue; }
                $public=json_decode((string)($account['public_config_json']??''),true) ?: [];
                $account['public']=$public;
                $account+=['shipment_count'=>0,'last_shipment_at'=>''];
                $account['source_name']='';
                foreach ($orderAccounts as $source) { if ((int)$source['id']===(int)($public['order_account_id']??0)) { $account['source_name']=(string)$source['name']; } }
                $definition['accounts'][]=$account;
            }
            $definition['source_accounts']=[];
            if ($definition['source_platform']!=='') {
                foreach ($orderAccounts as $source) { if ((string)$source['platform']===$definition['source_platform']) { $definition['source_accounts'][]=$source; } }
            }
            $catalog[]=$definition;
        }
        return $catalog;
    }
}
