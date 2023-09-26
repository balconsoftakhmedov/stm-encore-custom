<?php

namespace StmEncoreCustom\Import\Type;

use Exception;
use StmEncoreCustom\Inc\Logging;
use StmEncoreCustom\Traits\Instantiating;
use function StmEncoreCustom\Inc\get_map_setting;

class SFTP {
	use Instantiating;

	/**
	 * @throws Exception
	 */
	public function setup_connection(): string {
		$server    = get_map_setting( 'sftp_host' );
		$username  = get_map_setting( 'sftp_username' );
		$password  = get_map_setting( 'sftp_password' );
		$directory = get_map_setting( 'ftp_directory' );
		$filename  = get_map_setting( 'ftp_filename' );

		$file = '';

		// Connect to the SFTP server
		$sftp = new \phpseclib3\Net\SFTP( $server );
		if ( ! $sftp->login( $username, $password ) ) {
			Logging::write_error_log( 'sftp', 'Login failed.' );
		}

		if ( ! $sftp->is_dir( $directory ) ) {
			Logging::write_error_log( 'sftp', 'Could not access the specific directory.' );
		} else {
			$filePath    = "$directory/$filename";
			$fileContent = $sftp->get( $filePath );

			if ( $fileContent === false ) {
				Logging::write_error_log( 'sftp', 'Failed to read the file' );
			} else {
				$file = $fileContent;
			}
		}

		if ( ! $file ) {
			throw new Exception( 'no such file or directory sftp' );
		}

		return $file;
	}
}