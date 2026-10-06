<?php

namespace Tests\Feature\Users;

use App\Actions\Users\DeleteUser;
use App\Actions\Users\UpdateUser;
use App\Exceptions\LastAdminException;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Two admins demoting or deleting each other at the same moment both pass authorization
 * on the old state. These tests recreate the second request: the acting admin still looks
 * like an admin in memory, while the first request has already demoted them in the database.
 */
class LastAdminTest extends TestCase
{
    use RefreshDatabase;

    public function test_concurrent_demotion_cannot_remove_the_last_admin(): void
    {
        [$staleAdmin, $lastAdmin] = $this->adminDemotedByConcurrentRequest();
        Sanctum::actingAs($staleAdmin);

        $this->patchJson("/api/users/{$lastAdmin->id}", ['is_admin' => false])
            ->assertConflict()
            ->assertJsonPath('message', 'At least one admin must remain.');

        $this->assertTrue($lastAdmin->fresh()?->is_admin);
    }

    public function test_concurrent_deletion_cannot_remove_the_last_admin(): void
    {
        [$staleAdmin, $lastAdmin] = $this->adminDemotedByConcurrentRequest();
        $lastAdmin->createToken('api');
        Sanctum::actingAs($staleAdmin);

        $this->deleteJson("/api/users/{$lastAdmin->id}")
            ->assertConflict()
            ->assertJsonPath('message', 'At least one admin must remain.');

        $this->assertModelExists($lastAdmin);
        $this->assertDatabaseHas('personal_access_tokens', ['tokenable_id' => $lastAdmin->id]);
    }

    public function test_rejected_demotion_rolls_back_other_changes_too(): void
    {
        $onlyAdmin = User::factory()->admin()->create(['name' => 'Boss']);

        try {
            app(UpdateUser::class)->handle($onlyAdmin, ['name' => 'Renamed', 'is_admin' => false]);
            $this->fail('Expected LastAdminException.');
        } catch (LastAdminException) {
            // expected
        }

        $this->assertDatabaseHas('users', ['id' => $onlyAdmin->id, 'name' => 'Boss', 'is_admin' => true]);
    }

    public function test_only_admin_cannot_be_deleted_by_any_path(): void
    {
        $onlyAdmin = User::factory()->admin()->create();

        $this->expectException(LastAdminException::class);

        app(DeleteUser::class)->handle($onlyAdmin);
    }

    public function test_guard_ignores_users_who_are_not_admins(): void
    {
        Sanctum::actingAs(User::factory()->admin()->create());
        $user = User::factory()->create();

        $this->patchJson("/api/users/{$user->id}", ['is_admin' => false])->assertOk();
        $this->deleteJson("/api/users/{$user->id}")->assertNoContent();
    }

    public function test_one_of_two_admins_can_still_be_demoted_and_deleted(): void
    {
        Sanctum::actingAs(User::factory()->admin()->create());
        $second = User::factory()->admin()->create();
        $third = User::factory()->admin()->create();

        $this->patchJson("/api/users/{$second->id}", ['is_admin' => false])->assertOk();
        $this->deleteJson("/api/users/{$third->id}")->assertNoContent();
    }

    /**
     * @return array{0: User, 1: User}
     */
    private function adminDemotedByConcurrentRequest(): array
    {
        $staleAdmin = User::factory()->admin()->create();
        $lastAdmin = User::factory()->admin()->create();

        User::query()->whereKey($staleAdmin->id)->update(['is_admin' => false]);

        return [$staleAdmin, $lastAdmin];
    }
}
