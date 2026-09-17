<?php

declare(strict_types=1);
namespace App\Services;

use InvalidArgumentException;
use RuntimeException;

/**
 * KSeF API 2.0 client: KSeF-token authentication, interactive (online) sessions,
 * invoice status and UPO. Transport can be injected for offline tests.
 */
final class KsefClient
{
    public const ENVIRONMENTS=[
        'sandbox'=>'https://api-test.ksef.mf.gov.pl/v2',
        'production'=>'https://api.ksef.mf.gov.pl/v2',
    ];
    public const FORM_CODE=['systemCode'=>'FA (3)','schemaVersion'=>'1-0E','value'=>'FA'];

    private $environment;
    private $base;
    private $transport;
    private $sleep;

    /** @param callable|null $transport fn(string $method,string $url,array $headers,?string $body): array{0:int,1:string} */
    public function __construct(string $environment,?callable $transport=null,?callable $sleep=null)
    {
        if (!isset(self::ENVIRONMENTS[$environment])) { throw new InvalidArgumentException('Nieznane środowisko KSeF.'); }
        $this->environment=$environment;
        $this->base=self::ENVIRONMENTS[$environment];
        $this->transport=$transport;
        $this->sleep=$sleep?:static function (int $ms): void { usleep($ms*1000); };
    }

    public function environment(): string { return $this->environment; }

    /** Public certificates of the Ministry of Finance, keyed by usage. */
    public function publicKeys(): array
    {
        $keys=[];
        foreach ($this->json('GET','/security/public-key-certificates') as $certificate) {
            if (!is_array($certificate) || empty($certificate['certificate'])) { continue; }
            foreach ((array)($certificate['usage']??[]) as $usage) {
                $validTo=strtotime((string)($certificate['validTo']??'')) ?: PHP_INT_MAX;
                if ($validTo<time()) { continue; }
                if (!isset($keys[$usage]) || $validTo>$keys[$usage]['valid_to']) {
                    $keys[$usage]=['certificate'=>(string)$certificate['certificate'],'public_key_id'=>(string)($certificate['publicKeyId']??''),'valid_to'=>$validTo];
                }
            }
        }
        foreach (['KsefTokenEncryption','SymmetricKeyEncryption'] as $usage) {
            if (!isset($keys[$usage])) { throw new RuntimeException('KSeF nie zwrócił ważnego klucza publicznego ('.$usage.').'); }
        }
        return $keys;
    }

    /** Full KSeF-token flow; returns ['token'=>JWT access token,'valid_until'=>ISO date]. */
    public function authenticate(string $nip,string $ksefToken,array $keys): array
    {
        $challenge=$this->json('POST','/auth/challenge',[],'{}');
        $timestamp=(int)($challenge['timestampMs']??0);
        if (empty($challenge['challenge']) || $timestamp<1) { throw new RuntimeException('KSeF nie zwrócił wyzwania uwierzytelnienia.'); }
        $key=$keys['KsefTokenEncryption'];
        $body=[
            'challenge'=>(string)$challenge['challenge'],
            'contextIdentifier'=>['type'=>'Nip','value'=>$nip],
            'encryptedToken'=>base64_encode(self::rsaOaepEncrypt($ksefToken.'|'.$timestamp,$key['certificate'])),
        ];
        if ($key['public_key_id']!=='') { $body['publicKeyId']=$key['public_key_id']; }
        $init=$this->json('POST','/auth/ksef-token',[],self::encode($body));
        $authToken=(string)($init['authenticationToken']['token']??'');
        $reference=(string)($init['referenceNumber']??'');
        if ($authToken==='' || $reference==='') { throw new RuntimeException('KSeF nie rozpoczął uwierzytelnienia.'); }
        for ($attempt=0;;$attempt++) {
            $status=$this->json('GET','/auth/'.rawurlencode($reference),$this->bearer($authToken));
            $code=(int)($status['status']['code']??0);
            if ($code===200) { break; }
            if ($code!==100) { throw new InvalidArgumentException('KSeF odrzucił uwierzytelnienie: '.self::statusText($status['status']??[])); }
            if ($attempt>=20) { throw new RuntimeException('Uwierzytelnienie w KSeF trwa zbyt długo. Spróbuj ponownie za chwilę.'); }
            ($this->sleep)(min(2000,500+$attempt*250));
        }
        $tokens=$this->json('POST','/auth/token/redeem',$this->bearer($authToken),'{}');
        if (empty($tokens['accessToken']['token'])) { throw new RuntimeException('KSeF nie wydał tokena dostępowego.'); }
        return ['token'=>(string)$tokens['accessToken']['token'],'valid_until'=>(string)($tokens['accessToken']['validUntil']??'')];
    }

