import { defineStore } from 'pinia'
import { ref } from 'vue'
import { api, Token } from '@/api/client'

export const useTokensStore = defineStore('tokens', () => {
  const tokens = ref<Token[]>([])
  const loading = ref(false)
  const error = ref<string | null>(null)

  async function fetchTokens() {
    loading.value = true
    error.value = null
    try {
      tokens.value = await api.listTokens()
    } catch (e: any) {
      error.value = e.message || 'Failed to fetch tokens'
    } finally {
      loading.value = false
    }
  }

  async function createToken(name: string, scopes: string[], ttl: number) {
    loading.value = true
    error.value = null
    try {
      const result = await api.createToken(name, scopes, ttl)
      await fetchTokens()
      return result
    } catch (e: any) {
      error.value = e.message || 'Failed to create token'
      throw e
    } finally {
      loading.value = false
    }
  }

  async function revokeToken(id: number) {
    loading.value = true
    error.value = null
    try {
      await api.revokeToken(id)
      await fetchTokens()
    } catch (e: any) {
      error.value = e.message || 'Failed to revoke token'
      throw e
    } finally {
      loading.value = false
    }
  }

  return {
    tokens,
    loading,
    error,
    fetchTokens,
    createToken,
    revokeToken,
  }
})