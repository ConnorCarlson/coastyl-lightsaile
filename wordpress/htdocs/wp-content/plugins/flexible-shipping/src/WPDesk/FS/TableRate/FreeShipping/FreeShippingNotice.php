<?php
/**
 * Free Shipping Notice.
 *
 * @package WPDesk\FS\TableRate\FreeShipping
 */

namespace WPDesk\FS\TableRate\FreeShipping;

use FSVendor\WPDesk\PluginBuilder\Plugin\Hookable;
use WC_Cart;
use WC_Session;
use WP;

/**
 * Can display free shipping notice.
 */
class FreeShippingNotice implements Hookable {

	const FLEXIBLE_SHIPPING_FREE_SHIPPING_NOTICE = 'flexible_shipping_free_shipping_notice';
	const NOTICE_TYPE_SUCCESS = 'success';

	/**
	 * @var WC_Cart
	 */
	private $cart;

	/**
	 * @var WC_Session
	 */
	private $session;

	/**
	 * @var WP
	 */
	private $wp;

	/**
	 * FreeShippingNotice constructor.
	 *
	 * @param WC_Cart    $cart    .
	 * @param WC_Session $session .
	 * @param WP         $wp .
	 */
	public function __construct( WC_Cart $cart, WC_Session $session, WP $wp ) {
		$this->wp      = $wp;
		$this->cart    = $cart;
		$this->session = $session;
	}

	/**
	 * Hooks.
	 */
	public function hooks() {
		add_action( 'woocommerce_after_calculate_totals', array( $this, 'add_notice_free_shipping' ) );
	}

	/**
	 * Add notice to free shipping left.
	 */
	public function add_notice_free_shipping() {
		remove_action( 'woocommerce_after_calculate_totals', array( $this, 'add_notice_free_shipping' ) );

		$message_text = $this->session->get( FreeShippingNoticeGenerator::SESSION_VARIABLE, '' );

		if ( $this->should_add_to_card( $message_text ) || $this->should_add_to_checkout( $message_text ) ) {
			wc_add_notice( $message_text, self::NOTICE_TYPE_SUCCESS, array( self::FLEXIBLE_SHIPPING_FREE_SHIPPING_NOTICE => 'yes' ) );
		}
	}

	/**
	 * @param string $message_text .
	 *
	 * @return bool
	 */
	private function should_add_to_card( $message_text ) {
		return is_cart() && ! wp_doing_ajax() && $this->should_show_notice( $message_text );
	}

	/**
	 * @param string $message_text .
	 *
	 * @return bool
	 */
	private function should_add_to_checkout( $message_text ) {
		return is_checkout() && $this->should_show_notice( $message_text );
	}

	/**
	 * @param string $message_text .
	 *
	 * @return bool
	 */
	private function should_show_notice( $message_text ) {
		return ! empty( $this->wp->request ) && $message_text && $this->cart->needs_shipping() && ! wc_has_notice( $message_text, self::NOTICE_TYPE_SUCCESS );
	}
}
