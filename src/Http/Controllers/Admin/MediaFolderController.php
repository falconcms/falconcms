<?php

namespace FalconCms\Core\Http\Controllers\Admin;

use FalconCms\Core\Models\Media;
use FalconCms\Core\Models\MediaFolder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

/**
 * Folders in the media library: list, create, rename, move, delete, and moving files into
 * them. The routes sit under admin/media, so they follow the library's own access: Media to
 * look, Media → Add New to change anything.
 */
class MediaFolderController extends Controller
{
    /** The folder tree with file counts, plus the "All files" and "Uncategorized" totals. */
    public function index(): JsonResponse
    {
        $total = Media::query()->count();

        return response()->json([
            'folders' => MediaFolder::tree(),
            'total' => $total,
            // Before the folders migration has run every file is in no folder.
            'uncategorized' => MediaFolder::ready() ? Media::query()->whereNull('folder_id')->count() : $total,
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $name = $this->name($request);
        $parent = $this->parent($request);
        if ($this->taken($name, $parent)) {
            return $this->refuse('A folder named "'.$name.'" is already there.');
        }

        $folder = MediaFolder::create(['name' => $name, 'parent_id' => $parent]);
        falcon_log_activity('created', "Created media folder: {$folder->name}");

        return response()->json(['success' => true, 'folder' => $folder]);
    }

    /** Rename a folder, or move it into another one (parent_id; empty = the top level). */
    public function update(Request $request, MediaFolder $folder): JsonResponse
    {
        $name = $request->has('name') ? $this->name($request) : $folder->name;
        $parent = $request->has('parent_id') ? $this->parent($request) : $folder->parent_id;

        if ($parent !== null && ($parent === $folder->id || in_array($parent, $folder->descendantIds(), true))) {
            return $this->refuse('A folder cannot go inside itself.');
        }
        if (($name !== $folder->name || $parent !== $folder->parent_id) && $this->taken($name, $parent, $folder->id)) {
            return $this->refuse('A folder named "'.$name.'" is already there.');
        }

        $old = $folder->name;
        $folder->update(['name' => $name, 'parent_id' => $parent]);
        if ($old !== $name) {
            falcon_log_activity('updated', "Renamed media folder: {$old} → {$name}");
        }

        return response()->json(['success' => true, 'folder' => $folder]);
    }

    /**
     * Delete a folder — never its files. They go back to Uncategorized, and its subfolders
     * move up into its own parent.
     */
    public function destroy(MediaFolder $folder): JsonResponse
    {
        $name = $folder->name;
        Media::query()->where('folder_id', $folder->id)->update(['folder_id' => null]);
        MediaFolder::query()->where('parent_id', $folder->id)->update(['parent_id' => $folder->parent_id]);
        $folder->delete();
        falcon_log_activity('deleted', "Deleted media folder: {$name}");

        return response()->json(['success' => true]);
    }

    /** Put files into a folder (folder_id empty = Uncategorized). */
    public function move(Request $request): JsonResponse
    {
        $ids = array_values(array_filter(array_map('intval', (array) $request->input('ids', []))));
        if (!$ids) {
            return $this->refuse('No files selected.');
        }
        $folder = $this->parent($request, 'folder_id');

        $moved = Media::query()->whereIn('id', $ids)->update(['folder_id' => $folder]);
        $where = $folder ? MediaFolder::find($folder)?->name : 'Uncategorized';
        falcon_log_activity('updated', "Moved {$moved} media ".($moved === 1 ? 'file' : 'files')." to {$where}");

        return response()->json(['success' => true, 'moved' => $moved, 'folder_id' => $folder]);
    }

    private function name(Request $request): string
    {
        $request->validate(['name' => 'required|string|max:100']);
        $name = trim(preg_replace('/\s+/u', ' ', strip_tags((string) $request->input('name'))));
        if ($name === '') {
            abort(response()->json(['success' => false, 'message' => 'Give the folder a name.'], 422));
        }

        return $name;
    }

    /** A folder id from the request that really exists, or null for the top level. */
    private function parent(Request $request, string $key = 'parent_id'): ?int
    {
        $id = (int) $request->input($key);
        if ($id <= 0) {
            return null;
        }
        if (!MediaFolder::query()->whereKey($id)->exists()) {
            abort(response()->json(['success' => false, 'message' => 'That folder no longer exists.'], 422));
        }

        return $id;
    }

    private function taken(string $name, ?int $parent, ?int $except = null): bool
    {
        return MediaFolder::query()
            ->where(fn ($q) => $parent === null ? $q->whereNull('parent_id') : $q->where('parent_id', $parent))
            ->whereRaw('LOWER(name) = ?', [mb_strtolower($name)])
            ->when($except, fn ($q) => $q->whereKeyNot($except))
            ->exists();
    }

    private function refuse(string $message): JsonResponse
    {
        return response()->json(['success' => false, 'message' => $message], 422);
    }
}
