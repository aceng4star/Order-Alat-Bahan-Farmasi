// Inventory Control - SPA Client Engine with Auth & Customer Orders
const STATE = {
    user: null, // { username, role, customer_name, customer_id }
    currentTab: 'dashboard',
    products: [],
    locations: [],
    suppliers: [],
    customers: [],
    transactions: [],
    orders: [],
    stockSummary: { locations: [], products: [] },
    charts: { trend: null, location: null }
};

document.addEventListener('DOMContentLoaded', () => {
    checkActiveSession();
});

// 1. CEK SESI AKTIF (STARTUP)
async function checkActiveSession() {
    registerAuthEvents();
    
    const res = await apiCall('session');
    if (res && res.success && res.user) {
        handleSuccessfulLogin(res.user);
    } else {
        showLoginScreen();
    }
}

// 2. REGISTER EVENT OTENTIKASI
function registerAuthEvents() {
    // Submit Login
    const loginForm = document.getElementById('form-login');
    if (loginForm) {
        loginForm.addEventListener('submit', async (e) => {
            e.preventDefault();
            const username = document.getElementById('login_username').value;
            const password = document.getElementById('login_password').value;
            
            const res = await apiCall('login', 'POST', { username, password });
            if (res && res.success && res.user) {
                showToast('Login berhasil! Selamat datang.', 'success');
                handleSuccessfulLogin(res.user);
            }
        });
    }

    // Tombol Logout di Sidebar
    document.getElementById('btn-logout').addEventListener('click', async (e) => {
        e.preventDefault();
        if (confirm('Apakah Anda yakin ingin keluar dari sistem?')) {
            const res = await apiCall('logout');
            if (res && res.success) {
                showToast('Logout berhasil.', 'success');
                showLoginScreen();
            }
        }
    });
}

function showLoginScreen() {
    STATE.user = null;
    document.getElementById('login-screen').style.display = 'flex';
    document.getElementById('app-main-layout').style.display = 'none';
    document.getElementById('form-login').reset();
}

function handleSuccessfulLogin(user) {
    STATE.user = user;
    document.getElementById('login-screen').style.display = 'none';
    document.getElementById('app-main-layout').style.display = 'flex';

    // Set role aktif di HTML body untuk visibilitas CSS (.admin-only / .customer-only)
    document.body.setAttribute('data-role-active', user.role);

    // Update Nama di Footer Sidebar & Profile
    document.getElementById('user-display-name').innerText = user.role === 'ADMIN' ? 'Administrator' : user.customer_name;
    document.getElementById('user-display-role').innerText = user.role === 'ADMIN' ? 'Sistem Farmasi' : 'Customer Rekanan';
    document.getElementById('sidebar-user-avatar').innerText = user.role === 'ADMIN' ? 'ADM' : user.customer_name.substring(0, 3).toUpperCase();

    // Register Event Lainnya jika belum
    registerAppEvents();
    
    // Load Master Data Awal
    loadInitialMasterData().then(() => {
        // Navigasi ke Tab Bawaan berdasarkan Role
        if (user.role === 'ADMIN') {
            switchTab('dashboard');
        } else {
            switchTab('customer-orders');
        }
    });

    // Default tanggal transaksi hari ini
    document.getElementById('tx_date').value = new Date().toISOString().split('T')[0];
    document.getElementById('order_date').value = new Date().toISOString().split('T')[0];
}

// 3. REGISTER EVENT APLIKASI
let appEventsRegistered = false;
function registerAppEvents() {
    if (appEventsRegistered) return;
    appEventsRegistered = true;

    // Navigasi Sidebar Tabs
    document.querySelectorAll('.sidebar-menu li a[data-tab]').forEach(link => {
        link.addEventListener('click', (e) => {
            e.preventDefault();
            const tabName = link.getAttribute('data-tab');
            switchTab(tabName);
            document.querySelector('.sidebar').classList.remove('active');
        });
    });

    // Toggle Tema
    document.getElementById('theme-toggle').addEventListener('click', toggleTheme);

    // Hamburger Menu Mobile
    document.getElementById('menu-hamburger').addEventListener('click', () => {
        document.querySelector('.sidebar').classList.toggle('active');
    });

    // Close Modals
    document.querySelectorAll('.modal-close, .btn-close-modal').forEach(btn => {
        btn.addEventListener('click', closeModal);
    });

    // Dynamic Selects in Transaction Modal
    document.getElementById('tx_type').addEventListener('change', adjustTransactionFormFields);

    // Add Item Row in Transaction Form
    document.getElementById('btn-add-tx-item').addEventListener('click', () => addTransactionItemRow());

    // Submit Transaction
    document.getElementById('form-transaction').addEventListener('submit', handleTransactionSubmit);

    // Submit Master Entity
    document.getElementById('form-master').addEventListener('submit', handleMasterSubmit);

    // Add Item Row in Customer Order Form
    document.getElementById('btn-add-order-item').addEventListener('click', () => addOrderItemRow());

    // Submit Customer Order Form
    document.getElementById('form-order').addEventListener('submit', handleOrderSubmit);

    // Submit Status Order Update (Admin)
    document.getElementById('form-status-order').addEventListener('submit', handleOrderStatusSubmit);

    // Filters for Transactions
    document.getElementById('filter-type').addEventListener('change', loadTransactionsData);
    document.getElementById('filter-location').addEventListener('change', loadTransactionsData);
    document.getElementById('filter-date-start').addEventListener('change', loadTransactionsData);
    document.getElementById('filter-date-end').addEventListener('change', loadTransactionsData);
    
    // Search Transactions
    let searchTimeout;
    document.getElementById('search-tx').addEventListener('input', () => {
        clearTimeout(searchTimeout);
        searchTimeout = setTimeout(loadTransactionsData, 300);
    });

    // Export CSV
    document.getElementById('btn-export-csv').addEventListener('click', exportTransactionsToCSV);

    // Status Order change dropdown in admin dialog (tampilkan gudang jika status = TERSEDIA)
    document.getElementById('order_status_select').addEventListener('change', (e) => {
        const warehouseWrapper = document.getElementById('wrapper-order-status-location');
        if (e.target.value === 'TERSEDIA') {
            warehouseWrapper.style.display = 'block';
            document.getElementById('order_status_location').required = true;
        } else {
            warehouseWrapper.style.display = 'none';
            document.getElementById('order_status_location').required = false;
        }
    });
}

