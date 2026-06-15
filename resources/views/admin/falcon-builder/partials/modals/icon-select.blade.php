<div v-if="showIconModal" class="fixed inset-0 z-[10000] flex items-center justify-center p-4">
    <div class="absolute inset-0 bg-black/60 backdrop-blur-sm" @click="showIconModal = false"></div>
    
    <div class="relative bg-white rounded-xl shadow-2xl w-full max-w-2xl overflow-hidden flex flex-col max-h-[80vh]">
        <!-- Header -->
        <div class="px-6 py-4 border-b border-slate-100 flex justify-between items-center bg-slate-50">
            <div>
                <h3 class="text-lg font-bold text-slate-800">Select Icon</h3>
                <p class="text-[11px] text-slate-400 font-medium uppercase tracking-wider">Choose from FontAwesome Library</p>
            </div>
            <button @click="showIconModal = false" class="w-8 h-8 rounded-full hover:bg-slate-200 flex items-center justify-center text-slate-400 transition-colors">
                <i class="fa fa-times"></i>
            </button>
        </div>

        <!-- Search -->
        <div class="p-6 border-b border-slate-100">
            <div class="relative">
                <i class="fa fa-search absolute left-4 top-1/2 -translate-y-1/2 text-slate-400"></i>
                <input type="text" 
                       v-model="searchIconQuery" 
                       placeholder="Search icons (e.g. cart, heart, arrow)..." 
                       class="w-full pl-11 pr-4 py-3 bg-slate-50 border border-slate-200 rounded-lg text-sm focus:outline-none focus:border-[#0091ea] focus:bg-white transition-all">
            </div>
        </div>

        <!-- Icons Grid -->
        <div class="flex-1 overflow-y-auto p-6 scrollbar-thin scrollbar-thumb-slate-200">
            <div class="grid grid-cols-6 sm:grid-cols-8 gap-3">
                <button v-for="icon in filteredIcons" 
                        :key="icon"
                        @click="selectIcon(icon)"
                        class="aspect-square flex flex-col items-center justify-center rounded-lg border border-slate-100 hover:border-[#0091ea] hover:bg-blue-50 transition-all group p-2">
                    <i :class="[icon, 'text-xl text-slate-600 group-hover:text-[#0091ea] mb-1']"></i>
                    <span class="text-[9px] text-slate-400 truncate w-full text-center">@{{ icon.replace('fa fa-', '').replace('fas fa-', '').replace('fab fa-', '') }}</span>
                </button>
            </div>
            
            <div v-if="filteredIcons.length === 0" class="py-20 text-center">
                <i class="fa fa-search text-4xl text-slate-200 mb-4"></i>
                <p class="text-slate-400">No icons found for "@{{ searchIconQuery }}"</p>
            </div>
        </div>

        <!-- Footer -->
        <div class="px-6 py-4 bg-slate-50 border-t border-slate-100 flex justify-between items-center text-[11px] text-slate-400 font-medium">
            <span>Click an icon to select it</span>
            <span>FontAwesome 6.4.0</span>
        </div>
    </div>
</div>
