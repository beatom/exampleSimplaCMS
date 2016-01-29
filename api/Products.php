<?php

/**
 * Работа с товарами
 *
 * @copyright    2011 Denis Pikusov
 * @link        http://simplacms.ru
 * @author        Denis Pikusov
 *
 */

require_once('Simpla.php');

class Products extends Simpla
{

    public $have_new = true;

    /**
     * Функция возвращает товары
     * Возможные значения фильтра:
     * id - id товара или их массив
     * category_id - id категории или их массив
     * brand_id - id бренда или их массив
     * page - текущая страница, integer
     * limit - количество товаров на странице, integer
     * sort - порядок товаров, возможные значения: position(по умолчанию), name, price
     * keyword - ключевое слово для поиска
     * features - фильтр по свойствам товара, массив (id свойства => значение свойства)
     */
    public function get_products($filter = array())
    {
        // По умолчанию
        $limit = 100;
        $page = 1;
        $more = 0;
        $category_id_filter = '';
        $brand_id_filter = '';
        $shop_id_filter = '';
        $product_id_filter = '';
        $features_filter = '';
        $keyword_filter = '';
        $visible_filter = '';
        $visible_filter = '';
        $is_featured_filter = '';
        $discounted_filter = '';
        $in_stock_filter = '';
        $new_filter = '';
        $group_by = 'GROUP BY p.id';
        $order = 'p.position DESC';
        $price_min_filter = '';
        $price_max_filter = '';
        $sale_filter = '';
        $colors_filter = '';
        $sizes_filter = '';
        $start_filter = '';

        if (!empty($filter['start'])) {

            $start_filter = $this->db->placehold('AND p.id < ?', (int)$filter['start']);
            if (isset($filter['offset'])) {
                unset($filter['offset']);
            }
        }

        if (isset($filter['limit'])) {

            $limit = max(1, intval($filter['limit']));
        }

        if (isset($filter['offset'])) {
            $offset = max(1, intval($filter['offset']));
            $sql_limit = $this->db->placehold(' LIMIT ? OFFSET ?', $limit, $offset);
        }

        if (isset($filter['page'])) {

            $page = max(1, intval($filter['page']));
        }


        if (!empty($filter['more'])) {

            $more = intval($filter['more']);
        }

        if (empty($filter['offset'])) {

            // more используется для кнопки "показать еще"
            $sql_limit = $this->db->placehold(' LIMIT ?, ? ', max(0, ($page - 1 - $more)) * $limit, max($limit, $limit * ($more + 1)));
        }

        if (!empty($filter['id'])) {

            $product_id_filter = $this->db->placehold('AND p.id in(?@)', (array)$filter['id']);
        }
        // Просмотренные
        elseif(!empty($filter['browsed'])) {

            $product_id_filter = $this->db->placehold('AND p.id in(?@)', (array)$filter['browsed']);
        }

        if (!empty($filter['category_id'])) {
            $category_id_filter = $this->db->placehold('AND pc.category_id in(?@)', (array)$filter['category_id']);

            if (empty($group_by)) {

                $group_by = "GROUP BY p.id";
            }
        }

        if (!empty($filter['colors'])) {

            $colors_filter = $this->db->placehold('INNER JOIN __products_colors pcol ON pcol.product_id = p.id AND pcol.color_id in(?@)', (array)$filter['colors']);

            if (empty($group_by)) {

                $group_by = "GROUP BY p.id";
            }
        }

        if (!empty($filter['sizes'])) {

            $sizes_filter = $this->db->placehold('INNER JOIN __products_sizes psizes ON psizes.product_id = p.id AND psizes.size_id in(?@)', (array)$filter['sizes']);

            if (empty($group_by)) {

                $group_by = "GROUP BY p.id";
            }
        }

        if (!empty($filter['brand_id'])) {

            $brand_id_filter = $this->db->placehold('AND p.brand_id in(?@)', (array)$filter['brand_id']);
        }

        if (!empty($filter['shop_id'])) {

            $shop_id_filter = $this->db->placehold('AND p.shop_id in(?@)', (array)$filter['shop_id']);
        }

        if (!empty($filter['featured']))
            $is_featured_filter = $this->db->placehold('AND p.featured=?', intval($filter['featured']));

        if (!empty($filter['discounted']))
            $discounted_filter = $this->db->placehold('AND (SELECT 1 FROM __variants pv WHERE pv.product_id=p.id AND pv.compare_price>0 LIMIT 1) = ?', intval($filter['discounted']));

        if (!empty($filter['in_stock']))
            $in_stock_filter = $this->db->placehold('AND (SELECT 1 FROM __variants pv WHERE pv.product_id=p.id AND pv.price>0 AND (pv.stock IS NULL OR pv.stock>0) LIMIT 1) = ?', intval($filter['in_stock']));

        if (!empty($filter['visible']))
            $visible_filter = $this->db->placehold('AND p.visible=?', intval($filter['visible']));

        if (!empty($filter['sale'])) {
//            $sale_filter = $this->db->placehold('AND p.is_sale=?', 1);

            $sale_filter = 'AND (SELECT pv.is_sale FROM __variants pv WHERE p.id = pv.product_id AND pv.is_sale=1 LIMIT 1) != ""';
        }


        if (!empty($filter['new'])) {
            $new_filter = $this->db->placehold('AND p.created > ?', $filter['new']);
        }

        if (!empty($filter['price_min'])) {
            $price_min_filter = $this->db->placehold('AND price >= ?', intval($filter['price_min']));
        }

        if (!empty($filter['price_max'])) {
            $price_max_filter = $this->db->placehold('AND price <= ?', intval($filter['price_max']));
        }

        if (!empty($filter['sort'])) {

            switch ($filter['sort']) {

                case 'new':
                    $order = 'p.position DESC';
                    $new_filter = $this->db->placehold('AND p.created > ?', date('Y-m-d', strtotime('- 7 day')));
                    break;

                case 'price_asc':
                    //$order = 'pv.price IS NULL, pv.price=0, pv.price';
                    $order = '(SELECT -pv.price FROM __variants pv WHERE (pv.stock IS NULL OR pv.stock>0) AND p.id = pv.product_id AND pv.position=(SELECT MIN(position) FROM __variants WHERE (stock>0 OR stock IS NULL) AND product_id=p.id LIMIT 1) LIMIT 1) DESC';
                    break;

                case 'price_desc':
                    //$order = 'pv.price IS NULL, pv.price=0, pv.price';
                    $order = '(SELECT -pv.price FROM __variants pv WHERE (pv.stock IS NULL OR pv.stock>0) AND p.id = pv.product_id AND pv.position=(SELECT MIN(position) FROM __variants WHERE (stock>0 OR stock IS NULL) AND product_id=p.id LIMIT 1) LIMIT 1) ASC';
                    break;

                case 'discount_desc':
                    $order = '(SELECT pv.discount_percent FROM __variants pv WHERE (pv.stock IS NULL OR pv.stock>0) AND p.id = pv.product_id AND pv.position=(SELECT MIN(position) FROM __variants WHERE (stock>0 OR stock IS NULL) AND product_id=p.id LIMIT 1) LIMIT 1) DESC';
                    break;

                case 'discount_asc':
                    $order = '(SELECT pv.discount_percent FROM __variants pv WHERE (pv.stock IS NULL OR pv.stock>0) AND p.id = pv.product_id AND pv.position=(SELECT MIN(position) FROM __variants WHERE (stock>0 OR stock IS NULL) AND product_id=p.id LIMIT 1) LIMIT 1) ASC';
                    break;

            }
        }

        if (!empty($filter['keyword'])) {
            $keywords = explode(' ', $filter['keyword']);
            foreach ($keywords as $keyword)
                $keyword_filter .= $this->db->placehold('AND (p.name LIKE "%' . $this->db->escape(trim($keyword)) . '%" OR p.meta_keywords LIKE "%' . $this->db->escape(trim($keyword)) . '%") ');
        }

        if (!empty($filter['features']) && !empty($filter['features'])) {
            foreach ($filter['features'] as $feature => $value) {

                $features_filter .= $this->db->placehold('AND p.id in (SELECT product_id FROM __options WHERE feature_id=? AND value in(?@)) ', $feature, (array)$value);
            }
        }

        while(true) {

            $query = "SELECT
                        /*star-rating*/
                        p.rating,
                        p.votes,
                        /*/star-rating*/
					p.id,
					p.url,
					c.url as category_url,
					p.brand_id,
					p.name,
					p.annotation,
					p.body,
					p.position,
					p.created as created,
					p.visible,
					p.featured,
					p.resource,
					p.meta_title,
					p.meta_keywords,
					p.meta_description,
					b.name as brand,
					b.url as brand_url,
					s.name as shop,
					s.url as shop_url,
					s.image as shop_image,
					p.shop_id,
					(SELECT pv.price FROM s_variants pv WHERE (pv.stock IS NULL OR pv.stock>0) AND p.id = pv.product_id AND pv.position=(SELECT MIN(position) FROM s_variants WHERE (stock>0 OR stock IS NULL) AND product_id=p.id LIMIT 1) LIMIT 1) as price
				FROM __products p
				INNER JOIN __products_categories pc ON pc.product_id = p.id $category_id_filter
				INNER JOIN __categories c ON c.id = pc.category_id
				$colors_filter
				$sizes_filter
				LEFT JOIN __brands b ON p.brand_id = b.id
				LEFT JOIN __shop s ON p.shop_id = s.id

				WHERE
					1
					$product_id_filter
					$brand_id_filter
					$shop_id_filter
					$features_filter
					$keyword_filter
					$is_featured_filter
					$discounted_filter
					$in_stock_filter
					$visible_filter
					$new_filter
					$sale_filter
					$start_filter

				$group_by
				HAVING
				    1
				    $price_min_filter
				    $price_max_filter
				ORDER BY visible DESC, $order
					$sql_limit";

            $query = $this->db->placehold($query);


//            exit($query);
            $is_cache = false;
            if ($this->db->isCache()) {

                $is_cache = true;
            }

            $this->db->query($query);

            $results = $this->db->results();

            if (!empty($new_filter) && empty($results)) {

                if ($is_cache) {

                    $this->db->cache();
                }

                $new_filter = '';
                $order = 'p.position DESC';
                $this->have_new = false;

            } else {

                return $results;
            }
        }
    }

