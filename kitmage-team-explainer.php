<?php
/**
 * Plugin Name: KitMage Team Explainer
 * Plugin URI: https://kitmage.com
 * Description: Adds seat-aware product instructions and team membership shortcodes for WooCommerce Memberships for Teams.
 * Version: 1.2.0
 * Author: Mike@KitMage
 * Author URI: https://kitmage.com
 * Text Domain: kitmage-team-explainer
 * Requires PHP: 7.4
 * WC requires at least: 7.0
 */

defined( 'ABSPATH' ) || exit;

require_once plugin_dir_path( __FILE__ ) . 'includes/class-kitmage-team-seats-expansion.php';

final class KitMage_Team_Explainer {

	const META_KEY = '_kitmage_team_explainer_message';
	const VERSION  = '1.2.0';

	/** @var self|null */
	private static $instance = null;

	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	private function __construct() {
		add_action( 'woocommerce_product_options_general_product_data', array( $this, 'render_product_field' ) );
		add_action( 'woocommerce_admin_process_product_object', array( $this, 'save_product_field' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_script' ) );
		add_filter( 'woocommerce_available_variation', array( $this, 'add_variation_data' ), 20, 3 );
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_frontend_script' ) );
	}

	public function render_product_field() {
		global $product_object;

		$value = $product_object instanceof WC_Product ? $product_object->get_meta( self::META_KEY, true ) : '';

		woocommerce_wp_textarea_input(
			array(
				'id'            => self::META_KEY,
				'value'         => $value,
				'label'         => __( 'Team seat instructions', 'kitmage-team-explainer' ),
				'description'   => __( 'Shown on the product page for this team product. Placeholders: %max_seats%, %product_name%, and %variation_name%. Add a custom fallback after a pipe, for example %variation_name|Standard% or %max_seats|unlimited%.', 'kitmage-team-explainer' ),
				'desc_tip'      => true,
				'class'         => 'short',
				'wrapper_class' => 'kitmage-team-explainer-field',
			)
		);

		wp_nonce_field( 'kitmage_team_explainer_admin', '_kitmage_team_explainer_nonce' );
	}

	public function save_product_field( $product ) {
		if ( ! isset( $_POST['_kitmage_team_explainer_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['_kitmage_team_explainer_nonce'] ) ), 'kitmage_team_explainer_admin' ) ) {
			return;
		}

		if ( ! current_user_can( 'edit_post', $product->get_id() ) ) {
			return;
		}

		$value = isset( $_POST[ self::META_KEY ] ) ? wp_kses_post( wp_unslash( $_POST[ self::META_KEY ] ) ) : '';

		if ( '' === trim( $value ) ) {
			$product->delete_meta_data( self::META_KEY );
		} else {
			$product->update_meta_data( self::META_KEY, $value );
		}
	}

	public function enqueue_admin_script( $hook ) {
		if ( ! in_array( $hook, array( 'post.php', 'post-new.php' ), true ) || 'product' !== get_current_screen()->post_type ) {
			return;
		}

		wp_enqueue_script(
			'kitmage-team-explainer-admin',
			plugins_url( 'assets/admin.js', __FILE__ ),
			array( 'jquery' ),
			self::VERSION,
			true
		);
	}

	public function add_variation_data( $variation_data, $product, $variation ) {
		if ( '' === trim( (string) $product->get_meta( self::META_KEY, true ) ) ) {
			return $variation_data;
		}

		$variation_data['kitmage_team_max_member_count'] = $this->get_max_member_count( $variation );
		$variation_data['kitmage_team_variation_name']   = wp_strip_all_tags( $variation->get_name() );

		return $variation_data;
	}

	public function enqueue_frontend_script() {
		if ( ! is_product() ) {
			return;
		}

		$product = wc_get_product( get_queried_object_id() );

		if ( ! $product ) {
			return;
		}

		$message = $product->get_meta( self::META_KEY, true );

		if ( '' === trim( $message ) ) {
			return;
		}

		wp_enqueue_script( 'wc-add-to-cart-variation' );
		wp_enqueue_script(
			'kitmage-team-explainer',
			plugins_url( 'assets/frontend.js', __FILE__ ),
			array( 'jquery', 'wc-add-to-cart-variation' ),
			self::VERSION,
			true
		);

		$data = array(
			'productId'     => $product->get_id(),
			'productName'   => wp_strip_all_tags( $product->get_name() ),
			'message'       => wp_kses_post( $message ),
			'isVariable'    => $product->is_type( 'variable' ),
			'maxSeats'      => $product->is_type( 'variable' ) ? null : $this->get_max_member_count( $product ),
			'variationName' => '',
		);

		wp_add_inline_script( 'kitmage-team-explainer', 'window.kitMageTeamExplainer=' . wp_json_encode( $data ) . ';', 'before' );
	}

	private function get_max_member_count( $product ) {
		$class = '\\SkyVerge\\WooCommerce\\Memberships\\Teams\\Product';
		$max   = class_exists( $class ) ? $class::get_max_member_count( $product ) : null;

		return null !== $max ? (int) $max : null;
	}
}

add_action( 'plugins_loaded', array( 'KitMage_Team_Explainer', 'instance' ) );
