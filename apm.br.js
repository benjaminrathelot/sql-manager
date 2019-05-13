// PDO Manager - APM (c) 2017 - 2019 Benjamin Rathelot (NodeJS version - PG sql)

var pg = require('pg');
var sanitizer = require('sanitizer');

var con = new pg.Client({
  host: "",
  user: "",
  password: "",
  database: ""
});

con.connect();


class Apm{

	/*
	Usage: inits a new Apm object linked to a specific table
	Arguments: 
		table = pg table name
		(whr) = custom where statement for all queries (example: 'age=20 AND gender=2')
		(innerjoin) = (default: []) enables the innerjoin system with one table or two: [table1, joinOn1] or [table1, joinOn1, table2, joinOn2]
		(select) = (default: *), specifies which columns should be select on every select statement
	*/
	constructor(table, whr=false, innerjoin=[], select="*"){
		this.table = table;
		this.whrX="";
		this.whrV=[];
		this.currentId = 0;
		this.whr(whr);
		this.select = select;
		this.setInnerJoin(innerjoin);
		this.data;
	}

	/*
	Usage: updates the table which will be used for the next queries
	Arguments: table = table name
	*/
	setTable(table) {
		this.table = table;
	}

	/*
	Usage: updates the inner join clause for next queries
	Arguments:
		innerjoin = (default:[]) innerjoin system with one table or two: [table1, joinOn1] or [table1, joinOn1, table2, joinOn2], cf. SQL INNER JOIN
	*/
	setInnerJoin(innerjoin=[]) {
		this.innerjoin = "";
		if(innerjoin.length==2) this.innerjoin = "INNER JOIN "+innerjoin[0]+" ON "+innerjoin[1];
		if(innerjoin.length==4) this.innerjoin = "INNER JOIN "+innerjoin[0]+" ON "+innerjoin[1]+" INNER JOIN "+innerjoin[2]+" ON "+innerjoin[3];
	}

	/*
	Usage: updates the selected columns for next queries (default = *)
	Arguments :
		s = new select part (ex: 'id,age, name')
	*/
	setSelect(s) {
		this.select = s;
	}

	/*
	Usage: returns data stored in the Apm object, will work if id() mode has been triggered (see below)
	Arguments: none
	*/
	getData() {
		return this.data;
	}

	/*
	Usage: inserts a new row in the table linked to the Apm object
	Arguments:
		array = an array of values to be stored
		(hasId) bool = true by default. If true an additional blank column will be added first for the id column, so that you don't have to mention the id every time you make an insert statement
	*/
	insert(array,hasId) {
		if(typeof hasId==='undefined')hasId=true;
		let valsX = [];
		let valsV = [];
		if(hasId) { valsX.push("default"); } 
		let query = "INSERT INTO "+this.table+" VALUES(";
		var l = array.length;
		var i;
		for(i=0;i!=l;i++) {
			valsX.push("$"+(i+1));
		}
		var v;
		for(v of array) {
			valsV.push(sanitizer.sanitize(v));
		}
		query+= valsX.join(', ')+");";
		con.query(query, valsV, function(err, result){
			if(err){
				console.log(err);
				return false;
			}
			return true;
		});
		
	}

	/*
	Usage: Private function, internal use only. Converts an object to a prepared sql statement ('id=$1 AND age=$2 ...')
	Arguments: 
		object = an object containing key and values to be converted to an WHERE or SET clause for instance ({id:37, age:20})
		word = the clause which will be inserted before the returned statement (default = WHERE)
		sep = the separator which will be used between elements (default = AND, could be OR)
	=> As you can notice it does not allow to make complex statements combining AND and OR, customWhere can be used in this case
	*/
	parseFromObject(object, word="WHERE", sep="AND") { 
		let add = "";
		let rt = word;
		let vals = [];
		var i = 1;
		for(k in object) {
			rt+=add+" "+k+"=$"+i+" ";
			vals.push(sanitizer.sanitize(object[k]));
			add=sep;
			i++;
		}
		return {result:rt, values:vals};
	}

	/*
	Usage: Sets a custom WHERE statement which will be used for the next queries. Useful in case of complex statements
	Arguments: 
		string = the WHERE statement string (without the WHERE clause)
		values = an Array of values
	*/
	customWhere(string, values=[]) {
		this.whrX="WHERE "+string;
		this.whrV=values;
	}

	/*
	Usage: Sets a custom WHERE statement from an object
	Arguments: 
		whereObject = an object containing key and values to be converted to an WHERE for instance ({id:37, age:20})
	*/
	whr(whereObject) {
		if(whereObject) {
			let r = this.parseFromObject(whereObject);
			this.whrX = r['result'];
			this.whrV = r['values'];
		}
		else
		{
			this.whrX = "";
		}
	}

	/*
	Usage: Executes a custom sql query with optional values using prepared statement
	Arguments: 
		query = the query to be executed
		arr = the array of values in case of prepared statement
		callback = a callback function receiving (err, result) from the PG sql engine
	*/
	customQuery(query, arr=[], callback) {
		con.query(query, arr, function(err, result){
			callback(err, result);
		});
	}

	/*
	Usage: Executes a select query and returns the result depending on the chosen type
	Arguments:
		callback = function which will receive a result depending on the type
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
	get(callback, whr=false, type="multiple") { 
		
		if(whr) {
			this.whr(whr);
		}
		return con.query("SELECT "+this.select+" FROM "+this.table+" "+this.innerjoin+" "+this.whrX, this.whrV, function(err, result){
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

	/*
	Usage: Shortcut for get() function with type = single
	Arguments: cf. get()
	*/ 
	one(callback,whr=false) {
		this.get(callback,whr, "single");
	}

	/*
	Usage: Shortcut for get() function with type = id
	Arguments: cf. get()
	*/ 
	id(callback,whr=false) {
		this.get(callback,whr, "id");
	}

	/*
	Usage: Shortcut for get() function with type = exists
	Arguments: cf. get()
	*/ 
	exists(callback,whr=false) {
		this.get(callback,whr, "exists");
	}

	/*
	Usage: Shortcut for get() function with type = count
	Arguments: cf. get()
	*/ 
	count(callback,whr=false) {
		this.get(callback,whr, "count");
	}

	/*
	Usage: Can be used when id mode is enabled (see get() function with type = id). Returns the stored value for attr if no newVal is provided, else performs a SQL update on attr to set the value to newVal
	Arguments:
		attr = the column name to be return from object's data if no newVal is specified, else the column to be updated
		(newVal) = optional. If provided, an update will be performed on the column with the value provided, using the id attached to the current Apm object
	*/ 
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
				
				let attr = sanitizer.sanitize(attr);
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

	/*
	Usage: Performs an update query using an object as an argument
	Arguments:
		set = object containing the columns to be updated and their new value
		(whr) = optional where object, cf. whr function
	*/ 
	update(set, whr=false) {
		
		if(whr) {
			this.whr(whr);
		}

		let set = this.parseFromObject(set, "SET", ",");
		con.query("UPDATE "+this.table+" "+set['result']+" "+this.whrX, set['values'].concat(this.whrV), function(err, result){
			if(err) throw err;
			return true;
		});
	}

	/*
	Usage: Performs a delete query with the current where statement attached to the Apm object
	Arguments:
		(whr) = optional where object, cf. whr function
	*/ 
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

module.exports = Apm;
