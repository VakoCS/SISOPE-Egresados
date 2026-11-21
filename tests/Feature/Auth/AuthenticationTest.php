<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use App\Modules\SeguimientoEgresado\Support\Accesos\Perfil;
use App\Modules\SeguimientoEgresado\Support\Accesos\RutaInicial;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Role::firstOrCreate(['name' => 'secretaria', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'director_escuela', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'alumno', 'guard_name' => 'web']);
    }

    public function test_login_screen_can_be_rendered(): void
    {
        $response = $this->get('/login');

        $response->assertStatus(200)
            ->assertSee('CRM Seguimiento al Egresado')
            ->assertSee('Ingresar');
    }

    public function test_secretaria_authenticates_and_redirects_directly_to_crm_padron(): void
    {
        $user = User::factory()->create([
            'email' => 'secretaria.informatica@unitru.edu.pe',
        ]);
        $user->assignRole('secretaria');

        $response = $this->post('/login', [
            'email' => $user->email,
            'password' => 'password',
        ]);

        $this->assertAuthenticated();
        $response->assertRedirect(route(RutaInicial::para(Perfil::Secretaria), absolute: false));
    }

    public function test_director_authenticates_and_redirects_directly_to_crm_indicadores(): void
    {
        $user = User::factory()->create([
            'email' => 'direccion.informatica@unitru.edu.pe',
        ]);
        $user->assignRole('director_escuela');

        $response = $this->post('/login', [
            'email' => $user->email,
            'password' => 'password',
        ]);

        $this->assertAuthenticated();
        $response->assertRedirect(route(RutaInicial::para(Perfil::Director), absolute: false));
    }

    public function test_unauthorized_role_is_rejected_from_crm_login(): void
    {
        $user = User::factory()->create([
            'email' => 'alumno@portal.local',
        ]);
        $user->assignRole('alumno');

        $response = $this->post('/login', [
            'email' => $user->email,
            'password' => 'password',
        ]);

        $this->assertGuest();
        $response->assertSessionHasErrors('email');
    }

    public function test_users_can_not_authenticate_with_invalid_password(): void
    {
        $user = User::factory()->create([
            'email' => 'secretaria.informatica@unitru.edu.pe',
        ]);
        $user->assignRole('secretaria');

        $this->post('/login', [
            'email' => $user->email,
            'password' => 'wrong-password',
        ]);

        $this->assertGuest();
    }

    public function test_users_can_logout_and_redirect_to_login(): void
    {
        $user = User::factory()->create();
        $user->assignRole('secretaria');

        $response = $this->actingAs($user)->post('/logout');

        $this->assertGuest();
        $response->assertRedirect(route('login'));
    }
}
