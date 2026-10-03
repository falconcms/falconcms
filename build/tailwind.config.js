/**
 * Static build of the Falcon theme's Tailwind CSS.
 *
 * The front-end normally ships tailwind.min.js (~400 KB) and compiles utilities in the browser.
 * With the Customizer → Performance "Compiled CSS" option on, the theme instead loads the single
 * purged stylesheet this config produces and skips the runtime entirely.
 *
 * Colours and the sans font are kept as CSS variables, not baked-in values, so bg-primary /
 * text-heading / text-link … stay driven by the Customizer exactly like the runtime build — the
 * theme emits the matching :root variables in app.blade.php.
 *
 * Content is scanned from the Blade templates, which also contain the class strings that Alpine
 * and inline scripts toggle (:class="...", classList), so those are captured too. A class a user
 * types into a builder element's "CSS Class" field lives in the database, not a file, so it is not
 * seen here — that is the one case the runtime build covered and this does not; such a class can
 * be added through custom CSS instead. Regenerate after adding features: `npm run build`.
 */
module.exports = {
  content: [
    '../resources/views/themes/**/*.blade.php',
    '../resources/views/frontend/**/*.blade.php',
    '../resources/views/components/**/*.blade.php',
    '../resources/views/*.blade.php',
  ],
  theme: {
    extend: {
      // rgb(var(--x-rgb) / <alpha-value>) form so opacity modifiers (bg-primary/5, ring-primary/20)
      // resolve against the Customizer colour. app.blade.php emits the matching "r g b" channels.
      colors: {
        primary: 'rgb(var(--primary-rgb) / <alpha-value>)',
        'primary-hover': 'rgb(var(--primary-hover-rgb) / <alpha-value>)',
        secondary: 'rgb(var(--secondary-rgb) / <alpha-value>)',
        heading: 'rgb(var(--heading-rgb) / <alpha-value>)',
        body: 'rgb(var(--body-rgb) / <alpha-value>)',
        link: 'rgb(var(--link-rgb) / <alpha-value>)',
        'link-hover': 'rgb(var(--link-hover-rgb) / <alpha-value>)',
      },
      fontFamily: {
        sans: ['var(--font-sans)', 'sans-serif'],
      },
    },
  },
  // Utilities that are only ever produced by JS string-building (not written whole in a template)
  // would be purged; the few the theme relies on are kept here so interaction states never break.
  safelist: [
    'hidden', 'block', 'flex', 'grid',
    'opacity-0', 'opacity-100',
    'overflow-hidden',
    'rotate-180',
    'translate-x-0', '-translate-x-full', 'translate-x-full',
    'translate-y-0', '-translate-y-full',
    'scale-95', 'scale-100',
    'pointer-events-none', 'pointer-events-auto',
    { pattern: /^(bg|text|border)-(primary|primary-hover|secondary|heading|body|link|link-hover)$/, variants: ['hover', 'focus'] },
  ],
}
