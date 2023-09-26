<?php

namespace StmEncoreCustom\Import;

use Exception;
use StmEncoreCustom\Inc\Logging;
use function StmEncoreCustom\Inc\get_available_attributes;
use function StmEncoreCustom\Inc\get_listings_taxonomies;
use function StmEncoreCustom\Inc\get_map_setting;
use function StmEncoreCustom\Inc\stm_store_file;

class Listing {
	private static int $post_id;
	private static array $listing_data;
	private static array $fields;

	public function __construct( $post_id, $listing_data, $fields, $post_author ) {
		$title       = get_map_setting( 'title' );
		$description = get_map_setting( 'description' );

		$post_data = [
			'post_title'   => self::generate_title( $title, $listing_data ),
			'post_content' => $listing_data[ $description ] ?? '',
			'post_status'  => 'publish',
			'post_type'    => 'listings',
			'post_author'  => $post_author,
		];

		if ( $post_id ) {
			$post_data['ID'] = $post_id;
		}

		try {
			$post_id = wp_insert_post( $post_data );

			update_post_meta( $post_id, 'stm_car_user', $post_author );
			update_post_meta( $post_id, 'stm_encore_custom_post', true );

			if ( is_wp_error( $post_id ) ) {
				throw new Exception( 'Can not create post . data:' . json_encode( $post_data ) );
			}

			self::$post_id      = $post_id;
			self::$listing_data = $listing_data;
			self::$fields       = $fields;

			self::update_listing_meta();
			self::set_listing_attributes();
		} catch ( Exception $e ) {
			Logging::write_exception_log( 'add_listing', $e );
		}

		foreach ( $fields as $field ) {
			if ( ! isset( $field['csv_field'] ) || ! isset( $field['key'] ) ) {
				continue;
			}

			$is_multiple = false;

			if ( isset( $field['multiple_separator'] ) && $field['multiple_separator'] ) {
				$is_multiple = true;
			}

			$csv_field     = $field['csv_field'];
			$listing_field = $field['key'];

			if ( ! isset( $data[ $csv_field ] ) ) {
				continue;
			}

			$value = $data[ $csv_field ];
		}
	}

	public function get_post_id(): int {
		return self::$post_id;
	}

	public static function generate_title( $title, $listing_data ) {
		preg_match_all( '/\{\{(.+?)}}/', $title, $matches );

		$matches = $matches[1];

		if ( empty( $matches ) ) {
			return $title;
		}

		foreach ( $matches as $match ) {
			if ( isset( $listing_data[ $match ] ) ) {
				$title = str_replace( '{{' . $match . '}}', $listing_data[ $match ], $title );
			}
		}

		return $title;
	}

	public static function update_listing_meta(): void {
		$metas = get_available_attributes();

		foreach ( $metas as $meta => $meta_name ) {
			if ( ! array_key_exists( $meta, self::$fields ) ) {
				continue;
			}

			$csv_field = self::$fields[ $meta ]['csv_field'] ?? '';
			$value     = self::$listing_data[ $csv_field ] ?? '';

			if ( 'gallery' === $meta && $value ) {
				self::upload_images( $value, self::$fields[ $meta ] );
				continue;
			}

			if ( 'additional_features' === $meta && $value ) {
				self::set_features( $value, self::$fields[ $meta ] );
				continue;
			}

			if ( ! $csv_field || ! $value ) {
				continue;
			}

			if ( isset( self::$fields[ $meta ]['multiple_separator'] ) && self::$fields[ $meta ]['multiple_separator'] ) {
				$value = explode( self::$fields[ $meta ]['multiple_separator'], $value );
			}

			update_post_meta( self::$post_id, $meta, $value );
		}

		$post_id    = self::$post_id;
		$price      = get_post_meta( $post_id, 'price', true );
		$sale_price = get_post_meta( $post_id, 'sale_price', true );

		if ( ! empty( $sale_price ) ) {
			$price = $sale_price;
		}

		update_post_meta( $post_id, 'stm_genuine_price', $price );
		update_post_meta( $post_id, 'car_mark_as_sold', '' );

		do_action( 'stm_encore_custom_update_listing_meta', $post_id, self::$fields, self::$listing_data );
	}

