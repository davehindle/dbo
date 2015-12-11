<?php

class store extends mysqli { // JPM says it should either hide inherrited methods and properties that are not common to all connections or should not extend
	function __construct($u, $p, $h = 'localhost', $d = false) {
		parent::__construct($h, $u, $p);
	}

	function describe($t) {
		$columns = array();
		$sql = "SELECT * FROM INFORMATION_SCHEMA.COLUMNS WHERE ".($t->schema ? "table_schema = '$t->schema' AND " : "")."table_name='$t->name'";

		if (!$result = $this->query($sql)) throw new exception($conn->error);

		while ($row = $result->fetch_assoc()) {
			foreach ($row as $col => $val) $row[strtolower($col)] = $val;

			$columns[$row['column_name']] = $row;
		}

		return $columns;
	}
}

?>
