<?php
require_once __DIR__ . '/db.php';
$db = getDbConnection();
?>
<!DOCTYPE html>
<html lang="id" data-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Apotek & Farmasi - Inventory Control System</title>
    <link rel="stylesheet" href="style.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="https://unpkg.com/lucide@latest"></script>
</head>
<body>

<!-- ==================== HALAMAN LOGIN ==================== -->
<div class="login-wrapper" id="login-screen">
    <div class="login-card">
        <div class="login-logo">
            <i data-lucide="package-check"></i>
        </div>
        <div class="login-header">
            <h2>Selamat Datang</h2>
            <p>Masukkan kredensial Anda untuk mengakses<br><strong>Farmasi Inventory Management System</strong></p>
        </div>
        <form id="form-login" style="display:flex;flex-direction:column;gap:16px;">
            <div class="form-group">
                <label for="login_username">Username</label>
                <div style="position:relative;">
                    <i data-lucide="user" style="position:absolute;left:12px;top:50%;transform:translateY(-50%);color:var(--text-muted);width:16px;height:16px;"></i>
                    <input type="text" id="login_username" placeholder="Masukkan username Anda" autocomplete="username" required style="padding-left:40px;">
                </div>
            </div>
            <div class="form-group">
                <label for="login_password">Password</label>
                <div style="position:relative;">
                    <i data-lucide="lock" style="position:absolute;left:12px;top:50%;transform:translateY(-50%);color:var(--text-muted);width:16px;height:16px;"></i>
                    <input type="password" id="login_password" placeholder="Masukkan password Anda" autocomplete="current-password" required style="padding-left:40px;">
                </div>
            </div>
            <button type="submit" class="btn-primary" style="width:100%;justify-content:center;padding:12px;margin-top:4px;">
                <i data-lucide="log-in" style="width:18px;height:18px;"></i>
                Masuk ke Sistem
            </button>
        </form>
        <div class="login-footer">
            <p>Default: <strong>admin</strong> / <strong>admin123</strong> &bull; Customer: <strong>sehat</strong> / <strong>sehat123</strong></p>
        </div>
    </div>
</div>

