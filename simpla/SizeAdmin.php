<?php

require_once('api/Simpla.php');


############################################
# Class Category - Edit the good gategory
############################################
class SizeAdmin extends Simpla
{

    function fetch()
    {

        $size = new stdClass;
        if ($this->request->method('post')) {

            $size->id   = $this->request->post('id', 'integer');
            $size->name = $this->request->post('name');
            $size->type = $this->request->post('type');

            if (empty($size->id)) {

                $size->type = !empty($size->type) ? $size->type : 'height';

                $size->id = $this->sizes->add_size($size);
                $this->design->assign('message_success', 'added');
            } else {
                $this->sizes->update_size($size->id, $size);
                $this->design->assign('message_success', 'updated');
            }

            $size = $this->sizes->get_size($size->id);

        } else {
            $size->id = $this->request->get('id', 'integer');
            $size     = $this->sizes->get_size($size->id);
        }

        $this->design->assign('size', $size);
        return $this->design->fetch('size.tpl');
    }
}