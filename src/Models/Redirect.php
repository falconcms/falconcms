<?php

namespace FalconCms\Core\Models;

use Illuminate\Database\Eloquent\Model;

class Redirect extends Model
{
    protected $table = 'cms_redirects';

    protected $fillable = [
        'old_url',
        'new_url',
        'status_code',
        'hits',
        'last_hit_at',
    ];

    protected $casts = [
        'last_hit_at' => 'datetime',
    ];

    /**
     * Record that content moved from one address to another, keeping the table loop-free.
     *
     * Writing the new rule on its own is not enough. Rename a page and then rename it back and
     * you are left with /a → /b from the first move and /b → /a from the second: a visitor to
     * either address bounces between them until the browser gives up (ERR_TOO_MANY_REDIRECTS).
     * The same shape appears whenever an address is reused by a later page.
     *
     * So three things happen here, in this order:
     *   1. the address that is now live stops redirecting anywhere — a page cannot both exist
     *      at a URL and send visitors away from it;
     *   2. anything that pointed at the old address is re-pointed at the new one, which
     *      flattens /x → /a → /b into /x → /b and cannot create a self-reference, because
     *      step 1 has already removed any rule whose source is the new address;
     *   3. the move itself is written.
     */
    public static function recordMove(string $oldUrl, string $newUrl, int $statusCode = 301): void
    {
        if ($oldUrl === '' || $newUrl === '' || $oldUrl === $newUrl) {
            return;
        }

        static::where('old_url', $newUrl)->delete();
        static::where('new_url', $oldUrl)->update(['new_url' => $newUrl]);
        static::updateOrCreate(
            ['old_url' => $oldUrl],
            ['new_url' => $newUrl, 'status_code' => $statusCode]
        );
    }
}
