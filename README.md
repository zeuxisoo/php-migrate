### Installation

1. update permission `chmod 777 migrate`
2. ran command `php cmd.php init`
	
### Usage

**Support MYSQL Only**

**Console Mode**
	
	php cmd.php help

**Web Interface**

	http://path/to/php-migrate/gui.php

**Sample**

Create migration file of user table

	php cmd.php db:create create_user_table

Update content of migration file

	/*
	 * Reference Function:	
	 *   http://docs.fuelphp.com/classes/database/dbutil.html
	 */
	class Create_User_Table_Migration extends Migration {
		public function up() {
			$this->create_table('user', array(
				'id' => array('type' => 'int', 'unsigned' => true, 'auto_increment' => true),
				'username' => array('type' => 'varchar', 'limit' => 30),
				'password' => array('type' => 'varchar', 'limit' => 32),
			), array(
				'primary_keys' => array('id'),
				'charset' => 'utf8'
			));
		}
		
		public function down() {
			$this->drop_table('user');
		}
	}
	
Update DB schema

	php cmd.php db:up

### License

	The BSD 2-Clause License
	
### Thanks

	The FuelPHP Framework