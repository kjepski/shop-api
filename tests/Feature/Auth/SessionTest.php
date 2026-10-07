<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * Cookie login of the admin panel (Sanctum SPA).
 */
class SessionTest extends TestCase
{
    use RefreshDatabase;

    private const PANEL_URL = 'http://localhost/admin-next/';

    protected function setUp(): void
    {
        parent::setUp();

        config(['sanctum.stateful' => ['localhost']]);
    }

    public function test_panel_logs_in_with_a_cookie_session_without_a_token(): void
    {
        $user = User::factory()->create(['password' => 'secret-password']);

        $response = $this->fromPanel()->postJson('/api/session', [
            'email' => $user->email,
            'password' => 'secret-password',
        ]);

        $response->assertOk()
            ->assertExactJsonStructure(['data' => ['id', 'name', 'email', 'is_admin', 'created_at']])
            ->assertJsonPath('data.id', $user->id);

        $this->assertAuthenticatedAs($user, 'web');
        $this->assertDatabaseCount('personal_access_tokens', 0);

        $this->withSessionFrom($response)
            ->getJson('/api/me')
            ->assertOk()
            ->assertJsonPath('data.id', $user->id);
    }

    public function test_login_replaces_the_session_id_and_csrf_token(): void
    {
        $user = User::factory()->create(['password' => 'secret-password']);
        $plantedId = str_repeat('a', 40);

        $response = $this->fromPanel()
            ->withCookie(config('session.cookie'), $plantedId)
            ->withSession(['_token' => 'planted-csrf-token'])
            ->postJson('/api/session', ['email' => $user->email, 'password' => 'secret-password'])
            ->assertOk();

        $this->assertNotSame($plantedId, $this->sessionIdFrom($response));
        $this->assertNotSame('planted-csrf-token', session()->token());
    }

    public function test_login_fails_with_wrong_password(): void
    {
        $user = User::factory()->create(['password' => 'secret-password']);

        $this->fromPanel()
            ->postJson('/api/session', ['email' => $user->email, 'password' => 'wrong-password'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['email']);

        $this->assertGuest('web');
    }

    public function test_login_fails_for_unknown_email(): void
    {
        $this->fromPanel()
            ->postJson('/api/session', ['email' => 'nobody@example.com', 'password' => 'secret-password'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['email']);

        $this->assertGuest('web');
    }

    public function test_login_requires_email_and_password(): void
    {
        $this->fromPanel()
            ->postJson('/api/session', [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['email', 'password']);
    }

    public function test_login_is_rejected_outside_the_panel_domain(): void
    {
        $user = User::factory()->create(['password' => 'secret-password']);

        $this->withHeader('Referer', 'https://evil.example.com/')
            ->postJson('/api/session', ['email' => $user->email, 'password' => 'secret-password'])
            ->assertBadRequest();

        $this->assertGuest('web');
    }

    public function test_login_is_throttled_after_five_failed_attempts(): void
    {
        $user = User::factory()->create(['password' => 'secret-password']);
        $payload = ['email' => $user->email, 'password' => 'wrong-password'];

        for ($i = 0; $i < 5; $i++) {
            $this->fromPanel()->postJson('/api/session', $payload)->assertUnprocessable();
        }

        $this->fromPanel()->postJson('/api/session', $payload)->assertTooManyRequests();
    }

    public function test_panel_request_without_session_is_unauthorized(): void
    {
        $this->fromPanel()->getJson('/api/me')->assertUnauthorized();
    }

    public function test_logout_ends_the_cookie_session(): void
    {
        $user = User::factory()->create(['password' => 'secret-password']);

        $login = $this->fromPanel()
            ->postJson('/api/session', ['email' => $user->email, 'password' => 'secret-password'])
            ->assertOk();

        $csrfToken = session()->token();

        $logout = $this->withSessionFrom($login)->postJson('/api/logout')->assertNoContent();

        $this->assertGuest('web');
        $this->assertNotSame($this->sessionIdFrom($login), $this->sessionIdFrom($logout));
        // A fresh CSRF token right away, so the panel can log in again without reloading.
        $this->assertNotEmpty(session()->token());
        $this->assertNotSame($csrfToken, session()->token());

        // The old session cookie no longer logs anyone in.
        $this->withSessionFrom($login)->getJson('/api/me')->assertUnauthorized();
    }

    public function test_logout_with_a_session_also_revokes_a_bearer_token_sent_alongside(): void
    {
        // The session wins over the token, so the old panel's "logout" lands in the session branch.
        $sessionUser = User::factory()->create(['password' => 'secret-password']);
        $token = User::factory()->create()->createToken('api');

        $login = $this->fromPanel()
            ->postJson('/api/session', ['email' => $sessionUser->email, 'password' => 'secret-password'])
            ->assertOk();

        $this->withSessionFrom($login)
            ->withToken($token->plainTextToken)
            ->postJson('/api/logout')
            ->assertNoContent();

        $this->assertGuest('web');
        $this->assertDatabaseMissing('personal_access_tokens', ['id' => $token->accessToken->id]);
    }

    public function test_panel_writes_require_the_csrf_token(): void
    {
        $this->enforceCsrf();
        $user = User::factory()->create(['password' => 'secret-password']);
        $credentials = ['email' => $user->email, 'password' => 'secret-password'];

        $cookie = $this->fromPanel()->get('/sanctum/csrf-cookie')->assertNoContent();
        // The browser sends back the XSRF-TOKEN cookie value as is (encrypted).
        $xsrf = (string) $cookie->getCookie('XSRF-TOKEN', decrypt: false)?->getValue();

        $this->withSessionFrom($cookie)->postJson('/api/session', $credentials)->assertStatus(419);
        $this->assertGuest('web');

        $login = $this->withSessionFrom($cookie)
            ->withHeader('X-XSRF-TOKEN', $xsrf)
            ->postJson('/api/session', $credentials)
            ->assertOk();

        $xsrf = (string) $login->getCookie('XSRF-TOKEN', decrypt: false)?->getValue();

        $this->withSessionFrom($login)->withoutHeader('X-XSRF-TOKEN')->postJson('/api/logout')->assertStatus(419);
        $this->withSessionFrom($login)->withHeader('X-XSRF-TOKEN', $xsrf)->postJson('/api/logout')->assertNoContent();
    }

    /**
     * Laravel skips the CSRF check while running tests; turn it back on for one test.
     */
    private function enforceCsrf(): void
    {
        $this->app->bind(ValidateCsrfToken::class, fn ($app) => new class($app, $app['encrypter']) extends ValidateCsrfToken
        {
            protected function runningUnitTests(): bool
            {
                return false;
            }
        });
    }

    private function fromPanel(): static
    {
        // Without withCredentials() the test client drops cookies from JSON requests.
        return $this->withCredentials()->withHeader('Referer', self::PANEL_URL);
    }

    /**
     * Next panel request with the session cookie from an earlier response, as a browser would send it.
     */
    private function withSessionFrom(TestResponse $response): static
    {
        // Within one test the guard caches the user and the session store keeps its data in memory;
        // drop both, so the next request is authenticated only by what its cookie points to.
        $this->app['auth']->forgetGuards();
        $this->app['session']->driver()->flush();

        return $this->fromPanel()->withCookie(config('session.cookie'), $this->sessionIdFrom($response));
    }

    private function sessionIdFrom(TestResponse $response): string
    {
        return (string) $response->getCookie(config('session.cookie'))?->getValue();
    }
}
