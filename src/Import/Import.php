<?php

namespace StmEncoreCustom\Import;

use Exception;
use StmEncoreCustom\Inc\Logging;
use StmEncoreCustom\Traits\Instantiating;
use WP_Query;
use function StmEncoreCustom\Inc\get_atts_and_taxes;
use function StmEncoreCustom\Inc\get_fields_map;
use function StmEncoreCustom\Inc\get_map_setting;
use function StmEncoreCustom\Inc\get_post_id_by_meta;
use function StmEncoreCustom\Inc\parse_scv;
use const StmEncoreCustom\STM_ENCORE_CUSTOM_RUNNER;

class Import {
	use Instantiating;

	public array $settings;
	protected ?string $file = null;

	public function init(): void {
		$this->settings = \StmEncoreCustom\Inc\get_settings();
		$this->import();
	}

	public function import(): void {
		try {
			switch ( $this->settings['type'] ) {
				case 'sftp':
					$sftp_import = new Type\SFTP();
					$this->file  = $sftp_import->setup_connection();
					break;
				default:
					do_action( 'stm_encore_custom_import', $this->settings );
			}

			if ( $this->file ) {
				$this->start_import();
			}
		} catch ( Exception $e ) {
			Logging::write_exception_log( 'import', $e );
		}
	}

	/**
	 * @throws Exception
	 */
	public function start_import(): void {
		$separator = $this->settings['field_separator'] ?: ',';

		$file_content = parse_scv( $this->file, $separator );

		if ( empty( $file_content ) ) {
			throw new Exception( 'no such file or directory' );
		}

		$uniq_field = get_map_setting( 'uniq_field' );

		if ( ! $uniq_field ) {
			throw new Exception( 'Set uniq_field in settings' );
		}

		$fields = get_fields_map();

		$post_author = $this->settings['post_author'];

		if ( ! $post_author ) {
			throw new Exception( 'Set post_author in settings' );
		}

		$atts_n_taxs = get_atts_and_taxes();

		if ( ! in_array( $uniq_field, $atts_n_taxs ) ) {
			throw new Exception( 'Set uniq_field in settings' );
		}

		$hash = substr( md5( time() ), 0, 10 );

		set_transient( STM_ENCORE_CUSTOM_RUNNER . $hash, $file_content, 60 * 60 * 10 );
		//setting initial offset
		set_transient( STM_ENCORE_CUSTOM_RUNNER . $hash . '_offset', 0, 60 * 60 * 10 );

		self::schedule_import( $hash );
	}

	public static function schedule_import( $hash ): void {
		wp_schedule_single_event( time(), STM_ENCORE_CUSTOM_RUNNER, compact( 'hash' ) );
	}

	/**
	 * @throws Exception
	 */
	public static function handle( $hash ): void {
		$file       = get_transient( STM_ENCORE_CUSTOM_RUNNER . $hash );
		$offset     = (int) get_transient( STM_ENCORE_CUSTOM_RUNNER . $hash . '_offset' ) ?? 0;
		$uniq_field = get_map_setting( 'uniq_field' );

		if ( ! $uniq_field ) {
			throw new Exception( 'Impossible to proceed import without uniq_field' );
		}

		$fields             = get_fields_map();
		$post_author        = get_map_setting( 'post_author' ) ?? 1;
		$rows_per_iteration = (int) get_map_setting( 'rows_per_iteration' ) ?? 5;
		$atts_n_taxs        = get_atts_and_taxes();

		if ( $rows_per_iteration <= 0 ) {
			$rows_per_iteration = 1;
		}

		if ( ! $atts_n_taxs ) {
			throw new Exception( 'Setup listing categories' );
		}

		$uniq_meta_field = array_flip( $atts_n_taxs )[ $uniq_field ];

		if ( ! $uniq_meta_field ) {
			throw new Exception( 'Missing meta uniq_meta_field' );
		}

		if ( ! $file ) {
			throw new Exception( 'Seems like file is corrupted hash: ' . $hash );
		}

		$data = array_slice( $file, $offset, $rows_per_iteration );

		$imported_posts_id = get_transient( STM_ENCORE_CUSTOM_RUNNER . $hash . '_imported_posts_id' );
		if ( ! $imported_posts_id ) {
			$imported_posts_id = [];
		}

		if ( empty( $data ) ) {
			self::remove_missing_listings( $imported_posts_id );
			self::remove_schedule_data( $hash );

			return;
		}

		foreach ( $data as $listing_data ) {
			//trim listing data both keys and value
			$listing_data = array_map( 'trim', $listing_data );

			if ( ! isset( $listing_data[ $uniq_field ] ) ) {
				Logging::write_error_log( 'handle_import', [
					'listing_data',
					$listing_data,
					'message' => 'No uniq field'
				] );
				continue;
			}

			$post_id = get_post_id_by_meta( $uniq_meta_field, $listing_data[ $uniq_field ] );
			$listing = new Listing( $post_id, $listing_data, $fields, $post_author );

			$imported_posts_id[] = $listing->get_post_id();
		}

		$offset += $rows_per_iteration;
		set_transient( STM_ENCORE_CUSTOM_RUNNER . $hash . '_offset', $offset, 60 * 60 * 10 );
		set_transient( STM_ENCORE_CUSTOM_RUNNER . $hash . '_imported_posts_id', $imported_posts_id, 60 * 60 * 10 );

		self::schedule_import( $hash );
	}

	public static function remove_missing_listings( $posts_id ): void {
		$query = new WP_Query( [
			'post_type'      => 'listings',
			'posts_per_page' => - 1,
			'post_status'    => 'publish',
			'meta_key'       => 'stm_encore_custom_post',
			'fields'         => 'ids'
		] );

		$posts = $query->posts;

		if ( empty( $posts || empty( $posts_id ) ) ) {
			return;
		}

		// unset intersect posts
		$intersect = array_intersect( $posts, $posts_id );
		$posts_id  = array_diff( $posts, $intersect );

		if ( empty( $posts_id ) ) {
			return;
		}

		Import::remove_listings( $posts_id );
	}

	public static function remove_listings( $posts_id ): void {
		foreach ( $posts_id as $post_id ) {
			if ( ! $post_id ) {
				continue;
			}

			$thumbnail_id = get_post_meta( $post_id, '_thumbnail_id', true );

			if ( $thumbnail_id ) {
				wp_delete_attachment( $thumbnail_id, true );
			}

			$gallery = get_post_meta( $post_id, 'gallery', true );

			if ( $gallery ) {
				foreach ( $gallery as $image_id ) {
					wp_delete_attachment( trim( $image_id ), true );
				}
			}

			wp_delete_post( $post_id, true );
		}
	}

	public static function remove_schedule_data( $hash ): void {
		delete_transient( STM_ENCORE_CUSTOM_RUNNER . $hash );
		delete_transient( STM_ENCORE_CUSTOM_RUNNER . $hash . '_offset' );
		delete_transient( STM_ENCORE_CUSTOM_RUNNER . $hash . '_imported_posts_id' );
	}
}