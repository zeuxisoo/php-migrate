<?php
if (defined('IN_APPS') === false) exit('Access Dead');

class MySQL_Adapter extends Database_Adapter {

	public $debug_log 		= array();
	public $query_time 		= 0;
	public $query_count 	= 0;
	public $update_count	= 0;
	public $last_insert_id 	= 0;
	public $debug 			= false;

	public function __construct($config) {
		$this->connect(
			$config['host'],
			$config['username'],
			$config['password'],
			$config['database'],
			$config['prefix'],
			$config['charset'],
			$config['port']
		);
	}

	public function connect($host, $username, $password, $database, $prefix, $charset, $port) {
		if(!@mysql_connect($host, $username,  $password)) {
			$this->halt('Can not connect to MySQL Server');
		}

		if(mysql_get_server_info() > '4.1') {
			$charset = str_replace('-', '', $charset);

			$this->query("SET NAMES '".$charset."'");
			$this->query("SET CHARACTER_SET_CLIENT = '".$charset."';");
			$this->query("SET CHARACTER_SET_RESULTS = '".$charset."';");

			if(mysql_get_server_info() > '5.0.1'){
				$this->query("SET sql_mode=''");
			}
		}

		if (!@mysql_select_db($database)) {
			$this->halt('Can not select database');
		}

		// Parent attribute
		$this->prefix = $prefix;
	}

	public function query($sql, $type = '') {
		if($type === 'UNBUFFERED' && function_exists('mysql_unbuffered_query')) {
			if ($query = mysql_unbuffered_query($sql) === false) {
				$this->halt('MySQL UnBuffered Query Error', $sql);
			}
		} else {
			if($type === 'CACHE' && intval(mysql_get_server_info()) >= 4) {
				$sql = 'SELECT SQL_CACHE '.substr($sql, 6);
			}

			if(($query = mysql_query($sql)) === false && $type !== 'SILENT') {
				$this->halt('MySQL Query Error', $sql);
			}
		}

		$this->query_time += $end_time;
		$this->query_count++;

		$this->last_insert_id = mysql_insert_id();

		return $query;
	}

	public function update($sql, $type = '') {
		$query = $this->query($sql, 'UNBUFFERED');

		$this->query_time += $end_time;
		$this->query_count++;

		return $query;
	}

	public function close() {
		return mysql_close();
	}

	public function set_debug($status = false) {
		$this->debug = $status;
	}

	public function get_debug_log() {
		return $this->debug_log;
	}

	//
	public function get_error($sql = '') {
		return mysql_error();
	}

	public function get_error_no() {
		return mysql_errno();
	}

	//
	public function escape($data) {
		if(is_array($data)) {
			return array_map("mysql_real_escape_string", $data);
		}
		return mysql_real_escape_string($data);
	}

	public function get_last_insert_id() {
		return $this->last_insert_id;
	}

	//
	function fetch_array($query, $type = MYSQL_ASSOC) {
		return mysql_fetch_array($query, $type);
	}

	public function result($query, $column_number) {
		return mysql_result($query, $column_number);
	}

	public function free_result($query) {
		return mysql_free_result($query);
	}

	//
	public function fetch_one($sql, $type = MYSQL_ASSOC) {
		$result = $this->query($sql);
		$record = $this->fetch_array($result, $type);
		$this->free_result($result);
		return $record;
	}

	public function fetch_all($sql, $type = MYSQL_ASSOC) {
		$records = array();
		$result = $this->query($sql);
		while($row = $this->fetch_array($result, MYSQL_ASSOC)) {
			$records[] = $row;
		}
		$this->free_result($result);
		return $records;
	}

	//
	public function debug_query($sql, $query_time) {
		if(preg_match("#^select#i", strtolower(trim($sql)))) {

			$sql_info = array();
			$query = mysql_query("EXPLAIN $sql");
			while($row = mysql_fetch_array($query)) {
				$sql_info[] = $row;
			}

			$this->debug_log[] = array(
				'type' => "select",
				'query_count' => $this->query_count,
				'sql' => $sql,
				'sql_info' => $sql_info,
				'query_time' => $query_time,
			);

		}else{
			$this->debug_log[] = array(
				'type' => "update",
				'query_count' => $this->query_count,
				'sql' => $sql,
				'query_time' => $query_time,
			);
		}
	}

}
