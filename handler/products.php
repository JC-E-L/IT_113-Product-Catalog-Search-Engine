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

//API for Viewing product details via product ID
function viewProductDetails($conn, $productId)
{
    // Validate the product ID to ensure it is a valid integer
    if (!filter_var($productId, FILTER_VALIDATE_INT)) {
        echo json_encode(['error' => 'Invalid product ID.']);
        return;
    }

    try {
        // Fetch the product details including category and associated photos (if any)
        $sql = "SELECT p.product_id, p.product_name, p.description, p.price, p.size, p.color, 
                        p.material, p.date_added, pc.category_name, 
                        GROUP_CONCAT(p.image_url) AS photos
                    FROM products p
                    LEFT JOIN product_category pc ON p.category_id = pc.category_id
                    WHERE p.product_id = :product_id
                    GROUP BY p.product_id";

        $stmt = $conn->prepare($sql);
        $stmt->bindParam(':product_id', $productId, PDO::PARAM_INT);
        $stmt->execute();
        $product = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($product) {
            // Convert the 'photos' column to an array if it's not null
            $product['photos'] = $product['photos'] ? explode(',', $product['photos']) : [];
            echo json_encode($product);
        } else {
            echo json_encode(["message" => "Product not found."]);
        }
    } catch (Exception $e) {
        echo json_encode(['error' => 'Failed to fetch product details: ' . $e->getMessage()]);
    }
}



//API for filtering the products vy category using category ID
function filterProductsByCategory($conn, $categoryId)
{
    if (!filter_var($categoryId, FILTER_VALIDATE_INT)) {
        echo json_encode(['error' => 'Invalid category ID.']);
        return;
    }
    try {
        // Fetch the products that belong to the specified category
        $sql = "SELECT p.*, pc.category_id
                    FROM products p
                    LEFT JOIN product_category pc ON p.category_id = pc.category_id
                    WHERE p.category_id = :category_id";

        $stmt = $conn->prepare($sql);
        $stmt->bindParam(':category_id', $categoryId, PDO::PARAM_STR);
        $stmt->execute();
        $products = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if ($products) {
            echo json_encode($products);
        } else {
            echo json_encode(["message" => "No products found in this category."]);
        }
    } catch (Exception $e) {
        echo json_encode(['error' => 'Failed to fetch products: ' . $e->getMessage()]);
    }
}