    /** Opens an online session; returns reference number and the AES key/IV needed to encrypt invoices. */
    public function openSession(string $accessToken,array $keys): array
    {
        $aesKey=random_bytes(32); $iv=random_bytes(16);
        $key=$keys['SymmetricKeyEncryption'];
        $encryption=['encryptedSymmetricKey'=>base64_encode(self::rsaOaepEncrypt($aesKey,$key['certificate'])),'initializationVector'=>base64_encode($iv)];
        if ($key['public_key_id']!=='') { $encryption['publicKeyId']=$key['public_key_id']; }
        $response=$this->json('POST','/sessions/online',$this->bearer($accessToken),self::encode(['formCode'=>self::FORM_CODE,'encryption'=>$encryption]));
        if (empty($response['referenceNumber'])) { throw new RuntimeException('KSeF nie otworzył sesji interaktywnej.'); }
        return ['reference'=>(string)$response['referenceNumber'],'key'=>$aesKey,'iv'=>$iv];
    }

    /** Sends one invoice XML in an open session; returns invoice reference number and SHA-256 hash (Base64). */
    public function sendInvoice(string $accessToken,array $session,string $xml): array
    {
        $encrypted=openssl_encrypt($xml,'aes-256-cbc',$session['key'],OPENSSL_RAW_DATA,$session['iv']);
        if ($encrypted===false) { throw new RuntimeException('Nie udało się zaszyfrować faktury.'); }
        $hash=base64_encode(hash('sha256',$xml,true));
        $body=['invoiceHash'=>$hash,'invoiceSize'=>strlen($xml),'encryptedInvoiceHash'=>base64_encode(hash('sha256',$encrypted,true)),'encryptedInvoiceSize'=>strlen($encrypted),'encryptedInvoiceContent'=>base64_encode($encrypted)];
        $response=$this->json('POST','/sessions/online/'.rawurlencode($session['reference']).'/invoices',$this->bearer($accessToken),self::encode($body));
        if (empty($response['referenceNumber'])) { throw new RuntimeException('KSeF nie przyjął faktury do przetwarzania.'); }
        return ['reference'=>(string)$response['referenceNumber'],'hash'=>$hash];
    }

    public function closeSession(string $accessToken,string $sessionReference): void
    {
        $this->json('POST','/sessions/online/'.rawurlencode($sessionReference).'/close',$this->bearer($accessToken),'{}');
    }

    public function invoiceStatus(string $accessToken,string $sessionReference,string $invoiceReference): array
    {
        return $this->json('GET','/sessions/'.rawurlencode($sessionReference).'/invoices/'.rawurlencode($invoiceReference),$this->bearer($accessToken));
    }

    public function upo(string $accessToken,string $sessionReference,string $invoiceReference): string
    {
        [$status,$raw]=$this->send('GET','/sessions/'.rawurlencode($sessionReference).'/invoices/'.rawurlencode($invoiceReference).'/upo',$this->bearer($accessToken)+['Accept'=>'application/xml'],null);
        if ($status<200 || $status>=300 || trim($raw)==='') { $this->fail($status,$raw); }
        return $raw;
    }

    /** RSA-OAEP with SHA-256 (MGF1-SHA256) as required by KSeF, working on every PHP 7.4+ OpenSSL build. */
    public static function rsaOaepEncrypt(string $data,string $certificateBase64): string
    {
        $pem="-----BEGIN CERTIFICATE-----\n".chunk_split(preg_replace('/\s+/','',$certificateBase64)??'',64,"\n")."-----END CERTIFICATE-----\n";
        $key=openssl_pkey_get_public($pem);
        if ($key===false) { throw new RuntimeException('Nieprawidłowy certyfikat klucza publicznego KSeF.'); }
        $details=openssl_pkey_get_details($key);
        if (!is_array($details) || ($details['type']??null)!==OPENSSL_KEYTYPE_RSA) { throw new RuntimeException('Klucz publiczny KSeF nie jest kluczem RSA.'); }
        $k=intdiv((int)$details['bits']+7,8); $hLen=32;
        if (strlen($data)>$k-2*$hLen-2) { throw new RuntimeException('Dane są za długie do szyfrowania RSA.'); }
        $db=hash('sha256','',true).str_repeat("\0",$k-strlen($data)-2*$hLen-2)."\x01".$data;
        $seed=random_bytes($hLen);
        $maskedDb=$db^self::mgf1($seed,$k-$hLen-1);
        $maskedSeed=$seed^self::mgf1($maskedDb,$hLen);
        if (!openssl_public_encrypt("\0".$maskedSeed.$maskedDb,$encrypted,$key,OPENSSL_NO_PADDING)) { throw new RuntimeException('Szyfrowanie RSA-OAEP nie powiodło się.'); }
        return $encrypted;
    }

