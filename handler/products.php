<?php
require __DIR__ . '/../connect.php';

header("Content-Type: application/json");


// Function to fetch all products (Product Catalog)
$requestCounts = [];


//API for fetching all products

    function fetchAllProducts($conn)
    {
        global $requestCounts;

        // Get the client IP address
        $clientIP = $_SERVER['REMOTE_ADDR'];

        // Initialize request count for the IP address if it doesn't exist
        if (!isset($requestCounts[$clientIP])) {
            $requestCounts[$clientIP] = ['count' => 0, 'time' => time()];
        }

        // Check if the request limit is exceeded (e.g., 100 requests per minute)
        if ($requestCounts[$clientIP]['count'] >= 100 && (time() - $requestCounts[$clientIP]['time']) < 60) {
            echo json_encode(['error' => 'Rate limit exceeded. Please try again later.']);
            return;
        }

        // Increment the request count
        $requestCounts[$clientIP]['count']++;

        try {
            // Use JOIN to also fetch category names from product_category
            $sql = "SELECT p.*, pc.category_name 
                    FROM products p
                    LEFT JOIN product_category pc ON p.category_id = pc.category_id";

            $stmt = $conn->prepare($sql);
            $stmt->execute();
            $products = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Check if any products were found
            if ($products) {
                echo json_encode($products);
            } else {
                echo json_encode(["message" => "No products found."]);
            }
        } catch (Exception $e) {
            echo json_encode(['error' => 'Failed to fetch products: ' . $e->getMessage()]);
        }
    }

