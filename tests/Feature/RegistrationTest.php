<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class RegistrationTest extends TestCase
{
    use RefreshDatabase;

    private function payload(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Jamie Punter',
            'username' => 'jamie_p',
            'email' => 'Jamie@Example.com',
            'mobile' => '0412 345 678',
            'date_of_birth' => Carbon::today('Australia/Sydney')->subYears(25)->toDateString(),
            'password' => 'secret123',
            'password_confirmation' => 'secret123',
            'terms' => '1',
            'role' => 'admin', // must be ignored
        ], $overrides);
    }

    public function test_adult_can_register_as_punter(): void
    {
        $this->post('/register', $this->payload())->assertRedirect(route('home'));

        $user = User::firstWhere('username', 'jamie_p');
        $this->assertNotNull($user);
        $this->assertSame('jamie@example.com', $user->email);
        $this->assertSame(UserRole::Punter, $user->role);
        $this->assertSame(0, $user->points);
        $this->assertAuthenticatedAs($user);
    }

    public function test_under_18_is_rejected_server_side(): void
    {
        $dob = Carbon::today('Australia/Sydney')->subYears(18)->addDay()->toDateString(); // turns 18 tomorrow

        $this->post('/register', $this->payload(['date_of_birth' => $dob]))->assertSessionHasErrors('date_of_birth');
        $this->assertGuest();
    }

    public function test_exactly_18_today_is_allowed(): void
    {
        $dob = Carbon::today('Australia/Sydney')->subYears(18)->toDateString();

        $this->post('/register', $this->payload(['date_of_birth' => $dob]))->assertSessionHasNoErrors();
    }

    public function test_register_form_has_client_side_max_date(): void
    {
        $this->withoutVite()->get('/register')
            ->assertOk()
            ->assertSee('max="'.Carbon::today('Australia/Sydney')->subYears(18)->toDateString().'"', false);
    }

    public function test_username_must_be_unique(): void
    {
        User::factory()->create(['username' => 'jamie_p']);

        $this->post('/register', $this->payload())->assertSessionHasErrors('username');
    }
}
