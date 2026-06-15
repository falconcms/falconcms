<div class="space-y-8">
    <!-- General Settings Header -->
    <div class="flex items-center gap-2 pb-2 border-b border-slate-50">
        <div class="w-1.5 h-4 bg-[#2271b1] rounded-full"></div>
        <h4 class="text-[11px] font-black uppercase tracking-widest text-slate-700">General Settings</h4>
    </div>

    <div class="space-y-6">
        <!-- Select Menu -->
        <div class="space-y-4">
            <div class="flex justify-between items-center">
                <label class="text-[12px] font-bold text-slate-700">Menu</label>
            </div>
            <select v-model="editingElement.settings.menuId" 
                    class="w-full border border-slate-200 rounded px-3 py-2.5 text-[13px] focus:outline-none focus:border-[#0091ea] bg-white">
                <option value="">Select a menu...</option>
                <option v-for="(name, id) in falconMenusList" :value="id">@{{ name }}</option>
            </select>
        </div>

        <!-- Direction Selector -->
        <div class="space-y-4 pt-4 border-t border-slate-50">
            <div class="flex justify-between items-center">
                <label class="text-[12px] font-bold text-slate-700">Direction</label>
            </div>
            <div class="flex bg-slate-50 border border-slate-100 rounded p-1 w-fit">
                <button @click="editingElement.settings.layout = 'horizontal'" 
                        :class="(editingElement.settings.layout === 'horizontal' || !editingElement.settings.layout) ? 'bg-[#2271b1] text-white shadow-sm' : 'text-slate-400 hover:bg-white/50'"
                        class="px-5 py-2 text-[11px] font-bold rounded transition-all">Horizontal</button>
                <button @click="editingElement.settings.layout = 'vertical'" 
                        :class="editingElement.settings.layout === 'vertical' ? 'bg-[#2271b1] text-white shadow-sm' : 'text-slate-400 hover:bg-white/50'"
                        class="px-5 py-2 text-[11px] font-bold rounded transition-all">Vertical</button>
            </div>
        </div>

        <!-- Margin -->
        <div class="space-y-4 pt-4 border-t border-slate-50">
            <div class="flex justify-between items-center">
                <label class="text-[12px] font-bold text-slate-700">Margin</label>
            </div>
            <div class="grid grid-cols-2 gap-4">
                <div class="space-y-1">
                    <label class="text-[9px] font-black text-slate-400 uppercase tracking-widest">Top</label>
                    <input type="number" v-model="editingElement.settings.marginTop" 
                           class="w-full border border-slate-200 rounded px-3 py-2 text-[13px] focus:outline-none focus:border-[#0091ea]">
                </div>
                <div class="space-y-1">
                    <label class="text-[9px] font-black text-slate-400 uppercase tracking-widest">Bottom</label>
                    <input type="number" v-model="editingElement.settings.marginBottom" 
                           class="w-full border border-slate-200 rounded px-3 py-2 text-[13px] focus:outline-none focus:border-[#0091ea]">
                </div>
            </div>
        </div>

        <!-- Transition Time -->
        <div class="space-y-4 pt-4 border-t border-slate-50">
            <div class="flex justify-between items-center">
                <label class="text-[12px] font-bold text-slate-700">Transition Time</label>
            </div>
            <div class="flex items-center gap-4">
                <input type="number" v-model="editingElement.settings.itemTransitionMs" 
                       @input="editingElement.settings.itemTransition = editingElement.settings.itemTransitionMs / 1000"
                       class="w-16 border border-slate-200 rounded px-2 py-2 text-[13px] text-center focus:outline-none focus:border-[#0091ea]">
                <input type="range" min="0" max="2000" step="50" v-model="editingElement.settings.itemTransitionMs" 
                       @input="editingElement.settings.itemTransition = editingElement.settings.itemTransitionMs / 1000"
                       class="flex-1 h-1.5 bg-slate-100 rounded-lg appearance-none cursor-pointer accent-[#0091ea]">
            </div>
        </div>

        <!-- Space Between Main Menu and Submenu -->
        <div class="space-y-4 pt-4 border-t border-slate-50">
            <div class="flex justify-between items-center">
                <label class="text-[12px] font-bold text-slate-700">Space Between Main Menu and Submenu</label>
            </div>
            <input type="number" v-model="editingElement.settings.submenuSpace" 
                   class="w-full border border-slate-200 rounded px-3 py-2.5 text-[13px] focus:outline-none focus:border-[#0091ea]">
        </div>

        <!-- Menu Arrows -->
        <div class="space-y-4 pt-4 border-t border-slate-50">
            <div class="flex justify-between items-center">
                <label class="text-[12px] font-bold text-slate-700">Menu Arrows</label>
            </div>
            <div class="flex bg-slate-50 border border-slate-100 rounded overflow-hidden">
                <button @click="editingElement.settings.arrowScopeObj = editingElement.settings.arrowScopeObj || {main:true, active:false, submenu:false}; editingElement.settings.arrowScopeObj.main = !editingElement.settings.arrowScopeObj.main" 
                        :class="(editingElement.settings.arrowScopeObj?.main ?? true) ? 'bg-[#2271b1] text-white shadow-sm' : 'text-slate-500 hover:bg-slate-50'"
                        class="flex-1 py-2.5 text-[11px] font-bold transition-all border-r border-slate-100">Main</button>
                <button @click="editingElement.settings.arrowScopeObj = editingElement.settings.arrowScopeObj || {main:true, active:false, submenu:false}; editingElement.settings.arrowScopeObj.active = !editingElement.settings.arrowScopeObj.active" 
                        :class="editingElement.settings.arrowScopeObj?.active ? 'bg-[#2271b1] text-white shadow-sm' : 'text-slate-500 hover:bg-slate-50'"
                        class="flex-1 py-2.5 text-[11px] font-bold transition-all border-r border-slate-100">Main Active</button>
                <button @click="editingElement.settings.arrowScopeObj = editingElement.settings.arrowScopeObj || {main:true, active:false, submenu:false}; editingElement.settings.arrowScopeObj.submenu = !editingElement.settings.arrowScopeObj.submenu" 
                        :class="editingElement.settings.arrowScopeObj?.submenu ? 'bg-[#2271b1] text-white shadow-sm' : 'text-slate-500 hover:bg-slate-50'"
                        class="flex-1 py-2.5 text-[11px] font-bold transition-all">Submenu</button>
            </div>
        </div>

        <!-- Element Visibility -->
        <div class="space-y-4 pt-4 border-t border-slate-50">
            <div class="flex justify-between items-center">
                <label class="text-[12px] font-bold text-slate-700">Element Visibility</label>
            </div>
            <div class="flex bg-slate-50 border border-slate-100 rounded overflow-hidden">
                <button @click="editingElement.settings.visibility = editingElement.settings.visibility || {mobile:true, tablet:true, desktop:true}; editingElement.settings.visibility.mobile = !editingElement.settings.visibility.mobile"
                        :class="(editingElement.settings.visibility?.mobile ?? true) ? 'bg-[#2271b1] text-white shadow-sm' : 'text-slate-400'"
                        class="flex-1 py-2.5 flex items-center justify-center border-r border-slate-100 transition-all">
                    <i class="fa fa-mobile-alt text-xs"></i>
                </button>
                <button @click="editingElement.settings.visibility = editingElement.settings.visibility || {mobile:true, tablet:true, desktop:true}; editingElement.settings.visibility.tablet = !editingElement.settings.visibility.tablet"
                        :class="(editingElement.settings.visibility?.tablet ?? true) ? 'bg-[#2271b1] text-white shadow-sm' : 'text-slate-400'"
                        class="flex-1 py-2.5 flex items-center justify-center border-r border-slate-100 transition-all">
                    <i class="fa fa-tablet-alt text-xs"></i>
                </button>
                <button @click="editingElement.settings.visibility = editingElement.settings.visibility || {mobile:true, tablet:true, desktop:true}; editingElement.settings.visibility.desktop = !editingElement.settings.visibility.desktop"
                        :class="(editingElement.settings.visibility?.desktop ?? true) ? 'bg-[#2271b1] text-white shadow-sm' : 'text-slate-400'"
                        class="flex-1 py-2.5 flex items-center justify-center transition-all">
                    <i class="fa fa-desktop text-xs"></i>
                </button>
            </div>
        </div>

        <!-- CSS Class -->
        <div class="space-y-4 pt-4 border-t border-slate-50">
            <div class="flex justify-between items-center">
                <label class="text-[12px] font-bold text-slate-700">CSS Class</label>
            </div>
            <input type="text" v-model="editingElement.settings.cssClass" 
                   class="w-full border border-slate-200 rounded px-3 py-2.5 text-[13px] focus:outline-none focus:border-[#0091ea]">
        </div>

        <!-- CSS ID -->
        <div class="space-y-4 pt-4 border-t border-slate-50 pb-8">
            <div class="flex justify-between items-center">
                <label class="text-[12px] font-bold text-slate-700">CSS ID</label>
            </div>
            <input type="text" v-model="editingElement.settings.cssId" 
                   class="w-full border border-slate-200 rounded px-3 py-2.5 text-[13px] focus:outline-none focus:border-[#0091ea]">
        </div>
    </div>
</div>
