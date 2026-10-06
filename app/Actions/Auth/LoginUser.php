<?php

namespace App\Actions\Auth;

use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class LoginUser
{
    public function __construct(private IssueToken $issueToken) {}

    /**
     * Verify credentials and issue a new API token.
     *
     * @return array{user: User, token: string, expires_at: CarbonInterface|null}
     *
     * @throws ValidationException
     */
    public function handle(string $email, string $password): array
    {
        $user = User::where('email', $email)->first();

        if (! $user) {
            // Spend the same hashing time as a real check so response timing does not reveal registered emails.
            Hash::make($password);
        }

        if (! $user || ! Hash::check($password, $user->password)) {
            throw ValidationException::withMessages([
                'email' => __('auth.failed'),
            ]);
        }

        return ['user' => $user, ...$this->issueToken->handle($user)];
    }
}
