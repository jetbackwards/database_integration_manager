<?php

/*
	Database_IO
	
	Our own database driver - to take us away from having to use Symphony as it
	causes conflicts!
*/

/*
	query constants
*/

define('RETURN_VALUE',"1");
define('RETURN_OBJECTS',"2");
define('RETURN_NONE',"3");
define("MULTI_QUERY","99");

class Database_IO {

	var $dbConnection = null;
	var $tablePrefix = "";

	
	/*
		->__construct($databaseParams)
		Constructor
		@params
			$databaseParams - the details of the database to connect to. Usually just what is in the Symphony config.
	*/
	public function __construct($databaseParams) {
		$this->dbConnection = new mysqli(
			$databaseParams["host"],
			$databaseParams["user"],
			$databaseParams["password"],
			$databaseParams["db"],
			$databaseParams["port"]);
		
		//mysql_select_db($databaseParams["db"], $this->dbConnection);
		
		$this->tablePrefix = $databaseParams["tbl_prefix"];
	}
	
	/**
	
		getConnection
		
	*/
	public function getConnection(){
		return $this->dbConnection;
	}
	
	/*
		->query($sql, $returnMode)
		Run a query against the current database.
		@params
			$sql - the SQL statement to run.
			$mode - the return mode of the query, see constants defined above
			$suppressSanitize - allows suppression of auto-sanitize, in case it's already been done (optional, defaults to false)
	*/
	public function query($sql, $returnMode, $suppressSanitize = false) {
		
		
		if(!$sql || count($sql) < 1){
			return false;
		}
		
		// transform the table prefixes first..
		$sql = str_replace("tbl_", $this->tablePrefix, $sql);
		
		if(!$suppressSanitize) {
			$sql = $this->sanitize($sql, 1);
		}
		
		
		if($returnMode == MULTI_QUERY){
		
			/*
				Execute the query
				
			*/
			if(!$rawRet = $this->dbConnection->multi_query($sql)){
				throw new Exception('There was an error running the query [' . $this->dbConnection->error . "]");
			}
			
			/*
				Get and free the responses
			*/
			$data = array(); $i = 0;
			do {
	        	$data[$i] = array();
				if ($result = $this->dbConnection->store_result()) {
					$j = 0;
	            	while ($row = $result->fetch_row()) {
	            	    $data[$i][$j] = $row;
	            	    $j++;
					}
					$result->free();
					$i++;
				}
			} while ($this->dbConnection->next_result());
			
			return $data;
			return true;
		}
		else{
			
			if(!$rawRet = $this->dbConnection->query($sql)){
				throw new Exception('There was an error running the query [' . $this->dbConnection->error . '] sql: ['.$sql.']');
			}

			
			switch($returnMode) {
				case RETURN_VALUE:
				
					if($rawRet && $rawRet != null && is_object($rawRet) ){
						
						$proc = $rawRet->fetch_array();
						if(is_array($proc)){
							return $proc[0];
						}
						return array();	
					}
					break;
				case RETURN_OBJECTS:
				
					if($rawRet && $rawRet != null && is_object($rawRet) ){
					
						$objects = array();
						while($newObj = $rawRet->fetch_object()) {
							$objects[] = $newObj;				
						}
						return $objects;
					}
					break;
					
				case RETURN_NONE:
				default:
					return null;
				break;
			}
			
			return array();	
		}
		
		
	}
	
	/*
		->sanitize($sql, $level)
		Sanitize the SQL string passed.
		@params 
			$sql - the SQL statement or partial statement to santize
			$level - the level to which the statement should be sanitized from 1 - weak to 4 - strong (optional, defaults to 1)
		@returns
			string - the sanitized SQL
	*/
	public function sanitize($sql, $level = 1) {
		// deliberately fall through the cases, ensures one level doesn't accidentally create an attack vector that would
		// then bypass another filter.
		switch($level) {
			case 4:
				// only allow specific characters through!
				$allowedChars = explode("", "abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789");
				$sqlSplit = explode("", $sql);
				
				// we can go forwards because we're not going to remove the array elements, just set them to empty (makes
				// it easier for my poor brain)
				for($a=0;$a<count($sqlSplit);$a++) {
					if(!in_array($sqlSplit[$a], $allowedChars)) {
						$sqlSplit[$a] = "";
					}
				}
				
				//rebuild the SQL
				$sql = "";
				foreach($sqlSplit as $s) {
					$sql .= $s;
				}
				
			case 3:
				// remove common (and almost always legitimate) SQL keywords... and then fall through to remove the risky ones
				
				$sql = str_ireplace(array("SELECT", "WHERE", "DISTINCT", "ORDER BY"), "", $sql);
				
			case 2:
				// remove more risky SQL keywords
				
				$sql = str_ireplace(array("UPDATE", "DELETE", "TRUNCATE", "DROP", "INSERT", "JOIN", "UNION", "HAVING", "CREATE"), "", $sql);
				
			case 1:
				// just escape the string
				$sql =  mysqli_real_escape_string($this->dbConnection,$sql);
				break;
		}
		return $sql;	
	}		
		
}


?>