<?php
require_once __DIR__ . '/connect.php';
require_once __DIR__ . '/handler/products.php';
require_once __DIR__ . '/handler/search.php';

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

