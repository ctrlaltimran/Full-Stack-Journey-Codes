<?php
if (!defined('ABSPATH')) { exit; }

class Rill_Image_Optimizer {
    private static $instance = null;

    const OPT_KEY   = 'rill_imgopt_options';
    const META_ORIG = '_rill_imgopt_original_bytes';
    const META_OPT  = '_rill_imgopt_optimized_bytes';
    const META_LAST = '_rill_imgopt_last_optimized';

    public static function instance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public static function activate() {
        $defaults = [
            // Strong defaults but still safe
            'quality' => 88,                 // JPEG/WebP quality (75-95)
            'strip_metadata' => 1,           // remove EXIF/metadata where possible
            'backup_originals' => 1,         // safer: keeps backups
            'max_batch' => 10,               // bulk batch size
            'optimize_sizes' => 1,           // optimize thumbnails/intermediate sizes
            'optimize_original' => 1,        // optimize original upload file
        ];

        $existing = get_option(self::OPT_KEY, []);
        if (!is_array($existing)) $existing = [];
        update_option(self::OPT_KEY, array_merge($defaults, $existing));
    }

    private function __construct() {
        add_action('admin_menu', [$this, 'admin_menu']);
        add_action('admin_enqueue_scripts', [$this, 'admin_assets']);

        // Optimize future uploads automatically
        add_filter('wp_generate_attachment_metadata', [$this, 'on_generate_metadata'], 20, 2);

        // Bulk / reset actions
        add_action('wp_ajax_rill_imgopt_bulk_step', [$this, 'ajax_bulk_step']);
        add_action('wp_ajax_rill_imgopt_reset_stats', [$this, 'ajax_reset_stats']);
    }

    public function get_options() {
        $opt = get_option(self::OPT_KEY, []);
        if (!is_array($opt)) { $opt = []; }

        $defaults = [
            'quality' => 88,
            'strip_metadata' => 1,
            'backup_originals' => 1,
            'max_batch' => 10,
            'optimize_sizes' => 1,
            'optimize_original' => 1,
        ];

        $opt = array_merge($defaults, $opt);

        $opt['quality'] = max(75, min(95, intval($opt['quality'])));
        $opt['max_batch'] = max(1, min(50, intval($opt['max_batch'])));
        $opt['strip_metadata'] = !empty($opt['strip_metadata']) ? 1 : 0;
        $opt['backup_originals'] = !empty($opt['backup_originals']) ? 1 : 0;
        $opt['optimize_sizes'] = !empty($opt['optimize_sizes']) ? 1 : 0;
        $opt['optimize_original'] = !empty($opt['optimize_original']) ? 1 : 0;

        return $opt;
    }

    public function admin_menu() {
        add_menu_page(
            'Image Optimizer',
            'Image Optimizer',
            'manage_options',
            'rill-image-optimizer',
            [$this, 'render_admin_page'],
            'dashicons-images-alt2',
            81
        );
    }

    public function admin_assets($hook) {
        if ($hook !== 'toplevel_page_rill-image-optimizer') return;

        wp_enqueue_style('rill-imgopt-admin', RILL_IMGOPT_URL . 'assets/admin.css', [], RILL_IMGOPT_VERSION);
        wp_enqueue_script('rill-imgopt-admin', RILL_IMGOPT_URL . 'assets/admin.js', ['jquery'], RILL_IMGOPT_VERSION, true);

        wp_localize_script('rill-imgopt-admin', 'RillImgOpt', [
            'ajaxurl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('rill_imgopt_nonce'),
        ]);
    }

