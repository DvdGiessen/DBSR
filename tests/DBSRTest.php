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

	// We need DBSR before we can test it!
	require_once 'DBSR.php';

	/**
	 * PHPUnit tests for the DBSR class.
	 *
	 * @author Daniël van de Giessen
	 * @package DBSR
	 */
	class DBSRTest extends PHPUnit_Extensions_Database_TestCase {
		/**
		 * PDO schema used for connecting to the MySQL server
		 * @var string
		 */
		const PDO_SCHEMA = 'localhost;dbname=DBSRTest';

		/**
		 * PDO username used for connecting to the MySQL server
		 * @var string
		 */
		const PDO_USERNAME = NULL;

		/**
		 * PDO password used for connecting to the MySQL server
		 * @var string
		 */
		const PDO_PASSWORD = NULL;

		/**
		 * The PDO-object representing the database connection.
		 * @var PDO
		 */
		private $pdo;

		/**
		 * @return PHPUnit_Extensions_Database_DB_IDatabaseConnection
		 */
		public function getConnection() {
			$this->pdo = new PDO('mysql:' . self::PDO_SCHEMA . ';charset=utf8', self::PDO_USERNAME, self::PDO_PASSWORD, array(
				PDO::MYSQL_ATTR_INIT_COMMAND => 'SET NAMES utf8',
			));
			return $this->createDefaultDBConnection($this->pdo, self::PDO_SCHEMA);
		}

		/**
		 * @return PHPUnit_Extensions_Database_DataSet_IDataSet
		 */
		public function getDataSet() {
			// TODO: create temporary tables using something like the WP testing XML export
			// https://wpcom-themes.svn.automattic.com/demo/theme-unit-test-data.xml
		}

		public function testCreateClass() {
			// Find a non-existing class name
			do {
				$className = 'DBSRTest_RandomClass_' . mt_rand(0, PHP_INT_MAX);
			} while(class_exists($className, FALSE));

			// Run the static method
			DBSR::createClass($className);

			// Test existance of previously nonexisting class
			$this->assertTrue(class_exists($className, FALSE), 'Testing class existance after calling DBSR::createClass()...');

			// Test errorless behaviour when calling with a existing class
			DBSR::createClass('DBSRTest');
		}

		public function testGetPHPType() {
			// Test case array
			$testCases = array();

			// Base test case settings (for automatic generation)
			// TODO: add BIT and all date/time types
			$baseTestCases = array(
				'boolean' => array(
					'BOOL' 				=> 	0,
					'BOOLEAN' 			=> 	0,
				),
				'integer' => array(
					'INT' 				=> 	1,
					'INTEGER' 			=> 	1,
					'TINYINT' 			=> 	1,
					'SMALLINT' 			=> 	1,
					'MEDIUMINT' 		=> 	1,
					'BIGINT' 			=> 	1,
				),
				'float' => array(
					'DECIMAL' 			=> 	2,
					'NUMERIC' 			=> 	2,
					'FLOAT' 			=> 	2,
					'DOUBLE' 			=> 	2,
					'DOUBLE PRECISION' 	=> 	2,
					'REAL' 				=> 	2,
					'DEC' 				=> 	2,
					'FIXED' 			=> 	2,
				),
				'string' => array(
					'CHAR' 				=> 	1,
					'VARCHAR' 			=> 	1,
					'BINARY' 			=> 	1,
					'VARBINARY' 		=> 	1,
					'BLOB' 				=> 	0,
					'TINYBLOB' 			=> 	0,
					'MEDIUMBLOB' 		=> 	0,
					'LONGBLOB' 			=> 	0,
					'TEXT' 				=> 	0,
					'TINYTEXT' 			=> 	0,
					'MEDIUMTEXT' 		=> 	0,
					'LONGTEXT' 			=> 	0,
					'ENUM' 				=> 	0,
					'SET' 				=> 	0,
				),
			);

			// Generate numeric test cases
			foreach($baseTestCases as $expected => $tests) {
				foreach($tests as $type => $argc) {
					$testCases[$expected][] = $type;
					$testCases[$expected][] = strtolower($type);
					$testCases[$expected][] = ' ' . $type . ' ';
					if($argc >= 1) {
						$testCases[$expected][] = $type . '(10)';
						$testCases[$expected][] = strtolower($type) . '(10)';
						$testCases[$expected][] = ' ' . $type . '(10) ';
						$testCases[$expected][] = $type . '( 10 )';
					}
					if($argc >= 2) {
						$testCases[$expected][] = $type . '(10,5)';
						$testCases[$expected][] = strtolower($type) . '(10,5)';
						$testCases[$expected][] = ' ' . $type . '(10,5) ';
						$testCases[$expected][] = $type . '( 10 , 5 )';
					}
				}
			}

			// Run test cases
			foreach($testCases as $expected => $testCase) {
				foreach($testCase as $test) {
					$this->assertEquals($expected, DBSR::getPHPType($test), 'MySQL type "' . $test . '" should convert to a PHP ' . $expected);
				}
			}

		}


	}
