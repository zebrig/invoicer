<?php include 'header.php'; ?>
<div id="app">
  <div class="mb-3">
    <a href="customers.php" class="btn btn-outline-secondary">
      &larr; Back to Customers
    </a>
  </div>
  <h1 v-if="allMode">All invoices</h1>
  <h1 v-else>Invoices for {{ customer.name }}</h1>
  <button v-if="allMode" @click="openCustomerModal" class="btn btn-primary mb-2"><i class="bi bi-file-earmark-plus"></i> Create Invoice</button>
  <button v-if="allMode" @click="openBulkModal" class="btn btn-secondary mb-2 ms-2"><i class="bi bi-copy"></i> Bulk Copy Invoices</button>
  <button v-if="!allMode" @click="newInvoice" class="btn btn-primary mb-2"><i class="bi bi-file-earmark-plus"></i> New Invoice</button>
  <a class="btn btn-success mb-2 ms-2" href="/admin/send_invoices.php"><i class="bi bi-envelope-plus"></i> Send Invoices</a>

  <div class="row mb-3">
    <div class="col-auto">
      <label class="form-label">Start Date</label>
      <input type="date" v-model="startDate" class="form-control">
    </div>
    <div class="col-auto">
      <label class="form-label">End Date</label>
      <input type="date" v-model="endDate" class="form-control">
    </div>
    <div class="col-auto">
      <label class="form-label">Status</label>
      <select v-model="statusFilter" class="form-select">
        <option value="">All</option>
        <option value="paid">Paid</option>
        <option value="unpaid">Unpaid</option>
      </select>
    </div>
    <div class="col-auto">
      <label class="form-label">Search</label>
      <input type="text" v-model="searchQuery" class="form-control" placeholder="Filter invoices...">
    </div>
    <div class="col-auto align-self-end">
      <button class="btn btn-outline-secondary" @click="clearDateFilter()">Clear</button>
    </div>
  </div>

  <table class="table table-bordered table-striped table-hover">
    <thead>
      <tr>
        <th>Date</th>
        <th>Invoice #</th>
        <th v-if="allMode">Customer</th>
        <th>Actions</th>
        <th>Status</th>
        <th>Total</th>
        <th>Currency</th>
        <th>PDF</th>
        <th>History</th>
      </tr>
    </thead>
    <tbody>
      <template v-for="group in groupedInvoices" :key="group.month">
        <tr class="table-secondary">
          <td colspan="9">{{ group.month }}</td>
        </tr>
        <tr v-for="inv in group.items" :key="inv.id" @click="goToInvoice(inv)" style="cursor:pointer">
          <td>{{ inv.date }}</td>
          <td>{{ inv.invoice_number }}</td>
          <td v-if="allMode">{{ findCustomerName(inv.customer_id) }}</td>
          <td>
            <div class="btn-group-vertical">
              <a
                :href="`invoice_form.php?customer_id=${inv.customer_id || customer.id}&invoice_id=${inv.id}&return_to=${currentReturnTo}`"
                class="btn btn-sm btn-secondary"
                @click.stop
                title="View/Edit">
                <i class="bi bi-pencil me-1"></i> View/Edit
              </a>
              <button
                @click.stop="copyInvoice(inv)"
                class="btn btn-sm btn-outline-primary"
                title="Copy">
                <i class="bi bi-clipboard me-1"></i> Copy
              </button>
              <button
                @click.stop="deleteInvoice(inv)"
                class="btn btn-sm btn-outline-danger"
                title="Delete">
                <i class="bi bi-trash me-1"></i>Delete
              </button>
            </div>
          </td>
          <td>
            <select v-model="inv.status" class="form-select form-select-sm" @click.stop @change.stop="onTableStatusChange(inv)">
              <option value="unpaid">Unpaid</option>
              <option value="paid">Paid</option>
            </select>
          </td>
          <td>{{ formatCurrency(inv.total, inv.currency) }}</td>
          <td>{{ inv.currency }}</td>
          <td>
            <a
              v-if="inv.signed_file"
              :href="'/view_signed_pdf.php?file=' + encodeURIComponent(inv.signed_file)"
              target="_blank"
              class="btn btn-sm btn-outline-secondary file-viewer"
              title="PDF" @click.stop>
              <i class="bi bi-file-earmark-pdf me-1"></i>PDF
            </a>
          </td>
          <td>
            <a
              v-if="inv.history.length"
              :href="'/admin/view_invoice_history.php?file=' + encodeURIComponent(inv.history[0])"
              target="_blank"
              class="btn btn-sm btn-outline-secondary file-viewer"
              title="History" @click.stop>
              <i class="bi bi-clock-history me-1"></i><span>{{ extractSaveDatetime(inv.history[0]) }}</span>
            </a>
          </td>
        </tr>
      </template>
    </tbody>
  </table>

  <div class="mb-4">
    <h2>Invoice Summary</h2>
    <div class="row">
      <div class="col-md-4">
        <h5>Invoice Counts</h5>
        <table class="table table-sm table-bordered">
          <thead>
            <tr>
              <th>Metric</th>
              <th>All</th>
              <th v-for="currency in Object.keys(countByCurrency)" :key="currency">
                {{ currency }}
              </th>
            </tr>
          </thead>
          <tbody>
            <tr>
              <th>Total</th>
              <td>{{ countTotal }}</td>
              <td v-for="(cnt, currency) in countByCurrency" :key="currency">
                {{ cnt }}
              </td>
            </tr>
            <tr>
              <th>Paid</th>
              <td>{{ countPaid }}</td>
              <td v-for="currency in Object.keys(countByCurrency)" :key="currency">
                {{ countPaidByCurrency[currency] || 0 }}
              </td>
            </tr>
            <tr>
              <th>Unpaid</th>
              <td>{{ countUnpaid }}</td>
              <td v-for="currency in Object.keys(countByCurrency)" :key="currency">
                {{ countUnpaidByCurrency[currency] || 0 }}
              </td>
            </tr>
            <tr>
              <th>% Paid</th>
              <td>{{ countTotal ? ((countPaid / countTotal) * 100).toFixed(1) + '%' : '-' }}</td>
              <td v-for="(cnt, currency) in countByCurrency" :key="currency">
                {{ cnt ? ((countPaidByCurrency[currency] || 0) / cnt * 100).toFixed(1) + '%' : '-' }}
              </td>
            </tr>
            <tr>
              <th>% Unpaid</th>
              <td>{{ countTotal ? ((countUnpaid / countTotal) * 100).toFixed(1) + '%' : '-' }}</td>
              <td v-for="(cnt, currency) in countByCurrency" :key="currency">
                {{ cnt ? ((countUnpaidByCurrency[currency] || 0) / cnt * 100).toFixed(1) + '%' : '-' }}
              </td>
            </tr>
          </tbody>
        </table>
      </div>
      <div class="col-md-4">
        <h5>Invoice Amounts</h5>
        <table class="table table-sm table-bordered">
          <thead>
            <tr>
              <th>Metric</th>
              <th v-for="currency in Object.keys(totalByCurrency)" :key="currency">
                {{ currency }}
              </th>
            </tr>
          </thead>
          <tbody>
            <tr>
              <th>Total</th>
              <td v-for="(sum, currency) in totalByCurrency" :key="currency">
                {{ formatCurrency(sum, currency) }}
              </td>
            </tr>
            <tr>
              <th>Paid</th>
              <td v-for="(sum, currency) in totalByCurrency" :key="currency">
                {{ formatCurrency(totalPaidByCurrency[currency] || 0, currency) }}
              </td>
            </tr>
            <tr>
              <th>Unpaid</th>
              <td v-for="(sum, currency) in totalByCurrency" :key="currency">
                {{ formatCurrency(totalUnpaidByCurrency[currency] || 0, currency) }}
              </td>
            </tr>
            <tr>
              <th>% Paid</th>
              <td v-for="(sum, currency) in totalByCurrency" :key="currency">
                {{ sum ? ((totalPaidByCurrency[currency] || 0) / sum * 100).toFixed(1) + '%' : '-' }}
              </td>
            </tr>
            <tr>
              <th>% Unpaid</th>
              <td v-for="(sum, currency) in totalByCurrency" :key="currency">
                {{ sum ? ((totalUnpaidByCurrency[currency] || 0) / sum * 100).toFixed(1) + '%' : '-' }}
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>
    <div class="row">
      <h5>Charts</h5>
      <div class="col-md-6">
        <h6 class="text-center">Counts: Paid & Unpaid by Currency</h6>
        <canvas id="countChart" style="width:100%; max-height:200px;"></canvas>
      </div>
      <div class="col-md-6">
        <h6 class="text-center">Amounts: Paid vs Unpaid per Currency</h6>
        <div class="row">
          <div class="col-6 mb-3" v-for="currency in Object.keys(totalByCurrency)" :key="currency">
            <h6 class="text-center">{{ currency }}</h6>
            <canvas :id="'amountChart-' + currency" style="width:100%; max-height:200px;"></canvas>
          </div>
        </div>
      </div>
    </div>
  </div>

  <!-- Customer selection modal for creating invoice -->
  <div class="modal" tabindex="-1" ref="customerModal">
    <div class="modal-dialog">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title">Select Customer</h5>
          <button type="button" class="btn-close" @click="closeCustomerModal()"></button>
        </div>
        <div class="modal-body">
          <div class="mb-3">
            <label class="form-label">Customer</label>
            <select v-model="selectedCustomerId" class="form-select">
              <option value="" disabled>-- Select Customer --</option>
              <option v-for="c in customers" :key="c.id" :value="c.id">{{ c.name }}</option>
            </select>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" @click="closeCustomerModal()">Cancel</button>
          <button type="button" class="btn btn-primary" :disabled="!selectedCustomerId" @click="confirmNewInvoice()">Create</button>
        </div>
      </div>
    </div>
  </div>

  <!-- Bulk copy invoices modal -->
  <div class="modal" tabindex="-1" ref="bulkModal">
    <div class="modal-dialog modal-lg">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title">Bulk Copy Invoices</h5>
          <button type="button" class="btn-close" @click="closeBulkModal()"></button>
        </div>
        <div class="modal-body">
          <div v-if="bulk.step === 1">
            <div class="mb-3">
              <label class="form-label">Source Month</label>
              <input type="month" v-model="bulk.sourceMonth" class="form-control">
            </div>
          </div>
          <div v-else>
            <div class="mb-3">
              <button class="btn btn-sm btn-outline-primary me-2" @click="bulkSelectAll()">Select All</button>
              <button class="btn btn-sm btn-outline-secondary" @click="bulkDeselectAll()">Deselect All</button>
            </div>
            <table class="table table-bordered table-striped">
              <thead>
                <tr>
                  <th></th>
                  <th>Date</th>
                  <th>#</th>
                  <th>Customer</th>
                  <th>Total</th>
                  <th>Currency</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="inv in bulk.sourceInvoices" :key="inv.id">
                  <td><input type="checkbox" :value="inv.id" v-model="bulk.selectedBulkInvoices"></td>
                  <td>{{ inv.date }}</td>
                  <td>{{ inv.invoice_number }}</td>
                  <td>{{ findCustomerName(inv.customer_id) }}</td>
                  <td>{{ formatCurrency(inv.total, inv.currency) }}</td>
                  <td>{{ inv.currency }}</td>
                </tr>
              </tbody>
            </table>
            <div class="mb-3">
              <label class="form-label">Date for New Invoices</label>
              <input type="date" v-model="bulk.newDate" class="form-control">
            </div>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" @click="closeBulkModal()">Cancel</button>
          <button v-if="bulk.step === 2" type="button" class="btn btn-primary" @click="confirmBulkCopy()">Copy</button>
          <button v-if="bulk.step === 1" type="button" class="btn btn-primary" :disabled="!bulk.sourceMonth" @click="fetchBulkSourceInvoices()">Next</button>
          <button v-if="bulk.step === 2" type="button" class="btn btn-outline-secondary" @click="bulk.step = 1">Back</button>
        </div>
      </div>
    </div>
  </div>

