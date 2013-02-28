<html>
<body>
<?php
// http://blockchain.info/api/blockchain_api

/*
$rs = file_get_contents('http://blockchain.info/latestblock');
$json = json_decode($rs);
$transactions = $json->txIndexes;
$txn = $transactions[1];
*/

if (isset($_GET['txn'])) {
	$main_txn = intval($_GET['txn']);
} else {
	$main_txn = 'bf2cd6af459f147844f1b9443998ed818bdbff3305abe4e3933d9539c8e53291';
}

// Cache all transactions needed for this plot
$txns = array();
getTxn($main_txn);
foreach($txns[$main_txn]->inputs as $in) {
	$child_id = $in->prev_out->tx_index;
	getTxn($child_id);
	foreach($txns[$child_id]->inputs as $sub) {
		getTxn($sub->prev_out->tx_index);
	}
}
$max_total = 0;
foreach($txns as $tx) {
	if ($tx->value > $max_total) $max_total = $tx->value;
}

$max_tx_height = 300;
$handle_size = 20;
$ratio = $max_tx_height/$max_total; // pixels per satoshi

$txn_box_width = 140;
$addr_box_width = 225;
$min_box_height = 35;
$gutter_width = 40; // Horizontal space between transaction box and address boxes
$gap_size = 10; // Vertical gap between address boxes

$address_boxes = array(); // Global list of where Address boxes are, to connect them later
$txn_boxes = array();

$tree = array();
$tree[0] = array($main_txn); // Main transaction
$tree[1] = array(); // Children
$tree[2] = array(); // Grandchildren
foreach($txns[$main_txn]->inputs as $in) {
	$tree[1][] = $in->prev_out->tx_index;
	foreach($txns[$in->prev_out->tx_index]->inputs as $sub) {
		$tree[2][] = $sub->prev_out->tx_index;
	}
}
$tree[1] = array_unique($tree[1]);
$tree[2] = array_unique($tree[2]);

//var_dump($tree);

// Calculate total height
$max_height = 0;
$col1_height = 0;
foreach($tree[2] as $id) {
	$col1_height += txnHeight($id)+20;
}
$col1_height -= 20;
if ($col1_height > $max_height) $max_height = $col1_height;

$col2_height = 0;
foreach($tree[1] as $id) {
	$col2_height += txnHeight($id)+20;
}
$col2_height -= 20;
if ($col2_height > $max_height) $max_height = $col2_height;

$height = txnHeight($tree[0][0]);
if ($height > $max_height) $max_height = $height;

$total_width = ($txn_box_width+$gutter_width+$addr_box_width+50)*3-50+10;

$svg = '<svg xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" width="'.$total_width.'" height="'.($max_height+10).'">';
$svg .= '<defs><style type="text/css"><![CDATA[';
$svg .= 'rect { fill:#E0E0E0; stroke:#333; stroke-width:2px; }';
$svg .= 'rect.address { fill:#F0F0E0; stroke:#660; }';
$svg .= 'rect.transaction { fill:#C0C0F0; }';
$svg .= 'rect.txn_bb { opacity: 0; }';
$svg .= 'text { font-size:12px; }';
$svg .= 'tspan.field-label { font-weight:bold; }';
$svg .= 'tspan.address { font-size:10px; font-family:monospace; }';
$svg .= 'path.input { fill:#AFA; stroke:#3F3; stroke-width:1px; opacity:0.6; }';
$svg .= 'path.output { fill:#FAA; stroke:#F33; stroke-width:1px; opacity:0.6; }';
$svg .= ']]></style></defs>';


$cur_y = ($max_height-$col1_height)/2+5;
$cur_x = 5;
foreach($tree[2] as $id) {
	$rs = drawTxn($cur_x, $cur_y, $id);
	$svg .= $rs['svg'];
	$cur_y += $rs['height']+20;
}

$cur_x += $rs['width']+50;
$cur_y = ($max_height-$col2_height)/2+5;
foreach($tree[1] as $id) {
	$rs = drawTxn($cur_x, $cur_y, $id);
	$svg .= $rs['svg'];
	$cur_y += $rs['height']+20;
}


$cur_x += $rs['width']+50;
$cur_y = ($max_height-txnHeight($main_txn))/2;
$rs = drawTxn($cur_x, $cur_y, $main_txn);
$svg .= $rs['svg'];

$svg .= drawInputs($main_txn); // Draw connections to main transaction inputs

// Draw connections to grandchildren
foreach($tree[1] as $id) {
	$svg .= drawInputs($id);
}



foreach($address_boxes as $box) {
	$svg .= '<a xlink:href="http://blockchain.info/address/'.$box['addr'].'" target="_blank"><rect id="addr-'.$box['addr'].'" class="address" x="'.$box['x'].'" y="'.$box['y'].'" width="'.$box['width'].'" height="'.$box['height'].'" />'.
		'<text x="'.($box['x']+5).'" y="'.($box['y']+15).'"><tspan class="address">'.truncateAddress($box['addr']).'</tspan><tspan x="'.($box['x']+5).'" dy="1.2em"><tspan class="field-label">Value:</tspan> '.btcFormat($box['value']).'</tspan></text></a>';
};
foreach($txn_boxes as $box) {
	$svg .= '<a xlink:href="?txn='.$box['tx_index'].'" target="_blank"><rect id="txn-'.$box['tx_index'].'" class="transaction" x="'.$box['x'].'" y="'.$box['y'].'" width="'.$box['width'].'" height="'.$box['height'].'" />'.
		'<text x="'.($box['x']+5).'" y="'.($box['y']+15).'"><tspan><tspan class="field-label">Transaction:</tspan> '.$box['tx_index'].'</tspan><tspan x="'.($box['x']+5).'" dy="1.2em"><tspan class="field-label">Value:</tspan> '.btcFormat($box['value']).'</tspan></text></a>';
	
}

