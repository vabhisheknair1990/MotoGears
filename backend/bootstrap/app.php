<?php

use App\Exceptions\BusinessException;
use App\Http\Middleware\EnsureAccountIsActive;
use App\Http\Middleware\EnsurePermission;
use App\Http\Middleware\EnsureUserIsStaff;
use App\Http\Middleware\ForceJsonAccept;
use App\Http\Middleware\SecurityHeaders;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Exceptions\ThrottleRequestsException;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        apiPrefix: 'api',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->api(prepend: [ForceJsonAccept::class]);
        $middleware->append(SecurityHeaders::class);
        // Only trust X-Forwarded-* from explicitly configured proxies (e.g. the bundled nginx),
        // so clients can't spoof their IP to dodge rate limits.
        if ($proxies = env('TRUSTED_PROXIES')) {
            $middleware->trustProxies(at: $proxies === '*' ? '*' : array_map('trim', explode(',', $proxies)));
        }
        $middleware->alias([
            'staff' => EnsureUserIsStaff::class,
            'permission' => EnsurePermission::class,
            'active' => EnsureAccountIsActive::class,
        ]);
        // Token-based API: never redirect unauthenticated API calls to a login page.
        $middleware->redirectGuestsTo(fn (Request $request) => $request->is('api/*') ? null : '/');
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(fn (Request $request) => $request->is('api/*') || $request->expectsJson());

        $json = fn (string $message, int $status, array $extra = []) => response()->json(array_merge(['success' => false, 'message' => $message], $extra), $status);

        $exceptions->render(function (ValidationException $e, Request $request) use ($json) {
            if ($request->is('api/*')) {
                return $json($e->status === 422 ? 'Validation failed' : collect($e->errors())->flatten()->first(), $e->status, ['errors' => $e->errors()]);
            }
        });
        $exceptions->render(function (BusinessException $e, Request $request) use ($json) {
            return $json($e->getMessage(), $e->status, $e->errors ? ['errors' => $e->errors] : []);
        });
        $exceptions->render(function (AuthenticationException $e, Request $request) use ($json) {
            if ($request->is('api/*')) {
                return $json('Unauthenticated. Please log in.', 401);
            }
        });
        $exceptions->render(function (AuthorizationException|AccessDeniedHttpException $e, Request $request) use ($json) {
            if ($request->is('api/*')) {
                return $json($e->getMessage() && $e->getMessage() !== 'This action is unauthorized.' ? $e->getMessage() : 'You are not allowed to perform this action.', 403);
            }
        });
        $exceptions->render(function (NotFoundHttpException $e, Request $request) use ($json) {
            if ($request->is('api/*')) {
                $prev = $e->getPrevious();
                $message = $prev instanceof ModelNotFoundException
                    ? class_basename($prev->getModel()).' not found.'
                    : 'The requested resource was not found.';

                return $json(str_replace(['ProductCompatibility', 'VehicleVariant'], ['Compatibility record', 'Vehicle variant'], $message), 404);
            }
        });
        $exceptions->render(function (ThrottleRequestsException $e, Request $request) use ($json) {
            if ($request->is('api/*')) {
                return $json('Too many requests. Please slow down and try again shortly.', 429)
                    ->withHeaders($e->getHeaders());
            }
        });
        $exceptions->render(function (Throwable $e, Request $request) use ($json) {
            if (! $request->is('api/*')) {
                return null;
            }
            if ($e instanceof HttpExceptionInterface) {
                return $json($e->getMessage() ?: 'Request could not be processed.', $e->getStatusCode());
            }
            report($e);

            return $json('Something went wrong on our side. Please try again.', 500,
                config('app.debug') ? ['debug' => ['exception' => get_class($e), 'message' => $e->getMessage(), 'file' => $e->getFile().':'.$e->getLine()]] : []);
        });
        $exceptions->dontReport([BusinessException::class]);
    })->create();
