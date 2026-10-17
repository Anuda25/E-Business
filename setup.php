<?php
require_once __DIR__ . '/config.php';

$conn_setup = new mysqli(DB_HOST, DB_USER, DB_PASS);
if ($conn_setup->connect_error) {
    die('<div style="color:red;font-family:sans-serif;padding:20px;">Connection failed: ' . $conn_setup->connect_error . '</div>');
}

$messages = [];
$errors = [];

// 1. Create / Verify database
$sql = "CREATE DATABASE IF NOT EXISTS `" . DB_NAME . "` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci";
if ($conn_setup->query($sql)) {
    $messages[] = "✅ Database '" . DB_NAME . "' created/verified.";
} else {
    $errors[] = "❌ Failed to create database: " . $conn_setup->error;
}
$conn_setup->select_db(DB_NAME);

// Helper function to check/add column
function ensureColumn($conn, $table, $column, $definition) {
    $check = $conn->query("SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = '" . DB_NAME . "' AND TABLE_NAME = '$table' AND COLUMN_NAME = '$column'");
    if ($check && (int)$check->fetch_row()[0] === 0) {
        $conn->query("ALTER TABLE `$table` ADD COLUMN `$column` $definition");
        return true;
    }
    return false;
}

// 2. Create / Repair `users` table
$sql = "CREATE TABLE IF NOT EXISTS `users` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `full_name` VARCHAR(150) NOT NULL DEFAULT '',
    `name` VARCHAR(150) NOT NULL DEFAULT '',
    `username` VARCHAR(80) NOT NULL UNIQUE,
    `email` VARCHAR(150) NOT NULL UNIQUE,
    `bank_details` VARCHAR(255) DEFAULT '',
    `phone` VARCHAR(50) DEFAULT '',
    `password` VARCHAR(255) NOT NULL,
    `role` ENUM('Guest','Admin') NOT NULL DEFAULT 'Guest',
    `avatar` TEXT,
    `joined` DATE DEFAULT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
if ($conn_setup->query($sql)) {
    $messages[] = "✅ Table 'users' verified.";
} else {
    $errors[] = "❌ Table 'users' error: " . $conn_setup->error;
}

// Repair users columns if created with different schema
ensureColumn($conn_setup, 'users', 'id', 'INT AUTO_INCREMENT UNIQUE KEY FIRST');
ensureColumn($conn_setup, 'users', 'full_name', "VARCHAR(150) NOT NULL DEFAULT ''");
ensureColumn($conn_setup, 'users', 'name', "VARCHAR(150) NOT NULL DEFAULT ''");
ensureColumn($conn_setup, 'users', 'bank_details', "VARCHAR(255) DEFAULT ''");
ensureColumn($conn_setup, 'users', 'phone', "VARCHAR(50) DEFAULT ''");
ensureColumn($conn_setup, 'users', 'avatar', "TEXT");
ensureColumn($conn_setup, 'users', 'joined', "DATE DEFAULT NULL");
ensureColumn($conn_setup, 'users', 'role', "VARCHAR(30) NOT NULL DEFAULT 'Guest'");

// Sync full_name and name
$conn_setup->query("UPDATE `users` SET `full_name`=`name` WHERE (`full_name`='' OR `full_name` IS NULL) AND `name`!=''");
$conn_setup->query("UPDATE `users` SET `name`=`full_name` WHERE (`name`='' OR `name` IS NULL) AND `full_name`!=''");

// Migrate old 'Host' roles to 'Admin'
$conn_setup->query("UPDATE `users` SET `role`='Admin' WHERE `role`='Host' OR `role` NOT IN ('Guest','Admin')");

// 3. Create / Repair `accommodations` table
$sql = "CREATE TABLE IF NOT EXISTS `accommodations` (
    `id` VARCHAR(80) PRIMARY KEY,
    `name` VARCHAR(200) NOT NULL DEFAULT '',
    `title` VARCHAR(200) NOT NULL DEFAULT '',
    `type` VARCHAR(80) NOT NULL DEFAULT 'Room',
    `description` TEXT,
    `location` VARCHAR(200) NOT NULL DEFAULT '',
    `price` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    `units` INT NOT NULL DEFAULT 1,
    `image` VARCHAR(255) DEFAULT '',
    `available_from` DATE DEFAULT NULL,
    `available_to` DATE DEFAULT NULL,
    `rating` DECIMAL(3,2) DEFAULT 4.90,
    `reviews` INT DEFAULT 10,
    `created_by` VARCHAR(80) NOT NULL DEFAULT 'admin',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
if ($conn_setup->query($sql)) {
    $messages[] = "✅ Table 'accommodations' verified.";
}
ensureColumn($conn_setup, 'accommodations', 'name', "VARCHAR(200) NOT NULL DEFAULT ''");
ensureColumn($conn_setup, 'accommodations', 'title', "VARCHAR(200) NOT NULL DEFAULT ''");
ensureColumn($conn_setup, 'accommodations', 'units', "INT NOT NULL DEFAULT 1");
ensureColumn($conn_setup, 'accommodations', 'images', "TEXT DEFAULT NULL");
ensureColumn($conn_setup, 'accommodations', 'available_from', "DATE DEFAULT NULL");
ensureColumn($conn_setup, 'accommodations', 'available_to', "DATE DEFAULT NULL");
ensureColumn($conn_setup, 'accommodations', 'created_by', "VARCHAR(80) NOT NULL DEFAULT 'admin'");
$conn_setup->query("UPDATE `accommodations` SET `name`=`title` WHERE (`name`='' OR `name` IS NULL) AND `title`!=''");
$conn_setup->query("UPDATE `accommodations` SET `title`=`name` WHERE (`title`='' OR `title` IS NULL) AND `name`!=''");
$conn_setup->query("UPDATE `accommodations` SET `created_by`='admin' WHERE `created_by` IS NULL OR `created_by`=''");

// 4. Create / Repair `bikes` table
$sql = "CREATE TABLE IF NOT EXISTS `bikes` (
    `id` VARCHAR(80) PRIMARY KEY,
    `name` VARCHAR(200) NOT NULL DEFAULT '',
    `title` VARCHAR(200) NOT NULL DEFAULT '',
    `model` VARCHAR(100) NOT NULL DEFAULT 'City Bike',
    `type` VARCHAR(100) NOT NULL DEFAULT 'City Bike',
    `description` TEXT,
    `location` VARCHAR(200) NOT NULL DEFAULT '',
    `price` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    `time_period` VARCHAR(50) DEFAULT '1 day',
    `image` VARCHAR(255) DEFAULT '',
    `images` TEXT DEFAULT NULL,
    `available_from` DATE DEFAULT NULL,
    `available_to` DATE DEFAULT NULL,
    `rating` DECIMAL(3,2) DEFAULT 4.90,
    `reviews` INT DEFAULT 10,
    `created_by` VARCHAR(80) NOT NULL DEFAULT 'admin',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
