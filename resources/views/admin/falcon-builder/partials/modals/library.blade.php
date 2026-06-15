{{-- Builder Library Modal --}}
<transition name="modal-fade">
<div v-if="showLibraryModal"
     class="fixed inset-0 bg-black/50 z-[9998] flex items-center justify-center p-4"
     @click.self="showLibraryModal = false">
    <div class="bg-white rounded-2xl shadow-2xl w-full max-w-4xl max-h-[88vh] flex flex-col overflow-hidden">

        {{-- Header --}}
        <div class="flex items-center justify-between px-6 py-4 border-b border-slate-100 flex-shrink-0">
            <div class="flex items-center gap-3">
                <div class="w-8 h-8 bg-[#2271b1]/10 rounded-lg flex items-center justify-center">
                    <i class="fa fa-archive text-[#0091ea] text-sm"></i>
                </div>
                <h2 class="text-base font-bold text-slate-700">Builder Library</h2>
            </div>
            <button @click="showLibraryModal = false"
                    class="w-8 h-8 flex items-center justify-center hover:bg-slate-100 rounded-lg transition-colors text-slate-400 hover:text-slate-600">
                <i class="fa fa-times"></i>
            </button>
        </div>

        {{-- Tabs --}}
        <div class="flex border-b border-slate-100 px-6 flex-shrink-0 bg-white">
            <button v-for="tab in libraryTabs" :key="tab.key"
                    @click="libraryActiveTab = tab.key"
                    class="px-4 py-3 text-xs font-semibold border-b-2 transition-colors flex items-center gap-2 mr-1"
                    :class="libraryActiveTab === tab.key
                        ? (tab.key === 'global_sections' ? 'border-[#7c3aed] text-[#7c3aed]' : 'border-[#0091ea] text-[#0091ea]')
                        : 'border-transparent text-slate-400 hover:text-slate-600'">
                <i :class="tab.icon"></i>
                @{{ tab.label }}
                <span v-if="tab.key === 'global_sections' ? globalSections.length : libraryItems[tab.key]?.length"
                      class="px-1.5 py-0.5 rounded-full text-[10px] font-bold"
                      :class="libraryActiveTab === tab.key
                          ? (tab.key === 'global_sections' ? 'bg-[#7c3aed]/10 text-[#7c3aed]' : 'bg-[#2271b1]/10 text-[#0091ea]')
                          : 'bg-slate-100 text-slate-400'">
                    @{{ tab.key === 'global_sections' ? globalSections.length : libraryItems[tab.key].length }}
                </span>
            </button>
        </div>

        {{-- Body --}}
        <div class="flex-1 overflow-y-auto p-6">

            {{-- Save current item to library --}}
            <div v-if="libraryCanSave"
                 class="mb-6 p-4 rounded-xl border"
                 :class="saveAsGlobalChecked ? 'bg-[#7c3aed]/5 border-[#7c3aed]/20' : 'bg-[#2271b1]/5 border-[#0091ea]/20'">
                <p class="text-xs font-semibold text-slate-600 mb-3 flex items-center gap-2">
                    <i class="fa fa-download" :class="saveAsGlobalChecked ? 'text-[#7c3aed]' : 'text-[#0091ea]'"></i>
                    Save current <span :class="saveAsGlobalChecked ? 'text-[#7c3aed]' : 'text-[#0091ea]'">@{{ libraryActiveTabLabel }}</span> to library
                </p>
                <div class="flex gap-3 mb-3">
                    <input v-model="libraryNewName"
                           type="text"
                           placeholder="Give it a name..."
                           class="flex-1 border border-slate-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2"
                           :class="saveAsGlobalChecked ? 'focus:border-[#7c3aed] focus:ring-[#7c3aed]/10' : 'focus:border-[#0091ea] focus:ring-[#0091ea]/10'"
                           @keydown.enter="saveToLibrary">
                    <button @click="saveToLibrary"
                            :disabled="!libraryNewName.trim() || isSavingToLibrary"
                            class="px-4 py-2 text-white text-sm font-semibold rounded-lg disabled:opacity-40 disabled:cursor-not-allowed transition-colors flex items-center gap-2 whitespace-nowrap"
                            :class="saveAsGlobalChecked ? 'bg-[#7c3aed] hover:bg-[#6d28d9]' : 'bg-[#2271b1] hover:bg-[#1a5a96]'">
                        <i :class="isSavingToLibrary ? 'fa fa-spinner fa-spin' : 'fa fa-save'"></i>
                        Save to Library
                    </button>
                </div>
                {{-- Save as Global checkbox (containers only) --}}
                <div v-if="libraryActiveTab === 'containers'" class="flex items-center gap-2">
                    <input type="checkbox" id="saveAsGlobalCheck" v-model="saveAsGlobalChecked"
                           class="w-3.5 h-3.5 rounded accent-[#7c3aed] cursor-pointer">
                    <label for="saveAsGlobalCheck" class="text-xs font-semibold text-slate-600 cursor-pointer flex items-center gap-1.5 select-none">
                        <i class="fa fa-globe text-[#7c3aed] text-[11px]"></i>
                        Save as Global
                        <span class="text-[10px] font-normal text-slate-400">(edit once, updates everywhere)</span>
                    </label>
                </div>
            </div>

            {{-- Empty state --}}
            <div v-if="libraryCurrentItems.length === 0"
                 class="text-center py-16 text-slate-300">
                <i :class="libraryTabIcon + ' text-5xl mb-4 block'"></i>
                <p class="text-sm font-semibold text-slate-400">No saved @{{ libraryActiveTabLabel }} yet</p>
                <p v-if="libraryActiveTab === 'global_sections'" class="text-xs text-slate-300 mt-1">
                    Right-click a container → "Save as Global", or check "Save as Global" when saving to library
                </p>
                <p v-else class="text-xs text-slate-300 mt-1">Save items from the canvas toolbar to reuse them here</p>
            </div>

            {{-- Library grid --}}
            <div v-else class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 gap-4">
                <div v-for="item in libraryCurrentItems" :key="item.id"
                     class="border rounded-xl overflow-hidden hover:shadow-md transition-all group cursor-default"
                     :class="libraryActiveTab === 'global_sections' ? 'border-slate-200 hover:border-[#7c3aed]' : 'border-slate-200 hover:border-[#0091ea]'">
                    {{-- Icon Preview --}}
                    <div class="bg-gradient-to-br p-8 flex items-center justify-center border-b border-slate-100"
                         :class="libraryActiveTab === 'global_sections' ? 'from-purple-50 to-purple-100' : 'from-slate-50 to-slate-100'">
                        <i :class="libraryTabIcon + ' text-4xl transition-colors'"
                           :style="libraryActiveTab === 'global_sections' ? 'color:#c4b5fd' : 'color:#e2e8f0'"
                           class="group-hover:opacity-60"></i>
                    </div>
                    {{-- Info + Actions --}}
                    <div class="p-3">
                        <p class="text-xs font-bold text-slate-700 mb-1 truncate" :title="item.name">@{{ item.name }}</p>
                        <p class="text-[10px] text-slate-400 mb-3">@{{ item.created_at }}</p>
                        <div class="flex gap-2">
                            <button v-if="libraryActiveTab === 'global_sections'"
                                    @click="insertGlobalFromLibrary(item)"
                                    class="flex-1 text-[11px] py-1.5 bg-[#7c3aed] text-white rounded-lg hover:bg-[#6d28d9] transition-colors font-semibold flex items-center justify-center gap-1">
                                <i class="fa fa-plus text-[10px]"></i> Add Global
                            </button>
                            <button v-else
                                    @click="insertFromLibrary(item)"
                                    class="flex-1 text-[11px] py-1.5 bg-[#2271b1] text-white rounded-lg hover:bg-[#1a5a96] transition-colors font-semibold flex items-center justify-center gap-1">
                                <i class="fa fa-plus text-[10px]"></i> Add @{{ {containers:'Container',columns:'Column',nested_columns:'Nested Column',elements:'Element'}[libraryActiveTab] || '' }}
                            </button>
                            <button @click="libraryActiveTab === 'global_sections' ? deleteGlobalSection(item.id) : deleteFromLibrary(item.id)"
                                    class="w-8 py-1.5 bg-red-50 text-red-400 rounded-lg hover:bg-red-100 hover:text-red-500 transition-colors flex items-center justify-center"
                                    title="Delete">
                                <i class="fa fa-trash-alt text-[10px]"></i>
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
</transition>
