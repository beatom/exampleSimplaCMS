<?php

require_once('api/Simpla.php');

class ProductLoader extends Simpla
{
    private $_item_process;
    private $_shop_categories = [];

    private $_product_propertis = [
        'url',
        'resource',
        'brand_id',
        'shop_id',
        'name',
        'annotation',
        'body',
        'visible',
        'is_sale',
        'position',
        'meta_title',
        'meta_keywords',
        'meta_description',
        'featured',
    ];

    private $_variant_propertis = [
        'product_id',
        'sku',
        'name',
        'price',
        'compare_price',
        'discount_percent',
        'stock',
        'position',
        'attachment',
        'external_id',
        'is_sale',
    ];

    /**
     * @return mixed
     */
    public function getItemProcess()
    {
        return $this->_item_process;
    }

    /**
     * @param mixed $item_process
     */
    public function setItemProcess($item_process)
    {
        $this->_item_process = $item_process;

        return $this;
    }

    /**
     * @param mixed $shop_categories
     */
    public function setShopCategories()
    {

        $query = $this->db->placehold("SELECT shop_id, category FROM __import_settings WHERE is_active=1");
        $this->db->query($query);
        $res = $this->db->results();

        foreach ($res as $shop) {
            $this->_shop_categories[$shop->shop_id] = unserialize($shop->category);
        }

//        print_r($this->_shop_categories[41]);
//        exit;

        return $this;
    }

    public function prepare_product_propertis($product)
    {

        $res = [];

        foreach ($product as $k => $v) {

            if (in_array($k, $this->_product_propertis)) {

                $res[$k] = $v;
            }
        }

        $res['hash'] = md5($res['body']);

        return $res;
    }

    public function prepare_variant_propertis($product)
    {

        $res = [];

        foreach ($product as $k => $v) {

            if (in_array($k, $this->_variant_propertis)) {

                $res[$k] = $v;
            }
        }

        return $res;
    }

    /**
     * @param array $data
     */
    public function updateItemProcess($data)
    {
        if (empty($this->_item_process)) {

            return $this;
        }

        $query = $this->db->placehold("UPDATE __price SET ?% WHERE id=?", $data, $this->_item_process->id);
        $this->db->query($query);

        return $this;
    }

    public function load_cron()
    {
        try {

            // Статусы, для прайс-строк товаров, которые нужно обновить
            $status = [
                'updated',
            ];

            $this->setShopCategories();

            while (TRUE) {

//                $this->db->query("SELECT * FROM (SELECT * FROM __price WHERE product_status in(?@) LIMIT 2000) t ORDER BY hash", $status);
                $this->db->query("SELECT * FROM __price WHERE product_status in(?@) ORDER BY hash LIMIT 5000", $status);

                $items = $this->db->results();

                if (!empty($items)) {

                    foreach ($items as $item) {

                        $this
                            ->setItemProcess($item)
                            ->process()
                            ->setItemProcess(NULL);
                    }
                } else {

                    break;
                }
            }

            $this->cache
                ->setPath('_cache/')
                ->delete('sql');

        } catch (\Exception $e) {

            echo $e->getMessage() . " (" . $e->getFile() . ", " . $e->getLine() . ")", "\n";

            return FALSE;
        }

        return TRUE;
    }

    public function fix_cron()
    {
        try {

            // Статусы, для прайс-строк товаров, которые нужно обновить
            $status = [
                'updated',
            ];

            $this->setShopCategories();

            while (TRUE) {

//                $this->db->query("SELECT * FROM (SELECT * FROM __price WHERE product_status in(?@) LIMIT 2000) t ORDER BY hash", $status);
                $this->db->query("SELECT * FROM __price WHERE (product_id = 0 OR variant_id = 0) AND status != 'new' LIMIT 5000");

                $items = $this->db->results();

                if (!empty($items)) {

                    foreach ($items as $item) {

                        $this
                            ->setItemProcess($item)
                            ->fix_product_id()
                            ->setItemProcess(NULL);
                    }
                } else {

                    break;
                }
            }

        } catch (\Exception $e) {

            echo $e->getMessage() . " (" . $e->getFile() . ", " . $e->getLine() . ")", "\n";

            return FALSE;
        }

        return TRUE;
    }

