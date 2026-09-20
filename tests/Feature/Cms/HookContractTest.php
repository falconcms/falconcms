<?php

namespace FalconCms\Core\Tests\Feature\Cms;

use FalconCms\Core\Tests\TestCase;

/**
 * What a hook promises, for everyone who writes against one.
 *
 * The aliasing of the renamed tags has its own file. This is the contract underneath it: the
 * order callbacks run in, the arguments they are handed, what a filter's return value does to
 * the next one in the queue, and the rules for taking one back off again — including the one
 * that surprises everybody, that a closure can only be removed by the same closure.
 *
 * A theme or plugin author relies on every line of this, so each is pinned rather than
 * assumed.
 */
class HookContractTest extends TestCase
{
    // ── actions ──────────────────────────────────────────────────────────────

    public function test_an_action_runs_its_callback(): void
    {
        $ran = 0;
        add_falcon_action('fct_plain', function () use (&$ran) { $ran++; });

        do_falcon_action('fct_plain');

        $this->assertSame(1, $ran);
    }

    public function test_an_action_nobody_registered_on_is_harmless(): void
    {
        do_falcon_action('fct_nobody_is_here');

        $this->assertFalse(has_falcon_action('fct_nobody_is_here'));
    }

    public function test_actions_run_lowest_priority_first_whatever_order_they_were_added(): void
    {
        $order = [];
        add_falcon_action('fct_order', function () use (&$order) { $order[] = 'default'; });
        add_falcon_action('fct_order', function () use (&$order) { $order[] = 'late'; }, 20);
        add_falcon_action('fct_order', function () use (&$order) { $order[] = 'early'; }, 5);

        do_falcon_action('fct_order');

        $this->assertSame(['early', 'default', 'late'], $order);
    }

    public function test_two_callbacks_at_the_same_priority_run_in_the_order_they_were_added(): void
    {
        $order = [];
        add_falcon_action('fct_same', function () use (&$order) { $order[] = 'first'; }, 10);
        add_falcon_action('fct_same', function () use (&$order) { $order[] = 'second'; }, 10);

        do_falcon_action('fct_same');

        $this->assertSame(['first', 'second'], $order);
    }

    public function test_an_action_hands_over_every_argument_it_was_fired_with(): void
    {
        $seen = null;
        add_falcon_action('fct_args', function (...$args) use (&$seen) { $seen = $args; });

        do_falcon_action('fct_args', 'a post', 42, ['deep' => true]);

        $this->assertSame(['a post', 42, ['deep' => true]], $seen);
    }

    public function test_what_an_action_returns_is_discarded(): void
    {
        add_falcon_action('fct_returns', fn () => 'ignored');

        // An action has no return value of its own; the point is that firing one is safe
        // even when the callbacks hand something back.
        $this->assertNull(do_falcon_action('fct_returns'));
    }

    // ── filters ──────────────────────────────────────────────────────────────

    public function test_a_filter_with_no_callbacks_hands_the_value_straight_back(): void
    {
        $this->assertSame('untouched', apply_falcon_filters('fct_empty', 'untouched'));
    }

    public function test_a_filter_returns_what_its_callback_returned(): void
    {
        add_falcon_filter('fct_one', fn ($value) => $value.' and more');

        $this->assertSame('a value and more', apply_falcon_filters('fct_one', 'a value'));
    }

    public function test_each_filter_is_handed_what_the_one_before_it_returned(): void
    {
        add_falcon_filter('fct_chain', fn ($v) => $v.'-second', 20);
        add_falcon_filter('fct_chain', fn ($v) => $v.'-first', 10);

        $this->assertSame('start-first-second', apply_falcon_filters('fct_chain', 'start'));
    }

    public function test_a_filter_that_returns_nothing_replaces_the_value_with_null(): void
    {
        // Not a nicety — this is the single commonest mistake writing a filter, and the
        // behaviour a theme has to be able to reason about when it happens.
        add_falcon_filter('fct_forgot', function ($value) { $value.'!'; });

        $this->assertNull(apply_falcon_filters('fct_forgot', 'a value'));
    }

    public function test_a_filter_is_handed_the_extra_arguments_after_the_value(): void
    {
        $seen = null;
        add_falcon_filter('fct_filter_args', function ($value, ...$rest) use (&$seen) {
            $seen = $rest;

            return $value;
        });

        apply_falcon_filters('fct_filter_args', 'v', 'post', 7);

        $this->assertSame(['post', 7], $seen);
    }

    public function test_a_filter_may_change_the_type_it_was_given(): void
    {
        add_falcon_filter('fct_type', fn (array $v) => count($v));

        $this->assertSame(3, apply_falcon_filters('fct_type', ['a', 'b', 'c']));
    }