if ($conn_setup->query($sql)) {
    $messages[] = "✅ Table 'bikes' verified.";
}
ensureColumn($conn_setup, 'bikes', 'name', "VARCHAR(200) NOT NULL DEFAULT ''");
ensureColumn($conn_setup, 'bikes', 'title', "VARCHAR(200) NOT NULL DEFAULT ''");
ensureColumn($conn_setup, 'bikes', 'model', "VARCHAR(100) NOT NULL DEFAULT 'City Bike'");
ensureColumn($conn_setup, 'bikes', 'images', "TEXT DEFAULT NULL");
ensureColumn($conn_setup, 'bikes', 'time_period', "VARCHAR(50) DEFAULT '1 day'");
ensureColumn($conn_setup, 'bikes', 'available_from', "DATE DEFAULT NULL");
ensureColumn($conn_setup, 'bikes', 'available_to', "DATE DEFAULT NULL");
ensureColumn($conn_setup, 'bikes', 'created_by', "VARCHAR(80) NOT NULL DEFAULT 'admin'");
$conn_setup->query("UPDATE `bikes` SET `name`=`title` WHERE (`name`='' OR `name` IS NULL) AND `title`!=''");
$conn_setup->query("UPDATE `bikes` SET `title`=`name` WHERE (`title`='' OR `title` IS NULL) AND `name`!=''");
$conn_setup->query("UPDATE `bikes` SET `model`=`type` WHERE (`model`='' OR `model` IS NULL) AND `type`!=''");
$conn_setup->query("UPDATE `bikes` SET `type`=`model` WHERE (`type`='' OR `type` IS NULL) AND `model`!=''");
$conn_setup->query("UPDATE `bikes` SET `created_by`='admin' WHERE `created_by` IS NULL OR `created_by`=''");

// 5. Create / Repair `equipment` table
$sql = "CREATE TABLE IF NOT EXISTS `equipment` (
    `id` VARCHAR(80) PRIMARY KEY,
    `name` VARCHAR(200) NOT NULL DEFAULT '',
    `title` VARCHAR(200) NOT NULL DEFAULT '',
    `type` VARCHAR(100) NOT NULL DEFAULT 'Other',
    `category` VARCHAR(100) NOT NULL DEFAULT 'Other',
    `description` TEXT,
    `location` VARCHAR(200) NOT NULL DEFAULT '',
    `price` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    `deposit` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    `time_period` VARCHAR(50) DEFAULT '1 day',
    `image` VARCHAR(255) DEFAULT '',
    `images` TEXT DEFAULT NULL,
    `available_from` DATE DEFAULT NULL,
    `available_to` DATE DEFAULT NULL,
    `rating` DECIMAL(3,2) DEFAULT 4.90,
    `reviews` INT DEFAULT 10,
    `created_by` VARCHAR(80) NOT NULL DEFAULT 'admin',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
if ($conn_setup->query($sql)) {
    $messages[] = "✅ Table 'equipment' verified.";
}
ensureColumn($conn_setup, 'equipment', 'name', "VARCHAR(200) NOT NULL DEFAULT ''");
ensureColumn($conn_setup, 'equipment', 'title', "VARCHAR(200) NOT NULL DEFAULT ''");
ensureColumn($conn_setup, 'equipment', 'type', "VARCHAR(100) NOT NULL DEFAULT 'Other'");
ensureColumn($conn_setup, 'equipment', 'category', "VARCHAR(100) NOT NULL DEFAULT 'Other'");
ensureColumn($conn_setup, 'equipment', 'images', "TEXT DEFAULT NULL");
ensureColumn($conn_setup, 'equipment', 'deposit', "DECIMAL(10,2) NOT NULL DEFAULT 0.00");
ensureColumn($conn_setup, 'equipment', 'rating', "DECIMAL(3,2) DEFAULT 4.90");
ensureColumn($conn_setup, 'equipment', 'reviews', "INT DEFAULT 10");
ensureColumn($conn_setup, 'equipment', 'time_period', "VARCHAR(50) DEFAULT '1 day'");
ensureColumn($conn_setup, 'equipment', 'available_from', "DATE DEFAULT NULL");
ensureColumn($conn_setup, 'equipment', 'available_to', "DATE DEFAULT NULL");
ensureColumn($conn_setup, 'equipment', 'created_by', "VARCHAR(80) NOT NULL DEFAULT 'admin'");
$conn_setup->query("UPDATE `equipment` SET `name`=`title` WHERE (`name`='' OR `name` IS NULL) AND `title`!=''");
$conn_setup->query("UPDATE `equipment` SET `title`=`name` WHERE (`title`='' OR `title` IS NULL) AND `name`!=''");
$conn_setup->query("UPDATE `equipment` SET `type`=`category` WHERE (`type`='' OR `type` IS NULL) AND `category`!=''");
$conn_setup->query("UPDATE `equipment` SET `category`=`type` WHERE (`category`='' OR `category` IS NULL) AND `type`!=''");
$conn_setup->query("UPDATE `equipment` SET `created_by`='admin' WHERE `created_by` IS NULL OR `created_by`=''");
ensureColumn($conn_setup, 'accommodations', 'reviews', "INT DEFAULT 10");
ensureColumn($conn_setup, 'bikes', 'reviews', "INT DEFAULT 10");
ensureColumn($conn_setup, 'guides', 'reviews', "INT DEFAULT 10");

// 6. Create / Repair `guides` table
$sql = "CREATE TABLE IF NOT EXISTS `guides` (
    `id` VARCHAR(80) PRIMARY KEY,
    `name` VARCHAR(200) NOT NULL,
    `title` VARCHAR(200) DEFAULT '',
    `description` TEXT,
    `bio` TEXT,
    `location` VARCHAR(200) NOT NULL,
    `experience` VARCHAR(200) DEFAULT '',
    `price` DECIMAL(10,2) NOT NULL DEFAULT 45.00,
    `rating` DECIMAL(3,2) DEFAULT 5.0,
    `reviews` INT DEFAULT 10,
    `image` VARCHAR(255) DEFAULT '',
    `avatar` VARCHAR(255) DEFAULT '',
    `images` TEXT DEFAULT NULL,
    `tags` TEXT,
    `languages` VARCHAR(255) DEFAULT 'English, Sinhala',
    `created_by` VARCHAR(80) NOT NULL DEFAULT 'admin',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
