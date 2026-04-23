<?php

declare(strict_types=1);

namespace Jumpnrun\Shortcode;

use Jumpnrun\Db\HighscoreRepository;

/** Rendert die Highscore-Tabelle als [jumpnrun_scoreboard]-Shortcode. */
final class ScoreboardShortcode
{
    public const TAG = 'jumpnrun_scoreboard';

    /** Registriert den Shortcode bei WordPress. */
    public function register(): void
    {
        add_shortcode(self::TAG, [$this, 'render']);
    }

    /**
     * Rendert die Top-N-Liste als HTML-Tabelle.
     *
     * @param array<string, mixed>|string $atts
     */
    public function render(array|string $atts = []): string
    {
        if (!is_array($atts)) {
            $atts = [];
        }

        $atts = shortcode_atts([
            'limit' => 10,
            'show_level' => 'yes',
        ], $atts, self::TAG);

        $limit = max(1, min((int) $atts['limit'], 50));
        $showLevel = $atts['show_level'] === 'yes';

        $rows = (new HighscoreRepository())->topN($limit);

        if ($rows === []) {
            return '<div class="jumpnrun-scoreboard jumpnrun-scoreboard-empty">'
                . esc_html__('Noch keine Eintraege. Sei die/der Erste!', 'jumpnrun')
                . '</div>';
        }

        $html = '<table class="jumpnrun-scoreboard"><thead><tr>';
        $html .= '<th class="jnr-col-rank">#</th>';
        $html .= '<th class="jnr-col-name">' . esc_html__('Name', 'jumpnrun') . '</th>';
        $html .= '<th class="jnr-col-score">' . esc_html__('Score', 'jumpnrun') . '</th>';
        if ($showLevel) {
            $html .= '<th class="jnr-col-level">' . esc_html__('Level', 'jumpnrun') . '</th>';
        }
        $html .= '</tr></thead><tbody>';

        $rank = 1;
        foreach ($rows as $row) {
            $html .= '<tr>';
            $html .= '<td class="jnr-col-rank">' . $rank++ . '</td>';
            $html .= '<td class="jnr-col-name">' . esc_html($row['name']) . '</td>';
            $html .= '<td class="jnr-col-score">' . esc_html((string) $row['score']) . '</td>';
            if ($showLevel) {
                $html .= '<td class="jnr-col-level">' . esc_html((string) $row['level']) . '</td>';
            }
            $html .= '</tr>';
        }

        $html .= '</tbody></table>';
        return $html;
    }
}
