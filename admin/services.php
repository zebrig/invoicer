<?php include 'header.php'; ?>
<div id="app">
  <h1>Services</h1>
  <button @click="openForm()" class="btn btn-primary mb-2">Add Service</button>
  <table class="table table-bordered table-striped">
    <thead>
      <tr><th>Description</th><th>Unit Price</th><th>Currency</th><th>Actions</th></tr>
    </thead>
    <tbody>
      <tr v-for="s in services" :key="s.id">
        <td>{{ s.description }}</td><td>{{ s.unit_price.toFixed(2) }}</td><td>{{ s.currency }}</td>
        <td>
          <button class="btn btn-sm btn-warning" @click="edit(s)">Edit</button>
          <button class="btn btn-sm btn-danger" @click="remove(s.id)">Delete</button>
        </td>
      </tr>
    </tbody>
  </table>
  <!-- Modal -->
  <div class="modal" tabindex="-1" ref="modal">
    <div class="modal-dialog">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title">{{ form.id ? 'Edit' : 'Add' }} Service</h5>
          <button type="button" class="btn-close" @click="closeForm()"></button>
        </div>
        <div class="modal-body">
          <form @submit.prevent="save">
            <div class="mb-3"><label class="form-label">Description</label><input v-model="form.description" required class="form-control"/></div>
            <div class="mb-3"><label class="form-label">Unit Price</label><input v-model.number="form.unit_price" required type="number" step="0.01" class="form-control"/></div>
            <div class="mb-3"><label class="form-label">Currency</label>
              <select v-model="form.currency" class="form-select" required>
                <option v-for="c in currencies" :key="c" :value="c">{{ c }}</option>
              </select>
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
    return { services: [], form: {}, currencies: [] };
  },
  methods: {
    fetch() {
      fetch('/api/services.php').then(r => r.json()).then(data => this.services = data);
    },
    fetchCurrencies() {
      fetch('/api/currencies.php').then(r => r.json()).then(data => this.currencies = data);
    },
    openForm() {
      this.form = { description: '', unit_price: 0, currency: this.currencies[0] };
      new bootstrap.Modal(this.$refs.modal).show();
    },
    closeForm() {
      bootstrap.Modal.getInstance(this.$refs.modal).hide();
    },
    edit(s) {
      this.form = Object.assign({}, s);
      new bootstrap.Modal(this.$refs.modal).show();
    },
    save() {
      const method = this.form.id ? 'PUT' : 'POST';
      fetch('/api/services.php', {
        method,
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(this.form)
      }).then(() => { this.closeForm(); this.fetch(); });
    },
    remove(id) {
      if (confirm('Delete this service?')) {
        fetch('/api/services.php?id=' + id, { method: 'DELETE' }).then(() => this.fetch());
      }
    }
  },
  mounted() {
    this.fetch();
    this.fetchCurrencies();
  }
}).mount('#app');
</script>
<?php include 'footer.php'; ?>