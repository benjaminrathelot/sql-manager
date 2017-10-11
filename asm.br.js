// Agencys Nodejs Sql Manager: Asm — (c) 2017 Benjamin Rathelot
// Converted From: Agencys PDO Manager - APM (c) 2017 Benjamin Rathelot



var mysql = require('mysql');
var sanitizer = require('sanitizer');

var con = mysql.createConnection({
  host: "localhost",
  user: "root",
  password: "",
  database: "genrpg"
});

con.connect(function(err) {
  if (err) throw err;
  console.log("Connected!");
});


class Asm{

	constructor(table, whr=false){
		this.table = table;
		this.whrX="";
		this.whrV=[];
		this.currentId = 0;
		this.whr(whr);
		this.data;
	}

	setTable(table) {
		this.table = table;
	}

	getData() {
		return this.data;
	}

	insert(array,hasId=true) {
		valsX = array();
		valsV = array();
		if(hasId) { valsX.push("''"); } 
		query = "INSERT INTO "+this.table+" VALUES(";
		l = array.length;
		for(i=0;i!=l;i++) {
			valsX.push("?");
		}
		for(v of array) {
			valsV.push(sanitizer.sanitize(v));
		}
		query+= valsX.join(', ')+");";
		con.query(query, valsV, function(err, result){
			if(err) throw err;
			return true;
		});
		
	}

	parseFromObject(array, word="WHERE", sep="AND") { 
		add = "";
		rt = word;
		vals = [];
		for(k in array) {
			rt+=add+" k=? ";
			vals.push(sanitizer.sanitize(array[k]));
			add=sep;
		}
		return {result:rt, values:vals};
	}

	customWhere(string, values) {
		this.whrX="WHERE "+string;
		this.whrV=values;
	}

	whr(whereObject) {
		if(whereObject) {
			r = this.parseFromObject(whereObject);
			this.whrX = r['result'];
			this.whrV = r['values'];
		}
		else
		{
			this.whrX = "";
		}
	}

	customQuery(query, arr={}, callback) {
		con.query(query, arr, function(err, result){
			callback(err, result);
		});
	}

	get(callback, whr=false, type="multiple") { 
		
		if(whr) {
			this.whr(whr);
		}
		return con.query("SELECT * FROM "+this.table+" "+this.whrX, this.whrV, function(err, result){
			if (err) throw err;
			switch(type) {
				case "multiple":
					if(result.length>0) {
						callback(result);
					}
					else
					{
						callback(false);
					}
				break;
				case "single":
					if(result.length>0) {
						callback(result[0]);
					}
					else
					{
						callback(false);
					}
				break;
				case "id":
					if(result.length>0) {
						this.data = result[0];
						this.currentId = this.data['id'];
						this.whrX = "WHERE id=?";
						this.whrV = [this.currentId];
						callback(this.data);
					}
					else
					{
						callback(false);
					}
				break;
				case "count":
					callback(result.length);
				break;
				case "exists":
					if(result.length>0) {
						callback(true);
					}
					else
					{
						callback(false);
					}
				break;
				default:
					console.log("Error: Invalid get type+");
					throw "error";
				break;
		}
		});
		
	}

	one(callback,whr=false) {
		this.get(callback,whr, "single");
	}

	id(callback,whr=false) {
		this.get(callback,whr, "id");
	}

	exists(callback,whr=false) {
		this.get(callback,whr, "exists");
	}

	count(callback,whr=false) {
		this.get(callback,whr, "count");
	}

	attr(attr, newVal="APM_DEFAULT_VALUE_1208#--__--") {
		if(this.currentId) {
			if(newVal == "APM_DEFAULT_VALUE_1208#--__--") {
				if(this.data[attr]) {
					return this.data[attr];
				}
				else
				{
					return false;
				}
			}
			else
			{
				
				attr = addslashes(attr);
				con.query("UPDATE "+this.table+" SET attr=? WHERE id=?",[newVal, this.currentId], function(err, result){
					if(err) throw err;
					return true;
				});
				
			}
		}
		else
		{
			console.log("Error: not an ID object+");
			throw "err";
		}
	}

	update(set, whr=false) {
		
		if(whr) {
			this.whr(whr);
		}

		set = this.parseFromObject(set, "SET", ",");
		con.query("UPDATE "+this.table+" "+set['result']+" "+this.whrX, set['values'].concat(this.whrV), function(err, result){
			if(err) throw err;
			return true;
		});
	}

	delete(whr=false) {
		
		if(whr) {
			this.whr(whr);
		}
		con.query("DELETE FROM "+this.table+" "+this.whrX, this.whrV, function(err, result){
			if(err) throw err;
			if(this.currentId!=0) {
				this.currentId=0;
				this.data = null;
			}
			return true;
		});
		
	}
}

module.exports = Asm;