// 4. SWITCH TABS & VALIDASI AKSES
function switchTab(tabName) {
    // Validasi Akses Role
    if (STATE.user.role === 'CUSTOMER') {
        const allowedCustomerTabs = ['customer-orders'];
        if (!allowedCustomerTabs.includes(tabName)) {
            tabName = 'customer-orders';
        }
    }

    STATE.currentTab = tabName;
    
    // Update Menu Sidebar Active
    document.querySelectorAll('.sidebar-menu li').forEach(li => li.classList.remove('active'));
    const activeLink = document.querySelector(`.sidebar-menu li a[data-tab="${tabName}"]`);
    if (activeLink) {
        activeLink.parentElement.classList.add('active');
    }

    // Toggle View Panels
    document.querySelectorAll('.view-panel').forEach(panel => panel.classList.remove('active'));
    const targetPanel = document.getElementById(`view-${tabName}`);
    if (targetPanel) {
        targetPanel.classList.add('active');
    }

    // Update Header Text dinamis
    updateHeaderText(tabName);

    // Load Data
    if (tabName === 'dashboard') {
        loadDashboardData();
    } else if (tabName === 'stock') {
        loadStockSummaryData();
    } else if (tabName === 'transactions') {
        loadTransactionsData();
    } else if (tabName === 'master') {
        renderMasterDataTables();
    } else if (tabName === 'customer-orders') {
        loadOrdersData();
    }
}

function updateHeaderText(tabName) {
    const titleEl = document.getElementById('view-title');
    const subtitleEl = document.getElementById('view-subtitle');

    switch (tabName) {
        case 'dashboard':
            titleEl.innerText = 'Dashboard Analitik';
            subtitleEl.innerText = 'Ringkasan posisi stok dan tren pengeluaran farmasi.';
            break;
        case 'stock':
            titleEl.innerText = 'Matriks Ringkasan Stok';
            subtitleEl.innerText = 'Posisi stok real-time seluruh produk obat di setiap gudang.';
            break;
        case 'transactions':
            titleEl.innerText = 'Riwayat Mutasi Barang';
            subtitleEl.innerText = 'Log mutasi internal stok (Opname, Pembelian, Penjualan, Transfer).';
            break;
        case 'master':
            titleEl.innerText = 'Pengelolaan Master Data';
            subtitleEl.innerText = 'Manajemen Produk, Lokasi, Rekanan Supplier, dan Akun Login Customer.';
            break;
        case 'customer-orders':
            titleEl.innerText = STATE.user.role === 'ADMIN' ? 'Manajemen Order Customer' : 'Daftar Pesanan Saya';
            subtitleEl.innerText = STATE.user.role === 'ADMIN' ? 'Lihat, verifikasi ketersediaan stok, dan proses pemesanan customer.' : 'Pesan alat/bahan farmasi dan pantau status ketersediaannya.';
            break;
    }
}

// 5. THEME SWITCHER
function toggleTheme() {
    const currentTheme = document.documentElement.getAttribute('data-theme') || 'dark';
    const newTheme = currentTheme === 'dark' ? 'light' : 'dark';
    document.documentElement.setAttribute('data-theme', newTheme);
    
    const themeIcon = document.querySelector('#theme-toggle i');
    if (newTheme === 'light') {
        themeIcon.setAttribute('data-lucide', 'moon');
    } else {
        themeIcon.setAttribute('data-lucide', 'sun');
    }
    lucide.createIcons();

    if (STATE.charts.trend) STATE.charts.trend.update();
    if (STATE.charts.location) STATE.charts.location.update();
}

// 6. GLOBAL API CALL WRAPPER
async function apiCall(action, method = 'GET', body = null) {
    try {
        const url = `api.php?action=${action}`;
        const options = {
            method: method,
            headers: { 'Content-Type': 'application/json' }
        };
        if (body) {
            options.body = JSON.stringify(body);
        }
        
        const response = await fetch(url, options);
        
        // Handle Session timeout (401)
        if (response.status === 401 && action !== 'session') {
            showToast('Sesi berakhir, silakan login kembali.', 'warning');
            showLoginScreen();
            return null;
        }

        const json = await response.json();
        
        if (!response.ok || !json.success) {
            throw new Error(json.message || `API error ${response.status}`);
        }
        return json;
    } catch (error) {
        showToast(error.message, 'error');
        console.error(error);
        return null;
    }
}

// 7. LOAD INITIAL ENTITIES FOR FORMS
async function loadInitialMasterData() {
    const productsRes = await apiCall('products');
    const locationsRes = await apiCall('locations');
    const suppliersRes = await apiCall('suppliers');
    const customersRes = await apiCall('customers');

    if (productsRes) STATE.products = productsRes.data;
    if (locationsRes) STATE.locations = locationsRes.data;
    if (suppliersRes) STATE.suppliers = suppliersRes.data;
    if (customersRes) STATE.customers = customersRes.data;

    // Populasikan filter
    const filterLoc = document.getElementById('filter-location');
    if (filterLoc) {
        filterLoc.innerHTML = '<option value="">Semua Lokasi</option>';
        STATE.locations.forEach(loc => {
            filterLoc.innerHTML += `<option value="${loc.id}">${loc.name}</option>`;
        });
    }

    // Populasi pilihan gudang alokasi status order admin
    const statusLocSelect = document.getElementById('order_status_location');
    if (statusLocSelect) {
        statusLocSelect.innerHTML = '<option value="">Pilih Lokasi Sumber Stok</option>';
        STATE.locations.forEach(loc => {
            statusLocSelect.innerHTML += `<option value="${loc.id}">${loc.name}</option>`;
        });
    }
}

// 8. TOAST NOTIFICATIONS
function showToast(message, type = 'success') {
    const container = document.getElementById('toast-container');
    const toast = document.createElement('div');
    toast.className = `toast ${type}`;
    
    let icon = 'info';
    if (type === 'success') icon = 'check-circle';
    if (type === 'error') icon = 'alert-triangle';
    if (type === 'warning') icon = 'alert-circle';
    
    toast.innerHTML = `
        <i data-lucide="${icon}"></i>
        <span>${message}</span>
    `;
    
    container.appendChild(toast);
    lucide.createIcons();

    setTimeout(() => {
        toast.style.animation = 'slideIn 0.3s cubic-bezier(0.16, 1, 0.3, 1) reverse forwards';
        setTimeout(() => toast.remove(), 300);
    }, 4500);
}

