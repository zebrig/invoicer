<?php include 'header.php'; ?>
<?php
$templateDirs = [ROOT_DIR_PATH . '/templates', PRIVATE_DIR_PATH . '/templates'];
$templates = [];
foreach ($templateDirs as $dir) {
  if (!is_dir($dir)) {
    mkdir($dir, 0755, true);
  }
  foreach (glob("$dir/invoice*.html") as $path) {
    $templates[] = basename($path);
  }
}
sort($templates);
$templates = array_values(array_unique($templates));
$historyFiles = [];
if (isset($_GET['invoice_id'])) {
  require_once ROOT_DIR_PATH . '/db.php';
  $invId = (int)$_GET['invoice_id'];
  $stmt = $pdo->prepare('SELECT invoice_number FROM invoices WHERE id = ?');
  $stmt->execute([$invId]);
  $invNum = $stmt->fetchColumn();
  if ($invNum) {
    $dir = PRIVATE_DIR_PATH . '/invoices_history/';
    if (is_dir($dir)) {
      $pattern = $dir . "*_{$invId}_*.html";
      $paths = glob($pattern);
      usort($paths, function ($a, $b) {
        return filemtime($b) - filemtime($a);
      });
      $paths = array_slice($paths, 0, 3);
      foreach ($paths as $path) {
        $historyFiles[] = basename($path);
      }
    }
  }
}
// Determine existing signed PDF file (one per invoice)
$signedFile = '';
// User feedback message for upload/delete actions
$uploadMsg = '';
if (isset($_GET['upload_status'])) {
  switch ($_GET['upload_status']) {
    case 'success':
      $uploadMsg = 'Signed PDF uploaded successfully.';
      break;
    case 'error':
      $uploadMsg = 'Error processing signed PDF.';
      break;
    case 'deleted':
      $uploadMsg = 'Signed PDF deleted.';
      break;
  }
}
if (isset($invId)) {
  $signedDir = PRIVATE_DIR_PATH . '/invoices_signed/';
  if (is_dir($signedDir)) {
    $pattern = $signedDir . "*_${invId}.pdf";
    $files = glob($pattern);
    if ($files) {
      usort($files, function ($a, $b) {
        return filemtime($b) - filemtime($a);
      });
      $signedFile = basename($files[0]);
    }
  }
}
?>
<div id="app" v-if="invoice">
  <div class="mb-3">
    <a :href="backLink" class="btn btn-outline-secondary">
      &larr; {{ backText }}
    </a>
  </div>
  <div class="mb-4">
    <h1>{{ invoice.id ? 'Edit' : 'New' }} Invoice for {{ customer.name }}</h1>
  </div>

  <div class="card mb-4">
    <div class="card-header">Invoice Details</div>
    <div class="card-body">
      <div class="row">
        <div class="col-md-6">
          <div class="mb-3"><label class="form-label">Invoice Number</label>
            <input v-model="invoice.invoice_number"
                   :class="['form-control', { 'border border-warning': duplicateNumber }]" />
            <div v-if="duplicateNumber" class="text-warning small mt-1">
              An invoice with this number already exists for {{ customer.name }}.
            </div>
          </div>
          <div class="mb-3"><label class="form-label">Date</label>
            <input type="date" v-model="invoice.date"
                   :class="['form-control', { 'border border-warning': duplicateDate }]" />
            <div v-if="duplicateDate" class="text-warning small mt-1">
              An invoice on this date already exists for {{ customer.name }}.
            </div>
          </div>
          <div class="mb-3">
            <label class="form-label">Month of Service</label>
            <input type="text"
              v-model="invoice.month_service"
              class="form-control"
              :class="{'is-invalid': !monthServiceValid}"
              pattern="(0[1-9]|1[0-2])\.[0-9]{4}" />
            <div v-if="!monthServiceValid" class="invalid-feedback">
              Month of Service must be in MM.YYYY format.
            </div>
            <div v-if="duplicateMonth" class="text-warning small mt-1">
              An invoice for {{ customer.name }} in {{ invoice.month_service }} already exists.
            </div>
          </div>
          <div class="mb-3"><label class="form-label">Contract</label>
            <select v-model="invoice.contract_id" class="form-select">
              <option :value="null">None</option>
              <option v-for="c in contracts" :value="c.id">{{ c.name }}</option>
            </select>
          </div>
        </div>
        <div class="col-md-6">
          <div class="mb-3"><label class="form-label">VAT Rate (%)</label>
            <input type="number" v-model.number="invoice.vat_rate"
              class="form-control" @input="calculateTotals" />
          </div>
          <div class="mb-3"><label class="form-label">Status</label>
            <select v-model="invoice.status" class="form-select" @change="onStatusChange">
              <option value="unpaid">Unpaid</option>
              <option value="paid">Paid</option>
            </select>
          </div>
        </div>
      </div>
    </div>
  </div>

  <div class="card mb-4">
    <div class="card-header">Company Details</div>
    <div class="card-body">
      <div class="row">
        <div class="col-md-6">
          <div class="mb-3"><label class="form-label">Select Company</label>
            <select v-model="selectedCompany" @change="loadCompany" class="form-select">
              <option v-for="c in filteredCompanies" :value="c">{{ c.name }}</option>
            </select>
            <button type="button" class="btn btn-sm btn-outline-primary mt-1" @click="loadCompany">
              <i class="bi bi-arrow-repeat"></i>
              Re-fill company details
            </button>
          </div>
          <div class="mb-3"><label class="form-label">Name</label>
            <input v-model="myCompany.name" class="form-control" />
          </div>
          <div class="mb-3"><label class="form-label">Company</label>
            <input v-model="myCompany.company" class="form-control" />
          </div>
          <div class="mb-3"><label class="form-label">ID/NIP Number</label>
            <input v-model="myCompany.id_number" class="form-control" />
          </div>
          <div class="mb-3"><label class="form-label">CEIDG/KRS Number</label>
            <input v-model="myCompany.regon_krs_number" class="form-control" />
          </div>
          <div class="mb-3"><label class="form-label">REGON</label>
            <input v-model="myCompany.regon_number" class="form-control" />
          </div>
          <div class="mb-3"><label class="form-label">VAT Number</label>
            <input v-model="myCompany.vat_number" class="form-control" />
          </div>
          <div class="mb-3"><label class="form-label">Website</label>
            <input v-model="myCompany.website" class="form-control" />
          </div>
          <div class="mb-3"><label class="form-label">Email</label>
            <input v-model="myCompany.email" class="form-control" />
          </div>
          <div class="mb-3"><label class="form-label">Phone</label>
            <input v-model="myCompany.phone" class="form-control" />
          </div>
        </div>
        <div class="col-md-6">
          <div class="mb-3"><label class="form-label">Address</label>
            <input v-model="myCompany.address" class="form-control" />
          </div>
          <div class="mb-3"><label class="form-label">City</label>
            <input v-model="myCompany.city" class="form-control" />
          </div>
          <div class="mb-3"><label class="form-label">Postal Code</label>
            <input v-model="myCompany.postal_code" class="form-control" />
          </div>
          <div class="mb-3"><label class="form-label">Country</label>
            <input v-model="myCompany.country" class="form-control" />
          </div>
          <div class="mb-3"><label class="form-label">Bank Name</label>
            <input v-model="myCompany.bank_name" class="form-control" />
          </div>
          <div class="mb-3"><label class="form-label">Bank Account #</label>
            <input v-model="myCompany.bank_account" class="form-control" />
          </div>
          <div class="mb-3"><label class="form-label">Bank Code</label>
            <input v-model="myCompany.bank_code" class="form-control" />
          </div>
        </div>
      </div>
    </div>
  </div>

  <div class="card mb-4">
    <div class="card-header">Items</div>
    <div class="card-body">
      <table class="table table-bordered">
        <thead>
          <tr>
            <th>Description</th>
            <th>Qty / H:M</th>
            <th>Unit / Hr Price</th>
            <th>Line (Net/Gross)</th>
            <th>Actions</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="(line,index) in invoice.items" :key="index" :class="{'table-warning': lineMonthMismatch(line)}">
            <td class="col-6">
              <input type="text" list="services-list" v-model="line.description"
                @input="onSelectService(line)" class="form-control" />
              <input class="form-check-input" type="checkbox" v-model="line.specify_month"
                @change="onToggleMonth(line)" :id="`specify-month-${index}`">
              <label class="form-check-label fs-6" :for="`specify-month-${index}`">Specify month</label>
              <div v-if="lineMonthMismatch(line)" class="text-warning small mt-1">
                Specified month does not match invoice month of service {{ invoice.month_service }}.
              </div>
            </td>
            <td class="col-2">
              <div v-if="line.time_based" class="d-flex">
                <input type="number" v-model.number="line.hours" class="form-control" @input="onTimeChange(line)" style="flex: 0 1 80px;">
                <span class="me-1 align-self-center">h</span>
                <input type="number" v-model.number="line.minutes" class="form-control" @input="onTimeChange(line)" style="flex: 0 1 80px;">
                <span class="align-self-center">m</span>
              </div>
              <div v-else>
                <input type="number" v-model.number="line.quantity" class="form-control" @input="calculateTotals" />
              </div>
              <input class="form-check-input" type="checkbox" v-model="line.time_based"
                @change="onToggleTimeBased(line)" :id="`time-based-${index}`">
              <label class="form-check-label fs-6" :for="`time-based-${index}`">Time-based</label>
            </td>
            <td class="col-1"><input type="number" v-model.number="line.unit_price"
                class="form-control" @input="calculateTotals" style="flex: 0 1 80px;" /></td>
            <td class="col-2">{{ formatCurrency(line.subtotal, invoice.currency) }} / {{ formatCurrency(line.subtotal * (1 + invoice.vat_rate/100), invoice.currency) }}</td>
            <td class="col-1"><button class="btn btn-sm btn-danger"
                @click="removeLine(index)"><i class="bi bi-dash-square"></i> Remove</button></td>
          </tr>
        </tbody>
      </table>
      <datalist id="services-list">
        <option v-for="s in filteredServices" :value="s.description"></option>
      </datalist>
      <button class="btn btn-sm btn-secondary" @click="addLine"><i class="bi bi-plus-square"></i> Add Line</button>
    </div>
  </div>

  <div class="card mb-4">
    <div class="card-header">Summary & Actions</div>
    <div class="card-body">
      <div class="row">
        <div class="col-md-6">
          <div class="mb-3">
            <p>Subtotal: {{ formatCurrency(totals.subtotal, invoice.currency) }}</p>
            <p>Tax: {{ formatCurrency(totals.tax, invoice.currency) }}</p>
            <p>Total: {{ formatCurrency(totals.total, invoice.currency) }}</p>
          </div>


        </div>
        <div class="col-md-6">
          <div class="mb-3">
            <label class="form-label">Currency</label>
            <select v-model="invoice.currency" class="form-select" disabled>
              <option v-for="c in currencies" :value="c">{{ c }}</option>
            </select>
          </div>
          <div class="mb-3"><label class="form-label">Template</label>
            <select v-model="selectedTemplate" class="form-select">
              <option v-for="t in templates" :value="t">{{ t }}</option>
            </select>
          </div>
        </div>
        <div class="row">
          <div class="mb-3">
        <button class="btn btn-success" @click="generatePreview()">
          <i class="bi bi-file-earmark-text"></i> Preview Invoice
        </button>
        <button class="btn btn-primary ms-2" @click="saveInvoice()">
          <i class="bi bi-floppy"></i> Save to db
        </button>
        <button class="btn btn-outline-primary ms-2" @click="printInvoice()">
          <i class="bi bi-filetype-pdf"></i>
          {{ historyFiles.length ? 'Print as PDF (the latest version)' : 'Save + Print as PDF' }}
        </button>
          </div>
        </div>
      </div>
    </div>
  </div>

  <div class="card mb-4">
    <div class="card-header">Preview</div>
    <div class="card-body p-0">
      <iframe
        id="invoice-preview-iframe"
        style="width:100%; height:600px; border:none;"
        sandbox="allow-same-origin allow-scripts allow-modals"></iframe>
    </div>
  </div>

  <div v-if="invoice.id && historyEntries.length" class="card mb-4">
    <div class="card-header">History</div>
    <div class="card-body">
      <table class="table table-sm">
        <thead>
          <tr>
            <th>Date</th>
            <th>Customer</th>
            <th>Invoice #</th>
            <th>Time</th>
            <th>View</th>
            <th>Print</th>
            <th>Delete</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="h in historyEntries" :key="h.file">
            <td>{{ h.date }}</td>
            <td>{{ h.customer }}</td>
            <td>{{ h.invoice }}</td>
            <td>{{ h.time }}</td>
            <td>
              <a :href="'view_invoice_history.php?file=' + encodeURIComponent(h.file)"
                target="_blank"
                class="btn btn-sm btn-outline-primary file-viewer">
                <i class="bi bi-arrow-up-right-square"></i>
                View
              </a>
            </td>
            <td>
              <button type="button"
                class="btn btn-sm btn-outline-secondary"
                @click="printHistory(h.file)">
                <i class="bi bi-filetype-pdf"></i>

                Print to PDF
              </button>
            </td>
            <td>
              <button type="button"
                class="btn btn-sm btn-danger"
                @click="deleteHistory(h.file)">
                <i class="bi bi-trash"></i>
                Delete
              </button>
            </td>
          </tr>
        </tbody>
      </table>
    </div>
  </div>

