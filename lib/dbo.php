<?php

/**
 * @file lib/dbo.php
 * @brief DBO Class definitions
 */

/**
 * @brief object used to represent either a connection to a database table or the result of a join
 */

class entity {
	public $name;			/**< @brief Database table name */
	public $schema;			/**< @brief Name of table schema (not pgsql database) */
	public $columns = false;	/**< @brief Array containing table column information */
	public $filters = array();	/**< @brief Array containing registered filters */
	private $store;
	private $join = false;

/**
 * @param $store DBO store object containing database connection
 * @param $t Database table name
 * @param $d Database table schema
 */
	function __construct($store, $t, $d = false) {
		$this->store = $store;
		$this->name = $t;
		$this->schema = $d;

		$this->describe();
	}

/**
 * @brief Load the specification of the base table columns into the columns attribute
 * @param $erase Erase the current description if known and requery the database
 */
	function describe($erase = false) {
		if ($erase) $this->columns = false; // Will this cause a memory leak?

		if ($this->columns) return;

		$this->columns = $this->store->describe($this);
	}

/**
 * @brief Set a column value
 * Use this method to set a column value to be used in a create or modify operation. Set as many values as you need before issuing the create or modify.
 * @param $c The column name
 * @paran $v The new value
 */
	function setValue($c, $v) {
		$this->describe();

		if (array_key_exists($c, $this->columns)) $this->columns[$c]['value'] = $v;
		else throw new exception("Column $c does not exist");
	}

/**
 * @brief Clear any values that have been set for columns in the entity
 * Call this on an entity that has been used for a create or modify operation already and is going to be reused for a different operation.
 */
	function clearValues() {
		$this->describe(true);
	}

/**
 * @brief Add a filter to this entity
 * An entity can be associated with any number of filters. Data visible in the entity must satisfy at least one of the filters. Filters apply to data, modify and delete operations.
 * @param $f The filter to be added
 */
	function addFilter($f) {
		$this->describe();

		foreach($f->constraints as $c) {
			if (!array_key_exists($c->column, $this->columns)) throw new exception('Column '.$c->column.' does not exist in table '.$this->schema.'.'.$this->name);
		}

		$this->filters[] = $f;
	}

/**
 * @brief Remove all filters registered on this entity
 */
	function clearFilters() {
		$this->filters = array();
	}

/**
 * @brief Create a new record in the database
 * The record will have its column values set using any setValue operations that have been executed since the entity was created or since clearValues was last issued.
 * Note that any columns not populated in this way must either allow null values or have a default defined in the table definition.
 */
	function create($forceful = false) {
		$cols = '';
		$vals = '';
		$into = $this->schema.".".$this->name;

		foreach ($this->columns as $col => $def) {
			if (isset($def['value'])) {
				$cols .= ($cols == '' ? '' : ', ').$def['column_name'];
				$vals .= ($vals == '' ? '' : ', ')."'".$this->store->real_escape_string($def['value'])."'";
			}
		}

		$sql = "INSERT INTO $into ($cols) VALUES ($vals)";

		if (!$this->store->query($sql)) throw new exception($this->store->error);
	}

/**
 * @brief Modify a database record
 * All records matching any filters put on the entity will be modified.
 * If no filters exist, the modification will apply to all records in the base table. This will cause an exception to be raised unless the $forceful parameter is set true.
 * The columns to be changed and their new values should be set using one setValue operation for each column.
 * @param $forceful Allow all records to be modified by a single call
 */
	function modify($forceful = false) {
		$sets = '';
		$from = $this->schema.".".$this->name;
		$where = '';

		foreach ($this->columns as $col => $def) {
			if (isset($def['value'])) $sets .= ($sets == '' ? '' : ', ').$def['column_name']."='".$this->store->real_escape_string($def['value'])."'";
			if (isset($def['operand'])) $where .= ($where == '' ? '' : ' AND ').$def['column_name']." ".$def['operator']." '".$this->store->real_escape_string($def['operand'])."'";
		}

		if ($sets == '') throw new exception ("No columns to update");
		if (!$forceful && ($where == '')) throw new exception ("Attempt to update all rows ignored");

		$sql = "UPDATE $from SET $sets".($where == '' ? "" : " WHERE $where");

		if (!$this->store->query($sql)) throw new exception($this->store->error);
	}

/**
 * @brief Remove records from the database table
 * This method will remove all records from the entity's base table that match any of the filters that have been applied to it.
 * If no filters exist, the modification will apply to all records in the base table. This will cause an exception to be raised unless the $forceful parameter is set true.
 * @param $forceful Allow all records to be removed by a single call
 */
	function remove($forceful = false) {
		$from = $this->schema.".".$this->name;
		$where = '';

		foreach ($this->columns as $col => $def) {
			if (isset($def['operand'])) $where .= ($where == '' ? '' : ' AND ').$def['column_name']." ".$def['operator']." '".$this->store->real_escape_string($def['operand'])."'";
		}

		if (!$forceful && ($where == '')) throw new exception ("Attempt to delete all rows ignored");

		$sql = "DELETE FROM $from".($where == '' ? "" : " WHERE $where");

		if (!$this->store->query($sql)) throw new exception($this->store->error);
	}

/**
 * @brief Gets all applicable entity data
 * This method returns an array containing all data in the base table that match any of the filters currently applied to the entity.
 * The return value is an array of arrays of the following structure:
 *
 * `Array`
 * `(`
 * `   [<rownum>] => Array`
 * `      (`
 * `          [<table name>] => Array`
 * `              (`
 * `                  [id] => <value>`
 * `                  [name] => <column name>`
 * `              )`
 * `      )`
 * `)`
 *
 * @param $showKeys If set true, the return array will contain all the primary and foreign key values in the base table (these will be hidden otherwise)
 * @returns An array of data from the base table that match the currently defined entity
 */
	function data($showKeys = false) {
		$ret = array();

		$select = '';
		$from = '';
		$where1 = '';
		$where2 = '';

		$table = $this;
		$tn = 0;

		$show = array();
		$hide = array();
		$cols = array();

		do {
			$show[$tn] = array();
			$hide[$tn] = array();

			$table->describe();

			$from .= ($from == '' ? "" : ", ")."".$table->schema.".".$table->name." AS ".sprintf("t%04d", $tn)."";

			$t1 = sprintf("t%04d", $tn);
			$t2 = sprintf("t%04d", $tn+1);

			foreach ($table->filters as $f) {
				$subclause = '';

				foreach ($f->constraints as $c) {
					$subclause .= ($subclause == '' ? '' : ' AND ')."$t1.".$c->column." ".$c->operator." '".$this->store->real_escape_string($c->operand)."'";
				}

				if ($subclause !== '') $where1 .= ($where1 == '' ? '' : ' OR ')."($subclause)";
			}

			foreach ($table->columns as $column => $def) {
				if (isset($def['join_column'])) {
					$where2 .= ($where2 == '' ? '' : ' AND ')."$t1.".$def['column_name']." = $t2.".$def['join_column']."";

					if (!$showKeys) {
						$hide[$tn][] = "$t1-".$def['column_name'];
						$hide[$tn+1][] = "$t2-".$def['join_column'];
					}
				}

				if ($showKeys || !isset($def['Join'])) {
					$show[$tn][] = "$t1-".$def['column_name'];
					$cols["$t1-".$def['column_name']] = "$t1.".$def['column_name']." AS ".$t1."_".$table->name."_".$def['column_name']."";
				}
			}

			$table = $table->join;
			$tn++;
		}
		while ($table);

		for ($i = 0; $i < $tn; $i++) {
			foreach ($show[$i] as $col) {
				if ($showKeys || !in_array($col, $hide[$i]))
					$select .= ($select == '' ? '' : ', ').$cols[$col];
			}
		}

		$where = '';
		$where .= ($where1 == '' ? "" : ($where == '' ? " WHERE " : " AND ")."($where1)");
		$where .= ($where2 == '' ? "" : ($where == '' ? " WHERE " : " AND ")."($where2)");

		$sql = "SELECT $select FROM $from$where";

		if (!$result = $this->store->query($sql)) throw new exception($this->store->error);

		while ($row = $result->fetch_assoc()) {
			$data = array();

			foreach ($row as $col => $val) {
				list($tn, $t, $c) = explode('_', $col);
				$data[$t][$c] = $val;
			}

			$ret[] = $data;
		}

		return $ret;
	}

/**
 * @brief Create an entity representing a join
 * This method creates a new entity containing sufficient information to join the data relevant to two other entities.
 * The data encapsulated in the resultant entity can be viewed using the data method and joined with another entity.
 * Note that for a joined entity to be joined again, the second join must be over columns in the main entity in the first join, not the entity with which it was joined (see Join Chaining).
 * @param $t The entity to be joined with this one
 * @param $j An array defining the join condition (each element of the array should contain the name of the column in this table and the name of the column in the join table that need to match)
 * @returns The joined entity
 */
	function join($t, $j) {
		$this->describe();

		if ($this->join) throw new exception("Entity is already joined - chain your joins such that each new join is on an unjoined entity");

		$ret = clone $this;
		$ret->join = $t;

		foreach ($j as $from => $to) {
			if (!array_key_exists($from, $this->columns)) throw new Exception("Colunm $from not found in table ".$this->schema.".".$this->name);

			if (!array_key_exists($to, $t->columns)) {
				if (($t->join) && (array_key_exists($to, $t->join->columns))) throw new Exception("Inverted join chain link - please use only forward chains");
				else throw new Exception("Colunm $to not found in table ".$t->schema.".".$t->name);
			}

			$ret->columns[$from]['column_name'] = $from;
			$ret->columns[$from]['join_column'] = $to;
		}

		return $ret;
	}
}

