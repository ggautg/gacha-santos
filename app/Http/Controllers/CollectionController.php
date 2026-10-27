<?php

namespace App\Http\Controllers;

use App\Models\Player;
use App\Models\Saint;
use App\Models\SaintCopy;
use App\Support\CardPresenter;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

class CollectionController extends Controller
{
    public function index(Request $request)
    {
        /** @var Player $player */
        $player = $request->attributes->get('player');

        $copies = SaintCopy::with('saint')
            ->where('player_id', $player->id)
            ->get()
            ->keyBy('saint_id');

        $saints = Saint::orderByDesc('rarity')
            ->orderBy('sort')
            ->orderBy('name')
            ->get();

        $ownedCards = [];
        $veiledCards = [];

        foreach ($saints as $saint) {
            $copy = $copies->get($saint->id);

            if ($copy) {
                $ownedCards[] = CardPresenter::saint($saint, $copy);
            } else {
                $veiledCards[] = [
                    'order' => md5($saint->slug),
                    'card' => CardPresenter::veiled($saint),
                ];
            }
        }

        $cards = collect($ownedCards)
            ->concat(collect($veiledCards)->sortBy('order')->pluck('card'))
            ->values();

        $rarityTotals = Saint::selectRaw('rarity, count(*) as total')
            ->groupBy('rarity')
            ->pluck('total', 'rarity');

        $rarityOwned = [];
        foreach ($copies as $copy) {
            $rarity = $copy->saint->rarity;
            $rarityOwned[$rarity] = ($rarityOwned[$rarity] ?? 0) + 1;
        }

        return Inertia::render('Collection', [
            'saints' => $cards,
            'player' => [
                'name' => $player->name,
                'grace' => $player->grace,
                'owned' => $copies->count(),
                'total' => $cards->count(),
            ],
            'raritySummary' => collect(Saint::RARITIES)
                ->map(fn (array $meta, int $rarity) => [
                    'rarity' => $rarity,
                    'label' => $meta['label'],
                    'color' => $meta['color'],
                    'owned' => $rarityOwned[$rarity] ?? 0,
                    'total' => (int) ($rarityTotals[$rarity] ?? 0),
                ])
                ->sortKeysDesc()
                ->values(),
            'level' => [
                'max' => (int) config('sanctorum.max_level'),
                'base_cost' => (int) config('sanctorum.level_cost_base'),
            ],
        ]);
    }

    public function levelUp(Request $request, Saint $saint)
    {
        /** @var Player $player */
        $player = $request->attributes->get('player');

        return DB::transaction(function () use ($player, $saint) {
            /** @var Player $player */
            $player = Player::whereKey($player->id)->lockForUpdate()->firstOrFail();

            $copy = SaintCopy::where('player_id', $player->id)
                ->where('saint_id', $saint->id)
                ->first();

            if (! $copy) {
                throw ValidationException::withMessages([
                    'level' => 'Todavía no has invocado a este santo.',
                ]);
            }

            $max = (int) config('sanctorum.max_level');

            if ($copy->level >= $max) {
                throw ValidationException::withMessages([
                    'level' => 'Este santo ya alcanzó la santidad plena (nivel '.$max.').',
                ]);
            }

            $cost = (int) config('sanctorum.level_cost_base') * $copy->level;

            if ($player->grace < $cost) {
                throw ValidationException::withMessages([
                    'level' => 'Necesitas '.$cost.' de Gracia para iluminar a este santo.',
                ]);
            }

            $player->grace -= $cost;
            $player->save();

            $copy->level++;
            $copy->save();

            return response()->json([
                'ok' => true,
                'grace' => $player->grace,
                'level' => $copy->level,
                'xp' => $copy->xp,
                'xp_next' => $copy->xpForNextLevel(),
                'cost_next' => $copy->level >= $max ? null : (int) config('sanctorum.level_cost_base') * $copy->level,
                'max_level' => $max,
                'stats' => $copy->stats(),
                'power' => $copy->power(),
            ]);
        });
    }
}
