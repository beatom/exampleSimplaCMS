<?php

/**
 * Работа с файловым кешем
 */

require_once('Simpla.php');

class Cache extends Simpla
{

    /**
     * папка с файлами
     *
     * @var string слеш к конце-обязательно
     */
    public $path;

    /*
     * время жизни кежа
     *
     */
    public static $cache_time = 600;



    public function __construct()
    {

        $this->path = $_SERVER['DOCUMENT_ROOT'] . '/' . $this->config->cache_pach . '/';
    }

    public function __destruct()
    {

    }

    /**
     * @return int
     */
    public function getCacheTime()
    {
        return self::$cache_time;
    }

    /**
     * @param int $cache_time
     */
    public function setCacheTime($cache_time)
    {
        self::$cache_time = $cache_time;
        return $this;
    }

    /**
     * @param string $path
     */
    public function setPath($path)
    {
        $this->path = $path;
        return $this;
    }

    /**
     * Запись в файл кеша
     *
     * @param string $index индекс кеша
     * @param string $hash данные для получения хеша (может быть sql-запрос, число и т.п)
     * @param array|string $data данные для сохранения
     * @return true|false
     */
    public function save($index, $hash, $data)
    {
        $filename = $this->_getPath($index, $hash);
        $f        = @fopen($filename, 'w');
        if (!$f) {
            //throw new MainException('Error save to cache ' . $filename);
        } else {
            $data = (is_array($data) || is_object($data) ? serialize($data) : $data);
            fwrite($f, $data);
            fclose($f);
        }
        $old    = umask(0);
        chmod($filename, 0777);
        umask($old);

        return TRUE;
    }

    /**
     * Получаем данные из кеша
     *
     * @param index string индекс кеша
     * @param hash string данные для получения хеша (может быть sql-запрос, число и т.п)
     * @return true|false
     */
    public function load($index, $hash)
    {
        $filename = $this->_getPath($index, $hash);

        // если есть файл - читаем данные из него
        if (is_file($filename)) {

            if ($this->is_living($filename)) {

                $content = file_get_contents($filename);
                $array   = @unserialize($content);
                // в файле может быть сериализованный массив
                return is_array($array) || is_object($array) ? $array : $content;
            }

            @unlink($filename);
        }
        return FALSE;
    }

    /**
     * Проверяет кеш на устарелость
     * @param filename
     * @return true|false
     */
    public function is_living($filename)
    {
        if (empty(self::$cache_time)) {

            return TRUE;
        }

        $filemtime = @filemtime($filename);

        if (!$filemtime || (time() - $filemtime >= self::$cache_time)) {

            return FALSE;
        }

        return TRUE;
    }

    /**
     * Удаление файлов кеша с заданным индексом
     *
     * @param string|array $index индекс для удаления файлов
     * @return true|false
     */
    public function delete($index)
    {
        $dir = opendir($this->path);
        if ($dir) {
            //Сканируем директорию
            while (FALSE !== ($file = readdir($dir))) {
                //удаляем файлы в том случае, если совпадает индекс
                if (is_array($index)) {
                    foreach ($index as $ind) {
                        if (strpos($file, $ind) === 0) {
                            $this->removeDirectory($this->path . $file);
                        }
                    }
                } elseif (strpos($file, $index) === 0) {
                    $this->removeDirectory($this->path . $file);
                }
            }
            closedir($dir);
        }
        return TRUE;
    }

    private function removeDirectory($dir)
    {
        if ($objs = glob($dir . "/*")) {
            foreach ($objs as $obj) {
                is_dir($obj) ? $this->removeDirectory($obj) : unlink($obj);
            }
        }
        rmdir($dir);
    }

    private function _getPath($index, $hash)
    {
        $md5  = md5($hash);
        $path = $this->path . $index;
//        @mkdir($path, 0777);
        $path .= '/' . substr($md5, 0, 4);
//        @mkdir($path, 0777);
        $path .= '/' . substr($md5, 4, 4);
//        @mkdir($path, 0777);
        $path .= '/' . substr($md5, 8, 4);
//        @mkdir($path, 0777);
        $path .= '/' . substr($md5, 12, 4);
//        @mkdir($path, 0777);
        $old    = umask(0);
        @mkdir($path, 0777, TRUE);
        umask($old);
        return $path . '/' . $index . '_' . md5($hash);
    }
}
