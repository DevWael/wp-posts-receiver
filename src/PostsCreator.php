<?php

namespace DevWael\WpPostsReceiver;

// Exit if accessed directly
if ( ! defined( '\ABSPATH' ) ) {
	exit;
}


class PostsCreator {

	/**
	 * @var String Default post status.
	 */
	private $default_post_status;

	/**
	 * @var array Post data coming from the rest request.
	 */
	private array $post_data;

	public function __construct( array $post_data = [] ) {
		if ( ! empty( $post_data ) ) {
			$this->post_data = $post_data;
		}
	}

	/**
	 * Set post data.
	 *
	 * @param array $post_data
	 */
	public function set_post_data( array $post_data ) {
		$this->post_data = $post_data;
	}

	/**
	 * Create post.
	 *
	 * @return false|int|\WP_Error Post ID on success. The value 0 or WP_Error on failure.
	 */
	public function create_post() {
		$post_id = \wp_insert_post(
			[
				'post_title'   => sanitize_text_field( $this->post_data['post_title'] ),
				'post_content' => wp_kses_post( $this->post_data['post_content'] ),
				'post_status'  => sanitize_text_field( $this->get_default_post_status() ),
				'post_type'    => sanitize_text_field( $this->post_data['post_type'] ),
				'post_excerpt' => wp_kses_post( $this->post_data['post_excerpt'] ),
				'post_author'  => sanitize_text_field( $this->post_data['post_author'] ),
			],
			true
		);

		if ( is_wp_error( $post_id ) ) {
			return $post_id;
		}

		// set post-taxonomies
		if ( isset( $this->post_data['post_taxonomies'] ) && is_array( $this->post_data['post_taxonomies'] ) ) {
			$this->set_post_taxonomies( $this->post_data['post_taxonomies'], $post_id );
		}
		// set post-thumbnail from thumb url
		if ( isset( $this->post_data['post_image'] ) && ! empty( $this->post_data['post_image'] ) ) {
			$this->set_post_thumbnail( esc_url_raw( $this->post_data['post_image'] ), $post_id );
		}

		// set post acf fields
		if ( isset( $this->post_data['post_acf_fields'] ) && is_array( $this->post_data['post_acf_fields'] ) ) {
			$this->set_acf_fields( $this->post_data['post_acf_fields'], $post_id );
		}

		return $post_id;
	}

	/**
	 * Set post taxonomies.
	 *
	 * @param $post_taxonomies array
	 * @param $post_id         int
	 */
	private function set_post_taxonomies( $post_taxonomies, $post_id ): void {
		foreach ( $post_taxonomies as $taxonomy ) {
			// Check if the term exists
			$term = term_exists( sanitize_text_field( $taxonomy['name'] ), sanitize_text_field( $taxonomy['taxonomy'] ) );

			// If the term doesn't exist, create it
			if ( ! $term ) {
				$term_data = wp_insert_term( sanitize_text_field( $taxonomy['name'] ), sanitize_text_field( $taxonomy['taxonomy'] ) );
				if ( ! is_wp_error( $term_data ) ) {
					$term_id = $term_data['term_id'];
				}
			} else {
				$term_id = $term['term_id'];
			}

			// Assign the term to the post
			if ( isset( $term_id ) ) {
				wp_set_post_terms( $post_id, $term_id, sanitize_text_field( $taxonomy['taxonomy'] ), true );
			}
		}
	}

	/**
	 * Set post-thumbnail.
	 *
	 * @param $post_image string url
	 * @param $post_id    int post id
	 */
	private function set_post_thumbnail( $post_image, $post_id ): void {
		$attach_id = $this->download_image( $post_image );
		if ( $attach_id ) {
			\set_post_thumbnail( $post_id, $attach_id );
		}
	}

	/**
	 * Set post acf fields.
	 *
	 * @param $post_acf_fields  array of acf fields
	 * @param $post_id          int post id
	 */
	private function set_acf_fields( $post_acf_fields, $post_id ): void {
		foreach ( $post_acf_fields as $key => $value ) {
			// check if the field is an image
			if ( is_array( $value ) && isset( $value['ID'] ) ) {
				$attach_id = $this->download_image( $value['url'] );
				if ( $attach_id ) {
					$value['ID'] = $attach_id;
				}
			} else {
				// check if the field is a repeater
				if ( is_array( $value ) && isset( $value[0] ) ) {
					foreach ( $value as $repeater_key => $repeater_value ) {
						// check if the repeater field is an image
						if ( is_array( $repeater_value ) && isset( $repeater_value['ID'] ) ) {
							$attach_id = $this->download_image( esc_url_raw( $repeater_value['url'] ) );
							if ( $attach_id ) {
								$repeater_value['ID'] = $attach_id;
							}
						}
					}
				}
			}

			// update the acf field
			Helpers::update_acf_field( $key, $value, $post_id );
		}
	}

	/**
	 * Download image from url and return the attachment id.
	 *
	 * @param $image_url
	 *
	 * @return false|int|\WP_Error
	 */
	private function download_image( $image_url ) {
		// Check if the file is an image
		$image_info = getimagesize( $image_url );
		if ( ! $image_info ) {
			return false;
		}

		// Download the image using wp_remote_get()
		$response = wp_remote_get( $image_url );

		// Check if the request was successful
		if ( is_wp_error( $response ) || $response['response']['code'] !== 200 ) {
			return false;
		}

		// Prepare an array with the image file data
		$upload = wp_upload_bits( basename( $image_url ), null, $response['body'] );

		// Check if the upload was successful
		if ( $upload['error'] ) {
			return false;
		}

		// Set up the attachment data
		$attachment = array(
			'post_mime_type' => $image_info['mime'],
			'post_title'     => sanitize_file_name( pathinfo( $upload['file'], PATHINFO_FILENAME ) ),
			'post_content'   => '',
			'post_status'    => 'inherit',
		);

		// Insert the attachment
		$attach_id = wp_insert_attachment( $attachment, $upload['file'] );

		// Generate metadata and update the attachment
		$attach_data = wp_generate_attachment_metadata( $attach_id, $upload['file'] );
		wp_update_attachment_metadata( $attach_id, $attach_data );

		return $attach_id;
	}

	/**
	 * Get default post status.
	 *
	 * @return false|mixed
	 */
	private function get_default_post_status() {
		$status = Helpers::get_acf_field( 'wp_posts_receiver_default_post_status', 'option' );
		if ( $status ) {
			$status = 'draft';
		}

		/**
		 * Filter default post status.
		 *
		 * @param string $status Default post status.
		 *
		 * @since 1.0.0
		 */
		return apply_filters( 'wp_posts_receiver_default_post_status', $status );
	}
}