<?PHP

require_once('api/Simpla.php');

############################################
# Class Properties displays a list of product parameters
############################################
class CacheAdmin extends Simpla
{
	function fetch()
	{	

		if($index = $this->request->get('index'))
		{
            $this->cache->delete($index);
		}

        $dir = opendir($this->cache->path);

        $caches = [];

        if ($dir) {
            //Сканируем директорию
            while ($file = readdir($dir)) {

                if ($file != '.' && $file != '..' && $file != '.gitignore') {

                    $caches[] = $file;
                }
            }
            closedir($dir);
        }

		$this->design->assign('caches', $caches);

		return $this->body = $this->design->fetch('cache.tpl');
	}
}