<!-- ==================== LAYOUT UTAMA APLIKASI ==================== -->
<div class="app-container" id="app-main-layout" style="display:none;">

    <!-- SIDEBAR NAVIGASI -->
    <aside class="sidebar">
        <div class="sidebar-header">
            <div class="sidebar-logo">
                <i data-lucide="package-check"></i>
            </div>
            <div class="sidebar-title">
                <div>FARMASI INVENTORY</div>
                <span style="font-size:10px;color:var(--text-muted);font-weight:600;letter-spacing:0.5px;">MANAGEMENT SYSTEM</span>
            </div>
        </div>

        <ul class="sidebar-menu">
            <!-- Menu ADMIN Only -->
            <li class="menu-label admin-only">Analitik</li>
            <li class="admin-only"><a href="#" data-tab="dashboard"><i data-lucide="layout-dashboard"></i> Dashboard</a></li>
            <li class="admin-only"><a href="#" data-tab="stock"><i data-lucide="boxes"></i> Ringkasan Stok</a></li>
            <li class="admin-only"><a href="#" data-tab="transactions"><i data-lucide="arrow-left-right"></i> Riwayat Transaksi</a></li>

            <!-- Menu ADMIN Only -->
            <li class="menu-label admin-only">Manajemen</li>
            <li class="admin-only"><a href="#" data-tab="master"><i data-lucide="database"></i> Data Master</a></li>

            <!-- Menu Bersama (Order) -->
            <li class="menu-label">Pesanan</li>
            <li><a href="#" data-tab="customer-orders">
                <i data-lucide="shopping-cart"></i>
                <span class="admin-only">Manajemen Order</span>
                <span class="customer-only">Pesanan Saya</span>
            </a></li>
        </ul>

        <div class="sidebar-footer">
            <div style="display:flex;align-items:center;gap:10px;flex:1;">
                <div id="sidebar-user-avatar" style="width:36px;height:36px;border-radius:50%;background:var(--accent-primary);display:flex;align-items:center;justify-content:center;font-weight:700;font-size:11px;color:white;flex-shrink:0;">
                    ADM
                </div>
                <div style="overflow:hidden;">
                    <div id="user-display-name" style="font-size:13px;font-weight:600;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">Administrator</div>
                    <div id="user-display-role" style="font-size:10px;color:var(--text-muted);">Sistem Farmasi</div>
                </div>
            </div>
            <button id="btn-logout" title="Keluar" style="background:none;border:none;cursor:pointer;color:var(--text-muted);padding:6px;border-radius:6px;transition:all 0.2s;" onmouseover="this.style.color='var(--danger)'" onmouseout="this.style.color='var(--text-muted)'">
                <i data-lucide="log-out" style="width:18px;height:18px;"></i>
            </button>
        </div>
    </aside>

    <!-- AREA CONTENT UTAMA -->
    <main class="main-content">
        <!-- TOP BAR -->
        <header class="top-nav">
            <div style="display:flex;align-items:center;gap:12px;">
                <button class="hamburger" id="menu-hamburger"><i data-lucide="menu"></i></button>
                <div>
                    <h1 id="view-title">Inventory Control</h1>
                    <p id="view-subtitle" style="color:var(--text-secondary);font-size:14px;">Sistem manajemen persediaan alat dan bahan farmasi.</p>
                </div>
            </div>
            <div class="nav-actions">
                <button class="theme-toggle-btn" id="theme-toggle" title="Ubah Tema">
                    <i data-lucide="sun" style="width:20px;height:20px;"></i>
                </button>
                <div style="display:flex;align-items:center;gap:8px;font-size:13px;background-color:var(--bg-secondary);border:1px solid var(--border-color);padding:8px 14px;border-radius:8px;">
                    <span style="width:8px;height:8px;border-radius:50%;background-color:var(--success);display:inline-block;"></span>
                    <span class="text-muted">DB:</span> <strong>MariaDB/SQLite</strong>
                </div>
            </div>
        </header>

        <!-- ====== VIEW: DASHBOARD ====== -->
        <section id="view-dashboard" class="view-panel">
            <div class="stat-grid">
                <div class="stat-card blue">
                    <div class="stat-info">
                        <h3>Total Produk</h3>
                        <div class="stat-value" id="stat-total-products">-</div>
                    </div>
                    <div class="stat-icon"><i data-lucide="pill"></i></div>
                </div>
                <div class="stat-card indigo">
                    <div class="stat-info">
                        <h3>Stok Terhitung</h3>
                        <div class="stat-value" id="stat-total-stock">-</div>
                    </div>
                    <div class="stat-icon"><i data-lucide="package"></i></div>
                </div>
                <div class="stat-card green">
                    <div class="stat-info">
                        <h3>Gudang / Cabang</h3>
                        <div class="stat-value" id="stat-total-locations">-</div>
                    </div>
                    <div class="stat-icon"><i data-lucide="map-pin"></i></div>
                </div>
                <div class="stat-card orange">
                    <div class="stat-info">
                        <h3>Stok Menipis</h3>
                        <div class="stat-value" id="stat-low-stock-count">-</div>
                    </div>
                    <div class="stat-icon"><i data-lucide="alert-triangle"></i></div>
                </div>
            </div>

            <div class="charts-grid">
                <div class="chart-card">
                    <div class="chart-header">
                        <h3>Tren Nilai Transaksi Bulanan (Pembelian vs Penjualan)</h3>
                    </div>
                    <div class="chart-wrapper">
                        <canvas id="chart-trend"></canvas>
                    </div>
                </div>
                <div class="chart-card">
                    <div class="chart-header">
                        <h3>Komposisi Stok per Lokasi</h3>
                    </div>
                    <div class="chart-wrapper">
                        <canvas id="chart-locations"></canvas>
                    </div>
                </div>
            </div>

            <div style="display:grid;grid-template-columns:1fr 1.2fr;gap:24px;">
                <div class="low-stock-card">
                    <div class="low-stock-header">
                        <h3><i data-lucide="alert-circle" style="color:var(--warning);"></i> Peringatan Stok Rendah</h3>
                        <span class="low-stock-badge">Batasan minimum &lt; 15</span>
                    </div>
                    <div class="table-container" style="max-height:300px;overflow-y:auto;">
                        <table class="custom-table">
                            <thead><tr><th>Kode</th><th>Nama Produk</th><th>Lokasi</th><th>Sisa Stok</th></tr></thead>
                            <tbody id="table-low-stock-body"></tbody>
                        </table>
                    </div>
                </div>
                <div class="low-stock-card">
                    <div class="low-stock-header">
                        <h3><i data-lucide="history" style="color:var(--accent-primary);"></i> Transaksi Terakhir</h3>
                    </div>
                    <div class="table-container" style="max-height:300px;overflow-y:auto;">
                        <table class="custom-table">
                            <thead><tr><th>Ref No</th><th>Tipe</th><th>Tanggal</th><th>Alur</th><th>Total Item</th></tr></thead>
                            <tbody id="table-recent-body"></tbody>
                        </table>
                    </div>
                </div>
            </div>
        </section>

        <!-- ====== VIEW: RINGKASAN STOK ====== -->
        <section id="view-stock" class="view-panel">
            <div class="control-bar">
                <div>
                    <h2 style="font-size:16px;font-weight:700;">Stok Posisi Real-Time</h2>
                    <span class="text-muted" style="font-size:13px;">Matriks rincian jumlah persediaan barang farmasi di setiap lokasi fisik.</span>
                </div>
                <button class="btn-primary" onclick="openCreateTransactionModal()">
                    <i data-lucide="plus" style="width:16px;height:16px;"></i> Input Transaksi / Opname
                </button>
            </div>
            <div class="table-container">
                <table class="custom-table" id="table-stock-pivot">
                    <thead><tr id="stock-table-header"></tr></thead>
                    <tbody id="table-stock-body"></tbody>
                </table>
            </div>
        </section>

        <!-- ====== VIEW: RIWAYAT TRANSAKSI ====== -->
        <section id="view-transactions" class="view-panel">
            <div class="control-bar">
                <div class="search-box">
                    <i data-lucide="search" style="width:18px;height:18px;"></i>
                    <input type="text" id="search-tx" placeholder="Cari Kode Ref, Produk, Keterangan...">
                </div>
                <div class="filter-group">
                    <select id="filter-type" class="filter-select">
                        <option value="">Semua Transaksi</option>
                        <option value="OPNAME">Stok Opname</option>
                        <option value="PURCHASE">Pembelian Supplier</option>
                        <option value="RETURN_PURCHASE">Retur Pembelian</option>
                        <option value="SALE">Penjualan Customer</option>
                        <option value="RETURN_SALE">Retur Penjualan</option>
                        <option value="TRANSFER">Transfer Lokasi</option>
                    </select>
                    <select id="filter-location" class="filter-select">
                        <option value="">Semua Lokasi</option>
                    </select>
                    <div style="display:flex;align-items:center;gap:6px;">
                        <input type="date" id="filter-date-start" class="filter-select" style="min-width:120px;">
                        <span class="text-muted">s/d</span>
                        <input type="date" id="filter-date-end" class="filter-select" style="min-width:120px;">
                    </div>
                </div>
                <div style="display:flex;gap:8px;">
                    <button class="btn-secondary" id="btn-export-csv">
                        <i data-lucide="download" style="width:16px;height:16px;display:inline-block;vertical-align:middle;margin-right:4px;"></i> Ekspor CSV
                    </button>
                    <button class="btn-primary" onclick="openCreateTransactionModal()">
                        <i data-lucide="plus" style="width:16px;height:16px;"></i> Transaksi Baru
                    </button>
                </div>
            </div>
            <div class="table-container">
                <table class="custom-table">
                    <thead><tr>
                        <th>No. Referensi</th><th>Tipe Transaksi</th><th>Tanggal</th>
                        <th>Alur Pengiriman</th><th>Keterangan</th><th>Aksi</th>
                    </tr></thead>
                    <tbody id="table-transactions-body"></tbody>
                </table>
            </div>
        </section>

        <!-- ====== VIEW: DATA MASTER ====== -->
        <section id="view-master" class="view-panel">
            <div style="display:flex;flex-direction:column;gap:24px;">

                <!-- Produk -->
                <div class="low-stock-card">
                    <div class="low-stock-header">
                        <h3><i data-lucide="pill" style="color:var(--accent-primary);"></i> Daftar Produk</h3>
                        <button class="btn-primary" onclick="openAddMasterModal('product')">
                            <i data-lucide="plus" style="width:14px;height:14px;"></i> Tambah Produk
                        </button>
                    </div>
                    <div class="table-container">
                        <table class="custom-table">
                            <thead><tr><th>Kode</th><th>Nama Produk</th><th>Kategori</th><th>Satuan</th><th>Deskripsi</th><th>Aksi</th></tr></thead>
                            <tbody id="master-products-body"></tbody>
                        </table>
                    </div>
                </div>

                <!-- Lokasi -->
                <div class="low-stock-card">
                    <div class="low-stock-header">
                        <h3><i data-lucide="map-pin" style="color:var(--success);"></i> Lokasi Penyimpanan</h3>
                        <button class="btn-primary" onclick="openAddMasterModal('location')">
                            <i data-lucide="plus" style="width:14px;height:14px;"></i> Tambah Lokasi
                        </button>
                    </div>
                    <div class="table-container">
                        <table class="custom-table">
                            <thead><tr><th>ID</th><th>Nama Lokasi</th><th>Tanggal Dibuat</th><th>Aksi</th></tr></thead>
                            <tbody id="master-locations-body"></tbody>
                        </table>
                    </div>
                </div>

                <!-- Supplier -->
                <div class="low-stock-card">
                    <div class="low-stock-header">
                        <h3><i data-lucide="truck" style="color:var(--info);"></i> Rekanan Supplier</h3>
                        <button class="btn-primary" onclick="openAddMasterModal('supplier')">
                            <i data-lucide="plus" style="width:14px;height:14px;"></i> Tambah Supplier
                        </button>
                    </div>
                    <div class="table-container">
                        <table class="custom-table">
                            <thead><tr><th>Nama Supplier</th><th>No. Telepon</th><th>Alamat</th><th>Aksi</th></tr></thead>
                            <tbody id="master-suppliers-body"></tbody>
                        </table>
                    </div>
                </div>

                <!-- Customer (dengan kolom Username Login) -->
                <div class="low-stock-card">
                    <div class="low-stock-header">
                        <h3><i data-lucide="users" style="color:var(--warning);"></i> Customer & Akun Login</h3>
                        <button class="btn-primary" onclick="openAddMasterModal('customer')">
                            <i data-lucide="plus" style="width:14px;height:14px;"></i> Tambah Customer
                        </button>
                    </div>
                    <div class="table-container">
                        <table class="custom-table">
                            <thead><tr><th>Nama Customer</th><th>Akun Login</th><th>No. Telepon</th><th>Alamat</th><th>Aksi</th></tr></thead>
                            <tbody id="master-customers-body"></tbody>
                        </table>
                    </div>
                </div>

            </div>
        </section>

        <!-- ====== VIEW: CUSTOMER ORDERS (Admin: Manajemen, Customer: Pesanan Saya) ====== -->
        <section id="view-customer-orders" class="view-panel">
            <div class="control-bar">
                <div>
                    <h2 style="font-size:16px;font-weight:700;" class="admin-only">Daftar Semua Pesanan Customer</h2>
                    <h2 style="font-size:16px;font-weight:700;" class="customer-only">Pesanan Saya</h2>
                    <span class="text-muted" style="font-size:13px;" class="admin-only">Verifikasi stok dan ubah status pesanan yang masuk dari rekanan.</span>
                    <span class="text-muted" style="font-size:13px;" class="customer-only">Pantau status ketersediaan pesanan barang farmasi Anda.</span>
                </div>
                <!-- Hanya customer yang bisa buat order baru -->
                <button class="btn-primary customer-only" onclick="openCreateOrderModal()">
                    <i data-lucide="plus" style="width:16px;height:16px;"></i> Buat Pesanan Baru
                </button>
            </div>

            <div class="table-container">
                <table class="custom-table">
                    <thead>
                        <tr>
                            <th>No. Order</th>
                            <th class="admin-only">Customer</th>
                            <th>Tanggal Pesanan</th>
                            <th>Daftar Barang</th>
                            <th>Status</th>
                            <th>Aksi</th>
                        </tr>
                    </thead>
                    <tbody id="table-orders-body"></tbody>
                </table>
            </div>
        </section>

    </main>
