<?PHP

require_once('api/Simpla.php');

class ShopsAdmin extends Simpla
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
                            $this->shop->delete_shop($id);
                        break;
                    }
                }
        }

        $shops = $this->shop->get_shops();

        $this->design->assign('shops', $shops);
        return $this->body = $this->design->fetch('shops.tpl');
    }
}

