<?PHP

require_once('api/Simpla.php');

############################################
# Class Properties displays a list of product parameters
############################################
class CronAdmin extends Simpla
{
    function fetch()
    {

        $pach = __DIR__ . '/../cron/';

        $index = $this->request->get('name');

        if (!empty($index) && is_file($pach . $index)) {

            unlink($pach . $index);
            header('location: /simpla/index.php?module=CronAdmin');
        }

        $dir = opendir($pach);

        $crons = [];

        if ($dir) {
            //Сканируем директорию
            while ($file = readdir($dir)) {

                if ($file != '.' && $file != '..' && $file != '.gitignore') {

                    $file_name = $pach . $file;

                    $file_time = fileatime($file_name);

                    $crons[(int)file_get_contents($file_name)] = [
                        'name' => $file,
                        'time' => str_replace(' ', '&nbsp', date('Y-m-d H:i:s', $file_time)),
                    ];
                }
            }
            closedir($dir);
        }

        $this->design->assign('crons', $crons);

        return $this->body = $this->design->fetch('cron.tpl');
    }
}
