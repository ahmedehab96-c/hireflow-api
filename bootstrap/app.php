<?php

use App\Http\Middleware\EnsureUserHasRole;
use App\Http\Middleware\ForceJsonResponse;
use App\Http\Middleware\SecurityHeaders;
use App\Support\ApiResponse;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Exceptions\ThrottleRequestsException;
use Illuminate\Http\Request;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->prependToGroup('api', [ForceJsonResponse::class, SecurityHeaders::class]);
        $middleware->alias(['role' => EnsureUserHasRole::class]);
        // Check the role before route-model binding, so a wrong-role user gets 403 rather than
        // learning whether a record id exists (404).
        $middleware->prependToPriorityList(before: SubstituteBindings::class, prepend: EnsureUserHasRole::class);

        // There is no web login page; API guests get a JSON 401 instead.
        $middleware->redirectGuestsTo(fn (Request $request) => $request->is('api/*') ? null : '/');
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );

        // Same envelope for every API error; internal details never leak.
        // Messages passed to our own abort() calls are kept; framework defaults get a generic one.
        $own = fn (Throwable $e): ?string => ($m = $e->getMessage()) !== '' && $m !== 'This action is unauthorized.' ? $m : null;

        $exceptions->render(function (Throwable $e, Request $request) use ($own) {
            if (! $request->is('api/*')) {
                return null;
            }

            $status = $e instanceof HttpExceptionInterface ? $e->getStatusCode() : 500;

            return match (true) {
                $e instanceof ValidationException => ApiResponse::error('The given data was invalid.', 422, $e->errors()),
                $e instanceof AuthenticationException => ApiResponse::error('Unauthenticated.', 401),
                $e instanceof ThrottleRequestsException => ApiResponse::error('Too many requests. Please try again later.', 429),
                $status === 404 => ApiResponse::error('Resource not found.', 404),
                $status === 403 => ApiResponse::error($own($e) ?? 'You are not allowed to perform this action.', 403),
                $status === 405 => ApiResponse::error('Method not allowed.', 405),
                $status >= 500 => ApiResponse::error('Server error.', 500),
                default => ApiResponse::error($own($e) ?? 'Bad request.', $status),
            };
        });
    })->create();
