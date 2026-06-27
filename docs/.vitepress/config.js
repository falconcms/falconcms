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
  titleTemplate: '%s | FalconCMS',
  description: 'A WordPress-like drag-and-drop CMS package for Laravel — page builder, e-commerce, multi-language, hooks API, mega menus, and more.',
  lastUpdated: true,

  head: [
    ['link', { rel: 'icon', href: '/falconcms/falcon-cms-logo.png', type: 'image/png' }],
    ['meta', { property: 'og:type',        content: 'website' }],
    ['meta', { property: 'og:site_name',   content: 'FalconCMS' }],
    ['meta', { property: 'og:description', content: 'A WordPress-like drag-and-drop CMS package for Laravel — page builder, e-commerce, multi-language, hooks API, mega menus, and more.' }],
    ['meta', { property: 'og:image',       content: 'https://falconcms.github.io/falconcms/falcon-cms-logo.png' }],
    ['meta', { name: 'twitter:card',       content: 'summary_large_image' }],
    ['meta', { name: 'twitter:image',      content: 'https://falconcms.github.io/falconcms/falcon-cms-logo.png' }],
    ['meta', { name: 'keywords',           content: 'Laravel CMS, Laravel page builder, drag-and-drop, e-commerce, multi-language, mega menu, hooks API' }],
  ],

  themeConfig: {
    logo: '/falcon-cms-logo.png',
    siteTitle: 'FalconCMS',

    nav: [
      { text: 'Guide', link: '/guide/introduction' },
      { text: 'Builder', link: '/builder/overview' },
      { text: 'E-commerce', link: '/ecommerce/overview' },
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
      '/ecommerce/': [
        {
          text: 'E-commerce',
          items: [
            { text: 'Overview', link: '/ecommerce/overview' },
            { text: 'Products', link: '/ecommerce/products' },
            { text: 'Orders', link: '/ecommerce/orders' },
            { text: 'Coupons', link: '/ecommerce/coupons' },
          ]
        },
      ],
      '/api/': [
        {
          text: 'API Reference',
          items: [
            { text: 'Hooks', link: '/api/hooks' },
            { text: 'Helper Functions', link: '/api/helpers' },
          ]
        },
      ],
      '/changelog': [
        {
          text: 'Changelog',
          items: [
            { text: 'v1.7.2 (Latest)', link: '/changelog#v1-7-2' },
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
