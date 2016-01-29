<?php

session_start();
require_once('../view/View.php');

class Comment extends View
{

    function fetch()
    {

        $id = $this->request->get('id', 'integer');
        $action = $this->request->get('action');

        if (empty($id) || empty($action)) {
            return false;
        }

        $result = array(
            'success' => false,
        );

        switch ($action) {
            case 'positive':

                if ($this->comments->add_comments_vote($id, session_id(), 'positive')) {

                    $count = $this->comments->count_comment_votes($id, $action);

                    $this->comments->update_comment($id, array(
                        'count_positive' => $count,
                    ));

                    $result = array(
                        'success' => true,
                        'count' => $count,
                    );
                }

                break;
            case 'negative':

                if ($this->comments->add_comments_vote($id, session_id(), 'negative')) {

                    $count = $this->comments->count_comment_votes($id, $action);

                    $this->comments->update_comment($id, array(
                        'count_negative' => $count,
                    ));

                    $result = array(
                        'success' => true,
                        'count' => $count,
                    );
                }

                break;
            case 'more':

                $page = $this->request->get('page');
                $limit = $this->request->get('limit');

                // Отзывы о товаре
                $comments = $this->comments->get_comments(array(
                    'type' => 'product',
                    'object_id' => $id,
                    'approved' => 1,
                    'ip' => $_SERVER['REMOTE_ADDR'],
                    'page' => !empty($page) ? $page : 1,
                    'limit' => $limit,
                ));

                if (!empty($comments)) {

                    $min_comment_id = 0;
                    foreach ($comments as &$c) {

                        if ($c->id < $min_comment_id || empty($min_comment_id)) {
                            $min_comment_id = $c->id;
                        }

                        $c->positive = unserialize($c->positive);
                        $c->negative = unserialize($c->negative);
                    }

                    $this->design->assign('comments', $comments);

                    $html = $this->design->fetch('comments.tpl');

                    $result = array(
                        'success' => true,
                        'html' => $html,
                        'min_comment_id' => $min_comment_id,
                    );
                }

                break;
            default:

                $result = array(
                    'success' => false,
                );

                break;
        }

        header("Content-type: application/json; charset=UTF-8");
        header("Cache-Control: must-revalidate");
        header("Pragma: no-cache");
        header("Expires: -1");
        print json_encode($result);
    }

}

$page = new Comment();

$page->fetch();
