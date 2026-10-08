<script setup lang="ts">
import { onMounted, ref } from 'vue'
import { useTasksStore } from '@/stores/tasks'
import { CheckCircle2, CircleX, ListFilter, ListTodo, TriangleAlert } from '@lucide/vue'

const store = useTasksStore()
const activeFilter = ref('pending')

const filters = [
  { key: 'pending', label: 'Pending', icon: ListTodo },
  { key: 'completed', label: 'Completed', icon: CheckCircle2 },
  { key: 'failed', label: 'Failed', icon: CircleX },
  { key: '', label: 'All', icon: ListFilter },
]

async function load(filter: string) {
  activeFilter.value = filter
  await store.fetchTasks(filter || undefined)
}

onMounted(() => load('pending'))

async function handleApprove(id: number) {
  try {
    await store.approveTask(id)
    await load(activeFilter.value)
  } catch (e: any) {
    const msg = e?.response?.data?.error || e?.message || 'Unknown error'
    alert('Gagal approve task #' + id + ': ' + msg)
  }
}

async function handleReject(id: number) {
  if (confirm('Yakin hoyong reject task ieu?')) {
    try {
      await store.rejectTask(id)
      await load(activeFilter.value)
    } catch (e: any) {
      const msg = e?.response?.data?.error || e?.message || 'Unknown error'
      alert('Gagal reject task: ' + msg)
    }
  }
}
</script>

<template>
  <div class="px-4 sm:px-6 lg:px-8">
    <div class="sm:flex sm:items-center mb-6">
      <div class="sm:flex-auto">
        <h1 class="text-2xl font-semibold text-gray-900">Task Queue</h1>
        <p class="mt-2 text-sm text-gray-700">
          Review and approve pending AI agent actions.
        </p>
      </div>
    </div>

    <div class="task-filters mb-4 flex flex-wrap gap-2" aria-label="Filter tasks">
      <button
        v-for="f in filters"
        :key="f.key"
        @click="load(f.key)"
        :class="activeFilter === f.key
          ? 'is-active'
          : ''"
        class="task-filter-button"
      >
        <component :is="f.icon" :size="14" :stroke-width="1.8" aria-hidden="true" />
        {{ f.label }}
      </button>
    </div>

    <!-- Loading State -->
    <div v-if="store.loading" class="flex justify-center py-12">
      <div class="animate-spin rounded-full h-12 w-12 border-b-2 border-indigo-600"></div>
    </div>

    <!-- Error State -->
    <div v-else-if="store.error" class="text-center py-12 bg-red-50 rounded-lg">
      <TriangleAlert class="mx-auto h-12 w-12 text-red-400" :stroke-width="1.6" aria-hidden="true" />
      <h3 class="mt-2 text-sm font-medium text-red-900">Failed to load tasks</h3>
      <p class="mt-1 text-sm text-red-500">{{ store.error }}</p>
    </div>
    
    <!-- Empty State -->
    <div v-else-if="store.tasks.length === 0" class="text-center py-12 bg-gray-50 rounded-lg">
      <CheckCircle2 class="mx-auto h-12 w-12 text-gray-400" :stroke-width="1.6" aria-hidden="true" />
      <h3 class="mt-2 text-sm font-medium text-gray-900">No pending tasks</h3>
      <p class="mt-1 text-sm text-gray-500">There are no pending tasks to review.</p>
    </div>

    <!-- Table -->
    <div v-else class="task-table-frame overflow-x-auto">
      <table class="task-table min-w-full divide-y divide-gray-300">
        <colgroup>
          <col class="task-tool-col" />
          <col class="task-payload-col" />
          <col class="task-requested-col" />
          <col class="task-status-col" />
          <col class="task-actions-col" />
        </colgroup>
        <thead class="bg-gray-50">
          <tr>
            <th scope="col" class="task-heading">Tool</th>
            <th scope="col" class="task-heading">Payload</th>
            <th scope="col" class="task-heading">Requested</th>
            <th scope="col" class="task-heading">Status</th>
            <th scope="col" class="task-heading task-heading-actions">Actions</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-gray-200">
          <tr v-for="task in store.tasks" :key="task.id" class="task-row">
            <td class="task-cell task-tool">
              <span class="task-tool-name">{{ task.tool }}</span>
              <span class="task-id">TASK #{{ task.id }}</span>
            </td>
            <td class="task-cell task-payload-cell">
              <pre class="task-payload">{{ JSON.stringify(task.payload, null, 2) }}</pre>
            </td>
            <td class="task-cell task-date">
              {{ task.created_at }}
            </td>
            <td class="task-cell">
              <span
                class="task-status"
                :class="{
                  'is-pending': task.status === 'pending',
                  'is-completed': task.status === 'completed',
                  'is-failed': task.status === 'failed',
                  'is-processing': task.status === 'processing',
                }"
              >
                {{ task.status }}
              </span>
            </td>
            <td class="task-cell task-actions-cell">
              <button
                type="button"
                @click="handleApprove(task.id)"
                class="task-action task-action-approve"
              >
                Approve
              </button>
              <button
                type="button"
                @click="handleReject(task.id)"
                class="task-action task-action-reject"
              >
                Reject
              </button>
            </td>
          </tr>
        </tbody>
      </table>
    </div>
  </div>
