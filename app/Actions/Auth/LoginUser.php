<?php

namespace App\Actions\Auth;

use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Validation\ValidationException;

class LoginUser
{
    public function __construct(
        private VerifyCredentials $verifyCredentials,
        private IssueToken $issueToken,
    ) {}

    /**
     * Verify credentials and issue a new API token.
     *
     * @return array{user: User, token: string, expires_at: CarbonInterface|null}
     *
     * @throws ValidationException
     */
    public function handle(string $email, string $password): array
    {
        $user = $this->verifyCredentials->handle($email, $password);

        return ['user' => $user, ...$this->issueToken->handle($user)];
    }
}