function openModal(modalId) {
    document.getElementById(modalId).classList.add('active');
}

function closeModal() {
    document.querySelectorAll('.modal-overlay').forEach(el => el.classList.remove('active'));
}

// 9. DASHBOARD LOADER
async function loadDashboardData() {
    const res = await apiCall('dashboard');
    if (!res) return;

    document.getElementById('stat-total-products').innerText = res.summary.total_products;
    document.getElementById('stat-total-stock').innerText = Math.round(res.summary.total_stock);
    document.getElementById('stat-total-locations').innerText = res.summary.total_locations;
    document.getElementById('stat-low-stock-count').innerText = res.summary.low_stock_count;

    // Low Stock Alert
    const lowStockTable = document.getElementById('table-low-stock-body');
    lowStockTable.innerHTML = '';
    if (res.low_stock_items.length === 0) {
        lowStockTable.innerHTML = `<tr><td colspan="4" style="text-align:center;" class="text-muted">Semua stok aman.</td></tr>`;
    } else {
        res.low_stock_items.forEach(item => {
            lowStockTable.innerHTML += `
                <tr>
                    <td><strong>${item.code}</strong></td>
                    <td>${item.name}</td>
                    <td><span class="badge opname">${item.location_name}</span></td>
                    <td><span class="text-danger" style="font-weight:700;">${Math.round(item.qty)}</span> ${item.unit}</td>
                </tr>
            `;
        });
    }

    // Recent Transactions
    const recentTable = document.getElementById('table-recent-body');
    recentTable.innerHTML = '';
    if (res.recent_transactions.length === 0) {
        recentTable.innerHTML = `<tr><td colspan="5" style="text-align:center;" class="text-muted">Belum ada transaksi.</td></tr>`;
    } else {
        res.recent_transactions.forEach(tx => {
            let badgeClass = getTransactionBadgeClass(tx.transaction_type);
            let flowDetails = getTransactionFlowDetails(tx);
            
            recentTable.innerHTML += `
                <tr>
                    <td><strong>${tx.reference_no}</strong></td>
                    <td><span class="badge ${badgeClass}">${tx.transaction_type}</span></td>
                    <td>${formatDateTime(tx.transaction_date)}</td>
                    <td><small>${flowDetails}</small></td>
                    <td><strong>${Math.round(tx.total_qty)}</strong> item</td>
                </tr>
            `;
        });
    }

    initCharts(res.charts);
}

function initCharts(chartsData) {
    const isDark = document.documentElement.getAttribute('data-theme') !== 'light';
    const textColor = isDark ? '#94a3b8' : '#475569';
    const gridColor = isDark ? 'rgba(255, 255, 255, 0.05)' : 'rgba(15, 23, 42, 0.05)';

    const ctxTrend = document.getElementById('chart-trend').getContext('2d');
    if (STATE.charts.trend) STATE.charts.trend.destroy();
    
    STATE.charts.trend = new Chart(ctxTrend, {
        type: 'bar',
        data: {
            labels: chartsData.trend_months,
            datasets: [
                {
                    label: 'Pembelian',
                    data: chartsData.purchase_trend,
                    backgroundColor: 'rgba(16, 185, 129, 0.8)',
                    borderRadius: 6
                },
                {
                    label: 'Penjualan',
                    data: chartsData.sale_trend,
                    backgroundColor: 'rgba(99, 102, 241, 0.8)',
                    borderRadius: 6
                }
            ]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { labels: { color: textColor, font: { family: 'Plus Jakarta Sans' } } }
            },
            scales: {
                x: { grid: { color: gridColor }, ticks: { color: textColor } },
                y: { 
                    grid: { color: gridColor }, 
                    ticks: { 
                        color: textColor,
                        callback: (value) => 'Rp ' + value.toLocaleString('id-ID')
                    } 
                }
            }
        }
    });

    const ctxLoc = document.getElementById('chart-locations').getContext('2d');
    if (STATE.charts.location) STATE.charts.location.destroy();
    
    const locNames = chartsData.stock_per_location.map(l => l.location_name);
    const locQtys = chartsData.stock_per_location.map(l => parseFloat(l.total_qty));

    STATE.charts.location = new Chart(ctxLoc, {
        type: 'doughnut',
        data: {
            labels: locNames,
            datasets: [{
                data: locQtys,
                backgroundColor: [
                    'rgba(99, 102, 241, 0.8)',
                    'rgba(16, 185, 129, 0.8)',
                    'rgba(245, 158, 11, 0.8)',
                    'rgba(14, 165, 233, 0.8)'
                ],
                borderWidth: 0
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { 
                    position: 'bottom',
                    labels: { color: textColor, font: { family: 'Plus Jakarta Sans' } } 
                }
            }
        }
    });
}

// 10. STOCK RINGKASAN
async function loadStockSummaryData() {
    const res = await apiCall('stock_summary');
    if (!res) return;

    STATE.stockSummary = res;

    const headerRow = document.getElementById('stock-table-header');
    headerRow.innerHTML = `
        <th>Kode Produk</th>
        <th>Nama Produk</th>
        <th>Kategori</th>
        <th>Satuan</th>
    `;
    res.locations.forEach(loc => {
        headerRow.innerHTML += `<th style="text-align: right;">${loc.name}</th>`;
    });
    headerRow.innerHTML += `<th style="text-align: right; background-color: var(--bg-tertiary);">Total Stok</th>`;

    const body = document.getElementById('table-stock-body');
    body.innerHTML = '';
    
    if (res.products.length === 0) {
        body.innerHTML = `<tr><td colspan="${5 + res.locations.length}" style="text-align:center;" class="text-muted">Tidak ada produk.</td></tr>`;
        return;
    }

    res.products.forEach(p => {
        let rowHtml = `
            <tr>
                <td><strong>${p.code}</strong></td>
                <td>${p.name}</td>
                <td><span class="badge opname">${p.category || 'Lainnya'}</span></td>
                <td>${p.unit}</td>
        `;
        res.locations.forEach(loc => {
            const qty = p.stock[loc.id] || 0;
            const textClass = qty === 0 ? 'text-muted' : (qty < 15 ? 'text-warning font-weight-bold' : '');
            rowHtml += `<td style="text-align: right;" class="${textClass}">${qty.toLocaleString('id-ID')}</td>`;
        });
        rowHtml += `<td style="text-align: right; font-weight: 700; background-color: var(--bg-tertiary);">${p.total_stock.toLocaleString('id-ID')}</td>`;
        rowHtml += `</tr>`;
        body.innerHTML += rowHtml;
    });
}

