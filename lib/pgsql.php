<?php

require_once('dbo.php');

class store { 
	private $pgsql;
	public $error;

	function __construct($u, $p, $h = 'localhost', $d = false) {
		$this->pgsql = pg_connect("host=$h user=$u password=$p".($d ? " dbname=$d" : "")) or $this->error = pg_last_error();
	}

	function describe($t) {
		$columns = array();
		$sql = "SELECT * FROM INFORMATION_SCHEMA.COLUMNS WHERE ".($t->schema ? "table_schema = '$t->schema' AND " : "")."table_name='$t->name'";

		if (!$result = $this->query($sql)) throw new exception($conn->error);

		while ($row = $result->fetch_assoc()) $columns[$row['column_name']] = $row;

		return $columns;
	}

	function query($sql) {
		$result = pg_query($this->pgsql, $sql) or $this->error = pg_last_error();

		return new dbo_result($result);
	}

	function real_escape_string($s) {
		return $s;
	}
}

class dbo_result {
	private $result;

	function __construct($result) {
		$this->result = $result;
	}

	function fetch_assoc() {
		$row = pg_fetch_assoc($this->result);

		return $row;
	}
}

?>
