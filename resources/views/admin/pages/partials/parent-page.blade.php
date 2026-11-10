{{-- Page Attributes → Parent Page. A child page lives under its parent: /parent-page/child-page.
     $parentOptions comes from falcon_page_parent_options() — the page itself and its sub-pages
     are already left out, so nothing here can make a page its own ancestor. --}}
@php
    $currentParent = (int) old('parent_id', $page->parent_id ?? 0);
    // The site root for this page's language, so the Permalink line can follow the choice.
    $__ppLang = (string) (($page->lang_code ?? null) ?: request('lang', falcon_default_language()));
    $__ppRoot = rtrim(url($__ppLang === falcon_default_language() ? '/' : $__ppLang), '/').'/';
@endphp
<div class="wp-metabox mb-0">
    <div class="wp-metabox-header flex justify-between items-center cursor-pointer"><span>Page Attributes</span> <svg class="w-4 h-4 text-[#646970]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 15l7-7 7 7"/></svg></div>
    <div class="wp-metabox-content p-3">
        <label for="parent-page-select" class="block text-[13px] font-semibold text-[#1d2327] mb-1.5">Parent Page</label>
        <select name="parent_id" id="parent-page-select" class="wp-input w-full text-[13px] h-8 py-0" data-root="{{ $__ppRoot }}">
            <option value="" data-path="">(no parent)</option>
            @foreach($parentOptions ?? [] as $opt)
                <option value="{{ $opt['id'] }}" data-path="{{ $opt['path'] }}" @selected($currentParent === $opt['id'])>{{ str_repeat('— ', $opt['depth']) }}{{ $opt['title'] }}</option>
            @endforeach
        </select>
        @error('parent_id')
            <p class="text-[12px] text-[#b32d2e] mt-1.5">{{ $message }}</p>
        @enderror
        <p class="text-[12px] text-[#646970] mt-1.5 leading-relaxed">A sub-page's address includes its parent's: <code class="text-[11px]">/parent-page/this-page</code>. Old links to this page keep working — they redirect to the new address.</p>
    </div>
</div>
<script>
(function () {
    // Keep the Permalink line above the editor in step with the chosen parent (it becomes the
    // real address on save). Both page screens name the base part the same way.
    var sel = document.getElementById('parent-page-select');
    if (!sel) return;
    sel.addEventListener('change', function () {
        var opt = sel.options[sel.selectedIndex];
        var path = opt ? (opt.getAttribute('data-path') || '') : '';
        var base = sel.getAttribute('data-root') + (path ? path + '/' : '');
        ['permalink-base-display', 'permalink-base-edit'].forEach(function (id) {
            var el = document.getElementById(id);
            if (el) el.textContent = base;
        });
    });
})();
</script>
