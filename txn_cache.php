<?php
// Build a cache of transaction IDs that are spent in a given transaction

$db = new PDO('sqlite:blockchain_cache.sqlite');
$db->query('CREATE TABLE IF NOT EXISTS "transactions" ("tx_in" INTEGER, "in_addr" TEXT, "tx_spend" INTEGER, "block_spend" INTEGER)');
$db->query('CREATE UNIQUE INDEX IF NOT EXISTS "transactions_unique" ON "transactions" ("tx_in", "in_addr", "tx_spend")');

if (isset($argv[1])) {
	$block_index = $argv[1];
} else {
	$rs = $db->query('SELECT MAX("block_spend") FROM "transactions"');
	$rs = $rs->fetchColumn(0);
	$block_index = intval($rs);
	if ($block_index < 171) $block_index = 171; // First block that has a non-genesis transaction
}

$stmt = $db->prepare('INSERT INTO "transactions" ("tx_in", "in_addr", "tx_spend", "block_spend") VALUES (:in, :addr, :spend, :block)');
while (true) {
	echo "Block {$block_index}...\n";
	$json = get_json('http://blockchain.info/rawblock/'.$block_index);
	if ($json === false) { // Block not found
		$block_index++;
		continue;
	}
	if ($json === null) exit;
	foreach($json->tx as $tx) {
		//print_r($tx);
		foreach($tx->inputs as $in) {
			if (isset($in->prev_out)) {
				$stmt->bindValue(':in', $in->prev_out->tx_index);
				$stmt->bindValue(':addr', $in->prev_out->addr);
				$stmt->bindValue(':spend', $tx->tx_index);
				$stmt->bindValue(':block', $block_index);
				$stmt->execute();
				echo "\t{$in->prev_out->tx_index} ({$in->prev_out->addr}) => {$tx->tx_index}\n";
			}
		}
	}
	$block_index++;
}


function get_json($uri) {
	$rs = file_get_contents($uri);
	if (strpos($rs, '<html>') !== false) return false;
	if ($rs === false) {
		echo "$uri\n";
		exit;
	}
	return json_decode($rs);
}