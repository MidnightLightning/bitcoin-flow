<html>
<body>
<?php
// http://blockchain.info/api/blockchain_api

// Interesting depictions to explore:
// Transaction 56288626 is both parent (56288626[1]) and grandparent (56288626[0] => [0]56288641) of 56619498		?txn=56619498&g=3&prune
// Both parents of 56151204 pull from grandparent 56150957 (56150957[0] => 56151185[0], 56150957[1] => 56151072[0])		?txn=56151204&g=3&prune
// Transaction 55240899 is a grandchild of a coinbase transaction (54910748), and therefore the tapering of the graph goes the other way		?txn=55240899&g=3&prune

$main_txn = (isset($_GET['txn']))? intval($_GET['txn']) : '56619498';
$generations = (isset($_GET['g']))? intval($_GET['g']) : 3;
if ($generations > 5) $generations = 5;

$txn = new btc_txn($main_txn, $generations);
$txn->prune = (isset($_GET['prune']))? true : false;
$svg = $txn->toSVG();
echo "<div id=\"svg-box\">{$svg}</div>";
echo '<button id="grab-svg">Save SVG</button>';
echo "<pre>";
print_r($txn->getTxn($main_txn));
echo "</pre>";

?>
<script src="http://ajax.googleapis.com/ajax/libs/jquery/1.9.1/jquery.min.js"></script>
<script>
$(document).load(function() {
	console.log("Init!");
	$('#grab-svg').click(function(e) {
		console.log("Grabbing!");
		var svg = $('#svg-box').html();
		console.log(svg);
		window.open('data:img/svg+xml,'+encodeURIComponent(svg));
	});
});
</script>

<?php
class btc_txn {
	private $txns = array();
	private $txn_tree = array();
	private $addr_boxes = array(); // Plot locations of each Address box on the image
	private $fee_boxes = array(); // Plot locations of each Fee box on the image
	private $txn_boxes = array(); // Plot locations of each Transaction box on the image
	private $txn_bboxes = array(); // Plot locations of the bounding box of each transaction
	public $ratio = false;
	public $prune = false; // Remove transaction outputs that aren't re-spent in the tree? 
	public $txn_box_width = 140; // Width of the Transaction boxes
	public $addr_box_width = 225; // Width of the Address boxes
	public $min_box_height = 35; // Minimum box height (2 rows of text)
	public $max_txn_height = 300; // Largest size a Transaction box can be; sets the ratio of the image overall, where the largest-value transaction displayed is this height
	public $gutter_width = 40; // Horizontal space between transaction box and address boxes
	public $gap_size = 10; // Vertical gap between address boxes
	public $tx_gap_size = 20; // Vertical gap between transactions in a generation
	
	function __construct($txn_id, $generations) {
		$start = microtime();
		while (count($this->txn_tree) < $generations) {
			$this->txn_tree[] = array();
		}
		$this->_buildTree($txn_id, 0);
		$this->txn_tree = array_reverse($this->txn_tree);
		//var_dump($this->txn_tree);
		
		// Flag outputs that aren't critical
		foreach($this->txn_tree as $i => $generation) {
			foreach($generation as $id) {
				$txn = $this->getTxn($id);
				foreach($txn->out as $out) {
					if ($i+1 == count($this->txn_tree)) {
						$out->spent = true;
					} else {
						$found = false;
						foreach($this->txn_tree[$i+1] as $search_id) {
							$search_tx = $this->getTxn($search_id);
							if (!isset($search_tx->inputs[0]->prev_out)) continue;
							foreach($search_tx->inputs as $in) {
								if ($in->prev_out->tx_index == $txn->tx_index && $in->prev_out->addr == $out->addr) {
									$found = true;
									break 2;
								}
							}
						}
						$out->spent = $found;
					}
				}
			}
		}
		$delta = microtime() - $start;
		echo "Construction took {$delta}<br />";
	}
	
