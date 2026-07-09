<?php
define('API_CALL', true);
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Headers: Content-Type');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit;
}

// Mulai Session PHP
session_start();

require_once __DIR__ . '/db.php';
$db = getDbConnection();

$action = $_GET['action'] ?? '';

// Proteksi Akses API berdasarkan Sesi (Kecuali untuk publik)
$publicActions = ['login', 'logout', 'session'];
if (!in_array($action, $publicActions)) {
    if (empty($_SESSION['user_id'])) {
        http_response_code(401);
        echo json_encode(['success' => false, 'message' => 'Sesi berakhir, silakan login kembali.']);
        exit;
    }
}

try {
    switch ($action) {
        // Otentikasi
        case 'login':
            handleLogin($db);
            break;
        case 'logout':
            handleLogout();
            break;
        case 'session':
            handleSession($db);
            break;

        // Dashboard & Ringkasan Stok
        case 'dashboard':
            handleDashboard($db);
            break;
        case 'stock_summary':
            handleStockSummary($db);
            break;

        // Entitas Master Data
        case 'products':
            handleProducts($db);
            break;
        case 'locations':
            handleLocations($db);
            break;
        case 'suppliers':
            handleSuppliers($db);
            break;
        case 'customers':
            handleCustomers($db);
            break;
        case 'save_entity':
            handleSaveEntity($db);
            break;
        case 'delete_entity':
            handleDeleteEntity($db);
            break;

        // Riwayat Transaksi Internal
        case 'transactions':
            handleTransactions($db);
            break;
        case 'create_transaction':
            handleCreateTransaction($db);
            break;

        // Manajemen Order Customer
        case 'orders':
            handleOrders($db);
            break;
        case 'create_order':
            handleCreateOrder($db);
            break;
        case 'update_order_status':
            handleUpdateOrderStatus($db);
            break;

        default:
            http_response_code(404);
            echo json_encode(['success' => false, 'message' => 'Action tidak ditemukan']);
            break;
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Internal Server Error: ' . $e->getMessage()]);
}

// ----------------- IMPLEMENTASI HANDLER -----------------

// A. OTENTIKASI HANDLERS
function handleLogin($db) {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        echo json_encode(['success' => false, 'message' => 'Metode HTTP harus POST']);
        return;
    }

    $input = json_decode(file_get_contents('php://input'), true);
    $username = $input['username'] ?? '';
    $password = $input['password'] ?? '';

    if (empty($username) || empty($password)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Username dan password wajib diisi']);
        return;
    }

    $stmt = $db->prepare("SELECT * FROM users WHERE username = ?");
    $stmt->execute([$username]);
    $user = $stmt->fetch();

    if ($user && password_verify($password, $user['password'])) {
        // Set variables session
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['role'] = $user['role'];
        $_SESSION['customer_id'] = $user['customer_id'];

        $customerName = '';
        if ($user['role'] === 'CUSTOMER' && !empty($user['customer_id'])) {
            $stmtCust = $db->prepare("SELECT name FROM customers WHERE id = ?");
            $stmtCust->execute([$user['customer_id']]);
            $customerName = $stmtCust->fetchColumn();
        }

        echo json_encode([
            'success' => true,
            'message' => 'Login berhasil',
            'user' => [
                'username' => $user['username'],
                'role' => $user['role'],
                'customer_name' => $customerName,
                'customer_id' => $user['customer_id']
            ]
        ]);
    } else {
        http_response_code(401);
        echo json_encode(['success' => false, 'message' => 'Username atau password salah']);
    }
}

function handleLogout() {
    session_destroy();
    echo json_encode(['success' => true, 'message' => 'Logout berhasil']);
}

