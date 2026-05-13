<?php


require_once __DIR__ . '/db.php';
require_once __DIR__ . '/config.php';

$input  = file_get_contents('php://input');
$update = json_decode($input, true);

if (empty($update['message'])) { http_response_code(200); exit; }

$message  = $update['message'];
$chat_id  = (string) ($message['chat']['id'] ?? '');
$text     = trim($message['text'] ?? '');
$from     = $message['from'] ?? [];
$username = trim(($from['first_name'] ?? '') . ' ' . ($from['last_name'] ?? ''));
$username = $username ?: "User {$chat_id}";

if ($chat_id === '') { http_response_code(200); exit; }

$db = get_db();

// Log every inbound message
$stmt = $db->prepare("INSERT INTO replies (chat_id, message) VALUES (?, ?)");
$stmt->bind_param('ss', $chat_id, $text);
$stmt->execute();

// ── Route ─────────────────────────────────────────────────────────────────────
if (stripos($text, '/start') === 0) {
    // Register if not already in DB; assign to Default Site (id=1)
    $default_name = "Worker – {$username}";
    $default_site = 1;

    $check = $db->prepare("SELECT id FROM workers WHERE telegram_chat_id = ?");
    $check->bind_param('s', $chat_id);
    $check->execute();

    if ($check->get_result()->num_rows === 0) {
        $ins = $db->prepare(
            "INSERT INTO workers (site_id, name, telegram_chat_id) VALUES (?, ?, ?)"
        );
        $ins->bind_param('iss', $default_site, $default_name, $chat_id);
        $ins->execute();
    }

    $reply = "👷 *Welcome to Heat Stress Manager!*\n\n"
           . "You are now registered (Default Site).\n"
           . "Your Chat ID: `{$chat_id}`\n\n"
           . "When your site manager sends a heat alert, reply *OK* to confirm you are safe.\n\n"
           . "Stay safe! 💧";

} elseif (strtolower($text) === 'ok') {
    $stmt2 = $db->prepare(
        "UPDATE workers SET last_checkin = NOW() WHERE telegram_chat_id = ?"
    );
    $stmt2->bind_param('s', $chat_id);
    $stmt2->execute();

    $reply = "✅ Check-in received at *" . date('H:i') . "*. Thank you – stay hydrated! 💧";

} else {
    $reply = "👷 Message received.\nReply *OK* to check in after a heat alert.\nYour Chat ID: `{$chat_id}`";
}

// ── Reply ─────────────────────────────────────────────────────────────────────
$ch = curl_init(TELEGRAM_API_BASE . '/sendMessage');
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST           => true,
    CURLOPT_POSTFIELDS     => json_encode(['chat_id' => $chat_id, 'text' => $reply, 'parse_mode' => 'Markdown']),
    CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
    CURLOPT_TIMEOUT        => 8,
]);
curl_exec($ch);
curl_close($ch);

http_response_code(200);
echo 'OK';