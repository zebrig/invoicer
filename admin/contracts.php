<?php include 'header.php'; ?>
<div id="app">
  <h1>Contracts</h1>
  <button @click="openForm()" class="btn btn-primary mb-2">Add Contract</button>

  <div class="row mb-3">
    <div class="col-md-3">
      <input v-model="filters.search" @input="fetchContracts" type="text" placeholder="Search..." class="form-control" />
    </div>
    <div class="col-md-3">
      <select v-model="filters.customer_id" @change="fetchContracts" class="form-select">
        <option value="">All Customers</option>
        <option v-for="c in customers" :value="c.id">{{ c.name }}</option>
      </select>
    </div>
    <div class="col-md-3">
      <select v-model="filters.company_id" @change="fetchContracts" class="form-select">
        <option value="">All Companies</option>
        <option v-for="c in companies" :value="c.id">{{ c.name }}</option>
      </select>
    </div>
    <div class="col-md-3 d-flex gap-2">
      <input type="date" v-model="filters.start_date" @change="fetchContracts" class="form-control" />
      <input type="date" v-model="filters.end_date" @change="fetchContracts" class="form-control" />
    </div>
  </div>

  <table class="table table-bordered table-striped table-hover">
    <thead>
      <tr>
        <th>Name</th>
        <th>Customer</th>
        <th>Company</th>
        <th>Date</th>
        <th>End Date</th>
        <th>Description</th>
        <th>Attachments</th>
        <th>Actions</th>
      </tr>
    </thead>
    <tbody>
      <tr v-for="ctr in contracts" :key="ctr.id" @click="edit(ctr)" style="cursor:pointer">
        <td>{{ ctr.name }}</td>
        <td>{{ ctr.customer_name }}</td>
        <td>{{ ctr.company_name }}</td>
        <td>{{ ctr.date }}</td>
        <td>{{ ctr.end_date }}</td>
        <td>{{ ctr.description }}</td>
        <td>
          <template v-for="f in ctr.attachments">
            <a
              v-if="/\.pdf$/i.test(f.filename)"
              :href="`/view_contract_file.php?file_id=${f.id}&original_name=${encodeURIComponent(f.filename)}`"
              target="_blank"
              class="btn btn-sm btn-outline-secondary file-viewer"
              :title="f.filename"
              @click.stop
            >
              <i class="bi bi-file-earmark-pdf me-1"></i>PDF
            </a>
            <a
              v-else
              :href="`/api/contracts.php?download_file_id=${f.id}`"
              target="_blank"
            >{{ f.filename }}</a>
          </template>
        </td>
        <td>
          <button class="btn btn-sm btn-warning" @click.stop="edit(ctr)">Edit</button>
          <button class="btn btn-sm btn-danger ms-1" @click.stop="remove(ctr.id)">Delete</button>
        </td>
      </tr>
    </tbody>
  </table>

  <div class="modal" tabindex="-1" ref="modal">
    <div class="modal-dialog">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title">{{ form.id ? 'Edit' : 'Add' }} Contract</h5>
          <button type="button" class="btn-close" @click="closeForm()"></button>
        </div>
        <div class="modal-body">
          <form @submit.prevent="save">
            <div class="mb-3"><label class="form-label">Name</label><input v-model="form.name" required class="form-control" /></div>
            <div class="mb-3"><label class="form-label">Customer</label>
              <select v-model="form.customer_id" required class="form-select">
                <option v-for="c in customers" :value="c.id">{{ c.name }}</option>
              </select>
            </div>
            <div class="mb-3"><label class="form-label">Company</label>
              <select v-model="form.company_id" required class="form-select">
                <option v-for="c in companies" :value="c.id">{{ c.name }}</option>
              </select>
            </div>
            <div class="mb-3"><label class="form-label">Date</label><input type="date" v-model="form.date" required class="form-control" /></div>
            <div class="mb-3"><label class="form-label">End Date</label><input type="date" v-model="form.end_date" class="form-control" /></div>
            <div class="mb-3"><label class="form-label">Description</label><textarea v-model="form.description" class="form-control"></textarea></div>
            <div class="mb-3"><label class="form-label">Attachments</label><input ref="fileInput" type="file" accept=".pdf,.doc,.docx" multiple @change="onFilesChange" class="form-control" /></div>
            <div class="mb-3" v-if="form.attachments.length">
              <label class="form-label">Current Attachments</label><br><br>
              <ul class="list-group">
                <li v-for="(f,i) in form.attachments" :key="f.id || f.filename" class="list-group-item d-flex justify-content-between align-items-center">
                  {{ f.filename }}
                    <a
                      v-if="/\.pdf$/i.test(f.filename)"
                      :href="`/view_contract_file.php?file_id=${f.id}&original_name=${encodeURIComponent(f.filename)}`"
                      target="_blank"
                      class="btn btn-sm btn-outline-secondary file-viewer"
                      title="PDF"
                      @click.stop
                    >
                      <i class="bi bi-file-earmark-pdf me-1"></i>PDF
                    </a>
                    <a
                      v-else
                      :href="'/api/contracts.php?download_file_id=' + f.id"
                      target="_blank"
                    >{{ f.filename }}</a>
                  </span>
                  <button type="button" class="btn btn-sm btn-danger ms-2" @click="removeAttachment(i)">Remove</button>
                </li>
              </ul>
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
      contracts: [],
      customers: [],
      companies: [],
      filters: { search: '', customer_id: '', company_id: '', start_date: '', end_date: '' },
      form: { id: null, customer_id: null, company_id: null, date: '', end_date: '', name: '', description: '', attachments: [] }
    };
  },
  methods: {
    fetchContracts() {
      const params = new URLSearchParams({
        search: this.filters.search,
        customer_id: this.filters.customer_id,
        company_id: this.filters.company_id,
        start_date: this.filters.start_date,
        end_date: this.filters.end_date
      });
      fetch('/api/contracts.php?' + params)
        .then(r => r.json())
        .then(data => { this.contracts = data; });
    },
    fetchCustomers() {
      fetch('/api/customers.php').then(r => r.json()).then(data => this.customers = data);
    },
    fetchCompanies() {
      fetch('/api/companies.php').then(r => r.json()).then(data => this.companies = data);
    },
    openForm() {
      this.form = {
        id: null,
        customer_id: this.customers.length ? this.customers[0].id : null,
        company_id: this.companies.length ? this.companies[0].id : null,
        date: '',
        end_date: '',
        name: '',
        description: '',
        attachments: []
      };
      this.$refs.fileInput.value = null;
      new bootstrap.Modal(this.$refs.modal).show();
    },
    closeForm() {
      bootstrap.Modal.getInstance(this.$refs.modal).hide();
    },
    edit(c) {
      fetch('/api/contracts.php?id=' + c.id)
        .then(r => r.json())
        .then(data => {
          this.form = data;
          this.$refs.fileInput.value = null;
          new bootstrap.Modal(this.$refs.modal).show();
        });
    },
    save() {
      const method = this.form.id ? 'PUT' : 'POST';
      fetch('/api/contracts.php', {
        method,
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(this.form)
      }).then(() => {
        this.closeForm();
        this.fetchContracts();
      });
    },
    remove(id) {
      if (confirm('Delete this contract?')) {
        fetch('/api/contracts.php?id=' + id, { method: 'DELETE' })
          .then(() => this.fetchContracts());
      }
    },
    onFilesChange(event) {
      const files = event.target.files;
      for (const file of files) {
        if (!['application/pdf','application/msword','application/vnd.openxmlformats-officedocument.wordprocessingml.document'].includes(file.type)) {
          alert('Files must be PDF, DOC or DOCX');
          continue;
        }
        const reader = new FileReader();
        reader.onload = e => {
          this.form.attachments.push({ filename: file.name, data: e.target.result });
        };
        reader.readAsDataURL(file);
      }
      event.target.value = null;
    },
    removeAttachment(i) {
      this.form.attachments.splice(i, 1);
    }
  },
  watch: {
    'filters.search': 'fetchContracts',
    'filters.customer_id': 'fetchContracts',
    'filters.company_id': 'fetchContracts',
    'filters.start_date': 'fetchContracts',
    'filters.end_date': 'fetchContracts'
  },
  mounted() {
    this.fetchCustomers();
    this.fetchCompanies();
    this.fetchContracts();
  }
}).mount('#app');
</script>
<?php include 'footer.php'; ?>