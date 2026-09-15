<?php

namespace FalconCms\Core\Tests\Feature\Admin;

use App\Models\User;
use FalconCms\Core\Models\Form;
use FalconCms\Core\Tests\TestCase;
use Illuminate\Support\Facades\DB;

/**
 * Renaming a form.
 *
 * The name could be set once, on the create screen, and never again — so a form called
 * "Untitled" or "test" stayed that way. It is editable in the builder header now and saves
 * with the rest of the form.
 *
 * The slug is the part that must NOT follow the name. It is the form's address: every
 * `[falcon_form slug="…"]` already embedded in a page resolves through it, so a rename that
 * changed the slug would quietly empty every page the form is on.
 */
class FormRenameTest extends TestCase
{
    private ?User $admin = null;

    private function administrator(): User
    {
        return $this->admin ??= User::forceCreate([
            'name' => 'Admin',
            'email' => 'admin@example.test',
            'password' => 'secret-password',
            'role_id' => (int) DB::table('roles')->where('slug', 'administrator')->value('id'),
        ]);
    }

    private function createForm(string $title = 'Contact Form')
    {
        $this->actingAs($this->administrator())
            ->post('/admin/forms', ['title' => $title])
            ->assertRedirect();

        return Form::where('title', $title)->latest('id')->firstOrFail();
    }

    private function save(Form $form, array $payload)
    {
        return $this->actingAs($this->administrator())
            ->postJson('/admin/forms/'.$form->id.'/save', $payload);
    }

    public function test_the_builder_saves_a_new_name(): void
    {
        $form = $this->createForm();

        $this->save($form, ['title' => 'Get in Touch', 'fields' => [], 'settings' => []])
            ->assertOk()
            ->assertJson(['success' => true, 'title' => 'Get in Touch']);

        $this->assertSame('Get in Touch', $form->fresh()->title);
    }

    public function test_renaming_leaves_the_shortcode_slug_alone(): void
    {
        $form = $this->createForm();
        $slug = $form->slug;

        $this->save($form, ['title' => 'Get in Touch', 'fields' => [], 'settings' => []])->assertOk();

        $this->assertSame(
            $slug,
            $form->fresh()->slug,
            'The slug is what every embedded shortcode points at; a rename must not move it.'
        );
    }

    public function test_the_builder_offers_the_name_as_an_editable_field(): void
    {
        $form = $this->createForm('Contact Form');

        $this->actingAs($this->administrator())
            ->get('/admin/forms/'.$form->id.'/builder')
            ->assertOk()
            ->assertSee('id="form-title"', false)
            ->assertSee('value="Contact Form"', false);
    }

    public function test_an_empty_name_is_refused(): void
    {
        $form = $this->createForm();

        $this->save($form, ['title' => '', 'fields' => [], 'settings' => []])
            ->assertStatus(422)
            ->assertJsonValidationErrors('title');

        $this->assertSame('Contact Form', $form->fresh()->title);
    }

    public function test_a_save_that_sends_no_name_keeps_the_one_it_has(): void
    {
        // Anything that posts to this endpoint without a title — an older cached builder page,
        // say — is saving fields, not clearing the name.
        $form = $this->createForm();

        $this->save($form, ['fields' => [['type' => 'text', 'name' => 'x']], 'settings' => []])->assertOk();

        $this->assertSame('Contact Form', $form->fresh()->title);
        $this->assertCount(1, $form->fresh()->fields);
    }

    public function test_two_forms_with_the_same_name_get_different_slugs(): void
    {
        // The slug carries the identity a rename does not, so two forms sharing one would make
        // a shortcode ambiguous — it would render whichever row came back first.
        $first = $this->createForm('Contact Form');
        $second = $this->createForm('Contact Form');

        $this->assertNotSame($first->slug, $second->slug);
    }
}
