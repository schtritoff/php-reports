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

		// create PDO_OCI8 object
		$report->conn = new \Yajra\Pdo\Oci8($dsn,$username,$password);
		$report->conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

		// apply Oracle NLS settings if configured
		if (array_key_exists("nls_options", $config) && is_array($config["nls_options"]) && sizeof($config["nls_options"])>0) {
			$sql_nls = "ALTER SESSION SET ";
			foreach	($config["nls_options"] as $field => $value) {
				$sql_nls .= " {$field} = '{$value}' ";
			}
			$stmt = $report->conn->prepare($sql_nls);
			$stmt->execute();
		}

	}
}
