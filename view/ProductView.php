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


class ProductView extends View
{

	function fetch()
	{

        /*
        // Test rename cat
        $this->db->query("SELECT c.id, c.url, pc.url p_name, ppc.url pp_name, ppc.url ppp_name
        FROM __categories c
        LEFT JOIN __categories pc ON pc.id = c.parent_id
        LEFT JOIN __categories ppc ON ppc.id = pc.parent_id
        LEFT JOIN __categories pppc ON pppc.id = ppc.parent_id

        ");

        foreach ($this->db->results() as $cat) {
            echo '<pre>';

            $cat->url = (!empty($cat->pp_name) ? $cat->pp_name . '-' : '') . (!empty($cat->p_name) ? $cat->p_name . '-' : '') . (!empty($cat->url) ? $cat->url : '');


            unset($cat->ppp_name, $cat->pp_name, $cat->p_name);

            $query = $this->db->placehold("UPDATE __categories SET ?% WHERE id =? LIMIT 1", $cat, $cat->id);
            $this->db->query($query);

            print_r($cat);
        }

        exit;
        */

        $product_url = $this->request->get('product_url', 'string');
		$category_url = $this->request->get('category_url', 'string');
		
		if(empty($product_url))
			return false;

		// Выбираем товар из базы
		$product = $this->products->cache()->get_product((string)$product_url , $category_url);

//		echo '<pre>';
//		print_r(unserialize($product));
//		exit;

//		if(empty($product) || (!$product->visible && empty($_SESSION['admin'])))
		if(empty($product))
			return false;
		
		$product->images = $this->products->get_images(['product_id'=>$product->id]);
		$product->image = reset($product->images);

		$variants = [];
		foreach($this->variants->cache()->get_variants(['product_id'=>$product->id, 'in_stock'=>true]) as $v)
			$variants[$v->id] = $v;
		
		$product->variants = $variants;
		
		// Вариант по умолчанию
		if(($v_id = $this->request->get('variant', 'integer'))>0 && isset($variants[$v_id]))
			$product->variant = $variants[$v_id];
		else
			$product->variant = reset($variants);
					
		$product->features = $this->features->cache()->get_product_options(['product_id'=>$product->id]);

        // Цвета товара
        if ($product_colors = $this->products->cache()->get_product_colors($product->id)) {

            $this->design->assign('product_colors', $product_colors);
        }

        // Размеры товара
        if ($product_sizes = $this->products->cache()->get_product_sizes($product->id)) {

			$sizes = [];

			foreach ($product_sizes as $product_size) {

				$sizes[$product_size->name] = $product_size;
			}

			ksort($sizes);

            $this->design->assign('product_sizes', $sizes);
        }
	
		// Автозаполнение имени для формы комментария
		if(!empty($this->user))
			$this->design->assign('comment_name', $this->user->name);

		// Принимаем комментарий
		if ($this->request->method('post') && $this->request->post('comment'))
		{
			$comment = new stdClass;

			$comment->text      = $this->request->post('text');
			$comment->positive  = $this->request->post('positive');
			$comment->negative  = $this->request->post('negative');
			$comment->recommend = $this->request->post('recommend');
			$comment->name      = $this->request->post('name');
//			$captcha_code =  $this->request->post('captcha_code', 'string');
			
			// Передадим комментарий обратно в шаблон - при ошибке нужно будет заполнить форму
			$this->design->assign('comment_text', $comment->text);
			$this->design->assign('comment_positive', $comment->positive);
			$this->design->assign('comment_negative', $comment->negative);
			$this->design->assign('comment_recommend', $comment->recommend);
			$this->design->assign('comment_name', $comment->name);

			$captcha = $this->request->post('captcha');
			$hash = $this->request->post('hash');
			$captchaError = $this->captcha_confirm($captcha, $hash);

			
			// Проверяем капчу и заполнение формы
			if (!empty($captchaError))
			{
				$this->design->assign('error', 'captcha');
			}
			elseif (empty($comment->name))
			{
				$this->design->assign('error', 'empty_name');
			}
			elseif (empty($comment->text))
			{
				$this->design->assign('error', 'empty_comment');
			}
			else
			{
				// Создаем комментарий
				$comment->object_id = $product->id;
				$comment->type      = 'product';
				$comment->ip        = $_SERVER['REMOTE_ADDR'];

				// Если были одобренные комментарии от текущего ip, одобряем сразу
				$this->db->query("SELECT 1 FROM __comments WHERE approved=1 AND ip=? LIMIT 1", $comment->ip);
				if($this->db->num_rows()>0)
					$comment->approved = 1;
				
				// Добавляем комментарий в базу
				$comment_id = $this->comments->add_comment($comment);
				
				// Отправляем email
				$this->notify->email_comment_admin($comment_id);				
				
				// Приберем сохраненную капчу, иначе можно отключить загрузку рисунков и постить старую
				unset($_SESSION['captcha_code']);
				header('location: '.$_SERVER['REQUEST_URI'].'#comment_'.$comment_id);
			}			
		}
				
		// Связанные товары
		$related_ids = [];
		$related_products = [];

		foreach($this->products->cache()->get_related_products_dynamic($product->id, $product->category_id, $product->variant->price) as $p)
		{
			$related_ids[] = $p->product_id;
			$related_products[$p->product_id] = null;
		}
		if(!empty($related_ids))
		{
			foreach($this->products->cache()->get_products(['id'=>$related_ids, 'in_stock'=>1, 'visible'=>1]) as $p)
				$related_products[$p->id] = $p;
			
			$related_products_images = $this->products->get_images(['product_id'=>array_keys($related_products)]);
			foreach($related_products_images as $related_product_image)
				if(isset($related_products[$related_product_image->product_id]))
					$related_products[$related_product_image->product_id]->images[] = $related_product_image;
			$related_products_variants = $this->variants->get_variants(['product_id'=>array_keys($related_products), 'in_stock'=>1]);
			foreach($related_products_variants as $related_product_variant)
			{
				if(isset($related_products[$related_product_variant->product_id]))
				{
					$related_products[$related_product_variant->product_id]->variants[] = $related_product_variant;
				}
			}
			foreach($related_products as $id=>$r)
			{
				if(is_object($r))
				{
					$r->image = &$r->images[0];
					$r->variant = &$r->variants[0];
				}
				else
				{
					unset($related_products[$id]);
				}
			}
			$this->design->assign('related_products', $related_products);
		}

		// Отзывы о товаре
        $comments = $this->comments->get_comments([
            'type' => 'product',
            'object_id' => $product->id,
            'approved' => 1,
            'ip' => $_SERVER['REMOTE_ADDR'],
//            'page' => 1,
//            'limit' => 3,
        ]);

        if ($count_comments = $this->comments->getFoundRows()) {
            $this->design->assign('count_comments', $count_comments);
        }

        $best_positive_comment = null;
        $best_negative_comment = null;
        $min_comment_id = null;

        if (!empty($comments)) {

            foreach ($comments as &$c) {

                if ($c->id < $min_comment_id || empty($min_comment_id)) {
                    $min_comment_id = $c->id;
                }

                if (empty($best_positive_comment->count_positive) || $c->count_positive > $best_positive_comment->count_positive) {
                    $best_positive_comment = $c;
                }

                if (empty($best_negative_comment->count_negative) || $c->count_negative > $best_negative_comment->count_negative) {
                    $best_negative_comment = $c;
                }
            }
        }

        $comments = array_slice($comments, 0, 3);

        $this->design->assign('min_comment_id', $min_comment_id);
		$this->design->assign('best_positive_comment', $best_positive_comment);
		$this->design->assign('best_negative_comment', $best_negative_comment);

		// Соседние товары
//		$this->design->assign('next_product', $this->products->get_next_product($product->id));
//		$this->design->assign('prev_product', $this->products->get_prev_product($product->id));

		// И передаем его в шаблон
		$this->design->assign('product', $product);
		$this->design->assign('comments', $comments);
		
		// Категория и бренд товара
		$product->categories = $this->categories->cache()->get_categories(['product_id'=>$product->id]);

		$brand = $this->brands->cache()->get_brand(intval($product->brand_id));
		$this->design->assign('brand', $brand);
		$this->design->assign('category', reset($product->categories));		
		

		// Добавление в историю просмотров товаров
		$max_visited_products = 100; // Максимальное число хранимых товаров в истории
		$expire = time()+60*60*24*30; // Время жизни - 30 дней
		if(!empty($_COOKIE['browsed_products']))
		{
			$browsed_products = explode(',', $_COOKIE['browsed_products']);
			// Удалим текущий товар, если он был
			if(($exists = array_search($product->id, $browsed_products)) !== false)
				unset($browsed_products[$exists]);
		}
		// Добавим текущий товар
		$browsed_products[] = $product->id;
		$cookie_val = implode(',', array_slice($browsed_products, -$max_visited_products, $max_visited_products));
		setcookie("browsed_products", $cookie_val, $expire, "/");

		// Метатеги
		$meta_title = $product->name . (!empty($product->variant->sku) ? (' ' . $product->variant->sku) : '') . ' купить на moda.tm. ' . $this->money->convert($product->variant->price) . ' ' . $this->currency->sign;
		$meta_description = 'На мода.тм ' . $product->name . (!empty($brand->name) ? (' от ' . $brand->name . ' ') : '' ) . 'в наличии и по низкой цене онлайн с доставкой по всей России.';

		$this->design->assign('meta_title', !empty($product->meta_title) ? $product->meta_title : $meta_title);
		$this->design->assign('meta_keywords', $product->meta_keywords);
		$this->design->assign('meta_description', !empty($product->meta_description) ? $product->meta_description : $meta_description);

		return $this->design->fetch('product.tpl');
	}
	


}
