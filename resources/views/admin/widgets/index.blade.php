<x-falcon-cms::layouts.admin title="Manage Widgets">
    <x-falcon-cms::admin.delete-modal />
@php
    $hasBuilderFooter = \FalconCms\Core\Models\Post::where('type', 'falcon_footer')->where('status', 'published')->exists();
    $hasBuilderHeader = \FalconCms\Core\Models\Post::where('type', 'falcon_header')->where('status', 'published')->exists();
@endphp
<div class="max-w-[1400px] mx-auto px-6 py-8">
    <div class="flex items-center justify-between mb-8">
        <div>
            <h1 class="text-2xl font-bold text-slate-900">Widgets</h1>
            <p class="text-slate-500 text-sm mt-1">Drag and add widgets to your theme areas.</p>
        </div>
    </div>

    {{-- Footer Builder status notice --}}
    @if($hasBuilderFooter)
    <div class="mb-6 flex items-start gap-3 bg-amber-50 border border-amber-200 rounded-xl px-5 py-4">
        <span class="material-symbols-outlined text-amber-500 text-[22px] mt-0.5 shrink-0">info</span>
        <div>
            <p class="text-[13px] font-bold text-amber-800">Footer Builder is active</p>
            <p class="text-[12px] text-amber-700 mt-0.5">
                A published <strong>Footer Builder</strong> page is overriding the default footer.
                Footer column widgets (1–4) will <strong>not appear</strong> on the frontend until the Footer Builder is unpublished or deleted.
                <a href="{{ route('admin.posts.index', ['type' => 'falcon_footer']) }}" class="underline hover:text-amber-900">Manage Footer Builder →</a>
            </p>
        </div>
    </div>
    @else
    <div class="mb-6 flex items-start gap-3 bg-green-50 border border-green-200 rounded-xl px-5 py-4">
        <span class="material-symbols-outlined text-green-500 text-[22px] mt-0.5 shrink-0">check_circle</span>
        <div>
            <p class="text-[13px] font-bold text-green-800">Default footer is active</p>
            <p class="text-[12px] text-green-700 mt-0.5">No Footer Builder is published. Footer column widgets (1–4) are <strong>live on the frontend</strong>.</p>
        </div>
    </div>
    @endif

    <div class="grid grid-cols-1 lg:grid-cols-12 gap-8">
        <!-- Available Widgets -->
        <div class="lg:col-span-4">
            <div class="bg-white rounded-xl border border-slate-200 shadow-sm">
                <div class="px-5 py-4 border-b border-slate-100 bg-slate-50/50">
                    <h3 class="font-bold text-slate-800">Available Widgets</h3>
                </div>
                <div class="p-5 space-y-4">
                    @foreach($availableWidgets as $type => $info)
                        <div class="p-4 border border-slate-200 rounded-lg hover:border-primary/30 hover:bg-slate-50 transition-all group">
                            <div class="flex items-center justify-between mb-2">
                                <h4 class="font-bold text-slate-900">{{ $info['name'] }}</h4>
                                <div class="dropdown relative">
                                    <button class="p-1 text-slate-400 hover:text-primary transition-colors">
                                        <span class="material-symbols-outlined text-[20px]">add_circle</span>
                                    </button>
                                    <div class="dropdown-menu hidden absolute right-0 top-full w-48 bg-white border border-slate-200 shadow-xl rounded-lg py-2 z-50">
                                        @foreach($widgetAreas as $areaKey => $areaName)
                                            <form action="{{ route('admin.widgets.store') }}" method="POST">
                                                @csrf
                                                <input type="hidden" name="type" value="{{ $type }}">
                                                <input type="hidden" name="area" value="{{ $areaKey }}">
                                                <button type="submit" class="w-full text-left px-4 py-2 text-sm text-slate-600 hover:bg-slate-50 hover:text-primary transition-colors">
                                                    Add to {{ $areaName }}
                                                </button>
                                            </form>
                                        @endforeach
                                    </div>
                                </div>
                            </div>
                            <p class="text-xs text-slate-500 leading-relaxed">{{ $info['description'] }}</p>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>

        <!-- Widget Areas -->
        <div class="lg:col-span-8 space-y-6">
            @foreach($widgetAreas as $areaKey => $areaName)
                <div class="bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden">
                    <div class="px-5 py-4 border-b border-slate-100 bg-slate-50/50 flex items-center justify-between">
                        <div class="flex items-center gap-2">
                            <h3 class="font-bold text-slate-800">{{ $areaName }}</h3>
                            @if(str_starts_with($areaKey, 'footer-'))
                                @if($hasBuilderFooter)
                                    <span class="text-[9px] font-bold uppercase px-2 py-0.5 rounded-full bg-amber-100 text-amber-600">Builder Active</span>
                                @else
                                    <span class="text-[9px] font-bold uppercase px-2 py-0.5 rounded-full bg-green-100 text-green-600">Live</span>
                                @endif
                            @elseif($areaKey === 'primary-sidebar')
                                <span class="text-[9px] font-bold uppercase px-2 py-0.5 rounded-full bg-blue-100 text-blue-600">Blog / Single Posts</span>
                            @endif
                        </div>
                        <span class="text-[10px] font-bold text-slate-400 uppercase tracking-widest">
                            {{ ($activeWidgets[$areaKey] ?? collect())->count() }} Widgets
                        </span>
                    </div>
                    <div class="p-5 space-y-3 min-h-[100px] widget-area" data-area="{{ $areaKey }}">
                        @forelse($activeWidgets[$areaKey] ?? [] as $widget)
                            <div class="widget-item bg-slate-50 border border-slate-200 rounded-lg overflow-hidden transition-all hover:border-slate-300" data-id="{{ $widget->id }}">
                                <div class="px-4 py-3 flex items-center justify-between cursor-move">
                                    <div class="flex items-center gap-3">
                                        <span class="material-symbols-outlined text-slate-300 text-[18px]">drag_indicator</span>
                                        <span class="font-bold text-slate-700 text-sm">{{ $widget->title ?: ucwords(str_replace('_', ' ', $widget->type)) }}</span>
                                        <span class="text-[10px] px-2 py-0.5 bg-slate-200 text-slate-500 rounded font-bold uppercase">
                                            {{ str_replace('_', ' ', $widget->type) }}
                                        </span>
                                    </div>
                                    <div class="flex items-center gap-2">
                                        <button onclick="toggleWidgetSettings({{ $widget->id }})" class="p-1 text-slate-400 hover:text-primary transition-colors">
                                            <span class="material-symbols-outlined text-[18px]">settings</span>
                                        </button>
                                        <form id="delete-widget-{{ $widget->id }}" action="{{ route('admin.widgets.destroy', $widget->id) }}" method="POST">
                                            @csrf
                                            @method('DELETE')
                                            <button type="button" onclick="confirmWidgetDelete({{ $widget->id }})" class="p-1 text-slate-400 hover:text-red-500 transition-colors">
                                                <span class="material-symbols-outlined text-[18px]">delete</span>
                                            </button>
                                        </form>
                                    </div>
                                </div>
                                
                                <!-- Widget Settings Form -->
                                <div id="widget-settings-{{ $widget->id }}" data-widget-type="{{ $widget->type }}" class="hidden px-5 py-5 border-t-2 border-primary/10 bg-slate-50/30">
                                    <form action="{{ route('admin.widgets.update', $widget->id) }}" method="POST" class="space-y-5">
                                        @csrf
                                        @method('PUT')
                                        <div class="bg-white p-4 rounded-lg border border-slate-200 shadow-sm space-y-4">
                                            <div>
                                                <label class="block text-[11px] font-bold text-slate-400 uppercase mb-1.5">Widget Title</label>
                                                <input type="text" name="title" value="{{ $widget->title }}" class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-primary/20 focus:border-primary outline-none transition-all">
                                            </div>

                                            @if($widget->type === 'search')
                                                <div>
                                                    <label class="block text-[11px] font-bold text-slate-400 uppercase mb-1.5">Placeholder Text</label>
                                                    <input type="text" name="settings[placeholder]" value="{{ $widget->settings['placeholder'] ?? 'Search...' }}" class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm">
                                                </div>
                                            @elseif($widget->type === 'recent_posts')
                                                <div>
                                                    <label class="block text-[11px] font-bold text-slate-400 uppercase mb-1.5">Number of posts</label>
                                                    <input type="number" name="settings[limit]" value="{{ $widget->settings['limit'] ?? 5 }}" min="1" max="20" class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm">
                                                </div>
                                                <div>
                                                    <label class="block text-[11px] font-bold text-slate-400 uppercase mb-1.5">Post Type</label>
                                                    <select name="settings[post_type]" class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm">
                                                        <option value="auto" {{ ($widget->settings['post_type'] ?? 'auto') === 'auto' ? 'selected' : '' }}>Auto (Current Page)</option>
                                                        @foreach($allActivePostTypes as $slug => $name)
                                                            <option value="{{ $slug }}" {{ ($widget->settings['post_type'] ?? 'auto') === $slug ? 'selected' : '' }}>
                                                                {{ $name }}
                                                            </option>
                                                        @endforeach
                                                    </select>
                                                </div>
                                            @elseif($widget->type === 'categories')
                                                <div>
                                                    <label class="block text-[11px] font-bold text-slate-400 uppercase mb-1.5">Post Type</label>
                                                    <select name="settings[post_type]" class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm">
                                                        <option value="auto" {{ ($widget->settings['post_type'] ?? 'auto') === 'auto' ? 'selected' : '' }}>Auto (Current Page)</option>
                                                        @foreach($postTypesWithCategories as $slug => $name)
                                                            <option value="{{ $slug }}" {{ ($widget->settings['post_type'] ?? 'auto') === $slug ? 'selected' : '' }}>
                                                                {{ $name }}
                                                            </option>
                                                        @endforeach
                                                    </select>
                                                </div>
                                                <div>
                                                    <label class="block text-[11px] font-bold text-slate-400 uppercase mb-1.5">Show post count</label>
                                                    <select name="settings[show_count]" class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm">
                                                        <option value="1" {{ ($widget->settings['show_count'] ?? '1') === '1' ? 'selected' : '' }}>Yes</option>
                                                        <option value="0" {{ ($widget->settings['show_count'] ?? '1') === '0' ? 'selected' : '' }}>No</option>
                                                    </select>
                                                </div>
                                            @elseif($widget->type === 'nav_menu')
                                                <div>
                                                    <label class="block text-[11px] font-bold text-slate-400 uppercase mb-1.5">Select Menu</label>
                                                    <select name="settings[menu_id]" class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm">
                                                        <option value="">— Select a Menu —</option>
                                                        @foreach($menus as $menu)
                                                            <option value="{{ $menu->id }}" {{ ($widget->settings['menu_id'] ?? '') == $menu->id ? 'selected' : '' }}>{{ $menu->name }}</option>
                                                        @endforeach
                                                    </select>
                                                </div>

                                            @elseif($widget->type === 'image')
                                                {{-- Image picker using the global media modal --}}
                                                @php $wpid = 'widget_img_' . $widget->id; $wpInitial = $widget->settings['image_url'] ?? ''; @endphp
                                                <div>
                                                    <label class="block text-[11px] font-bold text-slate-400 uppercase mb-1.5">Image</label>
                                                    <div x-data="{ imgUrl: '{{ $wpInitial }}' }" class="space-y-2">
                                                        {{-- Preview / pick area --}}
                                                        <div @click="window.openMediaModal(function(a){ let u=a.full_url||a.url||a.path||''; if(u.startsWith('media/'))u='/storage/'+u; imgUrl=u; document.getElementById('{{ $wpid }}').value=u; })"
                                                             class="relative w-full h-36 rounded-xl border-2 border-dashed cursor-pointer overflow-hidden flex items-center justify-center transition-all hover:border-primary"
                                                             :class="imgUrl ? 'border-slate-200 bg-slate-50' : 'border-slate-300 bg-slate-50 hover:bg-primary/5'">
                                                            <template x-if="imgUrl">
                                                                <img :src="imgUrl" class="w-full h-full object-contain p-1">
                                                            </template>
                                                            <template x-if="!imgUrl">
                                                                <div class="text-center text-slate-400">
                                                                    <i data-lucide="image-plus" class="w-8 h-8 mx-auto mb-1 opacity-40"></i>
                                                                    <span class="text-[11px] font-bold uppercase tracking-wide">Click to Upload / Select</span>
                                                                </div>
                                                            </template>
                                                        </div>
                                                        {{-- Actions --}}
                                                        <div class="flex items-center gap-3">
                                                            <button type="button"
                                                                @click="window.openMediaModal(function(a){ let u=a.full_url||a.url||a.path||''; if(u.startsWith('media/'))u='/storage/'+u; imgUrl=u; document.getElementById('{{ $wpid }}').value=u; })"
                                                                class="text-[11px] font-bold text-primary hover:text-primary/80 uppercase tracking-wide flex items-center gap-1">
                                                                <i data-lucide="image" class="w-3.5 h-3.5"></i> Select Image
                                                            </button>
                                                            <template x-if="imgUrl">
                                                                <button type="button" @click="imgUrl=''; document.getElementById('{{ $wpid }}').value=''"
                                                                    class="text-[11px] font-bold text-red-500 hover:text-red-700 uppercase tracking-wide flex items-center gap-1">
                                                                    <i data-lucide="x" class="w-3.5 h-3.5"></i> Remove
                                                                </button>
                                                            </template>
                                                        </div>
                                                        <input type="hidden" name="settings[image_url]" id="{{ $wpid }}" :value="imgUrl">
                                                    </div>
                                                </div>
                                                <div>
                                                    <label class="block text-[11px] font-bold text-slate-400 uppercase mb-1.5">Link URL <span class="text-slate-300 normal-case font-normal">(optional)</span></label>
                                                    <input type="text" name="settings[link_url]" value="{{ $widget->settings['link_url'] ?? '' }}" placeholder="https://..." class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm">
                                                </div>
                                                <div class="grid grid-cols-2 gap-3">
                                                    <div>
                                                        <label class="block text-[11px] font-bold text-slate-400 uppercase mb-1.5">Open Link In</label>
                                                        <select name="settings[link_target]" class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm">
                                                            <option value="_self"  {{ ($widget->settings['link_target'] ?? '_self') === '_self'  ? 'selected' : '' }}>Same Tab</option>
                                                            <option value="_blank" {{ ($widget->settings['link_target'] ?? '_self') === '_blank' ? 'selected' : '' }}>New Tab</option>
                                                        </select>
                                                    </div>
                                                    <div>
                                                        <label class="block text-[11px] font-bold text-slate-400 uppercase mb-1.5">Alt Text</label>
                                                        <input type="text" name="settings[alt_text]" value="{{ $widget->settings['alt_text'] ?? '' }}" placeholder="Image description..." class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm">
                                                    </div>
                                                </div>
                                                <div>
                                                    <label class="block text-[11px] font-bold text-slate-400 uppercase mb-1.5">Caption <span class="text-slate-300 normal-case font-normal">(optional)</span></label>
                                                    <input type="text" name="settings[caption]" value="{{ $widget->settings['caption'] ?? '' }}" placeholder="Image caption..." class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm">
                                                </div>

                                            @elseif($widget->type === 'text')
                                                <div>
                                                    <label class="block text-[11px] font-bold text-slate-400 uppercase mb-1.5">Content</label>
                                                    <textarea id="tinymce-widget-{{ $widget->id }}" name="settings[content]" rows="8" class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm">{{ $widget->settings['content'] ?? '' }}</textarea>
                                                </div>
                                            @elseif($widget->type === 'custom_html')
                                                <div>
                                                    <label class="block text-[11px] font-bold text-slate-400 uppercase mb-1.5">HTML Content</label>
                                                    <textarea name="settings[content]" rows="5" class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm font-mono">{{ $widget->settings['content'] ?? '' }}</textarea>
                                                </div>
                                            @elseif($widget->type === 'social_media')
                                                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                                    @foreach([
                                                        'facebook'  => 'Facebook URL',
                                                        'twitter'   => 'Twitter / X URL',
                                                        'instagram' => 'Instagram URL',
                                                        'linkedin'  => 'LinkedIn URL',
                                                        'youtube'   => 'YouTube URL',
                                                        'github'    => 'GitHub URL',
                                                        'tiktok'    => 'TikTok URL',
                                                        'whatsapp'  => 'WhatsApp URL',
                                                    ] as $key => $label)
                                                    <div>
                                                        <label class="block text-[11px] font-bold text-slate-400 uppercase mb-1.5">{{ $label }}</label>
                                                        <input type="text" name="settings[{{ $key }}]" value="{{ $widget->settings[$key] ?? get_cms_option('theme_social_' . $key) }}" class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm" placeholder="https://...">
                                                    </div>
                                                    @endforeach
                                                </div>
                                            @endif
                                        </div>

                                        <div class="flex items-center justify-between pt-2">
                                            <label class="flex items-center gap-2 cursor-pointer group">
                                                <input type="checkbox" name="is_active" value="1" {{ $widget->is_active ? 'checked' : '' }} class="w-4 h-4 rounded border-slate-300 text-primary focus:ring-primary transition-all">
                                                <span class="text-xs font-bold text-slate-500 group-hover:text-slate-700 uppercase tracking-wider">Active Status</span>
                                            </label>
                                            <div class="flex gap-3">
                                                <button type="button" onclick="toggleWidgetSettings({{ $widget->id }})" class="px-5 py-2 rounded-lg text-xs font-bold text-slate-500 hover:bg-slate-200 transition-all">
                                                    Cancel
                                                </button>
                                                <button type="button" onclick="saveWidgetSettings({{ $widget->id }})" id="save-btn-{{ $widget->id }}" class="px-8 py-2 rounded-lg text-xs font-black uppercase tracking-widest hover:shadow-lg transition-all flex items-center gap-2" style="background-color: #1d4ed8 !important; color: white !important;">
                                                    <span class="save-text">Save Widget</span>
                                                    <span class="save-loader hidden">
                                                        <svg class="animate-spin h-3.5 w-3.5 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>
                                                    </span>
                                                </button>
                                            </div>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        @empty
                            <div class="py-10 text-center border-2 border-dashed border-slate-100 rounded-lg">
                                <p class="text-slate-400 text-sm italic">No widgets in this area.</p>
                            </div>
                        @endforelse
                    </div>
                </div>
            @endforeach
        </div>
    </div>
