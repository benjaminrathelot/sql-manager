<?php
// PDO Manager - APM (c) 2017 - 2019 Benjamin Rathelot
$dbHost ="";
$dbUser = "";
$dbPassword = "";

$ApmHandler = new PDO("mysql:host=$dbHost;dbname=$dbName;charset=utf8", $dbUser, $dbPassword);

class Apm{
	protected $table="";
	protected $whrX="";
	protected $innerjoin = "";
	protected $select="*";
	protected $whrV=[];
	protected $currentId;
	protected $data;
	public function __construct($table, $whr=false, $innerjoin=[], $select="*"){
		$this->table = $table;
		$this->currentId = 0;
		$this->select = $select;
		$this->whr($whr);
		if(count($innerjoin)==2)$this->innerjoin = "INNER JOIN ".$innerjoin[0]." ON ".$innerjoin[1];
		if(count($innerjoin)==4)$this->innerjoin = "INNER JOIN ".$innerjoin[0]." ON ".$innerjoin[1]." INNER JOIN ".$innerjoin[2]." ON ".$innerjoin[3];
	}
	public function setTable($table) {
		$this->table = $table;
	}
	public function setInnerJoin($innerjoin=[]) {
		$this->innerjoin = "";
		if(count($innerjoin)==2)$this->innerjoin = "INNER JOIN ".$innerjoin[0]." ON ".$innerjoin[1];
		if(count($innerjoin)==4)$this->innerjoin = "INNER JOIN ".$innerjoin[0]." ON ".$innerjoin[1]." INNER JOIN ".$innerjoin[2]." ON ".$innerjoin[3];
	}
	public function setSelect($s) {
		$this->select = $s;
	}

	public function getData() {
		return $this->data;
	}
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
	public function customWhere($string, $values) {
		$this->whrX="WHERE ".$string;
		$this->whrV=$values;
	}
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
	public function one($whr=false) {
		return $this->get($whr, "single");
	}
	public function id($whr=false) {
		return $this->get($whr, "id");
	}
	public function exists($whr=false) {
		return $this->get($whr, "exists");
	}
	public function count($whr=false) {
		return $this->get($whr, "count");
	}
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

