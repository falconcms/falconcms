<x-falcon-cms::layouts.admin pro-lock-feature="ecommerce" title="Edit Category">
    <div class="mb-4">
        <h1 class="text-[23px] font-normal text-[#1d2327]">Edit Category</h1>
    </div>

    @if(session('success'))
        <div class="bg-[#fff] border-l-4 border-[#00a32a] shadow-[0_1px_11px_rgba(0,0,0,.04)] p-3 mb-4 rounded-sm text-[13px]">
            <p>{{ session('success') }}</p>
        </div>
    @endif

    @if($errors->any())
        <div class="bg-[#fff] border-l-4 border-[#d63638] shadow-[0_1px_1px_rgba(0,0,0,.04)] p-3 mb-4 rounded-sm text-[13px]">
            <p class="font-bold mb-2">Error: Please check the following fields:</p>
            <ul class="list-disc list-inside text-[#d63638] space-y-1">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="max-w-4xl bg-white border border-[#c3c4c7] p-5">
        <form action="{{ route('admin.product-categories.update', $category) }}" method="POST">
            @csrf
            @method('PUT')
            
            <table class="w-full text-[14px]">
                @php $activeLanguages = \FalconCms\Core\Models\Language::where('status', true)->get(); @endphp
                @if($activeLanguages->count() > 1)
                <tr class="align-top border-b border-[#f0f0f1]">
                    <th class="w-[200px] text-left py-4 px-2 font-medium">Language</th>
                    <td class="py-4 px-2">
                        <select name="lang_code" class="wp-input w-full max-w-[400px] h-8 py-0">
                            @foreach($activeLanguages as $lang)
                                <option value="{{ $lang->code }}" {{ $category->lang_code == $lang->code ? 'selected' : '' }}>
                                    {{ $lang->flag }} {{ $lang->name }}
                                </option>
                            @endforeach
                        </select>
                        
                        @if(!$category->origin_id)
                            <div class="mt-4 p-3 bg-gray-50 border border-gray-200 rounded-sm max-w-[400px]">
                                <label class="flex items-center text-[13px] font-bold text-[#1d2327] mb-2 cursor-pointer">
                                    <input type="checkbox" name="make_multilingual_copy" value="1" class="mr-2 rounded-sm border-[#8c8f94] text-[#2271b1]" onchange="document.getElementById('multi-lang-list').classList.toggle('hidden', !this.checked)">
                                    Make more copies?
                                </label>
                                
                                <div id="multi-lang-list" class="hidden space-y-2 pl-4">
                                    <p class="text-[11px] text-gray-500 mb-2">Clone to:</p>
                                    @php 
                                        $existingClones = \FalconCms\Core\Models\Category::where('origin_id', $category->id)->pluck('lang_code')->toArray();
                                    @endphp
                                    @foreach($activeLanguages as $lang)
                                        @if($lang->code !== $category->lang_code && !in_array($lang->code, $existingClones))
                                            <label class="flex items-center text-[12px] cursor-pointer">
                                                <input type="checkbox" name="copy_to_languages[]" value="{{ $lang->code }}" class="mr-2 rounded-sm border-[#8c8f94] text-[#2271b1]">
                                                {{ $lang->flag }} {{ $lang->name }}
                                            </label>
                                        @endif
                                    @endforeach
                                </div>
                            </div>
                        @elseif($category->origin_id)
                            <div class="mt-2 text-[12px] text-[#646970]">
                                This is a translation of 
                                <a href="{{ route('admin.product-categories.edit', $category->origin_id) }}" class="text-[#2271b1] underline">the original category</a>.
                            </div>
                        @endif
                    </td>
                </tr>
                @else
                    <input type="hidden" name="lang_code" value="{{ $category->lang_code }}">
                @endif
                <tr class="align-top border-b border-[#f0f0f1]">
                    <th class="w-[200px] text-left py-4 px-2 font-medium">Name</th>
                    <td class="py-4 px-2">
                        <input type="text" name="name" value="{{ old('name', $category->name) }}" class="wp-input w-full max-w-[400px] @error('name') border-[#d63638] @enderror" required>
                        @error('name')
                            <p class="text-[#d63638] text-[12px] mt-1">{{ $message }}</p>
                        @enderror
                        <p class="text-[12px] text-[#646970] mt-1">The name is how it appears on your site.</p>
                    </td>
                </tr>

                <tr class="align-top border-b border-[#f0f0f1]">
                    <th class="w-[200px] text-left py-4 px-2 font-medium">Slug</th>
                    <td class="py-4 px-2">
                        <input type="text" name="slug" value="{{ old('slug', $category->slug) }}" class="wp-input w-full max-w-[400px]">
                        <p class="text-[12px] text-[#646970] mt-1">The "slug" is the URL-friendly version of the name. It is usually all lowercase and contains only letters, numbers, and hyphens.</p>
                    </td>
                </tr>

                <tr class="align-top border-b border-[#f0f0f1]">
                    <th class="w-[200px] text-left py-4 px-2 font-medium">Parent Category</th>
                    <td class="py-4 px-2">
                        <select name="parent_id" class="wp-input w-full max-w-[400px]">
                            <option value="">None</option>
                            @foreach($fullTree as $cat)
                                <option value="{{ $cat->id }}" {{ $cat->id == old('parent_id', $category->parent_id) ? 'selected' : '' }}>{{ str_repeat('— ', $cat->level ?? 0) }}{{ $cat->name }}</option>
                            @endforeach
                        </select>
                        <p class="text-[12px] text-[#646970] mt-1">Categories, unlike tags, can have a hierarchy. You might have a Jazz category, and under that have children categories for Bebop and Big Band. Totally optional.</p>
                    </td>
                </tr>

                <tr class="align-top">
                    <th class="w-[200px] text-left py-4 px-2 font-medium">Description</th>
                    <td class="py-4 px-2">
                        <textarea name="description" rows="5" class="wp-input w-full max-w-[400px]">{{ old('description', $category->description) }}</textarea>
                        <p class="text-[12px] text-[#646970] mt-1">The description is not prominent by default; however, some themes may show it.</p>
                    </td>
                </tr>
            </table>

            <div class="mt-6 flex items-center space-x-4">
                <button type="submit" class="wp-btn-primary">Update</button>
                <a href="{{ route('admin.product-categories.index') }}" class="text-[#2271b1] text-[13px] underline">Back to Categories</a>
            </div>
        </form>
    </div>
</x-falcon-cms::layouts.admin>
