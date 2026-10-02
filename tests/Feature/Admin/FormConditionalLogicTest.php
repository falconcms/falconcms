<?php

namespace FalconCms\Core\Tests\Feature\Admin;

use App\Models\User;
use FalconCms\Core\Models\Form;
use FalconCms\Core\Tests\TestCase;
use Illuminate\Support\Facades\DB;

/**
 * Conditional logic on form fields — a field shows or hides based on another field's answer.
 *
 * The rules are stored as part of each field (so they save with the form, no schema change),
 * and the front-end renderer emits them as data-lf-cond for the in-page engine to act on.
 */
class FormConditionalLogicTest extends TestCase
{
    private ?User $admin = null;

    private function administrator(): User
    {
        return $this->admin ??= User::forceCreate([
            'name' => 'Admin', 'email' => 'admin@example.test', 'password' => 'secret-password',
            'role_id' => (int) DB::table('roles')->where('slug', 'administrator')->value('id'),
        ]);
    }

    private function conditionalForm(array $conditional): Form
    {
        return Form::create([
            'title' => 'Survey', 'slug' => 'survey-'.uniqid(), 'status' => true, 'lang_code' => 'en',
            'fields' => [
                ['type' => 'radio', 'name' => 'contact_me', 'label' => 'Contact me?', 'options' => "Yes\nNo"],
                ['type' => 'tel', 'name' => 'phone', 'label' => 'Phone', 'required' => true, 'conditional' => $conditional],
            ],
            'settings' => [],
        ]);
    }

    public function test_the_builder_save_keeps_the_conditional_rules(): void
    {
        $this->actingAs($this->administrator())
            ->postJson('/admin/forms', ['title' => 'Survey'])
            ->assertRedirect();
        $form = Form::where('title', 'Survey')->latest('id')->firstOrFail();

        $fields = [
            ['type' => 'select', 'name' => 'reason', 'label' => 'Reason', 'options' => "Sales\nSupport"],
            ['type' => 'textarea', 'name' => 'details', 'label' => 'Details', 'conditional' => [
                'enabled' => true, 'action' => 'show', 'logic' => 'all',
                'rules' => [['field' => 'reason', 'operator' => 'is', 'value' => 'Support']],
            ]],
        ];
        $this->actingAs($this->administrator())
            ->postJson('/admin/forms/'.$form->id.'/save', ['title' => 'Survey', 'fields' => $fields, 'settings' => []])
            ->assertOk();

        $saved = $form->fresh()->fields;
        $this->assertTrue($saved[1]['conditional']['enabled']);
        $this->assertSame('reason', $saved[1]['conditional']['rules'][0]['field']);
        $this->assertSame('Support', $saved[1]['conditional']['rules'][0]['value']);
    }

    public function test_the_renderer_emits_the_condition_and_the_engine(): void
    {
        $form = $this->conditionalForm([
            'enabled' => true, 'action' => 'show', 'logic' => 'all',
            'rules' => [['field' => 'contact_me', 'operator' => 'is', 'value' => 'Yes']],
        ]);

        $html = render_falcon_form($form->slug);

        $this->assertStringContainsString('data-lf-cond=', $html);
        $this->assertStringContainsString('&quot;field&quot;:&quot;contact_me&quot;', $html);
        $this->assertStringContainsString('&quot;operator&quot;:&quot;is&quot;', $html);
        $this->assertStringContainsString('data-lf-name="phone"', $html, 'the field carries its name for the engine');
        $this->assertStringContainsString('applyConditions', $html, 'the conditional engine is present');
    }

    public function test_a_disabled_or_empty_condition_emits_nothing(): void
    {
        $off = render_falcon_form($this->conditionalForm([
            'enabled' => false, 'rules' => [['field' => 'contact_me', 'operator' => 'is', 'value' => 'Yes']],
        ])->slug);
        $this->assertStringNotContainsString('data-lf-cond="', $off);

        // Enabled but every rule malformed → nothing emitted.
        $empty = render_falcon_form($this->conditionalForm([
            'enabled' => true, 'rules' => [['field' => '', 'operator' => '']],
        ])->slug);
        $this->assertStringNotContainsString('data-lf-cond="', $empty);
    }
}
