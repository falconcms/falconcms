<?php

namespace FalconCms\Core\Http\Controllers\Admin;

use Illuminate\Routing\Controller;
use FalconCms\Core\Models\FieldGroup;
use FalconCms\Core\Models\Field;
use FalconCms\Core\Models\PostType;
use Illuminate\Http\Request;

class CustomFieldController extends Controller
{
    public function index()
    {
        $fieldGroups = FieldGroup::withCount('fields')->orderBy('order')->get();
        return view('falcon-cms::admin.acpt.fields.index', compact('fieldGroups'));
    }

    public function create()
    {
        $postTypes = PostType::where('is_active', true)->get();
        return view('falcon-cms::admin.acpt.fields.create', compact('postTypes'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'rules' => 'nullable|array',
            'is_active' => 'boolean',
        ]);

        $group = FieldGroup::create([
            'title' => $validated['title'],
            'rules' => $request->rules,
            'is_active' => $request->has('is_active'),
            'order' => $request->order ?? 0,
        ]);

        if ($request->has('fields')) {
            $order = 0;
            foreach ($request->fields as $fieldData) {
                if (empty($fieldData['label']) || empty($fieldData['name'])) continue;
                $type = $fieldData['type'] ?? 'text';
                if ($type === 'repeater') {
                    $sfLabels = $fieldData['sf_label'] ?? [];
                    $sfNames  = $fieldData['sf_name']  ?? [];
                    $sfTypes  = $fieldData['sf_type']  ?? [];
                    $subFields = [];
                    foreach ($sfLabels as $i => $label) {
                        if (empty($label)) continue;
                        $subFields[] = ['label' => $label, 'name' => $sfNames[$i] ?? '', 'type' => $sfTypes[$i] ?? 'text'];
                    }
                    $params = ['sub_fields' => $subFields];
                } else {
                    $params = ['options' => $fieldData['options'] ?? ''];
                }
                Field::create([
                    'field_group_id' => $group->id,
                    'label'          => $fieldData['label'],
                    'name'           => $fieldData['name'],
                    'type'           => $type,
                    'instructions'   => $fieldData['instructions'] ?? null,
                    'required'       => isset($fieldData['required']),
                    'params'         => $params,
                    'order'          => $order++,
                ]);
            }
        }

        return redirect()->route('admin.acpt.fields.index')->with('success', 'Field group and fields created successfully.');
    }

    public function exportGroup(FieldGroup $field)
    {
        $field->load('fields');
        return falcon_export_response('falcon_field_group', [
            'title'       => $field->title,
            'description' => $field->description,
            'rules'       => $field->rules,
            'order'       => (int) $field->order,
            'is_active'   => (bool) $field->is_active,
            'fields'      => $field->fields->map(fn ($f) => [
                'label'        => $f->label,
                'name'         => $f->name,
                'type'         => $f->type,
                'instructions' => $f->instructions,
                'required'     => (bool) $f->required,
                'params'       => $f->params,
                'order'        => (int) $f->order,
            ])->values()->all(),
        ], (\Illuminate\Support\Str::slug($field->title ?: 'field-group') ?: 'field-group') . '-fields');
    }

    public function importGroup(Request $request)
    {
        $request->validate(['import_file' => ['required', 'file', 'max:5120']]);
        $d = falcon_read_import($request, 'import_file', 'falcon_field_group');
        if ($d === null) {
            return back()->with('error', 'That is not a valid Field Group export file.');
        }

        $group = FieldGroup::create([
            'title'       => $d['title'] ?? 'Imported Field Group',
            'description' => $d['description'] ?? null,
            'rules'       => is_array($d['rules'] ?? null) ? $d['rules'] : null,
            'order'       => (int) ($d['order'] ?? 0),
            'is_active'   => (bool) ($d['is_active'] ?? true),
        ]);

        $order = 0;
        foreach ((is_array($d['fields'] ?? null) ? $d['fields'] : []) as $f) {
            if (empty($f['label']) || empty($f['name'])) continue;
            Field::create([
                'field_group_id' => $group->id,
                'label'          => $f['label'],
                'name'           => $f['name'],
                'type'           => $f['type'] ?? 'text',
                'instructions'   => $f['instructions'] ?? null,
                'required'       => (bool) ($f['required'] ?? false),
                'params'         => is_array($f['params'] ?? null) ? $f['params'] : [],
                'order'          => (int) ($f['order'] ?? $order),
            ]);
            $order++;
        }

        return redirect()->route('admin.acpt.fields.index')->with('success', 'Field group imported successfully.');
    }

