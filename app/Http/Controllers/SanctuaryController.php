<?php

namespace App\Http\Controllers;

use App\Models\Player;
use App\Models\Pull;
use App\Models\Saint;
use App\Models\SaintCopy;
use App\Support\CardPresenter;
use App\Support\DungeonRules;
use Illuminate\Http\Request;
use Inertia\Inertia;

class SanctuaryController extends Controller
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

        $total = Saint::count();
        $rarityTotals = Saint::selectRaw('rarity, count(*) as total')
            ->groupBy('rarity')
            ->pluck('total', 'rarity');

        $rarityOwned = [];
        foreach ($copies as $copy) {
            $rarity = $copy->saint->rarity;
            $rarityOwned[$rarity] = ($rarityOwned[$rarity] ?? 0) + 1;
        }

        $deepestFloor = 0;
        $clearedFloors = 0;
        $openDungeons = 0;

        foreach (array_keys(DungeonRules::all()) as $key) {
            $deepestFloor = max($deepestFloor, DungeonRules::bestFloor($player, $key));
            $clearedFloors += DungeonRules::clearedCount($player, $key);

            if (DungeonRules::isUnlocked($player, $key)) {
                $openDungeons++;
            }
        }

        $rarityProgress = collect(Saint::RARITIES)
            ->map(fn (array $meta, int $rarity) => [
                'rarity' => $rarity,
                'label' => $meta['label'],
                'color' => $meta['color'],
                'owned' => $rarityOwned[$rarity] ?? 0,
                'total' => (int) ($rarityTotals[$rarity] ?? 0),
            ])
            ->sortKeysDesc()
            ->values();

        return Inertia::render('Sanctuary', [
            'player' => [
                'name' => $player->name,
                'grace' => $player->grace,
                'pity' => $player->pity,
                'pity_max' => (int) config('sanctorum.pity'),
                'free_ready' => $player->freePullReady(),
                'free_in' => $player->secondsUntilFreePull(),
                'wins' => $player->wins,
                'losses' => $player->losses,
                'owned' => $copies->count(),
                'total' => $total,
                'dungeons' => [
                    'deepest' => $deepestFloor,
                    'cleared' => $clearedFloors,
                    'open' => $openDungeons,
                    'total' => count(DungeonRules::all()),
                ],
            ],
            'featured' => CardPresenter::saint($featured, $copies->get($featured->id)),
            'recent' => $recent,
            'banner' => $banner,
            'rarityProgress' => $rarityProgress,
            'costs' => [
                'single' => (int) config('sanctorum.pull_cost'),
                'ten' => (int) config('sanctorum.ten_pull_cost'),
                'free_hours' => (int) config('sanctorum.free_pull_hours'),
                'level' => (int) config('sanctorum.level_cost_base'),
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
}
