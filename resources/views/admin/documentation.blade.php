<x-falcon-cms::layouts.admin>
    <x-slot name="title">Documentation - FalconCMS</x-slot>

    <div class="px-6 py-4">
        <div class="flex items-center justify-between mb-8 border-b border-gray-200 pb-4">
            <div>
                <h1 class="text-3xl font-black text-gray-900">Developer Documentation</h1>
                <p class="text-gray-500 mt-1">Master FalconCMS and build stunning websites with freedom.</p>
            </div>
            <div class="bg-blue-50 text-blue-700 px-4 py-2 rounded-lg text-sm font-bold border border-blue-100">
                v1.2.0 Stable
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-4 gap-8">
            {{-- Navigation Sidebar --}}
            <div class="lg:col-span-1">
                <nav class="sticky top-6 space-y-1" id="doc-nav">
                    <a href="#getting-started" class="nav-link block px-4 py-2 text-sm font-medium text-blue-600 bg-blue-50 rounded-md transition-all duration-200">Getting Started</a>
                    <a href="#updating" class="nav-link block px-4 py-2 text-sm font-medium text-gray-600 hover:bg-gray-50 rounded-md transition-all duration-200">Updating & Syncing</a>
                    <a href="#page-generation" class="nav-link block px-4 py-2 text-sm font-medium text-gray-600 hover:bg-gray-50 rounded-md transition-all duration-200">Page Generation</a>
                    <a href="#custom-routes" class="nav-link block px-4 py-2 text-sm font-medium text-gray-600 hover:bg-gray-50 rounded-md transition-all duration-200">Custom Routes</a>
                    <a href="#helpers" class="nav-link block px-4 py-2 text-sm font-medium text-gray-600 hover:bg-gray-50 rounded-md transition-all duration-200">Helper Functions</a>
                    <a href="#loops" class="nav-link block px-4 py-2 text-sm font-medium text-gray-600 hover:bg-gray-50 rounded-md transition-all duration-200">Displaying Posts (Loops)</a>
                    <a href="#custom-options" class="nav-link block px-4 py-2 text-sm font-medium text-gray-600 hover:bg-gray-50 rounded-md transition-all duration-200">Custom Settings & Options</a>
                    <a href="#seo" class="nav-link block px-4 py-2 text-sm font-medium text-gray-600 hover:bg-gray-50 rounded-md transition-all duration-200">SEO & Metadata</a>
                    <a href="#templates" class="nav-link block px-4 py-2 text-sm font-medium text-gray-600 hover:bg-gray-50 rounded-md transition-all duration-200">Custom Templates</a>
                    <a href="#custom-widgets" class="nav-link block px-4 py-2 text-sm font-medium text-gray-600 hover:bg-gray-50 rounded-md transition-all duration-200">Custom Widgets</a>
                    <a href="#hooks" class="nav-link block px-4 py-2 text-sm font-medium text-gray-600 hover:bg-gray-50 rounded-md transition-all duration-200">Hooks (Actions & Filters)</a>
                    <a href="#ecommerce-hooks" class="nav-link block px-4 py-2 text-sm font-medium text-gray-600 hover:bg-gray-50 rounded-md transition-all duration-200 pl-7">↳ Ecommerce Product Hooks</a>
                    <a href="#checkout-form-hooks" class="nav-link block px-4 py-2 text-sm font-medium text-gray-600 hover:bg-gray-50 rounded-md transition-all duration-200 pl-10">↳ Checkout Form Hooks</a>
                    <a href="#order-hooks" class="nav-link block px-4 py-2 text-sm font-medium text-gray-600 hover:bg-gray-50 rounded-md transition-all duration-200 pl-10">↳ Order & Invoice Hooks</a>
                    <a href="#builder-elements" class="nav-link block px-4 py-2 text-sm font-medium text-gray-600 hover:bg-gray-50 rounded-md transition-all duration-200">Custom Builder Elements</a>
                    <a href="#rest-api" class="nav-link block px-4 py-2 text-sm font-medium text-gray-600 hover:bg-gray-50 rounded-md transition-all duration-200">REST API & Headless</a>
                    <a href="#multilingual" class="nav-link block px-4 py-2 text-sm font-medium text-gray-600 hover:bg-gray-50 rounded-md transition-all duration-200">Multilingual & Localization</a>
                    <a href="#rbac" class="nav-link block px-4 py-2 text-sm font-medium text-gray-600 hover:bg-gray-50 rounded-md transition-all duration-200">Roles & Permissions (RBAC)</a>
                    <a href="#security" class="nav-link block px-4 py-2 text-sm font-medium text-gray-600 hover:bg-gray-50 rounded-md transition-all duration-200">Security Hardening</a>
                    <a href="#maintenance-mode" class="nav-link block px-4 py-2 text-sm font-medium text-gray-600 hover:bg-gray-50 rounded-md transition-all duration-200 pl-7">↳ Maintenance Mode</a>
                    <a href="#theme-isolation" class="nav-link block px-4 py-2 text-sm font-medium text-gray-600 hover:bg-gray-50 rounded-md transition-all duration-200">Theme Isolation & Sync</a>
                </nav>
            </div>

            {{-- Content --}}
            <div class="lg:col-span-3 space-y-12 pb-20">
                
                {{-- Section: Getting Started --}}
                <section id="getting-started">
                    <h2 class="text-2xl font-bold text-gray-900 mb-4">Getting Started</h2>
                    <p class="text-gray-700 mb-6">FalconCMS is designed to give you full control over your content while keeping the development process simple.</p>
                    
                    <div class="bg-white border border-gray-200 rounded-xl p-6 shadow-sm mb-8">
                        <h3 class="font-bold text-gray-800 mb-4">Fresh Installation</h3>
                        <div class="bg-gray-900 rounded-lg p-4 text-gray-300 font-mono text-xs space-y-4">
                            <div>
                                <span class="text-gray-500"># 1. Install via composer</span><br>
                                <code class="text-green-400">composer require falconcms/falconcms</code>
                            </div>
                            <div>
                                <span class="text-gray-500"># 2. Run CMS installer</span><br>
                                <code class="text-green-400">php artisan lazy:install</code>
                                <p class="text-[10px] text-gray-400 mt-1 italic">// This handles migrations, assets, themes, and admin creation.</p>
                            </div>
                            <div>
                                <span class="text-gray-500"># 3. View all available commands</span><br>
                                <code class="text-green-400">php artisan lazy</code>
                                <p class="text-[10px] text-gray-400 mt-1 italic">// Lists all specialized FalconCMS commands.</p>
                            </div>
                            <div>
                                <span class="text-gray-500"># 4. Seed demo data (Optional)</span><br>
                                <code class="text-green-400">php artisan lazy:seed</code>
                                <p class="text-[10px] text-gray-400 mt-1 italic">// Populates default menus and initial demo content.</p>
                            </div>
                        </div>
                    </div>
                </section>

                {{-- Section: Updating --}}
                <section id="updating">
                    <h2 class="text-2xl font-bold text-gray-900 mb-4">Updating & Syncing</h2>
                    <p class="text-gray-700 mb-6">Whenever you update the package or make changes to the core files, use the built-in update command to keep everything in sync.</p>

                    <div class="bg-gray-900 rounded-lg p-4 text-gray-300 font-mono text-xs space-y-4">
                        <div>
                            <span class="text-gray-500"># 1. Update composer package</span><br>
                            <code class="text-green-400">composer require falconcms/falconcms</code>
                        </div>
                        <div>
                            <span class="text-gray-500"># 2. Run Sync Command</span><br>
                            <code class="text-green-400">php artisan lazy:update</code>
                            <p class="text-[10px] text-gray-400 mt-1 italic">// This automates migrations, refreshing assets/themes, and clearing cache.</p>
                        </div>
                    </div>
                </section>

                {{-- Section: Page Generation --}}
                <section id="page-generation">
                    <h2 class="text-2xl font-bold text-gray-900 mb-4">Page Generation</h2>
                    <p class="text-gray-700 mb-6">Quickly scaffold new dashboard pages, controllers, and menu items with a single command.</p>

                    <div class="bg-gray-900 rounded-lg p-4 text-gray-300 font-mono text-xs space-y-4">
                        <div>
                            <span class="text-gray-500"># Create a new dashboard page (e.g. Portfolio)</span><br>
                            <code class="text-green-400">php artisan make:lazy-page Portfolio</code>
                            <p class="text-[10px] text-gray-400 mt-1 italic">// This creates a controller, a view, and adds a menu item in the sidebar.</p>
                        </div>
                    </div>
                </section>

                {{-- Section: Custom Routes --}}
                <section id="custom-routes">
                    <h2 class="text-2xl font-bold text-gray-900 mb-4">Custom Routes</h2>
                    <p class="text-gray-700 mb-4">You can define your own routes in <code>routes/web.php</code>. These will take precedence over the CMS catch-all routes.</p>
                    <div class="bg-gray-900 rounded-xl p-6 text-gray-300 font-mono text-sm overflow-x-auto">
                        <pre><code>// Example: Custom Route for Blogs
Route::get('/blogs', function () {
    return view('blogs');
});</code></pre>
                    </div>
                </section>

                {{-- Section: Helpers --}}
                <section id="helpers">
                    <h2 class="text-2xl font-bold text-gray-900 mb-4">Global Helper Functions</h2>
                    <div class="space-y-6">
                        {{-- get_falcon_posts --}}
                        <div class="border border-gray-200 rounded-xl p-6 bg-white shadow-sm">
                            <h3 class="font-bold text-blue-600 mb-2">get_falcon_posts($args)</h3>
                            <p class="text-sm text-gray-600 mb-2">Fetch posts with advanced options like pagination and filtering.</p>
                            <code class="block bg-gray-50 p-3 rounded text-sm mb-2">$posts = get_falcon_posts(['post_type' => 'post', 'limit' => 10, 'paginate' => true]);</code>
                        </div>

                        {{-- the_lazy_pagination --}}
                        <div class="border border-gray-200 rounded-xl p-6 bg-white shadow-sm">
                            <h3 class="font-bold text-blue-600 mb-2">the_lazy_pagination($items, $view)</h3>
                            <p class="text-sm text-gray-600 mb-4">Render pagination links with custom design support.</p>
                            <div class="bg-gray-900 rounded-lg p-4 text-gray-300 font-mono text-xs">
                                <pre><code>@verbatim
{!! the_lazy_pagination($postItems) !!}
{!! the_lazy_pagination($postItems, 'custom-view') !!}
@endverbatim</code></pre>
                            </div>
                        </div>

                        {{-- get_lazy_post --}}
                        <div class="border border-gray-200 rounded-xl p-6 bg-white shadow-sm">
                            <h3 class="font-bold text-blue-600 mb-2">get_lazy_post($slugOrId)</h3>
                            <p class="text-sm text-gray-600 mb-2">Get a single post/page data by its slug or ID.</p>
                            <code class="block bg-gray-50 p-3 rounded text-sm">$post = get_lazy_post('about-us');</code>
                        </div>

                        {{-- get_cms_option --}}
                        <div class="border border-gray-200 rounded-xl p-6 bg-white shadow-sm">
                            <h3 class="font-bold text-blue-600 mb-2">get_cms_option($key, $default)</h3>
                            <p class="text-sm text-gray-600 mb-2">Get any setting value from the CMS settings table.</p>
                            <code class="block bg-gray-50 p-3 rounded text-sm">$site_name = get_cms_option('site_title', 'FalconCMS');</code>
                        </div>

                        {{-- get_falcon_excerpt --}}
                        <div class="border border-gray-200 rounded-xl p-6 bg-white shadow-sm">
                            <h3 class="font-bold text-blue-600 mb-2">get_falcon_excerpt($post, $limit)</h3>
                            <p class="text-sm text-gray-600 mb-2">Extracts plain text from builder JSON or rich text content.</p>
                            <code class="block bg-gray-50 p-3 rounded text-sm">$excerpt = get_falcon_excerpt($post, 150);</code>
                        </div>

                        {{-- get_lazy_categories --}}
                        <div class="border border-gray-200 rounded-xl p-6 bg-white shadow-sm">
                            <h3 class="font-bold text-blue-600 mb-2">get_lazy_categories($taxonomy)</h3>
                            <p class="text-sm text-gray-600 mb-2">Fetch all categories or custom taxonomy terms.</p>
                            <code class="block bg-gray-50 p-3 rounded text-sm">$cats = get_lazy_categories('category');</code>
                        </div>
                    </div>
                </section>

                {{-- Section: Archives --}}
                <section id="archives">
                    <h2 class="text-2xl font-bold text-gray-900 mb-4">Archive & Filtering</h2>
                    <p class="text-gray-700 mb-4">CMS automatically handles archive pages for categories and tags.</p>
                    <div class="bg-gray-100 p-4 rounded-lg space-y-2 text-sm font-mono text-gray-700">
                        <div><span class="text-blue-600 font-bold">Category URL:</span> /category/{slug}</div>
                        <div><span class="text-blue-600 font-bold">Tag URL:</span> /tag/{slug}</div>
                    </div>
                </section>

                {{-- Section: Loops --}}
                <section id="loops">
                    <h2 class="text-2xl font-bold text-gray-900 mb-4">Displaying Posts (Loops)</h2>
                    <p class="text-gray-700 mb-4">Use <code>the_lazy_loop()</code> for a quick grid, or write your own <code>@@foreach</code> for total freedom.</p>
                    
                    <h3 class="font-bold mt-6 mb-2">Method 1: Fast Grid</h3>
                    <div class="bg-gray-900 rounded-xl p-6 text-gray-300 font-mono text-sm mb-6">
                        <pre><code>the_lazy_loop(['post_type' => 'post', 'limit' => 6]);</code></pre>
                    </div>

                    <h3 class="font-bold mb-2">Method 2: Custom HTML with Pagination (Recommended)</h3>
                    <div class="bg-gray-900 rounded-xl p-6 text-gray-300 font-mono text-sm">
                        <pre><code>@verbatim
@php $items = get_falcon_posts(['post_type' => 'post', 'limit' => 6, 'paginate' => true]); @endphp

@foreach($items as $post)
    <div class="card">
        <h2>{{ $post->title }}</h2>
        <a href="{{ route('frontend.category', $post->categories->first()->slug) }}">
            {{ $post->categories->first()->name }}
        </a>
    </div>
@endforeach

<div class="pagination">
    {!! the_lazy_pagination($items) !!}
</div>
@endverbatim</code></pre>
                    </div>
                </section>

                {{-- Section: Custom Settings & Options Pages --}}
                <section id="custom-options">
                    <h2 class="text-2xl font-bold text-gray-900 mb-4">Custom Settings & Options Pages</h2>
                    <p class="text-gray-700 mb-6">You can extend the CMS by adding new fields to existing settings or creating entirely new admin pages via <code>config/falcon-options.php</code>.</p>

                    <div class="space-y-8">
                        {{-- Adding to Main Settings --}}
                        <div class="bg-white border border-gray-200 rounded-xl p-6 shadow-sm">
                            <h3 class="text-lg font-bold text-blue-600 mb-3">1. Add Fields to Main Settings</h3>
                            <p class="text-sm text-gray-600 mb-4">To add new inputs to the <b>General Settings</b> page, use the <code>hooks</code> array in your config.</p>
                            <div class="bg-gray-900 rounded-lg p-4 text-gray-300 font-mono text-xs overflow-x-auto">
                                <pre><code>@verbatim
// config/falcon-options.php
'hooks' => [
    'general-settings' => [
        'fields' => [
            'site_tagline' => [
                'type' => 'text',
                'label' => 'Site Tagline',
                'placeholder' => 'Enter slogan...',
            ],
        ]
    ]
]
@endverbatim</code></pre>
                            </div>
                        </div>

                        {{-- Creating New Pages --}}
                        <div class="bg-white border border-gray-200 rounded-xl p-6 shadow-sm">
                            <h3 class="text-lg font-bold text-blue-600 mb-3">2. Create a Custom Admin Page</h3>
                            <p class="text-sm text-gray-600 mb-4">You can create standalone pages that appear in the sidebar using the <code>pages</code> array.</p>
                            <div class="bg-gray-900 rounded-lg p-4 text-gray-300 font-mono text-xs overflow-x-auto">
                                <pre><code>@verbatim
// config/falcon-options.php
'pages' => [
    'theme-settings' => [
        'title' => 'Theme Options',
        'icon'  => 'palette',
        'group' => 'Appearance',
        'fields' => [
            'primary_color' => [
                'type' => 'text',
                'label' => 'Primary Brand Color',
                'default' => '#007bff'
            ],
            'footer_text' => [
                'type' => 'textarea',
                'label' => 'Footer Copyright Text',
            ],
        ]
    ]
]
@endverbatim</code></pre>
                            </div>
                        </div>

                        {{-- Displaying Values --}}
                        <div class="bg-white border border-gray-200 rounded-xl p-6 shadow-sm">
                            <h3 class="text-lg font-bold text-blue-600 mb-3">3. How to Show Values in Frontend</h3>
                            <p class="text-sm text-gray-600 mb-4">Values are automatically saved to the database. Use <code>get_cms_option()</code> to retrieve them anywhere.</p>
                            <div class="bg-gray-900 rounded-lg p-4 text-gray-300 font-mono text-xs space-y-4">
                                <div>
                                    <span class="text-gray-500">// Get simple text value</span><br>
                                    <code class="text-blue-400">@verbatim
{{ get_cms_option('site_tagline') }}
@endverbatim</code>
                                </div>
                                <div>
                                    <span class="text-gray-500">// Get image URL</span><br>
                                    <code class="text-blue-400">@verbatim
<img src="{{ asset(get_cms_option('header_logo')) }}">
@endverbatim</code>
                                </div>
                            </div>
                        </div>
                    </div>
                </section>

                {{-- Section: SEO & Metadata --}}
                <section id="seo">
                    <h2 class="text-2xl font-bold text-gray-900 mb-4">SEO & Metadata</h2>
                    <p class="text-gray-700 mb-6">FalconCMS provides a built-in SEO engine that handles meta tags, social sharing (OpenGraph/X), and JSON-LD schema markup automatically.</p>

                    <div class="space-y-8">
                        {{-- Method 1: Automatic --}}
                        <div class="bg-white border border-gray-200 rounded-xl p-6 shadow-sm">
                            <h3 class="text-lg font-bold text-blue-600 mb-3">1. The Automatic Component (Best)</h3>
                            <p class="text-sm text-gray-600 mb-4">Add this single line inside your <code>&lt;head&gt;</code> tag. It will handle everything based on the current post or page.</p>
                            <div class="bg-gray-900 rounded-lg p-4 text-gray-300 font-mono text-xs overflow-x-auto">
                                <pre><code>@verbatim
<!-- Inside layout/app.blade.php head section -->
<x-falcon-cms::frontend.seo-meta :post="$post ?? null" :title="$title ?? null" />
@endverbatim</code></pre>
                            </div>
                        </div>

                        {{-- Method 2: Manual Access --}}
                        <div class="bg-white border border-gray-200 rounded-xl p-6 shadow-sm">
                            <h3 class="text-lg font-bold text-blue-600 mb-3">2. Manual Value Access</h3>
                            <p class="text-sm text-gray-600 mb-4">If you want to access specific SEO values manually, use the <code>seo_meta</code> array on the post object.</p>
                            <div class="bg-gray-900 rounded-lg p-4 text-gray-300 font-mono text-xs space-y-4">
                                <pre><code>@verbatim
