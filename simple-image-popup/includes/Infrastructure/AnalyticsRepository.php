<?php

declare(strict_types=1);

namespace SimpleImagePopup\Infrastructure;

if (! defined('ABSPATH')) {
    exit;
}

/**
 * Stores popup totals and reads the view history from earlier versions.
 */
final class AnalyticsRepository
{
    /**
     * Return the legacy popup views table name.
     */
    private function getLegacyTableName(): string
    {
        global $wpdb;

        return $wpdb->prefix . 'sip_popup_views';
    }

    private function getTotalsTableName(): string
    {
        global $wpdb;

        return $wpdb->prefix . 'sip_popup_view_totals';
    }

    /**
     * Create the analytics tables and confirm they are available.
     */
    public function createTables(): bool
    {
        global $wpdb;

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        $legacy_table    = $this->getLegacyTableName();
        $totals_table    = $this->getTotalsTableName();
        $charset_collate = $wpdb->get_charset_collate();
        $legacy_sql      = "CREATE TABLE {$legacy_table} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            popup_id bigint(20) unsigned NOT NULL,
            object_id bigint(20) unsigned DEFAULT NULL,
            page_url text NOT NULL,
            session_key varchar(191) NOT NULL,
            viewed_at datetime NOT NULL,
            PRIMARY KEY  (id),
            KEY popup_id (popup_id),
            KEY object_id (object_id),
            KEY session_key (session_key),
            KEY viewed_at (viewed_at)
        ) {$charset_collate};";

        $totals_sql = "CREATE TABLE {$totals_table} (
            popup_id bigint(20) unsigned NOT NULL,
            total_views bigint(20) unsigned NOT NULL DEFAULT 0,
            PRIMARY KEY  (popup_id)
        ) {$charset_collate};";

        dbDelta(array($legacy_sql, $totals_sql));

        foreach (array($legacy_table, $totals_table) as $table) {
            if ($wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $wpdb->esc_like($table))) !== $table) {
                return false;
            }
        }

        return true;
    }

    /**
     * Sign the popup ID and site ID with the site's secret, for use on cached pages.
     */
    public static function viewToken(int $popup_id): string
    {
        return hash_hmac('sha256', 'simple-image-popup-view:' . get_current_blog_id() . ':' . $popup_id, wp_salt('nonce'));
    }

    /**
     * Increment a popup total without inserting a separate row for each view.
     *
     * @param int $popup_id Popup ID.
     */
    public function recordView(int $popup_id): bool
    {
        global $wpdb;

        // The table uses a WordPress-validated prefix and a fixed suffix.
        $table = esc_sql($this->getTotalsTableName());
        $saved = $wpdb->query(
            $wpdb->prepare(
                "INSERT INTO {$table} (popup_id, total_views) VALUES (%d, 1)
                ON DUPLICATE KEY UPDATE total_views = total_views + 1",
                $popup_id
            )
        );

        return $saved !== false;
    }

    /**
     * Return the total view count for a popup.
     */
    public function getViewCount(int $popup_id): int
    {
        return $this->getViewCounts(array($popup_id))[$popup_id] ?? 0;
    }

    /**
     * Return total view counts indexed by popup ID.
     *
     * @param int[] $popup_ids Popup IDs.
     * @return array<int, int>
     */
    public function getViewCounts(array $popup_ids): array
    {
        global $wpdb;

        $popup_ids = array_values(array_filter(array_map('intval', $popup_ids)));

        if (empty($popup_ids)) {
            return array();
        }

        $placeholders = implode(',', array_fill(0, count($popup_ids), '%d'));
        $legacy_table = esc_sql($this->getLegacyTableName());
        $totals_table = esc_sql($this->getTotalsTableName());
        $results      = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT popup_id, COUNT(*) AS total_views
                FROM {$legacy_table}
                WHERE popup_id IN ({$placeholders})
                GROUP BY popup_id
                UNION ALL
                SELECT popup_id, total_views
                FROM {$totals_table}
                WHERE popup_id IN ({$placeholders})",
                array_merge($popup_ids, $popup_ids)
            ),
            ARRAY_A
        );

        $counts = array();

        if (is_array($results)) {
            foreach ($results as $row) {
                $id = (int) $row['popup_id'];
                $counts[$id] = ($counts[$id] ?? 0) + (int) $row['total_views'];
            }
        }

        return $counts;
    }
}