    // ── removing ─────────────────────────────────────────────────────────────

    public function test_a_named_function_comes_off_by_name(): void
    {
        add_falcon_action('fct_named', __NAMESPACE__.'\\fct_named_callback', 10);
        $this->assertTrue(has_falcon_action('fct_named'));

        $this->assertTrue(remove_falcon_action('fct_named', __NAMESPACE__.'\\fct_named_callback', 10));
        $this->assertFalse(has_falcon_action('fct_named'));
    }

    public function test_a_removal_at_the_wrong_priority_does_nothing_and_says_so(): void
    {
        add_falcon_action('fct_priority', __NAMESPACE__.'\\fct_named_callback', 30);

        $this->assertFalse(remove_falcon_action('fct_priority', __NAMESPACE__.'\\fct_named_callback'));
        $this->assertTrue(has_falcon_action('fct_priority'), 'the callback should still be registered');

        $this->assertTrue(remove_falcon_action('fct_priority', __NAMESPACE__.'\\fct_named_callback', 30));
        $this->assertFalse(has_falcon_action('fct_priority'));
    }

    public function test_an_identical_closure_is_not_the_same_closure(): void
    {
        add_falcon_action('fct_closure', function () { echo 'hi'; }, 10);

        // Character for character the same, and it will not match: the registry compares
        // with ===, and === on two closures asks whether they are one object.
        $this->assertFalse(remove_falcon_action('fct_closure', function () { echo 'hi'; }, 10));
        $this->assertTrue(has_falcon_action('fct_closure'));
    }

    public function test_a_closure_kept_in_a_variable_comes_off(): void
    {
        $callback = function () { echo 'hi'; };
        add_falcon_action('fct_kept', $callback, 10);

        $this->assertTrue(remove_falcon_action('fct_kept', $callback, 10));
        $this->assertFalse(has_falcon_action('fct_kept'));
    }

    public function test_removing_one_of_two_leaves_the_other_running(): void
    {
        $order = [];
        $doomed = function () use (&$order) { $order[] = 'doomed'; };
        add_falcon_action('fct_two', $doomed, 10);
        add_falcon_action('fct_two', function () use (&$order) { $order[] = 'survivor'; }, 10);

        remove_falcon_action('fct_two', $doomed, 10);
        do_falcon_action('fct_two');

        $this->assertSame(['survivor'], $order);
    }

    public function test_a_filter_comes_off_the_same_way_and_stops_changing_the_value(): void
    {
        add_falcon_filter('fct_removable', __NAMESPACE__.'\\fct_filter_callback', 10);
        $this->assertSame('value!', apply_falcon_filters('fct_removable', 'value'));

        $this->assertTrue(remove_falcon_filter('fct_removable', __NAMESPACE__.'\\fct_filter_callback', 10));
        $this->assertFalse(has_falcon_filter('fct_removable'));
        $this->assertSame('value', apply_falcon_filters('fct_removable', 'value'));
    }

    public function test_a_hook_reports_itself_empty_once_its_last_callback_is_gone(): void
    {
        // An empty priority bucket left behind used to keep has_falcon_action() answering
        // true for a hook with nothing on it, which is the answer a template acts on.
        add_falcon_action('fct_pruned', __NAMESPACE__.'\\fct_named_callback', 10);
        remove_falcon_action('fct_pruned', __NAMESPACE__.'\\fct_named_callback', 10);

        $this->assertFalse(has_falcon_action('fct_pruned'));
    }

    public function test_removing_something_that_was_never_registered_is_false_not_an_error(): void
    {
        $this->assertFalse(remove_falcon_action('fct_never', 'no_such_function', 10));
        $this->assertFalse(remove_falcon_filter('fct_never', 'no_such_function', 10));
    }

    // ── the two registries are separate ──────────────────────────────────────

    public function test_an_action_and_a_filter_may_share_a_tag_without_meeting(): void
    {
        $ranAction = false;
        add_falcon_action('fct_shared', function () use (&$ranAction) { $ranAction = true; });
        add_falcon_filter('fct_shared', fn ($v) => $v.' filtered');

        $this->assertSame('v filtered', apply_falcon_filters('fct_shared', 'v'));
        $this->assertFalse($ranAction, 'applying a filter must not fire the action of the same name');

        do_falcon_action('fct_shared');
        $this->assertTrue($ranAction);
    }
}

/** A named callback, because a closure cannot be removed by a copy of itself. */
function fct_named_callback(): void
{
    // Nothing to do: these tests are about registration, not output.
}

function fct_filter_callback(string $value): string
{
    return $value.'!';
}