@php $seo = $post->seo_meta; @endphp

<!-- Get Meta Title -->
{{ $seo['title'] ?? $post->title }}

<!-- Get OpenGraph Image -->
@if(!empty($seo['og_image']))
    <meta property="og:image" content="{{ asset('storage/' . $seo['og_image']) }}">
@endif
@endverbatim</code></pre>
                            </div>
                        </div>

                        {{-- Section: Sitemap & Robots --}}
                        <div class="bg-blue-50 border border-blue-100 rounded-xl p-6">
                            <h3 class="text-lg font-bold text-blue-700 mb-2">Sitemap & Robots.txt</h3>
                            <p class="text-sm text-blue-600">These files are served dynamically at the root of your site:</p>
                            <ul class="mt-3 list-disc list-inside text-sm text-blue-600 space-y-1">
                                <li><code>your-site.com/sitemap.xml</code></li>
                                <li><code>your-site.com/robots.txt</code></li>
                            </ul>
                        </div>
                    </div>
                </section>

                {{-- Section: Templates --}}
                <section id="templates">
                    <h2 class="text-2xl font-bold text-gray-900 mb-4">Theme Development Guide</h2>
                    <p class="text-gray-700 mb-6">FalconCMS allows you to build custom themes with total creative freedom. Follow this guide to create your first theme.</p>

                    <div class="space-y-8">
                        {{-- Theme Structure --}}
                        <div class="bg-white border border-gray-200 rounded-xl p-6 shadow-sm">
                            <h3 class="text-lg font-bold text-blue-600 mb-3">1. Theme Structure & Location</h3>
                            <p class="text-sm text-gray-600 mb-4">Themes are located in <code>resources/views/themes/{theme-name}/</code>. A standard theme should follow this structure:</p>
                            <div class="bg-gray-100 p-4 rounded-lg font-mono text-xs text-gray-700 leading-relaxed">
                                your-theme-name/<br>
                                ├── layouts/ &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp; # Master layout (app.blade.php)<br>
                                ├── partials/ &nbsp;&nbsp;&nbsp;&nbsp;&nbsp; # Reusable parts (header, footer)<br>
                                ├── widgets/ &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp; # Custom theme widgets<br>
                                ├── index.blade.php &nbsp; # Home / Blog index<br>
                                ├── single.blade.php # Single post view<br>
                                ├── page.blade.php &nbsp;&nbsp; # Single page view<br>
                                ├── archive.blade.php # Category / Tag archives<br>
                                └── functions.php &nbsp;&nbsp; # Theme hooks & logic
                            </div>
                        </div>

                        {{-- Technical Requirements --}}
                        <div class="bg-white border border-gray-200 rounded-xl p-6 shadow-sm">
                            <h3 class="text-lg font-bold text-blue-600 mb-3">2. Essential Technical Criteria</h3>
                            <p class="text-sm text-gray-600 mb-4">To ensure your theme works perfectly with core features, you must include these elements:</p>
                            
                            <div class="space-y-4">
                                <div class="p-3 bg-gray-50 rounded border-l-4 border-blue-400">
                                    <p class="text-xs font-bold text-gray-500 uppercase mb-1">SEO Support (In &lt;head&gt;)</p>
                                    <code class="text-xs">@verbatim
<x-falcon-cms::frontend.seo-meta :post="$post ?? null" />
@endverbatim</code>
                                </div>

                                <div class="p-3 bg-gray-50 rounded border-l-4 border-blue-400">
                                    <p class="text-xs font-bold text-gray-500 uppercase mb-1">Head Hook (Before &lt;/head&gt;)</p>
                                    <code class="text-xs">@verbatim
{!! do_falcon_action('lazy_head') !!}
@endverbatim</code>
                                </div>

                                <div class="p-3 bg-gray-50 rounded border-l-4 border-blue-400">
                                    <p class="text-xs font-bold text-gray-500 uppercase mb-1">Footer Hook (Before &lt;/body&gt;)</p>
                                    <code class="text-xs">@verbatim
{!! do_falcon_action('falcon_footer') !!}
@endverbatim</code>
                                </div>
                            </div>
                        </div>

                        {{-- Template Hierarchy --}}
                        <div class="bg-amber-50 border border-amber-100 rounded-xl p-6">
                            <h3 class="text-lg font-bold text-amber-800 mb-2">Template Overrides</h3>
                            <p class="text-sm text-amber-700">If you create a file in <code>resources/views/</code> that matches a page slug (e.g., <code>contact.blade.php</code>), it will override the theme's default <code>page.blade.php</code>.</p>
                        </div>
                    </div>
                </section>

                {{-- Section: Custom Widgets --}}
                <section id="custom-widgets">
                    <h2 class="text-2xl font-bold text-gray-900 mb-4">Custom Widgets</h2>
                    <p class="text-gray-700 mb-6">Master the widget system by creating your own custom widgets within your theme. The system automatically detects any blade file in your theme's widget directory.</p>

                    <div class="space-y-8">
                        {{-- Step 1: Create File --}}
                        <div class="bg-white border border-gray-200 rounded-xl p-6 shadow-sm">
                            <h3 class="text-lg font-bold text-blue-600 mb-3">1. Create Widget File</h3>
                            <p class="text-sm text-gray-600 mb-4">Create a new Blade file in your active theme's widget folder:</p>
                            <code class="block bg-gray-50 p-3 rounded text-sm mb-4">/resources/views/themes/falcon-theme/widgets/about-author.blade.php</code>
                            <p class="text-sm text-gray-600 mb-4">Once created, it will automatically appear in <b>Appearance > Widgets</b> as "About Author".</p>
                        </div>

                        {{-- Step 2: Example Code --}}
                        <div class="bg-white border border-gray-200 rounded-xl p-6 shadow-sm">
                            <h3 class="text-lg font-bold text-blue-600 mb-3">2. Example Widget Code</h3>
                            <p class="text-sm text-gray-600 mb-4">Use the <code>$widget</code> variable to access settings and title.</p>
                            <div class="bg-gray-900 rounded-lg p-4 text-gray-300 font-mono text-xs overflow-x-auto">
                                <pre><code>@verbatim
<!-- about-author.blade.php -->
<div class="widget mb-10 p-6 bg-gray-50 rounded-xl">
    @if($widget->title)
        <h4 class="widget-title text-xl font-bold mb-4">{{ $widget->title }}</h4>
    @endif
    
    <div class="author-box flex items-center gap-4">
        <img src="{{ asset('theme/images/avatar.jpg') }}" class="w-16 h-16 rounded-full">
        <div>
            <p class="text-gray-600 text-sm">Hello, I'm a passionate developer building amazing things with FalconCMS.</p>
        </div>
    </div>
</div>
@endverbatim</code></pre>
                            </div>
                        </div>

                        {{-- Step 3: Registering in Theme --}}
                        <div class="bg-white border border-gray-200 rounded-xl p-6 shadow-sm">
                            <h3 class="text-lg font-bold text-blue-600 mb-3">3. Rendering Widgets</h3>
                            <p class="text-sm text-gray-600 mb-4">To display a widget area (like a sidebar) in your theme, use the global helper:</p>
                            <div class="bg-gray-900 rounded-lg p-4 text-gray-300 font-mono text-xs">
                                <pre><code>@verbatim
<!-- In your sidebar.blade.php -->
{!! render_lazy_widgets('primary-sidebar') !!}
@endverbatim</code></pre>
                            </div>
                        </div>
                    </div>
                </section>

                {{-- Section: Hooks System --}}
                <section id="hooks">
                    <h2 class="text-2xl font-bold text-gray-900 mb-4">Hooks System (Actions & Filters)</h2>
                    <p class="text-gray-700 mb-6">FalconCMS features a powerful hook architecture similar to WordPress, allowing you to extend the core functionality without modifying package files.</p>

                    {{-- Theme Functions.php --}}
                    <div class="bg-blue-50 border border-blue-100 rounded-xl p-6 mb-8">
                        <h3 class="text-lg font-bold text-blue-700 mb-2">Theme Functions File</h3>
                        <p class="text-sm text-blue-600 mb-4">Just like WordPress, you can create a <code>functions.php</code> file inside your theme folder to register hooks, add custom logic, or include scripts.</p>
                        <code class="block bg-white/50 p-2 rounded text-xs border border-blue-200">/resources/views/themes/falcon-theme/functions.php</code>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
                        {{-- Actions --}}
                        <div class="space-y-4">
                            <h3 class="text-lg font-bold text-gray-800 flex items-center gap-2">
                                <span class="w-2 h-2 bg-blue-600 rounded-full"></span>
                                Action Hooks
                            </h3>
                            <p class="text-sm text-gray-600">Actions allow you to "do something" at specific points in the page lifecycle.</p>
                            
                            <div class="bg-gray-50 border border-gray-200 rounded-lg p-4">
                                <h4 class="text-xs font-bold text-gray-400 uppercase mb-2">Frontend Actions</h4>
                                <ul class="text-xs space-y-1 text-gray-700 mb-4">
                                    <li><code>lazy_head</code> - Inside frontend &lt;head&gt;</li>
                                    <li><code>falcon_footer</code> - Before frontend &lt;/body&gt;</li>
                                    <li><code>lazy_before_content</code> - Above post body</li>
                                    <li><code>lazy_after_content</code> - Below post body</li>
                                </ul>

                                <h4 class="text-xs font-bold text-gray-400 uppercase mb-2">Admin Panel Actions</h4>
                                <ul class="text-xs space-y-1 text-gray-700">
                                    <li><code>lazy_admin_head</code> - Inside admin &lt;head&gt;</li>
                                    <li><code>falcon_admin_footer</code> - Before admin &lt;/body&gt;</li>
                                    <li><code>lazy_admin_bar_right_before</code> - Top bar right side</li>
                                    <li><code>lazy_settings_form_top</code> - General Settings top</li>
                                    <li><code>lazy_settings_form_bottom</code> - General Settings bottom</li>
                                    <li><code>lazy_seo_settings_form_top</code> - SEO Settings top</li>
                                    <li><code>lazy_seo_settings_form_bottom</code> - SEO Settings bottom</li>
                                </ul>
                            </div>

                            <div class="bg-gray-900 rounded-lg p-4 text-gray-300 font-mono text-xs">
                                <pre><code>@verbatim
// Example: Add CSS to Admin
add_falcon_action('lazy_admin_head', function() {
    echo "<style>body { border-top: 4px solid red; }</style>";
});
@endverbatim</code></pre>
                            </div>
                        </div>

                        {{-- Filters --}}
                        <div class="space-y-4">
                            <h3 class="text-lg font-bold text-gray-800 flex items-center gap-2">
                                <span class="w-2 h-2 bg-green-600 rounded-full"></span>
                                Filter Hooks
                            </h3>
                            <p class="text-sm text-gray-600">Filters allow you to modify data before it is rendered or saved.</p>

                            <div class="bg-gray-50 border border-gray-200 rounded-lg p-4">
                                <h4 class="text-xs font-bold text-gray-400 uppercase mb-2">Available Filters</h4>
                                <ul class="text-xs space-y-2 text-gray-700">
                                    <li><code>lazy_the_content</code> - Filters post body HTML</li>
                                    <li><code>lazy_post_title</code> - Filters post title</li>
                                    <li><code>cms_theme_options</code> - Modify theme settings pages (via functions.php)</li>
                                    <li><code>lazy_general_settings_fields</code> - Simplified filter for General Settings</li>
                                    <li><code>lazy_users_edit_fields</code> - Simplified filter for User Edit page</li>
                                </ul>
                            </div>

                            <div class="bg-gray-900 rounded-lg p-4 text-gray-300 font-mono text-xs">
                                <pre><code>@verbatim
// Example: Simplified Field Hook
add_falcon_filter('lazy_general_settings_fields', function($fields) {
    $fields['copyright_text'] = [
        'type' => 'text',
        'label' => 'Copyright'
    ];
    return $fields;
});
@endverbatim</code></pre>
                            </div>

                            <h3 class="text-lg font-bold text-gray-800 mt-6 flex items-center gap-2">
                                <span class="w-2 h-2 bg-red-600 rounded-full"></span>
                                Removing Hooks & Fields
                            </h3>
                            <p class="text-sm text-gray-600">You can unregister any previously added hook or remove dynamic fields using removal helpers and high-priority filters.</p>
                            
                            <h4 class="text-xs font-bold text-gray-500 uppercase mt-4 mb-2">1. Removing Hooks</h4>
                            <div class="bg-gray-900 rounded-lg p-4 text-gray-300 font-mono text-xs mb-4">
                                <pre><code>@verbatim
remove_falcon_action('tag', 'callback', $priority);
remove_lazy_filter('tag', 'callback', $priority);
@endverbatim</code></pre>
                            </div>

                            <h4 class="text-xs font-bold text-gray-500 uppercase mt-4 mb-2">2. Removing Dynamic Fields</h4>
                            <div class="bg-gray-900 rounded-lg p-4 text-gray-300 font-mono text-xs">
                                <pre><code>@verbatim
