<?PHP

require_once('api/Simpla.php');


class SubscribersAdmin extends Simpla
{
    function fetch()
    {

        if ($this->request->method('post')) {

            // Действия с выбранными
            $ids = $this->request->post('check');
            if (is_array($ids)) {

                switch ($this->request->post('action')) {
                    case 'disable': {
                        foreach ($ids as $id)
                            $this->subscription->update_subscriber($id, array('enabled' => 0));
                        break;
                    }
                    case 'enable': {
                        foreach ($ids as $id)
                            $this->subscription->update_subscriber($id, array('enabled' => 1));
                        break;
                    }
                    case 'delete': {
                        foreach ($ids as $id)
                            $this->subscription->delete_subscriber($id);
                        break;
                    }
                }
            }
        }

        $filter          = array();
        $filter['page']  = max(1, $this->request->get('page', 'integer'));
        $filter['limit'] = 20;

        $sex = $this->request->get('sex', 'string');
        if ($sex) {

            $filter['sex'] = $sex;
        }

        // Поиск
        $keyword = $this->request->get('keyword', 'string');
        if (!empty($keyword)) {

            $filter['keyword'] = $keyword;
            $this->design->assign('keyword', $keyword);
        }

        // Сортировка подписчиков
        if ($sort = $this->request->get('sort', 'string')) {

            $_SESSION['subscribers_admin_sort'] = $sort;
        }

        if (!empty($_SESSION['subscribers_admin_sort'])) {

            $filter['sort'] = $_SESSION['subscribers_admin_sort'];
        } else {

            $filter['sort'] = 'name';
        }

        $this->design->assign('sort', $filter['sort']);

        $subscribers_count = $this->subscription->count_subscribers($filter);

        // Показать все страницы сразу
        if ($this->request->get('page') == 'all') {

            $filter['limit'] = $subscribers_count;
        }

        $subscribers = $this->subscription->get_subscribers($filter);
        $this->design->assign('pages_count', ceil($subscribers_count / $filter['limit']));
        $this->design->assign('current_page', $filter['page']);
        $this->design->assign('sex', $sex);
        $this->design->assign('subscribers', (array)$subscribers);
        $this->design->assign('subscribers_count', $subscribers_count);
        return $this->body = $this->design->fetch('subscribers.tpl');
    }
}
