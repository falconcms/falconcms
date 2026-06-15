@php
    $layout = json_decode($post->content) ?: [];
@endphp

<div class="falcon-builder-content">
    @foreach($layout as $container)
        @php $cs = $container->settings; @endphp
        <section class="lazy-container" style="
            padding-top: {{ $cs->paddingTop ?? 60 }}px;
            padding-bottom: {{ $cs->paddingBottom ?? 60 }}px;
            background-color: {{ $cs->bgColor ?? 'transparent' }};
            @if(isset($cs->bgImage) && $cs->bgImage)
                background-image: url('{{ $cs->bgImage }}');
                background-size: {{ $cs->bgImgSize ?? 'cover' }};
                background-position: {{ $cs->bgImgPosition ?? 'center' }};
            @endif
        ">
            <div class="row flex flex-wrap mx-auto {{ ($cs->contentWidth ?? 'site') === 'site' ? 'container-custom' : 'w-full' }}" style="
                justify-content: {{ $cs->justifyContent ?? 'flex-start' }};
                align-items: {{ $cs->alignItems ?? 'center' }};
            ">
                @foreach($container->columns as $column)
                    @php 
                        $cls = $column->settings;
                        $tag = $cls->htmlTag ?? 'div';
                        $link = $cls->linkUrl ?? null;
                        $visibility = $cls->visibility ?? (object)['mobile'=>true, 'tablet'=>true, 'desktop'=>true];
                        
                        $colStyle = "
                            flex-basis: " . ($column->basis ?? (100 / count($container->columns))) . ";
                            max-width: " . ($column->basis ?? (100 / count($container->columns))) . ";
                            padding: " . ($cls->paddingTop ?? 10) . "px " . ($cls->paddingRight ?? 10) . "px " . ($cls->paddingBottom ?? 10) . "px " . ($cls->paddingLeft ?? 10) . "px;
                            margin: " . ($cls->marginTop ?? 0) . "px " . ($cls->marginRight ?? 0) . "px " . ($cls->marginBottom ?? 0) . "px " . ($cls->marginLeft ?? 0) . "px;
                        ";
                    @endphp
                    
                    @if($link)
                        <a href="{{ $link }}" class="column-link {{ ($visibility->desktop ?? true) ? '' : 'hidden md:block' }} {{ ($visibility->tablet ?? true) ? '' : 'md:hidden lg:block' }} {{ ($visibility->mobile ?? true) ? '' : 'hidden sm:block' }}" 
                           style="{{ $colStyle }} text-decoration: none; color: inherit; display: flex; flex-direction: column;">
                    @endif

                    <{{ $tag }} class="column {{ !$link ? (($visibility->desktop ?? true) ? '' : 'hidden md:block') . ' ' . (($visibility->tablet ?? true) ? '' : 'md:hidden lg:block') . ' ' . (($visibility->mobile ?? true) ? '' : 'hidden sm:block') : '' }}" 
                        style="{{ !$link ? $colStyle : 'flex: 1; width: 100%;' }} @if($link) cursor: pointer; @endif"
                    >
                        @foreach(($column->elements ?? $column->children ?? []) as $element)
                            @if($element->type === 'heading')
                                <{{ $element->settings->tag ?? 'h2' }} style="
                                    color: {{ $element->settings->color ?? '#1a1a1a' }};
                                    font-size: {{ $element->settings->fontSize ?? '32' }}px;
                                    text-align: {{ $element->settings->textAlign ?? 'left' }};
                                    font-weight: {{ $element->settings->fontWeight ?? '800' }};
                                ">
                                    {{ $element->settings->title ?? '' }}
                                </{{ $element->settings->tag ?? 'h2' }}>
                            @elseif($element->type === 'text')
                                <div style="
                                    color: {{ $element->settings->color ?? '#4b5563' }};
                                    font-size: {{ $element->settings->fontSize ?? '16' }}px;
                                    text-align: {{ $element->settings->textAlign ?? 'left' }};
                                    line-height: {{ $element->settings->lineHeight ?? '1.6' }};
                                ">
                                    {!! $element->settings->content ?? '' !!}
                                </div>
                            @elseif($element->type === 'row')
                                <div class="lazy-row flex flex-wrap" style="width: 100%; margin: 0 auto; gap: {{ $element->settings->gapWidth ?? 0 }}px; align-items: {{ $element->settings->alignItems ?? 'center' }}; justify-content: {{ $element->settings->justifyContent ?? 'flex-start' }};">
                                    @foreach($element->columns as $ncolumn)
                                        <div class="nested-column" style="flex-basis: {{ $ncolumn->basis ?? '100%' }}; max-width: {{ $ncolumn->basis ?? '100%' }};">
                                            @foreach(($ncolumn->elements ?? $ncolumn->children ?? []) as $nelement)
                                                @if($nelement->type === 'heading')
                                                     <{{ $nelement->settings->tag ?? 'h3' }} style="color: {{ $nelement->settings->color ?? '#1a1a1a' }}; font-size: {{ $nelement->settings->fontSize ?? '24' }}px; text-align: {{ $nelement->settings->textAlign ?? 'left' }}; font-weight: {{ $nelement->settings->fontWeight ?? '700' }};">
                                                        {{ $nelement->settings->title ?? '' }}
                                                     </{{ $nelement->settings->tag ?? 'h3' }}>
                                                @elseif($nelement->type === 'text')
                                                     <div style="color: {{ $nelement->settings->color ?? '#4b5563' }}; font-size: {{ $nelement->settings->fontSize ?? '16' }}px; text-align: {{ $nelement->settings->textAlign ?? 'left' }};">
                                                        {!! $nelement->settings->content ?? '' !!}
                                                     </div>
                                                @endif
                                            @endforeach
                                        </div>
                                    @endforeach
                                </div>
                            @endif
                        @endforeach
                    </{{ $tag }}>

                    @if($link)
                        </a>
                    @endif
                @endforeach
            </div>
        </section>
    @endforeach
</div>
