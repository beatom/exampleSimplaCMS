<?PHP
require_once('api/Simpla.php');

class ImportXmlAdmin extends Simpla
{
    public $import_files_dir = 'simpla/files/import/';
    public $import_file = 'import.xml';
    public $allowed_extensions = ['xml', 'yml'];
    private $locale = 'ru_RU.UTF-8';
    public $profile = '';

    public $columns_names = [
        'name'             => 'Название',
        'url'              => 'Ссылка',
        'visible'          => 'Видим',
        'featured'         => 'Рекомендуемый',
        'category'         => 'Категория',
        'brand'            => 'Бренд',
        'variant'          => 'Вариант',
        'price'            => 'Цена',
        'compare_price'    => 'Cтарая цена',
        'sku'              => 'Артикул',
        'stock'            => 'На складе',
        'meta_title'       => 'Meta title',
        'meta_keywords'    => 'Meta keywords',
        'meta_description' => 'Meta description',
        'annotation'       => 'Краткое описание',
        'description'      => 'Описание',
        'images'           => 'Изображения',
        'color'            => 'Цвет',
        'size'             => 'Размер',
        'gender'           => 'Пол',
    ];

    public function __construct()
    {
        parent::__construct();

        $profile = $this->request->get('profile');

        if (!empty($profile)) {
            $this->profile = $profile;
        }
    }

