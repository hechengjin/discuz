<?php

/**
 * DiscuzX Convert
 *
 * $Id: postposition.php 15398 2010-08-24 02:26:44Z monkey $
 */

$curprg = basename(__FILE__);

$table_source = $db_source->tablepre . 'postposition';
$table_target = $db_target->tablepre . 'forum_postposition';

$limit = 2500;
$step = getgpc('step');
$step = intval($step);
$total = getgpc('total');
$total = intval($total);

$continue = false;

if(!$step) {
	$db_target->query("TRUNCATE $table_target");
}

$offset = $step * $limit;

$query = $db_source->query("SELECT * FROM $table_source LIMIT $offset, $limit");
while($row = $db_source->fetch_array($query)) {
	$continue = true;
	$row = daddslashes($row, 1);
	$data = implode_field_value($row, ',', db_table_fields($db_target, $table_target));
	$db_target->query("INSERT INTO $table_target SET $data");
	$total ++;
}
$nextstep = $step + 1;
if($continue) {
	showmessage("����ת�����ݱ� ".$table_source."����ת�� $total ����¼��", "index.php?a=$action&source=$source&prg=$curprg&step=$nextstep&total=$total");
}
?>