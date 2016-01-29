<?php

/**
 * Simpla CMS
 *
 * @copyright	2011 Denis Pikusov
 * @link		http://simplacms.ru
 * @author		Denis Pikusov
 *
 */

require_once('Simpla.php');

class Categories extends Simpla
{
	// Список указателей на категории в дереве категорий (ключ = id категории)
	private $all_categories;
	// Дерево категорий
	private $categories_tree;
    // Количество всех видимых категорий
	private $count_categories = 0;


	/**
	 * @param mixed $all_categories
	 */
	public function setAllCategories($all_categories)
	{
		$this->all_categories = $all_categories;

		return $this;
	}

    /**
     * @return int
     */
    public function get_count_categories()
    {
        return $this->count_categories;
    }

	public function category_build($item, $row) {

		if (!empty($item->parent_id) && !empty($row[$item->parent_id])
			&& is_object($row[$item->parent_id])
		) {

			$item->name = $row[$item->parent_id]->name . '/' . $item->name;
			$item->parent_id = $row[$item->parent_id]->parent_id;

			return $this->category_build($item, $row);
		}

		return $item;
	}

	// Функция возвращает массив категорий
	public function get_all_categories()
	{
		$query = $this->db->placehold("SELECT
											id,
											parent_id,
											name
 										FROM __categories");
		$this->db->query($query);
//		return $this->db->results();
		$all = $this->db->results();

		$categories = [];
		foreach ($all as $k => $v) {

			$categories[$v->id] = $v;
		}

		$res = [];
		foreach ($categories as $k => $v) {

			$res[$v->id] = $this->category_build($v, $categories);
		}

		return $res;
	}

	// Функция возвращает массив категорий
	public function get_categories($filter = [])
	{
		if(!isset($this->categories_tree))
			$this->init_categories();

		if(!empty($filter['product_id']))
		{
			$query = $this->db->placehold("SELECT category_id FROM __products_categories WHERE product_id in(?@) ORDER BY position", (array)$filter['product_id']);
			$this->db->query($query);
			$categories_ids = $this->db->results('category_id');
			$result = [];
			foreach($categories_ids as $id)
				if(isset($this->all_categories[$id]))
					$result[$id] = $this->all_categories[$id];
			return $result;
		}

		return $this->all_categories;
	}

	// Функция возвращает id категорий для заданного товара
	public function get_product_categories($product_id)
	{
		$query = $this->db->placehold("SELECT product_id, category_id, position FROM __products_categories WHERE product_id in(?@) ORDER BY position", (array)$product_id);
		$this->db->query($query);
		return $this->db->results();
	}	

	// Функция возвращает id категорий для всех товаров
	public function get_products_categories()
	{
		$query = $this->db->placehold("SELECT product_id, category_id, position FROM __products_categories ORDER BY position");
		$this->db->query($query);
		return $this->db->results();
	}	

	// Функция возвращает дерево категорий
	public function get_categories_tree($filter = [])
	{
		if(!isset($this->categories_tree))
			$this->init_categories($filter);
			
		return $this->categories_tree;
	}

	// Функция возвращает заданную категорию
	public function get_category($id)
	{
		if(!isset($this->all_categories)) {
			$this->init_categories();
		}

		if(is_int($id) && array_key_exists(intval($id), $this->all_categories)) {

			return $category = $this->all_categories[intval($id)];
		} elseif (is_string($id)) {

			foreach ($this->all_categories as $category) {

				if ($category->url == $id) {

					return $this->get_category((int)$category->id);
				}
			}
		}
		
		return false;
	}
	
	// Добавление категории
	public function add_category($category)
	{
		$category = (array)$category;
		if(empty($category['url']))
		{
			$name = $this->translit($category['name']);
			$category['url'] = preg_replace("/[\s]+/ui", '_', $name);
			$category['url'] = strtolower(preg_replace("/[^0-9a-zа-я_]+/ui", '', $category['url']));
		}	

		// Если есть категория с таким URL, добавляем к нему число
		while($this->get_category((string)$category['url']))
		{
			if(preg_match('/(.+)_([0-9]+)$/', $category['url'], $parts))
				$category['url'] = $parts[1].'_'.($parts[2]+1);
			else
				$category['url'] = $category['url'].'_2';
		}

		$this->db->query("INSERT INTO __categories SET ?%", $category);
		$id = $this->db->insert_id();
		$this->db->query("UPDATE __categories SET position=id WHERE id=?", $id);		
		unset($this->categories_tree);	
		unset($this->all_categories);	
		return $id;
	}
	
	// Изменение категории
	public function update_category($id, $category)
	{
		$query = $this->db->placehold("UPDATE __categories SET ?% WHERE id=? LIMIT 1", $category, intval($id));
		$this->db->query($query);
		unset($this->categories_tree);			
		unset($this->all_categories);	
		return intval($id);
	}
	
	// Удаление категории
	public function delete_category($ids)
	{
		$ids = (array) $ids;
		foreach($ids as $id)
		{
			if($category = $this->get_category(intval($id)))
			$this->delete_image($category->children);
			if(!empty($category->children))
			{
				$query = $this->db->placehold("DELETE FROM __categories WHERE id in(?@)", $category->children);
				$this->db->query($query);
				$query = $this->db->placehold("DELETE FROM __products_categories WHERE category_id in(?@)", $category->children);
				$this->db->query($query);
			}
		}
		unset($this->categories_tree);			
		unset($this->all_categories);	
		return $id;
	}
	
	// Добавить категорию к заданному товару
	public function add_product_category($product_id, $category_id, $position=0)
	{
		$query = $this->db->placehold("INSERT IGNORE INTO __products_categories SET product_id=?, category_id=?, position=?", $product_id, $category_id, $position);
		$this->db->query($query);
	}

	// Удалить категорию заданного товара
	public function delete_product_category($product_id, $category_id)
	{
		$query = $this->db->placehold("DELETE FROM __products_categories WHERE product_id=? AND category_id=? LIMIT 1", intval($product_id), intval($category_id));
		$this->db->query($query);
	}
	
	// Удалить изображение категории
	public function delete_image($categories_ids)
	{
		$categories_ids = (array) $categories_ids;
		$query = $this->db->placehold("SELECT image FROM __categories WHERE id in(?@)", $categories_ids);
		$this->db->query($query);
		$filenames = $this->db->results('image');
		if(!empty($filenames))
		{
			$query = $this->db->placehold("UPDATE __categories SET image=NULL WHERE id in(?@)", $categories_ids);
			$this->db->query($query);
			foreach($filenames as $filename)
			{
				$query = $this->db->placehold("SELECT count(*) as count FROM __categories WHERE image=?", $filename);
				$this->db->query($query);
				$count = $this->db->result('count');
				if($count == 0)
				{			
					@unlink($this->config->root_dir.$this->config->categories_images_dir.$filename);		
				}
			}
			unset($this->categories_tree);
			unset($this->all_categories);	
		}
	}


	// Инициализация категорий, после которой категории будем выбирать из локальной переменной
	public function init_categories($filter = [])
	{
		// Дерево категорий
		$tree = new stdClass();
		$tree->subcategories = [];
		
		// Указатели на узлы дерева
		$pointers = [];
		$pointers[0] = &$tree;
		$pointers[0]->path = [];
		$pointers[0]->level = 0;

        $count_categories = 0;

		$group_by         = '';
		$not_empty_filter = '';
		if (!empty($filter['not_empty'])) {

			$not_empty_filter = $this->db->placehold('AND (not_empty > 0 OR IFNULL(cc_parent_id,0) = 0)');
			$group_by         = $this->db->placehold('GROUP BY c.id');
		}

		$visible_filter = '';
		if (!empty($filter['visible'])) {

			$visible_filter = $this->db->placehold('AND c.visible = 1');
		}

		// Выбираем все категории
		$query = $this->db->placehold("SELECT
											c.id, c.parent_id, c.name, c.article_title, c.description, c.url, c.meta_title, c.meta_keywords, c.meta_description, c.image, c.visible, c.position,
											cc.parent_id cc_parent_id,
											ccc.parent_id ccc_parent_id,
											(SELECT 1 FROM s_products p WHERE p.id=pc.product_id LIMIT 1) as not_empty
										FROM __categories c
										LEFT JOIN __categories cc ON cc.id = c.parent_id
										LEFT JOIN __categories ccc ON ccc.id = cc.parent_id
										LEFT JOIN __products_categories pc ON pc.category_id = c.id
										WHERE 1 $visible_filter
										GROUP BY c.id HAVING 1 $not_empty_filter  ORDER BY c.parent_id, c.position");
											
		// Выбор категорий с подсчетом количества товаров для каждой. Может тормозить при большом количестве товаров.
		// $query = $this->db->placehold("SELECT c.id, c.parent_id, c.name, c.description, c.url, c.meta_title, c.meta_keywords, c.meta_description, c.image, c.visible, c.position, COUNT(p.id) as products_count
		//                               FROM __categories c LEFT JOIN __products_categories pc ON pc.category_id=c.id LEFT JOIN __products p ON p.id=pc.product_id AND p.visible GROUP BY c.id ORDER BY c.parent_id, c.position");
		

//		echo $query;
		$this->db->query($query);
		$categories = $this->db->results();
//		echo '<pre>';
//		print_r(count($categories));
//		echo '<pre>';
//		echo '<pre>';
//		print_r($categories);
//		echo '<pre>';
//		exit;
				
		$finish = false;
		// Не кончаем, пока не кончатся категории, или пока ниодну из оставшихся некуда приткнуть
		while(!empty($categories)  && !$finish)
		{
			$flag = false;
			// Проходим все выбранные категории
			foreach($categories as $k=>$category)
			{
				if(isset($pointers[$category->parent_id]))
				{
					// В дерево категорий (через указатель) добавляем текущую категорию
					$pointers[$category->id] = $pointers[$category->parent_id]->subcategories[] = $category;
					
					// Путь к текущей категории
					$curr = $pointers[$category->id];
					$pointers[$category->id]->path = array_merge((array)$pointers[$category->parent_id]->path, [$curr]);
					
					// Уровень вложенности категории
					$pointers[$category->id]->level = 1+$pointers[$category->parent_id]->level;

					// Убираем использованную категорию из массива категорий
					unset($categories[$k]);
					$flag = true;
				}

                if ($category->visible) {
                    $this->count_categories++;
                }
			}
			if(!$flag) $finish = true;
		}
		
		// Для каждой категории id всех ее деток узнаем
		$ids = array_reverse(array_keys($pointers));
		foreach($ids as $id)
		{
			if($id>0)
			{
				$pointers[$id]->children[] = $id;

				if(isset($pointers[$pointers[$id]->parent_id]->children))
					$pointers[$pointers[$id]->parent_id]->children = array_merge($pointers[$id]->children, $pointers[$pointers[$id]->parent_id]->children);
				else
					$pointers[$pointers[$id]->parent_id]->children = $pointers[$id]->children;

//              Дописать
//                if(isset($pointers[$pointers[$id]->parent_id]) && $pointers[$id]->visible) {
//                    $pointers[$pointers[$id]->parent_id]->count_children += $pointers[$id]->count_children + 1;
//                }
					
				// Добавляем количество товаров к родительской категории, если текущая видима
				// if(isset($pointers[$pointers[$id]->parent_id]) && $pointers[$id]->visible)
				//		$pointers[$pointers[$id]->parent_id]->products_count += $pointers[$id]->products_count;
			}
		}
		unset($pointers[0]);
		unset($ids);

//        echo '<pre>';
//        print_r($tree->subcategories);
//        exit;

		$this->categories_tree = $tree->subcategories;
		$this->all_categories = $pointers;	
	}

	private function translit($text)
	{
		$ru = explode('-', "А-а-Б-б-В-в-Ґ-ґ-Г-г-Д-д-Е-е-Ё-ё-Є-є-Ж-ж-З-з-И-и-І-і-Ї-ї-Й-й-К-к-Л-л-М-м-Н-н-О-о-П-п-Р-р-С-с-Т-т-У-у-Ф-ф-Х-х-Ц-ц-Ч-ч-Ш-ш-Щ-щ-Ъ-ъ-Ы-ы-Ь-ь-Э-э-Ю-ю-Я-я");
		$en = explode('-', "A-a-B-b-V-v-G-g-G-g-D-d-E-e-E-e-E-e-ZH-zh-Z-z-I-i-I-i-I-i-J-j-K-k-L-l-M-m-N-n-O-o-P-p-R-r-S-s-T-t-U-u-F-f-H-h-TS-ts-CH-ch-SH-sh-SCH-sch---Y-y---E-e-YU-yu-YA-ya");

		$res = str_replace($ru, $en, $text);
		$res = preg_replace("/[\s]+/ui", '-', $res);
		$res = preg_replace('/[^\p{L}\p{Nd}\d-]/ui', '', $res);
		$res = strtolower($res);
		return $res;
	}
}