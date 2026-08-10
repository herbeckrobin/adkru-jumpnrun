<?php

declare(strict_types=1);

namespace Jumpnrun\Block;

use Jumpnrun\Embed\GameAttributes;
use Jumpnrun\Embed\GameRenderer;

/**
 * Gutenberg-Block als dritter Einbindungsweg.
 *
 * Dynamischer Block: das Markup entsteht bei jedem Aufruf im PHP, nicht beim
 * Speichern im Editor. Damit greifen Änderungen an Einstellungen und Assets
 * sofort auf allen Seiten, ohne dass jemand jeden Block neu speichern muss.
 *
 * Der Editor-Teil kommt ohne Build aus: er nutzt die von WordPress
 * ausgelieferten `wp.*`-Globals. Die Attribut-Definitionen kommen aus
 * GameAttributes und werden dem Editor als Inline-Script gespiegelt, damit
 * Schema und Controls nicht auseinanderlaufen.
 */
final class GameBlock
{
    public const BLOCK_NAME = 'jumpnrun/game';

    private const EDITOR_HANDLE = 'jumpnrun-block-editor';

    private GameRenderer $renderer;

    public function __construct(?GameRenderer $renderer = null)
    {
        $this->renderer = $renderer ?? new GameRenderer();
    }

    public function register(): void
    {
        $this->registerEditorScript();

        register_block_type(
            JUMPNRUN_DIR . 'src-php/Block',
            [
                'attributes' => self::blockAttributes(),
                'render_callback' => [$this, 'render'],
            ]
        );
    }

    /**
     * Block-Attribute aus dem gemeinsamen Schema.
     *
     * Bewusst alle als String mit leerem Default: ein leeres Feld heißt "Wert
     * aus den Plugin-Einstellungen". Ein number-Attribut mit Default 0 würde
     * die Einstellung überschreiben, sobald der Block eingefügt wird.
     *
     * @return array<string, array{type:string, default:string}>
     */
    private static function blockAttributes(): array
    {
        $attributes = [];

        foreach (array_keys(GameAttributes::schema()) as $key) {
            $attributes[$key] = ['type' => 'string', 'default' => ''];
        }

        return $attributes;
    }

    /** Registriert das buildlose Editor-Script samt gespiegeltem Schema. */
    private function registerEditorScript(): void
    {
        $path = JUMPNRUN_DIR . 'assets/block/editor.js';

        wp_register_script(
            self::EDITOR_HANDLE,
            JUMPNRUN_URL . 'assets/block/editor.js',
            ['wp-blocks', 'wp-element', 'wp-block-editor', 'wp-components', 'wp-i18n'],
            file_exists($path) ? (string) filemtime($path) : JUMPNRUN_VERSION,
            true
        );

        // PHP bleibt Single Source, der Editor bekommt nur eine Kopie.
        $schema = [];
        foreach (GameAttributes::schema() as $key => $definition) {
            $schema[$key] = [
                'type' => $definition['type'],
                'label' => $definition['label'],
                'description' => $definition['description'],
            ];
        }

        wp_add_inline_script(
            self::EDITOR_HANDLE,
            'window.JumpnrunBlockSchema = ' . wp_json_encode($schema) . ';',
            'before'
        );
    }

    /**
     * @param array<string, mixed> $attributes
     */
    public function render(array $attributes = []): string
    {
        // Im Block-Editor rendert WordPress dynamische Blöcke über die
        // REST-Route /wp/v2/block-renderer. Dort soll das Spiel nicht starten,
        // sonst läuft es im Editor mit und fängt Tastatureingaben ab.
        if (self::isEditorPreview()) {
            return $this->renderer->renderEditorPlaceholder($attributes);
        }

        return $this->renderer->render($attributes);
    }

    /** Läuft der Aufruf gerade über den Block-Renderer des Editors? */
    private static function isEditorPreview(): bool
    {
        if (!defined('REST_REQUEST') || !REST_REQUEST) {
            return false;
        }

        $route = isset($GLOBALS['wp']->query_vars['rest_route'])
            ? (string) $GLOBALS['wp']->query_vars['rest_route']
            : '';

        if (str_contains($route, '/block-renderer/')) {
            return true;
        }

        // Fallback: die Route steht je nach Permalink-Setup nur in der URL.
        $requestUri = isset($_SERVER['REQUEST_URI']) ? (string) $_SERVER['REQUEST_URI'] : '';

        return str_contains($requestUri, '/block-renderer/');
    }
}
