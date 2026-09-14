<?php

namespace FalconCms\Core\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * A content type — the built-in Post and Page, plus whatever ACPT defines.
 *
 * The columns are spelled out because static analysis cannot see through Eloquent's
 * `__get`, and without them every `$postType->slug` in the codebase reads as an access
 * to an undefined property.
 *
 * @property int $id
 * @property string $name plural label, and the sidebar menu's title
 * @property string|null $singular_name
 * @property string $slug the post `type` value these rows are stored under
 * @property string|null $description
 * @property string|null $icon a Material Symbols name, or a raw <svg> blob on older rows
 * @property array<int, string>|null $supports
 * @property bool $is_builtin
 * @property bool $is_active
 * @property bool $show_in_menu
 * @property int|null $menu_after id of the sidebar menu this type is placed after
 * @property bool $is_public
 */
class PostType extends Model
{
    use SoftDeletes;

    protected $fillable = ['name', 'singular_name', 'slug', 'description', 'icon', 'is_builtin', 'is_active', 'show_in_menu', 'menu_after', 'is_public', 'supports'];

    protected $casts = [
        'supports' => 'array',
        'is_builtin' => 'boolean',
        'is_active' => 'boolean',
        'show_in_menu' => 'boolean',
        'is_public' => 'boolean',
        'menu_after' => 'integer',
    ];

    public function posts(): HasMany
    {
        return $this->hasMany(Post::class, 'type', 'slug');
    }
}
