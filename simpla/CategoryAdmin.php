<?php

require_once('api/Simpla.php');


############################################
# Class Category - Edit the good gategory
############################################
class CategoryAdmin extends Simpla
{
    private $allowed_image_extentions = ['png', 'gif', 'jpg', 'jpeg', 'ico'];

    function fetch()
    {

        $manager = $this->managers->get_manager();

        $category = new stdClass;
        if ($this->request->method('post')) {

            $category->id        = $this->request->post('id', 'integer');
            $category->parent_id = $this->request->post('parent_id', 'integer');
            $category->name      = $this->request->post('name');
            $category->visible   = $this->request->post('visible', 'boolean');

            $category->url              = $this->request->post('url', 'string');
            $category->meta_title       = $this->request->post('meta_title');
            $category->meta_keywords    = $this->request->post('meta_keywords');
            $category->meta_description = $this->request->post('meta_description');

            $category->article_title = $this->request->post('article_title');
            $category->description   = $this->request->post('description');

            // Не допустить одинаковые URL разделов.
            if (($c = $this->categories->get_category($category->url)) && $c->id != $category->id) {

                $this->design->assign('message_error', 'url_exists');
            } else {

                if (empty($category->id)) {

                    if ($this->managers->can('categories', 'insert')) {

                        $category->id = $this->categories->add_category($category);
                        $this->design->assign('message_success', 'added');

                        $edit = true;
                    } else {

//                        $this->design->assign('message_error', '');
                    }

                } else {

                    if ($this->managers->can('categories', 'edit')) {

                        $this->categories->update_category($category->id, $category);
                        $this->design->assign('message_success', 'updated');

                        $edit = true;
                    }
                }

                if (!empty($edit)) {

                    $this->log->add_log($category, 's_categories', $category->id, $manager->login);

                    // Удаление изображения
                    if ($this->request->post('delete_image')) {
                        $this->categories->delete_image($category->id);
                    }

                    // Загрузка изображения
                    $image = $this->request->files('image');
                    if (!empty($image['name']) && in_array(strtolower(pathinfo($image['name'], PATHINFO_EXTENSION)), $this->allowed_image_extentions)) {

                        $this->categories->delete_image($category->id);
                        move_uploaded_file($image['tmp_name'], $this->root_dir . $this->config->categories_images_dir . $image['name']);
                        $this->categories->update_category($category->id, ['image' => $image['name']]);
                    }

                }

                $category = $this->categories->get_category(intval($category->id));
            }
        } else {

            $category->id = $this->request->get('id', 'integer');
            $category     = $this->categories->get_category($category->id);
        }


        $categories = $this->categories->get_categories_tree();

        if (!empty($category->id)) {

            $logs = $this->log->get_logs(['table' => 's_categories', 'item_id' => $category->id]);

            if (!empty($logs)) {

                foreach ($logs as &$log) {

                    $log->log = unserialize($log->log);
                }
            }

            $this->design->assign('log', $logs);
        }

        $this->design->assign('category', $category);
        $this->design->assign('categories', $categories);

        return $this->design->fetch('category.tpl');
    }
}