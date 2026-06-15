<x-falcon-cms::layouts.admin :title="'Edit ' . $taxonomy->singular_name . ' — ' . strtoupper($cptSlug)">
    <div class="mb-4">
        <h1 class="text-[23px] font-normal text-[#1d2327]">Edit {{ $taxonomy->singular_name }}</h1>
    </div>

    @if(session('success'))
        <div class="bg-[#fff] border-l-4 border-[#00a32a] shadow-[0_1px_1px_rgba(0,0,0,.04)] p-3 mb-4 rounded-sm text-[13px]">
            <p>{{ session('success') }}</p>
        </div>
    @endif

    @if($errors->any())
        <div class="bg-[#fff] border-l-4 border-[#d63638] shadow-[0_1px_1px_rgba(0,0,0,.04)] p-3 mb-4 rounded-sm text-[13px]">
            <ul class="list-disc pl-5">
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="max-w-4xl bg-white border border-[#c3c4c7] p-5">
        <form action="{{ route('admin.acpt.terms.update', [$taxonomy->slug, $term->id]) }}" method="POST">
            @csrf
            @method('PUT')
            <input type="hidden" name="cpt_slug" value="{{ $cptSlug }}">
            
            <table class="w-full text-[14px]">
                <tr class="align-top border-b border-[#f0f0f1]">
                    <th class="w-[200px] text-left py-4 px-2 font-medium">Name <span class="text-red-500">*</span></th>
                    <td class="py-4 px-2">
                        <input type="text" name="name" value="{{ old('name', $term->name) }}" class="wp-input w-full max-w-[400px]" required>
                        <p class="text-[12px] text-[#646970] mt-1">The name is how it appears on your site.</p>
                    </td>
                </tr>

                <tr class="align-top border-b border-[#f0f0f1]">
                    <th class="w-[200px] text-left py-4 px-2 font-medium">Slug</th>
                    <td class="py-4 px-2">
                        <input type="text" name="slug" value="{{ old('slug', $term->slug) }}" class="wp-input w-full max-w-[400px]">
                        <p class="text-[12px] text-[#646970] mt-1">URL-friendly version. Lowercase letters, numbers, and hyphens only.</p>
                    </td>
                </tr>

                <tr class="align-top border-b border-[#f0f0f1]">
                    <th class="w-[200px] text-left py-4 px-2 font-medium">Parent {{ $taxonomy->singular_name }}</th>
                    <td class="py-4 px-2">
                        <select name="parent_id" class="wp-input w-full max-w-[400px]">
                            <option value="">None</option>
                            @foreach($fullParents as $parent)
                                <option value="{{ $parent->id }}" {{ $parent->id == old('parent_id', $term->parent_id) ? 'selected' : '' }}>{{ str_repeat('— ', $parent->level ?? 0) }}{{ $parent->name }}</option>
                            @endforeach
                        </select>
                    </td>
                </tr>

                <tr class="align-top">
                    <th class="w-[200px] text-left py-4 px-2 font-medium">Description</th>
                    <td class="py-4 px-2">
                        <textarea name="description" rows="5" class="wp-input w-full max-w-[400px]">{{ old('description', $term->description) }}</textarea>
                        <p class="text-[12px] text-[#646970] mt-1">The description is not prominent by default; however, some themes may show it.</p>
                    </td>
                </tr>
            </table>

            <div class="mt-6 flex items-center space-x-4">
                <button type="submit" class="wp-btn-primary">Update</button>
                <a href="{{ route('admin.acpt.terms.index', [$taxonomy->slug, 'cpt' => $cptSlug]) }}" class="text-[#2271b1] text-[13px] underline">Back to {{ $taxonomy->name }}</a>
            </div>
        </form>
    </div>
</x-falcon-cms::layouts.admin>
