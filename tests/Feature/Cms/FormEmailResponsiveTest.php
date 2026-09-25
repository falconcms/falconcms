<?php

namespace FalconCms\Core\Tests\Feature\Cms;

use FalconCms\Core\Tests\TestCase;

/**
 * The email a form submission sends, read on a phone.
 *
 * It was laid out for a desktop mail client and nothing gave way below that. The label column
 * was fixed at 36% and carried `white-space: nowrap`, so a question like "How did you hear
 * about us?" could not wrap: it pushed the table wider than the screen, and the table took the
 * whole email with it — the reader got a sideways scroll and text running off the edge. Every
 * gutter was 40px as well, which on a 320px screen is eighty pixels spent on nothing.
 *
 * The fix is a stylesheet that only ever narrows: the inline styles still carry the desktop
 * layout, so a client that drops the <style> block gets that rather than a broken one. Under
 * 520px the label and its answer become two lines instead of two columns, the gutters halve,
 * and the decorative badge — a 56px column, a sixth of a phone — steps out of the way.
 */
class FormEmailResponsiveTest extends TestCase
{
    private function render(array $rows): string
    {
        return view('falcon-cms::emails.form.notification', [
            'form' => (object) ['title' => 'Contact Us'],
            'rows' => $rows,
            'submittedAt' => '25 Sep 2026, 10:42',
            'ip' => '203.0.113.9',
            'introText' => 'You have received a new submission.',
            'footerText' => 'This is an automated notification.',
        ])->render();
    }

    private function row(string $label, string $display, bool $file = false, bool $empty = false): array
    {
        return ['label' => $label, 'display' => $display, 'is_file' => $file, 'is_empty' => $empty];
    }

    public function test_a_long_label_is_allowed_to_wrap(): void
    {
        // The single thing that broke the email: a label that could not break pushed the
        // table past the width of the screen.
        $html = $this->render([$this->row('How did you hear about us?', 'A friend')]);

        $this->assertStringNotContainsString('white-space:nowrap', $html,
            'a label that cannot wrap makes the whole email scroll sideways');
    }

    public function test_the_label_and_its_answer_stack_on_a_narrow_screen(): void
    {
        $html = $this->render([$this->row('Email', 'someone@example.test')]);

        $this->assertStringContainsString('@media only screen and (max-width: 520px)', $html);
        $this->assertStringContainsString('class="fc-row-label"', $html);
        $this->assertStringContainsString('class="fc-row-value"', $html);
        $this->assertMatchesRegularExpression('/\.fc-row-label,\s*\.fc-row-value\s*\{[^}]*display: block !important/s', $html,
            'the two columns never become two lines, so each gets half a phone');
    }

    public function test_the_desktop_layout_is_still_written_inline(): void
    {
        // Gmail on Android strips <style> for accounts it does not host. Nothing in the
        // stylesheet may be load-bearing: it narrows a layout that is already there.
        $html = $this->render([$this->row('Name', 'Tareq')]);

        $this->assertStringContainsString('width:36%', $html, 'the desktop column width left the inline styles');
        $this->assertStringContainsString('max-width:600px', $html);
        $this->assertStringContainsString('padding:13px 18px', $html);
    }

    public function test_the_gutters_narrow_rather_than_the_content(): void
    {
        $html = $this->render([$this->row('Name', 'Tareq')]);

        $this->assertMatchesRegularExpression('/\.fc-pad\s*\{[^}]*padding-left: 20px !important/s', $html);
        // Every 40px gutter has to be wired up, or half the email narrows and half does not.
        $this->assertSame(5, substr_count($html, 'class="fc-pad"'),
            'a 40px gutter was left behind');
    }

    public function test_a_long_answer_breaks_instead_of_overflowing(): void
    {
        $long = str_repeat('averylongunbrokenword', 8);
        $html = $this->render([$this->row('Message', $long)]);

        $this->assertStringContainsString('word-break:break-word', $html);
        $this->assertStringContainsString('overflow-wrap:break-word', $html);
        $this->assertStringContainsString($long, $html);
    }

    public function test_every_kind_of_row_still_renders(): void
    {
        // The three shapes a row comes in — a file, an empty answer, and ordinary text.
        $html = $this->render([
            $this->row('Attachment', 'https://example.test/storage/cv.pdf', file: true),
            $this->row('Phone', '', empty: true),
            $this->row('Message', 'Hello<br />there'),
        ]);

        $this->assertStringContainsString('https://example.test/storage/cv.pdf', $html);
        $this->assertStringContainsString('Download File', $html);
        $this->assertStringContainsString('Hello<br />there', $html, 'the line break in an answer was escaped away');
        $this->assertStringContainsString('font-style:italic', $html, 'an empty answer lost its dash');
    }

    public function test_the_stylesheet_sits_in_the_head(): void
    {
        // Gmail only reads a stylesheet in <head>; one in <body> is dropped.
        $html = $this->render([$this->row('Name', 'Tareq')]);

        $head = substr($html, 0, (int) strpos($html, '</head>'));
        $this->assertStringContainsString('@media only screen', $head,
            'the media query is outside <head>, where Gmail will not read it');
    }
}
