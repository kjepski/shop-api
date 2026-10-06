<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Console\Scheduling\Event;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
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

    public function test_expired_tokens_are_pruned_daily(): void
    {
        $events = collect($this->app->make(Schedule::class)->events())
            ->filter(fn (Event $event) => str_contains((string) $event->command, 'sanctum:prune-expired'));

        $this->assertCount(1, $events);
        $this->assertSame('0 0 * * *', $events->first()?->expression);
    }
}
