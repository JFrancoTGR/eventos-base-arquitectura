<?php

header('Content-Type: application/json; charset=utf-8');

try {
    require_once __DIR__ . '/../src/bootstrap.php';

    $stmt = $pdo->query('SELECT DATABASE() AS db, NOW() AS server_time');
    $result = $stmt->fetch();

    http_response_code(200);

    echo json_encode([
        'ok' => true,
        'database' => $result['db'],
        'server_time' => $result['server_time'],
    ]);

} catch (Throwable $e) {

    http_response_code(500);

    echo json_encode([
        'ok' => false,
        'message' => 'Database connection failed.',
    ]);
}