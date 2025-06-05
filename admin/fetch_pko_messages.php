#!/usr/bin/env php
<?php
if (php_sapi_name() !== 'cli') {
    require_once __DIR__.'/../auth.php';
    if (!is_admin()) {
        http_response_code(403);
        exit('Forbidden');
    }
}
/**
 * Fetch last 10 messages from the default Inbox folder via Microsoft Graph
 * and process each payment notification in place.
 *
 * Requires:
 *   - config.php must define Azure/Graph OAuth2 constants:
 *       AZURE_TENANT_ID, AZURE_CLIENT_ID, AZURE_CLIENT_SECRET,
 *       AZURE_SHARED_MAILBOX_EMAIL, AZURE_REDIRECT_URI,
 *       AZURE_TOKEN_FILE, AZURE_MAIL_FOLDER.
 *   - Composer dependencies installed (league/oauth2-client, the-networg/oauth2-azure, guzzlehttp/guzzle).
 *
 * Usage:
 *   php fetch_pko_messages.php
 */

require ROOT_DIR_PATH.'/vendor/autoload.php';
require ROOT_DIR_PATH.'/config.php';
require_once ROOT_DIR_PATH . '/db.php';

// Ensure STDERR is defined for error output (so fwrite(STDERR...) works in web & CLI)
if (!defined('STDERR')) {
    define('STDERR', fopen('php://stderr', 'w'));
}

