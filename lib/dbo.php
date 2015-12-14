<?php

class entity {
	public $schema;
	public $name;
	private $conn;
	public $columns = false;
	private $join = false;
	public $filters = array();

	function __construct($conn, $t, $d = false) {
		$this->conn = $conn;
		$this->name = $t;
		$this->schema = $d;

		$this->describe();
	}

	function describe($erase = false) {
		if ($erase) $this->columns = false; // Will this cause a memory leak?

		if ($this->columns) return;

		$this->columns = $this->conn->describe($this);
	}

	function setValue($c, $v) {
		$this->describe();

		if (array_key_exists($c, $this->columns)) $this->columns[$c]['value'] = $v;
		else throw new exception("Column $c does not exist");
	}

	function clearValues() {
		$this->describe(true);
	}

	function addFilter($f) {
		$this->describe();

		foreach($f->constraints as $c) {
			if (!array_key_exists($c->column, $this->columns)) throw new exception('Column '.$c->column.' does not exist in table '.$this->schema.'.'.$this->name);
		}

		$this->filters[] = $f;
	}

	function clearFilters() {
		$this->filters = array();
	}

	function create($forceful = false) {
		$cols = '';
		$vals = '';
		$into = $this->schema.".".$this->name;

		foreach ($this->columns as $col => $def) {
			if (isset($def['value'])) {
				$cols .= ($cols == '' ? '' : ', ').$def['column_name'];
				$vals .= ($vals == '' ? '' : ', ')."'".$this->conn->real_escape_string($def['value'])."'";
			}
		}

		$sql = "INSERT INTO $into ($cols) VALUES ($vals)";

		if (!$this->conn->query($sql)) throw new exception($this->conn->error);
	}

	function modify($forceful = false) {
		$sets = '';
		$from = $this->schema.".".$this->name;
		$where = '';

		foreach ($this->columns as $col => $def) {
			if (isset($def['value'])) $sets .= ($sets == '' ? '' : ', ').$def['column_name']."='".$this->conn->real_escape_string($def['value'])."'";
			if (isset($def['operand'])) $where .= ($where == '' ? '' : ' AND ').$def['column_name']." ".$def['operator']." '".$this->conn->real_escape_string($def['operand'])."'";
		}

		if ($sets == '') throw new exception ("No columns to update");
		if (!$forceful && ($where == '')) throw new exception ("Attempt to update all rows ignored");

		$sql = "UPDATE $from SET $sets".($where == '' ? "" : " WHERE $where");

		if (!$this->conn->query($sql)) throw new exception($this->conn->error);
	}

	function remove($forceful = false) {
		$from = $this->schema.".".$this->name;
		$where = '';

		foreach ($this->columns as $col => $def) {
			if (isset($def['operand'])) $where .= ($where == '' ? '' : ' AND ').$def['column_name']." ".$def['operator']." '".$this->conn->real_escape_string($def['operand'])."'";
		}

		if (!$forceful && ($where == '')) throw new exception ("Attempt to delete all rows ignored");

		$sql = "DELETE FROM $from".($where == '' ? "" : " WHERE $where");

		if (!$this->conn->query($sql)) throw new exception($this->conn->error);
	}

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
					$subclause .= ($subclause == '' ? '' : ' AND ')."$t1.".$c->column." ".$c->operator." '".$this->conn->real_escape_string($c->operand)."'";
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

		if (!$result = $this->conn->query($sql)) throw new exception($this->conn->error);

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

class constraint {
	const EQ = 1;
	const LT = 2;
	const GT = 3;
	const LTE = 4;
	const GTE = 5;
	const NE = 6;

	public $column;
	public $operand;
	public $operator;

	function __construct($column, $operand, $operator = constraint::EQ) {
		$ops=array(constraint::EQ => '=', constraint::LT => '<', constraint::GT => '>', constraint::LTE => '<=', constraint::GTE => '>=', constraint::NE => '<>');

		$this->column = $column;
		$this->operand = $operand;
		$this->operator = $ops[$operator];
	}
}

class filter {
	public $constraints = array();

	function __construct($c = false) {
		if ($c) $this->add($c);
	}

	function add($c) {
		$this->constraints[] = $c;
	}

	function clear() {
		$this->constraints = array();
	}
}

?>
