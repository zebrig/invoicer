<?php include 'header.php'; ?>
<div id="app">
  <h1>Users</h1>
  <button @click="openForm()" class="btn btn-primary mb-2">Add User</button>
  <a href="/admin/sessions.php" class="btn btn-secondary ms-2 mb-2">User sessions</a>
  <table class="table table-bordered table-striped">
    <thead>
      <tr>
        <th>Username</th>
        <th>Admin</th>
        <th>Disabled</th>
        <th>Assigned Customers</th>
        <th>Actions</th>
      </tr>
    </thead>
    <tbody>
      <tr v-for="u in users" :key="u.id">
        <td>{{ u.username }}</td>
        <td>{{ u.is_admin ? 'Yes' : 'No' }}</td>
        <td>{{ u.disabled ? 'Yes' : 'No' }}</td>
        <td>{{ customers.filter(c => u.customer_ids.includes(c.id)).map(c => c.name).join(', ') }}</td>
        <td>
          <button class="btn btn-sm btn-warning" @click="edit(u)">Edit</button>
          <button class="btn btn-sm btn-danger" @click="remove(u.id)" v-if="u.id !== currentUserId">Delete</button>
        </td>
      </tr>
    </tbody>
  </table>
  <div class="modal" tabindex="-1" ref="modal">
    <div class="modal-dialog">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title">{{ form.id ? 'Edit' : 'Add' }} User</h5>
          <button type="button" class="btn-close" @click="closeForm()"></button>
        </div>
        <div class="modal-body">
          <form @submit.prevent="save">
            <div class="mb-3">
              <label class="form-label">Username</label>
              <input v-model="form.username" required class="form-control"/>
            </div>
            <div class="mb-3">
              <label class="form-label">Password</label>
              <input type="password" v-model="form.password" :required="!form.id" class="form-control"/>
              <div v-if="form.id" class="form-text">Leave blank to keep current password</div>
            </div>
            <div class="mb-3 form-check">
              <input type="checkbox" v-model="form.is_admin" class="form-check-input" id="isAdminCheck"/>
              <label class="form-check-label" for="isAdminCheck">Administrator</label>
            </div>
            <div class="mb-3 form-check">
              <input type="checkbox" v-model="form.disabled" class="form-check-input" id="disabledCheck"/>
              <label class="form-check-label" for="disabledCheck">Disabled</label>
            </div>
            <div class="mb-3">
              <label class="form-label">Assigned Customers</label>
              <select v-model="form.customer_ids" class="form-select" multiple>
                <option v-for="c in customers" :key="c.id" :value="c.id">{{ c.name }}</option>
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
    return {
      users: [],
      customers: [],
      form: {},
      currentUserId: <?= json_encode($_SESSION['user_id'] ?? null) ?>
    };
  },
  methods: {
    fetch() {
      fetch('/api/users.php')
        .then(r => r.json())
        .then(data => this.users = data);
    },
    fetchCustomers() {
      fetch('/api/customers.php')
        .then(r => r.json())
        .then(data => this.customers = data);
    },
    openForm() {
      this.form = { username: '', password: '', disabled: false, is_admin: false, customer_ids: [] };
      new bootstrap.Modal(this.$refs.modal).show();
    },
    closeForm() {
      bootstrap.Modal.getInstance(this.$refs.modal).hide();
    },
    edit(u) {
      this.form = {
        id: u.id,
        username: u.username,
        password: '',
        disabled: u.disabled == 1,
        is_admin: u.is_admin == 1,
        customer_ids: u.customer_ids || []
      };
      new bootstrap.Modal(this.$refs.modal).show();
    },
    save() {
      const method = this.form.id ? 'PUT' : 'POST';
      fetch('/api/users.php', {
        method,
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(this.form)
      }).then(() => { this.closeForm(); this.fetch(); });
    },
    remove(id) {
      if (confirm('Delete this user?')) {
        fetch('/api/users.php?id=' + id, { method: 'DELETE' }).then(() => this.fetch());
      }
    }
  },
  mounted() {
    this.fetchCustomers();
    this.fetch();
  }
}).mount('#app');
</script>
<?php include 'footer.php'; ?>