<?php

function start_timer() {
	$GLOBALS['timer'] = microtime(true);
}

function end_timer() {
	$execTime = microtime(true) - $GLOBALS['timer'];
	echo "<p>Execution time: <strong>" . $execTime . "</strong></p>";
}

function show_includes() {
	echo "\n<p><br/><strong>Total includes: </strong>" . count(get_included_files());
	echo "<ol>";
	foreach (get_included_files() as $value) {
		echo "<li>" . $value . "</li>\n";
	}
	echo "</ol></p>";
}

function debug($data) {
	if(is_array($data) || is_object($data)) {
		echo "<pre>"; print_r($data); echo "</pre>";
	} else {
		echo $data . "<br/>\n";
	}
}
srand();

?>