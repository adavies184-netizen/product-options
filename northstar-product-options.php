<?php
/**
 * Plugin Name: Northstar Product Options
 * Description: Replaces WooCommerce variation dropdowns with selectable product option cards. Includes Gutenberg/FSE block, shortcode, classic-theme automatic placement and AJAX side-cart compatibility.
 * Version: 0.1.1
 * Author: Northstar Ridge Limited
 * Requires at least: 6.3
 * Requires PHP: 7.4
 * WC requires at least: 7.8
 * WC tested up to: 10.0
 * Text Domain: northstar-product-options
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Northstar_Product_Options {
	const VERSION = '0.1.1';
	const META_ENABLED = '_nspo_enabled';
	const META_PLACEMENT = '_nspo_placement';
	const META_DEFAULT_VARIATION = '_nspo_default_variation';
	const META_CARD_TITLE = '_nspo_card_title';
	const META_BADGE = '_nspo_badge';

	private static $instance = null;
	private $rendered_products = array();

	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		add_action( 'before_woocommerce_init', array( $this, 'declare_compatibility' ) );
		add_action( 'plugins_loaded', array( $this, 'init' ) );
	}

	public function declare_compatibility() {
		if ( class_exists( '\Automattic\WooCommerce\Utilities\FeaturesUtil' ) ) {
			\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', __FILE__, true );
			\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'cart_checkout_blocks', __FILE__, true );
		}
	}

	public function init() {
		if ( ! class_exists( 'WooCommerce' ) ) {
			add_action( 'admin_notices', array( $this, 'woocommerce_missing_notice' ) );
			return;
		}

		add_action( 'wp_enqueue_scripts', array( $this, 'register_frontend_assets' ) );
		add_action( 'init', array( $this, 'register_block' ) );
		add_shortcode( 'northstar_product_options', array( $this, 'shortcode' ) );

		add_action( 'woocommerce_product_options_general_product_data', array( $this, 'product_fields' ) );
		add_action( 'woocommerce_admin_process_product_object', array( $this, 'save_product_fields' ) );
		add_action( 'woocommerce_product_after_variable_attributes', array( $this, 'variation_fields' ), 10, 3 );
		add_action( 'woocommerce_save_product_variation', array( $this, 'save_variation_fields' ), 10, 2 );

		add_action( 'wp', array( $this, 'setup_classic_replacement' ) );
		add_filter( 'woocommerce_get_price_html', array( $this, 'filter_product_price_html' ), 20, 2 );
		add_filter( 'woocommerce_available_variation', array( $this, 'add_variation_payload' ), 10, 3 );

		add_action( 'wp_ajax_nspo_add_to_cart', array( $this, 'ajax_add_to_cart' ) );
		add_action( 'wp_ajax_nopriv_nspo_add_to_cart', array( $this, 'ajax_add_to_cart' ) );
	}

	public function woocommerce_missing_notice() {
		echo '<div class="notice notice-error"><p>' . esc_html__( 'Northstar Product Options requires WooCommerce to be installed and active.', 'northstar-product-options' ) . '</p></div>';
	}

	public function register_frontend_assets() {
		wp_register_style(
			'northstar-product-options',
			plugins_url( 'assets/product-options.css', __FILE__ ),
			array(),
			self::VERSION
		);
		wp_register_script(
			'northstar-product-options',
			plugins_url( 'assets/product-options.js', __FILE__ ),
			array( 'jquery', 'wc-cart-fragments' ),
			self::VERSION,
			true
		);
		wp_localize_script(
			'northstar-product-options',
			'NSPO_DATA',
			array(
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'nonce' => wp_create_nonce( 'nspo_add_to_cart' ),
				'addingText' => __( 'Adding…', 'northstar-product-options' ),
				'errorText' => __( 'This option could not be added to your cart. Please try again.', 'northstar-product-options' ),
			)
		);
	}

	public function register_block() {
		$editor_handle = 'nspo-block-editor';
		wp_register_script(
			$editor_handle,
			plugins_url( 'blocks/product-options/editor.js', __FILE__ ),
			array( 'wp-blocks', 'wp-element', 'wp-components', 'wp-block-editor', 'wp-server-side-render', 'wp-i18n' ),
			self::VERSION,
			true
		);

		register_block_type(
			__DIR__ . '/blocks/product-options',
			array(
				'editor_script' => $editor_handle,
				'render_callback' => array( $this, 'render_block' ),
			)
		);
	}

	public function product_fields() {
		global $post;
		if ( ! $post ) {
			return;
		}

		echo '<div class="options_group show_if_variable">';
		woocommerce_wp_checkbox(
			array(
				'id' => self::META_ENABLED,
				'label' => __( 'Northstar option cards', 'northstar-product-options' ),
				'description' => __( 'Enable the custom variation card interface for this product.', 'northstar-product-options' ),
			)
		);
		woocommerce_wp_select(
			array(
				'id' => self::META_PLACEMENT,
				'label' => __( 'Display mode', 'northstar-product-options' ),
				'options' => array(
					'auto' => __( 'Automatic replacement (classic themes)', 'northstar-product-options' ),
					'manual' => __( 'Block or shortcode only', 'northstar-product-options' ),
				),
				'description' => __( 'Use Block or shortcode only for custom Gutenberg/FSE product templates.', 'northstar-product-options' ),
				'desc_tip' => true,
			)
		);

		$product = wc_get_product( $post->ID );
		$options = array( '' => __( 'First available variation', 'northstar-product-options' ) );
		if ( $product && $product->is_type( 'variable' ) ) {
			foreach ( $product->get_children() as $variation_id ) {
				$variation = wc_get_product( $variation_id );
				if ( $variation ) {
					$options[ $variation_id ] = $this->variation_label( $variation );
				}
			}
		}
		woocommerce_wp_select(
			array(
				'id' => self::META_DEFAULT_VARIATION,
				'label' => __( 'Default option', 'northstar-product-options' ),
				'options' => $options,
				'description' => __( 'The card selected when the product page first loads.', 'northstar-product-options' ),
				'desc_tip' => true,
			)
		);
		echo '</div>';
	}

	public function save_product_fields( $product ) {
		if ( ! $product->is_type( 'variable' ) ) {
			return;
		}
		$product->update_meta_data( self::META_ENABLED, isset( $_POST[ self::META_ENABLED ] ) ? 'yes' : 'no' );
		$placement = isset( $_POST[ self::META_PLACEMENT ] ) ? sanitize_key( wp_unslash( $_POST[ self::META_PLACEMENT ] ) ) : 'manual';
		$product->update_meta_data( self::META_PLACEMENT, in_array( $placement, array( 'auto', 'manual' ), true ) ? $placement : 'manual' );
		$default_variation = isset( $_POST[ self::META_DEFAULT_VARIATION ] ) ? absint( $_POST[ self::META_DEFAULT_VARIATION ] ) : 0;
		$product->update_meta_data( self::META_DEFAULT_VARIATION, $default_variation );
	}

	public function variation_fields( $loop, $variation_data, $variation ) {
		woocommerce_wp_text_input(
			array(
				'id' => self::META_CARD_TITLE . '[' . $loop . ']',
				'name' => self::META_CARD_TITLE . '[' . $loop . ']',
				'value' => get_post_meta( $variation->ID, self::META_CARD_TITLE, true ),
				'label' => __( 'Card title', 'northstar-product-options' ),
				'placeholder' => __( 'Example: Buy 2 Bottles', 'northstar-product-options' ),
				'wrapper_class' => 'form-row form-row-first',
			)
		);
		woocommerce_wp_text_input(
			array(
				'id' => self::META_BADGE . '[' . $loop . ']',
				'name' => self::META_BADGE . '[' . $loop . ']',
				'value' => get_post_meta( $variation->ID, self::META_BADGE, true ),
				'label' => __( 'Badge', 'northstar-product-options' ),
				'placeholder' => __( 'Most Popular', 'northstar-product-options' ),
				'wrapper_class' => 'form-row form-row-last',
			)
		);
	}

	public function save_variation_fields( $variation_id, $loop ) {
		$title_values = isset( $_POST[ self::META_CARD_TITLE ] ) ? (array) wp_unslash( $_POST[ self::META_CARD_TITLE ] ) : array();
		$badge_values = isset( $_POST[ self::META_BADGE ] ) ? (array) wp_unslash( $_POST[ self::META_BADGE ] ) : array();
		update_post_meta( $variation_id, self::META_CARD_TITLE, isset( $title_values[ $loop ] ) ? sanitize_text_field( $title_values[ $loop ] ) : '' );
		update_post_meta( $variation_id, self::META_BADGE, isset( $badge_values[ $loop ] ) ? sanitize_text_field( $badge_values[ $loop ] ) : '' );
	}

	public function setup_classic_replacement() {
		if ( ! is_product() ) {
			return;
		}
		$product = wc_get_product( get_queried_object_id() );
		if ( ! $this->is_enabled_product( $product ) || 'auto' !== $product->get_meta( self::META_PLACEMENT ) ) {
			return;
		}

		remove_action( 'woocommerce_single_product_summary', 'woocommerce_template_single_price', 10 );
		remove_action( 'woocommerce_single_product_summary', 'woocommerce_template_single_add_to_cart', 30 );
		add_action( 'woocommerce_single_product_summary', array( $this, 'render_auto_selector' ), 30 );
	}

	public function filter_product_price_html( $price_html, $product ) {
		if ( is_admin() && ! wp_doing_ajax() ) {
			return $price_html;
		}
		if ( is_product() && $this->is_enabled_product( $product ) && 'auto' === $product->get_meta( self::META_PLACEMENT ) ) {
			return '';
		}
		return $price_html;
	}

	public function render_auto_selector() {
		global $product;
		if ( $product ) {
			echo $this->render_selector( $product ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		}
	}

	public function shortcode( $atts ) {
		$atts = shortcode_atts(
			array(
				'product_id' => 0,
				'layout' => 'columns',
				'button_text' => '',
				'button_bg' => '',
			),
			$atts,
			'northstar_product_options'
		);
		$product = $this->resolve_product( absint( $atts['product_id'] ) );
		if ( ! $product ) {
			return '';
		}
		return $this->render_selector(
			$product,
			array(
				'layout' => sanitize_key( $atts['layout'] ),
				'buttonText' => sanitize_text_field( $atts['button_text'] ),
				'buttonBg' => sanitize_hex_color( $atts['button_bg'] ),
			)
		);
	}

	public function render_block( $attributes, $content = '', $block = null ) {
		$product_id = isset( $attributes['productId'] ) ? absint( $attributes['productId'] ) : 0;
		$product = $this->resolve_product( $product_id );
		if ( ! $product ) {
			if ( is_admin() || wp_is_json_request() ) {
				return '<div class="nspo-editor-placeholder"><strong>' . esc_html__( 'Northstar Product Options', 'northstar-product-options' ) . '</strong><br>' . esc_html__( 'The variation cards will use the product being viewed.', 'northstar-product-options' ) . '</div>';
			}
			return '';
		}
		return $this->render_selector(
			$product,
			array(
				'layout' => isset( $attributes['layout'] ) ? sanitize_key( $attributes['layout'] ) : 'columns',
				'buttonText' => isset( $attributes['buttonText'] ) ? sanitize_text_field( $attributes['buttonText'] ) : '',
				'buttonBg' => isset( $attributes['buttonBg'] ) ? sanitize_hex_color( $attributes['buttonBg'] ) : '',
			)
		);
	}

	private function resolve_product( $product_id = 0 ) {
		if ( $product_id ) {
			return wc_get_product( $product_id );
		}
		global $product;
		if ( $product instanceof WC_Product ) {
			return $product;
		}
		$id = get_the_ID();
		return $id ? wc_get_product( $id ) : false;
	}

	private function is_enabled_product( $product ) {
		return $product instanceof WC_Product && $product->is_type( 'variable' ) && 'yes' === $product->get_meta( self::META_ENABLED );
	}

	private function render_selector( $product, $settings = array() ) {
		if ( ! $this->is_enabled_product( $product ) ) {
			return current_user_can( 'manage_woocommerce' ) ? '<div class="woocommerce-info">' . esc_html__( 'Enable Northstar option cards in this variable product’s settings.', 'northstar-product-options' ) . '</div>' : '';
		}

		$settings = wp_parse_args(
			$settings,
			array(
				'layout' => 'columns',
				'buttonText' => '',
				'buttonBg' => '',
			)
		);
		$layout = 'horizontal' === $settings['layout'] ? 'horizontal' : 'columns';
		$button_text = $settings['buttonText'] ? $settings['buttonText'] : $product->single_add_to_cart_text();
		$button_bg = sanitize_hex_color( $settings['buttonBg'] );

		$product_id = $product->get_id();
		if ( isset( $this->rendered_products[ $product_id ] ) ) {
			return '';
		}
		$this->rendered_products[ $product_id ] = true;

		$variations = array();
		foreach ( $product->get_children() as $variation_id ) {
			$variation = wc_get_product( $variation_id );
			if ( ! $variation || ! $variation->exists() || ! $variation->variation_is_visible() ) {
				continue;
			}
			$variations[] = $this->variation_data( $variation );
		}
		if ( empty( $variations ) ) {
			return '<p class="stock out-of-stock">' . esc_html__( 'No product options are currently available.', 'northstar-product-options' ) . '</p>';
		}

		$default_id = absint( $product->get_meta( self::META_DEFAULT_VARIATION ) );
		$available_ids = wp_list_pluck( $variations, 'id' );
		if ( ! in_array( $default_id, $available_ids, true ) ) {
			$default_id = 0;
			foreach ( $variations as $candidate ) {
				if ( $candidate['purchasable'] ) {
					$default_id = $candidate['id'];
					break;
				}
			}
		}
		$default = current( array_filter( $variations, function( $item ) use ( $default_id ) { return $item['id'] === $default_id; } ) );
		if ( ! $default ) {
			$default = $variations[0];
			$default_id = $default['id'];
		}

		wp_enqueue_style( 'northstar-product-options' );
		wp_enqueue_script( 'northstar-product-options' );

		ob_start();
		?>
		<div class="nspo nspo--<?php echo esc_attr( $layout ); ?>" data-product-id="<?php echo esc_attr( $product_id ); ?>" data-default-variation="<?php echo esc_attr( $default_id ); ?>"<?php echo $button_bg ? ' style="--nspo-button-bg:' . esc_attr( $button_bg ) . '"' : ''; ?>>
			<div class="nspo__cards" role="radiogroup" aria-label="<?php echo esc_attr__( 'Choose an option', 'northstar-product-options' ); ?>">
				<?php foreach ( $variations as $index => $item ) : ?>
					<button type="button" class="nspo__card<?php echo $item['id'] === $default_id ? ' is-selected' : ''; ?><?php echo ! $item['purchasable'] ? ' is-disabled' : ''; ?>" role="radio" aria-checked="<?php echo $item['id'] === $default_id ? 'true' : 'false'; ?>" data-variation='<?php echo esc_attr( wp_json_encode( $item ) ); ?>' <?php disabled( ! $item['purchasable'] ); ?>>
						<?php if ( $item['badge'] ) : ?><span class="nspo__badge"><?php echo esc_html( $item['badge'] ); ?></span><?php endif; ?>
						<span class="nspo__image-wrap"><?php echo wp_kses_post( $item['image'] ); ?></span>
						<span class="nspo__title"><?php echo esc_html( $item['title'] ); ?></span>
						<span class="nspo__prices">
							<?php if ( $item['on_sale'] && $item['regular_price_html'] ) : ?><span class="nspo__regular-price"><?php echo wp_kses_post( $item['regular_price_html'] ); ?></span><?php endif; ?>
							<span class="nspo__price"><?php echo wp_kses_post( $item['price_html'] ); ?></span>
						</span>
						<?php if ( $item['saving_label'] ) : ?><span class="nspo__saving"><?php echo esc_html( $item['saving_label'] ); ?></span><?php endif; ?>
						<?php if ( ! $item['purchasable'] ) : ?><span class="nspo__unavailable"><?php echo esc_html__( 'Unavailable', 'northstar-product-options' ); ?></span><?php endif; ?>
					</button>
				<?php endforeach; ?>
			</div>

			<div class="nspo__purchase-row">
				<span class="nspo__radio-mark" aria-hidden="true"></span>
				<span class="nspo__purchase-label"><?php echo esc_html__( 'One Time Purchase', 'northstar-product-options' ); ?></span>
				<span class="nspo__final-prices">
					<span class="nspo__final-regular"<?php echo $default['on_sale'] ? '' : ' hidden'; ?>><?php echo wp_kses_post( $default['regular_price_html'] ); ?></span>
					<span class="nspo__final-price"><?php echo wp_kses_post( $default['price_html'] ); ?></span>
				</span>
			</div>

			<input type="hidden" class="nspo__variation-id" value="<?php echo esc_attr( $default_id ); ?>">
			<button type="button" class="nspo__add-to-cart button alt" <?php disabled( ! $default['purchasable'] ); ?>><?php echo esc_html( $button_text ); ?></button>
			<div class="nspo__message" role="status" aria-live="polite"></div>
		</div>
		<?php
		return ob_get_clean();
	}

	private function variation_data( $variation ) {
		$display_price = (float) wc_get_price_to_display( $variation );
		$display_regular = (float) wc_get_price_to_display( $variation, array( 'price' => $variation->get_regular_price() ) );
		$on_sale = $variation->is_on_sale() && $display_regular > $display_price;
		$saving_label = '';
		if ( $on_sale && $display_regular > 0 ) {
			$amount = $display_regular - $display_price;
			$percent = round( ( $amount / $display_regular ) * 100 );
			$saving_label = sprintf( __( 'Save %1$s%% (%2$s)', 'northstar-product-options' ), $percent, wp_strip_all_tags( wc_price( $amount ) ) );
		}
		$image_id = $variation->get_image_id();
		if ( ! $image_id && $variation->get_parent_id() ) {
			$image_id = get_post_thumbnail_id( $variation->get_parent_id() );
		}
		$image = $image_id ? wp_get_attachment_image( $image_id, 'woocommerce_thumbnail', false, array( 'loading' => 'lazy' ) ) : wc_placeholder_img( 'woocommerce_thumbnail' );
		$title = $variation->get_meta( self::META_CARD_TITLE );
		if ( ! $title ) {
			$title = $this->variation_label( $variation );
		}
		return array(
			'id' => $variation->get_id(),
			'title' => $title,
			'badge' => $variation->get_meta( self::META_BADGE ),
			'image' => $image,
			'price_html' => wc_price( $display_price ),
			'regular_price_html' => $display_regular > 0 ? wc_price( $display_regular ) : '',
			'on_sale' => $on_sale,
			'saving_label' => $saving_label,
			'purchasable' => $variation->is_purchasable() && $variation->is_in_stock(),
			'attributes' => $variation->get_variation_attributes(),
		);
	}

	private function variation_label( $variation ) {
		$parts = array();
		foreach ( $variation->get_variation_attributes() as $name => $value ) {
			$taxonomy = str_replace( 'attribute_', '', $name );
			if ( taxonomy_exists( $taxonomy ) ) {
				$term = get_term_by( 'slug', $value, $taxonomy );
				$parts[] = $term && ! is_wp_error( $term ) ? $term->name : $value;
			} else {
				$parts[] = $value;
			}
		}
		$label = implode( ' / ', array_filter( $parts ) );
		return $label ?: sprintf( __( 'Option %d', 'northstar-product-options' ), $variation->get_id() );
	}

	public function add_variation_payload( $data, $product, $variation ) {
		if ( ! $this->is_enabled_product( $product ) ) {
			return $data;
		}
		$data['nspo_card_title'] = $variation->get_meta( self::META_CARD_TITLE );
		$data['nspo_badge'] = $variation->get_meta( self::META_BADGE );
		return $data;
	}

	public function ajax_add_to_cart() {
		check_ajax_referer( 'nspo_add_to_cart', 'nonce' );
		$product_id = isset( $_POST['product_id'] ) ? absint( $_POST['product_id'] ) : 0;
		$variation_id = isset( $_POST['variation_id'] ) ? absint( $_POST['variation_id'] ) : 0;
		$product = wc_get_product( $product_id );
		$variation = wc_get_product( $variation_id );

		if ( ! $this->is_enabled_product( $product ) || ! $variation || $variation->get_parent_id() !== $product_id || ! $variation->is_purchasable() || ! $variation->is_in_stock() ) {
			wp_send_json_error( array( 'message' => __( 'The selected option is unavailable.', 'northstar-product-options' ) ) );
		}

		$passed = apply_filters( 'woocommerce_add_to_cart_validation', true, $product_id, 1, $variation_id, $variation->get_variation_attributes() );
		if ( ! $passed ) {
			wp_send_json_error( array( 'message' => __( 'This option could not be added to the cart.', 'northstar-product-options' ) ) );
		}

		$cart_item_key = WC()->cart->add_to_cart( $product_id, 1, $variation_id, $variation->get_variation_attributes() );
		if ( ! $cart_item_key ) {
			wp_send_json_error( array( 'message' => __( 'This option could not be added to the cart.', 'northstar-product-options' ) ) );
		}

		do_action( 'woocommerce_ajax_added_to_cart', $product_id );
		if ( 'yes' === get_option( 'woocommerce_cart_redirect_after_add' ) ) {
			wc_add_to_cart_message( array( $product_id => 1 ), true );
		}

		ob_start();
		woocommerce_mini_cart();
		$mini_cart = ob_get_clean();
		$fragments = apply_filters(
			'woocommerce_add_to_cart_fragments',
			array( 'div.widget_shopping_cart_content' => '<div class="widget_shopping_cart_content">' . $mini_cart . '</div>' )
		);

		wp_send_json_success(
			array(
				'fragments' => $fragments,
				'cart_hash' => WC()->cart->get_cart_hash(),
				'cart_item_key' => $cart_item_key,
				'product_id' => $product_id,
				'variation_id' => $variation_id,
			)
		);
	}
}

Northstar_Product_Options::instance();
