<?php
require __DIR__ . '/../connect.php';  

header("Content-Type: application/json");
// Function to search for products by name or tags
function searchProducts($conn)
{
    $search = isset($_GET['name']) ? trim($_GET['name']) : ''; // Trim input to remove extra spaces
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
    $search = isset($_GET['name']) ? trim($_GET['name']) : '';
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
    $price = isset($_GET['price']) ? $_GET['price'] : null;


    if ($price !== null && !is_numeric($price)) {
        echo json_encode(["error" => "Invalid minimum price."]);
        return;
    }

    try {
        // Build the SQL query with search and price filtering
        $sql = "SELECT p.*, pc.category_id 
                FROM products p
                LEFT JOIN product_category pc ON p.category_id = pc.category_id
                WHERE (p.product_name LIKE :search OR p.description LIKE :search)";

        // Add price filtering if specified
        if ($price !== null) {
            $sql .= " AND p.price <= :price || p.price >= :price";
        }
        $stmt = $conn->prepare($sql);

        // Bind the search parameter with wildcard for LIKE
        $searchQuery = '%' . $search . '%';
        $stmt->bindParam(':search', $searchQuery, PDO::PARAM_STR);

        // Bind price parameters if they are provided and are numeric
        if ($price !== null) {
            $stmt->bindValue(':price', (float)$price, PDO::PARAM_STR);
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

// function advancedSearchProducts($conn)
// {
//     // Get and sanitize query parameters
//     $search = isset($_GET['name']) ? trim($_GET['name']) : '';
//     $categoryName = isset($_GET['category']) ? trim($_GET['category']) : null;
//     $minPrice = isset($_GET['min_price']) ? trim($_GET['min_price']) : null;
//     $maxPrice = isset($_GET['max_price']) ? trim($_GET['max_price']) : null;
//     $sortBy = isset($_GET['sort']) ? trim($_GET['sort']) : 'product_name';

//     // Validate input
//     if (empty($search) && empty($categoryName) && $minPrice === null && $maxPrice === null) {
//         echo json_encode(["message" => "Please provide at least one search query."]);
//         return;
//     }
//     if (!empty($search) && strlen($search) < 3) {
//         echo json_encode(["error" => "Search term must be at least 3 characters long."]);
//         return;
//     }
//     if (!empty($search) && !preg_match('/^[a-zA-Z0-9\s]+$/', $search)) {
//         echo json_encode(["error" => "Invalid characters in search term."]);
//         return;
//     }

//     // Define valid sort options
//     $validSortOptions = [
//         'product_name', 'price', 'date_added',
//         'product_name_desc', 'price_desc', 'date_added_desc'
//     ];

//     // Validate and map sort options
//     if (!in_array($sortBy, $validSortOptions)) {
//         echo json_encode(["error" => "Invalid sort option."]);
//         return;
//     }
//     $sortColumn = str_replace('_desc', '', $sortBy);
//     $sortDirection = strpos($sortBy, '_desc') !== false ? 'DESC' : 'ASC';

//     // Validate price range inputs
//     if (($minPrice !== null && !is_numeric($minPrice)) || ($maxPrice !== null && !is_numeric($maxPrice))) {
//         echo json_encode(["error" => "Invalid price value."]);
//         return;
//     }

//     // Start building the query
//     $sql = "SELECT p.*, pc.category_name 
//             FROM products p
//             LEFT JOIN product_category pc ON p.category_id = pc.category_id
//             WHERE 1=1"; // Base condition for appending filters

//     // Add filters for product_name
//     if (!empty($search)) {
//         $sql .= " AND p.product_name LIKE :search";
//     }

//     // Add filters for price range
//     if ($minPrice !== null) {
//         $sql .= " AND p.price >= :min_price";
//     }
//     if ($maxPrice !== null) {
//         $sql .= " AND p.price <= :max_price";
//     }

//     // Add filters for category_name (case-insensitive partial match)
//     if (!empty($categoryName)) {
//         $sql .= " AND LOWER(pc.category_name) LIKE LOWER(:category_name)";
//     }

//     // Add sorting
//     $sql .= " ORDER BY $sortColumn $sortDirection";

//     try {
//         // Prepare the query
//         $stmt = $conn->prepare($sql);

//         // Bind parameters
//         if (!empty($search)) {
//             $searchQuery = '%' . $search . '%'; // Match any part of the product name
//             $stmt->bindParam(':search', $searchQuery, PDO::PARAM_STR);
//         }
//         if ($minPrice !== null) {
//             $stmt->bindParam(':min_price', $minPrice, PDO::PARAM_INT);
//         }
//         if ($maxPrice !== null) {
//             $stmt->bindParam(':max_price', $maxPrice, PDO::PARAM_INT);
//         }
//         if (!empty($categoryName)) {
//             $categoryQuery = '%' . $categoryName . '%'; // Match any part of the category name
//             $stmt->bindParam(':category_name', $categoryQuery, PDO::PARAM_STR);
//         }

//         // Execute and fetch results
//         $stmt->execute();
//         $products = $stmt->fetchAll(PDO::FETCH_ASSOC);

//         // Check if products were found
//         if ($products) {
//             echo json_encode($products);
//         } else {
//             echo json_encode(["message" => "No products found."]);
//         }
//     } catch (Exception $e) {
//         echo json_encode(['error' => 'Search failed: ' . $e->getMessage()]);
//     }
// }

function advancedSearchProducts($conn)
{
    // Get and sanitize query parameters
    $search = isset($_GET['name']) ? trim($_GET['name']) : '';
    $categoryName = isset($_GET['category']) ? trim($_GET['category']) : null;
    $minPrice = isset($_GET['min_price']) ? trim($_GET['min_price']) : null;
    $maxPrice = isset($_GET['max_price']) ? trim($_GET['max_price']) : null;
    $material = isset($_GET['material']) ? trim($_GET['material']) : null;
    $color = isset($_GET['color']) ? trim($_GET['color']) : null;
    $sortBy = isset($_GET['sort']) ? trim($_GET['sort']) : 'product_name';

    // Validate input
    if (empty($search) && empty($categoryName) && $minPrice === null && $maxPrice === null && empty($material) && empty($color)) {
        echo json_encode(["message" => "Please provide at least one search query."]);
        return;
    }
    if (!empty($search) && strlen($search) < 3) {
        echo json_encode(["error" => "Search term must be at least 3 characters long."]);
        return;
    }
    if (!empty($search) && !preg_match('/^[a-zA-Z0-9\s]+$/', $search)) {
        echo json_encode(["error" => "Invalid characters in search term."]);
        return;
    }

    // Define valid sort options
    $validSortOptions = [
        'product_name', 'price', 'date_added',
        'product_name_desc', 'price_desc', 'date_added_desc'
    ];

    // Validate and map sort options
    if (!in_array($sortBy, $validSortOptions)) {
        echo json_encode(["error" => "Invalid sort option."]);
        return;
    }
    $sortColumn = str_replace('_desc', '', $sortBy);
    $sortDirection = strpos($sortBy, '_desc') !== false ? 'DESC' : 'ASC';

    // Validate price range inputs
    if (($minPrice !== null && !is_numeric($minPrice)) || ($maxPrice !== null && !is_numeric($maxPrice))) {
        echo json_encode(["error" => "Invalid price value."]);
        return;
    }

    // Start building the query
    $sql = "SELECT p.*, pc.category_name 
            FROM products p
            LEFT JOIN product_category pc ON p.category_id = pc.category_id
            WHERE 1=1"; // Base condition for appending filters

    // Add filters for product_name
    if (!empty($search)) {
        $sql .= " AND p.product_name LIKE :search";
    }

    // Add filters for price range
    if ($minPrice !== null) {
        $sql .= " AND p.price >= :min_price";
    }
    if ($maxPrice !== null) {
        $sql .= " AND p.price <= :max_price";
    }

    // Add filters for category_name (case-insensitive partial match)
    if (!empty($categoryName)) {
        $sql .= " AND LOWER(pc.category_name) LIKE LOWER(:category_name)";
    }

    // Add filters for material (case-insensitive partial match)
    if (!empty($material)) {
        $sql .= " AND LOWER(p.material) LIKE LOWER(:material)";
    }

    // Add filters for color (case-insensitive partial match)
    if (!empty($color)) {
        $sql .= " AND LOWER(p.color) LIKE LOWER(:color)";
    }

    // Add sorting
    $sql .= " ORDER BY $sortColumn $sortDirection";

    try {
        // Prepare the query
        $stmt = $conn->prepare($sql);

        // Bind parameters
        if (!empty($search)) {
            $searchQuery = '%' . $search . '%'; // Match any part of the product name
            $stmt->bindParam(':search', $searchQuery, PDO::PARAM_STR);
        }
        if ($minPrice !== null) {
            $stmt->bindParam(':min_price', $minPrice, PDO::PARAM_INT);
        }
        if ($maxPrice !== null) {
            $stmt->bindParam(':max_price', $maxPrice, PDO::PARAM_INT);
        }
        if (!empty($categoryName)) {
            $categoryQuery = '%' . $categoryName . '%'; // Match any part of the category name
            $stmt->bindParam(':category_name', $categoryQuery, PDO::PARAM_STR);
        }
        if (!empty($material)) {
            $materialQuery = '%' . $material . '%'; // Match any part of the material name
            $stmt->bindParam(':material', $materialQuery, PDO::PARAM_STR);
        }
        if (!empty($color)) {
            $colorQuery = '%' . $color . '%'; // Match any part of the color name
            $stmt->bindParam(':color', $colorQuery, PDO::PARAM_STR);
        }

        // Execute and fetch results
        $stmt->execute();
        $products = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Check if products were found
        if ($products) {
            echo json_encode($products);
        } else {
            echo json_encode(["message" => "No products found."]);
        }
    } catch (Exception $e) {
        echo json_encode(['error' => 'Search failed: ' . $e->getMessage()]);
    }
}



