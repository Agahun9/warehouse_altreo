<?php

declare(strict_types=1);

namespace App\Services;

class AccountingWarehouseClassifier
{
    public function classify(string $sourceName): array
    {
        $normalized = $this->normalize($sourceName);

        $rules = array(
            'karta graficzna' => array('rtx', 'gtx', 'geforce', 'radeon', 'gpu', 'karta graficzna'),
            'procesor' => array('procesor', 'cpu', 'ryzen', 'threadripper', 'epyc', 'intel core', 'core i', 'celeron', 'pentium', 'xeon'),
            'plyta glowna' => array('plyta glowna', 'motherboard', 'mainboard'),
            'pamiec ram' => array('ram', 'ddr4', 'ddr5', 'so-dimm', 'udimm', 'dimm'),
            'dysk' => array('ssd', 'nvme', 'm.2', 'hdd', 'dysk', 'sata'),
            'zasilacz' => array('zasilacz', 'psu'),
            'chlodzenie' => array('chlodzenie', 'cooler', 'wentylator', 'aio'),
            'obudowa' => array('obudowa', 'case'),
            'monitor' => array('monitor'),
            'laptop' => array('laptop', 'notebook'),
            'telefon' => array('telefon', 'smartfon', 'iphone', 'samsung galaxy', 'xiaomi', 'pixel'),
            'tablet' => array('tablet', 'ipad'),
            'sluchawki' => array('sluchawki', 'headset', 'airpods'),
            'klawiatura' => array('klawiatura', 'keyboard'),
            'mysz' => array('mysz', 'mouse'),
            'kabel' => array('kabel', 'przewod', 'usb-c', 'hdmi', 'displayport'),
            'ladowarka' => array('ladowarka', 'charger', 'zasilacz usb'),
            'powerbank' => array('powerbank'),
            'wifi' => array('wifi', 'wi fi', 'wlan', 'adapter wifi', 'adapter wi fi', 'karta wifi', 'dongle wifi'),
            'router' => array('router', 'switch', 'access point'),
            'kamera' => array('kamera', 'webcam'),
            'wysylka' => array('wysylka', 'przesylka', 'kurier', 'kurierska', 'shipping', 'dostawa'),
            'etui' => array('etui', 'case do', 'cover'),
            'torba' => array('torba', 'pokrowiec'),
            'plecak' => array('plecak'),
            't-shirt' => array('t-shirt', 'koszulka', 'shirt'),
            'drukarka' => array('drukarka', 'printer'),
            'oprogramowanie' => array('licencja', 'software', 'oprogramowanie', 'windows', 'office'),
            'usluga' => array('usluga', 'serwis', 'transport'),
        );

        foreach ($rules as $canonicalName => $keywords) {
            foreach ($keywords as $keyword) {
                if ($keyword !== '' && mb_strpos($normalized, $this->normalize($keyword)) !== false) {
                    return array(
                        'canonical_name' => $canonicalName,
                        'confidence' => 'high',
                        'rule' => $keyword,
                    );
                }
            }
        }

        return array(
            'canonical_name' => 'pozostale',
            'confidence' => 'low',
            'rule' => '',
        );
    }

    public function commonItemNames(): array
    {
        return array(
            'karta graficzna',
            'procesor',
            'plyta glowna',
            'pamiec ram',
            'dysk',
            'zasilacz',
            'chlodzenie',
            'obudowa',
            'monitor',
            'laptop',
            'telefon',
            'tablet',
            'sluchawki',
            'klawiatura',
            'mysz',
            'kabel',
            'ladowarka',
            'powerbank',
            'wifi',
            'router',
            'kamera',
            'wysylka',
            'etui',
            'torba',
            'plecak',
            't-shirt',
            'drukarka',
            'oprogramowanie',
            'usluga',
            'pozostale',
        );
    }

    public function normalize(string $value): string
    {
        $value = trim(mb_strtolower($value, 'UTF-8'));
        if ($value === '') {
            return '';
        }

        $value = strtr($value, array(
            'ą' => 'a',
            'ć' => 'c',
            'ę' => 'e',
            'ł' => 'l',
            'ń' => 'n',
            'ó' => 'o',
            'ś' => 's',
            'ź' => 'z',
            'ż' => 'z',
            'Ä…' => 'a',
            'Ä‡' => 'c',
            'Ä™' => 'e',
            'Ĺ‚' => 'l',
            'Ĺ„' => 'n',
            'Ăł' => 'o',
            'Ĺ›' => 's',
            'Ĺş' => 'z',
            'ĹĽ' => 'z',
        ));
        $value = preg_replace('/[^a-z0-9]+/u', ' ', $value);

        return trim((string) preg_replace('/\s+/u', ' ', (string) $value));
    }
}
