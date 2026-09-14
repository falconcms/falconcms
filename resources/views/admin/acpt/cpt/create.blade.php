<x-falcon-cms::layouts.admin title="Add New Post Type">
    <div class="max-w-[1280px] mx-auto pb-12" x-data="cptForm()">
        <form action="{{ route('admin.acpt.cpt.store') }}" method="POST">
            @csrf

            <!-- Header -->
            <div class="flex items-center justify-between mb-4 mt-2">
                <h1 class="text-[22px] font-normal text-[#1d2327]">Add New Post Type</h1>
                <button type="submit" class="bg-[#2271b1] hover:bg-[#135e96] text-white px-3 py-[4px] text-[13px] rounded-[3px] border border-[#2271b1]">Save Changes</button>
            </div>

            <!-- Main Box -->
            <div class="bg-white border border-[#c3c4c7] shadow-[0_1px_1px_rgba(0,0,0,0.04)] rounded-[4px] mb-6 p-6 space-y-6">
                <!-- Plural Label -->
                <div class="grid grid-cols-[200px_1fr] items-start">
                    <label class="text-[13px] font-semibold text-[#2c3338] pt-1">Plural Label <span class="text-[#d63638]">*</span></label>
                    <div class="w-full max-w-[400px]">
                        <input type="text" name="plural_label" x-model="pluralLabel" @input="updateKey" required class="w-full border-[#8c8f94] focus:border-[#2271b1] border py-1.5 px-3 rounded-[3px] shadow-[inset_0_1px_2px_rgba(0,0,0,0.07)] text-[14px]">
                    </div>
                </div>

                <!-- Singular Label -->
                <div class="grid grid-cols-[200px_1fr] items-start">
                    <label class="text-[13px] font-semibold text-[#2c3338] pt-1">Singular Label <span class="text-[#d63638]">*</span></label>
                    <div class="w-full max-w-[400px]">
                        <input type="text" name="singular_label" x-model="singularLabel" required class="w-full border-[#8c8f94] focus:border-[#2271b1] border py-1.5 px-3 rounded-[3px] shadow-[inset_0_1px_2px_rgba(0,0,0,0.07)] text-[14px]">
                    </div>
                </div>

                <!-- Post Type Key -->
                <div class="grid grid-cols-[200px_1fr] items-start">
                    <label class="text-[13px] font-semibold text-[#2c3338] pt-1">Post Type Key <span class="text-[#d63638]">*</span></label>
                    <div class="w-full max-w-[400px]">
                        <input type="text" name="post_type_key" x-model="postTypeKey" required class="w-full border-[#8c8f94] focus:border-[#2271b1] border py-1.5 px-3 rounded-[3px] shadow-[inset_0_1px_2px_rgba(0,0,0,0.07)] text-[14px]">
                        <p class="text-[12px] text-[#646970] mt-1">Lower case letters, underscores and dashes only, Max 20 characters.</p>
                    </div>
                </div>

                <!-- Advanced Configuration Toggle -->
                <div class="grid grid-cols-[200px_1fr] items-start mt-6">
                    <div></div>
                    <div class="flex items-start">
                        <button type="button" @click="advanced = !advanced" 
                            :class="advanced ? 'bg-[#0073aa]' : 'bg-[#c3c4c7]'" 
                            class="relative inline-flex h-5 w-9 flex-shrink-0 cursor-pointer rounded-full border-2 border-transparent transition-colors duration-200 ease-in-out mt-1">
                            <span :class="advanced ? 'translate-x-4' : 'translate-x-0'" class="pointer-events-none inline-block h-4 w-4 transform rounded-full bg-white shadow ring-0 transition duration-200 ease-in-out"></span>
                        </button>
                        <div class="ml-3">
                            <span class="text-[13px] font-semibold text-[#2c3338] block cursor-pointer" @click="advanced = !advanced">Advanced Configuration</span>
                            <span class="text-[12px] text-[#646970]">I know what I'm doing, show me all the options.</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Advanced Settings Box -->
            <div x-show="advanced" x-cloak class="bg-white border border-[#c3c4c7] shadow-[0_1px_1px_rgba(0,0,0,0.04)] rounded-[4px] mb-6">
                
                <div class="flex items-center text-[#2c3338] px-4 py-3 font-semibold text-[14px]">
                    <span class="mr-2 border border-[#8c8f94] rounded text-[10px] px-1 py-0.5 inline-block -mt-0.5">⚙</span> 
                    Advanced Settings
                </div>

                <!-- Tabs -->
                <div class="border-b border-t border-[#f0f0f1] bg-[#f6f7f7] px-4 flex text-[13px] font-medium text-[#c3c4c7]">
                    <button type="button" @click="tab = 'fields'" :class="tab === 'fields' ? 'text-[#2271b1] border-b-2 border-[#2271b1]' : ''" class="py-3 px-4">CPT Fields</button>
                    <button type="button" @click="tab = 'icons'" :class="tab === 'icons' ? 'text-[#2271b1] border-b-2 border-[#2271b1]' : ''" class="py-3 px-4">Icons</button>
                </div>

                <!-- CPT Fields Tab Content -->
                <div x-show="tab === 'fields'" class="p-6 space-y-6">
                    <!-- Supports -->
                    <div class="grid grid-cols-[200px_1fr] items-start">
                        <div>
                            <label class="text-[13px] font-semibold text-[#2c3338] block">Add Fields</label>
                            <p class="text-[12px] text-[#646970] mt-1">Add any fields to the CPT</p>
                        </div>
                        <div>
                            <div class="grid grid-cols-3 gap-y-3 gap-x-6 text-[13px] text-[#2c3338] max-w-[600px]">
                                <label class="flex items-center"><input type="checkbox" name="supports[]" value="title" class="mr-2 border-[#8c8f94] rounded-[2px]" checked> Title</label>
                                <label class="flex items-center"><input type="checkbox" name="supports[]" value="editor" class="mr-2 border-[#8c8f94] rounded-[2px]"> Editor</label>
                                <label class="flex items-center"><input type="checkbox" name="supports[]" value="excerpt" class="mr-2 border-[#8c8f94] rounded-[2px]"> Excerpt</label>
                                <label class="flex items-center"><input type="checkbox" name="supports[]" value="featured_image" class="mr-2 border-[#8c8f94] rounded-[2px]"> Featured Image</label>
                            </div>
                        </div>
                    </div>

                    <!-- Menu Visibility -->
                    <div class="grid grid-cols-[200px_1fr] items-start">
                        <div>
                            <label class="text-[13px] font-semibold text-[#2c3338] block">Menu Visibility</label>
                            <p class="text-[12px] text-[#646970] mt-1">Should this CPT appear in the menu?</p>
                        </div>
                        <div>
                            <label class="flex items-center text-[13px] text-[#2c3338]">
                                <input type="checkbox" name="show_in_menu" value="1" class="mr-2 border-[#8c8f94] rounded-[2px]" checked>
                                Show on menu?
                            </label>
                        </div>
                    </div>

                    <!-- Menu Position -->
                    <div class="grid grid-cols-[200px_1fr] items-start">
                        <div>
                            <label class="text-[13px] font-semibold text-[#2c3338] block">Menu Position</label>
                            <p class="text-[12px] text-[#646970] mt-1">Where this post type sits in the sidebar</p>
                        </div>
                        <div class="w-full max-w-[400px]">
                            <select name="menu_after" class="w-full border-[#8c8f94] focus:border-[#2271b1] border py-1.5 px-2 rounded-[3px] text-[13px] bg-white">
                                <option value="">Bottom of the menu</option>
                                @foreach($menuAnchors as $anchorId => $anchorLabel)
                                    <option value="{{ $anchorId }}" @selected(old('menu_after') == $anchorId)>Show after &ldquo;{{ $anchorLabel }}&rdquo;</option>
                                @endforeach
                            </select>
                            <p class="text-[12px] text-[#646970] mt-1">It appears directly below the menu you pick. Shop and Products always stay together, so choosing Shop places this under them both.</p>
                        </div>
                    </div>

                    <!-- Hide Visibility -->
                    <div class="grid grid-cols-[200px_1fr] items-start">
                        <div>
                            <label class="text-[13px] font-semibold text-[#2c3338] block">Hide Visibility</label>
                            <p class="text-[12px] text-[#646970] mt-1">Should this CPT have hide the URLs?</p>
                        </div>
                        <div>
                            <input type="hidden" name="is_public" value="1">
                            <label class="flex items-center text-[13px] text-[#2c3338]">
                                <input type="checkbox" name="is_public" value="0" class="mr-2 border-[#8c8f94] rounded-[2px]">
                                Hide public URL? (Slug will not be generated)
                            </label>
                        </div>
                    </div>
                </div>

                <!-- Icons Tab Content -->
                <div x-show="tab === 'icons'" class="p-6">
                    <input type="hidden" name="icon" x-model="selectedIcon">

                    <div class="flex flex-wrap items-center gap-4 mb-4">
                        <div class="relative">
                            <span class="material-symbols-outlined absolute left-2 top-1/2 -translate-y-1/2 text-[18px] text-[#646970] pointer-events-none">search</span>
                            <input type="text" x-model="iconQuery" @input="iconLimit = 180" autocomplete="off"
                                   placeholder="Search {{ number_format(count($iconNames)) }} icons&hellip;"
                                   class="w-[300px] border-[#8c8f94] focus:border-[#2271b1] border py-1.5 pl-8 pr-3 rounded-[3px] shadow-[inset_0_1px_2px_rgba(0,0,0,0.07)] text-[13px]">
                        </div>
                        <span class="text-[12px] text-[#646970]" x-text="matchCount.toLocaleString() + (matchCount === 1 ? ' icon' : ' icons')"></span>
                        <button type="button" x-show="iconQuery" @click="iconQuery = ''" class="text-[12px] text-[#2271b1] hover:underline">Clear</button>
                    </div>

                    <div class="border border-[#dfdfdf] rounded-[4px] bg-[#fbfbfc] p-3 max-h-[380px] overflow-y-auto">
                        <div class="grid gap-2" style="grid-template-columns: repeat(auto-fill, minmax(46px, 1fr))">
                            <template x-for="name in visibleIcons" :key="name">
                                <button type="button" @click="selectedIcon = name" :title="name"
                                        :class="selectedIcon === name ? 'border-[#2271b1] bg-[#f0f6fc] text-[#2271b1]' : 'border-[#dcdcde] bg-white text-[#50575e] hover:border-[#2271b1] hover:text-[#2271b1]'"
                                        class="h-11 w-full border rounded-[4px] flex items-center justify-center transition-colors">
                                    <span class="material-symbols-outlined text-[20px] leading-none" x-text="name"></span>
                                </button>
                            </template>
                        </div>

                        <div x-show="matchCount === 0" class="py-10 text-center text-[13px] text-[#646970]">
                            No icon matches &ldquo;<span x-text="iconQuery"></span>&rdquo;.
                        </div>

                        <div x-show="matchCount > visibleIcons.length" class="pt-3 text-center">
                            <button type="button" @click="iconLimit += 400" class="text-[12px] text-[#2271b1] hover:underline">
                                Show more (<span x-text="(matchCount - visibleIcons.length).toLocaleString()"></span> left)
                            </button>
                        </div>
                    </div>

                    <div class="mt-6 p-4 bg-[#f6f7f7] border border-[#dfdfdf] rounded-[4px] flex items-center gap-4 max-w-[460px]">
                        <div class="w-12 h-12 bg-white border border-[#c3c4c7] rounded-[4px] flex items-center justify-center text-[#1d2327] flex-shrink-0">
                            <template x-if="iconIsSvg">
                                <div class="w-6 h-6" x-html="selectedIcon"></div>
                            </template>
                            <template x-if="!iconIsSvg">
                                <span class="material-symbols-outlined text-[22px] leading-none" x-text="selectedIcon"></span>
                            </template>
                        </div>
                        <div class="min-w-0">
                            <h4 class="text-[13px] font-semibold text-[#1d2327]">Preview Icon</h4>
                            <p class="text-[12px] text-[#646970]" x-show="!iconIsSvg">
                                This icon will appear in the sidebar menu &mdash; <code class="text-[#2271b1]" x-text="selectedIcon"></code>
                            </p>
                            <p class="text-[12px] text-[#646970]" x-show="iconIsSvg" x-cloak>
                                This post type still uses a custom SVG. Picking an icon above replaces it.
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </form>
    </div>

    @push('scripts')
    <script>
        document.addEventListener('alpine:init', () => {
            Alpine.data('cptForm', () => ({
                pluralLabel: '',
                singularLabel: '',
                postTypeKey: '',
                advanced: false,
                tab: 'fields',
                selectedIcon: 'description',
                
                // Every name here is a ligature that exists in the Material Symbols font the
                // admin ships, so nothing in the grid can render as an empty box. Search covers
                // the whole set; only a slice of it is ever in the DOM.
                allIcons: @json($iconNames),
                iconQuery: '',
                iconLimit: 180,

                get matches() {
                    const q = this.iconQuery.trim().toLowerCase().replace(/[\s-]+/g, '_');
                    return q ? this.allIcons.filter(n => n.includes(q)) : this.allIcons;
                },
                get matchCount() { return this.matches.length; },
                get visibleIcons() { return this.matches.slice(0, this.iconLimit); },
                get iconIsSvg() { return String(this.selectedIcon || '').trim().startsWith('<svg'); },

                updateKey() {
                    this.postTypeKey = this.pluralLabel.toLowerCase().replace(/[^a-z0-9]/g, '_').substring(0, 20);
                }
            }))
        })
    </script>
    <style>
        [x-cloak] { display: none !important; }
    </style>
    @endpush
</x-falcon-cms::layouts.admin>
