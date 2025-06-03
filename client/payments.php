<?php include 'header.php'; ?>
<div id="app">
  <h1>Payments</h1>
  <table class="table table-bordered table-striped">
    <thead>
      <tr>
        <th>Transaction Date</th>
        <th>Account Number</th>
        <th>Amount</th>
        <th>Currency</th>
        <th>Sender</th>
        <th>Title</th>
      </tr>
    </thead>
    <tbody>
      <tr v-for="p in payments" :key="p.id">
        <td>{{ p.transaction_date }}</td>
        <td>{{ p.account_number }}</td>
        <td>{{ formatCurrency(p.amount, p.currency) }}</td>
        <td>{{ p.currency }}</td>
        <td>{{ p.sender }}</td>
        <td>{{ p.title }}</td>
      </tr>
    </tbody>
  </table>
</div>
<script>
const { createApp } = Vue;
createApp({
  data() { return { payments: [] }; },
  methods: {
    fetch() {
      fetch('/api/payments.php')
        .then(r => r.json())
        .then(data => this.payments = data);
    },
    formatCurrency(value, currency) {
      return parseFloat(value).toFixed(2) + ' ' + currency;
    }
  },
  mounted() { this.fetch(); }
}).mount('#app');
</script>
<?php include 'footer.php'; ?>