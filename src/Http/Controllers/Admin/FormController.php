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