add_falcon_filter('lazy_general_settings_fields', function($fields) {
    unset($fields['copyright_text']); // Remove a specific field
    return $fields;
}, 20); // Priority 20 to run after adding
@endverbatim</code></pre>
                            </div>
                        </div>
                    </div>
                </section>

                {{-- Section: Ecommerce Product Hooks --}}
                <section id="ecommerce-hooks">
                    <h2 class="text-2xl font-bold text-gray-900 mb-2">Ecommerce Product Hooks</h2>
                    <p class="text-gray-700 mb-6">WordPress-style hooks placed throughout the single product pages. Use them in your theme's <code>functions.php</code> to inject content, modify output, add custom fields, or remove sections — without touching package files.</p>

                    {{-- Helper Functions --}}
                    <div class="bg-white border border-gray-200 rounded-xl p-6 shadow-sm mb-8">
                        <h3 class="text-lg font-bold text-blue-600 mb-4">Hook Helper Functions</h3>
                        <div class="overflow-x-auto">
                            <table class="w-full text-xs text-left border border-gray-100 rounded-lg overflow-hidden">
                                <thead class="bg-gray-50 text-gray-500 uppercase">
                                    <tr><th class="px-3 py-2">Function</th><th class="px-3 py-2">Description</th></tr>
                                </thead>
                                <tbody class="divide-y divide-gray-100">
                                    <tr><td class="px-3 py-2 font-mono text-blue-600">add_falcon_action($tag, $cb, $priority)</td><td class="px-3 py-2">Register an action</td></tr>
                                    <tr><td class="px-3 py-2 font-mono text-blue-600">do_falcon_action($tag, ...$args)</td><td class="px-3 py-2">Fire an action</td></tr>
                                    <tr><td class="px-3 py-2 font-mono text-blue-600">remove_falcon_action($tag, $cb, $priority)</td><td class="px-3 py-2">Remove a registered action</td></tr>
                                    <tr><td class="px-3 py-2 font-mono text-blue-600">add_falcon_filter($tag, $cb, $priority)</td><td class="px-3 py-2">Register a filter</td></tr>
                                    <tr><td class="px-3 py-2 font-mono text-blue-600">apply_falcon_filters($tag, $value, ...$args)</td><td class="px-3 py-2">Apply filters and return result</td></tr>
                                    <tr><td class="px-3 py-2 font-mono text-blue-600">remove_lazy_filter($tag, $cb, $priority)</td><td class="px-3 py-2">Remove a registered filter</td></tr>
                                    <tr><td class="px-3 py-2 font-mono text-blue-600">has_lazy_action($tag)</td><td class="px-3 py-2">Check if action has registered callbacks</td></tr>
                                    <tr><td class="px-3 py-2 font-mono text-blue-600">has_lazy_filter($tag)</td><td class="px-3 py-2">Check if filter has registered callbacks</td></tr>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    {{-- Shared Hooks --}}
                    <div class="bg-white border border-gray-200 rounded-xl p-6 shadow-sm mb-8">
                        <h3 class="text-lg font-bold text-blue-600 mb-1">Shared Hooks <span class="text-sm font-normal text-gray-500">(fire on both Simple & Variable pages)</span></h3>
                        <div class="overflow-x-auto mt-4">
                            <table class="w-full text-xs text-left border border-gray-100 rounded-lg overflow-hidden">
                                <thead class="bg-gray-50 text-gray-500 uppercase">
                                    <tr><th class="px-3 py-2">Hook Tag</th><th class="px-3 py-2 w-16">Type</th><th class="px-3 py-2">Args</th><th class="px-3 py-2">Where it fires</th></tr>
                                </thead>
                                <tbody class="divide-y divide-gray-100">
                                    <tr><td class="px-3 py-2 font-mono text-blue-600">falcon_before_single_product</td><td class="px-3 py-2"><span class="bg-blue-100 text-blue-700 px-1.5 py-0.5 rounded text-[10px] font-bold">ACTION</span></td><td class="px-3 py-2 font-mono">$post</td><td class="px-3 py-2">Before entire product page</td></tr>
                                    <tr><td class="px-3 py-2 font-mono text-blue-600">falcon_after_single_product</td><td class="px-3 py-2"><span class="bg-blue-100 text-blue-700 px-1.5 py-0.5 rounded text-[10px] font-bold">ACTION</span></td><td class="px-3 py-2 font-mono">$post</td><td class="px-3 py-2">After entire product page</td></tr>
                                    <tr><td class="px-3 py-2 font-mono text-blue-600">falcon_before_product_images</td><td class="px-3 py-2"><span class="bg-blue-100 text-blue-700 px-1.5 py-0.5 rounded text-[10px] font-bold">ACTION</span></td><td class="px-3 py-2 font-mono">$post</td><td class="px-3 py-2">Before image gallery column</td></tr>
                                    <tr><td class="px-3 py-2 font-mono text-blue-600">falcon_after_product_images</td><td class="px-3 py-2"><span class="bg-blue-100 text-blue-700 px-1.5 py-0.5 rounded text-[10px] font-bold">ACTION</span></td><td class="px-3 py-2 font-mono">$post</td><td class="px-3 py-2">After image gallery column</td></tr>
                                    <tr><td class="px-3 py-2 font-mono text-blue-600">falcon_before_product_description</td><td class="px-3 py-2"><span class="bg-blue-100 text-blue-700 px-1.5 py-0.5 rounded text-[10px] font-bold">ACTION</span></td><td class="px-3 py-2 font-mono">$post</td><td class="px-3 py-2">Before description section</td></tr>
                                    <tr><td class="px-3 py-2 font-mono text-blue-600">falcon_after_product_description</td><td class="px-3 py-2"><span class="bg-blue-100 text-blue-700 px-1.5 py-0.5 rounded text-[10px] font-bold">ACTION</span></td><td class="px-3 py-2 font-mono">$post</td><td class="px-3 py-2">After description section</td></tr>
                                    <tr><td class="px-3 py-2 font-mono text-green-600">falcon_product_description_title</td><td class="px-3 py-2"><span class="bg-green-100 text-green-700 px-1.5 py-0.5 rounded text-[10px] font-bold">FILTER</span></td><td class="px-3 py-2 font-mono">$html, $post</td><td class="px-3 py-2">Modify "Description" heading HTML</td></tr>
                                    <tr><td class="px-3 py-2 font-mono text-green-600">falcon_product_description</td><td class="px-3 py-2"><span class="bg-green-100 text-green-700 px-1.5 py-0.5 rounded text-[10px] font-bold">FILTER</span></td><td class="px-3 py-2 font-mono">$html, $post</td><td class="px-3 py-2">Modify full description HTML</td></tr>
                                    <tr class="bg-emerald-50"><td class="px-3 py-2 font-mono text-green-600">falcon_product_fields</td><td class="px-3 py-2"><span class="bg-green-100 text-green-700 px-1.5 py-0.5 rounded text-[10px] font-bold">FILTER</span></td><td class="px-3 py-2 font-mono">$fields, $post</td><td class="px-3 py-2">Add custom form fields to <b>both</b> simple & variable product pages at once (rendered before the ATC button). Use <code>lazy_simple_product_fields</code> / <code>lazy_variable_product_fields</code> for type-specific fields.</td></tr>
                                    <tr class="bg-emerald-50"><td class="px-3 py-2 font-mono text-green-600">lazy_simple_product_fields</td><td class="px-3 py-2"><span class="bg-green-100 text-green-700 px-1.5 py-0.5 rounded text-[10px] font-bold">FILTER</span></td><td class="px-3 py-2 font-mono">$fields, $post</td><td class="px-3 py-2">Simple product only — applied after <code>falcon_product_fields</code></td></tr>
                                    <tr class="bg-emerald-50"><td class="px-3 py-2 font-mono text-green-600">lazy_variable_product_fields</td><td class="px-3 py-2"><span class="bg-green-100 text-green-700 px-1.5 py-0.5 rounded text-[10px] font-bold">FILTER</span></td><td class="px-3 py-2 font-mono">$fields, $post</td><td class="px-3 py-2">Variable product only — applied after <code>falcon_product_fields</code></td></tr>
                                </tbody>
                            </table>
                        </div>
                        <p class="text-xs text-gray-500 mt-3">Render helper: <code class="bg-gray-100 px-1 rounded">falcon_render_product_fields($fields)</code> — called automatically by the theme. Each field is an array with a <code>type</code> key (<code>text</code>, <code>select</code>, <code>textarea</code>, <code>raw</code>…). The <code>raw</code> type lets you output any HTML via <code>ob_start()</code>.</p>
                    </div>

                    {{-- Simple Product Hooks --}}
                    <div class="bg-white border border-gray-200 rounded-xl p-6 shadow-sm mb-8">
                        <h3 class="text-lg font-bold text-blue-600 mb-1">Simple Product Hooks <span class="text-sm font-normal text-gray-500">(prefix: <code>lazy_simple_</code>)</span></h3>
                        <p class="text-sm text-gray-600 mt-1 mb-4">These fire only on simple product pages.</p>
                        <div class="overflow-x-auto">
                            <table class="w-full text-xs text-left border border-gray-100 rounded-lg overflow-hidden">
                                <thead class="bg-gray-50 text-gray-500 uppercase">
                                    <tr><th class="px-3 py-2">Hook Tag</th><th class="px-3 py-2 w-16">Type</th><th class="px-3 py-2">Args</th><th class="px-3 py-2">Position</th></tr>
                                </thead>
                                <tbody class="divide-y divide-gray-100">
                                    <tr><td class="px-3 py-2 font-mono text-blue-600">lazy_simple_before_product_title</td><td class="px-3 py-2"><span class="bg-blue-100 text-blue-700 px-1.5 py-0.5 rounded text-[10px] font-bold">ACTION</span></td><td class="px-3 py-2 font-mono">$post</td><td class="px-3 py-2">Before &lt;h1&gt; title</td></tr>
                                    <tr><td class="px-3 py-2 font-mono text-green-600">lazy_simple_product_title</td><td class="px-3 py-2"><span class="bg-green-100 text-green-700 px-1.5 py-0.5 rounded text-[10px] font-bold">FILTER</span></td><td class="px-3 py-2 font-mono">$html, $post</td><td class="px-3 py-2">Modify title HTML</td></tr>
                                    <tr><td class="px-3 py-2 font-mono text-blue-600">lazy_simple_after_product_title</td><td class="px-3 py-2"><span class="bg-blue-100 text-blue-700 px-1.5 py-0.5 rounded text-[10px] font-bold">ACTION</span></td><td class="px-3 py-2 font-mono">$post</td><td class="px-3 py-2">After title</td></tr>
                                    <tr><td class="px-3 py-2 font-mono text-blue-600">lazy_simple_before_product_price</td><td class="px-3 py-2"><span class="bg-blue-100 text-blue-700 px-1.5 py-0.5 rounded text-[10px] font-bold">ACTION</span></td><td class="px-3 py-2 font-mono">$post</td><td class="px-3 py-2">Before price block</td></tr>
                                    <tr><td class="px-3 py-2 font-mono text-green-600">lazy_simple_product_price</td><td class="px-3 py-2"><span class="bg-green-100 text-green-700 px-1.5 py-0.5 rounded text-[10px] font-bold">FILTER</span></td><td class="px-3 py-2 font-mono">$html, $post</td><td class="px-3 py-2">Modify price HTML</td></tr>
                                    <tr><td class="px-3 py-2 font-mono text-blue-600">lazy_simple_after_product_price</td><td class="px-3 py-2"><span class="bg-blue-100 text-blue-700 px-1.5 py-0.5 rounded text-[10px] font-bold">ACTION</span></td><td class="px-3 py-2 font-mono">$post</td><td class="px-3 py-2">After price block</td></tr>
                                    <tr><td class="px-3 py-2 font-mono text-blue-600">lazy_simple_before_short_description</td><td class="px-3 py-2"><span class="bg-blue-100 text-blue-700 px-1.5 py-0.5 rounded text-[10px] font-bold">ACTION</span></td><td class="px-3 py-2 font-mono">$post</td><td class="px-3 py-2">Before short description</td></tr>
                                    <tr><td class="px-3 py-2 font-mono text-green-600">lazy_simple_short_description</td><td class="px-3 py-2"><span class="bg-green-100 text-green-700 px-1.5 py-0.5 rounded text-[10px] font-bold">FILTER</span></td><td class="px-3 py-2 font-mono">$html, $post</td><td class="px-3 py-2">Modify short description HTML</td></tr>
                                    <tr><td class="px-3 py-2 font-mono text-blue-600">lazy_simple_after_short_description</td><td class="px-3 py-2"><span class="bg-blue-100 text-blue-700 px-1.5 py-0.5 rounded text-[10px] font-bold">ACTION</span></td><td class="px-3 py-2 font-mono">$post</td><td class="px-3 py-2">After short description</td></tr>
                                    <tr><td class="px-3 py-2 font-mono text-blue-600">lazy_simple_before_add_to_cart_form</td><td class="px-3 py-2"><span class="bg-blue-100 text-blue-700 px-1.5 py-0.5 rounded text-[10px] font-bold">ACTION</span></td><td class="px-3 py-2 font-mono">$post</td><td class="px-3 py-2">Before add-to-cart form</td></tr>
                                    <tr><td class="px-3 py-2 font-mono text-blue-600">lazy_simple_add_to_cart_form_top</td><td class="px-3 py-2"><span class="bg-blue-100 text-blue-700 px-1.5 py-0.5 rounded text-[10px] font-bold">ACTION</span></td><td class="px-3 py-2 font-mono">$post</td><td class="px-3 py-2">Inside form — top (custom fields)</td></tr>
                                    <tr><td class="px-3 py-2 font-mono text-blue-600">lazy_simple_add_to_cart_form_bottom</td><td class="px-3 py-2"><span class="bg-blue-100 text-blue-700 px-1.5 py-0.5 rounded text-[10px] font-bold">ACTION</span></td><td class="px-3 py-2 font-mono">$post</td><td class="px-3 py-2">Inside form — bottom</td></tr>
                                    <tr><td class="px-3 py-2 font-mono text-blue-600">lazy_simple_before_add_to_cart_button</td><td class="px-3 py-2"><span class="bg-blue-100 text-blue-700 px-1.5 py-0.5 rounded text-[10px] font-bold">ACTION</span></td><td class="px-3 py-2 font-mono">$post</td><td class="px-3 py-2">Before submit button</td></tr>
                                    <tr><td class="px-3 py-2 font-mono text-green-600">lazy_simple_add_to_cart_button</td><td class="px-3 py-2"><span class="bg-green-100 text-green-700 px-1.5 py-0.5 rounded text-[10px] font-bold">FILTER</span></td><td class="px-3 py-2 font-mono">$html, $post</td><td class="px-3 py-2">Modify button HTML</td></tr>
                                    <tr><td class="px-3 py-2 font-mono text-blue-600">lazy_simple_after_add_to_cart_button</td><td class="px-3 py-2"><span class="bg-blue-100 text-blue-700 px-1.5 py-0.5 rounded text-[10px] font-bold">ACTION</span></td><td class="px-3 py-2 font-mono">$post</td><td class="px-3 py-2">After submit button</td></tr>
                                    <tr><td class="px-3 py-2 font-mono text-blue-600">lazy_simple_after_add_to_cart_form</td><td class="px-3 py-2"><span class="bg-blue-100 text-blue-700 px-1.5 py-0.5 rounded text-[10px] font-bold">ACTION</span></td><td class="px-3 py-2 font-mono">$post</td><td class="px-3 py-2">After add-to-cart form</td></tr>
                                    <tr><td class="px-3 py-2 font-mono text-blue-600">falcon_simple_out_of_stock_button</td><td class="px-3 py-2"><span class="bg-blue-100 text-blue-700 px-1.5 py-0.5 rounded text-[10px] font-bold">ACTION</span></td><td class="px-3 py-2 font-mono">$post</td><td class="px-3 py-2">Replaces default out-of-stock button</td></tr>
                                    <tr><td class="px-3 py-2 font-mono text-blue-600">lazy_simple_before_product_meta</td><td class="px-3 py-2"><span class="bg-blue-100 text-blue-700 px-1.5 py-0.5 rounded text-[10px] font-bold">ACTION</span></td><td class="px-3 py-2 font-mono">$post</td><td class="px-3 py-2">Before SKU/category meta</td></tr>
                                    <tr><td class="px-3 py-2 font-mono text-blue-600">lazy_simple_product_meta_fields</td><td class="px-3 py-2"><span class="bg-blue-100 text-blue-700 px-1.5 py-0.5 rounded text-[10px] font-bold">ACTION</span></td><td class="px-3 py-2 font-mono">$post</td><td class="px-3 py-2">Inside meta — add extra rows</td></tr>
                                    <tr><td class="px-3 py-2 font-mono text-blue-600">lazy_simple_after_product_meta</td><td class="px-3 py-2"><span class="bg-blue-100 text-blue-700 px-1.5 py-0.5 rounded text-[10px] font-bold">ACTION</span></td><td class="px-3 py-2 font-mono">$post</td><td class="px-3 py-2">After SKU/category meta</td></tr>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    {{-- Variable Product Hooks --}}
                    <div class="bg-white border border-gray-200 rounded-xl p-6 shadow-sm mb-8">
                        <h3 class="text-lg font-bold text-blue-600 mb-1">Variable Product Hooks <span class="text-sm font-normal text-gray-500">(prefix: <code>lazy_variable_</code>)</span></h3>
                        <p class="text-sm text-gray-600 mt-1 mb-4">These fire only on variable product pages. All simple product hooks exist here with the <code>lazy_variable_</code> prefix plus these extras:</p>
                        <div class="overflow-x-auto">
                            <table class="w-full text-xs text-left border border-gray-100 rounded-lg overflow-hidden">
                                <thead class="bg-gray-50 text-gray-500 uppercase">
                                    <tr><th class="px-3 py-2">Hook Tag</th><th class="px-3 py-2 w-16">Type</th><th class="px-3 py-2">Args</th><th class="px-3 py-2">Position</th></tr>
                                </thead>
                                <tbody class="divide-y divide-gray-100">
                                    <tr><td class="px-3 py-2 font-mono text-blue-600">lazy_variable_before_single_product</td><td class="px-3 py-2"><span class="bg-blue-100 text-blue-700 px-1.5 py-0.5 rounded text-[10px] font-bold">ACTION</span></td><td class="px-3 py-2 font-mono">$post</td><td class="px-3 py-2">Before variable product wrapper</td></tr>
                                    <tr><td class="px-3 py-2 font-mono text-blue-600">lazy_variable_after_single_product</td><td class="px-3 py-2"><span class="bg-blue-100 text-blue-700 px-1.5 py-0.5 rounded text-[10px] font-bold">ACTION</span></td><td class="px-3 py-2 font-mono">$post</td><td class="px-3 py-2">After variable product wrapper</td></tr>
                                    <tr><td class="px-3 py-2 font-mono text-blue-600">lazy_variable_before_product_title</td><td class="px-3 py-2"><span class="bg-blue-100 text-blue-700 px-1.5 py-0.5 rounded text-[10px] font-bold">ACTION</span></td><td class="px-3 py-2 font-mono">$post</td><td class="px-3 py-2">Before title</td></tr>
                                    <tr><td class="px-3 py-2 font-mono text-green-600">lazy_variable_product_title</td><td class="px-3 py-2"><span class="bg-green-100 text-green-700 px-1.5 py-0.5 rounded text-[10px] font-bold">FILTER</span></td><td class="px-3 py-2 font-mono">$html, $post</td><td class="px-3 py-2">Modify title HTML</td></tr>
                                    <tr><td class="px-3 py-2 font-mono text-blue-600">lazy_variable_before_add_to_cart_form</td><td class="px-3 py-2"><span class="bg-blue-100 text-blue-700 px-1.5 py-0.5 rounded text-[10px] font-bold">ACTION</span></td><td class="px-3 py-2 font-mono">$post</td><td class="px-3 py-2">Before variation selector + form</td></tr>
                                    <tr><td class="px-3 py-2 font-mono text-blue-600">lazy_variable_add_to_cart_form_top</td><td class="px-3 py-2"><span class="bg-blue-100 text-blue-700 px-1.5 py-0.5 rounded text-[10px] font-bold">ACTION</span></td><td class="px-3 py-2 font-mono">$post</td><td class="px-3 py-2">Inside form — top (custom fields)</td></tr>
                                    <tr><td class="px-3 py-2 font-mono text-blue-600">lazy_variable_add_to_cart_form_bottom</td><td class="px-3 py-2"><span class="bg-blue-100 text-blue-700 px-1.5 py-0.5 rounded text-[10px] font-bold">ACTION</span></td><td class="px-3 py-2 font-mono">$post</td><td class="px-3 py-2">Inside form — bottom</td></tr>
                                    <tr><td class="px-3 py-2 font-mono text-green-600">lazy_variable_add_to_cart_button</td><td class="px-3 py-2"><span class="bg-green-100 text-green-700 px-1.5 py-0.5 rounded text-[10px] font-bold">FILTER</span></td><td class="px-3 py-2 font-mono">$html, $post</td><td class="px-3 py-2">Modify button HTML</td></tr>
                                    <tr><td class="px-3 py-2 font-mono text-blue-600">lazy_variable_product_meta_fields</td><td class="px-3 py-2"><span class="bg-blue-100 text-blue-700 px-1.5 py-0.5 rounded text-[10px] font-bold">ACTION</span></td><td class="px-3 py-2 font-mono">$post</td><td class="px-3 py-2">Inside meta — add extra rows</td></tr>
                                    <tr><td class="px-3 py-2 font-mono text-green-600">lazy_variable_product_description</td><td class="px-3 py-2"><span class="bg-green-100 text-green-700 px-1.5 py-0.5 rounded text-[10px] font-bold">FILTER</span></td><td class="px-3 py-2 font-mono">$html, $post</td><td class="px-3 py-2">Modify full description HTML</td></tr>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    {{-- Cart & Admin Hooks --}}
                    <div class="bg-white border border-gray-200 rounded-xl p-6 shadow-sm mb-8">
                        <h3 class="text-lg font-bold text-blue-600 mb-4">Cart & Checkout Hooks</h3>
                        <div class="overflow-x-auto">
                            <table class="w-full text-xs text-left border border-gray-100 rounded-lg overflow-hidden">
                                <thead class="bg-gray-50 text-gray-500 uppercase">
                                    <tr><th class="px-3 py-2">Hook Tag</th><th class="px-3 py-2 w-16">Type</th><th class="px-3 py-2">Args</th><th class="px-3 py-2">Description</th></tr>
                                </thead>
                                <tbody class="divide-y divide-gray-100">
                                    <tr><td class="px-3 py-2 font-mono text-green-600">falcon_cart_item_custom_fields</td><td class="px-3 py-2"><span class="bg-green-100 text-green-700 px-1.5 py-0.5 rounded text-[10px] font-bold">FILTER</span></td><td class="px-3 py-2 font-mono">$fields, $product, $variation</td><td class="px-3 py-2">Validate / modify custom fields array before it is stored in the cart session</td></tr>
                                    <tr class="bg-emerald-50"><td class="px-3 py-2 font-mono text-green-600">falcon_cart_item_data</td><td class="px-3 py-2"><span class="bg-green-100 text-green-700 px-1.5 py-0.5 rounded text-[10px] font-bold">FILTER</span></td><td class="px-3 py-2 font-mono">$item, $product, $variation</td><td class="px-3 py-2">Modify the <b>full cart item array</b> just before it is written to the session. Use this to bake price add-ons into <code>$item['price']</code> / <code>$item['sale_price']</code> so totals, tax, coupons, and invoices stay correct automatically.</td></tr>
                                    <tr><td class="px-3 py-2 font-mono text-green-600">lazy_order_item_meta</td><td class="px-3 py-2"><span class="bg-green-100 text-green-700 px-1.5 py-0.5 rounded text-[10px] font-bold">FILTER</span></td><td class="px-3 py-2 font-mono">$meta, $item, $order</td><td class="px-3 py-2">Modify order item meta before it is saved to the database</td></tr>
                                    <tr><td class="px-3 py-2 font-mono text-blue-600">lazy_before_place_order</td><td class="px-3 py-2"><span class="bg-blue-100 text-blue-700 px-1.5 py-0.5 rounded text-[10px] font-bold">ACTION</span></td><td class="px-3 py-2 font-mono">$order, $cart, $request</td><td class="px-3 py-2">After the order row is created, before order items are saved</td></tr>
                                    <tr class="bg-emerald-50"><td class="px-3 py-2 font-mono text-green-600">falcon_custom_field_labels</td><td class="px-3 py-2"><span class="bg-green-100 text-green-700 px-1.5 py-0.5 rounded text-[10px] font-bold">FILTER</span></td><td class="px-3 py-2 font-mono">$labels, $context</td><td class="px-3 py-2">Provide human-readable labels for custom item fields (<code>$item-&gt;meta['custom_fields']</code>). The <code>$labels</code> array maps field key → label. Used automatically by <code>falcon_render_item_custom_fields()</code> across mini-cart, cart, checkout, confirmation, and invoice.</td></tr>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <div class="bg-white border border-gray-200 rounded-xl p-6 shadow-sm mb-8">
                        <h3 class="text-lg font-bold text-blue-600 mb-4">Admin Product Hooks</h3>
                        <div class="overflow-x-auto">
                            <table class="w-full text-xs text-left border border-gray-100 rounded-lg overflow-hidden">
                                <thead class="bg-gray-50 text-gray-500 uppercase">
                                    <tr><th class="px-3 py-2">Hook Tag</th><th class="px-3 py-2 w-16">Type</th><th class="px-3 py-2">Args</th><th class="px-3 py-2">Description</th></tr>
                                </thead>
                                <tbody class="divide-y divide-gray-100">
                                    <tr><td class="px-3 py-2 font-mono text-green-600">lazy_admin_before_save_product</td><td class="px-3 py-2"><span class="bg-green-100 text-green-700 px-1.5 py-0.5 rounded text-[10px] font-bold">FILTER</span></td><td class="px-3 py-2 font-mono">$data, $post|null, $request</td><td class="px-3 py-2">Modify product data before insert/update</td></tr>
                                    <tr><td class="px-3 py-2 font-mono text-blue-600">lazy_admin_after_save_product</td><td class="px-3 py-2"><span class="bg-blue-100 text-blue-700 px-1.5 py-0.5 rounded text-[10px] font-bold">ACTION</span></td><td class="px-3 py-2 font-mono">$post, $shopData, $request, $action</td><td class="px-3 py-2">After save — <code>$action</code> is <code>'create'</code> or <code>'update'</code></td></tr>
                                    <tr><td class="px-3 py-2 font-mono text-blue-600">lazy_admin_before_delete_product</td><td class="px-3 py-2"><span class="bg-blue-100 text-blue-700 px-1.5 py-0.5 rounded text-[10px] font-bold">ACTION</span></td><td class="px-3 py-2 font-mono">$post</td><td class="px-3 py-2">Before product moved to trash</td></tr>
                                    <tr><td class="px-3 py-2 font-mono text-blue-600">lazy_admin_after_delete_product</td><td class="px-3 py-2"><span class="bg-blue-100 text-blue-700 px-1.5 py-0.5 rounded text-[10px] font-bold">ACTION</span></td><td class="px-3 py-2 font-mono">$postId, $title</td><td class="px-3 py-2">After product trashed</td></tr>
                                    <tr class="bg-emerald-50"><td class="px-3 py-2 font-mono text-blue-600">lazy_admin_order_item_meta</td><td class="px-3 py-2"><span class="bg-blue-100 text-blue-700 px-1.5 py-0.5 rounded text-[10px] font-bold">ACTION</span></td><td class="px-3 py-2 font-mono">$item</td><td class="px-3 py-2">Fires under each order item row in the admin order detail page. Echo HTML to display custom field labels/values (e.g. engraving, protective case). The <code>$item</code> object exposes <code>$item-&gt;meta['custom_fields']</code>.</td></tr>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    {{-- Custom Fields Example --}}
                    <div class="bg-white border border-gray-200 rounded-xl p-6 shadow-sm mb-8">
                        <h3 class="text-lg font-bold text-blue-600 mb-3">Custom Fields — Add to Cart → Order</h3>
                        <p class="text-sm text-gray-600 mb-4">Add custom input fields to the add-to-cart form. Field names must be prefixed <code>lazy_custom_</code>. They are automatically stored in the cart session and persisted to <code>shop_order_items.meta</code> when the order is placed.</p>

                        <p class="text-xs font-bold text-gray-500 uppercase mb-2">Method A — Array fields via <code>falcon_product_fields</code> (recommended, renders on both product types)</p>
                        <div class="bg-gray-900 rounded-lg p-4 text-gray-300 font-mono text-xs overflow-x-auto mb-4">
                            <pre><code>@verbatim
