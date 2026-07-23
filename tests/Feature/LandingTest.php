<?php

namespace Tests\Feature;

use App\Models\LandingEmail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LandingTest extends TestCase
{
    use RefreshDatabase;

    public function test_landing_page_is_available(): void
    {
        $this->get('/landing')
            ->assertOk()
            ->assertSee('Pequeños hábitos hoy.')
            ->assertSee('Quiero conocer DOMUS');
    }

    public function test_a_family_can_register_an_email(): void
    {
        $response = $this->post('/landing', [
            'email' => 'Familia@Ejemplo.com',
        ]);

        $response
            ->assertRedirect(route('landing').'#unete')
            ->assertSessionHas('landing_success');

        $this->assertDatabaseHas('landing_emails', [
            'email' => 'familia@ejemplo.com',
        ]);
    }

    public function test_registering_the_same_email_twice_does_not_duplicate_it(): void
    {
        $this->post('/landing', ['email' => 'familia@ejemplo.com']);
        $this->post('/landing', ['email' => 'FAMILIA@EJEMPLO.COM']);

        $this->assertSame(1, LandingEmail::count());
    }

    public function test_email_must_be_valid(): void
    {
        $this->from('/landing')
            ->post('/landing', ['email' => 'correo-invalido'])
            ->assertRedirect('/landing')
            ->assertSessionHasErrors('email');

        $this->assertDatabaseEmpty('landing_emails');
    }
}
