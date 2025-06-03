<?php include 'header.php'; ?>
<div id="app">
  <h1>Invoices</h1>
  <table class="table table-bordered table-striped">
    <thead>
      <tr>
        <th>Invoice #</th>
        <th>Date</th>
        <th>Total</th>
        <th>Currency</th>
        <th>PDF</th>
      </tr>
    </thead>
    <tbody>
      <template v-for="group in groupedInvoices" :key="group.month">
        <tr class="table-secondary">
          <td colspan="5">{{ group.month }}</td>
        </tr>
        <tr v-for="inv in group.items" :key="inv.id">
          <td>{{ inv.invoice_number }}</td>
          <td>{{ inv.date }}</td>
          <td>{{ formatCurrency(inv.total, inv.currency) }}</td>
          <td>{{ inv.currency }}</td>
          <td>
            <a
              v-if="inv.signed_file"
              :href="'/view_signed_pdf.php?file=' + encodeURIComponent(inv.signed_file)"
              target="_blank"
              class="btn btn-sm btn-outline-secondary"
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
    return { invoices: [] };
  },
  methods: {
    fetch() {
      fetch('/api/invoices.php')
        .then(r => r.json())
        .then(data => { this.invoices = data; });
    },
    formatCurrency(value, currency) {
      return parseFloat(value).toFixed(2) + ' ' + currency;
    }
  },
  computed: {
    groupedInvoices() {
      const months = ['January','February','March','April','May','June','July','August','September','October','November','December'];
      const map = new Map();
      this.invoices.forEach(inv => {
        const d = new Date(inv.date);
        const label = months[d.getMonth()] + ' ' + d.getFullYear();
        if (!map.has(label)) map.set(label, []);
        map.get(label).push(inv);
      });
      return Array.from(map, ([month, items]) => ({ month, items }));
    }
  },
  mounted() { this.fetch(); }
}).mount('#app');
</script>
<?php include 'footer.php'; ?>