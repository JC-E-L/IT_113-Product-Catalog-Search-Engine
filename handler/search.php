<?php
require __DIR__ . '/../connect.php';  

header("Content-Type: application/json");
// Function to search for products by name or tags
function searchProducts($conn)
{
    $search = isset($_GET['q']) ? trim($_GET['q']) : ''; // Trim input to remove extra spaces
    if (empty($search)) {
        echo json_encode(["message" => "Please provide a search query"]);
        return;
    }

    // Check for minimum search length
    if (strlen($search) < 3) {
        echo json_encode(["error" => "Search term must be at least 3 characters long."]);
        return;
    }

    // Restrict input to only alphanumeric characters and basic punctuation to further prevent SQL injection
    $sanitizedSearch = preg_replace('/[^a-zA-Z0-9\s]/', '', $search);
    if ($sanitizedSearch !== $search) {
        echo json_encode(["error" => "Invalid characters in search term."]);
        return;
    }

    try {
        // SQL query with LIKE search using parameterized inputs
        $sql = "SELECT p.*, pc.category_name 
                FROM products p
                LEFT JOIN product_category pc ON p.category_id = pc.category_id
                WHERE p.product_name LIKE :search 
                OR pc.category_name LIKE :search";

        $stmt = $conn->prepare($sql);

        // Prepare search parameter with wildcard for LIKE
        $searchQuery = '%' . $sanitizedSearch . '%';
        $stmt->bindParam(':search', $searchQuery, PDO::PARAM_STR);

        $stmt->execute();
        $products = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Check if any products were found
        if ($products) {
            echo json_encode($products);
        } else {
            echo json_encode(["message" => "No products found."]);
        }
    } catch (Exception $e) {
        echo json_encode(['error' => 'Search failed: ' . $e->getMessage()]);
    }
}

// API for Searching Products by Price
function searchProductsByPrice($conn)
{
    // Get the search term from the 'query' parameter in the URL
    $search = isset($_GET['query']) ? trim($_GET['query']) : '';
    if (empty($search)) {
        echo json_encode(["message" => "Please provide a search query."]);
        return;
    }

    // Validate length of the search term
    if (strlen($search) < 3) {
        echo json_encode(["error" => "Search term must be at least 3 characters long."]);
        return;
    }

    // Sanitize the search term to only allow alphanumeric characters and whitespace
    if (!preg_match('/^[a-zA-Z0-9\s]+$/', $search)) {
        echo json_encode(["error" => "Invalid characters in search term."]);
        return;
    }

    // Get and validate price range parameters
    $minPrice = isset($_GET['min_price']) ? $_GET['min_price'] : null;
    $maxPrice = isset($_GET['max_price']) ? $_GET['max_price'] : null;

    if ($minPrice !== null && !is_numeric($minPrice)) {
        echo json_encode(["error" => "Invalid minimum price."]);
        return;
    }
    if ($maxPrice !== null && !is_numeric($maxPrice)) {
        echo json_encode(["error" => "Invalid maximum price."]);
        return;
    }

    try {
        // Build the SQL query with search and price filtering
        $sql = "SELECT p.*, pc.category_id 
                FROM products p
                LEFT JOIN product_category pc ON p.category_id = pc.category_id
                WHERE (p.product_name LIKE :search OR p.description LIKE :search)";

        // Add price filtering if specified
        if ($minPrice !== null) {
            $sql .= " AND p.price >= :min_price";
        }
        if ($maxPrice !== null) {
            $sql .= " AND p.price <= :max_price";
        }

        $stmt = $conn->prepare($sql);

        // Bind the search parameter with wildcard for LIKE
        $searchQuery = '%' . $search . '%';
        $stmt->bindParam(':search', $searchQuery, PDO::PARAM_STR);

        // Bind price parameters if they are provided and are numeric
        if ($minPrice !== null) {
            $stmt->bindValue(':min_price', (float)$minPrice, PDO::PARAM_STR);
        }
        if ($maxPrice !== null) {
            $stmt->bindValue(':max_price', (float)$maxPrice, PDO::PARAM_STR);
        }

        $stmt->execute();
        $products = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Check if any products were found
        if ($products) {
            echo json_encode($products);
        } else {
            echo json_encode(["message" => "No products found."]);
        }
    } catch (Exception $e) {
        echo json_encode(['error' => 'Search failed: ' . $e->getMessage()]);
    }
}



