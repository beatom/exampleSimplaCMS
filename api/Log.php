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

class Log extends Simpla
{

	public function get_logs($filter = array())
	{
        $id_filter = '';
        if(!empty($filter['id'])) {
                $id_filter = $this->db->placehold('AND id in(?@)', (array)$filter['id']);
        }

       $table_filter = '';
        if(!empty($filter['table'])) {
            $table_filter = $this->db->placehold('AND resource in(?@)', (array)$filter['table']);
        }

        $item_id_filter = '';
        if(!empty($filter['item_id'])) {
            $item_id_filter = $this->db->placehold('AND item_id in(?@)', (array)$filter['item_id']);
        }

        $manager_filter = '';
        if(!empty($filter['manager'])) {
            $manager_filter = $this->db->placehold('AND manager in(?@)', (array)$filter['manager']);
        }

		// Выбираем
		$query = $this->db->placehold("SELECT * FROM __log WHERE 1 $id_filter $table_filter $item_id_filter $manager_filter ORDER BY time DESC ");
		$this->db->query($query);

		return $this->db->results();
	}

	public function add_log($text, $table, $item_id = '', $manager = '')
	{
        $log = new stdClass;

        $log->log = serialize($text);
        $log->resource = $table;
        $log->item_id = $item_id;
        $log->manager = $manager;

		$this->db->query("INSERT INTO __log SET ?%", $log);
		return $this->db->insert_id();
	}


}