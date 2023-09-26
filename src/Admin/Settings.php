<?php

namespace StmEncoreCustom\Admin;

use DateInterval;
use DateTime;
use DateTimeZone;
use Exception;
use StmEncoreCustom\Import\Import;
use StmEncoreCustom\Inc\Logging;
use StmEncoreCustom\Traits\Instantiating;
use function StmEncoreCustom\Inc\get_listings_attributes;
use const StmEncoreCustom\STM_ENCORE_CUSTOM_ACTION;

class Settings {
	use Instantiating;

	const OPTION_NAME = 'stm_encore_custom_settings';

	public function init(): void {
		add_filter( 'wpcfto_options_page_setup', [ $this, 'setup' ], 100 );

		add_action( 'wpcfto_after_settings_saved', [ $this, 'add_schedule' ], 10, 2 );
	}

	public function setup( $setups ) {
		$setups[] = array(
			'option_name' => self::OPTION_NAME,

			'title'     => esc_html__( 'Encore Options', 'stm-encore-custom' ),
			'sub_title' => esc_html__( 'by Stylemix Customizations', 'stm-encore-custom' ),
			'logo'      => 'https://s3.envato.com/files/235051023/avatar-80x80.png',
			'page'      => array(
				'page_title' => esc_html__( 'Encore Options', 'stm-encore-custom' ),
				'menu_title' => esc_html__( 'Encore Options', 'stm-encore-custom' ),
				'menu_slug'  => self::OPTION_NAME,
				'icon'       => 'https://s3.envato.com/files/235051023/avatar-80x80.png',
				'position'   => 100,
			),
			'fields'    => array(
				'import'     => array(
					'name'   => esc_html__( 'Import', 'stm-encore-custom' ),
					'fields' => array(
						'post_author'        => array(
							'type'  => 'number',
							'label' => esc_html__( 'Enter post author ID', 'stm-encore-custom' ),
						),
						'rows_per_iteration' => array(
							'type'        => 'number',
							'label'       => esc_html__( 'Rows per iteration', 'stm-encore-custom' ),
							'value'       => '5',
							'description' => esc_html__( 'How many rows to import per iteration. The default is 5. It is not recommended to set a high value. A smaller value is better for optimizing server resource usage.', 'stm-encore-custom' ),
						),
						'type'               => array(
							'type'    => 'select',
							'label'   => esc_html__( 'Type', 'stm-encore-custom' ),
							'options' => array(
								'sftp' => esc_html__( 'SFTP', 'stm-encore-custom' ),
							),
							'value'   => 'sftp',
						),

						//sftp settings
						'sftp_host'          => array(
							'type'        => 'text',
							'label'       => esc_html__( 'SFTP Host', 'stm-encore-custom' ),
							'description' => esc_html__( 'Enter SFTP host', 'stm-encore-custom' ),
							'dependency'  => array(
								'key'   => 'type',
								'value' => 'sftp',
							),
						),
						'sftp_username'      => array(
							'type'        => 'text',
							'label'       => esc_html__( 'SFTP Username', 'stm-encore-custom' ),
							'description' => esc_html__( 'Enter SFTP username', 'stm-encore-custom' ),
							'dependency'  => array(
								'key'   => 'type',
								'value' => 'sftp',
							),
						),
						'sftp_password'      => array(
							'type'        => 'text',
							'label'       => esc_html__( 'SFTP Password', 'stm-encore-custom' ),
							'description' => esc_html__( 'Enter SFTP password', 'stm-encore-custom' ),
							'dependency'  => array(
								'key'   => 'type',
								'value' => 'sftp',
							),
						),
						'ftp_directory'      => array(
							'type'        => 'text',
							'label'       => esc_html__( 'File Directory', 'stm-encore-custom' ),
							'description' => esc_html__( 'Enter file directory', 'stm-encore-custom' ),
							'dependency'  => array(
								'key'   => 'type',
								'value' => 'sftp||ftp',
							),
						),
						'ftp_filename'       => array(
							'type'        => 'text',
							'label'       => esc_html__( 'File Name', 'stm-encore-custom' ),
							'description' => esc_html__( 'Enter file name', 'stm-encore-custom' ),
							'dependency'  => array(
								'key'   => 'type',
								'value' => 'sftp||ftp',
							),
						),

						//file settings
						'field_separator'    => array(
							'type'        => 'text',
							'label'       => esc_html__( 'Field separator', 'stm-encore-custom' ),
							'description' => esc_html__( 'Field separator. Default is comma.', 'stm-encore-custom' ),
						),
						'file_path'          => array(
							'type'        => 'text',
							'label'       => esc_html__( 'Enter file path to your CSV', 'stm-encore-custom' ),
							'value'       => '',
							'description' => sprintf( esc_html__( 'Your ABSPATH constant is: %s', 'stm-encore-custom' ), ABSPATH ),
							'dependency'  => array(
								'key'   => 'type',
								'value' => 'file',
							),
						),
					)
				),
				'schedule'   => array(
					'name'   => esc_html__( 'Schedule', 'stm-encore-custom' ),
					'fields' => array(
						'schedule' => array(
							'type'   => 'repeater',
							'label'  => esc_html__( 'Events', 'stm-encore-custom' ),
							'fields' => array(
								'timezone'   => array(
									'type'        => 'text',
									'label'       => esc_html__( 'Timezone', 'stm-encore-custom' ),
									'description' => esc_html__( 'Enter timezone as described in php DateTimeZone. Example: GMT-5:00', 'stm-encore-custom' ),
								),
								'interval'   => array(
									'type'        => 'text',
									'label'       => esc_html__( 'Interval', 'stm-encore-custom' ),
									'description' => esc_html__( 'Enter timezone as described in php DateInterval. Example: P1D. Leave blank if applicable.', 'stm-encore-custom' ),
								),
								'time'       => array(
									'type'  => 'time',
									'label' => esc_html__( 'Time', 'stm-encore-custom' ),
								),
								'recurrence' => array(
									'type'        => 'text',
									'label'       => esc_html__( 'Recurrence', 'stm-encore-custom' ),
									'description' => esc_html__( 'Possible values: hourly, twicedaily, daily. Or custom that added through filter cron_schedules. By default daily, leave empty if it is applicable.', 'stm-encore-custom' ),
								),
							)
						),
					)
				),
				'map_fields' => array(
					'name'   => esc_html__( 'Map Fields', 'stm-encore-custom' ),
					'fields' => array(
						'uniq_field'  => array(
							'type'        => 'text',
							'label'       => esc_html__( 'Unique field', 'stm-encore-custom' ),
							'description' => esc_html__( 'Unique field in CSV. Used to update listings.', 'stm-encore-custom' ),
						),
						'title'       => array(
							'type'        => 'text',
							'label'       => esc_html__( 'Title', 'stm-encore-custom' ),
							'description' => esc_html__( 'You may use multiple fields from CSV. Example: {{Make}} {{Model}} {{Trim}} {{Year}}', 'stm-encore-custom' ),
						),
						'description' => array(
							'type'  => 'text',
							'label' => esc_html__( 'Description', 'stm-encore-custom' ),
						),
						'fields'      => array(
							'type'   => 'repeater',
							'label'  => esc_html__( 'Fields', 'stm-encore-custom' ),
							'fields' => array(
								'key'                => array(
									'type'    => 'select',
									'label'   => esc_html__( 'Listing category', 'stm-encore-custom' ),
									'options' => get_listings_attributes( esc_html__( 'Select listing category', 'stm-encore-custom' ) ),
									'value'   => '',
								),
								'csv_field'          => array(
									'type'  => 'text',
									'label' => esc_html__( 'Field in CSV', 'stm-encore-custom' ),
								),
								'multiple_separator' => array(
									'type'        => 'text',
									'label'       => esc_html__( 'Multiple separator', 'stm-encore-custom' ),
									'description' => esc_html__( 'Leave blank if not multiple. Separator for multiple values.', 'stm-encore-custom' ),
								),
							)
						),
					)
				),
			)
		);

		return apply_filters( 'stm_encore_custom_settings', $setups );
	}

