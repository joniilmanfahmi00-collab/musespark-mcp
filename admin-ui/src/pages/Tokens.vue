<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { useTokensStore } from '@/stores/tokens'

const store = useTokensStore()
const showCreateModal = ref(false)
const newToken = ref('')
const isCreating = ref(false)
const copied = ref(false)
const formError = ref('')
const copyError = ref('')

const form = ref({
  name: '',
  scopes: 'content:read,content:write,seo:write,report:read',
  ttl: 3600,
})

onMounted(() => {
  store.fetchTokens()
})

function openCreateModal() {
  formError.value = ''
  showCreateModal.value = true
}

async function handleCreate() {
  const name = form.value.name.trim()
  const scopes = form.value.scopes.split(',').map((scope) => scope.trim()).filter(Boolean)

  if (!name) {
    formError.value = 'Enter a name for this token.'
    return
  }

  if (!scopes.length) {
    formError.value = 'Enter at least one scope.'
    return
  }

  formError.value = ''
  isCreating.value = true
  try {
    const result = await store.createToken(name, scopes, form.value.ttl)
    newToken.value = result.token
    copied.value = false
    copyError.value = ''
    showCreateModal.value = false
    form.value = { name: '', scopes: 'content:read,content:write,seo:write,report:read', ttl: 3600 }
  } catch (e) {
    alert('Failed to create token')
  } finally {
    isCreating.value = false
  }
}

async function handleRevoke(id: number) {
  if (confirm('Are you sure you want to revoke this token?')) {
    try {
      await store.revokeToken(id)
    } catch (e) {
      alert('Failed to revoke token')
    }
  }
}

async function copyToken() {
  copied.value = false
  copyError.value = ''

  try {
    if (navigator.clipboard && window.isSecureContext) {
      await navigator.clipboard.writeText(newToken.value)
    } else {
      const textarea = document.createElement('textarea')
      textarea.value = newToken.value
      textarea.setAttribute('readonly', '')
      textarea.style.position = 'fixed'
      textarea.style.opacity = '0'
      document.body.appendChild(textarea)
      let copiedToClipboard = false
      try {
        textarea.focus()
        textarea.select()
        copiedToClipboard = document.execCommand('copy')
      } finally {
        textarea.remove()
      }

      if (!copiedToClipboard) {
        throw new Error('The browser did not allow copying to the clipboard.')
      }
    }

    copied.value = true
  } catch (error) {
    console.error('Failed to copy token to clipboard:', error)
    copyError.value = 'Could not copy automatically. Select and copy the token above.'
  }
}
</script>

