<?php

declare(strict_types=1);

namespace Jumpnrun\Elementor;

use Elementor\Controls_Manager;
use Elementor\Widget_Base;
use Jumpnrun\Embed\GameAttributes;
use Jumpnrun\Embed\GameRenderer;

/**
 * Natives Elementor-Widget für das Spiel.
 *
 * Adapter über dem GameRenderer, kein eigener Render-Pfad. Die Controls
 * entstehen aus GameAttributes, damit Shortcode, Block und Widget dieselben
 * Werte kennen und ein neues Attribut nur an einer Stelle gepflegt wird.
 *
 * Die Datei wird ausschließlich über den Hook `elementor/widgets/register`
 * erreicht. Ohne aktives Elementor lädt der Autoloader sie nie, deshalb ist
 * das `extends Widget_Base` hier gefahrlos.
 */
final class GameWidget extends Widget_Base
{
    public const WIDGET_NAME = 'jumpnrun_game';

    public function get_name(): string
    {
        return self::WIDGET_NAME;
    }

    public function get_title(): string
    {
        return __('Jump and Run', 'jumpnrun');
    }

    public function get_icon(): string
    {
        return 'eicon-play';
    }

    /** @return string[] */
    public function get_categories(): array
    {
        return [ElementorIntegration::CATEGORY];
    }

    /** @return string[] */
    public function get_keywords(): array
    {
        return ['jump', 'run', 'spiel', 'game', 'canvas', 'highscore'];
    }

    /**
     * Baut die Panel-Controls aus dem Attribut-Schema.
     *
     * Alle Felder bleiben absichtlich leer vorbelegt: ein leeres Feld heißt
     * "Wert aus den Plugin-Einstellungen nehmen". Das Placeholder-Attribut
     * macht das im Panel sichtbar.
     */
    protected function register_controls(): void
    {
        $this->start_controls_section(
            'section_game',
            [
                'label' => __('Spiel', 'jumpnrun'),
                'tab' => Controls_Manager::TAB_CONTENT,
            ]
        );

        foreach (GameAttributes::schema() as $key => $definition) {
            $this->add_control(
                $key,
                [
                    'label' => $definition['label'],
                    'type' => $definition['type'] === 'number'
                        ? Controls_Manager::NUMBER
                        : Controls_Manager::TEXT,
                    'description' => $definition['description'],
                    'default' => '',
                    'placeholder' => __('Aus den Einstellungen', 'jumpnrun'),
                    'label_block' => true,
                ]
            );
        }

        $this->end_controls_section();
    }

    /**
     * Im Editor bewusst nur ein Platzhalter.
     *
     * Elementor rendert die Vorschau in einem iframe, das die echte Seite
     * lädt. Das Spiel würde dort tatsächlich starten, Tastatureingaben
     * abfangen und im Hintergrund weiterlaufen, während jemand die Seite
     * bearbeitet.
     */
    protected function render(): void
    {
        $renderer = new GameRenderer();

        $settings = $this->get_settings_for_display();
        if (!is_array($settings)) {
            $settings = [];
        }

        if (self::isEditMode()) {
            // Dieselben Settings wie im Frontend, damit der Platzhalter
            // exakt den Platz einnimmt, den das Spiel später braucht.
            // Markup stammt aus dem Renderer und ist dort bereits escaped.
            echo $renderer->renderEditorPlaceholder($settings); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
            return;
        }

        // Markup stammt aus dem Renderer, die Config ist dort per
        // wp_json_encode mit JSON_HEX_TAG kodiert.
        echo $renderer->render($settings); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
    }

    /** Läuft die Ausgabe gerade in der Editor-Vorschau? */
    private static function isEditMode(): bool
    {
        if (!class_exists('\Elementor\Plugin')) {
            return false;
        }

        $elementor = \Elementor\Plugin::$instance;

        return isset($elementor->editor) && $elementor->editor->is_edit_mode();
    }
}
