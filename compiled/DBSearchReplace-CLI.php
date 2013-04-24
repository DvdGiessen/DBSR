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
class DBSR{const VERSION='2.0.2';const OPTION_CASE_INSENSITIVE=0;const OPTION_EXTENSIVE_SEARCH=1;const OPTION_SEARCH_PAGE_SIZE=2;const OPTION_VAR_MATCH_STRICT=3;const OPTION_FLOATS_PRECISION=4;const OPTION_CONVERT_CHARSETS=5;const OPTION_VAR_CAST_REPLACE=6;const OPTION_DB_WRITE_CHANGES=7;const OPTION_HANDLE_SERIALIZE=8;public static function createClass($className){if(!class_exists($className,FALSE))eval('class '.$className.' {}');}public static function getPHPType($mysql_type){$types=array('/^\s*BOOL(EAN)?\s*$/i'=>'boolean','/^\s*TINYINT\s*(?:\(\s*\d+\s*\)\s*)?$/i'=>'integer','/^\s*SMALLINT\s*(?:\(\s*\d+\s*\)\s*)?$/i'=>'integer','/^\s*MEDIUMINT\s*(?:\(\s*\d+\s*\)\s*)?$/i'=>'integer','/^\s*INT(EGER)?\s*(?:\(\s*\d+\s*\)\s*)?$/i'=>'integer','/^\s*BIGINT\s*(?:\(\s*\d+\s*\)\s*)?$/i'=>'integer','/^\s*FLOAT\s*(?:\(\s*\d+\s*(?:,\s*\d+\s*)?\)\s*)?$/i'=>'float','/^\s*DOUBLE(\s+PRECISION)?\s*(?:\(\s*\d+\s*(?:,\s*\d+\s*)?\)\s*)?$/i'=>'float','/^\s*REAL\s*(?:\(\s*\d+\s*(?:,\s*\d+\s*)?\)\s*)?$/i'=>'float','/^\s*DEC(IMAL)?\s*(?:\(\s*\d+\s*(?:,\s*\d+\s*)?\)\s*)?$/i'=>'float','/^\s*NUMERIC\s*(?:\(\s*\d+\s*(?:,\s*\d+\s*)?\)\s*)?$/i'=>'float','/^\s*FIXED\s*(?:\(\s*\d+\s*(?:,\s*\d+\s*)?\)\s*)?$/i'=>'float',);foreach($types as $regex=>$type){if(preg_match($regex,$mysql_type))return $type;}return 'string';}protected $pdo;private $_pdo_charset;private $_pdo_collation;private $_dbr_callback;protected $options=array(self::OPTION_CASE_INSENSITIVE=>FALSE,self::OPTION_EXTENSIVE_SEARCH=>FALSE,self::OPTION_SEARCH_PAGE_SIZE=>10000,self::OPTION_VAR_MATCH_STRICT=>TRUE,self::OPTION_FLOATS_PRECISION=>5,self::OPTION_CONVERT_CHARSETS=>TRUE,self::OPTION_VAR_CAST_REPLACE=>TRUE,self::OPTION_DB_WRITE_CHANGES=>TRUE,self::OPTION_HANDLE_SERIALIZE=>TRUE,);protected $search=array();protected $replace=array();protected $search_converted=array();public function __construct(PDO$pdo){if(!extension_loaded('pcre')){throw new RuntimeException('The pcre (Perl-compatible regular expressions) extension is required for DBSR to work!');}if($pdo->getAttribute(PDO::ATTR_DRIVER_NAME)!='mysql'){throw new InvalidArgumentException('The given PDO instance is not representing an MySQL database!');}$this->pdo=$pdo;}public function getOption($option){return isset($this->options[$option])?$this->options[$option]:NULL;}public function setOption($option,$value){if(!isset($this->options[$option]))return FALSE;switch($option){case self::OPTION_SEARCH_PAGE_SIZE:if(is_integer($value)&&$value>0){$this->options[$option]=$value;return TRUE;}else{return FALSE;}case self::OPTION_FLOATS_PRECISION:if(is_integer($value)&&$value>=0){$this->options[$option]=$value;return TRUE;}else{return FALSE;}default:if(gettype($this->options[$option])==gettype($value)){$this->options[$option]=$value;return TRUE;}else{return FALSE;}}}public function exec(array$search,array$replace){if(count($search)==0||count($replace)==0||count($search)!=count($replace)){throw new InvalidArgumentException('The number of search- and replace-values is invalid!');}$search=array_values($search);$replace=array_values($replace);for($i=0;$i<count($search);$i++){if($search[$i]===$replace[$i]){array_splice($search,$i,1);array_splice($replace,$i,1);$i--;}}if(count($search)==0)throw new InvalidArgumentException('All given search- and replace-values are identical!');$this->search=$search;$this->replace=$replace;if(!ini_get('safe_mode')&&ini_get('max_execution_time')!='0'){set_time_limit(0);}return $this->DBRunner(array($this,'searchReplace'));}protected function DBRunner($callback){$this->_dbr_callback=$callback;$result=0;$unserialize_callback_func=ini_set('unserialize_callback_func',get_class().'::createClass');$pdo_attributes=array(PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION);foreach($pdo_attributes as $attribute=>$value){$pdo_attributes[$attribute]=$this->pdo->getAttribute($attribute);$this->pdo->setAttribute($attribute,$value);}try{$this->_pdo_charset=$this->pdo->query('SELECT @@character_set_client;',PDO::FETCH_COLUMN,0)->fetch();$this->_pdo_collation=$this->pdo->query('SELECT @@collation_connection;',PDO::FETCH_COLUMN,0)->fetch();$tables=$this->pdo->query('SHOW TABLES;',PDO::FETCH_COLUMN,0)->fetchAll();$this->pdo->query('LOCK TABLES `'.implode('` WRITE, `',$tables).'` WRITE;');foreach($tables as $table){$result+=$this->_DBRTable($table,$callback);}}catch(Exception$e){}$this->pdo->query('UNLOCK TABLES');foreach($pdo_attributes as $attribute=>$value){$this->pdo->setAttribute($attribute,$value);}ini_set('unserialize_callback_func',$unserialize_callback_func);if(isset($e)&&$e instanceof Exception){throw $e;}else{return $result;}}private function _DBRTable($table){$columns_info=$this->pdo->query('SHOW FULL COLUMNS FROM `'.$table.'`;',PDO::FETCH_NAMED);$columns=array();$keys=array();foreach($columns_info as $column_info){$columns[$column_info['Field']]=array('null'=>($column_info['Null']=='YES'),'type'=>self::getPHPType($column_info['Type']),'charset'=>preg_replace('/^([a-z\d]+)_[\w\d]+$/i','$1',$column_info['Collation']),'collation'=>$column_info['Collation'],);$keys[$column_info['Key']][$column_info['Field']]=$columns[$column_info['Field']];}if(isset($keys['PRI'])){$keys=$keys['PRI'];}elseif(isset($keys['UNI'])){$keys=$keys['UNI'];}else{$keys=$columns;}if(!$this->getOption(self::OPTION_EXTENSIVE_SEARCH)){$where=$this->_DBRWhereSearch($columns);if(!$where)return;}else{$where='';}if($this->getOption(self::OPTION_CONVERT_CHARSETS)){foreach($columns as $column=>$column_info)if(!isset($this->search_converted[$column_info['charset']])&&$column_info['type']=='string'&&!empty($column_info['charset'])&&$column_info['charset']!=$this->_pdo_charset){$search_convert=array();foreach($this->search as $i=>$item)if(is_string($item)){$this->search_converted[$column_info['charset']][$i]=$this->pdo->query('SELECT CONVERT(_'.$this->_pdo_charset.$this->pdo->quote($item).' USING '.$column_info['charset'].');',PDO::FETCH_COLUMN,0)->fetch();}else{$this->search_converted[$column_info['charset']][$i]=$item;}}}$row_count=(int)$this->pdo->query('SELECT COUNT(*) FROM `'.$table.'`'.$where.';',PDO::FETCH_COLUMN,0)->fetch();$row_change_count=0;$page_size=$this->getOption(self::OPTION_SEARCH_PAGE_SIZE);for($page_start=0;$page_start<$row_count;$page_start+=$page_size){$rows=$this->pdo->query('SELECT DISTINCT * FROM `'.$table.'`'.$where.'LIMIT '.$page_start.', '.$page_size.';',PDO::FETCH_ASSOC);foreach($rows as $row){if($this->_DBRRow($table,$columns,$keys,$row)>0)$row_change_count++;}}return $row_change_count;}private function _DBRRow($table,array$columns,array$keys,array$row){$changeset=array();foreach($columns+$keys as $column=>$column_info){if(!settype($row[$column],$column_info['type'])){throw new UnexpectedValueException('Failed to convert `'.$table.'`.`'.$column.'` value to a '.$column_info['type'].' for value "'.$row[$column].'"!');}}foreach($columns as $column=>$column_info){$value=&$row[$column];if($this->getOption(self::OPTION_CONVERT_CHARSETS)&&isset($this->search_converted[$column_info['charset']])){$value_new=call_user_func($this->_dbr_callback,$value,$this->search_converted[$column_info['charset']],$this->replace);}else{$value_new=call_user_func($this->_dbr_callback,$value);}if($value_new!==$value){$changeset[$column]=$value_new;}}if(count($changeset)>0&&$this->getOption(self::OPTION_DB_WRITE_CHANGES)){$where=$this->_DBRWhereRow($keys,$row);$updates=array();foreach($changeset as $column=>$value_new){switch($columns[$column]['type']){case 'integer':$updates[]='`'.$column.'` = '.(int)$value_new;$search_where_column=TRUE;break;case 'float':$updates[]='`'.$column.'` = '.(string)round((float)$value_new,$this->getOption(self::OPTION_FLOATS_PRECISION));break;default:case 'string':$update_string=$this->pdo->quote((string)$value_new);if(!empty($columns[$column]['charset'])&&$this->_pdo_charset!=$columns[$column]['charset']){if($this->getOption(self::OPTION_CONVERT_CHARSETS)){$update_string='CONVERT(_'.$this->_pdo_charset.$update_string.' USING '.$columns[$column]['charset'].')';}else{$update_string='BINARY '.$update_string;}}if(!empty($columns[$column]['collation'])&&$this->getOption(self::OPTION_CONVERT_CHARSETS)&&$this->_pdo_collation!=$columns[$column]['collation']){$update_string.=' COLLATE '.$columns[$column]['collation'];}$updates[]='`'.$column.'` = '.$update_string;break;}}$this->pdo->query('UPDATE `'.$table.'` SET '.implode(', ',$updates).$where.';');}return count($changeset);}private function _DBRWhereSearch(array&$columns){$where=array();foreach($columns as $column=>$column_info){$where_column=FALSE;foreach($this->search as $item){if($where_component=$this->_DBRWhereColumn($column,$column_info,$item,FALSE)){$where[]=$where_component;$where_column=TRUE;}}if(!$where_column)unset($columns[$column]);}if(count($where)>0){return ' WHERE '.implode(' OR ',$where).' ';}else{if(count($columns)!=0)throw new LogicException('No WHERE-clause was constructed, yet there are valid columns left!');return FALSE;}}private function _DBRWhereRow(array$keys,array$row){$where=array();foreach($keys as $key=>$key_info){$where[]=$this->_DBRWhereColumn($key,$key_info,$row[$key],TRUE);}return ' WHERE '.implode(' AND ',$where).' ';}private function _DBRWhereColumn($column,array$column_info,$value,$string_exact){switch($column_info['type']){case 'integer':if(!$this->getOption(self::OPTION_VAR_MATCH_STRICT)||is_integer($value)){return '`'.$column.'` = '.(int)$value;}break;case 'float':if(!$this->getOption(self::OPTION_VAR_MATCH_STRICT)||is_float($value)){return 'ABS(`'.$column.'` - '.(float)$value.') < POW(1, -'.$this->getOption(self::OPTION_FLOATS_PRECISION).')';}break;default:case 'string':if(is_float($value)){$value=round($value,$this->getOption(self::OPTION_FLOATS_PRECISION));}if(!$string_exact){$value='%'.(string)$value.'%';}$where_string=$this->pdo->quote((string)$value);if(!empty($column_info['charset'])&&$this->_pdo_charset!=$column_info['charset']){if($this->getOption(self::OPTION_CONVERT_CHARSETS)){$where_string='CONVERT(_'.$this->_pdo_charset.$where_string.' USING '.$column_info['charset'].')';}else{$where_string='BINARY '.$where_string;}}if(!empty($column_info['collation'])&&$this->getOption(self::OPTION_CONVERT_CHARSETS)&&$this->_pdo_collation!=$column_info['collation']){if($this->getOption(self::OPTION_CASE_INSENSITIVE)){$where_string.=' COLLATE '.preg_replace('/_cs$/i','_ci',$column_info['collation']);}else{$where_string.=' COLLATE '.$column_info['collation'];}}$column='`'.$column.'`';if(!empty($column_info['collation'])&&$this->getOption(self::OPTION_CASE_INSENSITIVE)&&preg_replace('/^.*_([a-z]+)$/i','$1',$column_info['collation'])=='cs'){$column.=' COLLATE '.preg_replace('/_cs$/i','_ci',$column_info['collation']);}$where_string=$column.' '.($string_exact?'=':'LIKE').' '.$where_string;if(!empty($column_info['charset'])&&!$this->getOption(self::OPTION_CONVERT_CHARSETS)&&$this->_pdo_charset!=$column_info['charset']){$where_string='BINARY '.$where_string;}return $where_string;}return FALSE;}protected function searchReplace($value,array$search=NULL,array$replace=NULL){if(is_null($search))$search=$this->search;if(is_null($replace))$replace=$this->replace;$new_value=$value;switch(TRUE){case is_array($value):$new_value=array();foreach($value as $key=>$element){$new_value[$this->searchReplace($key)]=$this->searchReplace($element);}break;case is_bool($value):for($i=0;$i<count($search);$i++){if($new_value===$search[$i]||!$this->getOption(self::OPTION_VAR_MATCH_STRICT)&&$new_value==$search[$i]){$new_value=$replace[$i];}}break;case is_float($value):$float_precision=pow(10,-1*$this->getOption(self::OPTION_FLOATS_PRECISION));for($i=0;$i<count($search);$i++){if(is_float($search[$i])&&abs($new_value-$search[$i])<$float_precision||!$this->getOption(self::OPTION_VAR_MATCH_STRICT)&&($new_value==$search[$i]||abs($new_value-(float)$search[$i])<$float_precision)){$new_value=$replace[$i];}}break;case is_int($value):for($i=0;$i<count($search);$i++){if($new_value===$search[$i]||!$this->getOption(self::OPTION_VAR_MATCH_STRICT)&&$new_value==$search[$i]){$new_value=$replace[$i];}}break;case is_object($value):$new_value=unserialize($this->searchReplace(preg_replace('/^O:\\d+:/','O:0:',serialize($new_value))));break;case is_string($value):$serialized_regex='/^(a:\\d+:\\{.*\\}|b:[01];|d:\\d+\\.\\d+;|i:\\d+;|N;|O:\\d+:"[a-zA-Z_\\x7F-\\xFF][a-zA-Z0-9_\\x7F-\\xFF]*":\\d+:\\{.*\\}|s:\\d+:".*";)$/Ss';$unserialized=@unserialize($new_value);if($this->getOption(self::OPTION_HANDLE_SERIALIZE)&&($unserialized!==FALSE||$new_value===serialize(FALSE))&&!is_object($unserialized)){$new_value=serialize($this->searchReplace($unserialized));}elseif($this->getOption(self::OPTION_HANDLE_SERIALIZE)&&(is_object($unserialized)||preg_match($serialized_regex,$new_value))){if($changed_value=preg_replace_callback('/b:([01]);/S',array($this,'_searchReplace_preg_callback_boolean'),$new_value)){$new_value=$changed_value;}if($changed_value=preg_replace_callback('/i:(\\d+);/S',array($this,'_searchReplace_preg_callback_integer'),$new_value)){$new_value=$changed_value;}if($changed_value=preg_replace_callback('/d:(\\d+)\.(\\d+);/S',array($this,'_searchReplace_preg_callback_float'),$new_value)){$new_value=$changed_value;}if($changed_value=preg_replace_callback('/O:\\d+:"([a-zA-Z_\\x7F-\\xFF][a-zA-Z0-9_\\x7F-\\xFF]*)":(\\d+):{(.*)}/Ss',array($this,'_searchReplace_preg_callback_objectname'),$new_value)){$new_value=$changed_value;}if($changed_value=preg_replace_callback('/s:\\d+:"(.*?|a:\\d+:{.*}|b:[01];|d:\\d+\\.\\d+;|i:\d+;|N;|O:\\d+:"[a-zA-Z_\\x7F-\\xFF][a-zA-Z0-9_\\x7F-\\xFF]*":\\d+:{.*}|s:\\d+:".*";)";/Ss',array($this,'_searchReplace_preg_callback_string'),$new_value)){$new_value=$changed_value;}if($new_value==$value)for($i=0;$i<count($search);$i++){if(is_string($this->search[$i])||!$this->getOption(self::OPTION_VAR_MATCH_STRICT)){$new_value=$this->getOption(self::OPTION_CASE_INSENSITIVE)?str_ireplace((string)$search[$i],(string)$replace[$i],$new_value):str_replace((string)$search[$i],(string)$replace[$i],$new_value);}}}else for($i=0;$i<count($search);$i++){if(is_string($search[$i])||!$this->getOption(self::OPTION_VAR_MATCH_STRICT)){$new_value=$this->getOption(self::OPTION_CASE_INSENSITIVE)?str_ireplace((string)$search[$i],(string)$replace[$i],$new_value):str_replace((string)$search[$i],(string)$replace[$i],$new_value);}}break;}return $new_value;}private function _searchReplace_preg_callback_boolean($matches){$result=$this->searchReplace((boolean)$matches[1]);if(self::OPTION_VAR_CAST_REPLACE)$result=(boolean)$result;return serialize($result);}private function _searchReplace_preg_callback_integer($matches){$result=$this->searchReplace((integer)$matches[1]);if(self::OPTION_VAR_CAST_REPLACE)$result=(integer)$result;return serialize($result);}private function _searchReplace_preg_callback_float($matches){$result=$this->searchReplace((float)($matches[1].'.'.$matches[2]));if(self::OPTION_VAR_CAST_REPLACE)$result=(float)$result;return serialize($result);}private function _searchReplace_preg_callback_objectname($matches){$name=preg_replace('/[^a-zA-Z0-9_\x7F-\xFF]+/','',(string)$this->searchReplace($matches[1]));return 'O:'.strlen($name).':"'.$name.'":'.$matches[2].':{'.$matches[3].'}';}private function _searchReplace_preg_callback_string($matches){$result=$this->searchReplace($matches[1]);if(self::OPTION_VAR_CAST_REPLACE)$result=(string)$result;return serialize($result);}}class DBSR_CLI{const VERSION='2.0.2';protected static $default_options=array('CLI'=>array('help'=>array('name'=>array('help','h','?'),'parameter'=>NULL,'description'=>'display this help and exit','default_value'=>NULL,),'version'=>array('name'=>array('version','v'),'parameter'=>NULL,'description'=>'print version information and exit','default_value'=>NULL,),'file'=>array('name'=>array('file','configfile','config','f'),'parameter'=>'FILENAME','description'=>'JSON-encoded configfile to load','default_value'=>NULL,),'output'=>array('name'=>array('output','o'),'parameter'=>'text|json','description'=>'output format','default_value'=>'text',),),'PDO'=>array('host'=>array('name'=>array('host','hostname'),'parameter'=>'HOSTNAME','description'=>'hostname of the MySQL server','default_value'=>NULL,),'port'=>array('name'=>array('port','portnumber'),'parameter'=>'PORTNUMBER','description'=>'port number of the MySQL server','default_value'=>NULL,),'user'=>array('name'=>array('user','username','u'),'parameter'=>'USERNAME','description'=>'username used for connecting to the MySQL server','default_value'=>NULL,),'password'=>array('name'=>array('password','pass','p'),'parameter'=>'PASSWORD','description'=>'password used for connecting to the MySQL server','default_value'=>NULL,),'database'=>array('name'=>array('database','db','d'),'parameter'=>'DATABASE','description'=>'name of the database to be searched','default_value'=>NULL,),'charset'=>array('name'=>array('charset','characterset','char'),'parameter'=>'CHARSET','description'=>'character set used for connecting to the MySQL server','default_value'=>NULL,),),'DBSR'=>array(DBSR::OPTION_CASE_INSENSITIVE=>array('name'=>'case-insensitive','parameter'=>'[true|false]','description'=>'use case-insensitive search and replace','default_value'=>FALSE,),DBSR::OPTION_EXTENSIVE_SEARCH=>array('name'=>'extensive-search','parameter'=>'[true|false]','description'=>'process *all* database rows','default_value'=>FALSE,),DBSR::OPTION_SEARCH_PAGE_SIZE=>array('name'=>'search-page-size','parameter'=>'SIZE','description'=>'number of rows to process simultaneously','default_value'=>10000,),DBSR::OPTION_VAR_MATCH_STRICT=>array('name'=>'var-match-strict','parameter'=>'[true|false]','description'=>'use strict matching','default_value'=>TRUE,),DBSR::OPTION_FLOATS_PRECISION=>array('name'=>'floats-precision','parameter'=>'PRECISION','description'=>'up to how many decimals floats should be matched','default_value'=>5,),DBSR::OPTION_CONVERT_CHARSETS=>array('name'=>'convert-charsets','parameter'=>'[true|false]','description'=>'automatically convert character sets','default_value'=>TRUE,),DBSR::OPTION_VAR_CAST_REPLACE=>array('name'=>'var-cast-replace','parameter'=>'[true|false]','description'=>'cast all replace-values to the original type','default_value'=>TRUE,),DBSR::OPTION_DB_WRITE_CHANGES=>array('name'=>'db-write-changes','parameter'=>'[true|false]','description'=>'write changed values back to the database','default_value'=>TRUE,),DBSR::OPTION_HANDLE_SERIALIZE=>array('name'=>'handle-serialize','parameter'=>'[true|false]','description'=>'interpret serialized strings as their PHP types','default_value'=>TRUE,),),);public static function printVersion(){echo 'DBSR CLI v',self::VERSION,', based on DBSR v'.DBSR::VERSION.', running on PHP ',PHP_VERSION,' (',PHP_SAPI,'), ',PHP_OS,'.',"\n";}public static function printHelp($filename=NULL){$pad_left=4;$width_left=40;$width_right=32;if(is_null($filename))$filename=isset($_SERVER['argv'])&&is_array($_SERVER['argv'])?$_SERVER['argv'][0]:basename($_SERVER['SCRIPT_NAME']);self::printVersion();echo "\n",'Usage: ',$filename,' [options] -- SEARCH REPLACE [SEARCH REPLACE...]',"\n".'       ',$filename,' --file FILENAME',"\n"."\n";foreach(self::$default_options as $name=>$optionset){echo $name,' options:',"\n";foreach($optionset as $key=>$option){$option['name']=(array)$option['name'];$parameter=(strlen($option['name'][0])>1?'--':'-').$option['name'][0];if(!is_null($option['parameter']))$parameter.=' '.$option['parameter'];$description_array=preg_split('/(.{1,'.$width_right.'}(?:\s(?!$)|(?=$)))/',$option['description'],NULL,PREG_SPLIT_NO_EMPTY|PREG_SPLIT_DELIM_CAPTURE);$description=$description_array[0];for($i=1;$i<count($description_array);$i++){$description.="\n".str_repeat(' ',$width_left+$pad_left).$description_array[$i];}if(!is_null($option['default_value'])){$default=$option['default_value'];if(is_bool($default)){$default=$default?'true':'false';}else{$default=(string)$default;}$default=' (default: '.$default.')';if(strlen($description_array[count($description_array)-1])+strlen($default)>$width_right){$description.="\n".str_repeat(' ',$width_left+$pad_left-1);}$description.=$default;}echo str_repeat(' ',$pad_left),str_pad($parameter,$width_left),$description,"\n";}}}protected static function getOption($switch,$check_prefix=TRUE){foreach(self::$default_options as $setname=>$set){foreach($set as $id=>$option){foreach((array)$option['name']as $name)if($switch==($check_prefix?(strlen($name)>1?('--'.$name):('-'.$name)):$name)){$option['set']=$setname;$option['id']=$id;return $option;}}}return FALSE;}protected $pdo;protected $dbsr;protected $options=array();protected $search=array();protected $replace=array();private $configfiles=array();public function __construct(){foreach(self::$default_options as $setname=>$set){foreach($set as $id=>$option){if(!is_null($option['default_value']))$this->options[$setname][$id]=$option['default_value'];}}}public function exec(){$pdo_options=array(PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,);$dsn='mysql:';if(isset($this->options['PDO']['host'])){$dsn.='host='.$this->options['PDO']['host'];if(isset($this->options['PDO']['port'])){$dsn.=':'.$this->options['PDO']['port'];}$dsn.=';';}if(isset($this->options['PDO']['database']))$dsn.='dbname='.$this->options['PDO']['database'].';';if(isset($this->options['PDO']['charset'])){$pdo_options[PDO::MYSQL_ATTR_INIT_COMMAND]='SET NAMES '.$this->options['PDO']['charset'];$dsn.='charset='.$this->options['PDO']['charset'].';';}try{$this->pdo=new PDO($dsn,@$this->options['PDO']['user'],@$this->options['PDO']['password'],$pdo_options);$this->dbsr=new DBSR($this->pdo);foreach($this->options['DBSR']as $option=>$value){$this->dbsr->setOption($option,$value);}$result=$this->dbsr->exec($this->search,$this->replace);}catch(Exception$e){switch($this->options['CLI']['output']){case 'text':die($e->getMessage());case 'json':die(json_encode(array('error'=>$e->getMessage())));}}switch($this->options['CLI']['output']){case 'text':die('Result: '.$result.' rows were '.($this->options['DBSR'][DBSR::OPTION_DB_WRITE_CHANGES]?'changed':'matched (no changes were written to the databasse)').'!');case 'json':die(json_encode(array('result'=>$result)));}}public function parseArguments(array$arguments){if(empty($arguments))$arguments=isset($_SERVER['argv'])&&is_array($_SERVER['argv'])?$_SERVER['argv']:array(basename($_SERVER['SCRIPT_NAME']));if(count($arguments)<=1){echo 'Usage: ',$arguments[0],' [options] -- SEARCH REPLACE [SEARCH REPLACE...]',"\n".'       ',$arguments[0],' --file FILENAME',"\n".'Try `',$arguments[0],' --help` for more information.',"\n";die();}for($i=1;$i<count($arguments);$i++){switch($arguments[$i]){case '--':if(count($arguments)-1-$i==0){die('Missing search- and replace-values!');}if((count($arguments)-1-$i)%2!=0){die('Missing replace-value for seach-value: '.(string)$arguments[count($arguments)-1]);}for(++$i;$i<count($arguments);$i++){$this->search[]=$arguments[$i];$this->replace[]=$arguments[++$i];}break;default:$option=self::getOption($arguments[$i]);if(!$option)die('Unknown argument: '.(string)$arguments[$i]);if(!is_null($option['parameter'])){$arg=@$arguments[$i+1];if(is_bool($option['default_value'])&&(is_null($arg)||preg_match('/^\-/',$arg))){$this->options[$option['set']][$option['id']]=!$option['default_value'];break;}if(is_null($arg)||preg_match('/^\-/',$arg)){die('Missing option for '.(string)$arguments[$i]);}switch($option['set'].'/'.$option['id']){case 'CLI/file':if(!$this->parseConfig($arg))die('Failed to parse config file: '.(string)$arg);$i++;break 2;}if(!is_null($option['default_value'])){if(is_bool($option['default_value'])){if(strtolower($arg)=='true'){$arg=TRUE;}elseif(strtolower($arg)=='false'){$arg=FALSE;}elseif(is_numeric($arg)){$arg=(bool)(int)$arg;}else{die('Invalid argument, expected boolean for '.(string)$arguments[$i]);}}elseif(is_int()){if(is_numeric($arg)){$arg=(int)$arg;}else{die('Invalid argument, expected integer for '.(string)$arguments[$i]);}}elseif(is_float()){if(is_numeric($arg)){$arg=(float)$arg;}else{die('Invalid argument, expected float for '.(string)$arguments[$i]);}}settype($arg,gettype($option['default_value']));}$this->options[$option['set']][$option['id']]=$arg;$i++;}else switch($option['set'].'/'.$option['id']){case 'CLI/help':die(self::printHelp($arguments[0]));case 'CLI/version':die(self::printVersion());}break;}}}public function parseConfig($file){if(!file_exists($file)||!realpath($file))return FALSE;if(in_array(realpath($file),$this->configfiles))return FALSE;$this->configfiles[]=realpath($file);$file_contents=@file_get_contents($file);if(!$file_contents){return FALSE;}$file_array=json_decode($file_contents,TRUE);if(!is_array($file_array))return FALSE;if(isset($file_array['search'])&&is_array($file_array['search'])){$this->search+=$file_array['search'];}if(isset($file_array['replace'])&&is_array($file_array['replace'])){$this->replace+=$file_array['replace'];}if(isset($file_array['options'])&&is_array($file_array['options'])){return $this->_parseConfigArray($file_array['options']);}else{return TRUE;}}private function _parseConfigArray(array$array){foreach($array as $key=>$element){if(is_array($element)){if(!$this->_parseConfigArray($element))return FALSE;}else{$option=self::getOption($key,FALSE);if(!$option)return FALSE;switch($option['set'].'/'.$option['id']){case 'CLI/help':die(self::printHelp());case 'CLI/version':die(self::printVersion());}if(is_null($option['parameter']))return FALSE;switch($option['set'].'/'.$option['id']){case 'CLI/file':if(!$this->parseConfig($element))return FALSE;}$this->options[$option['set']][$option['id']]=$element;}}return TRUE;}}class Bootstrapper{private static $is_initialized=FALSE;public static function exception_error_handler($errno,$errstr,$errfile,$errline){if(($errno&error_reporting())!=0){throw new ErrorException($errstr,0,$errno,$errfile,$errline);}}public static function autoloader($class_name){if(class_exists($class_name))return TRUE;$include_paths=explode(PATH_SEPARATOR,get_include_path());foreach($include_paths as $include_path){if(empty($include_path)||!is_dir($include_path))continue;$include_path=rtrim($include_path,'\\/'.DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR;foreach(array('.php','.php5','.inc.php','.inc.php5','.inc')as $extension){$count=substr_count($class_name,'_');for($i=0;$i<=$count;$i++){$filename=$include_path.preg_replace('/_/',DIRECTORY_SEPARATOR,$class_name,$i).$extension;if(is_readable($filename)){include_once $filename;if(class_exists($class_name))return TRUE;}}}}return FALSE;}protected static function stripslashes_recursive($value){return is_array($value)?array_map(array(get_class(),'stripslashes_recursive'),$value):(is_string($value)?stripslashes($value):$value);}public static function initialize(){if(self::$is_initialized)return;set_error_handler(array(get_class(),'exception_error_handler'));if(!defined('DEBUG')){define('DEBUG',FALSE);}error_reporting(DEBUG?E_ALL:0);set_include_path(get_include_path().PATH_SEPARATOR.realpath(dirname(__FILE__)));spl_autoload_register(array(get_class(),'autoloader'));if(function_exists('get_magic_quotes_gpc')&&@get_magic_quotes_gpc()){$_POST=self::stripslashes_recursive($_POST);$_GET=self::stripslashes_recursive($_GET);$_COOKIE=self::stripslashes_recursive($_COOKIE);$_REQUEST=self::stripslashes_recursive($_REQUEST);@ini_set('magic_quotes_gpc',FALSE);}if(function_exists('get_magic_quotes_gpc'))@set_magic_quotes_runtime(FALSE);@ini_set('memory_limit','-1');@ini_set('pcre.recursion_limit','100');if(extension_loaded('mbstring')){mb_internal_encoding('UTF-8');}if(extension_loaded('iconv')){iconv_set_encoding('internal_encoding','UTF-8');}date_default_timezone_set('Europe/Amsterdam');self::$is_initialized=TRUE;}public static function sessionDestroy(){$_SESSION=array();session_destroy();session_commit();}public static function sessionStart(){$security_data=array('server_ip'=>$_SERVER['SERVER_ADDR'],'server_file'=>__FILE__,'client_ip'=>$_SERVER['REMOTE_ADDR'],'client_ua'=>$_SERVER['HTTP_USER_AGENT']);session_name('DBSR_session');session_regenerate_id();session_start();if(session_id()==''||!isset($_SESSION['_session_security_data'])){$_SESSION['_session_security_data']=$security_data;}else{if($_SESSION['_session_security_data']!==$security_data){self::sessionDestroy();self::sessionStart();}}}}Bootstrapper::initialize();if(PHP_SAPI!='cli'&&!empty($_SERVER['REMOTE_ADDR'])){$_SERVER['argv']=array(basename($_SERVER['SCRIPT_FILENAME']));if(isset($_GET['args'])&&strlen(trim($_GET['args']))>0){$_SERVER['argv']=array_merge($_SERVER['argv'],explode(' ',trim($_GET['args'])));}@ini_set('html_errors',0);function DBSR_CLI_output($output){header('Content-Type: text/html; charset=UTF-8');return '<!DOCTYPE html>'."\n".'<html lang="en">'."\n".'<head>'."\n"."\t".'<meta http-equiv="Content-Type" content="text/html; charset=UTF-8" />'."\n"."\t".'<title>DBSR CLI</title>'."\n".'</head>'."\n".'<body>'."\n"."\t".'<form action="'.@$_SERVER['argv'][0].'" method="get">'."\n"."\t\t".'<p>'.htmlspecialchars(@$_SERVER['argv'][0]).' <input type="text" name="args" value="'.htmlspecialchars(@$_GET['args']).'" size="100" autofocus="autofocus"/></p>'."\n"."\t".'</form>'."\n"."\t".'<pre>'.htmlspecialchars($output).'</pre>'."\n".'</body>'."\n".'</html>';}ob_start('DBSR_CLI_output');}$cli=new DBSR_CLI();$cli->parseArguments($_SERVER['argv']);$cli->exec();die();