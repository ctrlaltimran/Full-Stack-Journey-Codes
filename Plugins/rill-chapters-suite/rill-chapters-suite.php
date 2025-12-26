<?php
/**
 * Plugin Name: RILL Chapters Suite (TOC + Chapter Products)
 * Description: Per-book TOC Builder (Parts + Chapters) + chapter product sync + downloads + citations (guest-friendly).
 * Version: 1.0.0
 * Author: ctrlaltimran.com
 */

if (!defined('ABSPATH')) exit;

class RILL_Chapters_Suite {

  const PART_TAX = 'book_part';
  const META_IS_CHAPTER_PRODUCT = '_rill_is_chapter_product';
  const META_LINKED_CHAPTER_ID  = '_rill_linked_chapter_id';
  const COOKIE_EMAIL = 'rill_customer_email';

  public static function init() {
    add_action('init', [__CLASS__, 'register_part_taxonomy']);
    add_action('admin_menu', [__CLASS__, 'register_admin_menu'], 999);
    add_action('woocommerce_thankyou', [__CLASS__, 'remember_guest_email_and_order'], 10, 1);

    add_action('woocommerce_thankyou', [__CLASS__, 'render_thankyou_chapter_downloads'], 25, 1);
add_action('woocommerce_email_order_details', [__CLASS__, 'email_chapter_downloads'], 25, 4);


    // Chapter product sync on save
    add_action('save_post', [__CLASS__, 'sync_chapter_product_on_save'], 50, 3);
    add_action('acf/save_post', [__CLASS__, 'sync_chapter_product_on_acf_save'], 30);

    // Shortcodes
    add_shortcode('rill_book_toc', [__CLASS__, 'sc_book_toc']);
    add_shortcode('rill_chapter_badge', [__CLASS__, 'sc_chapter_badge']);
    add_shortcode('rill_chapter_buy_or_download', [__CLASS__, 'sc_chapter_buy_or_download']);
    add_shortcode('rill_chapter_pdf_preview', [__CLASS__, 'sc_chapter_pdf_preview']);
    add_shortcode('rill_chapter_nav', [__CLASS__, 'sc_chapter_nav']);
    add_shortcode('rill_buy_full_book', [__CLASS__, 'sc_buy_full_book']);
    add_shortcode('rill_citation', [__CLASS__, 'sc_citation_button']);
    add_shortcode('rill_chapter_book_info', [__CLASS__, 'sc_chapter_book_info']);

    // Query vars + endpoints
    add_filter('query_vars', [__CLASS__, 'register_query_vars']);
    add_action('template_redirect', [__CLASS__, 'handle_endpoints']);

    // Hide chapter products from catalog/search even if custom queries ignore visibility
    add_action('pre_get_posts', [__CLASS__, 'exclude_chapter_products_from_shop_queries']);

    // Ensure cart shows the latest chapter title (avoids "old title" issue)
    add_filter('woocommerce_cart_item_name', [__CLASS__, 'filter_cart_item_name'], 10, 3);
  }

  public static function chapter_cpt() {
    if (post_type_exists('chapter')) return 'chapter';
    if (post_type_exists('chapters')) return 'chapters';
    return 'chapter';
  }

  public static function register_part_taxonomy() {
    $types = [];
    if (post_type_exists('chapter'))  $types[] = 'chapter';
    if (post_type_exists('chapters')) $types[] = 'chapters';
    if (!$types) $types = ['chapter'];

    register_taxonomy(self::PART_TAX, $types, [
      'label' => 'Book Parts',
      'public' => false,
      'show_ui' => true,
      'show_admin_column' => true,
      'hierarchical' => false,
      'rewrite' => false,
      'show_in_rest' => true,
    ]);
  }

  public static function register_admin_menu() {
    $parent = 'edit.php?post_type=' . self::chapter_cpt();

    add_submenu_page(
      $parent,
      'TOC Builder',
      'TOC Builder',
      'edit_posts',
      'rill-toc-builder',
      [__CLASS__, 'render_toc_builder_page']
    );
  }

  public static function get_books_with_tag() {
    return get_posts([
      'post_type' => 'product',
      'posts_per_page' => 300,
      'orderby' => 'title',
      'order' => 'ASC',
      'tax_query' => [
        [
          'taxonomy' => 'product_tag',
          'field'    => 'slug',
          'terms'    => ['chapters_cook'],
        ]
      ],
    ]);
  }

  public static function get_book_parts($book_id) {
    if (!$book_id) return [];

    return get_terms([
      'taxonomy' => self::PART_TAX,
      'hide_empty' => false,
      'meta_query' => [
        [
          'key' => 'rill_book_id',
          'value' => (string) $book_id,
          'compare' => '='
        ]
      ],
      'orderby' => 'meta_value_num',
      'meta_key' => 'rill_part_order',
      'order' => 'ASC',
    ]);
  }

  public static function next_part_order($book_id) {
    $parts = self::get_book_parts($book_id);
    $max = 0;
    foreach ($parts as $p) {
      $o = (int) get_term_meta($p->term_id, 'rill_part_order', true);
      if ($o > $max) $max = $o;
    }
    return $max + 1;
  }

  public static function term_belongs_to_book($term_id, $book_id) {
    if (!$term_id || !$book_id) return false;
    return (string) get_term_meta((int)$term_id, 'rill_book_id', true) === (string) $book_id;
  }

