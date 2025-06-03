<?php include 'header.php'; ?>
<div id="app">
  <h1>Currencies</h1>
  <button @click="openForm()" class="btn btn-primary mb-2">Add Currency</button>
  <table class="table table-bordered table-striped">
    <thead>
      <tr><th>Code</th><th>Actions</th></tr>
    </thead>
    <tbody>
      <tr v-for="c in currencies" :key="c">
        <td>{{ c }}</td>
        <td>
          <button class="btn btn-sm btn-warning" @click="edit(c)">Edit</button>
          <button class="btn btn-sm btn-danger" @click="remove(c)">Delete</button>
        </td>
      </tr>
    </tbody>
  </table>
  <div class="modal" tabindex="-1" ref="modal">
    <div class="modal-dialog">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title">{{ editing ? 'Edit' : 'Add' }} Currency</h5>
          <button type="button" class="btn-close" @click="closeForm()"></button>
        </div>
        <div class="modal-body">
          <form @submit.prevent="save">
            <div class="mb-3">
              <label class="form-label">Code</label>
              <input v-model="code" class="form-control" maxlength="3" required pattern="[A-Z]{3}">
            </div>
            <div class="d-flex justify-content-end gap-2">
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
    return { currencies: [], code: '', editing: false, oldCode: '' };
  },
  methods: {
    fetch() {
      fetch('/api/currencies.php')
        .then(r => r.json())
        .then(data => this.currencies = data);
    },
    openForm() {
      this.code = '';
      this.oldCode = '';
      this.editing = false;
      new bootstrap.Modal(this.$refs.modal).show();
    },
    closeForm() {
      bootstrap.Modal.getInstance(this.$refs.modal).hide();
    },
    edit(code) {
      this.code = code;
      this.oldCode = code;
      this.editing = true;
      new bootstrap.Modal(this.$refs.modal).show();
    },
    save() {
      const payload = { code: this.code };
      let method = 'POST';
      if (this.editing) {
        payload.old_code = this.oldCode;
        payload.new_code = this.code;
        method = 'PUT';
      }
      fetch('/api/currencies.php', {
        method,
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(payload)
      }).then(() => { this.closeForm(); this.fetch(); });
    },
    remove(code) {
      if (confirm('Delete currency ' + code + '?')) {
        fetch('/api/currencies.php?code=' + encodeURIComponent(code), { method: 'DELETE' })
          .then(() => this.fetch());
      }
    }
  },
  mounted() {
    this.fetch();
  }
}).mount('#app');
</script>
<?php include 'footer.php'; ?>