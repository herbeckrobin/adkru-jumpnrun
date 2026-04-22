<?php

declare(strict_types=1);

namespace Jumpnrun\Admin;

use Jumpnrun\Config\ConfigSchema;
use Jumpnrun\Config\ConfigService;

/**
 * Schema-driven Settings-Page: rendert alle im ConfigSchema deklarierten
 * Felder gruppiert nach Section, inkl. Range-Clamp beim Speichern. Neue
 * Config-Felder erscheinen automatisch hier — keine Extra-Registrierung.
 */
final class SettingsPage
{
    private const OPTION_GROUP = 'jumpnrun_settings_group';

    public function registerSettings(): void
    {
        register_setting(
            self::OPTION_GROUP,
            ConfigService::OPTION_KEY,
            [
                'type' => 'array',
                'sanitize_callback' => [$this, 'sanitize'],
                'default' => [],
            ]
        );
    }

    /** @param mixed $input */
    public function sanitize($input): array
    {
        if (!is_array($input)) {
            return [];
        }

        $out = [];
        foreach (ConfigSchema::fieldMap() as $key => $spec) {
            if (!array_key_exists($key, $input)) {
                continue;
            }
            $out[$key] = ConfigSchema::sanitizeValue($input[$key], $spec);
        }

        // configRawJson: muss valides JSON sein, sonst verwerfen + Notice.
        if (isset($out['configRawJson']) && $out['configRawJson'] !== '') {
            $decoded = json_decode($out['configRawJson'], true);
            if (!is_array($decoded)) {
                add_settings_error(
                    ConfigService::OPTION_KEY,
                    'invalid_raw_json',
                    'Das Override-JSON ist ungueltig und wurde verworfen.'
                );
                $out['configRawJson'] = '';
            }
        }

        ConfigService::clearCache();
        return $out;
    }

    public function render(): void
    {
        if (!current_user_can('manage_options')) {
            return;
        }

        $current = get_option(ConfigService::OPTION_KEY, []);
        if (!is_array($current)) {
            $current = [];
        }

        $view = JUMPNRUN_DIR . 'views/admin/settings.php';
        if (file_exists($view)) {
            $sections = ConfigSchema::all();
            // Sprites sind in der Spiel-Assets-Seite gebuendelt, Anti-Cheat ist
            // ein Backend-Detail das den Kunden nicht im Admin-Tab interessieren
            // soll. Schema selbst bleibt unangetastet — Sanitize + Defaults greifen.
            unset($sections['sprites'], $sections['antiCheat']);
            $optionKey = ConfigService::OPTION_KEY;
            $optionGroup = self::OPTION_GROUP;
            require $view;
        }
    }

    /**
     * Rendert ein einzelnes Input-Element passend zum Feld-Typ.
     * Wird aus dem View-Template aufgerufen.
     *
     * @param array<string, mixed> $spec
     */
    public static function renderField(string $key, array $spec, array $current): void
    {
        $optionKey = ConfigService::OPTION_KEY;
        $name = sprintf('%s[%s]', $optionKey, $key);
        $default = $spec['default'] ?? '';
        $value = array_key_exists($key, $current) ? $current[$key] : $default;
        $type = (string) ($spec['type'] ?? 'string');
        $id = 'jnr_' . $key;

        printf('<label for="%s" class="jnr-label">%s</label>', esc_attr($id), esc_html((string) $spec['label']));

        switch ($type) {
            case 'int':
            case 'float':
                printf(
                    '<input type="number" id="%s" name="%s" value="%s" min="%s" max="%s" step="%s" class="small-text">',
                    esc_attr($id),
                    esc_attr($name),
                    esc_attr((string) $value),
                    esc_attr((string) ($spec['min'] ?? '')),
                    esc_attr((string) ($spec['max'] ?? '')),
                    esc_attr((string) ($spec['step'] ?? ($type === 'int' ? 1 : 0.1))),
                );
                break;

            case 'bool':
                printf(
                    '<input type="hidden" name="%s" value="0">'
                    . '<label class="jnr-checkbox"><input type="checkbox" id="%s" name="%s" value="1"%s> Aktiv</label>',
                    esc_attr($name),
                    esc_attr($id),
                    esc_attr($name),
                    checked((bool) $value, true, false)
                );
                break;

            case 'url':
                printf(
                    '<input type="url" id="%s" name="%s" value="%s" class="regular-text">',
                    esc_attr($id),
                    esc_attr($name),
                    esc_attr((string) $value),
                );
                break;

            case 'textarea':
                printf(
                    '<textarea id="%s" name="%s" rows="6" class="large-text code">%s</textarea>',
                    esc_attr($id),
                    esc_attr($name),
                    esc_textarea((string) $value),
                );
                break;

            case 'attachment':
                $attachmentId = (int) $value;
                $previewUrl = $attachmentId > 0 ? (string) wp_get_attachment_image_url($attachmentId, 'thumbnail') : '';
                printf(
                    '<div class="jnr-attachment-picker" data-jnr-picker="1" style="display:flex;align-items:center;gap:10px;">'
                    . '<img class="jnr-attachment-preview" src="%4$s" alt="" style="%5$s">'
                    . '<input type="hidden" id="%1$s" name="%2$s" value="%3$s" data-jnr-picker-input>'
                    . '<button type="button" class="button" data-jnr-picker-select>Bild wählen</button>'
                    . '<button type="submit" class="button" name="jnr_clear[%6$s]" value="1" data-jnr-picker-clear>Entfernen</button>'
                    . '</div>',
                    esc_attr($id),
                    esc_attr($name),
                    esc_attr((string) $attachmentId),
                    esc_url($previewUrl),
                    $previewUrl !== '' ? 'max-width:80px;height:auto;border-radius:4px;display:block;' : 'display:none;',
                    esc_attr($key)
                );
                break;

            default:
                printf(
                    '<input type="text" id="%s" name="%s" value="%s" class="regular-text" maxlength="%d">',
                    esc_attr($id),
                    esc_attr($name),
                    esc_attr((string) $value),
                    (int) ($spec['maxlen'] ?? 255),
                );
        }

        if (!empty($spec['help'])) {
            printf('<p class="description">%s</p>', esc_html((string) $spec['help']));
        }
    }
}
