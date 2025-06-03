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
    // Create customers and invoices tables
    $pdo->exec("
        CREATE TABLE customers (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            name TEXT,
            email TEXT,
            company TEXT,
            id_number TEXT,
            vat_number TEXT,
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
            status TEXT,
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
            id_number TEXT,
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
    ");
}

$info = $pdo->query("PRAGMA table_info(customers)")->fetchAll(PDO::FETCH_COLUMN, 1);
if (!in_array('id_number', $info, true)) {
    $pdo->exec("ALTER TABLE customers ADD COLUMN id_number TEXT");
}
if (!in_array('vat_number', $info, true)) {
    $pdo->exec("ALTER TABLE customers ADD COLUMN vat_number TEXT");
}
if (!in_array('website', $info, true)) {
    $pdo->exec("ALTER TABLE customers ADD COLUMN website TEXT");
}
if (!in_array('currency', $info, true)) {
    $pdo->exec("ALTER TABLE customers ADD COLUMN currency TEXT NOT NULL DEFAULT 'USD'");
}
if (!in_array('logo', $info, true)) {
    $pdo->exec("ALTER TABLE customers ADD COLUMN logo TEXT");
}

// Ensure companies table has currency and logo columns
$info2 = $pdo->query("PRAGMA table_info(companies)")->fetchAll(PDO::FETCH_COLUMN, 1);
if (empty($info2)) {
    $pdo->exec("CREATE TABLE companies (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        name TEXT,
        id_number TEXT,
        vat_number TEXT,
        website TEXT,
        email TEXT,
        phone TEXT,
        address TEXT,
        city TEXT,
        postal_code TEXT,
        country TEXT,
        currency TEXT NOT NULL DEFAULT 'USD',
        logo TEXT
    )");
}
if (!in_array('currency', $info2, true)) {
    $pdo->exec("ALTER TABLE companies ADD COLUMN currency TEXT NOT NULL DEFAULT 'USD'");
}
if (!in_array('logo', $info2, true)) {
    $pdo->exec("ALTER TABLE companies ADD COLUMN logo TEXT");

}
if (!in_array('bank_name', $info2, true)) {
    $pdo->exec("ALTER TABLE companies ADD COLUMN bank_name TEXT");
}
if (!in_array('bank_account', $info2, true)) {
    $pdo->exec("ALTER TABLE companies ADD COLUMN bank_account TEXT");
}
if (!in_array('bank_code', $info2, true)) {
    $pdo->exec("ALTER TABLE companies ADD COLUMN bank_code TEXT");
}

$info3 = $pdo->query("PRAGMA table_info(services)")->fetchAll(PDO::FETCH_COLUMN, 1);
if (empty($info3)) {
    $pdo->exec("CREATE TABLE services (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        description TEXT,
        unit_price REAL,
        currency TEXT NOT NULL DEFAULT 'USD'
    )");
}
if (!in_array('currency', $info3, true)) {
    $pdo->exec("ALTER TABLE services ADD COLUMN currency TEXT NOT NULL DEFAULT 'USD'");
}

// Ensure currencies table exists for managing available currency codes
$infoCurrencies = $pdo->query("SELECT name FROM sqlite_master WHERE type='table' AND name='currencies'")->fetch();
if (!$infoCurrencies) {
    $pdo->exec("CREATE TABLE currencies (
        code TEXT PRIMARY KEY
    )");
    $stmt = $pdo->prepare("INSERT INTO currencies (code) VALUES (?)");
    foreach (['USD','EUR','PLN'] as $c) {
        $stmt->execute([$c]);
    }
}

// Ensure invoices table has company_id and month_service columns
$infoInv = $pdo->query("PRAGMA table_info(invoices)")->fetchAll(PDO::FETCH_COLUMN, 1);
if (!in_array('company_id', $infoInv, true)) {
    $pdo->exec("ALTER TABLE invoices ADD COLUMN company_id INTEGER");
}
if (!in_array('month_service', $infoInv, true)) {
    $pdo->exec("ALTER TABLE invoices ADD COLUMN month_service TEXT");
}

$info4 = $pdo->query("PRAGMA table_info(users)")->fetchAll(PDO::FETCH_COLUMN, 1);
if (empty($info4)) {
    $pdo->exec("CREATE TABLE users (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        username TEXT UNIQUE NOT NULL,
        password TEXT NOT NULL,
        disabled INTEGER NOT NULL DEFAULT 0,
        is_admin INTEGER NOT NULL DEFAULT 0
    )");
    $hash = password_hash(DEFAULT_ADMIN_PASSWORD, PASSWORD_DEFAULT);
    $stmt = $pdo->prepare('INSERT INTO users (username, password, disabled, is_admin) VALUES (?, ?, 0, 1)');
    $stmt->execute([DEFAULT_ADMIN_USERNAME, $hash]);
} elseif (!in_array('is_admin', $info4, true)) {
    // Add is_admin column and mark default admin account
    $pdo->exec("ALTER TABLE users ADD COLUMN is_admin INTEGER NOT NULL DEFAULT 0");
    $stmt = $pdo->prepare('UPDATE users SET is_admin = 1 WHERE username = ?');
    $stmt->execute([DEFAULT_ADMIN_USERNAME]);
}

$infoPayments = $pdo->query("PRAGMA table_info(pko_payments)")->fetchAll(PDO::FETCH_COLUMN, 1);
// Drop balance_after column if present (SQLite requires recreate)
if (in_array('balance_after', $infoPayments, true)) {
    $pdo->exec('PRAGMA foreign_keys=off');
    $pdo->exec('BEGIN TRANSACTION');
    $pdo->exec(<<<'SQL'
CREATE TABLE pko_payments_new (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    received_at TEXT NOT NULL,
    account_number TEXT,
    transaction_date TEXT,
    amount REAL,
    currency TEXT,
    sender TEXT,
    title TEXT,
    processed_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
);
CREATE UNIQUE INDEX IF NOT EXISTS pko_payments_unique_idx ON pko_payments_new(
    transaction_date, amount, sender, title
);
SQL
    );
    $pdo->exec("INSERT INTO pko_payments_new (id, received_at, account_number, transaction_date, amount, currency, sender, title, processed_at) SELECT id, received_at, account_number, transaction_date, amount, currency, sender, title, processed_at FROM pko_payments");
    $pdo->exec('DROP TABLE pko_payments');
    $pdo->exec('ALTER TABLE pko_payments_new RENAME TO pko_payments');
    $pdo->exec('COMMIT');
    $pdo->exec('PRAGMA foreign_keys=on');
    $infoPayments = $pdo->query("PRAGMA table_info(pko_payments)")->fetchAll(PDO::FETCH_COLUMN, 1);
}
if (empty($infoPayments)) {
    $pdo->exec(<<<'SQL'
CREATE TABLE IF NOT EXISTS pko_payments (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    received_at TEXT NOT NULL,
    account_number TEXT,
    transaction_date TEXT,
    amount REAL,
    currency TEXT,
    sender TEXT,
    title TEXT,
    processed_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
);
CREATE UNIQUE INDEX IF NOT EXISTS pko_payments_unique_idx ON pko_payments(
    transaction_date, amount, sender, title
);
SQL
    );
    $infoPayments = $pdo->query("PRAGMA table_info(pko_payments)")->fetchAll(PDO::FETCH_COLUMN, 1);
}
if (!in_array('customer_id', $infoPayments, true)) {
    $pdo->exec("ALTER TABLE pko_payments ADD COLUMN customer_id INTEGER");
}
// Ensure user_customers table exists
$infoUC = $pdo->query("PRAGMA table_info(user_customers)")->fetchAll(PDO::FETCH_COLUMN, 1);
if (empty($infoUC)) {
    $pdo->exec("CREATE TABLE user_customers (
        user_id INTEGER NOT NULL,
        customer_id INTEGER NOT NULL,
        PRIMARY KEY (user_id, customer_id),
        FOREIGN KEY(user_id) REFERENCES users(id),
        FOREIGN KEY(customer_id) REFERENCES customers(id)
    )");
}

$infoSessions = $pdo->query("PRAGMA table_info(auth_sessions)")->fetchAll(PDO::FETCH_COLUMN, 1);
if (empty($infoSessions)) {
    $pdo->exec(<<<'SQL'
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
}