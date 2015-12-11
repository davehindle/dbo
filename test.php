<?php

require_once('lib/dbo.php');
//require_once('lib/mysqli.php');
//require_once('lib/mysql.php');
require_once('lib/pgsql.php');

$store = new store('dave', 'dave');

$people = new entity($store, 'people', 'dave');
$location = new entity($store, 'location', 'dave');
$places = new entity($store, 'places', 'dave');

$places->setFilter('name', 'Home');

//print_r($places->join($location->join($people, array('person' => 'id')), array('id' => 'place'))->data());

$people->setFilter('name', 'Dave');

//print_r($places->join($location->join($people, array('person' => 'id')), array('id' => 'place'))->data());

$places->setFilter('name', 'Office');

//print_r($places->join($location->join($people, array('person' => 'id')), array('id' => 'place'))->data(true));

$location->clearFilters();

print_r($location->data());

$location->setFilter('person', 1);

print_r($location->data());

$location->remove();

$location->clearFilters();

print_r($location->data());

$location->setValue('person', 1);
$location->setValue('place', 1);

$location->create();

print_r($location->data());

$people->clearFilters();
$people->setFilter('name', 'Dave');
$people->setValue('surname', 'Grohl');
$people->modify();

print_r($people->data());

$people->setValue('surname', 'Hindle');
$people->modify();

print_r($people->data());

?>
