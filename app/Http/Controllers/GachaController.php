<?php

namespace App\Http\Controllers;

use App\Models\Player;
use App\Models\Saint;
use App\Services\GachaService;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

class GachaController extends Controller
{
    public function index(Request $request)
    {
        /** @var Player $player */
        $player = $request->attributes->get('player');

        return Inertia::render('Gacha', [
            'player' => [
                'grace' => $player->grace,
                'pity' => $player->pity,
                'pity_max' => (int) config('sanctorum.pity'),
            ],
            'costs' => [
                'single' => (int) config('sanctorum.pull_cost'),
                'ten' => (int) config('sanctorum.ten_pull_cost'),
            ],
            'rates' => collect(config('sanctorum.rates'))
                ->map(fn ($chance, $rarity) => [
                    'rarity' => $rarity,
                    'label' => Saint::RARITIES[$rarity]['label'],
                    'color' => Saint::RARITIES[$rarity]['color'],
                    'chance' => round($chance * 100, 1),
                ])
                ->sortKeysDesc()
                ->values(),
        ]);
    }

    public function pull(Request $request, GachaService $gacha)
    {
        /** @var Player $player */
        $player = $request->attributes->get('player');

        $data = $request->validate([
            'count' => ['nullable', 'integer', 'in:1,10'],
        ]);

        try {
            $result = $gacha->pull($player, (int) ($data['count'] ?? 1));
        } catch (ValidationException $exception) {
            return response()->json([
                'message' => $exception->getMessage(),
                'errors' => $exception->errors(),
            ], 422);
        }

        return response()->json($result);
    }
}