  public static function get_chapter_book_id($chapter_id) {
    $book = get_field('book_product', $chapter_id);
    if (!$book) return 0;
    return is_object($book) ? (int) $book->ID : (int) $book;
  }

  public static function get_chapter_product_id($chapter_id) {
    return (int) get_field('chapter_product_id', $chapter_id);
  }

  public static function get_book_chapters($book_id) {
    if (!$book_id) return [];
    $cpt = self::chapter_cpt();

    return get_posts([
      'post_type' => $cpt,
      'posts_per_page' => -1,
      'post_status' => 'publish',
      'meta_query' => [
        'relation' => 'OR',
        [
          'key' => 'book_product',
          'value' => (int) $book_id,
          'compare' => '='
        ],
        [
          'key' => 'book_product',
          'value' => '"' . (int) $book_id . '"',
          'compare' => 'LIKE'
        ],
      ],
      'orderby' => 'meta_value_num',
      'meta_key' => 'chapter_number',
      'order' => 'ASC',
    ]);
  }

  public static function render_toc_builder_page() {
    if (!current_user_can('edit_posts')) return;

    $book_id = isset($_GET['book_id']) ? (int) $_GET['book_id'] : 0;
    $books = self::get_books_with_tag();

    if (!empty($_POST['rill_action']) && check_admin_referer('rill_toc_builder_v2')) {
      $action = sanitize_text_field($_POST['rill_action']);
      $book_id = isset($_POST['book_id']) ? (int) $_POST['book_id'] : $book_id;

      if ($action === 'add_part' && $book_id && current_user_can('manage_categories')) {
        $new_part = isset($_POST['new_part_name']) ? sanitize_text_field($_POST['new_part_name']) : '';
        if ($new_part) {
          $res = wp_insert_term($new_part, self::PART_TAX);
          if (!is_wp_error($res) && !empty($res['term_id'])) {
            $tid = (int) $res['term_id'];
            update_term_meta($tid, 'rill_book_id', (string) $book_id);
            update_term_meta($tid, 'rill_part_order', (int) self::next_part_order($book_id));
          }
        }
      }

      if ($action === 'save_assignments' && $book_id) {
        $assign = isset($_POST['chapter_part']) && is_array($_POST['chapter_part']) ? $_POST['chapter_part'] : [];

        foreach ($assign as $chapter_id => $term_id) {
          $chapter_id = (int) $chapter_id;
          $term_id = (int) $term_id;

          if ($term_id > 0) {
            if (self::term_belongs_to_book($term_id, $book_id)) {
              wp_set_post_terms($chapter_id, [$term_id], self::PART_TAX, false);
            }
          } else {
            wp_set_post_terms($chapter_id, [], self::PART_TAX, false);
          }
        }

        echo '<div class="updated notice"><p>Saved. TOC updated.</p></div>';
      }
    }

    $parts = $book_id ? self::get_book_parts($book_id) : [];
    $chapters = $book_id ? self::get_book_chapters($book_id) : [];
    ?>
    <div class="wrap">
      <h1>TOC Builder</h1>

      <div style="display:flex;gap:18px;align-items:flex-start;flex-wrap:wrap;">
        <div style="flex:1 1 520px;min-width:420px;">
          <form method="get" style="margin: 14px 0;">
            <input type="hidden" name="page" value="rill-toc-builder">
            <label style="font-weight:700;">Books (tagged: chapters_cook):</label>
            <select name="book_id" style="min-width: 420px;max-width:100%;margin-left:8px;">
              <option value="0">Select a book…</option>
              <?php foreach ($books as $b): ?>
                <option value="<?php echo (int) $b->ID; ?>" <?php selected($book_id, $b->ID); ?>>
                  <?php echo esc_html($b->post_title); ?>
                </option>
              <?php endforeach; ?>
            </select>
            <button class="button button-primary" type="submit" style="margin-left:8px;">Load</button>
          </form>

          <?php if (!$book_id): ?>
            <div style="padding:12px;background:#fff;border:1px solid #e5e5e5;border-radius:8px;">
              Select a book to manage its Parts and Chapters.
            </div>
          <?php else: ?>

            <div style="padding:12px;background:#fff;border:1px solid #e5e5e5;border-radius:8px;margin-bottom:14px;">
              <div style="font-weight:700;margin-bottom:6px;">Create Part for this Book</div>

              <form method="post" style="display:flex;gap:8px;align-items:center;flex-wrap:wrap;">
                <?php wp_nonce_field('rill_toc_builder_v2'); ?>
                <input type="hidden" name="book_id" value="<?php echo (int) $book_id; ?>">
                <input type="hidden" name="rill_action" value="add_part">
                <input type="text" name="new_part_name" placeholder="Front Matter or Part 1: …" style="min-width:320px;">
                <button class="button" type="submit">Add Part</button>
              </form>

              <div style="color:#666;margin-top:8px;font-size:12px;">
                Parts are headings only. They do not create pages or products.
              </div>
            </div>

            <div style="padding:12px;background:#fff;border:1px solid #e5e5e5;border-radius:8px;">
              <div style="font-weight:700;margin-bottom:10px;">Assign Chapters to Parts</div>

              <?php if (!$chapters): ?>
                <div style="padding:10px;background:#fff3cd;border:1px solid #ffeeba;border-radius:6px;">
                  No chapters found for this book. Make sure each chapter has ACF <code>book_product</code> set to this book.
                </div>
              <?php else: ?>

                <form method="post">
                  <?php wp_nonce_field('rill_toc_builder_v2'); ?>
                  <input type="hidden" name="book_id" value="<?php echo (int) $book_id; ?>">
                  <input type="hidden" name="rill_action" value="save_assignments">

                  <table class="widefat striped">
                    <thead>
                      <tr>
                        <th style="width:60px;">#</th>
                        <th>Chapter</th>
                        <th style="width:320px;">Part (this book)</th>
                      </tr>
                    </thead>
                    <tbody>
                      <?php foreach ($chapters as $ch):
                        $num = get_field('chapter_number', $ch->ID);
                        $terms = wp_get_post_terms($ch->ID, self::PART_TAX);
                        $current_term_id = ($terms && !is_wp_error($terms)) ? (int) $terms[0]->term_id : 0;

                        if ($current_term_id && !self::term_belongs_to_book($current_term_id, $book_id)) {
                          $current_term_id = 0;
                        }
                      ?>
                        <tr>
                          <td><?php echo esc_html($num ?: ''); ?></td>
                          <td>
                            <strong><?php echo esc_html(get_the_title($ch)); ?></strong>
                            <div style="color:#666;font-size:12px;">ID: <?php echo (int) $ch->ID; ?></div>
                          </td>
                          <td>
                            <select name="chapter_part[<?php echo (int) $ch->ID; ?>]" style="width:100%;">
                              <option value="0" <?php selected($current_term_id, 0); ?>>Other (no part)</option>
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
                    <button class="button button-primary" type="submit">Save</button>
                  </p>
                </form>

              <?php endif; ?>
            </div>

          <?php endif; ?>
        </div>

        <div style="flex:1 1 520px;min-width:420px;">
          <h2 style="margin-top:14px;">Live TOC Preview</h2>
          <?php
            echo $book_id ? self::render_toc_preview($book_id) : '<div style="padding:12px;background:#fff;border:1px solid #e5e5e5;border-radius:8px;">Select a book to preview its TOC.</div>';
          ?>
          <div style="color:#666;margin-top:10px;">Tip: Use shortcode <code>[rill_book_toc]</code> on your book or chapter template.</div>
        </div>
      </div>
    </div>
    <?php
  }

