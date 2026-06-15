<x-falcon-cms::layouts.admin>
    <x-slot name="title">Roles - FalconCMS</x-slot>
    <x-falcon-cms::admin.delete-modal />

    <div class="px-2">
        <div class="flex justify-between items-center mb-4">
            <h1 class="text-[23px] font-normal text-[#1d2327]">Roles</h1>
            <a href="{{ route('admin.roles.create') }}" class="px-2 py-1 border border-[#2271b1] text-[#2271b1] hover:bg-[#f0f6fa] rounded-[3px] text-[13px] font-semibold">Add New Role</a>
        </div>

        @if(session('success'))
            <div class="bg-[#edfaef] border-l-4 border-[#46b450] p-3 mb-4 text-[13px] text-[#1d2327]">
                {{ session('success') }}
            </div>
        @endif

        <div class="bg-white border border-[#ccd0d4] shadow-sm">
            <table class="w-full text-[13px] text-left border-collapse">
                <thead>
                    <tr class="border-b border-[#ccd0d4] bg-[#f9f9f9]">
                        <th class="p-2 font-semibold text-[#2c3338]">Name</th>
                        <th class="p-2 font-semibold text-[#2c3338]">Slug</th>
                        <th class="p-2 font-semibold text-[#2c3338]">Description</th>
                        <th class="p-2 font-semibold text-[#2c3338]">Users</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($roles as $role)
                        <tr class="border-b border-[#f0f0f1] hover:bg-[#f6f7f7] group">
                            <td class="p-2">
                                <div class="font-semibold text-[#2271b1]">{{ $role->name }}</div>
                                <div class="opacity-0 group-hover:opacity-100 flex gap-2 mt-1 text-[12px]">
                                    @php
                                        $isSystemRole = in_array($role->slug, ['super-admin', 'administrator', 'subscriber']);
                                        $canManage = true;
                                        $currentUser = auth()->user();
                                        
                                        // Protection Logic:
                                        // 1. If target is Super Admin, only Super Admin can manage it.
                                        if ($role->slug === 'super-admin' && !$currentUser->hasRole('super-admin')) {
                                            $canManage = false;
                                        }
                                        
                                        // 2. Administrators can manage themselves and others, but not Super Admin.
                                    @endphp

                                    @if($canManage)
                                        <a href="{{ route('admin.roles.edit', $role->id) }}" class="text-[#2271b1] hover:text-[#135e96]">Edit</a>
                                    @endif

                                    @if(!$isSystemRole && $canManage)
                                        <span class="text-[#dcdcde]">|</span>
                                        <form id="delete-role-{{ $role->id }}" action="{{ route('admin.roles.destroy', $role->id) }}" method="POST" class="inline">
                                            @csrf @method('DELETE')
                                            <button type="button" class="text-[#b32d2e] hover:text-[#8a2424]" onclick="confirmRoleDelete({{ $role->id }})">Delete</button>
                                        </form>
                                    @endif
                                </div>
                            </td>
                            <td class="p-2 text-[#2c3338]">{{ $role->slug }}</td>
                            <td class="p-2 text-[#646970]">{{ $role->description }}</td>
                            <td class="p-2 text-[#2271b1]">{{ $role->users_count }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
    <script>
        window.confirmRoleDelete = async function(id) {
            const confirmed = await window.lazyConfirm({
                title: 'Delete Role',
                message: 'Are you sure you want to delete this role? This action cannot be undone and may affect users assigned to this role.',
                confirmText: 'Delete Role',
                isDanger: true
            });

            if (confirmed) {
                document.getElementById(`delete-role-${id}`).submit();
            }
        };
    </script>
</x-falcon-cms::layouts.admin>
