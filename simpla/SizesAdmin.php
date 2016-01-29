<?PHP

require_once('api/Simpla.php');

class SizesAdmin extends Simpla
{
    function fetch()
    {

        // Обработка действий
        if ($this->request->method('post')) {

            // Действия с выбранными
            $ids = $this->request->post('check');

            if (is_array($ids))
                switch ($this->request->post('action')) {
                    case 'delete':
                    {
                        foreach ($ids as $id)
                            $this->sizes->delete_size($id);
                        break;
                    }
                }
        }

        $sizes = $this->sizes->get_sizes();

        $this->design->assign('sizes', $sizes);
        return $this->body = $this->design->fetch('sizes.tpl');
    }
}

