<?php
require_once __DIR__ . '/header.php';

$templateFiles = glob(__DIR__ . '/../templates/invoiceemail_*.php');
$emailTemplates = [];
foreach ($templateFiles as $path) {
    $filename = basename($path);
    if (preg_match('/invoiceemail_(.+)\./', $filename, $m)) {
        $emailTemplates[] = ['code' => $m[1], 'label' => strtoupper($m[1])];
    }
}
?>
<div class="mb-3">
  <h1>Send Invoices</h1>
</div>
<div id="app">
  <div v-if="step === 1">
    <h3>Select Customers</h3>
    <button class="btn btn-sm btn-outline-primary me-2" @click="selectAllCustomers()">Select All</button>
    <button class="btn btn-sm btn-outline-secondary" @click="deselectAllCustomers()">Deselect All</button>
    <table class="table table-striped mt-3">
      <thead>
        <tr>
          <th></th>
          <th>Name</th>
          <th>Company</th>
        <th>Invoices (PDF)</th>
        </tr>
      </thead>
      <tbody>
        <tr v-for="c in customers" :key="c.id">
          <td><input type="checkbox" v-model="selectedCustomerIds" :value="c.id" :disabled="c.invoice_count === 0"></td>
          <td>{{ c.name }}</td>
          <td>{{ c.company }}</td>
          <td>{{ c.invoice_count }}</td>
        </tr>
      </tbody>
    </table>
    <button class="btn btn-primary mt-3" :disabled="!selectedCustomerIds.length" @click="proceedToInvoices()"> Next <i class="bi bi-arrow-right-square"></i></button>
  </div>

  <div v-if="step === 2">
    <button class="btn btn-secondary mb-3" @click="step=1">&larr; Back to Customers</button>
    <h3>Prepare Emails</h3>
    <p class="mb-3">Ready to send <strong>{{ totalInvoicesToSend }}</strong> invoice(s) to <strong>{{ selectedCustomerIds.length }}</strong> customer(s).</p>
    <div v-for="c in customers.filter(c => selectedCustomerIds.includes(c.id))" :key="c.id" class="card mb-4">
      <div class="card-header d-flex justify-content-between align-items-center">
        <label>
          <input type="checkbox" v-model="selectedCustomerIds" :value="c.id" @change="customerDeselected(c.id)">
          {{ c.name }} ({{ c.company }})
        </label>
        <span>{{ selectedInvoices[c.id].length }} / {{ invoicesMap[c.id].length }} selected</span>
      </div>
      <div class="card-body">
        <div class="mb-4 row row-cols-1 row-cols-md-3 g-3 align-items-start">
          <div class="col-lg-4 col-12 col-sm-12 col-md-12">
            <label class="form-label">Email Recipients</label>
            <ul class="list-group list-group-flush">
              <li v-for="(email, idx) in emailLists[c.id]" :key="idx" class="list-group-item d-flex align-items-center">
                <input v-model="emailLists[c.id][idx]" type="email" class="form-control form-control-sm me-2" placeholder="name@example.com">
                <button class="btn btn-outline-danger btn-sm" @click="emailLists[c.id].splice(idx,1)">
                  <i class="bi bi-x-lg"></i>
                </button>
              </li>
              <li v-if="!emailLists[c.id].length" class="list-group-item text-muted">
                No emails. Click "Add" to include recipients.
              </li>
            </ul>
            <button class="btn btn-outline-primary btn-sm mt-2" @click="emailLists[c.id].push('')">
              <i class="bi bi-plus-lg"></i> Add
            </button>
          </div>
          <div class="col-lg-2 col-12 col-sm-12 col-md-12 ">
            <label class="form-label">Template</label>
            <select v-model="templateSelections[c.id]" class="form-select form-select-sm">
              <option v-for="t in templates" :value="t.code">{{ t.label }}</option>
            </select><hr>
            <label class="form-label">Addressbook and template settings:</label>
              <button class="btn btn-outline-secondary btn-sm me-2" @click="restoreDefaults(c.id)">
              <i class="bi bi-arrow-counterclockwise"></i> Restore
            </button>
            <button class="btn btn-outline-primary btn-sm" @click="saveDefaults(c.id)">
              <i class="bi bi-save"></i> Save
            </button>
            
          </div>

        </div>
        <div class="mb-3">
          <hr>
          <div>
            <button class="btn btn-sm btn-outline-primary me-2" @click="selectAllInvoicesForCustomer(c.id)">Select All</button>
            <button class="btn btn-sm btn-outline-secondary" @click="deselectAllInvoicesForCustomer(c.id)">Deselect All</button>
            Invoices with PDF attached
          </div>
          <table class="table table-bordered table-striped mt-2">
            <thead>
              <tr>
                <th></th>
                <th>Date</th>
                <th>#</th>
                <th>Status</th>
                <th>Total</th>
                <th>Currency</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="inv in invoicesMap[c.id]" :key="inv.id">
                <td><input type="checkbox" v-model="selectedInvoices[c.id]" :value="inv.id"></td>
                <td>{{ inv.date }}</td>
                <td>{{ inv.invoice_number }}</td>
                <td>{{ inv.status }}</td>
                <td>{{ formatCurrency(inv.total, inv.currency) }}</td>
                <td>{{ inv.currency }}</td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
    </div>
    <button class="btn btn-success" @click="onSendClick()" :disabled="sending"><i class="bi bi-envelope-check"></i> Send</button>
  </div>
