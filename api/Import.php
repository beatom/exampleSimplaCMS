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

class Import extends Simpla
{

    // Возвращает настройки
    public function get_settings($name = null)
    {

        if (empty($name)) {

            $query = $this->db->placehold("SELECT * FROM __import_settings LIMIT 1");

        } else {

            $query = $this->db->placehold("SELECT * FROM __import_settings WHERE name=? LIMIT 1", $name);
        }

        if ($this->db->query($query))
            return $this->db->result();
        else
            return false;
    }

    // Изменение настроек
    public function update_settings($name, $data)
    {

        $query = $this->db->placehold("UPDATE __import_settings SET ?% WHERE name=?", $data, $name);
        $this->db->query($query);
        return true;
    }

    // Делаем товары неактивными перед парсингом прайса
    public function disable_products_by_shop_id($shop_id)
    {

        $query = $this->db->placehold("UPDATE __products SET visible=0 WHERE shop_id=?", $shop_id);
        $this->db->query($query);
        return true;
    }

    // Берет все активные профили для крона
    public function get_jobs($is_active = 1)
    {

        if ($is_active) {

            $query = $this->db->placehold("SELECT * FROM __import_settings WHERE is_active=?", $is_active);
        } else {

            $query = $this->db->placehold("SELECT * FROM __import_settings");
        }

        if ($this->db->query($query))
            return $this->db->results();
        else
            return false;
    }


    // Количество товаров в магазине
    public function get_count_products($shop_id)
    {
        $query = $this->db->placehold("SELECT COUNT(id) as count_products FROM __price WHERE shop_id=?", $shop_id);

        if ($this->db->query($query))
            return $this->db->result('count_products');
        else
            return 0;
    }

    // Количество новых товаров в магазине
    public function get_new_products($shop_id)
    {
        $query = $this->db->placehold("SELECT COUNT(id) as count_new_products FROM __price WHERE shop_id=? AND status='new'", $shop_id);

        if ($this->db->query($query))
            return $this->db->result('count_new_products');
        else
            return 0;
    }

    // Количество удаленных товаров в магазине
    public function get_deleted_products($shop_id)
    {
        $query = $this->db->placehold("SELECT COUNT(id) as count_deleted_products FROM __price WHERE shop_id=? AND status='deleted'", $shop_id);

        if ($this->db->query($query))
            return $this->db->result('count_deleted_products');
        else
            return 0;
    }

    // Количество новых товаров в магазине по категориям
    public function get_new_products_by_category($shop_id)
    {
        $query = $this->db->placehold("SELECT COUNT(id) as count_new_products, categoryId FROM s_price WHERE shop_id=? AND status='new' GROUP BY categoryId", $shop_id);

        if ($this->db->query($query))
            return $this->db->results();
        else
            return 0;
    }

    // Количество товаров без картинки в магазине
    public function get_error_image_products($shop_id)
    {
        $query = $this->db->placehold("SELECT COUNT(id) as count_error_image_products FROM __price WHERE shop_id=? AND product_status='error_images'", $shop_id);

        if ($this->db->query($query))
            return $this->db->result('count_error_image_products');
        else
            return 0;
    }

    // Количество товаров без картинки в магазине
    public function get_error_category_products($shop_id)
    {
        $query = $this->db->placehold("SELECT COUNT(id) as count_error_category_products FROM __price WHERE shop_id=? AND product_status='error_category'", $shop_id);

        if ($this->db->query($query))
            return $this->db->result('count_error_category_products');
        else
            return 0;
    }

    /*
    *
    * Добавление лога
    * @param $log
    *
    */
    public function add_log($log)
    {

        $this->db->query("INSERT INTO __import_log SET ?%", $log);

        return $this->db->insert_id();
    }

    /*
    *
    * Чтение лога
    * @param $created
    *
    */
    public function get_log($created = null)
    {

        if (!empty($created)) {

            $query = $this->db->placehold("select * from __import_log WHERE created=?", $created);
        } else {

            $query = $this->db->placehold("select * from __import_log ORDER BY created DESC");
        }

        if ($this->db->query($query))
            return $this->db->result();
        else
            return FALSE;
    }

    /*
    *
    * Список логов
    * @param $created
    *
    */
    public function get_logs()
    {

        $query = $this->db->placehold("select * from __import_log ORDER BY created DESC");

        if ($this->db->query($query))
            return $this->db->results();
        else
            return FALSE;
    }
}
