import { defineConfig } from 'vitepress'
import { readFileSync } from 'fs'
import { resolve } from 'path'
import { fileURLToPath } from 'url'

const __dirname = fileURLToPath(new URL('.', import.meta.url))
const { version } = JSON.parse(readFileSync(resolve(__dirname, '../../version.json'), 'utf-8'))

export default defineConfig({
  lang: 'en-US',
  base: '/falconcms/',
  title: 'FalconCMS',
  titleTemplate: ':title | FalconCMS',
  description: 'Documentation for FalconCMS — an open-source Laravel CMS with a visual page builder, e-commerce, dynamic content, themes, plugins, and developer APIs.',
  lastUpdated: true,

  head: [
    ['link', { rel: 'icon', href: '/falconcms/falcon-cms-logo.png', type: 'image/png' }],
    ['meta', { property: 'og:type',        content: 'website' }],
    ['meta', { property: 'og:site_name',   content: 'FalconCMS' }],
    ['meta', { property: 'og:image',       content: 'https://falconcms.github.io/falconcms/falcon-cms-logo.png' }],
    ['meta', { name: 'twitter:card',       content: 'summary_large_image' }],
    ['meta', { name: 'twitter:image',      content: 'https://falconcms.github.io/falconcms/falcon-cms-logo.png' }],
    ['meta', { name: 'keywords',           content: 'Laravel CMS, open source Laravel CMS, Laravel content management system, Laravel page builder, Laravel e-commerce, Laravel CMS plugins, Laravel CMS themes' }],
  ],

  // Emits a canonical URL for every page, plus per-page Open Graph / Twitter
  // metadata — but only where the page has not already declared its own.
  transformHead({ pageData, head, title, description }) {
    const origin = 'https://falconcms.github.io/falconcms/'
    const path = pageData.relativePath
      .replace(/(^|\/)index\.md$/, '$1')
      .replace(/\.md$/, '.html')
    const url = origin + path

    const declared = (key, value) => head.some(([, attrs = {}]) => attrs[key] === value)

    const tags = [['link', { rel: 'canonical', href: url }]]

    if (!declared('property', 'og:url')) {
      tags.push(['meta', { property: 'og:url', content: url }])
    }
    if (title && !declared('property', 'og:title')) {
      tags.push(['meta', { property: 'og:title', content: title }])
    }
    if (description && !declared('property', 'og:description')) {
      tags.push(['meta', { property: 'og:description', content: description }])
    }
    if (title && !declared('name', 'twitter:title')) {
      tags.push(['meta', { name: 'twitter:title', content: title }])
    }
    if (description && !declared('name', 'twitter:description')) {
      tags.push(['meta', { name: 'twitter:description', content: description }])
    }

    return tags
  },

  themeConfig: {
    logo: '/falcon-cms-logo.png',
    siteTitle: false,

    nav: [
      { text: 'Guide', link: '/guide/introduction' },
      { text: 'Pro', link: '/guide/pro' },
      { text: 'Builder', link: '/builder/overview' },
      { text: 'Slider', link: '/slider/overview' },
      { text: 'E-commerce', link: '/ecommerce/overview' },
      { text: 'Plugins', link: '/guide/plugins' },
      { text: 'Hooks API', link: '/api/hooks' },
      { text: 'Changelog', link: '/changelog' },
      { text: '🚀 Live Demo', link: '/demo' },
      {
        text: `v${version}`,
        items: [
          { text: 'Release Notes', link: '/changelog' },
          { text: 'Packagist', link: 'https://packagist.org/packages/falconcms/falconcms' },
        ]
      }
    ],

    sidebar: {
      '/guide/': [
        {
          text: 'Getting Started',
          items: [
            { text: 'Introduction', link: '/guide/introduction' },
            { text: 'Installation', link: '/guide/installation' },
            { text: 'Configuration', link: '/guide/configuration' },
            { text: 'Installing Pro', link: '/guide/pro' },
            { text: 'Upgrade Guide', link: '/guide/upgrade' },
          ]
        },
        {
          text: 'Core Concepts',
          items: [
            { text: 'Post Types', link: '/guide/post-types' },
            { text: 'Taxonomies', link: '/guide/taxonomies' },
            { text: 'Menus', link: '/guide/menus' },
            { text: 'Widgets', link: '/guide/widgets' },
            { text: 'Media Library', link: '/guide/media' },
            { text: 'Multi-language', link: '/guide/multilang' },
          ]
        },
        {
          text: 'Roles & Permissions',
          items: [
            { text: 'RBAC Overview', link: '/guide/rbac' },
          ]
        },
        {
          text: 'Theme Development',
          items: [
            { text: 'Theme Structure', link: '/guide/themes' },
            { text: 'Template Tags', link: '/guide/template-tags' },
          ]
        },
        {
          text: 'Plugin Development',
          items: [
            { text: 'Plugins', link: '/guide/plugins' },
            { text: 'Admin Menu API', link: '/api/admin-menu' },
            { text: 'Settings Fields API', link: '/api/settings-fields' },
          ]
        },
      ],
      '/builder/': [
        {
          text: 'Falcon Builder',
          items: [
            { text: 'Overview', link: '/builder/overview' },
            { text: 'Containers & Columns', link: '/builder/containers' },
            { text: 'Elements', link: '/builder/elements' },
            { text: 'Device Visibility', link: '/builder/visibility' },
            { text: 'Global Sections', link: '/builder/global-sections' },
            { text: 'Library', link: '/builder/library' },
          ]
        },
      ],
      '/slider/': [
        {
          text: 'Falcon Slider',
          items: [
            { text: 'Overview', link: '/slider/overview' },
            { text: 'Slides & Backgrounds', link: '/slider/slides' },
            { text: 'Layers', link: '/slider/layers' },
            { text: 'Animations', link: '/slider/animations' },
            { text: 'Navigation & Managing', link: '/slider/navigation' },
          ]
        },
      ],
      '/ecommerce/': [
        {
          text: 'E-commerce',
          items: [
            { text: 'Overview', link: '/ecommerce/overview' },
            { text: 'Products', link: '/ecommerce/products' },
            { text: 'Storefront', link: '/ecommerce/storefront' },
            { text: 'Shipping & Tax', link: '/ecommerce/shipping-tax' },
            { text: 'Orders', link: '/ecommerce/orders' },
            { text: 'Coupons', link: '/ecommerce/coupons' },
            { text: 'Promotions', link: '/ecommerce/promotions' },
          ]
        },
      ],
      '/api/': [
        {
          text: 'API Reference',
          items: [
            { text: 'Hooks', link: '/api/hooks' },
            { text: 'Helper Functions', link: '/api/helpers' },
            { text: 'Admin Menu API', link: '/api/admin-menu' },
            { text: 'Settings Fields API', link: '/api/settings-fields' },
          ]
        },
        {
          text: 'Extending FalconCMS',
          items: [
            { text: 'Plugins', link: '/guide/plugins' },
            { text: 'Themes', link: '/guide/themes' },
          ]
        },
      ],
      '/changelog': [
        {
          text: 'Changelog',
          items: [
            { text: 'v2.6.1 (Latest)', link: '/changelog#v2-6-1' },
            { text: 'v2.6.0', link: '/changelog#v2-6-0' },
            { text: 'v2.5.0', link: '/changelog#v2-5-0' },
            { text: 'v2.4.0', link: '/changelog#v2-4-0' },
            { text: 'v2.3.0', link: '/changelog#v2-3-0' },
            { text: 'v2.2.7', link: '/changelog#v2-2-7' },
            { text: 'v2.2.6', link: '/changelog#v2-2-6' },
            { text: 'v2.2.5', link: '/changelog#v2-2-5' },
            { text: 'v2.2.4', link: '/changelog#v2-2-4' },
            { text: 'v2.2.3', link: '/changelog#v2-2-3' },
            { text: 'v2.2.2', link: '/changelog#v2-2-2' },
            { text: 'v2.2.1', link: '/changelog#v2-2-1' },
            { text: 'v2.2', link: '/changelog#v2-2' },
            { text: 'v2.0', link: '/changelog#v2-0' },
            { text: 'v1.8.3', link: '/changelog#v1-8-3' },
            { text: 'v1.8.2', link: '/changelog#v1-8-2' },
            { text: 'v1.8.1', link: '/changelog#v1-8-1' },
            { text: 'v1.8.0', link: '/changelog#v1-8-0' },
            { text: 'v1.7.4', link: '/changelog#v1-7-4' },
            { text: 'v1.7.3', link: '/changelog#v1-7-3' },
            { text: 'v1.7.2', link: '/changelog#v1-7-2' },
            { text: 'v1.7.1', link: '/changelog#v1-7-1' },
            { text: 'v1.7.0', link: '/changelog#v1-7-0' },
            { text: 'v1.6.3', link: '/changelog#v1-6-3' },
            { text: 'v1.6.2', link: '/changelog#v1-6-2' },
            { text: 'v1.6.1', link: '/changelog#v1-6-1' },
            { text: 'v1.6.0', link: '/changelog#v1-6-0' },
            { text: 'v1.5.10', link: '/changelog#v1-5-10' },
            { text: 'v1.5.9', link: '/changelog#v1-5-9' },
            { text: 'v1.5.8', link: '/changelog#v1-5-8' },
            { text: 'v1.5.7', link: '/changelog#v1-5-7' },
            { text: 'v1.5.6', link: '/changelog#v1-5-6' },
            { text: 'v1.5.5', link: '/changelog#v1-5-5' },
            { text: 'v1.5.4', link: '/changelog#v1-5-4' },
            { text: 'v1.5.3', link: '/changelog#v1-5-3' },
            { text: 'v1.5.2', link: '/changelog#v1-5-2' },
            { text: 'v1.5.1', link: '/changelog#v1-5-1' },
            { text: 'v1.5.0', link: '/changelog#v1-5-0' },
            { text: 'v1.4.9', link: '/changelog#v1-4-9' },
            { text: 'v1.4.8', link: '/changelog#v1-4-8' },
            { text: 'v1.4.7', link: '/changelog#v1-4-7' },
            { text: 'v1.4.6', link: '/changelog#v1-4-6' },
            { text: 'v1.4.5', link: '/changelog#v1-4-5' },
            { text: 'v1.4.4', link: '/changelog#v1-4-4' },
            { text: 'v1.4.3', link: '/changelog#v1-4-3' },
            { text: 'v1.4.2', link: '/changelog#v1-4-2' },
            { text: 'v1.4.1', link: '/changelog#v1-4-1' },
            { text: 'v1.4.0', link: '/changelog#v1-4-0' },
            { text: 'v1.3.18', link: '/changelog#v1-3-18' },
            { text: 'v1.0.0', link: '/changelog#v1-0-0' },
          ]
        },
      ],
    },

    socialLinks: [
      { icon: 'github', link: 'https://github.com/falconcms/falconcms' },
    ],

    footer: {
      message: 'Released under the MIT License.',
      copyright: 'Copyright © 2026 Falcon CMS'
    },

    search: {
      provider: 'local'
    },

    editLink: {
      pattern: 'https://github.com/falconcms/falconcms/edit/main/docs/:path',
      text: 'Edit this page on GitHub'
    },
  }
})
