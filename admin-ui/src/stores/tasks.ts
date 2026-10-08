import { defineStore } from 'pinia'
import { ref } from 'vue'
import { api, Task } from '@/api/client'

export const useTasksStore = defineStore('tasks', () => {
  const tasks = ref<Task[]>([])
  const loading = ref(false)
  const error = ref<string | null>(null)

  async function fetchTasks(status?: string) {
    loading.value = true
    error.value = null
    try {
      tasks.value = await api.listTasks(status)
    } catch (e: any) {
      error.value = e.message || 'Failed to fetch tasks'
    } finally {
      loading.value = false
    }
  }

  async function approveTask(id: number) {
    loading.value = true
    error.value = null
    try {
      await api.approveTask(id)
      await fetchTasks()
    } catch (e: any) {
      error.value = e.message || 'Failed to approve task'
      throw e
    } finally {
      loading.value = false
    }
  }

  async function rejectTask(id: number) {
    loading.value = true
    error.value = null
    try {
      await api.rejectTask(id)
      await fetchTasks()
    } catch (e: any) {
      error.value = e.message || 'Failed to reject task'
      throw e
    } finally {
      loading.value = false
    }
  }

  return {
    tasks,
    loading,
    error,
    fetchTasks,
    approveTask,
    rejectTask,
  }
})