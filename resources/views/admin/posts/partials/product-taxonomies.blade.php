{{-- Dedicated Product Categories + Product Tags metaboxes (first-class, like Post's).
     Self-contained: works on both create and edit. Expects $post (may be a new model). --}}
@php
    $pcAll = \FalconCms\Core\Models\ProductCategory::orderBy('name')->get();
    $pcSelected = old('product_categories', ($post && $post->exists) ? $post->productCategories->pluck('id')->toArray() : []);
    $ptValue = old('product_tags', ($post && $post->exists) ? implode(', ', $post->productTags->pluck('name')->toArray()) : '');
@endphp

<!-- Product Categories Metabox -->
<div class="wp-metabox mb-6" style="margin-bottom: 24px !important; margin-top: 10px !important;">
    <div class="wp-metabox-header flex justify-between items-center cursor-pointer">
        <span>Product Categories</span> <svg class="w-4 h-4 text-[#646970]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 15l7-7 7 7"/></svg>
    </div>
    <div class="wp-metabox-content" style="padding: 10px;">
        <div id="lazy-pc-list" class="h-44 overflow-y-auto border border-[#dfdfdf] p-2 mb-3 bg-white">
            @forelse($pcAll as $cat)
                <label class="flex items-center text-[13px] text-[#2c3338] mb-1">
                    <input type="checkbox" name="product_categories[]" value="{{ $cat->id }}" {{ in_array($cat->id, $pcSelected) ? 'checked' : '' }} class="mr-2 rounded-sm border-[#8c8f94] text-[#2271b1]">
                    {{ $cat->name }}
                </label>
            @empty
                <p class="text-[12px] text-[#646970] italic" id="lazy-pc-empty">No categories found.</p>
            @endforelse
        </div>
        <a href="#" class="text-[#2271b1] text-[13px] underline" id="lazy-pc-toggle">+ Add New Category</a>
        <div class="hidden mt-3 space-y-2 pt-3 border-t border-[#f0f0f1]" id="lazy-pc-box">
            <input type="text" class="wp-input w-full text-[13px] h-8" id="lazy-pc-name" placeholder="Category Name">
            <select class="wp-input w-full text-[13px] h-8 py-0" id="lazy-pc-parent">
                <option value="">— Parent Category —</option>
                @foreach($pcAll as $cat)
                    <option value="{{ $cat->id }}">{{ $cat->name }}</option>
                @endforeach
            </select>
            <button type="button" class="wp-btn-secondary text-[12px] h-[30px] w-full mt-2" id="lazy-pc-add" data-url="{{ route('admin.product-categories.ajax') }}">Add New Category</button>
        </div>
    </div>
</div>

<!-- Product Tags Metabox -->
<div class="wp-metabox mb-6" style="margin-bottom: 24px !important; margin-top: 10px !important;">
    <div class="wp-metabox-header flex justify-between items-center cursor-pointer">
        <span>Product Tags</span> <svg class="w-4 h-4 text-[#646970]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 15l7-7 7 7"/></svg>
    </div>
    <div class="wp-metabox-content p-3">
        <div class="flex gap-2">
            <input type="text" id="lazy-pt-input" class="wp-input flex-grow text-[13px] h-8" placeholder="">
            <button type="button" id="lazy-pt-add" class="wp-btn-secondary h-8 px-4 text-[13px]">Add</button>
        </div>
        <p class="text-[11px] text-[#646970] mt-1 italic">Separate tags with commas</p>
        <div id="lazy-pt-container" class="mt-3 flex flex-wrap gap-2"></div>
        <input type="hidden" name="product_tags" id="lazy-pt-hidden" value="{{ $ptValue }}">
    </div>
</div>

<script>
(function(){
    var CSRF = '{{ csrf_token() }}';

    // ---- Product Categories quick-add ----
    var pcToggle = document.getElementById('lazy-pc-toggle');
    var pcBox = document.getElementById('lazy-pc-box');
    var pcAdd = document.getElementById('lazy-pc-add');
    if (pcToggle && pcBox) {
        pcToggle.addEventListener('click', function(e){ e.preventDefault(); pcBox.classList.toggle('hidden'); });
    }
    if (pcAdd) {
        pcAdd.addEventListener('click', function(){
            var nameEl = document.getElementById('lazy-pc-name');
            var parentEl = document.getElementById('lazy-pc-parent');
            var name = (nameEl.value || '').trim();
            if (!name) { if (window.showToast) showToast('Enter a category name.', 'warning'); return; }
            pcAdd.disabled = true;
            fetch(pcAdd.dataset.url, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': CSRF, 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' },
                body: JSON.stringify({ name: name, parent_id: parentEl.value || null })
            })
            .then(function(r){ return r.json(); })
            .then(function(cat){
                pcAdd.disabled = false;
                if (!cat || !cat.id) return;
                var list = document.getElementById('lazy-pc-list');
                var empty = document.getElementById('lazy-pc-empty');
                if (empty) empty.remove();
                var label = document.createElement('label');
                label.className = 'flex items-center text-[13px] text-[#2c3338] mb-1';
                label.innerHTML = '<input type="checkbox" name="product_categories[]" value="' + cat.id + '" checked class="mr-2 rounded-sm border-[#8c8f94] text-[#2271b1]"> ' + (cat.name || name);
                list.prepend(label);
                var opt = document.createElement('option');
                opt.value = cat.id; opt.textContent = cat.name || name;
                parentEl.appendChild(opt);
                nameEl.value = '';
                if (window.showToast) showToast('Category added.', 'success');
            })
            .catch(function(){ pcAdd.disabled = false; if (window.showToast) showToast('Could not add category.', 'error'); });
        });
    }

    // ---- Product Tags bubbles ----
    var ptInput = document.getElementById('lazy-pt-input');
    var ptAdd = document.getElementById('lazy-pt-add');
    var ptContainer = document.getElementById('lazy-pt-container');
    var ptHidden = document.getElementById('lazy-pt-hidden');
    var ptTags = (ptHidden.value || '').split(',').map(function(t){ return t.trim(); }).filter(Boolean);

    function ptRender(){
        ptContainer.innerHTML = '';
        ptTags.forEach(function(tag, i){
            var chip = document.createElement('span');
            chip.className = 'inline-flex items-center gap-1 bg-[#f0f0f1] text-[#2c3338] text-[12px] px-2 py-1 rounded';
            chip.innerHTML = tag + ' <button type="button" class="text-[#b32d2e]" data-i="' + i + '">&times;</button>';
            ptContainer.appendChild(chip);
        });
        ptHidden.value = ptTags.join(',');
    }
    function ptAddFromInput(){
        var val = (ptInput.value || '').trim();
        if (!val) return;
        val.split(',').map(function(t){ return t.trim(); }).filter(Boolean).forEach(function(t){
            if (ptTags.indexOf(t) === -1) ptTags.push(t);
        });
        ptInput.value = '';
        ptRender();
    }
    if (ptAdd) ptAdd.addEventListener('click', ptAddFromInput);
    if (ptContainer) ptContainer.addEventListener('click', function(e){
        var b = e.target.closest('button[data-i]');
        if (!b) return;
        ptTags.splice(parseInt(b.dataset.i, 10), 1);
        ptRender();
    });
    ptRender();
})();
</script>
