# Falcon CMS Localization Guide

This guide explains how to manage multilingual content, menus, and settings in Falcon CMS.

## 1. Post & Page Translations
Falcon CMS uses an `origin_id` system to link translations.
- **How to translate:** Edit a post/page and use the "Translate" or "Make Multilingual Copy" feature.
- **Lineage:** All translations share the same root `origin_id`, ensuring the language switcher can always find every version.
- **Slug Logic:** Slugs are automatically generated based on the language. For non-English languages, native characters are preserved where possible.

## 2. Menu Localization
Menus can be localized using two methods:

### Method A: Location-Based (Recommended)
1. Create a menu (e.g., "Main Menu English").
2. Set its location to `Header`.
3. Create another menu (e.g., "Main Menu Bengali").
4. Set its location also to `Header`.
5. The system will automatically fetch the menu that matches the current site language.

### Method B: Slug-Based Fallback
If you call a menu by slug, like `get_lazy_menu('main-nav')`, the system will automatically look for:
- `main-nav-bn` (if current language is Bengali)
- `main-nav-en` (if current language is English)

## 3. Localized Settings (Footer, Copyright, etc.)
The `get_cms_option($key)` helper is locale-aware.
- **Naming Convention:** Append `_{lang_code}` to the setting key.
- **Example:** 
    - `footer_copyright` (Default/English)
    - `footer_copyright_bn` (Bengali translation)
- **Behavior:** The system first looks for the localized key. If not found, it falls back to the default key.

## 4. Localized Widgets
Widgets now support a `lang_code` property.
- Widgets assigned to a specific language will only appear when that language is active.
- Widgets with no language assigned (NULL) will appear in all languages.

## 5. URL Structure & ISO Codes
URLs follow the structure: `{host}/{lang_code}/{post_type}/{slug}`.
The following regional ISO codes are used for better SEO and regional targeting:
- **KR:** South Korea
- **GB:** United Kingdom
- **CN:** China
- **SA:** Saudi Arabia
- **JP:** Japan
- **BD:** Bangladesh
- **IN:** India

## 6. Language Switcher (Dropdown)
To display the language switcher in your theme or content, you can use the following methods:

### In PHP (Blade Templates):
```php
{{ lazy_lang_dropdown() }}
```

### Using Shortcode (in Post/Page content or Widgets):
```text
[lazy_lang_dropdown]
```

## 7. Flag Assets
All flags are stored locally in `public/assets/flags/` to ensure high performance and offline reliability. They are mapped from language codes (e.g., `ko`) to country codes (e.g., `kr`) automatically.
