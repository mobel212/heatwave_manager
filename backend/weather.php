<?php

require_once __DIR__ . '/config.php';

error_reporting(0);
ini_set('display_errors', '0');

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: ' . ALLOW_ORIGIN);
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(204); exit; }

// ── Resolve coordinates: query-string wins, config is fallback ───────────────
$lat   = isset($_GET['lat'])   ? (float) $_GET['lat']   : SITE_LATITUDE;
$lon   = isset($_GET['lng'])   ? (float) $_GET['lng']   : SITE_LONGITUDE;
$label = isset($_GET['label']) ? trim($_GET['label'])   : SITE_LABEL;

if ($lat < -90 || $lat > 90 || $lon < -180 || $lon > 180) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid coordinates supplied.']);
    exit;
}

$api_url = "https://api.open-meteo.com/v1/forecast?"
         . "latitude={$lat}&longitude={$lon}"
         . "&hourly=temperature_2m,relativehumidity_2m,apparent_temperature"
         . "&timezone=auto"
         . "&forecast_days=2";   // 2 days so we always have 24 h ahead

$ch = curl_init($api_url);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT        => 10,
    CURLOPT_FOLLOWLOCATION => true,
    CURLOPT_SSL_VERIFYPEER => true,
]);
$raw  = curl_exec($ch);
$err  = curl_error($ch);
$code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($err || $code !== 200) {
    http_response_code(502);
    echo json_encode([
        'error'  => 'Failed to fetch weather data from Open-Meteo.',
        'detail' => $err ?: "HTTP {$code}",
    ]);
    exit;
}

$data = json_decode($raw, true);
if (!isset($data['hourly'])) {
    http_response_code(502);
    echo json_encode(['error' => 'Unexpected response format from Open-Meteo.']);
    exit;
}

$times      = $data['hourly']['time'];
$temps      = $data['hourly']['temperature_2m'];
$humidities = $data['hourly']['relativehumidity_2m'];
$apparent   = $data['hourly']['apparent_temperature'];

// ── Risk level helper ─────────────────────────────────────────────────────────
function risk_level(float $feels_like): string {
    if ($feels_like >= 40.0) return 'Red';
    if ($feels_like >= 32.0) return 'Yellow';
    return 'Green';
}

// ── Find the "current" hour slot (latest past-or-present hour) ────────────────
$now_ts      = time();
$current_idx = 0;
foreach ($times as $i => $t) {
    if (strtotime($t) <= $now_ts) $current_idx = $i;
    else break;
}

// ── Build hourly array (next 24 hours from current) ──────────────────────────
$hourly = [];
$limit  = min($current_idx + 24, count($times) - 1);
for ($i = $current_idx; $i <= $limit; $i++) {
    $hi       = (float) $apparent[$i];
    $hourly[] = [
        'time'                 => $times[$i],
        'temperature'          => round((float) $temps[$i], 1),
        'humidity'             => (int) $humidities[$i],
        'apparent_temperature' => round($hi, 1),
        'heat_index'           => round($hi, 1),
        'risk'                 => risk_level($hi),
    ];
}

$current = $hourly[0];

echo json_encode([
    'location'  => $label,
    'latitude'  => $lat,
    'longitude' => $lon,
    'current'   => $current,
    'hourly'    => $hourly,
], JSON_PRETTY_PRINT);