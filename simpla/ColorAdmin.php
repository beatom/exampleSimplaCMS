<?php

require_once('api/Simpla.php');


############################################
# Class Category - Edit the good gategory
############################################
class ColorAdmin extends Simpla
{
    private $allowed_image_extentions = array('png', 'gif', 'jpg', 'jpeg', 'ico');

    function fetch()
    {

        $color = new stdClass;
        if ($this->request->method('post')) {

            $color->id   = $this->request->post('id', 'integer');
            $color->name = $this->request->post('name');
            $color->color_key  = $this->request->post('color_key');
            $color->code = $this->request->post('code');

            // Не допустить одинаковые URL разделов.
            if (($c = $this->colors->get_color_by_key($color->color_key)) && $c->id != $color->id) {
                $this->design->assign('message_error', 'key_exists');
            } else {

                if (empty($color->id)) {
                    $color->id = $this->colors->add_color($color);
                    $this->design->assign('message_success', 'added');
                } else {
                    $this->colors->update_color($color->id, $color);
                    $this->design->assign('message_success', 'updated');
                }
                // Удаление изображения
                if ($this->request->post('delete_image')) {
                    $this->colors->delete_image($color->id);
                }

                // Загрузка изображения
                $image = $this->request->files('image');
                if (! empty($image['name']) && in_array(strtolower(pathinfo($image['name'], PATHINFO_EXTENSION)), $this->allowed_image_extentions)) {
//                    $this->colors->delete_image($color->id);
                    move_uploaded_file($image['tmp_name'], $this->root_dir . $this->config->color_images_dir . $image['name']);
                    $this->colors->update_color($color->id, array('image' => $image['name']));
                }

                $color = $this->colors->get_color($color->id);
            }
        } else {
            $color->id = $this->request->get('id', 'integer');
            $color     = $this->colors->get_color($color->id);
        }

        $this->design->assign('color', $color);
        return $this->design->fetch('color.tpl');
    }
}