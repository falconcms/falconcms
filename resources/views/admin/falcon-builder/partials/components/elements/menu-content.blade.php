<div class="space-y-8">
    <!-- General Settings Header -->
    <div class="flex items-center gap-2 pb-2 border-b border-slate-50">
        <div class="w-1.5 h-4 bg-[#2271b1] rounded-full"></div>
        <h4 class="text-[11px] font-black uppercase tracking-widest text-slate-700">General Settings</h4>
    </div>

    <div class="space-y-6">
        <!-- Select Menu -->
        <div class="space-y-4">
            {{-- A menu per device: tablet and mobile may each have their own; left on "Same as …"
                 they show the larger screen's menu (the page draws one copy per distinct menu). --}}
            <div class="flex justify-between items-center">
                <label class="text-[12px] font-bold text-slate-700">Menu</label>
                <div class="flex gap-1 items-center">
                    <button v-if="device !== 'desktop'" @click="editingElement.settings['menuId_' + device] = ''" title="Same as the larger screen" class="text-slate-300 hover:text-red-500 transition-colors">
                        <i class="fa fa-undo text-[10px]"></i>
                    </button>
                    @include('falcon-cms::admin.falcon-builder.partials.components.fields.responsive-mode', ['menu' => 'menuSelect'])
                </div>
            </div>
            <select v-if="device === 'desktop'" v-model="editingElement.settings.menuId"
                    class="w-full border border-slate-200 rounded px-3 py-2.5 text-[13px] focus:outline-none focus:border-[#0091ea] bg-white">
                <option value="">Select a menu...</option>
                <option v-for="(name, id) in falconMenusList" :value="id">@{{ name }}</option>
            </select>
            <select v-else :value="editingElement.settings['menuId_' + device] || ''"
                    @change="editingElement.settings['menuId_' + device] = $event.target.value"
                    class="w-full border border-slate-200 rounded px-3 py-2.5 text-[13px] focus:outline-none focus:border-[#0091ea] bg-white">
                <option value="">Same as @{{ device === 'mobile' ? 'Tablet' : 'Desktop' }} (@{{ falconMenusList[menuIdFor(editingElement.settings, device === 'mobile' ? 'tablet' : 'desktop')] || 'no menu' }})</option>
                <option v-for="(name, id) in falconMenusList" :value="id">@{{ name }}</option>
            </select>
            <p v-if="device !== 'desktop'" class="text-[11px] text-slate-400">The menu on @{{ device }} screens. Left on "Same as", the larger screen's menu is used.</p>
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
                    <input type="text" :value="spDisplay(editingElement.settings, 'marginTop', 'desktop', false)" @input="spInput(editingElement.settings, 'marginTop', 'desktop', $event.target.value, false)" :placeholder="spHint(editingElement.settings, 'marginTop', 'desktop', false, '0')" title="Any CSS size: 20, 20px, 2rem, 5%, 10vh" class="w-full border border-slate-200 rounded px-3 py-2 text-[13px] focus:outline-none focus:border-[#0091ea]">
                </div>
                <div class="space-y-1">
                    <label class="text-[9px] font-black text-slate-400 uppercase tracking-widest">Bottom</label>
                    <input type="text" :value="spDisplay(editingElement.settings, 'marginBottom', 'desktop', false)" @input="spInput(editingElement.settings, 'marginBottom', 'desktop', $event.target.value, false)" :placeholder="spHint(editingElement.settings, 'marginBottom', 'desktop', false, '0')" title="Any CSS size: 20, 20px, 2rem, 5%, 10vh" class="w-full border border-slate-200 rounded px-3 py-2 text-[13px] focus:outline-none focus:border-[#0091ea]">
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
                <button @click="editingElement.settings.visibility = editingElement.settings.visibility || {mobile:true, tablet:true, desktop:true}; editingElement.settings.visibility.mobile = editingElement.settings.visibility.mobile === false"
                        :class="(editingElement.settings.visibility?.mobile ?? true) ? 'bg-[#2271b1] text-white shadow-sm' : 'text-slate-400'"
                        class="flex-1 py-2.5 flex items-center justify-center border-r border-slate-100 transition-all">
                    <i class="fa fa-mobile-alt text-xs"></i>
                </button>
                <button @click="editingElement.settings.visibility = editingElement.settings.visibility || {mobile:true, tablet:true, desktop:true}; editingElement.settings.visibility.tablet = editingElement.settings.visibility.tablet === false"
                        :class="(editingElement.settings.visibility?.tablet ?? true) ? 'bg-[#2271b1] text-white shadow-sm' : 'text-slate-400'"
                        class="flex-1 py-2.5 flex items-center justify-center border-r border-slate-100 transition-all">
                    <i class="fa fa-tablet-alt text-xs"></i>
                </button>
                <button @click="editingElement.settings.visibility = editingElement.settings.visibility || {mobile:true, tablet:true, desktop:true}; editingElement.settings.visibility.desktop = editingElement.settings.visibility.desktop === false"
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
