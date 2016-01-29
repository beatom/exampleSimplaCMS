<?php

session_start();

require_once('../view/ProductsView.php');

class ProductsAjaxView extends ProductsView
{

    function fetchAjax()
    {
        $this->isAjax = true;
        $this->design->assign('isAjax', $this->isAjax);

        $result = [
            'success' => true,
        ];

        $this->fetch();

        $url = $this->request->url_empty_all();

        $result['products'] = $this->design->fetch('products_list.tpl');
        $result['pagination'] = $this->design->fetch('top_filter.tpl');
        $result['count'] = $this->design->get_var('total_products_num');
        $result['count_products'] = $this->design->get_var('count_products');

        $q_string = explode('?', $url);
        $result['url'] = !empty($url) ? (current(explode("?", $_SERVER['HTTP_REFERER'])) . (count(explode('?', $url)) > 1 ? ('?' . end($q_string)) : '' )) : '';

        header("Content-type: application/json; charset=UTF-8");
        header("Cache-Control: must-revalidate");
        header("Pragma: no-cache");
        header("Expires: -1");
        print json_encode($result);

    }

}

$page = new ProductsAjaxView();

$page->fetchAjax();