if ($conn_setup->query($sql)) {
    $messages[] = "✅ Table 'guides' verified.";
}
ensureColumn($conn_setup, 'guides', 'title', "VARCHAR(200) DEFAULT ''");
ensureColumn($conn_setup, 'guides', 'description', "TEXT");
ensureColumn($conn_setup, 'guides', 'bio', "TEXT");
ensureColumn($conn_setup, 'guides', 'experience', "VARCHAR(200) DEFAULT ''");
ensureColumn($conn_setup, 'guides', 'price', "DECIMAL(10,2) NOT NULL DEFAULT 45.00");
ensureColumn($conn_setup, 'guides', 'image', "VARCHAR(255) DEFAULT ''");
ensureColumn($conn_setup, 'guides', 'avatar', "VARCHAR(255) DEFAULT ''");
ensureColumn($conn_setup, 'guides', 'images', "TEXT DEFAULT NULL");
ensureColumn($conn_setup, 'guides', 'tags', "TEXT");
ensureColumn($conn_setup, 'guides', 'languages', "VARCHAR(255) DEFAULT 'English, Sinhala'");
ensureColumn($conn_setup, 'guides', 'available_from', "DATE DEFAULT NULL");
ensureColumn($conn_setup, 'guides', 'available_to', "DATE DEFAULT NULL");
ensureColumn($conn_setup, 'guides', 'created_by', "VARCHAR(80) NOT NULL DEFAULT 'admin'");
$conn_setup->query("UPDATE `guides` SET `description`=`bio` WHERE (`description`='' OR `description` IS NULL) AND `bio`!=''");
$conn_setup->query("UPDATE `guides` SET `bio`=`description` WHERE (`bio`='' OR `bio` IS NULL) AND `description`!=''");
$conn_setup->query("UPDATE `guides` SET `image`=`avatar` WHERE (`image`='' OR `image` IS NULL) AND `avatar`!=''");
$conn_setup->query("UPDATE `guides` SET `avatar`=`image` WHERE (`avatar`='' OR `avatar` IS NULL) AND `image`!=''");
$conn_setup->query("UPDATE `guides` SET `created_by`='admin' WHERE `created_by` IS NULL OR `created_by`=''");

// 7. Create / Repair `bookings` table
$sql = "CREATE TABLE IF NOT EXISTS `bookings` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `customer_username` VARCHAR(80) NOT NULL,
    `item_type` ENUM('accommodation','bike','equipment','guide') NOT NULL,
    `item_id` VARCHAR(80) NOT NULL,
    `created_by` VARCHAR(80) NOT NULL DEFAULT 'admin',
    `start_date` DATE DEFAULT NULL,
    `end_date` DATE DEFAULT NULL,
    `time_period` VARCHAR(50) DEFAULT '',
    `units` INT DEFAULT 1,
    `price` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    `status` ENUM('pending','confirmed','cancelled') NOT NULL DEFAULT 'pending',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
if ($conn_setup->query($sql)) {
    $messages[] = "✅ Table 'bookings' verified.";
}
ensureColumn($conn_setup, 'bookings', 'customer_username', "VARCHAR(80) NOT NULL DEFAULT ''");
ensureColumn($conn_setup, 'bookings', 'created_by', "VARCHAR(80) NOT NULL DEFAULT 'admin'");
ensureColumn($conn_setup, 'bookings', 'item_id', "VARCHAR(80) NOT NULL DEFAULT ''");
ensureColumn($conn_setup, 'bookings', 'item_type', "VARCHAR(50) NOT NULL DEFAULT 'accommodation'");
ensureColumn($conn_setup, 'bookings', 'start_date', "DATE DEFAULT NULL");
ensureColumn($conn_setup, 'bookings', 'end_date', "DATE DEFAULT NULL");
ensureColumn($conn_setup, 'bookings', 'time_period', "VARCHAR(50) DEFAULT ''");
ensureColumn($conn_setup, 'bookings', 'units', "INT DEFAULT 1");
ensureColumn($conn_setup, 'bookings', 'price', "DECIMAL(10,2) NOT NULL DEFAULT 0.00");
ensureColumn($conn_setup, 'bookings', 'status', "VARCHAR(50) NOT NULL DEFAULT 'pending'");
ensureColumn($conn_setup, 'bookings', 'order_id', "VARCHAR(100) DEFAULT ''");
ensureColumn($conn_setup, 'bookings', 'payment_status', "VARCHAR(50) NOT NULL DEFAULT 'unpaid'");
ensureColumn($conn_setup, 'bookings', 'payment_method', "VARCHAR(100) DEFAULT 'PayHere'");
ensureColumn($conn_setup, 'bookings', 'payment_id', "VARCHAR(100) DEFAULT ''");
ensureColumn($conn_setup, 'bookings', 'paid_at', "DATETIME DEFAULT NULL");