    public function render_admin_page() {
        if (!current_user_can('manage_options')) return;

        $opt = $this->get_options();

        // Save settings
        if (isset($_POST['rill_imgopt_save']) && check_admin_referer('rill_imgopt_save_settings')) {
            $new = [
                'quality' => isset($_POST['quality']) ? intval($_POST['quality']) : $opt['quality'],
                'strip_metadata' => !empty($_POST['strip_metadata']) ? 1 : 0,
                'backup_originals' => !empty($_POST['backup_originals']) ? 1 : 0,
                'max_batch' => isset($_POST['max_batch']) ? intval($_POST['max_batch']) : $opt['max_batch'],
                'optimize_sizes' => !empty($_POST['optimize_sizes']) ? 1 : 0,
                'optimize_original' => !empty($_POST['optimize_original']) ? 1 : 0,
            ];
            update_option(self::OPT_KEY, array_merge($opt, $new));
            $opt = $this->get_options();
            echo '<div class="notice notice-success is-dismissible"><p>Settings saved.</p></div>';
        }

        $stats = $this->get_stats();
        ?>
        <div class="wrap rill-imgopt-wrap">
            <h1>Rill Image Optimizer</h1>

            <div class="rill-grid">
                <div class="rill-card">
                    <h2>Overview</h2>

                    <div class="rill-kpis">
                        <div class="rill-kpi">
                            <div class="rill-kpi-label">Images optimized</div>
                            <div class="rill-kpi-value"><?php echo esc_html(number_format_i18n($stats['count'])); ?></div>
                        </div>
                        <div class="rill-kpi">
                            <div class="rill-kpi-label">Original size</div>
                            <div class="rill-kpi-value"><?php echo esc_html($this->format_bytes($stats['orig'])); ?></div>
                        </div>
                        <div class="rill-kpi">
                            <div class="rill-kpi-label">Optimized size</div>
                            <div class="rill-kpi-value"><?php echo esc_html($this->format_bytes($stats['opt'])); ?></div>
                        </div>
                        <div class="rill-kpi">
                            <div class="rill-kpi-label">Saved</div>
                            <div class="rill-kpi-value"><?php echo esc_html($this->format_bytes(max(0, $stats['orig'] - $stats['opt']))); ?></div>
                        </div>
                    </div>

                    <div class="rill-actions">
                        <button class="button button-primary" id="rill-imgopt-start">Optimize existing media</button>
                        <button class="button" id="rill-imgopt-reset">Reset stats (does not restore files)</button>
                    </div>

                    <div class="rill-progress" id="rill-imgopt-progress" style="display:none;">
                        <div class="rill-progress-bar"><span id="rill-imgopt-progress-fill" style="width:0%"></span></div>
                        <div class="rill-progress-text" id="rill-imgopt-progress-text">Preparing...</div>
                    </div>

                    <p class="description">
                        Safety rules:
                        <strong>1)</strong> Optimizes to a temp file,
                        <strong>2)</strong> Replaces only if the new file is smaller,
                        <strong>3)</strong> Optional backups in <code>wp-content/uploads/rill-imgopt-backup/</code>.
                    </p>
                </div>

                <div class="rill-card">
                    <h2>Settings</h2>
                    <form method="post">
                        <?php wp_nonce_field('rill_imgopt_save_settings'); ?>

                        <table class="form-table" role="presentation">
                            <tr>
                                <th scope="row"><label for="quality">JPEG/WebP quality</label></th>
                                <td>
                                    <input type="number" id="quality" name="quality" min="75" max="95" value="<?php echo esc_attr($opt['quality']); ?>" />
                                    <p class="description">88 saves more space. For maximum safety, use 92 to 95.</p>
                                </td>
                            </tr>

                            <tr>
                                <th scope="row">Optimize generated sizes</th>
                                <td>
                                    <label><input type="checkbox" name="optimize_sizes" <?php checked($opt['optimize_sizes'], 1); ?> /> Optimize thumbnails and intermediate sizes</label>
                                </td>
                            </tr>

                            <tr>
                                <th scope="row">Optimize original upload</th>
                                <td>
                                    <label><input type="checkbox" name="optimize_original" <?php checked($opt['optimize_original'], 1); ?> /> Optimize the original uploaded file</label>
                                </td>
                            </tr>

                            <tr>
                                <th scope="row">Strip metadata</th>
                                <td>
                                    <label><input type="checkbox" name="strip_metadata" <?php checked($opt['strip_metadata'], 1); ?> /> Remove EXIF/metadata when possible</label>
                                    <p class="description">May remove camera info and GPS data, usually safe for websites.</p>
                                </td>
                            </tr>

                            <tr>
                                <th scope="row">Backup originals</th>
                                <td>
                                    <label><input type="checkbox" name="backup_originals" <?php checked($opt['backup_originals'], 1); ?> /> Keep a backup copy (recommended)</label>
                                </td>
                            </tr>

                            <tr>
                                <th scope="row"><label for="max_batch">Bulk batch size</label></th>
                                <td>
                                    <input type="number" id="max_batch" name="max_batch" min="1" max="50" value="<?php echo esc_attr($opt['max_batch']); ?>" />
                                    <p class="description">If your hosting is weak, use 5 to 10.</p>
                                </td>
                            </tr>
                        </table>

                        <p>
                            <button type="submit" name="rill_imgopt_save" class="button button-primary">Save settings</button>
                        </p>
                    </form>
                </div>

                <div class="rill-card rill-span-2">
                    <h2>Recent optimizations</h2>
                    <?php echo $this->render_recent_table(); ?>
                </div>
            </div>
        </div>
        <?php
    }

    private function render_recent_table() {
        global $wpdb;

        $sql = $wpdb->prepare("
            SELECT p.ID, p.post_title,
                m1.meta_value AS orig,
                m2.meta_value AS opt,
                m3.meta_value AS last_opt
            FROM {$wpdb->posts} p
            LEFT JOIN {$wpdb->postmeta} m1 ON (m1.post_id = p.ID AND m1.meta_key = %s)
            LEFT JOIN {$wpdb->postmeta} m2 ON (m2.post_id = p.ID AND m2.meta_key = %s)
            LEFT JOIN {$wpdb->postmeta} m3 ON (m3.post_id = p.ID AND m3.meta_key = %s)
            WHERE p.post_type = 'attachment'
              AND p.post_mime_type LIKE 'image/%%'
              AND m3.meta_value IS NOT NULL
            ORDER BY CAST(m3.meta_value AS UNSIGNED) DESC
            LIMIT 20
        ", self::META_ORIG, self::META_OPT, self::META_LAST);

        $rows = $wpdb->get_results($sql);
        if (empty($rows)) {
            return '<p class="description">No optimizations recorded yet. Upload an image or run bulk optimization.</p>';
        }

        $out = '<table class="widefat striped"><thead><tr>
            <th>Media</th><th>Original</th><th>Optimized</th><th>Saved</th><th>Last optimized</th>
        </tr></thead><tbody>';

        foreach ($rows as $r) {
            $orig = intval($r->orig);
            $opt  = intval($r->opt);
            $saved = max(0, $orig - $opt);
            $when = !empty($r->last_opt)
                ? date_i18n(get_option('date_format') . ' ' . get_option('time_format'), intval($r->last_opt))
                : '-';

            $edit_link = get_edit_post_link($r->ID);
            $title = $r->post_title ? $r->post_title : '(no title)';

            $out .= '<tr>';
            $out .= '<td><a href="'. esc_url($edit_link) .'">'. esc_html($title) .'</a> (ID '. intval($r->ID) .')</td>';
            $out .= '<td>'. esc_html($this->format_bytes($orig)) .'</td>';
            $out .= '<td>'. esc_html($this->format_bytes($opt)) .'</td>';
            $out .= '<td><strong>'. esc_html($this->format_bytes($saved)) .'</strong></td>';
            $out .= '<td>'. esc_html($when) .'</td>';
            $out .= '</tr>';
        }

        $out .= '</tbody></table>';
        return $out;
    }

    public function on_generate_metadata($metadata, $attachment_id) {
        $mime = get_post_mime_type($attachment_id);
        if (!$mime || strpos($mime, 'image/') !== 0) return $metadata;

        $opt = $this->get_options();
        $file = get_attached_file($attachment_id);
        if (!$file || !file_exists($file)) return $metadata;

        $paths = [];
        if ($opt['optimize_original']) $paths[] = $file;

        if ($opt['optimize_sizes'] && !empty($metadata['sizes']) && is_array($metadata['sizes'])) {
            $upload_dir = wp_upload_dir();
            $base_dir = trailingslashit($upload_dir['basedir']);

            $rel = isset($metadata['file']) ? $metadata['file'] : '';
            $subdir = '';
            if ($rel) {
                $subdir = trailingslashit(dirname($rel));
                if ($subdir === './') $subdir = '';
            }

            foreach ($metadata['sizes'] as $size) {
                if (!empty($size['file'])) {
                    $paths[] = $base_dir . $subdir . $size['file'];
                }
            }
        }

        $before_total = 0;
        $after_total = 0;

        foreach (array_unique($paths) as $p) {
            if (!file_exists($p) || !is_file($p)) continue;

            $before = filesize($p);
            $after  = $this->optimize_file($p);

            if ($after === false) $after = $before;

            $before_total += $before;
            $after_total  += $after;
        }

        // Track stats per attachment
        if ($opt['optimize_original']) {
            $existing_orig = intval(get_post_meta($attachment_id, self::META_ORIG, true));
            if ($existing_orig <= 0) update_post_meta($attachment_id, self::META_ORIG, $before_total);
            update_post_meta($attachment_id, self::META_OPT, $after_total);
            update_post_meta($attachment_id, self::META_LAST, time());
        }

        return $metadata;
    }

    private function is_supported_image($path) {
        $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        return in_array($ext, ['jpg','jpeg','png','webp'], true);
    }

    private function maybe_backup($path) {
        $opt = $this->get_options();
        if (empty($opt['backup_originals'])) return true;

        $upload = wp_upload_dir();
        $basedir = trailingslashit($upload['basedir']);
        $backup_root = $basedir . 'rill-imgopt-backup/';

        if (!wp_mkdir_p($backup_root)) return false;

        $rel = str_replace($basedir, '', $path);
        $dest = $backup_root . $rel;
        $dest_dir = dirname($dest);

        if (!wp_mkdir_p($dest_dir)) return false;
        if (file_exists($dest)) return true;

        return copy($path, $dest);
    }

    /**
     * Safe optimization:
     * - writes to temp file
     * - replaces only if smaller
     * - optional backups
     */
    public function optimize_file($path) {
        if (!$this->is_supported_image($path)) return false;

        $before = @filesize($path);
        if ($before === false || $before <= 0) return false;

        if (!$this->maybe_backup($path)) return false;

        $opt = $this->get_options();
        $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));

        // Prefer WP editor (Imagick if available, else GD)
        $editor = wp_get_image_editor($path);
        if (is_wp_error($editor)) {
            if (class_exists('Imagick')) {
                return $this->optimize_with_imagick($path, $ext, $opt, $before);
            }
            return false;
        }

        // Quality for JPEG/WebP
        if (in_array($ext, ['jpg','jpeg','webp'], true)) {
            $editor->set_quality($opt['quality']);
        }

        $tmp = $path . '.rilltmp';
        $saved = $editor->save($tmp);

        if (is_wp_error($saved) || empty($saved['path']) || !file_exists($saved['path'])) {
            @unlink($tmp);
            return false;
        }

        // Stronger PNG lossless pass (Imagick)
        if ($ext === 'png' && class_exists('Imagick')) {
            $this->png_lossless_imagick($saved['path'], !empty($opt['strip_metadata']));
        }

        // Strip metadata if possible (Imagick)
        if (!empty($opt['strip_metadata']) && class_exists('Imagick')) {
            $this->strip_metadata_imagick($saved['path']);
        }

        $after = @filesize($saved['path']);
        if ($after === false || $after <= 0) {
            @unlink($saved['path']);
            return false;
        }

        // Keep original if larger
        if ($after > $before) {
            @unlink($saved['path']);
            return false;
        }

        // Replace original
        $ok = @rename($saved['path'], $path);
        if (!$ok) {
            @copy($saved['path'], $path);
            @unlink($saved['path']);
        }

        clearstatcache(true, $path);
        $final = @filesize($path);
        if ($final === false) return false;

        return $final;
    }

    private function strip_metadata_imagick($path) {
        try {
            $img = new Imagick($path);
            if (method_exists($img, 'autoOrient')) $img->autoOrient();
            $img->stripImage();
            $img->writeImage($path);
            $img->clear();
            $img->destroy();
        } catch (\Exception $e) {
            // ignore
        }
    }

    private function png_lossless_imagick($path, $strip) {
        try {
            $img = new Imagick($path);
            if ($strip) {
                if (method_exists($img, 'autoOrient')) $img->autoOrient();
                $img->stripImage();
            }
            $img->setImageFormat('png');
            $img->setOption('png:compression-level', '9');
            $img->setOption('png:compression-filter', '5');
            $img->setOption('png:compression-strategy', '1');
            $img->setImageCompression(Imagick::COMPRESSION_ZIP);
            $img->setImageCompressionQuality(9);
            $img->writeImage($path);
            $img->clear();
            $img->destroy();
        } catch (\Exception $e) {
            // ignore
        }
    }

    private function optimize_with_imagick($path, $ext, $opt, $before) {
        try {
            $img = new Imagick($path);

            if (!empty($opt['strip_metadata'])) {
                if (method_exists($img, 'autoOrient')) $img->autoOrient();
                $img->stripImage();
            }

            $tmp = $path . '.rilltmp';

            if ($ext === 'png') {
                $img->setImageFormat('png');
                $img->setOption('png:compression-level', '9');
                $img->setImageCompression(Imagick::COMPRESSION_ZIP);
                $img->setImageCompressionQuality(9);
            } else if (in_array($ext, ['jpg','jpeg','webp'], true)) {
                $img->setImageCompressionQuality(intval($opt['quality']));
            }

            $img->writeImage($tmp);
            $img->clear();
            $img->destroy();

            $after = @filesize($tmp);
            if ($after === false || $after <= 0) { @unlink($tmp); return false; }
            if ($after > $before) { @unlink($tmp); return false; }

            @rename($tmp, $path);
            clearstatcache(true, $path);
            return @filesize($path);
        } catch (\Exception $e) {
            return false;
        }
    }

    private function get_stats() {
        global $wpdb;

        $orig = $wpdb->get_var($wpdb->prepare("
            SELECT SUM(CAST(meta_value AS UNSIGNED))
            FROM {$wpdb->postmeta}
            WHERE meta_key = %s
        ", self::META_ORIG));

        $opt = $wpdb->get_var($wpdb->prepare("
            SELECT SUM(CAST(meta_value AS UNSIGNED))
            FROM {$wpdb->postmeta}
            WHERE meta_key = %s
        ", self::META_OPT));

        $count = $wpdb->get_var($wpdb->prepare("
            SELECT COUNT(DISTINCT post_id)
            FROM {$wpdb->postmeta}
            WHERE meta_key = %s
        ", self::META_LAST));

        return [
            'orig' => intval($orig),
            'opt'  => intval($opt),
            'count'=> intval($count),
        ];
    }

    private function format_bytes($bytes) {
        $bytes = max(0, intval($bytes));
        $units = ['B','KB','MB','GB','TB'];
        $i = 0;

        while ($bytes >= 1024 && $i < count($units) - 1) {
            $bytes /= 1024;
            $i++;
        }

        if ($i === 0) return $bytes . ' ' . $units[$i];
        return number_format_i18n($bytes, 2) . ' ' . $units[$i];
    }

    public function ajax_reset_stats() {
        if (!current_user_can('manage_options')) wp_send_json_error(['message' => 'Permission denied']);
        check_ajax_referer('rill_imgopt_nonce', 'nonce');

        global $wpdb;
        $wpdb->query($wpdb->prepare(
            "DELETE FROM {$wpdb->postmeta} WHERE meta_key IN (%s,%s,%s)",
            self::META_ORIG, self::META_OPT, self::META_LAST
        ));

        wp_send_json_success(['message' => 'Stats reset.']);
    }
 
    public function ajax_bulk_step() {
        if (!current_user_can('manage_options')) wp_send_json_error(['message' => 'Permission denied']);
        check_ajax_referer('rill_imgopt_nonce', 'nonce');

        $opt = $this->get_options();
        $batch = isset($_POST['batch']) ? max(1, min(50, intval($_POST['batch']))) : $opt['max_batch'];
        $offset = isset($_POST['offset']) ? max(0, intval($_POST['offset'])) : 0;


        $q = new WP_Query([
            'post_type' => 'attachment',
            'post_status' => 'inherit',
            'posts_per_page' => $batch,
            'offset' => $offset,
            'post_mime_type' => 'image',
            'fields' => 'ids',
            'orderby' => 'ID',
            'order' => 'ASC',
            'no_found_rows' => false,
        ]);

        $ids = $q->posts;
        $total = intval($q->found_posts);

        $processed = 0;
        $changed = 0;
        $saved_bytes = 0;

        foreach ($ids as $aid) {
            $file = get_attached_file($aid);
            if (!$file || !file_exists($file)) { $processed++; continue; }

            $before_total = 0;
            $after_total = 0;

            if ($opt['optimize_original']) {
                $b = filesize($file);
                $n = $this->optimize_file($file);
                $a = ($n !== false) ? $n : $b;
                $before_total += $b;
                $after_total  += $a;
            }

            if ($opt['optimize_sizes']) {
                $meta = wp_get_attachment_metadata($aid);
                if (!empty($meta['sizes']) && is_array($meta['sizes'])) {
                    $upload_dir = wp_upload_dir();
                    $base_dir = trailingslashit($upload_dir['basedir']);

                    $rel = isset($meta['file']) ? $meta['file'] : '';
                    $subdir = '';
                    if ($rel) {
                        $subdir = trailingslashit(dirname($rel));
                        if ($subdir === './') $subdir = '';
                    }

                    foreach ($meta['sizes'] as $size) {
                        if (empty($size['file'])) continue;
                        $p = $base_dir . $subdir . $size['file'];
                        if (!file_exists($p)) continue;

                        $b = filesize($p);
                        $n = $this->optimize_file($p);
                        $a = ($n !== false) ? $n : $b;

                        $before_total += $b;
                        $after_total  += $a;
                    }
                }
            }

            if ($before_total > 0) {
                $existing_orig = intval(get_post_meta($aid, self::META_ORIG, true));
                if ($existing_orig <= 0) update_post_meta($aid, self::META_ORIG, $before_total);

                update_post_meta($aid, self::META_OPT, $after_total);
                update_post_meta($aid, self::META_LAST, time());

                if ($after_total < $before_total) {
                    $changed++;
                    $saved_bytes += ($before_total - $after_total);
                }
            }

            $processed++;
        }

        $next_offset = $offset + $processed;
        $done = ($next_offset >= $total);

        $stats = $this->get_stats();

        wp_send_json_success([
            'processed' => $processed,
            'changed' => $changed,
            'saved_bytes' => $saved_bytes,
            'offset' => $next_offset,
            'total' => $total,
            'done' => $done,
            'stats' => $stats,
        ]);
    }
}