</div>
<script>
  const { createApp } = Vue;
  let countChart = null;
  const amountCharts = {};
  createApp({
    data() {
      return {
        customer: {
          id: null,
          name: ''
        },
        invoices: [],
        allMode: false,
        customers: [],
        selectedCustomerId: '',
        bulk: {
          step: 1,
          sourceMonth: '',
          sourceInvoices: [],
          selectedBulkInvoices: [],
          newDate: ''
        },
        currentReturnTo: window.location.pathname + window.location.search,
        startDate: '',
        endDate: '',
        statusFilter: '',
        searchQuery: ''
      };
    },
    methods: {
      fetch() {
        const params = new URLSearchParams(window.location.search);
        this.customer.id = params.get('customer_id') || this.customer.id;
        this.allMode = !this.customer.id;
        if (this.allMode) {
          fetch('/api/customers.php')
            .then(r => r.json())
            .then(data => {
              this.customers = data;
            });
        } else {
          fetch('/api/customers.php')
            .then(r => r.json())
            .then(cs => {
              const c = cs.find(x => x.id == this.customer.id);
              if (c) this.customer.name = c.name;
            });
        }
        const url = '/api/invoices.php' + (this.allMode ? '' : '?customer_id=' + this.customer.id);
        fetch(url)
          .then(r => r.json()).then(data => {
            this.invoices = data;
            this.invoices.forEach(inv => inv.prevStatus = inv.status);
          });
      },
      newInvoice() {
        window.location.href = `invoice_form.php?customer_id=${this.customer.id}&return_to=${this.currentReturnTo}`;
      },
      extractSaveDatetime(f) {
        const name = f.replace(/\.html$/, '');
        const parts = name.split('_');
        const dtParts = parts.slice(-3, -1);
        return dtParts.join(' ');
      },
      copyInvoice(inv) {
        const cid = inv.customer_id || this.customer.id;
        window.location.href = `invoice_form.php?customer_id=${cid}&copy_from=${inv.id}&return_to=${this.currentReturnTo}`;
      },
      formatCurrency(value, currency) {
        return parseFloat(value).toFixed(2) + ' ' + currency;
      },
      openCustomerModal() {
        this.selectedCustomerId = '';
        new bootstrap.Modal(this.$refs.customerModal).show();
      },
      closeCustomerModal() {
        bootstrap.Modal.getInstance(this.$refs.customerModal).hide();
      },
      confirmNewInvoice() {
        window.location.href = `invoice_form.php?customer_id=${this.selectedCustomerId}&return_to=${this.currentReturnTo}`;
      },
      goToInvoice(inv) {
        const cid = inv.customer_id || this.customer.id;
        window.location.href = `invoice_form.php?customer_id=${cid}&invoice_id=${inv.id}&return_to=${this.currentReturnTo}`;
      },
      async deleteInvoice(inv) {
        if (!confirm(`Delete invoice ${inv.invoice_number}? This cannot be undone.`)) return;
        await fetch(`/api/invoices.php?id=${inv.id}`, {
          method: 'DELETE'
        });
        this.fetch();
      },
      onTableStatusChange(inv) {
        const newStatus = inv.status;
        const prev = inv.prevStatus;
        if (newStatus === prev) return;
        if (!confirm(`Mark invoice ${inv.invoice_number} as ${newStatus}?`)) {
          inv.status = prev;
          return;
        }
        fetch('/api/invoice_status.php', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({ id: inv.id, status: newStatus })
        })
          .then(r => r.json())
          .then(() => { inv.prevStatus = newStatus; })
          .catch(() => { inv.status = prev; });
      },
      openBulkModal() {
        this.bulk = {
          step: 1,
          sourceMonth: '',
          sourceInvoices: [],
          selectedBulkInvoices: [],
          newDate: ''
        };
        new bootstrap.Modal(this.$refs.bulkModal).show();
      },
      closeBulkModal() {
        bootstrap.Modal.getInstance(this.$refs.bulkModal).hide();
      },
      fetchBulkSourceInvoices() {
        const [year, month] = this.bulk.sourceMonth.split('-');
        const ms = `${month}.${year}`;
        this.bulk.sourceInvoices = this.invoices.filter(inv => inv.month_service === ms);
        this.bulk.selectedBulkInvoices = this.bulk.sourceInvoices.map(inv => inv.id);
        this.bulk.step = 2;
      },
      bulkSelectAll() {
        this.bulk.selectedBulkInvoices = this.bulk.sourceInvoices.map(inv => inv.id);
      },
      bulkDeselectAll() {
        this.bulk.selectedBulkInvoices = [];
      },
      async confirmBulkCopy() {
        if (!this.bulk.newDate) {
          alert('Please select date for new invoices.');
          return;
        }
        if (!this.bulk.selectedBulkInvoices.length) {
          alert('No invoices selected for copying.');
          return;
        }
        if (!confirm(`Copy ${this.bulk.selectedBulkInvoices.length} invoice(s) to date ${this.bulk.newDate}?`)) return;
        for (const id of this.bulk.selectedBulkInvoices) {
          const orig = await fetch(`/api/invoices.php?id=${id}`).then(r => r.json());
          const date = this.bulk.newDate;
          const cust = this.customers.find(c => c.id === orig.customer_id);
          const slug = cust?.name.trim().substr(0, 3).toUpperCase() || '';
          const datePart = date.replace(/-/g, '');
          const invoice_number = `${slug}-${datePart}`;
          const dt = new Date(date);
          if (dt.getDate() < 15) dt.setMonth(dt.getMonth() - 1);
          const mm = String(dt.getMonth() + 1).padStart(2, '0');
          const yyyy = dt.getFullYear();
          const month_service = `${mm}.${yyyy}`;
          const [om, oy] = orig.month_service.split('.').map(Number);
          const origTotal = oy * 12 + om - 1;
          const newTotal = yyyy * 12 + (Number(mm) - 1);
          const delta = newTotal - origTotal;
          const items = (orig.items || []).map(line => {
            if (line.specify_month) {
              const m = line.description.match(/\s\[([0-9]{2}\.[0-9]{4})\]$/);
              if (m) {
                const [ip, iy] = m[1].split('.').map(Number);
                const t = iy * 12 + ip - 1 + delta;
                const ny = Math.floor(t / 12);
                const nm = t % 12 + 1;
                const newMs = `${String(nm).padStart(2, '0')}.${ny}`;
                line.description = line.description.replace(/\s\[[0-9]{2}\.[0-9]{4}\]$/, ` [${newMs}]`);
              }
            }
            return line;
          });
          const payload = {
            customer_id: orig.customer_id,
            company_id: orig.company_id,
            invoice_number,
            date,
            month_service,
            status: orig.status,
            template: orig.template,
            currency: orig.currency,
            vat_rate: orig.vat_rate,
            items,
            company_details: orig.company_details,
            subtotal: orig.subtotal,
            tax: orig.tax,
            total: orig.total
          };
          await fetch('/api/invoices.php', {
            method: 'POST',
            headers: {
              'Content-Type': 'application/json'
            },
            body: JSON.stringify(payload)
          });
        }
        this.closeBulkModal();
        this.fetch();
      },
      findCustomerName(id) {
        const c = this.customers.find(x => x.id === id);
        return c ? c.name : '';
      },
      renderCountChart() {
        const ctx = document.getElementById('countChart').getContext('2d');
        const currencies = Object.keys(this.totalByCurrency);
        const pieLabels = [];
        const pieData = [];
        const pieBg = [];
        const hueStep = 360 / currencies.length;
        const offset = hueStep / 3;
        currencies.forEach((currency, idx) => {
          const invs = this.filteredInvoices.filter(inv => inv.currency === currency);
          const paidCount = invs.filter(inv => inv.status === 'paid').length;
          const unpaidCount = invs.filter(inv => inv.status !== 'paid').length;
          const hueBase = idx * hueStep;
          const paidHue = hueBase;
          const unpaidHue = (hueBase + offset) % 360;
          pieLabels.push(`${currency} Paid`);
          pieData.push(paidCount);
          pieBg.push(`hsl(${paidHue}, 70%, 50%)`);
          pieLabels.push(`${currency} Unpaid`);
          pieData.push(unpaidCount);
          pieBg.push(`hsl(${unpaidHue}, 70%, 50%)`);
        });
        const totalCount = pieData.reduce((a, b) => a + b, 0);
        if (!countChart) {
          countChart = new Chart(ctx, {
            type: 'pie',
            data: { labels: pieLabels, datasets: [{ data: pieData, backgroundColor: pieBg }] },
            options: {
              responsive: true,
              maintainAspectRatio: false,
              plugins: {
                legend: { position: 'bottom' },
                tooltip: {
                  callbacks: {
                    label: context => {
                      const value = context.parsed;
                      const perc = totalCount ? (value / totalCount * 100).toFixed(1) : '0';
                      const label = context.chart.data.labels[context.dataIndex];
                      return `${label}: ${value} (${perc}%)`;
                    }
                  }
                },
              }
            }
          });
        } else {
          countChart.data.labels = pieLabels;
          countChart.data.datasets[0].data = pieData;
          countChart.data.datasets[0].backgroundColor = pieBg;
          countChart.update();
        }
      },
      renderAmountChart() {
        const currencies = Object.keys(this.totalByCurrency);
        currencies.forEach(currency => {
          const paid = this.totalPaidByCurrency[currency] || 0;
          const unpaid = this.totalUnpaidByCurrency[currency] || 0;
          const ctx = document.getElementById(`amountChart-${currency}`).getContext('2d');
          if (!amountCharts[currency]) {
            amountCharts[currency] = new Chart(ctx, {
              type: 'bar',
              data: {
                labels: [currency],
                datasets: [
                  {
                    label: 'Paid',
                    data: [paid],
                    backgroundColor: '#28a745'
                  },
                  {
                    label: 'Unpaid',
                    data: [unpaid],
                    backgroundColor: '#dc3545'
                  }
                ]
              },
              options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: { y: { beginAtZero: true } },
                plugins: {
                  tooltip: {
                    callbacks: {
                      label: context => {
                        const value = context.parsed.y;
                        const total = paid + unpaid;
                        const perc = total ? (value / total * 100).toFixed(1) : '0';
                        return `${context.dataset.label} ${context.label}: ${value} (${perc}%)`;
                      }
                    }
                  }
                }
              }
            });
          } else {
            amountCharts[currency].data.datasets[0].data = [paid];
            amountCharts[currency].data.datasets[1].data = [unpaid];
            amountCharts[currency].update();
          }
        });
      },
      clearDateFilter() {
        this.startDate = '';
        this.endDate = '';
      }
    },
    computed: {
      filteredInvoices() {
        return this.invoices.filter(inv => {
          if (this.startDate && inv.date < this.startDate) return false;
          if (this.endDate && inv.date > this.endDate) return false;
          if (this.statusFilter === 'paid' && inv.status !== 'paid') return false;
          if (this.statusFilter === 'unpaid' && inv.status === 'paid') return false;
            if (this.searchQuery) {
            const q = this.searchQuery.trim().toLowerCase();
            const haystack = `${inv.invoice_number} ${inv.status} ${inv.currency} ${this.findCustomerName(inv.customer_id)}`.toLowerCase();
            if (!haystack.includes(q)) return false;
          }
          return true;
        });
      },
      groupedInvoices() {
        const months = ['January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December'];
        const map = new Map();
        this.filteredInvoices.forEach(inv => {
          const d = new Date(inv.date);
          const label = months[d.getMonth()] + ' ' + d.getFullYear();
          if (!map.has(label)) map.set(label, []);
          map.get(label).push(inv);
        });
        return Array.from(map, ([month, items]) => ({
          month,
          items
        }));
      },
      countPaid() {
        return this.filteredInvoices.filter(inv => inv.status === 'paid').length;
      },
      countUnpaid() {
        return this.filteredInvoices.filter(inv => inv.status !== 'paid').length;
      },
      countTotal() {
        return this.filteredInvoices.length;
      },
      countByCurrency() {
        const counts = {};
        this.filteredInvoices.forEach(inv => {
          counts[inv.currency] = (counts[inv.currency] || 0) + 1;
        });
        return counts;
      },
      countPaidByCurrency() {
        const counts = {};
        this.filteredInvoices.filter(inv => inv.status === 'paid').forEach(inv => {
          counts[inv.currency] = (counts[inv.currency] || 0) + 1;
        });
        return counts;
      },
      countUnpaidByCurrency() {
        const counts = {};
        this.filteredInvoices.filter(inv => inv.status !== 'paid').forEach(inv => {
          counts[inv.currency] = (counts[inv.currency] || 0) + 1;
        });
        return counts;
      },
      totalByCurrency() {
        const sums = {};
        this.filteredInvoices.forEach(inv => {
          sums[inv.currency] = (sums[inv.currency] || 0) + parseFloat(inv.total);
        });
        return sums;
      },
      totalPaidByCurrency() {
        const sums = {};
        this.filteredInvoices.filter(inv => inv.status === 'paid').forEach(inv => {
          sums[inv.currency] = (sums[inv.currency] || 0) + parseFloat(inv.total);
        });
        return sums;
      },
      totalUnpaidByCurrency() {
        const sums = {};
        this.filteredInvoices.filter(inv => inv.status !== 'paid').forEach(inv => {
          sums[inv.currency] = (sums[inv.currency] || 0) + parseFloat(inv.total);
        });
        return sums;
      }
    },
    watch: {
      invoices() {
        this.$nextTick(() => {
          this.renderCountChart();
          this.renderAmountChart();
        });
      },
      filteredInvoices() {
        this.$nextTick(() => {
          this.renderCountChart();
          this.renderAmountChart();
        });
      }
    },
    mounted() {
      this.fetch();
    }
  }).mount('#app');
</script>
<?php include 'footer.php'; ?>