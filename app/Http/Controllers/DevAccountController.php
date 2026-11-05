<?php

namespace App\Http\Controllers;

use App\Models\Player;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class DevAccountController extends Controller
{
    /**
     * Borra la cuenta actual con todo su progreso. El middleware crea una
     * cuenta nueva en la siguiente petición. Solo existe fuera de producción.
     */
    public function reset(Request $request): RedirectResponse
    {
        /** @var Player $player */
        $player = $request->attributes->get('player');

        $player->delete();
        $request->session()->forget('player_token');

        return redirect()
            ->route('sanctuary')
            ->with('success', 'Cuenta reiniciada. Empiezas de cero.');
    }
}