<template>
  <div class="px-4 sm:px-6 lg:px-8">
    <div class="sm:flex sm:items-center">
      <div class="sm:flex-auto">
        <h1 class="text-2xl font-semibold text-gray-900">JWT Tokens</h1>
        <p class="mt-2 text-sm text-gray-700">
          Manage API tokens for MuseSpark MCP access.
        </p>
      </div>
      <div class="mt-4 sm:mt-0 sm:ml-16 sm:flex-none">
        <button
          @click="openCreateModal"
          class="inline-flex items-center justify-center rounded-md border border-transparent bg-indigo-600 px-4 py-2 text-sm font-medium text-white shadow-sm hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 sm:w-auto"
        >
          Create Token
        </button>
      </div>
    </div>

    <div class="mt-8 flex flex-col">
      <div class="-my-2 -mx-4 overflow-x-auto sm:-mx-6 lg:-mx-8">
        <div class="inline-block min-w-full py-2 align-middle md:px-6 lg:px-8">
          <div class="overflow-hidden shadow ring-1 ring-black ring-opacity-5 md:rounded-lg">
            <table class="min-w-full divide-y divide-gray-300">
              <thead class="bg-gray-50">
                <tr>
                  <th scope="col" class="py-3.5 pl-4 pr-3 text-left text-sm font-semibold text-gray-900 sm:pl-6">Name</th>
                  <th scope="col" class="px-3 py-3.5 text-left text-sm font-semibold text-gray-900">Scopes</th>
                  <th scope="col" class="px-3 py-3.5 text-left text-sm font-semibold text-gray-900">Expires</th>
                  <th scope="col" class="px-3 py-3.5 text-left text-sm font-semibold text-gray-900">Last Used</th>
                  <th scope="col" class="relative py-3.5 pl-3 pr-4 sm:pr-6">
                    <span class="sr-only">Actions</span>
                  </th>
                </tr>
              </thead>
              <tbody class="divide-y divide-gray-200 bg-white">
                <tr v-for="token in store.tokens" :key="token.id">
                  <td class="whitespace-nowrap py-4 pl-4 pr-3 text-sm font-medium text-gray-900 sm:pl-6">{{ token.name }}</td>
                  <td class="whitespace-nowrap px-3 py-4 text-sm text-gray-500">{{ token.scopes?.join(', ') || '—' }}</td>
                  <td class="whitespace-nowrap px-3 py-4 text-sm text-gray-500">{{ token.expires_at || 'Never' }}</td>
                  <td class="whitespace-nowrap px-3 py-4 text-sm text-gray-500">{{ token.last_used_at || 'Never' }}</td>
                  <td class="relative whitespace-nowrap py-4 pl-3 pr-4 text-right text-sm font-medium sm:pr-6">
                    <button @click="handleRevoke(token.id)" class="text-red-600 hover:text-red-900">Revoke</button>
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>
      </div>
    </div>

  </div>

  <Teleport to="body">
    <div v-if="showCreateModal || newToken" class="token-modal-overlay" @click.self="showCreateModal = false; newToken = ''">
      <section
        class="token-modal"
        role="dialog"
        aria-modal="true"
        :aria-labelledby="newToken ? 'token-created-title' : 'token-create-title'"
      >
        <template v-if="showCreateModal">
          <div class="token-modal-heading">
            <div>
              <span class="token-modal-eyebrow">ACCESS MANAGEMENT</span>
              <h2 id="token-create-title">Create a token</h2>
              <p>Set up credentials for an AI agent or integration.</p>
            </div>
            <button type="button" class="token-modal-close" aria-label="Close dialog" @click="showCreateModal = false">×</button>
          </div>

          <form class="token-form" @submit.prevent="handleCreate">
            <label class="token-field">
              <span>Token name</span>
              <input v-model="form.name" type="text" placeholder="e.g. My AI Agent" autocomplete="off" required />
            </label>

            <label class="token-field">
              <span>Scopes <small>Separate multiple scopes with commas</small></span>
              <textarea v-model="form.scopes" rows="3" placeholder="content:read, content:write" required></textarea>
            </label>

            <label class="token-field">
              <span>Token lifetime <small>Seconds</small></span>
              <input v-model.number="form.ttl" type="number" min="1" placeholder="3600" required />
            </label>

            <p v-if="formError" class="token-form-error" role="alert">{{ formError }}</p>

            <div class="token-modal-actions">
              <button type="button" class="token-button token-button-secondary" :disabled="isCreating" @click="showCreateModal = false">
                Cancel
              </button>
              <button type="submit" class="token-button token-button-primary" :disabled="isCreating">
                {{ isCreating ? 'Creating…' : 'Create token' }}
              </button>
            </div>
          </form>
        </template>

        <template v-else>
          <div class="token-modal-heading">
            <div>
              <span class="token-modal-eyebrow">TOKEN READY</span>
              <h2 id="token-created-title">Token created</h2>
              <p>Copy it now. For security, you won't be able to view it again.</p>
            </div>
            <button type="button" class="token-modal-close" aria-label="Close dialog" @click="newToken = ''">×</button>
          </div>
          <div class="token-value"><code>{{ newToken }}</code></div>
          <p v-if="copyError" class="token-form-error" role="alert">{{ copyError }}</p>
          <p v-else-if="copied" class="token-copy-success" role="status">Token copied to clipboard.</p>
          <div class="token-modal-actions">
            <button type="button" class="token-button token-button-secondary" @click="newToken = ''">Close</button>
            <button type="button" class="token-button token-button-primary" @click="copyToken">
              {{ copied ? 'Copied!' : 'Copy token' }}
            </button>
          </div>
        </template>
      </section>
    </div>
  </Teleport>
</template>

<style scoped>
.token-modal-overlay {
  position: fixed;
  z-index: 100000;
  inset: 0;
  display: grid;
  overflow-y: auto;
  place-items: center;
  padding: 24px;
  background: rgb(28 30 33 / 55%);
  backdrop-filter: blur(7px);
}

