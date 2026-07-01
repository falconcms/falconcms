<?php

namespace FalconCms\Core\Http\Controllers\Admin;

use Illuminate\Routing\Controller;
use FalconCms\Core\Models\Form;
use FalconCms\Core\Models\FormSubmission;
use Illuminate\Support\Facades\Storage;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class FormController extends Controller
{
    public function index()
    {
        $forms = Form::withCount('submissions')->latest()->paginate(10);
        return view('falcon-cms::admin.forms.index', compact('forms'));
    }

    public function create()
    {
        return view('falcon-cms::admin.forms.create');
    }

    public function store(Request $request)
    {
        $request->validate([
            'title' => 'required|string|max:255',
        ]);

        $form = Form::create([
            'title' => $request->title,
            'slug' => Str::slug($request->title),
            'status' => true,
            'lang_code' => app()->getLocale(),
        ]);

        return redirect()->route('admin.forms.builder', $form->id)->with('success', 'Form created successfully. Now build it!');
    }

    public function builder($id)
    {
        $form = Form::findOrFail($id);
        return view('falcon-cms::admin.forms.builder', compact('form'));
    }

    public function saveBuilder(Request $request, $id)
    {
        $form = Form::findOrFail($id);
        $form->update([
            'fields'   => $request->input('fields'),
            'settings' => $request->input('settings'),
        ]);

        return response()->json(['success' => true, 'message' => 'Form saved successfully.']);
    }

    /** Download a form (structure + settings, no submissions) as a portable .json file. */
    public function export($id)
    {
        $form = Form::findOrFail($id);

        $payload = [
            '_type'       => 'falcon_form',
            'version'     => 1,
            'exported_at' => now()->toIso8601String(),
            'form'        => [
                // The whole form (labels, validation, success message, layout,
                // colours, etc.) lives in fields + settings — that's everything.
                'title'    => $form->title,
                'fields'   => $form->fields   ?? [],
                'settings' => $form->settings ?? [],
            ],
        ];

        $filename = (Str::slug($form->title ?: 'form') ?: 'form') . '-form.json';

        return response()->json(
            $payload,
            200,
            ['Content-Disposition' => 'attachment; filename="' . $filename . '"'],
            JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
        );
    }

    /** Create a new form from an uploaded .json export (portable across FalconCMS sites). */
    public function import(Request $request)
    {
        $request->validate([
            'form_file' => [
                'required', 'file', 'max:5120',
                function ($attribute, $value, $fail) {
                    if (strtolower($value->getClientOriginalExtension()) !== 'json') {
                        $fail('Please upload a .json form export file.');
                    }
                },
            ],
        ], ['form_file.max' => 'The file is too large (max 5 MB).']);

        $data = json_decode((string) file_get_contents($request->file('form_file')->getRealPath()), true);

        if (!is_array($data) || ($data['_type'] ?? null) !== 'falcon_form' || empty($data['form']) || !is_array($data['form'])) {
            return back()->with('error', 'That file is not a valid FalconCMS form export.');
        }

        $f     = $data['form'];
        $title = trim((string) ($f['title'] ?? '')) ?: 'Imported Form';

        $form = Form::create([
            'title'    => $title,
            'slug'     => $this->uniqueFormSlug($title),
            'fields'   => is_array($f['fields']   ?? null) ? $f['fields']   : [],
            'settings' => is_array($f['settings'] ?? null) ? $f['settings'] : [],
            'status'   => true,
        ]);

        if (function_exists('falcon_log_activity')) {
            falcon_log_activity('created', "Imported form: {$form->title}", $form);
        }

        return redirect()->route('admin.forms.builder', $form->id)
            ->with('success', 'Form imported successfully — review it and save.');
    }

    /** Build a slug that doesn't collide with an existing form. */
    private function uniqueFormSlug(string $title): string
    {
        $base = Str::slug($title) ?: 'form';
        $slug = $base;
        $i = 1;
        while (Form::where('slug', $slug)->exists()) {
            $slug = $base . '-' . $i;
            $i++;
        }
        return $slug;
    }

    public function submissions($id)
    {
        $form = Form::findOrFail($id);
        $form->submissions()->where('is_read', false)->update(['is_read' => true]);
        $submissions = $form->submissions()->latest()->paginate(20);
        return view('falcon-cms::admin.forms.submissions', compact('form', 'submissions'));
    }

    public function allSubmissions()
    {
        $submissions = FormSubmission::with('form')->latest()->paginate(20);
        $form = null;
        return view('falcon-cms::admin.forms.submissions', compact('form', 'submissions'));
    }

    public function destroySubmission(FormSubmission $submission)
    {
        $formId = $submission->form_id;
        // Delete any uploaded files stored in the submission
        if (is_array($submission->data)) {
            foreach ($submission->data as $value) {
                if (is_string($value) && str_starts_with($value, 'form-uploads/')) {
                    Storage::disk('public')->delete($value);
                }
            }
        }
        $submission->delete();
        return redirect()->route('admin.forms.submissions', $formId)->with('success', 'Submission deleted.');
    }

    public function destroy(Form $form)
    {
        $form->delete();
        return redirect()->route('admin.forms.index')->with('success', 'Form deleted successfully.');
    }
}
