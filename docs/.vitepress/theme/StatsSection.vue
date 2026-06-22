<script setup>
import { ref, onMounted } from 'vue'
import versionData from '../../../version.json'

const downloads = ref(null)
const stars = ref(null)
const version = ref(versionData.version)
const lastDownload = ref(null)

onMounted(async () => {
  try {
    const res = await fetch('https://packagist.org/packages/falconcms/falconcms.json')
    const data = await res.json()
    downloads.value = data.package.downloads.total
    const v = data.package.version_normalized
    if (v) version.value = v.replace(/^(\d+\.\d+\.\d+).*$/, '$1')
    // If there were downloads today, the most recent download day is today.
    if (data.package.downloads.daily > 0) lastDownload.value = new Date().toISOString().slice(0, 10)
  } catch (_) {}

  // Packagist exposes day-level download counts (not exact times). The most recent
  // day with at least one download is the closest we can show to "last downloaded".
  try {
    const from = new Date(Date.now() - 120 * 86400000).toISOString().slice(0, 10)
    const res = await fetch(`https://packagist.org/packages/falconcms/falconcms/stats/all.json?average=daily&from=${from}`)
    const data = await res.json()
    const labels = data.labels || []
    const series = (data.values && data.values['falconcms/falconcms']) || []
    for (let i = series.length - 1; i >= 0; i--) {
      if (series[i] > 0) { lastDownload.value = labels[i]; break }
    }
  } catch (_) {}

  try {
    const res = await fetch('https://api.github.com/repos/falconcms/falconcms')
    const data = await res.json()
    stars.value = data.stargazers_count
  } catch (_) {}
})

function fmt(n) {
  if (n === null) return '—'
  if (n >= 1000) return (n / 1000).toFixed(1) + 'k'
  return String(n)
}

function agoDay(dateStr) {
  if (!dateStr) return '—'
  const today = new Date(); today.setHours(0, 0, 0, 0)
  const d = new Date(dateStr + 'T00:00:00')
  const days = Math.round((today - d) / 86400000)
  if (days <= 0) return 'today'
  if (days === 1) return 'yesterday'
  if (days < 7) return days + ' days ago'
  if (days < 30) { const w = Math.floor(days / 7); return w + ' week' + (w > 1 ? 's' : '') + ' ago' }
  return d.toLocaleDateString(undefined, { month: 'short', day: 'numeric', year: 'numeric' })
}
</script>

<template>
  <div class="stats-wrap">
    <div class="stats-inner">

      <div class="stat-card">
        <div class="stat-icon">📦</div>
        <div class="stat-value">{{ fmt(downloads) }}<span v-if="downloads !== null">+</span></div>
        <div class="stat-label">Total Downloads</div>
      </div>

      <div class="stat-divider"></div>

      <div class="stat-card">
        <div class="stat-stars">
          <span v-for="i in 5" :key="i" class="star">★</span>
        </div>
        <div class="stat-value">{{ fmt(stars) }}</div>
        <div class="stat-label">GitHub Stars</div>
      </div>

      <div class="stat-divider"></div>

      <div class="stat-card">
        <div class="stat-icon">🚀</div>
        <div class="stat-value">v{{ version }}</div>
        <div class="stat-label">Latest Release</div>
      </div>

      <div class="stat-divider"></div>

      <div class="stat-card">
        <div class="stat-icon">⬇️</div>
        <div class="stat-value stat-value-sm">{{ agoDay(lastDownload) }}</div>
        <div class="stat-label">Last Download</div>
      </div>

    </div>
  </div>
</template>

<style scoped>
.stats-wrap {
  padding: 56px 24px 64px;
  text-align: center;
  border-top: 1px solid var(--vp-c-divider);
  background: var(--vp-c-bg-soft);
}

.stats-inner {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 0;
  max-width: 820px;
  margin: 0 auto;
  background: var(--vp-c-bg);
  border: 1px solid var(--vp-c-divider);
  border-radius: 16px;
  padding: 36px 24px;
  box-shadow: 0 4px 24px rgba(0,0,0,.06);
}

.stat-card {
  flex: 1;
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 6px;
}

.stat-divider {
  width: 1px;
  height: 56px;
  background: var(--vp-c-divider);
  margin: 0 8px;
  flex-shrink: 0;
}

.stat-icon {
  font-size: 24px;
  line-height: 1;
}

.stat-stars {
  display: flex;
  gap: 2px;
}

.star {
  font-size: 20px;
  color: #f5a623;
  line-height: 1;
}

.stat-value {
  font-size: 28px;
  font-weight: 700;
  color: var(--vp-c-brand-1);
  line-height: 1.1;
}

.stat-value-sm {
  font-size: 17px;
}

.stat-label {
  font-size: 12px;
  font-weight: 500;
  color: var(--vp-c-text-2);
  text-transform: uppercase;
  letter-spacing: .06em;
}

@media (max-width: 480px) {
  .stats-inner {
    flex-direction: column;
    gap: 24px;
    padding: 28px 16px;
  }
  .stat-divider {
    width: 80px;
    height: 1px;
    margin: 0;
  }
}
</style>
