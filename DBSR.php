<?php
	/* This file is part of DBSR.
	 *
	 * DBSR is free software: you can redistribute it and/or modify
	 * it under the terms of the GNU General Public License as published by
	 * the Free Software Foundation, either version 3 of the License, or
	 * (at your option) any later version.
	 *
	 * DBSR is distributed in the hope that it will be useful,
	 * but WITHOUT ANY WARRANTY; without even the implied warranty of
	 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
	 * GNU General Public License for more details.
	 *
	 * You should have received a copy of the GNU General Public License
	 * along with DBSR.  If not, see <http://www.gnu.org/licenses/>.
	 */

	/**
	 * DBSR provides functionality for commiting search-and-replace-operations on MySQL databases.
	 *
	 * @author Daniël van de Giessen
	 * @package DBSR
	 * @version 2.1.0
	 */
	class DBSR {
		/* Constants */
		/**
		 * Version string indicating the DBSR version.
		 * @var string
		 */
		const VERSION = '2.1.0';

		/**
		 * Option: use case-insensitive search and replace.
		 * @var boolean
		 */
		const OPTION_CASE_INSENSITIVE = 0;

		/**
		 * Option: process *all* database rows.
		 * @var boolean
		 */
		const OPTION_EXTENSIVE_SEARCH = 1;

		/**
		 * Option: number of rows to process simultaneously.
		 * @var integer
		 */
		const OPTION_SEARCH_PAGE_SIZE = 2;

		/**
		 * Option: use strict matching.
		 * @var boolean
		 */
		const OPTION_VAR_MATCH_STRICT = 3;

		/**
		 * Option: up to how many decimals floats should be matched.
		 * @var integer
		 */
		const OPTION_FLOATS_PRECISION = 4;

		/**
		 * Option: automatically convert character sets.
		 * @var boolean
		 */
		const OPTION_CONVERT_CHARSETS = 5;

		/**
		 * Option: cast all replace-values to the original type.
		 * @var boolean
		 */
		const OPTION_VAR_CAST_REPLACE = 6;

		/**
		 * Option: write changed values back to the database.
		 * @var boolean
		 */
		const OPTION_DB_WRITE_CHANGES = 7;

		/**
		 * Option: interpret serialized strings as PHP types.
		 * @var boolean
		 */
		const OPTION_HANDLE_SERIALIZE = 8;

		/**
		 * Option: reverses the filters causing to search *only* in mentioned tables/columns.
		 * @var array
		 */
		const OPTION_REVERSED_FILTERS = 9;

		/* Static methods */
		/**
		 * Creates a new class with the given name if it does not exists.
		 *
		 * @param string $className The name of the class.
		 */
		public static function createClass($className) {
			if(!class_exists($className, FALSE)) eval('class ' . $className . ' {}');
		}

		/**
		 * Returns the PHP type for any MySQL type according to the PHP's settype() documentation.
		 * Will return 'string' for unknown / invalidly formatted types.
		 *
		 * @see http://php.net/manual/en/function.settype.php
		 *
		 * @param 	string $mysql_type 	The MySQL type.
		 *
		 * @return 	string 				The corresponding PHP type.
		 */
		public static function getPHPType($mysql_type) {
			// MySQL type regexes and corresponding PHP type
			$types = array(
				/* Boolean types */
				'/^\s*BOOL(EAN)?\s*$/i' 												=> 'boolean',

				/* Integer types */
				'/^\s*TINYINT\s*(?:\(\s*\d+\s*\)\s*)?$/i' 								=> 'integer',
				'/^\s*SMALLINT\s*(?:\(\s*\d+\s*\)\s*)?$/i' 								=> 'integer',
				'/^\s*MEDIUMINT\s*(?:\(\s*\d+\s*\)\s*)?$/i' 							=> 'integer',
				'/^\s*INT(EGER)?\s*(?:\(\s*\d+\s*\)\s*)?$/i' 							=> 'integer',
				'/^\s*BIGINT\s*(?:\(\s*\d+\s*\)\s*)?$/i' 								=> 'integer',

				/* Float types */
				'/^\s*FLOAT\s*(?:\(\s*\d+\s*(?:,\s*\d+\s*)?\)\s*)?$/i' 					=> 'float',
				'/^\s*DOUBLE(\s+PRECISION)?\s*(?:\(\s*\d+\s*(?:,\s*\d+\s*)?\)\s*)?$/i' 	=> 'float',
				'/^\s*REAL\s*(?:\(\s*\d+\s*(?:,\s*\d+\s*)?\)\s*)?$/i' 					=> 'float',
				'/^\s*DEC(IMAL)?\s*(?:\(\s*\d+\s*(?:,\s*\d+\s*)?\)\s*)?$/i' 			=> 'float',
				'/^\s*NUMERIC\s*(?:\(\s*\d+\s*(?:,\s*\d+\s*)?\)\s*)?$/i' 				=> 'float',
				'/^\s*FIXED\s*(?:\(\s*\d+\s*(?:,\s*\d+\s*)?\)\s*)?$/i' 					=> 'float',
			);

			// Try each type
			foreach($types as $regex => $type) {
				// Test on a whitespace-free version
				if(preg_match($regex, $mysql_type)) return $type;
			}

			// If nothing matches, return default (string)
			return 'string';
		}

		/* Properties */
		/**
		 * The PDO instance used for connecting to the database.
		 * @var PDO
		 */
		protected $pdo;

		/**
		 * The default charset used by the database connection.
		 * @var string
		 */
		private $_pdo_charset;

		/**
		 * The default collation used by the database connection.
		 * @var string
		 */
		private $_pdo_collation;

		/**
		 * The callback used by DBRunner.
		 * @var callback
		 */
		private $_dbr_callback;

		/**
		 * All options of the current instance.
		 * @var array
		 */
		protected $options = array(
			self::OPTION_CASE_INSENSITIVE => FALSE,
			self::OPTION_EXTENSIVE_SEARCH => FALSE,
			self::OPTION_SEARCH_PAGE_SIZE => 10000,
			self::OPTION_VAR_MATCH_STRICT => TRUE,
			self::OPTION_FLOATS_PRECISION => 5,
			self::OPTION_CONVERT_CHARSETS => TRUE,
			self::OPTION_VAR_CAST_REPLACE => TRUE,
			self::OPTION_DB_WRITE_CHANGES => TRUE,
			self::OPTION_HANDLE_SERIALIZE => TRUE,
			self::OPTION_REVERSED_FILTERS => FALSE,
		);

		/**
		 * The filters for tables/columns.
		 * @var array
		 */
		protected $filters = array();

		/**
		 * The search-values.
		 * @var array
		 */
		protected $search = array();

		/**
		 * The replace-values.
		 * @var array
		 */
		protected $replace = array();

		/**
		 * An array of search-values converted per charset.
		 * @var array
		 */
		protected $search_converted = array();

		/* Methods */
		/**
		 * Constructor: sets the PDO instance for use with this DBSR instance.
		 *
		 * @param 	PDO 						$pdo 	A PDO instance representing a connection to a MySQL database.
		 * @throws 	RuntimeException 					If the a required PHP extension is not available.
		 * @throws 	InvalidArgumentException 			If the given PDO instance does not represent a MySQL database.
		 */
		public function __construct(PDO $pdo) {
			// Check if the required PCRE library is available
			if(!extension_loaded('pcre')) {
				throw new RuntimeException('The pcre (Perl-compatible regular expressions) extension is required for DBSR to work!');
			}

			// Check if the PDO represents a connection to a MySQL database
			if($pdo->getAttribute(PDO::ATTR_DRIVER_NAME) != 'mysql') {
				throw new InvalidArgumentException('The given PDO instance is not representing an MySQL database!');
			}

			// Save the PDO instance
			$this->pdo = $pdo;
		}

        /**
         * Returns the value of an DBSR option.
         *
         * @param 	integer $option 	One of the DBSR::OPTION_* constants.
         *
         * @return 	mixed 				The value of the requested option or NULL if unsuccessful.
         */
		public function getOption($option) {
			return isset($this->options[$option]) ? $this->options[$option] : NULL;
		}

		/**
         * Sets an option on this instance.
         *
         * @param 	integer $attribute 	The attribute to be set.
         * @param 	mixed 	$value 		The new value for the given attribute.
         *
         * @throws 	PDOException 		If any database error occurs.
         * @throws 	PDOException 		If any database error occurs.
         */
		public function setOption($option, $value) {
			// Only set known options
			if(!isset($this->options[$option])) return FALSE;

			switch($option) {
				case self::OPTION_SEARCH_PAGE_SIZE:
					// Require the page size to be greater than 0
					if(is_integer($value) && $value > 0) {
						$this->options[$option] = $value;
						return TRUE;
					} else {
						return FALSE;
					}

				case self::OPTION_FLOATS_PRECISION:
					// Require the precision to be greater than or equal to 0
					if(is_integer($value) && $value >= 0) {
						$this->options[$option] = $value;
						return TRUE;
					} else {
						return FALSE;
					}

				default:
					// By default, check if the type is equal
					if(gettype($this->options[$option]) == gettype($value)) {
						// Allow setting the same type
						$this->options[$option] = $value;
						return TRUE;
					} else {
						// Don't allow setting the wrong type
						return FALSE;
					}

			}
		}

		/**
		 * Sets the filters by which to filter tables/columns.
		 *
		 * @param array 	$filters 	The filters as an associative array. For example:
		 * 								array(
		 *									'entire_table',
		 *									array(
		 *										'column',
		 *										'in',
		 *										'every',
		 *										'table',
		 *									),
		 *									'table' => 'specific_column',
		 *									'table' => array(
		 *										'specific',
		 *										'columns',
		 *									),
		 *								)
		 */
		public function setFilters(array $filters) {
			// Array for the parsed filters
			$filters_parsed = array();

			// For each filter
			foreach($filters as $key => $value) {
				if(is_int($key)) {
					if(is_string($value)) {
						// Entire table
						$filters_parsed[$value] = TRUE;
					} elseif(is_array($value)) {
						// Skip empty arrays
						if(!count($value)) continue;

						// Require strings
						foreach($value as $v) if(!is_string($v)) throw new InvalidArgumentException('Only strings qualify as column names!');

						// Save it
						if(isset($filters_parsed['.'])) {
							$filters_parsed['.'] = array_values(array_unique(array_merge($filters_parsed['.'], array_values($value))));
						} else {
							$filters_parsed['.'] = array_values(array_unique($value));
						}
					} else throw new InvalidArgumentException('The filter array can only contain strings or arrays!');
				} else {
					if(is_string($value)) {
						// Single column
						if(isset($filters_parsed[$key])) {
							$filters_parsed[$key] = array_values(array_unique(array_merge($filters_parsed[$key], array($value))));
						} else {
							$filters_parsed[$key] = array($value);
						}
					} elseif(is_array($value)) {
						// Skip empty arrays
						if(!count($value)) continue;

						// Require strings
						foreach($value as $v) if(!is_string($v)) throw new InvalidArgumentException('Only strings qualify as column names!');

						// Save it
						if(isset($filters_parsed[$key])) {
							$filters_parsed[$key] = array_values(array_unique(array_merge($filters_parsed[$key], array_values($value))));
						} else {
							$filters_parsed[$key] = array_values(array_unique($value));
						}
					} else throw new InvalidArgumentException('The filter array can only contain strings or arrays!');
				}
			}

			// Save the parsed filters
			$this->filters = $filters_parsed;
		}

		/**
		 * Resets all filters.
		 */
		public function resetFilters() {
			$this->filters = array();
		}

		/**
		 * Indicated whether the given table / column is filtered.
		 * @param string 	$table 		The name of the table.
		 * @param string 	$column 	(Optional.) Then name of the column.
		 */
		public function isFiltered($table, $column = NULL) {
			if($this->getOption(self::OPTION_REVERSED_FILTERS)) {
				// Reversed filters
				if($column == NULL) {
					// Never filter reversed based on table only, since there may be non-table-specific columns in it
					return FALSE;
				} else {
					// Process columns if the entire table is filtered or if the column is filtered for either this table or in global
					return !(
						isset($this->filters[$table]) && $this->filters[$table] === TRUE ||
						isset($this->filters[$table]) && in_array($column, $this->filters[$table], TRUE) ||
						isset($this->filters['.']) && in_array($column, $this->filters['.'], TRUE)
					);
				}
			} else {
				// Normal filters
				if($column == NULL) {
					// Only skip tables if the entire table is filtered
					return isset($this->filters[$table]) && $this->filters[$table] === TRUE;
				} else {
					// Skip columns if the entire table is filtered or if the column is filtered for either this table or in global
					return
						isset($this->filters[$table]) && $this->filters[$table] === TRUE ||
						isset($this->filters[$table]) && in_array($column, $this->filters[$table], TRUE) ||
						isset($this->filters['.']) && in_array($column, $this->filters['.'], TRUE)
					;
				}
			}
		}

		/**
		 * Sets the search- and replace-values.
		 *
		 * @param 	array 						$search 	The values to search for.
		 * @param 	array 						$replace 	The values to replace with.
		 * @throws 	InvalidArgumentException 				If the search- or replace-values are invalid.
		 */
		public function setValues(array $search, array $replace) {
			// Check array lengths
			if(count($search) == 0 || count($replace) == 0 || count($search) != count($replace)) {
				throw new InvalidArgumentException('The number of search- and replace-values is invalid!');
			}

			// Clean indices
			$search = array_values($search);
			$replace = array_values($replace);

			// Remove all identical values
			for($i = 0; $i < count($search); $i++) {
				if($search[$i] === $replace[$i]) {
					array_splice($search, $i, 1);
					array_splice($replace, $i, 1);
					$i--;
				}
			}

			// Check the length again
			if(count($search) == 0) throw new InvalidArgumentException('All given search- and replace-values are identical!');

			// Set the values
			$this->search = $search;
			$this->replace = $replace;
		}

		/**
		 * Runs a search- and replace-action on the database.
		 *
		 * @throws 	PDOException				If any database error occurs.
		 * @throws 	UnexpectedValueException 	If an error occurs processing data retrieved from the database.
		 * @return 	integer 					The number of changed rows.
		 */
		public function exec() {
			// Remove the time limit
			if(!ini_get('safe_mode') && ini_get('max_execution_time') != '0') {
				set_time_limit(0);
			}

			// Call the DBRunner
			return $this->DBRunner(array($this, 'searchReplace'));
		}

		/**
		 * Runs through the database and execs the provided callback on every value.
		 *
		 * @param 	callable 		$callback	The callback function to call on every value.
		 * @param 	array 			$search		(Optional.) Search value to limit the matched rows to.
		 * @throws 	PDOException				If any database error occurs.
		 * @throws 	UnexpectedValueException 	If an error occurs processing data retrieved from the database.
		 * @return 	integer 					The number of changed rows.
		 */
		protected function DBRunner($callback) {
			// Save the callback
			$this->_dbr_callback = $callback;

			// Count the number of changed rows
			$result = 0;

			// Set unserialize object handler
			$unserialize_callback_func = ini_set('unserialize_callback_func', get_class() . '::createClass');

			// PDO attributes to set
			$pdo_attributes = array(
				PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
			);

			// Set PDO attributes and save the old values
			foreach($pdo_attributes as $attribute => $value) {
				$pdo_attributes[$attribute] = $this->pdo->getAttribute($attribute);
				$this->pdo->setAttribute($attribute, $value);
			}

			// Catch all Exceptions so that we can reset the errormode before rethrowing it
			try {
				// Figure out the connection character set and collation
				$this->_pdo_charset = $this->pdo->query('SELECT @@character_set_client;', PDO::FETCH_COLUMN, 0)->fetch();
				$this->_pdo_collation = $this->pdo->query('SELECT @@collation_connection;', PDO::FETCH_COLUMN, 0)->fetch();

				// Get a list of all tables
				$tables = $this->pdo->query('SHOW TABLES;', PDO::FETCH_COLUMN, 0)->fetchAll();

				// Lock each table
				$this->pdo->query('LOCK TABLES `' . implode('` WRITE, `', $tables) . '` WRITE;');

				// Loop through all the (non-filtered) tables
				foreach($tables as $table) {
					if(!$this->isFiltered($table)) $result += $this->_DBRTable($table, $callback);
				}
			} catch(Exception $e) {}

			// Unlock all locked tables
			$this->pdo->query('UNLOCK TABLES');

			// Restore the old PDO attribute values
			foreach($pdo_attributes as $attribute => $value) {
				$this->pdo->setAttribute($attribute, $value);
			}

			// Reset the unserialize object handler
			ini_set('unserialize_callback_func', $unserialize_callback_func);

			// Check whether an exception was thrown
			if(isset($e) && $e instanceof Exception) {
				// Rethrow the exception
				throw $e;
			} else {
				// Return the results
				return $result;
			}
		}

		/**
		 * DBRunner: processes the given table.
		 *
		 * @param 	string 						$table 	Name of the table.
		 * @throws 	UnexpectedValueException 			If an error occurs processing data retrieved from the database.
		 * @return 	integer 							The number of changed rows.
		 */
		private function _DBRTable($table) {
			// List all columns of the current table
			$columns_info = $this->pdo->query('SHOW FULL COLUMNS FROM `' . $table . '`;', PDO::FETCH_NAMED);

			// Empty arrays for columns and keys
			$columns = array();
			$keys = array();

			// Process each column
			foreach($columns_info as $column_info) {
				// Determine type
				$columns[$column_info['Field']] = array(
					'null' 		=> 	($column_info['Null'] == 'YES'),
					'type' 		=> 	self::getPHPType($column_info['Type']),
					'charset' 	=> 	preg_replace('/^([a-z\d]+)_[\w\d]+$/i', '$1', $column_info['Collation']),
					'collation' => 	$column_info['Collation'],
				);

				// Determine wheter it's part of a candidate key
				$keys[$column_info['Key']][$column_info['Field']] = $columns[$column_info['Field']];
			}

			// Determine prefered candidate key
			if(isset($keys['PRI'])) {
				// Always prefere a primary key(set)
				$keys = $keys['PRI'];
			} elseif(isset($keys['UNI'])) {
				// Though a unique key(set) also works
				$keys = $keys['UNI'];
			} else {
				// If everything else fails, use the full column set
				$keys = $columns;
			}

			// Filter columns
			foreach($columns as $column => $column_info) {
				if($this->isFiltered($table, $column)) unset($columns[$column]);
			}

			// Prepare a smart WHERE-statement
			if(!$this->getOption(self::OPTION_EXTENSIVE_SEARCH)) {
				$where = $this->_DBRWhereSearch($columns);
			} else {
				// No WHERE-statement
				$where = '';
			}

			// Check if after filtering and WHERE-matching any valid columns are left
			if(count($columns) == 0) return;

			// Convert search-values to the correct charsets
			if($this->getOption(self::OPTION_CONVERT_CHARSETS)) {
				foreach($columns as $column => $column_info) if(!isset($this->search_converted[$column_info['charset']]) && $column_info['type'] == 'string' && !empty($column_info['charset']) && $column_info['charset'] != $this->_pdo_charset) {
					$search_convert = array();
					foreach($this->search as $i => $item) if(is_string($item)) {
						$this->search_converted[$column_info['charset']][$i] = $this->pdo->query('SELECT CONVERT(_' . $this->_pdo_charset . $this->pdo->quote($item) . ' USING ' . $column_info['charset'] . ');', PDO::FETCH_COLUMN, 0)->fetch();
					} else {
						$this->search_converted[$column_info['charset']][$i] = $item;
					}
				}
			}

			// Get the number of rows
			$row_count = (int) $this->pdo->query('SELECT COUNT(*) FROM `' . $table . '`' . $where . ';', PDO::FETCH_COLUMN, 0)->fetch();

			// Count the number of changed rows
			$row_change_count = 0;

			// For each page
			$page_size = $this->getOption(self::OPTION_SEARCH_PAGE_SIZE);
			for($page_start = 0; $page_start < $row_count; $page_start += $page_size) {
				// Get the rows of this page
				$rows = $this->pdo->query('SELECT DISTINCT * FROM `' . $table . '`' . $where . 'LIMIT ' . $page_start . ', ' . $page_size . ';', PDO::FETCH_ASSOC);

				// Loop over each row
				foreach($rows as $row) {
					if($this->_DBRRow($table, $columns, $keys, $row) > 0) $row_change_count++;
				}
			}

			// Return the number of changed rows
			return $row_change_count;
		}

		/**
		 * DBRunner: processes the given row.
		 *
		 * @param 	string 						$table		The name of the current table.
		 * @param 	array 						$columns 	The relevant columns of this table.
		 * @param 	array 						$keys		The candidate keyset for this table.
		 * @param 	array 						$row		The row to be processed.
		 * @throws 	UnexpectedValueException 				If an error occurs processing data retrieved from the database.
		 * @return 	integer 								The number of changed columns.
		 */
		private function _DBRRow($table, array $columns, array $keys, array $row) {
			// Array with row changes
			$changeset = array();

			// Convert columns
			foreach($columns + $keys as $column => $column_info) {
				if(!settype($row[$column], $column_info['type'])) {
					throw new UnexpectedValueException('Failed to convert `' . $table . '`.`' . $column . '` value to a ' . $column_info['type'] . ' for value "' . $row[$column] . '"!');
				}
			}

			// Loop over each column
			foreach($columns as $column => $column_info) {
				// Set the value
				$value = &$row[$column];

				// Call the callback
				if($this->getOption(self::OPTION_CONVERT_CHARSETS) && isset($this->search_converted[$column_info['charset']])) {
					$value_new = call_user_func($this->_dbr_callback, $value, $this->search_converted[$column_info['charset']], $this->replace);
				} else {
					$value_new = call_user_func($this->_dbr_callback, $value);
				}

				// Check the result
				if($value_new !== $value) {
					$changeset[$column] = $value_new;
				}
			}

			// Update the row if nessecary
			if(count($changeset) > 0 && $this->getOption(self::OPTION_DB_WRITE_CHANGES)) {
				// Build the WHERE-statement for this row
				$where = $this->_DBRWhereRow($keys, $row);

				// Determine the updates
				$updates = array();
				foreach($changeset as $column => $value_new) {
					switch($columns[$column]['type']) {
						case 'integer':
							$updates[] = '`' . $column . '` = ' . (int) $value_new;
							$search_where_column = TRUE;
							break;

						case 'float':
							$updates[] = '`' . $column . '` = ' . (string) round((float) $value_new, $this->getOption(self::OPTION_FLOATS_PRECISION));
							break;

						default:
						case 'string':
							// First, escape the string and add quotes
							$update_string = $this->pdo->quote((string) $value_new);

							// Then, check the charset
							if(!empty($columns[$column]['charset']) && $this->_pdo_charset != $columns[$column]['charset']) {
								if($this->getOption(self::OPTION_CONVERT_CHARSETS)) {
									$update_string = 'CONVERT(_' . $this->_pdo_charset . $update_string . ' USING ' . $columns[$column]['charset'] . ')';
								} else {
									$update_string = 'BINARY ' . $update_string;
								}
							}

							// Then, check the collation
							if(!empty($columns[$column]['collation']) && $this->getOption(self::OPTION_CONVERT_CHARSETS) && $this->_pdo_collation != $columns[$column]['collation']) {
								$update_string .= ' COLLATE ' . $columns[$column]['collation'];
							}

							// Finally, build and add the comparison for the WHERE-clause
							$updates[] = '`' . $column . '` = ' . $update_string;
							break;
					}
				}

				// Commit the updates
				$this->pdo->query('UPDATE `' . $table . '` SET ' . implode(', ', $updates) . $where . ';');
			}

			// Return the number of changed columns
			return count($changeset);
		}

		/**
		 * DBRunner: constructs the WHERE-clause for searching.
		 *
		 * @param 	array	$columns 	(Reference.) The columns to be searched. Inegible columns will be removed.
		 * @return 	mixed 				String with the constructed WHERE-clause, or FALSE if no column could be matched
		 * 								(thus the table may be skipped).
		 */
		private function _DBRWhereSearch(array &$columns) {
			// Array for WHERE-clause elements
			$where = array();

			// Loop over all columns
			foreach($columns as $column => $column_info) {
				// By default there's no reason to include this column
				$where_column = FALSE;

				// Loop over all search items
				foreach($this->search as $item) {
					// If there's a valid WHERE-component, add it
					if($where_component = $this->_DBRWhereColumn($column, $column_info, $item, FALSE)) {
						$where[] = $where_component;
						$where_column = TRUE;
					}
				}

				// Remove all columns which will never match since no valid WHERE-components could be constructed
				if(!$where_column) unset($columns[$column]);
			}

			// Combine the WHERE-clause or empty it
			if(count($where) > 0) {
				return ' WHERE ' . implode(' OR ', $where) . ' ';
			} else {
				// Assert count($columns) == 0
				if(count($columns) != 0) throw new LogicException('No WHERE-clause was constructed, yet there are valid columns left!');

				// Since there are no valid columns left, we can skip processing this table
				return FALSE;
			}
		}

		/**
		 * DBRunner: Constructs a WHERE-clause for the given row.
		 *
		 * @param 	array 	$keys 	The candidate keys to be used for constructing the WHERE-clause.
		 * @param 	array 	$row 	The row values.
		 * @return 	string 			The WHERE-clause for the given row.
		 */
		private function _DBRWhereRow(array $keys, array $row) {
			$where = array();
			foreach($keys as $key => $key_info) {
				$where[] = $this->_DBRWhereColumn($key, $key_info, $row[$key], TRUE);
			}
			return ' WHERE ' . implode(' AND ', $where) . ' ';
		}

		/**
		 * DBRunner: Constructs a WHERE component for the given column and value.
		 *
		 * @param 	string 	$column 		The column name.
		 * @param 	array 	$column_info 	Array with column info.
		 * @param 	mixed 	$value			The value to match.
		 * @param 	boolean $string_exact 	Whether to use 'LIKE %value%'-style matching.
		 *
		 * @return 	mixed					The WHERE component for the given parameters as a string,
		 * 									or FALSE if the value is not valid for the given column.
		 */
		private function _DBRWhereColumn($column, array $column_info, $value, $string_exact) {
			switch($column_info['type']) {
				case 'integer':
					// Search for integer value
					if(!$this->getOption(self::OPTION_VAR_MATCH_STRICT) || is_integer($value)) {
						// Add a where clause for the integer value
						return '`' . $column . '` = ' . (int) $value;
					}
					break;

				case 'float':
					// Search for float difference (since floats aren't precise enough to compare directly)
					if(!$this->getOption(self::OPTION_VAR_MATCH_STRICT) || is_float($value)) {
						return 'ABS(`' . $column . '` - ' . (float) $value . ') < POW(1, -' . $this->getOption(self::OPTION_FLOATS_PRECISION) . ')';
					}
					break;

				default:
				case 'string':
					// String search is even harder given the many possibly charsets

					// If the search item is a float, we have to limit it to the maximum precision first
					if(is_float($value)) {
						$value = round($value, $this->getOption(self::OPTION_FLOATS_PRECISION));
					}

					if(!$string_exact) {
						$value = '%' . (string) $value . '%';
					}

					// First, escape the string and add quotes
					$where_string = $this->pdo->quote((string) $value);

					// Then, check the charset
					if(!empty($column_info['charset']) && $this->_pdo_charset != $column_info['charset']) {
						if($this->getOption(self::OPTION_CONVERT_CHARSETS)) {
							$where_string = 'CONVERT(_' . $this->_pdo_charset . $where_string . ' USING ' . $column_info['charset'] . ')';
						} else {
							$where_string = 'BINARY ' . $where_string;
						}
					}

					// Then, check the collation
					if(!empty($column_info['collation']) && $this->getOption(self::OPTION_CONVERT_CHARSETS) && $this->_pdo_collation != $column_info['collation']) {
						if($this->getOption(self::OPTION_CASE_INSENSITIVE)) {
							$where_string .= ' COLLATE ' . preg_replace('/_cs$/i', '_ci', $column_info['collation']);
						} else {
							$where_string .= ' COLLATE ' . $column_info['collation'];
						}
					}

					// Column name
					$column = '`' . $column . '`';

					// Case insensitivity
					if(!empty($column_info['collation']) && $this->getOption(self::OPTION_CASE_INSENSITIVE) && preg_replace('/^.*_([a-z]+)$/i', '$1', $column_info['collation']) == 'cs') {
						 $column .= ' COLLATE ' . preg_replace('/_cs$/i', '_ci', $column_info['collation']);
					}

					// Add the column
					$where_string = $column . ' ' . ($string_exact ? '=' : 'LIKE') . ' ' . $where_string;

					if(!empty($column_info['charset']) && !$this->getOption(self::OPTION_CONVERT_CHARSETS) && $this->_pdo_charset != $column_info['charset']) {
						$where_string = 'BINARY ' . $where_string;
					}

					// Finally, build and add the comparison for the WHERE-clause
					return $where_string;
			}

			// It seems the value was not valid for this column
			return FALSE;
		}

		/**
		 * Runs a search-and-replace action on the provided value.
		 *
		 * @var 	mixed 	$value 		The value to search through.
		 * @var 	array 	$search 	(Optional.) The array of search-values. If not provided $this->search is used.
		 * @var 	array 	$replace 	(Optional.) The array of replace-values. If not provided $this->replace is used.
		 * @return 	mixed 				The value with all occurences of search items replaced.
		 */
		protected function searchReplace($value, array $search = NULL, array $replace = NULL) {
			// Check the search- and replace-values
			if(is_null($search)) $search = $this->search;
			if(is_null($replace)) $replace = $this->replace;

			// The new value
			$new_value = $value;

			// For each type
			switch(TRUE) {
				case is_array($value):
					// The result is also an array
					$new_value = array();
					// Loop through all the values
					foreach($value as $key => $element) {
						$new_value[$this->searchReplace($key)] = $this->searchReplace($element);
					}
					break;

				case is_bool($value):
					for($i = 0; $i < count($search); $i++) {
						if($new_value === $search[$i] || !$this->getOption(self::OPTION_VAR_MATCH_STRICT) && $new_value == $search[$i]) {
								$new_value = $replace[$i];
						}
					}
					break;

				case is_float($value):
					$float_precision = pow(10, -1 * $this->getOption(self::OPTION_FLOATS_PRECISION));
					for($i = 0; $i < count($search); $i++) {
						if(	is_float($search[$i]) && abs($new_value - $search[$i]) < $float_precision ||
							!$this->getOption(self::OPTION_VAR_MATCH_STRICT) && (
								$new_value == $search[$i] ||
								abs($new_value - (float) $search[$i]) < $float_precision
							)
						) {
							$new_value = $replace[$i];
						}
					}
					break;

				case is_int($value):
					for($i = 0; $i < count($search); $i++) {
						if($new_value === $search[$i] || !$this->getOption(self::OPTION_VAR_MATCH_STRICT) && $new_value == $search[$i]) {
							$new_value = $replace[$i];
						}
					}
					break;

				case is_object($value):
					// Abuse the fact that corrupted serialized strings are handled by our own regexes
					$new_value = unserialize($this->searchReplace(preg_replace('/^O:\\d+:/', 'O:0:', serialize($new_value))));
					break;

				case is_string($value):
					// Regex for detecting serialized strings
					$serialized_regex = '/^(a:\\d+:\\{.*\\}|b:[01];|d:\\d+\\.\\d+;|i:\\d+;|N;|O:\\d+:"[a-zA-Z_\\x7F-\\xFF][a-zA-Z0-9_\\x7F-\\xFF]*":\\d+:\\{.*\\}|s:\\d+:".*";)$/Ss';

					// Try unserializing it
					$unserialized = @unserialize($new_value);

					// Check if if actually was unserialized
					if($this->getOption(self::OPTION_HANDLE_SERIALIZE) && ($unserialized !== FALSE || $new_value === serialize(FALSE)) && !is_object($unserialized)) {
						// Process recursively
						$new_value = serialize($this->searchReplace($unserialized));
					} elseif($this->getOption(self::OPTION_HANDLE_SERIALIZE) && (is_object($unserialized) || preg_match($serialized_regex, $new_value))) {
						// If it looks like it's serialized, use special regexes for search-and-replace

						// TODO: split arrays/objects and process recursively?

						// Search and replace booleans
						if($changed_value = preg_replace_callback('/b:([01]);/S', array($this, '_searchReplace_preg_callback_boolean'), $new_value)) {
							$new_value = $changed_value;
						}

						// Search and replace integers
						if($changed_value = preg_replace_callback('/i:(\\d+);/S', array($this, '_searchReplace_preg_callback_integer'), $new_value)) {
							$new_value = $changed_value;
						}

						// Search and replace floats
						if($changed_value = preg_replace_callback('/d:(\\d+)\.(\\d+);/S', array($this, '_searchReplace_preg_callback_float'), $new_value)) {
							$new_value = $changed_value;
						}

						// Search-and-replace object names (and update length)
						if($changed_value = preg_replace_callback('/O:\\d+:"([a-zA-Z_\\x7F-\\xFF][a-zA-Z0-9_\\x7F-\\xFF]*)":(\\d+):{(.*)}/Ss', array($this, '_searchReplace_preg_callback_objectname'), $new_value)) {
							$new_value = $changed_value;
						}

						// Search-and-replace strings (and update length)
						if($changed_value = preg_replace_callback('/s:\\d+:"(.*?|a:\\d+:{.*}|b:[01];|d:\\d+\\.\\d+;|i:\d+;|N;|O:\\d+:"[a-zA-Z_\\x7F-\\xFF][a-zA-Z0-9_\\x7F-\\xFF]*":\\d+:{.*}|s:\\d+:".*";)";/Ss', array($this, '_searchReplace_preg_callback_string'), $new_value)) {
							$new_value = $changed_value;
						}

						// If the regexes didn't change anything, run a normal replace just to be sure
						if($new_value == $value) for($i = 0; $i < count($search); $i++) {
							if(is_string($this->search[$i]) || !$this->getOption(self::OPTION_VAR_MATCH_STRICT)) {
								$new_value = $this->getOption(self::OPTION_CASE_INSENSITIVE) ? str_ireplace((string) $search[$i], (string) $replace[$i], $new_value) : str_replace((string) $search[$i], (string) $replace[$i], $new_value);
							}
						}
					} else for($i = 0; $i < count($search); $i++) {
						// Do a normal search-and-replace
						if(is_string($search[$i]) || !$this->getOption(self::OPTION_VAR_MATCH_STRICT)) {
							$new_value = $this->getOption(self::OPTION_CASE_INSENSITIVE) ? str_ireplace((string) $search[$i], (string) $replace[$i], $new_value) : str_replace((string) $search[$i], (string) $replace[$i], $new_value);
						}
					}
					break;
			}

			// Return
			return $new_value;
		}

		/**
		 * searchReplace: Callback for serialized boolean replacement.
		 * @param 	array 	$matches 	The matches corresponding to the boolean value as provided by preg_replace_callback.
		 * @return 	string 				The serialized representation of the result.
		 */
		private function _searchReplace_preg_callback_boolean($matches) {
			$result = $this->searchReplace((boolean) $matches[1]);
			if(self::OPTION_VAR_CAST_REPLACE) $result = (boolean) $result;
			return serialize($result);
		}

		/**
		 * searchReplace: Callback for serialized integer replacement.
		 * @param 	array 	$matches 	The matches corresponding to the integer value as provided by preg_replace_callback.
		 * @return 	string 				The serialized representation of the result.
		 */
		private function _searchReplace_preg_callback_integer($matches) {
			$result = $this->searchReplace((integer) $matches[1]);
			if(self::OPTION_VAR_CAST_REPLACE) $result = (integer) $result;
			return serialize($result);
		}

		/**
		 * searchReplace: Callback for serialized float replacement.
		 * @param 	array 	$matches 	The matches corresponding to the float value as provided by preg_replace_callback.
		 * @return 	string 				The serialized representation of the result.
		 */
		private function _searchReplace_preg_callback_float($matches) {
			$result = $this->searchReplace((float) ($matches[1].'.'.$matches[2]));
			if(self::OPTION_VAR_CAST_REPLACE) $result = (float) $result;
			return serialize($result);
		}

		/**
		 * searchReplace: Callback for serialized object name replacement.
		 * @param 	array 	$matches 	The matches corresponding to the object name value as provided by preg_replace_callback.
		 * @return 	string 				The serialized representation of the result.
		 */
		private function _searchReplace_preg_callback_objectname($matches) {
			$name = preg_replace('/[^a-zA-Z0-9_\x7F-\xFF]+/', '', (string) $this->searchReplace($matches[1]));
			return 'O:' . strlen($name) . ':"' . $name . '":' . $matches[2] . ':{' . $matches[3] . '}';
		}

		/**
		 * searchReplace: Callback for serialized string replacement.
		 * @param 	array 	$matches 	The matches corresponding to the string value as provided by preg_replace_callback.
		 * @return 	string 				The serialized representation of the result.
		 */
		private function _searchReplace_preg_callback_string($matches) {
			$result = $this->searchReplace($matches[1]);
			if(self::OPTION_VAR_CAST_REPLACE) $result = (string) $result;
			return serialize($result);
		}
	}
