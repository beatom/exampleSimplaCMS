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

class Shop extends Simpla
{
	/*
	*
	* Функция возвращает массив магазинов
	* @param $filter
	*
	*/
	public function get_shops($filter = array())
	{
        $category_id_filter = '';
        if(!empty($filter['category_id'])) {
            $category_id_filter = $this->db->placehold('LEFT JOIN __products p ON p.shop_id=s.id LEFT JOIN __products_categories pc ON p.id = pc.product_id WHERE pc.category_id in(?@)', (array)$filter['category_id']);
        }

        $brand_id_filter = '';
        if(!empty($filter['brand_id'])) {
            $category_id_filter = $this->db->placehold('LEFT JOIN __products p ON p.shop_id=s.id WHERE p.brand_id in(?@)', (array)$filter['brand_id']);
        }

		// Выбираем все магазины
		$query = $this->db->placehold("SELECT DISTINCT s.id, s.name, s.url, s.meta_title, s.meta_keywords, s.meta_description, s.description, s.image
								 		FROM __shop s $category_id_filter $brand_id_filter ORDER BY s.name");
		$this->db->query($query);

		return $this->db->results();
	}

	/*
	*
	* Функция возвращает магазин по его id или url
	* (в зависимости от типа аргумента, int - id, string - url)
	* @param $id id или url поста
	*
	*/
	public function get_shop($id)
	{
		if(is_int($id))			
			$filter = $this->db->placehold('id = ?', $id);
		else
			$filter = $this->db->placehold('url = ?', $id);
		$query = "SELECT id, name, url, meta_title, meta_keywords, meta_description, description, image
								 FROM __shop WHERE $filter ORDER BY name LIMIT 1";
		$this->db->query($query);
		return $this->db->result();
	}

	/*
	*
	* Функция возвращает магазин по его name
	*
	*/
	public function get_shop_by_name($name)
	{

        $filter = $this->db->placehold('name = ?', $name);

		$query = "SELECT id, name, url, email, company, meta_title, meta_keywords, meta_description, description, image
								 FROM __shop WHERE $filter ORDER BY name LIMIT 1";

		$this->db->query($query);
		return $this->db->result();
	}

	/*
	*
	* Добавление магазина
	* @param $shop
	*
	*/
	public function add_shop($shop)
	{
		$shop = (array)$shop;
		if(empty($shop['url']))
		{
			$shop['url'] = preg_replace("/[\s]+/ui", '_', $shop['name']);
			$shop['url'] = strtolower(preg_replace("/[^0-9a-zа-я_]+/ui", '', $shop['url']));
		}
	
		$this->db->query("INSERT INTO __shop SET ?%", $shop);
		return $this->db->insert_id();
	}

	/*
	*
	* Обновление магазина(ов)
	* @param $shop
	*
	*/		
	public function update_shop($id, $shop)
	{
		$query = $this->db->placehold("UPDATE __shop SET ?% WHERE id=? LIMIT 1", $shop, intval($id));
		$this->db->query($query);
		return $id;
	}
	
	/*
	*
	* Удаление магазина
	* @param $id
	*
	*/	
	public function delete_shop($id)
	{
		if(!empty($id))
		{
			$this->delete_image($id);	
			$query = $this->db->placehold("DELETE FROM __shop WHERE id=? LIMIT 1", $id);
			$this->db->query($query);		
			$query = $this->db->placehold("UPDATE __products SET shop_id=NULL WHERE shop_id=?", $id);
			$this->db->query($query);	
		}
	}
	
	/*
	*
	* Удаление изображения магазина
	* @param $id
	*
	*/
	public function delete_image($shop_id)
	{
		$query = $this->db->placehold("SELECT image FROM __shop WHERE id=?", intval($shop_id));
		$this->db->query($query);
		$filename = $this->db->result('image');
		if(!empty($filename))
		{
			$query = $this->db->placehold("UPDATE __shop SET image=NULL WHERE id=?", $shop_id);
			$this->db->query($query);
			$query = $this->db->placehold("SELECT count(*) as count FROM __shop WHERE image=? LIMIT 1", $filename);
			$this->db->query($query);
			$count = $this->db->result('count');
			if($count == 0)
			{			
				@unlink($this->config->root_dir.$this->config->shops_images_dir.$filename);
			}
		}
	}

}