  public static function render_toc_preview($book_id) {
    $chapters = self::get_book_chapters($book_id);
    if (!$chapters) {
      return '<div style="padding:10px;background:#fff3cd;border:1px solid #ffeeba;border-radius:6px;">No chapters found for this book.</div>';
    }

    $parts = self::get_book_parts($book_id);
    $part_map = [];
    foreach ($parts as $p) $part_map[(int)$p->term_id] = $p->name;

    $grouped = [];
    $other = [];

    foreach ($chapters as $ch) {
      $terms = wp_get_post_terms($ch->ID, self::PART_TAX);
      $term_id = ($terms && !is_wp_error($terms)) ? (int) $terms[0]->term_id : 0;

      if ($term_id && isset($part_map[$term_id])) $grouped[$term_id][] = $ch;
      else $other[] = $ch;
    }

    ob_start(); ?>
      <div class="rill-prev-wrap">
        <?php foreach ($parts as $p): $pid = (int) $p->term_id; ?>
          <details open class="rill-prev-part">
            <summary><?php echo esc_html($p->name); ?></summary>
            <div class="rill-prev-list">
              <?php if (empty($grouped[$pid])): ?>
                <div class="rill-prev-empty">No chapters assigned yet.</div>
              <?php else: ?>
                <?php foreach ($grouped[$pid] as $ch):
                  $access = get_field('access_type', $ch->ID);
                  $author = get_field('author_name', $ch->ID);
                  $pages  = get_field('pages_range', $ch->ID);
                ?>
                  <div class="rill-prev-row">
                    <span class="rill-prev-access <?php echo $access === 'free' ? 'free' : 'restricted'; ?>">
                      <?php echo $access === 'free' ? 'Free access' : 'Restricted access'; ?>
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
                $access = get_field('access_type', $ch->ID);
                $author = get_field('author_name', $ch->ID);
                $pages  = get_field('pages_range', $ch->ID);
              ?>
                <div class="rill-prev-row">
                  <span class="rill-prev-access <?php echo $access === 'free' ? 'free' : 'restricted'; ?>">
                    <?php echo $access === 'free' ? 'Free access' : 'Restricted access'; ?>
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

      <style>
        .rill-prev-wrap{background:#fff;border:1px solid #e5e5e5;border-radius:8px;padding:12px}
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
    return ob_get_clean();
  }

  public static function sc_book_toc($atts) {
    $atts = shortcode_atts([
      'book_id'   => '',
      'open_all'  => '1',
    ], $atts);

    $book_id = (int) $atts['book_id'];

    if ($book_id <= 0) {
      if (is_singular('product')) {
        $pid = get_the_ID();
        if (!self::is_chapter_product($pid)) $book_id = $pid;
      } elseif (is_singular(self::chapter_cpt())) {
        $book_id = self::get_chapter_book_id(get_the_ID());
      }
    }

    if ($book_id <= 0) return '<div class="rill-toc">No TOC available.</div>';

    $chapters = self::get_book_chapters($book_id);
    if (!$chapters) return '<div class="rill-toc">No chapters found.</div>';

    $parts = self::get_book_parts($book_id);
    $part_map = [];
    foreach ($parts as $p) $part_map[(int)$p->term_id] = $p->name;

    $grouped = [];
    $other = [];
    foreach ($chapters as $ch) {
      $terms = wp_get_post_terms($ch->ID, self::PART_TAX);
      $term_id = ($terms && !is_wp_error($terms)) ? (int) $terms[0]->term_id : 0;

      if ($term_id && isset($part_map[$term_id])) $grouped[$term_id][] = $ch;
      else $other[] = $ch;
    }

    $open_attr = ($atts['open_all'] === '1') ? ' open' : '';

    ob_start(); ?>
      <div class="rill-toc">
        <div class="rill-toc-header"><strong>Table of Contents</strong></div>

        <?php foreach ($parts as $p): $pid = (int) $p->term_id; ?>
          <details class="rill-toc-part"<?php echo $open_attr; ?>>
            <summary><?php echo esc_html($p->name); ?></summary>
            <div class="rill-toc-list">
              <?php if (empty($grouped[$pid])): ?>
                <div class="rill-toc-empty">No chapters assigned.</div>
              <?php else: ?>
                <?php foreach ($grouped[$pid] as $ch): echo self::render_toc_chapter_row($ch); endforeach; ?>
              <?php endif; ?>
            </div>
          </details>
        <?php endforeach; ?>

        <?php if ($other): ?>
          <details class="rill-toc-part"<?php echo $open_attr; ?>>
            <summary>Other</summary>
            <div class="rill-toc-list">
              <?php foreach ($other as $ch) echo self::render_toc_chapter_row($ch); ?>
            </div>
          </details>
        <?php endif; ?>
      </div>

      <style>
        .rill-toc{border:1px solid #e5e5e5;border-radius:10px;padding:12px;margin:12px 0;background:#fff}
        .rill-toc-header{display:flex;align-items:center;gap:10px;margin-bottom:6px}
        .rill-toc-part{border-top:1px solid #eee;padding-top:10px;margin-top:10px}
        .rill-toc-part:first-of-type{border-top:0;padding-top:0;margin-top:0}
        .rill-toc-part summary{font-weight:700;cursor:pointer;list-style:none}
        .rill-toc-part summary::-webkit-details-marker{display:none}
        .rill-toc-part summary:before{content:"▾";display:inline-block;margin-right:8px}
        .rill-toc-part:not([open]) summary:before{content:"▸"}
        .rill-toc-list{padding:10px 0 0 16px}
        .rill-toc-row{display:flex;gap:10px;align-items:flex-start;justify-content:space-between;padding:8px;border-radius:8px}
        .rill-toc-row a{text-decoration:none}
        .rill-toc-meta{font-size:12px;color:#666;margin-top:3px}
        .rill-toc-badge{font-size:12px;padding:4px 10px;border-radius:999px;border:1px solid #ddd;white-space:nowrap}
        .rill-toc-empty{font-size:12px;color:#777;padding:6px 0}
      </style>
    <?php
    return ob_get_clean();
  }

  private static function render_toc_chapter_row($ch) {
    $ch_id = is_object($ch) ? (int) $ch->ID : (int) $ch;
    $title = get_the_title($ch_id);
    $pages = get_field('pages_range', $ch_id);

    $access_type = (string) get_field('access_type', $ch_id);
    $is_free = ($access_type === 'free');
    $has_access = self::user_has_access_to_chapter($ch_id);

    $label = $is_free ? 'Free access' : ($has_access ? 'Purchased' : 'Restricted access');

    ob_start(); ?>
      <div class="rill-toc-row">
        <div>
          <a href="<?php echo esc_url(get_permalink($ch_id)); ?>">
            <strong style="color:#0b57d0"><?php echo esc_html($title); ?></strong>
          </a>
          <?php if ($pages): ?><div class="rill-toc-meta">Pages: <?php echo esc_html($pages); ?></div><?php endif; ?>
        </div>
        <span class="rill-toc-badge"><?php echo esc_html($label); ?></span>
      </div>
    <?php
    return ob_get_clean();
  }

  public static function remember_guest_email_and_order($order_id) {
    if (!$order_id) return;
    $order = wc_get_order($order_id);
    if (!$order) return;

    $email = $order->get_billing_email();
    if ($email && is_email($email)) {
      @setcookie(self::COOKIE_EMAIL, $email, time() + (30 * DAY_IN_SECONDS), COOKIEPATH, COOKIE_DOMAIN, is_ssl(), true);
      $_COOKIE[self::COOKIE_EMAIL] = $email;
    }

    if (function_exists('WC') && WC()->session) {
      WC()->session->set('rill_last_order_id', (int) $order_id);
    }
  }

  public static function render_thankyou_chapter_downloads($order_id) {
  if (!$order_id) return;
  $order = wc_get_order($order_id);
  if (!$order) return;

  $items_html = '';

  foreach ($order->get_items() as $item) {
    $product_id = (int) $item->get_product_id();
    if (!$product_id) continue;

    // Only our chapter products
    if (!self::is_chapter_product($product_id)) continue;

    $chapter_id = (int) get_post_meta($product_id, self::META_LINKED_CHAPTER_ID, true);
    if (!$chapter_id) continue;

    $pdf_full = (string) get_field('pdf_full', $chapter_id);
    if (!$pdf_full) continue;

    $download_url = add_query_arg([
      'rill_chapter_download' => $chapter_id,
      'order_id' => $order_id,
      'key' => $order->get_order_key(),
      'email' => $order->get_billing_email(),
    ], home_url('/'));

    $items_html .= '<div style="margin:0 0 12px">';
    $items_html .= '<strong>' . esc_html(get_the_title($chapter_id)) . '</strong><br>';
    $items_html .= '<a class="button" href="' . esc_url($download_url) . '">Download Chapter PDF</a>';
    $items_html .= '</div>';
  }

  if ($items_html) {
    // Optional: hide Woo broken downloads block
    echo '<style>.woocommerce-order-downloads{display:none!important}</style>';

    echo '<div style="margin-top:18px;padding:16px;border:1px solid #e5e5e5;border-radius:12px">';
    echo '<h3 style="margin:0 0 12px">Your Chapter Download</h3>';
    echo $items_html;
    echo '</div>';
  }
}

public static function email_chapter_downloads($order, $sent_to_admin, $plain_text, $email) {
  if ($sent_to_admin || !$order) return;

  $items_html = '';

  foreach ($order->get_items() as $item) {
    $product_id = (int) $item->get_product_id();
    if (!$product_id) continue;

    if (!self::is_chapter_product($product_id)) continue;

    $chapter_id = (int) get_post_meta($product_id, self::META_LINKED_CHAPTER_ID, true);
    if (!$chapter_id) continue;

    $pdf_full = (string) get_field('pdf_full', $chapter_id);
    if (!$pdf_full) continue;

    $download_url = add_query_arg([
      'rill_chapter_download' => $chapter_id,
      'order_id' => $order->get_id(),
      'key' => $order->get_order_key(),
      'email' => $order->get_billing_email(),
    ], home_url('/'));

    $items_html .= '<p><strong>' . esc_html(get_the_title($chapter_id)) . '</strong><br>';
    $items_html .= '<a href="' . esc_url($download_url) . '">Download Chapter PDF</a></p>';
  }

  if ($items_html) {
    echo '<h3>Your Chapter Download</h3>';
    echo $items_html;
  }
}


  public static function user_bought_product($product_id) {
    $product_id = (int) $product_id;
    if ($product_id <= 0) return false;
    if (!function_exists('wc_customer_bought_product')) return false;

    if (is_user_logged_in()) {
      $uid = get_current_user_id();
      $user = get_user_by('id', $uid);
      if ($user && !empty($user->user_email)) {
        return wc_customer_bought_product($user->user_email, $uid, $product_id);
      }
      return false;
    }

    $email = isset($_COOKIE[self::COOKIE_EMAIL]) ? sanitize_email($_COOKIE[self::COOKIE_EMAIL]) : '';
    if ($email && is_email($email)) {
      $orders = wc_get_orders([
        'limit' => 25,
        'billing_email' => $email,
        'status' => ['processing', 'completed'],
        'orderby' => 'date',
        'order' => 'DESC',
        'return' => 'objects',
      ]);

      foreach ($orders as $order) {
        foreach ($order->get_items() as $item) {
          if ((int) $item->get_product_id() === $product_id) return true;
        }
      }
    }

    if (function_exists('WC') && WC()->session) {
      $last = (int) WC()->session->get('rill_last_order_id');
      if ($last) {
        $order = wc_get_order($last);
        if ($order) {
          foreach ($order->get_items() as $item) {
            if ((int) $item->get_product_id() === $product_id) return true;
          }
        }
      }
    }

    return false;
  }

  public static function user_has_access_to_chapter($chapter_id) {
    $access_type = (string) get_field('access_type', $chapter_id);
    if ($access_type === 'free') return true;

    $book_id = self::get_chapter_book_id($chapter_id);
    $chapter_product_id = self::get_chapter_product_id($chapter_id);

    if ($book_id && self::user_bought_product($book_id)) return true;
    if ($chapter_product_id && self::user_bought_product($chapter_product_id)) return true;

    return false;
  }

  public static function sync_chapter_product_on_save($post_id, $post, $update) {
    if (!is_object($post)) return;
    if ($post->post_type !== self::chapter_cpt()) return;
    if (wp_is_post_revision($post_id) || wp_is_post_autosave($post_id)) return;
    self::sync_chapter_product($post_id);
  }

  public static function sync_chapter_product_on_acf_save($post_id) {
    if ((string)$post_id === 'options') return;
    if (get_post_type($post_id) !== self::chapter_cpt()) return;
    self::sync_chapter_product((int) $post_id);
  }

  private static function sync_chapter_product($chapter_id) {
    if (!class_exists('WooCommerce')) return;

    $access = (string) get_field('access_type', $chapter_id);
    if ($access !== 'paid') return;

    $price = (float) get_field('chapter_price', $chapter_id);
    if ($price <= 0) return;

    $book_id = self::get_chapter_book_id($chapter_id);
    $chapter_title = (string) get_post_field('post_title', $chapter_id);

    $existing_product_id = (int) get_field('chapter_product_id', $chapter_id);
    // ===== Prevent 1 product being shared by multiple chapters (common after duplicating a chapter) =====
if ($existing_product_id) {

  // If the product is already linked to a different chapter, detach it from this chapter
  $linked = (int) get_post_meta($existing_product_id, self::META_LINKED_CHAPTER_ID, true);
  if ($linked && $linked !== (int) $chapter_id) {
    $existing_product_id = 0;
    update_field('chapter_product_id', 0, $chapter_id);
  } else {

    // Extra safety: if more than 1 chapter points to the same product, force a new product for this chapter
    $dupes = get_posts([
      'post_type'      => self::chapter_cpt(),
      'posts_per_page' => 2,
      'fields'         => 'ids',
      'meta_key'       => 'chapter_product_id',
      'meta_value'     => (int) $existing_product_id,
    ]);

    if (is_array($dupes) && count($dupes) > 1) {
      $existing_product_id = 0;
      update_field('chapter_product_id', 0, $chapter_id);
    }
  }
}


    if ($existing_product_id && $book_id && $existing_product_id === $book_id) {
      $existing_product_id = 0;
      update_field('chapter_product_id', 0, $chapter_id);
    }

    if ($existing_product_id && get_post_type($existing_product_id) === 'product') {
      if (!self::is_chapter_product($existing_product_id)) return;

      $product = wc_get_product($existing_product_id);
      if (!$product) return;

      $product->set_name($chapter_title);
      $product->set_regular_price($price);
      $product->set_catalog_visibility('hidden');
      $product->set_virtual(true);
      $product->set_sold_individually(true);

      //aik yahan update kr rha:

      // ===== Make chapter product downloadable (Woo will show download on Thank You + emails) =====
$pdf_full = (string) get_field('pdf_full', $chapter_id);
$pdf_full = strtok($pdf_full, '?'); // removes ?ver= etc, helps stability


if ($pdf_full) {
  $product->set_downloadable(true);
  $product->set_virtual(true);
  $product->set_download_limit(-1);
  $product->set_download_expiry(-1);

  // Build Woo download object
  $download = new WC_Product_Download();
  $download->set_name('Chapter PDF');
  $download->set_file($pdf_full);

  // Unique download ID (must be stable)
 $download_id = 'rill_chapter_pdf_' . (int) $chapter_id;


  $downloads = [$download_id => $download];
  $product->set_downloads($downloads);
} else {
  // If no full PDF is set, keep it non-downloadable
  $product->set_downloadable(false);
  $product->set_downloads([]);
}


      $product->save();

      update_post_meta($existing_product_id, self::META_LINKED_CHAPTER_ID, (int) $chapter_id);
      self::apply_wc_visibility_excludes($existing_product_id);
      return;
    }

    $product = new WC_Product_Simple();
    $product->set_name($chapter_title);
    $product->set_status('publish');
    $product->set_catalog_visibility('hidden');
    $product->set_virtual(true);
    $product->set_sold_individually(true);
    $product->set_regular_price($price);

    ///aik yahan add kr rha:

    // ===== Make chapter product downloadable (Woo will show download on Thank You + emails) =====
$pdf_full = (string) get_field('pdf_full', $chapter_id);
$pdf_full = strtok($pdf_full, '?'); // removes ?ver= etc, helps stability


if ($pdf_full) {
  $product->set_downloadable(true);
  $product->set_virtual(true);
  $product->set_download_limit(-1);
  $product->set_download_expiry(-1);

  // Build Woo download object
  $download = new WC_Product_Download();
  $download->set_name('Chapter PDF');
  $download->set_file($pdf_full);

  // Unique download ID (must be stable)
  $download_id = 'rill_chapter_pdf_' . (int) $chapter_id;


  $downloads = [$download_id => $download];
  $product->set_downloads($downloads);
} else {
  // If no full PDF is set, keep it non-downloadable
  $product->set_downloadable(false);
  $product->set_downloads([]);
}



    $new_id = $product->save();
    if ($new_id) {
      update_post_meta($new_id, self::META_IS_CHAPTER_PRODUCT, '1');
      update_post_meta($new_id, self::META_LINKED_CHAPTER_ID, (int) $chapter_id);
      update_field('chapter_product_id', (int) $new_id, $chapter_id);
      self::apply_wc_visibility_excludes($new_id);
    }
  }

  private static function apply_wc_visibility_excludes($product_id) {
    if (!taxonomy_exists('product_visibility')) return;
    wp_set_object_terms((int)$product_id, ['exclude-from-catalog', 'exclude-from-search'], 'product_visibility', true);
  }

  public static function is_chapter_product($product_id) {
    return get_post_meta((int)$product_id, self::META_IS_CHAPTER_PRODUCT, true) === '1';
  }

  public static function exclude_chapter_products_from_shop_queries($q) {
    if (is_admin() || !$q->is_main_query()) return;
    if (function_exists('is_shop') && (is_shop() || is_product_category() || is_product_tag())) {
      $meta_query = (array) $q->get('meta_query');
      $meta_query[] = [
        'key' => self::META_IS_CHAPTER_PRODUCT,
        'compare' => 'NOT EXISTS',
      ];
      $q->set('meta_query', $meta_query);
    }
  }

  public static function filter_cart_item_name($name, $cart_item, $cart_item_key) {
    if (empty($cart_item['product_id'])) return $name;
    $pid = (int) $cart_item['product_id'];
    if (!self::is_chapter_product($pid)) return $name;

    $chapter_id = (int) get_post_meta($pid, self::META_LINKED_CHAPTER_ID, true);
    if ($chapter_id) {
      $t = (string) get_post_field('post_title', $chapter_id);
      if ($t) return esc_html($t);
    }
    return $name;
  }

  public static function sc_chapter_badge() {
    if (!is_singular(self::chapter_cpt())) return '';
    $chapter_id = get_the_ID();

    $access_type = (string) get_field('access_type', $chapter_id);
    $has_access = self::user_has_access_to_chapter($chapter_id);

    if ($access_type === 'free') $text = 'Free access';
    else $text = $has_access ? 'Purchased' : 'Restricted access';

    return '<div class="rill-access-badge" style="display:inline-block;padding:6px 10px;border:1px solid #ddd;border-radius:999px;font-size:12px;">'
      . esc_html($text) .
    '</div>';
  }

  public static function sc_chapter_buy_or_download() {
    if (!is_singular(self::chapter_cpt())) return '';
    $chapter_id = get_the_ID();

    $has_access = self::user_has_access_to_chapter($chapter_id);

    $preview_url = (string) get_field('pdf_preview', $chapter_id);
    $full_url    = (string) get_field('pdf_full', $chapter_id);

    $out = '';

    if ($has_access && !empty($full_url)) {
      $download_link = add_query_arg(['rill_chapter_download' => $chapter_id], home_url('/'));
      $out .= '<a class="elementor-button elementor-size-sm" href="'.esc_url($download_link).'">Download PDF</a>';
    } else {
      $access_type = (string) get_field('access_type', $chapter_id);
      if ($access_type === 'paid') {
        $chapter_product_id = self::get_chapter_product_id($chapter_id);

        if ($chapter_product_id > 0) {
          $product = wc_get_product($chapter_product_id);
          if ($product) {
            $price_html = $product->get_price_html();
            $add_to_cart = esc_url(add_query_arg(['add-to-cart' => $chapter_product_id], wc_get_cart_url()));

            $out .= '<div style="margin:10px 0;">Price: '. wp_kses_post($price_html) .'</div>';
            $out .= '<a class="elementor-button elementor-size-sm" href="'.$add_to_cart.'">Add to cart</a>';
          }
        } else {
          $out .= '<div>Chapter product not linked yet.</div>';
        }
      }
    }

    if (!empty($preview_url)) {
      $out .= '<div style="margin-top:10px;"><a href="'.esc_url($preview_url).'" target="_blank" rel="noopener">Open preview PDF</a></div>';
    }

    return $out;
  }

  public static function sc_chapter_pdf_preview() {
    if (!is_singular(self::chapter_cpt())) return '';
    $chapter_id = get_the_ID();

    $src = add_query_arg(['rill_chapter_embed' => $chapter_id], home_url('/'));
    return '<div class="rill-pdf-frame" style="width:100%;height:700px;border:1px solid #eee;border-radius:10px;overflow:hidden;"><iframe src="'.esc_url($src).'" style="width:100%;height:100%;border:0;"></iframe></div>';
  }

  public static function sc_chapter_nav() {
    if (!is_singular(self::chapter_cpt())) return '';
    $chapter_id = get_the_ID();
    $book_id = self::get_chapter_book_id($chapter_id);
    if (!$book_id) return '';

    $chapters = self::get_book_chapters($book_id);
    if (!$chapters) return '';

    $ids = array_map(function($p){ return $p->ID; }, $chapters);
    $index = array_search($chapter_id, $ids, true);
    if ($index === false) return '';

    $prev_id = $ids[$index - 1] ?? 0;
    $next_id = $ids[$index + 1] ?? 0;

    $out = '<div class="rill-ch-nav" style="display:flex;gap:10px;flex-wrap:wrap;margin:12px 0;">';
    if ($prev_id) $out .= '<a class="elementor-button elementor-size-sm" href="'.esc_url(get_permalink($prev_id)).'">Previous chapter</a>';
    if ($next_id) $out .= '<a class="elementor-button elementor-size-sm" href="'.esc_url(get_permalink($next_id)).'">Next chapter</a>';
    $out .= '</div>';

    return $out;
  }

  public static function sc_buy_full_book() {
    if (!is_singular(self::chapter_cpt())) return '';
    $chapter_id = get_the_ID();
    $book_id = self::get_chapter_book_id($chapter_id);
    if (!$book_id) return '';

    $link = esc_url(add_query_arg(['add-to-cart' => $book_id], wc_get_cart_url()));
    return '<a class="elementor-button elementor-size-sm" href="'.$link.'">Buy complete book</a>';
  }

  public static function sc_citation_button($atts) {
    if (!is_singular(self::chapter_cpt())) return '';
    $atts = shortcode_atts(['format' => 'bib'], $atts);
    $chapter_id = get_the_ID();

    $link = add_query_arg([
      'rill_cite' => $chapter_id,
      'rill_cite_format' => sanitize_text_field($atts['format']),
    ], home_url('/'));

    $label = strtolower($atts['format']) === 'ris' ? 'Download RIS' : 'Download BibTeX';
    return '<a class="elementor-button elementor-size-sm" href="'.esc_url($link).'">'.$label.'</a>';
  }

  public static function sc_chapter_book_info() {
    if (!is_singular(self::chapter_cpt())) return '';

    $book = get_field('book_product');
    if (!$book) return '';
    $book_id = is_object($book) ? $book->ID : (int) $book;

    $book_title = get_the_title($book_id);

    $thumb_id = get_post_thumbnail_id($book_id);
    $book_image = $thumb_id ? wp_get_attachment_image($thumb_id, 'large', false, ['class' => 'rill-book-image']) : '';

    $additional_info = get_field('about_author_copy', $book_id);

    ob_start(); ?>
      <div class="rill-bookinfo-wrapper">
        <?php if ($book_image): ?><div class="rill-bookinfo-image"><?php echo $book_image; ?></div><?php endif; ?>

        <div class="rill-bookinfo-content">
          <?php if ($book_title): ?><h3 class="rill-bookinfo-title"><?php echo esc_html($book_title); ?></h3><?php endif; ?>
          <?php if ($additional_info): ?><div class="rill-bookinfo-additional"><?php echo wp_kses_post($additional_info); ?></div><?php endif; ?>
        </div>
      </div>

      <style>
        .rill-bookinfo-wrapper{display:flex;gap:24px;align-items:flex-start;margin:24px 0}
        .rill-bookinfo-image img{width:170px;height:auto;border-radius:12px}
        .rill-bookinfo-title{margin:0 0 12px;font-size:20px;line-height:1.3}
        .rill-bookinfo-additional{font-size:14px;line-height:1.7;opacity:.95}
        @media (max-width:768px){.rill-bookinfo-wrapper{flex-direction:column}.rill-bookinfo-image img{width:100%}}
      </style>
    <?php
    return ob_get_clean();
  }

  public static function register_query_vars($vars) {
    $vars[] = 'rill_chapter_download';
    $vars[] = 'rill_cite';
    $vars[] = 'rill_cite_format';
    $vars[] = 'rill_chapter_embed';
    return $vars;
  }

  private static function download_allowed_for_order($chapter_id) {

  // Logged in users: keep existing logic
  if (is_user_logged_in() && self::user_has_access_to_chapter($chapter_id)) {
    return true;
  }

  // Guest: validate using order info in URL
  $order_id = isset($_GET['order_id']) ? (int) $_GET['order_id'] : 0;
  $key      = isset($_GET['key']) ? sanitize_text_field(wp_unslash($_GET['key'])) : '';
  $email    = isset($_GET['email']) ? sanitize_email(wp_unslash($_GET['email'])) : '';

  if (!$order_id || !$key || !$email) return false;

  $order = wc_get_order($order_id);
  if (!$order) return false;

  if ($order->get_order_key() !== $key) return false;

  $order_email = $order->get_billing_email();
  if (!is_email($order_email) || strtolower($order_email) !== strtolower($email)) return false;

  if (!in_array($order->get_status(), ['processing','completed'], true)) return false;

  $chapter_product_id = self::get_chapter_product_id($chapter_id);
  $book_id = self::get_chapter_book_id($chapter_id);

  foreach ($order->get_items() as $item) {
    $pid = (int) $item->get_product_id();

    // Allow if they bought chapter product
    if ($chapter_product_id && $pid === (int) $chapter_product_id) return true;

    // Also allow if they bought full book
    if ($book_id && $pid === (int) $book_id) return true;
  }

  return false;
}


  public static function handle_endpoints() {
    $embed_id = isset($_GET['rill_chapter_embed']) ? (int) $_GET['rill_chapter_embed'] : 0;
    if ($embed_id > 0) {
      $pdf = self::user_has_access_to_chapter($embed_id) ? (string) get_field('pdf_full', $embed_id) : (string) get_field('pdf_preview', $embed_id);
      if (!$pdf) wp_die('PDF not available');
      wp_redirect($pdf);
      exit;
    }

    $download_id = (int) get_query_var('rill_chapter_download');
    if ($download_id > 0) {
      if (!self::download_allowed_for_order($download_id)) {
  wp_die('You do not have access to this chapter. Please purchase the chapter or the full book.');
}


      $file_url = (string) get_field('pdf_full', $download_id);
      if (empty($file_url)) wp_die('No file found.');

      $uploads = wp_get_upload_dir();
      if (strpos($file_url, $uploads['baseurl']) === 0) {
        $rel = str_replace($uploads['baseurl'], '', $file_url);
        $path = $uploads['basedir'] . $rel;

        if (!file_exists($path)) wp_die('File missing on server.');

        nocache_headers();
        header('Content-Type: application/pdf');
        header('Content-Disposition: attachment; filename="'. sanitize_file_name(get_the_title($download_id)) .'.pdf"');
        header('Content-Length: ' . filesize($path));
        readfile($path);
        exit;
      }

      wp_redirect($file_url);
      exit;
    }

    $cite_id = (int) get_query_var('rill_cite');
    if ($cite_id > 0) {
      $format = get_query_var('rill_cite_format');
      $format = $format ? strtolower((string)$format) : 'bib';

      $title = get_the_title($cite_id);
      $author = (string) get_field('author_name', $cite_id);
      $doi = (string) get_field('doi_url', $cite_id);
      $book_id = self::get_chapter_book_id($cite_id);
      $book_title = $book_id ? get_the_title($book_id) : '';

      if ($format === 'ris') {
        $content =
          "TY  - CHAP\n" .
          "TI  - {$title}\n" .
          ($author ? "AU  - {$author}\n" : "") .
          ($book_title ? "T2  - {$book_title}\n" : "") .
          ($doi ? "DO  - {$doi}\n" : "") .
          "ER  - \n";

        nocache_headers();
        header('Content-Type: application/x-research-info-systems');
        header('Content-Disposition: attachment; filename="citation-'.$cite_id.'.ris"');
        echo $content;
        exit;
      }

      $key = 'chapter'.$cite_id;
      $bib =
        "@incollection{{$key},\n" .
        "  title = {" . $title . "},\n" .
        ($author ? "  author = {" . $author . "},\n" : "") .
        ($book_title ? "  booktitle = {" . $book_title . "},\n" : "") .
        ($doi ? "  doi = {" . $doi . "},\n" : "") .
        "}\n";

      nocache_headers();
      header('Content-Type: text/plain');
      header('Content-Disposition: attachment; filename="citation-'.$cite_id.'.bib"');
      echo $bib;
      exit;
    }
  }
}

add_action('plugins_loaded', ['RILL_Chapters_Suite', 'init']);
