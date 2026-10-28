<?php

namespace FalconCms\Core\Tests\Feature\Ai;

use FalconAi\Support\HtmlSanitizer;
use FalconAi\Support\Settings;
use FalconCms\Core\Support\PluginManager;
use FalconCms\Core\Tests\Concerns\MakesShopFixtures;
use FalconCms\Core\Tests\TestCase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;

/**
 * AI → Settings: keys kept encrypted and never sent back to the browser, refused when they are
 * not keys, the SEO plugin's old Claude key carried over; and the HTML cleaning every AI answer
 * goes through.
 */
class AiSettingsTest extends TestCase
{
    use MakesShopFixtures;

    private const CLAUDE = 'sk-ant-api03-abcdefghijklmnopqrstuvwxyz0123456789';

    private const OPENAI = 'sk-proj-abcdefghijklmnopqrstuvwxyz0123456789';

    private function admin()
    {
        return $this->makeUser(['role_id' => (int) DB::table('roles')->where('slug', 'administrator')->value('id')]);
    }

    private function save(array $input)
    {
        return $this->post('/admin/ai/settings', $input + ['provider' => 'claude', 'language' => 'bn', 'tone' => 'professional']);
    }

    public function test_keys_are_encrypted_masked_and_kept(): void
    {
        $this->actingAs($this->admin());

        $this->save(['provider' => 'openai', 'claude_key' => ' '.self::CLAUDE.' ', 'openai_key' => self::OPENAI, 'openai_model' => 'gpt-test-1',
            'writer_roles' => ['editor', 'author'], 'monthly_limit' => '50000'])
            ->assertRedirect(route('admin.ai.settings'))->assertSessionHas('success');

        $stored = DB::table('cms_settings')->where('key', 'ai_claude_key')->value('value');
        $this->assertStringNotContainsString('sk-ant', (string) $stored);
        $this->assertSame(self::CLAUDE, Settings::key('claude'));
        $this->assertSame(self::OPENAI, Settings::key('openai'));
        $this->assertSame('openai', Settings::provider());
        $this->assertSame('gpt-test-1', Settings::model('openai'));
        $this->assertSame('claude-opus-5-5', Settings::model('claude'), 'the default model');
        $this->assertSame(['bn', 'professional', ['editor', 'author'], 50000],
            [Settings::language(), Settings::tone(), Settings::writerRoles(), Settings::monthlyLimit()]);

        // The screen shows the start and end only; saving it unchanged (or empty) keeps the key.
        $html = $this->get('/admin/ai/settings')->assertOk()->getContent();
        $this->assertStringContainsString('value="sk-ant-api03••••••••••••6789"', $html);
        $this->assertStringNotContainsString(self::CLAUDE, $html);
        $this->assertStringNotContainsString(self::OPENAI, $html);

        $this->save(['claude_key' => Settings::masked('claude'), 'openai_key' => ''])->assertSessionHas('success');
        $this->assertSame(self::CLAUDE, Settings::key('claude'));
        $this->assertSame(self::OPENAI, Settings::key('openai'));

        $this->save(['claude_key_remove' => '1']);
        $this->assertSame('', Settings::key('claude'));
    }

    /** What a browser's password manager fills in: the admin password, in every password field. */
    public function test_what_is_not_a_key_is_refused_and_the_rest_saves(): void
    {
        $this->actingAs($this->admin());
        $this->save(['claude_key' => self::CLAUDE]);

        $this->save(['claude_key' => 'MyAdminPass1', 'openai_key' => 'hunter2', 'tone' => 'academic'])
            ->assertSessionHas('error', fn ($m) => str_contains($m, 'The Claude key was not saved') && str_contains($m, 'The OpenAI key was not saved'));

        $this->assertSame(self::CLAUDE, Settings::key('claude'), 'the good key stays');
        $this->assertSame('', Settings::key('openai'));
        $this->assertSame('academic', Settings::tone(), 'the rest of the page still saves');
    }

