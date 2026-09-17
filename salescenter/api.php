<?php
/**
 * REST API własnego sklepu: /api.php/v1/...  (albo api.php?route=v1/...)
 * Autoryzacja: Authorization: Bearer <token z Integracje → Własny sklep (API)>.
 */
declare(strict_types=1);
$route=trim((string)($_GET['route']??''),'/');
if ($route==='') { $route=trim((string)($_SERVER['PATH_INFO']??''),'/'); }
if ($route==='' && preg_match('#/api\.php/(.+)$#i',(string)(parse_url((string)($_SERVER['REQUEST_URI']??''),PHP_URL_PATH)??''),$match)===1) { $route=trim(rawurldecode($match[1]),'/'); }
$_GET['controller']='api';
$_GET['action']='handle';
$_GET['api_route']=$route;
require __DIR__.'/index.php';
