{{-- Social share row — shown only when Customizer → Blog → Single Blog → Show Share Buttons is on. --}}
@if(get_cms_option('theme_single_show_share', '0') === '1')
@php
    $permalink = $permalink ?? get_falcon_permalink($post);
    $shareUrl  = urlencode($permalink);
    $shareTxt  = urlencode($post->title);
    $shareLinks = [
        ['icon' => 'facebook', 'label' => 'Facebook', 'url' => "https://www.facebook.com/sharer/sharer.php?u={$shareUrl}"],
        ['icon' => 'twitter',  'label' => 'X',        'url' => "https://twitter.com/intent/tweet?url={$shareUrl}&text={$shareTxt}"],
        ['icon' => 'linkedin', 'label' => 'LinkedIn', 'url' => "https://www.linkedin.com/sharing/share-offsite/?url={$shareUrl}"],
        ['icon' => 'message-circle', 'label' => 'WhatsApp', 'url' => "https://api.whatsapp.com/send?text={$shareTxt}%20{$shareUrl}"],
    ];
@endphp
<div class="mt-12 pt-8 border-t border-slate-100 flex items-center gap-3 flex-wrap">
    <span class="text-xs font-black uppercase tracking-widest text-slate-400 mr-2">Share:</span>
    @foreach($shareLinks as $s)
        <a href="{{ $s['url'] }}" target="_blank" rel="noopener noreferrer" aria-label="Share on {{ $s['label'] }}"
           class="w-10 h-10 flex items-center justify-center rounded-full bg-slate-50 text-slate-500 hover:bg-primary hover:text-white transition-all">
            <i data-lucide="{{ $s['icon'] }}" class="w-4 h-4"></i>
        </a>
    @endforeach
</div>
@endif
