<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Réserve une route à qui gère la comptabilité ouverte.
 *
 * Le contrôle « admin » regarde le rôle du compte, pas le dossier. Or le
 * responsable d'une comptabilité — celui qui l'a créée, le gérant du cabinet
 * qui la porte, ou la personne affectée avec un accès total — peut très bien
 * avoir un compte « comptable ». Il voyait le lien dans son menu, mais l'URL
 * lui répondait « Accès réservé aux administrateurs » ; le même écran s'ouvrait
 * pourtant au superadmin en mode switch, puisque celui-ci entre en admin.
 *
 * Un collaborateur sur habilitations reste dehors : la configuration d'un
 * dossier appartient à qui en répond. Chaque route garde en plus sa propre
 * habilitation (middleware « permission »).
 *
 * Usage : ->middleware('gere.comptabilite')
 */
class EnsureGereLaComptabilite
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (!$user) {
            return $request->expectsJson()
                ? response()->json(['message' => 'Non authentifié.'], 401)
                : redirect()->route('login');
        }

        if ($user->isAdmin() || $user->gereLaComptabiliteCourante()) {
            return $next($request);
        }

        $message = "Accès réservé au responsable de cette comptabilité.";

        return $request->expectsJson()
            ? response()->json(['message' => $message], 403)
            : abort(403, $message);
    }
}
