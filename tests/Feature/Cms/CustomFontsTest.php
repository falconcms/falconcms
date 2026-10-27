<?php

namespace FalconCms\Core\Tests\Feature\Cms;

use App\Models\User;
use FalconCms\Core\Support\CustomFonts\CustomFonts;
use FalconCms\Core\Support\CustomFonts\FontFile;
use FalconCms\Core\Support\MotionVisual\MotionVisuals;
use FalconCms\Core\Tests\TestCase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Customizer → Typography → Custom Font: uploaded fonts, offered everywhere a font is picked.
 *
 * Uses real fonts bundled with the package (Outfit, Inter), so what is read from a font's
 * tables and the WOFF made from it are checked against the genuine article.
 */
class CustomFontsTest extends TestCase
{
    private const OUTFIT_300 = __DIR__.'/../../../public/assets/fonts/funny/outfit-300.ttf';

    private const OUTFIT_800 = __DIR__.'/../../../public/assets/fonts/funny/outfit-800.ttf';

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
        CustomFonts::flush();
    }

    protected function tearDown(): void
    {
        CustomFonts::flush();
        parent::tearDown();
    }

    private function admin(): User
    {
        return User::forceCreate([
            'name' => 'Admin', 'email' => 'fonts-admin@example.test', 'password' => 'secret-password',
            'role_id' => (int) DB::table('roles')->where('slug', 'administrator')->value('id'),
        ]);
    }

    private function file(string $path, ?string $name = null): UploadedFile
    {
        $copy = tempnam(sys_get_temp_dir(), 'font');
        copy($path, $copy);

        return new UploadedFile($copy, $name ?? basename($path), null, null, true);
    }

    private function bytesFile(string $bytes, string $name): UploadedFile
    {
        $tmp = tempnam(sys_get_temp_dir(), 'font');
        file_put_contents($tmp, $bytes);

        return new UploadedFile($tmp, $name, null, null, true);
    }

    // ── FontFile ─────────────────────────────────────────────────────────────

    public function test_a_font_is_known_by_its_bytes_and_read_for_its_name_and_weight(): void
    {
        $ttf = (string) file_get_contents(self::OUTFIT_800);

        $this->assertSame('ttf', FontFile::format($ttf));
        $this->assertNull(FontFile::format('<?php echo 1;'));
        $this->assertSame(['family' => 'Outfit', 'weight' => 800, 'italic' => false], FontFile::describe($ttf));
    }

    public function test_a_woff_made_from_a_ttf_is_the_same_font(): void
    {
        $ttf = (string) file_get_contents(self::OUTFIT_300);
        $woff = FontFile::toWoff($ttf);

        $this->assertSame('woff', FontFile::format((string) $woff));
        $this->assertLessThan(strlen($ttf), strlen((string) $woff));
        $this->assertSame(FontFile::tables($ttf), FontFile::tables((string) $woff), 'every table survives the compression unchanged');
        $this->assertSame(FontFile::describe($ttf), FontFile::describe((string) $woff));
    }

    public function test_a_file_name_says_family_weight_and_style(): void
    {
        $this->assertSame(['family' => 'Open Sans', 'weight' => 600, 'italic' => true], FontFile::fromName('OpenSans-SemiBoldItalic.woff2'));
        $this->assertSame(['family' => 'Roboto', 'weight' => 200, 'italic' => false], FontFile::fromName('roboto_extra_light.woff2'));
        $this->assertSame(['family' => 'Lato', 'weight' => 900, 'italic' => false], FontFile::fromName('Lato-Black-webfont.woff'));
    }

    // ── Upload ───────────────────────────────────────────────────────────────

    public function test_uploading_ttf_files_makes_one_font_with_a_woff_for_every_browser(): void
    {
        $res = $this->actingAs($this->admin())->post(route('admin.customizer.custom-fonts.upload'), [
            'fonts' => [$this->file(self::OUTFIT_300), $this->file(self::OUTFIT_800), $this->bytesFile('not a font at all', 'evil.ttf'), $this->bytesFile('x', 'shell.php')],
        ], ['Accept' => 'application/json'])->assertOk();

        $this->assertCount(2, $res->json('errors'), 'the fake font and the PHP file are refused');
        $fonts = $res->json('fonts');
        $this->assertCount(1, $fonts);
        $this->assertSame('Outfit', $fonts[0]['family']);
        $this->assertSame([300, 800], array_column($fonts[0]['variants'], 'weight'));
        foreach ($fonts[0]['variants'] as $v) {
            $this->assertSame(['ttf', 'woff'], array_keys($v['files']));
            $this->assertSame(['woff'], $v['generated']);
            Storage::disk('public')->assertExists($v['files']['ttf']);
            Storage::disk('public')->assertExists($v['files']['woff']);
            $this->assertSame('woff', FontFile::format((string) Storage::disk('public')->get($v['files']['woff'])));
        }
        $this->assertStringNotContainsString('evil', implode(' ', Storage::disk('public')->allFiles()));
    }

    public function test_a_woff2_is_sorted_by_its_name_and_joins_the_same_weight(): void
    {
        $this->actingAs($this->admin());
        CustomFonts::upload([$this->file(self::OUTFIT_800)]);
        CustomFonts::upload([$this->bytesFile('wOF2'.str_repeat("\0", 60), 'Outfit-ExtraBold.woff2')]);

        $variant = CustomFonts::find('outfit')['variants'][0];
        $this->assertSame(800, $variant['weight']);
        $this->assertSame(['ttf', 'woff', 'woff2'], array_keys($variant['files']));
    }

    public function test_the_font_face_rules_offer_the_smallest_file_first_and_never_hide_text(): void
    {
        CustomFonts::upload([$this->file(self::OUTFIT_300)]);
        CustomFonts::upload([$this->bytesFile('wOF2'.str_repeat("\0", 60), 'Outfit-Light.woff2')]);

        $css = CustomFonts::css();
        $this->assertMatchesRegularExpression("#@font-face\{font-family:'Outfit';src:url\('/storage/fonts/[a-z0-9]+/[a-z0-9]+\.woff2'\) format\('woff2'\),url\('[^']+\.woff'\) format\('woff'\),url\('[^']+\.ttf'\) format\('truetype'\);font-weight:300;font-style:normal;font-display:swap\}#", $css);
        $this->assertStringStartsWith('<style id="falcon-custom-fonts">', CustomFonts::styleTag());
    }

    // ── Everywhere a font is picked ──────────────────────────────────────────

    public function test_every_page_declares_them_and_google_is_never_asked_for_them(): void
    {
        CustomFonts::upload([$this->file(self::OUTFIT_300)]);
        // the same name as a Google font: the uploaded one wins, Google is not asked for it
        $inter = $this->file(__DIR__.'/../../../public/assets/fonts/inter/inter-400.ttf');
        CustomFonts::upload([$inter]);

        ob_start();
        do_falcon_action('falcon_head');
        $head = (string) ob_get_clean();
        $this->assertStringContainsString("font-family:'Outfit'", $head);

        $url = falcon_google_font_url(['Inter', 'Outfit', 'Roboto']);
        $this->assertStringContainsString('family=Roboto', $url);
        $this->assertStringNotContainsString('Inter', $url);
        $this->assertStringNotContainsString('Outfit', $url);
    }

    public function test_the_pickers_list_them_with_their_weights_and_fallback(): void
    {
        CustomFonts::upload([$this->file(self::OUTFIT_300), $this->file(self::OUTFIT_800)]);

        $this->assertSame([['family' => 'Outfit', 'category' => 'Custom Fonts', 'variants' => ['300', '800'], 'fallback' => 'sans-serif']], CustomFonts::catalog());
        $this->assertSame("'Outfit', sans-serif", CustomFonts::stack('outfit'));

        // the builder's list and the screens that draw text in them
        $builder = (string) file_get_contents(__DIR__.'/../../../resources/views/admin/falcon-builder/partials/scripts.blade.php');
        $this->assertStringContainsString('array_merge(\FalconCms\Core\Support\CustomFonts\CustomFonts::catalog(), falcon_google_fonts())', $builder);
        $this->assertStringContainsString('fbOf(f) { return f.fallback ||', $builder);
        $this->assertStringContainsString('CustomFonts::styleTag()', (string) file_get_contents(__DIR__.'/../../../resources/views/admin/falcon-builder/partials/builder-data.blade.php'));

        // Motion Visual text
        $this->assertContains('Outfit', MotionVisuals::editorConfig()['customFonts']);
        $html = MotionVisuals::render(['mv_preset' => 'custom', 'mv_items' => MotionVisuals::encodeItems([['kind' => 'text', 'text' => 'Hi', 'font' => 'Outfit']])]);
        $this->assertStringContainsString('font-family:&#039;Outfit&#039;, sans-serif', $html);
    }

    public function test_the_customizer_shows_the_screen_and_lists_them_first(): void
    {
        CustomFonts::upload([$this->file(self::OUTFIT_300)]);

        $html = $this->actingAs($this->admin())->get(route('admin.customizer.index', ['section' => 'typography_custom_fonts']))->assertOk()->getContent();
        $this->assertStringContainsString('falconCustomFonts(', $html);
        $this->assertStringContainsString('Custom Font', $html);
        $this->assertMatchesRegularExpression('/const GOOGLE_FONTS = \{"Custom Fonts":\[\{"family":"Outfit"/', $html);
    }

    /** Save and Reset write every field of every section; the font list is not one of them. */
    public function test_saving_or_resetting_the_customizer_keeps_the_fonts(): void
    {
        CustomFonts::upload([$this->file(self::OUTFIT_300)]);
        $this->actingAs($this->admin());

        $this->post(route('admin.customizer.save'), ['_section' => 'typography'])->assertRedirect();
        $this->post(route('admin.customizer.reset'), ['all' => '1'])->assertRedirect();
        CustomFonts::flush();

        $this->assertNotNull(CustomFonts::find('Outfit'));
    }

    // ── Managing ─────────────────────────────────────────────────────────────

    public function test_renaming_to_another_fonts_name_joins_them(): void
    {
        $this->actingAs($this->admin());
        CustomFonts::upload([$this->file(self::OUTFIT_300)]);
        CustomFonts::upload([$this->file(self::OUTFIT_800, 'Brand-Bold.ttf')], null);
        $outfit = CustomFonts::find('Outfit');
        // the second upload said Outfit too — put it in a font of its own first
        CustomFonts::delete($outfit['id'], $outfit['variants'][1]['id']);
        CustomFonts::upload([$this->bytesFile('wOF2'.str_repeat("\0", 60), 'Brand-Bold.woff2')]);
        $brand = CustomFonts::find('Brand');
        $this->assertNotNull($brand);

        $this->post(route('admin.customizer.custom-fonts.update', ['font' => $brand['id']]), ['family' => 'Outfit', 'fallback' => 'serif'], ['Accept' => 'application/json'])->assertOk();
        CustomFonts::flush();

        $this->assertCount(1, CustomFonts::all());
        $this->assertSame([300, 700], array_column(CustomFonts::find('Outfit')['variants'], 'weight'));
        $this->assertSame('serif', CustomFonts::find('Outfit')['fallback']);
    }

    public function test_a_variant_can_change_weight_and_be_deleted_with_its_files(): void
    {
        $this->actingAs($this->admin());
        CustomFonts::upload([$this->file(self::OUTFIT_300)]);
        $font = CustomFonts::find('Outfit');
        $variant = $font['variants'][0];

        $this->post(route('admin.customizer.custom-fonts.variant', ['font' => $font['id'], 'variant' => $variant['id']]), ['weight' => 400, 'style' => 'italic'], ['Accept' => 'application/json'])->assertOk();
        CustomFonts::flush();
        $this->assertSame([400, 'italic'], [CustomFonts::find('Outfit')['variants'][0]['weight'], CustomFonts::find('Outfit')['variants'][0]['style']]);

        $this->delete(route('admin.customizer.custom-fonts.delete', ['font' => $font['id']]), [], ['Accept' => 'application/json'])->assertOk();
        CustomFonts::flush();
        $this->assertSame([], CustomFonts::all());
        Storage::disk('public')->assertMissing($variant['files']['ttf']);
    }

    public function test_only_someone_who_can_change_settings_can_upload(): void
    {
        $editor = User::forceCreate(['name' => 'Ed', 'email' => 'fonts-ed@example.test', 'password' => 'secret-password',
            'role_id' => (int) DB::table('roles')->where('slug', 'editor')->value('id') ?: null]);

        $res = $this->actingAs($editor)->post(route('admin.customizer.custom-fonts.upload'), ['fonts' => [$this->file(self::OUTFIT_300)]], ['Accept' => 'application/json']);

        $this->assertContains($res->getStatusCode(), [403, 404]);
        $this->assertSame([], CustomFonts::all());
    }

    public function test_a_family_name_cannot_break_out_of_the_css(): void
    {
        // quotes, braces, semicolons and angle brackets go; the letters stay
        $this->assertSame('Evilstylescriptfontx', CustomFonts::cleanFamily("Evil';}</style><script>font{x"));
        $this->assertSame('বাংলা Font', CustomFonts::cleanFamily('বাংলা Font'), 'any script is a valid name');
    }
}
