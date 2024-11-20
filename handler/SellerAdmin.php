<?php
require_once 'vendor/autoload.php';
require_once __DIR__ . '/../connect.php';

use Firebase\JWT\JWT;
use Firebase\JWT\Key;

header("Content-Type: application/json");

// Helper functions
function getUserIdFromToken()
{
    $headers = getallheaders();

    if (!isset($headers['Authorization'])) {
        throw new Exception('No authorization header found');
    }

    $authHeader = $headers['Authorization'];
    $token = str_replace('Bearer ', '', $authHeader);

    if (empty($token)) {
        throw new Exception('Token is empty');
    }

    try {
        $secret_key = "your_strong_secret_key_123!@#";
        $decoded = JWT::decode($token, new Key($secret_key, 'HS256'));
        return $decoded->user_id;
    } catch (Exception $e) {
        throw new Exception('Token validation failed: ' . $e->getMessage());
    }
}

function createUserProduct($conn, $data)
{
    try {
        $user_id = getUserIdFromToken();

        if (!$user_id) {
            throw new Exception('Invalid user ID retrieved from token');
        }

        $stmt = $conn->prepare("INSERT INTO products (product_name, description, price, 
                                                    category_id, size, color, material, 
                                                    user_id, date_added) 
                            VALUES (?, ?, ?, ?, ?, ?, ?, ?, CURDATE())");

        $result = $stmt->execute([
            $data['product_name'],  
            $data['description'],
            $data['price'],
            $data['category_id'],
            $data['size'],
            $data['color'],
            $data['material'],
            $user_id
        ]);

        return $result;
    } catch (Exception $e) {
        echo json_encode(['error' => 'Failed to create product: ' . $e->getMessage()]);
        return false;
    }
}