    /**
     * Функция возвращает количество товаров
     * Возможные значения фильтра:
     * category_id - id категории или их массив
     * brand_id - id бренда или их массив
     * keyword - ключевое слово для поиска
     * features - фильтр по свойствам товара, массив (id свойства => значение свойства)
     */
    public function count_products($filter = array())
    {
        $category_id_filter = '';
        $brand_id_filter = '';
        $shop_id_filter = '';
        $product_id_filter = '';
        $keyword_filter = '';
        $visible_filter = '';
        $is_featured_filter = '';
        $in_stock_filter = '';
        $discounted_filter = '';
        $features_filter = '';
        $new_filter = '';
        $price_min_filter = '';
	    $price_max_filter = '';
	    $sale_filter = '';
        $colors_filter = '';
        $sizes_filter = '';

        if (!empty($filter['category_id']))
            $category_id_filter = $this->db->placehold('INNER JOIN __products_categories pc ON pc.product_id = p.id AND pc.category_id in(?@)', (array)$filter['category_id']);


        if (!empty($filter['colors'])) {

            $colors_filter = $this->db->placehold('INNER JOIN __products_colors pcol ON pcol.product_id = p.id AND pcol.color_id in(?@)', (array)$filter['colors']);

        }

        if (!empty($filter['sizes'])) {

            $sizes_filter = $this->db->placehold('INNER JOIN __products_sizes psizes ON psizes.product_id = p.id AND psizes.size_id in(?@)', (array)$filter['sizes']);

        }

        if (!empty($filter['brand_id']))
            $brand_id_filter = $this->db->placehold('AND p.brand_id in(?@)', (array)$filter['brand_id']);

        if (!empty($filter['shop_id'])) {

            $shop_id_filter = $this->db->placehold('AND p.shop_id in(?@)', (array)$filter['shop_id']);
        }

        if (!empty($filter['id'])) {

            $product_id_filter = $this->db->placehold('AND p.id in(?@)', (array)$filter['id']);
        }
        // Просмотренные
        elseif(!empty($filter['browsed'])) {

            $product_id_filter = $this->db->placehold('AND p.id in(?@)', (array)$filter['browsed']);
        }


        if (isset($filter['keyword'])) {
            $keywords = explode(' ', $filter['keyword']);
            foreach ($keywords as $keyword)
                $keyword_filter .= $this->db->placehold('AND (p.name LIKE "%' . $this->db->escape(trim($keyword)) . '%" OR p.meta_keywords LIKE "%' . $this->db->escape(trim($keyword)) . '%") ');
        }

        if (!empty($filter['featured']))
            $is_featured_filter = $this->db->placehold('AND p.featured=?', intval($filter['featured']));

        if (!empty($filter['in_stock']))
            $in_stock_filter = $this->db->placehold('AND (SELECT 1 FROM __variants pv WHERE pv.product_id=p.id AND pv.price>0 AND (pv.stock IS NULL OR pv.stock>0) LIMIT 1) = ?', intval($filter['in_stock']));

        if (!empty($filter['discounted']))
            $discounted_filter = $this->db->placehold('AND (SELECT 1 FROM __variants pv WHERE pv.product_id=p.id AND pv.compare_price>0 LIMIT 1) = ?', intval($filter['discounted']));

        if (!empty($filter['visible']))
            $visible_filter = $this->db->placehold('AND p.visible=?', intval($filter['visible']));

        if (!empty($filter['sale'])) {
//            $sale_filter = $this->db->placehold('AND p.is_sale=?', 1);

            $sale_filter = 'AND (SELECT pv.is_sale FROM __variants pv WHERE p.id = pv.product_id AND pv.is_sale=1 LIMIT 1) != ""';
        }

        if (!empty($filter['features']) && !empty($filter['features'])) {
            foreach ($filter['features'] as $feature => $value) {

                $features_filter .= $this->db->placehold('AND p.id in (SELECT product_id FROM __options WHERE feature_id=? AND value in(?@)) ', $feature, (array)$value);
            }
        }

        if (!empty($filter['new'])) {
            $new_filter = $this->db->placehold('AND p.created > ?', $filter['new']);
        }

        if (!empty($filter['sort'])) {

            switch ($filter['sort']) {

                case 'new':
                    $new_filter = $this->db->placehold('AND p.created > ?', date('Y-m-d', strtotime('- 7 day')));
                    break;
            }
        }

        if (!empty($filter['price_min'])) {
            $price_min_filter = $this->db->placehold('AND (SELECT pv.price FROM s_variants pv WHERE (pv.stock IS NULL OR pv.stock>0) AND p.id = pv.product_id AND pv.position=(SELECT MIN(position) FROM s_variants WHERE (stock>0 OR stock IS NULL) AND product_id=p.id LIMIT 1) LIMIT 1) >= ?', intval($filter['price_min']));
        }

        if (!empty($filter['price_max'])) {
            $price_max_filter = $this->db->placehold('AND (SELECT pv.price FROM s_variants pv WHERE (pv.stock IS NULL OR pv.stock>0) AND p.id = pv.product_id AND pv.position=(SELECT MIN(position) FROM s_variants WHERE (stock>0 OR stock IS NULL) AND product_id=p.id LIMIT 1) LIMIT 1) <= ?', intval($filter['price_max']));
        }

        while(true) {

            $query = "SELECT
                    count(distinct p.id) as count
                    FROM __products AS p
				$category_id_filter
				$colors_filter
				$sizes_filter
				WHERE 1
					$brand_id_filter
					$shop_id_filter
					$product_id_filter
					$keyword_filter
					$is_featured_filter
					$in_stock_filter
					$discounted_filter
					$visible_filter
					$features_filter
					$new_filter
					$price_min_filter
				    $price_max_filter
				    $sale_filter
					";

            $is_cache = false;
            if ($this->db->isCache()) {

                $is_cache = true;
            }

            $this->db->query($query);

            $result = $this->db->result('count');

            if (!empty($new_filter) && empty($result)) {

                if ($is_cache) {

                    $this->db->cache();
                }

                $new_filter = '';
                $this->have_new = false;

            } else {

                return $result;
            }
        }
    }

