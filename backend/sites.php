<?php

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/config.php';

// ── CORS ─────────────────────────────────────────────────────────────────────
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: ' . ALLOW_ORIGIN);
header('Access-Control-Allow-Methods: GET, POST, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(204); exit; }

$db     = get_db();
$method = $_SERVER['REQUEST_METHOD'];

// ── GET: return all sites with worker counts ──────────────────────────────────
if ($method === 'GET') {
    $result = $db->query(
        "SELECT s.id, s.name, s.latitude, s.longitude, s.created_at,
                COUNT(w.id) AS worker_count
         FROM   sites s
         LEFT JOIN workers w ON w.site_id = s.id
         GROUP  BY s.id
         ORDER  BY s.created_at ASC"
    );

    $sites = [];
    while ($row = $result->fetch_assoc()) {
        // Cast numeric strings to proper types for clean JSON
        $row['id']           = (int)   $row['id'];
        $row['latitude']     = (float) $row['latitude'];
        $row['longitude']    = (float) $row['longitude'];
        $row['worker_count'] = (int)   $row['worker_count'];
        $sites[] = $row;
    }

    echo json_encode($sites);
    exit;
}

// ── POST: create a new site ───────────────────────────────────────────────────
if ($method === 'POST') {
    $body = json_decode(file_get_contents('php://input'), true) ?? [];

    $name      = trim($body['name']      ?? '');
    $latitude  = isset($body['latitude'])  ? (float) $body['latitude']  : null;
    $longitude = isset($body['longitude']) ? (float) $body['longitude'] : null;

    // Validate
    if ($name === '') {
        http_response_code(400);
        echo json_encode(['error' => 'Site name is required.']);
        exit;
    }
    if ($latitude === null || $longitude === null) {
        http_response_code(400);
        echo json_encode(['error' => 'Latitude and longitude are required.']);
        exit;
    }
    if ($latitude < -90 || $latitude > 90) {
        http_response_code(400);
        echo json_encode(['error' => 'Latitude must be between -90 and 90.']);
        exit;
    }
    if ($longitude < -180 || $longitude > 180) {
        http_response_code(400);
        echo json_encode(['error' => 'Longitude must be between -180 and 180.']);
        exit;
    }

    $stmt = $db->prepare(
        "INSERT INTO sites (name, latitude, longitude) VALUES (?, ?, ?)"
    );
    $stmt->bind_param('sdd', $name, $latitude, $longitude);

    if (!$stmt->execute()) {
        http_response_code(500);
        echo json_encode(['error' => 'Database error: ' . $db->error]);
        exit;
    }

    $new_id = (int) $db->insert_id;
    echo json_encode([
        'success' => true,
        'id'      => $new_id,
        'message' => "Site \"$name\" created successfully.",
    ]);
    exit;
}

if ($method === 'DELETE') {
    $id = (int) ($_GET['id'] ?? 0);
    if ($id <= 0) {
        http_response_code(400);
        echo json_encode(['error' => 'Valid site id required.']);
        exit;
    }
    if ($id === 1) {
        http_response_code(400);
        echo json_encode(['error' => 'Cannot delete the Default Site.']);
        exit;
    }

    // Check if any workers are still assigned to this site
    $check = $db->prepare("SELECT COUNT(*) AS cnt FROM workers WHERE site_id = ?");
    $check->bind_param('i', $id);
    $check->execute();
    $cnt = (int) $check->get_result()->fetch_assoc()['cnt'];

    if ($cnt > 0) {
        http_response_code(409);
        echo json_encode([
            'error' => "Cannot delete: $cnt worker(s) are still assigned to this site. "
                     . "Move or remove them first.",
        ]);
        exit;
    }

    $stmt = $db->prepare("DELETE FROM sites WHERE id = ?");
    $stmt->bind_param('i', $id);
    $stmt->execute();

    echo json_encode(['success' => true, 'deleted' => $stmt->affected_rows]);
    exit;
}

http_response_code(405);
echo json_encode(['error' => 'Method not allowed.']);