<div class="card mb-4">
  <div class="card-header">Signed PDF</div>
  <div class="card-body">
    <?php if ($uploadMsg): ?>
      <div class="alert alert-info"><?= htmlspecialchars($uploadMsg) ?></div>
    <?php endif; ?>

    <?php if ($signedFile): ?>
      <p>
        Signed PDF:
        <a href="/view_signed_pdf.php?file=<?= urlencode($signedFile) ?>" target="_blank" class="file-viewer">
          <?= htmlspecialchars($signedFile) ?>
        </a>
        <form method="POST" action="delete_signed_pdf.php" class="d-inline ms-3">
          <input type="hidden" name="invoice_id" value="<?= htmlspecialchars($invId) ?>">
          <button type="submit" class="btn btn-sm btn-danger" onclick="return confirm('Delete signed PDF?');">
            Delete
          </button>
        </form>
      </p>
    <?php else: ?>
      <p>No signed PDF uploaded yet.</p>
    <?php endif; ?>

    <form method="POST" action="upload_signed_pdf.php" enctype="multipart/form-data">
      <input type="hidden" name="invoice_id" value="<?= htmlspecialchars($invId) ?>">
      <div class="mb-3">
        <label class="form-label" for="signed_pdf">Upload signed PDF</label>
        <input type="file" name="signed_pdf" id="signed_pdf" accept="application/pdf" class="form-control" required>
      </div>
      <button type="submit" class="btn btn-primary">
        <i class="bi bi-cloud-arrow-up"></i> Upload {{ invoice.invoice_number }}
      </button>
    </form>
  </div>