    /**
     * Функция возвращает товар по id
     * @param    $id
     * @retval    object
     */
    public function find_product($id)
    {
        if (is_int($id)) {

            $filter = $this->db->placehold('BINARY p.id = ?', $id);
        } else {

            $filter = $this->db->placehold('BINARY p.url = ?', $id);
        }

        $query = "SELECT
					p.id
				FROM __products AS p
                WHERE $filter
                LIMIT 1";
        $this->db->query($query);
        $product = $this->db->result();

        return $product;
    }

    /**
     * Функция возвращает товар по id
     * @param    $id
     * @retval    object
     */
    public function get_product($id, $category = null)
    {
        if (is_int($id))
            $filter = $this->db->placehold('BINARY p.id = ?', $id);
        else
            $filter = $this->db->placehold('BINARY p.url = ?', $id);

        $category_filter = '';
        if (!empty($category)) {

            $category_filter = $this->db->placehold('AND c.url=?', $category);
        }

        $query = "SELECT DISTINCT
                        /*star-rating*/
                        p.rating,
                        p.votes,  
                        /*/star-rating*/ 
					p.id,
					p.url,
					c.url as category_url,
					c.id as category_id,
					p.brand_id,
					p.name,
					p.annotation,
					p.body,
					p.position,
					p.created as created,
					p.visible, 
					p.featured,
					p.resource,
					p.meta_title,
					p.meta_keywords, 
					p.meta_description,
					s.name as shop,
					s.url as shop_url,
					s.image as shop_image,
					p.shop_id
				FROM __products AS p
                LEFT JOIN __brands b ON p.brand_id = b.id
                LEFT JOIN __shop s ON p.shop_id = s.id
                LEFT JOIN __products_categories pc ON p.id = pc.product_id
                INNER JOIN __categories c ON c.id = pc.category_id $category_filter

                WHERE $filter
                GROUP BY p.id
                LIMIT 1";
        $this->db->query($query);
        $product = $this->db->result();

        return $product;
    }

