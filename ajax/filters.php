<?php

session_start();

require_once('../view/View.php');

class Filters extends View
{

    function fetchAjax()
    {

        if ($content = $this->cache->load('blocks', 'filters.tpl')) {

            $result = array(
                'success' => TRUE,
                'content' => $content,
            );

        } else {

            $result = array(
                'success' => FALSE,
            );
        }

        header("Content-type: application/json; charset=UTF-8");
        header("Cache-Control: must-revalidate");
        header("Pragma: no-cache");
        header("Expires: -1");
        print json_encode($result);

    }

}

$page = new Filters();

$page->fetchAjax();