// Works on BOTH simple and variable products
add_falcon_filter('falcon_product_fields', function ($fields) {
    $fields[] = [
        'type'        => 'text',
        'name'        => 'lazy_custom_engraving',
        'label'       => 'Engraving Text',
        'placeholder' => 'Enter text to engrave...',
        'required'    => false,
    ];
    return $fields;
});
@endverbatim</code></pre>
                        </div>

                        <p class="text-xs font-bold text-gray-500 uppercase mb-2">Method B — Raw HTML via action hooks (type-specific)</p>
                        <div class="bg-gray-900 rounded-lg p-4 text-gray-300 font-mono text-xs overflow-x-auto mb-4">
                            <pre><code>@verbatim
// Simple product only
add_falcon_action('lazy_simple_add_to_cart_form_top', function ($post) {
    echo '<div class="mb-4">';
    echo '  <label class="block text-sm font-medium text-gray-700 mb-1">Gift Message</label>';
    echo '  <textarea name="lazy_custom_gift_message" rows="2"';
    echo '    class="w-full border border-gray-300 rounded px-3 py-2 text-sm"';
    echo '    placeholder="Add a personal message..."></textarea>';
    echo '</div>';
});
@endverbatim</code></pre>
                        </div>

                        <div class="bg-blue-50 border-l-4 border-blue-400 p-3 text-xs text-blue-700">
                            Access in orders: <code>$item-&gt;meta['custom_fields']['engraving']</code> (the <code>lazy_custom_</code> prefix is stripped automatically).
                            Display across pages: register a label via <code>falcon_custom_field_labels</code> and the CMS renders it everywhere automatically (mini-cart, cart, checkout summary, confirmation, invoice).
                        </div>
                    </div>

                    {{-- Quick examples --}}
                    <div class="bg-white border border-gray-200 rounded-xl p-6 shadow-sm">
                        <h3 class="text-lg font-bold text-blue-600 mb-4">Quick Examples</h3>
                        <div class="space-y-6">
                            <div>
                                <p class="text-xs font-bold text-gray-500 uppercase mb-2">Add a badge after the title</p>
                                <div class="bg-gray-900 rounded-lg p-4 text-gray-300 font-mono text-xs overflow-x-auto">
                                    <pre><code>@verbatim
add_falcon_action('lazy_simple_after_product_title', function ($post) {
    if ($post->sku === 'FEATURED-001') {
        echo '<span class="inline-block bg-yellow-100 text-yellow-800 text-xs font-bold px-2 py-0.5 rounded mb-3">Staff Pick</span>';
    }
});
@endverbatim</code></pre>
                                </div>
                            </div>
                            <div>
                                <p class="text-xs font-bold text-gray-500 uppercase mb-2">Show savings below the price</p>
                                <div class="bg-gray-900 rounded-lg p-4 text-gray-300 font-mono text-xs overflow-x-auto">
                                    <pre><code>@verbatim
add_falcon_filter('lazy_simple_product_price', function ($html, $post) {
    if ($post->sale_price) {
        $savings = $post->price - $post->sale_price;
        $html .= '<p class="text-sm text-green-600 mt-1">You save ' . falcon_price_format($savings) . '</p>';
    }
    return $html;
});
@endverbatim</code></pre>
                                </div>
                            </div>
                            <div>
                                <p class="text-xs font-bold text-gray-500 uppercase mb-2">Remove the description section entirely</p>
                                <div class="bg-gray-900 rounded-lg p-4 text-gray-300 font-mono text-xs overflow-x-auto">
                                    <pre><code>@verbatim
add_falcon_filter('falcon_product_description', function ($html, $post) {
    return ''; // return empty string to suppress
});
@endverbatim</code></pre>
                                </div>
                            </div>
                            <div>
                                <p class="text-xs font-bold text-gray-500 uppercase mb-2">Low stock alert on product save</p>
                                <div class="bg-gray-900 rounded-lg p-4 text-gray-300 font-mono text-xs overflow-x-auto">
                                    <pre><code>@verbatim
add_falcon_action('lazy_admin_after_save_product', function ($post, $shopData, $request, $action) {
    if ($shopData && $shopData->manage_stock && $shopData->stock_quantity <= 5) {
        \Log::warning("Low stock: {$post->title} has {$shopData->stock_quantity} units.");
    }
});
@endverbatim</code></pre>
                                </div>
                            </div>
                        </div>
                    </div>
                </section>

                {{-- Section: Checkout Form Hooks --}}
                <section id="checkout-form-hooks">
                    <h2 class="text-2xl font-bold text-gray-900 mb-2">Checkout Form Hooks</h2>
                    <p class="text-gray-700 mb-6">The checkout billing and shipping forms are fully hookable. You can add, remove, or reorder fields via <code>functions.php</code> — required validation, storage, and display across all order pages happen automatically.</p>

                    {{-- How it works --}}
                    <div class="bg-blue-50 border border-blue-100 rounded-xl p-6 mb-8">
                        <h3 class="text-lg font-bold text-blue-700 mb-3">How custom checkout fields flow through the system</h3>
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-4 text-xs text-blue-800">
                            <div class="bg-white/60 rounded-lg p-3 border border-blue-100">
                                <div class="font-bold mb-1">1. Define</div>
                                Push a field array into <code>falcon_billing_fields</code> or <code>lazy_shipping_fields</code>.
                            </div>
                            <div class="bg-white/60 rounded-lg p-3 border border-blue-100">
                                <div class="font-bold mb-1">2. Automatic</div>
                                CMS renders it on checkout, validates if <code>required</code>, saves to <code>$order->meta['checkout_fields']</code>.
                            </div>
                            <div class="bg-white/60 rounded-lg p-3 border border-blue-100">
                                <div class="font-bold mb-1">3. Shown everywhere</div>
                                Order confirmation page · Admin order detail "Additional Info" · Invoice print — all automatic.
                            </div>
                        </div>
                        <p class="text-xs text-blue-700 mt-3"><b>Standard fields</b> (first name, last name, address, phone, email…) map to dedicated order columns. <b>Custom fields</b> — any name not in that list — are stored in <code>$order->meta['checkout_fields']</code>.</p>
                    </div>

                    {{-- Hooks table --}}
                    <div class="bg-white border border-gray-200 rounded-xl p-6 shadow-sm mb-8">
                        <h3 class="text-lg font-bold text-blue-600 mb-4">Available Hooks</h3>
                        <div class="overflow-x-auto">
                            <table class="w-full text-xs text-left border border-gray-100 rounded-lg overflow-hidden">
                                <thead class="bg-gray-50 text-gray-500 uppercase">
                                    <tr><th class="px-3 py-2">Hook Tag</th><th class="px-3 py-2 w-16">Type</th><th class="px-3 py-2">Args</th><th class="px-3 py-2">Description</th></tr>
                                </thead>
                                <tbody class="divide-y divide-gray-100">
                                    <tr><td class="px-3 py-2 font-mono text-green-600">falcon_billing_fields</td><td class="px-3 py-2"><span class="bg-green-100 text-green-700 px-1.5 py-0.5 rounded text-[10px] font-bold">FILTER</span></td><td class="px-3 py-2 font-mono">$fields</td><td class="px-3 py-2">Add / remove / reorder billing form fields. Return the modified array.</td></tr>
                                    <tr><td class="px-3 py-2 font-mono text-green-600">lazy_shipping_fields</td><td class="px-3 py-2"><span class="bg-green-100 text-green-700 px-1.5 py-0.5 rounded text-[10px] font-bold">FILTER</span></td><td class="px-3 py-2 font-mono">$fields</td><td class="px-3 py-2">Same as above for the shipping form.</td></tr>
                                    <tr><td class="px-3 py-2 font-mono text-green-600">lazy_checkout_custom_fields</td><td class="px-3 py-2"><span class="bg-green-100 text-green-700 px-1.5 py-0.5 rounded text-[10px] font-bold">FILTER</span></td><td class="px-3 py-2 font-mono">$fields, $request</td><td class="px-3 py-2">Final filter on the collected custom field values before they are saved to <code>$order->meta</code>. Useful for transforming or removing values.</td></tr>
                                    <tr><td class="px-3 py-2 font-mono text-green-600">falcon_checkout_field_labels</td><td class="px-3 py-2"><span class="bg-green-100 text-green-700 px-1.5 py-0.5 rounded text-[10px] font-bold">FILTER</span></td><td class="px-3 py-2 font-mono">$labels</td><td class="px-3 py-2">Map custom field key → human-readable label. Used on order confirmation, admin detail, and invoice. Returns an array e.g. <code>['billing_company' => 'Company']</code>.</td></tr>
                                    <tr><td class="px-3 py-2 font-mono text-blue-600">lazy_before_billing_fields</td><td class="px-3 py-2"><span class="bg-blue-100 text-blue-700 px-1.5 py-0.5 rounded text-[10px] font-bold">ACTION</span></td><td class="px-3 py-2 font-mono">—</td><td class="px-3 py-2">Before the billing fields grid renders</td></tr>
                                    <tr><td class="px-3 py-2 font-mono text-blue-600">lazy_after_billing_fields</td><td class="px-3 py-2"><span class="bg-blue-100 text-blue-700 px-1.5 py-0.5 rounded text-[10px] font-bold">ACTION</span></td><td class="px-3 py-2 font-mono">—</td><td class="px-3 py-2">After the billing fields grid renders</td></tr>
                                    <tr><td class="px-3 py-2 font-mono text-blue-600">lazy_before_shipping_fields</td><td class="px-3 py-2"><span class="bg-blue-100 text-blue-700 px-1.5 py-0.5 rounded text-[10px] font-bold">ACTION</span></td><td class="px-3 py-2 font-mono">—</td><td class="px-3 py-2">Before the shipping fields grid renders</td></tr>
                                    <tr><td class="px-3 py-2 font-mono text-blue-600">lazy_after_shipping_fields</td><td class="px-3 py-2"><span class="bg-blue-100 text-blue-700 px-1.5 py-0.5 rounded text-[10px] font-bold">ACTION</span></td><td class="px-3 py-2 font-mono">—</td><td class="px-3 py-2">After the shipping fields grid renders</td></tr>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    {{-- Field definition keys --}}
                    <div class="bg-white border border-gray-200 rounded-xl p-6 shadow-sm mb-8">
                        <h3 class="text-lg font-bold text-blue-600 mb-4">Field Definition Keys</h3>
                        <div class="overflow-x-auto">
                            <table class="w-full text-xs text-left border border-gray-100 rounded-lg overflow-hidden">
                                <thead class="bg-gray-50 text-gray-500 uppercase">
                                    <tr><th class="px-3 py-2">Key</th><th class="px-3 py-2">Required</th><th class="px-3 py-2">Description</th></tr>
                                </thead>
                                <tbody class="divide-y divide-gray-100">
                                    <tr><td class="px-3 py-2 font-mono text-blue-600">name</td><td class="px-3 py-2 font-bold text-red-500">Yes</td><td class="px-3 py-2">HTML input name. Standard names (e.g. <code>billing_first_name</code>) map to dedicated order columns. Any other name is treated as a custom field and saved to <code>$order->meta</code>.</td></tr>
                                    <tr><td class="px-3 py-2 font-mono text-blue-600">type</td><td class="px-3 py-2">—</td><td class="px-3 py-2"><code>text</code> · <code>email</code> · <code>tel</code> · <code>select</code> · <code>country</code> · <code>textarea</code> · <code>checkbox</code> · <code>hidden</code>. Defaults to <code>text</code>.</td></tr>
                                    <tr><td class="px-3 py-2 font-mono text-blue-600">label</td><td class="px-3 py-2">—</td><td class="px-3 py-2">Visible label above the input. <code>null</code> = no label.</td></tr>
                                    <tr><td class="px-3 py-2 font-mono text-blue-600">required</td><td class="px-3 py-2">—</td><td class="px-3 py-2"><code>true</code> adds HTML required attribute AND server-side Laravel validation automatically.</td></tr>
                                    <tr><td class="px-3 py-2 font-mono text-blue-600">rules</td><td class="px-3 py-2">—</td><td class="px-3 py-2">Override the default validation rule string (e.g. <code>'required|email|max:255'</code>). Only used when <code>required</code> is <code>true</code>.</td></tr>
                                    <tr><td class="px-3 py-2 font-mono text-blue-600">width</td><td class="px-3 py-2">—</td><td class="px-3 py-2"><code>'half'</code> = one column (default for most fields) · <code>'full'</code> = both columns (<code>md:col-span-2</code>).</td></tr>
                                    <tr><td class="px-3 py-2 font-mono text-blue-600">priority</td><td class="px-3 py-2">—</td><td class="px-3 py-2">Sort order — lower appears higher. Built-in billing fields use 10–100. Insert custom fields between them by choosing a value in that range.</td></tr>
                                    <tr><td class="px-3 py-2 font-mono text-blue-600">options</td><td class="px-3 py-2">—</td><td class="px-3 py-2">For <code>type=select</code>: associative array of <code>value => label</code> pairs.</td></tr>
                                    <tr><td class="px-3 py-2 font-mono text-blue-600">placeholder</td><td class="px-3 py-2">—</td><td class="px-3 py-2">Input placeholder text.</td></tr>
                                    <tr><td class="px-3 py-2 font-mono text-blue-600">default</td><td class="px-3 py-2">—</td><td class="px-3 py-2">Pre-filled value (e.g. from the logged-in user).</td></tr>
                                    <tr><td class="px-3 py-2 font-mono text-blue-600">class</td><td class="px-3 py-2">—</td><td class="px-3 py-2">Extra CSS classes on the input element.</td></tr>
                                    <tr><td class="px-3 py-2 font-mono text-blue-600">rows</td><td class="px-3 py-2">—</td><td class="px-3 py-2">Number of rows for <code>type=textarea</code>.</td></tr>
                                </tbody>
                            </table>
                        </div>

                        <h4 class="text-sm font-bold text-gray-700 mt-5 mb-1">Default billing field priorities</h4>
                        <div class="bg-gray-100 rounded-lg p-3 font-mono text-xs text-gray-700 leading-relaxed">
                            10 First name &nbsp;&nbsp;&nbsp; 20 Last name &nbsp;&nbsp;&nbsp; 30 Country &nbsp;&nbsp;&nbsp; 40 Street address<br>
                            50 Address line 2 &nbsp; 60 City &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp; 70 State &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp; 80 ZIP Code<br>
                            90 Phone &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp; 100 Email
                        </div>
                        <p class="text-xs text-gray-500 mt-2">Insert a custom field at priority 25 to place it between Last name and Country.</p>
                    </div>

                    {{-- Helper functions --}}
                    <div class="bg-white border border-gray-200 rounded-xl p-6 shadow-sm mb-8">
                        <h3 class="text-lg font-bold text-blue-600 mb-4">Helper Functions</h3>
                        <div class="overflow-x-auto">
                            <table class="w-full text-xs text-left border border-gray-100 rounded-lg overflow-hidden">
                                <thead class="bg-gray-50 text-gray-500 uppercase">
                                    <tr><th class="px-3 py-2">Function</th><th class="px-3 py-2">Description</th></tr>
                                </thead>
                                <tbody class="divide-y divide-gray-100">
                                    <tr><td class="px-3 py-2 font-mono text-blue-600">falcon_get_checkout_fields(string $section)</td><td class="px-3 py-2">Returns the merged + sorted field array for <code>'billing'</code> or <code>'shipping'</code> — the single source of truth used both for rendering the form and for server-side validation/collection.</td></tr>
                                    <tr><td class="px-3 py-2 font-mono text-blue-600">falcon_render_checkout_fields(array $fields)</td><td class="px-3 py-2">Wraps all fields in a responsive 2-column grid and calls <code>falcon_render_checkout_field</code> for each.</td></tr>
                                    <tr><td class="px-3 py-2 font-mono text-blue-600">falcon_render_checkout_field(array $field)</td><td class="px-3 py-2">Renders one field — handles all supported types automatically.</td></tr>
                                    <tr><td class="px-3 py-2 font-mono text-blue-600">falcon_standard_checkout_field_names()</td><td class="px-3 py-2">Returns the list of standard field names that map to dedicated order columns. Any other name is a "custom" field.</td></tr>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    {{-- Example --}}
                    <div class="bg-white border border-gray-200 rounded-xl p-6 shadow-sm mb-8">
                        <h3 class="text-lg font-bold text-blue-600 mb-3">Complete Example — Add "Company Name" field</h3>
                        <div class="bg-gray-900 rounded-lg p-4 text-gray-300 font-mono text-xs overflow-x-auto">
                            <pre><code>@verbatim
