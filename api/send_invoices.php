<?php
require_once __DIR__ . '/../auth.php';
header('Content-Type: application/json');
if (!is_admin()) {
    http_response_code(403);
    echo json_encode(['error' => 'Forbidden']);
    exit;
}
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../config.php';

use TheNetworg\OAuth2\Client\Provider\Azure;
use League\OAuth2\Client\Token\AccessTokenInterface;
use GuzzleHttp\Client as GuzzleClient;

/**
 * Load OAuth token from disk.
 */
function loadToken(): ?\League\OAuth2\Client\Token\AccessToken
{
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
 * Save OAuth token to disk.
 */
function saveToken(AccessTokenInterface $token): void
{
    $dir = dirname(AZURE_TOKEN_FILE);
    if (!is_dir($dir)) {
        mkdir($dir, 0700, true);
    }
    file_put_contents(AZURE_TOKEN_FILE, json_encode([
        'access_token'  => $token->getToken(),
        'refresh_token' => $token->getRefreshToken(),
        'expires'       => $token->getExpires(),
    ], JSON_PRETTY_PRINT));
    @chmod(AZURE_TOKEN_FILE, 0600);
}

/**
 * Get a valid OAuth access token, refreshing if needed.
 */
function getAccessToken(Azure $provider): string
{
    $token = loadToken();
    if ($token instanceof AccessTokenInterface && !$token->hasExpired()) {
        return $token->getToken();
    }
    if ($token instanceof AccessTokenInterface && $token->hasExpired() && $token->getRefreshToken()) {
        $newToken = $provider->getAccessToken('refresh_token', ['refresh_token' => $token->getRefreshToken()]);
        saveToken($newToken);
        return $newToken->getToken();
    }
    http_response_code(500);
    echo json_encode(['error' => 'OAuth token not available. Please run fetch_pko_messages.php to authenticate.']);
    exit;
}

// prepare Graph OAuth provider and HTTP client
$provider = new Azure([
    'clientId'               => AZURE_CLIENT_ID,
    'clientSecret'           => AZURE_CLIENT_SECRET,
    'redirectUri'            => AZURE_REDIRECT_URI,
    'tenant'                 => AZURE_TENANT_ID,
    'scopes'                 => [
        'offline_access',
        'User.Read',
        'Mail.Read.Shared',
        'Mail.Send.Shared',
        'https://graph.microsoft.com/Mail.Read',
        'https://graph.microsoft.com/Mail.Send'
    ],
    'defaultEndPointVersion' => Azure::ENDPOINT_VERSION_2_0,
]);
$httpClient = new GuzzleClient();

$graphToken = getAccessToken($provider);

$input = json_decode(file_get_contents('php://input'), true);
if (!isset($input['customers']) || !is_array($input['customers'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Missing customers payload']);
    exit;
}

$results = [];
foreach ($input['customers'] as $cust) {
    $custId = (int) ($cust['customer_id'] ?? 0);
    $invoiceIds = $cust['invoice_ids'] ?? [];
    $emails = $cust['emails'] ?? [];
    $template = $cust['template'] ?? '';

    if (!$custId || !is_array($invoiceIds) || !$emails) {
        $results[$custId] = 'Invalid payload for customer';
        continue;
    }


    // fetch invoice details (for template context)
    $inClause = implode(',', array_fill(0, count($invoiceIds), '?'));
    $stmt = $pdo->prepare("SELECT total,currency,month_service,date,invoice_number,status FROM invoices WHERE id IN ($inClause) order by date desc");
    $stmt->execute($invoiceIds);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // lookup customer name (for template context)
    $stmt2 = $pdo->prepare('SELECT name FROM customers WHERE id = ?');
    $stmt2->execute([$custId]);
    $customerName = $stmt2->fetchColumn() ?: '';

    // load PHP template for subject and body
    $tplFile = null;
    foreach (glob(__DIR__ . '/../templates/invoiceemail_' . $template . '.php') as $f) {
        $tplFile = $f;
        break;
    }
    if (!$tplFile) {
        $results[$custId] = "Template not found: $template";
        continue;
    }
    try {
        $templateData = require $tplFile;
    } catch (\Throwable $e) {
        $results[$custId] = "Error loading template '$template': " . $e->getMessage();
        continue;
    }
    if (!is_array($templateData) || !isset($templateData['subject'], $templateData['body'])) {
        $results[$custId] = "Invalid template format: $template";
        continue;
    }
    $subject = $templateData['subject'];
    $body = $templateData['body'];

    // collect attachments (signed PDFs)
    $attachments = [];
    foreach ($invoiceIds as $invId) {
        $files = glob(PRIVATE_DIR_PATH . "/invoices_signed/*_{$invId}.pdf");
        if ($files) {
            $file = $files[0];
            $data = base64_encode(file_get_contents($file));
            $attachments[] = [
                '@odata.type'  => '#microsoft.graph.fileAttachment',
                'name'         => basename($file),
                'contentBytes' => $data
            ];
        }
    }

    // send email via Microsoft Graph
    $mailbox = AZURE_INVOICES_SHARED_MAILBOX_EMAIL;
    $url = "https://graph.microsoft.com/v1.0/users/" . rawurlencode($mailbox) . "/sendMail";
    $toRecipients = array_map(function ($e) { return ['emailAddress' => ['address' => $e]]; }, $emails);

    $payload = [
        'message' => [
            'subject'      => $subject,
            'body'         => ['contentType' => 'html', 'content' => $body],
            'from'         => ['emailAddress' => ['name' => AZURE_INVOICES_SHARED_MAILBOX_FROM_NAME, 'address' => $mailbox]],
            'toRecipients' => $toRecipients,
            'attachments'  => $attachments
        ],
        'saveToSentItems' => true
    ];
    try {
        // DEBUG: log payload and HTTP request/response for diagnosis
        // error_log("send_invoices: sending payload to Graph for customer {$custId}: " . json_encode($payload));
        $res = $httpClient->request('POST', $url, [
            'headers'     => [
                'Authorization' => "Bearer $graphToken",
                'Content-Type'  => 'application/json'
            ],
            'body'        => json_encode($payload),
            'http_errors' => false,
            // 'debug'       => true
        ]);
        // error_log("send_invoices: Graph response for customer {$custId}: HTTP " . $res->getStatusCode() . ' ' . (string)$res->getBody());
        $code = $res->getStatusCode();
        if ($code >= 200 && $code < 300) {
            $results[$custId] = 'sent';
        } else {
            $results[$custId] = 'HTTP ' . $code . ' ' . $res->getBody();
        }
    } catch (\Exception $e) {
        $results[$custId] = $e->getMessage();
    }

}
// var_dump($payload);
echo json_encode(['success' => true, 'results' => $results]);