// 8. Create / Repair `wishlists` table (for Guest Equipment Wishlist with Heart Icon)
$sql = "CREATE TABLE IF NOT EXISTS `wishlists` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `customer_username` VARCHAR(80) NOT NULL,
    `item_type` VARCHAR(50) NOT NULL DEFAULT 'equipment',
    `item_id` VARCHAR(80) NOT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY `unique_wish` (`customer_username`, `item_type`, `item_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
if ($conn_setup->query($sql)) {
    $messages[] = "✅ Table 'wishlists' verified.";
}

// 9. Create / Repair `reviews` table (for Guest Reviews on Booked Items)
$sql = "CREATE TABLE IF NOT EXISTS `reviews` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `booking_id` INT NOT NULL,
    `customer_username` VARCHAR(80) NOT NULL,
    `item_type` VARCHAR(50) NOT NULL,
    `item_id` VARCHAR(80) NOT NULL,
    `rating` DECIMAL(3,2) NOT NULL DEFAULT 5.00,
    `review_text` TEXT NOT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY `unique_booking_review` (`booking_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
if ($conn_setup->query($sql)) {
    $messages[] = "✅ Table 'reviews' verified.";
}

// 10. Default Accounts setup
// Admin account: admin / admin123
$admin_username = 'admin';
$admin_pass_hash = password_hash('admin123', PASSWORD_DEFAULT);
$admin_email = 'admin@example.test';
$admin_name = 'Tour Guide Admin';
$admin_phone = '';
$admin_bank = '';

$stmt = $conn_setup->prepare("SELECT username FROM users WHERE username = ?");
$stmt->bind_param("s", $admin_username);
$stmt->execute();
$admin_exists = $stmt->get_result()->fetch_assoc();
$stmt->close();

if ($admin_exists) {
    $stmt = $conn_setup->prepare("UPDATE users SET role='Admin' WHERE username=?");
    $stmt->bind_param("s", $admin_username);
    $stmt->execute();
    $stmt->close();
    $messages[] = "✅ Default Admin account verified. Username: admin / Password: admin123";
} else {
    $stmt = $conn_setup->prepare("INSERT INTO users (full_name, name, username, email, bank_details, phone, password, role) VALUES (?,?,?,?,?,?,?,'Admin')");
    $stmt->bind_param("sssssss", $admin_name, $admin_name, $admin_username, $admin_email, $admin_bank, $admin_phone, $admin_pass_hash);
    if ($stmt->execute()) {
        $messages[] = "✅ Default Admin account created. Username: admin / Password: admin123";
    } else {
        $errors[] = "❌ Failed to create admin: " . $stmt->error;
    }
    $stmt->close();
}

// Guest account: traveler / password123
$guest_username = 'traveler';
$guest_pass_hash = password_hash('password123', PASSWORD_DEFAULT);
$guest_email = 'traveler@example.test';
$guest_name = 'Demo Traveler';
$guest_phone = '';
$guest_bank = '';

$stmt = $conn_setup->prepare("SELECT username FROM users WHERE username = ?");
$stmt->bind_param("s", $guest_username);
$stmt->execute();
$guest_exists = $stmt->get_result()->fetch_assoc();
$stmt->close();

if ($guest_exists) {
    $stmt = $conn_setup->prepare("UPDATE users SET role='Guest' WHERE username=?");
    $stmt->bind_param("s", $guest_username);
    $stmt->execute();
    $stmt->close();
    $messages[] = "✅ Default Guest account verified. Username: traveler / Password: password123";
} else {
    $stmt = $conn_setup->prepare("INSERT INTO users (full_name, name, username, email, bank_details, phone, password, role) VALUES (?,?,?,?,?,?,?,'Guest')");
    $stmt->bind_param("sssssss", $guest_name, $guest_name, $guest_username, $guest_email, $guest_bank, $guest_phone, $guest_pass_hash);
    if ($stmt->execute()) {
        $messages[] = "✅ Default Guest account created. Username: traveler / Password: password123";
    } else {
        $errors[] = "❌ Failed to create guest: " . $stmt->error;
    }
    $stmt->close();
}

// 11. Catalog Seeding (At least 2 items for every sub-item with distinct photos & multi-image arrays)
$seed_accommodations = [
    // Villa (3 items)
    ['id'=>'acc-1', 'name'=>'Luxury Palm Villa & Private Pool', 'type'=>'Villa', 'desc'=>'Architectural oceanfront masterpiece with private infinity pool, tropical gardens, chef service, and sunset views.', 'loc'=>'Mirissa, Sri Lanka', 'price'=>260.00, 'units'=>2, 'img'=>'image/accommodation/acc-villa-1.jpg', 'imgs'=>['image/accommodation/acc-villa-1.jpg', 'image/accommodation/acc-villa-1-2.jpg']],
    ['id'=>'acc-villa-2', 'name'=>'Sunset Beachfront Villa & Spa', 'type'=>'Villa', 'desc'=>'Stunning beachfront villa featuring private direct beach access, outdoor jacuzzi, and butler service.', 'loc'=>'Bentota, Sri Lanka', 'price'=>310.00, 'units'=>1, 'img'=>'image/accommodation/acc-villa-2.jpg', 'imgs'=>['image/accommodation/acc-villa-2.jpg', 'image/accommodation/acc-villa-2-2.jpg']],
    ['id'=>'acc-villa-3', 'name'=>'Hillside Tea Plantation Villa', 'type'=>'Villa', 'desc'=>'Colonial-style hillside estate surrounded by emerald tea plantations with panoramic mountain mist vistas.', 'loc'=>'Nuwara Eliya, Sri Lanka', 'price'=>220.00, 'units'=>2, 'img'=>'image/accommodation/acc-villa-3.jpg', 'imgs'=>['image/accommodation/acc-villa-3.jpg', 'image/accommodation/acc-villa-3-2.jpg']],
    // Apartment (3 items)
    ['id'=>'acc-2', 'name'=>'Skyline Luxury Penthouse Apartment', 'type'=>'Apartment', 'desc'=>'Modern luxury penthouse in Colombo with panoramic Indian Ocean views, high-speed fiber internet, and rooftop pool.', 'loc'=>'Colombo, Sri Lanka', 'price'=>145.00, 'units'=>3, 'img'=>'image/accommodation/acc-apt-1.jpg', 'imgs'=>['image/accommodation/acc-apt-1.jpg', 'image/accommodation/acc-apt-1-2.jpg']],
    ['id'=>'acc-apt-2', 'name'=>'Galle Fort Colonial Heritage Apartment', 'type'=>'Apartment', 'desc'=>'Charming 2-bedroom restored Dutch colonial apartment inside the historic UNESCO World Heritage Galle Fort.', 'loc'=>'Galle Fort, Sri Lanka', 'price'=>125.00, 'units'=>2, 'img'=>'image/accommodation/acc-apt-2.jpg', 'imgs'=>['image/accommodation/acc-apt-2.jpg', 'image/accommodation/acc-apt-2-2.jpg']],
    ['id'=>'acc-apt-3', 'name'=>'Marina Bayfront Serviced Apartment', 'type'=>'Apartment', 'desc'=>'Executive waterfront apartment complete with designer kitchen, gym access, and 24/7 security concierge.', 'loc'=>'Negombo Lagoon, Sri Lanka', 'price'=>110.00, 'units'=>4, 'img'=>'image/accommodation/acc-apt-3.jpg', 'imgs'=>['image/accommodation/acc-apt-3.jpg', 'image/accommodation/acc-apt-1-2.jpg']],
    // Room (3 items)
    ['id'=>'acc-room-1', 'name'=>'Deluxe Oceanview King Room', 'type'=>'Room', 'desc'=>'Spacious king suite with private balcony overlooking Unawatuna Bay. Includes artisan breakfast and air conditioning.', 'loc'=>'Unawatuna, Sri Lanka', 'price'=>65.00, 'units'=>5, 'img'=>'image/accommodation/acc-room-1.jpg', 'imgs'=>['image/accommodation/acc-room-1.jpg', 'image/accommodation/acc-room-1-2.jpg']],
    ['id'=>'acc-room-2', 'name'=>'Botanical Sanctuary Garden Room', 'type'=>'Room', 'desc'=>'Quiet and serene boutique garden room next to the Royal Botanical Gardens. Natural wood decor and high ceilings.', 'loc'=>'Kandy, Sri Lanka', 'price'=>55.00, 'units'=>4, 'img'=>'image/accommodation/acc-room-2.jpg', 'imgs'=>['image/accommodation/acc-room-2.jpg', 'image/accommodation/acc-room-1.jpg']],
    ['id'=>'acc-room-3', 'name'=>'Surfside Cabana Room', 'type'=>'Room', 'desc'=>'Cozy thatched-roof eco-cabana room situated directly on the surf beach point. Step straight onto the golden sand.', 'loc'=>'Arugam Bay, Sri Lanka', 'price'=>45.00, 'units'=>6, 'img'=>'image/accommodation/acc-room-3.jpg', 'imgs'=>['image/accommodation/acc-room-3.jpg', 'image/accommodation/acc-villa-2.jpg']],
];

foreach ($seed_accommodations as $item) {
    $imgsJson = json_encode($item['imgs']);
    $stmt = $conn_setup->prepare("INSERT IGNORE INTO accommodations (id, name, title, type, description, location, price, units, image, images, created_by) VALUES (?,?,?,?,?,?,?,?,?,?,'admin')");
    $stmt->bind_param("ssssssdiss", $item['id'], $item['name'], $item['name'], $item['type'], $item['desc'], $item['loc'], $item['price'], $item['units'], $item['img'], $imgsJson);
    $stmt->execute();
    $stmt->close();
}

// BIKES (Sub-items: Mountain Bike, Road Bike, City Bike, Electric Bike - at least 2 each)
$seed_bikes = [
    // Mountain Bike (2 items)
    ['id'=>'bike-mtn-1', 'name'=>'Trek Marlin 7 Trail Mountain Bike', 'model'=>'Mountain Bike', 'desc'=>'Rugged hardtail mountain bike with hydraulic disc brakes and RockShox front suspension for tea country trails.', 'loc'=>'Ella & Central Highlands', 'price'=>25.00, 'period'=>'1 day', 'img'=>'image/bikes/bike-mtn-1.jpg', 'imgs'=>['image/bikes/bike-mtn-1.jpg', 'image/bikes/bike-mtn-1-2.jpg']],
    ['id'=>'bike-mtn-2', 'name'=>'Giant Talon 29er Mountain Bike', 'model'=>'Mountain Bike', 'desc'=>'High-performance 29-inch wheel trail bike designed for steep climbs, rough gravel paths, and mountain adventures.', 'loc'=>'Knuckles Mountain Range', 'price'=>28.00, 'period'=>'1 day', 'img'=>'image/bikes/bike-mtn-2.jpg', 'imgs'=>['image/bikes/bike-mtn-2.jpg', 'image/bikes/bike-mtn-1.jpg']],
    // Road Bike (2 items)
    ['id'=>'bike-road-1', 'name'=>'Cannondale Synapse Carbon Road Bike', 'model'=>'Road Bike', 'desc'=>'Lightweight endurance road bike featuring carbon frame and Shimano gearing for fast coastal tarmac touring.', 'loc'=>'Colombo Marine Drive', 'price'=>32.00, 'period'=>'1 day', 'img'=>'image/bikes/bike-road-1.jpg', 'imgs'=>['image/bikes/bike-road-1.jpg', 'image/bikes/bike-road-2.jpg']],
    ['id'=>'bike-road-2', 'name'=>'Specialized Allez Sport Road Bike', 'model'=>'Road Bike', 'desc'=>'Aerodynamic performance road bicycle tailored for long-distance scenic rides along the southern expressway and coast.', 'loc'=>'Galle Coastal Route', 'price'=>30.00, 'period'=>'1 day', 'img'=>'image/bikes/bike-road-2.jpg', 'imgs'=>['image/bikes/bike-road-2.jpg', 'image/bikes/bike-road-1.jpg']],
    // City Bike (2 items)
    ['id'=>'bike-city-1', 'name'=>'Gazelle Classic Dutch City Bike', 'model'=>'City Bike', 'desc'=>'Comfortable step-through city cruiser with front wicker basket, bell, and puncture-resistant tyres for relaxed sightseeing.', 'loc'=>'Galle Fort, Sri Lanka', 'price'=>14.00, 'period'=>'1 day', 'img'=>'image/bikes/bike-city-1.jpg', 'imgs'=>['image/bikes/bike-city-1.jpg', 'image/bikes/bike-city-2.jpg']],
    ['id'=>'bike-city-2', 'name'=>'Linus Roadster Commuter City Bike', 'model'=>'City Bike', 'desc'=>'Sleek retro urban commuter bike equipped with leather saddle and rear luggage rack for leisurely city exploration.', 'loc'=>'Colombo Cinnamon Gardens', 'price'=>16.00, 'period'=>'1 day', 'img'=>'image/bikes/bike-city-2.jpg', 'imgs'=>['image/bikes/bike-city-2.jpg', 'image/bikes/bike-city-1.jpg']],
    // Electric Bike (2 items)
    ['id'=>'bike-ebike-1', 'name'=>'RadPower RadCity Step-Thru E-Bike', 'model'=>'Electric Bike', 'desc'=>'Effortless electric bike with 750W motor and 45-mile battery range. Breeze up hills with 5 levels of pedal assist.', 'loc'=>'Kandy Lake Promenade', 'price'=>35.00, 'period'=>'1 day', 'img'=>'image/bikes/bike-ebike-1.jpg', 'imgs'=>['image/bikes/bike-ebike-1.jpg', 'image/bikes/bike-ebike-2.jpg']],
    ['id'=>'bike-ebike-2', 'name'=>'Specialized Turbo Vado Electric Cruiser', 'model'=>'Electric Bike', 'desc'=>'Premium Swiss-engineered electric touring bike with smooth pedal assistance, integrated LED headlights, and digital display.', 'loc'=>'Mirissa Beach Strip', 'price'=>40.00, 'period'=>'1 day', 'img'=>'image/bikes/bike-ebike-2.jpg', 'imgs'=>['image/bikes/bike-ebike-2.jpg', 'image/bikes/bike-ebike-1.jpg']],
    // Extra Classic items (Cruiser & Dirt Bike)
    ['id'=>'bike-1', 'name'=>'Royal Enfield Classic 350 Gold', 'model'=>'Road Bike', 'desc'=>'Iconic vintage motorcycle perfect for cruising scenic Southern coastal roads. Serviced regularly with deep exhaust rumble.', 'loc'=>'Galle & South Coast', 'price'=>35.00, 'period'=>'1 day', 'img'=>'image/bikes/bike-enfield.jpg', 'imgs'=>['image/bikes/bike-enfield.jpg', 'image/bikes/bike-road-1.jpg']],
    ['id'=>'bike-2', 'name'=>'Honda CRF 250 Rally Dirt Bike', 'model'=>'Mountain Bike', 'desc'=>'Conquer mountain trails, tea estate dirt roads, and rugged terrain with high ground clearance and long travel suspension.', 'loc'=>'Ella & Central Highlands', 'price'=>45.00, 'period'=>'1 day', 'img'=>'image/bikes/bike-dirt.jpg', 'imgs'=>['image/bikes/bike-dirt.jpg', 'image/bikes/bike-mtn-1.jpg']],
];

foreach ($seed_bikes as $item) {
    $imgsJson = json_encode($item['imgs']);
    $stmt = $conn_setup->prepare("INSERT IGNORE INTO bikes (id, name, title, model, type, description, location, price, time_period, image, images, created_by) VALUES (?,?,?,?,?,?,?,?,?,?,?,'admin')");
    $stmt->bind_param("sssssssdsss", $item['id'], $item['name'], $item['name'], $item['model'], $item['model'], $item['desc'], $item['loc'], $item['price'], $item['period'], $item['img'], $imgsJson);
    $stmt->execute();
    $stmt->close();
}

// EQUIPMENT (Sub-items: Surfboard, Skateboard, Boat, Kayak, Snorkeling Gear, Camping Gear - at least 2 each)
$seed_equipment = [
    // Surfboard (2 items)
    ['id'=>'eq-surf-1', 'name'=>'Pro Epoxy 7ft Performance Surfboard', 'type'=>'Surfboard', 'desc'=>'High-performance epoxy surfboard for intermediate to advanced waves. Fast, lightweight, includes leash and wax.', 'loc'=>'Weligama / Arugam Bay', 'price'=>15.00, 'period'=>'1 day', 'img'=>'image/equipment/eq-surf-1.jpg', 'imgs'=>['image/equipment/eq-surf-1.jpg', 'image/equipment/eq-surf-1-2.jpg']],
    ['id'=>'eq-surf-2', 'name'=>'Torq Soft-Top 8ft Learner Surfboard', 'type'=>'Surfboard', 'desc'=>'Stable high-buoyancy beginner surfboard. Safe rubber soft fins and high-grip deck for catching your first waves.', 'loc'=>'Midigama Surf Point', 'price'=>12.00, 'period'=>'1 day', 'img'=>'image/equipment/eq-surf-2.jpg', 'imgs'=>['image/equipment/eq-surf-2.jpg', 'image/equipment/eq-surf-1.jpg']],
    // Skateboard (2 items)
    ['id'=>'eq-sk-1', 'name'=>'Loaded Bamboo Longboard Skateboard', 'type'=>'Skateboard', 'desc'=>'Flexible bamboo flex carving longboard for smooth cruising along promenades and ocean boardwalks.', 'loc'=>'Colombo Promenade', 'price'=>10.00, 'period'=>'1 day', 'img'=>'image/equipment/eq-sk-1.jpg', 'imgs'=>['image/equipment/eq-sk-1.jpg', 'image/equipment/eq-sk-2.jpg']],
    ['id'=>'eq-sk-2', 'name'=>'Globe Sun City Cruiser Skateboard', 'type'=>'Skateboard', 'desc'=>'Compact retro cruiser skateboard with ultra-smooth 65mm polyurethane wheels and protective pad set.', 'loc'=>'Galle Face Green, Colombo', 'price'=>8.00, 'period'=>'1 day', 'img'=>'image/equipment/eq-sk-2.jpg', 'imgs'=>['image/equipment/eq-sk-2.jpg', 'image/equipment/eq-sk-1.jpg']],
    // Boat (2 items)
    ['id'=>'eq-bt-1', 'name'=>'Yamaha 21ft Coastal Speedboat Charter', 'type'=>'Boat', 'desc'=>'Twin-engine motorboat with bimini shade canopy, safety vests, and captain service for coastal estuary tours.', 'loc'=>'Bentota Lagoon & River', 'price'=>75.00, 'period'=>'4 hours', 'img'=>'image/equipment/eq-bt-1.jpg', 'imgs'=>['image/equipment/eq-bt-1.jpg', 'image/equipment/eq-bt-2.jpg']],
    ['id'=>'eq-bt-2', 'name'=>'Yamaha WaveRunner Speedboat JetSki', 'type'=>'Boat', 'desc'=>'Exhilarating 180hp WaveRunner delivered directly to the beach with complete safety gear and beginner briefing.', 'loc'=>'Negombo Coast', 'price'=>85.00, 'period'=>'1 hour', 'img'=>'image/equipment/eq-bt-2.jpg', 'imgs'=>['image/equipment/eq-bt-2.jpg', 'image/equipment/eq-bt-1.jpg']],
    // Kayak (2 items)
    ['id'=>'eq-ky-1', 'name'=>'Dual Ocean Sea Kayak with Paddles', 'type'=>'Kayak', 'desc'=>'Tandem 2-seater ocean kayak complete with ergonomic padded backrests, high-visibility paddles, and waterproof dry bag.', 'loc'=>'Bentota River, Sri Lanka', 'price'=>30.00, 'period'=>'1 day', 'img'=>'image/equipment/eq-ky-1.jpg', 'imgs'=>['image/equipment/eq-ky-1.jpg', 'image/equipment/eq-ky-2.jpg']],
    ['id'=>'eq-ky-2', 'name'=>'Perception Tribe Single Sit-On Kayak', 'type'=>'Kayak', 'desc'=>'Agile single sit-on-top kayak designed for tranquil lake exploration, birdwatching, and mangrove photography.', 'loc'=>'Koggala Lake, Sri Lanka', 'price'=>20.00, 'period'=>'1 day', 'img'=>'image/equipment/eq-ky-2.jpg', 'imgs'=>['image/equipment/eq-ky-2.jpg', 'image/equipment/eq-ky-1.jpg']],
    // Snorkeling Gear (2 items)
    ['id'=>'eq-sn-1', 'name'=>'Cressi Panoramic Snorkel & Mask Set', 'type'=>'Snorkeling Gear', 'desc'=>'180-degree panoramic anti-fog silicone mask with dry-top purge snorkel and adjustable travel diving fins.', 'loc'=>'Hikkaduwa Coral Reef', 'price'=>14.00, 'period'=>'1 day', 'img'=>'image/equipment/eq-sn-1.jpg', 'imgs'=>['image/equipment/eq-sn-1.jpg', 'image/equipment/eq-sn-2.jpg']],
    ['id'=>'eq-sn-2', 'name'=>'Mares Reef Pro Scuba Diving & Snorkel Set', 'type'=>'Snorkeling Gear', 'desc'=>'Full dive-certified snorkeling and reef scuba set complete with neoprene boots, regulator, and dive computer.', 'loc'=>'Pigeon Island Marine Sanctuary', 'price'=>22.00, 'period'=>'1 day', 'img'=>'image/equipment/eq-sn-2.jpg', 'imgs'=>['image/equipment/eq-sn-2.jpg', 'image/equipment/eq-sn-1.jpg']],
    // Camping Gear (2 items)
    ['id'=>'eq-camp-1', 'name'=>'4-Person Waterproof Expedition Tent', 'type'=>'Camping Gear', 'desc'=>'Double-layer weatherproof camping tent with thermal floor, pop-up assembly, sleeping pads, and LED lantern.', 'loc'=>'Horton Plains / Ella', 'price'=>20.00, 'period'=>'1 day', 'img'=>'image/equipment/eq-camp-1.jpg', 'imgs'=>['image/equipment/eq-camp-1.jpg', 'image/equipment/eq-camp-2.jpg']],
    ['id'=>'eq-camp-2', 'name'=>'MSR Hubba Hubba 2-Person Ultralight Tent', 'type'=>'Camping Gear', 'desc'=>'Ultralight 2-person freestanding backpacking tent with rainfly, compact carry sack, and storm stakes.', 'loc'=>'Knuckles Range Campsites', 'price'=>16.00, 'period'=>'1 day', 'img'=>'image/equipment/eq-camp-2.jpg', 'imgs'=>['image/equipment/eq-camp-2.jpg', 'image/equipment/eq-camp-1.jpg']],
];

foreach ($seed_equipment as $item) {
    $imgsJson = json_encode($item['imgs']);
    $stmt = $conn_setup->prepare("INSERT IGNORE INTO equipment (id, name, title, type, category, description, location, price, time_period, image, images, created_by) VALUES (?,?,?,?,?,?,?,?,?,?,?,'admin')");
    $stmt->bind_param("sssssssdsss", $item['id'], $item['name'], $item['name'], $item['type'], $item['type'], $item['desc'], $item['loc'], $item['price'], $item['period'], $item['img'], $imgsJson);
    $stmt->execute();
    $stmt->close();
}

// GUIDES (Sub-items: Wildlife, Surfing, Heritage, Mountain - at least 2 each)
$seed_guides = [
    // Wildlife (2 guides)
    ['id'=>'guide-1', 'name'=>'Dinesh Wickramasinghe', 'title'=>'Senior Wildlife & Wilderness Safari Expert', 'desc'=>'Certified wilderness guide with 10+ years leading Yala National Park leopard safaris and birdwatching expeditions.', 'exp'=>'10+ years certified wilderness & wildlife naturalist', 'price'=>45.00, 'rating'=>4.96, 'loc'=>'Ella & Central Highlands', 'img'=>'image/guides/guide-dinesh.jpg', 'imgs'=>['image/guides/guide-dinesh.jpg'], 'tags'=>json_encode(['Wildlife','Safari','Leopard Tracking'])],
    ['id'=>'guide-wildlife-2', 'name'=>'Priya Jayasuriya', 'title'=>'Yala Leopard & Endemic Bird Watching Specialist', 'desc'=>'Award-winning wildlife photographer and naturalist specialising in leopard tracking, elephant herds, and endemic species.', 'exp'=>'8 years national park ranger and wildlife photographer', 'price'=>50.00, 'rating'=>4.93, 'loc'=>'Yala & Udawalawe National Park', 'img'=>'image/guides/guide-priya.jpg', 'imgs'=>['image/guides/guide-priya.jpg'], 'tags'=>json_encode(['Wildlife','Bird Watching','Photography'])],
    // Surfing (2 guides)
    ['id'=>'guide-2', 'name'=>'Samantha Perera', 'title'=>'ISA Certified Surfing & Ocean Safety Coach', 'desc'=>'Local surfing champion offering personalized surf coaching, secret reef break discovery, and coastal culture tours.', 'exp'=>'ISA Certified surf instructor & former national competitor', 'price'=>40.00, 'rating'=>4.92, 'loc'=>'Mirissa & Arugam Bay', 'img'=>'image/guides/guide-samantha.jpg', 'imgs'=>['image/guides/guide-samantha.jpg'], 'tags'=>json_encode(['Surfing','Ocean Safety','Beginner to Pro'])],
    ['id'=>'guide-surf-2', 'name'=>'Kasun Fernando', 'title'=>'Pro Surfer & Certified Ocean Lifeguard Instructor', 'desc'=>'Lifeguard certified surfing mentor specializing in wave reading, board balance, and beginner coaching in gentle bays.', 'exp'=>'7 years professional surf coaching & beach lifeguard', 'price'=>38.00, 'rating'=>4.85, 'loc'=>'Weligama Bay & Hikkaduwa', 'img'=>'image/guides/guide-kasun.jpg', 'imgs'=>['image/guides/guide-kasun.jpg'], 'tags'=>json_encode(['Surfing','Lifeguard','Reef Breaks'])],
    // Heritage (2 guides)
    ['id'=>'guide-3', 'name'=>'Rohan Silva', 'title'=>'Heritage & Ancient Rock Architecture Historian', 'desc'=>'Archaeology graduate specializing in Sigiriya Rock Fortress, Polonnaruwa medieval ruins, and Buddhist cultural walkthroughs.', 'exp'=>'BA Archaeology & licensed cultural heritage tour guide', 'price'=>42.00, 'rating'=>4.88, 'loc'=>'Sigiriya, Dambulla & Kandy', 'img'=>'image/guides/guide-rohan.jpg', 'imgs'=>['image/guides/guide-rohan.jpg'], 'tags'=>json_encode(['Heritage','Ancient Ruins','Temple Art'])],
    ['id'=>'guide-heritage-2', 'name'=>'Nirmala Perera', 'title'=>'Buddhist Temple & Colonial Fortress Historian', 'desc'=>'Historian offering deep cultural immersion into the Temple of the Tooth, Galle Dutch Fort, and colonial Ceylon history.', 'exp'=>'12 years historical research & licensed tour narrator', 'price'=>48.00, 'rating'=>4.90, 'loc'=>'Kandy & Galle Fort', 'img'=>'image/guides/guide-nirmala.jpg', 'imgs'=>['image/guides/guide-nirmala.jpg'], 'tags'=>json_encode(['Heritage','Colonial History','Culture'])],
    // Mountain (2 guides)
    ['id'=>'guide-mtn-1', 'name'=>'Aruna Bandara', 'title'=>'Knuckles Range & Adams Peak Trekking Guide', 'desc'=>'Elite mountain guide specializing in multi-day Knuckles Range cloud-forest expeditions and Adams Peak sunrise ascents.', 'exp'=>'Over 300 successful summit climbs & wilderness first-aid certified', 'price'=>55.00, 'rating'=>4.91, 'loc'=>'Knuckles Range & Adams Peak', 'img'=>'image/guides/guide-aruna.jpg', 'imgs'=>['image/guides/guide-aruna.jpg'], 'tags'=>json_encode(['Mountain','Trekking','Sunrise Climb'])],
    ['id'=>'guide-mtn-2', 'name'=>'Nuwan Gamage', 'title'=>'Ella Rock & High Plains Trekking Specialist', 'desc'=>'Central highlands hiking expert guiding Ella Rock, Little Adams Peak, and Mini Worlds End with tea estate history.', 'exp'=>'9 years guiding highland trails and mountain passes', 'price'=>46.00, 'rating'=>4.89, 'loc'=>'Ella & Haputale Highlands', 'img'=>'image/guides/guide-nuwan.jpg', 'imgs'=>['image/guides/guide-nuwan.jpg'], 'tags'=>json_encode(['Mountain','Highlands Trek','Tea Trails'])],
];

foreach ($seed_guides as $item) {
    $imgsJson = json_encode($item['imgs']);
    $stmt = $conn_setup->prepare("INSERT IGNORE INTO guides (id, name, title, description, bio, experience, price, rating, location, image, avatar, images, tags, created_by) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,'admin')");
    $stmt->bind_param("ssssssddsssss", $item['id'], $item['name'], $item['title'], $item['desc'], $item['desc'], $item['exp'], $item['price'], $item['rating'], $item['loc'], $item['img'], $item['img'], $imgsJson, $item['tags']);
    $stmt->execute();
    $stmt->close();
}

// Normalize older sub-item types to guarantee clean filter matching
$conn_setup->query("UPDATE `accommodations` SET `type`='Room' WHERE LOWER(`type`) IN ('rooms','room','units')");
$conn_setup->query("UPDATE `accommodations` SET `type`='Villa' WHERE LOWER(`type`)='villa'");
$conn_setup->query("UPDATE `accommodations` SET `type`='Apartment' WHERE LOWER(`type`)='apartment'");

$conn_setup->query("UPDATE `bikes` SET `model`='Road Bike', `type`='Road Bike' WHERE LOWER(`model`) IN ('royal-enfield','cruiser','road bike') OR LOWER(`type`) IN ('royal-enfield','cruiser')");
$conn_setup->query("UPDATE `bikes` SET `model`='Mountain Bike', `type`='Mountain Bike' WHERE LOWER(`model`) IN ('dirt-bike','mountain-bike','mountain bike') OR LOWER(`type`) IN ('dirt-bike','mountain-bike')");
$conn_setup->query("UPDATE `bikes` SET `model`='City Bike', `type`='City Bike' WHERE LOWER(`model`) IN ('city bike','scooter') OR LOWER(`type`) IN ('city bike','scooter')");
$conn_setup->query("UPDATE `bikes` SET `model`='Electric Bike', `type`='Electric Bike' WHERE LOWER(`model`) IN ('electric bike','electric-bike') OR LOWER(`type`) IN ('electric bike','electric-bike')");

$conn_setup->query("UPDATE `equipment` SET `type`='Camping Gear', `category`='Camping Gear' WHERE LOWER(`type`) IN ('camping','camping gear') OR LOWER(`category`) IN ('camping','camping gear')");
$conn_setup->query("UPDATE `equipment` SET `type`='Snorkeling Gear', `category`='Snorkeling Gear' WHERE LOWER(`type`) IN ('scuba','snorkeling gear','snorkel') OR LOWER(`category`) IN ('scuba','snorkeling gear','snorkel')");
$conn_setup->query("UPDATE `equipment` SET `type`='Boat', `category`='Boat' WHERE LOWER(`type`) IN ('boat') OR LOWER(`category`) IN ('boat')");
$conn_setup->query("UPDATE `equipment` SET `type`='Kayak', `category`='Kayak' WHERE LOWER(`type`) IN ('kayak') OR LOWER(`category`) IN ('kayak')");
$conn_setup->query("UPDATE `equipment` SET `type`='Surfboard', `category`='Surfboard' WHERE LOWER(`type`) IN ('surfboard') OR LOWER(`category`) IN ('surfboard')");
$conn_setup->query("UPDATE `equipment` SET `type`='Skateboard', `category`='Skateboard' WHERE LOWER(`type`) IN ('skateboard') OR LOWER(`category`) IN ('skateboard')");

$messages[] = "✅ Catalog verified and seeded: distinct photos, multi-image arrays, guide prices, reviews table, and at least 2 items guaranteed for every sub-item.";

// Ensure image directories exist
$dirs = [
    __DIR__ . '/image',
    __DIR__ . '/image/logo',
    __DIR__ . '/image/accommodation',
    __DIR__ . '/image/bikes',
    __DIR__ . '/image/equipment',
    __DIR__ . '/image/guides',
    __DIR__ . '/image/uploads',
];
foreach ($dirs as $dir) {
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }
}
$messages[] = "✅ Image and upload directories verified.";