</div>

<!-- ==================== MODAL: TRANSAKSI INTERNAL ==================== -->
<div class="modal-overlay" id="modal-transaction">
    <div class="modal-container" style="max-width:750px;">
        <div class="modal-header">
            <h3>Pencatatan Transaksi Barang</h3>
            <button class="modal-close"><i data-lucide="x"></i></button>
        </div>
        <form id="form-transaction">
            <div class="modal-body">
                <div class="form-row">
                    <div class="form-group">
                        <label for="tx_type">Jenis Transaksi</label>
                        <select id="tx_type" required>
                            <option value="OPNAME">Stok Opname / Saldo Awal</option>
                            <option value="PURCHASE">Pembelian Supplier (+ Stok)</option>
                            <option value="RETURN_PURCHASE">Retur Pembelian (- Stok)</option>
                            <option value="SALE">Penjualan Customer (- Stok)</option>
                            <option value="RETURN_SALE">Retur Penjualan (+ Stok)</option>
                            <option value="TRANSFER">Transfer Antar Lokasi (Mutasi)</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="tx_date">Tanggal Transaksi</label>
                        <input type="date" id="tx_date" required>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group" id="wrapper-source-location" style="display:none;">
                        <label for="tx_source_location">Lokasi Asal</label>
                        <select id="tx_source_location"></select>
                    </div>
                    <div class="form-group" id="wrapper-target-location" style="display:none;">
                        <label for="tx_target_location">Lokasi Tujuan</label>
                        <select id="tx_target_location"></select>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group" id="wrapper-supplier" style="display:none;">
                        <label for="tx_supplier">Rekan Supplier</label>
                        <select id="tx_supplier"></select>
                    </div>
                    <div class="form-group" id="wrapper-customer" style="display:none;">
                        <label for="tx_customer">Rekan Customer</label>
                        <select id="tx_customer"></select>
                    </div>
                </div>

                <div class="form-group">
                    <label for="tx_notes">Catatan / Keterangan</label>
                    <textarea id="tx_notes" rows="2" placeholder="Catatan tambahan..."></textarea>
                </div>

                <!-- Items -->
                <div class="items-list-container">
                    <div class="items-list-header">
                        <h4>Daftar Item Barang</h4>
                        <button type="button" class="btn-primary" id="btn-add-tx-item" style="padding:6px 12px;font-size:12px;">
                            <i data-lucide="plus-circle" style="width:14px;height:14px;"></i> Tambah Baris
                        </button>
                    </div>
                    <div style="display:grid;grid-template-columns:2fr 1fr 1.2fr 0.5fr;gap:10px;margin-bottom:6px;font-size:11px;font-weight:700;color:var(--text-muted);text-transform:uppercase;">
                        <div>Nama Produk</div><div>Kuantitas</div>
                        <div id="header-unit-price">Harga (Rp)</div><div></div>
                    </div>
                    <div id="tx-items-container"></div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn-secondary btn-close-modal">Batal</button>
                <button type="submit" class="btn-primary">Simpan Transaksi</button>
            </div>
        </form>
    </div>
