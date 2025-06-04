<?php include 'header.php'; ?>
<div id="app">
  <h1>Customers</h1>
  <button @click="openForm()" class="btn btn-primary mb-2">Add Customer</button>
  <table class="table table-bordered table-striped">
    <thead>
      <tr>
        <th>Name</th><th>Actions</th><th>Company</th><th>Logo</th><th>Agreement</th><th>ID Number</th><th>VAT Number</th><th>Website</th><th>Address</th>
        <th>City</th><th>Postal Code</th><th>Country</th><th>Email</th><th>Phone</th><th>Currency</th>
      </tr>
    </thead>
    <tbody>
      <tr v-for="c in customers" :key="c.id">

        <td>{{ c.name }}</td>
        <td>
          <a :href="'invoices.php?customer_id=' + c.id" class="btn btn-sm btn-secondary">Invoices</a>
          <button class="btn btn-sm btn-outline-primary ms-1" @click="copyCustomer(c)">Copy</button>
          <button class="btn btn-sm btn-warning ms-1" @click="edit(c)">Edit</button>
          <button v-if="c.invoice_count === 0" class="btn btn-sm btn-danger ms-1" @click="remove(c.id)">Delete</button>
        </td>
        <td>{{ c.company }}</td>
        
                <td>
          <img v-if="c.logo" :src="c.logo" alt="Logo" class="img-fluid" style="max-height:50px;">
        </td><td>{{ c.agreement }}</td><td>{{ c.id_number }}</td><td>{{ c.vat_number }}</td><td>{{ c.website }}</td><td>{{ c.address }}</td>
        <td>{{ c.city }}</td><td>{{ c.postal_code }}</td><td>{{ c.country }}</td><td>{{ c.email }}</td><td>{{ c.phone }}</td><td>{{ c.currency }}</td>
        
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
            <div class="mb-3"><label class="form-label">Company</label><input v-model="form.company" class="form-control"/></div>
            <div class="mb-3"><label class="form-label">Agreement</label><input v-model="form.agreement" class="form-control"/></div>
            <div class="mb-3"><label class="form-label">ID Number</label><input v-model="form.id_number" class="form-control"/></div>
            <div class="mb-3"><label class="form-label">VAT Number</label><input v-model="form.vat_number" class="form-control"/></div>
            <div class="mb-3"><label class="form-label">Website</label><input v-model="form.website" class="form-control"/></div>
            <div class="mb-3"><label class="form-label">Address</label><input v-model="form.address" class="form-control"/></div>
            <div class="mb-3"><label class="form-label">City</label><input v-model="form.city" class="form-control"/></div>
            <div class="mb-3"><label class="form-label">Postal Code</label><input v-model="form.postal_code" class="form-control"/></div>
            <div class="mb-3"><label class="form-label">Country</label><input v-model="form.country" class="form-control"/></div>
            <div class="mb-3"><label class="form-label">Email</label><input v-model="form.email" type="email" required class="form-control"/></div>
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
            <div v-if="form.id" class="mb-4">
              <h5>Prefixes</h5>
              <table class="table table-sm">
                <thead>
                  <tr><th>Entity</th><th>Property</th><th>Prefix</th><th>Actions</th></tr>
                </thead>
                <tbody>
                  <tr v-for="(p, idx) in prefixes" :key="idx">
                    <td>
                      <select v-model="p.entity" class="form-select">
                        <option value="client">Customer</option>
                        <option value="company">Company</option>
                      </select>
                    </td>
                    <td>
                      <select v-model="p.property" class="form-select">
                        <option v-for="opt in propertyOptions[p.entity]" :value="opt.value">{{ opt.label }}</option>
                      </select>
                    </td>
                    <td><input v-model="p.prefix" class="form-control" /></td>
                    <td><button type="button" class="btn btn-sm btn-danger" @click="removePrefix(idx)">Delete</button></td>
                  </tr>
                  <tr>
                    <td>
                      <select v-model="newPrefix.entity" class="form-select">
                        <option value="client">Customer</option>
                        <option value="company">Company</option>
                      </select>
                    </td>
                    <td>
                      <select v-model="newPrefix.property" class="form-select">
                        <option v-for="opt in propertyOptions[newPrefix.entity]" :value="opt.value">{{ opt.label }}</option>
                      </select>
                    </td>
                    <td><input v-model="newPrefix.prefix" class="form-control" placeholder="Prefix text"/></td>
                    <td><button type="button" class="btn btn-sm btn-primary" @click="addPrefix()">Add</button></td>
                  </tr>
                </tbody>
              </table>
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
      currencies: [],
      prefixes: [],
      newPrefix: { entity: 'client', property: '', prefix: '' },
      propertyOptions: {
        client: [
          { value: 'company', label: 'Company' },
          { value: 'number', label: 'ID Number' },
          { value: 'vat_number', label: 'VAT Number' },
          { value: 'address1', label: 'Address' },
          { value: 'city_state_postal', label: 'City & Postal Code' },
          { value: 'country', label: 'Country' },
          { value: 'contact.email', label: 'Email' }
        ],
        company: [
          { value: 'name', label: 'Name' },
          { value: 'company', label: 'Company' },
          { value: 'id_number', label: 'ID Number' },
          { value: 'regon_krs_number', label: 'REGON/KRS Number' },
          { value: 'vat_number', label: 'VAT Number' },
          { value: 'website', label: 'Website' },
          { value: 'email', label: 'Email' },
          { value: 'phone', label: 'Phone' },
          { value: 'address1', label: 'Address' },
          { value: 'city_state_postal', label: 'City & Postal Code' },
          { value: 'country', label: 'Country' },
          { value: 'bank_name', label: 'Bank Name' },
          { value: 'bank_account', label: 'Bank Account' },
          { value: 'bank_code', label: 'Bank Code' }
        ]
      }
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
      this.form = { name: '', email: '', company: '', agreement: '', id_number: '', vat_number: '', website: '', address: '', city: '', postal_code: '', country: '', phone: '', currency: this.currencies[0], logo: '', invoice_count: 0 };
      this.prefixes = [];
      this.newPrefix = { entity: 'client', property: '', prefix: '' };
      this.$refs.logoInput.value = null;
      new bootstrap.Modal(this.$refs.modal).show();
    },
    closeForm() {
      bootstrap.Modal.getInstance(this.$refs.modal).hide();
    },
    edit(c) {
      this.form = Object.assign({}, c);
      this.$refs.logoInput.value = null;
      this.fetchPrefixes();
      new bootstrap.Modal(this.$refs.modal).show();
    },
    async save() {
      const method = this.form.id ? 'PUT' : 'POST';
      const resp = await fetch('/api/customers.php', {
        method,
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(this.form)
      });
      if (!this.form.id) {
        const data = await resp.json();
        this.form.id = data.id;
      }
      // synchronize prefixes for this customer
      await fetch(`/api/prefixes.php?customer_id=${this.form.id}`, { method: 'DELETE' });
      for (const p of this.prefixes) {
        await fetch('/api/prefixes.php', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({ customer_id: this.form.id, entity: p.entity, property: p.property, prefix: p.prefix })
        });
      }
      this.closeForm();
      this.fetch();
    },
    remove(id) {
      if (confirm('Delete this customer?')) {
        fetch('/api/customers.php?id=' + id, { method: 'DELETE' }).then(() => this.fetch());
      }
    },
    copyCustomer(c) {
      this.form = { ...c, invoice_count: 0 };
      delete this.form.id;
      this.prefixes = [];
      this.newPrefix = { entity: 'client', property: '', prefix: '' };
      this.$refs.logoInput.value = null;
      new bootstrap.Modal(this.$refs.modal).show();
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
    },
    fetchPrefixes() {
      fetch(`/api/prefixes.php?customer_id=${this.form.id}`)
        .then(r => r.json()).then(data => { this.prefixes = data || []; });
    },
    addPrefix() {
      if (!this.newPrefix.property || !this.newPrefix.prefix) return;
      this.prefixes.push({
        entity: this.newPrefix.entity,
        property: this.newPrefix.property,
        prefix: this.newPrefix.prefix
      });
      this.newPrefix.property = '';
      this.newPrefix.prefix = '';
    },
    removePrefix(idx) {
      this.prefixes.splice(idx, 1);
    },
  },
  mounted() {
    this.fetch();
    this.fetchCurrencies();
  }
}).mount('#app');
</script>
<?php include 'footer.php'; ?>