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
			update_post_meta( $post_id, 'dw_post_fields', $this->post_data['post_acf_fields'] );
		}

		if ( isset( $this->post_data['page_template'] ) && $this->post_data['page_template'] ) {
			$page_template = sanitize_text_field( $this->post_data['page_template'] );
			$page_templates = wp_get_theme()->get_page_templates();
			if( isset( $page_templates[ $page_template ] ) ){
				update_post_meta( $post_id, '_wp_page_template', $page_template );
			}
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
		$attach_id = $this->download_image( $post_image, $post_id );
		if ( $attach_id ) {
			\set_post_thumbnail( $post_id, $attach_id );
		}
	}

	/**
	 * Set post acf fields.
	 *
	 * @param $post_id          int post id
	 */
	public function set_acf_fields( $post_id ) {
		$post_acf_fields = get_post_meta( $post_id, 'dw_post_fields', true );
		if ( ! $post_acf_fields ) {
			return;
		}
		// loop for the images
		$post_acf_fields = $this->parse_images( $post_acf_fields, $post_id );
		// update the post acf fields
		foreach ( $post_acf_fields as $group_key => $group_value ) {
			Helpers::update_acf_field( $group_key, $group_value, $post_id );
		}

		delete_post_meta( $post_id, 'dw_post_fields' );
	}

	private function parse_images( $post_acf_fields, $post_id ) {
		foreach ( $post_acf_fields as $group_key => $group_value ) {
			if ( is_array( $group_value ) && isset( $group_value['url'] ) ) {
				$attach_id = $this->download_image( $group_value['url'], $post_id );
				if ( $attach_id ) {
					$post_acf_fields[ $group_key ]['id'] = $attach_id;
					$post_acf_fields[ $group_key ]['ID'] = $attach_id;
					// remove the other keys except id and ID
					foreach ( $post_acf_fields[ $group_key ] as $key => $value ) {
						if ( $key !== 'id' && $key !== 'ID' ) {
							unset( $post_acf_fields[ $group_key ][ $key ] );
						}
					}
				}
			} else {
				if ( is_array( $group_value ) ) {
					$post_acf_fields[ $group_key ] = $this->parse_images( $group_value, $post_id );
				}
			}
		}

		return $post_acf_fields;
	}

	/**
	 * Download image from url and return the attachment id.
	 *
	 * @param $image_url string url
	 * @param $post_id   int post id
	 *
	 * @return false|int|\WP_Error
	 */
	private function download_image( $image_url, $post_id = false ) {
		require_once( ABSPATH . 'wp-admin/includes/media.php' );
		require_once( ABSPATH . 'wp-admin/includes/file.php' );
		require_once( ABSPATH . 'wp-admin/includes/image.php' );
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

		$attach_id = media_sideload_image( $image_url, $post_id, '', 'id' );

		// Generate metadata and update the attachment
		$attach_data = wp_generate_attachment_metadata( $attach_id, get_attached_file( $attach_id ) );
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
