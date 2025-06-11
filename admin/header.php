<?php
require_once __DIR__ . '/../auth.php';
if (!is_admin()) {
    http_response_code(403);
    exit('Forbidden <meta http-equiv="refresh" content="0; url=/">');
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Invoicer</title>
    
    <link id="favicon" rel="shortcut icon" type="image/svg+xml" href='data:image/svg+xml;utf8,%3Csvg%20xmlns%3D%22http%3A%2F%2Fwww.w3.org%2F2000%2Fsvg%22%20xmlns%3Axlink%3D%22http%3A%2F%2Fwww.w3.org%2F1999%2Fxlink%22%20viewBox%3D%220%200%2064%2064%22%3E%3Cdefs%3E%3ClinearGradient%20id%3D%22linear-gradient%22%20x1%3D%2236%22%20y1%3D%2241%22%20x2%3D%2260%22%20y2%3D%2241%22%20gradientUnits%3D%22userSpaceOnUse%22%3E%3Cstop%20offset%3D%220%22%20stop-color%3D%22%23fe9661%22%2F%3E%3Cstop%20offset%3D%221%22%20stop-color%3D%22%23ffb369%22%2F%3E%3C%2FlinearGradient%3E%3ClinearGradient%20id%3D%22linear-gradient-2%22%20x1%3D%2248%22%20y1%3D%2251%22%20x2%3D%2248%22%20y2%3D%2231%22%20xlink%3Ahref%3D%22%23linear-gradient%22%2F%3E%3ClinearGradient%20id%3D%22linear-gradient-3%22%20x1%3D%224%22%20y1%3D%2232%22%20x2%3D%2248%22%20y2%3D%2232%22%20gradientUnits%3D%22userSpaceOnUse%22%3E%3Cstop%20offset%3D%220%22%20stop-color%3D%22%23d3e6f5%22%2F%3E%3Cstop%20offset%3D%221%22%20stop-color%3D%22%23f0f7fc%22%2F%3E%3C%2FlinearGradient%3E%3ClinearGradient%20id%3D%22linear-gradient-4%22%20x1%3D%2248%22%20y1%3D%2247%22%20y2%3D%2235%22%20xlink%3Ahref%3D%22%23linear-gradient-3%22%2F%3E%3Cstyle%3E.cls-5%7Bfill%3A%23b4cde1%7D%3C%2Fstyle%3E%3C%2Fdefs%3E%3Cg%20id%3D%22Invoice%22%3E%3Cpath%20d%3D%22M60%2041a11.45%2011.45%200%200%201-.08%201.33%2012%2012%200%200%201-23.84%200A11.45%2011.45%200%200%201%2036%2041a10.28%2010.28%200%200%201%20.09-1.37v-.35a11.51%2011.51%200%200%201%20.6-2.28%2012%2012%200%200%201%2022.62%200%2011.51%2011.51%200%200%201%20.56%202.28A10.8%2010.8%200%200%201%2060%2041z%22%20style%3D%22fill%3Aurl%28%23linear-gradient%29%22%2F%3E%3Cpath%20d%3D%22M58%2041a8%208%200%200%201-.07%201.11%209.71%209.71%200%200%201-.9%203.18%2010%2010%200%200%201-18.06%200%209.71%209.71%200%200%201-.9-3.18A9.87%209.87%200%200%201%2038%2041a8.5%208.5%200%200%201%20.08-1.14%202.85%202.85%200%200%201%200-.29%209.11%209.11%200%200%201%20.47-1.9%2010%2010%200%200%201%2018.84%200%209.11%209.11%200%200%201%20.47%201.9A8.37%208.37%200%200%201%2058%2041z%22%20style%3D%22fill%3Aurl%28%23linear-gradient-2%29%22%2F%3E%3Cpath%20d%3D%22M36.09%2039.63A10.28%2010.28%200%200%200%2036%2041a11.45%2011.45%200%200%200%20.08%201.33A12%2012%200%200%200%2048%2053v2.85A4.09%204.09%200%200%201%2044%2060H8a4.09%204.09%200%200%201-4-4.15V8.15A4.09%204.09%200%200%201%208%204h32v6a2%202%200%200%200%202%202h6v17a12%2012%200%200%200-11.31%208%2011.51%2011.51%200%200%200-.56%202.28c-.01.12-.04.23-.04.35z%22%20style%3D%22fill%3Aurl%28%23linear-gradient-3%29%22%2F%3E%3Crect%20x%3D%2211%22%20y%3D%2218%22%20width%3D%2229%22%20height%3D%2210%22%20rx%3D%222.04%22%20style%3D%22fill%3A%2354a5ff%22%2F%3E%3Cpath%20class%3D%22cls-5%22%20d%3D%22M27%2039H12a1%201%200%200%201%200-2h15a1%201%200%200%201%200%202zM27%2034H12a1%201%200%200%201%200-2h15a1%201%200%200%201%200%202zM27%2044H12a1%201%200%200%201%200-2h15a1%201%200%200%201%200%202zM31%2049H12a1%201%200%200%201%200-2h19a1%201%200%200%201%200%202z%22%2F%3E%3Cpath%20class%3D%22cls-5%22%20d%3D%22M32%2049H12a1%201%200%200%201%200-2h20a1%201%200%200%201%200%202zM32%2054H12a1%201%200%200%201%200-2h20a1%201%200%200%201%200%202z%22%2F%3E%3Cpath%20class%3D%22cls-5%22%20d%3D%22M32%2054H12a1%201%200%200%201%200-2h20a1%201%200%200%201%200%202zM48%2012h-6a2%202%200%200%201-2-2V4.05z%22%2F%3E%3Cpath%20d%3D%22M51%2042.5a2.46%202.46%200%200%200-2.4-2.5h-.94a.51.51%200%200%201%200-1H50a1%201%200%200%200%200-2h-1v-1a1%201%200%200%200-2%200v1.11a2.51%202.51%200%200%200%20.66%204.89h.94a.51.51%200%200%201%200%201H46a1%201%200%200%200%200%202h1v1a1%201%200%200%200%202%200v-1a2.47%202.47%200%200%200%202-2.5z%22%20style%3D%22fill%3Aurl%28%23linear-gradient-4%29%22%2F%3E%3C%2Fg%3E%3C%2Fsvg%3E'>
    
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.6/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-4Q6Gf2aSP4eDXB8Miphtr37CMZZQ5oXLH2yaXMJ2w8e2ZtHTl7GptT4jmndRuHDT" crossorigin="anonymous">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.13.1/font/bootstrap-icons.min.css">
    <style>
      .mb-3:has(> label.form-label) {
        display: flex;
        align-items: center;
        gap: .5rem;
      }
      .mb-3:has(> label.form-label) > label.form-label {
        margin-bottom: 0;
        white-space: nowrap;
        width: 150px;
      }
      .mb-3:has(> label.form-label) > .form-control,
      .mb-3:has(> label.form-label) > .form-select {
        flex: 1 1 auto;
      }
    </style>
    <script src="https://cdn.jsdelivr.net/npm/vue@3/dist/vue.global.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script>
    (function(){
      const _fetch = window.fetch;
      window.fetch = function(...args){
        return _fetch(...args).then(async response=>{
          if(!response.ok){
            const text = await response.text().catch(()=>'');
            const msg = `Error ${response.status} (${response.statusText}): ${text}`;
            alert(msg);
            throw new Error(msg);
          }
          return response;
        }).catch(err=>{
          alert(`Network error: ${err.message}`);
          throw err;
        });
      };
    })();
    </script>
</head>
<body>
<nav class="navbar navbar-expand-lg navbar-light bg-light mb-4">
  <div class="container-fluid">
    <a class="navbar-brand" href="/admin/customers.php">Invoicer</a>
    <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
      <span class="navbar-toggler-icon"></span>
    </button>
    <div class="collapse navbar-collapse" id="navbarNav">
      <ul class="navbar-nav">
        <li class="nav-item"><a class="nav-link" href="/admin/customers.php">Customers</a></li>
        <li class="nav-item"><a class="nav-link" href="/admin/invoices.php">Invoices</a></li>
        <li class="nav-item"><a class="nav-link" href="/admin/payments.php">Payments</a></li>
        <li class="nav-item"><a class="nav-link" href="/admin/reconciliation.php">Reconciliation</a></li>
        <li class="nav-item"><a class="nav-link" href="/admin/invoice_status_changes.php">Change Log</a></li>
        <li class="nav-item"><a class="nav-link" href="/admin/companies.php">Companies</a></li>
        <li class="nav-item"><a class="nav-link" href="/admin/services.php">Services</a></li>
        <li class="nav-item"><a class="nav-link" href="/admin/currencies.php">Currencies</a></li>
        <li class="nav-item"><a class="nav-link" href="/admin/users.php">Users</a></li>
        <li class="nav-item"><a class="nav-link" href="/logout.php">Logout <small>(<?= htmlspecialchars(current_username()) ?>)</small></a></li>
      </ul>
    </div>
  </div>
</nav>
<div class="container">