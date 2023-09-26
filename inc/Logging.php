<?php

namespace StmEncoreCustom\Inc;

use Throwable;

class Logging {
	public static function __write_log( $place, $log, $fname = '_log' ): void {
		$logs_folder = ABSPATH . 'stm_logs' . '/';

		if ( ! file_exists( $logs_folder ) ) {
			mkdir( $logs_folder );
		}

		$date_path = date( "Y/m" );
		$_path     = '';
		foreach ( explode( '/', $date_path ) as $k => $path ) {
			$_path .= $path . '/';
			if ( ! file_exists( $logs_folder . $_path ) ) {
				mkdir( $logs_folder . $_path );
			}
		}

		$file_name = $logs_folder . $date_path . '/' . date( "d" ) . $fname . '.log';
		if ( ! file_exists( $file_name ) ) {
			file_put_contents( $file_name, '' );
		}

		file_put_contents( $file_name, date( 'Y-m-d H:i' ) . ' place: ' . $place ."\r\n", FILE_APPEND );

		if ( is_array( $log ) || is_object( $log ) ) {
			file_put_contents( $file_name, print_r( $log, true ) . "\r\n", FILE_APPEND );
		} else {
			file_put_contents( $file_name, $log . "\r\n", FILE_APPEND );
		}

		file_put_contents( $file_name, "\r\n", FILE_APPEND );
	}

	public static function write_log( $place, $log, $fname = '_log' ): void {
		if ( ! defined( 'STM_ENCORE_DEBUG' ) ) {
			return;
		}
		self::__write_log( $place, $log, $fname );
	}

	public static function write_error_log( $place, $log ): void {
		self::__write_log( $place, $log, '_error_log' );
	}

	public static function write_exception_log( $place, Throwable $exception, $file_name = '_error_log' ): void {
		self::__write_log( $place, [
			$exception->getMessage(),
			$exception->getFile() . ':' . $exception->getLine(),
			$exception->getTraceAsString()
		], $file_name );
	}
}