<?php
/**
 * Global configuration for the application.
 *
 * Defines constants for database location and default admin credentials.
*/

$defaultRoot = PHP_SAPI === 'cli' ? __DIR__ : $_SERVER['DOCUMENT_ROOT'];
$defaultPrivate = $defaultRoot . '/../private';

define('ROOT_DIR_PATH', getenv('ROOT_DIR_PATH') ?: $defaultRoot);
define('PRIVATE_DIR_PATH', getenv('PRIVATE_DIR_PATH') ?: $defaultPrivate);

$localConfig = PRIVATE_DIR_PATH . '/config.local.php';
if (!file_exists($localConfig)) {
    if (!file_exists(PRIVATE_DIR_PATH)) {
        mkdir(PRIVATE_DIR_PATH, 0755, true);
    }
    file_put_contents(
        $localConfig,
        "<?php\n// Local configuration overrides - see README.md for instructions\n\n//define('DEFAULT_ADMIN_USERNAME', 'admin');\n//define('DEFAULT_ADMIN_PASSWORD', 'admin');\n// Microsoft Graph / Azure OAuth2 settings for email fetching\n//define('AZURE_TENANT_ID', ‘tenant-id’); // Azure Directory (tenant) ID\n//define('AZURE_CLIENT_ID', ‘client-id’); // Azure Application (client) ID\n//define('AZURE_CLIENT_SECRET', ‘secret’); // Azure Client Secret value\n//define('AZURE_SHARED_MAILBOX_EMAIL', ‘mail@box.com‘); // Shared mailbox email address (UserPrincipalName)\n//define('AZURE_REDIRECT_URI', 'https://your.domain/fetch_pko_messages.php'); // OAuth Redirect URI for email scripts\n//define('AZURE_TOKEN_FILE', __DIR__ . '/microsoft_graph_token.json'); // file to store OAuth tokens\n//define('AZURE_MAIL_FOLDER', ‘mailfolder’); // Mail folder to fetch messages from"
    );
}
require_once $localConfig;

// Path to SQLite database file.
define('DB_FILE', PRIVATE_DIR_PATH.'/data/invoices.db');

// Default admin user credentials (for initial DB seed and resets).
if (!defined('DEFAULT_ADMIN_USERNAME')) {
    define('DEFAULT_ADMIN_USERNAME', getenv('DEFAULT_ADMIN_USERNAME') ?: 'admin');
}
if (!defined('DEFAULT_ADMIN_PASSWORD')) {
    define('DEFAULT_ADMIN_PASSWORD', getenv('DEFAULT_ADMIN_PASSWORD') ?: 'admin');
}

// Microsoft Graph / Azure OAuth2 settings for email fetching (can override in private/config.local.php or via environment variables)
if (!defined('AZURE_TENANT_ID')) {
    define('AZURE_TENANT_ID', getenv('AZURE_TENANT_ID') ?: 'your-tenant-id');
}
if (!defined('AZURE_CLIENT_ID')) {
    define('AZURE_CLIENT_ID', getenv('AZURE_CLIENT_ID') ?: 'your-client-id');
}
if (!defined('AZURE_CLIENT_SECRET')) {
    define('AZURE_CLIENT_SECRET', getenv('AZURE_CLIENT_SECRET') ?: 'your-client-secret');
}
if (!defined('AZURE_SHARED_MAILBOX_EMAIL')) {
    define('AZURE_SHARED_MAILBOX_EMAIL', getenv('AZURE_SHARED_MAILBOX_EMAIL') ?: 'shared-mailbox@example.com');
}
if (!defined('AZURE_REDIRECT_URI')) {
    define('AZURE_REDIRECT_URI', getenv('AZURE_REDIRECT_URI') ?: 'https://your-domain/fetch_pko_messages.php');
}
if (!defined('AZURE_TOKEN_FILE')) {
    define('AZURE_TOKEN_FILE', getenv('AZURE_TOKEN_FILE') ?: PRIVATE_DIR_PATH . '/microsoft_graph_token.json');
}
if (!defined('AZURE_MAIL_FOLDER')) {
    define('AZURE_MAIL_FOLDER', getenv('AZURE_MAIL_FOLDER') ?: 'folder-name');
}
