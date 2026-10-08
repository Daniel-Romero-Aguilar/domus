<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
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

        $diagnosticId = null;
        $startedAt = hrtime(true);
        if ($this->isLoanReadDiagnosticRequest($request)) {
            $diagnosticId = (string) Str::uuid();
            $request->attributes->set('loans_read_diagnostic_id', $diagnosticId);
            $request->attributes->set('loans_read_diagnostic_stage', 'middleware.dispatch');

            Log::info('LOANS_READ_DIAGNOSTIC_STARTED', [
                'diagnostic_id' => $diagnosticId,
                'action' => $request->method().' '.$request->path(),
                'route_name' => $request->route()?->getName(),
                'user_id' => $request->user()?->id,
                'user_role' => $request->user()?->role,
                'app_environment' => app()->environment(),
                'laravel_version' => app()->version(),
                'php_version' => PHP_VERSION,
            ]);
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

        if ($diagnosticId !== null) {
            $response->headers->set('X-Domus-Diagnostic-Id', $diagnosticId);
        }

        if ($response->getStatusCode() >= 400) {
            $this->writeWarning($request, $this->responseException($response), $response);
        }

        if ($diagnosticId !== null) {
            Log::info('LOANS_READ_DIAGNOSTIC_FINISHED', [
                'diagnostic_id' => $diagnosticId,
                'action' => $request->method().' '.$request->path(),
                'status' => $response->getStatusCode(),
                'duration_ms' => round((hrtime(true) - $startedAt) / 1_000_000, 2),
                'diagnostic_stage' => $request->attributes->get('loans_read_diagnostic_stage'),
                'loan_id' => $request->attributes->get('loans_read_loan_id'),
                'loan_count' => $request->attributes->get('loans_read_loan_count'),
                'loan_ids_sample' => $request->attributes->get('loans_read_loan_ids_sample'),
                'loan_status' => $request->attributes->get('loans_read_loan_status'),
                'loan_requested_months' => $request->attributes->get('loans_read_loan_requested_months'),
                'loan_due_date' => $request->attributes->get('loans_read_loan_due_date'),
                'loan_proposed_due_date' => $request->attributes->get('loans_read_loan_proposed_due_date'),
                'loan_initial_due_date' => $request->attributes->get('loans_read_loan_initial_due_date'),
            ]);
        }

        return $response;
    }

    private function shouldMonitor(Request $request): bool
    {
        return str_starts_with($request->path(), 'api/')
            && (in_array($request->method(), ['POST', 'PUT', 'PATCH', 'DELETE'], true)
                || $this->isLoanReadDiagnosticRequest($request));
    }

    private function isLoanReadDiagnosticRequest(Request $request): bool
    {
        return $request->isMethod('GET')
            && in_array(strtolower($request->path()), ['api/loans', 'api/loans/waiting'], true);
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

        $diagnosticId = $request->attributes->get('loans_read_diagnostic_id');
        if ($diagnosticId !== null) {
            $context['diagnostic_id'] = $diagnosticId;
            $context['diagnostic_stage'] = $request->attributes->get('loans_read_diagnostic_stage');
            $context['loan_id'] = $request->attributes->get('loans_read_loan_id');
            $context['loan_count'] = $request->attributes->get('loans_read_loan_count');
            $context['loan_ids_sample'] = $request->attributes->get('loans_read_loan_ids_sample');
            $context['loan_status'] = $request->attributes->get('loans_read_loan_status');
            $context['loan_requested_months'] = $request->attributes->get('loans_read_loan_requested_months');
            $context['loan_due_date'] = $request->attributes->get('loans_read_loan_due_date');
            $context['loan_proposed_due_date'] = $request->attributes->get('loans_read_loan_proposed_due_date');
            $context['loan_initial_due_date'] = $request->attributes->get('loans_read_loan_initial_due_date');
        }

        if ($response) {
            $context['failure_type'] = 'http_response';
            $context['status'] = $response->getStatusCode();
            $context['response_content_type'] = $response->headers->get('Content-Type');
            if ($diagnosticId === null || $response->getStatusCode() < 500) {
                $context['response_data'] = $this->responseData($response);
            } else {
                $context['response_body_omitted'] = true;
            }
        }

        if ($exception) {
            $context['failure_type'] = 'exception';
            $context['exception_class'] = $exception::class;
            $context['error'] = $exception->getMessage();
            $context['file'] = $exception->getFile();
            $context['line'] = $exception->getLine();
            $context['trace'] = $exception->getTraceAsString();
            $context['exception_chain'] = $this->exceptionChain($exception);

            if ($exception instanceof \Illuminate\Database\QueryException) {
                $context['database_query'] = [
                    'connection' => $exception->getConnectionName(),
                    'driver' => $exception->getConnectionDetails()['driver'] ?? null,
                    'database' => $exception->getConnectionDetails()['database'] ?? null,
                    'sql' => $exception->getSql(),
                    'binding_count' => count($exception->getBindings()),
                    'binding_types' => array_map(
                        static fn (mixed $binding): string => get_debug_type($binding),
                        $exception->getBindings()
                    ),
                ];
            }

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

        if ($diagnosticId !== null) {
            Log::error('LOANS_READ_DIAGNOSTIC_FAILED', $context);
        } else {
            Log::warning($context['failure_label'], $context);
        }
    }

    private function responseException(Response $response): ?Throwable
    {
        return property_exists($response, 'exception') && $response->exception instanceof Throwable
            ? $response->exception
            : null;
    }

    private function exceptionChain(Throwable $exception): array
    {
        $chain = [];

        do {
            $entry = [
                'class' => $exception::class,
                'message' => $exception->getMessage(),
                'file' => $exception->getFile(),
                'line' => $exception->getLine(),
            ];

            if ($exception instanceof \PDOException && isset($exception->errorInfo) && is_array($exception->errorInfo)) {
                $entry['database_error_info'] = $exception->errorInfo;
            }

            $chain[] = $entry;
            $exception = $exception->getPrevious();
        } while ($exception !== null);

        return $chain;
    }

    private function failureLabel(Request $request): string
    {
        $path = strtolower($request->path());

        return match (true) {
            $this->isLoanReadDiagnosticRequest($request) && str_ends_with($path, '/waiting') => 'LECTURA DE PRESTAMOS EN ESPERA FALLIDA',
            $this->isLoanReadDiagnosticRequest($request) => 'LECTURA DE PRESTAMOS FALLIDA',
            str_contains($path, 'loan-payments') => 'PAGO DE PRESTAMO FALLIDO',
            str_contains($path, '/loans/') && str_contains($path, 'review') => 'REVISION DE PRESTAMO FALLIDA',
            str_contains($path, '/loans/') && str_contains($path, 'reject') => 'RECHAZO DE PRESTAMO FALLIDO',
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
            $this->isLoanReadDiagnosticRequest($request) && str_ends_with($path, '/waiting') => 'listar prestamos en espera',
            $this->isLoanReadDiagnosticRequest($request) => 'listar prestamos',
            str_contains($path, 'loan-payments') => 'registrar el pago de una cuota de prestamo',
            str_contains($path, '/loans/') && str_contains($path, 'review') => 'revisar una solicitud de prestamo',
            str_contains($path, '/loans/') && str_contains($path, 'reject') => 'rechazar una solicitud de prestamo',
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
            $this->isLoanReadDiagnosticRequest($request) && str_ends_with($path, '/waiting') => 'listar prestamos en espera',
            $this->isLoanReadDiagnosticRequest($request) => 'listar prestamos',
            str_contains($path, 'loan-payments') => 'pagar cuota de prestamo',
            str_contains($path, '/loans/') && str_contains($path, 'review') => 'proponer condiciones de prestamo',
            str_contains($path, '/loans/') && str_contains($path, 'reject') => 'rechazar solicitud de prestamo',
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
