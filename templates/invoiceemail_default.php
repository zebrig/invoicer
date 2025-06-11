<?php
$monthNames = [
    1 => 'January',
    2 => 'February',
    3 => 'March',
    4 => 'April',
    5 => 'May',
    6 => 'June',
    7 => 'July',
    8 => 'August',
    9 => 'September',
    10 => 'October',
    11 => 'November',
    12 => 'December',
];
$months = [];
$unpaid = [];
foreach ($rows as $row) {
    $parts = explode('.', $row['month_service']);
    if (count($parts) < 2) {
        continue;
    }
    list($mon, $year) = $parts;
    $textMonth = $monthNames[(int)$mon] . ' ' . $year;
    $months[] = $textMonth;
    if ($row['status'] !== 'paid') {
        $unpaid[] = $row['invoice_number'] . ' from ' . $row['date'] . ' (' . round($row['total'], 2) . ' ' . $row['currency'] . ' for the services rendered in ' . $textMonth . ')';
    }
}
array_shift($unpaid);

$monthsList = match (count($months)) {
    0 => '',
    1 => $months[0],
    2 => implode(' and ', $months),
    default => implode(', ', array_slice($months, 0, -1)) . ' and ' . end($months),
};
$pref = '<p>';
$suf = '</p>';
$unpaidSection_diabled = $unpaid
    ? '<p>Please pay attention to outstanding invoices: ' . match (count($unpaid)) {
        1 => $pref . $unpaid[0] . $suf,
        2 => $pref . implode($suf . ' and ' . $pref, $unpaid) . $suf,
        default => $pref . implode($suf . ', ' . $pref, array_slice($unpaid, 0, -1)) . $suf . ' and ' . $pref . end($unpaid) . $suf,
    } . '.</p>'
    : '';

$unpaidSection = $unpaid ? '<p>Please pay attention to outstanding invoices: ' . $pref . implode($suf . $pref, $unpaid) . $suf . '</p>' : '';

$subject = "Invoices for services rendered in $monthsList";
$body = <<<EOD
<!DOCTYPE html>
<html>
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
</head>
<body>
  <div>
      Invoices for IT Services

    <p>Dear Customer,</p>

<p>Please find attached the invoices for services rendered in $monthsList.</p> $unpaidSection

<p>Kind regards,<br>
<strong>Name Secondname</strong><br>
<a href="https://example.com">example.com</a><br>
  </div>
</body>
</html>
EOD;
return ['subject' => $subject, 'body' => $body];