//API for searching the product details which include, name of the product, category ID, price range and sorting
function advancedSearchProducts($conn)
{
    // Get the search term from the 'query' parameter in the URL
    $search = isset($_GET['query']) ? trim($_GET['query']) : '';
    if (empty($search)) {
        echo json_encode(["message" => "Please provide a search query."]);
        return;
    }

    // Validate length of the search term
    if (strlen($search) < 3) {
        echo json_encode(["error" => "Search term must be at least 3 characters long."]);
        return;
    }

    // Sanitize the search term to allow only alphanumeric characters and spaces
    if (!preg_match('/^[a-zA-Z0-9\s]+$/', $search)) {
        echo json_encode(["error" => "Invalid characters in search term."]);
        return;
    }

    // Get and sanitize additional query parameters
    $categoryName = isset($_GET['category']) ? trim($_GET['category']) : null;
    $minPrice = isset($_GET['min_price']) ? $_GET['min_price'] : null;
    $maxPrice = isset($_GET['max_price']) ? $_GET['max_price'] : null;
    $sortBy = isset($_GET['sort']) ? trim($_GET['sort']) : 'product_name';

    // Define allowed sorting options
    $validSortOptions = ['product_name', 'price', 'date_added'];

    // Verify that the sort option is valid (map it to the actual database column)
    if (!in_array($sortBy, $validSortOptions)) {
        echo json_encode(["error" => "Invalid sort option."]);
        return;
    }

    // Validate and sanitize price inputs
    if ($minPrice !== null && !is_numeric($minPrice)) {
        echo json_encode(["error" => "Invalid minimum price."]);
        return;
    }
    if ($maxPrice !== null && !is_numeric($maxPrice)) {
        echo json_encode(["error" => "Invalid maximum price."]);
        return;
    }

    try {
        // Start building the SQL query
        $sql = "SELECT p.*, pc.category_name 
                FROM products p
                LEFT JOIN product_category pc ON p.category_id = pc.category_id
                WHERE (p.product_name LIKE :search OR p.description LIKE :search)";

        // Add filters based on provided parameters
        if ($categoryName !== null) {
            // Bind category name safely to prevent SQL injection
            $sql .= " AND pc.category_name = :category_name";
        }
        if ($minPrice !== null) {
            $sql .= " AND p.price >= :min_price";
        }
        if ($maxPrice !== null) {
            $sql .= " AND p.price <= :max_price";
        }

        // Add safe sorting clause (preventing SQL injection in ORDER BY)
        $sql .= " ORDER BY " . $sortBy;

        // Prepare the SQL query
        $stmt = $conn->prepare($sql);

        // Bind the search term with wildcard for LIKE
        $searchQuery = '%' . $search . '%';
        $stmt->bindParam(':search', $searchQuery, PDO::PARAM_STR);

        // Bind other parameters if provided
        if ($categoryName !== null) {
            $stmt->bindParam(':category_name', $categoryName, PDO::PARAM_STR);
        }
        if ($minPrice !== null) {
            $stmt->bindValue(':min_price', (float)$minPrice, PDO::PARAM_STR);
        }
        if ($maxPrice !== null) {
            $stmt->bindValue(':max_price', (float)$maxPrice, PDO::PARAM_STR);
        }

        // Execute the query
        $stmt->execute();
        $products = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Check if any products were found
        if ($products) {
            echo json_encode($products);
        } else {
            echo json_encode(["message" => "No products found."]);
        }
    } catch (Exception $e) {
        echo json_encode(['error' => 'Search failed: ' . $e->getMessage()]);
    }
}
