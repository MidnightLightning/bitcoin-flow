<?php

$json = jsonGet('http://blockchain.info/latestblock');
$cur_block_index = $json->block_index;
while ($cur_block_index > 0) {
	echo "Parsing block $cur_block_index...\n";
	$json = jsonGet('http://blockchain.info/rawblock/'.$cur_block_index);
	if ($json === null) {
		$cur_block_index--;
		continue;
	}
	foreach($json->tx as $tx) {
		$in_txs = array();
		foreach($tx->inputs as $in) {
			if (!isset($in->prev_out)) continue;;
			if (isset($in_txs[$in->prev_out->tx_index])) {
				echo "\t{$tx->tx_index}\n";
				break;
			}
			$in_txs[$in->prev_out->tx_index] = true;
		}
	}
	$cur_block_index--;
}


function jsonGet($uri) {
	$rs = file_get_contents($uri);
	return json_decode($rs);
}
