<?php

declare(strict_types=1);
namespace App\Services;
use InvalidArgumentException;

/**
 * Dane firmy po NIP do formularza nabywcy.
 * GUS (BIR 1.1, REGON) daje adres w osobnych polach oraz imię i nazwisko osoby prowadzącej JDG — wymaga klucza
 * `gus_api_key` w app/Config/app.php. Biała lista MF (bez klucza) podaje status VAT i jest źródłem awaryjnym.
 */
final class CompanyRegistryService
{
    private const GUS_URL='https://wyszukiwarkaregon.stat.gov.pl/wsBIR/UslugaBIRzewnPubl.svc';
    private const GUS_TEST_URL='https://wyszukiwarkaregontest.stat.gov.pl/wsBIR/UslugaBIRzewnPubl.svc';
    /** Publiczny klucz środowiska testowego GUS (dane fikcyjne). */
    public const GUS_TEST_KEY='abcde12345abcde12345';
    private const WL_URL='https://wl-api.mf.gov.pl/api/search/nip/';
    private const NS='http://CIS/BIR/PUBL/2014/07';

    private $gusKey;
    private $transport;

    /** @param callable|null $transport fn(string $url, array $headers, ?string $body): array{status:int, body:string} — dla testów. */
    public function __construct(string $gusKey='',?callable $transport=null)
    {
        $this->gusKey=trim($gusKey);
        $this->transport=$transport?:[$this,'http'];
    }

    public function gusEnabled(): bool { return $this->gusKey!==''; }

    /**
     * Pola nabywcy (company, first_name, last_name, street, building, postal_code, city, country, nip) oraz
     * regon, krs, vat_status, active, sources, warnings.
     */
    public function lookup(string $nip): array
    {
        $nip=KsefService::normalizeNip($nip);
        if (!KsefService::validNip($nip)) { throw new InvalidArgumentException('Nieprawidłowy NIP — sprawdź cyfry (suma kontrolna się nie zgadza).'); }
        $result=null; $warnings=[]; $sources=[];
        if ($this->gusEnabled()) {
            try { $result=$this->gus($nip); if ($result) { $sources[]='GUS REGON'; } }
            catch (\Throwable $e) { $warnings[]='GUS niedostępny: '.mb_substr($e->getMessage(),0,200,'UTF-8'); }
        }
        $wl=null;
        try { $wl=$this->whiteList($nip); }
        catch (\Throwable $e) { $warnings[]='Biała lista MF niedostępna: '.mb_substr($e->getMessage(),0,200,'UTF-8'); }
        if ($wl) {
            $sources[]='Biała lista VAT (MF)';
            if (!$result) { $result=$wl; }
            else { foreach (['krs','regon'] as $key) { if (($result[$key]??'')==='' && ($wl[$key]??'')!=='') { $result[$key]=$wl[$key]; } } }
            $result['vat_status']=$wl['vat_status'];
        }
        if (!$result) {
            if ($warnings) { throw new \RuntimeException(implode(' ',$warnings)); }
            throw new InvalidArgumentException('Nie znaleziono firmy o NIP '.$nip.'.'.($this->gusEnabled()?'':' Biała lista MF zawiera tylko czynnych i zwolnionych podatników VAT — dodaj klucz GUS (gus_api_key), aby wyszukiwać wszystkie firmy.'));
        }
        $result+=['vat_status'=>'','active'=>true];
        if (!$result['active']) { $warnings[]='Firma ma wpisaną datę zakończenia działalności.'; }
        if ($result['vat_status']!=='' && $result['vat_status']!=='Czynny') { $warnings[]='Status VAT: '.$result['vat_status'].'.'; }
        $result['nip']=$nip; $result['country']='PL'; $result['sources']=$sources; $result['warnings']=$warnings;
        return $result;
    }

