<?php

namespace EgoBytes\EgoDocs\Http;

use EgoBytes\EgoDocs\EgoDocs;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Firma un token nuevo para quien sigue con sesión.
 *
 * El token del script se firma al cargar la página y vence en una hora. En
 * una aplicación de una sola página (Inertia, Livewire) la página no se
 * vuelve a cargar al cambiar de pantalla: sin esto, después de una hora
 * todas las preguntas fallarían. El widget lo pide solo cuando EGO docs le
 * dice que el suyo venció.
 *
 * Usa la sesión del propio sistema, así que solo firma para quien ya está
 * dentro: no da nada que la página no tuviera ya.
 */
class RenovarToken
{
    public function __invoke(Request $request): JsonResponse
    {
        $guard = (string) $request->query('guard', '');

        if ($guard === '' || ! array_key_exists($guard, (array) config('auth.guards')) || ! EgoDocs::widget($guard)) {
            return $this->responder(['error' => 'guard'], 404);
        }

        $token = EgoDocs::token(null, $guard);

        if (! $token) {
            return $this->responder(['error' => 'sin_sesion'], 401);
        }

        return $this->responder(['token' => $token]);
    }

    /** @param  array<string, string>  $datos */
    protected function responder(array $datos, int $estado = 200): JsonResponse
    {
        // Lleva la identidad de la persona: que no lo guarde nadie en el camino.
        return response()->json($datos, $estado)->header('Cache-Control', 'no-store, private');
    }
}
