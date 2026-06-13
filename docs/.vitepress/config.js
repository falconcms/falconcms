import { defineConfig } from 'vitepress'

export default defineConfig({
  lang: 'en-US',
  title: 'Lazy CMS Builder',
  titleTemplate: '%s | Lazy CMS Builder',
  description: 'A WordPress-like drag-and-drop CMS package for Laravel — page builder, e-commerce, multi-language, hooks API, mega menus, and more.',
  lastUpdated: true,

  head: [
    ['link', { rel: 'icon', href: '/favicon.ico' }],
    ['meta', { property: 'og:type',        content: 'website' }],
    ['meta', { property: 'og:site_name',   content: 'Lazy CMS Builder' }],
    ['meta', { property: 'og:description', content: 'A WordPress-like drag-and-drop CMS package for Laravel — page builder, e-commerce, multi-language, hooks API, mega menus, and more.' }],
    ['meta', { property: 'og:image',       content: '/hero.png' }],
    ['meta', { name: 'twitter:card',       content: 'summary_large_image' }],
    ['meta', { name: 'twitter:image',      content: '/hero.png' }],
    ['meta', { name: 'keywords',           content: 'Laravel CMS, Laravel page builder, drag-and-drop, e-commerce, multi-language, mega menu, hooks API' }],
  ],

  themeConfig: {
    logo: '/logo.svg',
    siteTitle: 'Lazy CMS Builder',

    nav: [
      { text: 'Guide', link: '/guide/introduction' },
      { text: 'Builder', link: '/builder/overview' },
      { text: 'E-commerce', link: '/ecommerce/overview' },
      { text: 'Hooks API', link: '/api/hooks' },
      {
        text: 'v1.0.7',
        items: [
          { text: 'Changelog', link: 'https://github.com/lazycmsapp/lazy-cms-builder/releases' },
          { text: 'Packagist', link: 'https://packagist.org/packages/lazycmsapp/lazy-cms-builder' },
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
          text: 'Lazy Builder',
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
    },

    socialLinks: [
      { icon: 'github', link: 'https://github.com/lazycmsapp/lazy-cms-builder' },
    ],

    footer: {
      message: 'Released under the MIT License.',
      copyright: 'Copyright © 2024-present Tareq Codex'
    },

    search: {
      provider: 'local'
    },

    editLink: {
      pattern: 'https://github.com/lazycmsapp/lazy-cms-builder/edit/main/docs/:path',
      text: 'Edit this page on GitHub'
    },
  }
})
