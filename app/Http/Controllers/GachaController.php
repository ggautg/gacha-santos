<?php

namespace App\Http\Controllers;

use App\Models\Player;
use App\Models\Pull;
use App\Models\Saint;
use App\Models\SaintCopy;
use App\Services\GachaService;
use App\Support\CardPresenter;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

class GachaController extends Controller
{
    public function index(Request $request)
    {
        /** @var Player $player */
        $player = $request->attributes->get('player');

        $featured = Saint::where('slug', 'san-miguel-arcangel')->first()
            ?? Saint::orderByDesc('rarity')->first();

        $copies = SaintCopy::with('saint')
            ->where('player_id', $player->id)
            ->get()
            ->keyBy('saint_id');

        $recent = Pull::with('saint')
            ->where('player_id', $player->id)
            ->latest('id')
            ->take(10)
            ->get()
            ->map(fn (Pull $pull) => [
                'id' => $pull->id,
                'name' => $pull->saint->name,
                'rarity' => $pull->saint->rarity,
                'rarity_color' => $pull->saint->rarityColor(),
                'is_new' => $pull->is_new,
                'at' => $pull->created_at->format('d/m H:i'),
                'art' => CardPresenter::artUrl($pull->saint),
            ]);

        $banner = Saint::where('rarity', 5)
            ->orderBy('sort')
            ->get()
            ->map(fn (Saint $saint) => [
                'id' => $saint->id,
                'name' => $saint->name,
                'art' => CardPresenter::artUrl($saint),
                'rarity' => $saint->rarity,
                'rarity_color' => $saint->rarityColor(),
                'owned' => $copies->has($saint->id),
            ]);

        return Inertia::render('Gacha', [
            'player' => [
                'grace' => $player->grace,
                'pity' => $player->pity,
                'pity_max' => (int) config('sanctorum.pity'),
                'free_ready' => $player->freePullReady(),
                'free_in' => $player->secondsUntilFreePull(),
                'owned' => $copies->count(),
                'total' => Saint::count(),
            ],
            'featured' => CardPresenter::saint($featured, $copies->get($featured->id)),
            'recent' => $recent,
            'banner' => $banner,
            'costs' => [
                'single' => (int) config('sanctorum.pull_cost'),
                'ten' => (int) config('sanctorum.ten_pull_cost'),
                'free_hours' => (int) config('sanctorum.free_pull_hours'),
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
