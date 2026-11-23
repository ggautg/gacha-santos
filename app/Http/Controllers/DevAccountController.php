<?php

namespace App\Http\Controllers;

use App\Models\Player;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Herramienta de desarrollo para reiniciar la cuenta de prueba.
 *
 * La ruta está registrada siempre, pero la acción se rechaza en producción:
 * así el gate se evalúa en cada petición y no al cachear las rutas.
 */
class DevAccountController extends Controller
{
    /**
     * Borra la cuenta actual con todo su progreso. El middleware crea una
     * cuenta nueva en la siguiente petición. Solo funciona fuera de producción.
     */
    public function reset(Request $request): RedirectResponse
    {
        abort_unless(app()->environment(['local', 'testing']), 404);

        /** @var Player $player */
        $player = $request->attributes->get('player');

        $player->delete();
        $request->session()->forget('player_token');

        return redirect()
            ->route('sanctuary')
            ->with('success', 'Cuenta reiniciada. Empiezas de cero.');
    }
}
