<?php

namespace App\Actions\Auth;

use App\Models\User;
use Carbon\CarbonInterface;

class IssueToken
{
    /**
     * Issue a new API token whose expiry follows the sanctum.expiration config.
     *
     * @return array{token: string, expires_at: CarbonInterface|null}
     */
    public function handle(User $user): array
    {
        $minutes = (int) config('sanctum.expiration');

        // Whole seconds, so the value in the response matches what the database stores.
        $expiresAt = $minutes > 0 ? now()->addMinutes($minutes)->startOfSecond() : null;

        return [
            'token' => $user->createToken('api', ['*'], $expiresAt)->plainTextToken,
            'expires_at' => $expiresAt,
        ];
    }
}