$conn_setup->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Setup — Tour Guide</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: 'Segoe UI', sans-serif; background: #0b1329; color: #e2e8f0; min-height: 100vh; display: flex; align-items: center; justify-content: center; padding: 24px; }
        .card { background: #131f37; border: 1px solid rgba(56,189,248,0.2); border-radius: 16px; padding: 36px; max-width: 720px; width: 100%; box-shadow: 0 25px 50px rgba(0,0,0,0.6); }
        h1 { font-size: 2rem; background: linear-gradient(135deg, #38bdf8, #818cf8); -webkit-background-clip: text; -webkit-text-fill-color: transparent; margin-bottom: 8px; }
        .subtitle { color: #94a3b8; margin-bottom: 24px; font-size: 0.95rem; }
        .msg { padding: 10px 14px; margin: 6px 0; border-radius: 8px; font-size: 0.88rem; }
        .msg.success { background: rgba(34,197,94,0.12); border-left: 3px solid #22c55e; color: #86efac; }
        .msg.error { background: rgba(239,68,68,0.12); border-left: 3px solid #ef4444; color: #fca5a5; }
        .btn { display: inline-block; margin-top: 24px; padding: 14px 32px; background: linear-gradient(135deg, #38bdf8, #818cf8); color: #fff; text-decoration: none; border-radius: 10px; font-weight: 600; font-size: 1rem; transition: opacity 0.2s; border: none; cursor: pointer; }
        .btn:hover { opacity: 0.85; }
        .creds-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; margin-top: 20px; }
        .creds { background: rgba(56,189,248,0.06); border: 1px solid rgba(56,189,248,0.25); border-radius: 10px; padding: 16px; }
        .creds h3 { color: #38bdf8; margin-bottom: 8px; font-size: 1.05rem; }
        .creds p { color: #94a3b8; margin: 4px 0; font-size: 0.88rem; }
        .creds strong { color: #f1f5f9; }
    </style>
</head>
<body>
<div class="card">
    <h1>🧭 Tour Guide Setup &amp; Migration</h1>
    <p class="subtitle">Database repair, catalog seeding (2+ items per sub-type), and account validation</p>
    <?php foreach ($messages as $m): ?>
        <div class="msg success"><?= htmlspecialchars($m) ?></div>
    <?php endforeach; ?>
    <?php foreach ($errors as $e): ?>
        <div class="msg error"><?= htmlspecialchars($e) ?></div>
    <?php endforeach; ?>
    <?php if (empty($errors)): ?>
        <div class="creds-grid">
            <div class="creds">
                <h3>👑 Default Admin</h3>
                <p>Username: <strong>admin</strong></p>
                <p>Password: <strong>admin123</strong></p>
                <p>Role: <strong>Admin</strong></p>
            </div>
            <div class="creds">
                <h3>👤 Default Guest</h3>
                <p>Username: <strong>traveler</strong></p>
                <p>Password: <strong>password123</strong></p>
                <p>Role: <strong>Guest</strong></p>
            </div>
        </div>
        <a href="index.html" class="btn">🚀 Open Tour Guide Website</a>
    <?php else: ?>
        <p style="margin-top:20px;color:#fca5a5;">Please resolve any MySQL errors above and reload setup.php.</p>
    <?php endif; ?>
</div>
</body>
</html>
