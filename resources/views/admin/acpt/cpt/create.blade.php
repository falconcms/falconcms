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
                    
                    <div class="grid grid-cols-8 gap-4 max-w-[800px]">
                        <template x-for="(iconSvg, iconName) in availableIcons" :key="iconName">
                            <div @click="selectedIcon = iconSvg" 
                                 :class="selectedIcon === iconSvg ? 'border-[#2271b1] bg-blue-50' : 'border-[#c3c4c7] hover:border-[#2271b1]'"
                                 class="w-12 h-12 border rounded-[4px] p-2 cursor-pointer flex items-center justify-center transition-all bg-white"
                                 :title="iconName">
                                <div class="w-full h-full text-[#1d2327]" x-html="iconSvg"></div>
                            </div>
                        </template>
                    </div>

                    <div class="mt-8 p-4 bg-[#f6f7f7] border border-[#dfdfdf] rounded-[4px] flex items-center gap-4 max-w-[400px]">
                        <div class="w-12 h-12 bg-white border border-[#c3c4c7] rounded-[4px] p-2 text-[#1d2327]">
                            <div class="w-full h-full" x-html="selectedIcon"></div>
                        </div>
                        <div>
                            <h4 class="text-[13px] font-semibold text-[#1d2327]">Preview Icon</h4>
                            <p class="text-[12px] text-[#646970]">This icon will appear in the sidebar menu.</p>
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
                selectedIcon: '<svg class="w-full h-full" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 002-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"></path></svg>',
                
                availableIcons: {
                    'Post': '<svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 20H5a2 2 0 01-2-2V6a2 2 0 012-2h10a2 2 0 012 2v12a2 2 0 01-2 2z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 2v4M8 2v4M3 10h14"/></svg>',
                    'Page': '<svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z"/></svg>',
                    'Database': '<svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 7v10c0 2 1.5 3 3.5 3s3.5-1 3.5-3V7c0-2-1.5-3-3.5-3S4 5 4 7zm0 5c0 2 1.5 3 3.5 3s3.5-1 3.5-3m-7-5c0 2 1.5 3 3.5 3s3.5-1 3.5-3m7-5v10c0 2 1.5 3 3.5 3s3.5-1 3.5-3V7c0-2-1.5-3-3.5-3S13 5 13 7zm0 5c0 2 1.5 3 3.5 3s3.5-1 3.5-3"/></svg>',
                    'Users': '<svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"/></svg>',
                    'Shopping Cart': '<svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 11-4 0 2 2 0 014 0z"/></svg>',
                    'Chart': '<svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/></svg>',
                    'Image': '<svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>',
                    'Tag': '<svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z"/></svg>',
                    'Folder': '<svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 7v10a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-6l-2-2H5a2 2 0 00-2 2z"/></svg>',
                    'Settings': '<svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/></svg>',
                    'Star': '<svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11.049 2.927c.3-.921 1.603-.921 1.902 0l1.519 4.674a1 1 0 00.95.69h4.915c.969 0 1.371 1.24.588 1.81l-3.976 2.888a1 1 0 00-.363 1.118l1.518 4.674c.3.922-.755 1.688-1.538 1.118l-3.976-2.888a1 1 0 00-1.176 0l-3.976 2.888c-.783.57-1.838-.197-1.538-1.118l1.518-4.674a1 1 0 00-.363-1.118l-3.976-2.888c-.784-.57-.382-1.81.588-1.81h4.914a1 1 0 00.951-.69l1.519-4.674z"/></svg>',
                    'Heart': '<svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z"/></svg>',
                    'Code': '<svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 20l4-16m4 4l4 4-4 4M6 16l-4-4 4-4"/></svg>',
                    'Chat': '<svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.029-8 9-8s9 3.582 9 8z"/></svg>',
                    'Video': '<svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 10l4.553-2.276A1 1 0 0121 8.618v6.764a1 1 0 01-1.447.894L15 14M5 18h8a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v8a2 2 0 002 2z"/></svg>',
                    'Calendar': '<svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>',
                    'Bell': '<svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"/></svg>',
                    'Book': '<svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.168.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"/></svg>',
                    'Briefcase': '<svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 13.255A23.931 23.931 0 0112 15c-3.183 0-6.22-.62-9-1.745M16 6V4a2 2 0 00-2-2h-4a2 2 0 00-2 2v2m4 6h.01M5 20h14a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>',
                    'Credit Card': '<svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z"/></svg>',
                    'Flag': '<svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 21v-4m0 0V5a2 2 0 012-2h6.5l1 1H21l-3 6 3 6h-8.5l-1-1H5a2 2 0 00-2 2zm9-13.5V9"/></svg>',
                    'Globe': '<svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 12a9 9 0 01-9 9m9-9a9 9 0 00-9-9m9 9H3m9 9a9 9 0 01-9-9m9 9c1.657 0 3-4.03 3-9s-1.343-9-3-9m0 18c-1.657 0-3-4.03-3-9s1.343-9 3-9m-9 9a9 9 0 019-9"/></svg>',
                    'Lightning Bolt': '<svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/></svg>',
                    'Microphone': '<svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11a7 7 0 01-7 7m0 0a7 7 0 01-7-7m7 7v4m0 0H8m4 0h4m-4-8a3 3 0 01-3-3V5a3 3 0 116 0v6a3 3 0 01-3 3z"/></svg>',
                    'Music Note': '<svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19V6l12-3v13M9 19c0 1.105-1.343 2-3 2s-3-.895-3-2 1.343-2 3-2 3 .895 3 2zm12-3c0 1.105-1.343 2-3 2s-3-.895-3-2 1.343-2 3-2 3 .895 3 2zM9 10l12-3"/></svg>',
                    'Paper Clip': '<svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.172 7l-6.586 6.586a2 2 0 102.828 2.828l6.414-6.586a4 4 0 00-5.656-5.656l-6.415 6.585a6 6 0 108.486 8.486L20.5 13"/></svg>',
                    'Pencil': '<svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"/></svg>',
                    'Phone': '<svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"/></svg>',
                    'Location': '<svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/></svg>',
                    'Puzzle': '<svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 4a2 2 0 114 0v1a1 1 0 001 1h3a1 1 0 011 1v3a1 1 0 01-1 1h-1a2 2 0 100 4h1a1 1 0 011 1v3a1 1 0 01-1 1h-3a1 1 0 01-1-1v-1a2 2 0 10-4 0v1a1 1 0 01-1 1H7a1 1 0 01-1-1v-3a1 1 0 00-1-1H4a2 2 0 110-4h1a1 1 0 001-1V7a1 1 0 011-1h3a1 1 0 001-1V4z"/></svg>',
                    'Truck': '<svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17a2 2 0 11-4 0 2 2 0 014 0zM19 17a2 2 0 11-4 0 2 2 0 014 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16V6a1 1 0 00-1-1H4a1 1 0 00-1 1v10a1 1 0 001 1h1m8-1a1 1 0 01-1 1H9m4-1V8a1 1 0 011-1h2.586a1 1 0 01.707.293l3.414 3.414a1 1 0 01.293.707V16a1 1 0 01-1 1h-1m-6-1a1 1 0 001 1h1M5 17a2 2 0 104 0m-4 0a2 2 0 114 0m6 0a2 2 0 104 0m-4 0a2 2 0 114 0"/></svg>',
                    'Ticket': '<svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 5v2m0 4v2m0 4v2M5 5a2 2 0 00-2 2v3a2 2 0 110 4v3a2 2 0 002 2h14a2 2 0 002-2v-3a2 2 0 110-4V7a2 2 0 00-2-2H5z"/></svg>',
                    'Search': '<svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>',
                    'Thumb Up': '<svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 10h4.757a2 2 0 011.707 2.83l-1.5 4.5A2 2 0 0117.257 18H10c-1.105 0-2-.895-2-2s.895-2 2-2h1v-4l-1-1V7a1 1 0 011-1h3z"/></svg>',
                    'Gift': '<svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v13m0-13V6a2 2 0 112 2h-2zm0 0V6a2 2 0 10-2 2h2zm0 8H4.343M12 14h7.657m-7.657-4a2 2 0 10-2-2h2m0 0a2 2 0 112 2h-2z"/></svg>',
                },

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
