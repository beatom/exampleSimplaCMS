<?php

require_once('../../api/Simpla.php');
$simpla = new Simpla();
$limit  = 100;

$keyword = $simpla->request->get('query', 'string');

$keyword = rawurldecode($keyword);

$simpla->db->query("SELECT
							id,
							parent_id,
							name
						FROM __categories
						WHERE visible = 1
						ORDER BY name");

$all = $simpla->db->results();

$is_parent = [];
$categories = [];
foreach ($all as $k => $v) {

	$categories[$v->id] = $v;
	if (!empty($v->parent_id) && !in_array($v->parent_id, $is_parent)) {

		$is_parent[] = $v->parent_id;
	}
}

$suggestions = [];
foreach ($categories as $k => $v) {

	$data = $simpla->categories->category_build($v, $categories);

	if (mb_stripos($data->name, $keyword, NULL, "UTF-8") !== FALSE && !in_array($data->id, $is_parent)) {

		$suggestion        = new stdClass();
		$suggestion->value = $data->name;
		$suggestion->data  = $data;
		$suggestions[]     = $suggestion;
	}
}

$res              = new stdClass;
$res->query       = $keyword;
$res->suggestions = $suggestions;
header("Content-type: application/json; charset=UTF-8");
header("Cache-Control: must-revalidate");
header("Pragma: no-cache");
header("Expires: -1");
print json_encode($res);
