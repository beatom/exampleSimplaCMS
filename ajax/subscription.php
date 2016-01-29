<?php

session_start();
require_once('../view/View.php');

class SubscriptionAjax extends View
{

    function fetch()
    {

        $email = $this->request->get('email');
        $sex   = $this->request->get('sex');
        $get   = $this->request->get('get');

        $result = [
            'success' => FALSE,
        ];


        if ($get == TRUE) {

            $result = [
                'success'  => TRUE,
                'response' => $this->design->fetch('subscription.tpl'),
            ];
        } elseif (empty($email) || !preg_match('~^[0-9a-zA-Z\-_\.]+@[0-9a-zA-Z\-_]+\.[0-9a-zA-Z\-_\.]+$~', $email)) {

            $result = [
                'success' => FALSE,
            ];
        } else {

            $isset = $this->subscription->get_subscriber_by_email($email);

            if ($isset) {

                $result = [
                    'success'  => TRUE,
                    'response' => $this->design->fetch('subscription_success.tpl'),
                ];

            } else {

                $res = $this->subscription->add_subscriber([
                    'email' => $email,
                    'sex'   => $sex,
                ]);

                if ($res) {
                    $result = [
                        'success'  => TRUE,
                        'response' => $this->design->fetch('subscription_success.tpl'),
                    ];
                }
            }

        }

        header("Content-type: application/json; charset=UTF-8");
        header("Cache-Control: must-revalidate");
        header("Pragma: no-cache");
        header("Expires: -1");
        print json_encode($result);
    }

}

$page = new SubscriptionAjax();

$page->fetch();
