<?php
// PDO Manager - APM (c) 2017 - 2019 Benjamin Rathelot

if(!isset($dbPort)) $dbPort = "3306";
$ApmHandler = new PDO("mysql:host=$dbHost;port=$dbPort;dbname=$dbName;charset=utf8", $dbUser, $dbPassword);

class Apm{
	protected $table=""; // table name
	protected $whrX=""; // current where statement (text)
	protected $innerjoin = ""; // current inner joint statement
	protected $select="*"; // current select policy
	protected $whrV=[]; // current where statement values (cf. sql prepared queries)
	protected $currentId; // current id if id mode enabled (cf. attr() function and get function with type = id)
	protected $data; // current data if id mode enabled (cf. attr() function and get function with type = id)

	/*
	Usage: inits a new Apm object linked to a specific table
	Arguments: 
		table = pg table name
		(whr) = custom where statement for all queries (example: 'age=20 AND gender=2')
		(innerjoin) = (default: []) enables the innerjoin system with one table or two: [table1, joinOn1] or [table1, joinOn1, table2, joinOn2]
		(select) = (default: *), specifies which columns should be select on every select statement
	*/
	public function __construct($table, $whr=false, $innerjoin=[], $select="*"){
		$this->table = $table;
		$this->currentId = 0;
		$this->select = $select;
		$this->whr($whr);
		$this->setInnerJoin($innerjoin);
	}

	/*
	Usage: updates the table which will be used for the next queries
	Arguments: table = table name
	*/
	public function setTable($table) {
		$this->table = $table;
	}

	/*
	Usage: updates the inner join clause for next queries
	Arguments:
		innerjoin = (default:[]) innerjoin system with one table or two: [table1, joinOn1] or [table1, joinOn1, table2, joinOn2], cf. SQL INNER JOIN
	*/
	public function setInnerJoin($innerjoin=[]) {
		$this->innerjoin = "";
		if(count($innerjoin)==2)$this->innerjoin = "INNER JOIN ".$innerjoin[0]." ON ".$innerjoin[1];
		if(count($innerjoin)==4)$this->innerjoin = "INNER JOIN ".$innerjoin[0]." ON ".$innerjoin[1]." INNER JOIN ".$innerjoin[2]." ON ".$innerjoin[3];
	}

	/*
	Usage: updates the selected columns for next queries (default = *)
	Arguments :
		s = new select part (ex: 'id,age, name')
	*/
	public function setSelect($s) {
		$this->select = $s;
	}

	/*
	Usage: returns data stored in the Apm object, will work if id() mode has been triggered (see below)
	Arguments: none
	*/
	public function getData() {
		return $this->data;
	}

	/*
	Usage: inserts a new row in the table linked to the Apm object
	Arguments:
		array = an array of values to be stored
		(hasId) bool = true by default. If true an additional blank column will be added first for the id column, so that you don't have to mention the id every time you make an insert statement
	*/
	public function insert($array,$hasId=true) {
		global $ApmHandler;
		$valsX = [];
		$valsV = [];
		if($hasId) { $valsX[]="''"; } 
		$query = "INSERT INTO ".$this->table." VALUES(";
		$l = count($array);
		for($i=0;$i!=$l;$i++) {
			$valsX[] = "?";
		}
		foreach($array as $v) {
			$valsV[] = $v;
		}
		$query.= implode(', ', $valsX).");";
		$rq = $ApmHandler->prepare($query);
		return $rq->execute($valsV) or die(print_r($rq->errorInfo()));
		
	}

	/*
	Usage: Private function, internal use only. Converts an object to a prepared sql statement ('id=$1 AND age=$2 ...')
	Arguments: 
		array = an array containing key and values to be converted to an WHERE or SET clause for instance ({id:37, age:20})
		word = the clause which will be inserted before the returned statement (default = WHERE)
		sep = the separator which will be used between elements (default = AND, could be OR)
	=> As you can notice it does not allow to make complex statements combining AND and OR, customWhere can be used in this case
	*/
	private function parseFromArray($array, $word="WHERE", $sep="AND") { 
		$add = "";
		$rt = $word;
		$vals = [];
		foreach($array as $k=>$v) {
			$rt.=$add." $k=? ";
			$vals[] = $v;
			$add=$sep;
		}
		return array("result"=>$rt, "values"=>$vals);
	}

	/*
	Usage: Sets a custom WHERE statement which will be used for the next queries. Useful in case of complex statements
	Arguments: 
		string = the WHERE statement string (without the WHERE clause)
		values = an Array of values
	*/
	public function customWhere($string, $values) {
		$this->whrX="WHERE ".$string;
		$this->whrV=$values;
	}

	/*
	Usage: Sets a custom WHERE statement from an object
	Arguments: 
		whereObject = an object containing key and values to be converted to an WHERE for instance ({id:37, age:20})
	*/
	public function whr($whereArray) {
		if($whereArray) {
			$r = $this->parseFromArray($whereArray);
			$this->whrX = $r['result'];
			$this->whrV = $r['values'];
		}
		else
		{
			$this->whrX = "";
		}
	}

