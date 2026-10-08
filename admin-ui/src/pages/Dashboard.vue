<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { api } from '@/api/client'
import { Clock3, FileText, KeyRound, ShieldCheck } from '@lucide/vue'

const summary = ref<any>(null)
const loading = ref(true)
const museVideoUrl = window.MusesparkMCP?.assetBaseUrl
  ? new URL('muse.mp4', window.MusesparkMCP.assetBaseUrl).href
  : '/muse.mp4'

onMounted(async () => {
  try {
    summary.value = await api.getDashboardSummary()
  } catch (e) {
    console.error(e)
  } finally {
    loading.value = false
  }
})
</script>

<template>
  <div class="px-4 sm:px-6 lg:px-8">
    <div class="page-heading">
      <div>
        <h1 class="page-title">Dashboard</h1>
        <p class="page-description">Your MuseSpark MCP system at a glance.</p>
      </div>
      <div class="live-chip"><span></span> LIVE OVERVIEW</div>
    </div>

    <div v-if="loading" class="metric-grid" aria-label="Loading dashboard">
      <div v-for="card in 4" :key="card" class="skeleton-card animate-pulse"></div>
    </div>

    <div v-else-if="summary" class="metric-grid">
      <article class="metric-card">
        <div class="metric-card-top">
          <span class="metric-label">Total Tokens</span>
          <span class="metric-icon" aria-hidden="true">
            <KeyRound :size="16" :stroke-width="1.8" />
          </span>
        </div>
        <strong class="metric-value">{{ summary.total_tokens }}</strong>
        <div class="metric-caption">API access credentials</div>
      </article>

      <article class="metric-card">
        <div class="metric-card-top">
          <span class="metric-label">Pending Tasks</span>
          <span class="metric-icon" aria-hidden="true">
            <Clock3 :size="16" :stroke-width="1.8" />
          </span>
        </div>
        <strong class="metric-value">{{ summary.pending_tasks }}</strong>
        <div class="metric-caption">Waiting for your review</div>
      </article>

      <article class="metric-card">
        <div class="metric-card-top">
          <span class="metric-label">Total Logs</span>
          <span class="metric-icon" aria-hidden="true">
            <FileText :size="16" :stroke-width="1.8" />
          </span>
        </div>
        <strong class="metric-value">{{ summary.total_logs }}</strong>
        <div class="metric-caption">Recorded system activity</div>
      </article>

      <article class="metric-card">
        <div class="metric-card-top">
          <span class="metric-label">Mode</span>
          <span class="metric-icon" aria-hidden="true">
            <ShieldCheck :size="16" :stroke-width="1.8" />
          </span>
        </div>
        <strong class="metric-value is-text capitalize">{{ summary.mode }}</strong>
        <div class="metric-caption">Current security policy</div>
      </article>
    </div>

    <section class="dashboard-video" aria-hidden="true">
      <video
        class="dashboard-video-player"
        :src="museVideoUrl"
        autoplay
        muted
        loop
        playsinline
        preload="auto"
        tabindex="-1"
      ></video>
    </section>
  </div>
</template>

<style scoped>
.dashboard-video {
  margin-top: 24px;
}

.dashboard-video-player {
  display: block;
  width: 100%;
  height: auto;
  max-height: 560px;
  object-fit: contain;
}
</style>
