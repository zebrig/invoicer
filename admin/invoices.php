<?php include 'header.php'; ?>
<div id="app">
  <div class="mb-3">
    <a href="customers.php" class="btn btn-outline-secondary">
      &larr; Back to Customers
    </a>
  </div>
  <h1 v-if="allMode">All invoices</h1>
  <h1 v-else>Invoices for {{ customer.name }}</h1>
  <button v-if="allMode" @click="openCustomerModal" class="btn btn-primary mb-2">Create Invoice</button>
  <button v-if="allMode" @click="openBulkModal" class="btn btn-secondary mb-2 ms-2">Bulk Copy Invoices</button>
  <button v-if="!allMode" @click="newInvoice" class="btn btn-primary mb-2">New Invoice</button>
  <table class="table table-bordered table-striped table-hover">
    <thead>
      <tr>
        <th>Date</th>
        <th>Invoice #</th>
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
          <td colspan="8">{{ group.month }}</td>
        </tr>
        <tr v-for="inv in group.items" :key="inv.id" @click="goToInvoice(inv)" style="cursor:pointer">
          <td>{{ inv.date }}</td>
          <td>{{ inv.invoice_number }}</td>
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
          <td>{{ inv.status }}</td>
          <td>{{ formatCurrency(inv.total, inv.currency) }}</td>
          <td>{{ inv.currency }}</td>
          <td>
            <a
              v-if="inv.signed_file"
              :href="'/view_signed_pdf.php?file=' + encodeURIComponent(inv.signed_file)"
              target="_blank"
              class="btn btn-sm btn-outline-secondary"
              @click.stop
              title="PDF">
              <i class="bi bi-file-earmark-pdf me-1"></i>PDF
            </a>
          </td>
          <td>
            <a
              v-if="inv.history.length"
              :href="'/admin/view_invoice_history.php?file=' + encodeURIComponent(inv.history[0])"
              target="_blank"
              class="btn btn-sm btn-outline-secondary"
              @click.stop
              title="History">
              <i class="bi bi-clock-history me-1"></i><span>{{ extractSaveDatetime(inv.history[0]) }}</span>
            </a>
          </td>
        </tr>
      </template>
    </tbody>
  </table>
  <p>Total invoices: {{ invoices.length }}</p>
  <div class="row mb-3">
    <div class="col-md-4">
      <p>Paid invoices: {{ countPaid }}</p>
      <p>Unpaid invoices: {{ countUnpaid }}</p>
    </div>
    <div class="col-md-4">
      <p>Total all invoices:</p>
      <ul class="list-unstyled mb-0">
        <li v-for="(sum, currency) in totalByCurrency" :key="currency">
          {{ currency }}: {{ formatCurrency(sum, currency) }}
        </li>
      </ul>
    </div>
    <div class="col-md-4">
      <p>Total paid invoices:</p>
      <ul class="list-unstyled mb-0">
        <li v-for="(sum, currency) in totalPaidByCurrency" :key="currency">
          {{ currency }}: {{ formatCurrency(sum, currency) }}
        </li>
      </ul>
      <p class="mt-2">Total unpaid invoices:</p>
      <ul class="list-unstyled mb-0">
        <li v-for="(sum, currency) in totalUnpaidByCurrency" :key="currency">
          {{ currency }}: {{ formatCurrency(sum, currency) }}
        </li>
      </ul>
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
        invoices: [],
        allMode: false,
        customers: [],
        selectedCustomerId: '',
        bulk: { step: 1, sourceMonth: '', sourceInvoices: [], selectedBulkInvoices: [], newDate: '' },
        currentReturnTo: window.location.pathname + window.location.search
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
        await fetch(`/api/invoices.php?id=${inv.id}`, { method: 'DELETE' });
        this.fetch();
      },
      openBulkModal() {
        this.bulk = { step: 1, sourceMonth: '', sourceInvoices: [], selectedBulkInvoices: [], newDate: '' };
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
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload)
          });
        }
        this.closeBulkModal();
        this.fetch();
      },
      findCustomerName(id) {
        const c = this.customers.find(x => x.id === id);
        return c ? c.name : '';
      }
    },
    computed: {
      groupedInvoices() {
        const months = ['January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December'];
        const map = new Map();
        this.invoices.forEach(inv => {
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
        return this.invoices.filter(inv => inv.status === 'paid').length;
      },
      countUnpaid() {
        return this.invoices.filter(inv => inv.status !== 'paid').length;
      },
      totalByCurrency() {
        const sums = {};
        this.invoices.forEach(inv => {
          sums[inv.currency] = (sums[inv.currency] || 0) + parseFloat(inv.total);
        });
        return sums;
      },
      totalPaidByCurrency() {
        const sums = {};
        this.invoices.filter(inv => inv.status === 'paid').forEach(inv => {
          sums[inv.currency] = (sums[inv.currency] || 0) + parseFloat(inv.total);
        });
        return sums;
      },
      totalUnpaidByCurrency() {
        const sums = {};
        this.invoices.filter(inv => inv.status !== 'paid').forEach(inv => {
          sums[inv.currency] = (sums[inv.currency] || 0) + parseFloat(inv.total);
        });
        return sums;
      }
    },
    mounted() {
      this.fetch();
    }
  }).mount('#app');
</script>
<?php include 'footer.php'; ?>