// In functions.php

// 1. Add the field (priority 25 = between Last name and Country)
add_falcon_filter('falcon_billing_fields', function ($fields) {
    $fields[] = [
        'name'        => 'billing_company',
        'type'        => 'text',
        'label'       => 'Company Name',
        'required'    => false,
        'width'       => 'full',
        'priority'    => 25,
        'placeholder' => 'Optional',
    ];
    return $fields;
});

// 2. Human-readable label for order pages
add_falcon_filter('falcon_checkout_field_labels', function ($labels) {
    $labels['billing_company'] = 'Company';
    return $labels;
});

// Done!
// The CMS will:
// → Render the field on checkout between Last name and Country
// → Validate it (not required here, but it would be if required => true)
// → Save the value to $order->meta['checkout_fields']['billing_company']
// → Show "Company: Acme Ltd" on order confirmation, admin detail, and invoice
@endverbatim</code></pre>
                        </div>
                    </div>

                    {{-- Select example --}}
                    <div class="bg-white border border-gray-200 rounded-xl p-6 shadow-sm mb-8">
                        <h3 class="text-lg font-bold text-blue-600 mb-3">Example — Required select field</h3>
                        <div class="bg-gray-900 rounded-lg p-4 text-gray-300 font-mono text-xs overflow-x-auto">
                            <pre><code>@verbatim
// Add a required "Delivery Preference" select after Address line 2 (priority 55)
add_falcon_filter('falcon_billing_fields', function ($fields) {
    $fields[] = [
        'name'     => 'delivery_preference',
        'type'     => 'select',
        'label'    => 'Delivery Preference',
        'required' => true,
        'width'    => 'full',
        'priority' => 55,
        'options'  => [
            ''          => 'Select…',
            'leave'     => 'Leave at door',
            'reception' => 'Hand to reception',
            'neighbour' => 'Leave with neighbour',
        ],
    ];
    return $fields;
});

add_falcon_filter('falcon_checkout_field_labels', function ($labels) {
    $labels['delivery_preference'] = 'Delivery Preference';
    return $labels;
});
@endverbatim</code></pre>
                        </div>
                    </div>

                    {{-- Removing fields --}}
                    <div class="bg-white border border-gray-200 rounded-xl p-6 shadow-sm mb-8">
                        <h3 class="text-lg font-bold text-blue-600 mb-3">Removing Fields</h3>
                        <p class="text-sm text-gray-600 mb-4">Use <code>array_filter</code> inside the filter hook to remove any field by its <code>name</code>. Always use <b>priority 20</b> (or higher) so your filter runs <em>after</em> the default fields are built (priority 10).</p>

                        <p class="text-xs font-bold text-gray-500 uppercase mb-2">Remove a single field</p>
                        <div class="bg-gray-900 rounded-lg p-4 text-gray-300 font-mono text-xs overflow-x-auto mb-4">
                            <pre><code>@verbatim
add_falcon_filter('falcon_billing_fields', function ($fields) {
    return array_filter($fields, fn($f) => ($f['name'] ?? '') !== 'billing_address_2');
}, 20);
@endverbatim</code></pre>
                        </div>

                        <p class="text-xs font-bold text-gray-500 uppercase mb-2">Remove multiple fields at once</p>
                        <div class="bg-gray-900 rounded-lg p-4 text-gray-300 font-mono text-xs overflow-x-auto mb-6">
                            <pre><code>@verbatim
add_falcon_filter('falcon_billing_fields', function ($fields) {
    $remove = ['billing_address_2', 'billing_state'];
    return array_filter($fields, fn($f) => !in_array($f['name'] ?? '', $remove));
}, 20);
@endverbatim</code></pre>
                        </div>

                        <h4 class="text-sm font-bold text-gray-700 mb-3">Standard Billing Field Names</h4>
                        <div class="overflow-x-auto mb-4">
                            <table class="w-full text-xs text-left border border-gray-100 rounded-lg overflow-hidden">
                                <thead class="bg-gray-50 text-gray-500 uppercase">
                                    <tr><th class="px-3 py-2">name (use in array_filter)</th><th class="px-3 py-2">Field label</th><th class="px-3 py-2">Priority</th></tr>
                                </thead>
                                <tbody class="divide-y divide-gray-100">
                                    <tr><td class="px-3 py-2 font-mono text-blue-600">billing_first_name</td><td class="px-3 py-2">First name</td><td class="px-3 py-2">10</td></tr>
                                    <tr><td class="px-3 py-2 font-mono text-blue-600">billing_last_name</td><td class="px-3 py-2">Last name</td><td class="px-3 py-2">20</td></tr>
                                    <tr><td class="px-3 py-2 font-mono text-blue-600">billing_country</td><td class="px-3 py-2">Country / Region</td><td class="px-3 py-2">30</td></tr>
                                    <tr><td class="px-3 py-2 font-mono text-blue-600">billing_address_1</td><td class="px-3 py-2">Street address</td><td class="px-3 py-2">40</td></tr>
                                    <tr><td class="px-3 py-2 font-mono text-blue-600">billing_address_2</td><td class="px-3 py-2">Address line 2 (optional)</td><td class="px-3 py-2">50</td></tr>
                                    <tr><td class="px-3 py-2 font-mono text-blue-600">billing_city</td><td class="px-3 py-2">Town / City</td><td class="px-3 py-2">60</td></tr>
                                    <tr><td class="px-3 py-2 font-mono text-blue-600">billing_state</td><td class="px-3 py-2">State / Province</td><td class="px-3 py-2">70</td></tr>
                                    <tr><td class="px-3 py-2 font-mono text-blue-600">billing_postcode</td><td class="px-3 py-2">ZIP Code</td><td class="px-3 py-2">80</td></tr>
                                    <tr><td class="px-3 py-2 font-mono text-blue-600">billing_phone</td><td class="px-3 py-2">Phone</td><td class="px-3 py-2">90</td></tr>
                                    <tr><td class="px-3 py-2 font-mono text-blue-600">billing_email</td><td class="px-3 py-2">Email address</td><td class="px-3 py-2">100</td></tr>
                                </tbody>
                            </table>
                        </div>

                        <h4 class="text-sm font-bold text-gray-700 mb-3">Standard Shipping Field Names</h4>
                        <div class="overflow-x-auto">
                            <table class="w-full text-xs text-left border border-gray-100 rounded-lg overflow-hidden">
                                <thead class="bg-gray-50 text-gray-500 uppercase">
                                    <tr><th class="px-3 py-2">name</th><th class="px-3 py-2">Field label</th><th class="px-3 py-2">Priority</th></tr>
                                </thead>
                                <tbody class="divide-y divide-gray-100">
                                    <tr><td class="px-3 py-2 font-mono text-blue-600">shipping_first_name</td><td class="px-3 py-2">First name</td><td class="px-3 py-2">10</td></tr>
                                    <tr><td class="px-3 py-2 font-mono text-blue-600">shipping_last_name</td><td class="px-3 py-2">Last name</td><td class="px-3 py-2">20</td></tr>
                                    <tr><td class="px-3 py-2 font-mono text-blue-600">shipping_country</td><td class="px-3 py-2">Country / Region</td><td class="px-3 py-2">30</td></tr>
                                    <tr><td class="px-3 py-2 font-mono text-blue-600">shipping_address_1</td><td class="px-3 py-2">Street address</td><td class="px-3 py-2">40</td></tr>
                                    <tr><td class="px-3 py-2 font-mono text-blue-600">shipping_address_2</td><td class="px-3 py-2">Address line 2 (optional)</td><td class="px-3 py-2">50</td></tr>
                                    <tr><td class="px-3 py-2 font-mono text-blue-600">shipping_city</td><td class="px-3 py-2">Town / City</td><td class="px-3 py-2">60</td></tr>
                                    <tr><td class="px-3 py-2 font-mono text-blue-600">shipping_state</td><td class="px-3 py-2">State / Province</td><td class="px-3 py-2">70</td></tr>
                                    <tr><td class="px-3 py-2 font-mono text-blue-600">shipping_postcode</td><td class="px-3 py-2">ZIP Code</td><td class="px-3 py-2">80</td></tr>
                                    <tr><td class="px-3 py-2 font-mono text-blue-600">shipping_phone</td><td class="px-3 py-2">Phone</td><td class="px-3 py-2">90</td></tr>
                                </tbody>
                            </table>
                        </div>

                        <div class="mt-4 bg-amber-50 border-l-4 border-amber-400 p-3 text-xs text-amber-800">
                            <b>Important:</b> Removing a field from the hook only hides it from the form. The server-side validation for standard required fields (like <code>billing_email</code>) is enforced separately. If you remove a standard required field from the form, also pass an empty validation bypass or make it optional — otherwise the order will fail to submit.
                        </div>
                    </div>
                </section>

                {{-- Section: Order & Invoice Hooks --}}
                <section id="order-hooks">
                    <h2 class="text-2xl font-bold text-gray-900 mb-2">Order & Invoice Hooks</h2>
                    <p class="text-gray-700 mb-6">Hooks for the order confirmation page, admin order detail, and invoice print.</p>

                    {{-- Hooks table --}}
                    <div class="bg-white border border-gray-200 rounded-xl p-6 shadow-sm mb-8">
                        <h3 class="text-lg font-bold text-blue-600 mb-4">Order Confirmation Page</h3>
                        <div class="overflow-x-auto">
                            <table class="w-full text-xs text-left border border-gray-100 rounded-lg overflow-hidden">
                                <thead class="bg-gray-50 text-gray-500 uppercase">
                                    <tr><th class="px-3 py-2">Hook Tag</th><th class="px-3 py-2 w-16">Type</th><th class="px-3 py-2">Args</th><th class="px-3 py-2">Description</th></tr>
                                </thead>
                                <tbody class="divide-y divide-gray-100">
                                    <tr><td class="px-3 py-2 font-mono text-blue-600">lazy_before_order_confirmation</td><td class="px-3 py-2"><span class="bg-blue-100 text-blue-700 px-1.5 py-0.5 rounded text-[10px] font-bold">ACTION</span></td><td class="px-3 py-2 font-mono">$order</td><td class="px-3 py-2">Before the entire confirmation page renders</td></tr>
                                    <tr><td class="px-3 py-2 font-mono text-blue-600">lazy_after_order_confirmation</td><td class="px-3 py-2"><span class="bg-blue-100 text-blue-700 px-1.5 py-0.5 rounded text-[10px] font-bold">ACTION</span></td><td class="px-3 py-2 font-mono">$order</td><td class="px-3 py-2">After the entire confirmation page (great for analytics, pixel fires, etc.)</td></tr>
                                    <tr><td class="px-3 py-2 font-mono text-green-600">lazy_order_confirmation_item_name</td><td class="px-3 py-2"><span class="bg-green-100 text-green-700 px-1.5 py-0.5 rounded text-[10px] font-bold">FILTER</span></td><td class="px-3 py-2 font-mono">$html, $item, $order</td><td class="px-3 py-2">Modify the product name HTML in the order summary table (default: name + × qty). Return modified HTML string.</td></tr>
                                    <tr><td class="px-3 py-2 font-mono text-blue-600">lazy_order_confirmation_item_meta</td><td class="px-3 py-2"><span class="bg-blue-100 text-blue-700 px-1.5 py-0.5 rounded text-[10px] font-bold">ACTION</span></td><td class="px-3 py-2 font-mono">$item, $order</td><td class="px-3 py-2">Fires under each item row in the confirmation table. Echo extra HTML (e.g. customization details).</td></tr>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <div class="bg-white border border-gray-200 rounded-xl p-6 shadow-sm mb-8">
                        <h3 class="text-lg font-bold text-blue-600 mb-4">Invoice Print</h3>
                        <div class="overflow-x-auto">
                            <table class="w-full text-xs text-left border border-gray-100 rounded-lg overflow-hidden">
                                <thead class="bg-gray-50 text-gray-500 uppercase">
                                    <tr><th class="px-3 py-2">Hook Tag</th><th class="px-3 py-2 w-16">Type</th><th class="px-3 py-2">Args</th><th class="px-3 py-2">Description</th></tr>
                                </thead>
                                <tbody class="divide-y divide-gray-100">
                                    <tr><td class="px-3 py-2 font-mono text-green-600">lazy_invoice_title</td><td class="px-3 py-2"><span class="bg-green-100 text-green-700 px-1.5 py-0.5 rounded text-[10px] font-bold">FILTER</span></td><td class="px-3 py-2 font-mono">$title, $order</td><td class="px-3 py-2">Change the "Invoice" heading on the print page. Default: <code>'Invoice'</code>. You can return different text per order status, currency, or any condition.</td></tr>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    {{-- Examples --}}
                    <div class="bg-white border border-gray-200 rounded-xl p-6 shadow-sm">
                        <h3 class="text-lg font-bold text-blue-600 mb-4">Quick Examples</h3>
                        <div class="space-y-6">
                            <div>
                                <p class="text-xs font-bold text-gray-500 uppercase mb-2">Fire a conversion pixel after the confirmation page</p>
                                <div class="bg-gray-900 rounded-lg p-4 text-gray-300 font-mono text-xs overflow-x-auto">
                                    <pre><code>@verbatim
add_falcon_action('lazy_after_order_confirmation', function ($order) {
    echo '<script>
        fbq("track", "Purchase", {
            value: ' . (float) $order->total . ',
            currency: "' . strtoupper($order->currency ?? 'USD') . '"
        });
    </script>';
});
@endverbatim</code></pre>
                                </div>
                            </div>
                            <div>
                                <p class="text-xs font-bold text-gray-500 uppercase mb-2">Change the invoice title based on order status</p>
                                <div class="bg-gray-900 rounded-lg p-4 text-gray-300 font-mono text-xs overflow-x-auto">
                                    <pre><code>@verbatim
add_falcon_filter('lazy_invoice_title', function ($title, $order) {
    if ($order->status === 'refunded') {
        return 'Credit Note';
    }
    return $title; // default: "Invoice"
});
@endverbatim</code></pre>
                                </div>
                            </div>
                            <div>
                                <p class="text-xs font-bold text-gray-500 uppercase mb-2">Add extra data under an item in the confirmation table</p>
                                <div class="bg-gray-900 rounded-lg p-4 text-gray-300 font-mono text-xs overflow-x-auto">
                                    <pre><code>@verbatim
add_falcon_action('lazy_order_confirmation_item_meta', function ($item, $order) {
    $engraving = $item->meta['custom_fields']['engraving'] ?? null;
    if ($engraving) {
        echo '<div class="text-xs text-gray-500 mt-1">Engraving: ' . e($engraving) . '</div>';
    }
});
@endverbatim</code></pre>
                                </div>
                            </div>
                        </div>
                    </div>
                </section>

                <!-- Multilingual & Localization Section -->
                <section id="multilingual" class="doc-section mb-12">
                    <h2 class="text-2xl font-bold text-gray-900 mb-4">Multilingual & Localization</h2>
                    <p class="text-gray-700 mb-6">Reach a global audience with built-in translation support and dynamic URL structures.</p>

                    <div class="space-y-8">
                        {{-- Dynamic URL Logic --}}
                        <div class="bg-white border border-gray-200 rounded-xl p-6 shadow-sm">
                            <h3 class="text-lg font-bold text-blue-600 mb-3">1. Dynamic URL Logic</h3>
                            <p class="text-sm text-gray-600 mb-4">The system automatically detects if multi-language is enabled. If disabled, your site uses clean URLs (e.g. <code>/about-us</code>). If enabled, it uses ISO prefixes (e.g. <code>/en/about-us</code>).</p>
                            <div class="bg-blue-50 border-l-4 border-blue-400 p-4 text-xs text-blue-700">
                                <b>Auto-Hiding:</b> The language selector in the admin panel automatically hides itself when multi-language support is turned off.
                            </div>
                        </div>

                        {{-- Translatable Fields --}}
                        <div class="bg-white border border-gray-200 rounded-xl p-6 shadow-sm">
                            <h3 class="text-lg font-bold text-blue-600 mb-3">2. Translatable Option Fields</h3>
                            <p class="text-sm text-gray-600 mb-4">To make a custom option field translatable, simply add <code>'translatable' => true</code> to the field definition in <code>config/falcon-options.php</code>.</p>
                            <div class="bg-gray-900 rounded-lg p-4 text-gray-300 font-mono text-xs overflow-x-auto">
                                <pre><code>@verbatim
// config/falcon-options.php
'fields' => [
    'footer_text' => [
        'type' => 'textarea',
        'label' => 'Footer Copyright',
        'translatable' => true, // Enables per-language inputs
    ],
]
@endverbatim</code></pre>
                            </div>
                        </div>

                        {{-- Language Switcher --}}
                        <div class="bg-white border border-gray-200 rounded-xl p-6 shadow-sm">
                            <h3 class="text-lg font-bold text-blue-600 mb-3">3. Language Switcher Helpers</h3>
                            <p class="text-sm text-gray-600 mb-4">Use these helpers to render switchers in your frontend themes:</p>
                            <div class="bg-100 p-4 rounded-lg font-mono text-xs text-gray-700 space-y-2">
                                <code>{!! falcon_lang_dropdown() !!}</code> - A sleek dropdown with flags.<br>
                                <code>{!! lazy_lang_switcher() !!}</code> - A simple list/flex switcher.
                            </div>
                        </div>
                    </div>
                </section>

                {{-- Section: Custom Builder Elements --}}
                <section id="builder-elements">
                    <h2 class="text-2xl font-bold text-gray-900 mb-4">Custom Builder Elements</h2>
                    <p class="text-gray-700 mb-6">Register your own drag-and-drop elements for the Lazy Builder using the <code>falcon_builder_elements</code> filter inside your theme's <code>functions.php</code>. Each element gets its own fields, live canvas preview, automatic shortcode conversion, and a frontend template you fully control.</p>

                    {{-- 1. Registering an element --}}
                    <div class="bg-white border border-gray-200 rounded-xl p-6 shadow-sm mb-8">
                        <h3 class="text-lg font-bold text-blue-600 mb-3">1. Registering an Element</h3>
                        <p class="text-sm text-gray-600 mb-4">Add a filter that pushes a definition into the elements array. The array key and <code>type</code> should match and be unique.</p>
                        <div class="bg-gray-900 rounded-lg p-4 text-gray-300 font-mono text-xs overflow-x-auto">
                            <pre><code>@verbatim
