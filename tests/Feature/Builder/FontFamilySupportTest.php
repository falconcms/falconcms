<?php

namespace FalconCms\Core\Tests\Feature\Builder;

use FalconCms\Core\Models\Post;
use FalconCms\Core\Services\BuilderShortcodeConverter;
use FalconCms\Core\Tests\TestCase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\View;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * Every font field the builder offers has to reach the page as a font-family the browser
 * accepts, and the font itself has to be loaded.
 *
 * The pickers save "Name, fallback" without quotes. For 52 Google fonts — "Baloo Da 2",
 * "Source Sans 3", "Exo 2", "Press Start 2P" — that is not valid CSS: a word starting with
 * a digit cannot be part of an unquoted family, so the browser dropped the declaration and
 * the text showed in the inherited font. Each element is rendered here with such a font in
 * each of its font fields.
 */
class FontFamilySupportTest extends TestCase
{
    private const VIEWS = __DIR__.'/../../../resources/views';

    private const FONT = 'Baloo Da 2, sans-serif';

    // ── the value ─────────────────────────────────────────────────────────────

    public function test_names_css_cannot_read_bare_are_quoted(): void
    {
        $this->assertSame("'Baloo Da 2', sans-serif", falcon_css_font_family('Baloo Da 2, sans-serif'));
        $this->assertSame("'Source Sans 3', sans-serif", falcon_css_font_family('Source Sans 3, sans-serif'));
        $this->assertSame("'Press Start 2P', cursive", falcon_css_font_family('Press Start 2P, cursive'));
        $this->assertSame("'M PLUS 1p', sans-serif", falcon_css_font_family('M PLUS 1p, sans-serif'));
    }

    public function test_names_that_are_already_valid_are_left_alone(): void
    {
        $this->assertSame('Open Sans, sans-serif', falcon_css_font_family('Open Sans, sans-serif'));
        $this->assertSame('Inter', falcon_css_font_family('Inter'));
        $this->assertSame('inherit', falcon_css_font_family('inherit'));
        $this->assertSame('Hind Siliguri, sans-serif', falcon_css_font_family(' Hind Siliguri ,  sans-serif '));
        // Already quoted stays quoted, in single quotes either way.
        $this->assertSame("'Baloo Da 2', sans-serif", falcon_css_font_family("'Baloo Da 2', sans-serif"));
        $this->assertSame("ui-monospace, 'SF Mono', monospace", falcon_css_font_family('ui-monospace, "SF Mono", monospace'));
        // A generic family is never quoted — quoted it would name a font called "serif".
        $this->assertSame('serif', falcon_css_font_family('"serif"'));
    }

    public function test_nothing_in_the_value_can_end_the_declaration_or_the_markup(): void
    {
        $css = falcon_css_font_family('Evil"; color:red} </style><script>x</script>, serif');

        foreach (['"', ';', '{', '}', '<', '>'] as $char) {
            $this->assertStringNotContainsString($char, $css);
        }
    }

    public function test_every_font_setting_of_an_element_is_fixed_nested_ones_too(): void
    {
        $fixed = falcon_css_font_settings([
            'fontFamily' => self::FONT,
            'titleFontFamily' => self::FONT,
            'db_family' => self::FONT,
            'items' => [['labelFontFamily' => self::FONT]],
            'title' => 'Baloo Da 2, sans-serif', // not a font key: untouched
        ]);

        $this->assertSame("'Baloo Da 2', sans-serif", $fixed['fontFamily']);
        $this->assertSame("'Baloo Da 2', sans-serif", $fixed['titleFontFamily']);
        $this->assertSame("'Baloo Da 2', sans-serif", $fixed['db_family']);
        $this->assertSame("'Baloo Da 2', sans-serif", $fixed['items'][0]['labelFontFamily']);
        $this->assertSame('Baloo Da 2, sans-serif', $fixed['title']);
    }

    // ── every element, every font field ──────────────────────────────────────

