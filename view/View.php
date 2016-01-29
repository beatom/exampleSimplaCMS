<?PHP

/**
 * Simpla CMS
 *
 * @copyright    2011 Denis Pikusov
 * @link        http://simp.la
 * @author        Denis Pikusov
 *
 * Базовый класс для всех View
 *
 */


$pach = realpath(__DIR__ . '/../api/Simpla.php');

require_once($pach);

//require_once('api/Simpla.php');

class View extends Simpla
{
    /* Смысл класса в доступности следующих переменных в любом View */
    public $currency;
    public $currencies;
    public $user;
    public $group;
    public $page;

    /* Класс View похож на синглтон, храним статически его инстанс */
    private static $view_instance;

    public function __construct()
    {
        parent::__construct();

        // Если инстанс класса уже существует - просто используем уже существующие переменные
        if (self::$view_instance) {
            $this->currency = & self::$view_instance->currency;
            $this->currencies = & self::$view_instance->currencies;
            $this->user = & self::$view_instance->user;
            $this->group = & self::$view_instance->group;
            $this->page = & self::$view_instance->page;
        } else {
            // Сохраняем свой инстанс в статической переменной,
            // чтобы в следующий раз использовать его
            self::$view_instance = $this;

            // Все валюты
            $this->currencies = $this->money->cache()->get_currencies(array('enabled' => 1));

            // Выбор текущей валюты
            /*
            if ($currency_id = $this->request->get('currency_id', 'integer')) {
                $_SESSION['currency_id'] = $currency_id;
                header("Location: " . $this->request->url(array('currency_id' => null)));
            }
            */
            /*
            // Берем валюту из сессии
            if (isset($_SESSION['currency_id']))
                $this->currency = $this->money->get_currency($_SESSION['currency_id']);
            // Или первую из списка
            else
            */
                $this->currency = reset($this->currencies);

            // Пользователь, если залогинен
            if (isset($_SESSION['user_id'])) {
                $u = $this->users->get_user(intval($_SESSION['user_id']));
                if ($u && $u->enabled) {
                    $this->user = $u;
                    $this->group = $this->users->get_group($this->user->group_id);

                }
            }

            // Текущая страница (если есть)
            $subdir = substr(dirname(dirname(__FILE__)), strlen($_SERVER['DOCUMENT_ROOT']));
            $page_url = trim(substr($_SERVER['REQUEST_URI'], strlen($subdir)), "/");
            if (strpos($page_url, '?') !== false)
                $page_url = substr($page_url, 0, strpos($page_url, '?'));
            $this->page = $this->pages->cache()->get_page((string)$page_url);
            $this->design->assign('page', $this->page);

            // Передаем в дизайн то, что может понадобиться в нем
            $this->design->assign('currencies', $this->currencies);
            $this->design->assign('currency', $this->currency);
            $this->design->assign('user', $this->user);
            $this->design->assign('group', $this->group);

            $this->design->assign('config', $this->config);
            $this->design->assign('settings', $this->settings);

            // Настраиваем плагины для смарти
            $this->design->smarty->registerPlugin("function", "get_posts", array($this, 'get_posts_plugin'));
            $this->design->smarty->registerPlugin("function", "get_brands", array($this, 'get_brands_plugin'));
            $this->design->smarty->registerPlugin("function", "get_shops", array($this, 'get_shops_plugin'));
            $this->design->smarty->registerPlugin("function", "get_popular_brands", array($this, 'get_popular_brands_plugin'));
            $this->design->smarty->registerPlugin("function", "get_browsed_products", array($this, 'get_browsed_products'));
            $this->design->smarty->registerPlugin("function", "get_featured_products", array($this, 'get_featured_products_plugin'));
            $this->design->smarty->registerPlugin("function", "get_new_products", array($this, 'get_new_products_plugin'));
            $this->design->smarty->registerPlugin("function", "get_count_new_products", array($this, 'get_count_new_products_plugin'));
            $this->design->smarty->registerPlugin("function", "get_discounted_products", array($this, 'get_discounted_products_plugin'));
            $this->design->smarty->registerPlugin("function", "get_slider", array($this, 'get_slider_plugin'));
            $this->design->smarty->registerPlugin("function", "captcha_gen", array($this, 'captcha_gen'));
            $this->design->smarty->registerPlugin("function", "url_get", array($this, 'url_get'));
            $this->design->smarty->registerPlugin("function", "is_start", array($this, 'is_start'));
        }
    }

