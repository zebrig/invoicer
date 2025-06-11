<?php include 'header.php'; ?>
<div id="app">
  <h1>Change Log</h1>
  <div class="row mb-3">
    <div class="col-md-3">
      <label class="form-label">Start Date</label>
      <input type="date" v-model="startDate" class="form-control" />
    </div>
    <div class="col-md-3">
      <label class="form-label">End Date</label>
      <input type="date" v-model="endDate" class="form-control" />
    </div>
    <div class="col-md-3 align-self-end">
      <button class="btn btn-primary" @click="fetch()">Filter</button>
    </div>
  </div>
  <table class="table table-bordered table-striped">
    <thead>
      <tr>
        <th>Date</th>
        <th>Type</th>
        <th>Invoice #</th>
        <th>Customer</th>
        <th>Payment</th>
        <th>Previous</th>
        <th>New</th>
        <th>Reason</th>
        <th>Balance Before</th>
        <th>Balance After</th>
        <th>User</th>
        <th>IP</th>
      </tr>
    </thead>
    <tbody>
      <tr v-for="e in entries" :key="e.id">
        <td>{{ e.change_date }}</td>
        <td>{{ e.event_type }}</td>
        <td>{{ e.invoice_number || '' }}</td>
        <td>{{ e.customer_name || '' }}</td>
        <td>
          <template v-if="e.payment_id">
            {{ e.payment_date }} {{ e.payment_amount }} {{ e.payment_currency }} – {{ e.payment_sender }}
            <button class="btn btn-sm btn-link p-0 ms-1" @click="e.showTitle = !e.showTitle">
              {{ e.showTitle ? 'Hide Title' : 'Show Title' }}
            </button>
            <div v-if="e.showTitle" class="small text-muted">{{ e.payment_title }}</div>
          </template>
        </td>
        <td>{{ e.prev_value }}</td>
        <td>{{ e.new_value }}</td>
        <td>{{ e.reason }}</td>
        <td>{{ e.balance_before }}</td>
        <td>{{ e.balance_after }}</td>
        <td>{{ e.user_name || '' }}</td>
        <td>{{ e.ip_address || '' }}</td>
      </tr>
    </tbody>
  </table>
</div>
<script>
const { createApp } = Vue;
createApp({
  data() {
    return {
      entries: [],
      startDate: '',
      endDate: ''
    };
  },
  methods: {
    fetch() {
      const params = new URLSearchParams();
      if (this.startDate) params.set('start_date', this.startDate);
      if (this.endDate) params.set('end_date', this.endDate);
      fetch('/api/change_log.php?' + params)
        .then(r => r.json())
        .then(data => { this.entries = data; });
    }
  },
  mounted() {
    this.fetch();
  }
}).mount('#app');
</script>
<?php include 'footer.php'; ?>