/**
 * @brief object used to define a simple constraint
 */

class constraint {
	const EQ = 1;	/**< @brief Equal 		*/
	const LT = 2;	/**< @brief Less		*/
	const GT = 3;	/**< @brief Greater		*/
	const LE = 4;	/**< @brief Less or equal	*/
	const LTE = 5;	/**< @brief Less or equal	*/
	const GE = 6;	/**< @brief Greater or equal	*/
	const GTE = 7;	/**< @brief Greater or equal	*/
	const NE = 8;	/**< @brief Not equal		*/

	public $column;
	public $operand;
	public $operator;

/**
 * @param $column Name of the table column
 * @param $operand Actual value to be used in the comparison
 * @param $operator One of the known operators (default to EQ)
 */

	function __construct($column, $operand, $operator = constraint::EQ) {
echo "In constructor for constaint";
		$ops=array(constraint::EQ => '=', constraint::LT => '<', constraint::GT => '>', constraint::LE => '<=', constraint::LTE => '<=', constraint::GE => '>=', constraint::GTE => '>=', constraint::NE => '<>');

		$this->column = $column;
		$this->operand = $operand;
		$this->operator = $ops[$operator];
	}
}

/**
 * @brief object containing a set of constraints
 */

class filter {
	public $constraints = array();

/**
 * @param $c one constraint - more can be added using add method - default none
 */
	function __construct($c = false) {
		if ($c) $this->add($c);
	}

/**
 * @brief add a constraint to this filter
 * @param $c the constraint to be added
 */
	function add($c) {
		$this->constraints[] = $c;
	}

/**
 * @brief remove all constraints from this filter
 */
	function clear() {
		$this->constraints = array();
	}
}

?>