// resources/views/themes/your-theme/functions.php
add_falcon_filter('falcon_builder_elements', function ($elements) {
    $elements['my_card'] = [
        'type'      => 'my_card',                 // unique identifier (required)
        'name'      => 'My Card',                 // label shown in the element picker
        'icon'      => 'fa fa-th-large',          // Font Awesome icon
        'shortcode' => 'my_card',                 // optional: own shortcode tag
        'template'  => 'your-theme::builder.elements.my-card', // frontend blade
        'params'    => [
            ['type' => 'textfield', 'heading' => 'Title', 'param_name' => 'title', 'value' => 'Hello', 'tab' => 'general'],
            ['type' => 'colorpickeralpha', 'heading' => 'Title Color', 'param_name' => 'color', 'value' => '#222', 'tab' => 'design'],
        ],
    ];
    return $elements;
});
@endverbatim</code></pre>
                        </div>
                        <div class="mt-4 grid grid-cols-1 md:grid-cols-2 gap-3 text-xs">
                            <div class="p-3 bg-gray-50 rounded border-l-4 border-blue-400"><b>type</b> <span class="text-gray-500">— required unique key.</span></div>
                            <div class="p-3 bg-gray-50 rounded border-l-4 border-blue-400"><b>name</b> <span class="text-gray-500">— shown in the picker & navigator.</span></div>
                            <div class="p-3 bg-gray-50 rounded border-l-4 border-blue-400"><b>icon</b> <span class="text-gray-500">— Font Awesome class.</span></div>
                            <div class="p-3 bg-gray-50 rounded border-l-4 border-blue-400"><b>shortcode</b> <span class="text-gray-500">— optional. Own readable tag, else <code>falcon_element</code>.</span></div>
                            <div class="p-3 bg-gray-50 rounded border-l-4 border-blue-400"><b>template</b> <span class="text-gray-500">— frontend blade view. Omit for the generic auto-renderer.</span></div>
                            <div class="p-3 bg-gray-50 rounded border-l-4 border-blue-400"><b>params</b> <span class="text-gray-500">— array of field definitions.</span></div>
                        </div>
                    </div>

                    {{-- 2. Field anatomy --}}
                    <div class="bg-white border border-gray-200 rounded-xl p-6 shadow-sm mb-8">
                        <h3 class="text-lg font-bold text-blue-600 mb-3">2. Field Anatomy (shared keys)</h3>
                        <p class="text-sm text-gray-600 mb-4">Every field in <code>params</code> accepts these keys:</p>
                        <div class="overflow-x-auto">
                        <table class="w-full text-xs text-left border border-gray-200 rounded-lg overflow-hidden">
                            <thead class="bg-gray-50 text-gray-500 uppercase">
                                <tr><th class="px-3 py-2">Key</th><th class="px-3 py-2">Description</th></tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100">
                                <tr><td class="px-3 py-2 font-mono text-blue-600">type</td><td class="px-3 py-2">Field type (see list below). Required.</td></tr>
                                <tr><td class="px-3 py-2 font-mono text-blue-600">heading</td><td class="px-3 py-2">Field label.</td></tr>
                                <tr><td class="px-3 py-2 font-mono text-blue-600">param_name</td><td class="px-3 py-2">Setting key. Optional — auto-generated from heading (e.g. "Sub Title" → <code>sub_title</code>).</td></tr>
                                <tr><td class="px-3 py-2 font-mono text-blue-600">value</td><td class="px-3 py-2">Default value.</td></tr>
                                <tr><td class="px-3 py-2 font-mono text-blue-600">tab</td><td class="px-3 py-2"><code>general</code> · <code>design</code> · <code>extra</code> — which settings tab the field appears in.</td></tr>
                                <tr><td class="px-3 py-2 font-mono text-blue-600">description</td><td class="px-3 py-2">Helper text under the field.</td></tr>
                                <tr><td class="px-3 py-2 font-mono text-blue-600">condition</td><td class="px-3 py-2">Show field only when another field matches (see §5).</td></tr>
                                <tr><td class="px-3 py-2 font-mono text-blue-600">dynamic</td><td class="px-3 py-2"><code>true</code> to enable the dynamic-source toggle (text / url / link only).</td></tr>
                            </tbody>
                        </table>
                        </div>
                        <div class="mt-3 bg-blue-50 border-l-4 border-blue-400 p-3 text-xs text-blue-700">
                            <b>Default tabs:</b> Every custom element automatically gets <b>General</b>, <b>Design</b>, and <b>Extra</b> tabs. The General tab always includes Element Visibility, CSS Class and CSS ID. The Extra tab includes Conditional Visibility and Scroll Entrance Animation — exactly like built-in elements.
                        </div>
                    </div>

                    {{-- 3. Field types --}}
                    <div class="bg-white border border-gray-200 rounded-xl p-6 shadow-sm mb-8">
                        <h3 class="text-lg font-bold text-blue-600 mb-4">3. Supported Field Types</h3>
                        <div class="space-y-5">

                            <div>
                                <h4 class="font-bold text-gray-800 text-sm mb-1">Text &amp; Content</h4>
                                <div class="bg-gray-900 rounded-lg p-4 text-gray-300 font-mono text-[11px] overflow-x-auto"><pre><code>@verbatim
['type' => 'textfield', 'heading' => 'Title',   'param_name' => 'title'],
['type' => 'textarea',  'heading' => 'Desc',    'param_name' => 'desc', 'rows' => 4],
['type' => 'wysiwyg',   'heading' => 'Body',    'param_name' => 'body'],   // TinyMCE rich editor
@endverbatim</code></pre></div>
                            </div>

                            <div>
                                <h4 class="font-bold text-gray-800 text-sm mb-1">Numbers</h4>
                                <div class="bg-gray-900 rounded-lg p-4 text-gray-300 font-mono text-[11px] overflow-x-auto"><pre><code>@verbatim
['type' => 'number', 'heading' => 'Count',   'param_name' => 'count', 'min' => 0, 'max' => 100, 'step' => 1],
['type' => 'slider', 'heading' => 'Opacity', 'param_name' => 'opacity', 'min' => 0, 'max' => 100, 'step' => 5, 'unit' => '%'],
@endverbatim</code></pre></div>
                            </div>

                            <div>
                                <h4 class="font-bold text-gray-800 text-sm mb-1">Choices</h4>
                                <div class="bg-gray-900 rounded-lg p-4 text-gray-300 font-mono text-[11px] overflow-x-auto"><pre><code>@verbatim
['type' => 'select',   'heading' => 'Size',  'param_name' => 'size', 'value' => 'md',
    'options' => ['sm' => 'Small', 'md' => 'Medium', 'lg' => 'Large']],
['type' => 'radio',    'heading' => 'Align', 'param_name' => 'align',
    'options' => ['left' => 'Left', 'center' => 'Center', 'right' => 'Right']],
['type' => 'checkbox', 'heading' => 'Features', 'param_name' => 'features',  // value is an array
    'options' => ['wifi' => 'WiFi', 'ac' => 'AC', 'tv' => 'TV']],
['type' => 'toggle',   'heading' => 'Show Icon', 'param_name' => 'show_icon', 'value' => true], // boolean
@endverbatim</code></pre></div>
                            </div>

                            <div>
                                <h4 class="font-bold text-gray-800 text-sm mb-1">Visual</h4>
                                <div class="bg-gray-900 rounded-lg p-4 text-gray-300 font-mono text-[11px] overflow-x-auto"><pre><code>@verbatim
['type' => 'colorpickeralpha', 'heading' => 'Color', 'param_name' => 'color', 'value' => '#222'],
['type' => 'icon',       'heading' => 'Icon', 'param_name' => 'icon'],  // full FA picker (search + Solid/Regular/Brands)
['type' => 'typography', 'heading' => 'Heading Font', 'param_name' => 'typo'], // family/size/weight/line-height/spacing/transform
['type' => 'dimensions', 'heading' => 'Padding', 'param_name' => 'pad', 'unit' => 'px'], // T/R/B/L + unit + responsive
@endverbatim</code></pre></div>
                            </div>

                            <div>
                                <h4 class="font-bold text-gray-800 text-sm mb-1">Media &amp; Links</h4>
                                <div class="bg-gray-900 rounded-lg p-4 text-gray-300 font-mono text-[11px] overflow-x-auto"><pre><code>@verbatim
['type' => 'media',  'heading' => 'Image', 'param_name' => 'img'],   // full media-library picker (same as 'image')
['type' => 'url',    'heading' => 'Link',  'param_name' => 'link'],   // text input + upload button
['type' => 'link',   'heading' => 'Link',  'param_name' => 'lnk'],    // url + open-in → {key}, {key}_target
['type' => 'button', 'heading' => 'Button','param_name' => 'btn'],    // label + url + target → {key}, {key}_url, {key}_target
['type' => 'date',   'heading' => 'Date',  'param_name' => 'date'],
@endverbatim</code></pre></div>
                                <p class="text-[11px] text-gray-500 mt-2">Every color picker now includes an <b>opacity slider</b> — alpha is saved into the value as 8-digit hex (<code>#RRGGBBAA</code>).</p>
                            </div>

                        </div>
                    </div>

                    {{-- 3b. Canvas Live Preview (prefix convention) --}}
                    <div class="bg-white border border-gray-200 rounded-xl p-6 shadow-sm mb-8">
                        <h3 class="text-lg font-bold text-blue-600 mb-3">3b. Live Canvas Preview — Prefix Convention</h3>
                        <p class="text-sm text-gray-600 mb-4">The builder canvas renders a <b>live, real-time preview</b> of your fields automatically — you write <b>no canvas code</b>. It figures out which design field styles which content field purely from the <b>param_name prefix</b>.</p>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-3 mb-4 text-xs">
                            <div class="p-3 bg-blue-50 rounded border-l-4 border-blue-400">
                                <b class="text-blue-700">Content fields</b> <span class="text-gray-600">(rendered on canvas):</span>
                                <p class="font-mono mt-1">text · textarea · wysiwyg · image · media · icon · button · repeater</p>
                            </div>
                            <div class="p-3 bg-blue-50 rounded border-l-4 border-blue-400">
                                <b class="text-blue-700">Design modifiers</b> <span class="text-gray-600">(relate by prefix):</span>
                                <p class="font-mono mt-1">{base}_color · {base}_hover_color · {base}_bg · {base}_hover_bg · {base}_typo · {base}_pad · {base}_margin · {base}_align</p>
                            </div>
                        </div>

                        <p class="text-sm text-gray-600 mb-2">Name a design field <code>{contentField}_color</code> and it auto-applies to that content field — live:</p>
                        <div class="bg-gray-900 rounded-lg p-4 text-gray-300 font-mono text-[11px] overflow-x-auto"><pre><code>@verbatim
['type' => 'textfield',        'param_name' => 'title'],              // content
['type' => 'colorpickeralpha', 'param_name' => 'title_color'],        // → colors `title`
['type' => 'colorpickeralpha', 'param_name' => 'title_hover_color'],  // → `title` color on :hover
['type' => 'typography',       'param_name' => 'title_typo'],         // → fonts  `title`
['type' => 'dimensions',       'param_name' => 'title_pad'],          // → pads   `title`

['type' => 'textarea',         'param_name' => 'content'],            // content
['type' => 'colorpickeralpha', 'param_name' => 'content_color'],      // → colors `content`
@endverbatim</code></pre></div>
                        <ul class="mt-3 text-xs text-gray-600 list-disc list-inside space-y-1">
                            <li>Change any field → the canvas updates <b>instantly</b> (no save, no reload).</li>
                            <li><b>Hover:</b> <code>{base}_hover_color</code> / <code>{base}_hover_bg</code> generate a real <code>:hover</code> rule — works on both canvas and front-end.</li>
                            <li>A modifier whose base has no matching content field (e.g. <code>box_bg</code>) applies to the whole element wrapper.</li>
                            <li>When you don't supply a <code>template</code>, the front-end auto-renders with this <b>same convention</b> — so the live site mirrors the canvas exactly (no custom code needed).</li>
                        </ul>

                        <h4 class="text-sm font-bold text-gray-800 mt-5 mb-2">One field → many targets</h4>
                        <p class="text-sm text-gray-600 mb-2">Prefix relation is 1-to-1. To relate <b>one design field to multiple content fields</b>, give <code>param_name</code> an <b>array</b>. Two equally valid ways:</p>

                        <p class="text-xs font-bold text-gray-700 mt-3 mb-1">A) Bare target names <span class="font-normal text-gray-500">— simplest; just list the content fields it styles</span></p>
                        <div class="bg-gray-900 rounded-lg p-4 text-gray-300 font-mono text-[11px] overflow-x-auto"><pre><code>@verbatim
['type' => 'textfield', 'param_name' => 'title'],
['type' => 'textfield', 'param_name' => 'subtitle'],
['type' => 'wysiwyg',   'param_name' => 'content'],

// One color field that styles title + subtitle + content:
['type' => 'colorpickeralpha', 'heading' => 'Text Color',
    'param_name' => ['title', 'subtitle', 'content']],

// One typography field shared by title + subtitle:
['type' => 'typography', 'heading' => 'Heading Font', 'param_name' => ['title', 'subtitle']],
@endverbatim</code></pre></div>
                        <p class="text-xs text-gray-500 mt-2">The field's storage key is taken from its <b>heading</b> (so <code>Text Color</code> → <code>text_color</code>) — it never collides with your content fields. The property comes from the field type (color → color, typography → all font props, dimensions → padding).</p>

                        <p class="text-xs font-bold text-gray-700 mt-4 mb-1">B) Suffixed keys <span class="font-normal text-gray-500">— when you want different properties per target</span></p>
                        <div class="bg-gray-900 rounded-lg p-4 text-gray-300 font-mono text-[11px] overflow-x-auto"><pre><code>@verbatim
// Normal color on title + subtitle:
['type' => 'colorpickeralpha', 'param_name' => ['title_color', 'subtitle_color']],

// Hover color on title + subtitle:
['type' => 'colorpickeralpha', 'param_name' => ['title_hover_color', 'subtitle_hover_color']],

// Mixed: title text color + box background:
['type' => 'colorpickeralpha', 'param_name' => ['title_color', 'box_bg']],
@endverbatim</code></pre></div>
                        <p class="text-xs text-gray-500 mt-2">Each entry follows the <code>{base}_{suffix}</code> convention — <code>_color</code> · <code>_bg</code> · <code>_hover_color</code> · <code>_hover_bg</code> · <code>_typo</code> · <code>_pad</code> · <code>_margin</code>.</p>

                        <details class="mt-4">
                            <summary class="text-xs font-bold text-gray-600 cursor-pointer">Advanced: explicit <code>apply_to</code> / <code>apply_as</code></summary>
                            <p class="text-xs text-gray-600 mt-2 mb-2">Both array forms are sugar for these keys, which you can also write explicitly:</p>
                            <div class="bg-gray-900 rounded-lg p-4 text-gray-300 font-mono text-[11px] overflow-x-auto"><pre><code>@verbatim
['type' => 'colorpickeralpha', 'param_name' => 'brand_color',
    'apply_to' => ['title', 'content', 'subtitle'],   // target content keys
    'apply_as' => 'color',                             // color | bg | hover_color | hover_bg
],
@endverbatim</code></pre></div>
                            <ul class="mt-2 text-xs text-gray-600 list-disc list-inside space-y-1">
                                <li><code>apply_as</code> (color): <code>color</code> (default) · <code>bg</code> · <code>hover_color</code> · <code>hover_bg</code>.</li>
                                <li><code>apply_as</code> (dimensions): <code>padding</code> (default) · <code>margin</code>. Typography applies all its properties.</li>
                            </ul>
                        </details>
                        <p class="text-xs text-gray-600 mt-3">All forms work on <b>canvas &amp; front-end</b> and layer on top of normal prefix relations.</p>
                    </div>

                    {{-- 4. Repeater --}}
                    <div class="bg-white border border-gray-200 rounded-xl p-6 shadow-sm mb-8">
                        <h3 class="text-lg font-bold text-blue-600 mb-3">4. Repeater Fields</h3>
                        <p class="text-sm text-gray-600 mb-4">A repeater holds an unlimited list of rows. Each row contains its own set of sub-fields (any field type above). Rows can be added, deleted, and reordered. The stored value is an array of row objects.</p>
                        <div class="bg-gray-900 rounded-lg p-4 text-gray-300 font-mono text-[11px] overflow-x-auto"><pre><code>@verbatim
['type' => 'repeater', 'heading' => 'Team', 'param_name' => 'members', 'tab' => 'general',
    'fields' => [
        ['type' => 'textfield', 'heading' => 'Name',  'param_name' => 'name'],
        ['type' => 'media',     'heading' => 'Photo', 'param_name' => 'photo'],
        ['type' => 'icon',      'heading' => 'Icon',  'param_name' => 'icon'],
    ],
],
@endverbatim</code></pre></div>
                        <p class="text-xs text-gray-500 mt-3">Access in your template as an array: <code>$s['members']</code> → each item is <code>['name' => ..., 'photo' => ..., 'icon' => ...]</code>.</p>
                    </div>

                    {{-- 5. Conditional fields --}}
                    <div class="bg-white border border-gray-200 rounded-xl p-6 shadow-sm mb-8">
                        <h3 class="text-lg font-bold text-blue-600 mb-3">5. Conditional Fields</h3>
                        <p class="text-sm text-gray-600 mb-4">Use <code>condition</code> to show a field only when another field matches a value. The controlling field can be <b>any</b> type (toggle, select, text, number…).</p>
                        <div class="bg-gray-900 rounded-lg p-4 text-gray-300 font-mono text-[11px] overflow-x-auto"><pre><code>@verbatim
['type' => 'toggle', 'heading' => 'Use Link', 'param_name' => 'use_link', 'value' => false],
['type' => 'url', 'heading' => 'Link URL', 'param_name' => 'link_url',
    'condition' => ['field' => 'use_link', 'value' => true]],   // shown only when Use Link is ON

['type' => 'select', 'heading' => 'Style', 'param_name' => 'style',
    'options' => ['basic' => 'Basic', 'pro' => 'Pro']],
['type' => 'colorpickeralpha', 'heading' => 'Pro Color', 'param_name' => 'pro_color',
    'condition' => ['field' => 'style', 'value' => 'pro']],     // shown only when Style = Pro
@endverbatim</code></pre></div>
                        <div class="mt-3 text-xs text-gray-600">
                            <b>Operators</b> (<code>operator</code> key): <code>==</code> (default), <code>!=</code>, <code>&gt;</code>, <code>&lt;</code>, <code>in</code>, <code>not_in</code>, <code>contains</code>, <code>truthy</code>, <code>falsy</code>.
                            Pass an <b>array of conditions</b> for AND logic.
                        </div>
                    </div>

                    {{-- 6. Dynamic source --}}
                    <div class="bg-white border border-gray-200 rounded-xl p-6 shadow-sm mb-8">
                        <h3 class="text-lg font-bold text-blue-600 mb-3">6. Dynamic Sources</h3>
                        <p class="text-sm text-gray-600 mb-4">Add <code>'dynamic' => true</code> to a <code>text</code>, <code>url</code> or <code>link</code> field to show the database toggle. The editor can then bind the field to live post data, resolved automatically on the frontend.</p>
                        <div class="bg-gray-900 rounded-lg p-4 text-gray-300 font-mono text-[11px] overflow-x-auto"><pre><code>@verbatim
