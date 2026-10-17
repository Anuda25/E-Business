<?php
require_once __DIR__ . '/config.php';

function getDB() {
    static $conn = null;
    if ($conn === null) {
        mysqli_report(MYSQLI_REPORT_OFF);
        try {
            $conn = @new mysqli(DB_HOST, DB_USER, DB_PASS);
            if ($conn->connect_error) {
                http_response_code(500);
                echo json_encode(['success' => false, 'message' => 'Database connection failed: ' . $conn->connect_error]);
                exit;
            }
            // Ensure database exists
            $conn->query("CREATE DATABASE IF NOT EXISTS `" . DB_NAME . "` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
            $conn->select_db(DB_NAME);
            $conn->set_charset('utf8mb4');
        } catch (Throwable $e) {
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
            exit;
        }
    }
    return $conn;
}
