<?php

namespace App\Http\Controllers;

use App\Models\LandingEmail;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AdminLandingEmailController extends Controller
{
    public function index(): View
    {
        $emails = LandingEmail::query()
            ->latest()
            ->paginate(10)
            ->withQueryString();

        $registeredEmails = User::query()
            ->whereIn('email', $emails->getCollection()->pluck('email'))
            ->pluck('email')
            ->map(fn (string $email): string => mb_strtolower(trim($email)))
            ->flip();

        $emails->getCollection()->transform(function (LandingEmail $landingEmail) use ($registeredEmails): LandingEmail {
            $landingEmail->account_created = $registeredEmails->has(mb_strtolower(trim($landingEmail->email)));

            return $landingEmail;
        });

        return view('admin-landing-emails', [
            'emails' => $emails,
        ]);
    }

    public function export(): StreamedResponse
    {
        $filename = 'domus-correos-landing-'.now()->format('Y-m-d_H-i-s').'.csv';

        return response()->streamDownload(function (): void {
            $handle = fopen('php://output', 'wb');

            fputcsv($handle, ['Correo', 'Registro en landing', 'Cuenta creada']);

            LandingEmail::query()
                ->leftJoin('users', 'users.email', '=', 'landing_emails.email')
                ->select([
                    'landing_emails.email',
                    'landing_emails.created_at',
                    DB::raw('users.id as account_id'),
                ])
                ->orderBy('landing_emails.id')
                ->cursor()
                ->each(function (object $landingEmail) use ($handle): void {
                    fputcsv($handle, [
                        $landingEmail->email,
                        $landingEmail->created_at,
                        $landingEmail->account_id ? 'Sí' : 'No',
                    ]);
                });

            fclose($handle);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }
}