	/*
	Usage: Executes a custom sql query with optional values using prepared statement, and returns the query result
	Arguments: 
		query = the query to be executed
		arr = the array of values in case of prepared statement
	*/
	public function customQuery($query, $arr=false) {
		global $ApmHandler;
		$rq = $ApmHandler->prepare($query);
		if($arr){
			$rq->execute($arr);
		}
		else
		{
			$rq->execute();
		}
		return $rq;
	}

	/*
	Usage: Executes a select query and returns the result depending on the chosen type
	Arguments:
		whr = (default false) if not false will set the where statement using whr function (see above)
		type = 
			multiple = returns an array of every result
			single = returns the first line ; shortcut : one() function
			count = returns the line count ; shortcut : count() function
			exists = returns true if an element is retrieved, else false ; shortcut : exists() function
			id = triggers the id mode (shortcut : id() function).
				=> If the id mode is enabled:
				 - The current Apm object will be attached to the id of the first element retrieved by the function.
				 - The retrieved data will be stored in the object (see getData() above)
				 - The attr function will become available: enables quick update of the id-linked element (see attr())
	*/ 
	public function get($whr=false, $type="multiple") { 
		global $ApmHandler;
		if($whr) {
			$this->whr($whr);
		}
		$rq = $ApmHandler->prepare("SELECT ".$this->select." FROM ".$this->table." ".$this->innerjoin." ".$this->whrX);
		if($this->whrX!='') { 
			$rq->execute($this->whrV) or print_r($rq->errorInfo());
		}
		else
		{
			$rq->execute();
		}
		switch($type) {
			case "multiple":
				if($rq->rowCount()>0) {
					return $rq->fetchAll();
				}
				else
				{
					return false;
				}
			break;
			case "single":
				if($rq->rowCount()>0) {
					return $rq->fetch();
				}
				else
				{
					return false;
				}
			break;
			case "id":
				if($rq->rowCount()>0) {
					$this->data = $rq->fetch();
					$this->currentId = $this->data['id'];
					$this->whrX = "WHERE id=?";
					$this->whrV = [$this->currentId];
					return $this->data;
				}
				else
				{
					return false;
				}
			break;
			case "count":
				return $rq->rowCount();
			break;
			case "exists":
				if($rq->rowCount()>0) {
					return true;
				}
				else
				{
					return false;
				}
			break;
			default:
				echo "Error: Invalid get type.";
				exit;
			break;
		}
	}

	/*
	Usage: Shortcut for get() function with type = single
	Arguments: cf. get()
	*/ 
	public function one($whr=false) {
		return $this->get($whr, "single");
	}

	/*
	Usage: Shortcut for get() function with type = id
	Arguments: cf. get()
	*/ 
	public function id($whr=false) {
		return $this->get($whr, "id");
	}

	/*
	Usage: Shortcut for get() function with type = exists
	Arguments: cf. get()
	*/ 
	public function exists($whr=false) {
		return $this->get($whr, "exists");
	}

	/*
	Usage: Shortcut for get() function with type = count
	Arguments: cf. get()
	*/ 
	public function count($whr=false) {
		return $this->get($whr, "count");
	}

	/*
	Usage: Can be used when id mode is enabled (see get() function with type = id). Returns the stored value for attr if no newVal is provided, else performs a SQL update on attr to set the value to newVal
	Arguments:
		attr = the column name to be return from object's data if no newVal is specified, else the column to be updated
		(newVal) = optional. If provided, an update will be performed on the column with the value provided, using the id attached to the current Apm object
	*/ 
	public function attr($attr, $newVal="APM_DEFAULT_VALUE_1208#--__--") {
		if($this->currentId) {
			if($newVal == "APM_DEFAULT_VALUE_1208#--__--") {
				if(isset($this->data[$attr])) {
					return $this->data[$attr];
				}
				else
				{
					return false;
				}
			}
			else
			{
				global $ApmHandler;
				$attr = addslashes($attr);
				$rq = $ApmHandler->prepare("UPDATE ".$this->table." SET $attr=? WHERE id=?");
				$rq->execute([$newVal, $this->currentId]);
				return true;
			}
		}
		else
		{
			echo "Error: not an ID object.";
			exit;
		}
	}

	/*
	Usage: Performs an update query using an object as an argument
	Arguments:
		set = object containing the columns to be updated and their new value
		(whr) = optional where object, cf. whr function
	*/ 
	public function update($set, $whr=false) {
		global $ApmHandler;
		if($whr) {
			$this->whr($whr);
		}
		$set = $this->parseFromArray($set, "SET", ",");
		$rq = $ApmHandler->prepare("UPDATE ".$this->table." ".$set['result']." ".$this->whrX);
		$rq->execute(array_merge($set['values'], $this->whrV));
		return true;
	}

	/*
	Usage: Performs a delete query with the current where statement attached to the Apm object
	Arguments:
		(whr) = optional where object, cf. whr function
	*/ 
	public function delete($whr=false) {
		global $ApmHandler;
		if($whr) {
			$this->whr($whr);
		}
		$rq = $ApmHandler->prepare("DELETE FROM ".$this->table." ".$this->whrX);
		if($this->whrX!='') {
			$rq->execute($this->whrV);
		}
		else
		{
			$rq->execute();
		}
		if($this->currentId!=0) {
			$this->currentId=0;
			$this->data = null;
		}
		return true;
	}
}
