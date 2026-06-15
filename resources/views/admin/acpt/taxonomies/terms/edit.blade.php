<x-falcon-cms::layouts.admin title="Edit {{ $taxonomy->singular_name ?? $taxonomy->name }}" active-menu="acpt">
    <div class="mb-4">
        <h1 class="text-[23px] font-normal text-[#1d2327]">Edit {{ $taxonomy->singular_name ?? $taxonomy->name }}</h1>
    </div>

    @if($errors->any())
        <div class="bg-white border-l-4 border-[#d63638] shadow-sm p-3 mb-4 text-[13px]">
            <ul class="list-disc pl-5">
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="max-w-4xl bg-white border border-[#c3c4c7] p-6">
        <form action="{{ route('admin.acpt.terms.update', [$taxonomy->slug, $term->id]) }}" method="POST">
            @csrf
            @method('PUT')
            
            <div class="space-y-4">
                @php $activeLanguages = \FalconCms\Core\Models\Language::where('status', true)->get(); @endphp
                @if($activeLanguages->count() > 1)
                <div>
                    <label class="block text-[14px] text-[#1d2327] mb-1">Language</label>
                    <select name="lang_code" class="wp-input w-full md:w-1/2 h-8 py-0">
                        @foreach($activeLanguages as $lang)
                            <option value="{{ $lang->code }}" {{ $term->lang_code == $lang->code ? 'selected' : '' }}>
                                {{ $lang->flag }} {{ $lang->name }}
                            </option>
                        @endforeach
                    </select>
                    
                    @if(!$term->origin_id)
                        <div class="mt-4 p-3 bg-gray-50 border border-gray-200 rounded-sm w-full md:w-1/2">
                            <label class="flex items-center text-[13px] font-bold text-[#1d2327] mb-2 cursor-pointer">
                                <input type="checkbox" name="make_multilingual_copy" value="1" class="mr-2 rounded-sm border-[#8c8f94] text-[#2271b1]" onchange="document.getElementById('multi-lang-list').classList.toggle('hidden', !this.checked)">
                                Make more copies?
                            </label>
                            
                            <div id="multi-lang-list" class="hidden space-y-2 pl-4">
                                <p class="text-[11px] text-gray-500 mb-2">Clone to:</p>
                                @php 
                                    $existingClones = \FalconCms\Core\Models\TaxonomyTerm::where('origin_id', $term->id)->pluck('lang_code')->toArray();
                                @endphp
                                @foreach($activeLanguages as $lang)
                                    @if($lang->code !== $term->lang_code && !in_array($lang->code, $existingClones))
                                        <label class="flex items-center text-[12px] cursor-pointer">
                                            <input type="checkbox" name="copy_to_languages[]" value="{{ $lang->code }}" class="mr-2 rounded-sm border-[#8c8f94] text-[#2271b1]">
                                            {{ $lang->flag }} {{ $lang->name }}
                                        </label>
                                    @endif
                                @endforeach
                            </div>
                        </div>
                    @elseif($term->origin_id)
                        <div class="mt-2 text-[12px] text-[#646970]">
                            This is a translation of 
                            <a href="{{ route('admin.acpt.terms.edit', [$taxonomy->slug, $term->origin_id]) }}" class="text-[#2271b1] underline">the original term</a>.
                        </div>
                    @endif
                </div>
                @else
                    <input type="hidden" name="lang_code" value="{{ $term->lang_code }}">
                @endif
                <div>
                    <label class="block text-[14px] text-[#1d2327] mb-1">Name</label>
                    <input type="text" name="name" value="{{ old('name', $term->name) }}" class="wp-input w-full md:w-1/2" required>
                    <p class="text-[12px] text-[#646970] mt-1">The name is how it appears on your site.</p>
                </div>

                <div>
                    <label class="block text-[14px] text-[#1d2327] mb-1">Slug</label>
                    <input type="text" name="slug" value="{{ old('slug', $term->slug) }}" class="wp-input w-full md:w-1/2">
                    <p class="text-[12px] text-[#646970] mt-1">The “slug” is the URL-friendly version of the name. It is usually all lowercase and contains only letters, numbers, and hyphens.</p>
                </div>

                <div>
                    <label class="block text-[14px] text-[#1d2327] mb-1">Parent {{ $taxonomy->singular_name ?? $taxonomy->name }}</label>
                    <select name="parent_id" class="wp-input w-full md:w-1/2 h-8 py-0">
                        <option value="">None</option>
                        @foreach($fullTree ?? [] as $t)
                            <option value="{{ $t->id }}" {{ old('parent_id', $term->parent_id) == $t->id ? 'selected' : '' }}>{{ str_repeat('— ', $t->level ?? 0) }}{{ $t->name }}</option>
                        @endforeach
                    </select>
                    <p class="text-[12px] text-[#646970] mt-1">Assign a parent term to create a hierarchy. The term Jazz, for example, would be the child of Genre.</p>
                </div>

                <div>
                    <label class="block text-[14px] text-[#1d2327] mb-1">Description</label>
                    <textarea name="description" rows="5" class="wp-input w-full md:w-1/2">{{ old('description', $term->description) }}</textarea>
                    <p class="text-[12px] text-[#646970] mt-1">The description is not prominent by default; however, some themes may show it.</p>
                </div>

                <div class="pt-4 border-t border-[#f0f0f1]">
                    <button type="submit" class="wp-btn-primary">Update</button>
                    <a href="{{ route('admin.acpt.terms.index', $taxonomy->slug) }}" class="ml-4 text-[#2271b1] text-[13px] underline">Back to {{ $taxonomy->name }}</a>
                </div>
            </div>
        </form>
    </div>
</x-falcon-cms::layouts.admin>
