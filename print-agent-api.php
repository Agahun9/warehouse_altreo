<?php

declare(strict_types=1);

$route=trim((string)($_GET['route']??''),'/');
if ($route==='') {
    $pathInfo=trim((string)($_SERVER['PATH_INFO']??''),'/');
    if ($pathInfo!=='') { $route=rawurldecode($pathInfo); }
}
if ($route==='') {
    $requestPath=(string)(parse_url((string)($_SERVER['REQUEST_URI']??''),PHP_URL_PATH)??'');
    if (preg_match('#/print-agent-api\.php/(.+)$#i',$requestPath,$match)===1) { $route=rawurldecode(trim($match[1],'/')); }
}
if ($route==='') { $route='health'; }
if (preg_match('#^(?:health|stations/heartbeat|jobs/next|jobs/[0-9a-f-]{36}/(?:status|pdf)|fiscal/next|fiscal/[0-9a-f-]{36}/status)$#i',$route)!==1) {
    http_response_code(404);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['error'=>'Nieznany endpoint.']);
    exit;
}

$_SERVER['REQUEST_URI']='/api/print-agent/'.$route;
require __DIR__.'/index.php';
