<?php
/**
 * WordPress Importer
 * https://github.com/humanmade/WordPress-Importer
 *
 * Released under the GNU General Public License v2.0
 * https://github.com/humanmade/WordPress-Importer/blob/master/LICENSE
 *
 * @since 2.0.0
 *
 * @package WordPress Importer
 */

namespace AyyashStudio\Importer\Wxr;

class ImportInfo {

	/**
	 * Home
	 *
	 * @var string
	 */
	public $home;

	/**
	 * Siteurl
	 *
	 * @var string URL
	 */
	public $siteurl;

	/**
	 * Title
	 *
	 * @var string
	 */
	public $title;

	/**
	 * Users
	 *
	 * @var array
	 */
	public $users = array();

	/**
	 * Post_count
	 *
	 * @var int Count
	 */
	public $post_count = 0;

	/**
	 * Media Count
	 *
	 * @var int Count
	 */
	public $media_count = 0;

	/**
	 * Comment Count
	 *
	 * @var int Count
	 */
	public $comment_count = 0;

	/**
	 * Term Count
	 *
	 * @var int Count
	 */
	public $term_count = 0;

	/**
	 * Generator
	 *
	 * @var string
	 */
	public $generator = '';

	/**
	 * Version
	 *
	 * @var string
	 */
	public $version;
}

// End of file ImportInfo.php.
