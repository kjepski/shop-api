<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Console\Scheduling\Event;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class TokenExpirationTest extends TestCase
{
    use RefreshDatabase;

    public function test_token_is_valid_just_before_seven_days(): void
    {
        $token = User::factory()->create()->createToken('api')->plainTextToken;

        $this->travel(7 * 24 * 60 - 1)->minutes();

        $this->withToken($token)->getJson('/api/me')->assertOk();
    }

    public function test_token_expires_after_seven_days(): void
    {
        $token = User::factory()->create()->createToken('api')->plainTextToken;

        $this->travel(7 * 24 * 60 + 1)->minutes();

        $this->withToken($token)->getJson('/api/me')->assertUnauthorized();
    }

    private function pruneEvent(): Event
    {
        $events = collect($this->app->make(Schedule::class)->events())
            ->filter(fn (Event $event) => str_contains((string) $event->command, 'sanctum:prune-expired'));

        $this->assertCount(1, $events);

        return $events->first();
    }

    public function test_prune_command_is_scheduled_daily_with_one_day_grace_period(): void
    {
        $event = $this->pruneEvent();

        $this->assertSame('0 0 * * *', $event->expression);
        $this->assertStringContainsString('--hours=24', (string) $event->command);
    }

    public function test_prune_command_runs_on_one_server_only(): void
    {
        $this->assertTrue($this->pruneEvent()->onOneServer);
    }

    public function test_tokens_table_has_index_on_created_at_used_by_prune(): void
    {
        $indexedColumns = collect(Schema::getIndexes('personal_access_tokens'))->pluck('columns');

        $this->assertContains(['created_at'], $indexedColumns);
    }

    public function test_prune_removes_only_tokens_expired_for_more_than_a_day(): void
    {
        $user = User::factory()->create();
        $oldToken = $user->createToken('old')->accessToken;

        $this->travel(2)->minutes();
        $recentlyExpiredToken = $user->createToken('recently-expired')->accessToken;

        // 7 days of validity + 24 hours of grace period, counted from the old token's creation.
        $this->travel((7 * 24 + 24) * 60 - 1)->minutes();

        $this->artisan('sanctum:prune-expired --hours=24')->assertSuccessful();

        $this->assertDatabaseMissing('personal_access_tokens', ['id' => $oldToken->id]);
        $this->assertDatabaseHas('personal_access_tokens', ['id' => $recentlyExpiredToken->id]);
    }
}
