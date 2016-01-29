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

class Comments extends Simpla
{

    public $found_rows;

    public function getFoundRows(){

        $this->db->query("SELECT FOUND_ROWS()");
        $this->found_rows = $this->db->result('FOUND_ROWS()');

        return $this->found_rows;
    }

	// Возвращает комментарий по id
	public function get_comment($id)
	{
		$query = $this->db->placehold("SELECT c.id, c.object_id, c.name, c.ip, c.type, c.text, c.date, c.approved FROM __comments c WHERE id=? LIMIT 1", intval($id));

		if($this->db->query($query))
			return $this->db->result();
		else
			return false; 
	}
	
	// Возвращает комментарии, удовлетворяющие фильтру
	public function get_comments($filter = array())
	{	
		// По умолчанию
		$limit = 0;
		$page = 1;
		$object_id_filter = '';
		$type_filter = '';
		$keyword_filter = '';
		$approved_filter = '';

		if(isset($filter['limit']))
			$limit = max(1, intval($filter['limit']));

		if(isset($filter['page']))
			$page = max(1, intval($filter['page']));

		if(isset($filter['ip']))
			$ip = $this->db->placehold("OR c.ip=?", $filter['ip']);
		if(isset($filter['approved']))
			$approved_filter = $this->db->placehold("AND (c.approved=? $ip)", intval($filter['approved']));
			
		if($limit)
			$sql_limit = $this->db->placehold(' LIMIT ?, ? ', ($page-1)*$limit, $limit);
		else
			$sql_limit = '';

		if(!empty($filter['object_id']))
			$object_id_filter = $this->db->placehold('AND c.object_id in(?@)', (array)$filter['object_id']);

		if(!empty($filter['type']))
			$type_filter = $this->db->placehold('AND c.type=?', $filter['type']);

		if(!empty($filter['keyword']))
		{
			$keywords = explode(' ', $filter['keyword']);
			foreach($keywords as $keyword)
				$keyword_filter .= $this->db->placehold('AND c.name LIKE "%'.$this->db->escape(trim($keyword)).'%" OR c.text LIKE "%'.$this->db->escape(trim($keyword)).'%" ');
		}

			
		$sort='DESC';
		
		$query = $this->db->placehold("SELECT
                                          SQL_CALC_FOUND_ROWS (c.id),
                                          c.object_id,
                                          c.ip,
                                          c.text,
                                          c.type,
                                          c.date,
                                          c.approved,
                                          c.positive,
                                          c.negative,
                                          c.recommend,
                                          c.count_positive,
                                          c.count_negative,
                                          c.name
										FROM __comments c
										WHERE 1 $object_id_filter $type_filter $keyword_filter $approved_filter
										ORDER BY id $sort $sql_limit");

		$this->db->query($query);
		return $this->db->results();
	}
	
	// Количество комментариев, удовлетворяющих фильтру
	public function count_comments($filter = array())
	{	
		$object_id_filter = '';
		$type_filter = '';
		$approved_filter = '';
		$keyword_filter = '';

		if(!empty($filter['object_id']))
			$object_id_filter = $this->db->placehold('AND c.object_id in(?@)', (array)$filter['object_id']);

		if(!empty($filter['type']))
			$type_filter = $this->db->placehold('AND c.type=?', $filter['type']);

		if(isset($filter['approved']))
			$approved_filter = $this->db->placehold('AND c.approved=?', intval($filter['approved']));

		if(!empty($filter['keyword']))
		{
			$keywords = explode(' ', $filter['keyword']);
			foreach($keywords as $keyword)
				$keyword_filter .= $this->db->placehold('AND c.name LIKE "%'.$this->db->escape(trim($keyword)).'%" OR c.text LIKE "%'.$this->db->escape(trim($keyword)).'%" ');
		}

		$query = $this->db->placehold("SELECT count(distinct c.id) as count
										FROM __comments c WHERE 1 $object_id_filter $type_filter $keyword_filter $approved_filter", $this->settings->date_format);
	
		$this->db->query($query);	
		return $this->db->result('count');

	}

	// Количество комментариев, по списку ид товаров
	public function count_comments_ids($ids)
	{

		$query = $this->db->placehold("SELECT c.object_id, count(distinct c.id) as count
										FROM __comments c
										WHERE c.object_id in(?@)
										GROUP BY c.object_id", $ids);

		$this->db->query($query);
		return $this->db->results();

	}
	
	// Добавление комментария
	public function add_comment($comment)
	{	
		$query = $this->db->placehold('INSERT INTO __comments
		SET ?%,
		date = NOW()',
		$comment);

		if(!$this->db->query($query))
			return false;

		$id = $this->db->insert_id();
		return $id;
	}

	// Одобрение комментария
	public function add_comments_vote($id, $user_id, $type = 'positive')
	{

		$query = $this->db->placehold('SELECT id FROM __comments_vote WHERE user_id=? AND comment_id=?', $user_id, $id);

        $this->db->query($query);

		if($this->db->results())
			return false;

        $query = $this->db->placehold('INSERT INTO __comments_vote
		SET ?%,
		date = NOW()', array(
                'user_id' => $user_id,
                'comment_id' => $id,
                'vote_type' => $type,
            ));

        if(!$this->db->query($query))
            return false;

		return true;
	}

	// Одобрение количество голосов
	public function count_comment_votes($id, $type = 'positive')
	{

		$query = $this->db->placehold('SELECT count(id) as count
		                               FROM __comments_vote
		                               WHERE comment_id=? AND vote_type=?', $id, $type);

        $this->db->query($query);

		return $this->db->result('count');
	}
	
	// Изменение комментария
	public function update_comment($id, $comment)
	{
		$date_query = '';
		if(isset($comment->date))
		{
			$date = $comment->date;
			unset($comment->date);
			$date_query = $this->db->placehold(', date=STR_TO_DATE(?, ?)', $date, $this->settings->date_format);
		}
		$query = $this->db->placehold("UPDATE __comments SET ?% $date_query WHERE id in(?@) LIMIT 1", $comment, (array)$id);
		$this->db->query($query);
		return $id;
	}

	// Удаление комментария
	public function delete_comment($id)
	{
		if(!empty($id))
		{
			$query = $this->db->placehold("DELETE FROM __comments WHERE id=? LIMIT 1", intval($id));
			$this->db->query($query);
		}
	}	
}
