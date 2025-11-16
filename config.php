<?php
// config.php - adjust DB credentials if needed
$DB_HOST = 'localhost';
$DB_NAME = 'medicine_system';
$DB_USER = 'root';
$DB_PASS = ''; // default XAMPP root password is empty

try {
    $pdo = new PDO("mysql:host=$DB_HOST;dbname=$DB_NAME;charset=utf8mb4", $DB_USER, $DB_PASS);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (Exception $e) {
    die("DB Connection failed: " . $e->getMessage());
}
?>