    public function update_product($id, $product)
    {
        $query = $this->db->placehold("UPDATE __products SET ?% WHERE id in (?@) LIMIT ?", $product, (array)$id, count((array)$id));
        if ($this->db->query($query))
            return $id;
        else
            return false;
    }

    public function add_product($product)
    {
        $product = (array)$product;

        if (empty($product['url'])) {
            $product['url'] = preg_replace("/[\s]+/ui", '-', $product['name']);
            $product['url'] = strtolower(preg_replace("/[^0-9a-zа-я\-]+/ui", '', $product['url']));
        }

        $url =  preg_replace("/[\s]+/ui", '-', $product['name']);
        $url = strtolower(preg_replace("/[^0-9a-zа-я\-]+/ui", '', $url));

        if (!empty($product['url'])) {

            unset($product['url']);
        }

        $query = $this->db->placehold("INSERT INTO __products SET ?%", $product);

        if ($this->db->query($query)) {

            $id = $this->db->insert_id();

            $update = [
                'position' => $id,
                'url' => $url . '_' . $id,
            ];

            $this->db->query("UPDATE __products SET ?% WHERE id=?", $update, $id);

            return $id;
        } else {

            return false;
        }
    }


    function create_product_url($url)
    {
        $this->db->query("CALL GET_URL(?);", (string)$url);
        return $this->db->result('res_url');
    }


