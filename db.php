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
            $dateBlocksSql = "CREATE TABLE IF NOT EXISTS `item_date_blocks` (
                `id` INT AUTO_INCREMENT PRIMARY KEY,
                `item_type` ENUM('accommodation','bike','equipment','guide') NOT NULL,
                `item_id` VARCHAR(80) NOT NULL,
                `start_date` DATE NOT NULL,
                `end_date` DATE NOT NULL,
                `created_by` VARCHAR(80) NOT NULL,
                `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                KEY `item_date_block_lookup` (`item_type`, `item_id`, `start_date`, `end_date`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
            if (!$conn->query($dateBlocksSql)) {
                error_log('Unable to ensure item_date_blocks table: ' . $conn->error);
                http_response_code(500);
                echo json_encode(['success' => false, 'message' => 'Unable to initialize temporary booking date blocks.']);
                exit;
            }
        } catch (Throwable $e) {
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
            exit;
        }
    }
    return $conn;
}
