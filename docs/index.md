---
layout: home
title: FalconCMS Documentation
titleTemplate: Laravel CMS for Developers
description: Learn how to install, configure, customize, and extend FalconCMS — an open-source Laravel CMS with a visual page builder, e-commerce, dynamic content, themes, plugins, and developer APIs.

head:
  - - meta
    - property: og:title
      content: FalconCMS Documentation — Laravel CMS for Developers
  - - meta
    - property: og:description
      content: Build Laravel applications faster with an open-source CMS foundation, visual page builder, e-commerce, themes, plugins, and developer tools.
  - - meta
    - property: og:url
      content: https://falconcms.github.io/falconcms/
  - - meta
    - name: twitter:title
      content: FalconCMS Documentation — Laravel CMS for Developers
  - - meta
    - name: twitter:description
      content: Build Laravel applications faster with an open-source CMS foundation, visual page builder, e-commerce, themes, plugins, and developer tools.
  - - script
    - type: application/ld+json
    - |
      {
        "@context": "https://schema.org",
        "@graph": [
          {
            "@type": "WebSite",
            "@id": "https://falconcms.github.io/falconcms/#website",
            "url": "https://falconcms.github.io/falconcms/",
            "name": "FalconCMS Documentation",
            "description": "Documentation for FalconCMS, an open-source CMS foundation for Laravel.",
            "inLanguage": "en-US"
          },
          {
            "@type": "SoftwareApplication",
            "@id": "https://falconcms.github.io/falconcms/#software",
            "name": "FalconCMS",
            "applicationCategory": "DeveloperApplication",
            "applicationSubCategory": "Content Management System",
            "operatingSystem": "Cross-platform",
            "url": "https://falconcms.com/",
            "downloadUrl": "https://packagist.org/packages/falconcms/falconcms",
            "codeRepository": "https://github.com/falconcms/falconcms",
            "license": "https://opensource.org/licenses/MIT",
            "programmingLanguage": "PHP",
            "softwareRequirements": "PHP 8.3+, Laravel 13+, MySQL 5.7+ or MariaDB 10.3+",
            "offers": {
              "@type": "Offer",
              "price": "0",
              "priceCurrency": "USD"
            }
          }
        ]
      }

hero:
  name: "FalconCMS"
  text: "WordPress-like CMS for Laravel"
  tagline: Build powerful websites faster with a drag-and-drop page builder, full e-commerce, and multi-language support — all inside Laravel.
  image:
    src: /hero.svg
    alt: FalconCMS
  actions:
    - theme: brand
      text: Get Started
      link: /guide/introduction
    - theme: alt
      text: Live Demo
      link: /demo
    - theme: alt
      text: View on GitHub
      link: https://github.com/falconcms/falconcms

