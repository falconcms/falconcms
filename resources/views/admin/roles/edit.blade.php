<x-cms-dashboard::layouts.admin>
    <x-slot name="title">Edit Role - FalconCMS</x-slot>

    <div class="px-2">
        <div class="flex items-center justify-between mb-6">
            <h1 class="text-[23px] font-normal text-[#1d2327]">Edit Role: {{ $role->name }}</h1>
            <a href="{{ route('admin.roles.index') }}" class="wp-btn-secondary h-8 px-3">Back to Roles</a>
        </div>

        @if(session('success'))
            <div class="bg-[#edfaef] border-l-4 border-[#46b450] p-3 mb-4 text-[13px] text-[#1d2327] max-w-[1000px]">
                {{ session('success') }}
            </div>
        @endif
        @if(session('error'))
            <div class="bg-[#fbeaea] border-l-4 border-[#d63638] p-3 mb-4 text-[13px] text-[#1d2327] max-w-[1000px]">
                {{ session('error') }}
            </div>
        @endif

        <form action="{{ route('admin.roles.update', $role->id) }}" method="POST" class="max-w-[1000px]">
            @csrf
            @method('PUT')
            
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                <!-- Left: Basic Info -->
                <div class="lg:col-span-1 space-y-6">
                    <div class="wp-metabox">
                        <div class="wp-metabox-header">Role Details</div>
                        <div class="wp-metabox-content space-y-4">
                            <div>
                                <label for="name" class="block text-[13px] font-bold text-[#2c3338] mb-1">Name <span class="text-[#d72828]">*</span></label>
                                <input type="text" name="name" id="name" value="{{ $role->name }}" required class="wp-input w-full">
                            </div>

                            <div>
                                <label for="slug" class="block text-[13px] font-bold text-[#2c3338] mb-1">Slug <span class="text-[#d72828]">*</span></label>
                                <input type="text" name="slug" id="slug" value="{{ $role->slug }}" required class="wp-input w-full" {{ in_array($role->slug, ['administrator', 'super-admin', 'subscriber']) ? 'readonly' : '' }}>
                                <p class="text-[11px] text-[#646970] mt-1">Unique identifier. Read-only for system roles.</p>
                            </div>

                            <div>
                                <label for="description" class="block text-[13px] font-bold text-[#2c3338] mb-1">Description</label>
                                <textarea name="description" id="description" rows="3" class="wp-input w-full">{{ $role->description }}</textarea>
                            </div>
                        </div>
                    </div>

                    <div class="mt-6 flex gap-3">
                        <button type="submit" class="wp-btn-primary px-6">Update Role</button>
                        <a href="{{ route('admin.roles.index') }}" class="wp-btn-secondary px-6">Cancel</a>
                    </div>
                </div>

                <!-- Right: Dynamic Privileges -->
                <div class="lg:col-span-2">
                    <div class="wp-metabox">
                        <div class="wp-metabox-header">Privileges & Permissions</div>
                        <div class="wp-metabox-content">
                            @if($role->slug === 'super-admin')
                                <div class="bg-[#f0f6fb] border-l-4 border-[#2271b1] p-4 mb-4">
                                    <p class="text-[13px] text-[#1d2327]"><strong>Note:</strong> Super Admin has full access to every part of the system. Permissions shown here are for reference but cannot be restricted.</p>
                                </div>
                            @endif

                            <div class="space-y-8">
                                @foreach($dynamicPermissions as $groupName => $items)
                                    <div>
                                        <h3 class="text-[11px] font-bold text-[#646970] uppercase tracking-wider mb-4 border-b pb-1">{{ $groupName }}</h3>
                                        <div class="grid grid-cols-1 md:grid-cols-2 gap-x-8 gap-y-6">
                                            @foreach($items as $item)
                                                <div class="space-y-2 role-permission-group">
                                                    <label class="flex items-center group cursor-pointer">
                                                        <input type="checkbox" name="permissions[]" value="{{ $item['slug'] }}" 
                                                            class="parent-checkbox w-4 h-4 rounded border-[#8c8f94] text-[#2271b1] focus:ring-[#2271b1] mr-3"
                                                            onchange="syncParentCheck(this)"
                                                            {{ in_array($item['slug'], $rolePermissions) || $role->slug === 'super-admin' ? 'checked' : '' }}
                                                            {{ $role->slug === 'super-admin' ? 'disabled' : '' }}>
                                                        <span class="text-[14px] font-semibold text-[#1d2327] group-hover:text-[#2271b1]">{{ $item['title'] }}</span>
                                                    </label>

                                                    @if(!empty($item['children']))
                                                        <div class="ml-7 space-y-2 border-l border-[#dcdcde] pl-4">
                                                            @foreach($item['children'] as $child)
                                                                <label class="flex items-center group cursor-pointer">
                                                                    <input type="checkbox" name="permissions[]" value="{{ $child['slug'] }}" 
                                                                        class="child-checkbox w-4 h-4 rounded border-[#8c8f94] text-[#2271b1] focus:ring-[#2271b1] mr-3"
                                                                        onchange="syncChildCheck(this)"
                                                                        {{ in_array($child['slug'], $rolePermissions) || $role->slug === 'super-admin' ? 'checked' : '' }}
                                                                        {{ $role->slug === 'super-admin' ? 'disabled' : '' }}>
                                                                    <span class="text-[13px] text-[#2c3338] group-hover:text-[#2271b1]">{{ $child['title'] }}</span>
                                                                </label>
                                                            @endforeach
                                                        </div>
                                                    @endif
                                                </div>
                                            @endforeach
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </form>
    </div>

    @push('scripts')
    <script>
        function syncParentCheck(parent) {
            const group = parent.closest('.role-permission-group');
            const children = group ? group.querySelectorAll('.child-checkbox') : [];
            
            if (parent.checked && children.length > 0) {
                const anyChecked = Array.from(children).some(c => c.checked);
                if (!anyChecked) {
                    parent.checked = false;
                    window.showToast('Please select at least one sub-option (e.g. All Books, Add New) to enable this section.', 'warning');
                }
            } else if (!parent.checked) {
                children.forEach(c => c.checked = false);
            }
        }

        function syncChildCheck(child) {
            const group = child.closest('.role-permission-group');
            const parent = group ? group.querySelector('.parent-checkbox') : null;
            const children = group ? group.querySelectorAll('.child-checkbox') : [];
            const anyChecked = Array.from(children).some(c => c.checked);
            
            if (parent) {
                parent.checked = anyChecked;
            }
        }

        // Run sync on load
        window.addEventListener('load', function() {
            document.querySelectorAll('.role-permission-group').forEach(group => {
                const parent = group.querySelector('.parent-checkbox');
                const children = group.querySelectorAll('.child-checkbox');
                if (parent && children.length > 0) {
                    const anyChecked = Array.from(children).some(c => c.checked);
                    parent.checked = anyChecked;
                }
            });
        });
    </script>
    @endpush
</x-cms-dashboard::layouts.admin>
