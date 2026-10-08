import axios from 'axios'

const client = axios.create({
  baseURL: window.MusesparkMCP?.restUrl || '/wp-json/musespark-mcp/v1',
  headers: {
    'X-WP-Nonce': window.MusesparkMCP?.nonce || '',
    'Content-Type': 'application/json',
  },
})

export interface Token {
  id: number
  user_id: number
  name: string
  scopes: string[]
  expires_at: string | null
  last_used_at: string | null
  created_at: string
}

export interface Task {
  id: number
  tool: string
  payload: any
  status: 'pending' | 'processing' | 'completed' | 'failed'
  requested_by: number
  approved_by: number | null
  result: any
  error: string | null
  created_at: string
  updated_at: string
}

export interface Log {
  id: number
  jti: string | null
  user_id: number | null
  method: string | null
  tool: string | null
  status: string
  message: string | null
  ip: string
  created_at: string
}

export const api = {
  // Tokens
  async listTokens(): Promise<Token[]> {
    const response = await client.get('/admin/tokens')
    return response.data
  },

  async createToken(name: string, scopes: string[], ttl: number): Promise<Token & { token: string }> {
    const response = await client.post('/admin/tokens', { name, scopes, ttl })
    return response.data
  },

  async revokeToken(id: number): Promise<void> {
    await client.post(`/admin/tokens/${id}/revoke`)
  },

  // Tasks
  async listTasks(status?: string): Promise<Task[]> {
    const params = status ? { status } : {}
    const response = await client.get('/admin/tasks', { params })
    return response.data
  },

  async approveTask(id: number): Promise<Task> {
    const response = await client.post(`/admin/tasks/${id}/approve`)
    return response.data
  },

  async rejectTask(id: number): Promise<void> {
    await client.post(`/admin/tasks/${id}/reject`)
  },

  // Logs
  async listLogs(page: number = 1, perPage: number = 20): Promise<{ logs: Log[]; total: number }> {
    const response = await client.get('/admin/logs', { params: { page, per_page: perPage } })
    return response.data
  },

  // Settings
  async getSettings(): Promise<any> {
    const response = await client.get('/admin/settings')
    return response.data
  },

  async updateSettings(settings: any): Promise<void> {
    await client.post('/admin/settings', settings)
  },

  // Dashboard
  async getDashboardSummary(): Promise<any> {
    const response = await client.get('/admin/dashboard')
    return response.data
  },
}