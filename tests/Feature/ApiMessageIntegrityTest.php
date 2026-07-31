<?php

namespace Tests\Feature;

use App\Http\Middleware\EnsureSpanishApiMessages;
use Illuminate\Http\Request;
use Tests\TestCase;

class ApiMessageIntegrityTest extends TestCase
{
    public function test_success_messages_are_never_replaced_with_an_error_message(): void
    {
        $response = app(EnsureSpanishApiMessages::class)->handle(
            Request::create('/api/savings-boxes', 'POST'),
            fn () => response()->json(['message' => 'Caja de ahorro creada correctamente.'], 201)
        );

        $this->assertSame(201, $response->getStatusCode());
        $this->assertSame('Caja de ahorro creada correctamente.', $response->getData(true)['message']);
    }

    public function test_failure_messages_keep_their_specific_reason(): void
    {
        $response = app(EnsureSpanishApiMessages::class)->handle(
            Request::create('/api/savings-boxes', 'POST'),
            fn () => response()->json(['message' => 'Selecciona al menos un integrante.'], 422)
        );

        $this->assertSame(422, $response->getStatusCode());
        $this->assertSame('Selecciona al menos un integrante.', $response->getData(true)['message']);
    }
}
