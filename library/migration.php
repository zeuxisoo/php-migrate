<?php
if (defined("IN_APPS") === false) exit("Access Dead");

class Migration {
	protected static function create_table($table, $columns, $options = array()) {
		$default_options = array(
			'primary_keys' => array(),
			'if_not_exists' => true,
			'engine' => false,
			'charset' => null,
			'foreign_keys' => array()
		);

		$options = array_merge($default_options, $options);

		$sql  = 'CREATE TABLE';
		$sql .= $options['if_not_exists'] === true ? ' IF NOT EXISTS ' : ' ';
		$sql .= DataBase::quote_identifier(DataBase::table_prefix($table)).' (';
		$sql .= self::columns($columns);

		if (empty($options['primary_keys']) === false) {
			$key_name = DataBase::quote_identifier(implode('_', $options['primary_keys']));
			$primary_keys = DataBase::quote_identifier($options['primary_keys']);

			$sql .= ",\n\tPRIMARY KEY ".$key_name." (" . implode(', ', $options['primary_keys']) . ")";
		}

		if (empty($options['foreign_keys']) === false) {
			$sql .= self::process_foreign_keys($options['foreign_keys']);
		}

		$sql .= "\n)";
		$sql .= $options['engine'] !== false ? ' ENGINE = '.$options['engine'].' ' : '';
		$sql .= self::charset($options['charset'], true).";";

		return Database::instance()->update($sql);
	}

	protected static function drop_table($table) {
		return Database::instance()->query('DROP TABLE IF EXISTS '.DataBase::quote_identifier(DataBase::table_prefix($table)));
	}

	protected static function rename_table($table, $new_table_name) {
		return Database::instance()->update('RENAME TABLE '.DataBase::quote_identifier(DataBase::table_prefix($table)).' TO '.DataBase::quote_identifier(DataBase::table_prefix($new_table_name)));
	}

	public static function add_foreign_key($table, $foreign_key) {
		if (is_array($foreign_key) === false) {
			throw new InvalidArgumentException('Foreign key for add_foreign_key() must be specified as an array');
		}

		$sql = 'ALTER TABLE ';
		$sql .= DataBase::quote_identifier(DataBase::table_prefix($table)).' ';
		$sql .= 'ADD ';
		$sql .= ltrim(self::process_foreign_keys(array($foreign_key)), ',');

		return Database::instance()->update($sql);
	}

	protected static function add_columns($table, $columns) {
		return self::alter_columns('ADD', $table, $columns);
	}

	protected static function modify_columns($table, $columns) {
		return self::alter_columns('MODIFY', $table, $columns);
	}

	protected static function drop_columns($table, $columns) {
		return self::alter_columns('DROP', $table, $columns);
	}

	protected static function alter_columns($type, $table, $columns) {
		$sql = 'ALTER TABLE '.DataBase::quote_identifier(DataBase::table_prefix($table)).' ';

		if ($type === 'DROP') {
			if (is_array($columns) === false) {
				$columns = array($columns);
			}

			$map_columns = array();
			foreach($columns as $index => $column) {
				$map_columns[$index] = 'DROP '.DataBase::quote_identifier($column);
			}
			$columns = $map_columns;

			$sql .= implode(', ', $columns);
		}else{
			$use_brackets = in_array($type, array('ADD', 'CHANGE', 'MODIFY')) === false;

			if ($use_brackets === true) $sql .= $type.' ';
			if ($use_brackets === true) $sql .= '(';

			$sql .= self::columns($columns, ($use_brackets === false ? $type.' ' : ''));

			if ($use_brackets === true) $sql .= ')';
		}

		return Database::instance()->update($sql);
	}

	protected static function create_index($table, $index_columns, $index_name = '', $index = '') {
		static $accepted_index = array('UNIQUE', 'FULLTEXT', 'SPATIAL', 'NONCLUSTERED');

		// make sure the index type is uppercase
		if ($index !== '') $index = strtoupper($index);

		if (empty($index_name) === true) {
			if (is_array($index_columns) === true) {
				foreach ($index_columns as $key => $value) {
					if (is_numeric($key)) {
						$index_name .= ($index_name == '' ? '' : '_').$value;
					}else{
						$index_name .= ($index_name == '' ? '' : '_').str_replace(array('(', ')', ' '), '', $key);
					}
				}
			}else{
				$index_name = $index_columns;
			}
		}

		$sql = 'CREATE ';

		if ($index !== '') $sql .= in_array($index, $accepted_index) === true ? $index.' ' : '';

		$sql .= 'INDEX ';
		$sql .= DataBase::quote_identifier($index_name);
		$sql .= ' ON ';
		$sql .= DataBase::quote_identifier(DataBase::table_prefix($table));
		if (is_array($index_columns) === true) {
			$columns = '';
			foreach ($index_columns as $key => $value) {
				if (is_numeric($key)) {
					$columns .= ($columns=='' ? '' : ', ').DataBase::quote_identifier($value);
				}else{
					$columns .= ($columns=='' ? '' : ', ').DataBase::quote_identifier($key).' '.strtoupper($value);
				}
			}
			$sql .= ' ('.DataBase::quote_identifier($columns).')';
		}else{
			$sql .= ' ('.$index_columns.')';
		}

		return Database::instance()->update($sql);
	}

