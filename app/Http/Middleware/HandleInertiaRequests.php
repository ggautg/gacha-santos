<?php

namespace App\Http\Middleware;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Inertia\Middleware;

/**
 * Comparte con Inertia el jugador, los avisos y las opciones de desarrollo.
 */
class HandleInertiaRequests extends Middleware
{
    /** Plantilla Blade que se carga en la primera visita. */
    protected $rootView = 'app';

    /**
     * Versión de los recursos para forzar la recarga cuando cambian.
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Propiedades disponibles en todas las páginas Vue.
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        $player = $request->attributes->get('player');

        return [
            ...parent::share($request),
            'player' => $player ? [
                'name' => $player->name,
                'grace' => $player->grace,
                'pity' => $player->pity,
                'wins' => $player->wins,
                'losses' => $player->losses,
            ] : null,
            'canResetAccount' => app()->environment(['local', 'testing']) && Route::has('dev.account.reset'),
            'flash' => [
                'success' => fn () => $request->session()->get('success'),
                'error' => fn () => $request->session()->get('error'),
            ],
        ];
    }
}
