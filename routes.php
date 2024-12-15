<?php
require_once __DIR__ . '/connect.php';
require_once __DIR__ . '/handler/products.php';
require_once __DIR__ . '/handler/search.php';
require_once __DIR__ . '/handler/SellerAdmin.php';
require_once __DIR__ . '/vendor/autoload.php';

use Firebase\JWT\JWT;
use Firebase\JWT\Key;

$path = isset($_GET['path']) ? $_GET['path'] : '';

// Routing Logic
switch ($path) {
    case 'products':
        switch ($_SERVER['REQUEST_METHOD']) {
            case 'GET':
                try {
                    $product_id = $_GET['id'] ?? null;
                    $search = $_GET['search'] ?? null;
                    $sort_by = $_GET['sort_by'] ?? 'date_added'; // Default sort by date_added
                    $sort_order = strtolower($_GET['sort_order'] ?? 'desc'); // Default sort order is descending
                    $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
                    $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 10;
                    $offset = ($page - 1) * $limit;

                    // Validate sort_order
                    if (!in_array($sort_order, ['asc', 'desc'])) {
                        $sort_order = 'desc';
                    }

                    // Validate sort_by to prevent SQL injection
                    $valid_sort_columns = ['date_added', 'price', 'product_name'];
                    if (!in_array($sort_by, $valid_sort_columns)) {
                        $sort_by = 'date_added';
                    }

                    if ($product_id) {
                        // Get single product
                        $stmt = $conn->prepare("SELECT 
                            p.product_id,
                            p.product_name,
                            p.description,
                            p.price,
                            p.category_id,
                            pc.category_name,
                            p.size,
                            p.color,
                            p.material,
                            p.date_added
                        FROM products p
                        LEFT JOIN product_category pc ON p.category_id = pc.category_id
                        WHERE p.product_id = ?");

                        $stmt->execute([$product_id]);
                        $product = $stmt->fetch(PDO::FETCH_ASSOC);

                        if ($product) {
                            echo json_encode([
                                'success' => true,
                                'product' => $product
                            ]);
                        } else {
                            http_response_code(404);
                            echo json_encode([
                                'error' => 'Product not found'
                            ]);
                        }
                    } else {
                        // List products with optional search, sorting, and pagination
                        $baseQuery = "FROM products p
                                     LEFT JOIN product_category pc ON p.category_id = pc.category_id";

                        $whereClause = "";
                        $params = [];

                        if ($search) {
                            $whereClause = " WHERE p.product_name LIKE :search OR p.description LIKE :search";
                            $params[':search'] = "%$search%";
                        }

                        // Get total count
                        $countStmt = $conn->prepare("SELECT COUNT(*) as total " . $baseQuery . $whereClause);
                        $countStmt->execute($params);
                        $totalCount = $countStmt->fetch(PDO::FETCH_ASSOC)['total'];

                        // Get sorted and paginated results
                        $query = "SELECT 
                            p.product_id,
                            p.product_name,
                            p.description,
                            p.price,
                            p.category_id,
                            pc.category_name,
                            p.size,
                            p.color,
                            p.material,
                            p.date_added
                            " . $baseQuery . $whereClause . "
                            ORDER BY $sort_by $sort_order
                            LIMIT :limit OFFSET :offset";

                        $stmt = $conn->prepare($query);

                        // Bind parameters
                        if ($search) {
                            $stmt->bindValue(':search', "%$search%", PDO::PARAM_STR);
                        }
                        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
                        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);

                        $stmt->execute();
                        $products = $stmt->fetchAll(PDO::FETCH_ASSOC);

                        $totalPages = ceil($totalCount / $limit);

                        echo json_encode([
                            'success' => true,
                            'products' => $products,
                            'pagination' => [
                                'current_page' => $page,
                                'total_pages' => $totalPages,
                                'total_items' => $totalCount,
                                'items_per_page' => $limit
                            ]
                        ]);
                    }
                } catch (Exception $e) {
                    http_response_code(500);
                    echo json_encode([
                        'error' => 'Failed to fetch products: ' . $e->getMessage()
                    ]);
                }
                break;
        }
        break;


    case 'search':
        if (isset($_GET['name']) || isset($_GET['category']) || isset($_GET['minPrice']) || isset($_GET['maxPrice']) || isset($_GET['sort']) || isset($_GET['color']) || isset($_GET['size']) || isset($_GET['material'])) {
            try {
                advancedSearchProducts($conn);
            } catch (Exception $e) {
                http_response_code(500);
                echo json_encode([
                    'error' => 'Search failed: ' . $e->getMessage()
                ]);
            }
        } else {
            http_response_code(400);
            echo json_encode(['error' => 'No search parameters provided.']);
        }
        break;


    case 'login':
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            try {
                $data = json_decode(file_get_contents('php://input'), true);

                if (!isset($data['email']) || !isset($data['password'])) {
                    echo json_encode(['error' => 'Email and password are required']);
                    exit;
                }

                $email = $data['email'];
                $password = $data['password'];

                $stmt = $conn->prepare("SELECT u.user_id, u.password, r.role_name 
                                          FROM users u 
                                          JOIN roles r ON u.role_id = r.role_id 
                                          WHERE u.email = ?");
                $stmt->execute([$email]);
                $user = $stmt->fetch(PDO::FETCH_ASSOC);

                if ($user) {
                    $secret_key = "your_strong_secret_key_123!@#";
                    putenv("JWT_SECRET=$secret_key");

                    $payload = [
                        'user_id' => $user['user_id'],
                        'role' => $user['role_name'],
                        'exp' => time() + (60 * 60)
                    ];

                    $jwt = JWT::encode($payload, $secret_key, 'HS256');

                    echo json_encode([
                        'success' => true,
                        'token' => $jwt,
                        'role' => $user['role_name']
                    ]);
                } else {
                    echo json_encode(['error' => 'Invalid credentials']);
                }
            } catch (Exception $e) {
                echo json_encode(['error' => 'Login failed: ' . $e->getMessage()]);
            }
        }
        break;

    case 'user/products':
        switch ($_SERVER['REQUEST_METHOD']) {
            case 'GET':
                try {
                    $user = getUserIdFromToken();
                    $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
                    $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 10;
                    $offset = ($page - 1) * $limit;

                    // Get total count
                    $countStmt = $conn->prepare("SELECT COUNT(*) as total FROM products WHERE user_id = :user_id");
                    $countStmt->execute([':user_id' => $user['user_id']]);
                    $totalCount = $countStmt->fetch(PDO::FETCH_ASSOC)['total'];

                    // Get paginated results
                    $stmt = $conn->prepare("SELECT 
                            product_id,
                            product_name,
                            description,
                            price,
                            category_id,
                            size,
                            color,
                            material,
                            date_added
                            FROM products 
                            WHERE user_id = :user_id
                            ORDER BY date_added DESC
                            LIMIT :limit OFFSET :offset");

                    // Bind parameters
                    $stmt->bindValue(':user_id', $user['user_id'], PDO::PARAM_STR);
                    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
                    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);

                    $stmt->execute();
                    $products = $stmt->fetchAll(PDO::FETCH_ASSOC);

                    $totalPages = ceil($totalCount / $limit);

                    if ($products) {
                        echo json_encode([
                            'success' => true,
                            'products' => $products,
                            'pagination' => [
                                'current_page' => $page,
                                'total_pages' => $totalPages,
                                'total_items' => $totalCount,
                                'items_per_page' => $limit
                            ]
                        ]);
                    } else {
                        echo json_encode([
                            'success' => true,
                            'products' => [],
                            'pagination' => [
                                'current_page' => $page,
                                'total_pages' => 0,
                                'total_items' => 0,
                                'items_per_page' => $limit
                            ],
                            'message' => 'No products found'
                        ]);
                    }
                } catch (Exception $e) {
                    http_response_code(500);
                    echo json_encode([
                        'error' => 'Failed to fetch products: ' . $e->getMessage()
                    ]);
                }
                break;

            case 'POST':
                $data = json_decode(file_get_contents('php://input'), true);
                if (createUserProduct($conn, $data)) {
                    echo json_encode(['success' => true, 'message' => 'Product created successfully']);
                } else {
                    http_response_code(500);
                    echo json_encode(['error' => 'Failed to create product']);
                }
                break;

            case 'PUT':
                try {
                    $user = getUserIdFromToken();
                    if ($user['role'] !== 'Seller' && $user['role'] !== 'Admin') {
                        http_response_code(403);
                        echo json_encode(['error' => 'Only sellers can update products']);
                        break;
                    }

                    $data = json_decode(file_get_contents('php://input'), true);
                    $product_id = $_GET['id'] ?? null;

                    if (!$product_id) {
                        http_response_code(400);
                        echo json_encode(['error' => 'Product ID is required']);
                        break;
                    }

                    // Verify product belongs to user
                    $stmt = $conn->prepare("SELECT user_id FROM products WHERE product_id = ?");
                    $stmt->execute([$product_id]);
                    $product = $stmt->fetch(PDO::FETCH_ASSOC);

                    if (!$product || $product['user_id'] !== $user['user_id']) {
                        http_response_code(403);
                        echo json_encode(['error' => 'Unauthorized to edit this product']);
                        break;
                    }

                    $stmt = $conn->prepare("UPDATE products 
                                              SET product_name = ?, 
                                                  description = ?, 
                                                  price = ?,
                                                  category_id = ?, 
                                                  size = ?, 
                                                  color = ?, 
                                                  material = ?
                                              WHERE product_id = ? AND user_id = ?");

                    $result = $stmt->execute([
                        $data['product_name'],
                        $data['description'],
                        $data['price'],
                        $data['category_id'],
                        $data['size'],
                        $data['color'],
                        $data['material'],
                        $product_id,
                        $user['user_id']
                    ]);

                    if ($result) {
                        echo json_encode([
                            'success' => true,
                            'message' => 'Product updated successfully'
                        ]);
                    } else {
                        throw new Exception('Failed to update product');
                    }
                } catch (Exception $e) {
                    http_response_code(500);
                    echo json_encode(['error' => $e->getMessage()]);
                }
                break;

            case 'DELETE':
                try {
                    $user = getUserIdFromToken();
                    if ($user['role'] !== 'Seller' && $user['role'] !== 'Admin') {
                        http_response_code(403);
                        echo json_encode(['error' => 'Only sellers can delete products']);
                        break;
                    }

                    $product_id = $_GET['id'] ?? null;

                    if (!$product_id) {
                        http_response_code(400);
                        echo json_encode(['error' => 'Product ID is required']);
                        break;
                    }

                    // Verify product belongs to user and delete it
                    $stmt = $conn->prepare("DELETE FROM products 
                                              WHERE product_id = ? AND user_id = ?");
                    $result = $stmt->execute([$product_id, $user['user_id']]);

                    if ($stmt->rowCount() > 0) {
                        echo json_encode([
                            'success' => true,
                            'message' => 'Product deleted successfully'
                        ]);
                    } else {
                        http_response_code(404);
                        echo json_encode([
                            'error' => 'Product not found or unauthorized'
                        ]);
                    }
                } catch (Exception $e) {
                    http_response_code(500);
                    echo json_encode(['error' => $e->getMessage()]);
                }
                break;

            default:
                http_response_code(405);
                echo json_encode(['error' => 'Method not allowed']);
                break;
        }
        break;

    case 'products':
        switch ($_SERVER['REQUEST_METHOD']) {
            case 'GET':
                try {
                    $product_id = $_GET['id'] ?? null;
                    $search = $_GET['search'] ?? null;
                    $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
                    $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 10;
                    $offset = ($page - 1) * $limit;

                    if ($product_id) {
                        // Get single product
                        $stmt = $conn->prepare("SELECT 
                                p.product_id,
                                p.product_name,
                                p.description,
                                p.price,
                                p.category_id,
                                pc.category_name,
                                p.size,
                                p.color,
                                p.material,
                                p.date_added
                            FROM products p
                            LEFT JOIN product_category pc ON p.category_id = pc.category_id
                            WHERE p.product_id = ?");

                        $stmt->execute([$product_id]);
                        $product = $stmt->fetch(PDO::FETCH_ASSOC);

                        if ($product) {
                            echo json_encode([
                                'success' => true,
                                'product' => $product
                            ]);
                        } else {
                            http_response_code(404);
                            echo json_encode([
                                'error' => 'Product not found'
                            ]);
                        }
                    } else {
                        $baseQuery = "FROM products p
                                         LEFT JOIN product_category pc ON p.category_id = pc.category_id";

                        $whereClause = "";
                        $params = [];

                        if ($search) {
                            $whereClause = " WHERE p.product_name LIKE :search OR p.description LIKE :search";
                            $params[':search'] = "%$search%";
                        }

                        // Get total count
                        $countStmt = $conn->prepare("SELECT COUNT(*) as total " . $baseQuery . $whereClause);
                        $countStmt->execute($params);
                        $totalCount = $countStmt->fetch(PDO::FETCH_ASSOC)['total'];

                        // Get paginated results
                        $query = "SELECT 
                                p.product_id,
                                p.product_name,
                                p.description,
                                p.price,
                                p.category_id,
                                pc.category_name,
                                p.size,
                                p.color,
                                p.material,
                                p.date_added
                                " . $baseQuery . $whereClause . "
                                ORDER BY p.date_added DESC
                                LIMIT :limit OFFSET :offset";

                        $stmt = $conn->prepare($query);

                        // Bind parameters
                        if ($search) {
                            $stmt->bindValue(':search', "%$search%", PDO::PARAM_STR);
                        }
                        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
                        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);

                        $stmt->execute();
                        $products = $stmt->fetchAll(PDO::FETCH_ASSOC);

                        $totalPages = ceil($totalCount / $limit);

                        echo json_encode([
                            'success' => true,
                            'products' => $products,
                            'pagination' => [
                                'current_page' => $page,
                                'total_pages' => $totalPages,
                                'total_items' => $totalCount,
                                'items_per_page' => $limit
                            ]
                        ]);
                    }
                } catch (Exception $e) {
                    http_response_code(500);
                    echo json_encode([
                        'error' => 'Failed to fetch products: ' . $e->getMessage()
                    ]);
                }
                break;
        }
        break;

    case 'categories':
        if ($_SERVER['REQUEST_METHOD'] === 'GET') {
            try {
                $stmt = $conn->prepare("SELECT * FROM product_category ORDER BY name");
                $stmt->execute();
                $categories = $stmt->fetchAll(PDO::FETCH_ASSOC);

                echo json_encode([
                    'success' => true,
                    'categories' => $categories
                ]);
            } catch (Exception $e) {
                http_response_code(500);
                echo json_encode([
                    'error' => 'Failed to fetch categories: ' . $e->getMessage()
                ]);
            }
        }
        break;

    case 'admin/products':
        // Verify admin token first
        try {
            $headers = getallheaders();
            if (!isset($headers['Authorization'])) {
                throw new Exception('No authorization header found');
            }

            $token = str_replace('Bearer ', '', $headers['Authorization']);
            $secret_key = "your_strong_secret_key_123!@#";
            $decoded = JWT::decode($token, new Key($secret_key, 'HS256'));

            if ($decoded->role !== 'Admin') {
                throw new Exception('Unauthorized access');
            }

            switch ($_SERVER['REQUEST_METHOD']) {
                case 'PUT':
                    try {
                        $product_id = $_GET['id'] ?? null;
                        $data = json_decode(file_get_contents('php://input'), true);

                        if (!$product_id) {
                            http_response_code(400);
                            echo json_encode(['error' => 'Product ID is required']);
                            break;
                        }

                        $stmt = $conn->prepare("UPDATE products 
                                                      SET product_name = ?, 
                                                          description = ?, 
                                                          price = ?,
                                                          category_id = ?, 
                                                          size = ?, 
                                                          color = ?, 
                                                          material = ?
                                                      WHERE product_id = ?");

                        $result = $stmt->execute([
                            $data['product_name'],
                            $data['description'],
                            $data['price'],
                            $data['category_id'],
                            $data['size'],
                            $data['color'],
                            $data['material'],
                            $product_id
                        ]);

                        if ($result) {
                            echo json_encode([
                                'success' => true,
                                'message' => 'Product updated successfully'
                            ]);
                        } else {
                            throw new Exception('Failed to update product');
                        }
                    } catch (Exception $e) {
                        http_response_code(500);
                        echo json_encode(['error' => $e->getMessage()]);
                    }
                    break;

                case 'DELETE':
                    try {
                        $product_id = $_GET['id'] ?? null;

                        if (!$product_id) {
                            http_response_code(400);
                            echo json_encode(['error' => 'Product ID is required']);
                            break;
                        }

                        $stmt = $conn->prepare("DELETE FROM products WHERE product_id = ?");
                        $result = $stmt->execute([$product_id]);

                        if ($result) {
                            echo json_encode([
                                'success' => true,
                                'message' => 'Product deleted successfully'
                            ]);
                        } else {
                            throw new Exception('Failed to delete product');
                        }
                    } catch (Exception $e) {
                        http_response_code(500);
                        echo json_encode(['error' => $e->getMessage()]);
                    }
                    break;

                default:
                    http_response_code(405);
                    echo json_encode(['error' => 'Method not allowed']);
                    break;
            }
        } catch (Exception $e) {
            http_response_code(401);
            echo json_encode(['error' => 'Unauthorized: ' . $e->getMessage()]);
        }
        break;
    default:
        http_response_code(404);
        echo json_encode(["message" => "Route not found"]);
        break;
}
