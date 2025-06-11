<?php include 'header.php'; ?>
<?php
$templateDirs = [ROOT_DIR_PATH . '/templates', PRIVATE_DIR_PATH . '/templates'];
$templates = [];
foreach ($templateDirs as $dir) {
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }
    foreach (glob("$dir/reconciliation_*.html") as $path) {
        $templates[] = basename($path);
    }
}
sort($templates);
$templates = array_values(array_unique($templates));
?>
<div id="app">
  <div class="card mb-4">
    <div class="card-header">Reconciliation Statement</div>
    <div class="card-body">
      <div class="row">
        <div class="col-md-4">
          <div class="mb-3"><label class="form-label">Customer</label>
            <select v-model="customer_id" class="form-select">
              <option value="">-- Select Customer --</option>
              <option v-for="c in customers" :key="c.id" :value="c.id">{{ c.name }}</option>
            </select>
          </div>
        </div>
        <div class="col-md-4">
          <div class="mb-3"><label class="form-label">Start Date</label>
            <input type="date" v-model="startDate" class="form-control" />
          </div>
        </div>
        <div class="col-md-4">
          <div class="mb-3"><label class="form-label">End Date</label>
            <input type="date" v-model="endDate" class="form-control" />
          </div>
        </div>
      </div>
      <div class="row">
        <div class="col-md-12">
          <div class="mb-3"><label class="form-label">Template</label>
            <select v-model="selectedTemplate" class="form-select">
              <option v-for="t in templates" :value="t">{{ t }}</option>
            </select>
          </div>
        </div>
      </div>
      <div class="row">
        <div class="mb-3">
          <button class="btn btn-success" @click="generatePreview" :disabled="!customer_id">Generate</button>
          <button class="btn btn-outline-primary ms-2" @click="printStmt">Print / Save as PDF</button>
        </div>
      </div>
    </div>
  </div>

  <div class="card mb-4">
    <div class="card-header">Preview</div>
    <div class="card-body p-0">
      <iframe id="stmt-preview-iframe" style="width:100%; height:600px; border:none;" sandbox="allow-same-origin allow-scripts allow-modals"></iframe>
    </div>
  </div>
