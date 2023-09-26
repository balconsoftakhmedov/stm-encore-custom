<?php

namespace StmEncoreCustom;

use Exception;
use StmEncoreCustom\Admin\Settings;
use StmEncoreCustom\Import\Import;
use StmEncoreCustom\Inc\Logging;

class BladeRunner {
	public function __construct() {
		register_activation_hook( STM_ENCORE_CUSTOM_FILE, [ $this, 'activation' ] );
		register_deactivation_hook( STM_ENCORE_CUSTOM_FILE, [ $this, 'deactivation' ] );

		add_action( STM_ENCORE_CUSTOM_ACTION, [ $this, 'preparation' ] );
		add_action( STM_ENCORE_CUSTOM_RUNNER, [ $this, 'run' ] );
	}

	public function activation(): void {
		$settings = \StmEncoreCustom\Inc\get_settings();
		Settings::set_schedule( $settings );
		do_action( 'stm_encore_custom_activated' );
	}

	public function deactivation(): void {
		wp_unschedule_hook( STM_ENCORE_CUSTOM_ACTION );
		do_action( 'stm_encore_custom_deactivated' );
	}

	/**
	 * @param $type
	 * Going to have [type => import(or other)]
	 *
	 * @return void
	 */
	public function preparation( $type ): void {
		if ( method_exists( $this, 'preparation_for_' . $type ) && ! apply_filters( 'stm_encore_custom_prevent_preparation_' . $type, false ) ) {
			$this->{'preparation_for_' . $type}();
		} else {
			do_action( 'stm_encore_custom_runner_action' . $type );
		}
	}

	public function preparation_for_import(): void {
		Logging::write_log( 'preparation_for_import', 'start' );
		$import = Import::instance();
	}

	public function run( $hash ): void {
		Logging::write_log( 'import_run', $hash );
		try {
			Import::handle( $hash );
		} catch ( Exception $e ) {
			Import::remove_schedule_data( $hash );
			Logging::write_exception_log( 'run', $e );
		}
	}
}