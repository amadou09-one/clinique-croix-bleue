<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Routing\Exceptions\InvalidSignatureException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->alias([
            'role' => \App\Http\Middleware\EnsureUserHasRole::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        // Lien de validation RDV expiré ou altéré : page propre aux couleurs de la
        // clinique plutôt que le 403 brut par défaut de Laravel.
        $exceptions->render(function (InvalidSignatureException $e, $request) {
            if ($request->routeIs('rdv.validation')) {
                return response()->view('rdv.resultat', [
                    'icone' => '⏰',
                    'titre' => 'Ce lien a expiré',
                    'message' => "Ce lien de validation n'est plus valide (il expire 48 h après l'envoi de l'e-mail) ou a été modifié. Contactez le secrétariat si besoin.",
                    'details' => [],
                ], 403);
            }
        });
    })->create();
