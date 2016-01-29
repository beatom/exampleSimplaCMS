<?php

$pach = realpath(__DIR__ . '/../../api/Simpla.php');

require_once($pach);

class ImportXmlAjax extends Simpla
{

    // Соответствие полей в базе и имён колонок в файле
    public $columns_names = [
        'name' => ['product', 'name', 'товар', 'название', 'наименование'],
        'url' => ['url', 'адрес'],
        'visible' => [
            'visible',
            'published',
            'видим',
            'available',
        ],
        'featured' => ['featured', 'hit', 'хит', 'рекомендуемый'],
        'category' => ['category', 'категория'],
        'brand' => ['brand', 'бренд'],
        'variant' => ['variant', 'вариант'],
        'price' => ['price', 'цена'],
        'compare_price' => ['compare price', 'старая цена'],
        'sku' => ['sku', 'артикул'],
        'stock' => ['stock', 'склад', 'на складе'],
        'meta_title' => ['meta title', 'заголовок страницы'],
        'meta_keywords' => ['meta keywords', 'ключевые слова'],
        'meta_description' => ['meta description', 'описание страницы'],
        'annotation' => ['annotation', 'аннотация', 'краткое описание'],
        'description' => ['description', 'описание'],
        'images' => ['images', 'изображения'],
        'color' => ['color', 'цвет'],
        'size' => ['size', 'размер'],
    ];

    public $currency = [
        'RUR' => ['RUR', 'RUB'],
        'USD' => ['USD'],
        'UAH' => ['UAH'],
    ];

    // Соответствие имени колонки и поля в базе
    public $internal_columns_names = [];

    public $import_files_dir = '../files/import/'; // Временная папка
    public $import_file = 'import.xml'; // Временный файл
    public $category_delimiter = ','; // Разделитель каегорий в файле
    public $subcategory_delimiter = '/'; // Разделитель подкаегорий в файле
    public $products_count = 10;
    public $columns = [];
    public $categories_row = [];
    public $categories_names = [];
    public $items = [];
    public $profile = 'yml';
    public $settings = [];
    public $shop_info;
    public $xml;
    public $log = [];


    /**
     * @param array $log
     */
    public function addLog($k, $v, $kk = false)
    {
        if ($kk === false) {

            $this->log[$k][time()][] = $v;
        } else {

            $this->log[$k][$kk] = $v;
        }
    }

    /**
     * @return array
     */
    public function getLog($k = null)
    {
        if (!empty($k)) {

            return $this->log[$k];
        }

        return $this->log;
    }


    public function __construct($profile = null)
    {
        parent::__construct();
        $import_files_dir = realpath(__DIR__ . '/../files/import/');
        $this->import_files_dir = $import_files_dir . '/';

        if (!empty($profile)) {

            $this->setProfile($profile);

        } elseif ($profile = $this->request->get('profile')) {

            $this->setProfile($profile);
        }
    }

    /**
     * @param mixed $profile
     */
    public function setProfile($profile)
    {
        if (!empty($profile)) {
            $this->profile = $profile;
        }

        $this->settings = $this->import->get_settings($this->profile);
        $this->setColumnsNames(unserialize($this->settings->fields));

        $this->setCategoriesNames(unserialize($this->settings->category));
        $this->settings->category = unserialize($this->settings->category);

        return $this;
    }

    /**
     * @param array $categories_names
     */
    public function setCategoriesNames($categories_names = [])
    {
        $categories = (array)$this->categories->get_categories();

        $categories_names = array_diff((array)$categories_names, [null]);

        if (!empty($categories_names) && !empty($categories)) {

            foreach ($categories_names as $k => $v) {

                if  (!empty($categories[$k]->name)) {
                    $this->categories_names[$v] = $categories[$k]->name;
                }
            }

        }
    }