    private function gus(string $nip): ?array
    {
        $url=$this->gusKey===self::GUS_TEST_KEY?self::GUS_TEST_URL:self::GUS_URL;
        $sid=$this->soapResult($url,'Zaloguj','<ns:Zaloguj><ns:pKluczUzytkownika>'.htmlspecialchars($this->gusKey,ENT_XML1).'</ns:pKluczUzytkownika></ns:Zaloguj>');
        if ($sid==='') { throw new \RuntimeException('odrzucono klucz API GUS.'); }
        try {
            $found=$this->gusRows($this->soapResult($url,'DaneSzukajPodmioty','<ns:DaneSzukajPodmioty><ns:pParametryWyszukiwania><dat:Nip>'.$nip.'</dat:Nip></ns:pParametryWyszukiwania></ns:DaneSzukajPodmioty>',$sid));
            // Kilka wpisów dla jednego NIP (np. jednostki lokalne) — preferuj aktywny podmiot główny.
            usort($found,static function (array $a,array $b): int { return [($a['DataZakonczeniaDzialalnosci']??'')!=='',(string)($a['Typ']??'')==='LP'||(string)($a['Typ']??'')==='LF'] <=> [($b['DataZakonczeniaDzialalnosci']??'')!=='',(string)($b['Typ']??'')==='LP'||(string)($b['Typ']??'')==='LF']; });
            $row=$found[0]??null;
            if (!$row || isset($row['ErrorCode'])) { return null; }
            $result=[
                'company'=>trim((string)($row['Nazwa']??'')),'first_name'=>'','last_name'=>'',
                'street'=>trim((string)($row['Ulica']??'')),'building'=>self::building((string)($row['NrNieruchomosci']??''),(string)($row['NrLokalu']??'')),
                'postal_code'=>trim((string)($row['KodPocztowy']??'')),'city'=>trim((string)($row['Miejscowosc']??'')),
                'regon'=>trim((string)($row['Regon']??'')),'krs'=>'','active'=>trim((string)($row['DataZakonczeniaDzialalnosci']??''))==='',
            ];
            // Bez ulicy (małe miejscowości) adres to „Miejscowość nr”, a poczta bywa w innej miejscowości.
            $postTown=trim((string)($row['MiejscowoscPoczty']??''));
            if ($result['street']==='' && $result['city']!=='') { $result['street']=$result['city']; if ($postTown!=='') { $result['city']=$postTown; } }
            if (in_array((string)($row['Typ']??''),['F','LF'],true) && $result['regon']!=='') {
                try {
                    $person=$this->gusRows($this->soapResult($url,'DanePobierzPelnyRaport','<ns:DanePobierzPelnyRaport><ns:pRegon>'.htmlspecialchars($result['regon'],ENT_XML1).'</ns:pRegon><ns:pNazwaRaportu>BIR11OsFizycznaDaneOgolne</ns:pNazwaRaportu></ns:DanePobierzPelnyRaport>',$sid))[0]??[];
                    $result['first_name']=self::title((string)($person['fiz_imie1']??''));
                    $result['last_name']=self::title((string)($person['fiz_nazwisko']??''));
                } catch (\Throwable $ignored) { }
            }
            return $result;
        } finally {
            try { $this->soapResult($url,'Wyloguj','<ns:Wyloguj><ns:pIdentyfikatorSesji>'.htmlspecialchars($sid,ENT_XML1).'</ns:pIdentyfikatorSesji></ns:Wyloguj>',$sid); } catch (\Throwable $ignored) { }
        }
    }

    private function soapResult(string $url,string $action,string $body,string $sid=''): string
    {
        $envelope='<soap:Envelope xmlns:soap="http://www.w3.org/2003/05/soap-envelope" xmlns:ns="'.self::NS.'" xmlns:dat="'.self::NS.'/DataContract">'
            .'<soap:Header xmlns:wsa="http://www.w3.org/2005/08/addressing"><wsa:To>'.$url.'</wsa:To><wsa:Action>'.self::NS.'/IUslugaBIRzewnPubl/'.$action.'</wsa:Action></soap:Header>'
            .'<soap:Body>'.$body.'</soap:Body></soap:Envelope>';
        $headers=['Content-Type: application/soap+xml; charset=utf-8'];
        if ($sid!=='') { $headers[]='sid: '.$sid; }
        $response=($this->transport)($url,$headers,$envelope);
        if ((int)$response['status']<200 || (int)$response['status']>=300) { throw new \RuntimeException('HTTP '.(int)$response['status']); }
        // Odpowiedź bywa w kopercie MTOM (multipart) — wynik wyciągamy niezależnie od opakowania.
        if (!preg_match('/<(?:\w+:)?'.$action.'Result[^>]*>(.*?)<\/(?:\w+:)?'.$action.'Result>/s',(string)$response['body'],$m)) { return ''; }
        return html_entity_decode($m[1],ENT_QUOTES|ENT_XML1,'UTF-8');
    }