</div>

<!-- ==================== MODAL: DETAIL TRANSAKSI ==================== -->
<div class="modal-overlay" id="modal-detail">
    <div class="modal-container" style="max-width:650px;">
        <div class="modal-header">
            <h3>Detail Transaksi</h3>
            <button class="modal-close"><i data-lucide="x"></i></button>
        </div>
        <div class="modal-body">
            <div class="detail-meta-grid">
                <div class="detail-meta-item"><strong>No. Referensi</strong><span id="detail-ref-no">-</span></div>
                <div class="detail-meta-item"><strong>Jenis Transaksi</strong><span id="detail-type">-</span></div>
                <div class="detail-meta-item"><strong>Tanggal & Waktu</strong><span id="detail-date">-</span></div>
                <div class="detail-meta-item"><strong>Alur Pengiriman</strong><span id="detail-flow">-</span></div>
            </div>
            <div class="form-group" style="margin-bottom:20px;">
                <label>Catatan / Keterangan</label>
                <div id="detail-notes" style="padding:10px;background-color:var(--bg-tertiary);border:1px solid var(--border-color);border-radius:8px;font-size:13px;white-space:pre-wrap;">-</div>
            </div>
            <div class="table-container">
                <table class="custom-table" style="font-size:13px;">
                    <thead><tr>
                        <th>Kode</th><th>Nama Produk</th>
                        <th style="text-align:right;">Kuantitas</th>
                        <th style="text-align:right;">Harga Satuan</th>
                        <th style="text-align:right;">Total Nilai</th>
                    </tr></thead>
                    <tbody id="table-detail-items-body"></tbody>
                </table>
            </div>
            <div class="detail-total" id="detail-grand-total" style="display:none;"></div>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn-primary btn-close-modal">Selesai</button>
        </div>
    </div>
