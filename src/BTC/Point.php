<?php
namespace BTC;

// Structure class for holding an (x,y) point
class Point {
	public $x;
	public $y;
	function __construct($x, $y) {
		$this->x = $x;
		$this->y = $y;
	}
}