features:
  - icon: 🏗️
    title: Drag & Drop Page Builder
    details: Build stunning pages visually with Falcon Builder — containers, columns, and 22 built-in elements including text, image, gallery, counter, accordion, tabs, ticker, card, and more.
    link: /builder/overview
    linkText: Explore Falcon Builder

  - icon: 🎞️
    title: Falcon Slider (Pro)
    details: A layer-based, Slider-Revolution-class slider builder. Design slides on a visual canvas with per-layer timeline animation, text reveals, video & Ken-Burns backgrounds, and responsive per-device layouts.
    link: /slider/overview
    linkText: Explore Falcon Slider

  - icon: 🛒
    title: Full E-commerce — Free
    details: Complete shop system with products, variants, cart, checkout, orders, coupons, and order status management — built into the free core. No Pro license required.
    link: /ecommerce/overview
    linkText: Explore E-commerce

  - icon: 🌐
    title: Multi-language
    details: Native multi-language support with clean URL prefixes (/en/, /bn/). Easily manage content in multiple languages from one dashboard.
    link: /guide/multilang
    linkText: Read the Multi-language Guide

  - icon: 🎨
    title: Theme System
    details: WordPress-like theme structure with isolated frontend views, automated theme sync on update, and full template tag support.
    link: /guide/themes
    linkText: Explore Theme Development

  - icon: 🧩
    title: Plugins
    details: Drop-in plugins add admin pages, settings, routes, views, database tables and hooks — without touching the CMS core. Install one by dragging a .zip, or scaffold your own with make:plugin.
    link: /guide/plugins
    linkText: Explore Plugin Development

  - icon: 🔐
    title: Role-Based Access Control
    details: Granular permissions with 6 predefined roles — Administrator, Editor, Author, Contributor, Subscriber, and User.
    link: /guide/rbac
    linkText: Read the RBAC Overview

  - icon: 🗂️
    title: Mega Menu Builder
    details: Build rich mega menus visually — multi-column layouts with images, headings, and icon lists. Drops down centered to your site width with no CSS required.
    link: /guide/menus
    linkText: Explore Menus

  - icon: ⚡
    title: Hook System
    details: Extend anything with WordPress-like Action and Filter hooks. Add custom fields, modify output, and integrate third-party services cleanly.
    link: /api/hooks
    linkText: Explore the Hooks API

  - icon: 📄
    title: Custom Post Types
    details: Create unlimited custom post types (CPTs) from the dashboard — no code required. Each CPT gets its own admin panel, archive, and single view.
    link: /guide/post-types
    linkText: Learn About Custom Post Types

  - icon: 🔄
    title: Revisions & Autosave
    details: Never lose content. Every save creates a revision. Compare versions side-by-side and restore any previous state instantly.
    link: /guide/post-types#revisions
    linkText: Learn About Revisions
---

## Install FalconCMS in Minutes

Add FalconCMS to your Laravel application with Composer, run the installer, and start managing your content from the dashboard.

```bash
composer require falconcms/falconcms
php artisan falcon:install
```

The installer runs the migrations, publishes assets and the default theme, creates the storage symlink, and creates your admin user. Then visit `/admin` — your credentials are printed in the terminal.

[Read the Installation Guide](/guide/installation)

## What is FalconCMS?

FalconCMS is a Laravel-native content management system for developers who want the productivity of a CMS without giving up the flexibility of Laravel.

Instead of rebuilding pages, posts, media management, menus, custom content, users, and permissions for every project, FalconCMS provides the foundation so you can focus on the application itself. It installs as a Composer package and runs inside your existing Laravel app, alongside your own routes, models, and service providers.

[Read the Introduction](/guide/introduction)

## Model Your Content Your Way

Create custom post types and taxonomies for the content your application actually needs. Simple CPTs are created from **Admin → Post Types** with no code; Advanced Custom Post Types add custom fields and their own taxonomies.

```text
Properties
├── Title
├── Price
├── Location
├── Images
├── Features
└── Property Type
```

Create the content from the dashboard, then query and render it through your Laravel application:

```php
$properties = get_falcon_posts([
    'type'  => 'property',
    'limit' => 10,
]);
```

[Learn About Custom Post Types](/guide/post-types)

## Build Pages Visually with Falcon Builder

Falcon Builder works for pages, posts, custom post types, and the site header and footer.

- Drag-and-drop containers and columns with preset column layouts
- 22 built-in elements — headings, text, images, galleries, video, counters, accordions, tabs, cards, post grids, and more
- Desktop, tablet, and mobile preview with per-device visibility controls
- Library — save any container or column and reuse it as an independent copy
- Global Sections — edit once, update everywhere the section is used
- Header and footer building from **Appearance → Builder Sections**
- Autosave every 30 seconds, plus draft and publish states

[Read the Falcon Builder Guide](/builder/overview) · [See the Builder in Action](/demo)

## Build E-commerce Directly in Laravel

::: tip Included in the core
As of v2.2, the full e-commerce system is part of the free FalconCMS core — no Pro license required.
:::

- **Catalog** — products, variable products with per-variation pricing, product categories and tags, inventory, SKU, weight, and dimensions
- **Storefront** — shop filters by search, price, category, attribute and stock, a session-persistent cart, wishlist, and moderated product reviews
- **Checkout & orders** — guest or registered checkout, coupons and promotions, shipping zones, per-country tax, order workflow, invoices, refunds, and order tracking
- **Payments** — PayPal, Stripe, SSLCommerz, or bank transfer, with schema.org product structured data

