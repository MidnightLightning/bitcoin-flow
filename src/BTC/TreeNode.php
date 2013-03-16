<?php
namespace BTC;

/**
 * Arrange information into a tree structure, with parents and children
 *
 * Children have parents directly identified.
 * Child lists of a given parents are found by searching within the same tree.
 */
class TreeNode {
	private $t; // The Tree this Node is part of
	protected $id; // The identifier this node will be known by in this tree
	protected $parent;
	private $data;
	
	public function __construct($id, $parent, Tree $t) {
		$this->t = $t;
		$this->id = $id;
		$this->parent = $parent;
	}
	
	public function __get($id) {
		if ($id == 'id') return $this->id; // Short-circut the logic, if it's the ID being requested
		if ($id == 'parent') return $this->parent;
		return (isset($this->data[$id]))? $this->data[$id] : false;
	}
	public function __set($id, $value) {
		if (in_array($id, array('id', 'parent'))) return false; // Can't overwrite the core parameters
		$this->data[$id] = $value;
		return true;
	}
	
	function getParent() {
		return $this->t->getNode($this->parent);
	}
	
	function getChildren() {
		return $this->t->getChildren($this->id);
	}
	function getSiblings() {
		return $this->t->getChildren($this->parent);
	}
}