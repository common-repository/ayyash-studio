<?php
/**
 *
 *
 * @package Package
 * @author Name <email>
 * @version
 * @since
 * @license
 */

namespace AyyashStudio\Importer\Process\Types;

use AyyashStudio\Importer\Traits\Post_Query;
use \WP_CLI;

if ( ! defined( 'ABSPATH' ) ) {
	header( 'Status: 403 Forbidden' );
	header( 'HTTP/1.1 403 Forbidden' );
	die();
}

class WPCF7 {

	use Post_Query;

	/**
	 * Singleton instance ref.
	 *
	 * @var self
	 */
	protected static $instance;

	/**
	 * Create one instance of this class, stores and return that.
	 *
	 * @return self
	 */
	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Class constructor.
	 *
	 * Enforce singleton instance.
	 *
	 * @return void
	 */
	protected function __construct() {
	}

	/**
	 * @return string
	 */
	public static function generate_mail() {
		return 'wordpress@' . wp_parse_url( get_site_url(), PHP_URL_HOST );
	}

	/**
	 * @return void
	 */
	public function run() {
		if ( defined( 'WP_CLI' ) ) {
			WP_CLI::line( 'Processing Contact form 7 data' );
		}

		$mail = self::generate_mail();
		$mail = '<' . $mail . '>';

		$posts = self::get_posts_need_processing( 'wpcf7_contact_form' );

		foreach ( $posts as $post ) {
			$_mail = get_post_meta( $post, '_mail', true );

			if ( ! empty( $_mail['sender'] ) ) {
				$_mail['sender'] = preg_replace( '/<WordPress.+?>/i', $mail, $_mail['sender'] );
			}

			update_metadata( 'post', $post, '_mail', $_mail );

			$_mail2 = get_post_meta( $post, '_mail_2', true );

			if ( ! empty( $_mail2['sender'] ) ) {
				$_mail2['sender'] = preg_replace( '/<WordPress.+?>/i', $mail, $_mail2['sender'] );
			}

			update_metadata( 'post', $post, '_mail_2', $_mail2 );

			wp_update_post( [
				'ID'           => $post,
				'post_excerpt' => '',
				'post_content' => '',
			] );

			delete_post_meta( $post, '_ayyash_studio_need_processing' );
			delete_post_meta( $post, '_ayyash_studio_enable_for_batch' );
		}

		ayyash_studio_log_info( 'Processing Contact form 7' );
	}
}

// End of file NavMenu.php.
