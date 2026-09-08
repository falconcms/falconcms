<?php

namespace FalconCms\Core\Tests\Feature\Admin;

use FalconCms\Core\Tests\TestCase;

/**
 * The admin's "you have unsaved changes" guard, and the one way it must never behave.
 *
 * It exists so that closing a tab with half-edited work asks first. It shipped doing the
 * opposite: the warning appeared when the Save button was pressed — at the very moment the
 * work was being written — because most of this admin saves by calling form.submit() from
 * script, and a form submitted that way fires no submit event at all. That is a rule of the
 * DOM rather than a quirk of one browser, so the guard never learned the save had happened.
 *
 * There are 80-odd such call sites across the admin, which is why the fix is one patch of
 * HTMLFormElement.prototype in the layout rather than a change to each screen. Nothing here
 * can run JavaScript, so this asserts the seam is still in place; the behaviour itself was
 * checked in a browser across the cases the guard has to tell apart (typed-in POST form,
 * GET search form, opted-out form, script submit, cancelled submit, AJAX save, tab close).
 */
class UnsavedChangesGuardTest extends TestCase
{
    private function layout(): string
    {
        $path = __DIR__.'/../../../resources/views/components/layouts/admin.blade.php';

        $this->assertFileExists($path);

        return (string) file_get_contents($path);
    }

    public function test_a_form_submitted_from_script_counts_as_leaving(): void
    {
        $layout = $this->layout();

        $this->assertStringContainsString(
            'HTMLFormElement.prototype.submit',
            $layout,
            'form.submit() fires no submit event, so without this patch every screen that '
            .'saves from script warns about unsaved changes while it is saving them.'
        );
        $this->assertMatchesRegularExpression(
            '/HTMLFormElement\.prototype\.submit\s*=\s*function[^}]*leaving\s*=\s*true/s',
            $layout,
            'the patched submit must mark the page as leaving before handing over to the native one'
        );
    }

    public function test_the_guard_still_warns_when_the_page_is_closed(): void
    {
        $layout = $this->layout();

        $this->assertStringContainsString('beforeunload', $layout, 'the warning itself must still be wired up');
        $this->assertStringContainsString('falconHasUnsavedChanges', $layout);
        $this->assertStringContainsString('falconMarkSaved', $layout, 'screens that save over fetch need a way to clear it');
    }

    public function test_a_cancelled_submit_puts_the_guard_back(): void
    {
        // Client-side validation and confirm dialogs cancel submits. Nobody left, so the
        // work is still unsaved and closing the tab must still ask.
        $this->assertStringContainsString(
            'defaultPrevented',
            $this->layout(),
            'a submit that was cancelled must not leave the guard switched off'
        );
    }

    public function test_the_customizer_clears_the_flag_after_its_ajax_save(): void
    {
        // It saves without leaving the page, so there is no submit for the guard to see.
        $path = __DIR__.'/../../../resources/views/admin/customizer/index.blade.php';
        $this->assertFileExists($path);

        $this->assertStringContainsString(
            'falconMarkSaved',
            (string) file_get_contents($path),
            'saving the Customizer must stop it warning about unsaved changes'
        );
    }
}
