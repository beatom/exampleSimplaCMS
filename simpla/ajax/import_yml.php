<?php

require_once('import_xml.php');

class ImportYmlAjax extends ImportXmlAjax
{
    // Соответствие полей в базе и имён колонок в файле
    public $columns_names = [
        'name'             => ['product', 'name', 'товар', 'название', 'наименование'],
        'url'              => ['url', 'адрес'],
        'visible' => [
            'visible',
            'published',
            'видим',
            'available',
        ],
        'featured'         => ['featured', 'hit', 'хит', 'рекомендуемый'],
        'category'         => ['category', 'категория'],
        'brand'            => ['brand', 'бренд', 'vendor'],
        'variant'          => ['variant', 'вариант'],
        'price'            => ['price', 'цена'],
        'compare_price'    => ['compare price', 'старая цена'],
        'sku'              => ['sku', 'артикул'],
        'stock'            => ['stock', 'склад', 'на складе'],
        'meta_title'       => ['meta title', 'заголовок страницы'],
        'meta_keywords'    => ['meta keywords', 'ключевые слова'],
        'meta_description' => ['meta description', 'описание страницы'],
        'annotation'       => ['annotation', 'аннотация', 'краткое описание'],
        'description'      => ['description', 'описание'],
        'images'           => ['images', 'picture', 'изображения'],
        'color'            => ['color', 'цвет'],
        'size'             => ['size', 'размер'],
    ];

    public $pre_row = [];

    public function category_build($item)
    {

        if (!empty($item['parent_id']) && !empty($this->pre_row[$item['parent_id']])) {
            $item = [
                'name'      => $this->pre_row[$item['parent_id']]['name'] . '/' . $item['name'],
                'parent_id' => $this->pre_row[$item['parent_id']]['parent_id'],
            ];

            return $this->category_build($item);
        }

        return $item;
    }

    public function getCategories($categories)
    {

        if (empty($categories->category)) {
            return;
        }

        $this->pre_row = [];
        $this->categories_row = [];

        foreach ($categories->category as $category) {
            /** @var SimpleXMLElement $category */
            $attributes = $category->attributes();

            $category = (string)$category;
            $category = isset($this->categories_names[$category]) ? $this->categories_names[$category] : $category;

            $this->pre_row[(string)$attributes['id']] = [
                'name'      => $category,
                'parent_id' => !empty($attributes['parentId']) ? (string)$attributes['parentId'] : 0,
            ];
        }

        // Чистим память
        unset($categories->category);

        foreach ($this->pre_row as $k => $v) {

            $this->categories_row[$k] = $this->category_build($v)['name'];
        }

        asort($this->categories_row);

//        echo "\n";
//        ob_start();
//        print_r($this->categories_row);
//        $content = ob_get_contents();
//        file_put_contents('content.txt', $content);
//        exit;

        return $this->categories_row;
    }

    public function getItems($offers)
    {

        $this->addLog($this->settings->name, 'Разбор товаров');

        if (empty($offers->offer)) {

            $this->addLog($this->settings->name, 'Список товаров пуст');

            return;
        }

        $this->items = [];

        $note = [];

        foreach ($offers->offer as $offer) {
            /** @var SimpleXMLElement $offer */
            $item = (array)$offer->children();

            if (! empty($item['attrs'])) {

                $item = array_merge($item, (array) $offer->attrs);
            }

//            echo '<pre>';
//            print_r($this->settings->category[$item['categoryId']][$item['gender']]);
//            exit;

            if (! empty($item['categoryId'])) {
                
                if (!empty($item['gender']) && !empty($this->settings->category[$item['categoryId']][$item['gender']])) {


                    $item['category'] = $this->settings->category[$item['categoryId']][$item['gender']];

//                    echo '<pre>';
//                    print_r($item);
//                    exit;

                    unset ($item['categoryId']);
                }

                elseif (empty($item['gender']) && !empty($this->settings->category[$item['categoryId']])
//                    && $this->settings->category[$item['categoryId']] > 0
                ) {

                    $item['category'] = $this->settings->category[$item['categoryId']];

                    unset ($item['categoryId']);
                } else {

                    $note['msg']['Не указано соответствие категории товара (пропускаем)'] = [
                        'count' => empty($note['msg']['Не указано соответствие категории товара (пропускаем)']['count']) ? 1 : $note['msg']['Не указано соответствие категории товара (пропускаем)']['count'] + 1,
                    ];

                    continue;
                }

            } else {

                $note['error']['Не указана категория товара (пропускаем)'] = [
                    'count' => empty($note['error']['Не указана категория товара (пропускаем)']['count']) ? 1 : $note['error']['Не указана категория товара (пропускаем)']['count'] + 1,
                ];

                continue;
            }

            // Для аттрибутов в теге param
            if (! empty($item['param'])) {

                if (is_array($item['param'])) {
                    foreach ($offer->param as $param) {

                        $item[(string)$param->attributes()->name] = (string) $param;
                    }
                }
            }

            $attributes = $offer->attributes();

            if (! empty($attributes)) {

                foreach ($attributes as $k => $v) {
                    $item[(string)$k] = (string)$v;
                }
            }

//            echo '<pre>';
//            print_r($item);
//            exit;

            $this->items[] = $item;
        }

        $this->addLog($this->settings->name, $note, 'note');

        return $this->items;
    }

