<?PHP

require_once('api/Simpla.php');

class LogsAdmin extends Simpla
{
    function fetch()
    {
        $id = $this->request->get('id');

        if (!empty($id)) {

            $logs = $this->log->get_logs(['id' => (int)$id]);

            if (!empty($logs)) {

                foreach ($logs as &$log) {

                    $log->log = unserialize($log->log);
                }
            }

            $this->design->assign('log', $logs);
        }

        return $this->body = $this->design->fetch('logs.tpl');
    }
}

