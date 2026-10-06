<?php

namespace App\Actions\Auth;

use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;

class RegisterUser
{
    public function __construct(private IssueToken $issueToken) {}

    /**
     * Create a new user and issue an API token for them.
     *
     * @param  array{name: string, email: string, password: string}  $data
     * @return array{user: User, token: string, expires_at: CarbonInterface|null}
     */
    public function handle(array $data): array
    {
        return DB::transaction(function () use ($data) {
            $user = User::create($data);

            return ['user' => $user, ...$this->issueToken->handle($user)];
        });
    }
}
