<script setup lang="ts">
import { computed, nextTick, onBeforeUnmount, onMounted, onUpdated } from 'vue'
import { RouterLink, RouterView, useRoute } from 'vue-router'
import {
  ChartNoAxesColumnIncreasing,
  KeyRound,
  LayoutDashboard,
  ListTodo,
  Settings,
} from '@lucide/vue'

const route = useRoute()
const brandImageUrl = window.MusesparkMCP?.assetBaseUrl
  ? new URL('brand.png', window.MusesparkMCP.assetBaseUrl).href
  : '/brand.png'
let resizeObserver: ResizeObserver | undefined
let mutationObserver: MutationObserver | undefined
let pendingHeightFrame = 0

const currentPage = computed(() => {
  const titles: Record<string, string> = {
    dashboard: 'Dashboard',
    tokens: 'Tokens',
    tasks: 'Task queue',
    logs: 'System logs',
    settings: 'Settings',
  }

  return titles[String(route.name)] ?? 'Dashboard'
})

function reportHeight() {
  if (window.parent === window) return

  cancelAnimationFrame(pendingHeightFrame)
  pendingHeightFrame = requestAnimationFrame(() => {
    const app = document.getElementById('app')
    if (!app) return

    const appHeight = Math.max(app.scrollHeight, app.getBoundingClientRect().height)
    window.parent.postMessage(
      {
        type: 'musespark-mcp:resize',
        height: Math.ceil(appHeight),
      },
      window.location.origin,
    )
  })
}

function handleParentMessage(event: MessageEvent) {
  if (
    event.source !== window.parent ||
    event.origin !== window.location.origin ||
    event.data?.type !== 'musespark-mcp:request-height'
  ) return

  if (Number.isFinite(event.data.floor)) {
    document.documentElement.style.setProperty('--ms-floor', `${event.data.floor}px`)
  }
  reportHeight()
}

onMounted(() => {
  window.addEventListener('message', handleParentMessage)
  const app = document.getElementById('app')
  if (app) {
    resizeObserver = new ResizeObserver(reportHeight)
    resizeObserver.observe(app)
    mutationObserver = new MutationObserver(reportHeight)
    mutationObserver.observe(app, { childList: true, subtree: true, attributes: true })
  }

  void nextTick(reportHeight)
})

onUpdated(() => {
  void nextTick(reportHeight)
})

onBeforeUnmount(() => {
  window.removeEventListener('message', handleParentMessage)
  resizeObserver?.disconnect()
  mutationObserver?.disconnect()
  cancelAnimationFrame(pendingHeightFrame)
})
</script>

<template>
  <div class="app-shell">
    <aside class="app-sidebar">
      <RouterLink to="/" class="brand-lockup" aria-label="MuseSpark MCP home">
        <span class="brand-mark" aria-hidden="true">
          <img :src="brandImageUrl" alt="MuseSpark MCP" />
        </span>
        <span class="brand-copy">
          <span class="brand-name">MuseSpark</span>
          <span class="brand-caption">MCP CONTROL</span>
        </span>
      </RouterLink>

      <div class="sidebar-section-label">Workspace</div>
      <nav class="sidebar-nav" aria-label="Main navigation">
        <RouterLink to="/" class="nav-link" active-class="is-active" exact-active-class="is-active">
          <LayoutDashboard :size="18" :stroke-width="1.8" aria-hidden="true" />
          <span>Dashboard</span>
        </RouterLink>
        <RouterLink to="/tokens" class="nav-link" active-class="is-active">
          <KeyRound :size="18" :stroke-width="1.8" aria-hidden="true" />
          <span>Tokens</span>
        </RouterLink>
        <RouterLink to="/tasks" class="nav-link" active-class="is-active">
          <ListTodo :size="18" :stroke-width="1.8" aria-hidden="true" />
          <span>Tasks</span>
        </RouterLink>
        <RouterLink to="/logs" class="nav-link" active-class="is-active">
          <ChartNoAxesColumnIncreasing :size="18" :stroke-width="1.8" aria-hidden="true" />
          <span>Logs</span>
        </RouterLink>
        <RouterLink to="/settings" class="nav-link" active-class="is-active">
          <Settings :size="18" :stroke-width="1.8" aria-hidden="true" />
          <span>Settings</span>
        </RouterLink>
      </nav>

      <div class="sidebar-footer">
        <span class="connection-indicator"></span>
        <span>API connection</span>
        <span class="connection-status">READY</span>
      </div>
    </aside>

    <div class="app-body">
      <header class="topbar">
        <div class="topbar-context">
          <span class="topbar-kicker">MUSESPARK MCP</span>
          <span class="topbar-divider">/</span>
          <span class="topbar-page">{{ currentPage }}</span>
        </div>
        <div class="topbar-profile">
          <span class="topbar-status"><span></span> SYSTEM ONLINE</span>
          <span class="profile-avatar">MS</span>
        </div>
      </header>

      <main class="app-main">
        <div class="admin-content">
          <RouterView />
        </div>
        <footer class="app-footer">
          <span>MUSESPARK MCP</span>
          <span>CONTROL CENTER <span class="footer-dot">·</span> BUILT FOR WHAT'S NEXT</span>
        </footer>
      </main>
    </div>
  </div>
</template>
