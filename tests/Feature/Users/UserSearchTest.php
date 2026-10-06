<?php

namespace Tests\Feature\Users;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class UserSearchTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Sanctum::actingAs(User::factory()->admin()->create(['name' => 'Zed Admin', 'email' => 'zed@shop.test']));
    }

    public function test_search_matches_name_fragment_case_insensitively(): void
    {
        User::factory()->create(['name' => 'Jan Kowalski', 'email' => 'jk@example.com']);
        User::factory()->create(['name' => 'Anna Nowak', 'email' => 'an@example.com']);

        $this->assertNames($this->getJson('/api/users?search=KOWAL'), ['Jan Kowalski']);
    }

    public function test_search_matches_email_fragment(): void
    {
        User::factory()->create(['name' => 'Jan', 'email' => 'jan@firma.pl']);
        User::factory()->create(['name' => 'Anna', 'email' => 'anna@example.com']);

        $this->assertNames($this->getJson('/api/users?search=firma'), ['Jan']);
    }

    public function test_search_treats_wildcards_and_escape_character_literally(): void
    {
        User::factory()->create(['name' => '100% Jan', 'email' => 'a@example.com']);
        User::factory()->create(['name' => '100 Jan', 'email' => 'b@example.com']);
        User::factory()->create(['name' => 'Ann_a', 'email' => 'c@example.com']);
        User::factory()->create(['name' => 'AnnXa', 'email' => 'd@example.com']);
        User::factory()->create(['name' => 'Ola!Nowak', 'email' => 'e@example.com']);
        User::factory()->create(['name' => 'OlaNowak', 'email' => 'f@example.com']);

        $this->assertNames($this->getJson('/api/users?search='.urlencode('0%')), ['100% Jan']);
        $this->assertNames($this->getJson('/api/users?search=n_a'), ['Ann_a']);
        $this->assertNames($this->getJson('/api/users?search='.urlencode('a!N')), ['Ola!Nowak']);
    }

    public function test_search_treats_backslash_literally_in_name_and_email(): void
    {
        User::factory()->create(['name' => 'Jan\\Nowak', 'email' => 'g@example.com']);
        User::factory()->create(['name' => 'Ola', 'email' => 'ola\\x@example.com']);
        User::factory()->create(['name' => 'JanNowak', 'email' => 'olax@example.com']);

        $this->assertNames($this->getJson('/api/users?search='.urlencode('n\\N')), ['Jan\\Nowak']);
        $this->assertNames($this->getJson('/api/users?search='.urlencode('a\\x')), ['Ola']);
    }

    public function test_empty_filters_mean_no_filter(): void
    {
        User::factory()->create(['name' => 'Jan']);

        $this->assertNames($this->getJson('/api/users?search=&role='), ['Jan', 'Zed Admin']);
    }

    public function test_role_filter_returns_only_admins(): void
    {
        User::factory()->admin()->create(['name' => 'Ada Admin']);
        User::factory()->create(['name' => 'Bob User']);

        $this->assertNames($this->getJson('/api/users?role=admin'), ['Ada Admin', 'Zed Admin']);
    }

    public function test_role_filter_returns_only_regular_users(): void
    {
        User::factory()->admin()->create(['name' => 'Ada Admin']);
        User::factory()->create(['name' => 'Bob User']);

        $this->assertNames($this->getJson('/api/users?role=user'), ['Bob User']);
    }

    public function test_search_and_role_combine(): void
    {
        User::factory()->admin()->create(['name' => 'Jan Admin', 'email' => 'jan.admin@example.com']);
        User::factory()->create(['name' => 'Jan User', 'email' => 'jan.user@example.com']);
        User::factory()->create(['name' => 'Anna User', 'email' => 'anna@example.com']);

        $this->assertNames($this->getJson('/api/users?search=jan&role=user'), ['Jan User']);
    }

    public function test_pagination_links_keep_applied_filters_only(): void
    {
        User::factory(20)->create(['name' => 'Jan']);

        $response = $this->getJson('/api/users?search=jan&role=user&unknown=x')
            ->assertOk()
            ->assertJsonPath('meta.total', 20);

        $this->assertStringEndsWith('/api/users?search=jan&role=user&page=2', (string) $response->json('links.next'));

        $this->getJson('/api/users?search=jan&role=user&page=2')->assertJsonCount(5, 'data');
    }

    /**
     * @return array<string, array{0: string, 1: string}>
     */
    public static function invalidFilters(): array
    {
        return [
            'search too short' => ['search=a', 'search'],
            'search too long' => ['search='.str_repeat('a', 101), 'search'],
            'unknown role' => ['role=owner', 'role'],
            'role in wrong case' => ['role=Admin', 'role'],
            'search trimmed to one character' => ['search=%20a%20', 'search'],
            'search as array' => ['search[]=ab', 'search'],
            'role as array' => ['role[]=admin', 'role'],
        ];
    }

    #[DataProvider('invalidFilters')]
    public function test_invalid_filters_are_rejected(string $query, string $field): void
    {
        $this->getJson("/api/users?{$query}")
            ->assertUnprocessable()
            ->assertJsonValidationErrors([$field]);
    }

    public function test_regular_user_gets_403_before_validation(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $this->getJson('/api/users?search=a')->assertForbidden();
    }

    public function test_search_requires_token(): void
    {
        $this->app['auth']->forgetGuards();

        $this->getJson('/api/users?search=jan')->assertUnauthorized();
    }

    /**
     * @param  list<string>  $expected
     */
    private function assertNames(TestResponse $response, array $expected): void
    {
        $response->assertOk();

        $this->assertSame($expected, array_column($response->json('data'), 'name'));
    }
}
