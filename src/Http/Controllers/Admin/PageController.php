<?php

namespace Acme\CmsDashboard\Http\Controllers\Admin;

use Illuminate\Routing\Controller;
use Acme\CmsDashboard\Models\Page;
use Acme\CmsDashboard\Models\Revision;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;
use Acme\CmsDashboard\Services\BuilderShortcodeConverter;

class PageController extends Controller
{
    protected function generateUniqueSlug($title, $id = 0, $langCode = 'en')
    {
        // If string contains non-ascii characters OR lang is not english, use native slug logic
        if ($langCode !== 'en' || preg_match('/[^\x00-\x7F]/', $title)) {
            $slug = mb_strtolower($title, 'UTF-8');
            $slug = str_replace(' ', '-', trim($slug));
            // Keep letters (\p{L}), marks/vowels (\p{M}), numbers (\p{N}), and dashes.
            $slug = preg_replace('/[^\p{L}\p{M}\p{N}\-]+/u', '', $slug);
            $slug = preg_replace('/-+/', '-', $slug);
            $slug = trim($slug, '-');
        } else {
            $slug = Str::slug($title);
        }
        
        if (empty($slug)) {
            $slug = 'page-' . time();
        }

        $originalSlug = $slug;
        $count = 1;
        while (Page::withTrashed()
            ->where('slug', $slug)
            ->where('id', '!=', $id)
            ->exists()) {
            $slug = "{$originalSlug}-{$count}";
            $count++;
        }
        return $slug;
    }

    public function index(Request $request)
    {
        $status = $request->query('status');
        $lang = $request->query('lang');
        $query = Page::query();

        if ($lang && $lang !== 'all') {
            $query->where('lang_code', $lang);
        }

        if ($status === 'trash') {
            $query->onlyTrashed();
        } else {
            $query->withoutTrashed();
            if ($status) {
                $query->where('status', $status);
            }
        }

        // Search
        if ($request->filled('s')) {
            $query->where(function($q) use ($request) {
                $q->where('title', 'like', '%' . $request->s . '%')
                  ->orWhere('content', 'like', '%' . $request->s . '%');
            });
        }

        // Date Filter
        if ($request->filled('m') && $request->m != '-1') {
            $year = substr($request->m, 0, 4);
            $month = substr($request->m, 4, 2);
            $query->whereYear('created_at', $year)->whereMonth('created_at', $month);
        }

        $pages = $query->latest()->paginate(10)->withQueryString();

        $driver = \DB::connection()->getDriverName();
        $yearCol  = $driver === 'sqlite' ? "strftime('%Y', created_at)" : 'YEAR(created_at)';
        $monthCol = $driver === 'sqlite' ? "strftime('%m', created_at)" : 'MONTH(created_at)';
        $dates = Page::selectRaw("$yearCol as year, $monthCol as month")
            ->groupByRaw("$yearCol, $monthCol")
            ->orderByRaw("$yearCol DESC, $monthCol DESC")
            ->get();

        $countQuery = Page::query();
        if ($lang && $lang !== 'all') {
            $countQuery->where('lang_code', $lang);
        }

        $allCount = (clone $countQuery)->count();
        $publishedCount = (clone $countQuery)->where('status', 'published')->count();
        $draftCount = (clone $countQuery)->where('status', 'draft')->count();
        $scheduledCount = (clone $countQuery)->where('status', 'scheduled')->count();
        $trashCount = (clone $countQuery)->onlyTrashed()->count();

        return view('cms-dashboard::admin.pages.index', compact('pages', 'dates', 'allCount', 'publishedCount', 'draftCount', 'scheduledCount', 'trashCount'));
    }