// 11. TRANSAKSI INTERNAL LOADER
async function loadTransactionsData() {
    const type = document.getElementById('filter-type').value;
    const locId = document.getElementById('filter-location').value;
    const start = document.getElementById('filter-date-start').value;
    const end = document.getElementById('filter-date-end').value;
    const search = document.getElementById('search-tx').value;

    let query = `type=${type}&location_id=${locId}&start_date=${start}&end_date=${end}&search=${encodeURIComponent(search)}`;
    const res = await apiCall(`transactions&${query}`);
    if (!res) return;

    STATE.transactions = res.data;

    const tbody = document.getElementById('table-transactions-body');
    tbody.innerHTML = '';

    if (res.data.length === 0) {
        tbody.innerHTML = `<tr><td colspan="6" style="text-align:center;" class="text-muted">Tidak ada transaksi.</td></tr>`;
        return;
    }

    res.data.forEach(tx => {
        let badgeClass = getTransactionBadgeClass(tx.transaction_type);
        let flowDetails = getTransactionFlowDetails(tx);
        
        tbody.innerHTML += `
            <tr>
                <td><strong>${tx.reference_no}</strong></td>
                <td><span class="badge ${badgeClass}">${tx.transaction_type}</span></td>
                <td>${formatDateTime(tx.transaction_date)}</td>
                <td><small>${flowDetails}</small></td>
                <td>${tx.notes || '-'}</td>
                <td>
                    <button class="btn-icon edit" onclick="viewTransactionDetail(${tx.id})" title="Detail Transaksi">
                        <i data-lucide="eye" style="width:16px;height:16px;"></i>
                    </button>
                </td>
            </tr>
        `;
    });
    lucide.createIcons();
}

// 12. DYNAMIC TRANSACTIONS FORM BINDINGS
function openCreateTransactionModal() {
    document.getElementById('form-transaction').reset();
    document.getElementById('tx_date').value = new Date().toISOString().split('T')[0];
    document.getElementById('tx-items-container').innerHTML = '';
    
    addTransactionItemRow();
    adjustTransactionFormFields();
    openModal('modal-transaction');
}

function adjustTransactionFormFields() {
    const type = document.getElementById('tx_type').value;
    
    const wrapperSource = document.getElementById('wrapper-source-location');
    const wrapperTarget = document.getElementById('wrapper-target-location');
    const wrapperSupplier = document.getElementById('wrapper-supplier');
    const wrapperCustomer = document.getElementById('wrapper-customer');
    
    const labelTarget = wrapperTarget.querySelector('label');
    const labelSource = wrapperSource.querySelector('label');

    // Populate dropdowns
    document.getElementById('tx_source_location').innerHTML = '<option value="">Pilih Lokasi Asal</option>';
    document.getElementById('tx_target_location').innerHTML = '<option value="">Pilih Lokasi Tujuan</option>';
    document.getElementById('tx_supplier').innerHTML = '<option value="">Pilih Supplier</option>';
    document.getElementById('tx_customer').innerHTML = '<option value="">Pilih Customer</option>';

    STATE.locations.forEach(loc => {
        document.getElementById('tx_source_location').innerHTML += `<option value="${loc.id}">${loc.name}</option>`;
        document.getElementById('tx_target_location').innerHTML += `<option value="${loc.id}">${loc.name}</option>`;
    });
    STATE.suppliers.forEach(sup => {
        document.getElementById('tx_supplier').innerHTML += `<option value="${sup.id}">${sup.name}</option>`;
    });
    STATE.customers.forEach(cust => {
        document.getElementById('tx_customer').innerHTML += `<option value="${cust.id}">${cust.name}</option>`;
    });

    wrapperSource.style.display = 'none';
    wrapperTarget.style.display = 'none';
    wrapperSupplier.style.display = 'none';
    wrapperCustomer.style.display = 'none';

    const priceHeader = document.getElementById('header-unit-price');
    priceHeader.style.display = (type === 'PURCHASE' || type === 'SALE') ? 'block' : 'none';

    if (type === 'OPNAME') {
        wrapperTarget.style.display = 'block';
        labelTarget.innerText = 'Lokasi Opname';
    } 
    else if (type === 'PURCHASE') {
        wrapperTarget.style.display = 'block';
        labelTarget.innerText = 'Lokasi Penerima (Masuk)';
        wrapperSupplier.style.display = 'block';
    } 
    else if (type === 'RETURN_PURCHASE') {
        wrapperSource.style.display = 'block';
        labelSource.innerText = 'Lokasi Pengirim (Keluar)';
        wrapperSupplier.style.display = 'block';
    } 
    else if (type === 'SALE') {
        wrapperSource.style.display = 'block';
        labelSource.innerText = 'Lokasi Gudang (Keluar)';
        wrapperCustomer.style.display = 'block';
    } 
    else if (type === 'RETURN_SALE') {
        wrapperTarget.style.display = 'block';
        labelTarget.innerText = 'Lokasi Gudang (Masuk)';
        wrapperCustomer.style.display = 'block';
    } 
    else if (type === 'TRANSFER') {
        wrapperSource.style.display = 'block';
        labelSource.innerText = 'Lokasi Asal (Gudang Pengirim)';
        wrapperTarget.style.display = 'block';
        labelTarget.innerText = 'Lokasi Tujuan (Gudang Penerima)';
    }

    document.querySelectorAll('.item-row').forEach(row => {
        const priceInput = row.querySelector('.tx-item-price');
        if (priceInput) {
            priceInput.style.display = (type === 'PURCHASE' || type === 'SALE') ? 'block' : 'none';
        }
    });
}

