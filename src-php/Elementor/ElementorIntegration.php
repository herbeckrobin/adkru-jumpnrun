<?php

declare(strict_types=1);

namespace Jumpnrun\Elementor;

/**
 * Hängt das Widget in Elementor ein.
 *
 * Die Hooks werden immer registriert, aber sie feuern nur, wenn Elementor
 * aktiv ist. Ein Guard auf `did_action('elementor/loaded')` wäre hier
 * timing-abhängig, weil Elementor selbst erst auf `plugins_loaded` bootet und
 * das Plugin je nach Ladereihenfolge früher dran sein kann. Die Widget-Klasse
 * erbt von `\Elementor\Widget_Base` und wird vom Autoloader erst geladen, wenn
 * der Hook feuert. Ohne Elementor passiert also schlicht nichts.
 */
final class ElementorIntegration
{
    /** Eigene Panel-Kategorie, damit das Widget nicht zwischen den Core-Widgets untergeht. */
    public const CATEGORY = 'jumpnrun';

    public function register(): void
    {
        add_action('elementor/elements/categories_registered', [$this, 'registerCategory']);
        add_action('elementor/widgets/register', [$this, 'registerWidgets']);
    }

    /** @param \Elementor\Elements_Manager $manager */
    public function registerCategory($manager): void
    {
        $manager->add_category(
            self::CATEGORY,
            [
                'title' => __('Jump and Run', 'jumpnrun'),
                'icon' => 'eicon-play',
            ]
        );
    }

    /** @param \Elementor\Widgets_Manager $manager */
    public function registerWidgets($manager): void
    {
        $manager->register(new GameWidget());
    }
}