</div>

<!-- ==================== MODAL: DATA MASTER (ADD / EDIT) ==================== -->
<div class="modal-overlay" id="modal-master">
    <div class="modal-container">
        <div class="modal-header">
            <h3 id="modal-master-title">Data Master</h3>
            <button class="modal-close"><i data-lucide="x"></i></button>
        </div>
        <form id="form-master">
            <input type="hidden" id="master-entity-type">
            <input type="hidden" id="master-id">
            <div class="modal-body">

                <!-- Form Produk -->
                <div id="master-fields-product" style="display:none;">
                    <div class="form-row">
                        <div class="form-group">
                            <label for="master_p_code">Kode Produk</label>
                            <input type="text" id="master_p_code" placeholder="Contoh: PRD-006">
                        </div>
                        <div class="form-group">
                            <label for="master_p_unit">Satuan Barang</label>
                            <input type="text" id="master_p_unit" placeholder="Box, Botol, Pcs, Tablet">
                        </div>
                    </div>
                    <div class="form-group">
                        <label for="master_p_name">Nama Produk</label>
                        <input type="text" id="master_p_name" placeholder="Contoh: Paracetamol Syrup 120ml">
                    </div>
                    <div class="form-group">
                        <label for="master_p_category">Kategori</label>
                        <select id="master_p_category">
                            <option value="Obat Bebas">Obat Bebas</option>
                            <option value="Obat Bebas Terbatas">Obat Bebas Terbatas</option>
                            <option value="Obat Keras">Obat Keras</option>
                            <option value="Suplemen">Suplemen</option>
                            <option value="Alat Kesehatan">Alat Kesehatan</option>
                            <option value="Lainnya">Lainnya</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="master_p_desc">Keterangan / Khasiat</label>
                        <textarea id="master_p_desc" rows="3" placeholder="Keterangan detail mengenai produk..."></textarea>
                    </div>
                </div>

                <!-- Form Lokasi -->
                <div id="master-fields-location" style="display:none;">
                    <div class="form-group">
                        <label for="master_l_name">Nama Gudang / Lokasi</label>
                        <input type="text" id="master_l_name" placeholder="Contoh: Gudang Barat, Apotek Cabang Depok">
                    </div>
                </div>

                <!-- Form Supplier -->
                <div id="master-fields-supplier" style="display:none;">
                    <div class="form-group">
                        <label for="master_s_name">Nama Supplier</label>
                        <input type="text" id="master_s_name" placeholder="Contoh: PT Kimia Farma Trading">
                    </div>
                    <div class="form-group">
                        <label for="master_s_phone">No. Telepon</label>
                        <input type="text" id="master_s_phone" placeholder="0812-XXXX-XXXX">
                    </div>
                    <div class="form-group">
                        <label for="master_s_address">Alamat</label>
                        <textarea id="master_s_address" rows="3" placeholder="Alamat lengkap supplier..."></textarea>
                    </div>
                </div>

                <!-- Form Customer (dengan field Username & Password Login) -->
                <div id="master-fields-customer" style="display:none;">
                    <div class="form-group">
                        <label for="master_c_name">Nama Customer</label>
                        <input type="text" id="master_c_name" placeholder="Contoh: Rumah Sakit Umum Pusat">
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label for="master_c_phone">No. Telepon</label>
                            <input type="text" id="master_c_phone" placeholder="021-XXXXXXX">
                        </div>
                        <div class="form-group">
                            <label for="master_c_address">Alamat</label>
                            <input type="text" id="master_c_address" placeholder="Alamat customer...">
                        </div>
                    </div>

                    <!-- Separator Akun Login -->
                    <div style="border-top:1px solid var(--border-color);margin:16px 0 18px;padding-top:16px;">
                        <div style="display:flex;align-items:center;gap:8px;margin-bottom:14px;">
                            <div style="width:28px;height:28px;border-radius:8px;background:var(--accent-glow);display:flex;align-items:center;justify-content:center;">
                                <i data-lucide="key-round" style="width:14px;height:14px;color:var(--accent-primary);"></i>
                            </div>
                            <div>
                                <div style="font-size:13px;font-weight:700;">Akun Login Customer</div>
                                <div style="font-size:11px;color:var(--text-muted);">Kosongkan username untuk menghapus akses login customer</div>
                            </div>
                        </div>
                        <div class="form-row">
                            <div class="form-group">
                                <label for="master_c_username">Username Login</label>
                                <input type="text" id="master_c_username" placeholder="Contoh: apotek_sehat" autocomplete="off">
                            </div>
                            <div class="form-group">
                                <label for="master_c_password">Password Baru <small style="color:var(--text-muted);">(kosong = tidak berubah)</small></label>
                                <input type="password" id="master_c_password" placeholder="Min. 6 karakter" autocomplete="new-password">
                            </div>
                        </div>
                    </div>
                </div>

            </div>
            <div class="modal-footer">
                <button type="button" class="btn-secondary btn-close-modal">Batal</button>
                <button type="submit" class="btn-primary">Simpan Data</button>
            </div>
        </form>
    </div>
