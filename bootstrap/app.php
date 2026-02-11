<?php

declare(strict_types=1);

use App\Http\Middleware\ForceJsonResponseMiddleware;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
        then: function (): void {
            Route::middleware('api')
                ->prefix('api')
                ->as('api.')
                ->group(base_path('routes/api.php'));
        }
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->api([
            ForceJsonResponseMiddleware::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(function ($request) {
            return $request->expectsJson() || $request->is('api/*');
        });

        $exceptions->render(function (AuthenticationException $exception, Request $request): JsonResponse {
            logger()->error($exception);

            return response()->json([
                'success' => false,
                'message' => 'Unauthenticated',
            ], Response::HTTP_UNAUTHORIZED);
        });

        $exceptions->render(function (AccessDeniedHttpException $exception, Request $request): JsonResponse {
            logger()->error($exception);

            return response()->json([
                'success' => false,
                'message' => 'Unauthorized',
            ], Response::HTTP_FORBIDDEN);
        });

        $exceptions->render(function (ValidationException $exception, Request $request): JsonResponse {
            logger()->error($exception);

            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $exception->errors(),
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        });

        $exceptions->render(function (ModelNotFoundException $exception, Request $request): JsonResponse {
            logger()->error($exception);

            return response()->json([
                'success' => false,
                'message' => 'Resource not found',
            ], Response::HTTP_NOT_FOUND);
        });

        $exceptions->render(function (NotFoundHttpException $exception, Request $request): JsonResponse {
            logger()->error($exception);

            if ($exception->getPrevious() instanceof ModelNotFoundException) {
                return response()->json([
                    'success' => false,
                    'message' => 'Resource not found',
                ], Response::HTTP_NOT_FOUND);
            }

            return response()->json([
                'success' => false,
                'message' => 'Endpoint not found',
            ], Response::HTTP_NOT_FOUND);
        });

        $exceptions->render(function (TooManyRequestsHttpException $exception, Request $request): JsonResponse {
            logger()->error($exception);

            return response()->json([
                'success' => false,
                'message' => 'Too many requests. Please try again later.',
            ], Response::HTTP_TOO_MANY_REQUESTS);
        });

        $exceptions->render(function (MethodNotAllowedHttpException $exception, Request $request): JsonResponse {
            logger()->error($exception);

            return response()->json([
                'success' => false,
                'message' => 'Method not allowed',
            ], Response::HTTP_METHOD_NOT_ALLOWED);
        });

        $exceptions->render(function (Exception|Error $exception, Request $request): JsonResponse {
            logger()->error($exception);

            return response()->json([
                'success' => false,
                'message' => 'Internal server error',
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        });
    })->create();