    private static function mgf1(string $seed,int $length): string
    {
        $mask='';
        for ($counter=0;strlen($mask)<$length;$counter++) { $mask.=hash('sha256',$seed.pack('N',$counter),true); }
        return substr($mask,0,$length);
    }

    public static function statusText(array $status): string
    {
        $text=trim((string)($status['description']??'')).(isset($status['code'])?' ('.(int)$status['code'].')':'');
        $details=array_filter(array_map('strval',(array)($status['details']??[])));
        if ($details) { $text.=': '.implode('; ',$details); }
        return mb_substr(self::clean($text),0,900,'UTF-8');
    }

    private function bearer(string $token): array { return ['Authorization'=>'Bearer '.$token]; }

    private static function encode(array $body): string { return json_encode($body,JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR); }

    private function json(string $method,string $path,array $headers=[],?string $body=null): array
    {
        [$status,$raw]=$this->send($method,$path,$headers+['Accept'=>'application/json'],$body);
        if ($status<200 || $status>=300) { $this->fail($status,$raw); }
        if (trim($raw)==='') { return []; }
        $decoded=json_decode($raw,true);
        if (!is_array($decoded)) { throw new RuntimeException('KSeF zwrócił nieczytelną odpowiedź (HTTP '.$status.').'); }
        return $decoded;
    }

    private function send(string $method,string $path,array $headers,?string $body): array
    {
        $headers+=['X-Error-Format'=>'problem-details'];
        if ($body!==null) { $headers+=['Content-Type'=>'application/json']; }
        $url=$this->base.$path;
        if ($this->transport) { return ($this->transport)($method,$url,$headers,$body); }
        if (!function_exists('curl_init')) { throw new RuntimeException('Brak rozszerzenia cURL wymaganego do połączenia z KSeF.'); }
        $ch=curl_init($url);
        if ($ch===false) { throw new RuntimeException('Nie można uruchomić połączenia z KSeF.'); }
        $lines=[]; foreach ($headers as $name=>$value) { $lines[]=$name.': '.$value; }
        curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_CUSTOMREQUEST=>$method,CURLOPT_HTTPHEADER=>$lines,CURLOPT_TIMEOUT=>60,CURLOPT_CONNECTTIMEOUT=>15,CURLOPT_USERAGENT=>'SalesCenter/KSeF']);
        if ($body!==null) { curl_setopt($ch,CURLOPT_POSTFIELDS,$body); }
        $raw=curl_exec($ch); $status=(int)curl_getinfo($ch,CURLINFO_HTTP_CODE); $error=curl_error($ch);
        if (PHP_VERSION_ID<80000) { curl_close($ch); }
        if (!is_string($raw)) { throw new RuntimeException('Brak połączenia z KSeF'.($error!==''?': '.$error:'').'.'); }
        return [$status,$raw];
    }

    /** Converts KSeF problem-details / exception payloads into a readable message. */
    private function fail(int $status,string $raw): void
    {
        $decoded=json_decode($raw,true); $messages=[];
        if (is_array($decoded)) {
            foreach ((array)($decoded['exception']['exceptionDetailList']??[]) as $detail) {
                $messages[]=trim((string)($detail['exceptionDescription']??'').' '.implode('; ',array_map('strval',(array)($detail['details']??[]))));
            }
            foreach (['title','detail'] as $field) { if (!empty($decoded[$field]) && is_string($decoded[$field])) { $messages[]=$decoded[$field]; } }
            foreach ((array)($decoded['errors']??[]) as $field=>$errors) {
                $messages[]=(is_string($field)?$field.': ':'').(is_array($errors)?implode('; ',array_map(static function ($e) { return is_scalar($e)?(string)$e:json_encode($e,JSON_UNESCAPED_UNICODE); },$errors)):(string)$errors);
            }
            if (isset($decoded['status']) && is_array($decoded['status'])) { $messages[]=self::statusText($decoded['status']); }
        }
        $message=mb_substr(self::clean(implode(' · ',array_unique(array_filter(array_map('trim',$messages))))),0,900,'UTF-8');
        if ($status===401) { throw new KsefAuthException('KSeF odrzucił autoryzację (HTTP 401)'.($message!==''?': '.$message:'').'.'); }
        if ($status===429) { throw new InvalidArgumentException('Przekroczono limit zapytań KSeF. Spróbuj ponownie za kilka minut.'); }
        if ($status>=400 && $status<500) { throw new InvalidArgumentException('KSeF odrzucił żądanie (HTTP '.$status.')'.($message!==''?': '.$message:'').'.'); }
        throw new RuntimeException('KSeF jest chwilowo niedostępny (HTTP '.$status.')'.($message!==''?': '.$message:'').'.');
    }

    private static function clean(string $text): string { return trim(preg_replace('/[\x00-\x1F\x7F]+/u',' ',$text)??''); }
}
