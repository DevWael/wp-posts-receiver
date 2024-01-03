<?php

namespace DevWael\WpPostsReceiver\Rest;

// Exit if accessed directly
if ( ! defined( '\ABSPATH' ) ) {
	exit;
}

use DevWael\WpPostsReceiver\Helpers;
use DevWael\WpPostsReceiver\PostsCreator;


class EndPoint {

	/**
	 * @var PostsCreator
	 */
	private ?PostsCreator $posts_creator;

	public function __construct( PostsCreator $posts_creator = null ) {
		if ( ! $posts_creator ) {
			$posts_creator = new PostsCreator();
		}

		$this->posts_creator = $posts_creator;
	}

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
	 * @return \WP_REST_Response Response.
	 */
	public function receive_posts( \WP_REST_Request $request ) {
		$post_data = $request->get_param( 'post_data' );
		if ( empty( $post_data ) ) {
			return new \WP_REST_Response( [ 'message' => __( 'Post data is empty.', 'wp-posts-receiver' ) ], 400 );
		}

		$this->posts_creator->set_post_data( $post_data );
		$result = $this->posts_creator->create_post();

		if ( is_wp_error( $result ) ) {
			return new \WP_REST_Response( [ 'message' => $result->get_error_message() ], 400 );
		}

		return new \WP_REST_Response( [ 'message' => __( 'Post created successfully.', 'wp-posts-receiver' ) ], 200 );
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
		$encrypt_key         = \sanitize_text_field( $param );
		$current_encrypt_key = \sanitize_text_field( Helpers::get_acf_field( 'wp_posts_sender_encryption_key', 'option' ) );

		return $encrypt_key === $current_encrypt_key;
	}
}