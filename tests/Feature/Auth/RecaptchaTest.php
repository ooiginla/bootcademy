<?php

namespace Tests\Feature\Auth;

use App\Models\Academy;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class RecaptchaTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.recaptcha.site_key' => 'test-site-key',
            'services.recaptcha.secret_key' => 'test-secret-key',
            'services.recaptcha.min_score' => 0.5,
        ]);
    }

    public function test_registration_succeeds_with_a_human_score(): void
    {
        $this->fakeGoogle(['success' => true, 'score' => 0.9, 'action' => 'register']);

        $this->post(route('register.store'), $this->payload(['recaptcha_token' => 'good-token']))
            ->assertSessionHasNoErrors();

        $this->assertAuthenticated();
        $this->assertDatabaseHas(Academy::class, ['name' => 'Bright Futures Academy']);
        Http::assertSent(fn (Request $request): bool => $request['secret'] === 'test-secret-key' && $request['response'] === 'good-token');
    }

    public function test_registration_is_rejected_for_bots(): void
    {
        $cases = [
            'low score' => [['success' => true, 'score' => 0.1, 'action' => 'register'], 'token'],
            'wrong action' => [['success' => true, 'score' => 0.9, 'action' => 'apply'], 'token'],
            'invalid token' => [['success' => false, 'error-codes' => ['invalid-input-response']], 'token'],
            'missing token' => [['success' => true, 'score' => 0.9, 'action' => 'register'], null],
        ];

        foreach ($cases as $name => [$google, $token]) {
            $this->fakeGoogle($google);

            $this->post(route('register.store'), $this->payload($token === null ? [] : ['recaptcha_token' => $token]))
                ->assertSessionHasErrors('recaptcha_token', null, 'default');

            $this->assertGuest();
            $this->assertSame(0, User::query()->count(), "{$name} created a user");
            $this->assertSame(0, Academy::query()->count(), "{$name} created an academy");
        }
    }

    public function test_registration_is_rejected_when_google_cannot_be_reached(): void
    {
        Http::fake(fn () => throw new ConnectionException('timeout'));

        $this->post(route('register.store'), $this->payload(['recaptcha_token' => 'token']))
            ->assertSessionHasErrors('recaptcha_token');

        $this->assertSame(0, Academy::query()->count());
    }

    public function test_verification_is_skipped_when_keys_are_not_configured(): void
    {
        config(['services.recaptcha.site_key' => null, 'services.recaptcha.secret_key' => null]);
        Http::fake();

        $this->post(route('register.store'), $this->payload())->assertSessionHasNoErrors();

        $this->assertAuthenticated();
        Http::assertNothingSent();
    }

    public function test_the_site_key_is_shared_with_pages_but_the_secret_is_not(): void
    {
        $this->get(route('register'))
            ->assertInertia(fn (Assert $page) => $page->where('recaptcha_site_key', 'test-site-key'))
            ->assertDontSee('test-secret-key');
    }

    /** @param array<string, mixed> $body */
    private function fakeGoogle(array $body): void
    {
        Http::fake(['https://www.google.com/recaptcha/api/siteverify' => Http::response($body)]);
    }

    /**
     * @param  array<string, mixed>  $extra
     * @return array<string, mixed>
     */
    private function payload(array $extra = []): array
    {
        return [
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
            'academy_name' => 'Bright Futures Academy',
            'academy_type' => 'Language',
            'timezone' => 'Africa/Lagos',
            'currency' => 'NGN',
            ...$extra,
        ];
    }
}