$svg .= "</svg>";
echo $svg;


echo "<pre>";
print_r($txns[$main_txn]);
echo "</pre>";




function btcFormat($satoshis) {
	return bcdiv($satoshis, 100000000, 8);
}
function truncateAddress($address) {
	return $address;
	return substr($address, 0, 8).'...'.substr($address, -3);
}
function jsonGet($uri) {
	$rs = file_get_contents($uri);
	return json_decode($rs);
}
function drawFlow($x1, $y1, $h1, $x2, $y2, $h2) {
	$handle_size = 30;
	$actions = array(
		'M'.$x1.' '.$y1,
		'C'.($x1+$handle_size).' '.$y1.' '.($x2-$handle_size).' '.$y2.' '.$x2.' '.$y2,
		'L'.$x2.' '.($y2+$h2),
		'C'.($x2-$handle_size).' '.($y2+$h2).' '.($x1+$handle_size).' '.($y1+$h1).' '.$x1.' '.($y1+$h1),
		'Z',
	);
	return implode($actions);
}

function getTxn($tx_id) {
	global $txns;
	if (isset($txns[$tx_id])) return;
	$txns[$tx_id] = jsonGet('http://blockchain.info/rawtx/'.$tx_id);
	// Add value
	$total = 0;
	foreach($txns[$tx_id]->inputs as $in) {
		$total += $in->prev_out->value;
	}
	$txns[$tx_id]->value = $total;
}

function txnHeight($tx_id) {
	global $txns, $ratio, $min_box_height, $gap_size;
	$txn = $txns[$tx_id];
	$total_height = 0;
	foreach($txn->out as $out) {
		$height = $out->value*$ratio;
		if ($height < $min_box_height) $height = $min_box_height;
		$total_height += $height + $gap_size;
	}
	return $total_height-$gap_size;
}

function drawInputs($tx_id) {
	global $address_boxes, $txn_boxes, $txns;
	$txn = $txns[$tx_id];
	foreach($txn_boxes as $tx_box) {
		if ($tx_box['tx_index'] == $tx_id) break;
	}
	$txn_ratio = $tx_box['height']/$txn->value; // pixels per satoshi
	$cur_x = $tx_box['x'];
	$cur_y = $tx_box['y'];
	$out = '';
	foreach($txn->inputs as $in) {
		$height = $in->prev_out->value*$txn_ratio;
		foreach($address_boxes as $box) {
			if ($box['addr'] == $in->prev_out->addr && $box['tx_index'] == $in->prev_out->tx_index) {
				$out .= '<path class="input" d="'.drawFlow($box['x']+$box['width'], $box['y'], $box['height'], $cur_x, $cur_y, $height).'" />';
				$cur_y += $height;
				break;
			}
		}
	}
	return $out;
}

function drawTxn($x, $y, $tx_id) {
	global $txns, $address_boxes, $txn_boxes, $ratio, $txn_box_width, $addr_box_width, $min_box_height, $gutter_width, $gap_size;
	$txn = $txns[$tx_id];
	
	// Calculate total height
	$total_height = txnHeight($tx_id);
	$txn_height = $txn->value*$ratio;
	if ($txn_height < $min_box_height) $txn_height = $min_box_height;
	$txn_yOff = $y+($total_height-$txn_height)/2;
	
	$txn_ratio = $txn_height/$txn->value; // pixels per satoshi
	
	$tx_off = $txn_yOff;
	$addr_off = $y;
	$paths = array();
	foreach($txn->out as $out) {
		$real_height = $out->value*$ratio;
		$height = ($real_height < $min_box_height)? $min_box_height : $real_height;
		$real_height = $out->value*$txn_ratio;
		$paths[] = '<path class="output" d="'.drawFlow($x+$txn_box_width, $tx_off, $real_height, $x+$txn_box_width+$gutter_width, $addr_off, $height).'" />';
		$box_data = array('tx_index' => $tx_id, 'addr' => $out->addr, 'value' => $out->value, 'x' => $x+$txn_box_width+$gutter_width, 'y' => $addr_off, 'width' => $addr_box_width, 'height' => $height);
		$address_boxes[] = $box_data;
		$addr_off += $height + $gap_size;
		$tx_off += $real_height;
	}
	$txn_boxes[] = array('x' => $x, 'y' => $txn_yOff, 'width' => $txn_box_width, 'height' => $txn_height, 'tx_index' => $tx_id, 'value' => $txn->value);
	
	$total_width = $txn_box_width+$gutter_width+$addr_box_width;
	return array('svg' => '<g id="txn-'.$tx_id.'">'.implode($paths).'<rect class="txn_bb" x="'.$x.'" y="'.$y.'" width="'.$total_width.'" height="'.$total_height.'" /></g>', 'width' => $total_width, 'height' => $total_height);
}
