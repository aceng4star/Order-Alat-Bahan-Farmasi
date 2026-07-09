<?php
require_once __DIR__ . '/config.php';

function getDbConnection() {
    static $pdo = null;
    if ($pdo !== null) {
        return $pdo;
    }

    try {
        // Coba hubungkan ke MySQL/MariaDB
        $dsnWithoutDb = "mysql:host=" . DB_HOST . ";charset=" . DB_CHARSET;
        $tempPdo = new PDO($dsnWithoutDb, DB_USER, DB_PASS, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_TIMEOUT => 2
        ]);
        
        $dbName = DB_NAME;
        $tempPdo->exec("CREATE DATABASE IF NOT EXISTS `$dbName` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
        $tempPdo = null;

        // Hubungkan ke database sesungguhnya
        $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
        $pdo = new PDO($dsn, DB_USER, DB_PASS, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false
        ]);
        
        // Jalankan inisialisasi tabel & seeder
        initDatabase($pdo);
        
        return $pdo;
    } catch (PDOException $e) {
        // Jika koneksi MySQL gagal, fall back ke SQLite
        try {
            $sqlitePath = __DIR__ . '/database.sqlite';
            $pdo = new PDO("sqlite:" . $sqlitePath, null, null, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
            ]);
            
            // Aktifkan Foreign Keys di SQLite
            $pdo->exec("PRAGMA foreign_keys = ON;");
            
            // Jalankan inisialisasi tabel & seeder khusus SQLite
            initDatabase($pdo);
            
            return $pdo;
        } catch (PDOException $sqliteEx) {
            if (str_contains($_SERVER['REQUEST_URI'] ?? '', 'api.php') || (defined('API_CALL') && API_CALL)) {
                header('Content-Type: application/json');
                http_response_code(500);
                echo json_encode([
                    'success' => false,
                    'message' => 'Koneksi Database Gagal (MySQL & SQLite): ' . $sqliteEx->getMessage()
                ]);
                exit;
            } else {
                die("Koneksi Database Gagal (MySQL & SQLite): " . $sqliteEx->getMessage());
            }
        }
    }
}

