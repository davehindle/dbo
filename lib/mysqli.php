<?php

require_once('/usr/local/dbo/lib/dbo.php');

class store extends mysqli { // JPM says it should either hide inherrited methods and properties that are not common to all connections or should not extend
	function __construct($u, $p, $h = 'localhost', $d = false) {
		parent::__construct($h, $u, $p);
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

	private function columnDef($col) {
		return $col['column_name'].' '.strtoupper($col['column_type'].(array_key_exists('is_nullable', $col) ? (strtolower($col['is_nullable']) == 'yes' ? ' NULL' : ' NOT NULL') : '').(array_key_exists('extra', $col) ? ' '.$col['extra'] : ''));
	}

	function createTable($e) {
		if (!$e->columns) throw new exception("Entity has no definition");

		$cols = '';
		$keys = '';

		foreach ($e->columns as $columnName => $col) {
			$cols .= ($cols == '' ? '' : ', ').$this->columnDef($col);

			if (array_key_exists('column_key', $col) && $col['column_key']) $keys .= ($keys == '' ? '' : ', ').$columnName;
		}

		$sql = 'CREATE TABLE '.($e->schema ? $e->schema.'.' : '').$e->name.' ('.$cols.($keys == '' ? '' : ', CONSTRAINT pk_'.$e->name.' PRIMARY KEY ('.$keys.')').')';
echo $sql."\n";

		if (!$result = $this->query($sql)) throw new exception($this->error);
	}

	function changeTable($e) {
		$newLayout = $e->columns;

		$e->columns = $this->describe($e);

		$mods = '';

		foreach ($newLayout as $newColumnName => $newCol) {
			$mod = false;

			foreach ($e->columns as $columnName => $col) {

				if ($columnName == $newColumnName) {
					$mods .= ($mods == '' ? '' : ', ').'MODIFY '.$this->columnDef($newCol);

					$mod = true;
				}
			}

			if (!$mod) $mods .= ($mods == '' ? '' : ', ').'ADD '.$this->columnDef($newCol);
		}

		foreach ($e->columns as $columnName => $col) {
			$mod = false;

			foreach ($newLayout as $newColumnName => $newCol) {
				if ($newColumnName == $columnName) $mod = true;
			}

			if (!$mod) $mods .= ($mods == '' ? '' : ', ').'DROP '.$columnName;
		}

		$sql = 'ALTER TABLE '.($e->schema ? $e->schema.'.' : '').$e->name.' '.$mods;
echo $sql."\n";

		if (!$result = $this->query($sql)) throw new exception($this->error);
	}
}

?>
