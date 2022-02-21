<?php

require_once __DIR__ . '/wallee/wallee.php';

class wallee_template extends wallee {
	public function __construct() {
		$this->code = 'wallee_template';
		parent::__construct();
	}
}
