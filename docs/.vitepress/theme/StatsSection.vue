<script setup>
import { ref, onMounted } from 'vue'
import versionData from '../../../version.json'

const downloads = ref(null)
const stars = ref(null)
const version = ref(versionData.version)

onMounted(async () => {
  try {
    const res = await fetch('https://packagist.org/packages/lazycmsapp/lazy-cms-builder.json')
    const data = await res.json()
    downloads.value = data.package.downloads.total
    const v = data.package.version_normalized
    if (v) version.value = v.replace(/^(\d+\.\d+\.\d+).*$/, '$1')
  } catch (_) {}

  try {
    const res = await fetch('https://api.github.com/repos/lazycmsapp/lazy-cms-builder')
    const data = await res.json()
    stars.value = data.stargazers_count
  } catch (_) {}
})

function fmt(n) {
  if (n === null) return '—'
  if (n >= 1000) return (n / 1000).toFixed(1) + 'k'
  return String(n)
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
  max-width: 640px;
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
