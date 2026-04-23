<?php

declare(strict_types=1);

namespace Jumpnrun\Admin;

use Jumpnrun\Db\HighscoreRepository;

/**
 * Admin-Scoreboard: zeigt Highscores mit Einzel-Delete (Nonce-geschuetzt)
 * und Bulk-Delete per POST. Rein HTML-Tabelle, ohne WP_List_Table —
 * reicht fuer die Groessenordnung und haelt den Code schlank.
 */
final class ScoreboardPage
{
    private const DELETE_NONCE = 'jumpnrun_delete_score';
    private const BULK_NONCE = 'jumpnrun_bulk_delete';

    /** Verarbeitet Delete-Aktionen und rendert die Admin-Highscore-Tabelle. */
    public function render(): void
    {
        if (!current_user_can('manage_options')) {
            wp_die('Unzureichende Berechtigung.');
        }

        $repo = new HighscoreRepository();
        $notice = $this->handleActions($repo);
        $rows = $repo->topN(100);

        $view = JUMPNRUN_DIR . 'views/admin/scoreboard.php';
        if (file_exists($view)) {
            $deleteNonce = wp_create_nonce(self::DELETE_NONCE);
            $bulkNonce = wp_create_nonce(self::BULK_NONCE);
            // $notice, $rows, $deleteNonce, $bulkNonce stehen im View zur Verfuegung.
            require $view;
        }
    }

    private function handleActions(HighscoreRepository $repo): ?string
    {
        // Einzel-Delete via GET-Link + Nonce
        if (isset($_GET['action'], $_GET['id'], $_GET['_wpnonce']) && $_GET['action'] === 'delete') {
            $nonce = (string) $_GET['_wpnonce'];
            if (!wp_verify_nonce($nonce, self::DELETE_NONCE)) {
                wp_die('Nonce ungueltig.');
            }
            $repo->delete((int) $_GET['id']);
            return 'Eintrag geloescht.';
        }

        // Bulk-Delete via POST
        if (isset($_POST['jumpnrun_bulk_delete_nonce'])
            && wp_verify_nonce((string) $_POST['jumpnrun_bulk_delete_nonce'], self::BULK_NONCE)
            && isset($_POST['ids']) && is_array($_POST['ids'])
        ) {
            $count = 0;
            foreach ($_POST['ids'] as $rawId) {
                if ($repo->delete((int) $rawId)) {
                    $count++;
                }
            }
            return sprintf('%d Eintrag/Eintraege geloescht.', $count);
        }

        return null;
    }
}
