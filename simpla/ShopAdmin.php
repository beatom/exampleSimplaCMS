<?php

require_once('api/Simpla.php');


############################################
# Class Category - Edit the good gategory
############################################
class ShopAdmin extends Simpla
{
    private $allowed_image_extentions = array('png', 'gif', 'jpg', 'jpeg', 'ico');

    function fetch()
    {
        $shop = new stdClass;
        if($this->request->method('post'))
        {
            $shop->id = $this->request->post('id', 'integer');
            $shop->name = $this->request->post('name');
            $shop->description = $this->request->post('description');

            $shop->url = $this->request->post('url', 'string');
            $shop->meta_title = $this->request->post('meta_title');
            $shop->meta_keywords = $this->request->post('meta_keywords');
            $shop->meta_description = $this->request->post('meta_description');

            // Не допустить одинаковые URL разделов.
            if(($c = $this->shop->get_shop($shop->url)) && $c->id!=$shop->id)
            {
                $this->design->assign('message_error', 'url_exists');
            }
            else
            {
                if(empty($shop->id))
                {
                    $shop->id = $this->shop->add_shop($shop);
                    $this->design->assign('message_success', 'added');
                }
                else
                {
                    $this->shop->update_shop($shop->id, $shop);
                    $this->design->assign('message_success', 'updated');
                }
                // Удаление изображения
                if($this->request->post('delete_image'))
                {
                    $this->shop->delete_image($shop->id);
                }
                // Загрузка изображения
                $image = $this->request->files('image');
                if(!empty($image['name']) && in_array(strtolower(pathinfo($image['name'], PATHINFO_EXTENSION)), $this->allowed_image_extentions))
                {
                    $this->shop->delete_image($shop->id);
                    move_uploaded_file($image['tmp_name'], $this->root_dir.$this->config->shop_images_dir.$image['name']);
                    $this->shop->update_shop($shop->id, array('image'=>$image['name']));
                }
                $shop = $this->shop->get_shop($shop->id);
            }
        }
        else
        {
            $shop->id = $this->request->get('id', 'integer');
            $shop = $this->shop->get_shop($shop->id);
        }

        $this->design->assign('shop', $shop);
        return  $this->design->fetch('shop.tpl');
    }
}