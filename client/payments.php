<?php include 'header.php'; ?>
<div id="app">
  <h1>Payments</h1>
  <div class="row mb-3">
    <div class="col-md-3">
      <input type="text" v-model="filterText" class="form-control" placeholder="Search...">
    </div>
    <div class="col-md-2">
      <select v-model="filterCustomer" class="form-select">
        <option value="">All Customers</option>
        <option v-for="c in customers" :key="c.id" :value="c.id">{{ c.name }}</option>
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
        <th @click="changeSort('transaction_date')">
          Transaction Date <span v-if="sortBy==='transaction_date'">{{ sortDir==='asc' ? '▲' : '▼' }}</span>
        </th>
        <th>Account Number</th>
        <th @click="changeSort('amount')">
          Amount <span v-if="sortBy==='amount'">{{ sortDir==='asc' ? '▲' : '▼' }}</span>
        </th>
        <th>Currency</th>
        <th>Sender</th>
        <th>Title</th>
        <th>Customer</th>
      </tr>
    </thead>
    <tbody>
      <template v-for="group in groupedPayments" :key="group.month">
        <tr class="table-secondary">
          <td colspan="7">{{ group.month }}</td>
        </tr>
        <tr v-for="p in group.items" :key="p.id">
          <td>{{ p.transaction_date }}</td>
          <td>{{ p.account_number }}</td>
          <td>{{ formatCurrency(p.amount, p.currency) }}</td>
          <td>{{ p.currency }}</td>
          <td>{{ p.sender }}</td>
          <td>{{ p.title }}</td>
          <td>{{ getCustomerName(p.customer_id) }}</td>
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
      payments: [],
      customers: [],
      filterText: '',
      filterCustomer: '',
      dateFrom: '',
      dateTo: '',
      sortBy: 'transaction_date',
      sortDir: 'desc'
    };
  },
  methods: {
    fetch() {
      const params = new URLSearchParams({ sort_by: this.sortBy, sort_dir: this.sortDir });
      fetch(`/api/payments.php?${params}`)
        .then(r => r.json())
        .then(data => this.payments = data);
    },
    changeSort(field) {
      if (this.sortBy === field) {
        this.sortDir = this.sortDir === 'asc' ? 'desc' : 'asc';
      } else {
        this.sortBy = field;
        this.sortDir = 'asc';
      }
      this.fetch();
    },
    formatCurrency(value, currency) {
      return parseFloat(value).toFixed(2) + ' ' + currency;
    },
    fetchCustomers() {
      fetch('/api/customers.php')
        .then(r => r.json())
        .then(data => this.customers = data);
    },
    getCustomerName(id) {
      const c = this.customers.find(c => c.id == id);
      return c ? c.name : '';
    }
  },
  computed: {
    filteredPayments() {
      return this.payments.filter(p => {
        if (this.filterText) {
          const txt = this.filterText.toLowerCase();
          const all = [
            p.transaction_date,
            p.account_number,
            p.amount,
            p.currency,
            p.sender,
            p.title,
            this.getCustomerName(p.customer_id)
          ].join(' ').toLowerCase();
          if (!all.includes(txt)) return false;
        }
        if (this.filterCustomer && p.customer_id != this.filterCustomer) return false;
        if (this.dateFrom && new Date(p.transaction_date) < new Date(this.dateFrom)) return false;
        if (this.dateTo && new Date(p.transaction_date) > new Date(this.dateTo)) return false;
        return true;
      });
    },
    groupedPayments() {
      const months = ['January','February','March','April','May','June','July','August','September','October','November','December'];
      const map = new Map();
      this.filteredPayments.forEach(p => {
        const d = new Date(p.transaction_date);
        const label = months[d.getMonth()] + ' ' + d.getFullYear();
        if (!map.has(label)) map.set(label, []);
        map.get(label).push(p);
      });
      return Array.from(map, ([month, items]) => ({ month, items }));
    }
  },
  mounted() {
    this.fetchCustomers();
    this.fetch();
  }
}).mount('#app');
</script>
<?php include 'footer.php'; ?>