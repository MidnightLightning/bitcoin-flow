<html>
<body>
<?php
// http://blockchain.info/api/blockchain_api

// Interesting depictions to explore:
// Transaction 56288626 is both parent (56288626[1]) and grandparent (56288626[0] => [0]56288641) of 56619498		?txn=56619498&g=3&prune
// Both parents of 56151204 pull from grandparent 56150957 (56150957[0] => 56151185[0], 56150957[1] => 56151072[0])		?txn=56151204&g=3&prune
// Transaction 55240899 is a grandchild of a coinbase transaction (54910748), and therefore the tapering of the graph goes the other way		?txn=55240899&g=3&prune
// Transaction 57326023 and its ancestors are a good example of multiple inputs coming from one prior transaction:
//    Three of transaction 57326023's inputs came from transaction 57315077, which pulled three outputs from 57303263.
//    Both of transaction 57303591's outputs make their way into 57326023 (one via 57306406 and one via 57315077)
// Transacction 61980201 is the payout of a SatoshiDice "less than 1" winner (http://satoshidice.com/full.php?tx=d602ad09f967f11d50fcb2f98df8d001a812ce4965d6e69340f8961aeb32f27c)
//    Shows 1920.00 BTC being collected into one output. The owner then split the profits several ways in successive transactions. (i.e. 61974097 is grandchild to that transaction)
//    ?txn=61980201&g=2
//    Most of the inputs appear to be from SatoshiDice "hot" accounts (directly linking to a SD profit taken from a losing bet), but there's 6 inputs all from the same transaction (?txn=61976940&prune&g=2) that appear to be a "colder" storage of SD. Address 1znAP17iQ8FZaWRH2RB1ktsYASokLDnD8 appears to be a "holding area" for SD funds; it gets filled up, and when it hits ~2,500 BTC, it gets split into 25 new addresses equally (each gets ~100 BTC).

// @TODO: when pruning outputs, don't prune outputs that go to an address that is unpruned somewhere else
// @TODO: allow whitelisting addresses to never get pruned
// @TODO: allow pruning just transaction fee displays
// @TODO: identify "change" outputs (outputs going to an address that is among the inputs of the transaction) and draw them differently. Will probably require sorting those to the top or bottom of the list of outputs.
// @TODO: Add javascript interaction such that rolling over an address box highlights other instances of the same address on the image.

date_default_timezone_set('America/Chicago');
require 'vendor/autoload.php';
require 'config.php';

if (isset($_GET['txn'])) {
	if (intval($_GET['txn']) !== $_GET['txn']) {
		// Transaction identified by hash?
		$rs = json_decode(file_get_contents('http://blockchain.info/rawtx/'.$_GET['txn']));
		if ($rs) {
			$main_txn = $rs->tx_index;
		} else {
			exit("Can't find that transaction...");
		}
	} else {
		$main_txn = $_GET['txn'];
	}
} else {
	$main_txn = '56619498';
}
$generations = (isset($_GET['g']))? intval($_GET['g']) : 3;
if ($generations > 5) $generations = 5;

$txn = new BTC\TxnTree($main_txn, $generations, $db);
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
$(document).ready(function() {
	console.log("Init!");
	$('#grab-svg').click(function(e) {
		console.log("Grabbing!");
		var svg = $('#svg-box').html();
		console.log(svg);
		window.open('data:image/svg+xml,'+encodeURIComponent(svg));
	});
});
</script>
