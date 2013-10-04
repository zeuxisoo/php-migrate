<?php
if (defined('IN_APPS') === false) exit('Access Dead');

class Database {

	private static $instance = null;

	public static function init($settings) {
		if (empty($settings['host']) === true) {
			$settings['host'] = "127.0.0.1";
		}

		$adapter = $settings['adapter'].'_adapter';

		self::$instance = new $adapter($settings);
		self::$instance->set_debug($settings['debug']);

		return self::$instance;
	}

	public static function instance() {
		return self::$instance;
	}

	public static function table_prefix($table) {
		return self::$instance->prefix.$table;
	}

	public static function quote_identifier($column) {
		return self::$instance->quote_identifier($column);
	}
}
