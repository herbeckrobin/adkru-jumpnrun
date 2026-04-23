<?php

declare(strict_types=1);

namespace Jumpnrun\Db;

/** Verwaltet Spiel-Sessions — Lebenszyklus active → submitted mit Min-Dauer-Check. */
final class SessionRepository
{
    /** Legt eine neue Session an und liefert die UUIDv4 zurueck. */
    public function create(string $ipHash): string
    {
        global $wpdb;
        $id = wp_generate_uuid4();

        $wpdb->insert(
            Schema::sessionsTable(),
            ['id' => $id, 'state' => 'active', 'ip_hash' => $ipHash],
            ['%s', '%s', '%s']
        );

        return $id;
    }

    /**
     * Atomic state-transition: active -> submitted, nur wenn die Session
     * mindestens $minDurationSec alt ist. DB-seitiger Zeitvergleich
     * vermeidet Zeitzonen-Drift zwischen PHP und MySQL.
     *
     * Gibt true zurueck wenn submitted, false sonst (schon submitted,
     * unbekannt oder zu schnell — aus Anti-Cheat-Sicht der gleiche Fall).
     */
    public function markSubmitted(string $id, int $minDurationSec): bool
    {
        global $wpdb;
        $table = Schema::sessionsTable();

        // started_at wird beim INSERT mit CURRENT_TIMESTAMP in der DB-Session-Timezone
        // geschrieben. NOW() liefert die gleiche Timezone — damit ist der Diff
        // unabhaengig davon ob der Server UTC oder eine lokale Zone nutzt.
        $affected = $wpdb->query(
            $wpdb->prepare(
                "UPDATE {$table} SET state = 'submitted'
                 WHERE id = %s
                   AND state = 'active'
                   AND started_at <= DATE_SUB(NOW(), INTERVAL %d SECOND)",
                $id,
                $minDurationSec
            )
        );

        return $affected === 1;
    }
}
