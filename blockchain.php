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
	$main_txn = $_GET['txn'];
} else {
	$main_txn = 'bf2cd6af459f147844f1b9443998ed818bdbff3305abe4e3933d9539c8e53291';
}

// Cache all transactions needed for this plot
$txns = array();
$txns[$main_txn] = jsonGet('http://blockchain.info/rawtx/'.$main_txn);
$grand_total = 0;
$max_total = 0;
foreach($txns[$main_txn]->inputs as $in) {
	$grand_total += $in->prev_out->value;
	$id = $in->prev_out->tx_index;
	if (!isset($txns[$id])) {
		$txns[$id] = jsonGet('http://blockchain.info/rawtx/'.$id);
		$total = 0;
		foreach($txns[$id]->inputs as $sub_in) {
			$total += $sub_in->prev_out->value;
		}
		$txns[$id]->value = $total;
		if ($total > $max_total) $max_total = $total;
	}
}
$txns[$main_txn]->value = $grand_total;
if ($grand_total > $max_total) $max_total = $grand_total;

$max_tx_width = 500;
$box_height = 35;
$handle_size = 20;
$ratio = $max_tx_width/$max_total; // pixels per satoshi

$main_tx_width = $txns[$main_txn]->value*$ratio;
if ($main_tx_width < 140) $main_tx_width = 140;

$svg = '<svg xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" width="1400" height="600">';
$svg .= '<defs><style type="text/css"><![CDATA[';
$svg .= 'rect { fill:#E0E0E0; stroke:#333; stroke-width:2px; }';
$svg .= 'rect.address { fill:#F0F0E0; }';
$svg .= 'rect.transaction { fill:#C0C0F0; }';
$svg .= 'text { font-size:12px; }';
$svg .= 'path.input { fill:#CFC; stroke:#9F9; stroke-width:1px; opacity:0.6; }';
$svg .= 'path.output { fill:#FCC; stroke:#F99; stroke-width:1px; opacity:0.6; }';
$svg .= ']]></style></defs>';

// Draw all OUTPUTs
$out_offset = 10;
$tx_offset = 10;
$tx_fee = $txns[$main_txn]->value;
foreach($txns[$main_txn]->out as $out) {
	$real_width = $out->value*$ratio;
	$width = ($real_width<140)? 140 : $real_width;

	$svg .= '<path class="output" d="'.drawFlow($out_offset, 10+$box_height, $width, $tx_offset, 100, $real_width).'" />';
	$svg .= '<rect class="address" x="'.$out_offset.'" y="10" width="'.$width.'" height="'.$box_height.'" />';
	$svg .= '<text x="'.($out_offset+5).'" y="25"><tspan>Value: '.btcFormat($out->value).'</tspan><tspan x="'.($out_offset+5).'" dy="1.2em">Address: '.truncateAddress($out->addr).'</tspan></text>';
	$out_offset += $width+20;
	$tx_offset += $real_width;
	$tx_fee -= $out->value;
}
if ($tx_fee > 0) { // Draw the miner's fee
	$real_width = $tx_fee*$ratio;
	$width = ($real_width<140)? 140 : $real_width;
	
	$svg .= '<path class="output" d="'.drawFlow($out_offset, 10+$box_height, $width, $tx_offset, 100, $real_width).'" />';
	$svg .= '<rect x="'.$out_offset.'" y="10" width="'.$width.'" height="'.$box_height.'" />';
	$svg .= '<text x="'.($out_offset+5).'" y="25"><tspan>Miner Fee: '.btcFormat($tx_fee).'</tspan></text>';	
}

// Draw all INPUTs
$in_offset = 10;
$tx_offset = 10;
foreach($txns[$main_txn]->inputs as $in) {
	// Get prior transaction
	$prior = $txns[$in->prev_out->tx_index];
	
	// Calculate what will be the total width of the outputs of this transaction
	$total_width = 0;
	foreach($prior->out as $out) {
		$width = $out->value*$ratio;
		if ($width < 140) $width = 140;
		$total_width += $width+20;
	}
	
	$prior_width = $prior->value*$ratio;
	if ($prior_width<140) $prior_width = 140;
	$prior_offset = $in_offset+($total_width-$prior_width)/2;
	$prior_tx_offset = $prior_offset;
	
	foreach($prior->out as $out) {
		$real_width = $out->value*$ratio;
		$width = ($real_width<140)? 140 : $real_width;
		
		$vert_offset = 200;
		if ($out->addr == $in->prev_out->addr) {
			$svg .= '<path class="input" d="'.drawFlow($tx_offset, 100+$box_height, $real_width, $in_offset, $vert_offset, $width).'" />';
			$tx_offset += $real_width;
		}
		// Draw connecting output to prior transaction
		$svg .= '<path class="output" d="'.drawFlow($in_offset, $vert_offset+$box_height, $width, $prior_tx_offset, 300, $real_width).'" />';
		$prior_tx_offset += $real_width;
		
		$svg .= '<rect class="address" x="'.$in_offset.'" y="'.$vert_offset.'" width="'.$width.'" height="'.$box_height.'" />';
		$svg .= '<text x="'.($in_offset+5).'" y="'.($vert_offset+15).'"><tspan>Value: '.btcFormat($out->value).'</tspan><tspan x="'.($in_offset+5).'" dy="1.2em">Address: '.truncateAddress($out->addr).'</tspan></text>';
		
		$in_offset += $width+20;
	}
	$svg .= '<a xlink:href="?txn='.$prior->tx_index.'" target="_top"><rect class="transaction" x="'.$prior_offset.'" y="300" width="'.$prior_width.'" height="'.$box_height.'" />';
	$svg .= '<text x="'.($prior_offset+5).'" y="315"><tspan>Transaction: '.$prior->tx_index.'</tspan><tspan x="'.($prior_offset+5).'" dy="1.2em">Value: '.btcFormat($prior->value).'</tspan></text></a>';
	
}


$svg .= '<a xlink:href="http://blockchain.info/tx-index/'.$main_txn.'" target="_blank"><rect class="transaction" x="10" y="100" width="'.$main_tx_width.'" height="'.$box_height.'" />';
$svg .= '<text x="15" y="115"><tspan>Transaction: '.$txns[$main_txn]->tx_index.'</tspan><tspan x="15" dy="1.2em">Value: '.btcFormat($txns[$main_txn]->value).'</tspan></text></a>';


$svg .= "</svg>";
echo $svg;


echo "<pre>";
print_r($txns[$main_txn]);
echo "</pre>";




function btcFormat($satoshis) {
	return $satoshis/100000000;
}
function truncateAddress($address) {
	return substr($address, 0, 8).'...'.substr($address, -3);
}
function jsonGet($uri) {
	$rs = file_get_contents($uri);
	return json_decode($rs);
}
function drawFlow($x1, $y1, $w1, $x2, $y2, $w2) {
	$handle_size = 30;
	$actions = array(
		'M'.$x1.' '.$y1,
		'C'.$x1.' '.($y1+$handle_size).' '.$x2.' '.($y2-$handle_size).' '.$x2.' '.$y2,
		'L'.($x2+$w2).' '.$y2,
		'C'.($x2+$w2).' '.($y2-$handle_size).' '.($x1+$w1).' '.($y1+$handle_size).' '.($x1+$w1).' '.$y1,
		'Z',
	);
	return implode($actions);
}