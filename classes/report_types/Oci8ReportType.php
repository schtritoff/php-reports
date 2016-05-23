<?php
class Oci8ReportType extends PdoReportType {
	
	public static function openConnection(&$report) {
		if(isset($report->conn)) return;


		$environments = PhpReports::$config['environments'];
		$config = $environments[$report->options['Environment']][$report->options['Database']];

		if(isset($config['dsn'])) {
			$dsn = $config['dsn'];
		}
		else {
			$host = $config['host'];
			if(isset($report->options['access']) && $report->options['access']==='rw') {
				if(isset($config['host_rw'])) $host = $config['host_rw'];
			}

			$driver = isset($config['driver'])? $config['driver'] : static::$default_driver;

			if(!$driver) {
				throw new Exception("Must specify database `driver` (e.g. 'mysql')");
			}

			$dsn = $driver.':host='.$host;

			if(isset($config['database'])) {
				$dsn .= ';dbname='.$config['database'];
			}
		}

		//the default is to use a user with read only privileges
		$username = $config['user'];
		$password = $config['pass'];

		//if the report requires read/write privileges
		if(isset($report->options['access']) && $report->options['access']==='rw') {
			if(isset($config['user_rw'])) $username = $config['user_rw'];
			if(isset($config['pass_rw'])) $password = $config['pass_rw'];
		}
        
        //putenv('NLS_LANG=CROATIAN_CROATIA.AL32UTF8');
		$report->conn = new \Yajra\Pdo\Oci8($dsn,$username,$password);

		$report->conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
	}
}