    /**
     * @return array<string, array{0: string, 1: string, 2: array<string, mixed>}>
     */
    public static function fontFields(): array
    {
        $acc = ['items' => [['id' => 'a1', 'title' => 'Question', 'content' => 'Answer']], 'defaultOpen' => 'first'];
        $tabs = ['items' => [['id' => 't1', 'title' => 'Tab one', 'content' => 'Panel one']]];
        $boxes = ['items' => [['id' => 'b1', 'title' => 'Box title', 'content' => 'Box text', 'linkText' => 'Read more', 'linkUrl' => '/x']]];
        $iconBox = ['title' => 'Box', 'description' => 'Words', 'readMoreText' => 'More', 'readMoreUrl' => '/x', 'showReadMore' => true];
        $gallery = ['images' => [['id' => 'g1', 'url' => '/a.jpg', 'caption' => 'Caption']], 'showCaption' => true, 'captionPosition' => 'below'];
        $table = ['rows' => [['Head'], ['Body']], 'headerRow' => true];

        return [
            // Title and Breadcrumb read their defaults straight from the saved settings, as
            // the builder always writes them; the converter fills them in the same way.
            'Title' => ['title', 'fontFamily', ['@defaults' => 'title']],
            'Text Block' => ['text_block', 'fontFamily', ['content' => '<p>Hello</p>']],
            'Heading' => ['heading', 'fontFamily', ['title' => 'Hello']],
            'Button' => ['button', 'fontFamily', ['text' => 'Go']],
            'Dual Button' => ['dual_button', 'db_family', []],
            'Breadcrumb' => ['breadcrumb', 'fontFamily', ['@defaults' => 'breadcrumb']],
            'Code Block' => ['code_block', 'fontFamily', ['code' => 'echo 1;']],
            'Item List' => ['icon_list', 'fontFamily', ['items' => [['id' => 'i1', 'text' => 'Item', 'icon' => 'fa fa-check']]]],
            'Counter number' => ['counter', 'numberFontFamily', ['endValue' => 10]],
            'Counter label' => ['counter', 'labelFontFamily', ['endValue' => 10, 'label' => 'Things']],
            'Star Rating label' => ['star_rating', 'labelFontFamily', ['label' => 'Great']],
            'Gallery caption' => ['gallery', 'captionFontFamily', $gallery],
            'Accordion title' => ['accordion', 'titleFontFamily', $acc],
            'Accordion content' => ['accordion', 'contentFontFamily', $acc],
            'Tabs tab' => ['tabs', 'tabFontFamily', $tabs],
            'Tabs content' => ['tabs', 'contentFontFamily', $tabs],
            'Icon Box title' => ['icon_box', 'titleFontFamily', $iconBox],
            'Icon Box description' => ['icon_box', 'descFontFamily', $iconBox],
            'Icon Box read more' => ['icon_box', 'readMoreFontFamily', $iconBox],
            'Content Box title' => ['content_box', 'titleFontFamily', $boxes],
            'Content Box content' => ['content_box', 'contentFontFamily', $boxes],
            'Content Box link' => ['content_box', 'linkFontFamily', $boxes],
            'Callout title' => ['callout', 'cal_title_family', ['title' => 'Note', 'content' => 'Body']],
            'Callout body' => ['callout', 'cal_body_family', ['title' => 'Note', 'content' => 'Body']],
            'Table head' => ['table', 'tbl_head_family', $table],
            'Table body' => ['table', 'tbl_body_family', $table],
            'Section Separator text' => ['section_separator', 'sep_text_family', ['sepContent' => 'text', 'sepText' => 'OR']],
            'Post Content' => ['post_content', 'fontFamily', []],
            'Post Meta' => ['post_meta', 'meta_family', []],
            'Comments title' => ['post_comments', 'cm_title_family', []],
            'Comments body' => ['post_comments', 'cm_body_family', []],
        ];
    }

    #[DataProvider('fontFields')]
    public function test_the_font_reaches_the_page_as_valid_css(string $type, string $key, array $settings): void
    {
        View::share('current_post', $this->makePost());
        if (isset($settings['@defaults'])) {
            $settings = BuilderShortcodeConverter::parseElement($settings['@defaults'], 'title="Hello"', 'Hello')['settings'];
        }

        $html = $this->renderElement($type, array_merge($settings, [$key => self::FONT]));
        $decoded = html_entity_decode($html, ENT_QUOTES | ENT_HTML5);

        $this->assertMatchesRegularExpression(
            "/font(?:-family)?\s*:[^;\"]*'Baloo Da 2'/",
            $decoded,
            "{$type}.{$key}: the font is not on the page as a quoted font-family"
        );
        $this->assertDoesNotMatchRegularExpression(
            "/font-family\s*:\s*Baloo Da 2/",
            $decoded,
            "{$type}.{$key}: printed bare, which the browser throws away"
        );
    }

    public function test_the_code_block_default_stack_is_not_html_escaped_inside_its_style(): void
    {
        $html = $this->renderElement('code_block', ['code' => 'echo 1;']);

        $this->assertStringNotContainsString('&quot;SF Mono&quot;', $html);
        $this->assertStringContainsString("'SF Mono'", $html);
    }