    /*
    *
    * Удалить товар
    *
    */
    public function delete_product($id)
    {
        if (!empty($id)) {
            // Удаляем варианты
            $variants = $this->variants->get_variants(array('product_id' => $id));
            foreach ($variants as $v)
                $this->variants->delete_variant($v->id);

            // Удаляем изображения
            $images = $this->get_images(array('product_id' => $id));
            foreach ($images as $i)
                $this->delete_image($i->id);

            // Удаляем категории
            $categories = $this->categories->get_categories(array('product_id' => $id));
            foreach ($categories as $c)
                $this->categories->delete_product_category($id, $c->id);

            // Удаляем свойства
            $options = $this->features->get_options(array('product_id' => $id));
            foreach ($options as $o)
                $this->features->delete_option($id, $o->feature_id);

            // Удаляем связанные товары
            $related = $this->get_related_products($id);
            foreach ($related as $r)
                $this->delete_related_product($id, $r->related_id);

            // Удаляем отзывы
            $comments = $this->comments->get_comments(array('object_id' => $id, 'type' => 'product'));
            foreach ($comments as $c)
                $this->comments->delete_comment($c->id);

            // Удаляем из покупок
            $this->db->query('UPDATE __purchases SET product_id=NULL WHERE product_id=?', intval($id));

            // Удаляем товар
            $query = $this->db->placehold("DELETE FROM __products WHERE id=? LIMIT 1", intval($id));
            if ($this->db->query($query))
                return true;
        }
        return false;
    }

