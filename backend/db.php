<?php

define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');              
define('DB_NAME', 'heatwave_manager');


function get_db(): mysqli {
    static $conn = null;

    if ($conn !== null) {
        return $conn;
    }

    $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);

    if ($conn->connect_error) {
        // Return a JSON error so the frontend can display it gracefully
        http_response_code(500);
        header('Content-Type: application/json');
        echo json_encode([
            'error'   => 'Database connection failed.',
            'detail'  => $conn->connect_error,
        ]);
        exit;
    }

    $conn->set_charset('utf8mb4');
    return $conn;
}
