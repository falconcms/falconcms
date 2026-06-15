<x-falcon-cms::layouts.admin title="Custom Post Types">
    <div class="mb-6">
        <h1 class="text-2xl font-semibold text-gray-900">Custom Post Types</h1>
    </div>
    
    @if(session('success')) <div class="mb-4 px-4 py-3 bg-green-50 text-green-700 rounded-md">{{ session('success') }}</div> @endif
    @if(session('error')) <div class="mb-4 px-4 py-3 bg-red-50 text-red-700 rounded-md">{{ session('error') }}</div> @endif

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
        <div class="lg:col-span-2">
            <div class="bg-white shadow-sm ring-1 ring-gray-200 rounded-lg overflow-hidden">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Name</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Slug</th>
                            <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">Action</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200 bg-white">
                        @foreach($postTypes as $type)
                            <tr>
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">{{ $type->name }}</td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">{{ $type->slug }}</td>
                                <td class="px-6 py-4 whitespace-nowrap text-right text-sm">
                                    @if(!$type->is_builtin)
                                        <form action="{{ route('admin.post-types.destroy', $type) }}" method="POST">
                                            @csrf @method('DELETE')
                                            <button type="submit" class="text-red-600 font-medium">Delete</button>
                                        </form>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
        <div class="bg-white shadow-sm ring-1 ring-gray-200 rounded-lg p-6 h-fit">
            <h3 class="text-lg font-medium text-gray-900 mb-4">Add New</h3>
            <form action="{{ route('admin.post-types.store') }}" method="POST" class="space-y-4">
                @csrf
                <div><input type="text" name="name" placeholder="Name (e.g. Products)" class="w-full border-gray-300 rounded-md shadow-sm px-3 py-2 border" required></div>
                <div><input type="text" name="slug" placeholder="Slug (optional)" class="w-full border-gray-300 rounded-md shadow-sm px-3 py-2 border"></div>
                <div class="pt-2"><button type="submit" class="w-full px-4 py-2 bg-indigo-600 text-white text-sm font-medium rounded-md shadow-sm">Register</button></div>
            </form>
        </div>
    </div>
</x-falcon-cms::layouts.admin>
