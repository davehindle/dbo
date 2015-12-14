<?php

require_once('lib/dbo.php');
//require_once('lib/mysqli.php');
//require_once('lib/mysql.php');
require_once('lib/pgsql.php');

$store = new store('dave', 'dave');

$people = new entity($store, 'people', 'dave');
$location = new entity($store, 'location', 'dave');
$places = new entity($store, 'places', 'dave');

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