['type' => 'text', 'heading' => 'Title', 'param_name' => 'title', 'dynamic' => true],
['type' => 'link', 'heading' => 'Read More', 'param_name' => 'more', 'dynamic' => true],
@endverbatim</code></pre></div>
                        <p class="text-xs text-gray-500 mt-3">Available sources: <code>post_title</code>, <code>post_url</code>, <code>post_excerpt</code>, <code>post_date</code>, <code>post_author</code>, <code>featured_image</code>, <code>site_name</code>.</p>
                    </div>

                    {{-- 7. Frontend template --}}
                    <div class="bg-white border border-gray-200 rounded-xl p-6 shadow-sm mb-8">
                        <h3 class="text-lg font-bold text-blue-600 mb-3">7. Frontend Template (full control)</h3>
                        <p class="text-sm text-gray-600 mb-4">Point <code>template</code> to a blade view. All field values arrive in <code>$s</code> (alias of <code>$el['settings']</code>). Dynamic values are already resolved. Visibility, CSS class and CSS ID are applied by the builder's master wrapper — you only write the inner markup.</p>
                        <div class="bg-gray-900 rounded-lg p-4 text-gray-300 font-mono text-[11px] overflow-x-auto"><pre><code>@verbatim
{{-- resources/views/themes/your-theme/builder/elements/my-card.blade.php --}}
@php $s = $el['settings'] ?? []; @endphp

<div class="my-card" style="color: {{ $s['color'] ?? '#222' }}">
    <h3>{{ $s['title'] ?? '' }}</h3>
    <p>{!! $s['content'] ?? '' !!}</p>

    @if(!empty($s['bg_image']))
        <img src="{{ $s['bg_image'] }}" alt="">
    @endif

    {{-- Repeater rows --}}
    @foreach($s['members'] ?? [] as $m)
        <div class="member">
            @if(!empty($m['photo']))<img src="{{ $m['photo'] }}">@endif
            <i class="{{ $m['icon'] ?? '' }}"></i>
            <span>{{ $m['name'] ?? '' }}</span>
        </div>
    @endforeach
</div>
@endverbatim</code></pre></div>
                        <div class="mt-3 bg-amber-50 border-l-4 border-amber-400 p-3 text-xs text-amber-800">
                            If you omit <code>template</code>, the front-end auto-renders using the <b>same prefix convention as the canvas</b> (§3b) — text, images, icons, buttons, repeaters with all <code>_color</code>/<code>_typo</code>/<code>_pad</code>/<code>_hover_*</code> styling applied. The live site mirrors the canvas with zero custom code.
                        </div>
                    </div>

                    {{-- 8. Shortcode conversion --}}
                    <div class="bg-white border border-gray-200 rounded-xl p-6 shadow-sm">
                        <h3 class="text-lg font-bold text-blue-600 mb-3">8. Automatic Shortcode Conversion</h3>
                        <p class="text-sm text-gray-600 mb-4">Every field is serialized into a clean, human-readable shortcode automatically — and parsed back losslessly. Fields you remove from the definition are dropped from the shortcode on the next save.</p>
                        <div class="bg-gray-900 rounded-lg p-4 text-gray-300 font-mono text-[11px] overflow-x-auto"><pre><code>@verbatim
[my_card title="Hello" color="#222" features="wifi,ac"]
    [lzr_members name="Jane" photo="/storage/2026/05/jane.webp" icon="fa fa-star" /]
    [lzr_members name="John" icon="fa fa-heart" /]
[/my_card]
@endverbatim</code></pre></div>
                        <ul class="mt-3 text-xs text-gray-600 list-disc list-inside space-y-1">
                            <li>Scalars → plain attributes (<code>title="…"</code>)</li>
                            <li>Checkbox arrays → comma list (<code>features="wifi,ac"</code>)</li>
                            <li>Booleans → <code>1</code> / <code>0</code></li>
                            <li>Repeater rows → child shortcodes <code>[lzr_&lt;key&gt; … /]</code></li>
                            <li>Global Extra-tab options (animation, conditional visibility, CSS class/ID) are always preserved.</li>
                            <li>No base64 / encryption — everything stays readable &amp; editable.</li>
                        </ul>

                        <div class="mt-5 pt-4 border-t border-gray-100">
                            <h4 class="text-sm font-bold text-gray-800 mb-2">Whitelisting extra custom keys</h4>
                            <p class="text-xs text-gray-600 mb-3">Need a setting key that isn't a declared field to survive the round-trip? Add it to <code>shortcode_keys</code> in the element definition:</p>
                            <div class="bg-gray-900 rounded-lg p-4 text-gray-300 font-mono text-[11px] overflow-x-auto"><pre><code>@verbatim
$elements['my_card'] = [
    'type'           => 'my_card',
    'shortcode'      => 'my_card',
    'shortcode_keys' => ['layout_mode', 'custom_data'], // persisted in the shortcode
    'params'         => [ /* ... */ ],
];
@endverbatim</code></pre></div>
                        </div>
                    </div>
                </section>

                <!-- REST API Section -->
                <section id="rest-api" class="doc-section mb-12">
                    <h2 class="text-2xl font-bold text-gray-900 mb-4">REST API & Headless</h2>
                    <p class="text-gray-700 mb-6">A built-in JSON REST API lets you power a React / Vue / Next.js front-end, a mobile app, or any external integration. <b>Read</b> endpoints are public; <b>write</b> endpoints require a personal API token. All responses are JSON and CORS is enabled, so you can call the API from any domain.</p>

                    {{-- Basics --}}
                    <div class="bg-white border border-gray-200 rounded-xl p-6 shadow-sm mb-6">
                        <h3 class="font-bold text-gray-800 mb-3">1. Basics</h3>
                        <ul class="text-sm text-gray-700 space-y-2">
                            <li><b>Base URL:</b> <code class="bg-gray-100 px-1.5 py-0.5 rounded text-blue-600">{{ url('/api/v1') }}</code></li>
                            <li><b>Format:</b> every response is <code class="bg-gray-100 px-1 rounded">application/json</code> wrapped in a consistent envelope:</li>
                        </ul>
                        <div class="bg-gray-900 rounded-lg p-4 text-gray-300 font-mono text-[11px] mt-3">
<pre>@verbatim{
  "success": true,
  "data": [ ... ],          // object or array
  "meta": {                 // present on paginated lists
    "current_page": 1,
    "per_page": 10,
    "total": 42,
    "last_page": 5
  }
}@endverbatim</pre>
                        </div>
                        <ul class="text-xs text-gray-500 space-y-1 mt-3">
                            <li>• <b>Rate limit:</b> 60 requests / minute per IP (see the <code>X-RateLimit-*</code> response headers).</li>
                            <li>• <b>Enable / disable:</b> the whole API can be toggled from <b>Settings → API</b> (<code>enable_rest_api</code>).</li>
                            <li>• <b>Pagination:</b> add <code>?limit=20</code> (max 100) and <code>?page=2</code> to any list endpoint.</li>
                        </ul>
                    </div>

                    {{-- Authentication --}}
                    <div class="bg-white border border-gray-200 rounded-xl p-6 shadow-sm mb-6">
                        <h3 class="font-bold text-gray-800 mb-3">2. Authentication (for write access)</h3>
                        <p class="text-sm text-gray-700 mb-3">Reading is public and needs no key. To <b>create / update / delete</b>, generate a personal token:</p>
                        <ol class="text-sm text-gray-700 list-decimal list-inside space-y-1 mb-3">
                            <li>Go to <b>Settings → API → API Tokens</b>.</li>
                            <li>Type a name (e.g. "Mobile App") and click <b>Generate Token</b>.</li>
                            <li>Copy the token <b>immediately</b> — it is shown only once (only its hash is stored).</li>
                        </ol>
                        <p class="text-sm text-gray-700 mb-2">Send it on every write request in the <code class="bg-gray-100 px-1 rounded">Authorization</code> header:</p>
                        <div class="bg-gray-900 rounded-lg p-4 text-gray-300 font-mono text-[11px]">
