<?php
/**
 * @var list<array{id:int,name:string,score:int,level:int}> $rows
 * @var string|null $notice
 * @var string $deleteNonce
 * @var string $bulkNonce
 */

declare(strict_types=1);

defined('ABSPATH') || exit;
?>
<div class="wrap jumpnrun-scoreboard">
    <h1><?php echo esc_html__('Jump-n-Run · Highscores', 'jumpnrun'); ?></h1>

    <?php if ($notice !== null) : ?>
        <div class="notice notice-success is-dismissible"><p><?php echo esc_html($notice); ?></p></div>
    <?php endif; ?>

    <?php if ($rows === []) : ?>
        <p><?php echo esc_html__('Noch keine Eintraege.', 'jumpnrun'); ?></p>
    <?php else : ?>
        <form method="post">
            <?php wp_nonce_field('jumpnrun_bulk_delete', 'jumpnrun_bulk_delete_nonce'); ?>

            <div class="tablenav top">
                <div class="alignleft actions">
                    <button type="submit" class="button action">
                        <?php echo esc_html__('Ausgewaehlte loeschen', 'jumpnrun'); ?>
                    </button>
                </div>
            </div>

            <table class="wp-list-table widefat fixed striped">
                <thead>
                <tr>
                    <td class="check-column"><input type="checkbox" onclick="this.closest('table').querySelectorAll('tbody input[type=checkbox]').forEach(c => c.checked = this.checked);"></td>
                    <th style="width:60px;"><?php echo esc_html__('Rang', 'jumpnrun'); ?></th>
                    <th><?php echo esc_html__('Name', 'jumpnrun'); ?></th>
                    <th style="width:120px;"><?php echo esc_html__('Score', 'jumpnrun'); ?></th>
                    <th style="width:80px;"><?php echo esc_html__('Level', 'jumpnrun'); ?></th>
                    <th style="width:140px;"><?php echo esc_html__('Aktion', 'jumpnrun'); ?></th>
                </tr>
                </thead>
                <tbody>
                <?php $rank = 1; foreach ($rows as $row) : ?>
                    <?php
                    $deleteUrl = wp_nonce_url(
                        add_query_arg(['action' => 'delete', 'id' => $row['id']]),
                        'jumpnrun_delete_score'
                    );
                    ?>
                    <tr>
                        <th class="check-column">
                            <input type="checkbox" name="ids[]" value="<?php echo esc_attr((string) $row['id']); ?>">
                        </th>
                        <td><?php echo esc_html((string) $rank++); ?></td>
                        <td><strong><?php echo esc_html($row['name']); ?></strong></td>
                        <td><?php echo esc_html((string) $row['score']); ?></td>
                        <td><?php echo esc_html((string) $row['level']); ?></td>
                        <td>
                            <a href="<?php echo esc_url($deleteUrl); ?>"
                               class="button button-small"
                               onclick="return confirm('<?php echo esc_js(__('Eintrag loeschen?', 'jumpnrun')); ?>');">
                                <?php echo esc_html__('Loeschen', 'jumpnrun'); ?>
                            </a>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </form>
    <?php endif; ?>
</div>
