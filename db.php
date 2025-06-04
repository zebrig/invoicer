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