function addTransactionItemRow() {
    const container = document.getElementById('tx-items-container');
    const type = document.getElementById('tx_type').value;
    const isPriceVisible = (type === 'PURCHASE' || type === 'SALE') ? 'block' : 'none';
    
    const row = document.createElement('div');
    row.className = 'item-row';
    
    let productOptions = '<option value="">Pilih Barang</option>';
    STATE.products.forEach(p => {
        productOptions += `<option value="${p.id}" data-unit="${p.unit}">${p.code} - ${p.name}</option>`;
    });

    row.innerHTML = `
        <select class="form-group tx-item-product" required onchange="handleItemProductChange(this)">
            ${productOptions}
        </select>
        <div style="display:flex;align-items:center;gap:4px;">
            <input type="number" class="tx-item-qty" placeholder="Qty" step="any" min="0.0001" required style="width:100%;">
            <span class="item-unit-label text-muted" style="font-size:12px;min-width:30px;"></span>
        </div>
        <input type="number" class="tx-item-price" placeholder="Harga Satuan (Rp)" min="0" style="display:${isPriceVisible};width:100%;">
        <button type="button" class="btn-icon delete" onclick="this.parentElement.remove()" style="margin-bottom:18px;">
            <i data-lucide="trash-2" style="width:16px;height:16px;"></i>
        </button>
    `;
    
    container.appendChild(row);
    lucide.createIcons();
}

function handleItemProductChange(selectEl) {
    const selectedOption = selectEl.options[selectEl.selectedIndex];
    const unit = selectedOption.getAttribute('data-unit') || '';
    const row = selectEl.parentElement;
    row.querySelector('.item-unit-label').innerText = unit;
}

async function handleTransactionSubmit(e) {
    e.preventDefault();

    const type = document.getElementById('tx_type').value;
    const date = document.getElementById('tx_date').value;
    const sourceId = document.getElementById('tx_source_location').value;
    const targetId = document.getElementById('tx_target_location').value;
    const supplierId = document.getElementById('tx_supplier').value;
    const customerId = document.getElementById('tx_customer').value;
    const notes = document.getElementById('tx_notes').value;

    const items = [];
    const itemRows = document.querySelectorAll('#tx-items-container .item-row');
    
    if (itemRows.length === 0) {
        showToast('Minimal harus menginputkan 1 item barang!', 'warning');
        return;
    }

    let itemValid = true;
    itemRows.forEach(row => {
        const pId = row.querySelector('.tx-item-product').value;
        const qty = parseFloat(row.querySelector('.tx-item-qty').value);
        const price = parseFloat(row.querySelector('.tx-item-price').value) || 0;

        if (!pId || isNaN(qty) || qty <= 0) {
            itemValid = false;
            return;
        }

        items.push({
            product_id: pId,
            qty: qty,
            unit_price: price,
            notes: ''
        });
    });

    if (!itemValid) {
        showToast('Pastikan semua baris barang telah dipilih dengan jumlah yang valid!', 'warning');
        return;
    }

    const payload = {
        transaction_type: type,
        transaction_date: date + ' ' + new Date().toTimeString().split(' ')[0],
        source_location_id: sourceId,
        target_location_id: targetId,
        supplier_id: supplierId,
        customer_id: customerId,
        notes: notes,
        items: items
    };

    const res = await apiCall('create_transaction', 'POST', payload);
    if (res) {
        showToast(`Transaksi ${res.reference_no} berhasil disimpan!`, 'success');
        closeModal();
        switchTab(STATE.currentTab);
    }
}

async function viewTransactionDetail(txId) {
    const tx = STATE.transactions.find(t => t.id === txId);
    if (!tx) return;

    document.getElementById('detail-ref-no').innerText = tx.reference_no;
    
    const badgeClass = getTransactionBadgeClass(tx.transaction_type);
    document.getElementById('detail-type').innerHTML = `<span class="badge ${badgeClass}">${tx.transaction_type}</span>`;
    document.getElementById('detail-date').innerText = formatDateTime(tx.transaction_date);
    document.getElementById('detail-flow').innerHTML = getTransactionFlowDetails(tx);
    document.getElementById('detail-notes').innerText = tx.notes || '-';

    const tableBody = document.getElementById('table-detail-items-body');
    tableBody.innerHTML = '';

    let grandTotal = 0;
    tx.items.forEach(item => {
        let total = item.qty * item.unit_price;
        grandTotal += total;

        let formattedPrice = item.unit_price > 0 ? 'Rp ' + Math.round(item.unit_price).toLocaleString('id-ID') : '-';
        let formattedTotal = total > 0 ? 'Rp ' + Math.round(total).toLocaleString('id-ID') : '-';

        tableBody.innerHTML += `
            <tr>
                <td><strong>${item.product_code}</strong></td>
                <td>${item.product_name}</td>
                <td style="text-align: right;">${item.qty.toLocaleString('id-ID')} ${item.product_unit}</td>
                <td style="text-align: right;">${formattedPrice}</td>
                <td style="text-align: right;">${formattedTotal}</td>
            </tr>
        `;
    });

    const wrapperTotal = document.getElementById('detail-grand-total');
    if (grandTotal > 0) {
        wrapperTotal.style.display = 'block';
        wrapperTotal.innerHTML = `Total Nilai: <strong>Rp ${Math.round(grandTotal).toLocaleString('id-ID')}</strong>`;
    } else {
        wrapperTotal.style.display = 'none';
    }

    openModal('modal-detail');
}