    public function create()
    {
        $allPages = Page::orderBy('title')->get();
        
        // Fetch applicable custom field groups for pages
        $fieldGroups = \Acme\CmsDashboard\Models\FieldGroup::where('is_active', true)
            ->where(function($q) {
                $q->whereJsonContains('rules->post_type', 'page');
            })
            ->with('fields')
            ->orderBy('order')
            ->get();

        return view('cms-dashboard::admin.pages.create', compact('allPages', 'fieldGroups'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'content' => 'nullable|string',
            'status' => 'required|in:draft,published,scheduled',
            'parent_id' => 'nullable|exists:posts,id',
            'menu_order' => 'nullable|integer',
            'template' => 'nullable|string',
            'published_at' => 'nullable|date',
            'featured_image' => 'nullable',
            'editor_type' => 'nullable|string|in:rich,builder',
            'lang_code' => 'nullable|string|max:10',
            'seo' => 'nullable|array',
        ]);

        $validated['seo_meta'] = $request->input('seo');
        unset($validated['seo']);

        $lang = $request->input('lang_code');
        if (!$lang || $lang === 'all') {
            $lang = app()->getLocale();
        }
        $validated['lang_code'] = $lang;
        $validated['slug'] = $this->generateUniqueSlug($validated['title'], 0, $validated['lang_code']);
        $validated['user_id'] = auth()->id();

        if ($request->hasFile('featured_image')) {
            $validated['featured_image'] = $request->file('featured_image')->store('pages', 'public');
        } elseif ($request->filled('featured_image')) {
            $validated['featured_image'] = $request->input('featured_image');
        }

        if (empty($validated['template']) || $validated['template'] === 'default') {
            $validated['template'] = 'site-width';
        }

        // Interpret the publish date in the CMS timezone → store UTC, and decide status on the server.
        $validated = lazy_normalize_publish($validated);

        $page = Page::create($validated);

        // Save Custom Fields
        if ($request->has('custom_fields')) {
            foreach ($request->custom_fields as $fieldId => $value) {
                DB::table('post_custom_field_values')->insert([
                    'post_id' => $page->id,
                    'field_id' => $fieldId,
                    'value' => is_array($value) ? json_encode($value) : $value,
                    'created_at' => now(), 'updated_at' => now()
                ]);
            }
        }

        lazy_log_activity('created', "Created a new page: {$page->title}", $page);
        
        // Multilingual Copy Logic
        if ($request->has('make_multilingual_copy') && $request->has('copy_to_languages')) {
            foreach ($request->copy_to_languages as $langCode) {
                $clone = $page->replicate();
                $clone->lang_code = $langCode;
                $clone->origin_id = $page->id;
                
                $clone->title = lazy_translate($page->title, $langCode);
                $clone->slug = $this->generateUniqueSlug($clone->title, 0, $langCode);
                
                if ($page->editor_type === 'rich') {
                    $clone->content = lazy_translate($page->content, $langCode);
                }
                
                if ($page->excerpt) {
                    $clone->excerpt = lazy_translate($page->excerpt, $langCode);
                }
                
                $clone->save();
            }
        }

        if ($request->has('redirect_to_builder')) {
            return redirect()->route('admin.pages.edit', ['page' => $page, 'start_builder' => 1])->with('success', 'Page created successfully.');
        }

        return redirect()->route('admin.pages.edit', $page)->with('success', 'Page created successfully.');
    }