	function toSVG() {
		$start = microtime();
		// Calculate max value
		$max = 0;
		foreach($this->txns as $tx) {
			if ($tx->value > $max) $max = $tx->value;
		}
		$this->ratio = $this->max_txn_height/$max; // Pixels per Satoshi
		
		$svg = '';
		$svg .= '<defs><style type="text/css"><![CDATA[';
		$svg .= 'rect { fill:#E0E0E0; stroke:#333; stroke-width:2px; }';
		$svg .= 'rect.address { fill:#F0F0E0; stroke:#660; }';
		$svg .= 'rect.transaction { fill:#C0C0F0; }';
		$svg .= 'rect.transaction.coinbase { fill:#cea8db; }';
		$svg .= 'rect.txn_bb { opacity: 0; }';
		$svg .= 'text { font-size:12px; }';
		$svg .= 'tspan.field-label { font-weight:bold; }';
		$svg .= 'tspan.address { font-size:10px; font-family:monospace; }';
		$svg .= 'path.input { fill:#AFA; stroke:#3F3; stroke-width:1px; opacity:0.6; }';
		$svg .= 'path.output { fill:#FAA; stroke:#F33; stroke-width:1px; opacity:0.6; }';
		$svg .= 'path.fee { fill:#AAA; stroke:#333; stroke-width:1px; }';
		$svg .= 'path.unspent { opacity:0.4; mask:url(#fade_right_svg_mask); }';
		$svg .= ']]></style></defs>';
		$svg .= '<linearGradient id="fade_right_svg_gradient" gradientUnits="objectBoundingBox" x2="1" y2="0">';
		$svg .= '<stop stop-color="white" stop-opacity="1" offset="10%"></stop>';
		$svg .= '<stop stop-color="white" stop-opacity="0.8" offset="60%"></stop>';
		$svg .= '<stop stop-color="white" stop-opacity="0" offset="100%"></stop>';
		$svg .= '</linearGradient>';
		$svg .= '<mask id="fade_right_svg_mask" maskUnits="objectBoundingBox" maskContentUnits="objectBoundingBox">';
		$svg .= '<rect x="0" y="0" width="1" height="1" style="stroke-width:0; fill: url(#fade_right_svg_gradient);" />';
		$svg .= '</mask>';
		
		/*
		$svg .= '<rect x="'.($total_width-100).'" y="0" width="100" height="100" style="stroke-width:0; fill:black" />';
		$svg .= '<rect x="'.($total_width-100).'" y="0" width="100" height="100" style="stroke-width:0; fill:url(#fade_right_svg_gradient);" />';
		*/
		
		$cur = new Point(5, 5);
		$max_height = 0;
		for($i=0; $i<count($this->txn_tree); $i++) {
			if ($i == 0) {
				// deepest level is just stacked up
				foreach($this->txn_tree[$i] as $id) {
					$svg .= $this->_drawTxn($cur, $id, $i, $this->prune);
				}
			} else {
				// higher levels center on their children
				foreach($this->txn_tree[$i] as $id) {
					$txn = $this->getTxn($id);
					$prune = ($i+1 == count($this->txn_tree))? false : $this->prune;
					$txn_height = $this->_txnHeight($id, $prune);
					if (!isset($txn->inputs[0]->prev_out)) {
						// Coinbase transaction
						$svg .= $this->_drawTxn($cur, $id, $i, $prune);
					} else {
						$min_y = 9999999999;
						$max_y = 0;
						foreach($txn->inputs as $in) {
							if (!isset($in->prev_out)) continue;
							$target_id = $in->prev_out->tx_index;
							foreach($this->txn_bboxes as $box) {
								if ($box['tx_id'] == $target_id) {
									if ($box['pos']->y < $min_y) $min_y = $box['pos']->y;
									if ($box['pos']->y+$box['height'] > $max_y) $max_y = $box['pos']->y+$box['height'];
									break;
								}
							}
						}
						$children_height = $max_y-$min_y;
						$centered_y = $min_y + ($children_height-$txn_height)/2;
						if ($centered_y > $cur->y) $cur->y = $centered_y; // Only scoot a transaction block down, not up
						$svg .= $this->_drawTxn($cur, $id, $i, $prune);
					}
				}
			}
			$max_height = max($cur->y, $max_height);
			$cur->y = 5;
			$cur->x += $this->txn_box_width+$this->gutter_width+$this->addr_box_width+$this->gutter_width;
		}
		
		$total_width = $cur->x - $this->gutter_width;
		$max_height -= $this->tx_gap_size;
		$svg = '<svg xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" width="'.($total_width+10).'" height="'.($max_height+10).'">' . $svg;
		
		
		// Now draw input connections
		for ($i=1; $i<count($this->txn_tree); $i++) {
			foreach($this->txn_tree[$i] as $id) {
				$svg .= $this->_drawInputs($id, $i);
			}
		}
		
		// Draw Address boxes
		foreach($this->addr_boxes as $box) {
			$svg .= '<a xlink:href="http://blockchain.info/address/'.$box['addr'].'" target="_blank"><rect id="addr-'.$box['addr'].'" class="address" x="'.$box['pos']->x.'" y="'.$box['pos']->y.'" width="'.$box['width'].'" height="'.$box['height'].'" />'.
				'<text x="'.($box['pos']->x+5).'" y="'.($box['pos']->y+15).'"><tspan class="address">'.$box['addr'].'</tspan><tspan x="'.($box['pos']->x+5).'" dy="1.2em"><tspan class="field-label">Value:</tspan> '.$this->_btcFormat($box['value']).'</tspan></text></a>';
		}
		
		// Draw Fee boxes
		foreach($this->fee_boxes as $box) {
			$svg .= '<rect id="fee-'.$box['tx_index'].'" class="fee" x="'.$box['pos']->x.'" y="'.$box['pos']->y.'" width="'.$box['width'].'" height="'.$box['height'].'" />'.
				'<text x="'.($box['pos']->x+5).'" y="'.($box['pos']->y+15).'"><tspan><tspan class="field-label">Transaction Fee:</tspan></tspan><tspan x="'.($box['pos']->x+5).'" dy="1.2em"><tspan class="field-label">Value:</tspan> '.$this->_btcFormat($box['value']).'</tspan></text></a>';
		}
		// Draw Transaction boxes
		foreach($this->txn_boxes as $box) {
			$svg .= '<a xlink:href="?txn='.$box['tx_index'].'" target="_blank"><rect id="txn-'.$box['tx_index'].'" class="transaction';
			if ($box['coinbase'] == true) $svg .= ' coinbase';
			$svg .= '" x="'.$box['pos']->x.'" y="'.$box['pos']->y.'" width="'.$box['width'].'" height="'.$box['height'].'" />'.
				'<text x="'.($box['pos']->x+5).'" y="'.($box['pos']->y+15).'"><tspan><tspan class="field-label">Transaction:</tspan> '.$box['tx_index'].'</tspan><tspan x="'.($box['pos']->x+5).'" dy="1.2em"><tspan class="field-label">Value:</tspan> '.$this->_btcFormat($box['value']).'</tspan></text></a>';
		}
		
		$svg .= '</svg>';
		$delta = microtime() - $start;
		echo "SVG Parsing took {$delta}<br />";
		return $svg;
	}
	
