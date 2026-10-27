<?php

namespace App\Http\Middleware;

use App\Models\Player;
use App\Models\Saint;
use App\Models\SaintCopy;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\View;
use Symfony\Component\HttpFoundation\Response;

class EnsurePlayer
{
    public function handle(Request $request, Closure $next): Response
    {
        $token = $request->session()->get('player_token');

        $player = $token
            ? Player::where('token', $token)->first()
            : null;

        if (! $player) {
            $player = $this->createPlayer();
            $request->session()->put('player_token', $player->token);
            $request->session()->save();
        }

        $request->attributes->set('player', $player);
        View::share('currentPlayer', $player);

        return $next($request);
    }

    private function createPlayer(): Player
    {
        return DB::transaction(function () {
            $player = Player::create([
                'name' => 'Peregrino',
                'grace' => config('sanctorum.starting_grace'),
                'depth' => 0,
            ]);

            $starters = Saint::whereIn('slug', config('sanctorum.starter_slugs'))
                ->get()
                ->sortBy(fn (Saint $saint) => array_search($saint->slug, config('sanctorum.starter_slugs'), true))
                ->values();

            foreach ($starters as $saint) {
                SaintCopy::create([
                    'player_id' => $player->id,
                    'saint_id' => $saint->id,
                    'copies' => 1,
                    'level' => 1,
                    'xp' => 0,
                ]);
            }

            $player->team = $starters->pluck('id')->all();
            $player->save();

            return $player;
        });
    }
}