    public function test_settings_are_validated(): void
    {
        $this->actingAs($this->admin());

        $this->save(['provider' => 'no-such-ai'])->assertSessionHasErrors('provider');
        $this->save(['language' => 'xx'])->assertSessionHasErrors('language');
        $this->save(['writer_roles' => ['no-such-role']])->assertSessionHasErrors('writer_roles.0');
        $this->save(['monthly_limit' => '-5'])->assertSessionHasErrors('monthly_limit');
    }

    /** The plugin is on by default, so its migration does the one-time work. */
    public function test_the_seo_plugins_claude_key_is_carried_over(): void
    {
        $encrypted = Crypt::encryptString(self::CLAUDE);
        $this->setCmsOptions(['seo_ai_api_key' => $encrypted, 'seo_ai_model' => 'claude-sonnet-5-5']);

        $migration = require app(PluginManager::class)->manifest('falcon-ai')['dir'].'/database/migrations/2026_10_29_000001_create_falcon_ai_usage_table.php';
        $migration->up();
        forget_cms_options_cache();

        $this->assertSame(self::CLAUDE, Settings::key('claude'));
        $this->assertSame('claude-sonnet-5-5', Settings::model('claude'));
        $this->assertSame(0, DB::table('cms_settings')->whereIn('key', ['seo_ai_api_key', 'seo_ai_model'])->count(), 'the old copies are gone');
        $this->assertTrue(DB::table('permissions')->where('slug', 'manage_ai')->exists());

        // Run again (a second update): a key saved since is never overwritten.
        $this->setCmsOptions(['seo_ai_api_key' => Crypt::encryptString('sk-ant-api03-oldoldoldoldoldoldoldold')]);
        $migration->up();
        forget_cms_options_cache();
        $this->assertSame(self::CLAUDE, Settings::key('claude'));
    }

    // ── cleaning what the AI writes ─────────────────────────────────────────

    public function test_only_article_markup_survives(): void
    {
        $dirty = '<h1 class="x">Title</h1><p style="color:red" onmouseover="steal()">Text <b>bold</b> <span>kept text</span></p>'
            .'<script>alert(1)</script><style>p{}</style><iframe src="https://evil.test"></iframe><img src=x onerror=alert(1)>'
            .'<ul><li>One</li><li><a href="https://ok.test" target="_blank" onclick="x()">Two</a></li></ul>'
            .'<p><a href="java&#09;script:alert(1)">tab trick</a> <a href="//evil.test/x">protocol-relative</a> <a href="/about">relative</a> <a href="mailto:a@b.test">mail</a></p>'
            .'<!-- a comment --><svg><script>alert(2)</script></svg><table><tr><td>cell</td></tr></table>';

        $this->assertSame(
            '<h2>Title</h2><p>Text <b>bold</b> kept text</p><ul><li>One</li><li><a href="https://ok.test" rel="noopener">Two</a></li></ul>'
            .'<p>tab trick protocol-relative <a href="/about">relative</a> <a href="mailto:a@b.test">mail</a></p><table><tr><td>cell</td></tr></table>',
            HtmlSanitizer::clean($dirty),
        );
    }

    public function test_fences_and_plain_text_are_handled(): void
    {
        $this->assertSame('<p>Hello</p>', HtmlSanitizer::clean("```html\n<p>Hello</p>\n```"));
        $this->assertSame("<p>First line<br>\nsecond line</p>\n<p>Tea &amp; water: 1 &lt; 2</p>", HtmlSanitizer::clean("First line\nsecond line\n\nTea & water: 1 < 2"));
        $this->assertSame('', HtmlSanitizer::clean('  '));
        $this->assertSame('<p>বাংলা লেখা ঠিক থাকে</p>', HtmlSanitizer::clean('<p>বাংলা লেখা ঠিক থাকে</p>'));
    }
}
