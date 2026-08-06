=== Northstar Product Options ===
Contributors: northstar
Tags: woocommerce, variations, gutenberg, fse, product options
Requires at least: 6.3
Tested up to: 6.9
Requires PHP: 7.4
Stable tag: 0.1.1

A card-based WooCommerce variation selector for classic, Gutenberg and FSE product templates.

== Version 0.1.0 ==

* Three-or-more variation cards generated from a variable product.
* Live selected-price updates.
* Variation-specific card title and badge fields.
* Gutenberg/FSE dynamic block.
* Shortcode fallback: [northstar_product_options]
* Optional classic-theme replacement mode.
* AJAX add to cart with standard added_to_cart event for side-cart compatibility.

== Setup ==

1. Create a WooCommerce variable product and add variations such as 1 Bottle, 2 Bottles and 3 Bottles.
2. Enter a regular and/or sale price, SKU, stock and variation image for each option.
3. In Product data > General, enable Northstar option cards.
4. Choose Automatic replacement for a standard classic product template, or Block/shortcode only for a custom Gutenberg/FSE template.
5. Optionally enter a Card title and Badge inside each variation.
6. For FSE/Greenshift templates, add the Northstar Product Options block and omit WooCommerce's standard Product Price and Add to Cart blocks.
