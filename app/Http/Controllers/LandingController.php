<?php

namespace App\Http\Controllers;

use App\Models\LandingEmail;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class LandingController extends Controller
{
    public function show(): View
    {
        return view('landing');
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'email' => ['required', 'string', 'email:rfc', 'max:254'],
        ], [
            'email.required' => 'Escribe un correo para guardar tu lugar.',
            'email.email' => 'Escribe un correo válido, por ejemplo: nombre@correo.com.',
            'email.max' => 'El correo es demasiado largo.',
        ]);

        LandingEmail::firstOrCreate([
            'email' => mb_strtolower(trim($validated['email'])),
        ]);

        return redirect()
            ->to(route('landing').'#unete')
            ->with('landing_success', 'Te avisaremos cuando haya algo especial para compartir.');
    }
}
