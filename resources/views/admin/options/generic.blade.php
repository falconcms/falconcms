<x-cms-dashboard::layouts.admin>
    <x-slot name="title">{{ $config['title'] }} - FalconCMS</x-slot>

    <div class="px-2">
        <div class="flex items-center gap-3 mb-8">
            <div class="w-10 h-10 bg-indigo-50 rounded-xl flex items-center justify-center text-indigo-600">
                @if(isset($config['icon']) && str_starts_with($config['icon'], '<svg'))
                    {!! $config['icon'] !!}
                @else
                    <span class="material-symbols-outlined">{{ $config['icon'] ?? 'settings' }}</span>
                @endif
            </div>
            <h1 class="text-[23px] font-normal text-[#1d2327]">{{ $config['title'] }}</h1>
        </div>

        @if(session('success'))
            <div class="bg-[#edfaef] border-l-4 border-[#46b450] p-3 mb-6 text-[13px] text-[#1d2327]">
                {{ session('success') }}
            </div>
        @endif

        <form action="{{ route('admin.options.update', $slug) }}" method="POST" enctype="multipart/form-data" class="max-w-[800px]">
            @csrf
            <table class="w-full border-separate border-spacing-y-8">
                @foreach($config['fields'] as $key => $field)
                    <tr>
                        <th scope="row" class="w-[250px] text-left align-top pt-2">
                            <label for="{{ $key }}" class="text-[14px] font-semibold text-[#1d2327]">{{ $field['label'] }}</label>
                        </th>
                        <td>
                            @if($field['type'] === 'text')
                                <input type="text" name="{{ $key }}" id="{{ $key }}" 
                                       value="{{ $settings[$key] ?? ($field['default'] ?? '') }}" 
                                       placeholder="{{ $field['placeholder'] ?? '' }}"
                                       class="wp-input w-full h-8 shadow-sm">
                            @elseif($field['type'] === 'number')
                                <input type="number" name="{{ $key }}" id="{{ $key }}" 
                                       value="{{ $settings[$key] ?? ($field['default'] ?? '') }}" 
                                       class="wp-input w-[150px] h-8 shadow-sm">
                            @elseif($field['type'] === 'textarea')
                                <textarea name="{{ $key }}" id="{{ $key }}" rows="5" 
                                          placeholder="{{ $field['placeholder'] ?? '' }}"
                                          class="wp-input w-full p-2 shadow-sm min-h-[120px]">{{ $settings[$key] ?? ($field['default'] ?? '') }}</textarea>
                            @elseif($field['type'] === 'select')
                                <select name="{{ $key }}" id="{{ $key }}" class="wp-input w-[250px] h-8 shadow-sm">
                                    @foreach($field['options'] ?? [] as $val => $label)
                                        <option value="{{ $val }}" {{ ($settings[$key] ?? ($field['default'] ?? '')) == $val ? 'selected' : '' }}>{{ $label }}</option>
                                    @endforeach
                                </select>
                            @elseif($field['type'] === 'checkbox')
                                <label class="inline-flex items-center cursor-pointer">
                                    <input type="checkbox" name="{{ $key }}" value="1" 
                                           {{ ($settings[$key] ?? ($field['default'] ?? '0')) == '1' ? 'checked' : '' }}
                                           class="w-4 h-4 text-indigo-600 border-gray-300 rounded focus:ring-indigo-500">
                                    <span class="ml-2 text-sm text-gray-600">{{ $field['checkbox_label'] ?? 'Enable' }}</span>
                                </label>
                            @elseif($field['type'] === 'image')
                                <div class="flex items-center gap-4">
                                    @if(isset($settings[$key]))
                                        <div class="w-16 h-16 border rounded bg-slate-50 flex items-center justify-center overflow-hidden">
                                            <img src="{{ asset($settings[$key]) }}" class="max-w-full max-h-full object-contain">
                                        </div>
                                    @endif
                                    <input type="file" name="{{ $key }}" id="{{ $key }}" class="text-sm text-slate-500 file:mr-4 file:py-1 file:px-4 file:rounded file:border-0 file:text-sm file:font-semibold file:bg-indigo-50 file:text-indigo-700 hover:file:bg-indigo-100">
                                </div>
                            @elseif($field['type'] === 'media')
                                <div class="flex items-center gap-4">
                                    <div id="media-preview-{{ $key }}" class="w-16 h-16 border rounded bg-slate-50 flex items-center justify-center overflow-hidden {{ empty($settings[$key]) ? 'hidden' : '' }}">
                                        @if(!empty($settings[$key]))
                                            <img src="{{ asset('storage/' . $settings[$key]) }}" class="max-w-full max-h-full object-contain">
                                        @endif
                                    </div>
                                    <div class="flex flex-col gap-2">
                                        <button type="button" class="wp-btn-secondary h-8 px-4 text-[12px] open-media-for-setting" data-target="{{ $key }}">Choose from Library</button>
                                        <input type="hidden" name="{{ $key }}" id="input-{{ $key }}" value="{{ $settings[$key] ?? '' }}">
                                        @if(!empty($settings[$key]))
                                            <button type="button" class="text-[#b32d2e] text-[11px] underline text-left remove-setting-media" data-target="{{ $key }}">Remove</button>
                                        @endif
                                    </div>
                                </div>
                            @endif
                            
                            @if(isset($field['desc']))
                                <p class="text-[12px] text-[#646970] mt-1 italic">{{ $field['desc'] }}</p>
                            @endif
                        </td>
                    </tr>
                @endforeach
            </table>

            <div class="mt-8 pt-6 border-t border-[#c3c4c7]">
                <button type="submit" class="wp-btn-primary h-[32px] px-6 font-semibold">Save Changes</button>
            </div>
        </form>
    </div>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            document.querySelectorAll('.open-media-for-setting').forEach(btn => {
                btn.addEventListener('click', function() {
                    const target = this.getAttribute('data-target');
                    window.openMediaModal(function(media) {
                        document.getElementById('input-' + target).value = media.path;
                        const preview = document.getElementById('media-preview-' + target);
                        preview.innerHTML = `<img src="/storage/${media.path}" class="max-w-full max-h-full object-contain">`;
                        preview.classList.remove('hidden');
                    });
                });
            });

            document.querySelectorAll('.remove-setting-media').forEach(btn => {
                btn.addEventListener('click', function() {
                    const target = this.getAttribute('data-target');
                    document.getElementById('input-' + target).value = '';
                    document.getElementById('media-preview-' + target).classList.add('hidden');
                    this.remove();
                });
            });
        });
    </script>
</x-cms-dashboard::layouts.admin>
