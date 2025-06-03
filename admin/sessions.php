<?php include 'header.php'; ?>
<div id="app">
  <h1>Sessions</h1>
  <div class="mb-3">
    <input v-model="filterText" class="form-control" placeholder="Filter sessions...">
  </div>
  <table class="table table-bordered table-striped">
    <thead>
      <tr>
        <th @click="sortBy('masked_token')" style="cursor: pointer;">
          Token <span v-if="sortKey==='masked_token'">{{ sortDesc ? '▼' : '▲' }}</span>
        </th>
        <th @click="sortBy('username')" style="cursor: pointer;">
          User <span v-if="sortKey==='username'">{{ sortDesc ? '▼' : '▲' }}</span>
        </th>
        <th @click="sortBy('created_at')" style="cursor: pointer;">
          Created At <span v-if="sortKey==='created_at'">{{ sortDesc ? '▼' : '▲' }}</span>
        </th>
        <th>Created IP</th>
        <th>Created User Agent</th>
        <th @click="sortBy('last_used_at')" style="cursor: pointer;">
          Last Used At <span v-if="sortKey==='last_used_at'">{{ sortDesc ? '▼' : '▲' }}</span>
        </th>
        <th>Last Used IP</th>
        <th>Last Used User Agent</th>
        <th @click="sortBy('expires_at')" style="cursor: pointer;">
          Expires At <span v-if="sortKey==='expires_at'">{{ sortDesc ? '▼' : '▲' }}</span>
        </th>
        <th>Actions</th>
      </tr>
    </thead>
    <tbody>
      <tr v-for="s in filteredAndSortedSessions" :key="s.id">
        <td><code>{{ s.masked_token }}</code></td>
        <td>{{ s.username }}</td>
        <td>{{ s.created_at }} ({{ relativeDate(s.created_at) }})</td>
        <td>{{ s.created_ip }}</td>
        <td>{{ s.created_user_agent }}</td>
        <td>{{ s.last_used_at }} ({{ relativeDate(s.last_used_at) }})</td>
        <td>{{ s.last_used_ip }}</td>
        <td>{{ s.last_used_user_agent }}</td>
        <td>{{ s.expires_at }} ({{ relativeDate(s.expires_at) }})</td>
        <td>
          <button class="btn btn-sm btn-danger" @click="revoke(s.id)" :disabled="s.token === currentToken">
            Revoke
          </button>
        </td>
      </tr>
    </tbody>
  </table>
</div>
<script>
const { createApp } = Vue;
createApp({
  data() {
    return {
      sessions: [],
      currentToken: <?= json_encode($_COOKIE['auth_token'] ?? '') ?>,
      filterText: '',
      sortKey: '',
      sortDesc: false
    };
  },
  computed: {
    filteredAndSortedSessions() {
      let list = this.sessions;
      if (this.filterText) {
        const text = this.filterText.toLowerCase();
        list = list.filter(s =>
          Object.values(s).some(v => String(v).toLowerCase().includes(text))
        );
      }
      if (this.sortKey) {
        list = [...list].sort((a, b) => {
          let aVal = a[this.sortKey] || '';
          let bVal = b[this.sortKey] || '';
          if (['created_at', 'last_used_at', 'expires_at'].includes(this.sortKey)) {
            aVal = new Date(aVal.replace(' ', 'T'));
            bVal = new Date(bVal.replace(' ', 'T'));
          }
          if (aVal < bVal) return this.sortDesc ? 1 : -1;
          if (aVal > bVal) return this.sortDesc ? -1 : 1;
          return 0;
        });
      }
      return list;
    }
  },
  methods: {
    fetch() {
      fetch('/api/sessions.php')
        .then(r => r.json())
        .then(data => this.sessions = data);
    },
    revoke(id) {
      if (!confirm('Revoke this session?')) return;
      fetch('/api/sessions.php', {
        method: 'DELETE',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ id })
      }).then(() => this.fetch());
    },
    sortBy(key) {
      if (this.sortKey === key) {
        this.sortDesc = !this.sortDesc;
      } else {
        this.sortKey = key;
        this.sortDesc = false;
      }
    },
    relativeDate(str) {
      if (!str) return '';
      const date = new Date(str.replace(' ', 'T'));
      const now = new Date();
      let diff = (date - now) / 1000;
      const abs = Math.abs(diff);
      const units = [
        { name: 'day', seconds: 86400 },
        { name: 'hour', seconds: 3600 },
        { name: 'minute', seconds: 60 },
        { name: 'second', seconds: 1 }
      ];
      for (const u of units) {
        const val = Math.floor(abs / u.seconds);
        if (val >= 1) {
          const plural = val > 1 ? 's' : '';
          return diff >= 0
            ? `in ${val} ${u.name}${plural}`
            : `${val} ${u.name}${plural} ago`;
        }
      }
      return 'just now';
    }
  },
  mounted() {
    this.fetch();
  }
}).mount('#app');
</script>
<?php include 'footer.php'; ?>