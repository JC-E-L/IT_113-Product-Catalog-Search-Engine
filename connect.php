<?php
// Database connection
$host = 'localhost'; // Database host
$db = 'clothing2'; // Database name
$user = 'mutillidae'; // Database user
$pass = 'jcladia123456'; // Database password

try {
    $conn = new PDO("mysql:host=$host;dbname=$db", $user, $pass);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    echo json_encode(['error' => 'Database connection failed: ' . $e->getMessage()]);
    exit;
}