	public function add_schedule( $page, $settings ): void {
		if ( ! self::is_correct_page( $page ) ) {
			return;
		}

		do_action( 'stm_encore_save_custom_settings', $settings );

		if ( ! is_array( $settings['schedule'] ) ) {
			return;
		}

		self::set_schedule( $settings );
	}

	public static function set_schedule( $settings ): void {
		wp_unschedule_hook( STM_ENCORE_CUSTOM_ACTION );

		if ( empty( $settings['schedule'] ) ) {
			return;
		}



		foreach ( $settings['schedule'] as $schedule ) {
			extract( $schedule );
			/**
			 * @var $timezone
			 * @var $interval
			 * @var $time
			 * @var $recurrence
			 */
			if ( empty( $timezone ) || empty( $time ) ) {
				continue;
			}

			$interval   = $interval ?? 'P1D';
			$recurrence = $recurrence ?? 'daily';

			try {
				$datetime     = new DateTime();
				$set_timezone = new DateTimeZone( $timezone );
				$datetime->add( new DateInterval( $interval ) );
				$datetime->setTimezone( $set_timezone );

				$time = explode( ':', $time );

				$datetime->setTime( ...$time );

				$event_args = apply_filters( 'stm_encore_custom_event_args', [
					'type' => 'import',
				], $schedule );

				$schedule = wp_schedule_event( $datetime->getTimestamp(), $recurrence, STM_ENCORE_CUSTOM_ACTION, $event_args );

				if ( is_wp_error( $schedule ) ) {
					throw new Exception( $schedule->get_error_message() );
				}
			} catch ( Exception $e ) {
				Logging::write_exception_log( 'add_schedule', $e );
			}

			$interval = $recurrence = null;
		}

		do_action( 'stm_encore_custom_schedule_added', $settings );
	}

	public static function is_correct_page( $page ): bool {
		if ( $page === self::OPTION_NAME ) {
			return true;
		}

		return false;
	}
}
