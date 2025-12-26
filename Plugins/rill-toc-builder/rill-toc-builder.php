<?php
/*
Plugin Name: Rill TOC Builder
Description: Build book Table of Contents with per-book Parts and Chapters. Includes a TOC Builder admin screen with live preview and a shortcode for front-end TOC.
Version: 1.0.0
Author: ctrlaltimran.com
License: GPLv2 or later
Text Domain: rill-toc-builder
*/

if (!defined('ABSPATH')) { exit; }

if (!class_exists('Rill_TOC_Builder')) {

class Rill_TOC_Builder {

  const TAX_PART = 'book_part';
  const TERM_META_BOOK_ID = 'rill_book_id';
  const TERM_META_ORDER = 'rill_part_order';
  // Manual chapter ordering (per chapter). If empty, the system falls back to ACF chapter_number.
  const CH_META_ORDER = 'rill_chapter_order';
  const BOOK_TAG_SLUG = 'chapters_cook';
  const PAGE_SLUG = 'rill-toc-builder';

  public static function init() {
    add_action('init', [__CLASS__, 'register_taxonomy']);
    add_action('admin_menu', [__CLASS__, 'register_admin_menu'], 999);

    // Shortcode: [rill_book_toc] or [rill_book_toc book_id="123"]
    add_shortcode('rill_book_toc', [__CLASS__, 'shortcode_book_toc']);
  }

  public static function register_taxonomy() {
    $types = [];
    if (post_type_exists('chapter'))  { $types[] = 'chapter'; }
    if (post_type_exists('chapters')) { $types[] = 'chapters'; }
    if (!$types) { $types = ['chapter']; }

    register_taxonomy(self::TAX_PART, $types, [
      'label' => __('Book Parts', 'rill-toc-builder'),
      'public' => false,
      'show_ui' => true,
      'show_admin_column' => true,
      'hierarchical' => false,
      'rewrite' => false,
      'show_in_rest' => true,
    ]);
  }

  public static function register_admin_menu() {
    // Always available top-level menu
    add_menu_page(
      __('TOC Builder', 'rill-toc-builder'),
      __('TOC Builder', 'rill-toc-builder'),
      'edit_posts',
      self::PAGE_SLUG,
      [__CLASS__, 'render_admin_page'],
      'dashicons-list-view',
      58
    );

    // Also attach under Chapters menu if it exists
    if (post_type_exists('chapter')) {
      add_submenu_page('edit.php?post_type=chapter', __('TOC Builder', 'rill-toc-builder'), __('TOC Builder', 'rill-toc-builder'), 'edit_posts', self::PAGE_SLUG, [__CLASS__, 'render_admin_page']);
    }
    if (post_type_exists('chapters')) {
      add_submenu_page('edit.php?post_type=chapters', __('TOC Builder', 'rill-toc-builder'), __('TOC Builder', 'rill-toc-builder'), 'edit_posts', self::PAGE_SLUG, [__CLASS__, 'render_admin_page']);
    }
  }

  private static function chapter_cpt_slug() {
    if (post_type_exists('chapter')) return 'chapter';
    if (post_type_exists('chapters')) return 'chapters';
    return 'chapter';
  }

  private static function get_books_with_tag() {
    // Requires WooCommerce for products, but will fail gracefully.
    return get_posts([
      'post_type' => 'product',
      'posts_per_page' => 500,
      'orderby' => 'title',
      'order' => 'ASC',
      'tax_query' => [
        [
          'taxonomy' => 'product_tag',
          'field' => 'slug',
          'terms' => [self::BOOK_TAG_SLUG],
        ],
      ],
    ]);
  }

  private static function get_book_parts($book_id) {
    if (!$book_id) return [];
    return get_terms([
      'taxonomy' => self::TAX_PART,
      'hide_empty' => false,
      'meta_query' => [
        [
          'key' => self::TERM_META_BOOK_ID,
          'value' => (string) $book_id,
          'compare' => '=',
        ],
      ],
      'orderby' => 'meta_value_num',
      'meta_key' => self::TERM_META_ORDER,
      'order' => 'ASC',
    ]);
  }

  private static function next_part_order($book_id) {
    $parts = self::get_book_parts($book_id);
    $max = 0;
    foreach ($parts as $p) {
      $o = (int) get_term_meta($p->term_id, self::TERM_META_ORDER, true);
      if ($o > $max) $max = $o;
    }
    return $max + 1;
  }

  private static function get_book_chapters($book_id) {
    if (!$book_id) return [];
    $cpt = self::chapter_cpt_slug();

    // Support both ACF storage styles: plain ID (=) or serialized/array (LIKE)
    $chapters = get_posts([
      'post_type' => $cpt,
      'posts_per_page' => -1,
      'meta_query' => [
        'relation' => 'OR',
        [
          'key' => 'book_product',
          'value' => (int) $book_id,
          'compare' => '=',
        ],
        [
          'key' => 'book_product',
          'value' => '"' . (int) $book_id . '"',
          'compare' => 'LIKE',
        ],
      ],
      'orderby' => 'meta_value_num',
      'meta_key' => 'chapter_number',
      'order' => 'ASC',
    ]);

    if (!$chapters) return [];

    // Apply manual order (if set). Fallback: chapter_number.
    usort($chapters, function($a, $b){
      $a_id = (int) $a->ID;
      $b_id = (int) $b->ID;

      $ao = (int) get_post_meta($a_id, self::CH_META_ORDER, true);
      $bo = (int) get_post_meta($b_id, self::CH_META_ORDER, true);

      $an = (int) get_field('chapter_number', $a_id);
      $bn = (int) get_field('chapter_number', $b_id);

      $a_key = $ao > 0 ? $ao : ($an > 0 ? $an : 999999);
      $b_key = $bo > 0 ? $bo : ($bn > 0 ? $bn : 999999);

      if ($a_key === $b_key) {
        // Tie-breaker: chapter_number then title
        if ($an === $bn) {
          return strcmp((string)$a->post_title, (string)$b->post_title);
        }
        return $an <=> $bn;
      }

      return $a_key <=> $b_key;
    });

    return $chapters;
  }

  private static function term_belongs_to_book($term_id, $book_id) {
    if (!$term_id || !$book_id) return false;
    return (string) get_term_meta((int) $term_id, self::TERM_META_BOOK_ID, true) === (string) $book_id;
  }

  private static function get_acf_field($key, $post_id) {
    if (function_exists('get_field')) {
      return get_field($key, $post_id);
    }
    return get_post_meta($post_id, $key, true);
  }

  public static function render_admin_page() {
    if (!current_user_can('edit_posts')) return;

    $book_id = isset($_GET['book_id']) ? (int) $_GET['book_id'] : 0;

    // Handle POST actions
    if (!empty($_POST['rill_action']) && check_admin_referer('rill_toc_builder_admin')) {
      $action = sanitize_text_field($_POST['rill_action']);
      $book_id = isset($_POST['book_id']) ? (int) $_POST['book_id'] : $book_id;

      if ($action === 'add_part' && $book_id && current_user_can('manage_categories')) {
        $new_part = isset($_POST['new_part_name']) ? sanitize_text_field($_POST['new_part_name']) : '';
        if ($new_part) {
          $res = wp_insert_term($new_part, self::TAX_PART);
          if (!is_wp_error($res) && !empty($res['term_id'])) {
            $tid = (int) $res['term_id'];
            update_term_meta($tid, self::TERM_META_BOOK_ID, (string) $book_id);
            update_term_meta($tid, self::TERM_META_ORDER, (int) self::next_part_order($book_id));
          }
        }
      }

      if ($action === 'save_assignments' && $book_id) {
        $assign = isset($_POST['chapter_part']) && is_array($_POST['chapter_part']) ? $_POST['chapter_part'] : [];
        $orders = isset($_POST['chapter_order']) && is_array($_POST['chapter_order']) ? $_POST['chapter_order'] : [];

        foreach ($assign as $chapter_id => $term_id) {
          $chapter_id = (int) $chapter_id;
          $term_id = (int) $term_id;

          // Save manual order (optional). Empty or 0 removes the custom ordering.
          $order_val = isset($orders[$chapter_id]) ? (int) $orders[$chapter_id] : 0;
          if ($order_val > 0) {
            update_post_meta($chapter_id, self::CH_META_ORDER, $order_val);
          } else {
            delete_post_meta($chapter_id, self::CH_META_ORDER);
          }

          if ($term_id > 0) {
            if (self::term_belongs_to_book($term_id, $book_id)) {
              wp_set_post_terms($chapter_id, [$term_id], self::TAX_PART, false);
            }
          } else {
            wp_set_post_terms($chapter_id, [], self::TAX_PART, false);
          }
        }

        add_settings_error('rill_toc_builder', 'saved', __('Saved. TOC updated.', 'rill-toc-builder'), 'updated');
      }
    }

    $books = self::get_books_with_tag();
    $parts = $book_id ? self::get_book_parts($book_id) : [];
    $chapters = $book_id ? self::get_book_chapters($book_id) : [];

    settings_errors('rill_toc_builder');

    ?>
    <div class="wrap rill-tocb-wrap">
      <h1><?php echo esc_html__('TOC Builder', 'rill-toc-builder'); ?></h1>

      <div class="rill-tocb-grid">
        <div class="rill-tocb-card">

          <form method="get" class="rill-tocb-row">
            <input type="hidden" name="page" value="<?php echo esc_attr(self::PAGE_SLUG); ?>">
            <label class="rill-tocb-label"><?php echo esc_html__('Books (tagged: chapters_cook)', 'rill-toc-builder'); ?></label>
            <div class="rill-tocb-row">
              <select name="book_id" class="rill-tocb-select">
                <option value="0"><?php echo esc_html__('Select a book...', 'rill-toc-builder'); ?></option>
                <?php foreach ($books as $b): ?>
                  <option value="<?php echo (int) $b->ID; ?>" <?php selected($book_id, $b->ID); ?>>
                    <?php echo esc_html($b->post_title); ?>
                  </option>
                <?php endforeach; ?>
              </select>
              <button class="button button-primary" type="submit"><?php echo esc_html__('Load', 'rill-toc-builder'); ?></button>
            </div>
          </form>

          <?php if (!$book_id): ?>
            <div class="rill-tocb-note"><?php echo esc_html__('Select a book to create Parts and connect Chapters.', 'rill-toc-builder'); ?></div>
          <?php else: ?>

            <div class="rill-tocb-section">
              <h2><?php echo esc_html__('Create Part for this Book', 'rill-toc-builder'); ?></h2>
              <form method="post" class="rill-tocb-row">
                <?php wp_nonce_field('rill_toc_builder_admin'); ?>
                <input type="hidden" name="book_id" value="<?php echo (int) $book_id; ?>">
                <input type="hidden" name="rill_action" value="add_part">
                <input type="text" name="new_part_name" class="rill-tocb-input" placeholder="Front Matter or Part 1: ..." />
                <button class="button" type="submit"><?php echo esc_html__('Add Part', 'rill-toc-builder'); ?></button>
              </form>
              <p class="description"><?php echo esc_html__('Parts are headings only. They do not create pages or products.', 'rill-toc-builder'); ?></p>
            </div>

            <div class="rill-tocb-section">
              <h2><?php echo esc_html__('Assign Chapters to Parts', 'rill-toc-builder'); ?></h2>

              <?php if (!$chapters): ?>
                <div class="rill-tocb-warn">
                  <?php echo wp_kses_post(__('No chapters found for this book. Make sure each chapter has ACF <code>book_product</code> set to this book.', 'rill-toc-builder')); ?>
                </div>
              <?php else: ?>

                <form method="post">
                  <?php wp_nonce_field('rill_toc_builder_admin'); ?>
                  <input type="hidden" name="book_id" value="<?php echo (int) $book_id; ?>">
                  <input type="hidden" name="rill_action" value="save_assignments">

                  <table class="widefat striped">
                    <thead>
                      <tr>
	                        <th style="width:70px;"><?php echo esc_html__('#', 'rill-toc-builder'); ?></th>
	                        <th style="width:90px;"><?php echo esc_html__('Order', 'rill-toc-builder'); ?></th>
                        <th><?php echo esc_html__('Chapter', 'rill-toc-builder'); ?></th>
                        <th style="width:340px;"><?php echo esc_html__('Part (this book)', 'rill-toc-builder'); ?></th>
                      </tr>
                    </thead>
                    <tbody>
                      <?php foreach ($chapters as $ch):
                        $num = self::get_acf_field('chapter_number', $ch->ID);
	                        $ord_current = (int) get_post_meta($ch->ID, self::CH_META_ORDER, true);
	                        if ($ord_current <= 0 && $num !== '') $ord_current = (int) $num;
                        $terms = wp_get_post_terms($ch->ID, self::TAX_PART);
                        $current_term_id = ($terms && !is_wp_error($terms)) ? (int) $terms[0]->term_id : 0;

                        if ($current_term_id && !self::term_belongs_to_book($current_term_id, $book_id)) {
                          $current_term_id = 0;
                        }
                      ?>
                        <tr>
                          <td><?php echo esc_html($num ?: ''); ?></td>
	                          <td>
	                            <input type="number" step="1" min="1" name="chapter_order[<?php echo (int) $ch->ID; ?>]" value="<?php echo esc_attr($ord_current); ?>" style="width:80px;" />
	                          </td>
                          <td>
                            <strong><?php echo esc_html(get_the_title($ch)); ?></strong>
                            <div class="rill-tocb-muted"><?php echo esc_html__('ID:', 'rill-toc-builder'); ?> <?php echo (int) $ch->ID; ?></div>
                          </td>
                          <td>
                            <select name="chapter_part[<?php echo (int) $ch->ID; ?>]" class="rill-tocb-select">
                              <option value="0" <?php selected($current_term_id, 0); ?>><?php echo esc_html__('Other (no part)', 'rill-toc-builder'); ?></option>
                              <?php foreach ($parts as $p): ?>
                                <option value="<?php echo (int) $p->term_id; ?>" <?php selected($current_term_id, $p->term_id); ?>>
                                  <?php echo esc_html($p->name); ?>
                                </option>
                              <?php endforeach; ?>
                            </select>
                          </td>
                        </tr>
                      <?php endforeach; ?>
                    </tbody>
                  </table>

                  <p style="margin-top:12px;">
                    <button class="button button-primary" type="submit"><?php echo esc_html__('Save', 'rill-toc-builder'); ?></button>
                  </p>
                </form>

              <?php endif; ?>
            </div>

          <?php endif; ?>

        </div>

        <div class="rill-tocb-card">
          <h2><?php echo esc_html__('Live TOC Preview', 'rill-toc-builder'); ?></h2>
          <?php
            echo $book_id ? self::render_preview($book_id) : '<div class="rill-tocb-note">Select a book to preview its TOC.</div>';
          ?>
          <p class="description">
            <?php echo esc_html__('Tip: Use shortcode [rill_book_toc] on your book or chapter template.', 'rill-toc-builder'); ?>
          </p>
        </div>
      </div>
    </div>

    <style>
      .rill-tocb-grid{display:flex;gap:18px;flex-wrap:wrap}
      .rill-tocb-card{flex:1 1 520px;min-width:420px;background:#fff;border:1px solid #e5e5e5;border-radius:10px;padding:14px}
      .rill-tocb-row{display:flex;gap:10px;align-items:center;flex-wrap:wrap;margin:8px 0}
      .rill-tocb-label{font-weight:700;display:block;margin-bottom:6px}
      .rill-tocb-select{min-width:360px;max-width:100%}
      .rill-tocb-input{min-width:360px;max-width:100%;padding:6px 10px}
      .rill-tocb-note{padding:10px;background:#f7f7f7;border:1px solid #eee;border-radius:8px}
      .rill-tocb-warn{padding:10px;background:#fff3cd;border:1px solid #ffeeba;border-radius:8px}
      .rill-tocb-muted{color:#666;font-size:12px;margin-top:3px}
      .rill-preview-wrap{background:#fff;border:1px solid #e5e5e5;border-radius:8px;padding:12px}
      .rill-prev-part{border-top:1px solid #eee;padding-top:10px;margin-top:10px}
      .rill-prev-part:first-child{border-top:none;padding-top:0;margin-top:0}
      .rill-prev-part summary{font-weight:700;cursor:pointer}
      .rill-prev-list{padding:10px 0 0 16px}
      .rill-prev-row{margin-bottom:12px}
      .rill-prev-access{font-weight:700;font-size:12px;margin-right:8px}
      .rill-prev-access.free{color:green}
      .rill-prev-access.restricted{color:#c00}
      .rill-prev-title{color:#0b57d0}
      .rill-prev-meta{font-size:12px;color:#555;margin-left:20px;margin-top:4px}
      .rill-prev-empty{font-size:12px;color:#666;padding:6px 0}
    </style>
    <?php
  }

  private static function render_preview($book_id) {
    $chapters = self::get_book_chapters($book_id);
    if (!$chapters) {
      return '<div class="rill-tocb-warn">No chapters found for this book. Make sure each chapter has ACF <code>book_product</code> set to this book.</div>';
    }

    $parts = self::get_book_parts($book_id);
    $part_map = [];
    foreach ($parts as $p) { $part_map[(int)$p->term_id] = $p->name; }

    $grouped = [];
    $other = [];

    foreach ($chapters as $ch) {
      $terms = wp_get_post_terms($ch->ID, self::TAX_PART);
      $term_id = ($terms && !is_wp_error($terms)) ? (int) $terms[0]->term_id : 0;

      if ($term_id && isset($part_map[$term_id])) {
        $grouped[$term_id][] = $ch;
      } else {
        $other[] = $ch;
      }
    }

    ob_start(); ?>
      <div class="rill-preview-wrap">
        <?php foreach ($parts as $p): $pid = (int) $p->term_id; ?>
          <details open class="rill-prev-part">
            <summary><?php echo esc_html($p->name); ?></summary>
            <div class="rill-prev-list">
              <?php if (empty($grouped[$pid])): ?>
                <div class="rill-prev-empty">No chapters assigned yet.</div>
              <?php else: ?>
                <?php foreach ($grouped[$pid] as $ch):
                  $access = self::get_acf_field('access_type', $ch->ID);
                  $author = self::get_acf_field('author_name', $ch->ID);
                  $pages  = self::get_acf_field('pages_range', $ch->ID);
                ?>
                  <div class="rill-prev-row">
                    <span class="rill-prev-access <?php echo ($access === 'free') ? 'free' : 'restricted'; ?>">
                      <?php echo ($access === 'free') ? 'Free access' : 'Restricted access'; ?>
                    </span>
                    <span class="rill-prev-title"><?php echo esc_html(get_the_title($ch)); ?></span>
                    <div class="rill-prev-meta">
                      <?php if ($author): ?>Author: <?php echo esc_html($author); ?><?php endif; ?>
                      <?php if ($pages): ?> · Pages: <?php echo esc_html($pages); ?><?php endif; ?>
                    </div>
                  </div>
                <?php endforeach; ?>
              <?php endif; ?>
            </div>
          </details>
        <?php endforeach; ?>

        <?php if ($other): ?>
          <details open class="rill-prev-part">
            <summary>Other (no part)</summary>
            <div class="rill-prev-list">
              <?php foreach ($other as $ch):
                $access = self::get_acf_field('access_type', $ch->ID);
                $author = self::get_acf_field('author_name', $ch->ID);
                $pages  = self::get_acf_field('pages_range', $ch->ID);
              ?>
                <div class="rill-prev-row">
                  <span class="rill-prev-access <?php echo ($access === 'free') ? 'free' : 'restricted'; ?>">
                    <?php echo ($access === 'free') ? 'Free access' : 'Restricted access'; ?>
                  </span>
                  <span class="rill-prev-title"><?php echo esc_html(get_the_title($ch)); ?></span>
                  <div class="rill-prev-meta">
                    <?php if ($author): ?>Author: <?php echo esc_html($author); ?><?php endif; ?>
                    <?php if ($pages): ?> · Pages: <?php echo esc_html($pages); ?><?php endif; ?>
                  </div>
                </div>
              <?php endforeach; ?>
            </div>
          </details>
        <?php endif; ?>
      </div>
    <?php
    return ob_get_clean();
  }

  public static function shortcode_book_toc($atts = []) {
    $atts = shortcode_atts([
      'book_id' => 0,
      'open_all' => 0,
    ], $atts, 'rill_book_toc');

    $book_id = (int) $atts['book_id'];

    // Auto detect book ID from current page:
    // - product page: use product ID
    // - chapter page: use ACF book_product
    if (!$book_id) {
      if (is_singular('product')) {
        $book_id = get_the_ID();
      } else {
        $cpt = self::chapter_cpt_slug();
        if (is_singular($cpt)) {
          $book = self::get_acf_field('book_product', get_the_ID());
          if (is_object($book) && isset($book->ID)) $book_id = (int) $book->ID;
          if (is_numeric($book)) $book_id = (int) $book;
        }
      }
    }

    if (!$book_id) return '';

    $chapters = self::get_book_chapters($book_id);
    if (!$chapters) return '';

    $parts = self::get_book_parts($book_id);
    $part_map = [];
    foreach ($parts as $p) { $part_map[(int)$p->term_id] = $p->name; }

    $grouped = [];
    $other = [];

    foreach ($chapters as $ch) {
      $terms = wp_get_post_terms($ch->ID, self::TAX_PART);
      $term_id = ($terms && !is_wp_error($terms)) ? (int) $terms[0]->term_id : 0;

      if ($term_id && isset($part_map[$term_id])) {
        $grouped[$term_id][] = $ch;
      } else {
        $other[] = $ch;
      }
    }

    $open_attr = ((int)$atts['open_all'] === 1) ? ' open' : '';

    ob_start(); ?>
      <div class="rill-book-toc">
        <?php foreach ($parts as $p): $pid = (int)$p->term_id; ?>
          <details class="rill-toc-part"<?php echo $open_attr; ?>>
            <summary><?php echo esc_html($p->name); ?></summary>
            <div class="rill-toc-list">
              <?php if (empty($grouped[$pid])): ?>
                <div class="rill-toc-empty">No chapters assigned.</div>
              <?php else: ?>
                <?php foreach ($grouped[$pid] as $ch):
                  $access = self::get_acf_field('access_type', $ch->ID);
                  $author = self::get_acf_field('author_name', $ch->ID);
                  $pages  = self::get_acf_field('pages_range', $ch->ID);
                ?>
                  <div class="rill-toc-row">
                    <span class="rill-toc-access <?php echo ($access === 'free') ? 'free' : 'restricted'; ?>">
                      <?php echo ($access === 'free') ? 'Free access' : 'Restricted access'; ?>
                    </span>
                    <a class="rill-toc-title" href="<?php echo esc_url(get_permalink($ch)); ?>">
                      <?php echo esc_html(get_the_title($ch)); ?>
                    </a>
                    <div class="rill-toc-meta">
                      <?php if ($author): ?>Author: <?php echo esc_html($author); ?><?php endif; ?>
                      <?php if ($pages): ?> · Pages: <?php echo esc_html($pages); ?><?php endif; ?>
                    </div>
                  </div>
                <?php endforeach; ?>
              <?php endif; ?>
            </div>
          </details>
        <?php endforeach; ?>

        <?php if ($other): ?>
          <details class="rill-toc-part"<?php echo $open_attr; ?>>
            <summary>Other</summary>
            <div class="rill-toc-list">
              <?php foreach ($other as $ch):
                $access = self::get_acf_field('access_type', $ch->ID);
                $author = self::get_acf_field('author_name', $ch->ID);
                $pages  = self::get_acf_field('pages_range', $ch->ID);
              ?>
                <div class="rill-toc-row">
                  <span class="rill-toc-access <?php echo ($access === 'free') ? 'free' : 'restricted'; ?>">
                    <?php echo ($access === 'free') ? 'Free access' : 'Restricted access'; ?>
                  </span>
                  <a class="rill-toc-title" href="<?php echo esc_url(get_permalink($ch)); ?>">
                    <?php echo esc_html(get_the_title($ch)); ?>
                  </a>
                  <div class="rill-toc-meta">
                    <?php if ($author): ?>Author: <?php echo esc_html($author); ?><?php endif; ?>
                    <?php if ($pages): ?> · Pages: <?php echo esc_html($pages); ?><?php endif; ?>
                  </div>
                </div>
              <?php endforeach; ?>
            </div>
          </details>
        <?php endif; ?>
      </div>

      <style>
        .rill-book-toc{border:1px solid #e5e5e5;border-radius:10px;padding:12px;background:#fff}
        .rill-toc-part{border-top:1px solid #eee;padding-top:10px;margin-top:10px}
        .rill-toc-part:first-child{border-top:none;padding-top:0;margin-top:0}
        .rill-toc-part summary{font-weight:700;cursor:pointer}
        .rill-toc-list{padding:10px 0 0 16px}
        .rill-toc-row{margin-bottom:12px}
        .rill-toc-access{font-weight:700;font-size:12px;margin-right:8px}
        .rill-toc-access.free{color:green}
        .rill-toc-access.restricted{color:#c00}
        .rill-toc-title{text-decoration:none}
        .rill-toc-meta{font-size:12px;color:#555;margin-left:20px;margin-top:4px}
        .rill-toc-empty{font-size:12px;color:#666;padding:6px 0}
      </style>
    <?php
    return ob_get_clean();
  }
}

Rill_TOC_Builder::init();

}
