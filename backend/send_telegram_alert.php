<?php


require_once __DIR__ . '/db.php';
require_once __DIR__ . '/config.php';

error_reporting(0);
ini_set('display_errors', '0');

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: ' . ALLOW_ORIGIN);
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(204); exit; }

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'POST required.']);
    exit;
}

// ── Parse body ────────────────────────────────────────────────────────────────
$body      = json_decode(file_get_contents('php://input'), true) ?? [];
$custom    = trim($body['message']   ?? '');
$site_id   = isset($body['site_id']) ? (int) $body['site_id']   : 0;
$site_lat  = isset($body['lat'])     ? (float) $body['lat']     : SITE_LATITUDE;
$site_lng  = isset($body['lng'])     ? (float) $body['lng']     : SITE_LONGITUDE;
$site_name = trim($body['site_name'] ?? SITE_LABEL);

if ($site_id <= 0) {
    http_response_code(400);
    echo json_encode(['error' => 'A valid site_id is required.']);
    exit;
}

// ── Determine current risk for this site ─────────────────────────────────────
$risk_level = 'Unknown';
try {
    $url = "https://api.open-meteo.com/v1/forecast?"
         . "latitude={$site_lat}&longitude={$site_lng}"
         . "&hourly=apparent_temperature&timezone=auto&forecast_days=1";

    $ch = curl_init($url);
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 8]);
    $raw = curl_exec($ch);
    curl_close($ch);

    $wd = json_decode($raw, true);

    // Find the current hour slot
    $now_ts = time();
    $idx    = 0;
    if (isset($wd['hourly']['time'])) {
        foreach ($wd['hourly']['time'] as $i => $t) {
            if (strtotime($t) <= $now_ts) $idx = $i;
            else break;
        }
    }

    if (isset($wd['hourly']['apparent_temperature'][$idx])) {
        $hi = (float) $wd['hourly']['apparent_temperature'][$idx];
        if ($hi >= 40)     $risk_level = 'Red';
        elseif ($hi >= 32) $risk_level = 'Yellow';
        else               $risk_level = 'Green';
    }
} catch (Exception $e) {
    // Non-fatal – we'll still send the alert
}

// ── Build message text ────────────────────────────────────────────────────────
$emoji_map = ['Red' => '🔴', 'Yellow' => '🟡', 'Green' => '🟢', 'Unknown' => '⚪'];
$emoji     = $emoji_map[$risk_level] ?? '⚪';
$timestamp = date('Y-m-d H:i');

$default_msg = "{$emoji} *HEAT STRESS ALERT* – {$risk_level} Level\n"
             . "Site: *{$site_name}*\n"
             . "Time: {$timestamp}\n\n"
             . "⚠️ Please take immediate precautions:\n"
             . "• Drink water every 15–20 minutes\n"
             . "• Seek shade or air-conditioned areas\n"
             . "• Watch for heat exhaustion symptoms\n\n"
             . "Reply *OK* to confirm you are safe.";

$message_text = $custom !== '' ? $custom : $default_msg;

// ── Fetch workers for this site that have a Telegram chat ID ─────────────────
$db = get_db();

$stmt = $db->prepare(
    "SELECT id, name, telegram_chat_id
     FROM   workers
     WHERE  site_id = ?
       AND  telegram_chat_id IS NOT NULL
       AND  telegram_chat_id != ''"
);
$stmt->bind_param('i', $site_id);
$stmt->execute();
$result = $stmt->get_result();

$workers = [];
while ($row = $result->fetch_assoc()) {
    $workers[] = $row;
}

// ── Log the alert ─────────────────────────────────────────────────────────────
$log = $db->prepare(
    "INSERT INTO alerts (site_id, risk_level, message) VALUES (?, ?, ?)"
);
$log->bind_param('iss', $site_id, $risk_level, $message_text);
$log->execute();
$alert_id = (int) $db->insert_id;

// ── Send via Telegram Bot API ─────────────────────────────────────────────────
$api_url = TELEGRAM_API_BASE . '/sendMessage';
$sent    = 0;
$skipped = 0;
$errors  = [];

foreach ($workers as $worker) {
    $chat_id = $worker['telegram_chat_id'];
    if (empty($chat_id)) { $skipped++; continue; }

    $payload = json_encode([
        'chat_id'    => $chat_id,
        'text'       => $message_text,
        'parse_mode' => 'Markdown',
    ]);

    $ch = curl_init($api_url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => $payload,
        CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
        CURLOPT_TIMEOUT        => 10,
        CURLOPT_SSL_VERIFYPEER => true,
    ]);
    $resp     = curl_exec($ch);
    $curl_err = curl_error($ch);
    curl_close($ch);

    if ($curl_err) {
        $errors[] = "Worker [{$worker['name']}]: cURL – {$curl_err}";
        continue;
    }

    $tg = json_decode($resp, true);
    if (!($tg['ok'] ?? false)) {
        $errors[] = "Worker [{$worker['name']}]: " . ($tg['description'] ?? 'Telegram error');
        continue;
    }

    $sent++;
}

echo json_encode([
    'success'    => true,
    'alert_id'   => $alert_id,
    'site_id'    => $site_id,
    'site_name'  => $site_name,
    'risk_level' => $risk_level,
    'sent'       => $sent,
    'skipped'    => $skipped,
    'total'      => count($workers),
    'errors'     => $errors,
]);