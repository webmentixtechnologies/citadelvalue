<?php
/**
 * CitadelValue API Proxy  v2
 * Upload to: http://citadelvalue.com/proxy.php
 */
header('Access-Control-Allow-Origin: *');
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(204); exit; }

function jsonErr($msg, $code=400) {
    http_response_code($code);
    echo json_encode(['s'=>'error','error'=>$msg,'code'=>$code]);
    exit;
}

$target = isset($_GET['url'])  ? trim($_GET['url'])  : '';
$auth   = isset($_GET['auth']) ? trim($_GET['auth']) : '';
if (empty($target)) jsonErr('Missing url param');

$allowed = ['api-t1.fyers.in','api-t2.fyers.in','query1.finance.yahoo.com','query2.finance.yahoo.com'];
$host = strtolower(parse_url($target, PHP_URL_HOST));
$ok = false;
foreach ($allowed as $h) { if ($host === $h) { $ok = true; break; } }
if (!$ok) jsonErr('Host not whitelisted: '.$host, 403);

$ch = curl_init();
$hdrs = ['Accept: application/json, */*', 'Connection: keep-alive'];
if (!empty($auth) && strpos($host,'fyers.in') !== false) $hdrs[] = 'Authorization: '.$auth;
$ua = (strpos($host,'yahoo') !== false)
    ? 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 Chrome/124.0.0.0 Safari/537.36'
    : 'Mozilla/5.0 (compatible; CitadelValue/2.0)';
curl_setopt_array($ch, [
    CURLOPT_URL            => $target,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_FOLLOWLOCATION => true,
    CURLOPT_MAXREDIRS      => 3,
    CURLOPT_SSL_VERIFYPEER => true,
    CURLOPT_TIMEOUT        => 12,
    CURLOPT_CONNECTTIMEOUT => 6,
    CURLOPT_USERAGENT      => $ua,
    CURLOPT_ENCODING       => '',
    CURLOPT_HTTPHEADER     => $hdrs,
]);
$raw = curl_exec($ch);
$httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlErr  = curl_error($ch);
curl_close($ch);

if ($curlErr || $raw === false) { jsonErr('cURL error: '.($curlErr?:'empty'), 502); }

$trimmed = ltrim($raw);
$is_html = (stripos($trimmed,'<!doctype') === 0 || stripos($trimmed,'<html') === 0);
if ($is_html) {
    preg_match('/<title[^>]*>(.*?)<\/title>/is', $raw, $m);
    $title = isset($m[1]) ? trim(strip_tags($m[1])) : 'upstream HTML error';
    echo json_encode(['s'=>'error','error'=>'upstream_html','message'=>$title,'http_status'=>$httpCode,'host'=>$host]);
    exit;
}

if (json_decode($raw) === null && json_last_error() !== JSON_ERROR_NONE) {
    echo json_encode(['s'=>'error','error'=>'invalid_json','message'=>'Non-JSON ('.$httpCode.')','host'=>$host,'preview'=>substr($trimmed,0,150)]);
    exit;
}

http_response_code($httpCode);
echo $raw;
?>
