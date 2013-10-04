<?php
class Migrate {
	private static $migrate_folder   = "./migrate";
	private static $migrate_template = "./template";

	private static $default_schema_version = array(
		'current' => 0,	// for up/down/latest/version, markdown current version
		'next' => 0,	// for create action, generate version number
	);

	public static function config($configs) {
		foreach($configs as $key => $value) {
			self::$$key = $value;
		}
	}

	public static function init() {
		if (file_exists(self::schema_version_path()) === false) {
			file_put_contents(self::schema_version_path(), self::schema_version_content(self::$default_schema_version));
			return "[Initial] Migrate initialled";
		}else{
			return "[Error] Can not initial again";
		}
	}

	public static function create($name) {
		if (empty($name) === true) {
			return "[Error] Please enter migrate name to create";
		}else{
			$file_name = self::next_migrate_number()."_".$name.".php";
			$file_path = self::$migrate_folder."/".$file_name;

			$template = file_get_contents(self::$migrate_template.'/create.php');
			$template = str_replace(
				array('{CLASS_NAME}'),
				implode("_", array_map("ucwords", explode("_", $name))),
				$template
			);

			file_put_contents($file_path, $template);

			return "[Created] ".basename($file_name);
		}
	}

	public static function clean() {
		$file_list = array();
		foreach(glob(self::$migrate_folder."/*.php") as $file_path) {
			if (preg_match("/schema_version\.php$/i", $file_path) == false) {
				if (@unlink($file_path)) {
					 $file_list[] = "[Deleted] ".basename($file_path);
				}else{
					 $file_list[] = "[Error] ".basename($file_path);
				}
			}
		}

		// Update next schema version numebr
		require self::schema_version_path();
		$schema_version['next'] = 0;
		file_put_contents(self::schema_version_path(), self::schema_version_content($schema_version));

		return $file_list;
	}

	public static function up() {
		$next_version = self::current_schema_version() + 1;

		return self::version($next_version);
	}

	public static function down() {
		$next_version = self::current_schema_version() - 1;

		if ($next_version < 0) {
			return false;
		}else{
			return self::version($next_version);
		}
	}

	public static function latest() {
		return self::version(null);
	}

	public static function current() {
		global $config;

		return self::version($config['db']['migration']['version']);
	}

	public static function version($version) {
		$migrations = self::find_migrations(self::current_schema_version(), $version);

		if ($version === null && empty($migrations) === false) {
			$keys = array_keys($migrations);
			$version = end($keys);
		}

		// Trigger by latest()
		if (empty($migrations) === true || self::current_schema_version() === $version) {
			return "Migration: everything up-to-date";
		}

		$start = self::current_schema_version();
		$stop = $version;

		$method = $version > self::current_schema_version() ? 'up' : 'down';

		$runnable_migrations = array();
		foreach ($migrations as $ver => $path) {
			$file = basename($path);

			if (preg_match('/^\d+_(\w+).php$/', $file, $match)) {
				$class_name = ucfirst(strtolower($match[1]));

				include_once $path;

				$class = $class_name."_migration";

				if (class_exists($class, false) === false) {
					return "Migration: class $class not exists";
				}

				if (is_callable($class, 'up') === false || is_callable($class, 'down') === false) {
					return "Migration: class $class has not method up or down";
				}

				$runnable_migrations[$ver] = $class;
			}else{
				return "Migration: invalid migations filename %file";
			}
		}

		$runnable = array();
		foreach ($runnable_migrations as $version => $class) {
			call_user_func(array(new $class, $method));

			self::update_schema_version($version);
		}

		return "Now in Version: ".self::current_schema_version();
	}

	//
	private static function schema_version_path() {
		return self::$migrate_folder."/schema_version.php";
	}

	private static function next_migrate_number() {
		require self::schema_version_path();
		$schema_version['next'] = $schema_version['next'] + 1;
		file_put_contents(self::schema_version_path(), self::schema_version_content($schema_version));

		return str_pad($schema_version['next'], 3, '0', STR_PAD_LEFT);
	}

	private static function current_schema_version() {
		require self::schema_version_path();
		return $schema_version['current'];
	}

	private static function next_schema_version() {
		require self::schema_version_path();
		return $schema_version['next'];
	}

	public static function update_schema_version($version) {
		require self::schema_version_path();

		$schema_version['current'] = $version;

		file_put_contents(self::schema_version_path(), self::schema_version_content($schema_version));
	}

	private static function schema_version_content($content) {
		return '<?'."php\nif (defined('IN_APPS') === false) exit('Access Dead');\n".'$schema_version = '.var_export($content, true).";\n?>";
	}

	private static function find_migrations($start_version, $end_version) {
		$files = array(); $full_paths = array();
		foreach(glob(self::$migrate_folder."/*.php") as $file_path) {
			if (preg_match("/schema_version\.php$/i", $file_path) == false) {
				$files[] = basename($file_path);
				$full_paths[] = $file_path;
			}
		}

		if ($end_version === null) {
			$direction = 'up';
		}else{
			$direction = $start_version > $end_version ? 'down' : 'up';
		}

		// Swap version for down (e.g. 10 > 1)
		if ($direction === 'down') {
			$temp_version = $start_version;
			$start_version = $end_version;
			$end_version = $temp_version;
		}

		$migrations = array();
		foreach ($files as $index => $file) {
			preg_match('/^(\d+)_(\w+).php$/', $file, $matches);
			$version = intval($matches[1]);
			if ($version > $start_version) {
				if ($end_version === null || $version <= $end_version) {
					$migrations[$version] = $full_paths[$index];
				}
			}
		}
		ksort($migrations, SORT_NUMERIC);


		if ($direction === 'down') {
			$keys = array_keys($migrations);

			$replacement = $keys;
			array_unshift($replacement, $start_version);

			for ($i=0; $i < count($keys); $i++) {
				$keys[$i] = $replacement[$i];
			}

			$migrations = array_combine($keys, $migrations);
			$migrations = array_reverse($migrations, true);
		}

		return $migrations;
	}
}
