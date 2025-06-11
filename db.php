<?php
require_once 'config.php';
// Shared SQLite database connection and initialization
$dbFile = PRIVATE_DIR_PATH.'/data/invoices.db';
$initDb = !file_exists($dbFile);
// Ensure data directory exists
if (!file_exists(PRIVATE_DIR_PATH.'/data')) {
    mkdir(PRIVATE_DIR_PATH.'/data', 0755, true);
}
$pdo = new PDO('sqlite:' . $dbFile);
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

if ($initDb) {
    // Create all necessary tables for a fresh installation
    $pdo->exec(<<<'SQL'
CREATE TABLE customers (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    name TEXT,
    email TEXT,
    company TEXT,
    regon_krs_number TEXT,
    regon_number TEXT,
    id_number TEXT,
    vat_number TEXT,
    agreement TEXT,
    website TEXT,
    address TEXT,
    city TEXT,
    postal_code TEXT,
    country TEXT,
    phone TEXT,
    currency TEXT,
    logo TEXT
);
CREATE TABLE contracts (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    customer_id INTEGER NOT NULL,
    company_id INTEGER NOT NULL,
    date TEXT NOT NULL,
    end_date TEXT,
    name TEXT NOT NULL,
    description TEXT,
    FOREIGN KEY(customer_id) REFERENCES customers(id),
    FOREIGN KEY(company_id) REFERENCES companies(id)
);

CREATE TABLE contract_files (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    contract_id INTEGER NOT NULL,
    filename TEXT NOT NULL,
    data TEXT,
    FOREIGN KEY(contract_id) REFERENCES contracts(id)
);

CREATE TABLE invoices (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    customer_id INTEGER,
    company_id INTEGER,
    invoice_number TEXT,
    date TEXT,
    month_service TEXT,
    status TEXT,
    template TEXT,
    currency TEXT,
    vat_rate REAL,
    items TEXT,
    company_details TEXT,
    subtotal REAL,
    tax REAL,
    total REAL,
    FOREIGN KEY(customer_id) REFERENCES customers(id),
    FOREIGN KEY(company_id) REFERENCES companies(id)
);

CREATE TABLE companies (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    name TEXT,
    company TEXT,
    id_number TEXT,
    regon_krs_number TEXT,
    regon_number TEXT,
    vat_number TEXT,
    website TEXT,
    email TEXT,
    phone TEXT,
    address TEXT,
    city TEXT,
    postal_code TEXT,
    country TEXT,
    bank_name TEXT,
    bank_account TEXT,
    bank_code TEXT,
    currency TEXT NOT NULL DEFAULT 'USD',
    logo TEXT
);
CREATE TABLE services (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    description TEXT,
    unit_price REAL,
    currency TEXT NOT NULL DEFAULT 'USD'
);

CREATE TABLE currencies (
    code TEXT PRIMARY KEY
);

CREATE TABLE customer_prefixes (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    customer_id INTEGER NOT NULL,
    entity TEXT NOT NULL,
    property TEXT NOT NULL,
    prefix TEXT NOT NULL,
    FOREIGN KEY(customer_id) REFERENCES customers(id)
);

CREATE TABLE users (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    username TEXT UNIQUE NOT NULL,
    password TEXT NOT NULL,
    disabled INTEGER NOT NULL DEFAULT 0,
    is_admin INTEGER NOT NULL DEFAULT 0
);

CREATE TABLE pko_payments (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    received_at TEXT NOT NULL,
    account_number TEXT,
    transaction_date TEXT,
    amount REAL,
    currency TEXT,
    sender TEXT,
    title TEXT,
    processed_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
    customer_id INTEGER
);

CREATE UNIQUE INDEX IF NOT EXISTS pko_payments_unique_idx
    ON pko_payments (transaction_date, amount, sender, title);

CREATE TABLE user_customers (
    user_id INTEGER NOT NULL,
    customer_id INTEGER NOT NULL,
    PRIMARY KEY (user_id, customer_id),
    FOREIGN KEY(user_id) REFERENCES users(id),
    FOREIGN KEY(customer_id) REFERENCES customers(id)
);

CREATE TABLE auth_sessions (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    token TEXT UNIQUE NOT NULL,
    user_id INTEGER NOT NULL,
    created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
    created_ip TEXT NOT NULL,
    created_user_agent TEXT NOT NULL,
    last_used_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
    last_used_ip TEXT NOT NULL,
    last_used_user_agent TEXT NOT NULL,
    expires_at TEXT NOT NULL
);
SQL
    );

    // Seed default currencies
    $stmt = $pdo->prepare('INSERT INTO currencies (code) VALUES (?)');
    foreach (['USD', 'EUR', 'PLN'] as $c) {
        $stmt->execute([$c]);
    }

    // Seed default admin user
    $hash = password_hash(DEFAULT_ADMIN_PASSWORD, PASSWORD_DEFAULT);
    $stmt = $pdo->prepare('INSERT INTO users (username, password, disabled, is_admin) VALUES (?, ?, 0, 1)');
    $stmt->execute([DEFAULT_ADMIN_USERNAME, $hash]);
}

