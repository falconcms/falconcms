<x-cms-dashboard::layouts.admin>
    <x-slot name="title">New Form - FalconCMS</x-slot>
    <div class="px-6 py-4 max-w-lg mx-auto">
        <h1 class="text-2xl font-black text-gray-900 mb-6">Create New Form</h1>
        <form method="POST" action="{{ route('admin.forms.store') }}" class="bg-white border border-gray-200 rounded-xl p-6 shadow-sm">
            @csrf
            <label class="block text-sm font-semibold text-gray-700 mb-1">Form Title</label>
            <input type="text" name="title" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500 focus:outline-none" placeholder="e.g. Contact Form" required>
            <button type="submit" class="mt-4 w-full bg-blue-600 hover:bg-blue-700 text-white py-2.5 rounded-lg text-sm font-semibold transition-colors">
                Create & Open Builder →
            </button>
        </form>
    </div>
</x-cms-dashboard::layouts.admin>
