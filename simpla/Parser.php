<?php

require_once('api/Simpla.php');

class Parser extends Simpla
{
    // Соответствие полей в базе и имён колонок в файле
    public $columns_names = [
        'name'             => ['typePrefix', 'product', 'name', 'model', 'товар', 'название', 'наименование'],
        'url'              => ['url', 'адрес'],
        'visible'          => [
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
        'sku'              => ['sku', 'артикул', 'vendorCode'],
        'stock'            => ['stock', 'склад', 'на складе'],
        'meta_title'       => ['meta title', 'заголовок страницы'],
        'meta_keywords'    => ['meta keywords', 'ключевые слова'],
        'meta_description' => ['meta description', 'описание страницы'],
        'annotation'       => ['annotation', 'аннотация', 'краткое описание'],
        'description'      => ['description', 'описание'],
        'images'           => ['images', 'picture', 'изображения'],
        'color'            => ['color', 'цвет'],
        'size'             => ['size', 'размер'],
        'gender'           => ['gender', 'пол'],
    ];

    public $price_fields = [
        'url',
        'resource',
        'brand',
        'shop_id',
        'categoryId',
        'currencyId',
        'name',
        'annotation',
        'body',
        'meta_title',
        'meta_keywords',
        'meta_description',
        'featured',
        'sku',
        'price',
        'compare_price',
        'discount_percent',
        'stock',
        'is_sale',
        'visible',
        'images',
        'color',
        'size',
        'gender',
        'body_hash',
        'hash',
        'custom_hash',
        'ver',
        'product_id',
        'variant_id',
    ];

    // Типо версии процесса (отмечаем строки, с которыми работали)
    public $ver;

    public $pre_row = [];
    public $items = [];
    public $log = [];
    public $profile;
    public $internal_columns_names;
    public $shop_info;
    public $categories_row;

    /**
     * @param mixed $ver
     */
    public function setVer($ver)
    {
        $this->ver = $ver;
    }

    /**
     * @return mixed
     */
    public function getVer()
    {
        return $this->ver;
    }

    public function getCategories($categories)
    {
        if (empty($categories->category)) {

            return;
        }

        $this->pre_row        = [];
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

        return $this->categories_row;
    }

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

    public function getItems(XMLReader $reader)
    {
        $this->addLog($this->settings->name, 'Разбор и добавление товаров');
        $this->items = [];
        $note = [];

        $counts  = [];
        $genders = [];

//        $counter = 0;

        while($reader->name === 'offer') {

//            print_r(strlen($reader->readOuterXML())); echo ' ' . (count($this->items) + 1) . ' ' . memory_get_usage() . ' ' . ++$counter . "\n";

            $offer = @simplexml_load_string(@$reader->readOuterXML());

            if ($offer === FALSE) {

                $reader->next('offer');
                continue;
            }

            /** @var SimpleXMLElement $offer */
            $item = (array)$offer->children();

            if (!empty($item['attrs'])) {

                $item = array_merge($item, (array)$offer->attrs);
            }

            if (empty($item['categoryId'])) {

                $note['error']['Не указана категория товара (пропускаем)'] = [
                    'count' => empty($note['error']['Не указана категория товара (пропускаем)']['count']) ? 1 : $note['error']['Не указана категория товара (пропускаем)']['count'] + 1,
                ];

                continue;
            }

            // Для аттрибутов в теге param
            if (!empty($item['param'])) {

                if (is_array($item['param'])) {
                    foreach ($offer->param as $param) {

                        $item[(string)$param->attributes()->name] = (string)$param;
                    }
                }
            }

            $attributes = $offer->attributes();

            if (!empty($attributes)) {

                foreach ($attributes as $k => $v) {
                    $item[(string)$k] = (string)$v;
                }
            }

            $this->items[] = $item;

            if (count($this->items) > 500) {

                $this->processItems();
                $this->items = [];
            }

            // Cчетчик товаров в категории
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

                    $counts[$cat_id][$gender]++;
                }

            } else {

                if (empty($counts[$cat_id])) {

                    $counts[$cat_id] = 1;
                } else {

                    $counts[$cat_id]++;
                }
            }

            $reader->next('offer');
        }