</div>

<script>
const emailTemplateCodes = <?php echo json_encode($emailTemplates); ?>;

const { createApp } = Vue;

createApp({
  data() {
    return {
      step: 1,
      customers: [],
      selectedCustomerIds: [],
      invoicesMap: {},
      selectedInvoices: {},
      emailLists: {},
      templateSelections: {},
      templates: emailTemplateCodes,
      sending: false
    };
  },
  mounted() {
    this.fetchCustomers();
  },
  computed: {
    totalInvoicesToSend() {
      return this.selectedCustomerIds.reduce((sum, id) => sum + (this.selectedInvoices[id]?.length || 0), 0);
    }
  },
  methods: {
    async fetchCustomers() {
      const resp = await fetch('/api/customers.php');
      const raw = await resp.json();
      // count only invoices that have a signed PDF
      this.customers = await Promise.all(raw.map(async c => {
        const res = await fetch(`/api/invoices.php?customer_id=${c.id}`);
        const invs = await res.json();
        const pdfCount = invs.filter(inv => inv.signed_file).length;
        return { ...c, invoice_count: pdfCount };
      }));
    },
    selectAllCustomers() {
      this.selectedCustomerIds = this.customers
        .filter(c => c.invoice_count > 0)
        .map(c => c.id);
    },
    deselectAllCustomers() {
      this.selectedCustomerIds = [];
    },
    async proceedToInvoices() {
      await this.fetchInvoices();
      this.step = 2;
    },
    customerDeselected(id) {
      if (!this.selectedCustomerIds.includes(id)) {
        delete this.invoicesMap[id];
        delete this.selectedInvoices[id];
        delete this.emailLists[id];
        delete this.templateSelections[id];
      }
    },
    async fetchInvoices() {
      const promises = this.selectedCustomerIds.map(async id => {
        const resp = await fetch(`/api/invoices.php?customer_id=${id}`);
        const raw = await resp.json();
        // Only include invoices that have a signed PDF attachment
        const invs = raw.filter(inv => inv.signed_file);
        this.invoicesMap[id] = invs;
        this.selectedInvoices[id] = invs.filter(inv => inv.status !== 'paid').map(inv => inv.id);
        const customer = this.customers.find(c => c.id === id);
        // Initialize email list and template selection from customer defaults, fallback to customer.email or first template
        let defaultEmails = [customer.email || ''];
        try {
          if (customer.default_invoice_emails) {
            const parsed = JSON.parse(customer.default_invoice_emails);
            if (parsed.length > 0) {
              defaultEmails = parsed;
            }
          }
        } catch (e) {}
        this.emailLists[id] = defaultEmails;
        const validTemplate = customer.default_invoice_template && this.templates.some(t => t.code === customer.default_invoice_template)
          ? customer.default_invoice_template
          : (this.templates.length ? this.templates[0].code : '');
        this.templateSelections[id] = validTemplate;
      });
      await Promise.all(promises);
    },
    selectAllInvoicesForCustomer(id) {
      this.selectedInvoices[id] = this.invoicesMap[id].map(inv => inv.id);
    },
    deselectAllInvoicesForCustomer(id) {
      this.selectedInvoices[id] = [];
    },
    formatCurrency(amount, currency) {
      return new Intl.NumberFormat(undefined, { style: 'currency', currency }).format(amount);
    },
    async saveDefaults(customerId) {
      const emailsJson = JSON.stringify(this.emailLists[customerId].filter(e => e));
      const template = this.templateSelections[customerId];
      const resp = await fetch('/api/customers.php', {
        method: 'PUT',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ id: customerId, default_invoice_emails: emailsJson, default_invoice_template: template })
      });
      const result = await resp.json();
      if (!result.success) {
        alert('Error saving defaults: ' + (result.error || JSON.stringify(result)));
      } else {
        alert('Defaults saved');
        const cust = this.customers.find(c => c.id === customerId);
        if (cust) {
          cust.default_invoice_emails = emailsJson;
          cust.default_invoice_template = template;
        }
      }
    },
    restoreDefaults(customerId) {
      const customer = this.customers.find(c => c.id === customerId);
      if (!customer) return;
      let defaultEmails = [customer.email || ''];
      try {
        if (customer.default_invoice_emails) {
          const parsed = JSON.parse(customer.default_invoice_emails);
          if (parsed.length > 0) {
            defaultEmails = parsed;
          }
        }
      } catch {}
      this.emailLists[customerId] = defaultEmails;
      const validTemplate = customer.default_invoice_template && this.templates.some(t => t.code === customer.default_invoice_template)
        ? customer.default_invoice_template
        : (this.templates.length ? this.templates[0].code : '');
      this.templateSelections[customerId] = validTemplate;
    },
    onSendClick() {
      if (!confirm('Are you sure you want to send invoices?')) {
        return;
      }
      const excluded = this.selectedCustomerIds.filter(id => !this.selectedInvoices[id] || this.selectedInvoices[id].length === 0);
      if (excluded.length) {
        const names = this.customers
          .filter(c => excluded.includes(c.id))
          .map(c => `${c.name} (${c.company})`);
        alert(`The following customers have no invoices selected and will be skipped:\n${names.join('\n')}`);
        this.selectedCustomerIds = this.selectedCustomerIds.filter(id => !excluded.includes(id));
        if (!this.selectedCustomerIds.length) {
          return;
        }
      }
      this.sendEmails();
    },
    async sendEmails() {
      this.sending = true;
      try {
        const payload = {
          customers: this.selectedCustomerIds.map(id => ({
            customer_id: id,
            invoice_ids: this.selectedInvoices[id] || [],
            emails: this.emailLists[id].filter(e => e),
            template: this.templateSelections[id]
          }))
        };
        const resp = await fetch('/api/send_invoices.php', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify(payload)
        });
        const result = await resp.json();
        if (result.success) {
          alert('Emails sent successfully');
          this.step = 1;
          this.selectedCustomerIds = [];
        } else {
          alert('Error sending emails: ' + (result.error || JSON.stringify(result)));
        }
      } catch (err) {
        alert('Network error: ' + err.message);
      } finally {
        this.sending = false;
      }
    }
  }
}).mount('#app');
</script>

<?php
require_once __DIR__ . '/footer.php';