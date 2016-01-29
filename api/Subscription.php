<?php

require_once('Simpla.php');

class Subscription extends Simpla
{

    /*

    ALTER TABLE `s_subscribers`
    ADD `enabled` tinyint(2) unsigned NOT NULL DEFAULT '1' AFTER `sex`,
    COMMENT='';

    */

    // Ищем подписчика по мылу
    public function get_subscriber_by_email($email)
    {
        $query = $this->db->placehold("SELECT * FROM __subscribers WHERE email=? LIMIT 1", $email);

        if($this->db->query($query))
            return $this->db->result();
        else
            return false;
    }

    // Добавляет подписчика
    public function add_subscriber($data)
    {
        $query = $this->db->placehold("INSERT INTO __subscribers SET ?%", $data);

        $this->db->query($query);
        return true;
    }


    // Список подписчиков
    public function get_subscribers($filter = array())
    {
        $limit = 1000;
        $page = 1;
        $keyword_filter = '';
        $sex_filter = '';

        if(isset($filter['limit']))
        {
            $limit = max(1, intval($filter['limit']));
        }

        if(isset($filter['page']))
        {
            $page = max(1, intval($filter['page']));
        }

        if(isset($filter['keyword']))
        {
            $keywords = explode(' ', $filter['keyword']);
            foreach($keywords as $keyword) {

                $keyword_filter .= $this->db->placehold('AND (email LIKE "%'.$this->db->escape(trim($keyword)).'%")');
            }
        }

        if (isset($filter['sex']))
        {
            $sex_filter = $this->db->placehold("AND sex=?", $filter['sex']);
        }

        $order = 'email';
        if(!empty($filter['sort']))
        {
            switch ($filter['sort'])
            {
                case 'date':
                    $order = 'created DESC';
                    break;
                case 'email':
                    $order = 'email';
                    break;
            }
        }

        $sql_limit = $this->db->placehold(' LIMIT ?, ? ', ($page-1)*$limit, $limit);

        // Выбираем подписчиков
        $query = $this->db->placehold("SELECT * FROM __subscribers WHERE 1 $sex_filter $keyword_filter ORDER BY $order $sql_limit");

        if($this->db->query($query)) {

            return $this->db->results();
        }
        else {

            return false;
        }
    }

    function count_subscribers($filter = array())
    {
        $sex_filter = '';
        $keyword_filter = '';

        if (isset($filter['sex']))
        {
            $sex_filter = $this->db->placehold("AND sex=?", $filter['sex']);
        }

        if(isset($filter['keyword']))
        {
            $keywords = explode(' ', $filter['keyword']);
            foreach($keywords as $keyword) {

                $keyword_filter .= $this->db->placehold('AND (email LIKE "%'.$this->db->escape(trim($keyword)).'%")');
            }
        }

        // Выбираем подписчиков
        $query = $this->db->placehold("SELECT count(*) as count FROM __subscribers WHERE 1 $sex_filter $keyword_filter");
        $this->db->query($query);

        return $this->db->result('count');
    }

    public function update_subscriber($id, $subscriber)
    {
        $subscriber = (array)$subscriber;

        $query = $this->db->placehold("UPDATE __subscribers SET ?% WHERE id=? LIMIT 1", $subscriber, intval($id));
        $this->db->query($query);
        return $id;
    }

    public function delete_subscriber($id)
    {
        if(!empty($id))
        {
            $query = $this->db->placehold("DELETE FROM __subscribers WHERE id=? LIMIT 1", intval($id));
            if($this->db->query($query))
                return true;
        }
        return false;
    }

}