// 13. DATA MASTER MANAGEMENT (CRUD WITH LOGIN CREDENTIALS)
function renderMasterDataTables() {
    // Products
    const prodBody = document.getElementById('master-products-body');
    prodBody.innerHTML = '';
    STATE.products.forEach(p => {
        prodBody.innerHTML += `
            <tr>
                <td><strong>${p.code}</strong></td>
                <td>${p.name}</td>
                <td><span class="badge opname">${p.category || '-'}</span></td>
                <td>${p.unit}</td>
                <td><small>${p.description || '-'}</small></td>
                <td>
                    <div class="action-buttons">
                        <button class="btn-icon edit" onclick="openEditMasterModal('product', ${p.id})" title="Edit"><i data-lucide="edit-3" style="width:14px;height:14px;"></i></button>
                        <button class="btn-icon delete" onclick="deleteMasterEntity('product', ${p.id})" title="Hapus"><i data-lucide="trash-2" style="width:14px;height:14px;"></i></button>
                    </div>
                </td>
            </tr>
        `;
    });

    // Locations
    const locBody = document.getElementById('master-locations-body');
    locBody.innerHTML = '';
    STATE.locations.forEach(l => {
        locBody.innerHTML += `
            <tr>
                <td>${l.id}</td>
                <td><strong>${l.name}</strong></td>
                <td>${formatDateTime(l.created_at)}</td>
                <td>
                    <div class="action-buttons">
                        <button class="btn-icon edit" onclick="openEditMasterModal('location', ${l.id})" title="Edit"><i data-lucide="edit-3" style="width:14px;height:14px;"></i></button>
                        <button class="btn-icon delete" onclick="deleteMasterEntity('location', ${l.id})" title="Hapus"><i data-lucide="trash-2" style="width:14px;height:14px;"></i></button>
                    </div>
                </td>
            </tr>
        `;
    });

    // Suppliers
    const supBody = document.getElementById('master-suppliers-body');
    supBody.innerHTML = '';
    STATE.suppliers.forEach(s => {
        supBody.innerHTML += `
            <tr>
                <td><strong>${s.name}</strong></td>
                <td>${s.phone || '-'}</td>
                <td><small>${s.address || '-'}</small></td>
                <td>
                    <div class="action-buttons">
                        <button class="btn-icon edit" onclick="openEditMasterModal('supplier', ${s.id})" title="Edit"><i data-lucide="edit-3" style="width:14px;height:14px;"></i></button>
                        <button class="btn-icon delete" onclick="deleteMasterEntity('supplier', ${s.id})" title="Hapus"><i data-lucide="trash-2" style="width:14px;height:14px;"></i></button>
                    </div>
                </td>
            </tr>
        `;
    });

    // Customers
    const custBody = document.getElementById('master-customers-body');
    custBody.innerHTML = '';
    STATE.customers.forEach(c => {
        const loginAccountText = c.username ? `<span class="badge status-tersedia" style="text-transform:lowercase;font-size:10px;">@${c.username}</span>` : '<span class="badge status-batal" style="font-size:9px;">Belum Ada Akun</span>';
        
        custBody.innerHTML += `
            <tr>
                <td><strong>${c.name}</strong></td>
                <td>${loginAccountText}</td>
                <td>${c.phone || '-'}</td>
                <td><small>${c.address || '-'}</small></td>
                <td>
                    <div class="action-buttons">
                        <button class="btn-icon edit" onclick="openEditMasterModal('customer', ${c.id})" title="Edit & Akun Login"><i data-lucide="user-cog" style="width:14px;height:14px;"></i></button>
                        <button class="btn-icon delete" onclick="deleteMasterEntity('customer', ${c.id})" title="Hapus"><i data-lucide="trash-2" style="width:14px;height:14px;"></i></button>
                    </div>
                </td>
            </tr>
        `;
    });
    
    lucide.createIcons();
}

function openAddMasterModal(entity) {
    document.getElementById('form-master').reset();
    document.getElementById('master-entity-type').value = entity;
    document.getElementById('master-id').value = '';
    document.getElementById('modal-master-title').innerText = `Tambah ${capitalizeFirstLetter(entity)}`;

    toggleMasterFormFields(entity);
    openModal('modal-master');
}

function openEditMasterModal(entity, id) {
    document.getElementById('form-master').reset();
    document.getElementById('master-entity-type').value = entity;
    document.getElementById('master-id').value = id;
    document.getElementById('modal-master-title').innerText = `Edit ${capitalizeFirstLetter(entity)}`;
    
    toggleMasterFormFields(entity);

    let item;
    if (entity === 'product') {
        item = STATE.products.find(p => p.id === id);
        document.getElementById('master_p_code').value = item.code;
        document.getElementById('master_p_name').value = item.name;
        document.getElementById('master_p_category').value = item.category || '';
        document.getElementById('master_p_unit').value = item.unit;
        document.getElementById('master_p_desc').value = item.description || '';
    } 
    else if (entity === 'location') {
        item = STATE.locations.find(l => l.id === id);
        document.getElementById('master_l_name').value = item.name;
    } 
    else if (entity === 'supplier') {
        item = STATE.suppliers.find(s => s.id === id);
        document.getElementById('master_s_name').value = item.name;
        document.getElementById('master_s_phone').value = item.phone || '';
        document.getElementById('master_s_address').value = item.address || '';
    } 
    else if (entity === 'customer') {
        item = STATE.customers.find(c => c.id === id);
        document.getElementById('master_c_name').value = item.name;
        document.getElementById('master_c_phone').value = item.phone || '';
        document.getElementById('master_c_address').value = item.address || '';
        
        // Pemuatan data login customer
        document.getElementById('master_c_username').value = item.username || '';
        document.getElementById('master_c_password').value = ''; // Kosongkan password
    }

    openModal('modal-master');
}

function toggleMasterFormFields(entity) {
    document.getElementById('master-fields-product').style.display = entity === 'product' ? 'block' : 'none';
    document.getElementById('master-fields-location').style.display = entity === 'location' ? 'block' : 'none';
    document.getElementById('master-fields-supplier').style.display = entity === 'supplier' ? 'block' : 'none';
    document.getElementById('master-fields-customer').style.display = entity === 'customer' ? 'block' : 'none';

    document.getElementById('master_p_code').required = entity === 'product';
    document.getElementById('master_p_name').required = entity === 'product';
    document.getElementById('master_p_unit').required = entity === 'product';
    document.getElementById('master_l_name').required = entity === 'location';
    document.getElementById('master_s_name').required = entity === 'supplier';
    document.getElementById('master_c_name').required = entity === 'customer';
}

