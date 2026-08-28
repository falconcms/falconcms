import DefaultTheme from 'vitepress/theme'
import { h } from 'vue'
import StatsSection from './StatsSection.vue'
import DemoForm from './DemoForm.vue'
import './custom.css'

export default {
  extends: DefaultTheme,
  Layout() {
    return h(DefaultTheme.Layout, null, {
      'home-features-after': () => h(StatsSection),
    })
  },
  enhanceApp({ app }) {
    app.component('DemoForm', DemoForm)
  },
}
