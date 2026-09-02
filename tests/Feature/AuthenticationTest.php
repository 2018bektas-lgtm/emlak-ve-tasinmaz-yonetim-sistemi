<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_page_is_displayed(): void
    {
        $this->get(route('login'))
            ->assertOk()
            ->assertSee('Hesabınıza giriş yapın', false);
    }

    public function test_users_can_authenticate_using_the_login_screen(): void
    {
        $user = User::factory()->create();

        $response = $this->post(route('login.store'), [
            'email' => $user->email,
            'password' => 'password',
        ]);

        $this->assertAuthenticated();
        $response->assertRedirect(route('panel.dashboard'));
    }

    public function test_users_cannot_authenticate_with_invalid_password(): void
    {
        $user = User::factory()->create();

        $this->post(route('login.store'), [
            'email' => $user->email,
            'password' => 'yanlis-sifre',
        ]);

        $this->assertGuest();
    }

    public function test_authenticated_users_can_visit_the_panel(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('panel.dashboard'))
            ->assertOk()
            ->assertSee($user->name, false);
    }

    public function test_guests_are_redirected_from_the_panel(): void
    {
        $this->get(route('panel.dashboard'))
            ->assertRedirect(route('login'));
    }

    public function test_authenticated_users_are_redirected_from_the_login_page(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('login'))
            ->assertRedirect(route('panel.dashboard'));
    }
}
