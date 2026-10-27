<?php

namespace FalconCms\Core\Tests\Feature\Cms;

use App\Models\User;
use FalconCms\Core\Models\Media;
use FalconCms\Core\Support\CustomFonts\CustomFonts;
use FalconCms\Core\Support\CustomFonts\FontFile;
use FalconCms\Core\Support\MotionVisual\MotionVisuals;
use FalconCms\Core\Tests\TestCase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Customizer → Typography → Custom Font: font faces whose files come from the Media Library,
 * offered everywhere a font is picked.
 *
 * Uses real fonts bundled with the package (Outfit, Inter), so what is read from a font and
 * the WOFF made from it are checked against the genuine article.
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

    /** A font in the Media Library, as an upload would leave it. */
    private function media(string $bytes, string $name): string
    {
        $path = 'media/2026/10/'.$name;
        Storage::disk('public')->put($path, $bytes);
        Media::create(['title' => pathinfo($name, PATHINFO_FILENAME), 'filename' => $name, 'path' => $path,
            'mime_type' => 'font/'.pathinfo($name, PATHINFO_EXTENSION), 'original_size' => strlen($bytes), 'compressed_size' => strlen($bytes)]);

        return $path;
    }

    private function upload(string $bytes, string $name): UploadedFile
    {
        $tmp = tempnam(sys_get_temp_dir(), 'font');
        file_put_contents($tmp, $bytes);

        return new UploadedFile($tmp, $name, null, null, true);
    }

    private function outfit(int $weight = 300): string
    {
        return (string) file_get_contents($weight === 800 ? self::OUTFIT_800 : self::OUTFIT_300);
    }

    /**
     * Outfit made a variable font: the same tables plus an fvar with a wght axis (and a wdth
     * axis before it, so the weight axis has to be found, not assumed first).
     */
    private function variable(float $min, float $max): string
    {
        $tables = FontFile::tables($this->outfit());
        $axis = fn (string $tag, float $lo, float $hi) => $tag.pack('NNNnn', (int) ($lo * 65536), (int) ($lo * 65536), (int) ($hi * 65536), 0, 256);
        $tables['fvar'] = pack('nnnnnnnn', 1, 0, 16, 2, 2, 20, 0, 12).$axis('wdth', 75, 100).$axis('wght', $min, $max);
        ksort($tables, SORT_STRING);
        $offset = 12 + 16 * count($tables);
        $dir = '';
        $body = '';
        foreach ($tables as $tag => $data) {
            $dir .= pack('a4NNN', $tag, 0, $offset, strlen($data));
            $data .= str_repeat("\0", (4 - strlen($data) % 4) % 4);
            $body .= $data;
            $offset += strlen($data);
        }

        return "\x00\x01\x00\x00".pack('nnnn', count($tables), 0, 0, 0).$dir.$body;
    }

    // ── FontFile ─────────────────────────────────────────────────────────────

    public function test_a_font_is_known_by_its_bytes_and_read_for_its_name_and_weight(): void
    {
        $this->assertSame('ttf', FontFile::format($this->outfit(800)));
        $this->assertNull(FontFile::format('<?php echo 1;'));
        $this->assertSame(['family' => 'Outfit', 'weight' => 800, 'italic' => false, 'weights' => null], FontFile::describe($this->outfit(800)));
        $this->assertSame(['family' => 'Open Sans', 'weight' => 600, 'italic' => true], FontFile::fromName('OpenSans-SemiBoldItalic.woff2'));
    }

    public function test_a_woff_made_from_a_ttf_is_the_same_font(): void
    {
        $woff = (string) FontFile::toWoff($this->outfit());

        $this->assertSame('woff', FontFile::format($woff));
        $this->assertLessThan(strlen($this->outfit()), strlen($woff));
        $this->assertSame(FontFile::tables($this->outfit()), FontFile::tables($woff), 'every table survives the compression unchanged');
    }

    public function test_a_variable_fonts_weight_range_is_read_from_its_axis(): void
    {
        $this->assertSame([100, 900], FontFile::describe($this->variable(100, 900))['weights']);
        // kept inside the axis: 250–780 offers 300–700, never a weight the font does not have
        $this->assertSame([300, 700], FontFile::describe($this->variable(250, 780))['weights']);
        $this->assertSame([100, 900], FontFile::describe((string) FontFile::toWoff($this->variable(100, 900)))['weights'], 'and from the WOFF made of it');
    }

    // ── Media Library ────────────────────────────────────────────────────────

    public function test_the_library_takes_a_real_font_and_refuses_a_renamed_file(): void
    {
        $this->actingAs($this->admin());
        $this->setCmsOptions(['performance_allowed_formats' => json_encode(['jpg', 'png'])]);

        // From the Custom Font screen: allowed for someone who may change settings, though fonts
        // are not in Allowed Upload Formats — the contents are what is checked.
        $ok = $this->post(route('admin.media.store'), ['file' => $this->upload($this->outfit(), 'Outfit-Light.ttf'), 'purpose' => 'font'], ['Accept' => 'application/json'])
            ->assertOk()->json('data');
        $this->assertSame('font/ttf', $ok['mime_type']);
        Storage::disk('public')->assertExists($ok['path']);

        $this->post(route('admin.media.store'), ['file' => $this->upload('<?php system($_GET[1]);', 'evil.woff2'), 'purpose' => 'font'], ['Accept' => 'application/json'])
            ->assertStatus(422)->assertJsonPath('message', 'That .woff2 file is not a woff2 font.');

        // Without the purpose, the site's Allowed Upload Formats still decide.
        $this->post(route('admin.media.store'), ['file' => $this->upload($this->outfit(), 'other.ttf')], ['Accept' => 'application/json'])
            ->assertStatus(422);
    }

    public function test_only_someone_who_can_change_settings_gets_past_the_allowed_formats(): void
    {
        $this->setCmsOptions(['performance_allowed_formats' => json_encode(['jpg', 'png'])]);
        $author = User::forceCreate(['name' => 'Au', 'email' => 'fonts-au@example.test', 'password' => 'secret-password',
            'role_id' => (int) DB::table('roles')->where('slug', 'author')->value('id') ?: null]);

        $res = $this->actingAs($author)->post(route('admin.media.store'), ['file' => $this->upload($this->outfit(), 'x.ttf'), 'purpose' => 'font'], ['Accept' => 'application/json']);

        $this->assertNotSame(200, $res->getStatusCode());
        $this->assertSame([], Storage::disk('public')->allFiles('media'));
    }

    public function test_the_library_lists_fonts_of_one_format(): void
    {
        $this->actingAs($this->admin());
        $this->media($this->outfit(), 'a.ttf');
        $this->media('wOF2'.str_repeat("\0", 60), 'b.woff2');

        $this->media('x', 'photo.jpg');

        // what the library's font mode (?type=font&font_format=…) asks for
        $this->assertSame(['media/2026/10/b.woff2'], Media::fonts('woff2')->pluck('path')->all());
        $this->assertEqualsCanonicalizing(['media/2026/10/a.ttf', 'media/2026/10/b.woff2'], Media::fonts()->pluck('path')->all());
        $this->assertStringContainsString("\$query->fonts((string) \$request->input('font_format', ''))", (string) file_get_contents(__DIR__.'/../../../src/Http/Controllers/Admin/MediaController.php'));
    }

    // ── Saving ───────────────────────────────────────────────────────────────

    public function test_saving_keeps_library_fonts_and_makes_a_woff_beside_a_ttf(): void
    {
        $ttf = $this->media($this->outfit(), 'outfit-300.ttf');

        $notes = CustomFonts::save([['family' => 'Brand Sans', 'weight' => 300, 'style' => 'normal', 'files' => ['ttf' => $ttf]]]);

        $this->assertSame([], $notes);
        $e = CustomFonts::all()[0];
        $this->assertSame(['ttf' => $ttf], $e['files']);
        $this->assertMatchesRegularExpression('#^fonts/[a-z0-9]+-[a-f0-9]{10}\.woff$#', $e['made']['woff']);
        $this->assertSame(FontFile::tables($this->outfit()), FontFile::tables((string) Storage::disk('public')->get($e['made']['woff'])));
        $this->assertMatchesRegularExpression("#@font-face\{font-family:'Brand Sans';src:url\('/storage/fonts/[^']+\.woff'\) format\('woff'\),url\('/storage/media/2026/10/outfit-300\.ttf'\) format\('truetype'\);font-weight:300;font-style:normal;font-display:swap\}#", CustomFonts::css());
    }

    /** The screen is not trusted: every file is checked again on the server. */
    public function test_files_that_are_not_library_fonts_of_their_slot_are_left_out(): void
    {
        $ttf = $this->media($this->outfit(), 'real.ttf');
        Storage::disk('public')->put('media/2026/10/stray.woff2', 'wOF2'.str_repeat("\0", 20)); // on disk, not in the library
        $fake = $this->media('not a font', 'fake.woff');

        $notes = CustomFonts::save([['family' => 'X', 'files' => [
            'woff2' => 'media/2026/10/stray.woff2',
            'woff' => $fake,
            'otf' => '../.env',
            'ttf' => $ttf,
        ]]]);

        $this->assertCount(3, $notes);
        $this->assertSame(['ttf' => $ttf], CustomFonts::all()[0]['files']);
        $this->assertStringNotContainsString('.env', CustomFonts::css());
    }

    public function test_faces_with_one_name_are_one_family(): void
    {
        $a = $this->media($this->outfit(300), 'o3.ttf');
        $b = $this->media($this->outfit(800), 'o8.ttf');
        CustomFonts::save([
            ['family' => 'Outfit Custom', 'weight' => 300, 'files' => ['ttf' => $a]],
            ['family' => 'outfit custom', 'weight' => 800, 'style' => 'italic', 'files' => ['ttf' => $b]],
            ['family' => 'No File Yet', 'weight' => 400, 'files' => []],
        ]);

        $this->assertSame([['family' => 'Outfit Custom', 'category' => 'Custom Fonts', 'variants' => ['300', '800'], 'fallback' => 'sans-serif']], CustomFonts::catalog());
        $this->assertSame("'Outfit Custom', sans-serif", CustomFonts::stack('OUTFIT CUSTOM'));
    }

    public function test_a_variable_face_declares_its_range_and_offers_every_weight_in_it(): void
    {
        $v = $this->media($this->variable(200, 800), 'brand-vf.ttf');
        $one = $this->media($this->outfit(800), 'o8.ttf');
        CustomFonts::save([
            ['family' => 'Brand', 'weight' => 200, 'weight_to' => 800, 'files' => ['ttf' => $v]],
            ['family' => 'Brand', 'weight' => 900, 'style' => 'italic', 'files' => ['ttf' => $one]],
            // a range the wrong way round, or of one weight, is just a weight
            ['family' => 'Other', 'weight' => 700, 'weight_to' => 300, 'files' => ['ttf' => $one]],
        ]);

        $this->assertStringContainsString("font-family:'Brand';src:", CustomFonts::css());
        $this->assertStringContainsString(';font-weight:200 800;font-style:normal;', CustomFonts::css());
        $this->assertStringContainsString(';font-weight:700;font-style:normal;', CustomFonts::css());
        $this->assertSame(['200', '300', '400', '500', '600', '700', '800', '900'], CustomFonts::catalog()[0]['variants']);
        $this->assertSame(0, CustomFonts::all()[2]['weight_to']);
    }

    public function test_the_screen_is_told_what_a_library_font_says_about_itself(): void
    {
        $admin = $this->admin();
        $v = $this->media($this->variable(100, 900), 'brand-vf.ttf');
        $bold = $this->media($this->outfit(800), 'outfit-800.ttf');
        $w2 = $this->media('wOF2'.str_repeat("\0", 40), 'OpenSans-SemiBoldItalic.woff2');
        $route = fn (string $p) => route('admin.customizer.font-info', ['path' => $p]);

        $this->actingAs($admin)->getJson($route($bold))->assertOk()
            ->assertExactJson(['family' => 'Outfit', 'weight' => 800, 'weight_to' => 0, 'style' => 'normal', 'from' => 'font']);
        $this->actingAs($admin)->getJson($route($v))->assertOk()->assertJson(['weight' => 100, 'weight_to' => 900, 'from' => 'font']);
        // a WOFF2 cannot be opened here: its name is what there is
        $this->actingAs($admin)->getJson($route($w2))->assertOk()
            ->assertExactJson(['family' => 'Open Sans', 'weight' => 600, 'weight_to' => 0, 'style' => 'italic', 'from' => 'name']);

        // only library fonts, and only for someone who can change settings
        Storage::disk('public')->put('media/2026/10/stray.ttf', $this->outfit());
        $this->actingAs($admin)->getJson($route('media/2026/10/stray.ttf'))->assertNotFound();
        $this->actingAs($admin)->getJson($route('../.env'))->assertNotFound();
        $editor = User::forceCreate(['name' => 'Ed', 'email' => 'fonts-ed@example.test', 'password' => 'secret-password',
            'role_id' => (int) DB::table('roles')->where('slug', 'editor')->value('id')]);
        $this->actingAs($editor)->getJson($route($bold))->assertForbidden();
    }

    public function test_a_made_woff_goes_when_its_font_does(): void
    {
        $ttf = $this->media($this->outfit(), 'o.ttf');
        CustomFonts::save([['family' => 'A', 'files' => ['ttf' => $ttf]]]);
        $made = CustomFonts::all()[0]['made']['woff'];

        CustomFonts::save([]);

        Storage::disk('public')->assertMissing($made);
        Storage::disk('public')->assertExists($ttf); // the library's file is never touched
    }

    public function test_the_customizers_save_saves_them_and_reset_keeps_them(): void
    {
        $ttf = $this->media($this->outfit(), 'o.ttf');
        $this->actingAs($this->admin());

        $this->post(route('admin.customizer.save'), ['_section' => 'typography_custom_fonts',
            'custom_fonts' => json_encode([['family' => 'Brand', 'weight' => 300, 'files' => ['ttf' => $ttf]]])])->assertRedirect();
        CustomFonts::flush();
        $this->assertNotNull(CustomFonts::find('Brand'));

        // a form without the list (another screen, an older page) leaves the fonts alone
        $this->post(route('admin.customizer.save'), ['_section' => 'typography'])->assertRedirect();
        $this->post(route('admin.customizer.reset'), ['all' => '1'])->assertRedirect();
        CustomFonts::flush();
        $this->assertNotNull(CustomFonts::find('Brand'));
    }

    public function test_the_customizer_shows_the_boxes_and_lists_the_fonts_first(): void
    {
        $ttf = $this->media($this->outfit(), 'o.ttf');
        CustomFonts::save([['family' => 'Brand', 'weight' => 300, 'files' => ['ttf' => $ttf]]]);

        $html = $this->actingAs($this->admin())->get(route('admin.customizer.index', ['section' => 'typography_custom_fonts']))->assertOk()->getContent();
        $this->assertStringContainsString('falconCustomFonts(', $html);
        $this->assertStringContainsString('name="custom_fonts"', $html);
        $this->assertStringContainsString('{ fontFormat: fmt }', $html, 'files are picked from the Media Library');
        $this->assertMatchesRegularExpression('/const GOOGLE_FONTS = \{"Custom Fonts":\[\{"family":"Brand"/', $html);
    }

    // ── Everywhere a font is picked ──────────────────────────────────────────

    public function test_every_page_declares_them_and_google_is_never_asked_for_them(): void
    {
        $o = $this->media($this->outfit(), 'o.ttf');
        $i = $this->media((string) file_get_contents(__DIR__.'/../../../public/assets/fonts/inter/inter-400.ttf'), 'inter.ttf');
        CustomFonts::save([['family' => 'Brand', 'files' => ['ttf' => $o]], ['family' => 'Inter', 'files' => ['ttf' => $i]]]);

        ob_start();
        do_falcon_action('falcon_head');
        $this->assertStringContainsString("font-family:'Brand'", (string) ob_get_clean());

        // the same name as a Google font: the site's own wins, Google is not asked for it
        $url = falcon_google_font_url(['Inter', 'Brand', 'Roboto']);
        $this->assertStringContainsString('family=Roboto', $url);
        $this->assertStringNotContainsString('Inter', $url);
        $this->assertStringNotContainsString('Brand', $url);
    }

    public function test_the_builder_and_motion_visual_offer_them(): void
    {
        $o = $this->media($this->outfit(), 'o.ttf');
        CustomFonts::save([['family' => 'Brand', 'files' => ['ttf' => $o]]]);

        $builder = (string) file_get_contents(__DIR__.'/../../../resources/views/admin/falcon-builder/partials/scripts.blade.php');
        $this->assertStringContainsString('array_merge(\FalconCms\Core\Support\CustomFonts\CustomFonts::catalog(), falcon_google_fonts())', $builder);
        $this->assertStringContainsString('fbOf(f) { return f.fallback ||', $builder);
        $this->assertStringContainsString('CustomFonts::styleTag()', (string) file_get_contents(__DIR__.'/../../../resources/views/admin/falcon-builder/partials/builder-data.blade.php'));

        $this->assertContains('Brand', MotionVisuals::editorConfig()['customFonts']);
        $html = MotionVisuals::render(['mv_preset' => 'custom', 'mv_items' => MotionVisuals::encodeItems([['kind' => 'text', 'text' => 'Hi', 'font' => 'brand']])]);
        $this->assertStringContainsString('font-family:&#039;Brand&#039;, sans-serif', $html);
    }

    public function test_a_family_name_cannot_break_out_of_the_css(): void
    {
        // quotes, braces, semicolons and angle brackets go; the letters stay
        $this->assertSame('Evilstylescriptfontx', CustomFonts::cleanFamily("Evil';}</style><script>font{x"));
        $this->assertSame('বাংলা Font', CustomFonts::cleanFamily('বাংলা Font'), 'any script is a valid name');
    }
}