    public function edit(Page $page)
    {
        $allPages = Page::where('id', '!=', $page->id)->orderBy('title')->get();
        
        // Fetch applicable custom field groups for pages
        $fieldGroups = \Acme\CmsDashboard\Models\FieldGroup::where('is_active', true)
            ->where(function($q) {
                $q->whereJsonContains('rules->post_type', 'page');
            })
            ->with('fields')
            ->orderBy('order')
            ->get();

        // Get existing field values
        $fieldValues = DB::table('post_custom_field_values')
            ->where('post_id', $page->id)
            ->pluck('value', 'field_id')
            ->toArray();

        if (!empty($page->content) && BuilderShortcodeConverter::isBuilderJson($page->content)) {
            $page->content = BuilderShortcodeConverter::jsonToShortcodes($page->content);
        }

        // Pending autosave (recovery banner) + revision count for the Revisions button
        $pendingAutosave = null;
        $revisionCount = 0;
        try {
            if (\Illuminate\Support\Facades\Schema::hasTable('cms_revisions')) {
                $base = Revision::where('revisionable_type', $page->getMorphClass())
                    ->where('revisionable_id', $page->getKey());
                $revisionCount = (clone $base)->where('type', 'revision')->count();
                $auto = (clone $base)->where('type', 'autosave')->first();
                if ($auto && $page->updated_at && $auto->updated_at->gt($page->updated_at)) {
                    $pendingAutosave = ['id' => $auto->id, 'time' => $auto->updated_at->format('M j, Y g:i A')];
                }
            }
        } catch (\Throwable $e) {}

        return view('cms-dashboard::admin.pages.edit', compact('page', 'allPages', 'fieldGroups', 'fieldValues', 'pendingAutosave', 'revisionCount'));
    }

    /** Full revisions comparison page for a Page — before/after diff + restore. */
    public function revisionsPage(Request $request, $id)
    {
        $page = Page::findOrFail($id);
        $revs = Revision::where('revisionable_type', $page->getMorphClass())
            ->where('revisionable_id', $page->getKey())
            ->orderByDesc('id')->with('user:id,name')->limit(80)->get();

        $entries = [];
        $entries['current'] = ['key' => 'current', 'label' => 'Current Version (live)', 'title' => $page->title, 'content' => $page->content, 'meta' => 'Currently published', 'type' => 'current'];
        foreach ($revs as $r) {
            $entries[(string) $r->id] = [
                'key'     => (string) $r->id,
                'label'   => ($r->type === 'autosave' ? 'Autosave' : 'Revision') . ' — ' . $r->created_at->format('M j, Y g:i A'),
                'title'   => $r->title,
                'content' => $r->content,
                'meta'    => ($r->user->name ?? 'System') . ' · ' . $r->created_at->diffForHumans(),
                'type'    => $r->type,
            ];
        }
        $to   = (string) $request->get('to', 'current');
        $from = (string) $request->get('from', $revs->count() ? (string) $revs->first()->id : 'current');
        if (!isset($entries[$to]))   $to = 'current';
        if (!isset($entries[$from])) $from = $to;
        $diff = lazy_revision_diff($entries[$from]['content'] ?? '', $entries[$to]['content'] ?? '');

        return view('cms-dashboard::admin.pages.revisions', compact('page', 'entries', 'from', 'to', 'diff'));
    }

    /** Page autosave — snapshots title + content without touching the live page. */
    public function autosaveClassic(Request $request, $id)
    {
        $page = Page::findOrFail($id);
        $draft = clone $page;
        $draft->title   = $request->input('title', $page->title);
        $draft->content = $request->input('content', $page->content);
        $rev = Revision::snapshot($draft, 'autosave');
        return response()->json(['success' => (bool) $rev, 'time' => $rev ? $rev->updated_at->format('g:i:s A') : null]);
    }

    /** Restore a revision's title + content onto the page. */
    public function restoreRevisionClassic(Request $request, $id, $revisionId)
    {
        $page = Page::findOrFail($id);
        $rev  = Revision::where('revisionable_type', $page->getMorphClass())
            ->where('revisionable_id', $page->getKey())->findOrFail($revisionId);
        Revision::snapshot($page, 'revision');
        $page->update(['title' => $rev->title ?: $page->title, 'content' => $rev->content]);
        Revision::clearAutosave($page);
        clear_page_cache();
        return redirect()->route('admin.pages.edit', $page)->with('success', 'Revision restored.');
    }

    /** Delete a single page revision. */
    public function deleteRevision(Request $request, $id, $revisionId)
    {
        $page = Page::findOrFail($id);
        Revision::where('revisionable_type', $page->getMorphClass())
            ->where('revisionable_id', $page->getKey())->where('id', $revisionId)->delete();
        return redirect()->route('admin.pages.revisions', $page->id)->with('success', 'Revision deleted.');
    }

