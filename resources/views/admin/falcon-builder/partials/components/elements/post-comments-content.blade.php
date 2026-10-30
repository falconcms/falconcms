{{-- Comments — Content tab.

     The comments are always those of the post the page is about, so there is nothing to
     pick here; these settings say what to show and what to call it. --}}

<p class="text-[11px] text-slate-500 bg-slate-50 border border-slate-100 rounded px-3 py-2">
    Shows the approved comments of the post being viewed, with replies and the form to add
    one. New comments wait for approval under Comments, unless the writer already has an
    approved one.
</p>

{{-- ══ LIST ══ --}}
<div class="space-y-4">
    <div class="text-[11px] font-bold text-slate-600 uppercase tracking-wide">Comment list</div>

    <div>
        <label class="text-[11px] font-bold text-slate-500 block mb-1.5">Heading</label>
        <input type="text" v-model="editingElement.settings.title" placeholder="Comments"
               class="w-full border border-slate-200 rounded px-3 py-2 text-[13px] focus:outline-none focus:border-[#0091ea]">
        <p class="text-[11px] text-slate-500 mt-1">Clear it to show no heading.</p>
    </div>

    <label class="flex items-center gap-2 cursor-pointer">
        <input type="checkbox" :checked="editingElement.settings.showCount !== false" @change="editingElement.settings.showCount = $event.target.checked" class="rounded border-slate-300 text-[#0091ea] focus:ring-0">
        <span class="text-[12px] text-slate-600">Show the number of comments</span>
    </label>

    <div>
        <label class="text-[11px] font-bold text-slate-500 block mb-1.5">Order</label>
        <select :value="editingElement.settings.order || 'oldest'" @change="editingElement.settings.order = $event.target.value" class="w-full border border-slate-200 rounded px-3 py-2 text-[13px] focus:outline-none focus:border-[#0091ea]">
            <option value="oldest">Oldest first</option>
            <option value="newest">Newest first</option>
        </select>
    </div>

    <label class="flex items-center gap-2 cursor-pointer">
        <input type="checkbox" :checked="editingElement.settings.showAvatars !== false" @change="editingElement.settings.showAvatars = $event.target.checked" class="rounded border-slate-300 text-[#0091ea] focus:ring-0">
        <span class="text-[12px] text-slate-600">Show avatars (the writer's initial)</span>
    </label>

    <label class="flex items-center gap-2 cursor-pointer">
        <input type="checkbox" :checked="editingElement.settings.showDates !== false" @change="editingElement.settings.showDates = $event.target.checked" class="rounded border-slate-300 text-[#0091ea] focus:ring-0">
        <span class="text-[12px] text-slate-600">Show when each was written</span>
    </label>

    <label class="flex items-center gap-2 cursor-pointer">
        <input type="checkbox" :checked="editingElement.settings.allowReplies !== false" @change="editingElement.settings.allowReplies = $event.target.checked" class="rounded border-slate-300 text-[#0091ea] focus:ring-0">
        <span class="text-[12px] text-slate-600">Allow replies</span>
    </label>

    <div>
        <label class="text-[11px] font-bold text-slate-500 block mb-1.5">When there are none</label>
        <input type="text" v-model="editingElement.settings.emptyText" placeholder="No comments yet. Be the first to share your thoughts!"
               class="w-full border border-slate-200 rounded px-3 py-2 text-[13px] focus:outline-none focus:border-[#0091ea]">
    </div>
</div>

{{-- ══ FORM ══ --}}
<div class="space-y-4">
    <div class="text-[11px] font-bold text-slate-600 uppercase tracking-wide">Form</div>

    <label class="flex items-center gap-2 cursor-pointer">
        <input type="checkbox" :checked="editingElement.settings.showForm !== false" @change="editingElement.settings.showForm = $event.target.checked" class="rounded border-slate-300 text-[#0091ea] focus:ring-0">
        <span class="text-[12px] text-slate-600">Let visitors leave a comment</span>
    </label>

    <template v-if="editingElement.settings.showForm !== false">
        <div>
            <label class="text-[11px] font-bold text-slate-500 block mb-1.5">Form heading</label>
            <input type="text" v-model="editingElement.settings.formTitle" placeholder="Leave a Reply"
                   class="w-full border border-slate-200 rounded px-3 py-2 text-[13px] focus:outline-none focus:border-[#0091ea]">
        </div>
        <div>
            <label class="text-[11px] font-bold text-slate-500 block mb-1.5">Button</label>
            <input type="text" v-model="editingElement.settings.buttonText" placeholder="Post Comment"
                   class="w-full border border-slate-200 rounded px-3 py-2 text-[13px] focus:outline-none focus:border-[#0091ea]">
        </div>
    </template>
</div>

<div class="pt-4 border-t border-slate-50">
    @include('falcon-cms::admin.falcon-builder.partials.components.fields.element-visibility')
</div>

<div class="pt-4 border-t border-slate-50">
    @include('falcon-cms::admin.falcon-builder.partials.components.fields.css-attributes')
</div>
