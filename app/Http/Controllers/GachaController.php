<?php

namespace App\Http\Controllers;

use App\Models\Player;
use App\Services\GachaService;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class GachaController extends Controller
{
    public function pull(Request $request, GachaService $gacha)
    {
        /** @var Player $player */
        $player = $request->attributes->get('player');

        $data = $request->validate([
            'count' => ['nullable', 'integer', 'in:1,10'],
            'free' => ['nullable', 'boolean'],
        ]);

        try {
            $result = $gacha->pull(
                $player,
                (int) ($data['count'] ?? 1),
                (bool) ($data['free'] ?? false),
            );
        } catch (ValidationException $exception) {
            return response()->json([
                'message' => $exception->getMessage(),
                'errors' => $exception->errors(),
            ], 422);
        }

        return response()->json($result);
    }
}