<pre>@verbatim Authorization: Bearer YOUR_API_TOKEN @endverbatim</pre>
                        </div>
                        <p class="text-xs text-gray-500 mt-3">A token <b>acts as the user who created it</b> and inherits that user's role permissions. So a token from an "Editor" can manage posts, but a token from a limited role gets <code>403 Forbidden</code> on actions it isn't allowed to perform.</p>
                    </div>

                    {{-- Endpoints table --}}
                    <h3 class="text-lg font-bold text-gray-800 mb-3">3. Endpoints</h3>
                    <div class="bg-gray-50 border border-gray-200 rounded-lg overflow-hidden mb-3">
                        <table class="w-full text-xs text-left">
                            <thead class="bg-gray-100 text-gray-600">
                                <tr><th class="px-3 py-2">Method</th><th class="px-3 py-2">Endpoint</th><th class="px-3 py-2">Auth</th><th class="px-3 py-2">Description</th></tr>
                            </thead>
                            <tbody class="text-gray-700">
                                @php
                                    $rows = [
                                        ['GET','/posts','—','List published posts. Params: limit, page, type'],
                                        ['GET','/posts/{slug}','—','Single post by slug'],
                                        ['GET','/pages','—','List published pages'],
                                        ['GET','/posts?type={cpt}','—','Any custom post type (e.g. type=portfolio)'],
                                        ['GET','/products','—','List products. Params: limit, page, category'],
                                        ['GET','/products/{slug}','—','Single product (price, stock, SKU…)'],
                                        ['GET','/categories','—','Post categories'],
                                        ['GET','/tags','—','Post tags'],
                                        ['GET','/menus','—','Front-end navigation menus (nested)'],
                                        ['GET','/settings','—','Public site settings'],
                                        ['GET','/search?q={term}','—','Search posts, pages & products (min 2 chars)'],
                                        ['POST','/posts','Token','Create a post/page/CPT'],
                                        ['PUT','/posts/{id}','Token','Update a post'],
                                        ['DELETE','/posts/{id}','Token','Delete a post'],
                                    ];
                                @endphp
                                @foreach($rows as $r)
                                    <tr class="border-t border-gray-100">
                                        <td class="px-3 py-2"><span class="font-bold {{ $r[0]==='GET' ? 'text-green-600' : ($r[0]==='DELETE' ? 'text-red-600' : 'text-orange-600') }}">{{ $r[0] }}</span></td>
                                        <td class="px-3 py-2"><code class="text-blue-600">/api/v1{{ $r[1] }}</code></td>
                                        <td class="px-3 py-2">{{ $r[2] }}</td>
                                        <td class="px-3 py-2 text-gray-500">{{ $r[3] }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>

                    {{-- Examples: reading --}}
                    <h3 class="text-lg font-bold text-gray-800 mb-3 mt-8">4. Examples — Reading (public)</h3>
                    <div class="grid grid-cols-1 lg:grid-cols-2 gap-4 mb-4">
                        <div>
                            <p class="text-xs font-bold text-gray-600 mb-1">cURL — latest 5 posts</p>
                            <div class="bg-gray-900 rounded-lg p-4 text-gray-300 font-mono text-[11px]">
<pre>@verbatim curl "{{ url('/api/v1/posts?limit=5') }}" @endverbatim</pre>
                            </div>
                        </div>
                        <div>
                            <p class="text-xs font-bold text-gray-600 mb-1">JavaScript (fetch) — products</p>
                            <div class="bg-gray-900 rounded-lg p-4 text-gray-300 font-mono text-[11px]">
<pre>@verbatim const res = await fetch(
  '{{ url('/api/v1/products?limit=12') }}'
);
const { data, meta } = await res.json();
console.log(data, meta.total);@endverbatim</pre>
                            </div>
                        </div>
                    </div>
                    <p class="text-xs font-bold text-gray-600 mb-1">Sample response — <code>GET /api/v1/products/{slug}</code></p>
                    <div class="bg-gray-900 rounded-lg p-4 text-gray-300 font-mono text-[11px] mb-2">
<pre>@verbatim{
  "success": true,
  "data": {
    "id": 45,
    "title": "Samsung Galaxy A57",
    "slug": "samsung-galaxy-a57",
    "excerpt": "...",
    "price": 50000,
    "sale_price": 47000,
    "sku": "SM-A57",
    "in_stock": true,
    "stock_quantity": 8,
    "product_type": "simple",
    "featured_image": "https://your-site.com/storage/media/a57.webp",
    "categories": [ { "name": "Phones", "slug": "phones" } ],
    "url": "https://your-site.com/product/samsung-galaxy-a57"
  }
}@endverbatim</pre>
                    </div>

                    {{-- Examples: writing --}}
                    <h3 class="text-lg font-bold text-gray-800 mb-3 mt-8">5. Examples — Writing (token required)</h3>
                    <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
                        <div>
                            <p class="text-xs font-bold text-gray-600 mb-1">Create a post (cURL)</p>
                            <div class="bg-gray-900 rounded-lg p-4 text-gray-300 font-mono text-[11px]">
<pre>@verbatim curl -X POST "{{ url('/api/v1/posts') }}" \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "Accept: application/json" \
  -d "title=Hello World" \
  -d "content=My first API post" \
  -d "status=published" \
  -d "type=post"@endverbatim</pre>
                            </div>
                        </div>
                        <div>
                            <p class="text-xs font-bold text-gray-600 mb-1">Update / Delete</p>
                            <div class="bg-gray-900 rounded-lg p-4 text-gray-300 font-mono text-[11px]">
<pre>@verbatim# Update
curl -X PUT "{{ url('/api/v1/posts/123') }}" \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "Accept: application/json" \
  -d "title=Updated title"

# Delete
curl -X DELETE "{{ url('/api/v1/posts/123') }}" \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "Accept: application/json"@endverbatim</pre>
                            </div>
                        </div>
                    </div>
                    <div class="bg-blue-50 border border-blue-100 rounded-lg p-4 mt-4">
                        <p class="text-xs text-blue-800"><b>Accepted fields on create/update:</b> <code>title</code> (required on create), <code>content</code>, <code>excerpt</code>, <code>status</code> (<code>draft</code> | <code>published</code> | <code>pending</code>), <code>type</code> (default <code>post</code>), <code>slug</code> (auto-generated if omitted).</p>
                    </div>

                    {{-- Errors --}}
                    <h3 class="text-lg font-bold text-gray-800 mb-3 mt-8">6. Error responses</h3>
                    <div class="bg-gray-50 border border-gray-200 rounded-lg overflow-hidden mb-2">
                        <table class="w-full text-xs text-left">
                            <thead class="bg-gray-100 text-gray-600"><tr><th class="px-3 py-2">Status</th><th class="px-3 py-2">Meaning</th></tr></thead>
                            <tbody class="text-gray-700">
                                <tr class="border-t border-gray-100"><td class="px-3 py-2 font-bold">401</td><td class="px-3 py-2">Missing or invalid API token (write endpoints).</td></tr>
                                <tr class="border-t border-gray-100"><td class="px-3 py-2 font-bold">403</td><td class="px-3 py-2">Token user lacks the required permission, or the API is disabled.</td></tr>
                                <tr class="border-t border-gray-100"><td class="px-3 py-2 font-bold">404</td><td class="px-3 py-2">Resource (slug / id) not found.</td></tr>
                                <tr class="border-t border-gray-100"><td class="px-3 py-2 font-bold">422</td><td class="px-3 py-2">Validation failed — check the <code>errors</code> object in the response.</td></tr>
                                <tr class="border-t border-gray-100"><td class="px-3 py-2 font-bold">429</td><td class="px-3 py-2">Too many requests (rate limit exceeded).</td></tr>
                            </tbody>
                        </table>
                    </div>

                    {{-- Extending --}}
                    <h3 class="text-lg font-bold text-gray-800 mb-3 mt-8">7. Extending API output</h3>
                    <p class="text-xs text-gray-600 mb-2">Add custom fields to the post JSON via a theme hook (in your child theme's <code>functions.php</code>):</p>
                    <div class="bg-gray-900 rounded-lg p-4 text-gray-300 font-mono text-[11px]">
<pre>@verbatim add_falcon_filter('lazy_api_post_data', function ($data, $post) {
    $data['reading_time'] = ceil(str_word_count(strip_tags($post->content)) / 200) . ' min read';
    return $data;
}); @endverbatim</pre>
                    </div>

                    {{-- Custom fields (dynamic) --}}
                    <h3 class="text-lg font-bold text-gray-800 mb-3 mt-8">8. Custom fields (auto-detected)</h3>
                    <div class="bg-green-50 border border-green-200 rounded-lg p-4 mb-4">
                        <p class="text-sm text-green-900"><b>The API is schema-driven.</b> Every post / page / product / CPT response includes a <code class="bg-white px-1 rounded">custom_fields</code> object built <b>live from the database</b>. When you add or remove a field in <b>ACPT → Field Groups</b>, the API output updates <b>automatically</b> — no code change, no redeploy.</p>
                    </div>
                    <ul class="text-sm text-gray-700 space-y-1 mb-3">
                        <li>• Only the fields assigned to that item's <b>post type</b> are returned (per the field group's rules).</li>
                        <li>• Fields with no value yet appear as <code>null</code>, so consumers always see the full schema.</li>
                        <li>• Complex values (repeater, gallery, checkbox group) are returned as decoded <b>arrays</b>.</li>
                    </ul>
                    <p class="text-xs font-bold text-gray-600 mb-1">Example — a "book" post with custom fields:</p>
                    <div class="bg-gray-900 rounded-lg p-4 text-gray-300 font-mono text-[11px]">
<pre>@verbatim{
  "success": true,
  "data": {
    "id": 12,
    "title": "The Pragmatic Programmer",
    "slug": "the-pragmatic-programmer",
    "type": "books",
    "custom_fields": {
      "writer_name": "Andrew Hunt",
      "publisher": "Addison-Wesley",
      "rating": null,                       // defined but not filled yet
      "chapters": [                          // repeater -> array
        { "title": "Intro", "pages": "12" }
      ]
    }
  }
}@endverbatim</pre>
                    </div>
                    <p class="text-xs text-gray-500 mt-2">Read a single field server-side with <code class="bg-gray-100 px-1 rounded">get_custom_field($post, 'writer_name')</code>, or the whole set with <code class="bg-gray-100 px-1 rounded">get_post_custom_fields($post)</code>.</p>
                </section>

                {{-- Section: RBAC --}}
                <section id="rbac">
                    <h2 class="text-2xl font-bold text-gray-900 mb-4">Roles & Permissions (RBAC)</h2>
                    <p class="text-gray-700 mb-6">FalconCMS includes a powerful granular permission system. Access is controlled via Roles that are linked to specific Permissions.</p>
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-8">
                        <div class="p-4 bg-white border border-gray-200 rounded-xl shadow-sm">
                            <h3 class="font-bold text-gray-800">Predefined Roles</h3>
                            <ul class="mt-2 space-y-1 text-sm text-gray-600">
                                <li><b>Super Admin:</b> Unrestricted access.</li>
                                <li><b>Administrator:</b> Full settings & content access.</li>
                                <li><b>Editor:</b> Manage all posts & comments.</li>
                                <li><b>Author:</b> Manage only their own posts.</li>
                                <li><b>Contributor:</b> Write posts (pending review).</li>
                                <li><b>User:</b> Basic dashboard access.</li>
                            </ul>
                        </div>
                        <div class="p-4 bg-white border border-gray-200 rounded-xl shadow-sm">
                            <h3 class="font-bold text-gray-800">Permission Syncing</h3>
                            <p class="text-xs text-gray-500 mt-2">When you add new menus or features, run the sync command to update permissions:</p>
                            <code class="block bg-gray-50 p-2 mt-2 text-[10px]">php artisan lazy:update</code>
                        </div>
                    </div>
                </section>

                {{-- Section: Security Hardening --}}
                <section id="security">
                    <h2 class="text-2xl font-bold text-gray-900 mb-4">Security Hardening</h2>
                    <p class="text-gray-700 mb-6">FalconCMS ships with multiple security layers enabled by default. This section documents what is active out of the box and what can be tuned.</p>

                    {{-- Security Headers --}}
                    <div class="bg-white border border-gray-200 rounded-xl p-6 shadow-sm mb-8">
                        <h3 class="text-lg font-bold text-blue-600 mb-3">1. HTTP Security Headers</h3>
                        <p class="text-sm text-gray-600 mb-4">The <code>SecurityHeadersMiddleware</code> is applied globally and injects the following headers on every response:</p>
                        <div class="overflow-x-auto">
                            <table class="w-full text-xs text-left border border-gray-100 rounded-lg overflow-hidden">
                                <thead class="bg-gray-50 text-gray-500 uppercase">
                                    <tr><th class="px-3 py-2">Header</th><th class="px-3 py-2">Value</th><th class="px-3 py-2">Purpose</th></tr>
                                </thead>
                                <tbody class="divide-y divide-gray-100">
                                    <tr><td class="px-3 py-2 font-mono text-blue-600 whitespace-nowrap">X-Content-Type-Options</td><td class="px-3 py-2 font-mono">nosniff</td><td class="px-3 py-2">Prevents MIME-type sniffing</td></tr>
                                    <tr><td class="px-3 py-2 font-mono text-blue-600 whitespace-nowrap">X-Frame-Options</td><td class="px-3 py-2 font-mono">SAMEORIGIN</td><td class="px-3 py-2">Blocks clickjacking via iframes</td></tr>
                                    <tr><td class="px-3 py-2 font-mono text-blue-600 whitespace-nowrap">X-XSS-Protection</td><td class="px-3 py-2 font-mono">1; mode=block</td><td class="px-3 py-2">Legacy XSS filter (IE/Edge)</td></tr>
                                    <tr><td class="px-3 py-2 font-mono text-blue-600 whitespace-nowrap">Referrer-Policy</td><td class="px-3 py-2 font-mono">strict-origin-when-cross-origin</td><td class="px-3 py-2">Controls referrer leakage</td></tr>
                                    <tr><td class="px-3 py-2 font-mono text-blue-600 whitespace-nowrap">Permissions-Policy</td><td class="px-3 py-2 font-mono">camera=(), microphone=(), geolocation=()</td><td class="px-3 py-2">Restricts browser feature access</td></tr>
                                    <tr><td class="px-3 py-2 font-mono text-blue-600 whitespace-nowrap">Content-Security-Policy</td><td class="px-3 py-2 font-mono">default-src 'self'; …</td><td class="px-3 py-2">Restricts content origins (XSS mitigation)</td></tr>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    {{-- Rate Limiting --}}
                    <div class="bg-white border border-gray-200 rounded-xl p-6 shadow-sm mb-8">
                        <h3 class="text-lg font-bold text-blue-600 mb-3">2. Rate Limiting</h3>
                        <p class="text-sm text-gray-600 mb-4">Throttle limits are applied at the route level to prevent brute-force and scraping attacks:</p>
                        <div class="overflow-x-auto">
                            <table class="w-full text-xs text-left border border-gray-100 rounded-lg overflow-hidden">
                                <thead class="bg-gray-50 text-gray-500 uppercase">
                                    <tr><th class="px-3 py-2">Route Group</th><th class="px-3 py-2">Limit</th><th class="px-3 py-2">Scope</th></tr>
                                </thead>
                                <tbody class="divide-y divide-gray-100">
                                    <tr><td class="px-3 py-2">Login, forgot-password</td><td class="px-3 py-2 font-mono">5 / min</td><td class="px-3 py-2">Per IP</td></tr>
                                    <tr><td class="px-3 py-2">Comment submission</td><td class="px-3 py-2 font-mono">3 / min</td><td class="px-3 py-2">Per IP</td></tr>
                                    <tr><td class="px-3 py-2">REST API</td><td class="px-3 py-2 font-mono">60 / min</td><td class="px-3 py-2">Per IP</td></tr>
                                    <tr><td class="px-3 py-2">General frontend</td><td class="px-3 py-2 font-mono">120 / min</td><td class="px-3 py-2">Per IP</td></tr>
                                </tbody>
                            </table>
                        </div>
                        <div class="mt-4 bg-blue-50 border-l-4 border-blue-400 p-3 text-xs text-blue-700">
                            The admin login form additionally tracks failed attempts per user account and per IP (see Brute-Force Protection below). Exceeding 5 failed attempts blocks the account for 30 minutes and may permanently block the IP.
                        </div>
                    </div>

                    {{-- Brute-Force / Login Protection --}}
                    <div class="bg-white border border-gray-200 rounded-xl p-6 shadow-sm mb-8">
                        <h3 class="text-lg font-bold text-blue-600 mb-3">3. Brute-Force & Login Protection</h3>
                        <ul class="text-sm text-gray-700 space-y-2 list-disc list-inside mb-4">
                            <li><b>Account lockout:</b> 5 wrong passwords → account blocked for 30 minutes. Super admins get their oldest session kicked instead.</li>
                            <li><b>IP blocking:</b> 5 attempts with non-existent emails → IP permanently blocked (stored in <code>blocked_ips</code> table). Admin can unblock from the dashboard.</li>
                            <li><b>Multi-device limit:</b> Configurable per site (<b>Settings → Security</b>). Exceeding the limit prevents new logins for non-super-admins.</li>
                            <li><b>Magic link login:</b> Optional passwordless login with a 10-minute token; used link is marked <code>used_at</code> immediately.</li>
                        </ul>
                        <div class="bg-amber-50 border-l-4 border-amber-400 p-3 text-xs text-amber-800">
                            Failed-login counters reset automatically on a successful login. Permanent IP blocks require manual admin review.
                        </div>
                    </div>

                    {{-- User Enumeration Prevention --}}
                    <div class="bg-white border border-gray-200 rounded-xl p-6 shadow-sm mb-8">
                        <h3 class="text-lg font-bold text-blue-600 mb-3">4. User Enumeration Prevention</h3>
                        <p class="text-sm text-gray-600 mb-4">The forgot-password endpoint returns an identical generic message regardless of whether the email is registered. This prevents attackers from probing which accounts exist:</p>
                        <div class="bg-gray-900 rounded-lg p-4 text-gray-300 font-mono text-xs">
                            <pre><code>// Both "found" and "not found" paths return:
"If that email is registered, you will receive a reset link shortly.
 Please check your inbox (and spam folder)."</code></pre>
                        </div>
                    </div>

                    {{-- File Upload Security --}}
                    <div class="bg-white border border-gray-200 rounded-xl p-6 shadow-sm mb-8">
                        <h3 class="text-lg font-bold text-blue-600 mb-3">5. File Upload Security</h3>
                        <ul class="text-sm text-gray-700 space-y-2 list-disc list-inside">
                            <li><b>MIME validation:</b> Post/page featured images are validated against an explicit allowlist (<code>image/jpeg</code>, <code>image/png</code>, <code>image/gif</code>, <code>image/webp</code>, <code>image/svg+xml</code>). Mismatched MIME types are rejected.</li>
                            <li><b>ZIP path traversal (theme upload):</b> All entries inside an uploaded theme ZIP are checked via <code>basename()</code> before extraction. Any entry with a path component outside the target directory is skipped.</li>
                            <li><b>Extension filtering:</b> The media library enforces an allowlist of safe extensions server-side (not just client-side).</li>
                        </ul>
                    </div>

                    {{-- Input Validation --}}
                    <div class="bg-white border border-gray-200 rounded-xl p-6 shadow-sm mb-8">
                        <h3 class="text-lg font-bold text-blue-600 mb-3">6. Input Validation Hardening</h3>
                        <ul class="text-sm text-gray-700 space-y-2 list-disc list-inside">
                            <li><b>Comment length:</b> Frontend comment body is capped at <b>3,000 characters</b> server-side (<code>max:3000</code>) to prevent large payload abuse.</li>
                            <li><b>Password on user update:</b> The password field is <b>optional</b> when editing an existing user — the current password is preserved if left blank, preventing accidental lockouts.</li>
                            <li><b>Builder permission checks:</b> <code>FalconBuilderController</code> enforces <code>manage_pages</code> or <code>manage_posts</code> permissions before any save/clone/delete operation.</li>
                        </ul>
                    </div>

                    {{-- Maintenance Mode sub-section --}}
                    <section id="maintenance-mode">
                        <h3 class="text-xl font-bold text-gray-900 mb-4 mt-2">Maintenance Mode</h3>
                        <p class="text-gray-700 mb-6">Put the public frontend into maintenance mode without touching code. Logged-in admins always bypass the maintenance page automatically.</p>

                        <div class="bg-white border border-gray-200 rounded-xl p-6 shadow-sm mb-6">
                            <h4 class="text-lg font-bold text-blue-600 mb-3">Enabling / Configuring</h4>
                            <ol class="text-sm text-gray-700 list-decimal list-inside space-y-2">
                                <li>Go to <b>Appearance → Customizer → Performance</b>.</li>
                                <li>Toggle <b>Enable Maintenance Mode</b>.</li>
                                <li>Optionally set a custom <b>Maintenance Message</b> and a <b>Coming Soon date</b> for a countdown timer.</li>
                                <li>Save — the public site immediately shows the maintenance page.</li>
                            </ol>
                        </div>

                        <div class="bg-white border border-gray-200 rounded-xl p-6 shadow-sm mb-6">
                            <h4 class="text-lg font-bold text-blue-600 mb-3">Bypass</h4>
                            <ul class="text-sm text-gray-700 space-y-2 list-disc list-inside">
                                <li><b>Logged-in admins</b> always see the real site (bypass is automatic).</li>
                                <li><b>Preview bypass</b> — append <code>?bypass_maintenance=true</code> to any URL while logged in as admin to force the real page.</li>
                                <li>The admin panel (<code>/admin/*</code>) is never affected by maintenance mode.</li>
                            </ul>
                        </div>

                        <div class="bg-white border border-gray-200 rounded-xl p-6 shadow-sm mb-6">
                            <h4 class="text-lg font-bold text-blue-600 mb-3">Customizer Options</h4>
                            <div class="overflow-x-auto">
                                <table class="w-full text-xs text-left border border-gray-100 rounded-lg overflow-hidden">
                                    <thead class="bg-gray-50 text-gray-500 uppercase">
                                        <tr><th class="px-3 py-2">Option Key</th><th class="px-3 py-2">Default</th><th class="px-3 py-2">Description</th></tr>
                                    </thead>
                                    <tbody class="divide-y divide-gray-100">
                                        <tr><td class="px-3 py-2 font-mono text-blue-600">maintenance_mode</td><td class="px-3 py-2">0</td><td class="px-3 py-2">Toggle — <code>1</code> = maintenance mode on</td></tr>
                                        <tr><td class="px-3 py-2 font-mono text-blue-600">maintenance_message</td><td class="px-3 py-2"><em>We'll be back soon…</em></td><td class="px-3 py-2">Message shown on the maintenance page</td></tr>
                                        <tr><td class="px-3 py-2 font-mono text-blue-600">maintenance_coming_soon_date</td><td class="px-3 py-2">—</td><td class="px-3 py-2">ISO date/time — enables a countdown timer</td></tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>

                        <div class="bg-white border border-gray-200 rounded-xl p-6 shadow-sm">
                            <h4 class="text-lg font-bold text-blue-600 mb-3">Header Side Padding (Customizer → Header)</h4>
                            <p class="text-sm text-gray-600 mb-4">The <b>Header Side Padding</b> option (<code>theme_header_side_padding</code>) controls the left/right inner padding of the <code>.main-header</code> and <code>.main-footer</code> containers. It defaults to <code>0px</code> so both containers align perfectly with full-width builder sections.</p>
                            <div class="overflow-x-auto mb-4">
                                <table class="w-full text-xs text-left border border-gray-100 rounded-lg overflow-hidden">
                                    <thead class="bg-gray-50 text-gray-500 uppercase">
                                        <tr><th class="px-3 py-2">Option Key</th><th class="px-3 py-2">Default</th><th class="px-3 py-2">Description</th></tr>
                                    </thead>
                                    <tbody class="divide-y divide-gray-100">
                                        <tr><td class="px-3 py-2 font-mono text-blue-600">theme_header_side_padding</td><td class="px-3 py-2">0px</td><td class="px-3 py-2">Left/right padding applied to <code>.main-header .container-custom</code> and <code>.main-footer .container-custom</code></td></tr>
                                    </tbody>
                                </table>
                            </div>
                            <div class="bg-blue-50 border-l-4 border-blue-400 p-3 text-xs text-blue-700">
                                On screens narrower than 640 px, a <code>max(16px, setting)</code> rule ensures at least 16 px of breathing room even when the setting is <code>0px</code>, preventing the logo from touching the viewport edge.
                            </div>
                        </div>
                    </section>
                </section>

                {{-- Section: Theme Isolation --}}
                <section id="theme-isolation">
                    <h2 class="text-2xl font-bold text-gray-900 mb-4">Theme Isolation & Sync</h2>
                    <p class="text-gray-700 mb-6">To maintain a clean and secure structure, FalconCMS enforces strict theme-only view rendering for the frontend.</p>
                    
                    <div class="space-y-6">
                        <div class="bg-amber-50 border border-amber-100 p-4 rounded-xl text-sm text-amber-800">
                            <b>Strict Rule:</b> All frontend view files must be inside <code>resources/views/themes/{theme-name}/</code>. Views placed directly in the root <code>resources/views/</code> folder will return a 404 error for frontend requests.
                        </div>

                        <div class="bg-white border border-gray-200 rounded-xl p-6 shadow-sm">
                            <h3 class="font-bold text-gray-800 mb-3">Automated Theme Refresh</h3>
                            <p class="text-sm text-gray-600 mb-4">When you update the package, use the sync command to ensure your local themes are updated with any core improvements.</p>
                            <div class="bg-gray-900 rounded-lg p-4 text-gray-300 font-mono text-xs">
                                <code class="text-green-400">php artisan lazy:update</code>
                            </div>
                        </div>
                    </div>
                </section>
            </div>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const navLinks = document.querySelectorAll('.nav-link');
            const sections = document.querySelectorAll('section[id]');

            // 1. Smooth Scroll on Click
            navLinks.forEach(link => {
                link.addEventListener('click', function(e) {
                    e.preventDefault();
                    const id = this.getAttribute('href');
                    document.querySelector(id).scrollIntoView({
                        behavior: 'smooth'
                    });
                    
                    // Update URL hash without jumping
                    history.pushState(null, null, id);
                });
            });

            // 2. Intersection Observer for Scrollspy
            const options = {
                rootMargin: '-20% 0px -70% 0px',
                threshold: 0
            };

            const observer = new IntersectionObserver(entries => {
                entries.forEach(entry => {
                    if (entry.isIntersecting) {
                        const id = '#' + entry.target.getAttribute('id');
                        
                        navLinks.forEach(link => {
                            link.classList.remove('text-blue-600', 'bg-blue-50');
                            link.classList.add('text-gray-600');

                            if (link.getAttribute('href') === id) {
                                link.classList.add('text-blue-600', 'bg-blue-50');
                                link.classList.remove('text-gray-600');
                            }
                        });
                    }
                });
            }, options);

            sections.forEach(section => observer.observe(section));

            // 3. One-click Copy buttons on all code blocks
            const copyText = (text) => {
                if (navigator.clipboard && window.isSecureContext) {
                    return navigator.clipboard.writeText(text);
                }
                // Fallback for non-secure contexts
                return new Promise((resolve, reject) => {
                    const ta = document.createElement('textarea');
                    ta.value = text;
                    ta.style.position = 'fixed';
                    ta.style.opacity = '0';
                    document.body.appendChild(ta);
                    ta.focus(); ta.select();
                    try { document.execCommand('copy'); resolve(); } catch (e) { reject(e); }
                    document.body.removeChild(ta);
                });
            };

            document.querySelectorAll('pre').forEach(pre => {
                const container = pre.closest('.bg-gray-900, .bg-gray-100, .bg-gray-50') || pre;
                if (container.querySelector('.doc-copy-btn')) return; // avoid duplicates
                if (getComputedStyle(container).position === 'static') container.style.position = 'relative';

                const btn = document.createElement('button');
                btn.type = 'button';
                btn.className = 'doc-copy-btn';
                btn.textContent = 'Copy';
                btn.addEventListener('click', () => {
                    const text = pre.innerText.replace(/\s+$/,'');
                    copyText(text).then(() => {
                        btn.textContent = '✓ Copied';
                        btn.classList.add('copied');
                        setTimeout(() => { btn.textContent = 'Copy'; btn.classList.remove('copied'); }, 1500);
                    }).catch(() => {
                        btn.textContent = 'Failed';
                        setTimeout(() => { btn.textContent = 'Copy'; }, 1500);
                    });
                });
                container.appendChild(btn);
            });
        });
    </script>

    <style>
        .doc-copy-btn {
            position: absolute;
            top: 8px;
            right: 8px;
            z-index: 5;
            font-size: 11px;
            font-weight: 600;
            line-height: 1;
            padding: 5px 10px;
            border-radius: 6px;
            color: #cbd5e1;
            background: rgba(255,255,255,0.08);
            border: 1px solid rgba(255,255,255,0.15);
            cursor: pointer;
            opacity: 0;
            transition: all .15s ease;
        }
        .bg-gray-900:hover .doc-copy-btn,
        .bg-gray-100:hover .doc-copy-btn,
        .bg-gray-50:hover .doc-copy-btn,
        pre:hover .doc-copy-btn { opacity: 1; }
        .doc-copy-btn:hover { background: #2563eb; color: #fff; border-color: #2563eb; }
        .doc-copy-btn.copied { background: #16a34a; color: #fff; border-color: #16a34a; opacity: 1; }
        /* light-background blocks need a darker button */
        .bg-gray-100 .doc-copy-btn,
        .bg-gray-50 .doc-copy-btn { color: #475569; background: rgba(0,0,0,0.05); border-color: rgba(0,0,0,0.1); }
    </style>
</x-falcon-cms::layouts.admin>
