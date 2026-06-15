<x-cms-dashboard::layouts.admin>
    <x-slot name="title">Edit User - FalconCMS</x-slot>

    <div class="px-2">
        <h1 class="text-[23px] font-normal text-[#1d2327] mb-6">Edit User</h1>

        <form action="{{ route('admin.users.update', $user) }}" method="POST" class="max-w-[800px]">
            @csrf
            @method('PUT')
            <table class="w-full border-separate border-spacing-y-6">
                <tr>
                    <th scope="row" class="w-[200px] text-left align-top pt-2"><label for="username" class="text-[14px] font-semibold text-[#1d2327]">Username</label></th>
                    <td><input type="text" name="username" id="username" value="{{ $user->username }}" class="wp-input w-[400px] h-8 shadow-sm" required></td>
                </tr>
                <tr>
                    <th scope="row" class="w-[200px] text-left align-top pt-2"><label for="name" class="text-[14px] font-semibold text-[#1d2327]">Name</label></th>
                    <td><input type="text" name="name" id="name" value="{{ $user->name }}" class="wp-input w-[400px] h-8 shadow-sm" required></td>
                </tr>
                <tr>
                    <th scope="row" class="w-[200px] text-left align-top pt-2"><label for="email" class="text-[14px] font-semibold text-[#1d2327]">Email</label></th>
                    <td><input type="email" name="email" id="email" value="{{ $user->email }}" class="wp-input w-[400px] h-8 shadow-sm" required></td>
                </tr>
                <tr>
                    <th scope="row" class="w-[200px] text-left align-top pt-2"><label for="password" class="text-[14px] font-semibold text-[#1d2327]">New Password</label></th>
                    <td>
                        <input type="password" name="password" id="password" class="wp-input w-[400px] h-8 shadow-sm mb-1" required>
                    </td>
                </tr>
                <tr>
                    <th scope="row" class="w-[200px] text-left align-top pt-2"><label for="password_confirmation" class="text-[14px] font-semibold text-[#1d2327]">Confirm New Password</label></th>
                    <td><input type="password" name="password_confirmation" id="password_confirmation" class="wp-input w-[400px] h-8 shadow-sm" required></td>
                </tr>
                @if(auth()->user()->isAdmin())
                @php $userRoleIds = $user->cmsRoleIds(); @endphp
                <tr>
                    <th scope="row" class="w-[200px] text-left align-top pt-2"><label class="text-[14px] font-semibold text-[#1d2327]">Roles</label></th>
                    <td>
                        <div class="space-y-1.5">
                            @foreach($roles as $role)
                                <label class="flex items-center gap-2 cursor-pointer">
                                    <input type="checkbox" name="roles[]" value="{{ $role->id }}"
                                           {{ in_array($role->id, old('roles', $userRoleIds)) ? 'checked' : '' }}
                                           class="w-4 h-4 rounded border-[#8c8f94] text-[#2271b1]">
                                    <span class="text-[13px] text-[#2c3338]">{{ $role->name }}</span>
                                </label>
                            @endforeach
                        </div>
                        <p class="text-[11px] text-[#646970] mt-1">Select one or more roles — permissions are the combined set of all selected roles.</p>
                        @error('roles')<p class="text-[11px] text-[#d63638] mt-1">{{ $message }}</p>@enderror
                    </td>
                </tr>
                @endif
            </table>

            @include('cms-dashboard::components.admin.dynamic-fields')

            <div class="mt-8 pt-6 border-t border-[#c3c4c7]">
                <button type="submit" class="wp-btn-primary h-[32px] px-4 font-semibold">Update User</button>
            </div>
        </form>
    </div>
</x-cms-dashboard::layouts.admin>
