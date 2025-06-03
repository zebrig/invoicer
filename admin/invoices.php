<?php include 'header.php'; ?>
<div id="app">
  <div class="mb-3">
    <a href="customers.php" class="btn btn-outline-secondary">
      &larr; Back to Customers
    </a>
  </div>
  <h1 v-if="allMode">All invoices</h1>
  <h1 v-else>Invoices for {{ customer.name }}</h1>
  <button v-if="!allMode" @click="newInvoice" class="btn btn-primary mb-2">New Invoice</button>
  <table class="table table-bordered table-striped">
    <thead>
      <tr>
        <th>Invoice #</th>
        <th>Date</th>
        <th>Status</th>
        <th>Total</th>
        <th>Currency</th>
        <th>PDF</th>
        <th>History</th>
        <th>Actions</th>
      </tr>
    </thead>
    <tbody>
      <template v-for="group in groupedInvoices" :key="group.month">
        <tr class="table-secondary">
          <td colspan="8">{{ group.month }}</td>
        </tr>
        <tr v-for="inv in group.items" :key="inv.id">
          <td>{{ inv.invoice_number }}</td>
          <td>{{ inv.date }}</td>
          <td>{{ inv.status }}</td>
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
          <td>
            <a
              v-for="f in inv.history"
              :key="f"
              :href="'/admin/view_invoice_history.php?file=' + encodeURIComponent(f)"
              target="_blank"
              class="btn btn-sm btn-outline-secondary"
            >
              {{ extractSaveDatetime(f) }}
            </a>
          </td>
          <td>
            <a
              :href="'invoice_form.php?customer_id=' + (inv.customer_id || customer.id) + '&invoice_id=' + inv.id"
              class="btn btn-sm btn-secondary"
            >
              View/Edit
            </a>
            <button
              @click="copyInvoice(inv)"
              class="btn btn-sm btn-outline-primary ms-1"
            >
              Copy
            </button>
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
    return { customer: { id: null, name: '' }, invoices: [], allMode: false };
  },
  methods: {
    fetch() {
      const params = new URLSearchParams(window.location.search);
      this.customer.id = params.get('customer_id') || this.customer.id;
      this.allMode = !this.customer.id;
      if (!this.allMode) {
        fetch('/api/customers.php').then(r => r.json()).then(cs => {
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
  mounted() {
    this.fetch();
  }
}).mount('#app');
</script>
<?php include 'footer.php'; ?>
