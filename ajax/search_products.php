<?php

session_start();

require_once('../api/Simpla.php');
require_once('../api/sphinxapi-2.0.2.php');

$simpla = new Simpla();
$limit  = 5;

$keyword     = $simpla->request->get('query', 'string');
$category_id = $simpla->request->get('category', 'integer');
$limit_get   = $simpla->request->get('limit', 'integer');

if (!empty($limit_get)) {

    $limit = $limit_get;
}

$q = $keyword . '*';

$Sphinx = new SphinxClient();

$Sphinx->SetServer('127.0.0.1', 9312);
$Sphinx->SetConnectTimeout(1);
$Sphinx->SetArrayResult(true);
$Sphinx->SetSortMode(SPH_SORT_EXTENDED, 'rank ASC, date_created DESC');

$Sphinx->SetLimits(0, $limit);
$ids       = array();
$total     = 0;
$indexName = 'moda_product';
$q         = $Sphinx->EscapeString($q);
$search    = $Sphinx->Query($q, $indexName);

$count = $search['total'];

if ($search !== FALSE) {
    if (!empty($search['matches'])) {

        foreach ($search['matches'] as $match) {

            $ids[] = (int)$match['id'];
        }

        $total = $search['total'];
    }
}

$category_id_filter = '';
$group_by           = '';

if ($category_id > 0) {

    $category = $simpla->categories->get_category($category_id);

    $category_ids   = !empty($category->children) ? $category->children : array();
    $category_ids[] = $category_id;

    $category_id_filter = $simpla->db->placehold('AND pc.category_id in(?@)', (array)$category_ids);
    $group_by           = "GROUP BY p.id";
}


if(!empty($ids)) {

    $kw    = $simpla->db->escape($keyword);
    $query = $simpla->db->placehold("SELECT p.id, p.name, p.url, i.filename as image, c.url as category_url
                        FROM __products AS p LEFT JOIN __images i ON i.product_id=p.id AND i.position=(SELECT MIN(position) FROM __images WHERE product_id=p.id LIMIT 1)
                        INNER JOIN __products_categories pc ON pc.product_id = p.id $category_id_filter
				        INNER JOIN __categories c ON c.id = pc.category_id
	                    WHERE p.id in(?@)
	                    GROUP BY p.id
	                    ORDER BY p.name LIMIT ?", $ids, $limit);


    $simpla->db->query($query);

    $res = $simpla->db->results();
}

$products = array();

if (!empty($res)) {

    foreach ($res as $p) {

        $p->full_name = $p->name;
        if (mb_strlen($p->name, 'UTF-8') > 30) {

            $p->name = mb_substr($p->name, 0, 27, 'UTF-8') . '...';
        }
        $products[$p->id] = $p;
    }
}

if (!empty($products)) {

    // Все валюты
    $currencies = $simpla->money->get_currencies(array('enabled' => 1));

    // Берем валюту из сессии
    if (isset($_SESSION['currency_id']))
        $currency = $simpla->money->get_currency($_SESSION['currency_id']);
    // Или первую из списка
    else
        $currency = reset($currencies);

    $products_ids = array_keys($products);

    foreach ($products as &$product) {
        $product->variants = array();
    }

    $variants = $simpla->variants->get_variants(array('product_id' => $products_ids, 'in_stock' => TRUE));

    foreach ($variants as &$variant) {

        $products[$variant->product_id]->variants[] = $variant;
    }

    foreach ($products as &$product) {

        if (isset($product->variants[0])) {

            $product->variant = $product->variants[0];

            $product->variant->price         = $simpla->money->convert($product->variant->price);
            $product->variant->compare_price = $simpla->money->convert($product->variant->compare_price);
            $product->variant->currency      = $currency;
        }
    }
}

$suggestions = array();

foreach ($products as &$product) {

    $suggestion = new stdClass();

    if (!empty($product->image)) {
        $product->image = (string)$simpla->design->resize_modifier((string)$product->image, 35, 35);
    }

    $product->url = (string)$product->category_url . '/' . (string)$product->url;

    $suggestion->value = (string)$product->name;
    $suggestion->data  = $product;
    $suggestion->count = $count;
    $suggestions[]     = $suggestion;
}

$res              = new stdClass;
$res->query       = $keyword;
$res->suggestions = $suggestions;

header("Content-type: application/json; charset=UTF-8");
header("Cache-Control: must-revalidate");
header("Pragma: no-cache");
header("Expires: -1");
print json_encode($res);
