<?PHP

/**
 * Simpla CMS
 *
 * @copyright 	2011 Denis Pikusov
 * @link 		http://simp.la
 * @author 		Denis Pikusov
 *
 * Этот класс использует шаблон index.tpl,
 * который содержит всю страницу кроме центрального блока
 * По get-параметру module мы определяем что сожержится в центральном блоке
 *
 */

require_once('View.php');

class IndexView extends View
{	
	public $modules_dir = 'view/';

	public function __construct()
	{
		parent::__construct();
	}

		
	/**
	 *
	 * Отображение
	 *
	 */
	function fetch()
	{
		// Содержимое корзины
		$this->design->assign('cart',		$this->cart->cache()->get_cart());
	
        // Категории товаров
		$this->design->assign('categories', $this->categories->cache()->get_categories_tree([
			'not_empty' => true,
			'visible' => true,
		]));
		
		// Страницы
		$pages = $this->pages->cache()->get_pages(array('visible'=>1));
		$this->design->assign('pages', $pages);
							
		// Текущий модуль (для отображения центрального блока)
		$module = $this->request->get('module', 'string');
		$module = preg_replace("/[^A-Za-z0-9]+/", "", $module);

		// Если не задан - берем из настроек
		if(empty($module))
			return false;
		//$module = $this->settings->main_module;

		// Создаем соответствующий класс
		if (is_file($this->modules_dir."$module.php"))
		{
				include_once($this->modules_dir."$module.php");
				if (class_exists($module))
				{
					$this->main = new $module($this);
				} else return false;
		} else return false;

		// Создаем основной блок страницы
		if (!$content = $this->main->fetch())
		{
			return false;
		}

        $slider = $this->design->fetch('slider.tpl');

        // Передаем основной блок в шаблон
        $this->design->assign('slider', $slider);

        // Передаем основной блок в шаблон
		$this->design->assign('content', $content);		
		
		// Передаем название модуля в шаблон, это может пригодиться
		$this->design->assign('module', $module);
				
		// Создаем текущую обертку сайта (обычно index.tpl)
		$wrapper = $this->design->smarty->getTemplateVars('wrapper');
		if(is_null($wrapper))
			$wrapper = 'index.tpl';
			
		if(!empty($wrapper))
			return $this->body = $this->design->fetch($wrapper);
		else
			return $this->body = $content;

	}
}
