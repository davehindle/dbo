<?php

class store { 
	private $mysql;
	public $error;

	function __construct($u, $p, $h = 'localhost', $d = false) {
		$this->mysql = mysql_connect($h, $u, $p) or $this->error = mysql_error();
	}

	function query($sql) {
		$result = mysql_query($sql, $this->mysql) or $this->error = mysql_error();

		return new dbo_result($result);
	}

	function real_escape_string($s) {
		return mysql_real_escape_string($s, $this->mysql);
	}

	function describe($t) {
		$columns = array();
		$sql = "SELECT * FROM INFORMATION_SCHEMA.COLUMNS WHERE ".($t->schema ? "table_schema = '$t->schema' AND " : "")."table_name='$t->name'";

		if (!$result = $this->query($sql)) throw new exception($this->error);

		while ($row = $result->fetch_assoc()) {
			foreach ($row as $col => $val) $row[strtolower($col)] = $val;

			$columns[$row['column_name']] = $row;
		}

		return $columns;
	}
}

class dbo_result {
	private $result;

	function __construct($result) {
		$this->result = $result;
	}

	function fetch_assoc() {
		return mysql_fetch_assoc($this->result);
	}
}

?>
