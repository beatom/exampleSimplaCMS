<?php
	session_start();
	chdir('..');
	require_once('api/Simpla.php');
	
	$simpla = new Simpla();
	if(isset($_GET['id']) && is_numeric($_POST['rating']))
	{
        $product_id = intval(str_replace('product_', '', $_GET['id']));
        $rating = floatval($_POST['rating']);
		
        if(!isset($_SESSION['rating_ids'])) $_SESSION['rating_ids'] = array();
        
        if(!in_array($product_id, $_SESSION['rating_ids']))
		{ 		
			$query = $simpla->db->placehold('SELECT rating, votes FROM __products WHERE id = ? LIMIT 1',  $product_id);
			$simpla->db->query($query);
			$product = $simpla->db->result();
			
            if(!empty($product))
			{
                $rate = ($product->rating * $product->votes + $rating) / ($product->votes + 1);
                $query = $simpla->db->placehold("UPDATE __products SET rating = ?, votes = votes + 1 WHERE id = ?", $rate, $product_id);
                $simpla->db->query($query);
					$_SESSION['rating_ids'][] = $product_id; // ������ � ������ ������� ��� �������������
                echo $rate;             
            }
            else echo -1; //����� �� ������
		}
		else echo 0; //��� ����������
	}
	else echo -1; //�������� �������