    /** Wiersze <dane> z wyniku GUS jako tablice pól. */
    private function gusRows(string $xml): array
    {
        if (trim($xml)==='') { return []; }
        $previous=libxml_use_internal_errors(true);
        $root=simplexml_load_string($xml,'SimpleXMLElement',LIBXML_NONET);
        libxml_use_internal_errors($previous);
        if (!$root) { throw new \RuntimeException('nieczytelna odpowiedź GUS.'); }
        $rows=[];
        foreach ($root->dane as $dane) { $row=[]; foreach ($dane->children() as $name=>$value) { $row[(string)$name]=trim((string)$value); } $rows[]=$row; }
        return $rows;
    }

    private function whiteList(string $nip): ?array
    {
        $response=($this->transport)(self::WL_URL.$nip.'?date='.(new \DateTimeImmutable('now',new \DateTimeZone('Europe/Warsaw')))->format('Y-m-d'),['Accept: application/json'],null);
        $data=json_decode((string)$response['body'],true);
        if ((int)$response['status']===400 && is_array($data)) { return null; }
        if ((int)$response['status']<200 || (int)$response['status']>=300 || !is_array($data)) { throw new \RuntimeException(is_array($data)&&!empty($data['message'])?(string)$data['message']:'HTTP '.(int)$response['status']); }
        $subject=$data['result']['subject']??null;
        if (!is_array($subject) || trim((string)($subject['name']??''))==='') { return null; }
        $address=self::parseAddress((string)($subject['workingAddress']??$subject['residenceAddress']??''));
        return $address+['company'=>trim((string)$subject['name']),'first_name'=>'','last_name'=>'','regon'=>trim((string)($subject['regon']??'')),'krs'=>trim((string)($subject['krs']??'')),'vat_status'=>trim((string)($subject['statusVat']??'')),'active'=>true];
    }

    /** „UL. SŁONECZNA 32/4, 77-100 BYTÓW” => street, building, postal_code, city. */
    public static function parseAddress(string $address): array
    {
        $result=['street'=>'','building'=>'','postal_code'=>'','city'=>''];
        $address=trim(preg_replace('/\s+/u',' ',$address)??'');
        if ($address==='') { return $result; }
        if (preg_match('/^(.*?),?\s*(\d{2}-\d{3})\s+(.+)$/u',$address,$m)) { $address=trim($m[1],' ,'); $result['postal_code']=$m[2]; $result['city']=self::title($m[3]); }
        [$street,$building]=OrderDocumentService::splitStreet($address);
        $result['street']=self::title($street); $result['building']=$building;
        return $result;
    }

    private static function building(string $number,string $local): string
    {
        $number=trim($number); $local=trim($local);
        return $local!==''?$number.'/'.$local:$number;
    }

    /** Wielkie litery z rejestru => „Jana Pawła II”, „ul. Słoneczna”. */
    private static function title(string $value): string
    {
        $value=trim($value);
        if ($value==='' || mb_strtoupper($value,'UTF-8')!==$value) { return $value; }
        $value=mb_convert_case(mb_strtolower($value,'UTF-8'),MB_CASE_TITLE,'UTF-8');
        $value=preg_replace_callback('/\b(Ul|Al|Pl|Os)\.(?=\s)/u',static function (array $m): string { return mb_strtolower($m[0],'UTF-8'); },$value)??$value;
        return preg_replace_callback('/\b([IVX]{1,4})\b/iu',static function (array $m): string { return strtoupper($m[1]); },$value)??$value;
    }

    private function http(string $url,array $headers,?string $body): array
    {
        if (!function_exists('curl_init')) { throw new \RuntimeException('brak rozszerzenia cURL.'); }
        $ch=curl_init($url);
        curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_TIMEOUT=>10,CURLOPT_CONNECTTIMEOUT=>5,CURLOPT_HTTPHEADER=>$headers,CURLOPT_USERAGENT=>'SalesCenter/1.0']);
        if ($body!==null) { curl_setopt($ch,CURLOPT_POST,true); curl_setopt($ch,CURLOPT_POSTFIELDS,$body); }
        $response=curl_exec($ch);
        $status=(int)curl_getinfo($ch,CURLINFO_HTTP_CODE); $error=curl_error($ch);
        curl_close($ch);
        if ($response===false) { throw new \RuntimeException($error?:'brak połączenia.'); }
        return ['status'=>$status,'body'=>(string)$response];
    }
}
