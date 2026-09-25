<?php

namespace FalconCms\Core\Tests\Feature\Cms;

use FalconCms\Core\Tests\TestCase;
use Illuminate\View\Compilers\BladeCompiler;

/**
 * Every email the CMS sends, read on a phone.
 *
 * These were all laid out for a desktop mail client and none of them gave way below that. The
 * order notification was the worst of it: 20px of wrapper plus 35px of content on each side
 * spends 110 pixels of a 320px screen before a word is written, and the Price column then
 * claimed a further fixed 120 — leaving item names about 60px to wrap a word at a time.
 *
 * The rule each of them now follows is that a media query may only take back room, never
 * carry the layout. A client that drops the stylesheet has to get the desktop version rather
 * than a broken one, because some do: the Gmail app strips <style> for accounts Google does
 * not host.
 *
 * This walks the whole directory rather than a list, so an email added later is held to the
 * same thing without anyone having to remember to add it here.
 */
class EmailsAreReadableOnPhonesTest extends TestCase
{
    /** @return array<string, string> file name => contents */
    private function templates(): array
    {
        $dir = __DIR__.'/../../../resources/views/emails';
        $found = [];

        $it = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS));
        foreach ($it as $file) {
            if ($file->isFile() && str_ends_with($file->getFilename(), '.blade.php')) {
                $name = str_replace('\\', '/', substr($file->getPathname(), strlen($dir) + 1));
                $found[$name] = (string) file_get_contents($file->getPathname());
            }
        }

        ksort($found);
        $this->assertNotEmpty($found, 'no email templates were found at all');

        return $found;
    }

    public function test_every_email_has_something_to_say_about_a_narrow_screen(): void
    {
        foreach ($this->templates() as $name => $html) {
            $this->assertStringContainsString('@media only screen and (max-width: 520px)', $html,
                "{$name} has no rule for a phone, so it renders at desktop widths on one");
        }
    }

    public function test_the_media_query_sits_where_gmail_will_read_it(): void
    {
        // Gmail reads a stylesheet in <head> and drops one in <body>.
        foreach ($this->templates() as $name => $html) {
            $head = substr($html, 0, (int) strpos($html, '</head>'));
            $this->assertStringContainsString('@media only screen', $head,
                "{$name} keeps its media query outside <head>, where Gmail will not read it");
        }
    }

    public function test_nothing_is_pinned_open_with_nowrap(): void
    {
        // A cell that cannot wrap pushes the table wider than the screen, and the table takes
        // the email with it. This is what made the form notification scroll sideways.
        foreach ($this->templates() as $name => $html) {
            // Only the real declaration counts; a comment explaining the trap does not.
            $this->assertDoesNotMatchRegularExpression('/(?<!No )white-space:\s*nowrap/', $html,
                "{$name} has a cell that cannot wrap");
        }
    }

    public function test_a_phone_gets_its_gutters_back(): void
    {
        // The specific thing that was wrong everywhere: desktop gutters left at desktop size.
        foreach ($this->templates() as $name => $html) {
            $mediaBlock = (string) strstr($html, '@media only screen and (max-width: 520px)');
            $this->assertMatchesRegularExpression('/padding[^;}]*(10px|14px|18px|20px)[^;}]*!important/', $mediaBlock,
                "{$name} narrows nothing, so its media query is decoration");
        }
    }

    public function test_the_order_email_stops_reserving_a_fixed_price_column(): void
    {
        $html = $this->templates()['shop/order_notification.blade.php'];

        // 120px out of the ~210px a phone has left is most of the row.
        $this->assertStringContainsString('width: 120px', $html, 'the desktop column width should stay for desktop');
        $this->assertMatchesRegularExpression('/\.items-table th\.text-right\s*\{[^}]*width:\s*84px\s*!important/s', $html,
            'the fixed Price column is never relaxed, so item names keep wrapping a word at a time');
    }

    public function test_the_order_email_stacks_its_order_details(): void
    {
        $html = $this->templates()['shop/order_notification.blade.php'];

        $this->assertMatchesRegularExpression('/\.order-meta td\s*\{[^}]*display:\s*block\s*!important/s', $html,
            '"Payment Method:" and its answer still have to share the width of a phone');
    }

    public function test_every_email_still_renders(): void
    {
        // The edits are inside <style> in Blade files; a stray brace takes the page down.
        $compiler = new BladeCompiler(app('files'), storage_path('framework/views'));
        $dir = __DIR__.'/../../../resources/views/emails';

        foreach (array_keys($this->templates()) as $name) {
            $compiler->compile($dir.'/'.$name);
            $this->assertTrue(true, "{$name} compiles");
        }
    }
}