    /**
     *
     * Отображение
     *
     */
    function fetch()
    {
        return false;
    }

    /**
     *
     * Плагины для смарти
     *
     */
    public function get_posts_plugin($params, &$smarty)
    {
        if (!isset($params['visible']))
            $params['visible'] = 1;
        if (!empty($params['var']))
            $smarty->assign($params['var'], $this->blog->get_posts($params));
    }

    /**
     *
     * Плагины для смарти
     *
     */
    public function get_brands_plugin($params, &$smarty)
    {
        if (!isset($params['visible']))
            $params['visible'] = 1;
        if (!empty($params['var']))
            $smarty->assign($params['var'], $this->brands->cache()->get_brands($params));
    }

    public function get_shops_plugin($params, &$smarty)
    {

        if (!empty($params['var']))
            $smarty->assign($params['var'], $this->shop->cache()->get_shops($params));
    }

    /**
     *
     * Плагины для смарти
     *
     */
    public function get_popular_brands_plugin($params, &$smarty)
    {
        if (!isset($params['visible']))
            $params['visible'] = 1;
        if (!empty($params['var']))
            $smarty->assign($params['var'], $this->brands->cache()->get_popular_brands($params));
    }

    public function get_browsed_products($params, &$smarty)
    {
        if (!empty($_COOKIE['browsed_products'])) {
            $browsed_products_ids = explode(',', $_COOKIE['browsed_products']);
            $browsed_products_ids = array_reverse($browsed_products_ids);
            if (isset($params['limit']))
                $browsed_products_ids = array_slice($browsed_products_ids, 0, $params['limit']);

            $products = array();
            foreach ($this->products->cache()->get_products(array('id' => $browsed_products_ids)) as $p)
                $products[$p->id] = $p;

            // Выбираем варианты товаров
            $variants = $this->variants->cache()->get_variants(array('product_id' => $browsed_products_ids, 'in_stock' => true));

            // Для каждого варианта
            foreach ($variants as &$variant) {
                // добавляем вариант в соответствующий товар
                $products[$variant->product_id]->variants[] = $variant;
            }

            // Выбираем изображения товаров
            $images = $this->products->get_images(array('product_id' => $browsed_products_ids));
            foreach ($images as $image)
                $products[$image->product_id]->images[] = $image;

            foreach ($products as &$product) {
                if (isset($product->variants[0]))
                    $product->variant = $product->variants[0];
                if (isset($product->images[0]))
                    $product->image = $product->images[0];
            }

            foreach ($browsed_products_ids as $id) {
                if (isset($products[$id])) {
                    if (isset($products[$id]->images[0]))
                        $products[$id]->image = $products[$id]->images[0];
                    $result[] = $products[$id];
                }
            }


            $smarty->assign($params['var'], $result);
        }
    }


    public function get_featured_products_plugin($params, &$smarty)
    {
        if (!isset($params['visible']))
            $params['visible'] = 1;
        $params['featured'] = 1;
        if (!empty($params['var'])) {
            foreach ($this->products->cache()->get_products($params) as $p)
                $products[$p->id] = $p;

            if (!empty($products)) {
                // id выбраных товаров
                $products_ids = array_keys($products);

                // Выбираем варианты товаров
                $variants = $this->variants->cache()->get_variants(array('product_id' => $products_ids, 'in_stock' => true));

                // Для каждого варианта
                foreach ($variants as &$variant) {
                    // добавляем вариант в соответствующий товар
                    $products[$variant->product_id]->variants[] = $variant;
                }

                // Выбираем изображения товаров
                $images = $this->products->get_images(array('product_id' => $products_ids));
                foreach ($images as $image)
                    $products[$image->product_id]->images[] = $image;

                foreach ($products as &$product) {
                    if (isset($product->variants[0]))
                        $product->variant = $product->variants[0];
                    if (isset($product->images[0]))
                        $product->image = $product->images[0];
                }
            }

            $smarty->assign($params['var'], $products);

        }
    }


