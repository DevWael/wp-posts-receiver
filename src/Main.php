<?php

namespace DevWael\WpPostsReceiver;

// Exit if accessed directly
if ( ! defined( '\ABSPATH' ) ) {
	exit;
}

use DevWael\WpPostsReceiver\Languages\Localization;

class Main {

	/**
	 * @var null singleton
	 */
	private static $instance = null;

	/**
	 * @var Localization
	 */
	private Localization $localization;

	/**
	 * Get instance
	 *
	 * @return self|null
	 */
	public static function get_instance() {
		if ( self::$instance === null ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Constructor
	 */
	private function __construct() {
		$this->localization  = new Localization();
	}

	/**
	 * Init
	 *
	 * @return void
	 */
	public function init(): void {
		$this->localization->load_hooks();
	}
}