</div>
<script>
const { createApp } = Vue;
createApp({
  data() {
    return {
      customers: [],
      customer_id: '',
      startDate: '',
      endDate: '',
      templates: <?= json_encode($templates) ?>,
      selectedTemplate: <?= !empty($templates) ? json_encode($templates[0]) : "''" ?>,
      previewHtml: ''
    };
  },
  methods: {
    fetchCustomers() {
      fetch('/api/customers.php')
        .then(r => r.json())
        .then(data => this.customers = data);
    },
    async generatePreview() {
      const params = new URLSearchParams({
        customer_id: this.customer_id,
        start_date: this.startDate,
        end_date: this.endDate
      });
      const resp = await fetch('/api/reconciliation.php?' + params);
      const result = await resp.json();
      // Update date range to actual values used (defaults if none specified)
      this.startDate = result.start_date;
      this.endDate = result.end_date;
      const payments = result.payments || [];
      const invoices = result.invoices || [];
      const tpl = await fetch(`/templates/${this.selectedTemplate}`);
      const text = await tpl.text();
      const parser = new DOMParser();
      const doc = parser.parseFromString(text, 'text/html');
      doc.querySelectorAll('[data-ref]').forEach(el => {
        const ref = el.getAttribute('data-ref');
        let value = '';
        if (ref === 'header-start_date') value = this.startDate;
        if (ref === 'header-end_date') value = this.endDate;
        if (ref === 'header-customer_name') {
          const c = this.customers.find(x => x.id == this.customer_id);
          value = c ? c.name : '';
        }
        el.textContent = value;
      });
      const txTable = doc.querySelector('#transactions-table');

      if (txTable) {
        const headerDesc = doc.querySelector('th[data-invoice-description]');
        const invoiceDesc = headerDesc ? headerDesc.getAttribute('data-invoice-description') : '';
        const paymentDesc = headerDesc ? headerDesc.getAttribute('data-payment-description') : '';
        const transactions = [];
        invoices.forEach(inv => {
          transactions.push({
            date: inv.invoice_date,
            reference: inv.invoice_number,
            description: invoiceDesc,
            amount: Number(parseFloat(inv.amount).toFixed(2)),
            currency: inv.currency
          });
        });
        payments.forEach(p => {
          transactions.push({
            date: p.transaction_date,
            reference: 'PAY-' + p.id,
            description: paymentDesc,
            amount: -Number(parseFloat(p.amount).toFixed(2)),
            currency: p.currency
          });
        });
        transactions.sort((a, b) => new Date(a.date) - new Date(b.date));
        const tb = txTable.querySelector('tbody');
        const rowTplTx = tb.querySelector('tr');
        tb.innerHTML = '';
        let bal = 0;
        transactions.forEach(tr => {
          const row = rowTplTx.cloneNode(true);
          row.querySelector('[data-ref="transactions-table-date"]').textContent = tr.date;
          row.querySelector('[data-ref="transactions-table-reference"]').textContent = tr.reference;
          row.querySelector('[data-ref="transactions-table-description"]').textContent = tr.description;
          row.querySelector('[data-ref="transactions-table-amount"]').textContent =
            (tr.amount < 0 ? '-' : '') + Math.abs(tr.amount).toFixed(2) + (tr.currency ? ' ' + tr.currency : '');
          bal += tr.amount;
          row.querySelector('[data-ref="transactions-table-balance"]').textContent =
            bal.toFixed(2) + (tr.currency ? ' ' + tr.currency : '');
          tb.appendChild(row);
        });
      }

      if (doc.querySelector('#payments-table')) {
        const tbody = doc.querySelector('#payments-table tbody');
        const rowTpl = tbody.querySelector('tr');
        tbody.innerHTML = '';
        payments.forEach(p => {
          const row = rowTpl.cloneNode(true);
          row.querySelector('[data-ref="payments-table-transaction_date"]').textContent = p.transaction_date;
          row.querySelector('[data-ref="payments-table-amount"]').textContent = parseFloat(p.amount).toFixed(2) + ' ' + p.currency;
          row.querySelector('[data-ref="payments-table-sender"]').textContent = p.sender;
          row.querySelector('[data-ref="payments-table-title"]').textContent = p.title;
          tbody.appendChild(row);
        });
      }

      if (doc.querySelector('#invoices-table')) {
        const invTbody = doc.querySelector('#invoices-table tbody');
        const invRowTpl = invTbody.querySelector('tr');
        invTbody.innerHTML = '';
        invoices.forEach(inv => {
          const row = invRowTpl.cloneNode(true);
          row.querySelector('[data-ref="invoices-table-date"]').textContent = inv.invoice_date;
          row.querySelector('[data-ref="invoices-table-number"]').textContent = inv.invoice_number;
          row.querySelector('[data-ref="invoices-table-amount"]').textContent = parseFloat(inv.amount).toFixed(2) + ' ' + inv.currency;
          invTbody.appendChild(row);
        });
      }

      const totalInvoices = invoices.reduce((sum, i) => sum + parseFloat(i.amount), 0);
      const totalPayments = payments.reduce((sum, p) => sum + parseFloat(p.amount), 0);
      const currency = (invoices[0]?.currency) || (payments[0]?.currency) || '';
      const formatVal = val => val.toFixed(2) + (currency ? ' ' + currency : '');
      const setRef = (ref, val) => {
        const el = doc.querySelector(`[data-ref="${ref}"]`);
        if (el) el.textContent = val;
      };
      setRef('summary-total_invoices', formatVal(totalInvoices));
      setRef('summary-total_payments', formatVal(totalPayments));
      setRef('summary-balance', formatVal(totalInvoices - totalPayments));

      const html = doc.documentElement.innerHTML;
      const frame = document.getElementById('stmt-preview-iframe');
      const frameDoc = frame.contentDocument || frame.contentWindow.document;
      frameDoc.open(); frameDoc.write(html); frameDoc.close();

    },
    printStmt() {
      const frame = document.getElementById('stmt-preview-iframe');
      if (frame && frame.contentWindow) {
        frame.contentWindow.focus();
        frame.contentWindow.print();
      }
    }
  },
  watch: {
    customer_id() {
      this.startDate = '';
      this.endDate = '';
    }
  },
  mounted() {
    this.fetchCustomers();
  }
}).mount('#app');
</script>
<?php include 'footer.php'; ?>