async function handleMasterSubmit(e) {
    e.preventDefault();

    const entity = document.getElementById('master-entity-type').value;
    const id = document.getElementById('master-id').value;
    
    const data = { id: id ? parseInt(id) : null };
    
    if (entity === 'product') {
        data.code = document.getElementById('master_p_code').value;
        data.name = document.getElementById('master_p_name').value;
        data.category = document.getElementById('master_p_category').value;
        data.unit = document.getElementById('master_p_unit').value;
        data.description = document.getElementById('master_p_desc').value;
    } 
    else if (entity === 'location') {
        data.name = document.getElementById('master_l_name').value;
    } 
    else if (entity === 'supplier') {
        data.name = document.getElementById('master_s_name').value;
        data.phone = document.getElementById('master_s_phone').value;
        data.address = document.getElementById('master_s_address').value;
    } 
    else if (entity === 'customer') {
        data.name = document.getElementById('master_c_name').value;
        data.phone = document.getElementById('master_c_phone').value;
        data.address = document.getElementById('master_c_address').value;
        
        // Kredensial akun
        data.username = document.getElementById('master_c_username').value;
        data.password = document.getElementById('master_c_password').value;
    }

    const payload = { entity: entity, data: data };
    const res = await apiCall('save_entity', 'POST', payload);
    
    if (res) {
        showToast('Data master berhasil disimpan!', 'success');
        closeModal();
        await loadInitialMasterData();
        switchTab(STATE.currentTab);
    }
}

async function deleteMasterEntity(entity, id) {
    if (!confirm(`Apakah Anda yakin ingin menghapus data ${entity} ini? Semua transaksi terkait akan terpengaruh.`)) {
        return;
    }

    const payload = { entity: entity, id: id };
    const res = await apiCall('delete_entity', 'POST', payload);
    if (res) {
        showToast('Data master berhasil dihapus.', 'success');
        await loadInitialMasterData();
        switchTab(STATE.currentTab);
    }
}

// 14. CUSTOMER ORDER FLOWS
async function loadOrdersData() {
    const res = await apiCall('orders');
    if (!res) return;

    STATE.orders = res.data;

    const tbody = document.getElementById('table-orders-body');
    tbody.innerHTML = '';

    if (res.data.length === 0) {
        tbody.innerHTML = `<tr><td colspan="6" style="text-align:center;" class="text-muted">Belum ada daftar pesanan.</td></tr>`;
        return;
    }

    res.data.forEach(o => {
        let statusBadge = getOrderStatusBadge(o.status);
        let actionBtn = '';

        if (STATE.user.role === 'ADMIN') {
            // Admin can process orders
            if (o.status === 'DIPESAN') {
                actionBtn = `
                    <button class="btn-primary" onclick="openUpdateStatusModal(${o.id})" style="padding:4px 8px; font-size:11px; font-weight:600;">
                        Proses Order
                    </button>
                `;
            } else {
                actionBtn = `<span class="text-muted" style="font-size:12px;">Sudah Diproses</span>`;
            }
        }

        tbody.innerHTML += `
            <tr>
                <td><strong>${o.order_no}</strong></td>
                <td class="admin-only"><strong>${o.customer_name}</strong></td>
                <td>${formatDateTime(o.order_date)}</td>
                <td>${renderOrderedItemsList(o.items)}</td>
                <td>${statusBadge}</td>
                <td>
                    <div style="display:flex;align-items:center;gap:10px;">
                        ${actionBtn}
                        <button class="btn-icon edit" onclick="viewOrderDetail(${o.id})" title="Detail Order">
                            <i data-lucide="eye" style="width:14px;height:14px;"></i>
                        </button>
                    </div>
                </td>
            </tr>
        `;
    });
    
    // Sembunyikan kolom admin-only jika login customer
    if (STATE.user.role === 'CUSTOMER') {
        document.querySelectorAll('.admin-only').forEach(el => el.style.display = 'none');
    } else {
        document.querySelectorAll('.admin-only').forEach(el => el.style.display = '');
    }

    lucide.createIcons();
}

function renderOrderedItemsList(items) {
    return items.map(item => `&bull; ${item.product_name} (<strong>${Math.round(item.qty)}</strong> ${item.product_unit})`).join('<br>');
}

function getOrderStatusBadge(status) {
    switch (status) {
        case 'DIPESAN': return '<span class="badge status-dipesan">Dipesan (Belum Tersedia)</span>';
        case 'TERSEDIA': return '<span class="badge status-tersedia">Tersedia (Sudah Diserahkan)</span>';
        case 'BATAL': return '<span class="badge status-batal">Dibatalkan</span>';
        default: return '';
    }
}

// open customer order creation modal
function openCreateOrderModal() {
    document.getElementById('form-order').reset();
    document.getElementById('order_date').value = new Date().toISOString().split('T')[0];
    document.getElementById('order-items-container').innerHTML = '';
    
    addOrderItemRow();
    openModal('modal-order');
}

function addOrderItemRow() {
    const container = document.getElementById('order-items-container');
    const row = document.createElement('div');
    row.className = 'order-item-row';

    let productOptions = '<option value="">Pilih Barang</option>';
    STATE.products.forEach(p => {
        productOptions += `<option value="${p.id}" data-unit="${p.unit}">${p.code} - ${p.name}</option>`;
    });

    row.innerHTML = `
        <select class="form-group order-item-product" required onchange="handleItemProductChange(this)">
            ${productOptions}
        </select>
        <div style="display:flex;align-items:center;gap:4px;">
            <input type="number" class="order-item-qty" placeholder="Jumlah" step="any" min="0.0001" required style="width:100%;">
            <span class="item-unit-label text-muted" style="font-size:12px;min-width:30px;"></span>
        </div>
        <button type="button" class="btn-icon delete" onclick="this.parentElement.remove()" style="margin-bottom:18px;">
            <i data-lucide="trash-2" style="width:16px;height:16px;"></i>
        </button>
    `;
    
    container.appendChild(row);
    lucide.createIcons();
}

async function handleOrderSubmit(e) {
    e.preventDefault();
    const date = document.getElementById('order_date').value;
    const notes = document.getElementById('order_notes').value;

    const items = [];
    const itemRows = document.querySelectorAll('#order-items-container .order-item-row');

    if (itemRows.length === 0) {
        showToast('Minimal harus menginputkan 1 item pesanan!', 'warning');
        return;
    }

    let isValid = true;
    itemRows.forEach(row => {
        const pId = row.querySelector('.order-item-product').value;
        const qty = parseFloat(row.querySelector('.order-item-qty').value);

        if (!pId || isNaN(qty) || qty <= 0) {
            isValid = false;
            return;
        }

        items.push({ product_id: pId, qty: qty, notes: '' });
    });

    if (!isValid) {
        showToast('Pastikan semua baris barang telah dipilih dengan jumlah yang valid!', 'warning');
        return;
    }

    const payload = {
        transaction_date: date + ' ' + new Date().toTimeString().split(' ')[0],
        notes: notes,
        items: items
    };

    const res = await apiCall('create_order', 'POST', payload);
    if (res) {
        showToast(`Pesanan ${res.order_no} berhasil dikirim!`, 'success');
        closeModal();
        loadOrdersData(); // Reload orders list
    }
}

