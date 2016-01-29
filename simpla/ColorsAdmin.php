<?PHP

require_once('api/Simpla.php');

class ColorsAdmin extends Simpla
{
    function fetch()
    {

        // Обработка действий
        if($this->request->method('post'))
        {

            // Действия с выбранными
            $ids = $this->request->post('check');

            if(is_array($ids))
                switch($this->request->post('action'))
                {
                    case 'delete':
                    {
                        foreach($ids as $id)
                            $this->colors->delete_color($id);
                        break;
                    }
                }
        }

        $colors = $this->colors->get_colors();

        $this->design->assign('colors', $colors);
        return $this->body = $this->design->fetch('colors.tpl');
    }
}

