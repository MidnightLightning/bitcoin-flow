<?php
// Tree testing
require 'vendor/autoload.php';

$t = new BTC\Tree();
$t->add('foo', false);
$t->add('bar', 'foo');
$t->add('example', 'foo');

var_dump($t->getNode('bar'));
var_dump($t->getNode('bar')->getSiblings());