[Read the E-commerce Guide](/ecommerce/overview)

## Extend FalconCMS with Plugins

Plugins are the functionality layer — reusable extensions that add admin pages, settings, routes, views, database tables, and custom behavior without modifying the CMS core.

```bash
php artisan make:plugin "SEO Booster"
```

This scaffolds `plugins/seo-booster/` with a manifest, a bootstrap file, and a lifecycle class. Plugins and themes load at the same point in the boot cycle, so a plugin can use every hook a theme can.

[Read the Plugin Development Guide](/guide/plugins)

## Built to Be Extended

Register action and filter hooks from a theme's `functions.php`, a plugin's `plugin.php`, or any Laravel service provider.

```php
// Modify post content before it is rendered on the frontend.
add_falcon_filter('falcon_the_content', function ($content, $post) {
    if ($post->type === 'post') {
        $content .= '<p class="published-note">Published on '
            . cms_date($post->published_at) . '</p>';
    }

    return $content;
});

// Register your own admin page in the FalconCMS sidebar.
add_falcon_action('falcon_admin_menu', function () {
    falcon_add_menu_page([
        'slug'  => 'my-plugin',
        'title' => 'My Plugin',
        'icon'  => 'extension',
        'route' => 'my-plugin.index',
    ]);
});
```

There are 109 hooks — 63 actions and 46 filters.

[Explore the Hooks API](/api/hooks)

## Where Should You Start?

- **New to FalconCMS** — install it and build your first project → [Installation Guide](/guide/installation)
- **Building a website** — pages, posts, custom content, menus, and media → [Post Types](/guide/post-types)
- **A Laravel developer** — themes, plugins, hooks, and template tags → [Hooks API](/api/hooks)
- **Building an online store** — products, cart, checkout, orders, and payments → [E-commerce Overview](/ecommerce/overview)

## Requirements

| Requirement | Version |
| --- | --- |
| PHP | 8.3+ |
| Laravel | 13+ |
| Database | MySQL 5.7+ or MariaDB 10.3+ |

MySQL 8+ is recommended for production. SQLite is not recommended — some features depend on MySQL-specific behaviour. PostgreSQL and SQL Server are not supported.

[View Detailed Requirements](/guide/installation#requirements)

## Explore the Documentation

<div class="doc-map">
<div class="doc-map-card">

### Getting Started

- [Introduction](/guide/introduction)
- [Installation](/guide/installation)
- [Configuration](/guide/configuration)
- [Installing Pro](/guide/pro)
- [Upgrade Guide](/guide/upgrade)

</div>
<div class="doc-map-card">

### Core Concepts

- [Post Types](/guide/post-types)
- [Taxonomies](/guide/taxonomies)
- [Media Library](/guide/media)
- [Menus](/guide/menus)
- [Widgets](/guide/widgets)
- [Roles & Permissions](/guide/rbac)
- [Multi-language](/guide/multilang)

</div>
<div class="doc-map-card">

### Build with FalconCMS

- [Falcon Builder](/builder/overview)
- [Containers & Columns](/builder/containers)
- [Elements](/builder/elements)
- [Global Sections](/builder/global-sections)
- [Library](/builder/library)
- [Falcon Slider](/slider/overview)
- [E-commerce](/ecommerce/overview)
- [Themes](/guide/themes)
- [Plugins](/guide/plugins)

</div>
<div class="doc-map-card">

### Developer Reference

- [Hooks API](/api/hooks)
- [Helper Functions](/api/helpers)
- [Template Tags](/guide/template-tags)
- [Admin Menu API](/api/admin-menu)
- [Settings Fields API](/api/settings-fields)

</div>
</div>

## Built in the Open

FalconCMS is open source and developed in the open. Explore the source code, report issues, contribute improvements, and follow new releases on GitHub.

[View on GitHub](https://github.com/falconcms/falconcms) · [Contribute](https://github.com/falconcms/falconcms/blob/main/CONTRIBUTING.md) · [Report an Issue](https://github.com/falconcms/falconcms/issues) · [Read the Changelog](/changelog)