	function getTxn($id) {
		if (isset($this->txns[$id])) return $this->txns[$id];
		
		// Look for cached version
		$cache_folder = './cached/';
		$cache_file = $cache_folder.$id;
		if (file_exists($cache_file)) {
			//echo "{$id} is cached<br />\n";
			$json = json_decode(file_get_contents($cache_file));
			$this->txns[$id] = $json; // Save in memory
			return $json;
		}
		//echo "{$id} is not cached<br />\n";
		
		// Otherwise have to build the cache
		$json = $this->_jsonGet('http://blockchain.info/rawtx/'.$id);
		if ($json == '') exit("failed to get Transaction info for ".$id);
		
		$json->coinbase = (!isset($json->inputs[0]->prev_out))? true : false;
		
		// Calculate value
		$total = 0;
		if ($json->coinbase) {
			foreach($json->out as $out) {
				$total += $out->value;
			}
		} else {
			foreach($json->inputs as $in) {
				$total += $in->prev_out->value;
			}
		}
		$json->value = $total;
		
		// Calculate fee
		$total = 0;
		foreach($json->out as $out) {
			$total += $out->value;
		}
		$json->fee = $json->value-$total;
		
		file_put_contents($cache_file, json_encode($json)); // Save cache
		$this->txns[$id] = $json; // Save in memory
		return $json;
	}
	
