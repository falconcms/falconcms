<?php

namespace FalconCms\Core\Tests\Feature\Security;

use FalconCms\Core\Models\Form;
use FalconCms\Core\Models\FormSubmission;
use FalconCms\Core\Tests\TestCase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

/**
 * Public form file uploads.
 *
 * A form's file input is only a hint in the browser; a visitor can POST any file, to any field
 * name, straight past it. The server is the real gate: it must take a file only for a field the
 * form declares as a file field, refuse executable or unknown extensions, cap the size, and
 * never keep the visitor's own filename. Without that, a .php dropped in the public uploads
 * folder is a web shell the moment Apache/Nginx serves that folder.
 */
class FormUploadTest extends TestCase
{
    private function form(array $fields): Form
    {
        return Form::create([
            'title' => 'Contact', 'slug' => 'contact-'.uniqid(), 'status' => true, 'lang_code' => 'en',
            'fields' => $fields, 'settings' => ['success_message' => 'Thanks'],
        ]);
    }

    private function fileForm(): Form
    {
        return $this->form([
            ['type' => 'text', 'name' => 'name', 'label' => 'Name'],
            ['type' => 'file', 'name' => 'cv', 'label' => 'CV'],
        ]);
    }

    private function submit(Form $form, array $data, array $files = [])
    {
        // UploadedFile instances go in the body with the rest; post()'s later args are headers.
        return $this->post('/form-submit', array_merge(['form_id' => $form->id], $data, $files));
    }

    public function test_a_php_upload_is_rejected(): void
    {
        Storage::fake('public');
        $form = $this->fileForm();

        $this->submit($form, ['name' => 'Bob'], ['cv' => UploadedFile::fake()->createWithContent('shell.php', '<?php echo 1;')])
            ->assertStatus(422);

        $this->assertSame(0, FormSubmission::count());
        $this->assertEmpty(Storage::disk('public')->allFiles('form-uploads'));
    }

    public function test_a_double_extension_is_judged_on_its_last_part(): void
    {
        Storage::fake('public');
        $form = $this->fileForm();

        $this->submit($form, ['name' => 'Bob'], ['cv' => UploadedFile::fake()->createWithContent('invoice.pdf.php', 'x')])
            ->assertStatus(422);
        $this->assertEmpty(Storage::disk('public')->allFiles('form-uploads'));
    }

    public function test_an_allowed_file_is_stored_under_a_random_safe_name(): void
    {
        Storage::fake('public');
        $form = $this->fileForm();

        $this->submit($form, ['name' => 'Bob'], ['cv' => UploadedFile::fake()->create('my resume.pdf', 20, 'application/pdf')])
            ->assertOk()
            ->assertJson(['success' => true]);

        $files = Storage::disk('public')->allFiles('form-uploads');
        $this->assertCount(1, $files);
        $this->assertMatchesRegularExpression('#^form-uploads/[0-9a-f-]{36}\.pdf$#', $files[0]);
        $this->assertStringNotContainsString('resume', $files[0]);
        $this->assertSame($files[0], FormSubmission::first()->data['cv']);
    }

    public function test_a_file_for_a_field_the_form_never_declared_is_dropped(): void
    {
        Storage::fake('public');
        // The form has no file field at all; a file posted under an invented name is ignored.
        $form = $this->form([['type' => 'text', 'name' => 'name', 'label' => 'Name']]);

        $this->submit($form, ['name' => 'Bob'], ['attachment' => UploadedFile::fake()->create('x.pdf', 10)])
            ->assertOk();

        $this->assertEmpty(Storage::disk('public')->allFiles('form-uploads'));
        $this->assertSame('Bob', FormSubmission::first()->data['name']);
        $this->assertArrayNotHasKey('attachment', FormSubmission::first()->data);
    }

    public function test_an_oversized_file_is_rejected(): void
    {
        Storage::fake('public');
        config(['falcon-options.form_upload_max_kb' => 100]);
        $form = $this->fileForm();

        $this->submit($form, ['name' => 'Bob'], ['cv' => UploadedFile::fake()->create('big.pdf', 200)])
            ->assertStatus(422);
        $this->assertEmpty(Storage::disk('public')->allFiles('form-uploads'));
    }

    public function test_the_extension_allowlist_helper(): void
    {
        $this->assertSame('pdf', falcon_safe_upload_extension('resume.pdf'));
        $this->assertSame('jpg', falcon_safe_upload_extension('photo.JPEG'));
        $this->assertNull(falcon_safe_upload_extension('shell.php'));
        $this->assertNull(falcon_safe_upload_extension('x.phtml'));
        $this->assertNull(falcon_safe_upload_extension('archive.phar'));
        $this->assertNull(falcon_safe_upload_extension('.htaccess'));
        $this->assertNull(falcon_safe_upload_extension('noextension'));
        $this->assertNull(falcon_safe_upload_extension('trick.php.jpg.php'));
    }
}