// Ensure table for storing PKO payment notifications exists (create or migrate schema)
$info = $pdo->query("PRAGMA table_info(pko_payments)")->fetchAll(PDO::FETCH_COLUMN, 1);
if (empty($info)) {
    // Table missing: create new pko_payments table with composite-unique index
    $pdo->exec(<<<'SQL'
    CREATE TABLE pko_payments (
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
} elseif (in_array('message_id', $info, true)) {
    // Migrate old schema: drop message_id column and balance_after by recreating table
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
    $pdo->exec('INSERT INTO pko_payments_new (id, received_at, account_number, transaction_date, amount, currency, sender, title, processed_at) SELECT id, received_at, account_number, transaction_date, amount, currency, sender, title, processed_at FROM pko_payments');
    $pdo->exec('DROP TABLE pko_payments');
    $pdo->exec('ALTER TABLE pko_payments_new RENAME TO pko_payments');
    $pdo->exec('COMMIT');
    $pdo->exec('PRAGMA foreign_keys=on');
}

// Local .eml processing disabled; fetching directly from mailbox via Microsoft Graph

function normalizeKey(string $str): string {
    return preg_replace('/[^A-Za-z0-9]/', '', $str);
}

use League\OAuth2\Client\Provider\Exception\IdentityProviderException;
use League\OAuth2\Client\Token\AccessTokenInterface;
use TheNetworg\OAuth2\Client\Provider\Azure;
use GuzzleHttp\Client as GuzzleClient;
use GuzzleHttp\Exception\RequestException as GuzzleRequestException;

$httpClient = new GuzzleClient();

$provider = new Azure([
    'clientId'                => AZURE_CLIENT_ID,
    'clientSecret'            => AZURE_CLIENT_SECRET,
    'redirectUri'             => AZURE_REDIRECT_URI,
    'tenant'                  => AZURE_TENANT_ID,
    'scopes'                  => ['offline_access', 'User.Read', 'Mail.Read.Shared', 'https://graph.microsoft.com/Mail.Read'],
    'defaultEndPointVersion'  => Azure::ENDPOINT_VERSION_2_0,
]);

/**
 * Load OAuth access token from file.
 */
function loadToken(): ?\League\OAuth2\Client\Token\AccessToken {
    if (!file_exists(AZURE_TOKEN_FILE)) {
        return null;
    }
    $data = json_decode((string) file_get_contents(AZURE_TOKEN_FILE), true);
    if (!is_array($data) || empty($data['access_token'])) {
        @unlink(AZURE_TOKEN_FILE);
        return null;
    }
    try {
        return new \League\OAuth2\Client\Token\AccessToken($data);
    } catch (\Exception $e) {
        @unlink(AZURE_TOKEN_FILE);
        return null;
    }
}

/**
 * Save OAuth access token to file.
 */
function saveToken(AccessTokenInterface $accessToken): void {
    $dir = dirname(AZURE_TOKEN_FILE);
    if (!is_dir($dir)) {
        mkdir($dir, 0700, true);
    }
    file_put_contents(AZURE_TOKEN_FILE, json_encode([
        'access_token'  => $accessToken->getToken(),
        'refresh_token' => $accessToken->getRefreshToken(),
        'expires'       => $accessToken->getExpires(),
    ], JSON_PRETTY_PRINT));
    @chmod(AZURE_TOKEN_FILE, 0600);
}

/**
 * Obtain a valid OAuth access token, refreshing or requesting authorization if needed.
 */
function getAccessToken(Azure $provider): string {
    $token = loadToken();
    if ($token instanceof AccessTokenInterface && !$token->hasExpired()) {
        return $token->getToken();
    }
    if ($token instanceof AccessTokenInterface && $token->hasExpired() && $token->getRefreshToken()) {
        $newToken = $provider->getAccessToken('refresh_token', ['refresh_token' => $token->getRefreshToken()]);
        saveToken($newToken);
        return $newToken->getToken();
    }
    // No valid token; prompt for authorization code
    $authUrl = $provider->getAuthorizationUrl();
    echo "Open the following URL in your browser and authorize the application:\n$authUrl\n\n";
    echo "Enter the full redirect URL after authorization: ";
    $handle = fopen('php://stdin', 'r');
    $redirectResponse = trim(fgets($handle));
    fclose($handle);
    parse_str(parse_url($redirectResponse, PHP_URL_QUERY), $params);
    if (empty($params['code'])) {
        fwrite(STDERR, "Authorization code not found in response URL.\n");
        exit(1);
    }
    $newToken = $provider->getAccessToken('authorization_code', ['code' => $params['code']]);
    saveToken($newToken);
    return $newToken->getToken();
}

// Main execution
$graphToken = getAccessToken($provider);

if (PHP_SAPI !== 'cli') {
    include ROOT_DIR_PATH . '/admin/header.php';
    echo '<h1>Fetch PKO Messages</h1>';
    echo "<p><a href='/admin/payments.php' class='btn btn-secondary'>Back to payments</a></p>";
    echo '<pre>';
}

$mailbox = AZURE_SHARED_MAILBOX_EMAIL;
$folderName = 'Inbox';

// Retrieve list of mail folders to find the target folder ID
$url = "https://graph.microsoft.com/v1.0/users/{$mailbox}/mailFolders?\$select=id,displayName";
try {
    $response = $httpClient->request('GET', $url, [
        'headers' => ['Authorization' => "Bearer {$graphToken}"],
        'http_errors' => false,
    ]);
} catch (GuzzleRequestException $e) {
    fwrite(STDERR, "Failed to retrieve mail folders: " . $e->getMessage() . "\n");
    exit(1);
}
$folders = json_decode((string) $response->getBody(), true)['value'] ?? [];
$folderId = null;
foreach ($folders as $folder) {
    if (isset($folder['displayName']) && $folder['displayName'] === $folderName) {
        $folderId = $folder['id'];
        break;
    }
}
if (!$folderId) {
    fwrite(STDERR, "Mail folder '{$folderName}' not found.\n");
    exit(1);
}

// Locate the mailbox folder where processed messages will be moved (must exist)
$processedFolderName = AZURE_MAIL_FOLDER . '-processed';
$processedFolderId = null;
foreach ($folders as $folder) {
    if (isset($folder['displayName']) && $folder['displayName'] === $processedFolderName) {
        $processedFolderId = $folder['id'];
        break;
    }
}
if (!$processedFolderId) {
    fwrite(STDERR, "Mail folder '{$processedFolderName}' not found. Please create it to store processed messages.\n");
    exit(1);
}

// Locate the mailbox folder for skipped messages (duplicates)
$skippedFolderName = AZURE_MAIL_FOLDER . '-skipped';
$skippedFolderId = null;
foreach ($folders as $folder) {
    if (isset($folder['displayName']) && $folder['displayName'] === $skippedFolderName) {
        $skippedFolderId = $folder['id'];
        break;
    }
}
if (!$skippedFolderId) {
    fwrite(STDERR, "Mail folder '{$skippedFolderName}' not found. Please create it to store skipped messages.\n");
    exit(1);
}

// Locate the mailbox folder for non-bank messages
$nonBankFolderName = 'non-bank';
$nonBankFolderId = null;
foreach ($folders as $folder) {
    if (isset($folder['displayName']) && $folder['displayName'] === $nonBankFolderName) {
        $nonBankFolderId = $folder['id'];
        break;
    }
}
if (!$nonBankFolderId) {
    fwrite(STDERR, "Mail folder '{$nonBankFolderName}' not found. Please create it to store non-bank messages.\n");
    exit(1);
}

// Locate the mailbox folder where processed messages will be moved (must exist)
$processedFolderName = AZURE_MAIL_FOLDER . '-processed';
$processedFolderId = null;
foreach ($folders as $folder) {
    if (isset($folder['displayName']) && $folder['displayName'] === $processedFolderName) {
        $processedFolderId = $folder['id'];
        break;
    }
}
if (!$processedFolderId) {
    fwrite(STDERR, "Mail folder '{$processedFolderName}' not found. Please create it to store processed messages.\n");
    exit(1);
}

// Fetch the last 10 messages from the target folder
$top = 10;
$select = urlencode('id,subject,receivedDateTime,from');
$url = "https://graph.microsoft.com/v1.0/users/{$mailbox}/mailFolders/{$folderId}/messages?\$top={$top}&\$select={$select}&\$orderby=receivedDateTime desc";
try {
    $response = $httpClient->request('GET', $url, [
        'headers' => ['Authorization' => "Bearer {$graphToken}"],
        'http_errors' => false,
    ]);
} catch (GuzzleRequestException $e) {
    fwrite(STDERR, "Failed to fetch messages: " . $e->getMessage() . "\n");
    exit(1);
}
// Prepare for duplicate detection: exact date+amount, normalized sender/title substring matching
$duplicateSelectStmt = $pdo->prepare(
    'SELECT sender, title FROM pko_payments WHERE transaction_date = ? AND amount = ?'
);

// Initialize reporting structures
$reportRows    = [];
$insertedCount = 0;
$skippedCount  = 0;
$nonBankCount  = 0;

$messages = json_decode((string) $response->getBody(), true)['value'] ?? [];

// Process each message: fetch raw MIME, extract payment data, store and move to processed folder
foreach ($messages as $msg) {
    if (empty($msg['id']) || empty($msg['receivedDateTime'])) {
        continue;
    }
    // Capture email metadata for reporting
    $msgDate    = $msg['receivedDateTime'];
    $msgFrom    = $msg['from']['emailAddress']['address'] ?? '';
    $msgSubject = $msg['subject'] ?? '';
    $msgId      = $msg['id'];


    // Fetch raw message content
    $rawUrl = "https://graph.microsoft.com/v1.0/users/{$mailbox}/messages/{$msgId}/\$value";
    try {
        $rawResp = $httpClient->request('GET', $rawUrl, [
            'headers' => ['Authorization' => "Bearer {$graphToken}"],
            'http_errors' => false,
        ]);
    } catch (GuzzleRequestException $e) {
        fwrite(STDERR, "Failed to fetch raw message for ID {$msgId}: " . $e->getMessage() . "\n");
        continue;
    }
    $rawData = (string) $rawResp->getBody();

    // Extract HTML table fragment
    $htmlTable = null;
    if (preg_match('/<table align="center".*?<\/table>/si', $rawData, $m)) {
        $htmlTable = $m[0];
    } else {
        fwrite(STDERR, "Could not extract HTML table from message {$msgId}\n");
        // Move to non-bank folder
        $moveUrl = "https://graph.microsoft.com/v1.0/users/{$mailbox}/messages/{$msgId}/move";
        try {
            $httpClient->request('POST', $moveUrl, [
                'headers' => [
                    'Authorization' => "Bearer {$graphToken}",
                    'Content-Type'  => 'application/json',
                ],
                'body'        => json_encode(['destinationId' => $nonBankFolderId]),
                'http_errors' => false,
            ]);
        } catch (GuzzleRequestException $e) {
            fwrite(STDERR, "Failed to move message {$msgId} to '{$nonBankFolderName}': " . $e->getMessage() . "\n");
        }
        continue;
    }

    $charset = 'UTF-8';
    if (preg_match('/Content-Type:\s*text\/html;\s*charset=([^;\s]+)/i', $rawData, $mc)) {
        $charset = trim($mc[1], "\"'");
    }
    if (strcasecmp($charset, 'UTF-8') !== 0) {
        $htmlTable = iconv($charset, 'UTF-8//IGNORE', $htmlTable);
    }
    $text = html_entity_decode(strip_tags($htmlTable), ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $text = preg_replace('/\x{00A0}/u', ' ', $text);

    // Extract fields
    $account = null;
    if (preg_match('/konto o numerze\s*([\d\.]+)/i', $text, $m)) {
        $account = $m[1];
    }
    $amount = null;
    $currency = null;
    if (preg_match('/wpłynęła kwota\s*([+\-]?[\d,\.]+)\s*([A-Z]{3})/i', $text, $m)) {
        $amount = str_replace(',', '.', $m[1]);
        $currency = $m[2];
    }
    $transDate = null;
    if (preg_match('/Data waluty:\s*([0-9]{4}-[0-9]{2}-[0-9]{2})/i', $text, $m)) {
        $transDate = $m[1];
    }
    $sender = null;
    if (preg_match('/nadawca:\s*(.*?)\s*(tytuł:|Data waluty:)/i', $text, $m)) {
        $sender = trim($m[1]);
    }
    $title = null;
    if (preg_match('/tytuł:\s*([^\r\n]+)/i', $text, $m)) {
        $title = trim($m[1]);
    }

    // Skip messages missing essential payment fields
    if (empty($transDate) || empty($amount)) {
        fwrite(STDERR, "Missing transaction date or amount in message {$msgId}\n");
        $nonBankCount++;
        $reportRows[] = [
            'date'    => $msgDate,
            'from'    => $msgFrom,
            'subject' => $msgSubject,
            'result'  => 'non-bank',
        ];
        // Move to non-bank folder
        $moveUrl = "https://graph.microsoft.com/v1.0/users/{$mailbox}/messages/{$msgId}/move";
        try {
            $httpClient->request('POST', $moveUrl, [
                'headers' => [
                    'Authorization' => "Bearer {$graphToken}",
                    'Content-Type'  => 'application/json',
                ],
                'body'        => json_encode(['destinationId' => $nonBankFolderId]),
                'http_errors' => false,
            ]);
        } catch (GuzzleRequestException $e) {
            fwrite(STDERR, "Failed to move message {$msgId} to '{$nonBankFolderName}': " . $e->getMessage() . "\n");
        }
        continue;
    }

    $duplicateSelectStmt->execute([$transDate, $amount]);
    $rows = $duplicateSelectStmt->fetchAll(PDO::FETCH_ASSOC);
    $isDuplicate = false;
    $keySender = normalizeKey($sender);
    $keyTitle  = normalizeKey($title);
    foreach ($rows as $row) {
        $rowSender = normalizeKey($row['sender']);
        $rowTitle  = normalizeKey($row['title']);
        if (strpos($rowSender, $keySender) !== false
            && strpos($rowTitle, $keyTitle) !== false) {
            $isDuplicate = true;
            break;
        }
    }
    if ($isDuplicate) {
        $skippedCount++;
        $reportRows[] = [
            'date'    => $msgDate,
            'from'    => $msgFrom,
            'subject' => $msgSubject,
            'result'  => 'skipped',
        ];
        // Move duplicate to skipped folder
        $moveUrl = "https://graph.microsoft.com/v1.0/users/{$mailbox}/messages/{$msgId}/move";
        try {
            $httpClient->request('POST', $moveUrl, [
                'headers' => [
                    'Authorization' => "Bearer {$graphToken}",
                    'Content-Type'  => 'application/json',
                ],
                'body'        => json_encode(['destinationId' => $skippedFolderId]),
                'http_errors' => false,
            ]);
        } catch (GuzzleRequestException $e) {
            fwrite(STDERR, "Failed to move skipped message {$msgId}: " . $e->getMessage() . "\n");
        }
        continue;
    }

    // Insert into database
    $insert = $pdo->prepare('INSERT INTO pko_payments
        (received_at, account_number, transaction_date, amount, currency, sender, title)
        VALUES (?, ?, ?, ?, ?, ?, ?)');
    $insert->execute([
        $msg['receivedDateTime'],
        $account,
        $transDate,
        $amount,
        $currency,
        $sender,
        $title,
    ]);

    $insertedCount++;
    $reportRows[] = [
        'date'    => $msgDate,
        'from'    => $msgFrom,
        'subject' => $msgSubject,
        'result'  => 'inserted',
    ];

    // Move message to processed folder
    $moveUrl = "https://graph.microsoft.com/v1.0/users/{$mailbox}/messages/{$msgId}/move";
    try {
        $httpClient->request('POST', $moveUrl, [
            'headers' => [
                'Authorization' => "Bearer {$graphToken}",
                'Content-Type' => 'application/json',
            ],
            'body' => json_encode(['destinationId' => $processedFolderId]),
            'http_errors' => false,
        ]);
    } catch (GuzzleRequestException $e) {
        fwrite(STDERR, "Failed to move message {$msgId}: " . $e->getMessage() . "\n");
    }
}

// Display detailed report
if (!empty($reportRows)) {
    // calculate column widths
    $cols = ['Date', 'From', 'Subject', 'Result'];
    $colWidths = array_combine($cols, array_map('mb_strlen', $cols));
    foreach ($reportRows as $row) {
        $colWidths['Date']    = max($colWidths['Date'], mb_strlen($row['date']));
        $colWidths['From']    = max($colWidths['From'], mb_strlen($row['from']));
        $colWidths['Subject'] = max($colWidths['Subject'], mb_strlen($row['subject']));
        $colWidths['Result']  = max($colWidths['Result'], mb_strlen($row['result']));
    }
    echo "\n";
    // header row
    printf(
        "%-{$colWidths['Date']}s | %-{$colWidths['From']}s | %-{$colWidths['Subject']}s | %-{$colWidths['Result']}s\n",
        'Date', 'From', 'Subject', 'Result'
    );
    // separator
    echo str_repeat('-', $colWidths['Date']) . '-+-' .
         str_repeat('-', $colWidths['From']) . '-+-' .
         str_repeat('-', $colWidths['Subject']) . '-+-' .
         str_repeat('-', $colWidths['Result']) . "\n";
    // data rows
    foreach ($reportRows as $row) {
        printf(
            "%-{$colWidths['Date']}s | %-{$colWidths['From']}s | %-{$colWidths['Subject']}s | %-{$colWidths['Result']}s\n",
            $row['date'], $row['from'], $row['subject'], $row['result']
        );
    }
}

$totalProcessed = count($reportRows);
echo "\nProcessed {$totalProcessed} emails: inserted {$insertedCount}, skipped {$skippedCount}, non-bank {$nonBankCount}.\n";

if (PHP_SAPI !== 'cli') {
    echo '</pre>';
    echo "<p><a href='/admin/payments.php' class='btn btn-secondary'>Back to payments</a></p>";
    include ROOT_DIR_PATH. '/admin/footer.php';
}
