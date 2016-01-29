<?PHP

require_once('api/Simpla.php');


class CategoriesAdmin extends Simpla
{
    function fetch()
    {

        if ($this->request->method('post')) {
            // Действия с выбранными
            $ids = $this->request->post('check');

            if (is_array($ids)) {

                switch ($this->request->post('action')) {
                    case 'disable': {

                        if ($this->managers->can('categories', 'edit')) {

                            foreach ($ids as $id) {

                                $this->categories->update_category($id, ['visible' => 0]);
                            }
                        }
                        break;
                    }
                    case 'enable': {

                        if ($this->managers->can('categories', 'edit')) {

                            foreach ($ids as $id) {

                                $this->categories->update_category($id, ['visible' => 1]);
                            }
                        }
                        break;
                    }
                    case 'delete': {

                        if ($this->managers->can('categories', 'delete')) {

                            $this->categories->delete_category($ids);
                        }

                        break;
                    }
                }
            }

            // Сортировка
            $positions = $this->request->post('positions');
            $ids       = array_keys($positions);
            sort($positions);
            foreach ($positions as $i => $position) {

                $this->categories->update_category($ids[$i], ['position' => $position]);
            }

        }

        $categories = $this->categories->get_categories_tree();

        $this->design->assign('categories', $categories);

        return $this->design->fetch('categories.tpl');
    }
}
