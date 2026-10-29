<?php

namespace FalconCms\Core\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Schema;
use Throwable;

/**
 * A folder in the media library. Folders only group files: a file keeps its own address
 * whichever folder it is in, and a folder can sit inside another one.
 *
 * @property int $id
 * @property string $name
 * @property int|null $parent_id
 */
class MediaFolder extends Model
{
    protected $table = 'cms_media_folders';

    protected $fillable = ['name', 'parent_id'];

    protected $casts = ['parent_id' => 'integer'];

    private static ?bool $ready = null;

    /**
     * Whether folders can be used yet. Right after an update the new code runs before its
     * migration (or a site updated with composer alone never ran it): until the table and the
     * media column exist, the library works as it did without folders instead of failing.
     */
    public static function ready(): bool
    {
        if (self::$ready === null) {
            try {
                self::$ready = Schema::hasTable('cms_media_folders') && Schema::hasColumn('media', 'folder_id');
            } catch (Throwable $e) {
                self::$ready = false;
            }
        }

        return self::$ready;
    }

    /** Forget the answer (tests that migrate mid-run). */
    public static function flushReady(): void
    {
        self::$ready = null;
    }

    public function media(): HasMany
    {
        return $this->hasMany(Media::class, 'folder_id');
    }

    /**
     * Every folder with the number of files directly in it, in tree order (each folder
     * followed by its own subfolders, by name) and with its depth — what the folder lists in
     * the library and in the media window show.
     *
     * @return array<int, array{id: int, name: string, parent_id: int|null, depth: int, count: int}>
     */
    public static function tree(): array
    {
        if (!static::ready()) {
            return [];
        }
        $counts = Media::query()->whereNotNull('folder_id')->selectRaw('folder_id, COUNT(*) as n')->groupBy('folder_id')->pluck('n', 'folder_id');
        $byParent = [];
        foreach (static::query()->orderBy('name')->get(['id', 'name', 'parent_id']) as $f) {
            $byParent[(int) $f->parent_id][] = $f;
        }

        $out = [];
        $walk = function (int $parent, int $depth) use (&$walk, &$out, $byParent, $counts) {
            foreach ($byParent[$parent] ?? [] as $f) {
                $out[] = ['id' => $f->id, 'name' => $f->name, 'parent_id' => $f->parent_id, 'depth' => $depth, 'count' => (int) ($counts[$f->id] ?? 0)];
                // A broken loop in the data (a folder inside itself) must not hang the page.
                if ($depth < 20) {
                    $walk($f->id, $depth + 1);
                }
            }
        };
        $walk(0, 0);

        return $out;
    }

    /** The ids of this folder's subfolders, all the way down. @return int[] */
    public function descendantIds(): array
    {
        $ids = [];
        $level = [$this->id];
        for ($i = 0; $level && $i < 20; $i++) {
            $level = static::query()->whereIn('parent_id', $level)->pluck('id')->all();
            $ids = array_merge($ids, $level);
        }

        return $ids;
    }
}