</template>

<style scoped>
.task-filters {
  margin-top: 24px;
  margin-bottom: 16px;
}

.task-filter-button {
  display: inline-flex;
  min-height: 34px;
  align-items: center;
  gap: 7px;
  padding: 0 12px;
  border: 1px solid var(--color-border-strong);
  border-radius: 9px;
  background: var(--color-surface);
  color: var(--color-text-secondary);
  cursor: pointer;
  font-size: 11px;
  font-weight: 600;
  transition: color .16s ease, background .16s ease, border-color .16s ease;
}

.task-filter-button:hover {
  border-color: var(--color-blue-border);
  color: var(--color-text);
}

.task-filter-button.is-active {
  border-color: var(--color-blue-border);
  background: var(--color-blue-soft);
  color: var(--color-blue);
}

.task-table-frame {
  overflow-x: auto;
  border: 1px solid var(--color-border);
  border-radius: 13px;
  background: var(--color-surface);
  box-shadow: var(--shadow-card);
}

.task-table {
  width: 100%;
  min-width: 820px;
  table-layout: fixed;
  border-collapse: collapse;
}

.task-tool-col {
  width: 17%;
}

.task-payload-col {
  width: 36%;
}

.task-requested-col {
  width: 19%;
}

.task-status-col {
  width: 12%;
}

.task-actions-col {
  width: 16%;
}

.task-heading {
  padding: 13px 16px;
  border-bottom: 1px solid var(--color-border);
  background: var(--color-surface-muted);
  color: var(--color-text-muted);
  font-size: 9px;
  font-weight: 700;
  letter-spacing: .12em;
  text-align: left;
  text-transform: uppercase;
}

.task-heading:first-child,
.task-cell:first-child {
  padding-left: 19px;
}

.task-heading-actions {
  padding-right: 19px;
  text-align: right;
}

.task-row {
  transition: background .16s ease;
}

.task-row:hover {
  background: var(--color-surface-muted);
}

.task-row:not(:last-child) .task-cell {
  border-bottom: 1px solid var(--color-border);
}

.task-cell {
  padding: 15px 16px;
  color: var(--color-text-secondary);
  font-size: 11px;
  vertical-align: middle;
}

.task-tool-name {
  display: block;
  overflow: hidden;
  color: var(--color-text);
  font-size: 12px;
  font-weight: 600;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.task-id {
  display: block;
  margin-top: 5px;
  color: #65676b;
  font-size: 8px;
  font-weight: 650;
  letter-spacing: .1em;
}

.task-payload {
  max-height: 112px;
  margin: 0;
  overflow: auto;
  padding: 10px 11px;
  border: 1px solid var(--color-border);
  border-radius: 8px;
  background: var(--color-surface-muted);
  color: var(--color-text);
  font-family: ui-monospace, SFMono-Regular, Menlo, Consolas, monospace;
  font-size: 10px;
  line-height: 1.55;
  overflow-wrap: anywhere;
  white-space: pre-wrap;
  word-break: break-word;
}

.task-date {
  color: var(--color-text-secondary);
  font-variant-numeric: tabular-nums;
  line-height: 1.5;
}

.task-status {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  padding: 5px 8px;
  border: 1px solid transparent;
  border-radius: 999px;
  font-size: 9px;
  font-weight: 650;
  line-height: 1;
  text-transform: capitalize;
  white-space: nowrap;
}

.task-status::before {
  width: 5px;
  height: 5px;
  border-radius: 50%;
  background: currentColor;
  content: "";
}

.task-status.is-pending {
  border-color: #f3d99a;
  background: #fff8e1;
  color: #8a6500;
}

.task-status.is-completed {
  border-color: var(--color-green-border);
  background: var(--color-green-soft);
  color: var(--color-green);
}

.task-status.is-failed {
  border-color: var(--color-red-border);
  background: var(--color-red-soft);
  color: var(--color-red);
}

.task-status.is-processing {
  border-color: var(--color-blue-border);
  background: var(--color-blue-soft);
  color: var(--color-blue);
}

.task-actions-cell {
  text-align: right;
  white-space: nowrap;
}

.task-action {
  min-height: 29px;
  margin-left: 5px;
  padding: 0 9px;
  border: 1px solid transparent;
  border-radius: 7px;
  cursor: pointer;
  font-size: 10px;
  font-weight: 650;
  transition: background .16s ease, border-color .16s ease;
}

.task-action-approve {
  border-color: var(--color-green-border);
  background: var(--color-green-soft);
  color: var(--color-green);
}

.task-action-approve:hover {
  background: #d9f0e1;
}

.task-action-reject {
  border-color: var(--color-red-border);
  background: var(--color-red-soft);
  color: var(--color-red);
}

.task-action-reject:hover {
  background: #ffe1e2;
}
</style>