    public function fetch()
    {
        $this->design->assign('import_files_dir', $this->import_files_dir);
        if (!is_writable($this->import_files_dir))
            $this->design->assign('message_error', 'no_permission');

        // Проверяем локаль
        $old_locale = setlocale(LC_ALL, 0);
        setlocale(LC_ALL, $this->locale);
        if (setlocale(LC_ALL, 0) != $this->locale) {
            $this->design->assign('message_error', 'locale_error');
            $this->design->assign('locale', $this->locale);
        }
        setlocale(LC_ALL, $old_locale);

        $mode = $this->request->get('mode');

        if ($this->request->method('post') && ($this->request->files("file"))) {
            $uploaded_name = $this->request->files("file", "tmp_name");
            $temp          = tempnam($this->import_files_dir, 'temp_');
            if (!move_uploaded_file($uploaded_name, $temp))
                $this->design->assign('message_error', 'upload_error');

            if (!$this->convert_file($temp, $this->import_files_dir . $this->import_file))
                $this->design->assign('message_error', 'convert_error');
            else
                $this->design->assign('filename', $this->request->files("file", "name"));
            unlink($temp);

        }

        $action = $this->request->post('action');

        if ($this->request->method('post') && !empty($action) && $action == 'filters') {

            unset($_POST['action']);
            unset($_POST['session_id']);

            $filters = serialize($_POST);

            $this->import->update_settings($this->profile, [
                'filters' => $filters,
            ]);
        }

        if ($this->request->method('post') && !empty($action) && $action == 'fields') {

            unset($_POST['session_id']);
            unset($_POST['action']);

            $fields = serialize($_POST);

            $this->import->update_settings($this->profile, [
                'fields' => $fields,
            ]);
        }

        if ($this->request->method('post') && !empty($action) && $action == 'automatic') {

            unset($_POST['session_id']);
            unset($_POST['action']);

            $this->import->update_settings($this->profile, [
                'is_active'   => $_POST['is_active'],
                'url'         => $_POST['url'],
                'schedule'    => $_POST['schedule'],
                'next_launch' => $_POST['next_launch'],
            ]);
        }

        if ($this->request->method('post') && !empty($action) && $action == 'shop') {

            unset($_POST['session_id']);
            unset($_POST['action']);

            $this->import->update_settings($this->profile, [
                'shop_id' => $_POST['shop_id'],
            ]);
        }

        $settings = $this->import->get_settings($this->profile);

        // Если не указан профиль
        if (empty($this->profile)) {

            $this->profile = $settings->name;
        }

        $settings->category       = !empty($settings->category) ? unserialize(trim($settings->category)) : [];
        $settings->genders        = !empty($settings->genders) ? unserialize(trim($settings->genders)) : [];
        $settings->price_category = !empty($settings->price_category) ? unserialize(trim($settings->price_category)) : '';
        $settings->price_count    = !empty($settings->price_count) ? unserialize(trim($settings->price_count)) : '';
        $settings->fields         = !empty($settings->fields) ? unserialize(trim($settings->fields)) : '';
        $settings->filters        = !empty($settings->filters) ? unserialize(trim($settings->filters)) : '';

        // Подсчет количеств товаров
        if (!empty($settings->shop_id)) {

            $new_products             = $this->import->get_new_products($settings->shop_id);
            $new_products_by_category = $this->import->get_new_products_by_category($settings->shop_id);
            $error_image_products     = $this->import->get_error_image_products($settings->shop_id);
            $error_category_products  = $this->import->get_error_category_products($settings->shop_id);
            $deleted_products         = $this->import->get_deleted_products($settings->shop_id);
            $count_products           = $this->import->get_count_products($settings->shop_id);

            // Новых товаров по категориям
            $new_products_by_category_row = [];
            if (!empty($new_products_by_category)) {

                foreach ($new_products_by_category as $v) {

                    $new_products_by_category_row[$v->categoryId] = $v->count_new_products;
                }
            }

            $this->design->assign('count_products', $count_products);
            $this->design->assign('deleted_products', $deleted_products);
            $this->design->assign('new_products', $new_products);
            $this->design->assign('error_image_products', $error_image_products);
            $this->design->assign('error_category_products', $error_category_products);
            $this->design->assign('new_products_by_category', $new_products_by_category_row);
        }


        $filters = [];
        if (!empty($settings->filters)) {

            foreach ($settings->filters['filters']['text'] as $k => $v) {

                $filters[$k] = [
                    'text'   => $v,
                    'is_not' => $settings->filters['filters']['is_not'][$k] ? TRUE : FALSE,
                ];
            }
        }

        // применяем текстовые фильтры
        foreach ($settings->price_category as $k => $v) {
            foreach ($filters as $filter) {

                if (mb_stripos($v, $filter['text'], NULL, "UTF-8") !== FALSE && !empty($filter['is_not'])) {

                    unset($settings->price_category[$k]);
                } elseif (mb_stripos($v, $filter['text'], NULL, "UTF-8") === FALSE && empty($filter['is_not'])) {

                    unset($settings->price_category[$k]);
                }

            }
        }

        $settings_price_category       = [];
        $settings_price_category_empty = [];
        foreach ($settings->price_category as $k => $v) {

            if (isset($settings->price_count[$k])) {

                if (empty($settings->category[$k])) {

                    $settings_price_category_empty[$k] = $v;
                } else {

                    $settings_price_category[$k] = $v;
                }

            }
        }

        $settings_price_category = $settings_price_category_empty + $settings_price_category;

        // Определяем массив категорий, которые есть родительскими
        $this->db->query("SELECT
							id,
							parent_id,
							name
						FROM __categories
						WHERE visible = 1
						ORDER BY name");

        $all = $this->db->results();

        $is_parent = [];
        foreach ($all as $k => $v) {

            if (!empty($v->parent_id) && !in_array($v->parent_id, $is_parent)) {

                $is_parent[] = $v->parent_id;
            }
        }


        $categories = $this->categories->get_all_categories();
        $shops      = $this->shop->get_shops();


        foreach ($categories as $k => &$v) {

            if (in_array($k, $is_parent)) { // чистим от родительских категорий

                unset($categories[$k]);
            } else {

                $v = $v->name;
            }
        }

        // Список соответствия категорий
        if ($this->request->method('post') && !empty($action) && $action == 'category') {

            unset($_POST['session_id']);
            unset($_POST['action']);


            $post = [];
            foreach ($_POST as $k => $v) {

                if (mb_stripos($k, 'value_') === FALSE) {

                    $settings->category[$k] = $v;
                    $post[$k]               = $v;
                }
            }

            $category = serialize($settings->category);

            $this->import->update_settings($this->profile, [
                'category' => $category,
            ]);

            // Обновляем статус товаров данного магазина, если вдруг поменялось соотвествие категорий
            if (!empty($settings->shop_id)) {

                $query = $this->db->placehold("UPDATE __price SET product_status='updated', status='new' WHERE product_status='error_category' AND shop_id=?", $settings->shop_id);
                $this->db->query($query);
            }
        }

        if (empty($settings->price_category)) {

            $this->design->assign('message_error', 'empty_price_category');
        }

        if (empty($settings->category)) {

            $this->design->assign('message_error', 'empty_category');

        } elseif (is_array($settings->category)) {

            $diff = array_diff((array)$settings->category, [NULL]);

            if ($diff == []) {

                $this->design->assign('message_error', 'empty_category_diff');
            }
        }

//        echo '<pre>';
//        print_r($settings->genders);
//        exit;

        $this->design->assign('profiles', $this->import->get_jobs(FALSE));
        $this->design->assign('options', (array)$settings);
        $this->design->assign('categories', $categories);
        $this->design->assign('genders', $settings->genders);
        $this->design->assign('categories_values', $settings->category);
//        $this->design->assign('price_category', $settings->price_category);
        $this->design->assign('price_count', $settings->price_count);
        $this->design->assign('columns_names', $this->columns_names);
        $this->design->assign('columns_values', $settings->fields);
        $this->design->assign('profile_name', $this->profile);
        $this->design->assign('shops', $shops);
        $this->design->assign('filters', $filters);


        //Пагинатор
        $limit = 1000;
        if ($this->request->get('page') == 'all') {

            $limit = count($settings_price_category);
        }


        if (count($settings->price_category) > 0) {

            $pages_count = ceil(count($settings_price_category) / $limit);
        } else {

            $pages_count = 0;
        }

        $page = $this->request->get('page');
        $page = min(empty($page) ? 1 : $page, $pages_count);

//        print_r($settings->price_category);

        $this->design->assign('price_category_count', count($settings_price_category));
        $this->design->assign('price_category', array_slice($settings_price_category, ($limit * ($page - 1)), $limit, TRUE));

//        echo '<pre>';
//        print_r(array_slice($settings->price_category, 0 ,10, TRUE));
//        exit;


        $this->design->assign('pages_count', $pages_count);
        $this->design->assign('current_page', $page);


        if (!empty($mode) && $mode == 'log') {

            $created = $this->request->get('created');

            $logs = $this->import->get_logs();
            $log  = $this->import->get_log($created);

//            echo '<pre>';
//            print_r($log);
//            exit;

            if (!empty($logs)) {

                $this->design->assign('logs', $logs);
            }

            if (!empty($log)) {

                $log->log = unserialize(trim($log->log));
                $this->design->assign('log', $log);

                //                echo '<pre>';
                //            print_r($log);
                //            exit;
            }

            return $this->design->fetch('import_log.tpl');
        }

        return $this->design->fetch('import_xml.tpl');
    }

    private function convert_file($source, $dest)
    {
        // Узнаем какая кодировка у файла
        $teststring = file_get_contents($source, NULL, NULL, NULL, 1000000);

        return copy($source, $dest);

    }


    private function win_to_utf($text)
    {
        if (function_exists('iconv')) {
            return @iconv('windows-1251', 'UTF-8', $text);
        } else {
            $t = '';
            for ($i = 0, $m = strlen($text); $i < $m; $i++) {
                $c = ord($text[$i]);
                if ($c <= 127) {
                    $t .= chr($c);
                    continue;
                }
                if ($c >= 192 && $c <= 207) {
                    $t .= chr(208) . chr($c - 48);
                    continue;
                }
                if ($c >= 208 && $c <= 239) {
                    $t .= chr(208) . chr($c - 48);
                    continue;
                }
                if ($c >= 240 && $c <= 255) {
                    $t .= chr(209) . chr($c - 112);
                    continue;
                }
                if ($c == 184) {
                    $t .= chr(209) . chr(145);
                    continue;
                }; #ё
                if ($c == 168) {
                    $t .= chr(208) . chr(129);
                    continue;
                }; #Ё
                if ($c == 179) {
                    $t .= chr(209) . chr(150);
                    continue;
                }; #і
                if ($c == 178) {
                    $t .= chr(208) . chr(134);
                    continue;
                }; #І
                if ($c == 191) {
                    $t .= chr(209) . chr(151);
                    continue;
                }; #ї
                if ($c == 175) {
                    $t .= chr(208) . chr(135);
                    continue;
                }; #ї
                if ($c == 186) {
                    $t .= chr(209) . chr(148);
                    continue;
                }; #є
                if ($c == 170) {
                    $t .= chr(208) . chr(132);
                    continue;
                }; #Є
                if ($c == 180) {
                    $t .= chr(210) . chr(145);
                    continue;
                }; #ґ
                if ($c == 165) {
                    $t .= chr(210) . chr(144);
                    continue;
                }; #Ґ
                if ($c == 184) {
                    $t .= chr(209) . chr(145);
                    continue;
                }; #Ґ
            }

            return $t;
        }
    }

}

