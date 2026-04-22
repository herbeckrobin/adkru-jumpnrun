<?php

declare(strict_types=1);

namespace Jumpnrun\Admin;

use WP_Post;
use WP_Query;

if (!class_exists('WP_List_Table')) {
    require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

/**
 * Generische WP_List_Table-Implementation für die drei Asset-CPT-Pools.
 * Konfiguriert via Constructor — keine Subklassen nötig. Liefert die
 * gleichen Features wie die Standard-edit.php-Liste:
 *
 *  - Bulk-Actions (Papierkorb, Endgültig löschen, Wiederherstellen)
 *  - Status-Filter (Veröffentlicht / Papierkorb)
 *  - Suche, Pagination, sortierbare Spalten
 *  - Row-Actions pro Zeile (Bearbeiten, Papierkorb, Wiederherstellen)
 *
 * Wird in AssetsPage::renderXxxTab() instanziiert.
 */
final class AssetListTable extends \WP_List_Table
{
    /**
     * @param array{
     *   post_type: string,
     *   columns: array<string,string>,
     *   sortable: array<string, array{0:string,1:bool}>,
     *   meta_keys: array<string,string>,
     *   default_orderby: string
     * } $cfg
     */
    public function __construct(private readonly array $cfg)
    {
        parent::__construct([
            'singular' => 'jnr_asset',
            'plural' => 'jnr_assets',
            'ajax' => false,
            'screen' => 'jumpnrun_assets',
        ]);
    }

    /** @return array<string,string> */
    public function get_columns(): array
    {
        return $this->cfg['columns'];
    }

    /** @return array<string, array{0:string,1:bool}> */
    public function get_sortable_columns(): array
    {
        return $this->cfg['sortable'];
    }

    /**
     * Status-Filter "Veröffentlicht / Papierkorb" oben über der Tabelle.
     *
     * @return array<string,string>
     */
    protected function get_views(): array
    {
        $base = remove_query_arg(['post_status', 'paged', 's', 'action', 'action2', '_wpnonce']);
        $current = $this->currentStatus();
        $counts = wp_count_posts($this->cfg['post_type']);
        $views = [];
        $statuses = ['publish' => 'Veröffentlicht', 'trash' => 'Papierkorb'];
        foreach ($statuses as $status => $label) {
            $count = (int) ($counts->{$status} ?? 0);
            $url = add_query_arg('post_status', $status, $base);
            $cls = $current === $status ? ' class="current"' : '';
            $views[$status] = sprintf(
                '<a href="%s"%s>%s <span class="count">(%d)</span></a>',
                esc_url($url),
                $cls,
                esc_html($label),
                $count
            );
        }
        return $views;
    }

    public function prepare_items(): void
    {
        $columns = $this->get_columns();
        $hidden = [];
        $sortable = $this->get_sortable_columns();
        $this->_column_headers = [$columns, $hidden, $sortable];

        $this->process_bulk_action();

        $perPage = 20;
        $page = $this->get_pagenum();
        $status = $this->currentStatus();
        $search = isset($_REQUEST['s']) ? sanitize_text_field(wp_unslash((string) $_REQUEST['s'])) : '';
        $orderby = isset($_REQUEST['orderby'])
            ? sanitize_key((string) $_REQUEST['orderby'])
            : $this->cfg['default_orderby'];
        $order = isset($_REQUEST['order']) && strtoupper((string) $_REQUEST['order']) === 'DESC' ? 'DESC' : 'ASC';

        $args = [
            'post_type' => $this->cfg['post_type'],
            'post_status' => $status === 'trash' ? 'trash' : 'publish',
            'posts_per_page' => $perPage,
            'paged' => $page,
            's' => $search,
            'order' => $order,
        ];

        if (isset($this->cfg['meta_keys'][$orderby])) {
            $args['meta_key'] = $this->cfg['meta_keys'][$orderby];
            $args['orderby'] = 'meta_value_num';
        } elseif ($orderby === 'title') {
            $args['orderby'] = 'title';
        } else {
            $args['orderby'] = 'date';
        }

        $query = new WP_Query($args);
        $this->items = $query->posts;

        $this->set_pagination_args([
            'total_items' => (int) $query->found_posts,
            'per_page' => $perPage,
            'total_pages' => (int) $query->max_num_pages,
        ]);
    }

    /**
     * Default-Renderer für Spalten ohne eigenen `column_*`-Method.
     * Wenn die Spalte einen Meta-Key in cfg['meta_keys'] hat, wird der Wert
     * gerendert. Sonst leer.
     *
     * @param WP_Post $item
     */
    public function column_default($item, $column_name): string
    {
        // Sondierfall "Größe" — kombiniert width × height aus zwei Meta-Keys.
        if ($column_name === 'jnr_size') {
            $w = (int) get_post_meta($item->ID, $this->cfg['meta_keys']['jnr_width'] ?? '_jnr_ob_width', true);
            $h = (int) get_post_meta($item->ID, $this->cfg['meta_keys']['jnr_height'] ?? '_jnr_ob_height', true);
            return sprintf('%d × %d', $w, $h);
        }
        $metaKey = $this->cfg['meta_keys'][$column_name] ?? null;
        if ($metaKey !== null) {
            return esc_html((string) (int) get_post_meta($item->ID, $metaKey, true));
        }
        return '';
    }

    /** @param WP_Post $item */
    public function column_cb($item): string
    {
        return sprintf('<input type="checkbox" name="post[]" value="%d">', $item->ID);
    }

    /** @param WP_Post $item */
    public function column_jnr_thumb($item): string
    {
        $url = (string) get_the_post_thumbnail_url($item->ID, 'thumbnail');
        if ($url === '') {
            return '<span style="color:#a00;">kein Bild</span>';
        }
        return sprintf(
            '<img src="%s" alt="" style="width:60px;height:auto;border-radius:4px;display:block;">',
            esc_url($url)
        );
    }

    /** @param WP_Post $item */
    public function column_title($item): string
    {
        $editUrl = (string) get_edit_post_link($item->ID);
        $title = sprintf(
            '<strong><a href="%s">%s</a></strong>',
            esc_url($editUrl),
            esc_html(get_the_title($item))
        );

        $actions = [];
        $status = (string) get_post_status($item->ID);
        if ($status === 'trash') {
            $untrashUrl = wp_nonce_url(
                add_query_arg(['jnr_action' => 'untrash', 'post' => $item->ID]),
                'jumpnrun-asset-' . $item->ID
            );
            $deleteUrl = wp_nonce_url(
                add_query_arg(['jnr_action' => 'delete', 'post' => $item->ID]),
                'jumpnrun-asset-' . $item->ID
            );
            $actions['untrash'] = sprintf('<a href="%s">Wiederherstellen</a>', esc_url($untrashUrl));
            $actions['delete'] = sprintf(
                '<a href="%s" style="color:#a00;" onclick="return confirm(\'Wirklich endgültig löschen?\');">Endgültig löschen</a>',
                esc_url($deleteUrl)
            );
        } else {
            $actions['edit'] = sprintf('<a href="%s">Bearbeiten</a>', esc_url($editUrl));
            $trashUrl = wp_nonce_url(
                add_query_arg(['jnr_action' => 'trash', 'post' => $item->ID]),
                'jumpnrun-asset-' . $item->ID
            );
            $actions['trash'] = sprintf('<a href="%s" style="color:#a00;">Papierkorb</a>', esc_url($trashUrl));
        }

        return $title . $this->row_actions($actions);
    }

    /** @return array<string,string> */
    public function get_bulk_actions(): array
    {
        if ($this->currentStatus() === 'trash') {
            return [
                'untrash' => 'Wiederherstellen',
                'delete' => 'Endgültig löschen',
            ];
        }
        return ['trash' => 'In den Papierkorb'];
    }

    /**
     * Verarbeitet sowohl Bulk-Actions (Form-POST mit name="post[]") als
     * auch Row-Actions (GET-Link mit jnr_action + post + nonce).
     */
    protected function process_bulk_action(): void
    {
        if (!current_user_can('edit_posts')) {
            return;
        }

        // Row-Action via GET-Link?
        $rowAction = isset($_GET['jnr_action']) ? sanitize_key((string) $_GET['jnr_action']) : '';
        $rowPostId = isset($_GET['post']) ? absint($_GET['post']) : 0;
        if ($rowAction !== '' && $rowPostId > 0) {
            check_admin_referer('jumpnrun-asset-' . $rowPostId);
            $this->applyAction($rowAction, [$rowPostId]);
            return;
        }

        // Bulk-Action via Form-POST?
        $bulkAction = $this->current_action();
        if (!is_string($bulkAction) || $bulkAction === '' || $bulkAction === '-1') {
            return;
        }
        check_admin_referer('bulk-' . $this->_args['plural']);
        $ids = isset($_REQUEST['post']) && is_array($_REQUEST['post'])
            ? array_map('absint', $_REQUEST['post'])
            : [];
        $ids = array_values(array_filter($ids));
        if ($ids === []) {
            return;
        }
        $this->applyAction($bulkAction, $ids);
    }

    /** @param list<int> $ids */
    private function applyAction(string $action, array $ids): void
    {
        foreach ($ids as $id) {
            if (get_post_type($id) !== $this->cfg['post_type']) {
                continue;
            }
            if (!current_user_can('delete_post', $id)) {
                continue;
            }
            switch ($action) {
                case 'trash':
                    wp_trash_post($id);
                    break;
                case 'untrash':
                    wp_untrash_post($id);
                    break;
                case 'delete':
                    wp_delete_post($id, true);
                    break;
            }
        }
    }

    private function currentStatus(): string
    {
        $status = isset($_REQUEST['post_status']) ? sanitize_key((string) $_REQUEST['post_status']) : 'publish';
        return $status === 'trash' ? 'trash' : 'publish';
    }
}
