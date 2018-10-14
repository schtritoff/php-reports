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

	public static function run(&$report) {
		$macros = $report->macros;
		foreach($macros as $key=>$value) {
			if(is_array($value)) {
				$first = true;
				foreach($value as $key2=>$value2) {
					$value[$key2] = $report->conn->quote(trim($value2));
					$value[$key2] = preg_replace("/(^'|'$)/",'',$value[$key2]);
					$first = false;
				}
				$macros[$key] = $value;
			}
			else {
				$macros[$key] = $report->conn->quote($value);
				$macros[$key] = preg_replace("/(^'|'$)/",'',$macros[$key]);
			}

			if($value === 'ALL') $macros[$key.'_all'] = true;
		}

		//add the config and environment settings as macros
		$macros['config'] = PhpReports::$config;
		$macros['environment'] = PhpReports::$config['environments'][$report->options['Environment']];

		//expand macros in query
		$sql = PhpReports::render($report->raw_query,$macros);

		$report->options['Query'] = $sql;

		$report->options['Query_Formatted'] = SqlFormatter::format($sql);

		//split into individual queries and run each one, saving the last result
		$queries = SqlFormatter::splitQuery($sql);

		$datasets = array();

		$explicit_datasets = preg_match('/--\s+@dataset(\s*=\s*|\s+)true/',$sql);

		foreach($queries as $i=>$query) {
			$is_last = $i === count($queries)-1;

			//skip empty queries
			$query = trim($query);
			if(!$query) continue;

			// remove comments from query (oci8 compatibility)
			$query_cleaned = SqlFormatter::compress($query);

			// remove semi-colon (needed in multi dataset report, oci8 compatibility)
			$query_cleaned = str_replace(';','',$query_cleaned);

			$result = $report->conn->query($query_cleaned);

			//if this query had an assert=empty flag and returned results, throw error
			if(preg_match('/^--[\s+]assert[\s]*=[\s]*empty[\s]*\n/',$query)) {
				if($result->fetch(PDO::FETCH_ASSOC))  throw new Exception("Assert failed.  Query did not return empty results.");
			}

			// If this query should be included as a dataset
			if((!$explicit_datasets && $is_last) || preg_match('/--\s+@dataset(\s*=\s*|\s+)true/',$query)) {
				$dataset = array('rows'=>array());

				while($row = $result->fetch(PDO::FETCH_ASSOC)) {
					$dataset['rows'][] = $row;
				}

				// Get dataset title if it has one
				if(preg_match('/--\s+@title(\s*=\s*|\s+)(.*)/',$query,$matches)) {
					$dataset['title'] = $matches[2];
				}

				$datasets[] = $dataset;
			}
		}

		return $datasets;
	}

}
