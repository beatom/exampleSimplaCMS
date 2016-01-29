<?php

require_once('Simpla.php');

class Slider extends Simpla
{

    // Получение слайда
    public function get_slide($id)
    {
        $query = $this->db->placehold("SELECT * FROM __main_slider WHERE id=? LIMIT 1", $id);

        if($this->db->query($query))
            return $this->db->result();
        else
            return false;
    }

    // Получение слайда по имени
    public function get_slide_from_image($image)
    {
        $query = $this->db->placehold("SELECT * FROM __main_slider WHERE image=? LIMIT 1", $image);

        if($this->db->query($query))
            return $this->db->result();
        else
            return false;
    }

    // Список активных слайдов
    public function get_slider()
    {
        $query = $this->db->placehold("SELECT * FROM __main_slider WHERE status=? ORDER BY sort", 'enabled');

        $this->db->query($query);
        return $this->db->results();
    }

    // Добавление слайда
    public function add_slide($image, $url = '')
    {

        $query = $this->db->placehold("INSERT INTO __main_slider SET ?%", array(
            'image' => $image,
            'url' => $url,
        ));

        $this->db->query($query);

        return $this->db->insert_id();
    }

    // Удаление слайда
    public function delete_slide($image)
    {
        if(!empty($image))
        {
            $query = $this->db->placehold("DELETE FROM __main_slider WHERE image=? LIMIT 1", $image);
            if($this->db->query($query))
                return true;
        }
        return false;
    }

    // Редактирование слайда
    public function update_slide($image, $slide)
    {
        $slide = (array)$slide;

        $query = $this->db->placehold("UPDATE __main_slider SET ?% WHERE image=? LIMIT 1", $slide, $image);

        $this->db->query($query);

        return $image;
    }
}
