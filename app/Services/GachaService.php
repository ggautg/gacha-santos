<?php

namespace App\Services;

use App\Models\Player;
use App\Models\Pull;
use App\Models\Saint;
use App\Models\SaintCopy;
use App\Support\CardPresenter;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class GachaService
{
    /**
     * Realiza una o diez invocaciones y devuelve las cartas obtenidas.
     */
    public function pull(Player $player, int $count = 1, bool $free = false): array
    {
        $count = $count >= 10 ? 10 : 1;
        $config = config('sanctorum');

        return DB::transaction(function () use ($player, $count, $free, $config) {
            /** @var Player $player */
            $player = Player::whereKey($player->id)->lockForUpdate()->firstOrFail();

            $free = $free && $count === 1 && $player->freePullReady();
            $cost = $free ? 0 : ($count === 10 ? $config['ten_pull_cost'] : $config['pull_cost'] * $count);

            if ($cost > 0 && $player->grace < $cost) {
                throw ValidationException::withMessages([
                    'gacha' => 'No tienes suficiente Gracia. Gana más descendiendo a las mazmorras.',
                ]);
            }

            $player->grace -= $cost;
            $batch = (string) Str::uuid();
            $cards = [];
            $guaranteedFour = false;

            for ($i = 0; $i < $count; $i++) {
                $isLast = $i === $count - 1;

                $rarity = $this->rollRarity($player, $isLast && $count === 10 && ! $guaranteedFour);

                if ($rarity >= 4) {
                    $guaranteedFour = true;
                }

                $saint = $this->randomSaint($rarity);

                $player->pity = $rarity === 5 ? 0 : $player->pity + 1;
                $player->ten_pity = $rarity >= 4 ? 0 : $player->ten_pity + 1;

                $copy = SaintCopy::firstOrNew([
                    'player_id' => $player->id,
                    'saint_id' => $saint->id,
                ]);

                $isNew = ! $copy->exists;

                if ($isNew) {
                    $copy->level = 1;
                    $copy->xp = 0;
                    $copy->copies = 1;
                } else {
                    $copy->copies++;
                    $player->grace += $config['duplicate_grace'][$saint->rarity] ?? 10;
                }

                $copy->save();

                Pull::create([
                    'player_id' => $player->id,
                    'saint_id' => $saint->id,
                    'rarity' => $saint->rarity,
                    'is_new' => $isNew,
                    'batch' => $batch,
                ]);

                $cards[] = $this->cardPayload($saint, $isNew, $copy->copies);
            }

            // El equipo se completa solo con las primeras cartas nuevas.
            $team = $player->teamIds();
            foreach ($cards as $card) {
                if (count($team) >= 3) {
                    break;
                }
                if ($card['is_new'] && ! in_array($card['id'], $team, true)) {
                    $team[] = $card['id'];
                }
            }

            if ($free) {
                $player->free_pull_at = now()->addHours($config['free_pull_hours']);
            }

            $player->team = array_values(array_unique($team));
            $player->save();

            return [
                'ok' => true,
                'free' => $free,
                'batch' => $batch,
                'cards' => $cards,
                'grace' => $player->grace,
                'pity' => $player->pity,
                'pity_max' => $config['pity'],
                'free_ready' => $player->freePullReady(),
                'free_in' => $player->secondsUntilFreePull(),
                'team' => $player->teamIds(),
            ];
        });
    }

    private function rollRarity(Player $player, bool $guaranteeFour): int
    {
        $config = config('sanctorum.rates');

        if ($player->pity >= config('sanctorum.pity') - 1) {
            return 5;
        }

        if ($guaranteeFour || $player->ten_pity >= config('sanctorum.ten_pity') - 1) {
            return $this->weighted([4 => 0.85, 5 => 0.15]);
        }

        $roll = mt_rand(1, 100000) / 100000;
        $cumulative = 0.0;

        foreach ($config as $rarity => $chance) {
            $cumulative += $chance;
            if ($roll <= $cumulative) {
                return $rarity;
            }
        }

        return 1;
    }

    private function weighted(array $weights): int
    {
        $roll = mt_rand(1, 100000) / 100000;
        $cumulative = 0.0;

        foreach ($weights as $value => $weight) {
            $cumulative += $weight;
            if ($roll <= $cumulative) {
                return $value;
            }
        }

        return array_key_last($weights);
    }

    private function randomSaint(int $rarity): Saint
    {
        for ($attempt = $rarity; $attempt >= 1; $attempt--) {
            $saint = Saint::where('rarity', $attempt)->inRandomOrder()->first();
            if ($saint) {
                return $saint;
            }
        }

        return Saint::inRandomOrder()->firstOrFail();
    }

    private function cardPayload(Saint $saint, bool $isNew, int $copies): array
    {
        return [
            'id' => $saint->id,
            'slug' => $saint->slug,
            'name' => $saint->name,
            'epithet' => $saint->epithet,
            'rarity' => $saint->rarity,
            'rarity_label' => $saint->rarityLabel(),
            'rarity_color' => $saint->rarityColor(),
            'stars' => Saint::RARITIES[$saint->rarity]['stars'],
            'role' => $saint->role,
            'quote' => $saint->quote,
            'skill_name' => $saint->skill_name,
            'skill_text' => $saint->skill_text,
            'is_new' => $isNew,
            'copies' => $copies,
            'art' => CardPresenter::artUrl($saint),
        ];
    }
}
