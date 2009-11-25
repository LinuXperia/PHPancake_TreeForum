<?php
require_once dirname(__FILE__) . "/PancakeTF_MysqliIterator.class.php";
require_once dirname(__FILE__) . "/interfaces/PancakeTF_DBAccessI.class.php";

class PancakeTF_MysqliAccess implements PancakeTF_DBAccessI{
	static private $db = null;
	static private $fetch_method = MYSQLI_ASSOC;
	
	static public function connect($host,$user,$password,$dbname){
		self::$db = new mysqli($host,$user,$password,$dbname);
		$error = self::$db->connect_error;
		if ($error) self::throw_error($error);
	}
	
	public function queryArray($sql, $params = array()){
		$result = self::$db->query($this->generateSQL($sql,$params));
		
		if (self::$db->error) self::throw_error(self::$db->error);
		
		$arr = array();
		while ($row = $result->fetch_row()) $arr[]=$row;
		return $arr;
	}	
	
	/**
	 * performs a query to the database and returns the result`s 1st row as an array
	 * 	@param string $sql    an sql query before sanitazation (question marks instead of paramater values)
	 * 	@param array  $params an array of paramaters to pass to the query
	 * @access public
	 * @return array
	 */
	public function queryRow( $sql, $params = array()){
		$result = self::$db->query($this->generateSQL($sql,$params));
		
		if (self::$db->error) self::throw_error(self::$db->error);
		return $result->fetch_assoc();
	}
	
	/**
	 * performs an update query to the database
	 *  @param string $sql    an sql query before sanitazation (question marks instead of paramater values)
	 * 	@param array  $params an array of paramaters to pass to the query
	 * @access public
	 * @return int number of affected rows
	 */
	public function update( $sql, $params=array()){
		if (isset($this->statements[$sql])){
			$st = $this->statements[$sql];
		}else{
			$this->statements[$sql] = $st = self::$db->prepare($sql);	
		}
		
		if (self::$db->error) self::throwError(self::$db->error);
		
		if (count($params))$this->bindParams($st,$params);
		
		if (self::$db->error) self::throwError(self::$db->error);
		$st->execute();
		return $st->affected_rows;
	}
	
	/**
	 * performs a simple cout action on a table acording to specified conditions
	 * 	@param string $table a table to count from
	 * 	@param array $condition an associative array of table fields and their required value (array('name'=>'arieh'))
	 * @access public
	 * @return int 
	 */
	public function count( $table, $conditions=array()){
		$fields = array_keys($conditions);
		$sql = "SELECT COUNT(" .( (count($fields)>0) ? "`$table`.`".$fields[0]."`" : '*' ) . ") as `c` FROM `$table` ";
		$values = array();
		if (count($fields)>0){
			$sql .= " WHERE ";
			$sep ='';
			foreach ($conditions as $field => $value){
				$sql .= "$sep `$field` = ? ";
				$sep = ' AND ';
				$values[]=$value;
			}
		}
		$row = $this->queryRow($sql,$values);

		return (int)$row['c'];
	}
	
	/**
	 * returns the last id generated by an insert query
	 * @access public
	 * @return int
	 */
	public function getLastId(){
		return self::$db->insert_id;
	}
	
	public function generateSQL($sql,$params){
		$nparams[] = str_replace('?','%s',$sql);
		foreach ($params as $param) $nparams[] = $this->quote($param);
		return call_user_func_array('sprintf',$nparams);
	}
	
	/**
	 * returns an Iterator for the query results
	 * 	@param string $sql    an sql query before sanitazation (question marks instead of paramater values)
	 * 	@param array  $params an array of paramaters to pass to the query
	 * @access public
	 * @return Iterator,Countable 
	 */
	public function queryIterator($sql,$params=array()){
		$result = self::$db->query($this->generateSQL($sql,$params));
		
		if (self::$db->error) self::throwError(self::$db->error);
		
		return new PancakeTF_MysqliIterator($result);
	}
	
	/**
	 * generates a IN-clause paramater list for sql queries, escaping the paramaters where needed
	 * 	@param array $array an array of variables to generate the IN list from
	 * @access public
	 * @return string
	 */
	public function generateInList(array $array){
		foreach ($array as &$param){
			if (!is_numeric($param)) $param = $this->quote($param);
		} 
		return implode(',',$array);
	}     
	
	private function bindParams(MySQLi_STMT $st,$params){
		$arr[0] = '';
		foreach ($params as $param){
			if (is_double($param)) $arr[0] .= 'd';
			elseif (is_int($param)) $arr[0].='i';
			else $arr[0] .='s';
			 $arr[]=$param;
		}
		call_user_func_array(array($st,'bind_param'),$arr);
		return $st;
	}
	
	private function quote($param){
		if (is_numeric($param)) return $param;
		return "'".self::$db->real_escape_string($param)."'";
	}
	
	static private function throwError($err){
		throw new PancakeTF_MysqliAccessException($err);
	}
}

class PancakeTF_MysqliAccessException extends Exception{}