</div>

<!-- ==================== MODAL: BUAT ORDER BARU (CUSTOMER) ==================== -->
<div class="modal-overlay" id="modal-order">
    <div class="modal-container" style="max-width:620px;">
        <div class="modal-header">
            <div>
                <h3>Buat Pesanan Baru</h3>
                <p style="font-size:12px;color:var(--text-muted);margin-top:2px;">Pesanan Anda akan diverifikasi oleh Admin sebelum disiapkan.</p>
            </div>
            <button class="modal-close"><i data-lucide="x"></i></button>
        </div>
        <form id="form-order">
            <div class="modal-body">
                <div class="form-row">
                    <div class="form-group">
                        <label for="order_date">Tanggal Pesanan</label>
                        <input type="date" id="order_date" required>
                    </div>
                    <div class="form-group">
                        <label for="order_notes">Catatan Pesanan</label>
                        <input type="text" id="order_notes" placeholder="Tujuan penggunaan, keperluan, dll...">
                    </div>
                </div>

                <!-- Items Pesanan -->
                <div class="items-list-container">
                    <div class="items-list-header">
                        <h4>Daftar Barang yang Dipesan</h4>
                        <button type="button" class="btn-primary" id="btn-add-order-item" style="padding:6px 12px;font-size:12px;">
                            <i data-lucide="plus-circle" style="width:14px;height:14px;"></i> Tambah Barang
                        </button>
                    </div>
                    <div style="display:grid;grid-template-columns:3fr 1.5fr 0.5fr;gap:12px;margin-bottom:8px;font-size:11px;font-weight:700;color:var(--text-muted);text-transform:uppercase;">
                        <div>Nama Produk</div><div>Jumlah</div><div></div>
                    </div>
                    <div id="order-items-container"></div>
                </div>

                <div style="background:var(--warning-glow);border:1px solid var(--warning);border-radius:8px;padding:12px 16px;font-size:12px;color:var(--warning);display:flex;gap:10px;align-items:flex-start;">
                    <i data-lucide="info" style="width:14px;height:14px;flex-shrink:0;margin-top:2px;"></i>
                    <span>Status pesanan awal adalah <strong>DIPESAN (Belum Tersedia)</strong>. Admin akan memproses dan mengubah status menjadi <strong>TERSEDIA</strong> setelah stok disiapkan.</span>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn-secondary btn-close-modal">Batal</button>
                <button type="submit" class="btn-primary">
                    <i data-lucide="send" style="width:14px;height:14px;"></i>
                    Kirim Pesanan
                </button>
            </div>
        </form>
    </div>
