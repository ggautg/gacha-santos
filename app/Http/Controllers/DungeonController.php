<?php

namespace App\Http\Controllers;

use App\Models\Battle as BattleRecord;
use App\Models\Player;
use App\Models\SaintCopy;
use App\Services\BattleService;
use App\Support\CardPresenter;
use App\Support\DungeonRules;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

class DungeonController extends Controller
{
    public function index(Request $request)
    {
        /** @var Player $player */
        $player = $request->attributes->get('player');

        $dungeons = collect(DungeonRules::all())
            ->map(function (array $dungeon, string $key) use ($player) {
                $unlocked = DungeonRules::isUnlocked($player, $key);
                $bestFloor = DungeonRules::bestFloor($player, $key);
                $run = $unlocked ? DungeonRules::run($player, $key) : null;
                $encounter = $unlocked ? DungeonRules::encounter($player, $key) : null;

                return [
                    'key' => $key,
                    'name' => $dungeon['name'],
                    'subtitle' => $dungeon['subtitle'],
                    'tagline' => $dungeon['tagline'] ?? '',
                    'theme' => $dungeon['theme'] ?? $key,
                    'font_name' => $dungeon['font_name'] ?? 'Píxel',
                    'accent' => $dungeon['accent'] ?? '#5a4a9a',
                    'accent2' => $dungeon['accent2'] ?? '#f2c94c',
                    'unlocked' => $unlocked,
                    'unlock_text' => DungeonRules::unlockText($key),
                    'best_floor' => $bestFloor,
                    'run_floor' => $run?->floor ?? 1,
                    'cleared' => DungeonRules::clearedCount($player, $key),
                    'next' => $encounter ? [
                        'floor' => $encounter['floor'],
                        'name' => $encounter['name'],
                        'subtitle' => $encounter['subtitle'],
                        'enemy' => $encounter['enemy'],
                        'sprite' => $encounter['sprite'],
                        'hp' => $encounter['hp'],
                        'atk' => $encounter['atk'],
                        'grace' => $encounter['grace'],
                        'xp' => $encounter['xp'],
                    ] : null,
                ];
            })
            ->values();

        return Inertia::render('Dungeons', [
            'dungeons' => $dungeons,
            'deepest' => $dungeons->max('best_floor') ?? 0,
            'player' => [
                'name' => $player->name,
                'grace' => $player->grace,
            ],
        ]);
    }

    public function show(Request $request, string $dungeon)
    {
        abort_unless(DungeonRules::exists($dungeon), 404);

        /** @var Player $player */
        $player = $request->attributes->get('player');
        $config = DungeonRules::get($dungeon);

        if (! DungeonRules::isUnlocked($player, $dungeon)) {
            return redirect()->route('dungeons')->with('error', DungeonRules::unlockText($dungeon));
        }

        $run = DungeonRules::run($player, $dungeon);
        $encounter = DungeonRules::encounter($player, $dungeon, $run->floor, $run);

        $copies = SaintCopy::with('saint')
            ->where('player_id', $player->id)
            ->get()
            ->sortByDesc(fn (SaintCopy $copy) => $copy->saint->rarity * 1000 + $copy->level)
            ->values();

        $ownedIds = $copies->pluck('saint_id')->all();
        $teamIds = array_values(array_filter(
            $player->teamIds(),
            fn (int $id) => in_array($id, $ownedIds, true),
        ));
        $teamIds = array_slice($teamIds, 0, 3);

        $history = BattleRecord::where('player_id', $player->id)
            ->where('dungeon', $dungeon)
            ->latest('id')
            ->take(8)
            ->get()
            ->map(fn (BattleRecord $battle) => [
                'floor' => $battle->stage,
                'name' => DungeonRules::bossName($dungeon, (string) $battle->enemy_key),
                'result' => $battle->result,
                'grace' => $battle->grace_reward,
                'at' => $battle->created_at->format('d/m H:i'),
            ])
            ->values();

        return Inertia::render('DungeonRun', [
            'dungeon' => [
                'key' => $dungeon,
                'name' => $config['name'],
                'subtitle' => $config['subtitle'],
                'tagline' => $config['tagline'] ?? '',
                'theme' => $config['theme'] ?? $dungeon,
                'font_name' => $config['font_name'] ?? 'Píxel',
                'accent' => $config['accent'] ?? '#5a4a9a',
                'accent2' => $config['accent2'] ?? '#f2c94c',
                'best_floor' => DungeonRules::bestFloor($player, $dungeon),
                'run_floor' => $run->floor,
                'cleared' => DungeonRules::clearedCount($player, $dungeon),
                'unlock_text' => DungeonRules::unlockText($dungeon),
            ],
            'encounter' => $encounter,
            'roster' => $copies->map(fn (SaintCopy $copy) => CardPresenter::saint($copy->saint, $copy))->values(),
            'teamIds' => $teamIds,
            'history' => $history,
            'player' => [
                'name' => $player->name,
                'grace' => $player->grace,
                'wins' => $player->wins,
                'losses' => $player->losses,
            ],
        ]);
    }

    public function fight(Request $request, BattleService $battles, string $dungeon)
    {
        abort_unless(DungeonRules::exists($dungeon), 404);

        /** @var Player $player */
        $player = $request->attributes->get('player');

        $data = $request->validate([
            'team' => ['nullable', 'array'],
            'team.*' => ['integer'],
        ]);

        try {
            $result = $battles->fightDungeon($player, $dungeon, $data['team'] ?? []);
        } catch (ValidationException $exception) {
            return response()->json([
                'message' => $exception->getMessage(),
                'errors' => $exception->errors(),
            ], 422);
        }

        return response()->json($result);
    }

    public function restart(Request $request, string $dungeon)
    {
        abort_unless(DungeonRules::exists($dungeon), 404);

        /** @var Player $player */
        $player = $request->attributes->get('player');

        if (! DungeonRules::isUnlocked($player, $dungeon)) {
            return redirect()->route('dungeons')->with('error', DungeonRules::unlockText($dungeon));
        }

        DungeonRules::restart($player, $dungeon);

        return redirect()
            ->route('dungeons.show', $dungeon)
            ->with('success', 'Has descendido por una grieta nueva: el elenco de esta mazmorra ha cambiado.');
    }
}
