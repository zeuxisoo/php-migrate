<?php
error_reporting(E_ALL & ~E_NOTICE);

define('IN_APPS', true);

require_once dirname(__FILE__).'/library/database/database_adapter.php';
require_once dirname(__FILE__).'/library/database/mysql_adapter.php';
require_once dirname(__FILE__).'/library/database.php';
require_once dirname(__FILE__).'/library/migrate.php';
require_once dirname(__FILE__).'/library/migration.php';

Migrate::config(array(
	'migrate_folder' => dirname(__FILE__).'/migrate',
));

Database::init(array(
	'adapter'	=> "mysql",
	'host'		=> "127.0.0.1",	// Do not use localhost instead of 127.0.0.1, throw mysql error
	'username'	=> "root",
	'password'	=> "root",
	'database'	=> "test",
	'charset'	=> 'utf-8',
	'port'		=> "3306",
	'prefix'	=> "test_",
	'debug'		=> true,
));

class Cmd {
	public static function run() {
		$arguments = self::arguments($_SERVER['argv']);

		switch(isset($arguments['1']) === true ? $arguments['1'] : "") {
			case 'init':
				self::write(Migrate::init());
				break;
			case 'db:create':
				self::write(Migrate::create($arguments['2']));
				break;
			case 'db:up':
				self::write(Migrate::up());
				break;
			case 'db:down':
				self::write(Migrate::down());
				break;
			case 'db:latest':
				self::write(Migrate::latest());
				break;
			case 'db:current':
				self::write(Migrate::current());
				break;
			case 'db:version':
				if (isset($arguments['version']) === true || isset($arguments['v']) === true) {
					if (isset($arguments['version']) === true) {
						$version = $arguments['version'];
					}

					if (isset($arguments['v']) === true) {
						$version = $arguments['v'];
					}

					if (is_numeric($version) === true || $version > 0) {
						self::write(Migrate::version($version));
					}else{
						self::error("db:version require --version=N or -v N parameters");
					}
				}else{
					self::error("db:version require --version=N or -v N parameters");
				}
				break;
			case 'db:clean':
				$messages = "";
				foreach(Migrate::clean() as $message) {
					$messages .= $message.PHP_EOL;
				}
				self::write($messages);
				break;
			default:
				self::write(array(
					"Please enter actions:",
					"- init",
					"  (Create schema_version file)", "",
					"- db:create [filename]",
					"  (e.g. db:create create_user)", "",
					"- db:up",
					"  (Up to next version)", "",
					"- db:down",
					"  (Down to previous version)", "",
					"- db:latest",
					"  (Move to latest version)", "",
					"- db:current",
					"  (Move to configured version)",
					"  (Only support on defined \$config['db']['migration']['version'])", "",
					"- db:version --version=[0..N]",
					"  (Move to version [N])", "",
					"- db:clean",
				));
				break;
		}
	}

	public static function arguments($argv) {
		$arguments = array();
		for ($i = 1; $i < $_SERVER['argc']; $i++) {
			$argument = explode('=', $_SERVER['argv'][$i]);

			if (count($argument) > 1 || strncmp($argument[0], '-', 1) === 0) {
				$arguments[ltrim($argument[0], '-')] = isset($argument[1]) === true ? $argument[1] : true;
			}else{
				$arguments[$i] = $argument[0];
			}
		}
		return $arguments;
	}

	public static function write($message) {
		if (is_array($message) === true) {
			$message = implode(PHP_EOL, $message);
		}

		fwrite(STDOUT, "Message >> ".PHP_EOL.PHP_EOL.$message.PHP_EOL);
	}

	public static function error($message) {
		if (is_array($message)) {
			$message = implode(PHP_EOL, $message);
		}

		fwrite(STDERR, "Error >> ".PHP_EOL.PHP_EOL.$message.PHP_EOL);
	}

	public static function clear_screen() {
		echo chr(27)."[H".chr(27)."[2J";
	}
}

Cmd::run();
?>