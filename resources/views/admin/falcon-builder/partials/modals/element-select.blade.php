<div v-if="showElementModal" class="fixed inset-0 z-[9999] flex items-center justify-center bg-black/60 backdrop-blur-sm animate-fade-in" @click.self="showElementModal = false">
    <div class="bg-white w-[95vw] max-w-[1200px] h-[90vh] flex flex-col shadow-2xl rounded overflow-hidden">

        <!-- Header -->
        <div class="bg-[#2271b1] text-white h-14 flex items-center justify-between px-6 shrink-0">
            <h3 class="text-sm font-bold uppercase tracking-wider text-white">Select Element</h3>
            <div class="flex items-center gap-4">
                <div class="relative" v-show="elementModalTab === 'elements' || elementModalTab === 'nested'">
                    <input type="text" v-model="searchElementQuery" :placeholder="elementModalTab === 'nested' ? 'Search Columns' : 'Search Elements'" class="bg-white border-none text-xs text-slate-800 placeholder-slate-400 px-10 py-2 rounded focus:ring-1 focus:ring-[#0091ea] w-64 outline-none">
                    <i class="fa fa-search absolute left-3 top-2.5 text-slate-400 text-xs"></i>
                </div>
                <button @click="showElementModal = false" class="text-slate-500 hover:text-white transition-colors"><i class="fa fa-times text-lg"></i></button>
            </div>
        </div>

        <!-- Tabs -->
        <div class="bg-[#2271b1] h-10 flex items-center px-4 shrink-0">
            <button v-if="elementModalAllowedTabs.includes('elements')"
                    @click="elementModalTab = 'elements'"
                    class="px-5 h-full text-[11px] font-bold uppercase transition-all"
                    :class="elementModalTab === 'elements' ? 'text-white bg-white/20' : 'text-white/70 hover:bg-white/5'">
                Elements
            </button>
            <button v-if="elementModalAllowedTabs.includes('elements')"
                    @click="elementModalTab = 'element_library'"
                    class="px-5 h-full text-[11px] font-bold uppercase transition-all flex items-center gap-2"
                    :class="elementModalTab === 'element_library' ? 'text-white bg-white/20' : 'text-white/70 hover:bg-white/5'">
                Element Library
                <span v-if="libraryItems.elements?.length" class="px-1.5 py-0.5 rounded-full text-[10px] font-bold bg-white/20 text-white">
                    @{{ libraryItems.elements.length }}
                </span>
            </button>
            <button v-if="!elementModalRestricted && elementModalAllowedTabs.includes('nested')"
                    @click="elementModalTab = 'nested'"
                    class="px-5 h-full text-[11px] font-bold uppercase transition-all"
                    :class="elementModalTab === 'nested' ? 'text-white bg-white/20' : 'text-white/70 hover:bg-white/5'">
                Nested Columns
            </button>
            <button v-if="!elementModalRestricted && elementModalAllowedTabs.includes('nested')"
                    @click="elementModalTab = 'nested_library'"
                    class="px-5 h-full text-[11px] font-bold uppercase transition-all flex items-center gap-2"
                    :class="elementModalTab === 'nested_library' ? 'text-white bg-white/20' : 'text-white/70 hover:bg-white/5'">
                Nested Col Library
                <span v-if="libraryItems.nested_columns?.length" class="px-1.5 py-0.5 rounded-full text-[10px] font-bold bg-white/20 text-white">
                    @{{ libraryItems.nested_columns.length }}
                </span>
            </button>
        </div>

        <!-- Grid Body -->
        <div class="flex-1 overflow-y-auto p-10 bg-[#fff] custom-scrollbar">

            <!-- Elements Tab -->
            <div v-if="elementModalTab === 'elements'" class="grid grid-cols-3 md:grid-cols-4 lg:grid-cols-6 gap-x-5 gap-y-8">
                <div v-for="el in filteredAvailableElements" :key="el.type"
                     @click="addElement(el.type)"
                     class="group flex flex-col items-center gap-3 cursor-pointer">
                    <div class="w-full aspect-square bg-white border-2 border-slate-200 flex flex-col items-center justify-center rounded-lg group-hover:border-[#0091ea] group-hover:shadow-md transition-all transform group-hover:-translate-y-1">
                        <i :class="el.icon" style="font-size:24px" class="text-slate-400 group-hover:text-[#0091ea] transition-colors"></i>
                    </div>
                    <span class="text-[12px] font-bold uppercase text-slate-500 group-hover:text-[#0091ea] transition-colors">@{{ el.name || el.type }}</span>
                </div>
            </div>

            <!-- Element Library Tab -->
            <div v-if="elementModalTab === 'element_library'">
                <div v-if="!libraryItems.elements?.length" class="text-center py-20 text-slate-300">
                    <i class="fa fa-box-open text-5xl mb-4 block"></i>
                    <p class="text-sm font-semibold text-slate-400">No saved elements yet</p>
                    <p class="text-xs text-slate-300 mt-1">Use the Library icon on an element toolbar to save it here</p>
                </div>
                <div v-else class="grid grid-cols-2 md:grid-cols-4 lg:grid-cols-5 xl:grid-cols-6 gap-5">
                    <div v-for="item in libraryItems.elements" :key="item.id"
                         class="bg-white border border-slate-200 rounded-xl overflow-hidden hover:border-[#0091ea] hover:shadow-lg transition-all group">
                        <div class="bg-gradient-to-br from-slate-50 to-slate-100 p-8 flex items-center justify-center border-b border-slate-100">
                            <i class="fa fa-cube text-4xl text-slate-200 group-hover:text-[#0091ea]/50 transition-colors"></i>
                        </div>
                        <div class="p-3">
                            <p class="text-xs font-bold text-slate-700 truncate mb-1" :title="item.name">@{{ item.name }}</p>
                            <p class="text-[10px] text-slate-400 mb-3">@{{ item.created_at }}</p>
                            <div class="flex gap-2">
                                <button @click="addElementFromElementModal(item)"
                                        class="flex-1 py-1.5 bg-[#2271b1]/10 text-[#0091ea] rounded-lg text-[11px] font-semibold hover:bg-[#1a5a96] hover:text-white transition-colors flex items-center justify-center gap-1">
                                    <i class="fa fa-plus text-[10px]"></i> Add Element
                                </button>
                                <button @click.stop="deleteFromLibrary(item.id)"
                                        class="w-8 py-1.5 bg-red-50 text-red-400 rounded-lg hover:bg-red-100 hover:text-red-500 transition-colors flex items-center justify-center">
                                    <i class="fa fa-trash-alt text-[10px]"></i>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Nested Columns Tab -->
            <div v-if="elementModalTab === 'nested'" class="grid grid-cols-2 md:grid-cols-4 lg:grid-cols-7 gap-x-6 gap-y-10">
                <div v-for="layout in filteredNestedColumnLayouts" :key="layout.id"
                     @click="selectNestedLayout(layout.config)"
                     class="group flex flex-col items-center gap-3 cursor-pointer">
                    <div class="w-full aspect-[16/10] bg-white border border-transparent group-hover:border-slate-200 group-hover:border-dashed p-1 transition-all rounded">
                        <div class="w-full h-full flex gap-1 justify-center">
                            <div v-for="part in layout.config.split('-')"
                                 class="h-full bg-[#9da8b1] rounded-sm transition-colors group-hover:bg-[#86929c]"
                                 :style="{ width: `calc(${(part.split('/')[0] / part.split('/')[1]) * 100}% - 4px)` }"></div>
                        </div>
                    </div>
                    <span class="text-[11px] font-bold text-slate-500 group-hover:text-black transition-colors">@{{ layout.label }}</span>
                </div>
            </div>

            <!-- Nested Column Library Tab -->
            <div v-if="elementModalTab === 'nested_library'">
                <div v-if="!libraryItems.nested_columns?.length" class="text-center py-20 text-slate-300">
                    <i class="fa fa-box-open text-5xl mb-4 block"></i>
                    <p class="text-sm font-semibold text-slate-400">No saved nested columns yet</p>
                    <p class="text-xs text-slate-300 mt-1">Use the Library icon on a nested column toolbar to save it here</p>
                </div>
                <div v-else class="grid grid-cols-2 md:grid-cols-4 lg:grid-cols-5 xl:grid-cols-6 gap-5">
                    <div v-for="item in libraryItems.nested_columns" :key="item.id"
                         class="bg-white border border-slate-200 rounded-xl overflow-hidden hover:border-[#0091ea] hover:shadow-lg transition-all group">
                        <div class="bg-gradient-to-br from-slate-50 to-slate-100 p-8 flex items-center justify-center border-b border-slate-100">
                            <i class="fa fa-layer-group text-4xl text-slate-200 group-hover:text-[#0091ea]/50 transition-colors"></i>
                        </div>
                        <div class="p-3">
                            <p class="text-xs font-bold text-slate-700 truncate mb-1" :title="item.name">@{{ item.name }}</p>
                            <p class="text-[10px] text-slate-400 mb-3">@{{ item.created_at }}</p>
                            <div class="flex gap-2">
                                <button @click="addNestedColumnFromElementModal(item)"
                                        class="flex-1 py-1.5 bg-[#2271b1]/10 text-[#0091ea] rounded-lg text-[11px] font-semibold hover:bg-[#1a5a96] hover:text-white transition-colors flex items-center justify-center gap-1">
                                    <i class="fa fa-plus text-[10px]"></i> Add Nested Column
                                </button>
                                <button @click.stop="deleteFromLibrary(item.id)"
                                        class="w-8 py-1.5 bg-red-50 text-red-400 rounded-lg hover:bg-red-100 hover:text-red-500 transition-colors flex items-center justify-center">
                                    <i class="fa fa-trash-alt text-[10px]"></i>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

        </div>

        <!-- Footer -->
        <div class="h-12 border-t border-slate-100 flex items-center px-6 bg-slate-50/50 shrink-0">
            <span class="text-[9px] font-bold text-slate-400 uppercase tracking-[0.2em]">FalconCMS • Element Selector</span>
        </div>

    </div>
</div>