    /**
     * @param array $columns_names
     */
    public function setColumnsNames($columns_names = [])
    {
        if (!empty($columns_names)) {

            $columns_names = array_diff($columns_names, [null]);

            $row = [];
            foreach ($columns_names as $k => $v) {
                $row[$k] = (array)$v;
            }

            $this->columns_names = array_merge($this->columns_names, $row);
        }


//        print_r($this->columns_names); exit;

    }

    public function getCategories($categories)
    {
    }

    public function getItems($categories)
    {
    }

    public function getShop()
    {
    }

    public function import()
    {
        if (!$this->managers->access('import')) {
            return FALSE;
        }

        // Для корректной работы установим локаль UTF-8
        setlocale(LC_ALL, 'ru_RU.UTF-8');

        $result = new stdClass;

        $xml = simplexml_load_file($this->import_files_dir . $this->import_file);

        $categories = $this->getCategories($xml->shop->categories);

        $items = $this->getItems($xml->shop->offers);

        $count_items = count($items);

        $start = 0;

        // Переходим на заданную позицию, если импортируем не сначала
        if ($from = $this->request->get('from')) {

            $start = $from;
        }

        // Массив импортированных товаров
        $imported_items = [];

        // Проходимся по строкам
        // или пока не импортировано достаточно строк для одного запроса
        for ($k = $start; $k <= $count_items && $k < ($this->products_count + $start); $k++) {

            $product = null;

            if (empty($items[$k]) || !is_array($items[$k])) {
                continue;
            }

            $product = $items[$k];

            $this->columns = array_keys($items[$k]);

            // Заменяем имена колонок из файла на внутренние имена колонок
            foreach ($this->columns as &$column) {
                if ($internal_name = $this->internal_column_name($column)) {
                    $this->internal_columns_names[$column] = $internal_name;
                    $column = $internal_name;
                }
            }

            // Если нет названия товара - не будем импортировать
            if (!in_array('name', $this->columns)
                && !in_array('sku', $this->columns)
            ) {

                continue;
            }

            foreach ($product as $kk => $vv) {
                if (isset($this->internal_columns_names[$kk]) && $kk != $this->internal_columns_names[$kk]) {
                    $product[$this->internal_columns_names[$kk]] = $vv;
                    unset($product[$kk]);
                }
            }

            // Импортируем этот товар
            if ($imported_item = $this->import_item($product)) {

                $imported_items[] = $imported_item;
            }
        }

        // Запоминаем на каком месте закончили импорт
        $from = $k;

        // И закончили ли полностью весь массив
        $result->end = $k >= max(array_keys($items)) ? TRUE : FALSE;

        // Создаем объект результата
        $result->from = $from; // На каком месте остановились
        $result->totalsize = $count_items; // Размер всего файла
        $result->items = $imported_items; // Импортированные товары

        return $result;
    }

