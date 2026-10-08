<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { api } from '@/api/client'

const settings = ref({
  mode: 'draft_only',
  require_ssl: true,
})
const loading = ref(true)
const saving = ref(false)

onMounted(async () => {
  try {
    settings.value = await api.getSettings()
  } catch (e) {
    console.error(e)
  } finally {
    loading.value = false
  }
})

async function saveSettings() {
  saving.value = true
  try {
    await api.updateSettings(settings.value)
    alert('Settings saved successfully!')
  } catch (e) {
    alert('Failed to save settings')
  } finally {
    saving.value = false
  }
}
</script>

<template>
  <div class="px-4 sm:px-6 lg:px-8">
    <div class="sm:flex sm:items-center">
      <div class="sm:flex-auto">
        <h1 class="text-2xl font-semibold text-gray-900">Settings</h1>
        <p class="mt-2 text-sm text-gray-700">
          Configure MuseSpark MCP security and behavior.
        </p>
      </div>
    </div>

    <div class="mt-8 space-y-6">
      <div class="bg-white shadow sm:rounded-lg">
        <div class="px-4 py-5 sm:p-6">
          <h3 class="text-lg font-medium leading-6 text-gray-900">Security Mode</h3>
          <div class="mt-2 max-w-xl text-sm text-gray-500">
            <p>Choose the default behavior for write operations.</p>
          </div>
          <div class="mt-5">
            <select v-model="settings.mode" class="block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
              <option value="read_only">Read Only</option>
              <option value="draft_only">Draft Only (Content/SEO only)</option>
              <option value="require_confirmation">Require Confirmation</option>
            </select>
          </div>
        </div>
      </div>

      <div class="bg-white shadow sm:rounded-lg">
        <div class="px-4 py-5 sm:p-6">
          <h3 class="text-lg font-medium leading-6 text-gray-900">SSL Requirement</h3>
          <div class="mt-2 max-w-xl text-sm text-gray-500">
            <p>Require HTTPS for all MCP API requests.</p>
          </div>
          <div class="mt-5">
            <label class="flex items-center">
              <input v-model="settings.require_ssl" type="checkbox" class="h-4 w-4 rounded border-gray-300 text-indigo-600 focus:ring-indigo-500" />
              <span class="ml-2 text-sm text-gray-900">Require SSL</span>
            </label>
          </div>
        </div>
      </div>

      <div class="flex justify-end">
        <button @click="saveSettings" :disabled="saving" class="inline-flex items-center justify-center rounded-md border border-transparent bg-indigo-600 px-4 py-2 text-sm font-medium text-white shadow-sm hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 sm:w-auto">
          {{ saving ? 'Saving...' : 'Save Settings' }}
        </button>
      </div>
    </div>
  </div>
</template>