    public function setShop()
    {

        $this->shop_info = new stdClass;

        $this->shop_info->name = !empty($this->xml->shop->name) ? (string)$this->xml->shop->name : '';
        $this->shop_info->company = !empty($this->xml->shop->company) ? (string)$this->xml->shop->company : '';
        $this->shop_info->url = !empty($this->xml->shop->url) ? (string)$this->xml->shop->url : '';
        $this->shop_info->email = !empty($this->xml->shop->email) ? (string)$this->xml->shop->email : '';

        if ($this->shop_info->name && $shop = $this->shop->get_shop_by_name($this->shop_info->name)) {

            $this->shop_info->id = $this->shop->update_shop($shop->id, $this->shop_info);
        } elseif ($this->shop_info->name) {

            $this->shop_info->id = $this->shop->add_shop($this->shop_info);
        }

        return $this;
    }

    public function getShop($id = 0)
    {
        if ($id > 0) {

            $this->shop_info = $this->shop->get_shop((int)$id);
        }

        return $this->shop_info;
    }

    public function import_cron()
    {
        try {

            if (! $this->managers->access('import')) {
                return FALSE;
            }

            // Для корректной работы установим локаль UTF-8
            setlocale(LC_ALL, 'ru_RU.UTF-8');

            $all_hash = '';

            foreach (explode("\n", trim($this->settings->url)) as $url) {

                $url = trim($url);

                $this->addLog($this->settings->name, 'Ссылка для парсинга ' . $url);

//                $now_hash = md5_file($url);

                if (! empty($now_hash)) {
                    $all_hash .= $now_hash;
                }

                if ((! empty($this->settings->hash) && ! empty($now_hash) && strpos($this->settings->hash, $now_hash) !== FALSE)
//                    || empty($now_hash)
                ) {

                    continue;
                }

//                /* Если сначала скачивать прайс */
//                $file_name = 'temp.xml';
//                $content   = FALSE;
//                if ($this->copyfile_chunked($url, $file_name)) {
//
//                    $content = file_get_contents($file_name);
//
//                    /*unlink($file_name);*/
//                }

                $content = file_get_contents($url);

                if (!$content) {

                    $this->addLog($this->settings->name, 'Не удалось получить контент по ссылке ' . $url);

                    continue;
                }

                $this->xml = new SimpleXMLElement($content);

                // Чистим память
                unset($content);

                if (empty($this->xml)) {

                    $this->addLog($this->settings->name, 'Не удалось распарсить документ');

                    continue;
                }

                if (empty($this->settings->shop_id)) {

                    $this->setShop();

                } else {

                    $this->getShop($this->settings->shop_id);
                }

                $this->import->disable_products_by_shop_id($this->shop_info->id);

                $categories = $this->getCategories($this->xml->shop->categories);

                $this->import->update_settings($this->settings->name, [
                    'price_category' => serialize($categories),
                ]);

                $this->addLog($this->settings->name, 'Считаем количество товаров в категориях');

                // Считаем количество товаров в категории
                $counts = [];
                $genders = [];
                if (!empty($this->xml->shop->offers->offer)) {

                    foreach ($this->xml->shop->offers->offer as $offer) {

                        $cat_id = (string)$offer->categoryId;

                        $gender = (string)$offer->attrs->gender;

                        if (!empty($gender)) {

                            if (isset($genders[$cat_id]) && !in_array($gender, $genders[$cat_id])) {

                                $genders[$cat_id][] = $gender;
                            } else {

                                $genders[$cat_id][0] = $gender;
                            }

                            if (empty($counts[$cat_id][$gender])) {

                                $counts[$cat_id][$gender] = 1;
                            } else {

                                $counts[$cat_id][$gender] ++;
                            }

                        } else {

                            if (empty($counts[$cat_id])) {

                                $counts[$cat_id] = 1;
                            } else {

                                $counts[$cat_id] ++;
                            }
                        }
                    }
                }

                foreach($genders as &$gender) {

                    $gender = array_unique($gender);
                }

//                echo '<pre>';
//                print_r($genders);
//                echo '</pre>';
//                exit;

                $this->import->update_settings($this->settings->name, [
                    'price_count' => serialize($counts),
                    'genders' => serialize($genders),
                ]);

                // Если парсим прайс впервый раз (т.е. пустой список категорий)
                if (empty($this->settings->price_category)) {

                    $this->addLog($this->settings->name, 'Т.к. пустой список категорий - обновляем список категорий, и останавливаем процесс');

                    continue;
                }

                $items = $this->getItems($this->xml->shop->offers);

                // Массив импортированных товаров
                $imported_items = [];

                // Чистим память
                $this->xml = null;

                $this->addLog($this->settings->name, 'Начинаем добавление товаров');

                // Проходимся по строкам
                foreach ($items as $k => &$v) {

                    $product = null;

                    if (! is_array($v)) {
                        continue;
                    }

                    $product = $v;

                    $this->columns = array_keys($v);

                    // Чистим память
                    unset($v);

                    // Заменяем имена колонок из файла на внутренние имена колонок
                    foreach ($this->columns as &$column) {

                        if ($internal_name = $this->internal_column_name($column)) {

                            $this->internal_columns_names[$column] = $internal_name;
                            $column                                = $internal_name;
                        }
                    }

                    // Если нет названия товара - не будем импортировать
                    if (! in_array('name', $this->columns)
                        && ! in_array('sku', $this->columns)
                    ) {

                        continue;
                    }

                    foreach ($product as $k => $v) {

                        if (isset($this->internal_columns_names[$k]) && $k != $this->internal_columns_names[$k]) {

                            $product[$this->internal_columns_names[$k]] = $v;
                            unset($product[$k]);
                        }
                    }

                    $cat_id = $product['category'];

                    if (empty($counts[$cat_id])) {

                        $counts[$cat_id] = 1;
                    } else {

                        $counts[$cat_id] ++;
                    }

                    // Импортируем этот товар
                    if ($imported_item = $this->import_item($product)) {

                        $imported_items[] = $imported_item;
                    }

                    // Чистим память
                    unset($product);
                }

//                echo '<pre>';
//                print_r($counts);
//                exit;

                $this->import->update_settings($this->settings->name, [
                    'price_count' => serialize($counts),
                ]);
            }

            $this->import->update_settings($this->settings->name, [
                'hash' => $all_hash,
            ]);

        } catch (\Exception $e) {

            echo $e->getMessage(), "\n";

            return FALSE;
        }

        return TRUE;
    }

}

// Если Ajax запрос
if (isset($_SERVER['HTTP_X_REQUESTED_WITH'])
    && ! empty($_SERVER['HTTP_X_REQUESTED_WITH'])
    && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest'
) {

    set_time_limit(0);
    ini_set("memory_limit","512M");

    $import_ajax = new ImportYmlAjax();
    header("Content-type: application/json; charset=UTF-8");
    header("Cache-Control: must-revalidate");
    header("Pragma: no-cache");
    header("Expires: -1");

    $json = json_encode($import_ajax->import());
    print $json;

}



