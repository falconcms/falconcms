@php
    // Generic auto-renderer for custom elements WITHOUT their own `template`.
    // Mirrors the builder canvas convention 1:1 (prefix relations + hover).
    $r = lazy_custom_element_render($el, $customDef);
@endphp

{{-- Outer visibility/class/id handled by master wrapper in column.blade.php --}}
<div class="lazy-custom-element w-full {{ $r['wrapperHoverClass'] }}" @if($r['wrapperStyle']) style="{!! $r['wrapperStyle'] !!}" @endif>
    @if($r['hoverCss'])<style>{!! $r['hoverCss'] !!}</style>@endif

    @foreach($r['items'] as $it)
        @php $cls = $it['hoverClass'] ?? ''; $sty = $it['style'] ?? ''; @endphp

        @if(in_array($it['kind'], ['image', 'media']))
            @if(!empty($it['value']))
                <img src="{{ $it['value'] }}" alt="" class="max-w-full h-auto {{ $cls }}" @if($sty) style="{!! $sty !!}" @endif>
            @endif

        @elseif($it['kind'] === 'icon')
            @if(!empty($it['value']))
                <i class="{{ $it['value'] }} {{ $cls }}" @if($sty) style="{!! $sty !!}" @endif></i>
            @endif

        @elseif($it['kind'] === 'button')
            @if(!empty($it['value']))
                <a href="{{ $it['url'] ?: '#' }}" target="{{ $it['target'] ?: '_self' }}"
                   class="lazy-ce-button {{ $cls }}" @if($sty) style="{!! $sty !!}" @endif>{{ $it['value'] }}</a>
            @endif

        @elseif($it['kind'] === 'repeater')
            @if(!empty($it['rows']))
                <div class="lazy-repeater {{ $cls }}" @if($sty) style="{!! $sty !!}" @endif>
                    @foreach($it['rows'] as $row)
                        <div class="lazy-repeater-item">
                            @foreach($it['subFields'] as $sf)
                                @php $sv = $row[$sf['key']] ?? null; @endphp
                                @if(!empty($sv))
                                    @if(in_array($sf['type'], ['image','media','url']))
                                        <img src="{{ $sv }}" alt="" class="max-w-full h-auto">
                                    @elseif($sf['type'] === 'icon')
                                        <i class="{{ $sv }}"></i>
                                    @else
                                        <div>{!! falcon_sanitize_html((string)$sv) !!}</div>
                                    @endif
                                @endif
                            @endforeach
                        </div>
                    @endforeach
                </div>
            @endif

        @elseif(in_array($it['kind'], ['text', 'textfield', 'textarea', 'wysiwyg']))
            @if($it['value'] !== null && $it['value'] !== '')
                <div class="{{ $cls }}" @if($sty) style="{!! $sty !!}" @endif>{!! falcon_sanitize_html((string)$it['value']) !!}</div>
            @endif

        @elseif(in_array($it['kind'], ['date', 'number', 'slider', 'select', 'radio', 'checkbox', 'url', 'link']))
            {{-- scalar value fields → plain text output --}}
            @if($it['value'] !== null && $it['value'] !== '')
                <div class="{{ $cls }}" @if($sty) style="{!! $sty !!}" @endif>{{ $it['value'] }}</div>
            @endif
        @endif
    @endforeach
</div>
