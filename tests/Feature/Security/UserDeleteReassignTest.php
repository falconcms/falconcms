<?php

namespace FalconCms\Core\Tests\Feature\Security;

use FalconCms\Core\Database\Seeders\MenuSeeder;
use FalconCms\Core\Models\Role;
use FalconCms\Core\Tests\Concerns\MakesShopFixtures;
use FalconCms\Core\Tests\TestCase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * Deleting a user must not delete their content unless that was chosen.
 *
 * Before: UserController::destroy() treated "reassign" with an empty or missing reassign_to as
 * "delete", so every post of the user went with them. A reassign_to of the user being deleted
 * left the posts without an owner, an unknown id surfaced the SQL error in the UI, and any
 * delete_option other than "reassign" deleted the content.
 */
class UserDeleteReassignTest extends TestCase
{
    use MakesShopFixtures;

    protected function setUp(): void
    {
        parent::setUp();
        // The menus decide who reaches a screen (AdminMiddleware), so the real ones are seeded.
        (new MenuSeeder)->run();
    }

    private function roleId(string $slug): int
    {
        return (int) DB::table('roles')->where('slug', $slug)->value('id')
            ?: (int) Role::create(['name' => ucfirst($slug), 'slug' => $slug])->getKey();
    }

    private function makePost(int $userId): int
    {
        return DB::table('posts')->insertGetId([
            'user_id' => $userId, 'title' => 'Post', 'slug' => 'post-'.uniqid(),
            'type' => 'post', 'status' => 'published', 'lang_code' => 'en',
        ]);
    }

    /** @return array<string, array{0: array<string, string>}> */
    public static function refusedRequests(): array
    {
        return [
            'empty reassign_to' => [['delete_option' => 'reassign', 'reassign_to' => '']],
            'missing reassign_to' => [['delete_option' => 'reassign']],
            'reassign_to the deleted user' => [['delete_option' => 'reassign', 'reassign_to' => 'SELF']],
            'reassign_to an unknown user' => [['delete_option' => 'reassign', 'reassign_to' => '999999']],
            'reassign_to not a number' => [['delete_option' => 'reassign', 'reassign_to' => 'abc']],
            'unknown delete_option' => [['delete_option' => 'wipe']],
        ];
    }

    #[DataProvider('refusedRequests')]
    public function test_a_refused_request_deletes_neither_the_user_nor_their_posts(array $data): void
    {
        $admin = $this->makeUser(['role_id' => $this->roleId('administrator')]);
        $user = $this->makeUser(['role_id' => $this->roleId('subscriber')]);
        $postId = $this->makePost($user->id);
        if (($data['reassign_to'] ?? null) === 'SELF') {
            $data['reassign_to'] = (string) $user->id;
        }

        $this->actingAs($admin)->delete("/admin/users/{$user->id}", $data)->assertSessionHas('error');

        $this->assertDatabaseHas('users', ['id' => $user->id]);
        $this->assertDatabaseHas('posts', ['id' => $postId, 'user_id' => $user->id]);
    }

    public function test_a_valid_reassign_moves_the_posts_and_deletes_the_user(): void
    {
        $admin = $this->makeUser(['role_id' => $this->roleId('administrator')]);
        $user = $this->makeUser(['role_id' => $this->roleId('subscriber')]);
        $heir = $this->makeUser(['role_id' => $this->roleId('subscriber')]);
        $postId = $this->makePost($user->id);

        $this->actingAs($admin)
            ->delete("/admin/users/{$user->id}", ['delete_option' => 'reassign', 'reassign_to' => (string) $heir->id])
            ->assertSessionHas('success');

        $this->assertDatabaseMissing('users', ['id' => $user->id]);
        $this->assertDatabaseHas('posts', ['id' => $postId, 'user_id' => $heir->id]);
    }

    public function test_a_normal_delete_still_deletes_the_user_and_their_posts(): void
    {
        $admin = $this->makeUser(['role_id' => $this->roleId('administrator')]);
        $user = $this->makeUser(['role_id' => $this->roleId('subscriber')]);
        $postId = $this->makePost($user->id);

        $this->actingAs($admin)->delete("/admin/users/{$user->id}", ['delete_option' => 'delete'])->assertSessionHas('success');

        $this->assertDatabaseMissing('users', ['id' => $user->id]);
        $this->assertDatabaseMissing('posts', ['id' => $postId]);
    }
}
