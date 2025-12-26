=== Rill TOC Builder ===
Contributors: rill
Tags: toc, chapters, books, woocommerce, acf
Requires at least: 5.8
Tested up to: 6.6
Requires PHP: 7.4
Stable tag: 1.0.0
License: GPLv2 or later

Build book Table of Contents with per-book Parts and Chapters.

== Description ==
Admin: TOC Builder page where you:
- See only WooCommerce products tagged: chapters_cook
- Create Parts for the selected book (Parts are headings only)
- Assign chapters to Parts
- See live TOC preview

Front-end:
- Shortcode [rill_book_toc]
- Optional attribute: [rill_book_toc book_id="123" open_all="1"]

== Installation ==
1. Upload plugin zip and activate.
2. Tag your book products with: chapters_cook
3. Link each chapter to its book using ACF field: book_product
4. Go to WP Admin -> TOC Builder
5. Use shortcode [rill_book_toc] in Elementor if needed.