// view order detail in modal (receipt style)
function viewOrderDetail(orderId) {
    const o = STATE.orders.find(ord => ord.id === orderId);
    if (!o) return;

    document.getElementById('detail-order-no').innerText = o.order_no;
    document.getElementById('detail-order-status').innerHTML = getOrderStatusBadge(o.status);
    document.getElementById('detail-order-date').innerText = formatDateTime(o.order_date);
    document.getElementById('detail-order-cust').innerText = o.customer_name;
    document.getElementById('detail-order-notes').innerText = o.notes || '-';

    const tbody = document.getElementById('table-detail-order-items-body');
    tbody.innerHTML = '';

    o.items.forEach(item => {
        tbody.innerHTML += `
            <tr>
                <td><strong>${item.product_code}</strong></td>
                <td>${item.product_name}</td>
                <td style="text-align: right;">${item.qty.toLocaleString('id-ID')} ${item.product_unit}</td>
            </tr>
        `;
    });

    openModal('modal-detail-order');
}

// 15. ADMIN ORDER APPROVAL (STATUS CHANGE)
let activeProcessingOrderId = null;

function openUpdateStatusModal(orderId) {
    activeProcessingOrderId = orderId;
    const o = STATE.orders.find(ord => ord.id === orderId);
    if (!o) return;

    document.getElementById('status-order-no').innerText = o.order_no;
    document.getElementById('order_status_select').value = 'TERSEDIA';
    
    // Tampilkan dropdown gudang secara default karena default pilihan status = TERSEDIA
    document.getElementById('wrapper-order-status-location').style.display = 'block';
    document.getElementById('order_status_location').required = true;
    document.getElementById('order_status_location').value = '';

    openModal('modal-update-order-status');
}

async function handleOrderStatusSubmit(e) {
    e.preventDefault();
    
    const status = document.getElementById('order_status_select').value;
    const sourceLocId = document.getElementById('order_status_location').value;

    const payload = {
        order_id: activeProcessingOrderId,
        status: status,
        source_location_id: status === 'TERSEDIA' ? parseInt(sourceLocId) : null
    };

    const res = await apiCall('update_order_status', 'POST', payload);
    if (res) {
        showToast('Status pesanan berhasil diperbarui!', 'success');
        closeModal();
        loadOrdersData(); // Reload list
    }
}

// 16. EXPORT TRANSACTIONS TO CSV
function exportTransactionsToCSV() {
    if (STATE.transactions.length === 0) {
        showToast('Tidak ada data transaksi untuk diekspor.', 'warning');
        return;
    }

    let csvContent = "data:text/csv;charset=utf-8,";
    csvContent += "No. Referensi,Tipe Transaksi,Tanggal Transaksi,Alur Pengiriman,Keterangan,Item Barang,Qty,Harga Satuan (Rp),Total (Rp)\n";

    STATE.transactions.forEach(tx => {
        const ref = tx.reference_no;
        const type = tx.transaction_type;
        const date = formatDateTime(tx.transaction_date);
        const flow = getTransactionFlowDetails(tx).replace(/<[^>]*>/g, '').replace(/,/g, ';');
        const notes = (tx.notes || '-').replace(/,/g, ';').replace(/\n/g, ' ');

        tx.items.forEach(item => {
            const prodName = item.product_name.replace(/,/g, ';');
            const qty = item.qty;
            const price = Math.round(item.unit_price);
            const total = qty * price;

            csvContent += `"${ref}","${type}","${date}","${flow}","${notes}","${prodName} ${item.product_unit}",${qty},${price},${total}\n`;
        });
    });

    const encodedUri = encodeURI(csvContent);
    const link = document.createElement("a");
    link.setAttribute("href", encodedUri);
    link.setAttribute("download", `laporan_transaksi_stok_${new Date().toISOString().split('T')[0]}.csv`);
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
    showToast('Ekspor CSV berhasil diunduh.', 'success');
}

// --- UTILITY HELPER FUNCTIONS ---

function getTransactionBadgeClass(type) {
    switch (type) {
        case 'OPNAME': return 'opname';
        case 'PURCHASE': return 'purchase';
        case 'RETURN_PURCHASE': return 'return_purchase';
        case 'SALE': return 'sale';
        case 'RETURN_SALE': return 'return_sale';
        case 'TRANSFER': return 'transfer';
        default: return '';
    }
}

function getTransactionFlowDetails(tx) {
    switch (tx.transaction_type) {
        case 'OPNAME':
            return `Lokasi: <strong>${tx.target_location}</strong>`;
        case 'PURCHASE':
            return `Dari: <strong>${tx.supplier}</strong> &rarr; Ke: <strong>${tx.target_location}</strong>`;
        case 'RETURN_PURCHASE':
            return `Dari: <strong>${tx.source_location}</strong> &rarr; Ke Supplier: <strong>${tx.supplier}</strong>`;
        case 'SALE':
            return `Dari: <strong>${tx.source_location}</strong> &rarr; Ke Customer: <strong>${tx.customer}</strong>`;
        case 'RETURN_SALE':
            return `Dari Customer: <strong>${tx.customer}</strong> &rarr; Ke: <strong>${tx.target_location}</strong>`;
        case 'TRANSFER':
            return `Dari: <strong>${tx.source_location}</strong> &rarr; Ke: <strong>${tx.target_location}</strong>`;
        default:
            return '';
    }
}

function formatDateTime(dateStr) {
    if (!dateStr) return '-';
    const date = new Date(dateStr);
    if (isNaN(date.getTime())) return dateStr;
    
    const d = String(date.getDate()).padStart(2, '0');
    const m = String(date.getMonth() + 1).padStart(2, '0');
    const y = date.getFullYear();
    const h = String(date.getHours()).padStart(2, '0');
    const min = String(date.getMinutes()).padStart(2, '0');
    
    return `${d}/${m}/${y} ${h}:${min}`;
}

function capitalizeFirstLetter(string) {
    if (!string) return '';
    return string.charAt(0).toUpperCase() + string.slice(1);
}
