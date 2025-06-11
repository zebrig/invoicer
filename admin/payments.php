<?php include 'header.php'; ?>
<div id="app">
  <div class="d-flex justify-content-between align-items-center mb-3">
    <h1>Payments</h1>
    <div>
      <a href="import_payments.php" class="btn btn-primary">Import Payments</a>
      <a href="fetch_pko_messages.php" class="btn btn-secondary ms-2">Fetch PKO Messages</a>
    </div>
  </div>
  <div class="mb-3 row">
    <div class="col">
      <input type="date" class="form-control" placeholder="Date From" v-model="dateFrom">
    </div>
    <div class="col">
      <input type="date" class="form-control" placeholder="Date To" v-model="dateTo">
    </div>
  </div>
  <div class="mb-3 row">
    <div class="col">
      <select class="form-select" v-model="filterCustomer">
        <option value="">All Customers</option>
        <option v-for="c in customers" :key="c.id" :value="c.id">{{ c.name }}</option>
      </select>
    </div>
    <div class="col">
      <select class="form-select" v-model="filterCurrency">
        <option value="">All Currencies</option>
        <option v-for="c in uniqueCurrencies" :key="c" :value="c">{{ c }}</option>
      </select>
    </div>
    <div class="col">
      <input type="text" class="form-control" placeholder="Search..." v-model="filterText">
    </div>
  </div>
  <table class="table table-bordered table-striped">
    <thead>
      <tr>
        <th @click="changeSort('received_at')">
          Received At <span v-if="sortBy==='received_at'">{{ sortDir==='asc' ? '▲' : '▼' }}</span>
        </th>
        <th @click="changeSort('transaction_date')">
          Transaction Date <span v-if="sortBy==='transaction_date'">{{ sortDir==='asc' ? '▲' : '▼' }}</span>
        </th>
        <th @click="changeSort('account_number')">
          Account Number <span v-if="sortBy==='account_number'">{{ sortDir==='asc' ? '▲' : '▼' }}</span>
        </th>
        <th @click="changeSort('amount')">
          Amount <span v-if="sortBy==='amount'">{{ sortDir==='asc' ? '▲' : '▼' }}</span>
        </th>
        <th>Currency</th>
        <th @click="changeSort('sender')">
          Sender <span v-if="sortBy==='sender'">{{ sortDir==='asc' ? '▲' : '▼' }}</span>
        </th>
        <th>Title</th>
        <th>Customer</th>
      </tr>
    </thead>
    <tbody>
      <template v-for="group in groupedPayments" :key="group.month">
        <tr class="table-secondary">
          <td colspan="8">{{ group.month }}</td>
        </tr>
        <tr v-for="p in group.items" :key="p.id">
          <td>{{ p.received_at }}</td>
          <td>{{ p.transaction_date }}</td>
          <td>{{ p.account_number }}</td>
          <td>{{ formatCurrency(p.amount, p.currency) }}</td>
          <td>{{ p.currency }}</td>
          <td>{{ p.sender }}</td>
          <td>{{ p.title }}</td>
          <td>
            <select :value="p.customer_id" @change="assignCustomer(p, $event.target.value)" class="form-select form-select-sm">
              <option value="">--</option>
              <option v-for="c in customersFor(p)" :key="c.id" :value="c.id">{{ c.name }}</option>
            </select>
          </td>
        </tr>
      </template>
    </tbody>
  </table>
  <div class="mt-2">
    <strong>Totals:</strong>
    <span v-for="(sum, currency) in totalsByCurrency" :key="currency" class="ms-3">
      {{ currency }}: {{ sum.toFixed(2) }} {{ currency }}
    </span>
  </div>
</div>
<script>
const { createApp } = Vue;
createApp({
  data() {
    return {
      payments: [],
      customers: [],
      sortBy: 'transaction_date',
      sortDir: 'desc',
      filterText: '',
      filterCustomer: '',
      filterCurrency: '',
      dateFrom: '',
      dateTo: ''
    };
  },
  methods: {
    fetch() {
      const params = new URLSearchParams({
        sort_by: this.sortBy,
        sort_dir: this.sortDir
      });
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
    assignCustomer(p, customerId) {
      fetch('/api/payments.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ id: p.id, customer_id: customerId })
      })
        .then(r => r.json())
        .then(data => {
          if (!data.success) {
            alert('Failed to assign customer');
          } else {
            p.customer_id = customerId;
          }
        })
        .catch(() => {
          alert('Error assigning customer');
        });
    },
    getCustomerName(customerId) {
      const c = this.customers.find(c => c.id == customerId);
      return c ? c.name : '';
    },
    customersFor(payment) {
      return this.customers.filter(c => c.currency === payment.currency);
    }
  },
  computed: {
    filteredPayments() {
      return this.payments.filter(p => {
        // Text filter
        if (this.filterText) {
          const text = this.filterText.toLowerCase();
          const all = [
            p.received_at,
            p.transaction_date,
            p.account_number,
            p.amount,
            p.currency,
            p.sender,
            p.title,
            this.getCustomerName(p.customer_id)
          ].join(' ').toLowerCase();
          if (!all.includes(text)) {
            return false;
          }
        }
        // Customer filter
        if (this.filterCustomer) {
          if (p.customer_id != this.filterCustomer) {
            return false;
          }
        }
        // Currency filter
        if (this.filterCurrency) {
          if (p.currency !== this.filterCurrency) {
            return false;
          }
        }
        // Date range filter
        if (this.dateFrom) {
          if (new Date(p.transaction_date) < new Date(this.dateFrom)) {
            return false;
          }
        }
        if (this.dateTo) {
          if (new Date(p.transaction_date) > new Date(this.dateTo)) {
            return false;
          }
        }
        return true;
      });
    },
    groupedPayments() {
      const months = ['January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December'];
      const map = new Map();
      this.filteredPayments.forEach(p => {
        const d = new Date(p.transaction_date);
        const label = months[d.getMonth()] + ' ' + d.getFullYear();
        if (!map.has(label)) map.set(label, []);
        map.get(label).push(p);
      });
      return Array.from(map, ([month, items]) => ({ month, items }));
    },
    totalsByCurrency() {
      return this.filteredPayments.reduce((acc, p) => {
        const cur = p.currency;
        const amt = parseFloat(p.amount) || 0;
        acc[cur] = (acc[cur] || 0) + amt;
        return acc;
      }, {});
    },
    uniqueCurrencies() {
      return [...new Set(this.payments.map(p => p.currency))].sort();
    }
  },
  mounted() {
    this.fetchCustomers();
    this.fetch();
  }
}).mount('#app');
</script>
<?php include 'footer.php'; ?>