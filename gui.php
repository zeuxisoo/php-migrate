<?php
error_reporting(E_ALL & ~E_NOTICE);

session_start();

define('IN_APPS', true);
define('MIGRATE_ROOT', dirname(__FILE__).'/migrate');

require_once dirname(__FILE__).'/library/database/database_adapter.php';
require_once dirname(__FILE__).'/library/database/mysql_adapter.php';
require_once dirname(__FILE__).'/library/database.php';
require_once dirname(__FILE__).'/library/migrate.php';
require_once dirname(__FILE__).'/library/migration.php';

Migrate::config(array(
	'migrate_folder' => MIGRATE_ROOT,
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

class GUI {
	private static $base_uri = "";
	private static $uri = "";
	private static $scheme = "";

	public static function run() {
		$action = isset($_GET['action']) ? htmlspecialchars($_GET['action']) : "";

		if (isset($_POST['action']) === true) {
			$action = htmlspecialchars($_POST['action']);
		}

		switch($action) {
			case "db:init":
				$result = Migrate::init();

				if (strstr('error', $result) === false) {
					self::flash('error', $result);
				}else{
					self::flash('success', $result);
				}

				self::redirect(self::site_url());
				break;
			case "db:clean":
				$messages = array();
				foreach(Migrate::clean() as $message) {
					$messages[] = $message;
				}

				self::flash('success', implode("<br>", $messages));

				self::redirect(self::site_url());
				break;
			case "db:up":
				self::flash('info', Migrate::up());
				self::redirect(self::site_url());
				break;
			case "db:down":
				self::flash('info', Migrate::down());
				self::redirect(self::site_url());
				break;
			case "db:latest":
				self::flash('info', Migrate::latest());
				self::redirect(self::site_url());
				break;
			case "db:create":
				$name = isset($_POST['name']) ? htmlspecialchars($_POST['name']) : "";

				if (empty($name) === true) {
					self::flash('error', 'Please enter name for db:create');
				}else{
					self::flash('success', Migrate::create(str_replace(" ", "_", $name)));
				}

				self::redirect(self::site_url());
				break;
			case "db:version":
				$version = isset($_POST['version']) ? intval($_POST['version']) : "";

				if (is_numeric($version) === false || $version < 0 || $version === "") {
					self::flash('error', 'Please enter version number, must bigger than zero');
				}else{
					self::flash('success', Migrate::version($version));
				}

				self::redirect(self::site_url());
				break;
			default:
				$info = self::flash('info');
				$error = self::flash('error');
				$success = self::flash('success');

				$current_version = self::current_version();

				echo "<style>";
				echo "form { margin: 0px; padding: 0px; }";
				echo "p.info { color: #0088ff; }";
				echo "p.error { color: #ff0000 }";
				echo "p.success { color: #006600; }";
				echo "</style>";

				if (empty($info) === false) echo "<p class='info'>".$info."</p>";
				if (empty($error) === false) echo "<p class='error'>".$error."</p>";
				if (empty($success) === false) echo "<p class='success'>".$success."</p>";

				echo "<p>Current Version </p>";
				echo $current_version;
				echo "<p>Control Actions</p>";
				echo "<input type='button' name='init' value='db:init' onclick='window.location=\"?action=db:init\"'>";
				echo "<input type='button' name='clean' value='db:clean' onclick='window.location=\"?action=db:clean\"'>";
				echo "<br>";
				echo "<br>";
				echo "<form method='post'>";
				echo "<input type='hidden' name='action' value='db:create'>";
				echo "<input type='text' name='name' value='' size='20'>";
				echo "<input type='submit' name='create' value='db:create'>";
				echo "</form>";
				echo "<br>";
				echo "<form method='post'>";
				echo "<input type='hidden' name='action' value='db:version'>";
				echo "<input type='text' name='version' value='' size='3'>";
				echo "<input type='submit' name='go' value='db:version'>";
				echo "</form>";
				echo "<br>";
				echo "<input type='button' name='up' value='db:up' onclick='window.location=\"?action=db:up\"'>";
				echo "<input type='button' name='down' value='db:down' onclick='window.location=\"?action=db:down\"'>";
				echo "<input type='button' name='latest' value='db:latest' onclick='window.location=\"?action=db:latest\"'>";
				echo "<p>Migrations List</p>";

				foreach(glob(MIGRATE_ROOT."/*.php") as $file_path) {
					$file_name = basename($file_path);

					if (preg_match('/^(\d+)_(\w+).php$/', $file_name, $match) == true) {
						$version_number = intval($match[1]);
						$class_name = strtolower($match[2]);

						if ($version_number < $current_version) {
							$color = "blue";
						}elseif ($version_number == $current_version) {
							$color = "orange";
						}else{
							$color = "black";
						}

						printf(
							"<span style='color: %s'>- %s %s %s<br>",
							$color,
							$version_number,
							implode(" ", array_map("ucwords", explode("_", $class_name))),
							($version_number == $current_version) ? "<span style='color:#f088ff'><< (Current)</span>" : ""
						);
					}
				}
				break;
		}
	}

	private static function current_version() {
		$schema_version_path = MIGRATE_ROOT.'/schema_version.php';
		if (file_exists($schema_version_path) === true) {
			require_once $schema_version_path;
			return isset($schema_version['current']) ? $schema_version['current'] : "---";
		}
		return "---";
	}

	private static function flash($type, $message = '') {
		if (empty($message) === true) {
			$message = isset($_SESSION[$type]) ? $_SESSION[$type] : "";
			$_SESSION[$type] = "";
			return $message;
		}else{
			$_SESSION[$type] = $message;
		}
	}

	private static function redirect($url) {
		header("Location: $url");
	}

	private static function site_url() {
		$protocol = self::scheme(true);
		$domain = $_SERVER['HTTP_HOST'];
		$base_uri = self::base_uri(true);

		return $protocol."://".$domain.$base_uri;
	}

	private static function base_uri($reload = false) {
        if ($reload === true || is_null(self::$base_uri) === true) {
            $request_uri = isset($_SERVER['REQUEST_URI']) === true ? $_SERVER['REQUEST_URI'] : $_SERVER['PHP_SELF'];
            $script_name = $_SERVER['SCRIPT_NAME'];
            $base_uri = strpos($request_uri, $script_name) === 0 ? $script_name : str_replace('\\', '/', dirname($script_name));
            self::$base_uri = rtrim($base_uri, '/');
        }
        return self::$base_uri;
    }

    public static function uri($reload = false) {
        if ($reload === true || is_null(self::$uri) === true) {
            $uri = '';

            if (empty($_SERVER['PATH_INFO']) === false) {
                $uri = $_SERVER['PATH_INFO'];
            }else{
                if (isset($_SERVER['REQUEST_URI']) === true) {
                    $uri = parse_url(self::scheme().'://'.$_SERVER['HTTP_HOST'].$_SERVER['REQUEST_URI'], PHP_URL_PATH);
                }else if (isset($_SERVER['PHP_SELF']) === true) {
                    $uri = $_SERVER['PHP_SELF'];
                }else{
					throw new RuntimeException('Unable to detect request URI');
                }
            }

            if (self::base_uri() !== '' && strpos($uri, self::base_uri()) === 0) {
                $uri = substr($uri, strlen(self::base_uri()));
            }

            self::$uri = '/' . ltrim($uri, '/');
        }

        return self::$uri;
    }

    public static function scheme($reload = false) {
        if ($reload === true || is_null(self::$scheme) ===true) {
            self::$scheme = (empty($_SERVER['HTTPS']) === true || $_SERVER['HTTPS'] === 'off') ? 'http' : 'https';
        }

        return self::$scheme;
    }
}

GUI::run();
