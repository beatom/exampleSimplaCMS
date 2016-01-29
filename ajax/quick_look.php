<?php

session_start();
require_once('../view/View.php');

class ProductView extends View
{

    function fetch()
    {

        $id = $this->request->get('id', 'integer');

        if (empty($id)) {
            return false;
        }

        // Выбираем товар из базы
        $product = $this->products->get_product($id);

        if (empty($product) || (!$product->visible && empty($_SESSION['admin']))) {
            return false;
        }

        $product->images = $this->products->get_images(array('product_id' => $product->id));
        $product->image = reset($product->images);

        $variants = array();
        foreach ($this->variants->get_variants(array('product_id' => $product->id, 'in_stock' => true)) as $v) {
            $variants[$v->id] = $v;
        }

        $product->variants = $variants;

        // Вариант по умолчанию
        if (($v_id = $this->request->get('variant', 'integer')) > 0 && isset($variants[$v_id])) {
            $product->variant = $variants[$v_id];
        } else {
            $product->variant = reset($variants);
        }

        $product->features = $this->features->get_product_options(array('product_id' => $product->id));

        // Отзывы о товаре
        $comments = $this->comments->get_comments(array('type' => 'product', 'object_id' => $product->id, 'approved' => 1, 'ip' => $_SERVER['REMOTE_ADDR']));

        // И передаем его в шаблон
        $this->design->assign('product', $product);
        $this->design->assign('comments', $comments);

        // Категория и бренд товара
        $product->categories = $this->categories->get_categories(array('product_id' => $product->id));
        $this->design->assign('brand', $this->brands->get_brand(intval($product->brand_id)));
        $this->design->assign('category', reset($product->categories));

        // Цвета товара
        if ($product_colors = $this->products->get_product_colors($id)) {

            $this->design->assign('product_colors', $product_colors);
        }

        // Размеры товара
        if ($product_sizes = $this->products->get_product_sizes($id)) {

            $sizes = [];

            foreach ($product_sizes as $product_size) {

                $sizes[$product_size->name] = $product_size;
            }

            ksort($sizes);

            $this->design->assign('product_sizes', $sizes);
        }

        $result = $this->design->fetch('new_product_modal.tpl');

        header("Content-type: application/json; charset=UTF-8");
        header("Cache-Control: must-revalidate");
        header("Pragma: no-cache");
        header("Expires: -1");
        print json_encode($result);
    }

}

$page = new ProductView();

$page->fetch();