</div>

<!-- ==================== MODAL: DETAIL ORDER ==================== -->
<div class="modal-overlay" id="modal-detail-order">
    <div class="modal-container" style="max-width:580px;">
        <div class="modal-header">
            <h3>Detail Pesanan</h3>
            <button class="modal-close"><i data-lucide="x"></i></button>
        </div>
        <div class="modal-body">
            <div class="detail-meta-grid">
                <div class="detail-meta-item"><strong>No. Order</strong><span id="detail-order-no">-</span></div>
                <div class="detail-meta-item"><strong>Status</strong><span id="detail-order-status">-</span></div>
                <div class="detail-meta-item"><strong>Tanggal Pesanan</strong><span id="detail-order-date">-</span></div>
                <div class="detail-meta-item"><strong>Nama Customer</strong><span id="detail-order-cust">-</span></div>
            </div>
            <div class="form-group" style="margin-bottom:20px;">
                <label>Catatan Pesanan</label>
                <div id="detail-order-notes" style="padding:10px;background-color:var(--bg-tertiary);border:1px solid var(--border-color);border-radius:8px;font-size:13px;"></div>
            </div>
            <div class="table-container">
                <table class="custom-table" style="font-size:13px;">
                    <thead><tr>
                        <th>Kode</th><th>Nama Produk</th><th style="text-align:right;">Kuantitas</th>
                    </tr></thead>
                    <tbody id="table-detail-order-items-body"></tbody>
                </table>
            </div>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn-primary btn-close-modal">Tutup</button>
        </div>
    </div>
