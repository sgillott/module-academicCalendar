<?php
// USE ;end TO SEPARATE SQL STATEMENTS. DON'T USE ;end IN ANY OTHER PLACES!

$sql = [];
$count = 0;

// v0.1.0
$sql[$count][0] = "0.1.0";
$sql[$count][1] = "-- Initial release, no upgrade steps required";

// Example for future upgrades:
// $count++;
// $sql[$count][0] = "0.1.1";
// $sql[$count][1] = "INSERT INTO ... ;end UPDATE ... ;end";
