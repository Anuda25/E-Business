<?php
$requestUri = $_SERVER['REQUEST_URI'] ?? '/';
$requestPath = parse_url($requestUri, PHP_URL_PATH);
$query = [];
parse_str((string)(parse_url($requestUri, PHP_URL_QUERY) ?? ''), $query);

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST' || $requestPath !== '/' || ($query['action'] ?? '') !== 'payhere_notify') {
    http_response_code(404);
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Not found.']);
    return true;
}

$_GET = ['action' => 'payhere_notify'];
$_SERVER['QUERY_STRING'] = 'action=payhere_notify';
require __DIR__ . '/api.php';