	public static function upload_images( $images_list, $field ): void {
		$thumbnail_id = get_post_meta( self::$post_id, '_thumbnail_id', true );
		$gallery      = get_post_meta( self::$post_id, 'gallery', true );

		if ( $thumbnail_id ) {
			wp_delete_attachment( $thumbnail_id, true );
		}

		if ( $gallery ) {
			foreach ( $gallery as $image_id ) {
				wp_delete_attachment( trim( $image_id ), true );
			}
		}

		$multiple_separator = $field['multiple_separator'] ?? '';

		if ( $multiple_separator ) {
			$images_list   = explode( $multiple_separator, $images_list );
			$images_list   = array_map( 'trim', $images_list );
			$thumbnail_url = array_shift( $images_list );

			try {
				$thumbnail_id = stm_store_file( $thumbnail_url, self::$post_id );
				update_post_meta( self::$post_id, '_thumbnail_id', $thumbnail_id );
			} catch ( Exception $e ) {
				Logging::write_exception_log( 'upload_image_thumbnail', $e );
			}

			$gallery = [];

			foreach ( $images_list as $image_url ) {
				try {
					$image_id  = stm_store_file( $image_url, self::$post_id );
					$gallery[] = $image_id;
				} catch ( Exception $e ) {
					Logging::write_exception_log( 'upload_image_gallery', $e );
				}
			}

			update_post_meta( self::$post_id, 'gallery', $gallery );

			return;
		}

		try {
			$thumbnail_id = stm_store_file( $images_list, self::$post_id );
			update_post_meta( self::$post_id, '_thumbnail_id', $thumbnail_id );
		} catch ( Exception $e ) {
			Logging::write_exception_log( 'upload_image_thumbnail_no_separator', $e );
		}
	}

	public static function set_features( $features, $field ): void {
		if ( $field['multiple_separator'] ) {
			$features = explode( $field['multiple_separator'], $features );
			$features = array_map( 'trim', $features );

			self::set_features_terms( $features );

			return;
		}

		$features = explode( ',', $features );
		$features = array_map( 'trim', $features );

		self::set_features_terms( $features );
	}

	public static function set_features_terms( $features ): void {
		$features_terms_id = [];
		foreach ( $features as $feature ) {
			$term    = term_exists( $feature, 'stm_additional_features' );
			$term_id = '';

			if ( ! $term ) {
				$term = wp_insert_term( $feature, 'stm_additional_features' );
			}

			if ( ! is_wp_error( $term ) ) {
				$term_id = (int) $term['term_id'];
			}

			$features_terms_id[] = $term_id;
		}

		wp_delete_object_term_relationships( self::$post_id, 'stm_additional_features' );
		wp_set_object_terms( self::$post_id, $features_terms_id, 'stm_additional_features' );
		update_post_meta( self::$post_id, 'additional_features', join( ',', $features ) );
	}

	public static function set_listing_attributes(): void {
		$attributes = get_listings_taxonomies( 'origin' );

		if ( empty( $attributes ) ) {
			return;
		}

		$attributes = array_column( $attributes, null, 'slug' );

		foreach ( $attributes as $slug => $attribute ) {
			if ( ! array_key_exists( $slug, self::$fields ) ) {
				continue;
			}

			$csv_field = self::$fields[ $slug ]['csv_field'] ?? '';
			$value     = self::$listing_data[ $csv_field ] ?? '';

			if ( ! $csv_field || ! $value ) {
				continue;
			}

			if ( isset( self::$fields[ $slug ]['multiple_separator'] ) && self::$fields[ $slug ]['multiple_separator'] ) {
				$value = explode( self::$fields[ $slug ]['multiple_separator'], $value );
			}

			if ( is_array( $value && ! $attribute['numeric'] ) ) {
				$terms_id   = [];
				$terms_slug = [];

				foreach ( $value as $term_name ) {
					$term = term_exists( $term_name, $slug );

					if ( ! $term ) {
						$term = wp_insert_term( $term_name, $slug );
					}

					if ( ! is_wp_error( $term ) ) {
						$terms_id[]   = (int) $term['term_id'];
						$terms_slug[] = sanitize_title( $term_name );
					}
				}

				if ( ! empty( $terms_id ) ) {
					$terms_id = array_map( 'intval', $terms_id );
				}

				wp_delete_object_term_relationships( self::$post_id, $slug );
				wp_set_object_terms( self::$post_id, $terms_id, $slug );
				update_post_meta( self::$post_id, $slug, join( ',', $terms_slug ) );

				return;
			}

			if ( $attribute['numeric'] ) {
				update_post_meta( self::$post_id, $slug, $value );
				continue;
			}

			$term_id = '';

			if ( $value ) {
				$term = term_exists( $value, $slug );

				if ( ! $term ) {
					$term = wp_insert_term( $value, $slug );
				}

				if ( ! is_wp_error( $term ) ) {
					$term_id = (int) $term['term_id'];
				}
			}

			wp_delete_object_term_relationships( self::$post_id, $slug );
			wp_set_object_terms( self::$post_id, $term_id, $slug );
			update_post_meta( self::$post_id, $slug, sanitize_title( $value ) );
		}

		do_action( 'stm_encore_custom_set_listing_attributes', self::$post_id, $attributes, self::$fields, self::$listing_data );
	}
}