    public function edit(FieldGroup $field)
    {
        $field->load('fields');
        $postTypes = PostType::where('is_active', true)->get();
        return view('falcon-cms::admin.acpt.fields.edit', ['fieldGroup' => $field, 'postTypes' => $postTypes]);
    }

    public function update(Request $request, FieldGroup $field)
    {
        $request->validate([
            'title' => 'required|string|max:255',
        ]);

        $field->update([
            'title' => $request->title,
            'rules' => $request->rules,
            'is_active' => $request->has('is_active'),
            'order' => $request->order ?? 0,
        ]);

        // Update existing fields
        if ($request->has('fields')) {
            foreach ($request->fields as $id => $data) {
                $type = $data['type'];
                if ($type === 'repeater') {
                    $sfLabels = $data['sf_label'] ?? [];
                    $sfNames  = $data['sf_name']  ?? [];
                    $sfTypes  = $data['sf_type']  ?? [];
                    $subFields = [];
                    foreach ($sfLabels as $i => $label) {
                        if (empty($label)) continue;
                        $subFields[] = ['label' => $label, 'name' => $sfNames[$i] ?? '', 'type' => $sfTypes[$i] ?? 'text'];
                    }
                    $params = json_encode(['sub_fields' => $subFields]);
                } else {
                    $params = json_encode(['options' => $data['options'] ?? '']);
                }
                Field::where('id', $id)->update([
                    'label'    => $data['label'],
                    'name'     => $data['name'],
                    'type'     => $type,
                    'required' => isset($data['required']),
                    'params'   => $params,
                ]);
            }
        }

        // Create new fields added during this edit session
        if ($request->has('new_fields')) {
            foreach ($request->new_fields as $data) {
                if (empty($data['label']) || empty($data['name'])) continue;
                $type = $data['type'] ?? 'text';
                if ($type === 'repeater') {
                    $sfLabels = $data['sf_label'] ?? [];
                    $sfNames  = $data['sf_name']  ?? [];
                    $sfTypes  = $data['sf_type']  ?? [];
                    $subFields = [];
                    foreach ($sfLabels as $i => $label) {
                        if (empty($label)) continue;
                        $subFields[] = ['label' => $label, 'name' => $sfNames[$i] ?? '', 'type' => $sfTypes[$i] ?? 'text'];
                    }
                    $params = ['sub_fields' => $subFields];
                } else {
                    $params = ['options' => $data['options'] ?? ''];
                }
                Field::create([
                    'field_group_id' => $field->id,
                    'label'          => $data['label'],
                    'name'           => $data['name'],
                    'type'           => $type,
                    'required'       => isset($data['required']),
                    'params'         => $params,
                ]);
            }
        }

        return redirect()->back()->with('success', 'Field group updated successfully.');
    }

    public function destroy(FieldGroup $field)
    {
        $field->delete();
        return redirect()->route('admin.acpt.fields.index')->with('success', 'Field group deleted.');
    }

    // AJAX store/delete for individual fields
    public function storeField(Request $request)
    {
        $request->validate([
            'field_group_id' => 'required|exists:custom_field_groups,id',
            'label' => 'required|string',
            'name' => 'required|string',
            'type' => 'required|string',
        ]);

        $field = Field::create($request->all());
        return response()->json(['success' => true, 'field' => $field]);
    }

    public function deleteField(Field $field)
    {
        $field->delete();
        return response()->json(['success' => true]);
    }
}
