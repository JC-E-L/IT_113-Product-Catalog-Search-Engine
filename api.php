<?php
// Database connection
$host = 'localhost'; // Database host
$db = 'clothing'; // Database name
$user = 'mutillidae'; // Database user
$pass = 'jcladia123456'; // Database password

try {
    $conn = new PDO("mysql:host=$host;dbname=$db", $user, $pass);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    echo json_encode(['error' => 'Database connection failed: ' . $e->getMessage()]);
    exit;
}

header("Content-Type: application/json");

// Get the query parameter from the URL (instead of relying on URI parsing)
$path = isset($_GET['path']) ? $_GET['path'] : '';

// Routing Logic
switch ($path) {
    case 'products':

        if (isset($_GET['id']) && !empty($_GET['id'])) {
            viewProductDetails($conn, $_GET['id']);
        } else if (isset($_GET['category_id']) && !empty($_GET['category_id'])) {
            filterProductsByCategory($conn, $_GET['category_id']);
        } else {
            fetchAllProducts($conn);
        }
        break;

    case 'search':
        if (isset($_GET['min_price']) || isset($_GET['max_price'])) {
            searchProductsByPrice($conn);
        } else if (isset($_GET['category']) || isset($_GET['min_price']) || isset($_GET['max_price']) || isset($_GET['sort'])) {
            advancedSearchProducts($conn);
        } else {
            searchProducts($conn);
        }
        break;

    default:
        http_response_code(404);
        echo json_encode(["message" => "Route not found"]);
        break;
}

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