</div>

<!-- ==================== MODAL: UPDATE STATUS ORDER (ADMIN) ==================== -->
<div class="modal-overlay" id="modal-update-order-status">
    <div class="modal-container" style="max-width:480px;">
        <div class="modal-header">
            <div>
                <h3>Proses Pesanan Customer</h3>
                <p style="font-size:12px;color:var(--text-muted);margin-top:2px;">No. Order: <strong id="status-order-no">-</strong></p>
            </div>
            <button class="modal-close"><i data-lucide="x"></i></button>
        </div>
        <form id="form-status-order">
            <div class="modal-body">
                <div class="form-group">
                    <label for="order_status_select">Ubah Status Pesanan Menjadi</label>
                    <select id="order_status_select" required>
                        <option value="TERSEDIA">✅ TERSEDIA &mdash; Stok Disiapkan & Diserahkan</option>
                        <option value="BATAL">❌ BATAL &mdash; Pesanan Dibatalkan</option>
                    </select>
                </div>

                <!-- Pilihan Gudang (tampil hanya jika status = TERSEDIA) -->
                <div class="form-group" id="wrapper-order-status-location">
                    <label for="order_status_location">Pilih Gudang Sumber Stok</label>
                    <select id="order_status_location">
                        <option value="">Pilih Lokasi Sumber Stok</option>
                    </select>
                    <small style="color:var(--text-muted);display:block;margin-top:6px;">
                        <i data-lucide="info" style="width:12px;height:12px;vertical-align:middle;"></i>
                        Stok barang akan otomatis dipotong dari gudang yang dipilih dan transaksi penjualan akan dibuat secara otomatis.
                    </small>
                </div>

                <div style="background:var(--info-glow);border:1px solid var(--info);border-radius:8px;padding:12px 16px;font-size:12px;color:var(--info);display:flex;gap:10px;align-items:flex-start;margin-top:4px;">
                    <i data-lucide="info" style="width:14px;height:14px;flex-shrink:0;margin-top:2px;"></i>
                    <span>Sistem akan memvalidasi ketersediaan stok di gudang yang dipilih. Jika stok tidak mencukupi untuk salah satu item, proses akan dibatalkan dan status pesanan tidak berubah.</span>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn-secondary btn-close-modal">Batal</button>
                <button type="submit" class="btn-primary">
                    <i data-lucide="check-circle" style="width:14px;height:14px;"></i>
                    Konfirmasi & Proses
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Toast Notification Container -->
<div class="toast-container" id="toast-container"></div>

<!-- Scripts -->
<script src="app.js"></script>
<script>
    // Inisialisasi Lucide setelah DOM & app.js siap
    document.addEventListener('DOMContentLoaded', () => {
        lucide.createIcons();
    });
</script>

</body>
</html>
