import { defineStore } from 'pinia'
import { ref } from 'vue'
import { api, Log } from '@/api/client'

export const useLogsStore = defineStore('logs', () => {
  const logs = ref<Log[]>([])
  const total = ref(0)
  const loading = ref(false)
  const error = ref<string | null>(null)

  async function fetchLogs(page: number = 1, perPage: number = 20) {
    loading.value = true
    error.value = null
    try {
      const result = await api.listLogs(page, perPage)
      logs.value = result.logs
      total.value = result.total
    } catch (e: any) {
      error.value = e.message || 'Failed to fetch logs'
    } finally {
      loading.value = false
    }
  }

  return {
    logs,
    total,
    loading,
    error,
    fetchLogs,
  }
})