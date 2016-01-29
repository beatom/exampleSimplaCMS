<?php

session_start();
require_once('../view/View.php');

class ProductView extends View
{

    function fetch()
    {
        $offset = $this->request->get('offset', 'integer');
        $limit  = $this->request->get('limit', 'integer');
        $start  = $this->request->get('start', 'integer');

        if (empty($offset)) {
            return FALSE;
        }

        $this->get_new_products_plugin([
            'var'    => 'new_products',
            'limit'  => !empty($limit) ? $limit : 10,
            'offset' => !empty($offset) ? $offset : 10,
            'start'  => !empty($start) ? $start : 0,
            'new'    => 1,
        ], $this->design->smarty);

        $html = $this->design->fetch('new_product.tpl');

        $result = [
            'html' => $html,
        ];

        header("Content-type: application/json; charset=UTF-8");
        header("Cache-Control: must-revalidate");
        header("Pragma: no-cache");
        header("Expires: -1");
        print json_encode($result);

    }

}

$page = new ProductView();

$page->fetch();
