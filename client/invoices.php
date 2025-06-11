<?php include 'header.php'; ?>
<div id="app">
  <h1>Invoices</h1>
  <div class="row mb-3">
    <div class="col-md-3">
      <input type="text" v-model="searchText" class="form-control" placeholder="Search...">
    </div>
    <div class="col-md-3">
      <select v-model="filterCustomer" class="form-select">
        <option value="">All Customers</option>
        <option v-for="c in customers" :key="c.id" :value="c.id">{{ c.name }}</option>
      </select>
    </div>
    <div class="col-md-2">
      <select v-model="filterStatus" class="form-select">
        <option value="">All Statuses</option>
        <option value="paid">Paid</option>
        <option value="unpaid">Unpaid</option>
      </select>
    </div>
    <div class="col-md-2">
      <input type="date" v-model="dateFrom" class="form-control" placeholder="From date">
    </div>
    <div class="col-md-2">
      <input type="date" v-model="dateTo" class="form-control" placeholder="To date">
    </div>
  </div>
  <table class="table table-bordered table-striped">
    <thead>
      <tr>
        <th>Invoice #</th>
        <th>Customer</th>
        <th>Company</th>
        <th>Date</th>
        <th>Status</th>
        <th>Total</th>
        <th>Currency</th>
        <th>PDF</th>
      </tr>
    </thead>
    <tbody>
      <template v-for="group in groupedInvoices" :key="group.month">
        <tr class="table-secondary">
          <td colspan="8">{{ group.month }}</td>
        </tr>
        <tr v-for="inv in group.items" :key="inv.id">
          <td>{{ inv.invoice_number }}</td>
          <td>{{ inv.customer_name }}</td>
          <td>{{ inv.company_name }}</td>
          <td>{{ inv.date }}</td>
          <td>{{ inv.status }}</td>
          <td>{{ formatCurrency(inv.total, inv.currency) }}</td>
          <td>{{ inv.currency }}</td>
          <td>
            <a
              v-if="inv.signed_file"
              :href="'/view_signed_pdf.php?file=' + encodeURIComponent(inv.signed_file)"
              target="_blank"
              class="btn btn-sm btn-outline-secondary file-viewer"
            >
              PDF
            </a>
          </td>
        </tr>
      </template>
    </tbody>
  </table>
</div>
<script>
const { createApp } = Vue;
createApp({
  data() {
    return {
      invoices: [],
      customers: [],
      searchText: '',
      filterCustomer: '',
      filterStatus: '',
      dateFrom: '',
      dateTo: ''
    };
  },
  methods: {
    fetch() {
      fetch('/api/invoices.php')
        .then(r => r.json())
        .then(data => { this.invoices = data; });
    },
    fetchCustomers() {
      fetch('/api/customers.php')
        .then(r => r.json())
        .then(data => { this.customers = data; });
    },
    formatCurrency(value, currency) {
      return parseFloat(value).toFixed(2) + ' ' + currency;
    }
  },
  computed: {
    filteredInvoices() {
      return this.invoices.filter(inv => {
        if (this.searchText) {
          const txt = this.searchText.toLowerCase();
          const all = [inv.invoice_number, inv.customer_name, inv.company_name, inv.date, inv.total, inv.currency].join(' ').toLowerCase();
          if (!all.includes(txt)) return false;
        }
        if (this.filterCustomer && inv.customer_id != this.filterCustomer) return false;
        if (this.filterStatus && inv.status !== this.filterStatus) return false;
        if (this.dateFrom && new Date(inv.date) < new Date(this.dateFrom)) return false;
        if (this.dateTo && new Date(inv.date) > new Date(this.dateTo)) return false;
        return true;
      });
    },
    groupedInvoices() {
      const months = ['January','February','March','April','May','June','July','August','September','October','November','December'];
      const map = new Map();
      this.filteredInvoices.forEach(inv => {
        const d = new Date(inv.date);
        const label = months[d.getMonth()] + ' ' + d.getFullYear();
        if (!map.has(label)) map.set(label, []);
        map.get(label).push(inv);
      });
      return Array.from(map, ([month, items]) => ({ month, items }));
    }
  },
  mounted() {
    this.fetch();
    this.fetchCustomers();
  }
}).mount('#app');
</script>
<?php include 'footer.php'; ?>