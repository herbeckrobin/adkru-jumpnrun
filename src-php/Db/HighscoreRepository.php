<?php

declare(strict_types=1);

namespace Jumpnrun\Db;

final class HighscoreRepository
{
    /**
     * Upsert: Ein Eintrag pro Name, behaelt den Bestwert.
     *
     * @return array{personalBest:bool, previousBest:int|null, storedScore:int}
     */
    public function upsert(string $name, int $score, int $level): array
    {
        global $wpdb;
        $table = Schema::highscoresTable();

        $previousRaw = $wpdb->get_var(
            $wpdb->prepare("SELECT score FROM {$table} WHERE name = %s", $name)
        );
        $previousBest = $previousRaw === null ? null : (int) $previousRaw;

        $wpdb->query(
            $wpdb->prepare(
                "INSERT INTO {$table} (name, score, level)
                 VALUES (%s, %d, %d)
                 ON DUPLICATE KEY UPDATE
                    score = GREATEST(score, VALUES(score)),
                    level = IF(VALUES(score) > score, VALUES(level), level)",
                $name,
                $score,
                $level
            )
        );

        $personalBest = $previousBest === null || $score > $previousBest;
        $storedScore = $personalBest ? $score : (int) $previousBest;

        return [
            'personalBest' => $personalBest,
            'previousBest' => $previousBest,
            'storedScore' => $storedScore,
        ];
    }

    /** @return list<array{id:int, name:string, score:int, level:int}> */
    public function topN(int $limit = 10): array
    {
        global $wpdb;
        $table = Schema::highscoresTable();
        $limit = max(1, min($limit, 100));

        $rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT id, name, score, level FROM {$table}
                 ORDER BY score DESC, updated_at ASC
                 LIMIT %d",
                $limit
            ),
            ARRAY_A
        );

        if (!is_array($rows)) {
            return [];
        }

        return array_map(
            static fn (array $row): array => [
                'id' => (int) $row['id'],
                'name' => (string) $row['name'],
                'score' => (int) $row['score'],
                'level' => (int) $row['level'],
            ],
            $rows
        );
    }

    /** Rang des Scores in der Top-Liste (1-basiert). */
    public function rankOf(int $score): int
    {
        global $wpdb;
        $table = Schema::highscoresTable();

        $higher = (int) $wpdb->get_var(
            $wpdb->prepare("SELECT COUNT(*) FROM {$table} WHERE score > %d", $score)
        );

        return $higher + 1;
    }

    public function delete(int $id): bool
    {
        global $wpdb;
        return $wpdb->delete(Schema::highscoresTable(), ['id' => $id], ['%d']) === 1;
    }
}
