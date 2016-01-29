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

class Colors extends Simpla
{
    /*
    *
    * Функция возвращает массив цветов
    * @param $filter
    *
    */
    public function get_colors($filter = [])
    {
        $category_id_filter = '';
        if(!empty($filter['category_id'])) {
            $category_id_filter = $this->db->placehold('
            LEFT JOIN __products_colors pcol ON pcol.color_id=c.id
            LEFT JOIN __products p ON p.id=pcol.product_id
            LEFT JOIN __products_categories pc ON p.id = pc.product_id
            WHERE pc.category_id in(?@)', (array)$filter['category_id']);
        }

        $brand_id_filter = '';
        if(!empty($filter['brand_id'])) {
            $category_id_filter = $this->db->placehold('
            LEFT JOIN __products_colors pcol ON pcol.color_id=c.id
            LEFT JOIN __products p ON p.id=pcol.product_id
            WHERE p.brand_id in(?@)
            ', (array)$filter['brand_id']);
        }

        $not_empty_filter = '';
        if(!empty($filter['not_empty'])) {

            if (!empty($category_id_filter) || !empty($brand_id_filter)) {

                $not_empty_filter = $this->db->placehold("AND c.code != ''");
            } else {

                $not_empty_filter = $this->db->placehold("WHERE c.code != ''");
            }
        }

        $visible_filter = '';
        if(!empty($filter['visible'])) {

            if (!empty($category_id_filter) || !empty($brand_id_filter) || !empty($not_empty_filter)) {

                $visible_filter = $this->db->placehold("AND c.visible=1");
            } else {

                $visible_filter = $this->db->placehold("WHERE c.visible=1");
            }
        }

        // Выбираем все цветы
        $query = $this->db->placehold("SELECT DISTINCT c.id, c.name, c.color_key, c.code, c.image, c.visible
        FROM __colors c $category_id_filter $brand_id_filter $not_empty_filter $visible_filter ORDER BY c.name");

        $this->db->query($query);

        return $this->db->results();
    }

    /*
    * Функция возвращает цвет по его id
    * @param $id
    *
    */
    public function get_color($id)
    {

        $query = $this->db->placehold("SELECT c.id, c.name, c.color_key, c.code, c.image FROM __colors c WHERE c.id = ? LIMIT 1", $id);

        $this->db->query($query);

        return $this->db->result();
    }


   /*
    * Функция возвращает цвет по его key
    * @param $id
    *
    */
    public function get_color_by_key($key)
    {

        $query = $this->db->placehold("SELECT c.id, c.name, c.color_key, c.code, c.image FROM __colors c WHERE c.color_key = ? LIMIT 1", $key);

        $this->db->query($query);

        return $this->db->result();
    }

    /*
    *
    * Добавление цвета
    * @param $color
    *
    */
    public function add_color($color)
    {
        $color = (array)$color;

        $this->db->query("INSERT INTO __colors SET ?%", $color);
        return $this->db->insert_id();
    }

    /*
    *
    * Обновление цвета(ов)
    * @param $color
    *
    */
    public function update_color($id, $color)
    {
        $query = $this->db->placehold("UPDATE __colors SET ?% WHERE id=? LIMIT 1", $color, intval($id));
        $this->db->query($query);
        return $id;
    }

    /*
    *
    * Удаление цвета
    * @param $id
    *
    */
    public function delete_color($id)
    {
        if (! empty($id)) {
            $query = $this->db->placehold("DELETE FROM __colors WHERE id=? LIMIT 1", $id);
            $this->db->query($query);
        }
    }


}