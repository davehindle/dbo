<?php

require_once('lib/dbo.php');
//require_once('lib/mysqli.php');
//require_once('lib/mysql.php');
require_once('lib/pgsql.php');

$store = new store('dave', 'dave');

$people = new entity($store, 'people', 'dave');
$location = new entity($store, 'location', 'dave');
$places = new entity($store, 'places', 'dave');

$j1 = $location->join($people, array('person' => 'id'));

try {
	$j2 = $j1->join($places, array('place' => 'id'));
}
catch (exception $e) {
	echo $e->getMessage()."\n";
}

$j3 = $places->join($j1, array('id' => 'place'));

print_r($j3->data());

$j4 = $people->join($location, array('id' => 'person'));
$j5 = $places->join($j4, array('id' => 'place'));

print_r($j5->data());

$f = new filter();
$f->add(new constraint('name', 'Home'));
$f->add(new constraint('id', 3));

$places->addFilter($f);
$places->addFilter(new filter(new constraint('id', 3)));

//print_r($places->join($location->join($people, array('person' => 'id')), array('id' => 'place'))->data());

//print_r($places->join($location->join($people, array('person' => 'id')), array('id' => 'place'))->data());

//print_r($places->join($location->join($people, array('person' => 'id')), array('id' => 'place'))->data(true));

print_r($places->data());

//$location->setFilter('person', 1);

//print_r($location->data());

//$location->remove();

//$location->clearFilters();

//print_r($location->data());

//$location->setValue('person', 1);
//$location->setValue('place', 1);

//$location->create();

//print_r($location->data());

//$people->clearFilters();
//$people->setFilter('name', 'Dave');
//$people->setValue('surname', 'Grohl');
//$people->modify();

//print_r($people->data());

//$people->setValue('surname', 'Hindle');
//$people->modify();

//print_r($people->data());

?>
