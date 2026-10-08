<script setup lang="ts">
import { onMounted } from 'vue'
import { useLogsStore } from '@/stores/logs'

const store = useLogsStore()

onMounted(() => {
  store.fetchLogs()
})
</script>

<template>
  <div class="px-4 sm:px-6 lg:px-8">
    <div class="sm:flex sm:items-center">
      <div class="sm:flex-auto">
        <h1 class="text-2xl font-semibold text-gray-900">System Logs</h1>
        <p class="mt-2 text-sm text-gray-700">
          Monitor all MCP API requests and actions.
        </p>
      </div>
    </div>

    <div class="mt-8 flex flex-col">
      <div class="-my-2 -mx-4 overflow-x-auto sm:-mx-6 lg:-mx-8">
        <div class="inline-block min-w-full py-2 align-middle md:px-6 lg:px-8">
          <div class="overflow-hidden shadow ring-1 ring-black ring-opacity-5 md:rounded-lg">
            <table class="min-w-full divide-y divide-gray-300">
              <thead class="bg-gray-50">
                <tr>
                  <th scope="col" class="py-3.5 pl-4 pr-3 text-left text-sm font-semibold text-gray-900 sm:pl-6">Method</th>
                  <th scope="col" class="px-3 py-3.5 text-left text-sm font-semibold text-gray-900">Tool</th>
                  <th scope="col" class="px-3 py-3.5 text-left text-sm font-semibold text-gray-900">Status</th>
                  <th scope="col" class="px-3 py-3.5 text-left text-sm font-semibold text-gray-900">IP</th>
                  <th scope="col" class="px-3 py-3.5 text-left text-sm font-semibold text-gray-900">Time</th>
                </tr>
              </thead>
              <tbody class="divide-y divide-gray-200 bg-white">
                <tr v-for="log in store.logs" :key="log.id">
                  <td class="whitespace-nowrap py-4 pl-4 pr-3 text-sm font-medium text-gray-900 sm:pl-6">{{ log.method || '-' }}</td>
                  <td class="whitespace-nowrap px-3 py-4 text-sm text-gray-500">{{ log.tool || '-' }}</td>
                  <td class="whitespace-nowrap px-3 py-4 text-sm">
                    <span :class="[log.status === 'success' ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800']" class="inline-flex rounded-full px-2 text-xs font-semibold leading-5">{{ log.status }}</span>
                  </td>
                  <td class="whitespace-nowrap px-3 py-4 text-sm text-gray-500">{{ log.ip }}</td>
                  <td class="whitespace-nowrap px-3 py-4 text-sm text-gray-500">{{ log.created_at }}</td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>