    public function duplicate_product($id)
    {
        $product = $this->get_product($id);
        $product->id = null;
        $product->created = null;

        // Сдвигаем товары вперед и вставляем копию на соседнюю позицию
        $this->db->query('UPDATE __products SET position=position+1 WHERE position>?', $product->position);
        $new_id = $this->products->add_product($product);
        $this->db->query('UPDATE __products SET position=? WHERE id=?', $product->position + 1, $new_id);

        // Очищаем url
        $this->db->query('UPDATE __products SET url="" WHERE id=?', $new_id);

        // Дублируем категории
        $categories = $this->categories->get_product_categories($id);
        foreach ($categories as $c)
            $this->categories->add_product_category($new_id, $c->category_id);

        // Дублируем изображения
        $images = $this->get_images(array('product_id' => $id));
        foreach ($images as $image)
            $this->add_image($new_id, $image->filename);

        // Дублируем варианты
        $variants = $this->variants->get_variants(array('product_id' => $id));
        foreach ($variants as $variant) {
            $variant->product_id = $new_id;
            unset($variant->id);
            if ($variant->infinity)
                $variant->stock = null;
            unset($variant->infinity);
            $this->variants->add_variant($variant);
        }

        // Дублируем свойства
        $options = $this->features->get_options(array('product_id' => $id));
        foreach ($options as $o)
            $this->features->update_option($new_id, $o->feature_id, $o->value);

        // Дублируем связанные товары
        $related = $this->get_related_products($id);
        foreach ($related as $r)
            $this->add_related_product($new_id, $r->related_id);


        return $new_id;
    }


