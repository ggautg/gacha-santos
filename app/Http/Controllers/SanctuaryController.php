<?php

namespace App\Http\Controllers;

use App\Models\Player;
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
            'banner' => $banner,
            'rarityProgress' => $rarityProgress,
            'costs' => [
                'level' => (int) config('sanctorum.level_cost_base'),
            ],
        ]);
    }
}