</div>

<style>
    .dropdown:hover .dropdown-menu { display: block; }
    /* Bridge the gap between button and menu */
    .dropdown-menu::before {
        content: '';
        position: absolute;
        top: -10px;
        left: 0;
        right: 0;
        height: 10px;
    }

    /* Toast Styles */
    .toast-container {
        position: fixed;
        top: 2rem;
        right: 2rem;
        z-index: 9999;
    }
    .toast-message {
        background: #1d2327;
        color: white;
        padding: 0.75rem 1.5rem;
        border-radius: 0.5rem;
        box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1);
        margin-bottom: 0.75rem;
        display: flex;
        items-center;
        gap: 0.75rem;
        animation: toast-in 0.3s ease-out;
    }
    @keyframes toast-in {
        from { transform: translateX(100%); opacity: 0; }
        to { transform: translateX(0); opacity: 1; }
    }
</style>

<div class="toast-container" id="toast-container"></div>

<script src="{{ asset('vendor/cms-dashboard/js/sortable.min.js') }}"></script>
<script src="{{ asset('vendor/cms-dashboard/js/tinymce.min.js') }}"></script>
<script>if(window.tinymce) tinymce.baseURL='https://cdnjs.cloudflare.com/ajax/libs/tinymce/6.8.3';</script>
<script>
    function showToast(message, type = 'success') {
        const container = document.getElementById('toast-container');
        const toast = document.createElement('div');
        toast.className = 'toast-message';
        toast.innerHTML = `
            <span class="material-symbols-outlined text-green-400 text-[20px]">check_circle</span>
            <span class="text-sm font-medium">${message}</span>
        `;
        container.appendChild(toast);
        setTimeout(() => {
            toast.style.opacity = '0';
            toast.style.transform = 'translateX(100%)';
            toast.style.transition = 'all 0.3s ease';
            setTimeout(() => toast.remove(), 300);
        }, 3000);
    }

    // Check for Laravel session success message
    @if(session('success'))
        window.addEventListener('DOMContentLoaded', () => showToast("{{ session('success') }}"));
    @endif

    function toggleWidgetSettings(id) {
        const el = document.getElementById(`widget-settings-${id}`);
        const opening = el.classList.contains('hidden');
        el.classList.toggle('hidden');

        if (opening && el.dataset.widgetType === 'text' && window.tinymce) {
            const editorId = `tinymce-widget-${id}`;
            if (!tinymce.get(editorId)) {
                tinymce.init({
                    selector: `#${editorId}`,
                    menubar: false,
                    height: 280,
                    plugins: ['lists', 'link', 'code'],
                    toolbar: 'formatselect | bold italic underline | bullist numlist | link | code',
                    content_style: 'body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif; font-size:14px; padding:12px; }',
                    branding: false,
                });
            }
        }
    }

    async function saveWidgetSettings(id) {
        const form = document.getElementById(`widget-settings-${id}`).querySelector('form');
        const btn = document.getElementById(`save-btn-${id}`);
        const text = btn.querySelector('.save-text');
        const loader = btn.querySelector('.save-loader');
        
        // Show loading
        btn.disabled = true;
        text.innerText = 'Saving...';
        loader.classList.remove('hidden');

        // Sync TinyMCE content to its textarea before serializing
        if (window.tinymce) {
            const editor = tinymce.get(`tinymce-widget-${id}`);
            if (editor) editor.save();
        }

        const formData = new FormData(form);
        const data = {};
        formData.forEach((value, key) => {
            if (key.includes('[')) {
                const parts = key.split('[');
                const mainKey = parts[0];
                const subKey = parts[1].replace(']', '');
                if (!data[mainKey]) data[mainKey] = {};
                data[mainKey][subKey] = value;
            } else {
                data[key] = value;
            }
        });
        // FormData skips unchecked checkboxes — explicitly capture is_active
        const isActiveCheckbox = form.querySelector('input[name="is_active"][type="checkbox"]');
        if (isActiveCheckbox) data.is_active = isActiveCheckbox.checked ? 1 : 0;

        try {
            const baseUrl = '{{ url("admin/widgets") }}';
            const response = await fetch(`${baseUrl}/${id}`, {
                method: 'POST',
                headers: {
                    'Accept': 'application/json',
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': '{{ csrf_token() }}',
                    'X-HTTP-Method-Override': 'PUT'
                },
                body: JSON.stringify(data)
            });

            const result = await response.json();
            
            if (response.ok) {
                showToast('Settings saved successfully!');
                // Update title label if changed
                if (data.title) {
                    const titleLabel = form.closest('.widget-item').querySelector('.font-bold.text-slate-700');
                    titleLabel.innerText = data.title;
                }
            } else {
                showToast('Error saving settings', 'error');
            }
        } catch (error) {
            console.error(error);
            showToast('Something went wrong', 'error');
        } finally {
            btn.disabled = false;
            text.innerText = 'Save Changes';
            loader.classList.add('hidden');
        }
    }

    document.addEventListener('DOMContentLoaded', function() {
        const areas = document.querySelectorAll('.widget-area');
        
        areas.forEach(area => {
            new Sortable(area, {
                group: 'widgets',
                animation: 150,
                ghostClass: 'bg-primary/10',
                handle: '.cursor-move',
                onEnd: function() {
                    saveWidgetOrder();
                }
            });
        });

        function saveWidgetOrder() {
            const data = [];
            document.querySelectorAll('.widget-area').forEach(area => {
                const areaKey = area.dataset.area;
                area.querySelectorAll('.widget-item').forEach((item, index) => {
                    data.push({
                        id: item.dataset.id,
                        area: areaKey,
                        order: index + 1
                    });
                });
            });

            fetch('{{ route("admin.widgets.update-order") }}', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': '{{ csrf_token() }}'
                },
                body: JSON.stringify({ widgets: data })
            })
            .then(response => response.json())
            .then(data => {
                if(data.status === 'success') {
                    showToast('Widget order updated successfully!');
                }
            });
        }

        window.confirmWidgetDelete = async function(id) {
            const confirmed = await window.lazyConfirm({
                title: 'Remove Widget',
                message: 'Are you sure you want to remove this widget from the area? This action cannot be undone.',
                confirmText: 'Remove Widget',
                isDanger: true
            });

            if (confirmed) {
                document.getElementById(`delete-widget-${id}`).submit();
            }
        };
    });
</script>
</x-falcon-cms::layouts.admin>
