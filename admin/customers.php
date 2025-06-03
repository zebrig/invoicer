<?php include 'header.php'; ?>
<div id="app">
  <h1>Customers</h1>
  <button @click="openForm()" class="btn btn-primary mb-2">Add Customer</button>
  <table class="table table-bordered table-striped">
    <thead>
      <tr>
        <th>Logo</th><th>Name</th><th>Email</th><th>Company</th><th>ID Number</th><th>VAT Number</th><th>Website</th><th>Address</th>
        <th>City</th><th>Postal Code</th><th>Country</th><th>Phone</th><th>Currency</th><th>Actions</th>
      </tr>
    </thead>
    <tbody>
      <tr v-for="c in customers" :key="c.id">
        <td>
          <img v-if="c.logo" :src="c.logo" alt="Logo" class="img-fluid" style="max-height:50px;">
        </td>
        <td>{{ c.name }}</td><td>{{ c.email }}</td><td>{{ c.company }}</td><td>{{ c.id_number }}</td><td>{{ c.vat_number }}</td><td>{{ c.website }}</td><td>{{ c.address }}</td>
        <td>{{ c.city }}</td><td>{{ c.postal_code }}</td><td>{{ c.country }}</td><td>{{ c.phone }}</td><td>{{ c.currency }}</td>
        <td>
          <a :href="'invoices.php?customer_id=' + c.id" class="btn btn-sm btn-secondary">Invoices</a>
          <button class="btn btn-sm btn-warning" @click="edit(c)">Edit</button>
          <button v-if="c.invoice_count === 0" class="btn btn-sm btn-danger" @click="remove(c.id)">Delete</button>
        </td>
      </tr>
    </tbody>
  </table>
  <!-- Modal -->
  <div class="modal" tabindex="-1" ref="modal">
    <div class="modal-dialog">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title">{{ form.id ? 'Edit' : 'Add' }} Customer</h5>
          <button type="button" class="btn-close" @click="closeForm()"></button>
        </div>
        <div class="modal-body">
          <form @submit.prevent="save">
            <div class="mb-3"><label class="form-label">Name</label><input v-model="form.name" required class="form-control"/></div>
            <div class="mb-3"><label class="form-label">Email</label><input v-model="form.email" type="email" required class="form-control"/></div>
            <div class="mb-3"><label class="form-label">Company</label><input v-model="form.company" class="form-control"/></div>
            <div class="mb-3"><label class="form-label">ID Number</label><input v-model="form.id_number" class="form-control"/></div>
            <div class="mb-3"><label class="form-label">VAT Number</label><input v-model="form.vat_number" class="form-control"/></div>
            <div class="mb-3"><label class="form-label">Website</label><input v-model="form.website" class="form-control"/></div>
            <div class="mb-3"><label class="form-label">Address</label><input v-model="form.address" class="form-control"/></div>
            <div class="mb-3"><label class="form-label">City</label><input v-model="form.city" class="form-control"/></div>
            <div class="mb-3"><label class="form-label">Postal Code</label><input v-model="form.postal_code" class="form-control"/></div>
            <div class="mb-3"><label class="form-label">Country</label><input v-model="form.country" class="form-control"/></div>
            <div class="mb-3"><label class="form-label">Phone</label><input v-model="form.phone" class="form-control"/></div>
            <div class="mb-3">
              <label class="form-label">Currency</label>
              <select v-model="form.currency" class="form-select" required :disabled="form.id && form.invoice_count > 0">
                <option v-for="c in currencies" :key="c" :value="c">{{ c }}</option>
              </select>
            </div>
            <div class="mb-3"><label class="form-label">Logo</label><input ref="logoInput" type="file" accept=".png,.svg,.gif" @change="onLogoChange" class="form-control"/></div>
            <div class="mb-3" v-if="form.logo">
              <label class="form-label">Current Logo</label>
              <img :src="form.logo" alt="Logo Preview" class="img-fluid" style="max-height:100px;">
            </div>
            <div class="d-flex justify-content-end gap-2 mt-3">
              <button type="button" class="btn btn-secondary" @click="closeForm()">Cancel</button>
              <button type="submit" class="btn btn-primary">Save</button>
            </div>
          </form>
        </div>
      </div>
    </div>
  </div>
</div>
<script>
const { createApp } = Vue;
createApp({
  data() {
    return {
      customers: [],
      form: {},
      currencies: []
    };
  },
  methods: {
    fetch() {
      fetch('/api/customers.php').then(r => r.json()).then(data => this.customers = data);
    },
    fetchCurrencies() {
      fetch('/api/currencies.php').then(r => r.json()).then(data => this.currencies = data);
    },
    openForm() {
      this.form = { name: '', email: '', company: '', id_number: '', vat_number: '', website: '', address: '', city: '', postal_code: '', country: '', phone: '', currency: this.currencies[0], logo: '', invoice_count: 0 };
      this.$refs.logoInput.value = null;
      new bootstrap.Modal(this.$refs.modal).show();
    },
    closeForm() {
      bootstrap.Modal.getInstance(this.$refs.modal).hide();
    },
    edit(c) {
      this.form = Object.assign({}, c);
      this.$refs.logoInput.value = null;
      new bootstrap.Modal(this.$refs.modal).show();
    },
    save() {
      const method = this.form.id ? 'PUT' : 'POST';
      fetch('/api/customers.php', {
        method,
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(this.form)
      }).then(() => { this.closeForm(); this.fetch(); });
    },
    remove(id) {
      if (confirm('Delete this customer?')) {
        fetch('/api/customers.php?id=' + id, { method: 'DELETE' }).then(() => this.fetch());
      }
    },
    onLogoChange(event) {
      const file = event.target.files && event.target.files[0];
      if (!file) return;
      if (!['image/png','image/svg+xml','image/gif'].includes(file.type)) {
        alert('Logo must be PNG, SVG, or GIF');
        event.target.value = null;
        return;
      }
      if (file.size > 10 * 1024) {
        alert('Logo must be 10KB or smaller');
        event.target.value = null;
        return;
      }
      const reader = new FileReader();
      reader.onload = e => { this.form.logo = e.target.result; };
      reader.readAsDataURL(file);
    }
  },
  mounted() {
    this.fetch();
    this.fetchCurrencies();
  }
}).mount('#app');
</script>
<?php include 'footer.php'; ?>