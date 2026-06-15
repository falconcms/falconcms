@php
    $content = $widget->settings['content'] ?? '';
@endphp

@if($content)
<div class="widget mb-12">
    @if($widget->title)
        <h4 class="widget-title">{{ $widget->title }}</h4>
    @endif
    <div class="prose prose-sm max-w-none text-slate-600 leading-relaxed
                prose-headings:text-slate-800 prose-headings:font-semibold
                prose-a:text-primary prose-a:no-underline hover:prose-a:underline
                prose-strong:text-slate-700 prose-li:my-0.5">
        {!! falcon_sanitize_html($content) !!}
    </div>
</div>
@endif