    public function process()
    {

        $this
            ->updateItemProcess([
                'product_status' => 'process',
            ]);

        // Если не указана соответствующая категория
        if (empty($this->_item_process->gender) && empty($this->_shop_categories[$this->_item_process->shop_id][$this->_item_process->categoryId])

            // учитываем пол
        || !empty($this->_item_process->gender) && empty($this->_shop_categories[$this->_item_process->shop_id][$this->_item_process->categoryId][$this->_item_process->gender])

        ) {

            $this
                ->updateItemProcess([
                    'status'         => 'unchanged',
                    'product_status' => 'error_category',
                ]);

            return $this;
        }

        if (empty($this->_item_process->images)) {

            $this
                ->updateItemProcess([
                    'status'         => 'unchanged',
                    'product_status' => 'error_images',
                ]);

            return $this;
        }

        if ($this->_item_process->status == 'deleted') {

            $this->deleteProduct();

            $this
                ->updateItemProcess([
                    'product_status' => 'unchanged',
                ]);

            return $this;
        }

        $updateParams = [];

        // Если новая прайс-сторока
        if ($this->_item_process->status == 'new') {

            $this->addNewProduct();

            $updateParams = [
                'product_id' => $this->_item_process->product_id,
                'variant_id' => $this->_item_process->variant_id,
            ];

            // Если обновленная прайс-строка
        } elseif ($this->_item_process->status == 'updated') {

            $this->updateProduct();
        }

        $this
            ->updateItemProcess(array_merge($updateParams, [
                'status'         => 'unchanged',
                'product_status' => 'unchanged',
            ]));

        return $this;
    }

