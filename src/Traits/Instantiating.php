<?php

namespace StmEncoreCustom\Traits;

use stdClass;

trait Instantiating {
	public static stdClass|null $instance = null;

	public static function instance(): static {
		$instance = self::$instance;

		if ( $instance === null ) {
			$instance = new static();
			$instance->init();
		}

		return $instance;
	}

	public function init() {
	}
}