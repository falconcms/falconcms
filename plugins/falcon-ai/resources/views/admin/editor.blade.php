{{-- Beside the post editor's Rich Editor / Page Builder tabs (falcon_admin_post_editor_tabs), for someone allowed to write with AI:
     Write with AI (brief → outline → article → SEO), Improve selection, and the SEO box's
     suggestion buttons. The work is done by falcon-ai/admin/js/ai-writer.js. --}}
@php
    $falconAiConfig = [
        'ready' => $ready,
        'routes' => [
            'outline' => route('admin.ai.write.outline'),
            'section' => route('admin.ai.write.section'),
            'meta' => route('admin.ai.write.meta'),
            'rewrite' => route('admin.ai.write.rewrite'),
            'seo' => route('admin.ai.seo.suggest'),
            'settings' => $canManage ? route('admin.ai.settings') : null,
        ],
        'csrf' => csrf_token(),
        'language' => \FalconAi\Support\Settings::language(),
        'tone' => \FalconAi\Support\Settings::tone(),
        'languages' => \FalconAi\Support\Settings::languageOptions(),
        // Loaded by the script only when the window opens, for the searchable language list.
        'tomSelect' => ['js' => asset('vendor/falcon-cms/js/tom-select.complete.min.js'), 'css' => asset('vendor/falcon-cms/css/tom-select.default.min.css')],
        'tones' => \FalconAi\Support\Settings::TONES,
        'actions' => [
            'rewrite' => 'Rewrite', 'expand' => 'Expand', 'shorten' => 'Shorten', 'simplify' => 'Simplify',
            'translate_en' => 'Translate to English', 'translate_bn' => 'বাংলায় অনুবাদ',
        ],
    ];
    $falconAiCss = falcon_plugin_asset('falcon-ai', 'admin/css/ai-writer.css');
    $falconAiJs = falcon_plugin_asset('falcon-ai', 'admin/js/ai-writer.js');
@endphp
<div id="falcon-ai-bar" class="falcon-ai-bar">
    <button type="button" class="falcon-ai-btn falcon-ai-btn--primary" data-ai-open>✨ Write with AI</button>
    @if($ready)
        <div class="falcon-ai-improve">
            <button type="button" class="falcon-ai-btn" data-ai-improve-toggle aria-haspopup="true" aria-expanded="false">Improve selection ▾</button>
            <div class="falcon-ai-menu" data-ai-improve-menu hidden role="menu">
                @foreach($falconAiConfig['actions'] as $action => $label)
                    <button type="button" role="menuitem" data-ai-action="{{ $action }}">{{ $label }}</button>
                @endforeach
            </div>
        </div>
        <span class="falcon-ai-status" data-ai-status aria-live="polite"></span>
    @else
        <span class="falcon-ai-hint">
            AI writing is not set up yet.
            @if($canManage)
                <a href="{{ route('admin.ai.settings') }}">Add an API key</a>
            @else
                Ask an administrator to add an API key under AI → Settings.
            @endif
        </span>
    @endif
</div>
<script type="application/json" id="falcon-ai-config">@json($falconAiConfig)</script>
@if($falconAiCss !== '')
    <link rel="stylesheet" href="{{ $falconAiCss }}">
@endif
@if($falconAiJs !== '')
    <script src="{{ $falconAiJs }}" defer></script>
@endif