	private function _buildTree($id, $level) {
		if ($level >= count($this->txn_tree)) return;
		$txn = $this->getTxn($id);
		$this->txn_tree[$level][] = $id;
		$this->txn_tree[$level] = array_unique($this->txn_tree[$level]);
		if (isset($txn->inputs[0]->prev_out)) {
			foreach($txn->inputs as $in) {
				$this->_buildTree($in->prev_out->tx_index, $level+1);
			}
		}
	}
	
	private function _jsonGet($uri) {
		//return json_decode(file_get_contents($uri));
		$ch = curl_init();
		curl_setopt($ch, CURLOPT_URL, $uri);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($ch, CURLOPT_COOKIE, 'show_adv=true');
		return json_decode(curl_exec($ch));
	}
	
	private function _txnHeight($id, $prune = false) {
		if ($this->ratio === false) return false; // Need to set a ratio first
		$txn = $this->getTxn($id);
		$total_height = 0;
		$last_tx_spent = true;
		foreach($txn->out as $i => $out) {
			$height = $out->value*$this->ratio;
			if ($prune && $out->spent == false) {
				if ($last_tx_spent == false) $total_height -= $this->gap_size;
				$total_height += $height + $this->gap_size;
			} else {
				$height = max($height, $this->min_box_height);
				$total_height += $height + $this->gap_size;
			}
			$last_tx_spent = $out->spent;
		}
		if ($txn->fee > 0) {
			if ($prune) {
				$height = $txn->fee*$this->ratio;
			} else {
				$height = max($txn->fee*$this->ratio, $this->min_box_height);
			}
			$total_height += $height + $this->gap_size;
		}
		return $total_height-$this->gap_size;
	}
	
	private function _drawFlow(Point $p1, $h1, Point $p2, $h2) {
		$handle_size = ($p2->x - $p1->x)*0.4;
		return 'M'.$p1->x.' '.$p1->y.
		  'C'.($p1->x+$handle_size).' '.$p1->y.' '.($p2->x-$handle_size).' '.$p2->y.' '.$p2->x.' '.$p2->y.
		  'L'.$p2->x.' '.($p2->y+$h2).
		  'C'.($p2->x-$handle_size).' '.($p2->y+$h2).' '.($p1->x+$handle_size).' '.($p1->y+$h1).' '.$p1->x.' '.($p1->y+$h1).
		  'Z';
	}
	
	private function _drawInputs($tx_id, $generation) {
		$txn = $this->getTxn($tx_id);
		foreach($this->txn_boxes as $tx_box) {
			if ($tx_box['tx_index'] == $tx_id && $tx_box['generation'] == $generation) break;
		}
		$txn_ratio = $tx_box['height']/$txn->value; // Pixels per Satoshi
		$cur_tx = clone $tx_box['pos'];
		$paths = array();
		foreach($txn->inputs as $in) {
			if (!isset($in->prev_out)) continue;
			$height = $in->prev_out->value*$txn_ratio;
			foreach($this->addr_boxes as $box) {
				if ($box['addr'] == $in->prev_out->addr && $box['tx_index'] == $in->prev_out->tx_index && $box['generation'] == $generation-1) {
					$paths[] = '<path class="input" d="'.$this->_drawFlow(new Point($box['pos']->x+$box['width'], $box['pos']->y), $box['height'], $cur_tx, $height).'" />';
					break;
				}
			}
			$cur_tx->y += $height;
		}
		return implode('', $paths);
	}
	