// Migrate existing database: add default_invoice_emails and default_invoice_template columns if not present
$columns = $pdo->query("PRAGMA table_info(customers)")->fetchAll(PDO::FETCH_ASSOC);
$colNames = array_column($columns, 'name');
if (!in_array('default_invoice_emails', $colNames, true)) {
    $pdo->exec("ALTER TABLE customers ADD COLUMN default_invoice_emails TEXT");
}
if (!in_array('default_invoice_template', $colNames, true)) {
    $pdo->exec("ALTER TABLE customers ADD COLUMN default_invoice_template TEXT");
}
$columns = $pdo->query("PRAGMA table_info(customers)")->fetchAll(PDO::FETCH_ASSOC);
$colNames = array_column($columns, 'name');
// Migrate customers: add payment_unique_string column and unique index for same-currency uniqueness
if (!in_array('payment_unique_string', $colNames, true)) {
    $pdo->exec("ALTER TABLE customers ADD COLUMN payment_unique_string TEXT");
}
$indexInfo = $pdo->query("SELECT name FROM sqlite_master WHERE type='index' AND name='customers_payment_unique_string_idx'")->fetchColumn();
if (!$indexInfo) {
    $pdo->exec("CREATE UNIQUE INDEX customers_payment_unique_string_idx ON customers (payment_unique_string, currency)");
}

// Migrate invoices: add contract_id column if not present
$columns = $pdo->query("PRAGMA table_info(invoices)")->fetchAll(PDO::FETCH_ASSOC);
$colNames = array_column($columns, 'name');
if (!in_array('contract_id', $colNames, true)) {
    $pdo->exec("ALTER TABLE invoices ADD COLUMN contract_id INTEGER");
}

// Migrate contracts table if not present
$tables = $pdo->query("SELECT name FROM sqlite_master WHERE type='table'")->fetchAll(PDO::FETCH_COLUMN);
if (!in_array('contracts', $tables, true)) {
    $pdo->exec(<<<'SQL'
CREATE TABLE contracts (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    customer_id INTEGER NOT NULL,
    company_id INTEGER NOT NULL,
    date TEXT NOT NULL,
    end_date TEXT,
    name TEXT NOT NULL,
    description TEXT,
    FOREIGN KEY(customer_id) REFERENCES customers(id),
    FOREIGN KEY(company_id) REFERENCES companies(id)
);
SQL
    );
}

// Migrate contract_files table if not present
if (!in_array('contract_files', $tables, true)) {
    $pdo->exec(<<<'SQL'
CREATE TABLE contract_files (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    contract_id INTEGER NOT NULL,
    filename TEXT NOT NULL,
    data TEXT,
    FOREIGN KEY(contract_id) REFERENCES contracts(id)
);
SQL
    );
}

// Migrate change log for paid/unpaid status and payment association events
if (!in_array('change_log', $tables, true)) {
    $pdo->exec(<<<'SQL'
CREATE TABLE change_log (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    change_date TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
    event_type TEXT NOT NULL,
    invoice_id INTEGER,
    payment_id INTEGER,
    prev_value TEXT,
    new_value TEXT,
    reason TEXT,
    balance_before REAL,
    balance_after REAL,
    user_id INTEGER,
    ip_address TEXT,
    FOREIGN KEY(invoice_id) REFERENCES invoices(id),
    FOREIGN KEY(payment_id) REFERENCES pko_payments(id),
    FOREIGN KEY(user_id) REFERENCES users(id)
);
SQL
    );
}
