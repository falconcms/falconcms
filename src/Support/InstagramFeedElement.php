<?php

namespace FalconCms\Core\Support;

/**
 * The Instagram Feed builder element, a Pro element (builder_pro): the connected account's
 * latest posts (see Instagram), or pictures picked by hand, as a grid or a swipeable row, with
 * the profile header and a Follow button.
 *
 * Settings that are on by default are show/hide selects rather than toggles: the builder's
 * shortcode form leaves a switched-off toggle out, and reading it back would switch it on again.
 */
final class InstagramFeedElement
{
    public const TYPE = 'instagram_feed';

    /** On falcon_builder_elements. */
    public static function register(array $elements): array
    {
        $showHide = ['show' => 'Show', 'hide' => 'Hide'];
        $columns = array_combine(range(1, 8), array_map('strval', range(1, 8)));

        $elements[self::TYPE] = [
            'type' => self::TYPE,
            'name' => 'Instagram Feed',
            'icon' => 'fab fa-instagram',
            'template' => 'falcon-cms::frontend.builder.elements.instagram-feed',
            // The canvas shows the element's real output, pictures and all.
            'preview' => 'server',
            'fields' => [
                // ── General ─────────────────────────────────────────────
                'source' => ['type' => 'select', 'label' => 'Pictures from', 'default' => 'account', 'options' => [
                    'account' => 'The connected Instagram account',
                    'embed' => 'Instagram post links (any public account)',
                    'manual' => 'Pictures I choose',
                ], 'description' => 'Connected account: Appearance → Customizer → Instagram (Business or Creator). Post links work for personal accounts too, with no token.'],
                // Instagram's own embed of each post: works for any public account, personal ones
                // included, with no token — but each card is drawn by Instagram, in its design.
                'embed_posts' => ['type' => 'repeater', 'label' => 'Post links', 'default' => [],
                    'condition' => ['field' => 'source', 'value' => 'embed'],
                    'description' => 'Paste the address of each post or reel (Share → Copy link), e.g. https://www.instagram.com/p/ABC123/. The account must be public. Instagram draws each card and its script loads on the page.',
                    'fields' => [
                        ['param_name' => 'url', 'heading' => 'Post link', 'type' => 'url'],
                    ]],
                'items' => ['type' => 'repeater', 'label' => 'Pictures', 'default' => [],
                    'condition' => ['field' => 'source', 'value' => 'manual'],
                    'fields' => [
                        ['param_name' => 'image', 'heading' => 'Image', 'type' => 'image'],
                        ['param_name' => 'link', 'heading' => 'Link', 'type' => 'url'],
                        ['param_name' => 'caption', 'heading' => 'Caption', 'type' => 'text'],
                    ]],
                'username' => ['type' => 'text', 'label' => 'Instagram username', 'default' => '', 'placeholder' => 'yourpage',
                    'condition' => ['field' => 'source', 'operator' => 'in', 'value' => ['manual', 'embed']],
                    'description' => 'For the profile header and the Follow button.'],
                'count' => ['type' => 'slider', 'label' => 'Number of posts', 'default' => 8, 'min' => 1, 'max' => Instagram::FETCH, 'step' => 1],
                'layout' => ['type' => 'select', 'label' => 'Layout', 'default' => 'grid', 'options' => [
                    'grid' => 'Grid',
                    'carousel' => 'Carousel (swipe)',
                ]],
                'columns' => ['type' => 'select', 'label' => 'Columns', 'default' => '4', 'options' => $columns, 'responsive' => true],
                // Defaults for the responsive Columns above, so a phone gets 2 across, not 4.
                'columns_tablet' => ['type' => 'hidden', 'default' => '3'],
                'columns_mobile' => ['type' => 'hidden', 'default' => '2'],
                'ratio' => ['type' => 'select', 'label' => 'Picture shape', 'default' => 'square', 'options' => [
                    'square' => 'Square (1:1)',
                    'portrait' => 'Portrait (4:5)',
                    'landscape' => 'Landscape (16:9)',
                ]],
                'gap' => ['type' => 'slider', 'label' => 'Space between', 'default' => 8, 'min' => 0, 'max' => 40, 'step' => 1, 'unit' => 'px'],
                'arrows' => ['type' => 'select', 'label' => 'Arrows', 'default' => 'show', 'options' => $showHide,
                    'condition' => ['field' => 'layout', 'value' => 'carousel']],
                'header' => ['type' => 'select', 'label' => 'Profile header', 'default' => 'show', 'options' => $showHide],
                'follow_text' => ['type' => 'text', 'label' => 'Follow button text', 'default' => 'Follow on Instagram',
                    'condition' => ['field' => 'header', 'value' => 'show']],
                'caption' => ['type' => 'select', 'label' => 'Caption', 'default' => 'hover', 'options' => [
                    'hover' => 'On hover',
                    'below' => 'Below the picture',
                    'none' => 'Hidden',
                ]],
                'caption_length' => ['type' => 'number', 'label' => 'Caption length (characters)', 'default' => 90, 'min' => 10, 'max' => 500,
                    'condition' => ['field' => 'caption', 'operator' => '!=', 'value' => 'none']],
                'hover' => ['type' => 'select', 'label' => 'Hover effect', 'default' => 'overlay', 'options' => [
                    'overlay' => 'Dark overlay',
                    'zoom' => 'Zoom',
                    'both' => 'Overlay and zoom',
                    'none' => 'None',
                ]],
                'link_target' => ['type' => 'select', 'label' => 'Open posts', 'default' => '_blank', 'options' => [
                    '_blank' => 'In a new tab',
                    '_self' => 'In the same tab',
                ]],

                // ── Design ──────────────────────────────────────────────
                'radius' => ['type' => 'slider', 'label' => 'Corner radius', 'default' => 6, 'min' => 0, 'max' => 40, 'step' => 1, 'unit' => 'px', 'tab' => 'design'],
                'overlay_color' => ['type' => 'color', 'label' => 'Overlay colour', 'default' => 'rgba(0,0,0,0.45)', 'tab' => 'design'],
                'icon_color' => ['type' => 'color', 'label' => 'Icon colour', 'default' => '#ffffff', 'tab' => 'design'],
                'caption_color' => ['type' => 'color', 'label' => 'Caption colour', 'default' => '', 'tab' => 'design'],
                'caption_typo' => ['type' => 'typography', 'label' => 'Caption typography', 'tab' => 'design'],
                'name_color' => ['type' => 'color', 'label' => 'Username colour', 'default' => '', 'tab' => 'design'],
                'name_typo' => ['type' => 'typography', 'label' => 'Username typography', 'tab' => 'design'],
                'button_style' => ['type' => 'select', 'label' => 'Button style', 'default' => 'gradient', 'tab' => 'design', 'options' => [
                    'gradient' => 'Instagram gradient',
                    'solid' => 'Solid colour',
                    'outline' => 'Outline',
                ]],
                'button_color' => ['type' => 'color', 'label' => 'Button colour', 'default' => '#2271b1', 'tab' => 'design',
                    'condition' => ['field' => 'button_style', 'operator' => '!=', 'value' => 'gradient']],
                'button_text_color' => ['type' => 'color', 'label' => 'Button text colour', 'default' => '#ffffff', 'tab' => 'design'],
                'margin' => ['type' => 'dimensions', 'label' => 'Margin', 'tab' => 'design'],
            ],
        ];

        return $elements;
    }
}