function handleSession($db) {
    if (!empty($_SESSION['user_id'])) {
        $customerName = '';
        if ($_SESSION['role'] === 'CUSTOMER' && !empty($_SESSION['customer_id'])) {
            $stmtCust = $db->prepare("SELECT name FROM customers WHERE id = ?");
            $stmtCust->execute([$_SESSION['customer_id']]);
            $customerName = $stmtCust->fetchColumn();
        }

        echo json_encode([
            'success' => true,
            'user' => [
                'username' => $_SESSION['username'],
                'role' => $_SESSION['role'],
                'customer_name' => $customerName,
                'customer_id' => $_SESSION['customer_id']
            ]
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Sesi tidak aktif']);
    }
}

// B. MASTER DATA HANDLERS (DENGAN TAUTAN USER LOGIN CUSTOMER)
function handleProducts($db) {
    $products = $db->query("SELECT * FROM products ORDER BY code ASC")->fetchAll();
    echo json_encode(['success' => true, 'data' => $products]);
}

function handleLocations($db) {
    $locations = $db->query("SELECT * FROM locations ORDER BY name ASC")->fetchAll();
    echo json_encode(['success' => true, 'data' => $locations]);
}

function handleSuppliers($db) {
    $suppliers = $db->query("SELECT * FROM suppliers ORDER BY name ASC")->fetchAll();
    echo json_encode(['success' => true, 'data' => $suppliers]);
}

function handleCustomers($db) {
    // Join dengan tabel users untuk mendapatkan username login (jika ada)
    $sql = "
        SELECT c.*, u.username 
        FROM customers c 
        LEFT JOIN users u ON u.customer_id = c.id AND u.role = 'CUSTOMER' 
        ORDER BY c.name ASC
    ";
    $customers = $db->query($sql)->fetchAll();
    echo json_encode(['success' => true, 'data' => $customers]);
}

function handleSaveEntity($db) {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        echo json_encode(['success' => false, 'message' => 'Metode HTTP harus POST']);
        return;
    }

    $input = json_decode(file_get_contents('php://input'), true);
    $entity = $input['entity'] ?? '';
    $data = $input['data'] ?? [];
    $id = isset($data['id']) ? (int)$data['id'] : null;

    if (empty($entity) || empty($data)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Data entitas tidak lengkap']);
        return;
    }

    try {
        if ($entity === 'product') {
            $code = $data['code'];
            $name = $data['name'];
            $category = $data['category'] ?? '';
            $unit = $data['unit'];
            $desc = $data['description'] ?? '';

            if (empty($code) || empty($name) || empty($unit)) {
                throw new Exception('Kode, Nama, dan Satuan wajib diisi');
            }

            if ($id) {
                $stmt = $db->prepare("SELECT COUNT(*) FROM products WHERE code = ? AND id != ?");
                $stmt->execute([$code, $id]);
                if ($stmt->fetchColumn() > 0) {
                    throw new Exception('Kode Produk sudah digunakan oleh produk lain');
                }

                $stmt = $db->prepare("UPDATE products SET code = ?, name = ?, category = ?, unit = ?, description = ? WHERE id = ?");
                $stmt->execute([$code, $name, $category, $unit, $desc, $id]);
            } else {
                $stmt = $db->prepare("SELECT COUNT(*) FROM products WHERE code = ?");
                $stmt->execute([$code]);
                if ($stmt->fetchColumn() > 0) {
                    throw new Exception('Kode Produk sudah terdaftar');
                }

                $stmt = $db->prepare("INSERT INTO products (code, name, category, unit, description) VALUES (?, ?, ?, ?, ?)");
                $stmt->execute([$code, $name, $category, $unit, $desc]);
                $id = $db->lastInsertId();
            }
        }
        elseif ($entity === 'location') {
            $name = $data['name'];
            if (empty($name)) {
                throw new Exception('Nama lokasi wajib diisi');
            }

            if ($id) {
                $stmt = $db->prepare("SELECT COUNT(*) FROM locations WHERE name = ? AND id != ?");
                $stmt->execute([$name, $id]);
                if ($stmt->fetchColumn() > 0) {
                    throw new Exception('Nama lokasi sudah terdaftar');
                }

                $stmt = $db->prepare("UPDATE locations SET name = ? WHERE id = ?");
                $stmt->execute([$name, $id]);
            } else {
                $stmt = $db->prepare("SELECT COUNT(*) FROM locations WHERE name = ?");
                $stmt->execute([$name]);
                if ($stmt->fetchColumn() > 0) {
                    throw new Exception('Nama lokasi sudah terdaftar');
                }

                $stmt = $db->prepare("INSERT INTO locations (name) VALUES (?)");
                $stmt->execute([$name]);
                $id = $db->lastInsertId();
            }
        }
        elseif ($entity === 'supplier') {
            $name = $data['name'];
            $phone = $data['phone'] ?? '';
            $address = $data['address'] ?? '';

            if (empty($name)) {
                throw new Exception('Nama supplier wajib diisi');
            }

            if ($id) {
                $stmt = $db->prepare("SELECT COUNT(*) FROM suppliers WHERE name = ? AND id != ?");
                $stmt->execute([$name, $id]);
                if ($stmt->fetchColumn() > 0) {
                    throw new Exception('Nama supplier sudah terdaftar');
                }

                $stmt = $db->prepare("UPDATE suppliers SET name = ?, phone = ?, address = ? WHERE id = ?");
                $stmt->execute([$name, $phone, $address, $id]);
            } else {
                $stmt = $db->prepare("SELECT COUNT(*) FROM suppliers WHERE name = ?");
                $stmt->execute([$name]);
                if ($stmt->fetchColumn() > 0) {
                    throw new Exception('Nama supplier sudah terdaftar');
                }

                $stmt = $db->prepare("INSERT INTO suppliers (name, phone, address) VALUES (?, ?, ?)");
                $stmt->execute([$name, $phone, $address]);
                $id = $db->lastInsertId();
            }
        }
        elseif ($entity === 'customer') {
            $name = $data['name'];
            $phone = $data['phone'] ?? '';
            $address = $data['address'] ?? '';
            
            // Kolom Login Akun (Customer)
            $username = trim($data['username'] ?? '');
            $password = trim($data['password'] ?? '');

            if (empty($name)) {
                throw new Exception('Nama customer wajib diisi');
            }

            $db->beginTransaction();

            try {
                if ($id) {
                    $stmt = $db->prepare("SELECT COUNT(*) FROM customers WHERE name = ? AND id != ?");
                    $stmt->execute([$name, $id]);
                    if ($stmt->fetchColumn() > 0) {
                        throw new Exception('Nama customer sudah terdaftar');
                    }

                    $stmt = $db->prepare("UPDATE customers SET name = ?, phone = ?, address = ? WHERE id = ?");
                    $stmt->execute([$name, $phone, $address, $id]);
                } else {
                    $stmt = $db->prepare("SELECT COUNT(*) FROM customers WHERE name = ?");
                    $stmt->execute([$name]);
                    if ($stmt->fetchColumn() > 0) {
                        throw new Exception('Nama customer sudah terdaftar');
                    }

                    $stmt = $db->prepare("INSERT INTO customers (name, phone, address) VALUES (?, ?, ?)");
                    $stmt->execute([$name, $phone, $address]);
                    $id = $db->lastInsertId();
                }

                // PEMBUATAN / UPDATE LOGIN AKUN CUSTOMER
                if (!empty($username)) {
                    // Cek ketersediaan username di users (selain milik customer ini sendiri)
                    $stmtCheckUsername = $db->prepare("
                        SELECT id FROM users WHERE username = ? AND (customer_id != ? OR customer_id IS NULL)
                    ");
                    $stmtCheckUsername->execute([$username, $id]);
                    if ($stmtCheckUsername->fetch()) {
                        throw new Exception('Username sudah digunakan oleh akun lain');
                    }

                    // Ambil record login customer
                    $stmtUserExist = $db->prepare("SELECT id FROM users WHERE customer_id = ?");
                    $stmtUserExist->execute([$id]);
                    $userRow = $stmtUserExist->fetch();

                    if ($userRow) {
                        // Update akun lama
                        $updateSql = "UPDATE users SET username = ? ";
                        $params = [$username];
                        if (!empty($password)) {
                            $updateSql .= ", password = ? ";
                            $params[] = password_hash($password, PASSWORD_DEFAULT);
                        }
                        $updateSql .= "WHERE customer_id = ?";
                        $params[] = $id;
                        
                        $db->prepare($updateSql)->execute($params);
                    } else {
                        // Tambah akun baru
                        if (empty($password)) {
                            throw new Exception('Password wajib diisi untuk akun login baru');
                        }
                        $stmtInsertUser = $db->prepare("
                            INSERT INTO users (username, password, role, customer_id) VALUES (?, ?, 'CUSTOMER', ?)
                        ");
                        $stmtInsertUser->execute([$username, password_hash($password, PASSWORD_DEFAULT), $id]);
                    }
                } else {
                    // Jika username dikosongkan, hapus login akun terkait (jika ada)
                    $db->prepare("DELETE FROM users WHERE customer_id = ? AND role = 'CUSTOMER'")->execute([$id]);
                }

                $db->commit();
            } catch (Exception $exCust) {
                $db->rollBack();
                throw $exCust;
            }
        }

        echo json_encode(['success' => true, 'message' => 'Data berhasil disimpan', 'id' => $id]);
    } catch (Exception $e) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}

function handleDeleteEntity($db) {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        echo json_encode(['success' => false, 'message' => 'Metode HTTP harus POST']);
        return;
    }

    $input = json_decode(file_get_contents('php://input'), true);
    $entity = $input['entity'] ?? '';
    $id = isset($input['id']) ? (int)$input['id'] : null;

    if (empty($entity) || empty($id)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Data untuk menghapus tidak lengkap']);
        return;
    }

    try {
        $table = '';
        switch ($entity) {
            case 'product': $table = 'products'; break;
            case 'location': $table = 'locations'; break;
            case 'supplier': $table = 'suppliers'; break;
            case 'customer': $table = 'customers'; break;
            default:
                throw new Exception('Entitas tidak dikenal');
        }

        $stmt = $db->prepare("DELETE FROM `$table` WHERE id = ?");
        $stmt->execute([$id]);

        echo json_encode(['success' => true, 'message' => 'Data berhasil dihapus']);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Gagal menghapus: ' . $e->getMessage()]);
    }
}

// C. DASHBOARD & STOK SUMMARY HANDLERS
function handleDashboard($db) {
    $driver = $db->getAttribute(PDO::ATTR_DRIVER_NAME);

    // Total Produk
    $totalProducts = (int)$db->query("SELECT COUNT(*) FROM products")->fetchColumn();
    
    // Total Stok
    $totalStock = (float)$db->query("SELECT COALESCE(SUM(qty), 0) FROM stock")->fetchColumn();
    
    // Total Lokasi
    $totalLocations = (int)$db->query("SELECT COUNT(*) FROM locations")->fetchColumn();
    
    // Peringatan Stok Rendah (< 15) di tiap lokasi
    $lowStockQuery = "
        SELECT p.code, p.name, l.name as location_name, COALESCE(s.qty, 0) as qty, p.unit 
        FROM products p 
        CROSS JOIN locations l 
        LEFT JOIN stock s ON s.product_id = p.id AND s.location_id = l.id 
        WHERE COALESCE(s.qty, 0) < 15
        ORDER BY qty ASC, p.name ASC
    ";
    $lowStockItems = $db->query($lowStockQuery)->fetchAll();
    $lowStockCount = count($lowStockItems);

    // Recent Transactions (5 Terakhir)
    $recentQuery = "
        SELECT t.id, t.reference_no, t.transaction_type, t.transaction_date, 
               sl.name as source_location, tl.name as target_location, 
               s.name as supplier, c.name as customer,
               (SELECT SUM(qty) FROM transaction_details WHERE transaction_id = t.id) as total_qty
        FROM transactions t 
        LEFT JOIN locations sl ON t.source_location_id = sl.id 
        LEFT JOIN locations tl ON t.target_location_id = tl.id 
        LEFT JOIN suppliers s ON t.supplier_id = s.id 
        LEFT JOIN customers c ON t.customer_id = c.id 
        ORDER BY t.transaction_date DESC, t.id DESC
        LIMIT 5
    ";
    $recentTransactions = $db->query($recentQuery)->fetchAll();

    // Data Chart: Stok per Lokasi
    $stockPerLocation = $db->query("
        SELECT l.name as location_name, COALESCE(SUM(s.qty), 0) as total_qty 
        FROM locations l 
        LEFT JOIN stock s ON s.location_id = l.id 
        GROUP BY l.id, l.name
    ")->fetchAll();

    // Data Chart: Tren Transaksi 6 Bulan Terakhir
    if ($driver === 'sqlite') {
        $trendQuery = "
            SELECT strftime('%Y-%m', t.transaction_date) as month, 
                   t.transaction_type, 
                   SUM(d.qty * d.unit_price) as total_value
            FROM transactions t 
            JOIN transaction_details d ON d.transaction_id = t.id 
            WHERE t.transaction_date >= date('now', '-6 month')
              AND t.transaction_type IN ('PURCHASE', 'SALE')
            GROUP BY strftime('%Y-%m', t.transaction_date), t.transaction_type
            ORDER BY month ASC
        ";
    } else {
        $trendQuery = "
            SELECT DATE_FORMAT(t.transaction_date, '%Y-%m') as month, 
                   t.transaction_type, 
                   SUM(d.qty * d.unit_price) as total_value
            FROM transactions t 
            JOIN transaction_details d ON d.transaction_id = t.id 
            WHERE t.transaction_date >= DATE_SUB(CURRENT_DATE, INTERVAL 6 MONTH)
              AND t.transaction_type IN ('PURCHASE', 'SALE')
            GROUP BY DATE_FORMAT(t.transaction_date, '%Y-%m'), t.transaction_type
            ORDER BY month ASC
        ";
    }
    $trendData = $db->query($trendQuery)->fetchAll();
    
    // Format tren bulanan
    $months = [];
    $purchaseTrend = [];
    $saleTrend = [];
    
    for ($i = 5; $i >= 0; $i--) {
        $m = date('Y-m', strtotime("-$i months"));
        $months[] = date('M Y', strtotime("-$i months"));
        $purchaseTrend[$m] = 0;
        $saleTrend[$m] = 0;
    }
    
    foreach ($trendData as $row) {
        $m = $row['month'];
        if (isset($purchaseTrend[$m]) || isset($saleTrend[$m])) {
            if ($row['transaction_type'] === 'PURCHASE') {
                $purchaseTrend[$m] = (float)$row['total_value'];
            } elseif ($row['transaction_type'] === 'SALE') {
                $saleTrend[$m] = (float)$row['total_value'];
            }
        }
    }

    echo json_encode([
        'success' => true,
        'summary' => [
            'total_products' => $totalProducts,
            'total_stock' => $totalStock,
            'total_locations' => $totalLocations,
            'low_stock_count' => $lowStockCount
        ],
        'low_stock_items' => $lowStockItems,
        'recent_transactions' => $recentTransactions,
        'charts' => [
            'stock_per_location' => $stockPerLocation,
            'trend_months' => $months,
            'purchase_trend' => array_values($purchaseTrend),
            'sale_trend' => array_values($saleTrend)
        ]
    ]);
}

function handleStockSummary($db) {
    $locations = $db->query("SELECT id, name FROM locations ORDER BY name ASC")->fetchAll();
    $products = $db->query("SELECT id, code, name, category, unit FROM products ORDER BY name ASC")->fetchAll();
    $stockData = $db->query("SELECT product_id, location_id, qty FROM stock")->fetchAll();
    
    $stockMap = [];
    foreach ($stockData as $s) {
        $stockMap[$s['product_id']][$s['location_id']] = (float)$s['qty'];
    }
    
    $result = [];
    foreach ($products as $p) {
        $pStock = [];
        $totalStock = 0;
        foreach ($locations as $l) {
            $qty = $stockMap[$p['id']][$l['id']] ?? 0.0;
            $pStock[$l['id']] = $qty;
            $totalStock += $qty;
        }
        $p['stock'] = $pStock;
        $p['total_stock'] = $totalStock;
        $result[] = $p;
    }
    
    echo json_encode([
        'success' => true,
        'locations' => $locations,
        'products' => $result
    ]);
}

// D. TRANSAKSI INTERNAL HANDLERS
function handleTransactions($db) {
    $type = $_GET['type'] ?? '';
    $locationId = $_GET['location_id'] ?? '';
    $supplierId = $_GET['supplier_id'] ?? '';
    $customerId = $_GET['customer_id'] ?? '';
    $startDate = $_GET['start_date'] ?? '';
    $endDate = $_GET['end_date'] ?? '';
    $search = $_GET['search'] ?? '';

    $sql = "
        SELECT DISTINCT t.id, t.reference_no, t.transaction_type, t.transaction_date, 
               sl.name as source_location, tl.name as target_location, 
               s.name as supplier, c.name as customer, t.notes
        FROM transactions t
        LEFT JOIN locations sl ON t.source_location_id = sl.id
        LEFT JOIN locations tl ON t.target_location_id = tl.id
        LEFT JOIN suppliers s ON t.supplier_id = s.id
        LEFT JOIN customers c ON t.customer_id = c.id
        LEFT JOIN transaction_details td ON td.transaction_id = t.id
        LEFT JOIN products p ON td.product_id = p.id
        WHERE 1=1
    ";
    
    $params = [];

    if ($type !== '') {
        $sql .= " AND t.transaction_type = :type";
        $params['type'] = $type;
    }
    if ($locationId !== '') {
        $sql .= " AND (t.source_location_id = :loc_id OR t.target_location_id = :loc_id2)";
        $params['loc_id'] = $locationId;
        $params['loc_id2'] = $locationId;
    }
    if ($supplierId !== '') {
        $sql .= " AND t.supplier_id = :supplier_id";
        $params['supplier_id'] = $supplierId;
    }
    if ($customerId !== '') {
        $sql .= " AND t.customer_id = :customer_id";
        $params['customer_id'] = $customerId;
    }
    if ($startDate !== '') {
        $sql .= " AND t.transaction_date >= :start_date";
        $params['start_date'] = $startDate . " 00:00:00";
    }
    if ($endDate !== '') {
        $sql .= " AND t.transaction_date <= :end_date";
        $params['end_date'] = $endDate . " 23:59:59";
    }
    if ($search !== '') {
        $sql .= " AND (t.reference_no LIKE :search OR p.name LIKE :search2 OR p.code LIKE :search3 OR t.notes LIKE :search4)";
        $params['search'] = '%' . $search . '%';
        $params['search2'] = '%' . $search . '%';
        $params['search3'] = '%' . $search . '%';
        $params['search4'] = '%' . $search . '%';
    }

    $sql .= " ORDER BY t.transaction_date DESC, t.id DESC";
    
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $transactions = $stmt->fetchAll();

    $detailStmt = $db->prepare("
        SELECT td.id, td.product_id, p.code as product_code, p.name as product_name, p.unit as product_unit, 
               td.qty, td.unit_price, td.notes
        FROM transaction_details td
        JOIN products p ON td.product_id = p.id
        WHERE td.transaction_id = ?
    ");

    foreach ($transactions as &$t) {
        $detailStmt->execute([$t['id']]);
        $t['items'] = $detailStmt->fetchAll();
    }

    echo json_encode(['success' => true, 'data' => $transactions]);
}

function handleCreateTransaction($db) {
    $driver = $db->getAttribute(PDO::ATTR_DRIVER_NAME);

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        echo json_encode(['success' => false, 'message' => 'Metode HTTP harus POST']);
        return;
    }

    $input = json_decode(file_get_contents('php://input'), true);
    if (!$input) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Format JSON tidak valid']);
        return;
    }

    $type = $input['transaction_type'] ?? '';
    $date = $input['transaction_date'] ?? date('Y-m-d H:i:s');
    $sourceLocId = !empty($input['source_location_id']) ? (int)$input['source_location_id'] : null;
    $targetLocId = !empty($input['target_location_id']) ? (int)$input['target_location_id'] : null;
    $supplierId = !empty($input['supplier_id']) ? (int)$input['supplier_id'] : null;
    $customerId = !empty($input['customer_id']) ? (int)$input['customer_id'] : null;
    $notes = $input['notes'] ?? '';
    $items = $input['items'] ?? [];

    if (empty($type) || empty($items)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Parameter Tipe Transaksi dan Items wajib diisi']);
        return;
    }

    switch ($type) {
        case 'OPNAME':
            if (empty($targetLocId)) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'Stok Opname memerlukan Lokasi']);
                return;
            }
            break;
        case 'PURCHASE':
            if (empty($targetLocId) || empty($supplierId)) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'Pembelian Supplier memerlukan Lokasi Tujuan dan Supplier']);
                return;
            }
            break;
        case 'RETURN_PURCHASE':
            if (empty($sourceLocId) || empty($supplierId)) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'Retur Pembelian memerlukan Lokasi Asal dan Supplier']);
                return;
            }
            break;
        case 'SALE':
            if (empty($sourceLocId) || empty($customerId)) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'Penjualan memerlukan Lokasi Asal dan Customer']);
                return;
            }
            break;
        case 'RETURN_SALE':
            if (empty($targetLocId) || empty($customerId)) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'Retur Penjualan memerlukan Lokasi Tujuan dan Customer']);
                return;
            }
            break;
        case 'TRANSFER':
            if (empty($sourceLocId) || empty($targetLocId)) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'Transfer memerlukan Lokasi Asal dan Lokasi Tujuan']);
                return;
            }
            if ($sourceLocId === $targetLocId) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'Lokasi asal dan tujuan tidak boleh sama']);
                return;
            }
            break;
        default:
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Tipe Transaksi tidak dikenali']);
            return;
    }

    if (in_all_types($type, ['SALE', 'TRANSFER', 'RETURN_PURCHASE'])) {
        $checkStockStmt = $db->prepare("SELECT qty FROM stock WHERE product_id = ? AND location_id = ?");
        foreach ($items as $item) {
            $pId = (int)$item['product_id'];
            $reqQty = (float)$item['qty'];
            
            $pNameStmt = $db->prepare("SELECT name FROM products WHERE id = ?");
            $pNameStmt->execute([$pId]);
            $pName = $pNameStmt->fetchColumn();

            if ($reqQty <= 0) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => "Jumlah item '$pName' harus lebih besar dari 0"]);
                return;
            }

            $checkStockStmt->execute([$pId, $sourceLocId]);
            $currentQty = (float)$checkStockStmt->fetchColumn();

            if ($currentQty < $reqQty) {
                http_response_code(400);
                echo json_encode([
                    'success' => false, 
                    'message' => "Stok tidak mencukupi untuk '$pName'. Stok saat ini: $currentQty, Diperlukan: $reqQty"
                ]);
                return;
            }
        }
    }

    $refNo = generateReferenceNumber($db, $type, $date);
    $db->beginTransaction();

    try {
        $insertHeader = $db->prepare("
            INSERT INTO transactions (reference_no, transaction_type, transaction_date, source_location_id, target_location_id, supplier_id, customer_id, notes)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $insertHeader->execute([$refNo, $type, $date, $sourceLocId, $targetLocId, $supplierId, $customerId, $notes]);
        $transactionId = $db->lastInsertId();

        $insertDetail = $db->prepare("
            INSERT INTO transaction_details (transaction_id, product_id, qty, unit_price, notes)
            VALUES (?, ?, ?, ?, ?)
        ");

        foreach ($items as $item) {
            $pId = (int)$item['product_id'];
            $qty = (float)$item['qty'];
            $price = (float)($item['unit_price'] ?? 0);
            $itemNotes = $item['notes'] ?? '';

            $insertDetail->execute([$transactionId, $pId, $qty, $price, $itemNotes]);

            if ($type === 'OPNAME') {
                if ($driver === 'sqlite') {
                    $db->prepare("
                        INSERT INTO stock (product_id, location_id, qty) 
                        VALUES (?, ?, ?) 
                        ON CONFLICT(product_id, location_id) DO UPDATE SET qty = ?
                    ")->execute([$pId, $targetLocId, $qty, $qty]);
                } else {
                    $db->prepare("
                        INSERT INTO stock (product_id, location_id, qty) 
                        VALUES (?, ?, ?) 
                        ON DUPLICATE KEY UPDATE qty = ?
                    ")->execute([$pId, $targetLocId, $qty, $qty]);
                }
            }
            elseif ($type === 'PURCHASE' || $type === 'RETURN_SALE') {
                if ($driver === 'sqlite') {
                    $db->prepare("
                        INSERT INTO stock (product_id, location_id, qty) 
                        VALUES (?, ?, ?) 
                        ON CONFLICT(product_id, location_id) DO UPDATE SET qty = qty + ?
                    ")->execute([$pId, $targetLocId, $qty, $qty]);
                } else {
                    $db->prepare("
                        INSERT INTO stock (product_id, location_id, qty) 
                        VALUES (?, ?, ?) 
                        ON DUPLICATE KEY UPDATE qty = qty + ?
                    ")->execute([$pId, $targetLocId, $qty, $qty]);
                }
            }
            elseif ($type === 'SALE' || $type === 'RETURN_PURCHASE') {
                if ($driver === 'sqlite') {
                    $db->prepare("
                        INSERT INTO stock (product_id, location_id, qty) 
                        VALUES (?, ?, -?) 
                        ON CONFLICT(product_id, location_id) DO UPDATE SET qty = qty - ?
                    ")->execute([$pId, $sourceLocId, $qty, $qty]);
                } else {
                    $db->prepare("
                        INSERT INTO stock (product_id, location_id, qty) 
                        VALUES (?, ?, -?) 
                        ON DUPLICATE KEY UPDATE qty = qty - ?
                    ")->execute([$pId, $sourceLocId, $qty, $qty]);
                }
            }
            elseif ($type === 'TRANSFER') {
                if ($driver === 'sqlite') {
                    $db->prepare("
                        INSERT INTO stock (product_id, location_id, qty) 
                        VALUES (?, ?, -?) 
                        ON CONFLICT(product_id, location_id) DO UPDATE SET qty = qty - ?
                    ")->execute([$pId, $sourceLocId, $qty, $qty]);

                    $db->prepare("
                        INSERT INTO stock (product_id, location_id, qty) 
                        VALUES (?, ?, ?) 
                        ON CONFLICT(product_id, location_id) DO UPDATE SET qty = qty + ?
                    ")->execute([$pId, $targetLocId, $qty, $qty]);
                } else {
                    $db->prepare("
                        INSERT INTO stock (product_id, location_id, qty) 
                        VALUES (?, ?, -?) 
                        ON DUPLICATE KEY UPDATE qty = qty - ?
                    ")->execute([$pId, $sourceLocId, $qty, $qty]);

                    $db->prepare("
                        INSERT INTO stock (product_id, location_id, qty) 
                        VALUES (?, ?, ?) 
                        ON DUPLICATE KEY UPDATE qty = qty + ?
                    ")->execute([$pId, $targetLocId, $qty, $qty]);
                }
            }
        }

        $db->commit();
        echo json_encode(['success' => true, 'message' => 'Transaksi berhasil dicatat', 'reference_no' => $refNo]);
    } catch (Exception $ex) {
        $db->rollBack();
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Gagal memproses transaksi: ' . $ex->getMessage()]);
    }
}

// E. MANAJEMEN ORDER CUSTOMER HANDLERS
function handleOrders($db) {
    $role = $_SESSION['role'];
    $customerId = $_SESSION['customer_id'];

    if ($role === 'CUSTOMER') {
        $sql = "
            SELECT o.*, c.name as customer_name 
            FROM orders o 
            JOIN customers c ON o.customer_id = c.id 
            WHERE o.customer_id = ? 
            ORDER BY o.order_date DESC, o.id DESC
        ";
        $stmt = $db->prepare($sql);
        $stmt->execute([$customerId]);
    } else {
        $sql = "
            SELECT o.*, c.name as customer_name 
            FROM orders o 
            JOIN customers c ON o.customer_id = c.id 
            ORDER BY o.order_date DESC, o.id DESC
        ";
        $stmt = $db->query($sql);
    }
    
    $orders = $stmt->fetchAll();

    // Ambil detail item pesanan
    $detStmt = $db->prepare("
        SELECT od.id, od.product_id, p.code as product_code, p.name as product_name, p.unit as product_unit, od.qty, od.notes
        FROM order_details od
        JOIN products p ON od.product_id = p.id
        WHERE od.order_id = ?
    ");

    foreach ($orders as &$o) {
        $detStmt->execute([$o['id']]);
        $o['items'] = $detStmt->fetchAll();
    }

    echo json_encode(['success' => true, 'data' => $orders]);
}

function handleCreateOrder($db) {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        echo json_encode(['success' => false, 'message' => 'Metode HTTP harus POST']);
        return;
    }

    $input = json_decode(file_get_contents('php://input'), true);
    $items = $input['items'] ?? [];
    $notes = $input['notes'] ?? '';
    
    $role = $_SESSION['role'];
    $customerId = ($role === 'CUSTOMER') ? $_SESSION['customer_id'] : ($input['customer_id'] ?? null);

    if (empty($customerId)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Data Customer tidak ditemukan']);
        return;
    }

    if (empty($items)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Daftar item order tidak boleh kosong']);
        return;
    }

    // Generate Order Number
    $dateStr = date('Ymd');
    $driver = $db->getAttribute(PDO::ATTR_DRIVER_NAME);
    if ($driver === 'sqlite') {
        $stmt = $db->query("SELECT COUNT(*) FROM orders WHERE date(order_date) = date('now')");
    } else {
        $stmt = $db->query("SELECT COUNT(*) FROM orders WHERE DATE(order_date) = CURRENT_DATE");
    }
    $count = (int)$stmt->fetchColumn() + 1;
    $orderNo = "ORD-" . $dateStr . "-" . str_pad($count, 4, '0', STR_PAD_LEFT);

    $db->beginTransaction();
    try {
        $stmtOrd = $db->prepare("
            INSERT INTO orders (order_no, customer_id, order_date, status, notes) VALUES (?, ?, ?, 'DIPESAN', ?)
        ");
        $stmtOrd->execute([$orderNo, $customerId, date('Y-m-d H:i:s'), $notes]);
        $orderId = $db->lastInsertId();

        $stmtDet = $db->prepare("INSERT INTO order_details (order_id, product_id, qty, notes) VALUES (?, ?, ?, ?)");
        foreach ($items as $item) {
            $pId = (int)$item['product_id'];
            $qty = (float)$item['qty'];
            $itemNotes = $item['notes'] ?? '';
            
            if ($qty <= 0) {
                throw new Exception("Kuantitas produk tidak valid");
            }
            $stmtDet->execute([$orderId, $pId, $qty, $itemNotes]);
        }

        $db->commit();
        echo json_encode(['success' => true, 'message' => 'Pesanan berhasil dibuat', 'order_no' => $orderNo]);
    } catch (Exception $e) {
        $db->rollBack();
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Gagal membuat pesanan: ' . $e->getMessage()]);
    }
}

function handleUpdateOrderStatus($db) {
    if ($_SESSION['role'] !== 'ADMIN') {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Akses ditolak: Hanya Admin yang dapat memproses']);
        return;
    }

    $input = json_decode(file_get_contents('php://input'), true);
    $orderId = isset($input['order_id']) ? (int)$input['order_id'] : null;
    $status = $input['status'] ?? '';
    $sourceLocId = isset($input['source_location_id']) ? (int)$input['source_location_id'] : null;

    if (empty($orderId) || empty($status)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Data status tidak lengkap']);
        return;
    }

    $stmtOrd = $db->prepare("SELECT * FROM orders WHERE id = ?");
    $stmtOrd->execute([$orderId]);
    $order = $stmtOrd->fetch();

    if (!$order) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Pesanan tidak ditemukan']);
        return;
    }

    if ($order['status'] !== 'DIPESAN') {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Hanya pesanan berstatus DIPESAN yang dapat diproses']);
        return;
    }

    $db->beginTransaction();
    try {
        if ($status === 'TERSEDIA') {
            if (empty($sourceLocId)) {
                throw new Exception("Silakan pilih lokasi gudang untuk alokasi stok");
            }

            $stmtItems = $db->prepare("SELECT * FROM order_details WHERE order_id = ?");
            $stmtItems->execute([$orderId]);
            $items = $stmtItems->fetchAll();

            // 1. Validasi kecukupan stok lokasi asal
            $checkStockStmt = $db->prepare("SELECT qty FROM stock WHERE product_id = ? AND location_id = ?");
            foreach ($items as $item) {
                $checkStockStmt->execute([$item['product_id'], $sourceLocId]);
                $currentQty = (float)$checkStockStmt->fetchColumn();
                
                if ($currentQty < (float)$item['qty']) {
                    $pNameStmt = $db->prepare("SELECT name FROM products WHERE id = ?");
                    $pNameStmt->execute([$item['product_id']]);
                    $pName = $pNameStmt->fetchColumn();
                    throw new Exception("Stok tidak mencukupi untuk '$pName'. Stok tersedia: $currentQty, Diperlukan: " . $item['qty']);
                }
            }

            // 2. Potong stok gudang & buat transaksi SALE
            $txRefNo = generateReferenceNumber($db, 'SALE', date('Y-m-d H:i:s'));
            $stmtTx = $db->prepare("
                INSERT INTO transactions (reference_no, transaction_type, transaction_date, source_location_id, customer_id, notes) 
                VALUES (?, 'SALE', ?, ?, ?, ?)
            ");
            $stmtTx->execute([$txRefNo, date('Y-m-d H:i:s'), $sourceLocId, $order['customer_id'], "Penyerahan pesanan " . $order['order_no']]);
            $txId = $db->lastInsertId();

            $stmtTxDet = $db->prepare("
                INSERT INTO transaction_details (transaction_id, product_id, qty, unit_price, notes) 
                VALUES (?, ?, ?, 0.00, ?)
            ");

            $driver = $db->getAttribute(PDO::ATTR_DRIVER_NAME);

            foreach ($items as $item) {
                $pId = $item['product_id'];
                $qty = $item['qty'];

                $stmtTxDet->execute([$txId, $pId, $qty, "Dari pesanan " . $order['order_no']]);

                if ($driver === 'sqlite') {
                    $db->prepare("
                        INSERT INTO stock (product_id, location_id, qty) 
                        VALUES (?, ?, -?) 
                        ON CONFLICT(product_id, location_id) DO UPDATE SET qty = qty - ?
                    ")->execute([$pId, $sourceLocId, $qty, $qty]);
                } else {
                    $db->prepare("
                        INSERT INTO stock (product_id, location_id, qty) 
                        VALUES (?, ?, -?) 
                        ON DUPLICATE KEY UPDATE qty = qty - ?
                    ")->execute([$pId, $sourceLocId, $qty, $qty]);
                }
            }
        }

        // Update status order
        $stmtUpdate = $db->prepare("UPDATE orders SET status = ? WHERE id = ?");
        $stmtUpdate->execute([$status, $orderId]);

        $db->commit();
        echo json_encode(['success' => true, 'message' => 'Status pesanan berhasil diperbarui']);
    } catch (Exception $e) {
        $db->rollBack();
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}

// F. FUNGSI HELPER
function in_all_types($needle, $haystack) {
    return in_array($needle, $haystack);
}

function generateReferenceNumber($db, $type, $date) {
    $prefix = '';
    switch ($type) {
        case 'OPNAME': $prefix = 'OPN'; break;
        case 'PURCHASE': $prefix = 'PUR'; break;
        case 'RETURN_PURCHASE': $prefix = 'RPU'; break;
        case 'SALE': $prefix = 'SAL'; break;
        case 'RETURN_SALE': $prefix = 'RSA'; break;
        case 'TRANSFER': $prefix = 'TRF'; break;
    }

    $dateStr = date('Ymd', strtotime($date));
    $driver = $db->getAttribute(PDO::ATTR_DRIVER_NAME);
    
    if ($driver === 'sqlite') {
        $stmt = $db->prepare("
            SELECT COUNT(*) 
            FROM transactions 
            WHERE transaction_type = ? AND date(transaction_date) = date(?)
        ");
    } else {
        $stmt = $db->prepare("
            SELECT COUNT(*) 
            FROM transactions 
            WHERE transaction_type = ? AND DATE(transaction_date) = ?
        ");
    }
    $stmt->execute([$type, date('Y-m-d', strtotime($date))]);
    $count = (int)$stmt->fetchColumn() + 1;
    
    return $prefix . '-' . $dateStr . '-' . str_pad($count, 4, '0', STR_PAD_LEFT);
}
?>