    public function get_new_products_plugin($params, &$smarty)
    {
        if (!isset($params['visible']))
            $params['visible'] = 1;
        if (!isset($params['sort']))
            $params['sort'] = 'created';
        if (isset($params['new']))
            $params['new'] = date('Y-m-d', strtotime('- 7 day'));

        if (!empty($params['var'])) {
            foreach ($this->products->cache()->get_products($params) as $p)
                $products[$p->id] = $p;

            if (!empty($products)) {
                // id выбраных товаров
                $products_ids = array_keys($products);

                // Выбираем варианты товаров
                $variants = $this->variants->cache()->get_variants(array('product_id' => $products_ids, 'in_stock' => true));

                // Для каждого варианта
                foreach ($variants as &$variant) {

                    $variant = $this->variants->processVariant($variant);

                    // добавляем вариант в соответствующий товар
                    $products[$variant->product_id]->variants[] = $variant;
                }

                // Количество отзывов
                $count_comments = $this->comments->cache()->count_comments_ids($products_ids);

                if (!empty($count_comments)) {
                    foreach ($count_comments as $count) {

                        $products[$count->object_id]->count_comments = $count->count;
                    }
                }

                // Выбираем изображения товаров
                $images = $this->products->get_images(array('product_id' => $products_ids));
                foreach ($images as $image)
                    $products[$image->product_id]->images[] = $image;

                foreach ($products as &$product) {
                    if (isset($product->variants[0]))
                        $product->variant = $product->variants[0];
                    if (isset($product->images[0]))
                        $product->image = $product->images[0];
                }
            }

            $smarty->assign($params['var'], $products);
        }
    }

    public function get_count_new_products_plugin($params, &$smarty)
    {
        if (!isset($params['visible']))
            $params['visible'] = 1;
        if (isset($params['new']))
            $params['new'] = date('Y-m-d', strtotime('- 7 day'));

        $smarty->assign($params['var'], $this->products->cache()->count_products($params));
        $smarty->assign('have_new', $this->products->have_new);
    }


    public function get_discounted_products_plugin($params, &$smarty)
    {
        if (!isset($params['visible']))
            $params['visible'] = 1;
        $params['discounted'] = 1;
        if (!empty($params['var'])) {
            foreach ($this->products->cache()->get_products($params) as $p)
                $products[$p->id] = $p;

            if (!empty($products)) {
                // id выбраных товаров
                $products_ids = array_keys($products);

                // Выбираем варианты товаров
                $variants = $this->variants->cache()->get_variants(array('product_id' => $products_ids, 'in_stock' => true));

                // Для каждого варианта
                foreach ($variants as &$variant) {
                    // добавляем вариант в соответствующий товар
                    $products[$variant->product_id]->variants[] = $variant;
                }

                // Выбираем изображения товаров
                $images = $this->products->get_images(array('product_id' => $products_ids));
                foreach ($images as $image)
                    $products[$image->product_id]->images[] = $image;

                foreach ($products as &$product) {
                    if (isset($product->variants[0]))
                        $product->variant = $product->variants[0];
                    if (isset($product->images[0]))
                        $product->image = $product->images[0];
                }
            }

            $smarty->assign($params['var'], $products);

        }
    }


    public function get_slider_plugin($params = array(), &$smarty)
    {

        if (!empty($params['var'])) {

            $slides = $this->slider->cache()->get_slider();

            if (empty($slides)) {
                return;
            }

            foreach ($slides as &$slide) {
                if (!empty($slide->image)) {
                    $slide->image = '/' . $this->config->slider_dir . $slide->image;
                }
            }

            $smarty->assign($params['var'], $slides);
        }
    }

    public function url_get($params = array(), &$smarty)
    {
        $isAjax = $smarty->getTemplateVars('isAjax');

        $url = urldecode($this->request->url_empty_all($params));
        if ($isAjax) {

            $url = !empty($url) ? current(explode("?", $_SERVER['HTTP_REFERER'])) . (count(explode('?', $url)) > 1 ? '?' . explode('?', $url)[1] : '' ) : '';
        }

        echo $url;
    }

