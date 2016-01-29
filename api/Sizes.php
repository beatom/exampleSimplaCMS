<?php

/**
 * Simpla CMS
 *
 * @copyright    2011 Denis Pikusov
 * @link        http://simplacms.ru
 * @author        Denis Pikusov
 *
 */

require_once('Simpla.php');

class Sizes extends Simpla
{
    /*
    *
    * Функция возвращает массив размеров
    * @param $filter
    *
    */
    public function get_sizes($filter = array())
    {

        $visible_filter = '';
        if(!empty($filter['visible'])) {
            $visible_filter = $this->db->placehold(' AND s.visible=1');
        }

        $category_id_filter = '';
        if(!empty($filter['category_id'])) {
            $category_id_filter = $this->db->placehold("
            LEFT JOIN __products_sizes ps ON ps.size_id=s.id
            LEFT JOIN __products p ON p.id=ps.product_id
            LEFT JOIN __products_categories pc ON p.id = pc.product_id
            WHERE pc.category_id in(?@) $visible_filter", (array)$filter['category_id']);
        }

        $brand_id_filter = '';
        if(!empty($filter['brand_id'])) {
            $category_id_filter = $this->db->placehold("
            LEFT JOIN __products_sizes ps ON ps.size_id=s.id
            LEFT JOIN __products p ON p.id=ps.product_id
            WHERE p.brand_id in(?@) $visible_filter", (array)$filter['brand_id']);
        }

        // Выбираем все размеры
        $query = $this->db->placehold("SELECT DISTINCT s.id, s.name, s.type, s.visible FROM __sizes s $category_id_filter $brand_id_filter ORDER BY name");

        $this->db->query($query);

        return $this->db->results();
    }

    /*
    * Функция возвращает размер по его id
    * @param $id
    *
    */
    public function get_size($id)
    {

        $query = $this->db->placehold("SELECT c.id, c.name, c.type FROM __sizes c WHERE c.id = ? LIMIT 1", $id);

        $this->db->query($query);

        return $this->db->result();
    }

    /*
    *
    * Добавление размера
    * @param $size
    *
    */
    public function add_size($size)
    {
        $size = (array)$size;

        $this->db->query("INSERT INTO __sizes SET ?%", $size);
        return $this->db->insert_id();
    }

    /*
    *
    * Обновление размера(ов)
    * @param $size
    *
    */
    public function update_size($id, $size)
    {
        $query = $this->db->placehold("UPDATE __sizes SET ?% WHERE id=? LIMIT 1", $size, intval($id));
        $this->db->query($query);
        return $id;
    }

    /*
    *
    * Удаление размера
    * @param $id
    *
    */
    public function delete_size($id)
    {
        if (! empty($id)) {
            $query = $this->db->placehold("DELETE FROM __sizes WHERE id=? LIMIT 1", $id);
            $this->db->query($query);
        }
    }


}