<!DOCTYPE html>
<html lang="ar" dir="rtl">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <meta name="theme-color" content="#10b981">
    <meta name="description" content="Islamic Sales - Offline Sales Tracking">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <meta name="apple-mobile-web-app-title" content="حلقات اللبص🪒">

    <link rel="manifest" href="/islam-pwa/manifest.json">
    <link rel="icon" type="image/png" href="/images/logo2.png">
    <link rel="apple-touch-icon" href="/images/logo2.png">

    <title>حلقات اللبص🪒</title>

    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        :root {
            --primary: #10b981;
            --primary-dark: #059669;
            --secondary: #6366f1;
            --danger: #ef4444;
            --warning: #f59e0b;
            --bg: #f8fafc;
            --card: #ffffff;
            --text: #1e293b;
            --text-light: #64748b;
            --border: #e2e8f0;
            --shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Arial, sans-serif;
            background: var(--bg);
            color: var(--text);
            min-height: 100vh;
            padding-bottom: 80px;
        }

        .header {
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
            color: white;
            padding: 20px;
            text-align: center;
            position: sticky;
            top: 0;
            z-index: 100;
            box-shadow: var(--shadow);
        }

        .header h1 {
            font-size: 1.5rem;
            margin-bottom: 5px;
        }

        .header .date-display {
            font-size: 0.9rem;
            opacity: 0.9;
        }

        .container {
            max-width: 800px;
            margin: 0 auto;
            padding: 20px;
        }

        .section {
            background: var(--card);
            border-radius: 16px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: var(--shadow);
        }

        .customer-input {
            width: 100%;
            padding: 16px;
            font-size: 1.1rem;
            border: 2px solid var(--border);
            border-radius: 12px;
            outline: none;
            background: var(--bg);
        }

        .customer-input:focus {
            border-color: var(--primary);
        }

        .note-input {
            width: 100%;
            padding: 12px;
            font-size: 0.95rem;
            border: 2px solid var(--border);
            border-radius: 12px;
            margin-top: 12px;
            outline: none;
            background: var(--bg);
        }

        .note-input:focus {
            border-color: var(--secondary);
        }

        .section-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 16px;
        }

        .section-title {
            font-size: 1.1rem;
            font-weight: 600;
        }

        .add-btn {
            background: var(--primary);
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 10px;
            font-size: 0.9rem;
            cursor: pointer;
        }

        .shortcuts-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 10px;
        }

        .shortcut-btn {
            background: linear-gradient(135deg, var(--secondary), #4f46e5);
            color: white;
            border: none;
            padding: 20px 10px;
            border-radius: 12px;
            font-size: 1.2rem;
            font-weight: 700;
            cursor: pointer;
            position: relative;
        }

        .shortcut-btn:hover {
            transform: scale(1.05);
        }

        .shortcut-btn:active {
            transform: scale(0.95);
        }

        .shortcut-btn .delete-btn {
            position: absolute;
            top: -8px;
            right: -8px;
            width: 22px;
            height: 22px;
            background: var(--danger);
            border: 2px solid white;
            border-radius: 50%;
            color: white;
            font-size: 14px;
            cursor: pointer;
            display: none;
            line-height: 1;
        }

        .shortcut-btn:hover .delete-btn {
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .price-input-row {
            display: flex;
            gap: 10px;
            margin-top: 15px;
            padding-top: 15px;
            border-top: 1px solid var(--border);
        }

        .price-input {
            flex: 1;
            padding: 12px;
            border: 2px solid var(--border);
            border-radius: 12px;
            font-size: 1rem;
            text-align: center;
            background: var(--bg);
        }

        .price-input:focus {
            border-color: var(--primary);
            outline: none;
        }

        .add-price-btn {
            background: var(--primary);
            color: white;
            border: none;
            padding: 12px 24px;
            border-radius: 12px;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
        }

        .date-picker-row {
            display: flex;
            align-items: center;
            gap: 12px;
            margin-bottom: 16px;
        }

        .date-picker {
            flex: 1;
            padding: 12px;
            border: 2px solid var(--border);
            border-radius: 12px;
            font-size: 1rem;
            background: var(--bg);
        }

        .total-display {
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
            color: white;
            padding: 12px 20px;
            border-radius: 12px;
            font-weight: 700;
        }

        .table-wrapper {
            overflow-x: auto;
        }

        .transactions-table {
            width: 100%;
            border-collapse: collapse;
        }

        .transactions-table th,
        .transactions-table td {
            padding: 12px;
            text-align: right;
            border-bottom: 1px solid var(--border);
        }

        .transactions-table th {
            background: var(--bg);
            font-weight: 600;
            color: var(--text-light);
            font-size: 0.85rem;
        }

        .transactions-table .price {
            font-weight: 700;
            color: var(--primary);
            font-size: 1.1rem;
        }

        .delete-row {
            background: none;
            border: none;
            color: var(--danger);
            cursor: pointer;
            padding: 8px;
        }

        .empty-state {
            text-align: center;
            padding: 40px;
            color: var(--text-light);
        }

        .modal-overlay {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0, 0, 0, 0.5);
            display: none;
            align-items: center;
            justify-content: center;
            z-index: 2000;
            padding: 20px;
        }

        .modal-overlay.show {
            display: flex;
        }

        .modal {
            background: var(--card);
            border-radius: 20px;
            padding: 24px;
            width: 100%;
            max-width: 400px;
        }

        .modal h3 {
            text-align: center;
            margin-bottom: 20px;
        }

        .modal-input {
            width: 100%;
            padding: 14px;
            border: 2px solid var(--border);
            border-radius: 12px;
            font-size: 1.1rem;
            text-align: center;
            margin-bottom: 16px;
        }

        .modal-buttons {
            display: flex;
            gap: 12px;
        }

        .modal-btn {
            flex: 1;
            padding: 14px;
            border-radius: 12px;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            border: none;
        }

        .modal-btn.cancel {
            background: var(--bg);
            border: 2px solid var(--border);
        }

        .modal-btn.confirm {
            background: var(--primary);
            color: white;
        }

        .modal-btn.danger {
            background: var(--danger);
            color: white;
        }

        .toast {
            position: fixed;
            bottom: 100px;
            left: 50%;
            transform: translateX(-50%);
            background: var(--text);
            color: white;
            padding: 14px 24px;
            border-radius: 12px;
            font-size: 0.95rem;
            opacity: 0;
            transition: opacity 0.3s;
            z-index: 3000;
        }

        .toast.show {
            opacity: 1;
        }

        .status-badge {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 0.8rem;
            margin-top: 10px;
        }

        .status-badge.offline {
            background: rgba(239, 68, 68, 0.1);
            color: var(--danger);
        }

        .status-badge.online {
            background: rgba(16, 185, 129, 0.1);
            color: var(--primary);
        }

        .autocomplete-list {
            position: absolute;
            top: 100%;
            left: 0;
            right: 0;
            background: var(--card);
            border-radius: 12px;
            box-shadow: var(--shadow);
            max-height: 200px;
            overflow-y: auto;
            display: none;
            z-index: 1000;
            margin-top: 5px;
        }

        .autocomplete-list.show {
            display: block;
        }

        .autocomplete-item {
            padding: 12px 16px;
            cursor: pointer;
            border-bottom: 1px solid var(--border);
        }

        .autocomplete-item:last-child {
            border-bottom: none;
        }

        .autocomplete-item:hover {
            background: var(--bg);
        }

        /* Print Styles */
        .print-section {
            margin-top: 15px;
            padding-top: 15px;
            border-top: 1px solid var(--border);
        }

        .print-header {
            font-size: 1rem;
            font-weight: 600;
            margin-bottom: 12px;
            color: var(--text);
        }

        .date-range-row {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 12px;
            flex-wrap: wrap;
        }

        .date-range-row input[type="date"] {
            flex: 1;
            min-width: 140px;
            padding: 10px;
            border: 2px solid var(--border);
            border-radius: 10px;
            font-size: 0.9rem;
            background: var(--bg);
        }

        .date-separator {
            color: var(--text-light);
            font-weight: 500;
        }

        .print-btn {
            background: var(--secondary);
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 10px;
            font-size: 0.9rem;
            font-weight: 600;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .print-btn:hover {
            background: #4f46e5;
        }

        /* Print Modal */
        .print-modal-overlay {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0, 0, 0, 0.7);
            display: none;
            align-items: center;
            justify-content: center;
            z-index: 3000;
            padding: 20px;
        }

        .print-modal-overlay.show {
            display: flex;
        }

        .print-content {
            background: white;
            border-radius: 16px;
            padding: 30px;
            width: 100%;
            max-width: 800px;
            max-height: 90vh;
            overflow-y: auto;
        }

        .print-close {
            position: fixed;
            top: 20px;
            right: 20px;
            background: var(--danger);
            color: white;
            border: none;
            width: 40px;
            height: 40px;
            border-radius: 50%;
            font-size: 24px;
            cursor: pointer;
            z-index: 3001;
            display: none;
        }

        .print-modal-overlay.show .print-close {
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .print-title {
            text-align: center;
            font-size: 1.5rem;
            font-weight: 700;
            margin-bottom: 10px;
            color: var(--text);
        }

        .print-date-range {
            text-align: center;
            color: var(--text-light);
            margin-bottom: 20px;
            font-size: 0.95rem;
        }

        .print-total {
            background: var(--bg);
            padding: 15px 20px;
            border-radius: 12px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-top: 20px;
            font-size: 1.2rem;
            font-weight: 700;
        }

        .print-total .label {
            color: var(--text);
        }

        .print-total .amount {
            color: var(--primary);
        }

        @media print {
            body * {
                visibility: hidden;
            }

            .print-content,
            .print-content * {
                visibility: visible;
            }

            .print-content {
                position: absolute;
                left: 0;
                top: 0;
                width: 100%;
                max-width: none;
                max-height: none;
                padding: 20px;
            }

            .print-close {
                display: none !important;
            }
        }

        @media (max-width: 480px) {
            .shortcuts-grid {
                grid-template-columns: repeat(4, 1fr);
            }

            .shortcut-btn {
                padding: 16px 8px;
                font-size: 1rem;
            }
        }
    </style>
</head>

<body>
    <header class="header">
        <h1>مبيعات إسلامية</h1>
        <div class="date-display" id="currentDate"></div>
        <div class="status-badge offline" id="connectionStatus">
            <span id="statusText">غير متصل</span>
        </div>
    </header>

    <main class="container">
        <section class="section">
            <input type="text" class="customer-input" id="customerInput" placeholder="اسم العميل..."
                autocomplete="off">
            <div class="autocomplete-list" id="autocompleteList"></div>
            <input type="text" class="note-input" id="noteInput" placeholder="ملاحظة (اختياري)..."
                autocomplete="off">
        </section>

        <section class="section">
            <div class="section-header">
                <h2 class="section-title">أزرار shortcuts</h2>
                <button class="add-btn" id="addShortcutBtn">+ إضافة</button>
            </div>
            <div class="shortcuts-grid" id="shortcutsGrid">
                <div class="empty-state">لا توجد أزرار</div>
            </div>
            <div class="price-input-row">
                <input type="number" class="price-input" id="priceInput" placeholder="أدخل سعر يدوياً...">
                <button class="add-price-btn" id="addPriceBtn">إضافة</button>
            </div>
        </section>

        <section class="section">
            <div class="section-header">
                <h2 class="section-title">المبيعات</h2>
            </div>
            <div class="date-picker-row">
                <input type="date" class="date-picker" id="datePicker">
                <div class="total-display" id="totalDisplay">المجموع: 0</div>
            </div>
            <div class="print-section">
                <div class="print-header">طباعة المبيعات</div>
                <div class="date-range-row">
                    <input type="date" id="printFromDate">
                    <span class="date-separator">إلى</span>
                    <input type="date" id="printToDate">
                    <button class="print-btn" id="printBtn">🖨 طباعة</button>
                </div>
            </div>
            <div class="table-wrapper" id="transactionsTable">
                <div class="empty-state">لا توجد مبيعات لهذا اليوم</div>
            </div>
        </section>
    </main>

    <!-- Add Shortcut Modal -->
    <div class="modal-overlay" id="shortcutModal">
        <div class="modal">
            <h3>إضافة رقم جديد</h3>
            <input type="number" class="modal-input" id="shortcutValue" placeholder="أدخل الرقم...">
            <div class="modal-buttons">
                <button class="modal-btn cancel" id="cancelShortcut">إلغاء</button>
                <button class="modal-btn confirm" id="confirmShortcut">إضافة</button>
            </div>
        </div>
    </div>

    <!-- Delete Confirmation Modal -->
    <div class="modal-overlay" id="deleteModal">
        <div class="modal">
            <h3>تأكيد الحذف</h3>
            <p style="text-align: center; margin-bottom: 20px; color: var(--text-light);">
                هل أنت متأكد من حذف هذه المعاملة؟
            </p>
            <div class="modal-buttons">
                <button class="modal-btn cancel" id="cancelDelete">إلغاء</button>
                <button class="modal-btn danger" id="confirmDelete">حذف</button>
            </div>
        </div>
    </div>

    <div class="toast" id="toast"></div>

    <!-- Print Modal -->
    <div class="print-modal-overlay" id="printModal">
        <button class="print-close" id="printClose">×</button>
        <div class="print-content" id="printContent"></div>
    </div>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/sql.js/1.10.3/sql-wasm.js"></script>
    <script>
        (function() {
            'use strict';

            // Configuration
            var DB_CONFIG = {
                wasmPath: 'https://cdnjs.cloudflare.com/ajax/libs/sql.js/1.10.3/',
                fileName: 'islamic-sales.db'
            };

            // State
            var db = null;
            var shortcutButtons = [];
            var transactions = [];
            var customers = [];
            var currentDate = new Date().toISOString().split('T')[0];
            var deferredInstallPrompt = null;
            var SQL = null;
            var pendingDeleteId = null;

            // DOM Elements
            var elements = {};

            function getElement(id) {
                if (!elements[id]) {
                    elements[id] = document.getElementById(id);
                }
                return elements[id];
            }

            // ==================== Database Functions ====================
            async function initDatabase() {
                try {
                    SQL = await initSqlJs({
                        locateFile: function(file) {
                            return DB_CONFIG.wasmPath + file;
                        }
                    });

                    var savedDb = await loadDbFromIndexedDB();

                    if (savedDb) {
                        db = new SQL.Database(savedDb);
                    } else {
                        db = new SQL.Database();
                        createTables();
                    }

                    console.log('Database initialized');
                } catch (error) {
                    console.error('Failed to initialize database:', error);
                    showToast('فشل في تحميل قاعدة البيانات');
                }
            }

            function createTables() {
                db.run(
                    'CREATE TABLE IF NOT EXISTS customers (id INTEGER PRIMARY KEY AUTOINCREMENT, name TEXT NOT NULL UNIQUE, created_at DATETIME DEFAULT CURRENT_TIMESTAMP)'
                );
                db.run(
                    'CREATE TABLE IF NOT EXISTS shortcut_buttons (id INTEGER PRIMARY KEY AUTOINCREMENT, value REAL NOT NULL UNIQUE, created_at DATETIME DEFAULT CURRENT_TIMESTAMP)'
                );
                db.run(
                    'CREATE TABLE IF NOT EXISTS transactions (id INTEGER PRIMARY KEY AUTOINCREMENT, customer_name TEXT NOT NULL, price REAL NOT NULL, note TEXT, transaction_date DATE NOT NULL, created_at DATETIME DEFAULT CURRENT_TIMESTAMP)'
                );
                saveDbToIndexedDB();
            }

            function loadDbFromIndexedDB() {
                return new Promise(function(resolve) {
                    try {
                        var request = indexedDB.open('IslamicSalesDB', 1);

                        request.onerror = function() {
                            resolve(null);
                        };

                        request.onsuccess = function(event) {
                            var idb = event.target.result;
                            var transaction = idb.transaction(['database'], 'readonly');
                            var store = transaction.objectStore('database');
                            var getRequest = store.get(DB_CONFIG.fileName);

                            getRequest.onsuccess = function() {
                                if (getRequest.result) {
                                    resolve(new Uint8Array(getRequest.result));
                                } else {
                                    resolve(null);
                                }
                            };
                            getRequest.onerror = function() {
                                resolve(null);
                            };
                        };

                        request.onupgradeneeded = function(event) {
                            var idb = event.target.result;
                            if (!idb.objectStoreNames.contains('database')) {
                                idb.createObjectStore('database');
                            }
                        };
                    } catch (e) {
                        resolve(null);
                    }
                });
            }

            function saveDbToIndexedDB() {
                if (!db) return;

                try {
                    var data = db.export();
                    var request = indexedDB.open('IslamicSalesDB', 1);

                    request.onsuccess = function(event) {
                        var idb = event.target.result;
                        var transaction = idb.transaction(['database'], 'readwrite');
                        var store = transaction.objectStore('database');
                        store.put(data, DB_CONFIG.fileName);
                    };
                } catch (e) {
                    console.error('Failed to save database:', e);
                }
            }

            // ==================== Customer Functions ====================
            async function loadCustomers() {
                try {
                    var result = db.exec('SELECT DISTINCT customer_name FROM transactions ORDER BY customer_name');
                    customers = result.length > 0 ? result[0].values.map(function(row) {
                        return row[0];
                    }) : [];
                } catch (error) {
                    console.error('Failed to load customers:', error);
                    customers = [];
                }
            }

            function searchCustomers(query) {
                if (!query.trim()) return [];

                var lowerQuery = query.toLowerCase();
                return customers.filter(function(name) {
                    return name.toLowerCase().includes(lowerQuery);
                });
            }

            function showAutocomplete(items) {
                var list = getElement('autocompleteList');
                list.innerHTML = '';

                if (items.length === 0) {
                    list.classList.remove('show');
                    return;
                }

                items.forEach(function(item) {
                    var div = document.createElement('div');
                    div.className = 'autocomplete-item';
                    div.textContent = item;
                    div.addEventListener('click', function() {
                        getElement('customerInput').value = item;
                        list.classList.remove('show');
                    });
                    list.appendChild(div);
                });

                list.classList.add('show');
            }

            // ==================== Shortcut Functions ====================
            async function loadShortcutButtons() {
                try {
                    var result = db.exec('SELECT * FROM shortcut_buttons ORDER BY value ASC');
                    shortcutButtons = result.length > 0 ?
                        result[0].values.map(function(row) {
                            return {
                                id: row[0],
                                value: row[1]
                            };
                        }) : [];
                    renderShortcutButtons();
                } catch (error) {
                    console.error('Failed to load shortcuts:', error);
                }
            }

            function renderShortcutButtons() {
                var grid = getElement('shortcutsGrid');

                if (shortcutButtons.length === 0) {
                    grid.innerHTML = '<div class="empty-state">لا توجد أزرار</div>';
                    return;
                }

                var html = '';
                shortcutButtons.forEach(function(btn) {
                    html += '<button class="shortcut-btn" data-value="' + btn.value + '">';
                    html += '<span class="price-label">' + btn.value + '</span>';
                    html += '<span class="delete-btn" data-id="' + btn.id + '">×</span>';
                    html += '</button>';
                });
                grid.innerHTML = html;

                // Add click handlers
                grid.querySelectorAll('.shortcut-btn').forEach(function(btn) {
                    btn.addEventListener('click', function(e) {
                        if (e.target.classList.contains('delete-btn')) return;
                        handleShortcutClick(parseFloat(btn.dataset.value));
                    });
                });

                grid.querySelectorAll('.delete-btn').forEach(function(btn) {
                    btn.addEventListener('click', function(e) {
                        e.stopPropagation();
                        deleteShortcut(parseInt(btn.dataset.id));
                    });
                });
            }

            async function addShortcut(value) {
                try {
                    var stmt = db.prepare('INSERT INTO shortcut_buttons (value) VALUES (?)');
                    stmt.run([value]);
                    stmt.free();

                    saveDbToIndexedDB();
                    await loadShortcutButtons();
                    showToast('تم إضافة الرقم بنجاح');
                } catch (error) {
                    if (error.message.indexOf('UNIQUE constraint') !== -1) {
                        showToast('هذا الرقم موجود مسبقاً');
                    } else {
                        showToast('فشل في إضافة الرقم');
                    }
                }
            }

            async function deleteShortcut(id) {
                try {
                    var stmt = db.prepare('DELETE FROM shortcut_buttons WHERE id = ?');
                    stmt.run([id]);
                    stmt.free();

                    saveDbToIndexedDB();
                    await loadShortcutButtons();
                    showToast('تم حذف الرقم');
                } catch (error) {
                    showToast('فشل في حذف الرقم');
                }
            }

            function handleShortcutClick(value) {
                var customerName = getElement('customerInput').value.trim();
                var note = getElement('noteInput').value.trim();

                if (!customerName) {
                    showToast('الرجاء إدخال اسم العميل');
                    getElement('customerInput').focus();
                    return;
                }

                addTransaction(customerName, value, note);
            }

            // ==================== Transaction Functions ====================
            async function loadTransactions() {
                try {
                    var result = db.exec(
                        'SELECT * FROM transactions WHERE transaction_date = ? ORDER BY created_at DESC', [
                            currentDate
                        ]);
                    transactions = result.length > 0 ?
                        result[0].values.map(function(row) {
                            return {
                                id: row[0],
                                customer_name: row[1],
                                price: row[2],
                                note: row[3],
                                transaction_date: row[4],
                                created_at: row[5]
                            };
                        }) : [];

                    renderTransactions();
                } catch (error) {
                    console.error('Failed to load transactions:', error);
                }
            }

            function renderTransactions() {
                var table = getElement('transactionsTable');
                var totalDisplay = getElement('totalDisplay');

                var total = transactions.reduce(function(sum, t) {
                    return sum + t.price;
                }, 0);
                totalDisplay.textContent = 'المجموع: ' + total;

                if (transactions.length === 0) {
                    table.innerHTML = '<div class="empty-state">لا توجد مبيعات لهذا اليوم</div>';
                    return;
                }

                var html =
                    '<table class="transactions-table"><thead><tr><th>العميل</th><th>السعر</th><th>ملاحظة</th><th>الوقت</th><th></th></tr></thead><tbody>';

                transactions.forEach(function(t) {
                    html += '<tr>';
                    html += '<td>' + t.customer_name + '</td>';
                    html += '<td class="price">' + t.price + '</td>';
                    html += '<td>' + (t.note || '-') + '</td>';
                    html += '<td>' + formatTime(t.created_at) + '</td>';
                    html += '<td><button class="delete-row" data-id="' + t.id + '">🗑</button></td>';
                    html += '</tr>';
                });

                html += '</tbody></table>';
                table.innerHTML = html;

                table.querySelectorAll('.delete-row').forEach(function(btn) {
                    btn.addEventListener('click', function() {
                        pendingDeleteId = parseInt(btn.dataset.id);
                        getElement('deleteModal').classList.add('show');
                    });
                });
            }

            async function addTransaction(customerName, price, note) {
                try {
                    var stmt = db.prepare(
                        'INSERT INTO transactions (customer_name, price, note, transaction_date) VALUES (?, ?, ?, ?)'
                    );
                    stmt.run([customerName, price, note, currentDate]);
                    stmt.free();

                    saveDbToIndexedDB();
                    await loadTransactions();
                    await loadCustomers();

                    getElement('noteInput').value = '';
                    showToast('تم إضافة ' + customerName + ' - ' + price);
                } catch (error) {
                    console.error('Failed to add transaction:', error);
                    showToast('فشل في إضافة المعاملة');
                }
            }

            async function confirmDeleteTransaction() {
                if (!pendingDeleteId) return;

                try {
                    var stmt = db.prepare('DELETE FROM transactions WHERE id = ?');
                    stmt.run([pendingDeleteId]);
                    stmt.free();

                    saveDbToIndexedDB();
                    await loadTransactions();
                    showToast('تم حذف المعاملة');
                } catch (error) {
                    showToast('فشل في حذف المعاملة');
                }

                pendingDeleteId = null;
                getElement('deleteModal').classList.remove('show');
            }

            // ==================== Utility Functions ====================
            function updateDateDisplay() {
                var date = new Date(currentDate + 'T00:00:00');
                var options = {
                    weekday: 'long',
                    year: 'numeric',
                    month: 'long',
                    day: 'numeric'
                };
                getElement('currentDate').textContent = date.toLocaleDateString('ar-EG', options);
            }

            function formatTime(dateString) {
                var date = new Date(dateString);
                return date.toLocaleTimeString('ar-EG', {
                    hour: '2-digit',
                    minute: '2-digit'
                });
            }

            function updateConnectionStatus() {
                var status = getElement('connectionStatus');
                var text = getElement('statusText');

                if (navigator.onLine) {
                    status.className = 'status-badge online';
                    text.textContent = 'متصل';
                } else {
                    status.className = 'status-badge offline';
                    text.textContent = 'غير متصل - وضع دون إنترنت';
                }
            }

            function showToast(message) {
                var toast = getElement('toast');
                toast.textContent = message;
                toast.classList.add('show');
                setTimeout(function() {
                    toast.classList.remove('show');
                }, 3000);
            }

            // ==================== PWA Functions ====================
            async function savePageForOffline() {
                try {
                    var response = await fetch('/islam');
                    var html = await response.text();
                    localStorage.setItem('islam-page-html', html);
                    localStorage.setItem('islam-page-timestamp', Date.now());
                    console.log('Page saved for offline use');
                } catch (error) {
                    console.error('Failed to save page for offline:', error);
                }
            }

            function loadPageFromOffline() {
                var html = localStorage.getItem('islam-page-html');
                if (html) {
                    console.log('Page available for offline use');
                    return true;
                }
                return false;
            }

            // ==================== Print Functions ====================
            function generatePrintReport(fromDate, toDate) {
                try {
                    var result = db.exec(
                        'SELECT * FROM transactions WHERE transaction_date >= ? AND transaction_date <= ? ORDER BY transaction_date ASC, created_at ASC',
                        [fromDate, toDate]
                    );

                    var printTransactions = result.length > 0 ?
                        result[0].values.map(function(row) {
                            return {
                                id: row[0],
                                customer_name: row[1],
                                price: row[2],
                                note: row[3],
                                transaction_date: row[4],
                                created_at: row[5]
                            };
                        }) : [];

                    var total = printTransactions.reduce(function(sum, t) {
                        return sum + t.price;
                    }, 0);

                    // Format dates
                    var fromFormatted = formatDateDisplay(fromDate);
                    var toFormatted = formatDateDisplay(toDate);

                    // Build HTML
                    var html = '<div class="print-title">تقرير المبيعات</div>';
                    html += '<div class="print-date-range">من ' + fromFormatted + ' إلى ' + toFormatted + '</div>';
                    html +=
                        '<table class="transactions-table" style="width: 100%; border-collapse: collapse; margin-top: 20px;">';
                    html += '<thead><tr>';
                    html +=
                        '<th style="padding: 10px; border-bottom: 2px solid #e2e8f0; text-align: right;">التاريخ</th>';
                    html +=
                        '<th style="padding: 10px; border-bottom: 2px solid #e2e8f0; text-align: right;">العميل</th>';
                    html +=
                        '<th style="padding: 10px; border-bottom: 2px solid #e2e8f0; text-align: right;">ملاحظة</th>';
                    html +=
                        '<th style="padding: 10px; border-bottom: 2px solid #e2e8f0; text-align: right;">السعر</th>';
                    html += '</tr></thead><tbody>';

                    if (printTransactions.length === 0) {
                        html +=
                            '<tr><td colspan="4" style="padding: 20px; text-align: center; color: #64748b;">لا توجد مبيعات في هذه الفترة</td></tr>';
                    } else {
                        printTransactions.forEach(function(t) {
                            html += '<tr>';
                            html += '<td style="padding: 10px; border-bottom: 1px solid #e2e8f0;">' +
                                formatDateDisplay(t.transaction_date) + '</td>';
                            html += '<td style="padding: 10px; border-bottom: 1px solid #e2e8f0;">' + t
                                .customer_name + '</td>';
                            html += '<td style="padding: 10px; border-bottom: 1px solid #e2e8f0;">' + (t.note ||
                                '-') + '</td>';
                            html +=
                                '<td style="padding: 10px; border-bottom: 1px solid #e2e8f0; font-weight: bold; color: #10b981;">' +
                                t.price + '</td>';
                            html += '</tr>';
                        });
                    }

                    html += '</tbody></table>';
                    html += '<div class="print-total"><span class="label">المجموع الكلي:</span><span class="amount">' +
                        total + '</span></div>';

                    // Show print modal
                    getElement('printContent').innerHTML = html;
                    getElement('printModal').classList.add('show');

                    // Print after modal is shown
                    setTimeout(function() {
                        window.print();
                    }, 300);

                } catch (error) {
                    console.error('Failed to generate print report:', error);
                    showToast('فشل في إنشاء التقرير');
                }
            }

            function formatDateDisplay(dateString) {
                var date = new Date(dateString + 'T00:00:00');
                var options = {
                    year: 'numeric',
                    month: 'long',
                    day: 'numeric'
                };
                return date.toLocaleDateString('ar-EG', options);
            }

            function setupPWA() {
                // Register Service Worker
                if ('serviceWorker' in navigator) {
                    navigator.serviceWorker.register('/islam-pwa/sw.js').then(function(registration) {
                        console.log('Service Worker registered:', registration);
                    }).catch(function(error) {
                        console.log('Service Worker registration failed:', error);
                    });
                }

                // Handle install prompt
                window.addEventListener('beforeinstallprompt', function(e) {
                    e.preventDefault();
                    deferredInstallPrompt = e;
                    setTimeout(function() {
                        var prompt = document.getElementById('installPrompt');
                        if (prompt) prompt.classList.add('show');
                    }, 30000);
                });

                // Handle install button
                var installBtn = document.getElementById('installBtn');
                if (installBtn) {
                    installBtn.addEventListener('click', function() {
                        if (deferredInstallPrompt) {
                            deferredInstallPrompt.prompt();
                            deferredInstallPrompt.userChoice.then(function(outcome) {
                                console.log('Install outcome:', outcome);
                                deferredInstallPrompt = null;
                                var prompt = document.getElementById('installPrompt');
                                if (prompt) prompt.classList.remove('show');
                            });
                        }
                    });
                }

                // Hide install prompt on install
                window.addEventListener('appinstalled', function() {
                    deferredInstallPrompt = null;
                    var prompt = document.getElementById('installPrompt');
                    if (prompt) prompt.classList.remove('show');
                });
            }

            // ==================== Event Listeners ====================
            function setupEventListeners() {
                var customerInput = getElement('customerInput');

                customerInput.addEventListener('input', function() {
                    showAutocomplete(searchCustomers(customerInput.value));
                });

                customerInput.addEventListener('focus', function() {
                    showAutocomplete(searchCustomers(customerInput.value));
                });

                customerInput.addEventListener('blur', function() {
                    setTimeout(function() {
                        getElement('autocompleteList').classList.remove('show');
                    }, 200);
                });

                var datePicker = getElement('datePicker');
                datePicker.value = currentDate;
                datePicker.addEventListener('change', function(e) {
                    currentDate = e.target.value;
                    updateDateDisplay();
                    loadTransactions();
                });

                // Add shortcut modal
                getElement('addShortcutBtn').addEventListener('click', function() {
                    getElement('shortcutModal').classList.add('show');
                    getElement('shortcutValue').focus();
                });

                getElement('cancelShortcut').addEventListener('click', function() {
                    getElement('shortcutModal').classList.remove('show');
                    getElement('shortcutValue').value = '';
                });

                getElement('confirmShortcut').addEventListener('click', function() {
                    var value = parseFloat(getElement('shortcutValue').value);
                    if (!isNaN(value) && value > 0) {
                        addShortcut(value);
                        getElement('shortcutModal').classList.remove('show');
                        getElement('shortcutValue').value = '';
                    } else {
                        showToast('الرجاء إدخال رقم صحيح');
                    }
                });

                getElement('shortcutValue').addEventListener('keypress', function(e) {
                    if (e.key === 'Enter') {
                        getElement('confirmShortcut').click();
                    }
                });

                getElement('shortcutModal').addEventListener('click', function(e) {
                    if (e.target.classList.contains('modal-overlay')) {
                        getElement('shortcutModal').classList.remove('show');
                        getElement('shortcutValue').value = '';
                    }
                });

                // Delete modal
                getElement('cancelDelete').addEventListener('click', function() {
                    pendingDeleteId = null;
                    getElement('deleteModal').classList.remove('show');
                });

                getElement('confirmDelete').addEventListener('click', function() {
                    confirmDeleteTransaction();
                });

                getElement('deleteModal').addEventListener('click', function(e) {
                    if (e.target.classList.contains('modal-overlay')) {
                        pendingDeleteId = null;
                        getElement('deleteModal').classList.remove('show');
                    }
                });

                // Manual price input
                getElement('addPriceBtn').addEventListener('click', function() {
                    var priceValue = parseFloat(getElement('priceInput').value);
                    if (!isNaN(priceValue) && priceValue > 0) {
                        handleShortcutClick(priceValue);
                        getElement('priceInput').value = '';
                    } else {
                        showToast('الرجاء إدخال سعر صحيح');
                    }
                });

                getElement('priceInput').addEventListener('keypress', function(e) {
                    if (e.key === 'Enter') {
                        getElement('addPriceBtn').click();
                    }
                });

                window.addEventListener('online', updateConnectionStatus);
                window.addEventListener('offline', updateConnectionStatus);

                // Print functionality
                getElement('printBtn').addEventListener('click', function() {
                    var fromDate = getElement('printFromDate').value;
                    var toDate = getElement('printToDate').value;

                    if (!fromDate || !toDate) {
                        showToast('الرجاء اختيار تاريخ البداية والنهاية');
                        return;
                    }

                    generatePrintReport(fromDate, toDate);
                });

                getElement('printClose').addEventListener('click', function() {
                    getElement('printModal').classList.remove('show');
                });

                getElement('printModal').addEventListener('click', function(e) {
                    if (e.target.classList.contains('print-modal-overlay')) {
                        getElement('printModal').classList.remove('show');
                    }
                });
            }

            // ==================== Initialize Application ====================
            async function init() {
                updateConnectionStatus();

                // Load saved page HTML for offline use
                await savePageForOffline();

                // Initialize database
                await initDatabase();

                // Setup event listeners
                setupEventListeners();

                // Update date display
                updateDateDisplay();

                // Initialize print date pickers
                var today = new Date().toISOString().split('T')[0];
                var weekAgo = new Date();
                weekAgo.setDate(weekAgo.getDate() - 7);
                var weekAgoStr = weekAgo.toISOString().split('T')[0];
                getElement('printFromDate').value = weekAgoStr;
                getElement('printToDate').value = today;

                // Load data from database
                await loadShortcutButtons();
                await loadCustomers();
                await loadTransactions();

                // Setup PWA
                setupPWA();

                // Save initial data for offline use
                await savePageForOffline();
            }

            // Start application when DOM is ready
            if (document.readyState === 'loading') {
                document.addEventListener('DOMContentLoaded', init);
            } else {
                init();
            }
        })();
    </script>
</body>

</html>
