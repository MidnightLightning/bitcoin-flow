<?php
namespace BTC;

class Tree {
	private $nodes = array();
	private $parent_map = array();
	
	public function add($id, $parent) {
		if (isset($this->nodes[$id])) return false; // Already have something with that ID
		$n = new TreeNode($id, $parent, $this);
		$this->nodes[$id] = $n; // Add to collection
		if (!isset($this->parent_map[$parent])) $this->parent_map[$parent] = array();
		$this->parent_map[$parent][] = $id; // Index
		return $n;
	}
	
	public function getNode($id) {
		return (isset($this->nodes[$id]))? $this->nodes[$id] : false;
	}
	
	public function getChildren($id) {
		return $this->parent_map[$id];
	}
}