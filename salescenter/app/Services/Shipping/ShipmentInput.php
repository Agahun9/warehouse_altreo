<?php

declare(strict_types=1);
namespace App\Services\Shipping;

use InvalidArgumentException;
use RuntimeException;

/** Walidacja formularza nadania i wspólny transport HTTP operatorów przesyłek. */
final class ShipmentInput
{
    public static function number(array $input,string $key,float $min,float $max): float { $value=filter_var($input[$key]??null,FILTER_VALIDATE_FLOAT);if($value===false||$value<$min||$value>$max)throw new InvalidArgumentException('Nieprawidłowa wartość pola '.$key.'.');return (float)$value; }
    public static function required(array $input,string $key,int $max): string { $v=trim((string)($input[$key]??''));if($v===''||mb_strlen($v)>$max)throw new InvalidArgumentException('Uzupełnij pole '.$key.'.');return $v; }
    public static function email(array $input,string $key): string { $value=self::required($input,$key,200);if(filter_var($value,FILTER_VALIDATE_EMAIL)===false)throw new InvalidArgumentException('Nieprawidłowy adres e-mail.');return $value; }
    public static function phone(array $input,string $key): string { $value=self::required($input,$key,30);$value=preg_replace('/[\s().-]+/','',$value)??'';if(!preg_match('/^\+?\d{9,20}$/D',$value))throw new InvalidArgumentException('Nieprawidłowy numer telefonu.');return $value; }
    public static function country($value): string { $value=strtoupper(trim((string)$value));if(!preg_match('/^[A-Z]{2}$/D',$value))throw new InvalidArgumentException('Nieprawidłowy kod kraju.');return $value; }
    /** Wymiary paczki w cm i waga w kg – wspólne dla wszystkich operatorów. */
    public static function package(array $input): array { return ['length'=>self::number($input,'length',1,400),'width'=>self::number($input,'width',1,400),'height'=>self::number($input,'height',1,400),'weight'=>self::number($input,'weight',0.01,1000)]; }
    public static function content(int $orderId,array $input,array $defaults): string { $value=trim((string)($input['shipment_content']??$defaults['content']??'Towar'));$value=preg_replace('/\s+[—-]\s+zamówienie ID\s+\d+$/iu','',$value)??$value;$value=mb_substr(trim($value),0,180,'UTF-8');if($value==='')$value='Towar';return $value.' — zamówienie ID '.$orderId; }

    public static function jsonRequest(string $url,array $headers,array $payload): array { return self::request($url,array_merge($headers,['Content-Type: application/json']),json_encode($payload,JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR)); }
    public static function formRequest(string $url,array $payload): array { return self::request($url,['Content-Type: application/x-www-form-urlencoded'],http_build_query($payload)); }
    public static function request(string $url,array $headers,string $body): array { $ch=curl_init($url);if($ch===false)throw new RuntimeException('Nie można uruchomić połączenia z operatorem.');curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_POST=>true,CURLOPT_HTTPHEADER=>$headers,CURLOPT_POSTFIELDS=>$body,CURLOPT_TIMEOUT=>30,CURLOPT_CONNECTTIMEOUT=>10]);$raw=curl_exec($ch);$status=(int)curl_getinfo($ch,CURLINFO_HTTP_CODE);curl_close($ch);$decoded=is_string($raw)?json_decode($raw,true):null;if($status<200||$status>=300||!is_array($decoded)||isset($decoded['status'])&&(int)$decoded['status']!==200){$message=is_array($decoded)?trim((string)($decoded['message']??'')):'';$message=preg_replace('/[\x00-\x1F\x7F]+/u',' ',$message)?:'';$message=mb_substr($message,0,240,'UTF-8');if($message!=='')throw new InvalidArgumentException('Operator przesyłek odrzucił dane: '.$message);throw new RuntimeException('Operator przesyłek odrzucił zlecenie (HTTP '.$status.').');}return $decoded; }
    public static function getJson(string $url,array $headers): array { $raw=self::getBinary($url,array_merge($headers,['Accept: application/json']));$json=json_decode($raw,true);if(!is_array($json))throw new RuntimeException('Operator zwrócił niepoprawny status przesyłki.');return $json; }
    /** Pobiera plik (etykieta, protokół). Przekierowania tylko po HTTPS. */
    public static function getBinary(string $url,array $headers,bool $followRedirects=false): string { $ch=curl_init($url);if($ch===false)throw new RuntimeException('Nie można uruchomić pobierania od operatora.');curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_HTTPHEADER=>$headers,CURLOPT_TIMEOUT=>60,CURLOPT_CONNECTTIMEOUT=>10,CURLOPT_FOLLOWLOCATION=>$followRedirects,CURLOPT_MAXREDIRS=>3]);if($followRedirects&&defined('CURLOPT_REDIR_PROTOCOLS'))curl_setopt($ch,CURLOPT_REDIR_PROTOCOLS,CURLPROTO_HTTPS);$raw=curl_exec($ch);$status=(int)curl_getinfo($ch,CURLINFO_HTTP_CODE);curl_close($ch);if(!is_string($raw)||$status<200||$status>=300)throw new RuntimeException('Operator nie zwrócił pliku lub statusu (HTTP '.$status.').');return $raw; }
}
