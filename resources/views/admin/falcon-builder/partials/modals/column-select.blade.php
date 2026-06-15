<div v-if="showColumnModal" class="fixed inset-0 z-[9999] flex items-center justify-center bg-black/60 backdrop-blur-sm animate-fade-in" @click.self="showColumnModal = false">
    <div class="bg-white w-[95vw] max-w-[1200px] h-[90vh] flex flex-col shadow-2xl rounded overflow-hidden">
        <!-- Header -->
        <div class="bg-[#2271b1] text-white h-14 flex items-center justify-between px-6 shrink-0">
            <h3 class="text-sm font-bold uppercase tracking-wider text-white">@{{ columnModalType === 'new' ? 'Select Column' : 'Select Column Layout' }}</h3>
            <div class="flex items-center gap-4">
                <div class="relative" v-show="columnModalActiveTab === 'columns'">
                    <input type="text" v-model="searchColumnQuery" placeholder="Search Columns" class="bg-white border-none text-xs text-slate-800 placeholder-slate-400 px-10 py-2 rounded focus:ring-1 focus:ring-[#0091ea] w-64 outline-none">
                    <i class="fa fa-search absolute left-3 top-2.5 text-slate-400 text-xs"></i>
                </div>
                <button @click="showColumnModal = false" class="text-slate-500 hover:text-white transition-colors"><i class="fa fa-times text-lg"></i></button>
            </div>
        </div>

        <!-- Tabs -->
        <div class="bg-[#2271b1] h-10 flex items-center px-4 shrink-0">
            <button @click="columnModalActiveTab = 'columns'"
                    class="px-4 h-full text-[11px] font-bold uppercase transition-all"
                    :class="columnModalActiveTab === 'columns' ? 'text-white bg-white/20' : 'text-white/70 hover:bg-white/5'">
                Builder Columns
            </button>
            <!-- Container Library: shown when adding a new container -->
            <button v-if="columnModalType === 'new'"
                    @click="columnModalActiveTab = 'container_library'"
                    class="px-4 h-full text-[11px] font-bold uppercase transition-all flex items-center gap-2"
                    :class="columnModalActiveTab === 'container_library' ? 'text-white bg-white/20' : 'text-white/70 hover:bg-white/5'">
                Container Library
                <span v-if="libraryItems.containers?.length" class="px-1.5 py-0.5 rounded-full text-[10px] font-bold bg-white/20 text-white">
                    @{{ libraryItems.containers.length }}
                </span>
            </button>
            <!-- Column Library: shown when adding columns to an existing container -->
            <button v-if="columnModalType === 'edit'"
                    @click="columnModalActiveTab = 'column_library'"
                    class="px-4 h-full text-[11px] font-bold uppercase transition-all flex items-center gap-2"
                    :class="columnModalActiveTab === 'column_library' ? 'text-white bg-white/20' : 'text-white/70 hover:bg-white/5'">
                Column Library
                <span v-if="libraryItems.columns?.length" class="px-1.5 py-0.5 rounded-full text-[10px] font-bold bg-white/20 text-white">
                    @{{ libraryItems.columns.length }}
                </span>
            </button>
            <!-- Global Sections: shown when adding a new container -->
            <button v-if="columnModalType === 'new'"
                    @click="columnModalActiveTab = 'global_sections'"
                    class="px-4 h-full text-[11px] font-bold uppercase transition-all flex items-center gap-2"
                    :class="columnModalActiveTab === 'global_sections' ? 'text-white bg-white/20' : 'text-white/70 hover:bg-white/5'">
                <i class="fa fa-globe text-[10px]"></i> Global Sections
                <span v-if="globalSections.length" class="px-1.5 py-0.5 rounded-full text-[10px] font-bold bg-white/20 text-white">
                    @{{ globalSections.length }}
                </span>
            </button>
        </div>

        <!-- Builder Columns Tab -->
        <div v-show="columnModalActiveTab === 'columns'" class="flex-1 overflow-y-auto p-10 bg-[#fff]">
            <div class="grid grid-cols-2 md:grid-cols-4 lg:grid-cols-7 gap-x-6 gap-y-10">
                <div v-for="layout in filteredColumnLayouts" :key="layout.id"
                     class="group flex flex-col items-center gap-3 cursor-pointer"
                     @click="selectLayout(layout)">
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
        </div>

        <!-- Container Library Tab -->
        <div v-show="columnModalActiveTab === 'container_library'" class="flex-1 overflow-y-auto p-8 bg-[#fafafa]">
            <div v-if="!libraryItems.containers?.length" class="text-center py-20 text-slate-300">
                <i class="fa fa-box-open text-5xl mb-4 block"></i>
                <p class="text-sm font-semibold text-slate-400">No saved containers yet</p>
                <p class="text-xs text-slate-300 mt-1">Use the Library icon on a container toolbar to save it here</p>
            </div>
            <div v-else class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 xl:grid-cols-5 gap-5">
                <div v-for="item in libraryItems.containers" :key="item.id"
                     class="bg-white border border-slate-200 rounded-xl overflow-hidden hover:border-[#0091ea] hover:shadow-lg transition-all group">
                    <div class="bg-gradient-to-br from-slate-50 to-slate-100 p-8 flex items-center justify-center border-b border-slate-100">
                        <i class="fa fa-table-columns text-4xl text-slate-200 group-hover:text-[#0091ea]/50 transition-colors"></i>
                    </div>
                    <div class="p-3">
                        <p class="text-xs font-bold text-slate-700 truncate mb-1" :title="item.name">@{{ item.name }}</p>
                        <p class="text-[10px] text-slate-400 mb-3">@{{ item.created_at }}</p>
                        <div class="flex gap-2">
                            <button @click="addContainerFromColumnModal(item)"
                                    class="flex-1 py-1.5 bg-[#2271b1]/10 text-[#0091ea] rounded-lg text-[11px] font-semibold hover:bg-[#1a5a96] hover:text-white transition-colors flex items-center justify-center gap-1">
                                <i class="fa fa-plus text-[10px]"></i> Add Container
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

        <!-- Column Library Tab -->
        <div v-show="columnModalActiveTab === 'column_library'" class="flex-1 overflow-y-auto p-8 bg-[#fafafa]">
            <div v-if="!libraryItems.columns?.length" class="text-center py-20 text-slate-300">
                <i class="fa fa-box-open text-5xl mb-4 block"></i>
                <p class="text-sm font-semibold text-slate-400">No saved columns yet</p>
                <p class="text-xs text-slate-300 mt-1">Use the Library icon on a column toolbar to save it here</p>
            </div>
            <div v-else class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 xl:grid-cols-5 gap-5">
                <div v-for="item in libraryItems.columns" :key="item.id"
                     class="bg-white border border-slate-200 rounded-xl overflow-hidden hover:border-[#0091ea] hover:shadow-lg transition-all group">
                    <div class="bg-gradient-to-br from-slate-50 to-slate-100 p-8 flex items-center justify-center border-b border-slate-100">
                        <i class="fa fa-columns text-4xl text-slate-200 group-hover:text-[#0091ea]/50 transition-colors"></i>
                    </div>
                    <div class="p-3">
                        <p class="text-xs font-bold text-slate-700 truncate mb-1" :title="item.name">@{{ item.name }}</p>
                        <p class="text-[10px] text-slate-400 mb-3">@{{ item.created_at }}</p>
                        <div class="flex gap-2">
                            <button @click="addColumnFromColumnModal(item)"
                                    class="flex-1 py-1.5 bg-[#2271b1]/10 text-[#0091ea] rounded-lg text-[11px] font-semibold hover:bg-[#1a5a96] hover:text-white transition-colors flex items-center justify-center gap-1">
                                <i class="fa fa-plus text-[10px]"></i> Add Column
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
        <!-- Global Sections Tab -->
        <div v-show="columnModalActiveTab === 'global_sections'" class="flex-1 overflow-y-auto p-8 bg-[#fafafa]">
            <div v-if="!globalSections.length" class="text-center py-20 text-slate-300">
                <i class="fa fa-globe text-5xl mb-4 block"></i>
                <p class="text-sm font-semibold text-slate-400">No global sections saved yet</p>
                <p class="text-xs text-slate-300 mt-1">Right-click a container and choose "Save as Global" to create one</p>
            </div>
            <div v-else class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 xl:grid-cols-5 gap-5">
                <div v-for="section in globalSections" :key="section.id"
                     class="bg-white border border-slate-200 rounded-xl overflow-hidden hover:border-[#7c3aed] hover:shadow-lg transition-all group">
                    <div class="bg-gradient-to-br from-purple-50 to-purple-100 p-8 flex items-center justify-center border-b border-slate-100">
                        <i class="fa fa-globe text-4xl text-purple-200 group-hover:text-[#7c3aed]/50 transition-colors"></i>
                    </div>
                    <div class="p-3">
                        <p class="text-xs font-bold text-slate-700 truncate mb-1" :title="section.name">@{{ section.name }}</p>
                        <p class="text-[10px] text-slate-400 mb-3">@{{ section.created_at }}</p>
                        <div class="flex gap-2">
                            <button @click="insertGlobalSection(section)"
                                    class="flex-1 py-1.5 bg-[#7c3aed]/10 text-[#7c3aed] rounded-lg text-[11px] font-semibold hover:bg-[#7c3aed] hover:text-white transition-colors flex items-center justify-center gap-1">
                                <i class="fa fa-plus text-[10px]"></i> Insert
                            </button>
                            <button @click.stop="deleteGlobalSection(section.id)"
                                    class="w-8 py-1.5 bg-red-50 text-red-400 rounded-lg hover:bg-red-100 hover:text-red-500 transition-colors flex items-center justify-center">
                                <i class="fa fa-trash-alt text-[10px]"></i>
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
