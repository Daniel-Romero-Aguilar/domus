<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class LogFailedUserActions
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! $this->shouldMonitor($request)) {
            return $next($request);
        }

        try {
            $response = $next($request);
        } catch (Throwable $exception) {
            $response = $exception instanceof HttpResponseException
                ? $exception->getResponse()
                : null;
            $this->writeWarning($request, $exception, $response);
            throw $exception;
        }

        if ($response->getStatusCode() >= 400) {
            $this->writeWarning($request, null, $response);
        }

        return $response;
    }

    private function shouldMonitor(Request $request): bool
    {
        return str_starts_with($request->path(), 'api/')
            && in_array($request->method(), ['POST', 'PUT', 'PATCH', 'DELETE'], true);
    }

    private function writeWarning(Request $request, ?Throwable $exception, ?Response $response): void
    {
        $context = [
            'failure_label' => $this->failureLabel($request),
            'action' => $request->method().' '.$request->path(),
            'route_name' => $request->route()?->getName(),
            'operation' => $this->operation($request),
            'objective' => $this->objective($request),
            'user_id' => $request->user()?->id,
            'user_role' => $request->user()?->role,
            'ip' => $request->ip(),
            'request_data' => $this->safeInput($request->all()),
        ];

        if ($response) {
            $context['failure_type'] = 'http_response';
            $context['status'] = $response->getStatusCode();
            $context['response_data'] = $this->responseData($response);
        }

        if ($exception) {
            $context['failure_type'] = 'exception';
            $context['exception_class'] = $exception::class;
            $context['error'] = $exception->getMessage();
            $context['file'] = $exception->getFile();
            $context['line'] = $exception->getLine();
            $context['trace'] = $exception->getTraceAsString();

            $prefix = 'LOAN_AMOUNT_TRANSFORMATION_FAILED: ';
            if (str_starts_with($exception->getMessage(), $prefix)) {
                $details = json_decode(substr($exception->getMessage(), strlen($prefix)), true);
                if (is_array($details)) {
                    $context['diagnostic'] = $details;
                }
            }

            if ($exception instanceof ValidationException) {
                $context['validation_errors'] = $exception->errors();
            }
        }

        Log::warning($context['failure_label'], $context);
    }

    private function failureLabel(Request $request): string
    {
        $path = strtolower($request->path());

        return match (true) {
            str_contains($path, 'loan-payments') => 'PAGO DE PRESTAMO FALLIDO',
            str_contains($path, '/loans/') && str_contains($path, 'approve') => 'PRESTAMO APROBADO FALLIDO',
            str_contains($path, '/loans/') && str_contains($path, 'respond') => 'RESPUESTA DE PRESTAMO FALLIDA',
            str_ends_with($path, '/loans') => 'PRESTAMO SOLICITADO FALLIDO',
            str_contains($path, 'allowances') && str_contains($path, 'execute') => 'MESADA EJECUTADA FALLIDA',
            str_contains($path, 'allowances') => 'MESADA SOLICITADA FALLIDA',
            str_contains($path, 'goals') => 'META FALLIDA',
            str_contains($path, 'savings-boxes') => 'CAJA DE AHORRO FALLIDA',
            str_contains($path, 'withdrawals') => 'RETIRO FALLIDO',
            str_contains($path, 'transfers') => 'TRANSFERENCIA FALLIDA',
            str_contains($path, 'tasks') => 'TAREA FALLIDA',
            str_contains($path, 'family-members') => 'INTEGRANTE FAMILIAR FALLIDO',
            str_contains($path, 'domus-points') => 'PUNTOS DOMUS FALLIDOS',
            str_contains($path, 'education') => 'EDUCACION FALLIDA',
            str_contains($path, 'logout') => 'CIERRE DE SESION FALLIDO',
            str_contains($path, 'register') => 'REGISTRO FALLIDO',
            str_contains($path, 'login') => 'INICIO DE SESION FALLIDO',
            default => 'ACCION DEL USUARIO FALLIDA',
        };
    }

    private function objective(Request $request): string
    {
        $path = strtolower($request->path());

        return match (true) {
            str_contains($path, 'loan-payments') => 'registrar el pago de una cuota de prestamo',
            str_contains($path, 'loans') => 'crear o cambiar el estado de un prestamo',
            str_contains($path, 'allowances') => 'crear o ejecutar una mesada',
            str_contains($path, 'goals') => 'crear o modificar una meta',
            str_contains($path, 'savings-boxes') => 'crear o mover dinero en una caja de ahorro',
            str_contains($path, 'withdrawals') => 'solicitar o procesar un retiro',
            str_contains($path, 'transfers') => 'enviar dinero a un integrante',
            str_contains($path, 'tasks') => 'crear o actualizar una tarea',
            str_contains($path, 'family-members') => 'crear o modificar un integrante familiar',
            str_contains($path, 'domus-points') => 'crear, canjear o pagar una recompensa',
            str_contains($path, 'education') => 'completar una leccion o evaluacion',
            str_contains($path, 'logout') => 'cerrar la sesion',
            str_contains($path, 'register') => 'crear una cuenta',
            str_contains($path, 'login') => 'iniciar sesion',
            default => 'completar la accion solicitada',
        };
    }

    private function operation(Request $request): string
    {
        $path = strtolower($request->path());

        return match (true) {
            str_contains($path, 'loan-payments') => 'pagar cuota de prestamo',
            str_contains($path, '/loans/') && str_contains($path, 'approve') => 'aprobar prestamo',
            str_contains($path, '/loans/') && str_contains($path, 'respond') => 'aceptar o rechazar oferta de prestamo',
            str_ends_with($path, '/loans') => 'solicitar u ofrecer prestamo',
            str_contains($path, 'allowances') && str_contains($path, 'execute') => 'ejecutar mesada',
            str_ends_with($path, '/allowances') => 'crear mesada',
            str_ends_with($path, '/goals') => 'crear meta',
            str_contains($path, '/goals/') && str_contains($path, 'deposit') => 'depositar en meta',
            str_contains($path, '/goals/') && str_contains($path, 'withdraw') => 'retirar de meta',
            str_contains($path, '/goals/') && str_contains($path, 'complete') => 'completar meta',
            str_contains($path, '/goals/') && str_contains($path, 'cancel') => 'cancelar meta',
            str_ends_with($path, '/savings-boxes') => 'crear caja de ahorro',
            str_contains($path, '/savings-boxes/') && str_contains($path, 'deposit') => 'depositar en caja de ahorro',
            str_contains($path, '/savings-boxes/') && str_contains($path, 'withdraw') => 'retirar de caja de ahorro',
            str_contains($path, 'withdrawals') && str_contains($path, 'accept') => 'aceptar retiro',
            str_contains($path, 'withdrawals') && str_contains($path, 'cancel') => 'cancelar retiro',
            str_contains($path, 'withdrawals') => 'solicitar retiro',
            str_ends_with($path, '/transfers') => 'enviar transferencia',
            str_ends_with($path, '/family-members') => 'crear integrante familiar',
            str_contains($path, 'rewards') && str_contains($path, 'redeem') => 'canjear recompensa',
            str_contains($path, 'rewards') => 'crear recompensa',
            str_contains($path, 'redemptions') => 'pagar recompensa',
            str_contains($path, 'education') && str_contains($path, 'submit') => 'responder evaluacion',
            str_contains($path, 'education') => 'completar leccion',
            str_contains($path, 'tasks') && str_contains($path, 'review') => 'revisar tarea',
            str_contains($path, 'tasks') && str_contains($path, 'accept') => 'aceptar tarea',
            str_contains($path, 'tasks') && str_contains($path, 'completed') => 'completar tarea',
            str_ends_with($path, '/tasks') => 'crear tarea',
            str_contains($path, 'balance/add') => 'agregar saldo',
            str_contains($path, 'logout') => 'cerrar sesion',
            str_contains($path, 'register') => 'crear cuenta',
            str_contains($path, 'login') => 'iniciar sesion',
            default => $request->method().' '.$request->path(),
        };
    }

    private function responseData(Response $response): mixed
    {
        $content = $response->getContent();
        $decoded = json_decode($content, true);

        return json_last_error() === JSON_ERROR_NONE ? $decoded : $content;
    }

    private function safeInput(array $input): array
    {
        foreach ($input as $key => $value) {
            if (preg_match('/password|token|secret|authorization/i', (string) $key)) {
                $input[$key] = '[REDACTED]';
                continue;
            }

            if (is_array($value)) {
                $input[$key] = $this->safeInput($value);
            }
        }

        return $input;
    }
}