// Inisialisasi Database dan Seeders
function initDatabase($db) {
    $driver = $db->getAttribute(PDO::ATTR_DRIVER_NAME);

    // SQL Helper untuk penyesuaian driver database
    $executeSql = function($sql) use ($db, $driver) {
        if ($driver === 'sqlite') {
            $sql = preg_replace('/INT\s+AUTO_INCREMENT\s+PRIMARY\s+KEY/i', 'INTEGER PRIMARY KEY AUTOINCREMENT', $sql);
            $sql = preg_replace('/ENGINE\s*=\s*\w+/i', '', $sql);
            $sql = preg_replace('/DEFAULT\s+CHARSET\s*=\s*\w+/i', '', $sql);
        }
        $db->exec($sql);
    };

    // 1. Buat Tabel-tabel Master
    $executeSql("CREATE TABLE IF NOT EXISTS products (
        id INT AUTO_INCREMENT PRIMARY KEY,
        code VARCHAR(50) UNIQUE NOT NULL,
        name VARCHAR(255) NOT NULL,
        category VARCHAR(100),
        unit VARCHAR(50) NOT NULL,
        description TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    );");

    $executeSql("CREATE TABLE IF NOT EXISTS locations (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(100) UNIQUE NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    );");

    $executeSql("CREATE TABLE IF NOT EXISTS suppliers (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(255) UNIQUE NOT NULL,
        phone VARCHAR(50),
        address TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    );");

    $executeSql("CREATE TABLE IF NOT EXISTS customers (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(255) UNIQUE NOT NULL,
        phone VARCHAR(50),
        address TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    );");

    // 2. Buat Tabel Stok & Transaksi Internal
    $executeSql("CREATE TABLE IF NOT EXISTS stock (
        product_id INT NOT NULL,
        location_id INT NOT NULL,
        qty DECIMAL(15,4) DEFAULT 0.0000,
        PRIMARY KEY (product_id, location_id),
        FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE,
        FOREIGN KEY (location_id) REFERENCES locations(id) ON DELETE CASCADE
    );");

    $executeSql("CREATE TABLE IF NOT EXISTS transactions (
        id INT AUTO_INCREMENT PRIMARY KEY,
        reference_no VARCHAR(100) UNIQUE NOT NULL,
        transaction_type VARCHAR(50) NOT NULL,
        transaction_date DATETIME NOT NULL,
        source_location_id INT NULL,
        target_location_id INT NULL,
        supplier_id INT NULL,
        customer_id INT NULL,
        notes TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (source_location_id) REFERENCES locations(id) ON DELETE SET NULL,
        FOREIGN KEY (target_location_id) REFERENCES locations(id) ON DELETE SET NULL,
        FOREIGN KEY (supplier_id) REFERENCES suppliers(id) ON DELETE SET NULL,
        FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE SET NULL
    );");

    $executeSql("CREATE TABLE IF NOT EXISTS transaction_details (
        id INT AUTO_INCREMENT PRIMARY KEY,
        transaction_id INT NOT NULL,
        product_id INT NOT NULL,
        qty DECIMAL(15,4) NOT NULL,
        unit_price DECIMAL(15,2) DEFAULT 0.00,
        notes TEXT,
        FOREIGN KEY (transaction_id) REFERENCES transactions(id) ON DELETE CASCADE,
        FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE
    );");

    // 3. Buat Tabel Users (Otentikasi)
    $executeSql("CREATE TABLE IF NOT EXISTS users (
        id INT AUTO_INCREMENT PRIMARY KEY,
        username VARCHAR(100) UNIQUE NOT NULL,
        password VARCHAR(255) NOT NULL,
        role VARCHAR(50) NOT NULL, -- 'ADMIN' atau 'CUSTOMER'
        customer_id INT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE CASCADE
    );");

    // 4. Buat Tabel Orders (Pesanan Customer)
    $executeSql("CREATE TABLE IF NOT EXISTS orders (
        id INT AUTO_INCREMENT PRIMARY KEY,
        order_no VARCHAR(100) UNIQUE NOT NULL,
        customer_id INT NOT NULL,
        order_date DATETIME NOT NULL,
        status VARCHAR(50) DEFAULT 'DIPESAN', -- 'DIPESAN', 'TERSEDIA', 'BATAL'
        notes TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE CASCADE
    );");

    $executeSql("CREATE TABLE IF NOT EXISTS order_details (
        id INT AUTO_INCREMENT PRIMARY KEY,
        order_id INT NOT NULL,
        product_id INT NOT NULL,
        qty DECIMAL(15,4) NOT NULL,
        notes TEXT,
        FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE,
        FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE
    );");

    // --- SEEDING DATA ---

    // A. Seed Produk, Lokasi, Supplier, Customer, & Stok
    $stmtProdCount = $db->query("SELECT COUNT(*) FROM products");
    if ($stmtProdCount->fetchColumn() == 0) {
        // Seed Lokasi
        $locations = ["Gudang Utama", "Gudang Depan", "Toko Cabang"];
        $stmtLoc = $db->prepare("INSERT INTO locations (name) VALUES (?)");
        foreach ($locations as $loc) {
            $stmtLoc->execute([$loc]);
        }

        // Seed Supplier
        $suppliers = [
            ["PT Bio Farma", "021-1234567", "Jl. Pasteur No. 28, Bandung"],
            ["Kimia Farma", "021-7654321", "Jl. Veteran No. 9, Jakarta Pusat"],
            ["Kalbe Farma", "021-9876543", "Kawasan Industri Pulogadung, Jakarta Timur"],
            ["Indofarma", "021-5432109", "Jl. Indofarma No. 1, Cikarang"]
        ];
        $stmtSup = $db->prepare("INSERT INTO suppliers (name, phone, address) VALUES (?, ?, ?)");
        foreach ($suppliers as $sup) {
            $stmtSup->execute($sup);
        }

        // Seed Customer
        $customers = [
            ["Apotek Sehat Pharmacy", "0812-3456-7890", "Jl. Raya Bogor KM 30, Depok"],
            ["Rumah Sakit Bakti Husada", "0813-9876-5432", "Jl. Sudirman No. 120, Jakarta"],
            ["Klinik Pratama Medika", "0811-2233-4455", "Jl. Melati No. 5, Bogor"],
            ["Apotek Kita Jaya", "0855-6677-8899", "Jl. Pahlawan No. 45, Tangerang"]
        ];
        $stmtCust = $db->prepare("INSERT INTO customers (name, phone, address) VALUES (?, ?, ?)");
        foreach ($customers as $cust) {
            $stmtCust->execute($cust);
        }

        // Seed Produk
        $products = [
            ["PRD-001", "Paracetamol 500mg", "Obat Bebas", "Box", "Obat demam dan pereda nyeri isi 100 tablet per box."],
            ["PRD-002", "Amoxicillin 500mg", "Obat Keras", "Box", "Antibiotik spektrum luas isi 100 kaplet per box."],
            ["PRD-003", "Vitamin C 1000mg", "Suplemen", "Botol", "Suplemen imunitas isi 30 tablet per botol."],
            ["PRD-004", "Masker Medis 3-Ply", "Alat Kesehatan", "Box", "Masker pelindung medis isi 50 pcs per box."],
            ["PRD-005", "Jarum Suntik 3ml", "Alat Kesehatan", "Pack", "Syringe sekali pakai dengan jarum, isi 100 pcs per pack."]
        ];
        $stmtProd = $db->prepare("INSERT INTO products (code, name, category, unit, description) VALUES (?, ?, ?, ?, ?)");
        foreach ($products as $prod) {
            $stmtProd->execute($prod);
        }

        // Dapatkan ID
        $pIds = $db->query("SELECT id FROM products ORDER BY id ASC")->fetchAll(PDO::FETCH_COLUMN);
        $lIds = $db->query("SELECT id FROM locations ORDER BY id ASC")->fetchAll(PDO::FETCH_COLUMN);
        $sIds = $db->query("SELECT id FROM suppliers ORDER BY id ASC")->fetchAll(PDO::FETCH_COLUMN);
        $cIds = $db->query("SELECT id FROM customers ORDER BY id ASC")->fetchAll(PDO::FETCH_COLUMN);

        // --- TRANSAKSI STOK MOCK ---
        // 1. Stok Opname (Saldo Awal) - Gudang Utama
        $refOpname = "OPN-20260701-0001";
        $db->prepare("INSERT INTO transactions (reference_no, transaction_type, transaction_date, target_location_id, notes) 
                      VALUES (?, 'OPNAME', '2026-07-01 08:00:00', ?, 'Stok Awal Saldo Gudang Utama')")
           ->execute([$refOpname, $lIds[0]]);
        $tIdOpname = $db->lastInsertId();

        $stmtDet = $db->prepare("INSERT INTO transaction_details (transaction_id, product_id, qty, unit_price, notes) VALUES (?, ?, ?, ?, ?)");
        $stmtStock = $db->prepare("INSERT INTO stock (product_id, location_id, qty) VALUES (?, ?, ?)");

        $initialQtyGudangUtama = [100, 80, 150, 200, 50]; 
        $initialPrices = [12000, 35000, 45000, 25000, 85000];
        for ($i = 0; $i < 5; $i++) {
            $stmtDet->execute([$tIdOpname, $pIds[$i], $initialQtyGudangUtama[$i], $initialPrices[$i], "Saldo Awal"]);
            $stmtStock->execute([$pIds[$i], $lIds[0], $initialQtyGudangUtama[$i]]);
        }

        // 2. Pembelian Supplier
        $refPurchase = "PUR-20260703-0001";
        $db->prepare("INSERT INTO transactions (reference_no, transaction_type, transaction_date, target_location_id, supplier_id, notes) 
                      VALUES (?, 'PURCHASE', '2026-07-03 10:15:00', ?, ?, 'Pembelian Rutin Bulanan')")
           ->execute([$refPurchase, $lIds[0], $sIds[0]]);
        $tIdPurchase = $db->lastInsertId();

        $stmtDet->execute([$tIdPurchase, $pIds[0], 50, 11500, "Order normal"]);
        $stmtDet->execute([$tIdPurchase, $pIds[1], 30, 34000, "Order normal"]);

        $db->prepare("UPDATE stock SET qty = qty + 50 WHERE product_id = ? AND location_id = ?")->execute([$pIds[0], $lIds[0]]);
        $db->prepare("UPDATE stock SET qty = qty + 30 WHERE product_id = ? AND location_id = ?")->execute([$pIds[1], $lIds[0]]);

        // 3. Penjualan Customer
        $refSale = "SAL-20260705-0001";
        $db->prepare("INSERT INTO transactions (reference_no, transaction_type, transaction_date, source_location_id, customer_id, notes) 
                      VALUES (?, 'SALE', '2026-07-05 14:30:00', ?, ?, 'Penjualan Resep & Alkes')")
           ->execute([$refSale, $lIds[0], $cIds[0]]);
        $tIdSale = $db->lastInsertId();

        $stmtDet->execute([$tIdSale, $pIds[0], 20, 15000, "Eceran"]);
        $stmtDet->execute([$tIdSale, $pIds[3], 40, 35000, "Grosir"]);

        $db->prepare("UPDATE stock SET qty = qty - 20 WHERE product_id = ? AND location_id = ?")->execute([$pIds[0], $lIds[0]]);
        $db->prepare("UPDATE stock SET qty = qty - 40 WHERE product_id = ? AND location_id = ?")->execute([$pIds[3], $lIds[0]]);

        // 4. Transfer
        $refTransfer = "TRF-20260707-0001";
        $db->prepare("INSERT INTO transactions (reference_no, transaction_type, transaction_date, source_location_id, target_location_id, notes) 
                      VALUES (?, 'TRANSFER', '2026-07-07 09:00:00', ?, ?, 'Distribusi Stok ke Toko Cabang')")
           ->execute([$refTransfer, $lIds[0], $lIds[2]]);
        $tIdTransfer = $db->lastInsertId();

        $stmtDet->execute([$tIdTransfer, $pIds[2], 15, 45000, "Distribusi"]);
        $stmtDet->execute([$tIdTransfer, $pIds[3], 10, 25000, "Distribusi"]);

        $db->prepare("UPDATE stock SET qty = qty - 15 WHERE product_id = ? AND location_id = ?")->execute([$pIds[2], $lIds[0]]);
        $db->prepare("UPDATE stock SET qty = qty - 10 WHERE product_id = ? AND location_id = ?")->execute([$pIds[3], $lIds[0]]);
        
        $stmtStock->execute([$pIds[2], $lIds[2], 15]);
        $stmtStock->execute([$pIds[3], $lIds[2], 10]);

        // Stok sisa lokasi lain
        for ($i = 0; $i < 5; $i++) {
            if ($i != 2 && $i != 3) {
                $stmtStock->execute([$pIds[$i], $lIds[2], 10]);
            }
            $stmtStock->execute([$pIds[$i], $lIds[1], 25]);
        }
    }

    // B. Seed Users (Admin & Customer Users)
    $stmtUserCount = $db->query("SELECT COUNT(*) FROM users");
    if ($stmtUserCount->fetchColumn() == 0) {
        $cIds = $db->query("SELECT id FROM customers ORDER BY id ASC")->fetchAll(PDO::FETCH_COLUMN);

        $stmtUser = $db->prepare("INSERT INTO users (username, password, role, customer_id) VALUES (?, ?, ?, ?)");
        
        // 1. Admin Account (admin / admin123)
        $stmtUser->execute(["admin", password_hash("admin123", PASSWORD_DEFAULT), "ADMIN", null]);
        
        // 2. Customer 1 (sehat / sehat123) -> Apotek Sehat Pharmacy
        if (isset($cIds[0])) {
            $stmtUser->execute(["sehat", password_hash("sehat123", PASSWORD_DEFAULT), "CUSTOMER", $cIds[0]]);
        }
        // 3. Customer 2 (bakti / bakti123) -> RS Bakti Husada
        if (isset($cIds[1])) {
            $stmtUser->execute(["bakti", password_hash("bakti123", PASSWORD_DEFAULT), "CUSTOMER", $cIds[1]]);
        }
    }

    // C. Seed Customer Orders
    $stmtOrderCount = $db->query("SELECT COUNT(*) FROM orders");
    if ($stmtOrderCount->fetchColumn() == 0) {
        $cIds = $db->query("SELECT id FROM customers ORDER BY id ASC")->fetchAll(PDO::FETCH_COLUMN);
        $pIds = $db->query("SELECT id FROM products ORDER BY id ASC")->fetchAll(PDO::FETCH_COLUMN);

        if (isset($cIds[0]) && count($pIds) >= 3) {
            // Order 1: DIPESAN (Apotek Sehat)
            $refOrd1 = "ORD-20260708-0001";
            $db->prepare("INSERT INTO orders (order_no, customer_id, order_date, status, notes) 
                          VALUES (?, ?, '2026-07-08 11:00:00', 'DIPESAN', 'Pemesanan mendesak untuk apotek cabang utama')")
               ->execute([$refOrd1, $cIds[0]]);
            $oId1 = $db->lastInsertId();

            $stmtOdet = $db->prepare("INSERT INTO order_details (order_id, product_id, qty, notes) VALUES (?, ?, ?, ?)");
            $stmtOdet->execute([$oId1, $pIds[0], 15, "Sediaan influenza"]); // Paracetamol 15
            $stmtOdet->execute([$oId1, $pIds[2], 10, "Suplemen imun"]);   // Vit C 10
        }

        if (isset($cIds[1]) && count($pIds) >= 5) {
            // Order 2: TERSEDIA (RS Bakti Husada)
            $refOrd2 = "ORD-20260709-0001";
            $db->prepare("INSERT INTO orders (order_no, customer_id, order_date, status, notes) 
                          VALUES (?, ?, '2026-07-09 09:30:00', 'TERSEDIA', 'Alkes bulanan poli bedah')")
               ->execute([$refOrd2, $cIds[1]]);
            $oId2 = $db->lastInsertId();

            $stmtOdet = $db->prepare("INSERT INTO order_details (order_id, product_id, qty, notes) VALUES (?, ?, ?, ?)");
            $stmtOdet->execute([$oId2, $pIds[3], 25, "Kebutuhan ruangan"]); // Masker 25
            $stmtOdet->execute([$oId2, $pIds[4], 5, "Disposable syringe"]); // Jarum suntik 5
        }
    }
}
?>
