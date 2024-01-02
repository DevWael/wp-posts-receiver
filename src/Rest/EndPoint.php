<?php

namespace DevWael\WpPostsReceiver\Rest;

// Exit if accessed directly
if ( ! defined( '\ABSPATH' ) ) {
	exit;
}

class EndPoint {
	/**
	 * Load hooks
	 *
	 * @return void
	 */
	public function load_hooks() {
		add_action( 'rest_api_init', [ $this, 'register_end_point' ] );
	}

	/**
	 * Register end point.
	 *
	 * @return void
	 */
	public function register_end_point(): void {
		\register_rest_route( 'wp-posts-receiver/v1', '/add-post', [
			'methods'             => 'POST',
			'callback'            => [ $this, 'receive_posts' ],
			'permission_callback' => [ $this, 'has_permission' ],
			'args'                => [
				'post_data'   => [
					'required' => true,
					'type'     => 'array',
				],
				'encrypt_key' => [
					'required'          => true,
					'type'              => 'string',
					'validate_callback' => [ $this, 'validate_encrypt_key' ],
				],
			],
		] );
	}

	/**
	 * Receive posts.
	 *
	 * @return void
	 */
	public function receive_posts( \WP_REST_Request $request ) {
	}

	/**
	 * Allow all users to access this endpoint.
	 *
	 * @return true
	 */
	public function has_permission(): bool {
		/**
		 * Filter permission.
		 *
		 * @since 1.0.0
		 */
		return apply_filters( 'wp_posts_receiver_has_permission', true );
	}

	/**
	 * Validate request.
	 *
	 * @param string           $param   Parameter.
	 * @param \WP_REST_Request $request Request.
	 * @param string           $key     Key.
	 *
	 * @return bool True if the request is valid, false otherwise.
	 */
	public function validate_encrypt_key( $param, \WP_REST_Request $request, $key ): bool {
		return true;
	}
}