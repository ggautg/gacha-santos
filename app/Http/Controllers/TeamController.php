<?php

namespace App\Http\Controllers;

use App\Models\Player;
use App\Models\SaintCopy;
use App\Support\CardPresenter;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

class TeamController extends Controller
{
    public function index(Request $request)
    {
        /** @var Player $player */
        $player = $request->attributes->get('player');

        $copies = SaintCopy::with('saint')
            ->where('player_id', $player->id)
            ->get()
            ->sortByDesc(fn (SaintCopy $copy) => $copy->saint->rarity * 1000 + $copy->level)
            ->values();

        $byId = $copies->keyBy('saint_id');
        $teamIds = $player->teamIds();

        $team = collect($teamIds)
            ->map(fn (int $id) => $byId->get($id))
            ->filter()
            ->values();

        return Inertia::render('Team', [
            'roster' => $copies->map(fn (SaintCopy $copy) => CardPresenter::saint($copy->saint, $copy))->values(),
            'teamIds' => $team->pluck('saint_id')->values()->all(),
            'power' => $team->sum(fn (SaintCopy $copy) => $copy->power()),
            'player' => [
                'name' => $player->name,
                'grace' => $player->grace,
            ],
        ]);
    }

    public function save(Request $request)
    {
        /** @var Player $player */
        $player = $request->attributes->get('player');

        $data = $request->validate([
            'team' => ['nullable', 'array', 'max:3'],
            'team.*' => ['integer'],
        ]);

        $owned = SaintCopy::where('player_id', $player->id)
            ->pluck('saint_id')
            ->all();

        $team = collect($data['team'] ?? [])
            ->map(fn ($id) => (int) $id)
            ->filter(fn ($id) => in_array($id, $owned, true))
            ->unique()
            ->take(3)
            ->values()
            ->all();

        if (empty($team)) {
            throw ValidationException::withMessages([
                'team' => 'Tu equipo necesita al menos un santo.',
            ]);
        }

        $player->team = $team;
        $player->save();

        $copies = SaintCopy::with('saint')
            ->where('player_id', $player->id)
            ->whereIn('saint_id', $team)
            ->get()
            ->keyBy('saint_id');

        $power = collect($team)
            ->map(fn ($id) => $copies->get($id))
            ->filter()
            ->sum(fn (SaintCopy $copy) => $copy->power());

        return response()->json([
            'ok' => true,
            'team' => $team,
            'power' => $power,
        ]);
    }
}
