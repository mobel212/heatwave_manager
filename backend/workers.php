<?php


require_once __DIR__ . '/db.php';
require_once __DIR__ . '/config.php';

// Kill any accidental PHP error output before our JSON header fires
error_reporting(0);
ini_set('display_errors', '0');

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: ' . ALLOW_ORIGIN);
header('Access-Control-Allow-Methods: GET, POST, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(204); exit; }

$db     = get_db();
$method = $_SERVER['REQUEST_METHOD'];

// ── GET: list workers (optionally filtered by site_id) ───────────────────────
if ($method === 'GET') {
    $site_id = isset($_GET['site_id']) ? (int) $_GET['site_id'] : null;

    if ($site_id !== null && $site_id > 0) {
        // Filtered by site
        $stmt = $db->prepare(
            "SELECT w.id, w.site_id, s.name AS site_name,
                    w.name, w.phone, w.telegram_chat_id,
                    w.last_checkin, w.created_at
             FROM   workers w
             LEFT JOIN sites s ON s.id = w.site_id
             WHERE  w.site_id = ?
             ORDER  BY w.name ASC"
        );
        $stmt->bind_param('i', $site_id);
        $stmt->execute();
        $result = $stmt->get_result();
    } else {
        // All workers – include site name via JOIN
        $result = $db->query(
            "SELECT w.id, w.site_id, s.name AS site_name,
                    w.name, w.phone, w.telegram_chat_id,
                    w.last_checkin, w.created_at
             FROM   workers w
             LEFT JOIN sites s ON s.id = w.site_id
             ORDER  BY s.name ASC, w.name ASC"
        );
    }

    $workers = [];
    while ($row = $result->fetch_assoc()) {
        $row['id']      = (int) $row['id'];
        $row['site_id'] = (int) $row['site_id'];
        $workers[] = $row;
    }

    echo json_encode($workers);
    exit;
}

if ($method === 'POST') {
    $body = json_decode(file_get_contents('php://input'), true) ?? [];

    $name             = trim($body['name']             ?? '');
    $phone            = trim($body['phone']            ?? '');
    $telegram_chat_id = trim($body['telegram_chat_id'] ?? '');
    $site_id          = isset($body['site_id']) ? (int) $body['site_id'] : 1; // default to site 1

    if ($name === '') {
        http_response_code(400);
        echo json_encode(['error' => 'Worker name is required.']);
        exit;
    }
    if ($site_id <= 0) {
        http_response_code(400);
        echo json_encode(['error' => 'A valid site must be selected.']);
        exit;
    }

    // Verify the site exists
    $site_check = $db->prepare("SELECT id FROM sites WHERE id = ?");
    $site_check->bind_param('i', $site_id);
    $site_check->execute();
    if ($site_check->get_result()->num_rows === 0) {
        http_response_code(400);
        echo json_encode(['error' => 'Selected site does not exist.']);
        exit;
    }

    $stmt = $db->prepare(
        "INSERT INTO workers (site_id, name, phone, telegram_chat_id)
         VALUES (?, ?, ?, ?)"
    );
    $stmt->bind_param('isss', $site_id, $name, $phone, $telegram_chat_id);

    if (!$stmt->execute()) {
        $code = $db->errno;
        http_response_code($code === 1062 ? 409 : 500);
        echo json_encode([
            'error' => $code === 1062
                ? 'A worker with that Telegram Chat ID already exists.'
                : 'Database error: ' . $db->error,
        ]);
        exit;
    }

    echo json_encode([
        'success' => true,
        'id'      => (int) $db->insert_id,
        'message' => "Worker \"$name\" added successfully.",
    ]);
    exit;
}

if ($method === 'DELETE') {
    $id = (int) ($_GET['id'] ?? 0);
    if ($id <= 0) {
        http_response_code(400);
        echo json_encode(['error' => 'Valid worker id required.']);
        exit;
    }

    $stmt = $db->prepare("DELETE FROM workers WHERE id = ?");
    $stmt->bind_param('i', $id);
    $stmt->execute();

    echo json_encode(['success' => true, 'deleted' => (int) $stmt->affected_rows]);
    exit;
}

http_response_code(405);
echo json_encode(['error' => 'Method not allowed.']);