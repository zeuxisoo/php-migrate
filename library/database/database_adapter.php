<?php
if (defined('IN_APPS') === false) exit('Access Dead');

abstract class Database_Adapter {
	public $prefix = "";

	abstract public function connect($host, $username, $password, $database, $prefix, $charset, $port);
	abstract public function query($sql, $type = '');
	abstract public function update($sql, $type = '');
	abstract public function close();
	abstract public function set_debug($status = false);
	abstract public function get_debug_log();

	abstract public function get_error($sql = '');
	abstract public function get_error_no();

	abstract public function escape($data);
	abstract public function get_last_insert_id();

	abstract public function fetch_array($sql, $type = '');
	abstract public function result($query, $column_number);
	abstract public function free_result($query);

	// extra method
	abstract public function fetch_one($sql, $type = '');
	abstract public function fetch_all($sql, $type = '');

	//
	protected function halt($message, $sql = '') {
		$driver = __CLASS__;
		$error = $this->get_error();
		$error_no = $this->get_error_no();

		// filter prefix for security issue
		if (isset(Database::instance()->prefix)) {
			$sql = str_replace(Database::instance()->prefix, "..", $sql);
		}

		echo sprintf("SQL:\n\n%s\n\nError:\n\n%s\n", $sql, $error);
		exit;
	}

	public function quote_identifier($column) {
		return "`".$column."`";
	}

}