    // Импорт одного товара $item[column_name] = value;
    public function import_item($item)
    {
        $imported_item = new stdClass;

        // Проверим не пустое ли название и артинкул (должно быть хоть что-то из них)
        if (empty($item['name']) && empty($item['sku'])) {

            $note = $this->getLog($this->settings->name)['note'];

            $note['error']['Не указано имя и артикул товара (пропускаем)'] = [
                'count' => empty($note['error']['Не указано имя и артикул товара (пропускаем)']['count']) ? 1 : $note['error']['Не указано имя и артикул товара (пропускаем)']['count'] + 1,
            ];

            $this->addLog($this->settings->name, $note, 'note');

            return FALSE;
        }


        // Подготовим товар для добавления в базу
        $product = [];

        if (isset($item['name']))
            $product['name'] = trim($item['name']);

        // Наличие товара
        if (isset($item['visible'])) {

            $product['visible'] = $item['visible'] === true ? 1 : 0;
        } else {

            $product['visible'] = 1;
        }

        if (isset($item['meta_title']))
            $product['meta_title'] = trim($item['meta_title']);

        if (isset($item['meta_keywords']))
            $product['meta_keywords'] = trim($item['meta_keywords']);

        if (isset($item['meta_description']))
            $product['meta_description'] = trim($item['meta_description']);

        if (isset($item['annotation']))
            $product['annotation'] = trim($item['annotation']);

        if (isset($item['description'])){

            $product['body'] = trim($item['description']);
        } else {

            $product['body'] = '';
        }


        if (isset($item['visible'])) {
            if ($item['visible'] == true) {
                $item['visible'] = 1;
            }
            $product['visible'] = intval($item['visible']);
        }

        if (isset($item['featured']))
            $product['featured'] = intval($item['featured']);

        if (isset($item['url'])) {
            $product['resource'] = trim($item['url']);
        }

        if (isset($item['name'])) {
            $product['url'] = $this->translit($item['name']);
        }

        $product['hash'] = '';
        if (!empty($item['images'][0])) {

            $product['hash'] = md5($item['images'][0]);

//            echo '<pre>';
//            print_r($item['name']);
//            echo '</pre>';
//            echo '<pre>';
//            print_r($item['images'][0]);
//            echo '</pre>';
//            echo '<pre>';
//            print_r(md5($item['images'][0]));
//            echo '</pre>';
        } else {

            $note = $this->getLog($this->settings->name)['note'];

            $note['error']['У товара нет изображений (пропускаем)'] = [
                'count' => empty($note['error']['У товара нет изображений (пропускаем)']['count']) ? 1 : $note['error']['У товара нет изображений (пропускаем)']['count'] + 1,
            ];

            $this->addLog($this->settings->name, $note, 'note');

            return FALSE;
        }

        if (!empty($this->shop_info->id)) {
            $product['shop_id'] = intval($this->shop_info->id);
        }

        // Если задан бренд
        if (!empty($item['brand'])) {

            $item['brand'] = trim($item['brand']);

            $template = strtolower(preg_replace('/[^\d|\w]/', '', $item['brand']));
/*
            // Найдем его по имени
            $this->db->query('SELECT id FROM __brands WHERE name=?', $item['brand']);
            */

            // Найдем его по имени
            $this->db->query('SELECT id FROM __brands WHERE template=?', $template);

            if (!$product['brand_id'] = $this->db->result('id')) {

                // Создадим, если не найден
                $product['brand_id'] = $this->brands->add_brand([
                    'name' => $item['brand'],
                    'meta_title' => $item['brand'],
                    'meta_keywords' => $item['brand'],
                    'meta_description' => $item['brand'],
                    'template' => $template,
                ]);
            }
        }

        // Если задана категория
        $category_id = null;
        $categories_ids = [];
        if (isset($item['category'])) {

//            if (is_numeric($item['category'])) {

                $category_id = $item['category'];
                $categories_ids[] = $category_id;
//            } else {
//                /*Проблема с запятой*/
//                /*foreach (explode($this->category_delimiter, $item['category']) as $c)*/
//                    $categories_ids[] = $this->import_category($item['category']);
//                $category_id = reset($categories_ids);
//            }


//            echo $category_id; exit;
        }

        // Подготовим вариант товара
        $variant = [];

        if (isset($item['variant'])) {

            $variant['name'] = trim($item['variant']);
        }

        if (isset($item['price'])) {

            $variant['price'] = str_replace(',', '.', trim($item['price']));
        }

        if (isset($item['currencyId']) && isset($variant['price'])) {

            $variant['price'] = $this->money->reconvert($variant['price'], $this->define_currency($item['currencyId']));
        }

        if (isset($item['compare_price'])) {

            $variant['compare_price'] = trim($item['compare_price']);
        }

        if (isset($item['currencyId']) && isset($variant['compare_price'])) {

            $variant['compare_price'] = $this->money->reconvert($variant['compare_price'], $this->define_currency($item['currencyId']));
        }

        // Считаем скидку и отмечаем товар как "распродажа"
        if (!empty($item['compare_price']) && !empty($item['currencyId'])) {

            $variant['discount_percent'] = (1 - $variant['price'] / $variant['compare_price']) * 100;
            $variant['is_sale'] = 1;
        }


        if (isset($item['stock']))
            if ($item['stock'] == '')
                $variant['stock'] = null;
            else
                $variant['stock'] = trim($item['stock']);

        if (isset($item['sku']))
            $variant['sku'] = trim($item['sku']);

        // Если задан артикул варианта, найдем этот вариант и соответствующий товар
        /*
        if (!empty($variant['sku'])) {
            $this->db->query('SELECT id as variant_id, product_id FROM __variants WHERE sku=? LIMIT 1', $variant['sku']);
            $result = $this->db->result();
            if ($result) {
                // и обновим товар
                if (!empty($product)) {

                    if (!empty($item['color'])) {
                        $product_color = $this->get_color($item['color']);

                        if (!$this->products->get_product_color($result->product_id, $product_color)) {

                            $this->add_product_color($result->product_id, $product_color);
                        }
                    }

                    if (!empty($item['size'])) {
                        $product_size = $this->get_size($item['size']);

                        if (!$this->products->get_product_size($result->product_id, $product_size)) {

                            $this->add_product_size($result->product_id, $product_size);
                        }
                    }

                    unset($product['url']);
                    $this->products->update_product($result->product_id, $product);
                }

                // и вариант
                if (!empty($variant)) {

                    $this->variants->update_variant($result->variant_id, $variant);
                }

                $product_id = $result->product_id;
                $variant_id = $result->variant_id;
                // Обновлен
                $imported_item->status = 'updated';
            }
        }
        */

        // Если на прошлом шаге товар не нашелся, и задано хотя бы название товара
        if ((empty($product_id) || empty($variant_id)) && isset($item['name'])) {

            $shop_id_filter = '';
            if (!empty($product['shop_id'])) {

                $shop_id_filter = $this->db->placehold('AND p.shop_id =?', (int)($product['shop_id']));
            }

            $brand_id_filter = '';
            if (!empty($product['brand_id'])) {

                $brand_id_filter = $this->db->placehold('AND p.brand_id =?', (int)($product['brand_id']));
            }

            $hash_filter = '';
            if (!empty($item['images'][0])) {

                $hash_filter = $this->db->placehold('AND p.hash =?', md5($item['images'][0]));
            }

            if (!empty($variant['sku']) && empty($variant['name'])) {

//                $this->db->query("SELECT v.id as variant_id, p.id as product_id
//                                        FROM __products p LEFT JOIN __variants v ON v.product_id=p.id
//                                        WHERE v.sku=? and p.resource=? $shop_id_filter $brand_id_filter LIMIT 1", $variant['sku'], $product['resource']);

                $this->db->query("SELECT v.id as variant_id, p.id as product_id
                                        FROM __products p LEFT JOIN __variants v ON v.product_id=p.id
                                        WHERE v.sku=? AND p.body=? $shop_id_filter $brand_id_filter LIMIT 1", $variant['sku'], $product['body']);

            } elseif (isset($item['variant'])) {

//                $this->db->query("SELECT v.id as variant_id, p.id as product_id
//                                        FROM __products p LEFT JOIN __variants v ON v.product_id=p.id AND v.name=?
//                                        WHERE p.name=? and p.resource=? $shop_id_filter $brand_id_filter LIMIT 1", $item['variant'], $item['name'], $product['resource']);

                $this->db->query("SELECT v.id as variant_id, p.id as product_id
                                        FROM __products p LEFT JOIN __variants v ON v.product_id=p.id AND v.name=?
                                        WHERE p.name=? AND p.body=? $shop_id_filter $brand_id_filter LIMIT 1", $item['variant'], $item['name'], $product['body']);

            } else {

//                $this->db->query("SELECT v.id as variant_id, p.id as product_id
//                                        FROM __products p LEFT JOIN __variants v ON v.product_id=p.id
//                                        WHERE p.name=? and p.resource=? $shop_id_filter $brand_id_filter LIMIT 1", $item['name'], $product['resource']);

                $this->db->query("SELECT v.id as variant_id, p.id as product_id
                                        FROM __products p LEFT JOIN __variants v ON v.product_id=p.id
                                        WHERE p.name=? AND p.body=? $shop_id_filter $brand_id_filter LIMIT 1", $item['name'], $product['body']);

            }

            $r = $this->db->result();
            if ($r) {
                $product_id = $r->product_id;
                $variant_id = $r->variant_id;
            }

            // Если вариант найден - обновляем,
            if (!empty($variant_id)) {
                $this->variants->update_variant($variant_id, $variant);

                if (!empty($item['color'])) {

                    if (is_array($item['color'])) {

                        $colors = $item['color'];
                    } else {

                        $colors = explode(',', $item['color']);
                    }

                    foreach ($colors as $color) {

                        $product_color = $this->get_color(trim($color));

                        if (!$this->products->get_product_color($product_id, $product_color)) {

                            $this->add_product_color($product_id, $product_color);
                        }
                    }
                    unset($product_color);
                }

                if (!empty($item['size'])) {

                    if (is_array($item['size'])) {

                        $sizes = $item['size'];
                    } else {

                        $sizes = explode(',', $item['size']);
                    }

                    foreach ($sizes as $size) {

                        $product_size = $this->get_size(trim($size));

                        if (!$this->products->get_product_size($product_id, $product_size)) {

                            $this->add_product_size($product_id, $product_size);
                        }
                    }
                    unset($product_size);
                }

//                echo '<pre>';
//                print_r($item);
//                echo '</pre>';

                unset($product['url']); // перезаписывание url ломает выборку товаров
                $this->products->update_product($product_id, $product);

                $imported_item->status = 'updated';

                // Запись в лог
                $note = $this->getLog($this->settings->name)['note'];

                $note['msg']['Обновляем товар'] = [
                    'count' => empty($note['msg']['Обновляем товар']['count']) ? 1 : $note['msg']['Обновляем товар']['count'] + 1,
                ];

                $this->addLog($this->settings->name, $note, 'note');

                // по категориям
                $updated = isset($this->getLog($this->settings->name)['updated']) ? $this->getLog($this->settings->name)['updated'] : [];

                $updated[$category_id] = empty($updated[$category_id]) ? 1 : $updated[$category_id] + 1;

                $this->addLog($this->settings->name, $updated, 'updated');

            } // Иначе - добавляем
            elseif (empty($variant_id)) {

                if (empty($product_id)) {

                    $product_id = $this->products->add_product($product);
                }

                $this->db->query('SELECT max(v.position) as pos FROM __variants v WHERE v.product_id=? LIMIT 1', $product_id);
                $pos = $this->db->result('pos');

                $variant['position'] = $pos + 1;
                $variant['product_id'] = $product_id;
                $variant_id = $this->variants->add_variant($variant);
                $imported_item->status = 'added';

                // Запись в лог
                $note = $this->getLog($this->settings->name)['note'];

                $note['msg']['Добавляем товар'] = [
                    'count' => empty($note['msg']['Добавляем товар']['count']) ? 1 : $note['msg']['Добавляем товар']['count'] + 1,
                ];

                $this->addLog($this->settings->name, $note, 'note');

                // по категориям
                $new = isset($this->getLog($this->settings->name)['new']) ? $this->getLog($this->settings->name)['new'] : [];

                $new[$category_id] = empty($new[$category_id]) ? 1 : $new[$category_id] + 1;

                $this->addLog($this->settings->name, $new, 'new');
            }
        }

        // Если новый товар, то добавим для него цвет и размер
        if (!empty($imported_item->status) && $imported_item->status == 'added') {

            $query = $this->db->placehold("DELETE FROM __products_colors WHERE product_id=?", intval($product_id));
            $this->db->query($query);

            $query = $this->db->placehold("DELETE FROM __products_sizes WHERE product_id=?", intval($product_id));
            $this->db->query($query);

            // Цвет товара
            if (!empty($item['color'])) {

                $this->add_product_colors($item['color'], $product_id);
            }

            // Размер товара
            if (!empty($item['size'])) {

                $this->add_product_sizes($item['size'], $product_id);
            }
        }

        if (!empty($variant_id) && !empty($product_id)) {

            // Нужно вернуть обновленный товар
            $imported_item->variant = $this->variants->get_variant(intval($variant_id));
            $imported_item->product = $this->products->get_product(intval($product_id));

            // Добавляем категории к товару
            if (!empty($categories_ids)) {

                foreach ($categories_ids as $c_id) {

                    $this->categories->add_product_category($product_id, $c_id);
                }
            }

            // Изображения товаров
            if (isset($item['images'])) {

                // Изображений может быть несколько, через запятую
                if (!is_array($item['images'])) {
                    $images = explode(',', $item['images']);
                } else {
                    $images = $item['images'];
                }

                foreach ($images as $image) {

                    $image = trim(current(explode('?', $image)));

                    if (!empty($image)) {
                        // Имя файла
                        $image_filename = pathinfo($image, PATHINFO_BASENAME);

                        // Добавляем изображение только если такого еще нет в этом товаре
                        $this->db->query('SELECT filename FROM __images WHERE product_id=? AND (filename=? OR filename=? OR base_filename=?) LIMIT 1', $product_id, $image_filename, $image, $image_filename);
                        if (!$this->db->result('filename')) {

                            $this->products->add_image($product_id, $image);
//                            $this->image->download_image($image);
                        }
                    }
                }
            }

            // Характеристики товаров
            foreach ($item as $feature_name => $feature_value) {
                // Если нет такого названия колонки, значит это название свойства
                if (!in_array($feature_name, $this->internal_columns_names)) {
                    // Свойство добавляем только если для товара указана категория
                    if ($category_id) {
                        $this->db->query('SELECT f.id FROM __features f WHERE f.name=? LIMIT 1', $feature_name);
                        if (!$feature_id = $this->db->result('id'))
                            $feature_id = $this->features->add_feature(['name' => $feature_name]);

                        $this->features->add_feature_category($feature_id, $category_id);

                        if (!is_array($feature_value)) {

                            $this->features->update_option($product_id, $feature_id, $feature_value);
                        }
                    }

                }
            }
            return $imported_item;
        }
    }


    // Отдельная функция для импорта категории
    public function import_category($category)
    {

//        ob_start();
//        print_r($category);
//        $content = ob_get_contents();
//        file_put_contents('content.txt', "\n" . $content . "\n", FILE_APPEND);
//        exit;


        // Поле "категория" может состоять из нескольких имен, разделенных subcategory_delimiter-ом
        // Только неэкранированный subcategory_delimiter может разделять категории
        $delimiter = $this->subcategory_delimiter;
        $regex = "/\\DELIMITER((?:[^\\\\\DELIMITER]|\\\\.)*)/";
        $regex = str_replace('DELIMITER', $delimiter, $regex);
        $names = preg_split($regex, $category, 0, PREG_SPLIT_DELIM_CAPTURE);

        $id = null;
        $parent = 0;

//        $is_parent = false;
        $this->db->query('SELECT id FROM __categories WHERE name in(?@) AND parent_id=?', $names, $parent);
        $ids = $this->db->result('id');

//        if (!empty($ids)) {
//            echo "\n";
//            print_r($ids);
//            exit;
//        }

        if (!empty($ids)) {

            $is_parent = true;
        }

        // Для каждой категории
        foreach ($names as $name) {

            // Заменяем \/ на /
            $name = trim(str_replace("\\$delimiter", $delimiter, $name));

            if (!empty($name)) {

                // Найдем категорию по имени
                $this->db->query('SELECT id FROM __categories WHERE name=? AND parent_id=?', $name, $parent);
                $id = $this->db->result('id');

//                if ($parent == 0 && empty($id)) {
//                    continue;
//                }

                // Если не найдена - добавим ее
                if (empty($id)) {

                    $id = $this->categories->add_category([
                        'name' => $name,
                        'parent_id' => $parent,
                        'meta_title' => $name,
                        'meta_keywords' => $name,
                        'meta_description' => $name,
                        'url' => $this->translit($name),
                    ]);
                }

                if (!empty($id)) {
                    $parent = $id;
                }
            }
        }
        return $id;
    }

    public function add_product_colors($colors, $product_id)
    {
        if (!is_array($colors)) {
            $colors = explode(',', $colors);
        }

        foreach ($colors as $color) {

            $color_id = $this->get_color($color);

            if (!$color_id) {

                continue;
            }

            $this->add_product_color($product_id, $color_id);
        }
    }

    public function add_product_sizes($sizes, $product_id)
    {
        if (!is_array($sizes)) {
            $sizes = explode(',', $sizes);
        }

        foreach ($sizes as $size) {

            $size_id = $this->get_size($size);

            if (!$size_id) {

                continue;
            }

            $this->add_product_size($product_id, $size_id);
        }
    }

    public function add_product_size($product_id, $size_id) {

        $this->db->query('SELECT product_id FROM __products_sizes WHERE size_id=? AND product_id=? LIMIT 1', $size_id , $product_id);
        $result = $this->db->result();

        if (empty($result)) {

            $this->products->add_product_size($product_id, $size_id);
        }
    }

    public function get_size($size) {

        $size = trim($size);

        if (empty($size)) {
            return false;
        }

        $this->db->query('SELECT id FROM __sizes WHERE name=? LIMIT 1', strtolower($size));
        $size_id = $this->db->result('id');

        if (empty($size_id)) {

            $size_id = $this->sizes->add_size([
                'name' => $size,
                'type' => 'height',
            ]);
        }

        return $size_id;
    }

    public function get_color($color) {

        $color = trim($color);

        if (empty($color)) {
            return false;
        }

        $this->db->query('SELECT id FROM __colors WHERE color_key=? LIMIT 1', strtolower($color));
        $color_id = $this->db->result('id');

        if (empty($color_id)) {

            // Пытаемся парсить цвет по названию
            $new_color = $this->parse_color(strtolower($color));

            $code = '';
            if (!empty($new_color->hex)) {

                $code = str_replace('#', '', $new_color->hex);
            }

            $color_id = $this->colors->add_color([
                'color_key' => strtolower($color),
                'name'      => $color,
                'code'      => $code,
            ]);
        }

        return $color_id;
    }

    public function add_product_color($product_id, $color_id) {

        $this->db->query('SELECT product_id FROM __products_colors WHERE color_id=? AND product_id=? LIMIT 1', $color_id, $product_id);
        $result = $this->db->result();

        if (empty($result)) {

            $this->products->add_product_color($product_id, $color_id);
        }
    }

    public function translit($text)
    {
        $ru = explode('-', "А-а-Б-б-В-в-Ґ-ґ-Г-г-Д-д-Е-е-Ё-ё-Є-є-Ж-ж-З-з-И-и-І-і-Ї-ї-Й-й-К-к-Л-л-М-м-Н-н-О-о-П-п-Р-р-С-с-Т-т-У-у-Ф-ф-Х-х-Ц-ц-Ч-ч-Ш-ш-Щ-щ-Ъ-ъ-Ы-ы-Ь-ь-Э-э-Ю-ю-Я-я");
        $en = explode('-', "A-a-B-b-V-v-G-g-G-g-D-d-E-e-E-e-E-e-ZH-zh-Z-z-I-i-I-i-I-i-J-j-K-k-L-l-M-m-N-n-O-o-P-p-R-r-S-s-T-t-U-u-F-f-H-h-TS-ts-CH-ch-SH-sh-SCH-sch---Y-y---E-e-YU-yu-YA-ya");

        $res = str_replace($ru, $en, $text);
        $res = preg_replace("/[\s]+/ui", '-', $res);
        $res = preg_replace('/[^\p{L}\p{Nd}\d-]/ui', '', $res);
        $res = strtolower($res);
        return $res;
    }

    // Фозвращает внутреннее название колонки по названию колонки в файле
    public function internal_column_name($name)
    {
        $name = trim($name);
        $name = str_replace('/', '', $name);
        $name = str_replace('\/', '', $name);
        foreach ($this->columns_names as $i => $names) {
            foreach ($names as $n)
                if (!empty($name) && preg_match("/^" . preg_quote($name) . "$/ui", $n))
                    return $i;
        }
        return FALSE;
    }

    // Определяет валюту
    public function define_currency($currency)
    {

        foreach ($this->currency as $k => $v) {
            if (in_array($currency, $v)) {
                return $k;
            }
        }

        $currency = current($this->money->get_currencies(['enabled' => 1]));

        return $currency->code;
    }


    // Парсер цветов
    public function parse_color($name)
    {

        $res = file_get_contents('http://colorscheme.ru/color-names.json?search=' . urlencode($name));

        if ($res = json_decode($res)) {
            foreach ($res as $color) {

                if ($name == strtolower($color->name)) {
                    return $color;
                }
            }

            return $res[0];
        }
        return false;
    }

    // Загрузка файла на сервер
    public function download_file($url, $file_name)
    {
        try {

            $fp = fopen($file_name, 'w+');
            $ch = curl_init($url);
            curl_setopt($ch, CURLOPT_TIMEOUT, 75);
            curl_setopt($ch, CURLOPT_FILE, $fp);
            curl_exec($ch);
            curl_close($ch);
            fclose($fp);
        } catch (\Exception $e) {

            echo $e->getMessage(), "\n";

            return FALSE;
        }

        return TRUE;
    }

    /**
     * Copy remote file over HTTP one small chunk at a time.
     *
     * @param $infile The full URL to the remote file
     * @param $outfile The path where to save the file
     */
    public function copyfile_chunked($infile, $outfile) {
        $chunksize = 10 * (1024 * 1024); // 10 Megs

        /**
         * parse_url breaks a part a URL into it's parts, i.e. host, path,
         * query string, etc.
         */
        $parts = parse_url($infile);
        $i_handle = fsockopen($parts['host'], 80, $errstr, $errcode, 5);
        $o_handle = fopen($outfile, 'wb');

        if ($i_handle == false || $o_handle == false) {
            return false;
        }

        if (!empty($parts['query'])) {
            $parts['path'] .= '?' . $parts['query'];
        }

        /**
         * Send the request to the server for the file
         */
        $request = "GET {$parts['path']} HTTP/1.1\r\n";
        $request .= "Host: {$parts['host']}\r\n";
        $request .= "User-Agent: Mozilla/5.0\r\n";
        $request .= "Keep-Alive: 115\r\n";
        $request .= "Connection: keep-alive\r\n\r\n";
        fwrite($i_handle, $request);

        /**
         * Now read the headers from the remote server. We'll need
         * to get the content length.
         */
        $headers = [];
        while(!feof($i_handle)) {
            $line = fgets($i_handle);
            if ($line == "\r\n") break;
            $headers[] = $line;
        }

        /**
         * Look for the Content-Length header, and get the size
         * of the remote file.
         */
        $length = 0;
        foreach($headers as $header) {
            if (stripos($header, 'Content-Length:') === 0) {
                $length = (int)str_replace('Content-Length: ', '', $header);
                break;
            }
        }

        /**
         * Start reading in the remote file, and writing it to the
         * local file one chunk at a time.
         */
        $cnt = 0;
        while(!feof($i_handle)) {
            $buf = '';
            $buf = fread($i_handle, $chunksize);
            $bytes = fwrite($o_handle, $buf);
            if ($bytes == false) {
                return false;
            }
            $cnt += $bytes;

            /**
             * We're done reading when we've reached the conent length
             */
            if ($cnt >= $length) break;
        }

        fclose($i_handle);
        fclose($o_handle);
        return $cnt;
    }
}
