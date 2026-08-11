<?php

namespace App\Services\Ai;

// "Resolusi nama -> id (‘gopay' -> accounts.id) di server, fuzzy match pada
// data family. Ragu -> kosongkan field agar user melengkapi lewat Edit."
class NameResolver
{
    /**
     * @param  array<int, array{id: string, name: string}>  $candidates
     */
    public static function resolve(?string $query, array $candidates, int $minPercent = 60): ?string
    {
        $query = $query !== null ? trim($query) : '';

        if ($query === '' || $candidates === []) {
            return null;
        }

        $needle = mb_strtolower($query);

        foreach ($candidates as $candidate) {
            if (mb_strtolower($candidate['name']) === $needle) {
                return $candidate['id'];
            }
        }

        $bestId = null;
        $bestPercent = 0.0;

        foreach ($candidates as $candidate) {
            similar_text($needle, mb_strtolower($candidate['name']), $percent);

            if ($percent > $bestPercent) {
                $bestPercent = $percent;
                $bestId = $candidate['id'];
            }
        }

        return $bestPercent >= $minPercent ? $bestId : null;
    }
}
