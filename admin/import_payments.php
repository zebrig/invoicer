<?php
/**
 * Import XML payment statements into the pko_payments table via CLI or web.
 *
 * Usage (CLI): php import_payments.php
 * Usage (Web): upload XML files via web interface
 */

if (php_sapi_name() !== 'cli') {
    require_once __DIR__ . '/../auth.php';
    if (!is_admin()) {
        http_response_code(403);
        exit('Forbidden');
    }
}
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../db.php';
$info = $pdo->query("PRAGMA table_info(pko_payments)")->fetchAll(PDO::FETCH_COLUMN, 1);
if (empty($info)) {
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
}


// replace old exact-match existence check with date+amount lookup + normalizeKey()
$selectStmt = $pdo->prepare(
    'SELECT id, received_at, account_number, currency, sender, title
       FROM pko_payments
      WHERE transaction_date = ? AND amount = ?'
);
$insertStmt = $pdo->prepare(
    'INSERT INTO pko_payments
       (received_at, account_number, transaction_date, amount, currency, sender, title)
     VALUES (?, ?, ?, ?, ?, ?, ?)'
);
$updateStmt = $pdo->prepare(
    'UPDATE pko_payments
        SET received_at = ?, account_number = ?, currency = ?, sender = ?, title = ?, processed_at = CURRENT_TIMESTAMP
      WHERE id = ?'
);

function normalizeKey(string $str): string {
    return preg_replace('/[^A-Za-z0-9]/', '', $str);
}

function processXmlFiles(array $files)
{
    global $selectStmt, $insertStmt, $updateStmt;

    $report = ['total' => 0, 'inserted' => 0, 'updated' => 0, 'skipped' => 0, 'files' => []];

    foreach ($files as $file) {
        $fileReport = ['file' => $file, 'total' => 0, 'inserted' => 0, 'updated' => 0, 'skipped' => 0];

        $xml = simplexml_load_file($file);
        if (!$xml) {
            continue;
        }

        $account = (string) $xml->search->account;
        foreach ($xml->operations->operation as $op) {
            $report['total']++;
            $fileReport['total']++;

            $execDate = (string) $op->{'exec-date'};
            $description = trim((string) $op->description);
            $amount = floatval(str_replace(',', '.', (string) $op->amount));
            $currency = (string) $op->amount['curr'];

            $sender = '';
            if (preg_match('/Nazwa nadawcy\s*:\s*(.*?)\s*(?=Adres nadawcy|Tytuł|$)/u', $description, $m)) {
                $sender = trim($m[1]);
            }
            if (preg_match('/Tytuł\s*:\s*(.*?)\s*(?=Referencje własne|$)/u', $description, $m)) {
                $title = trim($m[1]);
            } elseif (preg_match('/^(.*?)\s*Referencje własne/u', $description, $m)) {
                $title = trim($m[1]);
            } else {
                $title = $description;
            }

            $receivedAt = date('c');

            $selectStmt->execute([$execDate, $amount]);
            $rows  = $selectStmt->fetchAll(PDO::FETCH_ASSOC);
            $found = false;
            foreach ($rows as $row) {
                if (normalizeKey($sender) === normalizeKey($row['sender'])
                    && normalizeKey($title) === normalizeKey($row['title'])) {
                    $found = true;
                    if (
                        $row['account_number'] !== $account
                        || $row['currency'] !== $currency
                    
                        || $row['sender'] !== $sender
                        || $row['title'] !== $title
                    ) {
                        $updateStmt->execute([
                            $receivedAt, $account, $currency,
                            $sender, $title,
                            $row['id'],
                        ]);
                        $report['updated']++;
                        $fileReport['updated']++;
                    } else {
                        $report['skipped']++;
                        $fileReport['skipped']++;
                    }
                    break;
                }
            }
            if (! $found) {
                $insertStmt->execute([
                    $receivedAt, $account, $execDate,
                    $amount, $currency, $sender,
                    $title,
                ]);
                $report['inserted']++;
                $fileReport['inserted']++;
            }
        }

        $report['files'][] = $fileReport;
    }

    return $report;
}

if (PHP_SAPI === 'cli') {
    if (!file_exists(PRIVATE_DIR_PATH.'/import')) {
        mkdir(PRIVATE_DIR_PATH.'/import', 0755, true);
    }
    $files = glob(PRIVATE_DIR_PATH . '/import/*.xml');
    $report = processXmlFiles($files);

    echo "Processed {$report['total']} records: inserted {$report['inserted']}, updated {$report['updated']}, skipped {$report['skipped']}.\n";
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_FILES['xml_files'])) {
    $files = [];
    foreach ($_FILES['xml_files']['tmp_name'] as $key => $tmpName) {
        if (is_uploaded_file($tmpName)) {
            $files[] = $tmpName;
        }
    }
    $report = processXmlFiles($files);
    include __DIR__ . '/header.php';
    ?>
    <h1>Import Payments</h1>
    <div class="alert alert-info">
        Processed <?= $report['total'] ?> records:
        inserted <?= $report['inserted'] ?>,
        updated <?= $report['updated'] ?>,
        skipped <?= $report['skipped'] ?>.
    </div>
    <p>
        <a href="payments.php" class="btn btn-secondary">Back to payments page</a>
        <a href="" class="btn btn-primary ms-2">Import more files</a>
    </p>
    <?php
    include __DIR__ . '/footer.php';
    exit;
}

include __DIR__ . '/header.php';
?>
<h1>Import Payments</h1>
<form action="" method="post" enctype="multipart/form-data">
    <div class="mb-3">
        <label class="form-label" for="xml_files">Select XML files to import:</label>
        <input class="form-control" type="file" name="xml_files[]" id="xml_files" accept=".xml" multiple>
    </div>
    <button id="import-btn" class="btn btn-primary" type="submit" disabled>Import</button>
    </form>
    <div class="d-flex flex-row-reverse">
        <div>
            <a href="payments.php" class="btn btn-secondary">Back to payments page</a>
        </div>
    </div>
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        var fileInput = document.getElementById('xml_files');
        var importBtn = document.getElementById('import-btn');
        function updateBtn() {
            importBtn.disabled = fileInput.files.length === 0;
        }
        updateBtn();
        fileInput.addEventListener('change', updateBtn);
    });
    </script>
<?php include __DIR__ . '/footer.php'; ?>