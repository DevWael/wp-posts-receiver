<?php

namespace DevWael\WpPostsReceiver;

// Exit if accessed directly
if ( ! defined( '\ABSPATH' ) ) {
	exit;
}

use DevWael\WpPostsReceiver\Admin\AdminOptions;
use DevWael\WpPostsReceiver\Languages\Localization;
use DevWael\WpPostsReceiver\Rest\EndPoint;

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
	 * @var EndPoint
	 */
	private EndPoint $endpoint;

	/**
	 * @var AdminOptions
	 */
	private AdminOptions $admin_options;

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
		$this->endpoint      = new Endpoint();
		$this->admin_options = new AdminOptions();
	}

	/**
	 * Init
	 *
	 * @return void
	 */
	public function init(): void {
		$this->localization->load_hooks();
		$this->endpoint->load_hooks();
		$this->admin_options->load_hooks();
	}
}