    public function fix_product_id()
    {

        $shop_id_filter = '';
        if (!empty($this->_item_process->shop_id)) {

            $shop_id_filter = $this->db->placehold('AND p.shop_id =?', (int)($this->_item_process->shop_id));
        }

        $brand_id_filter = '';
        if (!empty($this->_item_process->brand) && $this->getBrandId()) {

            $brand_id_filter = $this->db->placehold('AND p.brand_id =?', (int)($this->_item_process->brand_id));
        }

        if (!empty($this->_item_process->sku) && empty($this->_item_process->name)) {

            $this->db->query("SELECT v.id as variant_id, p.id as product_id
                                        FROM __products p LEFT JOIN __variants v ON v.product_id=p.id
                                        WHERE v.sku=? AND p.hash=? $shop_id_filter $brand_id_filter LIMIT 1", $this->_item_process->sku, md5($this->_item_process->body));

        } elseif (!empty($this->_item_process->variant)) {

            $this->db->query("SELECT v.id as variant_id, p.id as product_id
                                        FROM __products p LEFT JOIN __variants v ON v.product_id=p.id AND v.name=?
                                        WHERE p.name=? AND p.hash=? $shop_id_filter $brand_id_filter LIMIT 1", $this->_item_process->variant, $this->_item_process->name, md5($this->_item_process->body));

        } else {

            $this->db->query("SELECT v.id as variant_id, p.id as product_id
                                        FROM __products p LEFT JOIN __variants v ON v.product_id=p.id
                                        WHERE p.name=? AND p.hash=? $shop_id_filter $brand_id_filter LIMIT 1", $this->_item_process->name, md5($this->_item_process->body));

        }

        $r = $this->db->result();

        if ($r) {

            $this->_item_process->product_id = $r->product_id;
            $this->_item_process->variant_id = $r->variant_id;

            $this
                ->updateItemProcess([
                    'product_id' => $this->_item_process->product_id,
                    'variant_id' => $this->_item_process->variant_id,
                ]);
        } else {

            $this
                ->updateItemProcess([
                    'product_id' => 1,
                    'variant_id' => 1,
                ]);
        }

        return $this;
    }

    public function addNewProduct()
    {

        if ($this->is_new_product()) {

            $this->_item_process->product_id = $this->products->add_product($this->prepare_product_propertis($this->_item_process));

            if (!empty($this->_item_process->gender)) {

                $cat_id = $this->_shop_categories[$this->_item_process->shop_id][$this->_item_process->categoryId][$this->_item_process->gender];

            } else {

                $cat_id = $this->_shop_categories[$this->_item_process->shop_id][$this->_item_process->categoryId];
            }

            $this->categories->add_product_category($this->_item_process->product_id, $cat_id);

            $variant             = $this->prepare_variant_propertis($this->_item_process);
            $variant['position'] = 1;

            $this->_item_process->variant_id = $this->variants->add_variant($variant);

            $this
                ->productImages(TRUE)
                ->productColors(TRUE)
                ->productSizes(TRUE);

        } else {

            $this->updateProduct();
        }

        return $this;
    }

    public function updateProduct()
    {
        if (!empty($this->_item_process->variant_id)) {

            $variant = $this->prepare_variant_propertis($this->_item_process);
            $this->variants->update_variant($this->_item_process->variant_id, $variant);
        }

        if (!empty($this->_item_process->product_id)) {

            $product = $this->prepare_product_propertis($this->_item_process);
            unset($product['url']); // перезаписывание url ломает выборку товаров

            $this->products->update_product($this->_item_process->product_id, $product);
        }

        $this
            ->productImages()
            ->productColors()
            ->productSizes();

        return $this;
    }

    // На самом деле делаем продукт неактивным
    public function deleteProduct()
    {
        if (!empty($this->_item_process->product_id)) {

            // Ищем строки, которые привязаны к этому же товару но есть в наличии
            $this->db->query("SELECT id FROM __price WHERE status!='deleted' AND product_id=? LIMIT 1", $this->_item_process->product_id);
            $result = $this->db->result();

            // Если не нашли, то отмечаем товар как неактивный
            if (empty($result)) {

                $query = $this->db->placehold("UPDATE __products SET visible=0 WHERE id=?", $this->_item_process->product_id);
                $this->db->query($query);
            }
        }

        return $this;
    }

    public function is_new_product()
    {

        $shop_id_filter = '';
        if (!empty($this->_item_process->shop_id)) {

            $shop_id_filter = $this->db->placehold('AND p.shop_id =?', (int)($this->_item_process->shop_id));
        }

        $brand_id_filter = '';
        if (!empty($this->_item_process->brand) && $this->getBrandId()) {

            $brand_id_filter = $this->db->placehold('AND p.brand_id =?', (int)($this->_item_process->brand_id));
        }

        if (!empty($this->_item_process->sku) && empty($this->_item_process->name)) {

            $this->db->query("SELECT v.id as variant_id, p.id as product_id
                                        FROM __products p LEFT JOIN __variants v ON v.product_id=p.id
                                        WHERE v.sku=? AND p.hash=? $shop_id_filter $brand_id_filter LIMIT 1", $this->_item_process->sku, md5($this->_item_process->body));

        } elseif (!empty($this->_item_process->variant)) {

            $this->db->query("SELECT v.id as variant_id, p.id as product_id
                                        FROM __products p LEFT JOIN __variants v ON v.product_id=p.id AND v.name=?
                                        WHERE p.name=? AND p.hash=? $shop_id_filter $brand_id_filter LIMIT 1", $this->_item_process->variant, $this->_item_process->name, md5($this->_item_process->body));

        } else {

            $this->db->query("SELECT v.id as variant_id, p.id as product_id
                                        FROM __products p LEFT JOIN __variants v ON v.product_id=p.id
                                        WHERE p.name=? AND p.hash=? $shop_id_filter $brand_id_filter LIMIT 1", $this->_item_process->name, md5($this->_item_process->body));

        }

        $r = $this->db->result();

        if ($r) {

            $this->_item_process->product_id = $r->product_id;
            $this->_item_process->variant_id = $r->variant_id;

            $this
                ->updateItemProcess([
                    'product_id' => $this->_item_process->product_id,
                    'variant_id' => $this->_item_process->variant_id,
                ]);

            return FALSE;
        }

        return TRUE;
    }

    // Изображения товаров
    public function productImages($new_price = FALSE)
    {

        if (!empty($this->_item_process->images)) {

            $images = unserialize($this->_item_process->images);

            if (is_array($images)) {

                $image_ids = [];
                foreach ($images as $image) {

                    $image = trim(current(explode('?', $image)));

                    if (!empty($image)) {
                        // Имя файла
                        $image_filename = pathinfo($image, PATHINFO_BASENAME);

                        // Добавляем изображение только если такого еще нет в этом товаре
                        $this->db->query('SELECT id FROM __images WHERE product_id=? AND (filename=? OR filename=? OR base_filename=?) LIMIT 1',
                            $this->_item_process->product_id, $image_filename, $image, $image_filename);

                        if (!$image_id = $this->db->result('id')) {

                            $image_id = $this->add_image($this->_item_process->product_id, $image, $this->_item_process->id);

                            $image_ids[] = $image_id;
                        } else {

                            $image_ids[] = intval($image_id);
                        }
                    }
                }
            }
        }

        if (!$new_price) {

            if (!empty($image_ids)) {

                $query = $this->db->placehold("DELETE FROM __images WHERE product_id=? AND price_id=? AND id not in(?@)",
                    intval($this->_item_process->product_id), intval($this->_item_process->id), $image_ids);
                $this->db->query($query);
            } else {

                $query = $this->db->placehold("DELETE FROM __images WHERE product_id=? AND price_id=?",
                    intval($this->_item_process->product_id), intval($this->_item_process->id));
                $this->db->query($query);
            }
        }

        return $this;
    }

    public function productColors($new_price = FALSE)
    {

        $product_colors = [];

        if (!empty($this->_item_process->color)) {

            $colors = unserialize($this->_item_process->color);

            if (is_array($colors)) {

                foreach ($colors as $color) {

                    if (!is_array($color)) {

                        $product_color = $this->get_color(trim($color));

                        if ($product_color) {

                            $product_colors[] = intval($product_color);
                            if (!$this->products->get_product_color($this->_item_process->product_id, $product_color)) {

                                $this->add_product_color($this->_item_process->product_id, $product_color, $this->_item_process->id);
                            }
                        }
                    }
                }
            }
        }

        if (!$new_price) {

            if (!empty($product_colors)) {

                $query = $this->db->placehold("DELETE FROM __products_colors WHERE price_id=? AND product_id=? AND color_id not in(?@)",
                    intval($this->_item_process->id), intval($this->_item_process->product_id), $product_colors);
                $this->db->query($query);
            } else {

                $query = $this->db->placehold("DELETE FROM __products_colors WHERE price_id=? AND product_id=?",
                    intval($this->_item_process->id), intval($this->_item_process->product_id));
                $this->db->query($query);
            }
        }

        return $this;
    }

    public function productSizes($new_price = FALSE)
    {

        $product_sizes = [];
        if (!empty($this->_item_process->size)) {

            $sizes = unserialize($this->_item_process->size);

            if (is_array($sizes)) {

                foreach ($sizes as $size) {

                    if (!is_array($size)) {

                        $product_size = $this->get_size(trim($size));

                        if ($product_size) {

                            $product_sizes[] = intval($product_size);

                            if (!$this->products->get_product_size($this->_item_process->product_id, $product_size)) {

                                $this->add_product_size($this->_item_process->product_id, $product_size, $this->_item_process->id);
                            }
                        }
                    }
                }
            }
        }

        if (!$new_price) {

            if (!empty($product_sizes)) {

                $query = $this->db->placehold("DELETE FROM __products_sizes WHERE price_id=? AND product_id=? AND size_id not in(?@)",
                    intval($this->_item_process->id), intval($this->_item_process->product_id), $product_sizes);
                $this->db->query($query);
            } else {

                $query = $this->db->placehold("DELETE FROM __products_sizes WHERE price_id=? AND product_id=?",
                    intval($this->_item_process->id), intval($this->_item_process->product_id));
                $this->db->query($query);
            }
        }

        return $this;
    }

    public function getBrandId()
    {

        if (empty($this->_item_process->brand)) {

            return FALSE;
        }

        $this->_item_process->brand = trim($this->_item_process->brand);

        $template = strtolower(preg_replace('/[^\d|\w]/', '', $this->_item_process->brand));

        // Найдем его по имени
        $this->db->query('SELECT id FROM __brands WHERE template=?', $template);

        if (!$this->_item_process->brand_id = $this->db->result('id')) {

            // Создадим, если не найден
            $this->_item_process->brand_id = $this->brands->add_brand([
                'name'             => $this->_item_process->brand,
                'meta_title'       => $this->_item_process->brand,
                'meta_keywords'    => $this->_item_process->brand,
                'meta_description' => $this->_item_process->brand,
                'template'         => $template,
            ]);
        }

        return $this->_item_process->brand_id;
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

    public function get_color($color)
    {

        $color = trim($color);

        if (empty($color)) {
            return FALSE;
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

        return FALSE;
    }

    public function add_product_color($product_id, $color_id, $price_id = 0)
    {

        $this->db->query('SELECT product_id as id FROM __products_colors WHERE color_id=? AND product_id=? LIMIT 1', $color_id, $product_id);
        $result = $this->db->result('id');

        if (empty($result)) {

            $query = $this->db->placehold("INSERT IGNORE INTO __products_colors SET product_id=?, color_id=?, position=0, price_id=?", $product_id, $color_id, $price_id);
            $this->db->query($query);
        }
    }

    public function get_size($size)
    {

        $size = trim($size);

        if (empty($size)) {
            return FALSE;
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

    public function add_product_size($product_id, $size_id, $price_id = 0)
    {

        $this->db->query('SELECT product_id as id FROM __products_sizes WHERE size_id=? AND product_id=? LIMIT 1', $size_id, $product_id);
        $result = $this->db->result('id');

        if (empty($result)) {

            $query = $this->db->placehold("INSERT IGNORE INTO __products_sizes SET product_id=?, size_id=?, position=0, price_id=?", $product_id, $size_id, $price_id);
            $this->db->query($query);
        }
    }


    public function add_image($product_id, $filename, $price_id = 0)
    {
        $image_filename = pathinfo($filename, PATHINFO_BASENAME);

        $query = $this->db->placehold("INSERT INTO __images SET product_id=?, filename=?, base_filename=?, file_resource=? , price_id=?", $product_id, $filename, $image_filename, $filename, $price_id);
        $this->db->query($query);
        $id    = $this->db->insert_id();
        $query = $this->db->placehold("UPDATE __images SET position=id WHERE id=?", $id);
        $this->db->query($query);

        return ($id);
    }
}