.token-modal {
  width: min(100%, 520px);
  padding: 27px;
  border: 1px solid var(--color-border);
  border-radius: 18px;
  background: var(--color-surface);
  box-shadow: 0 16px 48px rgb(28 30 33 / 20%);
  color: var(--color-text);
}

.token-modal-heading {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: 18px;
  margin-bottom: 25px;
}

.token-modal-eyebrow {
  color: var(--color-blue);
  font-size: 9px;
  font-weight: 700;
  letter-spacing: .15em;
}

.token-modal-heading h2 {
  margin: 8px 0 0;
  color: var(--color-text);
  font-size: 22px;
  font-weight: 620;
  letter-spacing: -.04em;
}

.token-modal-heading p {
  margin: 8px 0 0;
  color: var(--color-text-secondary);
  font-size: 12px;
  line-height: 1.6;
}

.token-modal-close {
  display: grid;
  width: 32px;
  height: 32px;
  flex: none;
  place-items: center;
  border: 1px solid var(--color-border);
  border-radius: 9px;
  background: var(--color-surface-muted);
  color: var(--color-text-secondary);
  cursor: pointer;
  font-size: 21px;
  line-height: 1;
}

.token-form {
  display: grid;
  gap: 19px;
}

.token-field {
  display: grid;
  gap: 8px;
  color: var(--color-text);
  font-size: 12px;
  font-weight: 550;
}

.token-field small {
  margin-left: 5px;
  color: var(--color-text-muted);
  font-size: 10px;
  font-weight: 400;
}

.token-field input,
.token-field textarea {
  width: 100%;
  min-height: 42px;
  padding: 10px 12px;
  border: 1px solid var(--color-border-strong);
  border-radius: 9px;
  outline: none;
  background: var(--color-surface);
  color: var(--color-text);
  font-size: 12px;
  font-weight: 400;
  transition: border-color .18s ease, box-shadow .18s ease;
}

.token-field textarea {
  min-height: 76px;
  resize: vertical;
  line-height: 1.5;
}

.token-field input:focus,
.token-field textarea:focus {
  border-color: var(--color-blue);
  box-shadow: 0 0 0 3px rgb(8 102 255 / 14%);
}

.token-field input::placeholder,
.token-field textarea::placeholder {
  color: #8a8d91;
}

.token-modal-actions {
  display: flex;
  justify-content: flex-end;
  gap: 10px;
  margin-top: 7px;
}

.token-button {
  display: inline-flex;
  min-height: 39px;
  align-items: center;
  justify-content: center;
  padding: 0 15px;
  border: 1px solid transparent;
  border-radius: 9px;
  cursor: pointer;
  font-size: 12px;
  font-weight: 600;
  transition: background .18s ease, border-color .18s ease, opacity .18s ease;
}

.token-button:disabled {
  cursor: wait;
  opacity: .6;
}

.token-button-primary {
  background: var(--color-blue);
  color: #fff;
  box-shadow: 0 5px 16px rgb(8 102 255 / 18%);
}

.token-button-primary:hover:not(:disabled) {
  background: var(--color-blue-hover);
}

.token-button-secondary {
  border-color: var(--color-border-strong);
  background: var(--color-surface-muted);
  color: var(--color-text-secondary);
}

.token-button-secondary:hover:not(:disabled) {
  border-color: #aeb3b8;
  background: var(--color-page);
}

.token-form-error,
.token-copy-success {
  margin: -6px 0 0;
  color: var(--color-red);
  font-size: 11px;
  line-height: 1.5;
}

.token-copy-success {
  color: var(--color-green);
}

.token-value {
  overflow-wrap: anywhere;
  padding: 14px;
  border: 1px solid var(--color-border);
  border-radius: 10px;
  background: var(--color-surface-muted);
  color: var(--color-text);
  font-size: 11px;
  line-height: 1.7;
  user-select: all;
}

.token-value code {
  white-space: pre-wrap;
}

@media (max-width: 520px) {
  .token-modal-overlay {
    align-items: end;
    padding: 12px;
  }

  .token-modal {
    padding: 21px;
    border-radius: 16px;
  }

  .token-modal-actions .token-button {
    flex: 1;
  }
}
</style>