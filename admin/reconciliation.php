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
  <div class="card mb-4" v-if="currentInvoices.length">
    <div class="card-header">Executive Summary</div>
    <div class="card-body">
      <p v-if="uncoveredInvoiceNumbers.length">
        Invoices not covered by payments: <strong>{{ uncoveredInvoiceNumbers.join(', ') }}</strong>
      </p>
      <p v-else>
        All invoices are fully covered by payments.
      </p>
      <button v-if="coveredInvoiceIds.length > 0"
        class="btn btn-primary"
        @click="markCoveredPaid">
        Mark covered invoices as Paid ({{ coveredInvoiceIds.length }})
      </button>
    </div>
  </div>
  <div class="card mb-4">
    <div class="card-header">Invoices & Payments Visualization</div>
    <div class="card-body">
      <div class="mb-3">
        <label class="form-label">Min Value (cut-off): <br>{{ cutoff }} {{ currency }}</label>
        <input type="range" v-model="cutoff" :min="0" :max="maxTotal" step="1" class="form-range" />
      </div>
      <canvas id="inv-pay-chart" width="600" height="400" style="width:100%;"></canvas>
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
        customers: [],
        customer_id: '',
        startDate: '',
        endDate: '',
        templates: <?= json_encode($templates) ?>,
        selectedTemplate: <?= !empty($templates) ? json_encode($templates[0]) : "''" ?>,
        previewHtml: '',
        cutoff: 0,
        maxTotal: 0,
        currency: '',
        currentInvoices: [],
        currentPayments: [],
        coveredInvoiceIds: [],
        uncoveredInvoiceNumbers: [],
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
        // prepare visualization data and render chart
        this.currentInvoices = invoices;
        this.currentPayments = payments;
        this.currency = (invoices[0]?.currency) || (payments[0]?.currency) || '';
        const totalInv = invoices.reduce((sum, i) => sum + Number(parseFloat(i.amount).toFixed(2)), 0);
        const totalPay = payments.reduce((sum, p) => sum + Number(parseFloat(p.amount).toFixed(2)), 0);
        this.maxTotal = Math.max(totalInv, totalPay);
        if (this.cutoff > this.maxTotal) this.cutoff = 0;
        this.drawChart(invoices, payments);
        {
          let remaining = totalPay;
          const coveredAll = [];
          for (const inv of invoices) {
            const amt = Number(parseFloat(inv.amount).toFixed(2));
            if (remaining >= amt) {
              coveredAll.push(inv.id);
              remaining -= amt;
            } else {
              break;
            }
          }
          this.coveredInvoiceIds =
            invoices.filter(inv => coveredAll.includes(inv.id) && inv.status !== 'paid')
                    .map(inv => inv.id);
          this.uncoveredInvoiceNumbers =
            invoices.slice(coveredAll.length).map(i => i.invoice_number);
        }
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
        frameDoc.open();
        frameDoc.write(html);
        frameDoc.close();

      },
      drawChart(invoices, payments) {
        const canvas = document.getElementById('inv-pay-chart');
        if (!canvas) return;
        const ctx = canvas.getContext('2d');
        const width = canvas.width;
        const height = canvas.height;
        canvas.width = width;
        canvas.height = height;
        ctx.fillStyle = '#fff';
        ctx.fillRect(0, 0, width, height);
        const topPadding = 10;
        const bottomPadding = 30;
        const chartHeight = height - topPadding - bottomPadding;
        const totalInvoices = invoices.reduce((sum, i) => sum + parseFloat(i.amount), 0);
        const totalPayments = payments.reduce((sum, p) => sum + parseFloat(p.amount), 0);
        const maxTotal = Math.max(totalInvoices, totalPayments);
        const cutoff = Math.min(this.cutoff, maxTotal);
        const scale = maxTotal > cutoff ? chartHeight / (maxTotal - cutoff) : 0;
        const barWidth = 200;
        const barSpacing = barWidth / 4;
        const startX = (width - (2 * barWidth + barSpacing)) / 2;
        const invX = startX;
        const payX = startX + barWidth + barSpacing;
        let cumInv = 0;
        invoices.forEach((inv, idx) => {
          const amt = parseFloat(inv.amount);
          const prev = cumInv;
          cumInv += amt;
          if (cumInv <= cutoff) return;
          const startVal = Math.max(prev, cutoff);
          const endVal = cumInv;
          const y1 = height - bottomPadding - ((startVal - cutoff) * scale);
          const y0 = height - bottomPadding - ((endVal - cutoff) * scale);
          const h = y1 - y0;
          const color = `hsl(${idx * 360 / invoices.length},70%,50%)`;
          ctx.fillStyle = color;
          ctx.fillRect(invX, y0, barWidth, h);
          if (h > 15) {
            ctx.fillStyle = '#fff';
            ctx.font = '12px sans-serif';
            ctx.textAlign = 'center';
            ctx.textBaseline = 'middle';
            ctx.fillText(`${inv.invoice_number} ${parseFloat(inv.amount).toFixed(2)} ${inv.currency}`, invX + barWidth / 2, y0 + h / 2);
          }
        });
        let cumPay = 0;
        payments.forEach((p, idx) => {
          const amt = parseFloat(p.amount);
          const prev = cumPay;
          cumPay += amt;
          if (cumPay <= cutoff) return;
          const startVal = Math.max(prev, cutoff);
          const endVal = cumPay;
          const y1 = height - bottomPadding - ((startVal - cutoff) * scale);
          const y0 = height - bottomPadding - ((endVal - cutoff) * scale);
          const h = y1 - y0;
          const color = `hsl(${idx * 360 / payments.length},70%,50%)`;
          ctx.fillStyle = color;
          ctx.fillRect(payX, y0, barWidth, h);
          if (h > 15) {
            ctx.fillStyle = '#fff';
            ctx.font = '12px sans-serif';
            ctx.textAlign = 'center';
            ctx.textBaseline = 'middle';
            ctx.fillText(`${p.transaction_date} ${p.amount} ${p.currency}`, payX + barWidth / 2, y0 + h / 2);
          }
        });
        const invVisible = Math.max(0, totalInvoices - cutoff) * scale;
        const payVisible = Math.max(0, totalPayments - cutoff) * scale;
        const minVisible = Math.min(invVisible, payVisible);
        if (minVisible > 0) {
          const yLine = height - bottomPadding - minVisible;
          ctx.save();
          ctx.strokeStyle = '#000';
          ctx.lineWidth = 1;
          ctx.beginPath();
          ctx.moveTo(invX, yLine);
          ctx.lineTo(payX + barWidth, yLine);
          ctx.stroke();
          ctx.restore();
        }
        ctx.fillStyle = '#000';
        ctx.font = '12px sans-serif';
        ctx.textAlign = 'center';
        ctx.fillText('Invoices', invX + barWidth / 2, height - bottomPadding / 2);
        ctx.fillText('Payments', payX + barWidth / 2, height - bottomPadding / 2);
      },
      printStmt() {
        const frame = document.getElementById('stmt-preview-iframe');
        if (frame && frame.contentWindow) {
          frame.contentWindow.focus();
          frame.contentWindow.print();
        }
      },
      // Mark covered invoices as Paid
      async markCoveredPaid() {
        if (!this.coveredInvoiceIds.length) return;
        if (!confirm(`Mark ${this.coveredInvoiceIds.length} covered invoice(s) as Paid?`)) return;
        const resp = await fetch('/api/reconciliation.php?action=mark_paid', {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json'
          },
          body: JSON.stringify({
            invoice_ids: this.coveredInvoiceIds
          })
        });
        const data = await resp.json();
        if (data.success) {
          alert(`${data.marked} invoice(s) marked as Paid.`);
          this.generatePreview();
        } else {
          alert('Error marking invoices as Paid: ' + (data.error || 'Unknown error'));
        }
      }
    },
    watch: {
      cutoff() {
        this.drawChart(this.currentInvoices, this.currentPayments);
      }
    },
    mounted() {
      this.fetchCustomers();
    }
  }).mount('#app');
</script>
<?php include 'footer.php'; ?>