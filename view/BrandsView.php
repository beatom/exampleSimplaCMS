<?PHP

/**
 * Simpla CMS
 *
 * @copyright 	2011 Denis Pikusov
 * @link 		http://simplacms.ru
 * @author 		Denis Pikusov
 *
 * Этот класс использует шаблон product.tpl
 *
 */

require_once('View.php');


class BrandsView extends View
{

	function fetch()
	{

		$params = array(
			'visible' => 1,
			'not_empty' => true,
		);

		$brands = $this->brands->get_brands($params);

//		echo '<pre>';
//		print_r($brands);
//		echo '<pre>';

//		$c = [];
//		foreach ($brands as $brand) {
//			if ($brand->count_products < 1) {
//				$c[] = $brand;
//			}
//		}
//
//		echo '<pre>';
//		print_r($c);
//		echo '<pre>';
//		exit;

		$brands_sort = array();

		foreach($brands as $brand) {

            $first_simbol = mb_substr($brand->name, 0, 1);

            if (ctype_alpha($first_simbol) || is_numeric($first_simbol)) {

                $brands_sort[mb_strtoupper($first_simbol)][] = $brand;
            }
		}

		$this->design->assign('brands', $brands_sort);

		$page = $this->pages->get_page('brands');

		// Устанавливаем мета-теги
		if ($page) {
			$this->design->assign('page', $page);
			$this->design->assign('meta_title', $page->meta_title);
			$this->design->assign('meta_keywords', $page->meta_keywords);
			$this->design->assign('meta_description', $page->meta_description);
		}

		return $this->design->fetch('brands.tpl');
	}

}