</div>


</div>
<script>
  const {
    createApp
  } = Vue;
  createApp({
    data() {
        return {
        customer: {
          id: null,
          name: ''
        },
        invoice: {
          id: null,
          customer_id: null,
          company_id: null,
          contract_id: null,
          invoice_number: '',
          date: '',
          month_service: '',
          status: 'unpaid',
          currency: 'USD',
          vat_rate: 23,
          items: []
        },
        prevStatus: 'unpaid',
        myCompanies: [],
        selectedCompany: null,
        myCompany: {
          name: '',
          company: '',
          id_number: '',
          regon_krs_number: '',
          regon_number: '',
          vat_number: '',
          website: '',
          email: '',
          phone: '',
          address: '',
          city: '',
          postal_code: '',
          country: '',
          bank_name: '',
          bank_account: '',
          bank_code: ''
        },
        services: [],
        currencies: [],
        contracts: [],
        templates: <?= json_encode($templates) ?>,
        selectedTemplate: <?= !empty($templates) ? json_encode($templates[0]) : "''" ?>,
        historyFiles: <?= json_encode($historyFiles) ?>,
        totals: {
          subtotal: 0,
          tax: 0,
          total: 0
        },
        previewHtml: '',
        returnTo: '',
        existingInvoices: []
      };
    },
    methods: {
      onStatusChange() {
        const newStatus = this.invoice.status;
        const prev = this.prevStatus;
        if (newStatus === prev) return;
        if (!confirm(`Mark invoice ${this.invoice.invoice_number} as ${newStatus}?`)) {
          this.invoice.status = prev;
          return;
        }
        fetch('/api/invoice_status.php', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({ id: this.invoice.id, status: newStatus })
        })
          .then(r => r.json())
          .then(() => { this.prevStatus = newStatus; })
          .catch(() => { this.invoice.status = prev; });
      },
      fetchCurrencies() {
        fetch('/api/currencies.php').then(r => r.json()).then(data => this.currencies = data);
      },
      fetchContracts() {
        const cid = this.invoice.customer_id;
        const params = new URLSearchParams({ customer_id: cid });
        fetch('/api/contracts.php?' + params)
          .then(r => r.json())
          .then(data => { this.contracts = data; });
      },
      fetch() {
        const params = new URLSearchParams(window.location.search);
        const cid = params.get('customer_id');
        const invId = params.get('invoice_id');
        const copyFrom = params.get('copy_from');
        const returnTo = params.get('return_to');
        this.returnTo = returnTo;
        this.invoice.customer_id = cid;
        this.fetchContracts();
        
        if (cid) {
          fetch('/api/invoices.php?customer_id=' + cid)
            .then(r => r.json())
            .then(list => { this.existingInvoices = list; });
        }

        // Fetch services
        fetch('/api/services.php').then(r => r.json()).then(svcs => {
          this.services = svcs;
        });

        if (cid) {
          fetch('/api/customers.php?id=' + cid)
            .then(r => r.json())
            .then(c => {
              if (c) {
                this.customer = c;
                if (!invId && !copyFrom && c.currency) {
                  this.invoice.currency = c.currency;
                }
              }
            });
        }

        // Fetch companies first, then handle invoice load or initialize new invoice
        fetch('/api/companies.php').then(r => r.json()).then(cs => {
          this.myCompanies = cs;
          if (!invId && !copyFrom) {
            const matches = cs.filter(c => c.currency === this.invoice.currency);
            if (matches.length) {
              this.selectedCompany = matches[0];
              this.myCompany = {
                ...matches[0]
              };
              this.invoice.company_id = matches[0].id;
            }
          }
          if (copyFrom) {
            fetch('/api/invoices.php?id=' + copyFrom)
            .then(r => r.json()).then(data => {
                this.invoice = data;
                this.prevStatus = data.status;
                // restore saved template or default
                if (data.template && this.templates.includes(data.template)) {
                  this.selectedTemplate = data.template;
                }
                this.invoice.id = null;
                this.invoice.customer_id = cid;
                this.invoice.company_id = data.company_id;
                this.myCompany = data.company_details || this.myCompany;
                this.selectedCompany = this.myCompanies.find(c => c.id == this.invoice.company_id) || this.selectedCompany;
                this.invoice.items = data.items || [];
                // initialize month checkbox (preserving any existing month placeholder)
                this.invoice.items.forEach(line => {
                  const match = line.description.match(/\s\[[0-9]{2}\.[0-9]{4}\]$/);
                  if (match) {
                    line.specify_month = true;
                  } else {
                    line.specify_month = false;
                  }
                  line.time_based = !!line.time_based;
                  if (line.time_based) {
                    const qty = line.quantity || 0;
                    line.hours = Math.floor(qty);
                    line.minutes = Math.round((qty - line.hours) * 60);
                  } else {
                    line.hours = 0;
                    line.minutes = 0;
                  }
                });
                this.setMonthService();
                this.calculateTotals();
                fetch('/api/customers.php?id=' + this.invoice.customer_id)
                  .then(r => r.json())
                  .then(c2 => {
                    if (c2) this.customer = c2;
                  });
              });
          } else if (invId) {
            fetch('/api/invoices.php?id=' + invId)
            .then(r => r.json()).then(data => {
                this.invoice = data;
                this.prevStatus = data.status;
                // select saved template or fallback to default
                if (data.template && this.templates.includes(data.template)) {
                  this.selectedTemplate = data.template;
                } else {
                  this.selectedTemplate = this.templates[0] || '';
                }
                this.invoice.company_id = data.company_id;
                this.myCompany = data.company_details || this.myCompany;
                this.selectedCompany = this.myCompanies.find(c => c.id == this.invoice.company_id) || this.selectedCompany;
                this.invoice.items = data.items || [];
                // initialize month checkbox (preserving any existing month placeholder)
                this.invoice.items.forEach(line => {
                  const match = line.description.match(/\s\[[0-9]{2}\.[0-9]{4}\]$/);
                  if (match) {
                    line.specify_month = true;
                  } else {
                    line.specify_month = false;
                  }
                  line.time_based = !!line.time_based;
                  if (line.time_based) {
                    const qty = line.quantity || 0;
                    line.hours = Math.floor(qty);
                    line.minutes = Math.round((qty - line.hours) * 60);
                  } else {
                    line.hours = 0;
                    line.minutes = 0;
                  }
                });
                this.setMonthService();
                this.calculateTotals();
                this.fetchHistory();
                fetch('/api/customers.php?id=' + this.invoice.customer_id)
                  .then(r => r.json())
                  .then(c2 => {
                    if (c2) this.customer = c2;
                  });
              });
          } else {
            // New invoice default date and lines
            this.invoice.date = new Date().toISOString().substr(0, 10);
            this.addLine();
          }
        });
      },
      fetchHistory() {
        if (this.invoice.id) {
          fetch(`/api/invoice_history.php?invoice_id=${this.invoice.id}`)
            .then(r => r.json())
            .then(data => {
              this.historyFiles = data;
            });
        }
      },
      deleteHistory(file) {
        if (!confirm('Delete this history file?')) return;
        fetch(
            `/api/invoice_history.php?invoice_id=${this.invoice.id}&file=${encodeURIComponent(file)}`, {
              method: 'DELETE'
            }
          )
          .then(r => r.json())
          .then(data => {
            if (data.success) {
              this.fetchHistory();
            } else {
              alert('Failed to delete history file: ' + (data.error || ''));
            }
          });
      },
      addLine() {
        this.invoice.items.push({
          description: '',
          unit_price: 0,
          quantity: 1,
          subtotal: 0,
          specify_month: false,
          time_based: false,
          hours: 0,
          minutes: 0
        });
      },
      removeLine(i) {
        this.invoice.items.splice(i, 1);
        this.calculateTotals();
      },
      calculateTotals() {
        let sub = 0;
        this.invoice.items.forEach(l => {
          l.subtotal = l.unit_price * l.quantity;
          sub += l.subtotal;
        });
        const tax = sub * (this.invoice.vat_rate / 100);
        this.totals = {
          subtotal: sub,
          tax: tax,
          total: sub + tax
        };
      },
      formatCurrency(v, c) {
        return parseFloat(v).toFixed(2) + ' ' + c;
      },
      loadCompany() {
        if (this.selectedCompany) {
          this.myCompany = {
            ...this.selectedCompany
          };
          this.invoice.company_id = this.selectedCompany.id;
        }
      },
      onSelectService(line) {
        const svc = this.services.find(s => s.description === line.description && s.currency === this.invoice.currency);
        if (svc) {
          line.unit_price = svc.unit_price;
          this.calculateTotals();
        }
      },
      setInvoiceNumber() {
        if (!this.invoice.date || !this.customer.name) return;
        const slug = this.customer.name.trim().substr(0, 3).toUpperCase();
        const datePart = this.invoice.date.replace(/-/g, '');
        this.invoice.invoice_number = `${slug}-${datePart}`;
      },
      setMonthService() {
        if (!this.invoice.date) return;
        const invoiceDate = new Date(this.invoice.date);
        if (invoiceDate.getDate() < 15) {
          invoiceDate.setMonth(invoiceDate.getMonth() - 1);
        }
        const mm = String(invoiceDate.getMonth() + 1).padStart(2, '0');
        const yyyy = invoiceDate.getFullYear();
        const ms = `${mm}.${yyyy}`;
        this.invoice.month_service = ms;
        //      this.invoice.items.forEach(line => {
        //        if (line.specify_month) {
        //          line.description = line.description.replace(/\s\[[0-9]{2}\.[0-9]{4}\]$/, '');
        //          line.description = `${line.description.trim()} [${ms}]`;
        //        }
        //      });
      },
      lineMonthMismatch(line) {
        const m = line.description.match(/\[([0-9]{2}\.[0-9]{4})\]/);
        return m && m[1] !== this.invoice.month_service;
      },
      onToggleMonth(line) {
        if (line.specify_month) {
          line.description = `${line.description.trim()} [${this.invoice.month_service}]`;
        } else {
          line.description = line.description.replace(/\s\[[0-9]{2}\.[0-9]{4}\]$/, '');
        }
      },
      onToggleTimeBased(line) {
        if (line.time_based) {
          const qty = line.quantity || 0;
          line.hours = Math.floor(qty);
          line.minutes = Math.round((qty - line.hours) * 60);
        } else {
          line.quantity = (line.hours || 0) + (line.minutes || 0) / 60;
        }
        this.calculateTotals();
      },
      onTimeChange(line) {
        const h = line.hours || 0;
        const m = line.minutes || 0;
        line.quantity = h + m / 60;
        this.calculateTotals();
      },
      async generatePreview() {
        this.calculateTotals();
        this.invoice.total = this.formatCurrency(this.totals.total, this.invoice.currency);
        this.invoice.balance_due = this.formatCurrency(this.invoice.status === 'paid' ? 0 : this.totals.total, this.invoice.currency);
        const resp = await fetch(`/templates/${this.selectedTemplate}`);
        const text = await resp.text();
        const parser = new DOMParser();
        const doc = parser.parseFromString(text, 'text/html');
        // Load month mapping and remove from template
        const mappingEl = doc.getElementById('month-mapping');
        const monthMapping = mappingEl ? JSON.parse(mappingEl.textContent) : {};
        if (mappingEl) mappingEl.remove();
        // Fill static fields
        doc.querySelectorAll('[data-ref]').forEach(el => {
          const ref = el.getAttribute('data-ref');
          if (!ref.includes('-')) return;
          const key = ref.split('-').slice(1).join('-');
          let value, group, prop;
          if (key.startsWith('company.')) {
            group = 'company';
            prop = key.replace('company.', '');
            switch (prop) {
              case 'address1':
                value = this.myCompany.address;
                break;
              case 'city_state_postal':
                value = this.myCompany.postal_code + ', ' + this.myCompany.city;
                break;
              default:
                value = this.myCompany[prop];
            }
          } else if (key.startsWith('client.') || key === 'contact.email') {
            group = 'client';
            prop = key.replace('client.', '');
            switch (prop) {
              case 'number':
                value = this.customer.id_number;
                break;
              case 'vat_number':
                value = this.customer.vat_number || '';
                break;
              case 'address1':
                value = this.customer.address;
                break;
              case 'city_state_postal':
                value = this.customer.postal_code + ', ' + this.customer.city;
                break;
              case 'country':
                value = this.customer.country;
                break;
              case 'contact.email':
                value = this.customer.email;
                break;
              default:
                value = this.customer[prop];
            }
          } else if (key.startsWith('invoice.')) {
            prop = key.replace('invoice.', '');
            switch (prop) {
              case 'number':
                value = this.invoice.invoice_number;
                break;
              default:
                value = this.invoice[prop];
            }
          }
          if (value != null) {
            if (group && this.customer.prefixes) {
              const pfx = this.customer.prefixes.find(p => p.entity === group && p.property === prop);
              if (pfx && value.toString().trim() !== '') {
                value = pfx.prefix + ' ' + value;
              }
            }
            el.textContent = value;
          }
        });
        if (this.myCompany.logo) {
          const logoEl = doc.querySelector('.company-logo');
          if (logoEl) {
            logoEl.src = this.myCompany.logo;
            logoEl.alt = this.myCompany.name + ' logo';
          }
        }
        if (this.customer.logo) {
          const clientLogoEl = doc.querySelector('.client-logo');
          if (clientLogoEl) {
            clientLogoEl.src = this.customer.logo;
            clientLogoEl.alt = this.customer.name + ' logo';
          }
        }
        // Line items
        const tbody = doc.querySelector('#product-table tbody');
        if (tbody) {
          const rowTpl = tbody.querySelector('tr');
          if (rowTpl) {
            tbody.innerHTML = '';
            let it = 1;
            this.invoice.items.forEach(line => {
              const row = rowTpl.cloneNode(true);
              // Replace month placeholder with localized text
              let desc = line.description;
              desc = desc.replace(/\[([0-9]{2})\.([0-9]{4})\]/, (match, mm, yyyy) => {
                const prefix = monthMapping[mm] || '';
                return prefix ? `${prefix} ${yyyy}` : match;
              });
              let el = row.querySelector('[data-ref="product_table-product.item-number"]');
              if (el) el.textContent = it;
              el = row.querySelector('[data-ref="product_table-product.item-td"]');
              if (el) el.textContent = desc;
              el = row.querySelector('[data-ref="product_table-product.unit_cost-td"]');
              if (el) el.textContent = this.formatCurrency(line.unit_price, this.invoice.currency);
              {
                const qtyTd = row.querySelector('[data-ref="product_table-product.quantity-td"]');
                if (qtyTd) {
                  if (line.time_based) {
                    const h = Math.floor(line.quantity);
                    const m = Math.round((line.quantity - h) * 60);
                    qtyTd.textContent = `${h}h ${m}m`;
                  } else {
                    qtyTd.textContent = line.quantity;
                  }
                }
              }
              el = row.querySelector('[data-ref="product_table-product.line_total-td"]');
              if (el) el.textContent = this.formatCurrency(line.subtotal, this.invoice.currency);
              el = row.querySelector('[data-ref="product_table-product.tax1-td"]');
              if (el) el.textContent = this.invoice.vat_rate + '%';
              el = row.querySelector('[data-ref="product_table-product.tax_amount-td"]');
              if (el) el.textContent = this.formatCurrency(line.subtotal * (this.invoice.vat_rate / 100), this.invoice.currency);
              el = row.querySelector('[data-ref="product_table-product.gross_line_total-td"]');
              if (el) el.textContent = this.formatCurrency(line.subtotal * (1 + this.invoice.vat_rate / 100), this.invoice.currency);
              tbody.appendChild(row);
              it++;
            });
          }
        }
        // Totals
        let el = doc.querySelector('[data-ref="totals_table-net_subtotal"]');
        if (el) el.textContent = this.formatCurrency(this.totals.subtotal, this.invoice.currency);
        el = doc.querySelector('[data-ref="totals_table-subtotal"]');
        if (el) el.textContent = this.formatCurrency(this.totals.subtotal, this.invoice.currency);
        el = doc.querySelector('[data-ref="totals-table-line_tax_0"]');
        if (el) el.textContent = this.formatCurrency(this.totals.tax, this.invoice.currency);
        el = doc.querySelector('[data-ref="totals_table-total"]');
        if (el) el.textContent = this.formatCurrency(this.totals.total, this.invoice.currency);
        el = doc.querySelector('[data-ref="totals_table-outstanding"]');
        if (el) el.textContent = this.formatCurrency(this.invoice.status === 'paid' ? 0 : this.totals.total, this.invoice.currency);
        const paidTo = this.invoice.status === 'paid' ? this.totals.total : 0;
        el = doc.querySelector('[data-ref="totals_table-paid_to_date"]');
        if (el) el.textContent = this.formatCurrency(paidTo, this.invoice.currency);
        const stamp = doc.querySelector('.stamp');
        if (stamp) stamp.style.display = this.invoice.status === 'paid' ? 'block' : 'none';
        // Inject a <title> matching the invoice number for proper Save As filename (without ".html").
        const titleText = this.invoice.date + "_" + this.customer.name + "_" + this.invoice.invoice_number || '';
        const titleEl = document.querySelector('title');
        if (titleEl) {
          titleEl.textContent = titleText;
        } else {
          const head = document.querySelector('head');
          if (head) head.insertAdjacentHTML('afterbegin', `<title>${titleText}</title>`);
        }
        const html = doc.documentElement.innerHTML;
        this.previewHtml = html;
        const frame = document.getElementById('invoice-preview-iframe');
        const frameDoc = frame.contentDocument || frame.contentWindow.document;
        frameDoc.open();
        frameDoc.write(html);
        frameDoc.close();
      },
      async printInvoice() {
        if (this.historyFiles.length) {
          this.printHistory(this.historyFiles[0]);
          return;
        }
        await this.saveInvoice(true);
        await this.fetchHistory();
        if (this.historyFiles.length) {
          this.printHistory(this.historyFiles[0]);
        } else {
          const frame = document.getElementById('invoice-preview-iframe');
          if (frame && frame.contentWindow) {
            frame.contentWindow.focus();
            frame.contentWindow.print();
          }
        }
      },
      async printHistory(file) {
        const url = 'view_invoice_history.php?file=' + encodeURIComponent(file);
        const win = window.open(url, '_blank');
        if (win) {
          win.focus();
          win.print();
        }
      },

      async saveInvoice(skipRedirect = false) {
        // Always re-generate preview and totals before saving
        await this.generatePreview();
        this.calculateTotals();
        const payload = Object.assign({}, this.invoice, {
          template: this.selectedTemplate,
          items: this.invoice.items,
          company_id: this.invoice.company_id,
          company_details: this.myCompany,
          customer_name: this.customer.name,
          subtotal: this.totals.subtotal,
          tax: this.totals.tax,
          total: this.totals.total,
          preview_html: this.previewHtml
        });
        const method = this.invoice.id ? 'PUT' : 'POST';
        let data;
        try {
          const resp = await fetch('/api/invoices.php', {
            method,
            headers: {
              'Content-Type': 'application/json'
            },
            body: JSON.stringify(payload)
          });
          data = await resp.json();
        } catch (e) {
          alert('Error saving invoice: ' + e.message);
          return;
        }
        const wasNew = !this.invoice.id;
        if (data.id) this.invoice.id = data.id;
        const msgs = [];
        msgs.push(data.db_saved ? 'Saved to DB' : 'Failed saving to DB');
        if (typeof data.file_saved !== 'undefined') {
          msgs.push(data.file_saved ? 'Saved HTML snapshot' : 'Failed saving HTML snapshot');
        }
        if (data.file_error) {
          msgs.push('Error: ' + data.file_error);
        }
        alert(msgs.join("\n"));
        if (data.id && wasNew && !skipRedirect) {
          const cid = this.invoice.customer_id;
          window.location.href = `invoice_form.php?customer_id=${cid}&invoice_id=${data.id}`;
          return data;
        }
        this.fetchHistory();
        return data;
      }
    },
    watch: {
      'invoice.date': ['setInvoiceNumber', 'setMonthService'],
      'customer.name': 'setInvoiceNumber'
    },
    computed: {
      duplicateNumber() {
        return this.existingInvoices.some(inv =>
          inv.invoice_number === this.invoice.invoice_number &&
          inv.id !== this.invoice.id
        );
      },
      duplicateDate() {
        return this.existingInvoices.some(inv =>
          inv.date === this.invoice.date &&
          inv.id !== this.invoice.id
        );
      },
      duplicateMonth() {
        return this.existingInvoices.some(inv =>
          inv.month_service === this.invoice.month_service &&
          inv.id !== this.invoice.id
        );
      },
      monthServiceValid() {
        return /^(0[1-9]|1[0-2])\.[0-9]{4}$/.test(this.invoice.month_service);
      },
      historyEntries() {
        return this.historyFiles.map(f => {
          const name = f.replace(/\.html$/, '');
          const parts = name.split('_');
          return {
            file: f,
            date: parts[0] || '',
            customer: parts[1] || '',
            invoice: parts[2] || '',
            time: parts[3] + " " + parts[4] || ''
          };
        });
      },
      filteredServices() {
        if (!this.invoice || !this.invoice.currency || !Array.isArray(this.services)) {
          return [];
        }
        return this.services.filter(s => s.currency === this.invoice.currency);
      },
      filteredCompanies() {
        if (!this.invoice || !this.invoice.currency || !Array.isArray(this.myCompanies)) {
          return [];
        }
        return this.myCompanies.filter(c => c.currency === this.invoice.currency);
      },
      backLink() {
        return this.returnTo ?
          this.returnTo :
          (this.customer.id > 0 ?
            `invoices.php?customer_id=${this.customer.id}` :
            'invoices.php');
      },
      backText() {
        if (this.returnTo) {
          const url = this.returnTo;
          const qs = url.includes('?') ? url.split('?')[1] : '';
          const p = new URLSearchParams(qs);
          if (p.has('customer_id')) {
            return `Back to ${this.customer.name} Invoices`;
          } else {
            return 'Back to All Invoices';
          }
        } else {
          return this.customer.id > 0 ?
            `Back to ${this.customer.name} Invoices` :
            'Back to All Invoices';
        }
      }
    },
    mounted() {
      this.fetchCurrencies();
      this.fetch();
    }
  }).mount('#app');
</script>
<?php include 'footer.php'; ?>