    public function is_start($params, &$smarty) {

        $url = urldecode($this->request->url_empty_all());

        if (count(explode('?', $url)) < 2) {

            $return = true;

        } else {

            $return = false;
        }

        $smarty->assign($params['var'], $return);
    }

    /**
     * генерируем код для картинки и хеш для ответа в капче
     * алгоритм работы функции:
     * 1. генерируем 2 случайных числа для выражения
     * 2. генерируем код для капчи из 10 символов
     * 2a. на всякий случай заменяем 4-й и 8-й символ на другой (код символа с 65 по 80) для того чтобы потом
     * 3. 4-й и 8-й символы копируем в конец этого кода
     * 4. меняем 4-й и 8-й символы:
     *    - берём номера символов в таблице ASCII
     * 	  - добавляем к ним наши цифры
     * 	  - генерим новый символ (после этой операции должна получиться буква из латинского алфавита в верхнем регистре)
     * 5. считаем ответ (пока только сложение)
     * 6. генерируем хеш
     * 	  - берём ответ и добавляем к нем код для вызова капчи
     * 	  - кодируем то, что получилось в md5
     * 	  - добавляем к полученному код капчи, закодированный с помощью base64_encode (это нужно для того, чтобы при проверке можно было расшифровать наш md5)
     * 7. отправляем результат в архиве
     *
     * @return array два поля:
     * code - код для того, чтобы вставить картинку со ссылкой code_pict_%CODE%.jpg
     * hash - хеш ответа для вставки формы
     */
    public function captcha_gen($params, &$smarty)
    {

        $num1 = rand(0, 9); // первая цифра
        $num2 = rand(0, 9); // вторая цифра
        $code = $this->user__random_code(); // код в котором будут скрываться наши цифры
        $code[3] = chr(mt_rand(65, 80));
        $code[7] = chr(mt_rand(65, 80));
        $code .= $code[3] . $code[7];
        $code[3] = chr(ord($code[3]) + $num1); // первую цифру "прячем" в четвёртом символе (отсёт идёт с нуля)
        $code[7] = chr(ord($code[7]) + $num2); // первую цифру "прячем" в восьмом символе
        $otvet = $num1 + $num2;

        $hash = md5($otvet . $code) . base64_encode($code); //

        $smarty->assign('captcha', ['code' => $code, 'hash' => $hash]);
    }

    /**
     * проверяем правильность ввода капчи
     * алгоритм проверки:
     * 0. если пользователь не ввёл сумму с капчи - ошибка
     * 1. получаем хеш и введённый ответ на подсчёт капчи
     * 2. из хеша выделяем код, который передавался в генератор капчи
     * 2a. из кода выделяем строку - результат кодирования md5
     * 3. складываем вместе ответ и код из п.2 и генерируем md5
     * 4. если строки, полученная в п.3 и вычисленная в п.2, не совпадают - ошибка
     *
     * @param string $captch значение, которое ввёл пользователь
     * @param string $hash хеш с правильным ответом
     *
     * @return string сообщение об ошибке, пустая строка - всё ОК
     */
    public function captcha_confirm($captcha, $hash)
    {

        $return = '';
        if ($captcha == '')
        {
            $return = 'Посчитайте выражение на картинке'; //Посчитайте выражение на картинке
        }
        else
        {
            $code = base64_decode(substr($hash, 32));
            $hash = substr($hash, 0, 32);
            if (md5($captcha . $code) != $hash)
            {
                $return = 'Неверно посчитано выражение на картинке'; //Неверно посчитано выражение на картинке
            }
        }
        return $return;
    }

    /*
     * генерируем строку со случайныйным набором символов
     *
     * @param integer $count количество символов
     * @return string
     */
    public function user__random_code($count=10)
    {
        $conf = '';
        for ($i = 0; $i < $count; $i++)
        {
            switch (mt_rand(1, 3))
            {
                case 1:$conf.=chr(mt_rand(65, 90));
                    break;
                case 2:$conf.=chr(mt_rand(97, 122));
                    break;
                case 3:$conf.=chr(mt_rand(48, 57));
                    break;
            }
        }
        return $conf;
    }
}