        if (count($this->items) > 0) {

            $this->processItems();
            $this->items = [];
        }

        foreach ($genders as &$gender) {

            $gender = array_unique($gender);
        }

        $this->import->update_settings($this->settings->name, [
            'price_count' => serialize($counts),
            'genders'     => serialize($genders),
        ]);

        $this->addLog($this->settings->name, $note, 'note');

        return TRUE;
    }

    public function setShop()
    {

        $this->shop_info = new stdClass;

        $this->shop_info->name    = !empty($this->xml->shop->name) ? (string)$this->xml->shop->name : '';
        $this->shop_info->company = !empty($this->xml->shop->company) ? (string)$this->xml->shop->company : '';
        $this->shop_info->url     = !empty($this->xml->shop->url) ? (string)$this->xml->shop->url : '';
        $this->shop_info->email   = !empty($this->xml->shop->email) ? (string)$this->xml->shop->email : '';

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

    public function parse_cron()
    {
        try {

            // Для корректной работы установим локаль UTF-8
            setlocale(LC_ALL, 'ru_RU.UTF-8');

            $all_hash = '';

            foreach (explode("\n", trim($this->settings->url)) as $url) {

                $url = trim($url);

                if (empty($this->settings->shop_id)) {

                    $this->addLog($this->settings->name, 'Не указан магазин для прайса');

                    continue;
                }

                $url_headers = @get_headers($url);

                if ($url_headers[0] == 'HTTP/1.1 404 NOT FOUND') {

                    $this->addLog($this->settings->name, 'Ссылка для парсинга ' . $url . ' не правильная');

                    continue;
                }

                $this->getShop($this->settings->shop_id);

                $this->addLog($this->settings->name, 'Ссылка для парсинга ' . $url);

                $reader = new XMLReader();

                $reader->open($url);
                while ($reader->read()) {
                    switch ($reader->nodeType) {
                        case (XMLReader::ELEMENT):

                            if ($reader->name === 'categories') {

                                $xml_categories = new SimpleXMLElement($reader->readOuterXML());

                                $this->parseCategories($xml_categories);
                            }

                            if ($reader->name === 'offer') {

                                $items = $this->getItems($reader);
                            }

                        break;
                    }
                }

                // Отмечаем строки, которых нет в прайсе как удаленные
                if (!empty($items)) {

                    $query = $this->db->placehold("UPDATE __price SET status='deleted', product_status='updated' WHERE shop_id=? AND ver!=? AND status!='deleted'", $this->settings->shop_id, $this->ver);
                    $this->db->query($query);
                }
            }

        } catch (\Exception $e) {

            echo $e->getMessage() . " (" . $e->getFile() . ", " . $e->getLine() . ")", "\n";

            return FALSE;
        }

        return TRUE;
    }

    // Импорт товаров
    public function import_items($items)
    {
        /*
         *
DROP TABLE IF EXISTS `s_price`;
CREATE TABLE `s_price` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `url` varchar(255) NOT NULL DEFAULT '',
  `resource` varchar(255) NOT NULL DEFAULT '',
  `brand` varchar(255) NOT NULL DEFAULT '',
  `categoryId` varchar(16) NOT NULL,
  `currencyId` varchar(8) NOT NULL,
  `shop_id` int(11) DEFAULT NULL,
  `name` varchar(500) NOT NULL,
  `annotation` text NOT NULL,
  `body` longtext NOT NULL,
  `meta_title` varchar(500) NOT NULL,
  `meta_keywords` varchar(500) NOT NULL,
  `meta_description` varchar(500) NOT NULL,
  `featured` tinyint(1) DEFAULT NULL,
  `sku` varchar(255) NOT NULL,
  `price` decimal(14,2) NOT NULL DEFAULT '0.00',
  `compare_price` decimal(14,2) DEFAULT NULL,
  `discount_percent` decimal(14,2) DEFAULT NULL,
  `stock` mediumint(9) DEFAULT NULL,
  `is_sale` tinyint(1) NOT NULL DEFAULT '0',
  `visible` tinyint(1) NOT NULL DEFAULT '1',
  `created` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `images` longtext NOT NULL,
  `color` longtext NOT NULL,
  `size` longtext NOT NULL,
  `gender` varchar(16) DEFAULT NULL,
  `status` enum('new','updated','deleted','unchanged') NOT NULL DEFAULT 'new',
  `product_status` enum('updated','process','unchanged','error_category','error_images') NOT NULL DEFAULT 'updated',
  `product_id` int(11) NOT NULL DEFAULT '0',
  `variant_id` int(11) NOT NULL DEFAULT '0',
  `ver` varchar(60) NOT NULL,
  `body_hash` varchar(60) NOT NULL,
  `hash` varchar(60) NOT NULL,
  `custom_hash` varchar(60) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `index_hash` (`hash`),
  UNIQUE KEY `index_custom_hash` (`custom_hash`),
  KEY `index_body_hash` (`body_hash`),
  KEY `index_name` (`name`(333)),
  KEY `index_shop_id` (`shop_id`),
  KEY `index_brand` (`brand`),
  KEY `index_categoryId` (`categoryId`),
  KEY `index_resource` (`resource`),
  KEY `index_sku` (`sku`),
  KEY `index_product_id` (`product_id`),
  KEY `index_variant_id` (`variant_id`),
  KEY `index_ver` (`ver`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;


        ALTER IGNORE TABLE `s_price` ADD UNIQUE(custom_hash);

        update `s_price`
        set
        custom_hash = MD5(CONCAT(name, body, shop_id, brand, categoryId, resource, sku));

        ALTER TABLE `s_price`
        DROP INDEX `index_hash`;

        ALTER TABLE `s_price`
        ADD UNIQUE `custom_hash_body_hash` (`custom_hash`, `body_hash`),
        ADD UNIQUE `hash_body_hash` (`hash`, `body_hash`),
        ADD INDEX `index_custom_hash` (`custom_hash`),
        ADD INDEX `index_hash` (`hash`),
        DROP INDEX `index_brand`,
        DROP INDEX `index_categoryId`,
        DROP INDEX `index_resource`,
        DROP INDEX `index_sku`,
        DROP INDEX `index_product_id`,
        DROP INDEX `index_variant_id`;

        */
        $query = $this->db->placehold("INSERT INTO __price
                                            (" . implode(',', array_keys($items[0])) . ", status, product_status)
                                            VALUES\n");
        $insert = [];
        foreach($items as $item) {

            $item['status']         = 'new';
            $item['product_status'] = 'updated';

            $insert[] = $this->db->placehold("(?@)", $item);
        }

        $query .= implode(",\n", $insert);

        $update = [];
        foreach(array_keys($items[0]) as $attr) {

            if (in_array($attr, ['status', 'product_status', 'ver'])) {

                continue;
            }

            $update[] = $this->db->placehold(
                "\n$attr = (case when hash = values(hash) AND name = values(name)
                       then $attr
                       else values($attr)
                  end)");

        }

        $update[] = $this->db->placehold("\nver = (case when hash = values(hash) AND name = values(name)
                       then values(ver)
                       else values(ver)
                  end)");

        $update[] = $this->db->placehold("\nstatus = (case when hash = values(hash) AND name = values(name)
                       then IF(status = 'new', 'new', 'unchanged')
                       else IF(status = 'new', 'new', 'updated')
                  end)");

        $update[] = $this->db->placehold("\nproduct_status = (case when hash = values(hash) AND name = values(name)
                       then product_status
                       else values(product_status)
                  end)");

        $query .= "\nON DUPLICATE KEY UPDATE\n" . implode(",\n", $update);

        $this->db->query($query);

        return $item;
    }

    // Импорт одного товара
    public function import_item($item)
    {
        $item = $this->prepare_import_item($item);

        $this->db->query('SELECT id, status FROM __price WHERE hash=? LIMIT 1', $item['hash']);

        $result = $id = $this->db->result();

        // Если не найден такой же товар
        if (empty($result)) {

            // Ищем товары, которые изменились
            $this->db->query('SELECT id, status, product_status FROM __price
                        WHERE name=?
                            AND body_hash=?
                            AND	shop_id=?
                            AND	brand=?
                            AND	categoryId=?
                            AND	resource=?
                            AND	sku=?
                        LIMIT 1', $item['name'], $item['body_hash'], $item['shop_id'], $item['brand'], $item['categoryId'], $item['resource'], $item['sku']);

            $result = $id = $this->db->result();

            if (!empty($result)) {

                // Изменяем товар
                if ($result->status != 'new') {

                    $item['status'] = 'updated';
                } else {

                    $item['status'] = 'new';
                }

                $item['product_status'] = 'updated';

                $this->db->query("UPDATE __price SET ?% WHERE id=?", $item, $result->id);

            } else {

                // Новый товар
                $item['status']         = 'new';
                $item['product_status'] = 'updated';

                $this->db->query("INSERT INTO __price SET ?%", $item);
            }
        } else {

            // Полное соответствие товара
            if ($result->status != 'new') {

                $item['status'] = 'unchanged';
            } else {

                $item['status'] = 'new';
            }

            $this->db->query("UPDATE __price SET status=?, ver=? WHERE id=?", $item['status'], $item['ver'], $result->id);
        }

        return $item;
    }

    public function prepare_import_item($item)
    {

        if (isset($item['name'])) {

            $item['name'] = trim($item['name']);
        }

        // Наличие товара
        if (isset($item['visible'])) {

            $item['visible'] = $item['visible'] == TRUE ? 1 : 0;
        } else {

            $item['visible'] = 1;
        }

        if (isset($item['meta_title'])) {

            $item['meta_title'] = trim($item['meta_title']);
        }

        if (isset($item['meta_keywords'])) {

            $item['meta_keywords'] = trim($item['meta_keywords']);
        }

        if (isset($item['meta_description'])) {

            $item['meta_description'] = trim($item['meta_description']);
        }

        if (isset($item['annotation'])) {

            $item['annotation'] = trim($item['annotation']);
        }

        if (isset($item['description'])) {

            $item['body'] = trim($item['description']);
            unset($item['description']);
        } else {

            $item['body'] = '';
        }

        if (isset($item['featured'])) {

            $item['featured'] = intval($item['featured']);
        }

        if (isset($item['url'])) {

            $item['resource'] = trim($item['url']);
        }

        if (isset($item['name'])) {

            $item['url'] = $this->translit($item['name']);
        }

        // Изображения товаров
        if (isset($item['images'])) {

            if (!is_array($item['images'])) {

                $item['images'] = trim($item['images']);

                if (empty($item['images'])) {

                    $item['images'] = '';
                } else {

                    // Изображений может быть несколько, через запятую
                    $item['images'] = explode(',', $item['images']);
                }

            } elseif (empty($item['images'])) {

                $item['images'] = '';
            }
        }

        if (!empty($this->shop_info->id)) {
            $item['shop_id'] = intval($this->shop_info->id);
        }

        if (isset($item['price'])) {

            $item['price'] = str_replace(',', '.', trim($item['price']));
        }


        if (isset($item['compare_price'])) {

            $item['compare_price'] = str_replace(',', '.', trim($item['compare_price']));
        }

        // Считаем скидку и отмечаем товар как "распродажа"
        if (!empty($item['compare_price']) && !empty($item['currencyId'])) {

            $item['discount_percent'] = (1 - $item['price'] / $item['compare_price']) * 100;
            $item['is_sale']          = 1;
        }

        if (isset($item['stock'])) {

            if ($item['stock'] == '') {

                $item['stock'] = NULL;
            } else {

                $item['stock'] = trim($item['stock']);
            }
        }

        // Артикул
        if (isset($item['sku'])) {

            $item['sku'] = trim($item['sku']);
        } else {

            $item['sku'] = '';
        }

        // Бренд
        if (isset($item['brand'])) {

            $item['brand'] = trim($item['brand']);
        } else {

            $item['brand'] = '';
        }

        if (!empty($item['color'])) {

            if (!is_array($item['color'])) {

                $item['color'] = explode(',', $item['color']);
            }

            foreach ($item['color'] as $k => $v) {

                if (is_object($v) || is_array($v)) {
                    unset($item['color'][$k]);
                }
            }

            if (empty($item['color'])) {

                $item['color'] = '';
            }
        }

        if (!empty($item['size'])) {

            if (!is_array($item['size'])) {

                $item['size'] = explode(',', $item['size']);
            }

            foreach ($item['size'] as $k => $v) {

                if (is_object($v) || is_array($v)) {
                    unset($item['size'][$k]);
                }
            }

            if (empty($item['size'])) {

                $item['size'] = '';
            }
        }

        // пол
        if (!empty($item['gender'])) {

            $item['gender'] = trim($item['gender']);
        } else {

            $item['gender'] = '';
        }

        // Удаляем лишние поля, которых нет в таблице
        foreach ($item as $key => $value) {
            if (!in_array($key, $this->price_fields)) {
                unset($item[$key]);
            }
        }

        $item['hash']        = md5(serialize($item));
        $item['custom_hash'] = md5($item['name'] . $item['body'] . $item['shop_id'] . $item['brand'] . $item['categoryId'] . $item['resource'] . $item['sku']);
        $item['body_hash']   = md5($item['body']);
        $item['ver']         = $this->getVer();

        foreach ($item as $k => $v) {

            if (is_array($v)) {
                $item[$k] = serialize($v);
            }
        }

        $res = [];
        foreach($this->price_fields as $field) {

            $res[$field] = !empty($item[$field]) ? $item[$field] : NULL;
        }

        return $res;
    }

    public function parseCategories($xml_categories)
    {
        $categories = $this->getCategories($xml_categories);

        $this->import->update_settings($this->settings->name, [
            'price_category' => serialize($categories),
        ]);

        return $this;
    }

    public function countersProcess()
    {
        // Считаем количество товаров в категории
        if (!empty($this->xml->shop->offers->offer)) {

            $counts  = [];
            $genders = [];

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

                        $counts[$cat_id][$gender]++;
                    }

                } else {

                    if (empty($counts[$cat_id])) {

                        $counts[$cat_id] = 1;
                    } else {

                        $counts[$cat_id]++;
                    }
                }
            }

            foreach ($genders as &$gender) {

                $gender = array_unique($gender);
            }

            $this->import->update_settings($this->settings->name, [
                'price_count' => serialize($counts),
                'genders'     => serialize($genders),
            ]);
        }

        return $this;
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

    /**
     * Переписать нужно
     *
     * @param array $log
     */
    public function addLog($k, $v, $kk = FALSE)
    {

        if ($kk === FALSE) {

            $this->log[$k][time()][] = $v;
        } else {

            $this->log[$k][$kk] = $v;
        }
    }

    /**
     * @return array
     */
    public function getLog($k = NULL)
    {
        if (!empty($k)) {

            return $this->log[$k];
        }

        return $this->log;
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

        return $this;
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


    public function processItems()
    {
        $items_for_import = [];

        // Проходимся по строкам
        foreach ($this->items as $k => &$v) {

            $product = NULL;

            if (!is_array($v)) {
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
            if (!in_array('name', $this->columns)
                && !in_array('sku', $this->columns)
            ) {

                continue;
            }

            foreach ($product as $k => $v) {

                if (isset($this->internal_columns_names[$k]) && $k != $this->internal_columns_names[$k]) {

                    $product[$this->internal_columns_names[$k]] = $v;
                    unset($product[$k]);
                }
            }

            $items_for_import[] = $this->prepare_import_item($product);

            // Чистим память
            unset($product);
        }

        if (count($items_for_import) > 0) {

            $this->import_items($items_for_import);

            return TRUE;
        }

        return FALSE;
    }
}


