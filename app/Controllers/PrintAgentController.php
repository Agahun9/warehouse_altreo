<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Models\OrderRepository;
use App\Models\PrintAgentRepository;
use App\Services\OrderShipmentService;
use InvalidArgumentException;

final class PrintAgentController extends Controller
{
    private function repository(): PrintAgentRepository
    {
        $repository=new PrintAgentRepository($this->db());
        $repository->ensureSchema();
        return $repository;
    }

    public function health(): void
    {
        if (!$this->method('GET')) { return; }
        $repository=$this->repository(); $station=$this->station($repository);
        if (!$station) { return; }
        $repository->touch((int)$station['id']);
        $this->json(['ok'=>true,'station'=>$station['name']]);
    }

    public function heartbeat(): void
    {
        if (!$this->method('POST')) { return; }
        $repository=$this->repository(); $station=$this->station($repository);
        if (!$station) { return; }
        try { $this->json($repository->heartbeat((int)$station['id'],$this->body(),$this->stationName())); }
        catch (\Throwable $e) { $this->json(['error'=>'Nie udało się zapisać listy drukarek.'],422); }
    }

    public function next(): void
    {
        if (!$this->method('GET')) { return; }
        $repository=$this->repository(); $station=$this->station($repository);
        if (!$station) { return; }
        $repository->touch((int)$station['id']);
        $job=$repository->nextJob((int)$station['id']);
        if (!$job) { http_response_code(204); header('Cache-Control: no-store'); return; }
        $this->json($job);
    }

    public function status(): void
    {
        if (!$this->method('POST')) { return; }
        $repository=$this->repository(); $station=$this->station($repository);
        if (!$station) { return; }
        $id=(string)($_GET['job_id']??'');
        if (preg_match('/^[0-9a-f-]{36}$/Di',$id)!==1) { $this->json(['error'=>'Nieprawidłowe ID zadania.'],404); return; }
        $body=$this->body();
        try {
            if (!$repository->report((int)$station['id'],$id,(string)($body['status']??''),(string)($body['message']??''))) { $this->json(['error'=>'Nie znaleziono zadania.'],404); return; }
            $repository->touch((int)$station['id']);
            $this->json(['ok'=>true]);
        } catch (InvalidArgumentException $e) { $this->json(['error'=>$e->getMessage()],409); }
    }

    public function pdf(): void
    {
        if (!$this->method('GET')) { return; }
        $id=(string)($_GET['job_id']??''); $token=(string)($_GET['token']??'');
        if (preg_match('/^[0-9a-f-]{36}$/Di',$id)!==1) { http_response_code(404); return; }
        $job=$this->repository()->downloadableJob($id,$token);
        if (!$job) { http_response_code(403); header('Content-Type: text/plain; charset=utf-8'); echo 'Link do etykiety jest nieprawidłowy lub wygasł.'; return; }
        try {
            $orders=new OrderRepository($this->db()); $orders->ensureSchema();
            $pageSize=strpos((string)$job['print_settings'],'A4')!==false?'A4':'A6';
            $label=(new OrderShipmentService($orders))->label((int)$job['shipment_id'],$pageSize);
            header('Content-Type: '.$label['mime']);
            header('Content-Disposition: inline; filename="'.$label['name'].'"');
            header('Cache-Control: no-store, private');
            echo $label['bytes'];
        } catch (\Throwable $e) {
            http_response_code(409); header('Content-Type: text/plain; charset=utf-8'); echo 'Nie udało się pobrać etykiety przesyłki.';
        }
    }

    public function fiscalnext(): void
    {
        if (!$this->method('GET')) { return; }
        $repository=$this->repository(); $station=$this->station($repository);
        if (!$station) { return; }
        $repository->touch((int)$station['id']);
        $environment=(string)($_SERVER['HTTP_X_FISCAL_ENVIRONMENT']??$_SERVER['HTTP_X_AGENT_ENVIRONMENT']??'sandbox');
        $job=$repository->nextFiscalJob((int)$station['id'],$environment);
        if (!$job) { http_response_code(204); header('Cache-Control: no-store'); return; }
        $this->json($job);
    }

    public function fiscalstatus(): void
    {
        if (!$this->method('POST')) { return; }
        $repository=$this->repository(); $station=$this->station($repository);
        if (!$station) { return; }
        $id=(string)($_GET['job_id']??'');
        if (preg_match('/^[0-9a-f-]{36}$/Di',$id)!==1) { $this->json(['error'=>'Nieprawidłowe ID zadania.'],404); return; }
        $body=$this->body();
        try {
            if (!$repository->reportFiscal((int)$station['id'],$id,(string)($body['status']??''),(string)($body['message']??''),isset($body['fiscalNumber'])?(string)$body['fiscalNumber']:null)) { $this->json(['error'=>'Nie znaleziono zadania.'],404); return; }
            $repository->touch((int)$station['id']); $this->json(['ok'=>true]);
        } catch (InvalidArgumentException $e) { $this->json(['error'=>$e->getMessage()],409); }
    }

    private function station(PrintAgentRepository $repository): ?array
    {
        $station=$repository->authenticate($this->bearerToken());
        if (!$station) { $this->json(['error'=>'Nieprawidłowy lub wyłączony token stanowiska.'],401); return null; }
        return $station;
    }

    private function stationName(): string
    {
        return mb_substr(trim((string)($_SERVER['HTTP_X_STATION_NAME']??'')),0,150,'UTF-8');
    }

    private function body(): array
    {
        $raw=file_get_contents('php://input');
        if ($raw===false || $raw==='') { return []; }
        try { $body=json_decode($raw,true,512,JSON_THROW_ON_ERROR); return is_array($body)?$body:[]; }
        catch (\JsonException $e) { return []; }
    }

    private function method(string $expected): bool
    {
        if ($this->requestMethod()===$expected) { return true; }
        $this->json(['error'=>'Niedozwolona metoda.'],405); return false;
    }

    private function json(array $data,int $status=200): void
    {
        http_response_code($status); header('Content-Type: application/json; charset=utf-8'); header('Cache-Control: no-store');
        echo json_encode($data,JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR);
    }
}