    public function test_the_heading_design_tab_typography_reaches_the_page(): void
    {
        $html = html_entity_decode($this->renderElement('heading', [
            'title' => 'Hi', 'fontFamily' => 'Inter, sans-serif', 'fontWeight' => '300',
            'lineHeight' => '1.6', 'color' => '#ff0000', 'textTransform' => 'uppercase',
        ]), ENT_QUOTES | ENT_HTML5);

        foreach (['font-family:Inter, sans-serif;', 'font-weight:300;', 'line-height:1.6;', 'color:#ff0000;', 'text-transform:uppercase;'] as $decl) {
            $this->assertStringContainsString($decl, $html);
        }
    }

    // ── loading the font ──────────────────────────────────────────────────────

    /**
     * A Post Card / Post Grid template, a global section, a card preview: layouts the page
     * <head> never read. A font chosen in one was never requested.
     */
    public function test_a_layout_the_head_did_not_read_links_its_own_fonts_once(): void
    {
        $layout = $this->layout('title', ['title' => 'Hi', 'fontFamily' => 'Baloo Da 2, sans-serif', 'fontWeight' => '600']);
        $seen = new \ArrayObject;

        $link = falcon_builder_font_links($layout, $seen);
        $this->assertStringContainsString('fonts.googleapis.com/css2?family=Baloo+Da+2:wght@400;600;700', $link);

        $this->assertSame('', falcon_builder_font_links($layout, $seen), 'a second card of the same grid asks again');
    }

    public function test_a_font_the_head_already_loads_is_not_linked_again(): void
    {
        $post = $this->makePost(['content' => json_encode($this->layout('title', ['title' => 'Hi', 'fontFamily' => 'Baloo Da 2, sans-serif']))]);

        $this->assertSame('', falcon_builder_font_links(json_decode($post->content, true), new \ArrayObject, $post));
    }

    public function test_a_post_card_template_loads_its_font_on_the_page(): void
    {
        $cardLayout = $this->layout('title', array_merge(
            BuilderShortcodeConverter::parseElement('title', 'title="Card title"', 'Card title')['settings'],
            ['fontFamily' => 'Exo 2, sans-serif', 'dynamic_source' => '']
        ));
        update_cms_option('falcon_post_cards', json_encode([['id' => 'pc1', 'config' => ['layout' => $cardLayout]]]));
        $this->makePost(['title' => 'One']);
        $this->makePost(['title' => 'Two']);

        $html = get_falcon_post_content(json_encode($this->layout('card', [
            'post_type' => 'post', 'posts_count' => 2, 'post_card_id' => 'pc1',
        ])));

        $this->assertStringContainsString('Card title', $html, 'the card template was not drawn');
        $this->assertSame(1, substr_count($html, 'family=Exo+2'), 'the card font is linked exactly once');
    }

    public function test_the_builder_loads_and_quotes_every_font_key_not_only_font_family(): void
    {
        $scripts = (string) file_get_contents(self::VIEWS.'/admin/falcon-builder/partials/scripts.blade.php');

        $this->assertStringContainsString('fcLoadLayoutFonts(layout.value, true);', $scripts);
        $this->assertStringNotContainsString('scanForFonts', $scripts, 'the old fontFamily-only scan is back');
        $this->assertStringContainsString("const val = fcFontCss(f.family + ', ' + this.fbOf(f));", $scripts);
    }

    // ── helpers ───────────────────────────────────────────────────────────────

    private function renderElement(string $type, array $settings): string
    {
        return get_falcon_post_content(json_encode($this->layout($type, $settings)));
    }

    private function layout(string $type, array $settings): array
    {
        return [[
            'id' => 'c1', 'type' => 'container', 'settings' => [],
            'columns' => [['id' => 'k1', 'basis' => '100%', 'settings' => [], 'elements' => [[
                'id' => 'e1', 'type' => $type, 'settings' => $settings,
            ]]]],
        ]];
    }

    private function makePost(array $attrs = []): Post
    {
        $author = (int) DB::table('users')->insertGetId([
            'name' => 'A', 'email' => uniqid().'@example.test', 'password' => 'x',
            'role_id' => (int) DB::table('roles')->where('slug', 'administrator')->value('id'),
            'created_at' => now(), 'updated_at' => now(),
        ]);

        return Post::create($attrs + [
            'title' => 'Story', 'slug' => 'p-'.uniqid(), 'type' => 'post', 'status' => 'published',
            'content' => '<p>Hello world</p>', 'published_at' => now()->subDay(), 'user_id' => $author,
        ]);
    }
}
