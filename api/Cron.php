<?php

class Cron
{

	protected $_start;
	protected $_stop;
	protected $_lock;
	protected $_next_launch;

	/**
	 * @return mixed
	 */
	public function getNextLaunch()
	{
		return $this->_next_launch;
	}

	/**
	 * @param mixed $next_launch
	 */
	public function setNextLaunch($schedule)
	{
		$now = date('Y-m-d H:i:s');
		list($date, $time) = explode(' ', $now);
		list($year, $month, $day) = explode('-', $date);

		if (! empty($schedule)) {

			$a_sched = array();

			foreach (explode(',', $schedule) as $sched) {

				if ($schedule == 'hourly') {

					$a_sched[] = date('Y-m-d H:i:s', mktime(intval(substr($time, 0, 2)) + 1, 0, 0, $month, $day, $year));

				} elseif (preg_match('/every([0-9]+)min/', $sched, $matches)) {

					$a_sched[] = date('Y-m-d H:i:s', strtotime($now) + ($matches[1] * 60));
				} else {

					if ($pos = strpos($sched, ':')) {

						$s_hour   = intval(substr($sched, 0, $pos - 1));
						$s_minute = intval(substr($sched, $pos + 1));
					} else {

						$s_hour   = intval($sched);
						$s_minute = 0;
					}

					$a_sched[] = date('Y-m-d H:i:s', mktime($s_hour, $s_minute, 0, $month, $day, $year));
					$a_sched[] = date('Y-m-d H:i:s', mktime($s_hour, $s_minute, 0, $month, $day + 1, $year));
				}
			}

			asort($a_sched);
			foreach ($a_sched as $sched) {

				if ($sched > $now) {
					$download = $sched;
					break;
				}
			}

			if (! $download) {
				$download = date('Y-m-d H:i:s', mktime(9, 0, 0, $month, $day + 1, $year));
			}
		}

		$this->_next_launch = $download;
	}


	public function start()
	{
		$time         = explode(' ', microtime());
		$this->_start = $time[1] + $time[0];
	}

	/**
	 * @return float
	 */
	public function stop()
	{
		$time        = explode(' ', microtime());
		$this->_stop = $time[1] + $time[0];

		$time = (float)$this->_stop - (float)$this->_start;
		return $time;
	}

	/**
	 * @return bool
	 */
	public function checkLocked($lockName)
	{
		$this->_lock = __DIR__ . '/../cron/' . $lockName . '.lock';

		if (isset($_GET['ignore'])) {

			$this->unlock();
		}

		if (! file_exists($this->_lock)) {
			$this->_lock();
			return FALSE;
		}

		$content = file_get_contents($this->_lock);
		if ($content) {
			$pids = explode(PHP_EOL, `ps -e | awk '{print $1}'`);
			if (in_array($content, $pids)) {
				echo 'Job ' . $lockName . ' still running, pid=' . $content . PHP_EOL;
				return TRUE;
			}
		}

		$this->_lock();
		return FALSE;
	}

	public function _lock()
	{
		$pid    = getmypid();
		file_put_contents($this->_lock, $pid);
	}

	/**
	 * @return bool
	 */
	public function unlock()
	{
		if (file_exists($this->_lock)) {

			return unlink($this->_lock);
		}

		return FALSE;
	}

}