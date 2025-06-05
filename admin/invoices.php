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
                :href="'invoice_form.php?customer_id=' + (inv.customer_id || customer.id) + '&invoice_id=' + inv.id"
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
        selectedCustomerId: ''
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
        window.location.href = 'invoice_form.php?customer_id=' + this.customer.id;
      },
      extractSaveDatetime(f) {
        const name = f.replace(/\.html$/, '');
        const parts = name.split('_');
        const dtParts = parts.slice(-3, -1);
        return dtParts.join(' ');
      },
      copyInvoice(inv) {
        const params = new URLSearchParams();
        params.set('customer_id', inv.customer_id || this.customer.id);
        params.set('copy_from', inv.id);
        window.location.href = 'invoice_form.php?' + params.toString();
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
        window.location.href = 'invoice_form.php?customer_id=' + this.selectedCustomerId;
      },
      goToInvoice(inv) {
        const customerId = inv.customer_id || this.customer.id;
        window.location.href = 'invoice_form.php?customer_id=' + customerId + '&invoice_id=' + inv.id;
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
      }
    },
    mounted() {
      this.fetch();
    }
  }).mount('#app');
</script>
<?php include 'footer.php'; ?>