    function get_related_products($product_id = array())
    {
        if (empty($product_id))
            return array();

        $product_id_filter = $this->db->placehold('AND product_id in(?@)', (array)$product_id);

        $query = $this->db->placehold("SELECT product_id, related_id, position
					FROM __related_products
					WHERE
					1
					$product_id_filter
					ORDER BY position
					");

        $this->db->query($query);
        return $this->db->results();
    }

    function get_related_products_dynamic($product_id, $category_id, $price)
    {

        $price_min = $price * 0.5;
        $price_max = $price * 1.5;

        $query = $this->db->placehold("
                    SELECT product_id FROM (
                        SELECT product_id
                        FROM __products p
                        LEFT JOIN __products_categories pc ON p.id = pc.product_id
                        INNER JOIN __categories c ON c.id = pc.category_id AND c.id=?
                        WHERE
                        p.id != ?
                        AND (SELECT pv.price FROM s_variants pv WHERE (pv.stock IS NULL OR pv.stock>0) AND p.id = pv.product_id AND pv.position=(SELECT MIN(position) FROM s_variants WHERE (stock>0 OR stock IS NULL) AND product_id=p.id LIMIT 1) LIMIT 1) >= ?
                        AND (SELECT pv.price FROM s_variants pv WHERE (pv.stock IS NULL OR pv.stock>0) AND p.id = pv.product_id AND pv.position=(SELECT MIN(position) FROM s_variants WHERE (stock>0 OR stock IS NULL) AND product_id=p.id LIMIT 1) LIMIT 1) <= ?
                        ORDER BY p.position DESC
                        LIMIT 30
					) related_products
					ORDER BY rand()
					", $category_id, $product_id, $price_min, $price_max);

        $this->db->query($query);
        return $this->db->results();
    }

    // Функция возвращает связанные товары
    public function add_related_product($product_id, $related_id, $position = 0)
    {
        $query = $this->db->placehold("INSERT IGNORE INTO __related_products SET product_id=?, related_id=?, position=?", $product_id, $related_id, $position);
        $this->db->query($query);
        return $related_id;
    }

    // Удаление связанного товара
    public function delete_related_product($product_id, $related_id)
    {
        $query = $this->db->placehold("DELETE FROM __related_products WHERE product_id=? AND related_id=? LIMIT 1", intval($product_id), intval($related_id));
        $this->db->query($query);
    }


    function get_images($filter = array())
    {
        $product_id_filter = '';
        $group_by = '';

        if (!empty($filter['product_id']))
            $product_id_filter = $this->db->placehold('AND i.product_id in(?@)', (array)$filter['product_id']);

        // images
        $query = $this->db->placehold("SELECT i.id, i.product_id, i.name, i.filename, i.position
									FROM __images AS i WHERE 1 $product_id_filter $group_by ORDER BY i.product_id, i.position");
        $this->db->query($query);
        return $this->db->results();
    }

    public function add_image($product_id, $filename, $name = '')
    {
        $query = $this->db->placehold("SELECT id FROM __images WHERE product_id=? AND filename=?", $product_id, $filename);
        $this->db->query($query);
        $id = $this->db->result('id');
        if (empty($id)) {
            $image_filename = pathinfo($filename, PATHINFO_BASENAME);

            $query = $this->db->placehold("INSERT INTO __images SET product_id=?, filename=?, base_filename=?, file_resource=? ", $product_id, $filename, $image_filename, $filename);
            $this->db->query($query);
            $id = $this->db->insert_id();
            $query = $this->db->placehold("UPDATE __images SET position=id WHERE id=?", $id);
            $this->db->query($query);
        }
        return ($id);
    }

    public function update_image($id, $image)
    {

        $query = $this->db->placehold("UPDATE __images SET ?% WHERE id=?", $image, $id);
        $this->db->query($query);

        return ($id);
    }

    public function delete_image($id)
    {
        $query = $this->db->placehold("SELECT filename FROM __images WHERE id=?", $id);
        $this->db->query($query);
        $filename = $this->db->result('filename');
        $query = $this->db->placehold("DELETE FROM __images WHERE id=? LIMIT 1", $id);
        $this->db->query($query);
        $query = $this->db->placehold("SELECT count(*) as count FROM __images WHERE filename=? LIMIT 1", $filename);
        $this->db->query($query);
        $count = $this->db->result('count');
        if ($count == 0) {
            $file = pathinfo($filename, PATHINFO_FILENAME);
            $ext = pathinfo($filename, PATHINFO_EXTENSION);

            // Удалить все ресайзы
            $rezised_images = glob($this->config->root_dir . $this->config->resized_images_dir . $file . ".*x*." . $ext);
            if (is_array($rezised_images))
                foreach (glob($this->config->root_dir . $this->config->resized_images_dir . $file . ".*x*." . $ext) as $f)
                    @unlink($f);

            @unlink($this->config->root_dir . $this->config->original_images_dir . $filename);
        }
    }

    /*
    *
    * Следующий товар
    *
    */
    public function get_next_product($id)
    {
        $this->db->query("SELECT position FROM __products WHERE id=? LIMIT 1", $id);
        $position = $this->db->result('position');

        $this->db->query("SELECT pc.category_id FROM __products_categories pc WHERE product_id=? ORDER BY position LIMIT 1", $id);
        $category_id = $this->db->result('category_id');

        $query = $this->db->placehold("SELECT id FROM __products p, __products_categories pc
										WHERE pc.product_id=p.id AND p.position>? 
										AND pc.position=(SELECT MIN(pc2.position) FROM __products_categories pc2 WHERE pc.product_id=pc2.product_id)
										AND pc.category_id=? 
										AND p.visible ORDER BY p.position limit 1", $position, $category_id);
        $this->db->query($query);

        return $this->get_product((integer)$this->db->result('id'));
    }

    /*
    *
    * Предыдущий товар
    *
    */
    public function get_prev_product($id)
    {
        $this->db->query("SELECT position FROM __products WHERE id=? LIMIT 1", $id);
        $position = $this->db->result('position');

        $this->db->query("SELECT pc.category_id FROM __products_categories pc WHERE product_id=? ORDER BY position LIMIT 1", $id);
        $category_id = $this->db->result('category_id');

        $query = $this->db->placehold("SELECT id FROM __products p, __products_categories pc
										WHERE pc.product_id=p.id AND p.position<? 
										AND pc.position=(SELECT MIN(pc2.position) FROM __products_categories pc2 WHERE pc.product_id=pc2.product_id)
										AND pc.category_id=? 
										AND p.visible ORDER BY p.position DESC limit 1", $position, $category_id);
        $this->db->query($query);

        return $this->get_product((integer)$this->db->result('id'));
    }


    public function get_product_colors($product_id) {

        $query = $this->db->placehold("SELECT pc.color_id, c.color_key, c.name, c.code, c.image, c.visible
        FROM __products_colors pc
        LEFT JOIN __colors c ON pc.color_id = c.id
        WHERE pc.product_id=? AND code != ''
        ORDER BY pc.position", $product_id);

        $this->db->query($query);

        return $this->db->results();
    }

    public function get_product_color($product_id, $color_id) {

        $query = $this->db->placehold("SELECT id FROM __products_colors
                                        WHERE product_id=?, color_id=? LIMIT 1", $product_id, $color_id);

        $this->db->query($query);

        if ($res = $this->db->result()) {

            return $res;
        }

        return false;
    }


    // Добавить цвет к заданному товару
    public function add_product_color($product_id, $color_id, $position=0)
    {
        $query = $this->db->placehold("INSERT IGNORE INTO __products_colors SET product_id=?, color_id=?, position=?", $product_id, $color_id, $position);
        $this->db->query($query);
    }

    // Удалить цвет заданного товара
    public function delete_product_color($product_id, $color_id)
    {
        $query = $this->db->placehold("DELETE FROM __products_colors WHERE product_id=? AND color_id=? LIMIT 1", intval($product_id), intval($color_id));
        $this->db->query($query);
    }

    // Удалить старые цвета заданного товара
    public function delete_old_product_colors($product_id, $color_id)
    {
        $query = $this->db->placehold("DELETE FROM __products_colors WHERE product_id=? AND color_id NOT IN(?@)", intval($product_id), (array)$color_id);
        $this->db->query($query);
    }

    public function get_product_sizes($product_id) {

        $query = $this->db->placehold("SELECT ps.size_id, s.name, s.type, s.visible
        FROM __products_sizes ps
        LEFT JOIN __sizes s ON ps.size_id = s.id
        WHERE ps.product_id=? ORDER BY ps.position", $product_id);

        $this->db->query($query);

        return $this->db->results();
    }

    public function get_product_size($product_id, $size_id) {

        $query = $this->db->placehold("SELECT id FROM __products_sizes
                                        WHERE product_id=?, size_id=? LIMIT 1", $product_id, $size_id);

        $this->db->query($query);

        if ($res = $this->db->result()) {

            return $res;
        }

        return false;
    }

    // Добавить размер к заданному товару
    public function add_product_size($product_id, $size_id, $position=0)
    {
        $query = $this->db->placehold("INSERT IGNORE INTO __products_sizes SET product_id=?, size_id=?, position=?", $product_id, $size_id, $position);
        $this->db->query($query);
    }

    // Удалить размер заданного товара
    public function delete_product_size($product_id, $size_id)
    {
        $query = $this->db->placehold("DELETE FROM __products_sizes WHERE product_id=? AND size_id=? LIMIT 1", intval($product_id), intval($size_id));
        $this->db->query($query);
    }

    // Удалить старые размеры заданного товара
    public function delete_old_product_sizes($product_id, $size_id)
    {
        $query = $this->db->placehold("DELETE FROM __products_sizes WHERE product_id=? AND size_id NOT IN(?@)", intval($product_id), (array)$size_id);
        $this->db->query($query);
    }


}