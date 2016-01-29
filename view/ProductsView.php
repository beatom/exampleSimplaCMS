<?PHP

/**
 * Simpla CMS
 *
 * @copyright    2011 Denis Pikusov
 * @link        http://simplacms.ru
 * @author        Denis Pikusov
 *
 * Этот класс использует шаблон products.tpl
 *
 */

require_once('View.php');

class ProductsView extends View
{
    /**
     *
     * Отображение списка товаров
     *
     */


    public $isAjax;


    function fetch()
    {
        // GET-Параметры
        $category_url  = $this->request->get('category', 'string');
        $brand_url     = $this->request->get('brand', 'string');
        $brands        = $this->request->get('brands');
        $shops         = $this->request->get('shops');
        $colors        = $this->request->get('colors');
        $sizes         = $this->request->get('sizes');
        $limit         = min($this->request->get('limit', 'integer'), 500);
        $more          = $this->request->get('more', 'integer');
        $price_min     = $this->request->get('price_min', 'integer');
        $price_max     = $this->request->get('price_max', 'integer');
        $data_features = $this->request->get('features');
        $sale          = $this->request->get('sale');
        $new           = $this->request->get('new');
        $browsed       = $this->request->get('browsed');

        $this->design->assign('data_features', $data_features);

        $filter            = [];

        // Показывать товары только в наличии
//        $filter['visible'] = 1;

        // Устанавливаем время кеширования
        $this->cache->setCacheTime(3600);


        if (!empty($browsed)) {

            $browsed_products_ids = explode(',', $_COOKIE['browsed_products']);
            $browsed_products_ids = array_reverse($browsed_products_ids);

            $filter['browsed'] =  $browsed_products_ids;
        }

        // Фильтр по цветам
        if (! empty($colors)) {
            $filter['colors'] = $colors;
        }
        $this->design->assign('colors', $colors);

        // Фильтр по размерам
        if (! empty($sizes)) {
            $filter['sizes'] = $sizes;
        }
        $this->design->assign('sizes', $sizes);

        // Распродажа
        if (! empty($sale)) {
            $filter['sale'] = true;
            $this->design->assign('is_sale', true); // Для добавления в селектор сортировок сортировку по скидке
        }

        // Новинки
        if (! empty($new)) {
            $filter['new'] = date('Y-m-d', strtotime('- 7 day'));
        }

        // Фильтр по цене
        if (! empty($price_min)) {
            if ($price_min == $this->config->price_min) {

                unset($price_min);
            } else {

                $filter['price_min'] = $this->money->reconvert($price_min);
            }
        }
        if (! empty($price_max)) {
            if ($price_max == $this->config->price_max) {

                unset($price_max);
            } else {

                $filter['price_max'] = $this->money->reconvert($price_max);
            }
        }

        // Если задан бренд, выберем его из базы
        if (! empty($brand_url)) {

            $brand = $this->brands->cache()->get_brand((string)$brand_url);

            if (empty($brand)) {
                return FALSE;
            }

            $this->brands->update_brand($brand->id, [
                'count_views' => $brand->count_views + 1,
            ]);

            $this->design->assign('brand', $brand);
            $filter['brand_id'] = $brand->id;
        } // Фильтр по брендам
        elseif (! empty($brands)) {

            $filter['brand_id'] = $brands;
        }
        $this->design->assign('brands', $brands);

        // Фильтр по магазинам
        if (! empty($shops)) {

            $filter['shop_id'] = $shops;
        }
        $this->design->assign('shops', $shops);

        // Выберем текущую категорию
        if (! empty($category_url)) {

            $this->categories->cache()->init_categories([
                'visible' => true,
            ]); // Пересоздадим список категорий (в старом выводятся только непустые, здесь - все)


            $category = $this->categories
                ->cache()
                ->get_category((string)$category_url);

            if (empty($category) || (! $category->visible && empty($_SESSION['admin'])))
                return FALSE;
            $this->design->assign('category', $category);
            $filter['category_id'] = $category->children;
        }

        // Если задано ключевое слово
        $keyword = $this->request->get('keyword');
        if (! empty($keyword)) {
            $this->design->assign('keyword', $keyword);
            $filter['keyword'] = $keyword;
        }

        // Сортировка товаров, сохраняем в сесси, чтобы текущая сортировка оставалась для всего сайта
        if ($sort = $this->request->get('sort', 'string')) {
            $_SESSION['sort'] = $sort;
        }

        if (! empty($sort)) {
            $filter['sort'] = $sort;
        } else {
            $filter['sort'] = 'new';
        }
        $this->design->assign('sort', $filter['sort']);


        // Свойства товаров
        if (! empty($category)) {
            $features = [];

            foreach ($this->features->cache()->get_features(['category_id' => $category->id, 'in_filter' => 1]) as $feature) {
                $features[$feature->id] = $feature;
                if (($val = strval($this->request->get($feature->id))) != '')
                    $filter['features'][$feature->id] = $val;

                if (! empty($data_features[$feature->id])) {

                    $filter['features'][$feature->id] = $data_features[$feature->id];
                }
            }

            $options_filter['visible'] = 1;

            $features_ids = array_keys($features);
            if (! empty($features_ids))
                $options_filter['feature_id'] = $features_ids;
            $options_filter['category_id'] = $category->children;
            if (isset($filter['features']))
                $options_filter['features'] = $filter['features'];
            if (! empty($brand))
                $options_filter['brand_id'] = $brand->id;

            $options = $this->features->cache()->get_options($options_filter);

            foreach ($options as $option) {
                if (isset($features[$option->feature_id]))
                    $features[$option->feature_id]->options[] = $option;
            }

            foreach ($features as $i => &$feature) {
                if (empty($feature->options))
                    unset($features[$i]);
            }

            $this->design->assign('features', $features);
        }

        // Постраничная навигация
//        $items_per_page = ! empty($limit) ? $limit : $this->settings->products_num;

        if (! empty($more)) {

            $filter['more'] = $more;
        }

        if (! empty($limit)) {

            $items_per_page = $limit;
        } elseif (! empty($_COOKIE['limit']) && $_COOKIE['limit'] > 0) {

            $items_per_page = (int)$_COOKIE['limit'];
        } else {

            $items_per_page = $this->settings->products_num;
        }
        setcookie('limit', $items_per_page, null, "/");
        $this->design->assign('items_per_page', $items_per_page);

        // Текущая страница в постраничном выводе
        $current_page = $this->request->get('page', 'int');

        // Если не задана, то равна 1
        $current_page = max(1, $current_page);
        $this->design->assign('current_page_num', $current_page);
        // Вычисляем количество страниц
        $products_count = $this->products->cache()->count_products($filter);

        // Показать все страницы сразу
        if ($this->request->get('page') == 'all') {
            $items_per_page = $products_count;
        }

        $pages_num = ceil($products_count / $items_per_page);
        $this->design->assign('total_pages_num', $pages_num);
        $this->design->assign('total_products_num', $products_count);

        $filter['page']  = $current_page;
        $filter['limit'] = $items_per_page;

        ///////////////////////////////////////////////
        // Постраничная навигация END
        ///////////////////////////////////////////////

        $discount = 0;
        if (isset($_SESSION['user_id']) && $user = $this->users->get_user(intval($_SESSION['user_id']))) {

            $discount = $user->discount;
        }

        // Товары
        $products = [];
        foreach ($this->products->cache()->get_products($filter) as $p) {

            $products[$p->id] = $p;
        }

        if (! $this->isAjax) {

            // Если искали товар и найден ровно один - перенаправляем на него
            if (! empty($keyword) && $products_count == 1) {

                header('Location: ' . $this->config->root_url . '/products/' . $p->url);
            }
        }

        if (! empty($products)) {

            $products_ids = array_keys($products);
            foreach ($products as &$product) {

                $product->variants   = [];
                $product->images     = [];
                $product->properties = [];
            }

            $variants = $this->variants->cache()->get_variants(['product_id' => $products_ids, 'in_stock' => TRUE]);

            foreach ($variants as &$variant) {

                //$variant->price *= (100-$discount)/100;
                $products[$variant->product_id]->variants[] = $variant;

                $variant = $this->variants->processVariant($variant);
            }

            $images = $this->products->get_images(['product_id' => $products_ids]);
            foreach ($images as $image) {

                $products[$image->product_id]->images[] = $image;
            }

            foreach ($products as &$product) {

                if (isset($product->variants[0])) {

                    $product->variant = $product->variants[0];
                }

                if (isset($product->images[0])) {

                    $product->image = $product->images[0];
                }
            }

            /*
            $properties = $this->features->get_options(array('product_id'=>$products_ids));
            foreach($properties as $property)
                $products[$property->product_id]->options[] = $property;
            */

            $this->design->assign('products', $products);
            $this->design->assign('count_products', count($products));
        }

        // Выбираем бренды и магазины, они нужны нам в шаблоне
        if (! empty($category)) {
            $brands           = $this->brands->cache()->get_brands(['category_id' => $category->children]);
            $category->brands = $brands;

            $shops           = $this->shop->cache()->get_shops(['category_id' => $category->children]);
            $category->shops = $shops;

            $sizes = $this->sizes->cache()->get_sizes([
                'category_id' => $category->children,
                'visible' => TRUE,
            ]);

            $category->sizes = $sizes;

            $colors = $this->colors->cache()->get_colors([
                'category_id' => $category->children,
                'not_empty'   => TRUE,
                'visible' => TRUE,
            ]);
            $category->colors = $colors;
        }

        // Для бренда фильтры
        if (! empty($brand)) {

            $shops           = $this->shop->cache()->get_shops(['brand_id' => $brand->id]);
            $brand->shops = $shops;

            $sizes = $this->sizes->cache()->get_sizes([
                'brand_id' => $brand->id,
                'visible' => TRUE,
            ]);

            $brand->sizes = $sizes;

            $colors        = $this->colors->cache()->get_colors([
                'brand_id'  => $brand->id,
                'not_empty' => TRUE,
                'visible' => TRUE,
            ]);
            $brand->colors = $colors;
        }

        if (! $this->isAjax) {

            $meta_title_page = '';
            if (!empty($filter['page']) && $filter['page'] > 1) {

                $meta_title_page = ' Страница ' . $filter['page'];
            }

            $meta_title_color = '';
            if (!empty($filter['colors'])) {

                $c = [];
                foreach($colors as &$color) {

                    if (in_array($color->id, $filter['colors'])) {

                        $c[] = $color->name;
                    }
                }

                $meta_title_color = '. Цвет ' . implode(', ', $c);
            }

            $meta_title_size = '';
            if (!empty($filter['sizes'])) {

                $s = [];
                foreach($sizes as &$size) {

                    if (in_array($size->id, $filter['sizes'])) {

                        $s[] = $size->name;
                    }
                }

                $meta_title_size = '. Размер ' . implode(', ', $s);
            }

            if (!empty($meta_title_size) || !empty($meta_title_color)) {

                $title_cat = '';

                $cats = [];
                foreach ($category->path as $p) {

                    $cats[] = [
                        'id' => $p->id,
                        'name' => $p->name,
                    ];
                }

                $title_cat .= $cats[0]['name'];

                if (!empty($cats[1])) {

                    if (in_array($cats[1]['id'], [27, 35, 94, 101, 509, 754])) {

                        $txt = 'брендовые';
                    } elseif (in_array($cats[1]['id'], [755])) {

                        $txt = 'брендовое';
                    } elseif (in_array($cats[1]['id'], [188, 189, 1161])) {

                        $txt = 'брендовый';
                    } else {

                        $txt = 'брендовая';
                    }

                    $title_cat .= ' ' . $txt . ' ' . mb_strtolower($cats[1]['name'], 'UTF-8');
                }

                $title_cat .= '. Купить ' . (end($cats)['id'] != $cats[0]['id'] ? mb_strtolower(end($cats)['name'], 'UTF-8') . ' ' : '') . 'на moda.tm';

                $title_cat .= $meta_title_color . $meta_title_size;
                $description_cat = '';
                $keywords_cat = '';
            }

            // Флаг страницы фильтра категорий по бренду
            if (isset($category) && isset($brand)) {
                // Закрываем полностью страницу от индексации
                $this->design->assign('meta_noindex', TRUE);
            }

            // Устанавливаем мета-теги в зависимости от запроса
            if ($this->page) {
                $this->design->assign('meta_title', $this->page->meta_title);
                $this->design->assign('meta_keywords', $this->page->meta_keywords);
                $this->design->assign('meta_description', $this->page->meta_description);
            } elseif (isset($category)) {
                $this->design->assign('meta_title', !empty($title_cat) ? ($title_cat . '.' . $meta_title_page) : ($category->meta_title  . (!empty($category->meta_title) ? $meta_title_page : '')));
                $this->design->assign('meta_keywords', isset($keywords_cat) ? ($keywords_cat  . '.' . $meta_title_page) : ($category->meta_keywords  . (!empty($category->meta_keywords) ? $meta_title_page : '')));
                $this->design->assign('meta_description', isset($description_cat) ? ($description_cat  . '.' . $meta_title_page) : ($category->meta_description  . (!empty($category->meta_description) ? $meta_title_page : '')));
            } elseif (isset($brand)) {
                // Закрываем от индексации список товаров
                $this->design->assign('content_noindex', TRUE);

                $this->design->assign('meta_title', $brand->name . ' купить со скидкой в интернет магазине moda.tm');
                $this->design->assign('meta_keywords', $brand->meta_keywords);
                $this->design->assign('meta_description', $brand->meta_description);
            } elseif (!empty($sale)) {

                // Распродажа
                $page = $this->pages->cache()->get_page('sale');

                // Закрываем от индексации список товаров
                $this->design->assign('content_noindex', TRUE);

                $this->design->assign('page', $page);
                $this->design->assign('meta_title', $page->meta_title);
                $this->design->assign('meta_keywords', $page->meta_keywords);
                $this->design->assign('meta_description', $page->meta_description);

            } elseif (!empty($new)) {

                // Новинки
                $page = $this->pages->cache()->get_page('new');

                // Закрываем от индексации список товаров
                $this->design->assign('content_noindex', TRUE);

                $this->design->assign('page', $page);
                $this->design->assign('meta_title', $page->meta_title);
                $this->design->assign('meta_keywords', $page->meta_keywords);
                $this->design->assign('meta_description', $page->meta_description);

            } elseif (!empty($browsed)) {

                // Просмотренные
                $page = $this->pages->cache()->get_page('browsed');

                // Закрываем от индексации список товаров
                $this->design->assign('content_noindex', TRUE);

                $this->design->assign('page', $page);
                $this->design->assign('meta_title', $page->meta_title);
                $this->design->assign('meta_keywords', $page->meta_keywords);
                $this->design->assign('meta_description', $page->meta_description);

            } elseif (isset($keyword)) {
                $this->design->assign('meta_title', $keyword);
            }

            $filter_data = [
//                'price'     => array(
//                    'min' => $this->config->price_min,
//                    'max' => $this->config->price_max,
//                ),
                'price_min' => ! empty($price_min) ? $price_min : $this->config->price_min,
                'price_max' => ! empty($price_max) ? $price_max : $this->config->price_max,
                'page'      => $this->config->page,
                'limit'     => ! empty($limit) ? $limit : $this->config->limit,
                'more'      => $more,
                'category'  => $category_url,
                'brand'     => $brand_url,
                'features'  => $data_features,
                'sale'      => ! empty($sale) ? $sale : '',
                'new'       => ! empty($new) ? $new : '',
                'browsed'   => ! empty($browsed) ? $browsed : '',
            ];

//            $this->design->assign('category', $category);

            $this->design->assign('filter_data', json_encode($filter_data));

            $this->body = $this->design->fetch('products.tpl');

            $this->cache->save('blocks', 'filters.tpl', $this->design->fetch('filters.tpl'));

            return $this->body;

        }

    }
}
