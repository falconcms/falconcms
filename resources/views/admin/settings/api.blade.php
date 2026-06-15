<x-falcon-cms::layouts.admin>
    <x-slot name="title">REST API Settings - FalconCMS</x-slot>

    <div class="px-2">
        <h1 class="text-[23px] font-normal text-[#1d2327] mb-4">Settings</h1>
        
        @include('falcon-cms::admin.settings.nav')

        @if (session('success'))
            <div class="bg-[#edfaef] border-l-4 border-[#46b450] p-3 mb-6 text-[13px] text-[#1d2327]">
                {{ session('success') }}
            </div>
        @endif

        <div class="max-w-[800px]">
            <form action="{{ route('admin.settings.update') }}" method="POST">
                @csrf
                
                <h3 class="text-[18px] font-medium text-[#1d2327] mb-4">REST API Configuration</h3>
                
                <table class="w-full border-separate border-spacing-y-6">
                    <tr>
                        <th scope="row" class="w-[200px] text-left align-top pt-2">
                            <label class="text-[14px] font-semibold text-[#1d2327]">Enable REST API</label>
                        </th>
                        <td>
                            <label class="inline-flex items-center cursor-pointer">
                                <input type="checkbox" name="enable_rest_api" value="1" {{ ($settings['enable_rest_api'] ?? '1') == '1' ? 'checked' : '' }} class="w-4 h-4 mr-2">
                                <span class="text-[14px] text-[#1d2327]">Allow external access to CMS data via JSON API</span>
                            </label>
                        </td>
                    </tr>
                </table>

                <div class="mt-8 p-6 bg-blue-50 border border-blue-100 rounded-lg">
                    <h4 class="text-blue-800 font-bold mb-3 flex items-center gap-2">
                        <span class="material-icons text-sm">info</span>
                        How to use REST API
                    </h4>
                    <p class="text-sm text-blue-700 mb-4">Once enabled, you can access your content from any React, Vue, or Mobile app using these endpoints:</p>
                    
                    <ul class="space-y-3">
                        <li class="bg-white p-3 rounded border border-blue-100 flex items-center justify-between">
                            <code class="text-xs text-gray-700">{{ url('/api/v1/posts') }}</code>
                            <span class="text-[10px] bg-gray-100 px-2 py-1 rounded">GET</span>
                        </li>
                        <li class="bg-white p-3 rounded border border-blue-100 flex items-center justify-between">
                            <code class="text-xs text-gray-700">{{ url('/api/v1/settings') }}</code>
                            <span class="text-[10px] bg-gray-100 px-2 py-1 rounded">GET</span>
                        </li>
                    </ul>

                    <div class="mt-6">
                        <h5 class="text-blue-800 font-semibold text-xs uppercase mb-2">Example React/Vue Fetch:</h5>
                        <div class="bg-gray-900 rounded p-4 text-gray-300 font-mono text-[11px]">
                            <pre>fetch('{{ url('/api/v1/posts') }}')
  .then(res => res.json())
  .then(data => console.log(data));</pre>
                        </div>
                    </div>
                </div>

                <div class="pt-6 border-t border-gray-100 mt-8">
                    <button type="submit" class="wp-btn-primary px-4 h-8 font-semibold">Save Changes</button>
                </div>
            </form>

            <!-- ── API Tokens (for write endpoints) ───────────────────────────── -->
            <div class="mt-10 pt-8 border-t border-gray-200">
                <h3 class="text-[15px] font-semibold text-[#1d2327] mb-1">API Tokens</h3>
                <p class="text-[12px] text-[#646970] mb-4">Personal access tokens for authenticated requests (create / update / delete). Send as <code class="bg-gray-100 px-1 rounded">Authorization: Bearer &lt;token&gt;</code>. A token acts as you and inherits your permissions.</p>

                @if(session('new_api_token'))
                    <div class="bg-[#edfaef] border border-[#46b450] rounded p-3 mb-4">
                        <p class="text-[12px] text-[#1d2327] mb-1 font-semibold">New token — copy it now, it won't be shown again:</p>
                        <code class="block bg-white border border-[#c3c4c7] rounded px-3 py-2 text-[12px] break-all select-all">{{ session('new_api_token') }}</code>
                    </div>
                @endif

                <form method="POST" action="{{ route('admin.settings.api.tokens.store') }}" class="flex gap-2 mb-5 max-w-md">
                    @csrf
                    <input type="text" name="token_name" required maxlength="255" placeholder="Token name (e.g. Mobile App)" class="wp-input flex-1 h-8 shadow-sm">
                    <button type="submit" class="wp-btn-primary px-4 h-8 font-semibold whitespace-nowrap">Generate Token</button>
                </form>

                <div class="border border-[#c3c4c7] rounded overflow-hidden">
                    <table class="w-full text-[12px] text-left">
                        <thead class="bg-[#f6f7f7] text-[#646970] border-b border-[#c3c4c7]">
                            <tr>
                                <th class="px-3 py-2 font-semibold">Name</th>
                                <th class="px-3 py-2 font-semibold">Last used</th>
                                <th class="px-3 py-2 font-semibold">Created</th>
                                <th class="px-3 py-2 font-semibold text-right">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($tokens as $t)
                                <tr class="border-b border-[#f0f0f1]">
                                    <td class="px-3 py-2 text-[#1d2327] font-medium">{{ $t->name }}</td>
                                    <td class="px-3 py-2 text-[#646970]">{{ $t->last_used_at ? $t->last_used_at->diffForHumans() : 'Never' }}</td>
                                    <td class="px-3 py-2 text-[#646970]">{{ cms_date($t->created_at, 'M j, Y') }}</td>
                                    <td class="px-3 py-2 text-right">
                                        <form method="POST" action="{{ route('admin.settings.api.tokens.destroy', $t->id) }}" onsubmit="return confirm('Revoke this token? Apps using it will stop working.')">
                                            @csrf @method('DELETE')
                                            <button type="submit" class="text-[#d63638] font-semibold hover:underline">Revoke</button>
                                        </form>
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="4" class="px-3 py-4 text-center text-[#646970]">No API tokens yet.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</x-falcon-cms::layouts.admin>