	protected static function drop_index($table, $index_name) {
		$sql = 'DROP INDEX '.DataBase::quote_identifier($index_name);
		$sql .= ' ON '.DataBase::quote_identifier(DataBase::table_prefix($table));

		return Database::instance()->update($sql);
	}

	//
	private static function columns($columns, $prefix = '') {
		$sql_columns = array();

		foreach ($columns as $column => $attribute) {
			$sql = "\n\t".$prefix;
			$attribute = array_change_key_case($attribute, CASE_UPPER);

			$sql .= DataBase::quote_identifier($column);
			$sql .= array_key_exists('NAME', $attribute) ? ' '.DataBase::quote_identifier($attribute['NAME']).' ' : '';
			$sql .= array_key_exists('TYPE', $attribute) ? ' '.$attribute['TYPE'] : '';
			$sql .= array_key_exists('LIMIT', $attribute) ? '('.$attribute['LIMIT'].')' : '';
			$sql .= array_key_exists('CHARSET', $attribute) ? self::charset($attribute['CHARSET']) : '';

			if (array_key_exists('UNSIGNED', $attribute) === true && $attribute['UNSIGNED'] === true) {
				$sql .= ' UNSIGNED';
			}

			if(array_key_exists('DEFAULT', $attribute) === true) {
				$sql .= ' DEFAULT '.$attribute['DEFAULT'];
			}

			if(array_key_exists('NULL', $attribute) === true && $attribute['NULL'] === true) {
				$sql .= ' NULL';
			}else{
				$sql .= ' NOT NULL';
			}

			if (array_key_exists('AUTO_INCREMENT', $attribute) && $attribute['AUTO_INCREMENT'] === true) {
				$sql .= ' AUTO_INCREMENT';
			}

			if (array_key_exists('FIRST', $attribute) === true) {
				$sql .= ' FIRST '.DataBase::quote_identifier($attribute['FIRST']);
			}elseif (array_key_exists('AFTER', $attribute) === true) {
				$sql .= ' AFTER '.DataBase::quote_identifier($attribute['AFTER']);
			}

			$sql_columns[] = $sql;
		}

		return implode(',', $sql_columns);
	}

	private static function charset($charset = null, $is_default = false) {
		global $config;

		if ($charset === null) {
			$charset = isset($config['db']['charset']) === true ? $config['db']['charset'] : null;
		}

		if (empty($charset)) {
			return '';
		}

		if (($pos = stripos($charset, '_')) !== false) {
			$charset = ' CHARACTER SET '.substr($charset, 0, $pos).' COLLATE '.$charset;
		} else {
			$charset = ' CHARACTER SET '.$charset;
		}

		if ($is_default === true) {
			$charset = ' DEFAULT'.$charset;
		}

		return $charset;
	}

	private static function process_foreign_keys($foreign_keys) {
		if (is_array($foreign_keys) === false) {
			return "";
		}

		$foreign_key_list = array();

		foreach($foreign_keys as $definition) {
			if (empty($definition['key']) === true) {
				continue;
			}

			if (empty($definition['reference']) === true) {
				continue;
			}

			if (empty($definition['reference']['table']) or empty($definition['reference']['column'])) {
				continue;
			}

			$sql = '';

			if (empty($definition['constraint']) === false) {
				$sql .= " CONSTRAINT ".$definition['constraint'];
			}

			$sql .= " FOREIGN KEY (".$definition['key'].')';
			$sql .= " REFERENCES ".$definition['reference']['table'].' (';

			if (is_array($definition['reference']['column']) === true) {
				$sql .= implode(', ', $definition['reference']['column']);
			}else{
				$sql .= $definition['reference']['column'];
			}

			$sql .= ')';

			if (empty($definition['on_update']) === false) $sql .= " ON UPDATE ".$definition['on_update'];
			if (empty($definition['on_delete']) === false) $sql .= " ON DELETE ".$definition['on_delete'];

			$foreign_key_list[] = "\n\t".ltrim($sql);
		}

		return ', '.implode(',', $foreign_key_list);
	}
}