    /** Delete all revisions for a page. */
    public function clearRevisions(Request $request, $id)
    {
        $page = Page::findOrFail($id);
        Revision::where('revisionable_type', $page->getMorphClass())
            ->where('revisionable_id', $page->getKey())->delete();
        return redirect()->route('admin.pages.revisions', $page->id)->with('success', 'All revisions cleared.');
    }

    public function update(Request $request, Page $page)
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'content' => 'nullable|string',
            'status' => 'required|in:draft,published,scheduled',
            'parent_id' => 'nullable|exists:posts,id',
            'menu_order' => 'nullable|integer',
            'template' => 'nullable|string',
            'slug' => 'nullable|string|max:255',
            'published_at' => 'nullable|date',
            'featured_image' => 'nullable',
            'editor_type' => 'nullable|string|in:rich,builder',
            'lang_code' => 'nullable|string|max:10',
            'seo' => 'nullable|array'
        ]);

        $validated['seo_meta'] = $request->input('seo');
        unset($validated['seo']);

        if (empty($validated['slug'])) {
            $validated['slug'] = $this->generateUniqueSlug($validated['title'], $page->id, $page->lang_code);
        } else {
            $validated['slug'] = $this->generateUniqueSlug($validated['slug'], $page->id, $page->lang_code);
        }

        if ($request->hasFile('featured_image')) {
            if ($page->featured_image && !\Acme\CmsDashboard\Models\Media::where('path', $page->featured_image)->exists()) {
                \Illuminate\Support\Facades\Storage::disk('public')->delete($page->featured_image);
            }
            $validated['featured_image'] = $request->file('featured_image')->store('pages', 'public');
        } elseif ($request->input('remove_featured_image') === '1') {
            if ($page->featured_image && !\Acme\CmsDashboard\Models\Media::where('path', $page->featured_image)->exists()) {
                \Illuminate\Support\Facades\Storage::disk('public')->delete($page->featured_image);
            }
            $validated['featured_image'] = null;
        } elseif ($request->has('featured_image')) {
            $validated['featured_image'] = $request->input('featured_image');
        }

        if (empty($validated['template']) || $validated['template'] === 'default') {
            $validated['template'] = 'site-width';
        }

        $oldSlug = $page->getOriginal('slug');

        // Protect builder content ONLY when staying in builder mode with non-builder (empty/HTML) content.
        // Explicit rich-mode edits are always saved so they appear on the front-end.
        $currentContent = $page->content;
        $isCurrentBuilder = $page->editor_type === 'builder' || (is_string($currentContent) && (\Illuminate\Support\Str::startsWith($currentContent, '[') || \Illuminate\Support\Str::startsWith($currentContent, '{')));
        $targetEditorType = $validated['editor_type'] ?? $page->editor_type;
        $incoming = $validated['content'] ?? '';
        $isIncomingBuilder = is_string($incoming) && (\Illuminate\Support\Str::startsWith($incoming, '[') || \Illuminate\Support\Str::startsWith($incoming, '{'));
        if ($isCurrentBuilder && $targetEditorType === 'builder' && !$isIncomingBuilder) {
            unset($validated['content']);
        }

        // Snapshot the PRIOR state BEFORE overwriting (true undo on restore)
        Revision::snapshot($page, 'revision');

        // Interpret the publish date in the CMS timezone → store UTC, and decide status on the server.
        $validated = lazy_normalize_publish($validated);

        $page->update($validated);
        Revision::clearAutosave($page);

        // Automatic Redirection Logic
        if ($oldSlug !== $page->slug) {
            $oldUrl = '/' . ltrim($oldSlug, '/');
            $newUrl = '/' . ltrim($page->slug, '/');

            if ($oldUrl !== $newUrl) {
                \Acme\CmsDashboard\Models\Redirect::updateOrCreate(
                    ['old_url' => $oldUrl],
                    ['new_url' => $newUrl, 'status_code' => 301]
                );
            }
        }

        // Update Custom Fields
        if ($request->has('custom_fields')) {
            foreach ($request->custom_fields as $fieldId => $value) {
                DB::table('post_custom_field_values')->updateOrInsert(
                    ['post_id' => $page->id, 'field_id' => $fieldId],
                    [
                        'value' => is_array($value) ? json_encode($value) : $value,
                        'updated_at' => now()
                    ]
                );
            }
        }

        lazy_log_activity('updated', "Updated page: {$page->title}", $page);

        // Multilingual Copy Logic for Edit
        if ($request->has('make_multilingual_copy') && $request->has('copy_to_languages')) {
            foreach ($request->copy_to_languages as $langCode) {
                $exists = Page::where('origin_id', $page->id)->where('lang_code', $langCode)->exists();
                if ($exists) continue;

                $clone = $page->replicate();
                $clone->lang_code = $langCode;
                $clone->origin_id = $page->id;
                
                $clone->title = lazy_translate($page->title, $langCode);
                $clone->slug = $this->generateUniqueSlug($clone->title, 0, $langCode);
                
                if ($page->editor_type === 'rich') {
                    $clone->content = lazy_translate($page->content, $langCode);
                }
                
                if ($page->excerpt) {
                    $clone->excerpt = lazy_translate($page->excerpt, $langCode);
                }
                
                $clone->save();
            }
        }

        return back()->with('success', 'Page updated successfully.');
    }

    public function destroy(Page $page)
    {
        $title = $page->title;
        $page->delete();
        lazy_log_activity('deleted', "Moved page to trash: {$title}", $page);
        return redirect()->route('admin.pages.index')->with('success', 'Page moved to trash.');
    }

    public function restore($id)
    {
        $page = Page::onlyTrashed()->findOrFail($id);
        $page->restore();
        return back()->with('success', 'Page restored successfully.');
    }

    public function forceDelete($id)
    {
        $page = Page::onlyTrashed()->findOrFail($id);
        $page->forceDelete();
        return back()->with('success', 'Page deleted permanently.');
    }

    public function bulk(Request $request)
    {
        $action = $request->input('action') !== '-1' ? $request->input('action') : $request->input('action2');
        if ($action === '-1' || !$request->has('post_ids')) {
            return back()->with('error', 'Please select an action and at least one item.');
        }

        $ids = $request->input('post_ids');

        if ($action === 'trash') {
            $pages = Page::whereIn('id', $ids)->get();
            foreach ($pages as $page) {
                $page->delete();
                lazy_log_activity('deleted', "Moved page to trash: {$page->title}", $page);
            }
            return back()->with('success', count($ids) . ' pages moved to trash.');
        } elseif ($action === 'restore') {
            $pages = Page::onlyTrashed()->whereIn('id', $ids)->get();
            foreach ($pages as $page) {
                $page->restore();
                lazy_log_activity('restored', "Restored page from trash: {$page->title}", $page);
            }
            return back()->with('success', count($ids) . ' pages restored.');
        } elseif ($action === 'delete') {
            $pages = Page::onlyTrashed()->whereIn('id', $ids)->get();
            foreach ($pages as $page) {
                $title = $page->title;
                $page->forceDelete();
                lazy_log_activity('deleted', "Deleted page permanently: {$title}", $page);
            }
            return back()->with('success', count($ids) . ' pages deleted permanently.');
        } elseif (in_array($action, ['draft', 'published'])) {
            $pages = Page::whereIn('id', $ids)->get();
            foreach ($pages as $page) {
                $page->update(['status' => $action]);
                lazy_log_activity('updated', "Updated page status to {$action}: {$page->title}", $page);
            }
            return back()->with('success', count($ids) . ' pages marked as ' . ucfirst($action) . '.');
        }

        return back();
    }
}
