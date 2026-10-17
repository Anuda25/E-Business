<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';

header('Content-Type: application/json');
header('X-Content-Type-Options: nosniff');

// CORS for same-origin
header('Access-Control-Allow-Origin: ' . (isset($_SERVER['HTTP_HOST']) ? 'http://' . $_SERVER['HTTP_HOST'] : '*'));
header('Access-Control-Allow-Credentials: true');
header('Access-Control-Allow-Methods: GET, POST, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

$action = $_GET['action'] ?? $_POST['action'] ?? '';
if (empty($action) && !empty($_SERVER['QUERY_STRING'])) {
    parse_str($_SERVER['QUERY_STRING'], $qs);
    $action = $qs['action'] ?? '';
    if (empty($_GET)) {
        $_GET = $qs;
    }
}
if (empty($action)) {
    $rawInput = file_get_contents('php://input');
    if (!empty($rawInput)) {
        $json = json_decode($rawInput, true);
        if (is_array($json)) {
            $action = $json['action'] ?? '';
            $_POST = array_merge($_POST, $json);
        } else {
            parse_str($rawInput, $parsed);
            $action = $parsed['action'] ?? '';
            $_POST = array_merge($_POST, $parsed);
        }
    }
}
$method = $_SERVER['REQUEST_METHOD'];

// ─── Helper Functions ─────────────────────────────────────────────────────────

function respond($data, $code = 200) {
    http_response_code($code);
    echo json_encode($data);
    exit;
}

function requireLogin() {
    if (empty($_SESSION['username'])) {
        respond(['success' => false, 'message' => 'You must be logged in.'], 401);
    }
}

function requireAdmin() {
    requireLogin();
    if (($_SESSION['role'] ?? '') !== 'Admin') {
        respond(['success' => false, 'message' => 'Admin access required.'], 403);
    }
    // Verify role in database
    $db = getDB();
    $stmt = $db->prepare("SELECT role FROM users WHERE username=?");
    $stmt->bind_param("s", $_SESSION['username']);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if (!$row || $row['role'] !== 'Admin') {
        respond(['success' => false, 'message' => 'Admin access required (db verify).'], 403);
    }
}

function requireGuest() {
    requireLogin();
    if (($_SESSION['role'] ?? '') !== 'Guest') {
        respond(['success' => false, 'message' => 'This action is for Guest accounts only.'], 403);
    }
}

function sanitize($str) {
    return htmlspecialchars(strip_tags(trim((string)$str)), ENT_QUOTES, 'UTF-8');
}

function handleImageUpload($field, $subdir) {
    if (!isset($_FILES[$field]) || $_FILES[$field]['error'] === UPLOAD_ERR_NO_FILE) {
        return '';
    }
    $file = $_FILES[$field];
    if ($file['error'] !== UPLOAD_ERR_OK) {
        respond(['success' => false, 'message' => 'Image upload error: ' . $file['error']], 400);
    }
    $allowed = ['image/jpeg', 'image/jpg', 'image/png', 'image/webp'];
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime = $finfo->file($file['tmp_name']);
    if (!in_array($mime, $allowed)) {
        respond(['success' => false, 'message' => 'Invalid image format. Use JPG, PNG, or WEBP.'], 400);
    }
    if ($file['size'] > 5 * 1024 * 1024) {
        respond(['success' => false, 'message' => 'Image too large (max 5MB).'], 400);
    }
    $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
    $filename = uniqid('img_', true) . '.' . strtolower($ext);
    $dest_dir = __DIR__ . '/image/' . $subdir . '/';
    if (!is_dir($dest_dir)) mkdir($dest_dir, 0755, true);
    $dest = $dest_dir . $filename;
    if (!move_uploaded_file($file['tmp_name'], $dest)) {
        respond(['success' => false, 'message' => 'Failed to save image.'], 500);
    }
    return 'image/' . $subdir . '/' . $filename;
}

function handleMultiImageUpload($field, $subdir) {
    $paths = [];
    $dest_dir = __DIR__ . '/image/' . $subdir . '/';
    if (!is_dir($dest_dir)) mkdir($dest_dir, 0755, true);
    $allowed = ['image/jpeg', 'image/jpg', 'image/png', 'image/webp'];

    // Multi-file upload under $_FILES[$field]
    if (isset($_FILES[$field]) && is_array($_FILES[$field]['name'])) {
        $count = count($_FILES[$field]['name']);
        for ($i = 0; $i < $count; $i++) {
            if ($_FILES[$field]['error'][$i] === UPLOAD_ERR_OK) {
                $tmp = $_FILES[$field]['tmp_name'][$i];
                $finfo = new finfo(FILEINFO_MIME_TYPE);
                $mime = $finfo->file($tmp);
                if (in_array($mime, $allowed) && $_FILES[$field]['size'][$i] <= 8 * 1024 * 1024) {
                    $ext = strtolower(pathinfo($_FILES[$field]['name'][$i], PATHINFO_EXTENSION));
                    $filename = uniqid('img_', true) . '.' . $ext;
                    if (move_uploaded_file($tmp, $dest_dir . $filename)) {
                        $paths[] = 'image/' . $subdir . '/' . $filename;
                    }
                }
            }
        }
    } elseif (isset($_FILES[$field]) && !is_array($_FILES[$field]['name']) && $_FILES[$field]['error'] === UPLOAD_ERR_OK) {
        $single = handleImageUpload($field, $subdir);
        if ($single) $paths[] = $single;
    }

    // Also check single 'image' field if $field was 'images'
    if ($field !== 'image' && isset($_FILES['image']) && !is_array($_FILES['image']['name']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
        $single = handleImageUpload('image', $subdir);
        if ($single) array_unshift($paths, $single);
    }

    // Existing images (e.g. JSON string or array from edit form)
    if (!empty($_POST['existing_images'])) {
        $existing = is_array($_POST['existing_images']) ? $_POST['existing_images'] : json_decode($_POST['existing_images'], true);
        if (is_array($existing)) {
            $paths = array_merge($existing, $paths);
        }
    }

    // New URL inputs
    if (!empty($_POST['image_urls'])) {
        $urls = preg_split('/[\r\n,]+/', (string)$_POST['image_urls']);
        foreach ($urls as $u) {
            $u = trim($u);
            if (!empty($u) && (strpos($u, 'http') === 0 || strpos($u, 'image/') === 0)) {
                $paths[] = sanitize($u);
            }
        }
    }

    $paths = array_values(array_unique(array_filter($paths)));
    return $paths;
}

// Helper to get list of item IDs booked by current guest
function getGuestBookedItemIds($db, $itemType) {
    $booked = [];
    if (!empty($_SESSION['username']) && ($_SESSION['role'] ?? '') === 'Guest') {
        $stmt = $db->prepare("SELECT item_id FROM bookings WHERE customer_username=? AND item_type=? AND status!='cancelled' AND payment_status='paid'");
        if ($stmt) {
            $stmt->bind_param("ss", $_SESSION['username'], $itemType);
            $stmt->execute();
            $res = $stmt->get_result();
            while ($row = $res->fetch_row()) {
                $booked[] = $row[0];
            }
            $stmt->close();
        }
    }
    return $booked;
}

// ─── Routes ───────────────────────────────────────────────────────────────────

switch ($action) {

    // ── AUTH ──────────────────────────────────────────────────────────────────

    case 'register':
        if ($method !== 'POST') respond(['success' => false, 'message' => 'POST required'], 405);
        $full_name    = sanitize($_POST['full_name'] ?? '');
        $username     = sanitize($_POST['username'] ?? '');
        $email        = trim($_POST['email'] ?? '');
        $bank_details = sanitize($_POST['bank_details'] ?? '');
        $phone        = sanitize($_POST['phone'] ?? '');
        $password     = $_POST['password'] ?? '';
        $confirm      = $_POST['confirm_password'] ?? '';

        if (!$full_name || !$username || !$email || !$password) {
            respond(['success' => false, 'message' => 'Full name, username, email, and password are required.'], 400);
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            respond(['success' => false, 'message' => 'Invalid email address.'], 400);
        }
        if (strlen($password) < 6) {
            respond(['success' => false, 'message' => 'Password must be at least 6 characters.'], 400);
        }
        if ($password !== $confirm) {
            respond(['success' => false, 'message' => 'Passwords do not match.'], 400);
        }

        $db = getDB();
        $stmt = $db->prepare("SELECT username FROM users WHERE username=? OR email=?");
        $stmt->bind_param("ss", $username, $email);
        $stmt->execute();
        $exists = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        if ($exists) {
            respond(['success' => false, 'message' => 'Username or email already registered.'], 409);
        }

        $hash = password_hash($password, PASSWORD_DEFAULT);
        // Guests can only register as Guest
        $stmt = $db->prepare("INSERT INTO users (full_name, name, username, email, bank_details, phone, password, role) VALUES (?,?,?,?,?,?,?,'Guest')");
        $stmt->bind_param("sssssss", $full_name, $full_name, $username, $email, $bank_details, $phone, $hash);
        if ($stmt->execute()) {
            $stmt->close();
            $_SESSION['username']  = $username;
            $_SESSION['role']      = 'Guest';
            $_SESSION['full_name'] = $full_name;
            respond([
                'success' => true,
                'message' => "Guest account created successfully for {$full_name} (@{$username}).",
                'user' => [
                    'full_name'    => $full_name,
                    'username'     => $username,
                    'email'        => $email,
                    'bank_details' => $bank_details,
                    'phone'        => $phone,
                    'role'         => 'Guest'
                ]
            ]);
        } else {
            respond(['success' => false, 'message' => 'Registration failed: ' . $db->error], 500);
        }
        break;

    case 'login':
        if ($method !== 'POST') respond(['success' => false, 'message' => 'POST required'], 405);
        $username = sanitize($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';

        if (!$username || !$password) {
            respond(['success' => false, 'message' => 'Username and password are required.'], 400);
        }

        $db = getDB();
        $stmt = $db->prepare("SELECT id, full_name, name, username, email, bank_details, phone, password, role FROM users WHERE username=?");
        $stmt->bind_param("s", $username);
        $stmt->execute();
        $user = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$user || !password_verify($password, $user['password'])) {
            respond(['success' => false, 'message' => 'Invalid username or password.'], 401);
        }

        // Migrate role if old Host
        if ($user['role'] === 'Host') {
            $user['role'] = 'Admin';
            $db->query("UPDATE users SET role='Admin' WHERE username='{$user['username']}'");
        }

        $name = !empty($user['full_name']) ? $user['full_name'] : $user['name'];

        $_SESSION['username']  = $user['username'];
        $_SESSION['role']      = $user['role'];
        $_SESSION['full_name'] = $name;

        respond([
            'success' => true,
            'message' => "Welcome back, {$name} (@{$user['username']})!",
            'user' => [
                'full_name'    => $name,
                'username'     => $user['username'],
                'email'        => $user['email'],
                'bank_details' => $user['bank_details'] ?? '',
                'phone'        => $user['phone'] ?? '',
                'role'         => $user['role']
            ]
        ]);
        break;

    case 'logout':
        session_unset();
        session_destroy();
        respond(['success' => true, 'message' => 'Logged out successfully.']);
        break;

    case 'me':
        if (empty($_SESSION['username'])) {
            respond(['success' => false, 'loggedIn' => false]);
        }
        $db = getDB();
        $stmt = $db->prepare("SELECT full_name, name, username, email, bank_details, phone, role FROM users WHERE username=?");
        $stmt->bind_param("s", $_SESSION['username']);
        $stmt->execute();
        $user = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        if (!$user) {
            session_unset();
            session_destroy();
            respond(['success' => false, 'loggedIn' => false]);
        }
        $name = !empty($user['full_name']) ? $user['full_name'] : $user['name'];
        $user['full_name'] = $name;
        $_SESSION['role'] = $user['role'];
        $_SESSION['full_name'] = $name;
        respond(['success' => true, 'loggedIn' => true, 'user' => $user]);
        break;

    // ── ACCOMMODATIONS ────────────────────────────────────────────────────────

    case 'get_accommodations':
        $db = getDB();
        $where = ['1=1'];
        $params = [];
        $types = '';

        if (!empty($_GET['type'])) {
            $where[] = 'LOWER(a.type) = LOWER(?)';
            $params[] = $_GET['type'];
            $types .= 's';
        }
        if (!empty($_GET['location'])) {
            $where[] = 'a.location LIKE ?';
            $params[] = '%' . $_GET['location'] . '%';
            $types .= 's';
        }
        if (!empty($_GET['min_price'])) {
            $where[] = 'a.price >= ?';
            $params[] = (float)$_GET['min_price'];
            $types .= 'd';
        }
        if (!empty($_GET['max_price'])) {
            $where[] = 'a.price <= ?';
            $params[] = (float)$_GET['max_price'];
            $types .= 'd';
        }
        if (!empty($_GET['units'])) {
            $where[] = 'a.units >= ?';
            $params[] = (int)$_GET['units'];
            $types .= 'i';
        }
        $s_date = !empty($_GET['start_date']) ? $_GET['start_date'] : (!empty($_GET['check_in']) ? $_GET['check_in'] : '');
        $e_date = !empty($_GET['end_date']) ? $_GET['end_date'] : (!empty($_GET['check_out']) ? $_GET['check_out'] : '');
        if (!empty($s_date)) {
            if (empty($e_date)) $e_date = $s_date;
            $where[] = '(a.available_from IS NULL OR a.available_from <= ?)';
            $where[] = '(a.available_to IS NULL OR a.available_to >= ?)';
            $where[] = "a.id NOT IN (SELECT item_id FROM bookings WHERE item_type='accommodation' AND status!='cancelled' AND payment_status='paid' AND start_date IS NOT NULL AND start_date <= ? AND COALESCE(end_date, start_date) >= ?)";
            $params[] = $s_date;
            $params[] = $e_date;
            $params[] = $e_date;
            $params[] = $s_date;
            $types .= 'ssss';
        }

        $sql = "SELECT a.id, COALESCE(NULLIF(a.name,''), a.title) as name, a.type, a.description, a.location, a.price, a.units, a.image, a.images, a.rating, a.reviews, a.available_from, a.available_to, a.created_by, COALESCE(u.full_name, u.name, a.created_by) as admin_name
                FROM accommodations a
                LEFT JOIN users u ON a.created_by = u.username
                WHERE " . implode(' AND ', $where) . "
                ORDER BY a.created_at DESC";

        $stmt = $db->prepare($sql);
        if ($types && $params) {
            $stmt->bind_param($types, ...$params);
        }
        $stmt->execute();
        $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();

        // Stamp is_wishlisted for guest
        $wish_acc = [];
        if (!empty($_SESSION['username']) && ($_SESSION['role'] ?? '') === 'Guest') {
            $wstmt = $db->prepare("SELECT item_id FROM wishlists WHERE customer_username=? AND item_type='accommodation'");
            if ($wstmt) { $wstmt->bind_param('s', $_SESSION['username']); $wstmt->execute(); $wr = $wstmt->get_result(); while ($r = $wr->fetch_row()) $wish_acc[] = $r[0]; $wstmt->close(); }
        }
        foreach ($rows as &$item) { $item['is_wishlisted'] = in_array($item['id'], $wish_acc); }
        respond(['success' => true, 'data' => $rows]);
        break;

    case 'add_accommodation':
        requireAdmin();
        if ($method !== 'POST') respond(['success' => false, 'message' => 'POST required'], 405);
        $name       = sanitize($_POST['name'] ?? '');
        $type       = sanitize($_POST['type'] ?? 'Room');
        $desc       = sanitize($_POST['description'] ?? '');
        $loc        = sanitize($_POST['location'] ?? '');
        $price      = (float)($_POST['price'] ?? 0);
        $units      = (int)($_POST['units'] ?? 1);
        $avail_from = !empty($_POST['available_from']) ? $_POST['available_from'] : null;
        $avail_to   = !empty($_POST['available_to']) ? $_POST['available_to'] : null;

        if (!$name || !$loc || $price <= 0) {
            respond(['success' => false, 'message' => 'Name, location and positive price are required.'], 400);
        }

        $imgsArr = handleMultiImageUpload('images', 'accommodation');
        if (empty($imgsArr)) {
            $single = handleImageUpload('image', 'accommodation');
            if ($single) $imgsArr = [$single];
            else $imgsArr = ['image/accommodation/acc-villa-1.jpg'];
        }
        $image = $imgsArr[0];
        $imagesJson = json_encode($imgsArr);

        $created_by = $_SESSION['username'];
        $id = 'acc-' . uniqid();

        $db = getDB();
        $stmt = $db->prepare("INSERT INTO accommodations (id, name, title, type, description, location, price, units, image, images, available_from, available_to, created_by) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)");
        $stmt->bind_param("ssssssdisssss", $id, $name, $name, $type, $desc, $loc, $price, $units, $image, $imagesJson, $avail_from, $avail_to, $created_by);
        if ($stmt->execute()) {
            $stmt->close();
            respond(['success' => true, 'message' => "Accommodation added successfully by @{$created_by}.", 'id' => $id]);
        } else {
            respond(['success' => false, 'message' => 'Failed to add accommodation: ' . $db->error], 500);
        }
        break;

    case 'delete_accommodation':
        requireAdmin();
        $id = sanitize($_GET['id'] ?? $_POST['id'] ?? '');
        if (!$id) respond(['success' => false, 'message' => 'ID required.'], 400);

        $db = getDB();
        $stmt = $db->prepare("SELECT created_by, image FROM accommodations WHERE id=?");
        $stmt->bind_param("s", $id);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$row) respond(['success' => false, 'message' => 'Item not found.'], 404);
        if ($row['created_by'] !== $_SESSION['username']) {
            respond(['success' => false, 'message' => "Unauthorized: You can only remove items created by your own Admin account (@{$_SESSION['username']}). This item was created by @{$row['created_by']}."], 403);
        }

        if ($row['image'] && strpos($row['image'], 'uploads') !== false && file_exists(__DIR__ . '/' . $row['image'])) {
            @unlink(__DIR__ . '/' . $row['image']);
        }

        $stmt = $db->prepare("DELETE FROM accommodations WHERE id=? AND created_by=?");
        $stmt->bind_param("ss", $id, $_SESSION['username']);
        if ($stmt->execute() && $stmt->affected_rows > 0) {
            $stmt->close();
            respond(['success' => true, 'message' => 'Accommodation deleted successfully.']);
        } else {
            respond(['success' => false, 'message' => 'Delete failed.'], 500);
        }
        break;

    case 'get_accommodation':
        $id = sanitize($_GET['id'] ?? '');
        if (!$id) respond(['success' => false, 'message' => 'ID required.'], 400);
        $db = getDB();
        $stmt = $db->prepare("SELECT a.id, COALESCE(NULLIF(a.name,''), a.title) as name, a.type, a.description, a.location, a.price, a.units, a.image, a.images, a.rating, a.reviews, a.available_from, a.available_to, a.created_by, COALESCE(u.full_name, u.name, a.created_by) as admin_name
                             FROM accommodations a
                             LEFT JOIN users u ON a.created_by = u.username
                             WHERE a.id=?");
        $stmt->bind_param("s", $id);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        if (!$row) respond(['success' => false, 'message' => 'Accommodation not found.'], 404);

        // Availability and Wishlist
        $row['already_booked'] = false;
        $row['is_wishlisted'] = false;
        $row['booked_ranges'] = getBookedDateRanges($db, 'accommodation', $id);
        if (!empty($_SESSION['username']) && ($_SESSION['role'] ?? '') === 'Guest') {
            $wstmt = $db->prepare("SELECT id FROM wishlists WHERE customer_username=? AND item_type='accommodation' AND item_id=?");
            if ($wstmt) { $wstmt->bind_param('ss', $_SESSION['username'], $id); $wstmt->execute(); if ($wstmt->get_result()->fetch_assoc()) $row['is_wishlisted'] = true; $wstmt->close(); }
        }

        respond(['success' => true, 'data' => $row]);
        break;


    // ── BIKES ─────────────────────────────────────────────────────────────────

    case 'get_bikes':
        $db = getDB();
        $where = ['1=1'];
        $params = [];
        $types = '';

        if (!empty($_GET['model'])) {
            $where[] = '(LOWER(b.model) = LOWER(?) OR LOWER(b.type) = LOWER(?))';
            $params[] = $_GET['model'];
            $params[] = $_GET['model'];
            $types .= 'ss';
        }
        if (!empty($_GET['location'])) {
            $where[] = 'b.location LIKE ?';
            $params[] = '%' . $_GET['location'] . '%';
            $types .= 's';
        }
        if (!empty($_GET['min_price'])) {
            $where[] = 'b.price >= ?';
            $params[] = (float)$_GET['min_price'];
            $types .= 'd';
        }
        if (!empty($_GET['max_price'])) {
            $where[] = 'b.price <= ?';
            $params[] = (float)$_GET['max_price'];
            $types .= 'd';
        }
        $s_date = !empty($_GET['start_date']) ? $_GET['start_date'] : '';
        $e_date = !empty($_GET['end_date']) ? $_GET['end_date'] : (!empty($s_date) ? $s_date : '');
        if (!empty($s_date)) {
            $where[] = '(b.available_from IS NULL OR b.available_from <= ?)';
            $where[] = '(b.available_to IS NULL OR b.available_to >= ?)';
            $where[] = "b.id NOT IN (SELECT item_id FROM bookings WHERE item_type='bike' AND status!='cancelled' AND payment_status='paid' AND start_date IS NOT NULL AND start_date <= ? AND COALESCE(end_date, start_date) >= ?)";
            $params[] = $s_date;
            $params[] = $e_date;
            $params[] = $e_date;
            $params[] = $s_date;
            $types .= 'ssss';
        }

        $sql = "SELECT b.id, COALESCE(NULLIF(b.name,''), b.title) as name, COALESCE(NULLIF(b.model,''), b.type) as model, b.description, b.location, b.price, b.time_period, b.image, b.images, b.rating, b.reviews, b.available_from, b.available_to, b.created_by, COALESCE(u.full_name, u.name, b.created_by) as admin_name
                FROM bikes b
                LEFT JOIN users u ON b.created_by = u.username
                WHERE " . implode(' AND ', $where) . "
                ORDER BY b.created_at DESC";

        $stmt = $db->prepare($sql);
        if ($types && $params) $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();

        // Stamp is_wishlisted for guest
        $wish_bike = [];
        if (!empty($_SESSION['username']) && ($_SESSION['role'] ?? '') === 'Guest') {
            $wstmt = $db->prepare("SELECT item_id FROM wishlists WHERE customer_username=? AND item_type='bike'");
            if ($wstmt) { $wstmt->bind_param('s', $_SESSION['username']); $wstmt->execute(); $wr = $wstmt->get_result(); while ($r = $wr->fetch_row()) $wish_bike[] = $r[0]; $wstmt->close(); }
        }
        foreach ($rows as &$item) { $item['is_wishlisted'] = in_array($item['id'], $wish_bike); }
        respond(['success' => true, 'data' => $rows]);
        break;

    case 'add_bike':
        requireAdmin();
        if ($method !== 'POST') respond(['success' => false, 'message' => 'POST required'], 405);
        $name    = sanitize($_POST['name'] ?? '');
        $model   = sanitize($_POST['model'] ?? 'City Bike');
        $desc    = sanitize($_POST['description'] ?? '');
        $loc     = sanitize($_POST['location'] ?? '');
        $price   = (float)($_POST['price'] ?? 0);
        $period  = sanitize($_POST['time_period'] ?? '1 day');
        $avail_from = !empty($_POST['available_from']) ? $_POST['available_from'] : null;
        $avail_to   = !empty($_POST['available_to']) ? $_POST['available_to'] : null;

        if (!$name || !$loc || $price <= 0) {
            respond(['success' => false, 'message' => 'Name, location and price are required.'], 400);
        }

        $imgsArr = handleMultiImageUpload('images', 'bikes');
        if (empty($imgsArr)) {
            $single = handleImageUpload('image', 'bikes');
            if ($single) $imgsArr = [$single];
            else $imgsArr = ['image/bikes/bike-city-1.jpg'];
        }
        $image = $imgsArr[0];
        $imagesJson = json_encode($imgsArr);

        $created_by = $_SESSION['username'];
        $id = 'bike-' . uniqid();

        $db = getDB();
        $stmt = $db->prepare("INSERT INTO bikes (id, name, title, model, type, description, location, price, time_period, image, images, available_from, available_to, created_by) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?)");
        $stmt->bind_param("sssssssdssssss", $id, $name, $name, $model, $model, $desc, $loc, $price, $period, $image, $imagesJson, $avail_from, $avail_to, $created_by);
        if ($stmt->execute()) {
            $stmt->close();
            respond(['success' => true, 'message' => "Bike added successfully by @{$created_by}.", 'id' => $id]);
        } else {
            respond(['success' => false, 'message' => 'Failed to add bike: ' . $db->error], 500);
        }
        break;

    case 'delete_bike':
        requireAdmin();
        $id = sanitize($_GET['id'] ?? $_POST['id'] ?? '');
        if (!$id) respond(['success' => false, 'message' => 'ID required.'], 400);

        $db = getDB();
        $stmt = $db->prepare("SELECT created_by, image FROM bikes WHERE id=?");
        $stmt->bind_param("s", $id);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$row) respond(['success' => false, 'message' => 'Item not found.'], 404);
        if ($row['created_by'] !== $_SESSION['username']) {
            respond(['success' => false, 'message' => "Unauthorized: You can only remove items created by your own Admin account (@{$_SESSION['username']}). This item was created by @{$row['created_by']}."], 403);
        }

        if ($row['image'] && strpos($row['image'], 'uploads') !== false && file_exists(__DIR__ . '/' . $row['image'])) {
            @unlink(__DIR__ . '/' . $row['image']);
        }

        $stmt = $db->prepare("DELETE FROM bikes WHERE id=? AND created_by=?");
        $stmt->bind_param("ss", $id, $_SESSION['username']);
        if ($stmt->execute() && $stmt->affected_rows > 0) {
            $stmt->close();
            respond(['success' => true, 'message' => 'Bike deleted successfully.']);
        } else {
            respond(['success' => false, 'message' => 'Delete failed.'], 500);
        }
        break;

    case 'get_bike':
        $id = sanitize($_GET['id'] ?? '');
        if (!$id) respond(['success' => false, 'message' => 'ID required.'], 400);
        $db = getDB();
        $stmt = $db->prepare("SELECT b.id, COALESCE(NULLIF(b.name,''), b.title) as name, COALESCE(NULLIF(b.model,''), b.type) as model, b.description, b.location, b.price, b.time_period, b.image, b.images, b.rating, b.reviews, b.available_from, b.available_to, b.created_by, COALESCE(u.full_name, u.name, b.created_by) as admin_name
                             FROM bikes b
                             LEFT JOIN users u ON b.created_by = u.username
                             WHERE b.id=?");
        $stmt->bind_param("s", $id);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        if (!$row) respond(['success' => false, 'message' => 'Bike not found.'], 404);


        $row['already_booked'] = false;
        $row['is_wishlisted'] = false;
        $row['booked_ranges'] = getBookedDateRanges($db, 'bike', $id);
        if (!empty($_SESSION['username']) && ($_SESSION['role'] ?? '') === 'Guest') {
            $wstmt = $db->prepare("SELECT id FROM wishlists WHERE customer_username=? AND item_type='bike' AND item_id=?");
            if ($wstmt) { $wstmt->bind_param('ss', $_SESSION['username'], $id); $wstmt->execute(); if ($wstmt->get_result()->fetch_assoc()) $row['is_wishlisted'] = true; $wstmt->close(); }
        }

        respond(['success' => true, 'data' => $row]);
        break;

    // ── EQUIPMENT ─────────────────────────────────────────────────────────────

    case 'get_equipment':
        $db = getDB();
        $where = ['1=1'];
        $params = [];
        $types = '';

        if (!empty($_GET['type'])) {
            $where[] = '(LOWER(e.type) = LOWER(?) OR LOWER(e.category) = LOWER(?))';
            $params[] = $_GET['type'];
            $params[] = $_GET['type'];
            $types .= 'ss';
        }
        if (!empty($_GET['location'])) {
            $where[] = 'e.location LIKE ?';
            $params[] = '%' . $_GET['location'] . '%';
            $types .= 's';
        }
        if (!empty($_GET['min_price'])) {
            $where[] = 'e.price >= ?';
            $params[] = (float)$_GET['min_price'];
            $types .= 'd';
        }
        if (!empty($_GET['max_price'])) {
            $where[] = 'e.price <= ?';
            $params[] = (float)$_GET['max_price'];
            $types .= 'd';
        }
        $s_date = !empty($_GET['start_date']) ? $_GET['start_date'] : '';
        $e_date = !empty($_GET['end_date']) ? $_GET['end_date'] : (!empty($s_date) ? $s_date : '');
        if (!empty($s_date)) {
            $where[] = '(e.available_from IS NULL OR e.available_from <= ?)';
            $where[] = '(e.available_to IS NULL OR e.available_to >= ?)';
            $where[] = "e.id NOT IN (SELECT item_id FROM bookings WHERE item_type='equipment' AND status!='cancelled' AND payment_status='paid' AND start_date IS NOT NULL AND start_date <= ? AND COALESCE(end_date, start_date) >= ?)";
            $params[] = $s_date;
            $params[] = $e_date;
            $params[] = $e_date;
            $params[] = $s_date;
            $types .= 'ssss';
        }

        $sql = "SELECT e.id, COALESCE(NULLIF(e.name,''), e.title) as name, COALESCE(NULLIF(e.type,''), e.category) as type, e.description, e.location, e.price, e.time_period, e.image, e.images, COALESCE(e.rating, 4.90) as rating, COALESCE(e.reviews, 10) as reviews, e.available_from, e.available_to, e.created_by, COALESCE(u.full_name, u.name, e.created_by) as admin_name
                FROM equipment e
                LEFT JOIN users u ON e.created_by = u.username
                WHERE " . implode(' AND ', $where) . "
                ORDER BY e.created_at DESC";

        $stmt = $db->prepare($sql);
        if ($types && $params) $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();

        // Check wishlist items for current guest
        $wishlist_ids = [];
        if (!empty($_SESSION['username']) && ($_SESSION['role'] ?? '') === 'Guest') {
            $wstmt = $db->prepare("SELECT item_id FROM wishlists WHERE customer_username=? AND item_type='equipment'");
            if ($wstmt) {
                $wstmt->bind_param("s", $_SESSION['username']);
                $wstmt->execute();
                $wres = $wstmt->get_result();
                while ($wrow = $wres->fetch_row()) $wishlist_ids[] = $wrow[0];
                $wstmt->close();
            }
        }
        foreach ($rows as &$item) {
            $item['is_wishlisted'] = in_array($item['id'], $wishlist_ids);
        }

        respond(['success' => true, 'data' => $rows]);
        break;

    case 'add_equipment':
        requireAdmin();
        if ($method !== 'POST') respond(['success' => false, 'message' => 'POST required'], 405);
        $name    = sanitize($_POST['name'] ?? '');
        $type    = sanitize($_POST['type'] ?? 'Other');
        $desc    = sanitize($_POST['description'] ?? '');
        $loc     = sanitize($_POST['location'] ?? '');
        $price   = (float)($_POST['price'] ?? 0);
        $period  = sanitize($_POST['time_period'] ?? '1 day');
        $avail_from = !empty($_POST['available_from']) ? $_POST['available_from'] : null;
        $avail_to   = !empty($_POST['available_to']) ? $_POST['available_to'] : null;

        if (!$name || !$loc || $price <= 0) {
            respond(['success' => false, 'message' => 'Name, location and price are required.'], 400);
        }

        $imgsArr = handleMultiImageUpload('images', 'equipment');
        if (empty($imgsArr)) {
            $single = handleImageUpload('image', 'equipment');
            if ($single) $imgsArr = [$single];
            else $imgsArr = ['image/equipment/eq-surf-1.jpg'];
        }
        $image = $imgsArr[0];
        $imagesJson = json_encode($imgsArr);

        $created_by = $_SESSION['username'];
        $id = 'eq-' . uniqid();

        $db = getDB();
        $stmt = $db->prepare("INSERT INTO equipment (id, name, title, type, category, description, location, price, time_period, image, images, available_from, available_to, created_by) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?)");
        $stmt->bind_param("sssssssdssssss", $id, $name, $name, $type, $type, $desc, $loc, $price, $period, $image, $imagesJson, $avail_from, $avail_to, $created_by);
        if ($stmt->execute()) {
            $stmt->close();
            respond(['success' => true, 'message' => "Equipment added successfully by @{$created_by}.", 'id' => $id]);
        } else {
            respond(['success' => false, 'message' => 'Failed to add equipment: ' . $db->error], 500);
        }
        break;

    case 'delete_equipment':
        requireAdmin();
        $id = sanitize($_GET['id'] ?? $_POST['id'] ?? '');
        if (!$id) respond(['success' => false, 'message' => 'ID required.'], 400);

        $db = getDB();
        $stmt = $db->prepare("SELECT created_by, image FROM equipment WHERE id=?");
        $stmt->bind_param("s", $id);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$row) respond(['success' => false, 'message' => 'Item not found.'], 404);
        if ($row['created_by'] !== $_SESSION['username']) {
            respond(['success' => false, 'message' => "Unauthorized: You can only remove items created by your own Admin account (@{$_SESSION['username']}). This item was created by @{$row['created_by']}."], 403);
        }

        if ($row['image'] && strpos($row['image'], 'uploads') !== false && file_exists(__DIR__ . '/' . $row['image'])) {
            @unlink(__DIR__ . '/' . $row['image']);
        }

        $stmt = $db->prepare("DELETE FROM equipment WHERE id=? AND created_by=?");
        $stmt->bind_param("ss", $id, $_SESSION['username']);
        if ($stmt->execute() && $stmt->affected_rows > 0) {
            $stmt->close();
            respond(['success' => true, 'message' => 'Equipment deleted successfully.']);
        } else {
            respond(['success' => false, 'message' => 'Delete failed.'], 500);
        }
        break;

    case 'get_equipment_item':
        $id = sanitize($_GET['id'] ?? '');
        if (!$id) respond(['success' => false, 'message' => 'ID required.'], 400);
        $db = getDB();
        $stmt = $db->prepare("SELECT e.id, COALESCE(NULLIF(e.name,''), e.title) as name, COALESCE(NULLIF(e.type,''), e.category) as type, e.description, e.location, e.price, e.time_period, e.image, e.images, e.rating, e.reviews, e.available_from, e.available_to, e.created_by, COALESCE(u.full_name, u.name, e.created_by) as admin_name
                             FROM equipment e
                             LEFT JOIN users u ON e.created_by = u.username
                             WHERE e.id=?");
        $stmt->bind_param("s", $id);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();

        $stmt->close();
        if (!$row) respond(['success' => false, 'message' => 'Equipment not found.'], 404);

        $row['already_booked'] = false;
        $row['is_wishlisted'] = false;
        $row['booked_ranges'] = getBookedDateRanges($db, 'equipment', $id);
        if (!empty($_SESSION['username']) && ($_SESSION['role'] ?? '') === 'Guest') {
            $wstmt = $db->prepare("SELECT id FROM wishlists WHERE customer_username=? AND item_type='equipment' AND item_id=?");
            if ($wstmt) {
                $wstmt->bind_param("ss", $_SESSION['username'], $id);
                $wstmt->execute();
                if ($wstmt->get_result()->fetch_assoc()) $row['is_wishlisted'] = true;
                $wstmt->close();
            }
        }

        respond(['success' => true, 'data' => $row]);
        break;

    // ── GUIDES ────────────────────────────────────────────────────────────────

    case 'get_guides':
        $db = getDB();
        $where = ['1=1'];
        $params = [];
        $types = '';

        if (!empty($_GET['location'])) {
            $where[] = 'g.location LIKE ?';
            $params[] = '%' . $_GET['location'] . '%';
            $types .= 's';
        }
        if (!empty($_GET['min_rating'])) {
            $where[] = 'g.rating >= ?';
            $params[] = (float)$_GET['min_rating'];
            $types .= 'd';
        }
        $s_date = !empty($_GET['start_date']) ? $_GET['start_date'] : '';
        $e_date = !empty($_GET['end_date']) ? $_GET['end_date'] : (!empty($s_date) ? $s_date : '');
        if (!empty($s_date)) {
            $where[] = "g.id NOT IN (SELECT item_id FROM bookings WHERE item_type='guide' AND status!='cancelled' AND payment_status='paid' AND start_date IS NOT NULL AND start_date <= ? AND COALESCE(end_date, start_date) >= ?)";
            $params[] = $e_date;
            $params[] = $s_date;
            $types .= 'ss';
        }

        $sql = "SELECT g.id, g.name, g.title, COALESCE(NULLIF(g.description,''), g.bio) as description, g.location, g.experience, g.price, g.rating, g.reviews, COALESCE(NULLIF(g.image,''), g.avatar) as image, g.images, g.created_by, COALESCE(u.full_name, u.name, g.created_by) as admin_name
                FROM guides g
                LEFT JOIN users u ON g.created_by = u.username
                WHERE " . implode(' AND ', $where) . "
                ORDER BY g.rating DESC, g.created_at DESC";

        $stmt = $db->prepare($sql);
        if ($types && $params) $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();

        // Stamp is_wishlisted for guest
        $wish_guide = [];
        if (!empty($_SESSION['username']) && ($_SESSION['role'] ?? '') === 'Guest') {
            $wstmt = $db->prepare("SELECT item_id FROM wishlists WHERE customer_username=? AND item_type='guide'");
            if ($wstmt) { $wstmt->bind_param('s', $_SESSION['username']); $wstmt->execute(); $wr = $wstmt->get_result(); while ($r = $wr->fetch_row()) $wish_guide[] = $r[0]; $wstmt->close(); }
        }
        foreach ($rows as &$item) { $item['is_wishlisted'] = in_array($item['id'], $wish_guide); }
        respond(['success' => true, 'data' => $rows]);
        break;

    case 'add_guide':
        requireAdmin();
        if ($method !== 'POST') respond(['success' => false, 'message' => 'POST required'], 405);
        $name       = sanitize($_POST['name'] ?? '');
        $title      = sanitize($_POST['title'] ?? ($name . ' - Professional Guide'));
        $desc       = sanitize($_POST['description'] ?? '');
        $loc        = sanitize($_POST['location'] ?? '');
        $experience = sanitize($_POST['experience'] ?? '');
        $price      = (float)($_POST['price'] ?? 45.00);
        $rating     = (float)($_POST['rating'] ?? 5.0);

        if (!$name || !$loc) {
            respond(['success' => false, 'message' => 'Name and location are required.'], 400);
        }
        if ($rating < 1 || $rating > 5) {
            respond(['success' => false, 'message' => 'Rating must be between 1 and 5.'], 400);
        }

        $imgsArr = handleMultiImageUpload('images', 'guides');
        if (empty($imgsArr)) {
            $single = handleImageUpload('image', 'guides');
            if ($single) $imgsArr = [$single];
            else $imgsArr = ['image/guides/guide-dinesh.jpg'];
        }
        $image = $imgsArr[0];
        $imagesJson = json_encode($imgsArr);

        $created_by = $_SESSION['username'];
        $id = 'guide-' . uniqid();
        $tags = json_encode(['Guide', 'Local Expert']);

        $db = getDB();
        $stmt = $db->prepare("INSERT INTO guides (id, name, title, description, bio, location, experience, price, rating, image, avatar, images, tags, created_by) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?)");
        $stmt->bind_param("ssssssdddsssss", $id, $name, $title, $desc, $desc, $loc, $experience, $price, $rating, $image, $image, $imagesJson, $tags, $created_by);
        if ($stmt->execute()) {
            $stmt->close();
            respond(['success' => true, 'message' => "Tour guide added successfully by @{$created_by}.", 'id' => $id]);
        } else {
            respond(['success' => false, 'message' => 'Failed to add guide: ' . $db->error], 500);
        }
        break;

    case 'delete_guide':
        requireAdmin();
        $id = sanitize($_GET['id'] ?? $_POST['id'] ?? '');
        if (!$id) respond(['success' => false, 'message' => 'ID required.'], 400);

        $db = getDB();
        $stmt = $db->prepare("SELECT created_by, image, avatar FROM guides WHERE id=?");
        $stmt->bind_param("s", $id);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$row) respond(['success' => false, 'message' => 'Item not found.'], 404);
        if ($row['created_by'] !== $_SESSION['username']) {
            respond(['success' => false, 'message' => "Unauthorized: You can only remove items created by your own Admin account (@{$_SESSION['username']}). This item was created by @{$row['created_by']}."], 403);
        }

        $img = $row['image'] ?: $row['avatar'];
        if ($img && strpos($img, 'uploads') !== false && file_exists(__DIR__ . '/' . $img)) {
            @unlink(__DIR__ . '/' . $img);
        }

        $stmt = $db->prepare("DELETE FROM guides WHERE id=? AND created_by=?");
        $stmt->bind_param("ss", $id, $_SESSION['username']);
        if ($stmt->execute() && $stmt->affected_rows > 0) {
            $stmt->close();
            respond(['success' => true, 'message' => 'Guide deleted successfully.']);
        } else {
            respond(['success' => false, 'message' => 'Delete failed.'], 500);
        }
        break;

    case 'get_guide':
        $id = sanitize($_GET['id'] ?? '');
        if (!$id) respond(['success' => false, 'message' => 'ID required.'], 400);
        $db = getDB();
        $stmt = $db->prepare("SELECT g.id, g.name, g.title, COALESCE(NULLIF(g.description,''), g.bio) as description, g.location, g.experience, g.price, g.rating, g.reviews, COALESCE(NULLIF(g.image,''), g.avatar) as image, g.images, g.created_by, COALESCE(u.full_name, u.name, g.created_by) as admin_name
                             FROM guides g
                             LEFT JOIN users u ON g.created_by = u.username
                             WHERE g.id=?");
        $stmt->bind_param("s", $id);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        if (!$row) respond(['success' => false, 'message' => 'Guide not found.'], 404);

        $row['already_booked'] = false;
        $row['is_wishlisted'] = false;
        $row['booked_ranges'] = getBookedDateRanges($db, 'guide', $id);
        if (!empty($_SESSION['username']) && ($_SESSION['role'] ?? '') === 'Guest') {
            $wstmt = $db->prepare("SELECT id FROM wishlists WHERE customer_username=? AND item_type='guide' AND item_id=?");
            if ($wstmt) { $wstmt->bind_param('ss', $_SESSION['username'], $id); $wstmt->execute(); if ($wstmt->get_result()->fetch_assoc()) $row['is_wishlisted'] = true; $wstmt->close(); }
        }

        respond(['success' => true, 'data' => $row]);
        break;


    // ── ADMIN: MY ITEMS ───────────────────────────────────────────────────────

    case 'my_items':
        requireAdmin();
        $admin_user = $_SESSION['username'];
        $db = getDB();

        $stmt = $db->prepare("SELECT id, COALESCE(NULLIF(name,''), title) as name, type, location, price, units, description, available_from, available_to, image, images, COALESCE(rating, 4.9) as rating, COALESCE(reviews, 10) as reviews, created_at, created_by FROM accommodations WHERE created_by=? OR ?='admin' ORDER BY created_at DESC");
        $stmt->bind_param("ss", $admin_user, $admin_user);
        $stmt->execute();
        $accommodations = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();

        $stmt = $db->prepare("SELECT id, COALESCE(NULLIF(name,''), title) as name, COALESCE(NULLIF(model,''), type) as model, location, price, time_period, description, available_from, available_to, image, images, COALESCE(rating, 4.9) as rating, COALESCE(reviews, 10) as reviews, created_at, created_by FROM bikes WHERE created_by=? OR ?='admin' ORDER BY created_at DESC");
        $stmt->bind_param("ss", $admin_user, $admin_user);
        $stmt->execute();
        $bikes = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();

        $stmt = $db->prepare("SELECT id, COALESCE(NULLIF(name,''), title) as name, COALESCE(NULLIF(type,''), category) as type, location, price, time_period, description, available_from, available_to, image, images, COALESCE(rating, 4.9) as rating, COALESCE(reviews, 10) as reviews, created_at, created_by FROM equipment WHERE created_by=? OR ?='admin' ORDER BY created_at DESC");
        $stmt->bind_param("ss", $admin_user, $admin_user);
        $stmt->execute();
        $equipment = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();

        $stmt = $db->prepare("SELECT id, name, title, location, price, experience, description, bio, languages, tags, COALESCE(rating, 5.0) as rating, COALESCE(reviews, 10) as reviews, COALESCE(NULLIF(image,''), avatar) as image, images, created_at, created_by FROM guides WHERE created_by=? OR ?='admin' ORDER BY created_at DESC");
        $stmt->bind_param("ss", $admin_user, $admin_user);
        $stmt->execute();
        $guides = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();

        respond(['success' => true, 'data' => [
            'accommodations' => $accommodations,
            'bikes'          => $bikes,
            'equipment'      => $equipment,
            'guides'         => $guides,
        ]]);
        break;

    // ── ADMIN: RECEIVED BOOKINGS ──────────────────────────────────────────────

    case 'received_bookings':
        requireAdmin();
        $admin_user = $_SESSION['username'];
        $db = getDB();

        $where = ["b.created_by = ?"];
        $params = [$admin_user];
        $types = "s";

        // Filter: date_from
        if (!empty($_GET['date_from'])) {
            $where[] = "b.start_date >= ?";
            $params[] = $_GET['date_from'];
            $types .= "s";
        }
        // Filter: date_to
        if (!empty($_GET['date_to'])) {
            $where[] = "b.start_date <= ?";
            $params[] = $_GET['date_to'];
            $types .= "s";
        }
        // Filter: item_type
        if (!empty($_GET['item_type']) && in_array($_GET['item_type'], ['accommodation', 'bike', 'equipment', 'guide'])) {
            $where[] = "b.item_type = ?";
            $params[] = $_GET['item_type'];
            $types .= "s";
        }
        // Filter: status
        if (!empty($_GET['status'])) {
            if ($_GET['status'] === 'paid') {
                $where[] = "b.payment_status = 'paid'";
            } elseif ($_GET['status'] === 'unpaid') {
                $where[] = "b.payment_status != 'paid'";
            } else {
                $where[] = "b.status = ?";
                $params[] = $_GET['status'];
                $types .= "s";
            }
        }

        $sql = "SELECT b.*, COALESCE(u.full_name, u.name, b.customer_username) as customer_name, u.email as customer_email, u.phone as customer_phone
                FROM bookings b
                LEFT JOIN users u ON b.customer_username = u.username
                WHERE " . implode(' AND ', $where) . "
                ORDER BY COALESCE(b.start_date, DATE(b.created_at)) DESC, b.created_at DESC";
        $stmt = $db->prepare($sql);
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $bookings = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();

        $total_count = count($bookings);
        $total_earnings = 0.0;
        $paid_earnings = 0.0;
        $pending_earnings = 0.0;
        $by_type = ['accommodation' => 0.0, 'bike' => 0.0, 'equipment' => 0.0, 'guide' => 0.0];

        foreach ($bookings as &$bk) {
            $details = getItemDetails($db, $bk['item_type'], $bk['item_id']);
            $bk['item_name']        = $details['name'];
            $bk['item_location']    = $details['location'];
            $bk['item_image']       = $details['image'];
            $bk['item_images']      = $details['images'] ?? '[]';
            $bk['item_description'] = $details['description'] ?? '';
            $bk['item_rating']      = $details['rating'] ?? 5.0;
            $bk['item_reviews']     = $details['reviews'] ?? 10;
            $bk['current_price']    = $details['price'];

            $price = (float)$bk['price'];
            if ($bk['status'] !== 'cancelled') {
                $total_earnings += $price;
                if (($bk['payment_status'] ?? '') === 'paid') {
                    $paid_earnings += $price;
                } else {
                    $pending_earnings += $price;
                }
                if (isset($by_type[$bk['item_type']])) {
                    $by_type[$bk['item_type']] += $price;
                }
            }
        }

        $summary = [
            'total_count'      => $total_count,
            'total_earnings'   => round($total_earnings, 2),
            'paid_earnings'    => round($paid_earnings, 2),
            'pending_earnings' => round($pending_earnings, 2),
            'by_type'          => $by_type
        ];

        respond(['success' => true, 'data' => $bookings, 'summary' => $summary]);
        break;


    // ── BOOKINGS ──────────────────────────────────────────────────────────────

    case 'create_booking':
        requireLogin();
        if (($_SESSION['role'] ?? '') !== 'Guest') {
            respond(['success' => false, 'message' => 'Only Guest accounts can make bookings. Admins manage listings.'], 403);
        }
        if ($method !== 'POST') respond(['success' => false, 'message' => 'POST required'], 405);

        $item_type   = sanitize($_POST['item_type'] ?? '');
        $item_id     = sanitize($_POST['item_id'] ?? '');
        $start_date  = !empty($_POST['start_date']) ? $_POST['start_date'] : null;
        $end_date    = !empty($_POST['end_date']) ? $_POST['end_date'] : null;
        $time_period = sanitize($_POST['time_period'] ?? '');
        $units       = (int)($_POST['units'] ?? 1);
        $price       = (float)($_POST['price'] ?? 0);

        $valid_types = ['accommodation', 'bike', 'equipment', 'guide'];
        if (!in_array($item_type, $valid_types) || !$item_id) {
            respond(['success' => false, 'message' => 'Invalid item type or ID.'], 400);
        }

        $db = getDB();
        $customer_username = $_SESSION['username'];

        // RULE: An item is only unavailable for the date range it is booked/confirmed paid!
        // Check if requested date range overlaps with any active confirmed paid booking.
        $chk_start = !empty($start_date) ? $start_date : date('Y-m-d');
        $chk_end   = !empty($end_date) ? $end_date : $chk_start;
        $check = $db->prepare("SELECT id, start_date, end_date FROM bookings WHERE item_type=? AND item_id=? AND status!='cancelled' AND payment_status='paid' AND start_date IS NOT NULL AND start_date <= ? AND COALESCE(end_date, start_date) >= ?");
        $check->bind_param("ssss", $item_type, $item_id, $chk_end, $chk_start);
        $check->execute();
        $conflict = $check->get_result()->fetch_assoc();
        $check->close();
        if ($conflict) {
            respond(['success' => false, 'message' => "This item is already reserved from {$conflict['start_date']} to {$conflict['end_date']}. All other dates are available!"], 400);
        }

        // Get created_by (Admin) from item table
        $tableMap = [
            'accommodation' => 'accommodations',
            'bike'          => 'bikes',
            'equipment'     => 'equipment',
            'guide'         => 'guides',
        ];
        $table = $tableMap[$item_type];
        $stmt = $db->prepare("SELECT created_by, price FROM `{$table}` WHERE id=?");
        $stmt->bind_param("s", $item_id);
        $stmt->execute();
        $item = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$item) respond(['success' => false, 'message' => 'Item not found or no longer available.'], 404);
        $admin_owner = $item['created_by'] ?: 'admin';

        $stmt = $db->prepare("INSERT INTO bookings (customer_username, item_type, item_id, created_by, start_date, end_date, time_period, units, price, status, payment_status, payment_method) VALUES (?,?,?,?,?,?,?,?,?,'pending','unpaid','PayHere')");
        $stmt->bind_param("sssssssid", $customer_username, $item_type, $item_id, $admin_owner, $start_date, $end_date, $time_period, $units, $price);
        if ($stmt->execute()) {
            $new_bk_id = $stmt->insert_id;
            $stmt->close();

            $order_id = 'ORDER_' . $new_bk_id . '_' . time();
            $upd = $db->prepare("UPDATE bookings SET order_id=? WHERE id=?");
            $upd->bind_param("si", $order_id, $new_bk_id);
            $upd->execute();
            $upd->close();

            // Fetch guest details for PayHere
            $ustmt = $db->prepare("SELECT full_name, name, email, phone FROM users WHERE username=?");
            $ustmt->bind_param("s", $customer_username);
            $ustmt->execute();
            $guest_user = $ustmt->get_result()->fetch_assoc() ?: [];
            $ustmt->close();

            $cust_name = !empty($guest_user['full_name']) ? $guest_user['full_name'] : (!empty($guest_user['name']) ? $guest_user['name'] : $customer_username);
            $item_name = getItemName($db, $item_type, $item_id);
            $amount_formatted = number_format((float)$price, 2, '.', '');
            $payhere_hash = strtoupper(
                md5(
                    PAYHERE_MERCHANT_ID . 
                    $order_id . 
                    $amount_formatted . 
                    PAYHERE_CURRENCY . 
                    strtoupper(md5(PAYHERE_MERCHANT_SECRET))
                )
            );
            $is_sandbox = (defined('PAYHERE_ENV') && PAYHERE_ENV === 'live') ? false : true;

            respond([
                'success' => true,
                'message' => 'Reservation initiated! Complete payment to apply booking.',
                'booking_id' => $new_bk_id,
                'order_id' => $order_id,
                'payhere_data' => [
                    'sandbox'       => $is_sandbox,
                    'merchant_id'   => PAYHERE_MERCHANT_ID,
                    'return_url'    => '',
                    'cancel_url'    => '',
                    'notify_url'    => '/New/api.php?action=payhere_notify',
                    'order_id'      => $order_id,
                    'items'         => $item_name,
                    'amount'        => $amount_formatted,
                    'currency'      => PAYHERE_CURRENCY,
                    'hash'          => $payhere_hash,
                    'first_name'    => $cust_name,
                    'last_name'     => '',
                    'email'         => $guest_user['email'] ?? '',
                    'phone'         => $guest_user['phone'] ?? '',
                    'address'       => 'Tour Guide Travelers',
                    'city'          => 'Colombo',
                    'country'       => 'Sri Lanka',
                    'custom_1'      => (string)$new_bk_id,
                ]
            ]);
        } else {
            respond(['success' => false, 'message' => 'Booking failed: ' . $db->error], 500);
        }
        break;

    case 'get_booking_payment_data':
        requireLogin();
        $bk_id = (int)($_GET['booking_id'] ?? $_POST['booking_id'] ?? 0);
        if (!$bk_id) respond(['success' => false, 'message' => 'Booking ID required.'], 400);
        $db = getDB();
        $stmt = $db->prepare("SELECT * FROM bookings WHERE id=?");
        $stmt->bind_param("i", $bk_id);
        $stmt->execute();
        $bk = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        if (!$bk) respond(['success' => false, 'message' => 'Booking not found.'], 404);
        if ($bk['customer_username'] !== $_SESSION['username'] && ($_SESSION['role'] ?? '') !== 'Admin') {
            respond(['success' => false, 'message' => 'Unauthorized booking access.'], 403);
        }
        $order_id = $bk['order_id'] ?: ('ORDER_' . $bk['id'] . '_' . time());
        if (!$bk['order_id']) {
            $upd = $db->prepare("UPDATE bookings SET order_id=? WHERE id=?");
            $upd->bind_param("si", $order_id, $bk['id']);
            $upd->execute();
            $upd->close();
        }
        $ustmt = $db->prepare("SELECT full_name, name, email, phone FROM users WHERE username=?");
        $ustmt->bind_param("s", $bk['customer_username']);
        $ustmt->execute();
        $guest_user = $ustmt->get_result()->fetch_assoc() ?: [];
        $ustmt->close();
        $cust_name = !empty($guest_user['full_name']) ? $guest_user['full_name'] : (!empty($guest_user['name']) ? $guest_user['name'] : $bk['customer_username']);
        $item_name = getItemName($db, $bk['item_type'], $bk['item_id']);
        $amount_formatted = number_format((float)$bk['price'], 2, '.', '');
        $payhere_hash = strtoupper(
            md5(
                PAYHERE_MERCHANT_ID . 
                $order_id . 
                $amount_formatted . 
                PAYHERE_CURRENCY . 
                strtoupper(md5(PAYHERE_MERCHANT_SECRET))
            )
        );
        $is_sandbox = (defined('PAYHERE_ENV') && PAYHERE_ENV === 'live') ? false : true;
        respond([
            'success' => true,
            'booking_id' => $bk['id'],
            'order_id' => $order_id,
            'payhere_data' => [
                'sandbox'       => $is_sandbox,
                'merchant_id'   => PAYHERE_MERCHANT_ID,
                'return_url'    => '',
                'cancel_url'    => '',
                'notify_url'    => '/New/api.php?action=payhere_notify',
                'order_id'      => $order_id,
                'items'         => $item_name,
                'amount'        => $amount_formatted,
                'currency'      => PAYHERE_CURRENCY,
                'hash'          => $payhere_hash,
                'first_name'    => $cust_name,
                'last_name'     => '',
                'email'         => $guest_user['email'] ?? '',
                'phone'         => $guest_user['phone'] ?? '',
                'address'       => 'Tour Guide Travelers',
                'city'          => 'Colombo',
                'country'       => 'Sri Lanka',
                'custom_1'      => (string)$bk['id'],
            ]
        ]);
        break;

    case 'my_bookings':
        requireLogin();
        if (($_SESSION['role'] ?? '') !== 'Guest') {
            respond(['success' => false, 'message' => 'Only Guest accounts have personal travel bookings.'], 403);
        }
        $customer_username = $_SESSION['username'];
        $db = getDB();

        $sql = "SELECT b.*, 
                       COALESCE(admin.full_name, admin.name, b.created_by) as owner_name,
                       admin.email as owner_email,
                       admin.phone as owner_phone,
                       admin.bank_details as owner_bank_details,
                       r.id as review_id,
                       r.rating as review_rating,
                       r.review_text,
                       r.created_at as review_created_at
                FROM bookings b
                LEFT JOIN users admin ON b.created_by = admin.username
                LEFT JOIN reviews r ON b.id = r.booking_id
                WHERE b.customer_username=? 
                ORDER BY COALESCE(b.start_date, DATE(b.created_at)) DESC, b.created_at DESC";
        $stmt = $db->prepare($sql);
        $stmt->bind_param("s", $customer_username);
        $stmt->execute();
        $bookings = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();

        foreach ($bookings as &$bk) {
            $details = getItemDetails($db, $bk['item_type'], $bk['item_id']);
            $bk['item_name']        = $details['name'];
            $bk['item_location']    = $details['location'];
            $bk['item_image']       = $details['image'];
            $bk['item_images']      = $details['images'] ?? '[]';
            $bk['item_description'] = $details['description'] ?? '';
            $bk['item_rating']      = $details['rating'] ?? 5.0;
            $bk['item_reviews']     = $details['reviews'] ?? 10;
            $bk['current_price']    = $details['price'];
        }

        respond(['success' => true, 'data' => $bookings]);
        break;


    case 'cancel_booking':
        requireLogin();
        if (($_SESSION['role'] ?? '') !== 'Guest') {
            respond(['success' => false, 'message' => 'Only Guests can cancel bookings.'], 403);
        }
        $id = (int)($_GET['id'] ?? $_POST['id'] ?? 0);
        if (!$id) respond(['success' => false, 'message' => 'Booking ID required.'], 400);

        $db = getDB();
        $stmt = $db->prepare("SELECT customer_username, status FROM bookings WHERE id=?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $bk = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$bk) respond(['success' => false, 'message' => 'Booking not found.'], 404);
        if ($bk['customer_username'] !== $_SESSION['username']) {
            respond(['success' => false, 'message' => 'Unauthorized: Not your booking.'], 403);
        }
        if ($bk['status'] === 'cancelled') {
            respond(['success' => false, 'message' => 'Booking already cancelled.'], 400);
        }

        $stmt = $db->prepare("UPDATE bookings SET status='cancelled' WHERE id=? AND customer_username=?");
        $stmt->bind_param("is", $id, $_SESSION['username']);
        if ($stmt->execute() && $stmt->affected_rows > 0) {
            $stmt->close();
            respond(['success' => true, 'message' => 'Booking cancelled successfully. Item is now available again.']);
        } else {
            respond(['success' => false, 'message' => 'Cancellation failed.'], 500);
        }
        break;

    // ── PAYHERE PAYMENT & RECEIPT ─────────────────────────────────────────────

    case 'confirm_payment':
        requireLogin();
        $booking_id = (int)($_POST['booking_id'] ?? $_GET['booking_id'] ?? 0);
        $order_id   = sanitize($_POST['order_id'] ?? '');
        $payment_id = sanitize($_POST['payment_id'] ?? ('PH-' . strtoupper(substr(md5(uniqid()), 0, 10))));
        $pay_method = !empty($_POST['payment_method']) ? sanitize($_POST['payment_method']) : 'PayHere Payment Gateway';

        if (!$booking_id) respond(['success' => false, 'message' => 'Booking ID required.'], 400);

        $db = getDB();
        $stmt = $db->prepare("SELECT b.*, u.full_name, u.name, u.email, u.phone FROM bookings b LEFT JOIN users u ON b.customer_username = u.username WHERE b.id = ?");
        $stmt->bind_param("i", $booking_id);
        $stmt->execute();
        $bk = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$bk) respond(['success' => false, 'message' => 'Booking not found.'], 404);
        if ($bk['customer_username'] !== $_SESSION['username'] && ($_SESSION['role'] ?? '') !== 'Admin') {
            respond(['success' => false, 'message' => 'Unauthorized booking access.'], 403);
        }

        $now = date('Y-m-d H:i:s');
        $upd = $db->prepare("UPDATE bookings SET status='confirmed', payment_status='paid', payment_method=?, payment_id=?, paid_at=? WHERE id=?");
        $upd->bind_param("sssi", $pay_method, $payment_id, $now, $booking_id);
        $upd->execute();
        $upd->close();

        $item_name = getItemName($db, $bk['item_type'], $bk['item_id']);
        $cust_name = !empty($bk['full_name']) ? $bk['full_name'] : (!empty($bk['name']) ? $bk['name'] : $bk['customer_username']);

        $receipt = [
            'receipt_no'       => 'TG-REC-' . str_pad((string)$booking_id, 5, '0', STR_PAD_LEFT),
            'booking_id'       => $booking_id,
            'order_id'         => $bk['order_id'] ?: $order_id,
            'payment_id'       => $payment_id,
            'payment_method'   => $pay_method,
            'merchant_id'      => PAYHERE_MERCHANT_ID,
            'payment_status'   => 'PAID',
            'status'           => 'confirmed',
            'paid_at'          => $now,
            'item_name'        => $item_name,
            'item_type'        => $bk['item_type'],
            'start_date'       => $bk['start_date'],
            'end_date'         => $bk['end_date'],
            'time_period'      => $bk['time_period'],
            'units'            => $bk['units'],
            'amount'           => number_format((float)$bk['price'], 2, '.', ''),
            'currency'         => PAYHERE_CURRENCY,
            'customer_name'    => $cust_name,
            'customer_username'=> $bk['customer_username'],
            'customer_email'   => $bk['email'],
            'customer_phone'   => $bk['phone'],
        ];

        respond([
            'success' => true,
            'message' => 'Payment received successfully via PayHere! You can now download your official receipt.',
            'receipt' => $receipt
        ]);
        break;

    case 'get_receipt':
        requireLogin();
        $booking_id = (int)($_GET['booking_id'] ?? $_POST['booking_id'] ?? 0);
        if (!$booking_id) respond(['success' => false, 'message' => 'Booking ID required.'], 400);

        $db = getDB();
        $stmt = $db->prepare("SELECT b.*, u.full_name, u.name, u.email, u.phone FROM bookings b LEFT JOIN users u ON b.customer_username = u.username WHERE b.id = ?");
        $stmt->bind_param("i", $booking_id);
        $stmt->execute();
        $bk = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$bk) respond(['success' => false, 'message' => 'Booking not found.'], 404);
        if ($bk['customer_username'] !== $_SESSION['username'] && ($_SESSION['role'] ?? '') !== 'Admin' && $bk['created_by'] !== $_SESSION['username']) {
            respond(['success' => false, 'message' => 'Unauthorized to view this receipt.'], 403);
        }

        $item_name = getItemName($db, $bk['item_type'], $bk['item_id']);
        $cust_name = !empty($bk['full_name']) ? $bk['full_name'] : (!empty($bk['name']) ? $bk['name'] : $bk['customer_username']);

        $receipt = [
            'receipt_no'       => 'TG-REC-' . str_pad((string)$booking_id, 5, '0', STR_PAD_LEFT),
            'booking_id'       => $booking_id,
            'order_id'         => $bk['order_id'],
            'payment_id'       => $bk['payment_id'] ?: 'PH-PENDING',
            'payment_method'   => $bk['payment_method'] ?: 'PayHere Payment Gateway',
            'merchant_id'      => PAYHERE_MERCHANT_ID,
            'payment_status'   => strtoupper($bk['payment_status'] ?: 'unpaid'),
            'status'           => $bk['status'],
            'paid_at'          => $bk['paid_at'] ?: $bk['created_at'],
            'item_name'        => $item_name,
            'item_type'        => $bk['item_type'],
            'start_date'       => $bk['start_date'],
            'end_date'         => $bk['end_date'],
            'time_period'      => $bk['time_period'],
            'units'            => $bk['units'],
            'amount'           => number_format((float)$bk['price'], 2, '.', ''),
            'currency'         => PAYHERE_CURRENCY,
            'customer_name'    => $cust_name,
            'customer_username'=> $bk['customer_username'],
            'customer_email'   => $bk['email'],
            'customer_phone'   => $bk['phone'],
        ];

        respond(['success' => true, 'receipt' => $receipt]);
        break;

    case 'payhere_notify':
        $merchant_id      = $_POST['merchant_id'] ?? '';
        $order_id         = $_POST['order_id'] ?? '';
        $payhere_amount   = $_POST['payhere_amount'] ?? '';
        $payhere_currency = $_POST['payhere_currency'] ?? '';
        $status_code      = $_POST['status_code'] ?? '';
        $md5sig           = $_POST['md5sig'] ?? '';
        $payment_id       = $_POST['payment_id'] ?? '';

        $local_md5sig = strtoupper(
            md5(
                $merchant_id . 
                $order_id . 
                $payhere_amount . 
                $payhere_currency . 
                $status_code . 
                strtoupper(md5(PAYHERE_MERCHANT_SECRET))
            )
        );

        if (($local_md5sig === $md5sig) && ($status_code == 2)) {
            $db = getDB();
            $now = date('Y-m-d H:i:s');
            $stmt = $db->prepare("UPDATE bookings SET status='confirmed', payment_status='paid', payment_method='PayHere', payment_id=?, paid_at=? WHERE order_id=?");
            $stmt->bind_param("sss", $payment_id, $now, $order_id);
            $stmt->execute();
            $stmt->close();
        }
        respond(['success' => true]);
        break;

    // ── WISHLIST (Heart Icon for Equipment) ───────────────────────────────────

    case 'toggle_wishlist':
        requireLogin();
        if (($_SESSION['role'] ?? '') !== 'Guest') {
            respond(['success' => false, 'message' => 'Wishlist is available for Guest accounts.'], 403);
        }
        $item_id   = sanitize($_POST['item_id'] ?? $_GET['item_id'] ?? '');
        $item_type = sanitize($_POST['item_type'] ?? $_GET['item_type'] ?? 'equipment');

        if (!$item_id) respond(['success' => false, 'message' => 'Item ID required.'], 400);

        $db = getDB();
        $user = $_SESSION['username'];

        $chk = $db->prepare("SELECT id FROM wishlists WHERE customer_username = ? AND item_type = ? AND item_id = ?");
        $chk->bind_param("sss", $user, $item_type, $item_id);
        $chk->execute();
        $exists = $chk->get_result()->fetch_assoc();
        $chk->close();

        if ($exists) {
            $del = $db->prepare("DELETE FROM wishlists WHERE customer_username = ? AND item_type = ? AND item_id = ?");
            $del->bind_param("sss", $user, $item_type, $item_id);
            $del->execute();
            $del->close();
            respond(['success' => true, 'in_wishlist' => false, 'message' => 'Removed from Wishlist.']);
        } else {
            $ins = $db->prepare("INSERT INTO wishlists (customer_username, item_type, item_id) VALUES (?,?,?)");
            $ins->bind_param("sss", $user, $item_type, $item_id);
            $ins->execute();
            $ins->close();
            respond(['success' => true, 'in_wishlist' => true, 'message' => 'Added to Wishlist! ❤️']);
        }
        break;

    case 'get_wishlist':
        requireLogin();
        if (($_SESSION['role'] ?? '') !== 'Guest') {
            respond(['success' => false, 'message' => 'Wishlist is for Guests.'], 403);
        }
        $db = getDB();
        $user = $_SESSION['username'];

        // Fetch from all 4 tables via UNION so wishlist works for every item type
        $stmt = $db->prepare("
            SELECT w.item_type, w.item_id as id, w.created_at as wished_at,
                   COALESCE(NULLIF(a.name,''), a.title) as name,
                   a.type, a.description, a.location, a.price, NULL as time_period,
                   a.image, a.images, COALESCE(a.rating,4.9) as rating, COALESCE(a.reviews,10) as reviews
            FROM wishlists w
            JOIN accommodations a ON w.item_id = a.id AND w.item_type='accommodation'
            WHERE w.customer_username = ?

            UNION ALL

            SELECT w.item_type, w.item_id as id, w.created_at as wished_at,
                   COALESCE(NULLIF(b.name,''), b.title) as name,
                   COALESCE(NULLIF(b.model,''), b.type) as type, b.description, b.location, b.price, b.time_period,
                   b.image, b.images, COALESCE(b.rating,4.9) as rating, COALESCE(b.reviews,10) as reviews
            FROM wishlists w
            JOIN bikes b ON w.item_id = b.id AND w.item_type='bike'
            WHERE w.customer_username = ?

            UNION ALL

            SELECT w.item_type, w.item_id as id, w.created_at as wished_at,
                   COALESCE(NULLIF(e.name,''), e.title) as name,
                   COALESCE(NULLIF(e.type,''), e.category) as type, e.description, e.location, e.price, e.time_period,
                   e.image, e.images, COALESCE(e.rating,4.9) as rating, COALESCE(e.reviews,10) as reviews
            FROM wishlists w
            JOIN equipment e ON w.item_id = e.id AND w.item_type='equipment'
            WHERE w.customer_username = ?

            UNION ALL

            SELECT w.item_type, w.item_id as id, w.created_at as wished_at,
                   g.name, 'Guide' as type,
                   COALESCE(NULLIF(g.description,''), g.bio) as description,
                   g.location, g.price, '1 day' as time_period,
                   COALESCE(NULLIF(g.image,''), g.avatar) as image, g.images,
                   COALESCE(g.rating,5.0) as rating, COALESCE(g.reviews,10) as reviews
            FROM wishlists w
            JOIN guides g ON w.item_id = g.id AND w.item_type='guide'
            WHERE w.customer_username = ?

            ORDER BY wished_at DESC
        ");
        $stmt->bind_param("ssss", $user, $user, $user, $user);
        $stmt->execute();
        $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();

        // Mark all as wishlisted (they're in the wishlist table)
        foreach ($rows as &$row) { $row['is_wishlisted'] = true; }
        respond(['success' => true, 'data' => $rows]);
        break;

    // ── ADMIN: CREATE ADMIN ───────────────────────────────────────────────────

    case 'create_admin':
        requireAdmin();
        if ($method !== 'POST') respond(['success' => false, 'message' => 'POST required'], 405);
        $full_name    = sanitize($_POST['full_name'] ?? '');
        $username     = sanitize($_POST['username'] ?? '');
        $email        = trim($_POST['email'] ?? '');
        $bank_details = sanitize($_POST['bank_details'] ?? '');
        $phone        = sanitize($_POST['phone'] ?? '');
        $password     = $_POST['password'] ?? '';
        $confirm      = $_POST['confirm_password'] ?? '';

        if (!$full_name || !$username || !$email || !$password) {
            respond(['success' => false, 'message' => 'Full name, username, email, and password are required.'], 400);
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            respond(['success' => false, 'message' => 'Invalid email address.'], 400);
        }
        if (strlen($password) < 6) {
            respond(['success' => false, 'message' => 'Password must be at least 6 characters.'], 400);
        }
        if ($password !== $confirm) {
            respond(['success' => false, 'message' => 'Passwords do not match.'], 400);
        }

        $db = getDB();
        $stmt = $db->prepare("SELECT username FROM users WHERE username=? OR email=?");
        $stmt->bind_param("ss", $username, $email);
        $stmt->execute();
        if ($stmt->get_result()->fetch_assoc()) {
            respond(['success' => false, 'message' => 'Username or email already exists.'], 409);
        }
        $stmt->close();

        $hash = password_hash($password, PASSWORD_DEFAULT);
        $stmt = $db->prepare("INSERT INTO users (full_name, name, username, email, bank_details, phone, password, role) VALUES (?,?,?,?,?,?,?,'Admin')");
        $stmt->bind_param("sssssss", $full_name, $full_name, $username, $email, $bank_details, $phone, $hash);
        if ($stmt->execute()) {
            $stmt->close();
            respond(['success' => true, 'message' => "Admin account created successfully for {$full_name} (@{$username})."]);
        } else {
            respond(['success' => false, 'message' => 'Failed to create admin: ' . $db->error], 500);
        }
        break;

    // ── PROFILE: UPDATE PROFILE ────────────────────────────────────────────────

    case 'update_profile':
        requireLogin();
        if ($method !== 'POST') respond(['success' => false, 'message' => 'POST required'], 405);
        $curr_username = $_SESSION['username'];
        $full_name     = sanitize($_POST['full_name'] ?? '');
        $email         = trim($_POST['email'] ?? '');
        $phone         = sanitize($_POST['phone'] ?? '');
        $bank_details  = sanitize($_POST['bank_details'] ?? '');
        $current_pass  = $_POST['current_password'] ?? '';
        $new_pass      = $_POST['new_password'] ?? '';
        $confirm_pass  = $_POST['confirm_new_password'] ?? '';

        if (!$full_name || !$email) {
            respond(['success' => false, 'message' => 'Full name and email are required.'], 400);
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            respond(['success' => false, 'message' => 'Invalid email address.'], 400);
        }

        $db = getDB();
        // Check email uniqueness if changed
        $check = $db->prepare("SELECT id FROM users WHERE email=? AND username!=?");
        $check->bind_param("ss", $email, $curr_username);
        $check->execute();
        if ($check->get_result()->fetch_assoc()) {
            $check->close();
            respond(['success' => false, 'message' => 'Email is already in use by another account.'], 409);
        }
        $check->close();

        // Optional password update
        if (!empty($new_pass)) {
            if (strlen($new_pass) < 6) {
                respond(['success' => false, 'message' => 'New password must be at least 6 characters.'], 400);
            }
            if ($new_pass !== $confirm_pass) {
                respond(['success' => false, 'message' => 'New passwords do not match.'], 400);
            }
            // Verify current password
            $pcheck = $db->prepare("SELECT password FROM users WHERE username=?");
            $pcheck->bind_param("s", $curr_username);
            $pcheck->execute();
            $prow = $pcheck->get_result()->fetch_assoc();
            $pcheck->close();
            if (!$prow || !password_verify($current_pass, $prow['password'])) {
                respond(['success' => false, 'message' => 'Current password is incorrect.'], 400);
            }
            $hash = password_hash($new_pass, PASSWORD_DEFAULT);
            $stmt = $db->prepare("UPDATE users SET full_name=?, name=?, email=?, phone=?, bank_details=?, password=? WHERE username=?");
            $stmt->bind_param("sssssss", $full_name, $full_name, $email, $phone, $bank_details, $hash, $curr_username);
        } else {
            $stmt = $db->prepare("UPDATE users SET full_name=?, name=?, email=?, phone=?, bank_details=? WHERE username=?");
            $stmt->bind_param("ssssss", $full_name, $full_name, $email, $phone, $bank_details, $curr_username);
        }

        if ($stmt->execute()) {
            $stmt->close();
            $_SESSION['full_name'] = $full_name;
            $_SESSION['email']     = $email;
            respond([
                'success' => true,
                'message' => 'Profile updated successfully!',
                'user' => [
                    'username'     => $curr_username,
                    'full_name'    => $full_name,
                    'email'        => $email,
                    'phone'        => $phone,
                    'bank_details' => $bank_details,
                    'role'         => $_SESSION['role'],
                ]
            ]);
        } else {
            respond(['success' => false, 'message' => 'Profile update failed: ' . $db->error], 500);
        }
        break;

    // ── ADMIN: EDIT ITEM DETAILS & PHOTOS ─────────────────────────────────────

    case 'edit_item':
        requireAdmin();
        if ($method !== 'POST') respond(['success' => false, 'message' => 'POST required'], 405);
        $type = sanitize($_POST['item_type'] ?? '');
        $id   = sanitize($_POST['id'] ?? '');

        $valid_types = ['accommodation', 'bike', 'equipment', 'guide'];
        if (!in_array($type, $valid_types) || !$id) {
            respond(['success' => false, 'message' => 'Invalid item type or ID.'], 400);
        }

        $tableMap = [
            'accommodation' => 'accommodations',
            'bike'          => 'bikes',
            'equipment'     => 'equipment',
            'guide'         => 'guides',
        ];
        $table = $tableMap[$type];
        $db = getDB();

        // Verify ownership
        $stmt = $db->prepare("SELECT created_by, image, images FROM `{$table}` WHERE id=?");
        $stmt->bind_param("s", $id);
        $stmt->execute();
        $existing = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$existing) respond(['success' => false, 'message' => 'Item not found.'], 404);
        if ($existing['created_by'] !== $_SESSION['username'] && ($_SESSION['role'] ?? '') !== 'Admin' && $_SESSION['username'] !== 'admin') {
            respond(['success' => false, 'message' => 'Unauthorized: You can only edit your own listings.'], 403);
        }

        // Upload new images or merge existing
        $subdir = $type === 'guide' ? 'guides' : ($type === 'bike' ? 'bikes' : $type);
        $uploaded = handleMultiImageUpload('images', $subdir);
        $existingImgs = !empty($existing['images']) ? json_decode($existing['images'], true) : [];
        if (!is_array($existingImgs) && !empty($existing['image'])) $existingImgs = [$existing['image']];
        
        // Retain existing images user kept
        $retained = !empty($_POST['keep_images']) ? (is_array($_POST['keep_images']) ? $_POST['keep_images'] : json_decode($_POST['keep_images'], true)) : $existingImgs;
        if (!is_array($retained)) $retained = [];
        
        $finalImgs = array_values(array_unique(array_filter(array_merge($retained, $uploaded))));
        if (empty($finalImgs)) {
            $finalImgs = [$existing['image'] ?: 'image/icon.jpg'];
        }
        $primaryImg = $finalImgs[0];
        $imgsJson = json_encode($finalImgs);

        $name = sanitize($_POST['name'] ?? '');
        $loc  = sanitize($_POST['location'] ?? '');
        $desc = sanitize($_POST['description'] ?? '');
        $price = (float)($_POST['price'] ?? 0);

        if (!$name || !$loc) {
            respond(['success' => false, 'message' => 'Name and location are required.'], 400);
        }

        if ($type === 'accommodation') {
            $subType = sanitize($_POST['type'] ?? 'Room');
            $units = (int)($_POST['units'] ?? 1);
            $af = !empty($_POST['available_from']) ? $_POST['available_from'] : null;
            $at = !empty($_POST['available_to']) ? $_POST['available_to'] : null;
            $upd = $db->prepare("UPDATE accommodations SET name=?, title=?, type=?, description=?, location=?, price=?, units=?, image=?, images=?, available_from=?, available_to=? WHERE id=?");
            $upd->bind_param("sssssdisssss", $name, $name, $subType, $desc, $loc, $price, $units, $primaryImg, $imgsJson, $af, $at, $id);
        } elseif ($type === 'bike') {
            $model = sanitize($_POST['model'] ?? 'City Bike');
            $tp = sanitize($_POST['time_period'] ?? '1 day');
            $af = !empty($_POST['available_from']) ? $_POST['available_from'] : null;
            $at = !empty($_POST['available_to']) ? $_POST['available_to'] : null;
            $upd = $db->prepare("UPDATE bikes SET name=?, title=?, model=?, type=?, description=?, location=?, price=?, time_period=?, image=?, images=?, available_from=?, available_to=? WHERE id=?");
            $upd->bind_param("ssssssdsssss", $name, $name, $model, $model, $desc, $loc, $price, $tp, $primaryImg, $imgsJson, $af, $at, $id);
        } elseif ($type === 'equipment') {
            $subType = sanitize($_POST['type'] ?? 'Other');
            $tp = sanitize($_POST['time_period'] ?? '1 day');
            $af = !empty($_POST['available_from']) ? $_POST['available_from'] : null;
            $at = !empty($_POST['available_to']) ? $_POST['available_to'] : null;
            $upd = $db->prepare("UPDATE equipment SET name=?, title=?, type=?, category=?, description=?, location=?, price=?, time_period=?, image=?, images=?, available_from=?, available_to=? WHERE id=?");
            $upd->bind_param("ssssssdsssss", $name, $name, $subType, $subType, $desc, $loc, $price, $tp, $primaryImg, $imgsJson, $af, $at, $id);
        } elseif ($type === 'guide') {
            $title = sanitize($_POST['title'] ?? $name); // fall back to name if not sent
            $exp = sanitize($_POST['experience'] ?? '');
            $lang = sanitize($_POST['languages'] ?? 'English, Sinhala');
            $upd = $db->prepare("UPDATE guides SET name=?, title=?, description=?, bio=?, location=?, experience=?, price=?, languages=?, image=?, avatar=?, images=? WHERE id=?");
            $upd->bind_param("ssssssdsssss", $name, $title, $desc, $desc, $loc, $exp, $price, $lang, $primaryImg, $primaryImg, $imgsJson, $id);
        }

        if ($upd && $upd->execute()) {
            $upd->close();
            respond(['success' => true, 'message' => "Item updated successfully!"]);
        } else {
            respond(['success' => false, 'message' => 'Failed to update item: ' . $db->error], 500);
        }
        break;

    // ── GUEST REVIEWS ─────────────────────────────────────────────────────────

    case 'submit_review':
        requireLogin();
        if (($_SESSION['role'] ?? '') !== 'Guest') {
            respond(['success' => false, 'message' => 'Only Guests can submit reviews.'], 403);
        }
        if ($method !== 'POST') respond(['success' => false, 'message' => 'POST required'], 405);
        $booking_id  = (int)($_POST['booking_id'] ?? 0);
        $rating      = (float)($_POST['rating'] ?? 5.0);
        $review_text = sanitize($_POST['review_text'] ?? '');

        if (!$booking_id || $rating < 1 || $rating > 5 || empty($review_text)) {
            respond(['success' => false, 'message' => 'Valid booking ID, rating (1-5), and review text are required.'], 400);
        }

        $db = getDB();
        $curr_user = $_SESSION['username'];

        // Verify booking belongs to this guest
        $bstmt = $db->prepare("SELECT item_type, item_id FROM bookings WHERE id=? AND customer_username=?");
        $bstmt->bind_param("is", $booking_id, $curr_user);
        $bstmt->execute();
        $bk = $bstmt->get_result()->fetch_assoc();
        $bstmt->close();

        if (!$bk) respond(['success' => false, 'message' => 'Booking not found or not owned by you.'], 404);

        $item_type = $bk['item_type'];
        $item_id   = $bk['item_id'];

        $stmt = $db->prepare("INSERT INTO reviews (booking_id, customer_username, item_type, item_id, rating, review_text) VALUES (?, ?, ?, ?, ?, ?) ON DUPLICATE KEY UPDATE rating=VALUES(rating), review_text=VALUES(review_text), created_at=CURRENT_TIMESTAMP");
        $stmt->bind_param("isssds", $booking_id, $curr_user, $item_type, $item_id, $rating, $review_text);
        if ($stmt->execute()) {
            $stmt->close();

            // Recalculate average rating and review count
            $rcalc = $db->prepare("SELECT AVG(rating) as avg_r, COUNT(*) as cnt FROM reviews WHERE item_type=? AND item_id=?");
            $rcalc->bind_param("ss", $item_type, $item_id);
            $rcalc->execute();
            $calc = $rcalc->get_result()->fetch_assoc();
            $rcalc->close();

            $new_rating = round((float)$calc['avg_r'], 2);
            $new_count  = (int)$calc['cnt'];

            $tableMap = [
                'accommodation' => 'accommodations',
                'bike'          => 'bikes',
                'equipment'     => 'equipment',
                'guide'         => 'guides',
            ];
            if (isset($tableMap[$item_type])) {
                $tbl = $tableMap[$item_type];
                $upd = $db->prepare("UPDATE `{$tbl}` SET rating=?, reviews=? WHERE id=?");
                $upd->bind_param("dis", $new_rating, $new_count, $item_id);
                $upd->execute();
                $upd->close();
            }

            respond([
                'success' => true,
                'message' => 'Thank you! Your review has been submitted successfully.',
                'rating' => $new_rating,
                'reviews_count' => $new_count
            ]);
        } else {
            respond(['success' => false, 'message' => 'Review submission failed: ' . $db->error], 500);
        }
        break;

    case 'get_reviews':
        $item_type = sanitize($_GET['item_type'] ?? '');
        $item_id   = sanitize($_GET['item_id'] ?? '');
        if (!$item_type || !$item_id) respond(['success' => false, 'message' => 'item_type and item_id required.'], 400);

        $db = getDB();
        $stmt = $db->prepare("SELECT r.*, COALESCE(u.full_name, u.name, r.customer_username) as reviewer_name
                              FROM reviews r
                              LEFT JOIN users u ON r.customer_username = u.username
                              WHERE r.item_type=? AND r.item_id=?
                              ORDER BY r.created_at DESC");
        $stmt->bind_param("ss", $item_type, $item_id);
        $stmt->execute();
        $reviews = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
        respond(['success' => true, 'data' => $reviews]);
        break;


    // ── DEFAULT ───────────────────────────────────────────────────────────────

    default:
        respond(['success' => false, 'message' => 'Unknown action: ' . htmlspecialchars($action)], 404);
        break;
}

// ─── Utility Functions ────────────────────────────────────────────────────────

function getItemName($db, $type, $id) {
    $details = getItemDetails($db, $type, $id);
    return $details['name'] ?? 'Item #' . htmlspecialchars($id);
}

function getItemDetails($db, $type, $id) {
    $tableMap = [
        'accommodation' => 'accommodations',
        'bike'          => 'bikes',
        'equipment'     => 'equipment',
        'guide'         => 'guides',
    ];
    $table = $tableMap[$type] ?? $type;
    $imgExpr = ($table === 'guides') ? "COALESCE(NULLIF(image,''), avatar)" : "image";
    $descExpr = ($table === 'guides') ? "COALESCE(NULLIF(description,''), bio)" : "description";
    $sql = "SELECT id, COALESCE(NULLIF(name,''), title) as name, location, price, {$imgExpr} as image, images, {$descExpr} as description, rating, reviews, created_by FROM `{$table}` WHERE id=?";
    $stmt = $db->prepare($sql);
    if (!$stmt) return ['name' => 'Archived Item', 'location' => '', 'price' => 0, 'image' => 'image/icon.jpg', 'images' => '[]', 'description' => '', 'rating' => 5.0, 'reviews' => 10, 'created_by' => 'admin'];
    $stmt->bind_param("s", $id);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return $row ?: ['name' => 'Archived Item', 'location' => '', 'price' => 0, 'image' => 'image/icon.jpg', 'images' => '[]', 'description' => '', 'rating' => 5.0, 'reviews' => 10, 'created_by' => 'admin'];
}

function getBookedDateRanges($db, $type, $id) {
    $ranges = [];
    $stmt = $db->prepare("SELECT id, start_date, end_date FROM bookings WHERE item_type=? AND item_id=? AND status!='cancelled' AND payment_status='paid' AND start_date IS NOT NULL ORDER BY start_date ASC");
    if ($stmt) {
        $stmt->bind_param("ss", $type, $id);
        $stmt->execute();
        $res = $stmt->get_result();
        while ($r = $res->fetch_assoc()) {
            $ranges[] = [
                'start_date' => $r['start_date'],
                'end_date'   => $r['end_date'] ?: $r['start_date'],
            ];
        }
        $stmt->close();
    }
    return $ranges;
}