	private function _drawTxn(Point &$cur, $tx_id, $generation, $prune = false) {
		$txn = $this->getTxn($tx_id);
		$total_height = $this->_txnHeight($tx_id, $prune); // Height of the outputs
		
		$txn_height = max($txn->value*$this->ratio, $this->min_box_height);
		$txn_ratio = $txn_height/$txn->value; // Pixels per Satoshi
		
		$cur_tx = new Point($cur->x+$this->txn_box_width, $cur->y + ($total_height-$txn_height)/2);
		$cur_addr = new Point($cur->x+$this->txn_box_width+$this->gutter_width, $cur->y);
		$this->txn_boxes[] = array('tx_index' => $tx_id, 'generation' => $generation, 'value' => $txn->value, 'fee' => $txn->fee, 'coinbase' => $txn->coinbase, 'width' => $this->txn_box_width, 'height' => $txn_height, 'pos' => new Point($cur->x, $cur->y + ($total_height-$txn_height)/2));
		
		$paths = array();
		$last_tx_spent = true;
		foreach($txn->out as $out) {
			$real_height = $out->value*$txn_ratio; // What fraction of the transaction's height is this output?
			if ($prune && $out->spent == false) {
				// Show minimal display
				$visual_height = $out->value*$this->ratio; // What fraction of the transaction's height is this output?
				if ($last_tx_spent == false) $cur_addr->y -= $this->gap_size; // Shrink up if prior was unspent too
				$paths[] = '<path class="output unspent" d="'.$this->_drawFlow($cur_tx, $real_height, $cur_addr, $visual_height).'" />';
				
				$cur_addr->y += $visual_height + $this->gap_size;
				$cur_tx->y += $real_height;
			} else {
				// Show full display
				$real_height = $out->value*$txn_ratio; // What fraction of the transaction's height is this output?
				$visual_height = max($out->value*$this->ratio, $this->min_box_height);
			
				$paths[] = '<path class="output" d="'.$this->_drawFlow($cur_tx, $real_height, $cur_addr, $visual_height).'" />';
				$box_data = array('tx_index' => $tx_id, 'generation' => $generation, 'addr' => $out->addr, 'value' => $out->value, 'pos' => clone $cur_addr, 'width' => $this->addr_box_width, 'height' => $visual_height);
				$this->addr_boxes[] = $box_data;
				
				$cur_addr->y += $visual_height + $this->gap_size;
				$cur_tx->y += $real_height;
			}
			$last_tx_spent = $out->spent;
		}
		if ($txn->fee > 0) {
			$real_height = $txn->fee*$txn_ratio;
			if ($prune) {
				// Don't show the fee box
				$paths[] = '<path class="fee unspent" d="'.$this->_drawFlow($cur_tx, $real_height, $cur_addr, $real_height).'" />';
				$cur_tx->y += $real_height;
			} else {
				// Show fee box
				$visual_height = max($txn->fee*$this->ratio, $this->min_box_height);
				$paths[] = '<path class="fee" d="'.$this->_drawFlow($cur_tx, $real_height, $cur_addr, $visual_height).'" />';
				$this->fee_boxes[] = array('tx_index' => $tx_id, 'value' => $txn->fee, 'pos' => clone $cur_addr, 'width' => $this->addr_box_width, 'height' => $visual_height);
				
				$cur_tx->y += $visual_height;
			}
			$cur_addr->y += $real_height + $this->gap_size;
		}
		
		$total_width = $this->txn_box_width+$this->gutter_width+$this->addr_box_width;
		$this->txn_bboxes[] = array('tx_id' => $tx_id, 'pos' => clone $cur, 'width' => $total_width, 'height' => $total_height);
		$svg = '<g id="txn-'.$tx_id.'">'.implode($paths).'<rect class="txn_bb" x="'.$cur->x.'" y="'.$cur->y.'" width="'.$total_width.'" height="'.$total_height.'" /></g>';
		$cur->y += $total_height + $this->tx_gap_size; // Update current position
		return $svg;
	}
	
	/**
	 * Take a raw number of satoshis and present it as full bitcoins
	 *
	 * Uses bcdiv() because if simply using the $n/100000000 math, an input of "1" will result in an output of "1E-8" rather than "0.00000001"
	 */
	private function _btcFormat($satoshis) {
		return bcdiv($satoshis, 100000000, 8);
	}
}

// Structure class for holding an (x,y) point
class Point {
	public $x;
	public $y;
	function __construct($x, $y) {
		$this->x = $x;
		$this->y = $y;
	}
}
