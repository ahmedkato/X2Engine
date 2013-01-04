<?php
/* * *******************************************************************************
 * The X2CRM by X2Engine Inc. is free software. It is released under the terms of 
 * the following BSD License.
 * http://www.opensource.org/licenses/BSD-3-Clause
 * 
 * X2Engine Inc.
 * P.O. Box 66752
 * Scotts Valley, California 95066 USA
 * 
 * Company website: http://www.x2engine.com 
 * Community and support website: http://www.x2community.com 
 * 
 * Copyright (C) 2011-2012 by X2Engine Inc. www.X2Engine.com
 * All rights reserved.
 * 
 * Redistribution and use in source and binary forms, with or without modification, 
 * are permitted provided that the following conditions are met:
 * 
 * - Redistributions of source code must retain the above copyright notice, this 
 *   list of conditions and the following disclaimer.
 * - Redistributions in binary form must reproduce the above copyright notice, this 
 *   list of conditions and the following disclaimer in the documentation and/or 
 *   other materials provided with the distribution.
 * - Neither the name of X2Engine or X2CRM nor the names of its contributors may be 
 *   used to endorse or promote products derived from this software without 
 *   specific prior written permission.
 * 
 * THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS "AS IS" AND 
 * ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT LIMITED TO, THE IMPLIED 
 * WARRANTIES OF MERCHANTABILITY AND FITNESS FOR A PARTICULAR PURPOSE ARE DISCLAIMED. 
 * IN NO EVENT SHALL THE COPYRIGHT OWNER OR CONTRIBUTORS BE LIABLE FOR ANY DIRECT, 
 * INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES (INCLUDING, 
 * BUT NOT LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES; LOSS OF USE, 
 * DATA, OR PROFITS; OR BUSINESS INTERRUPTION) HOWEVER CAUSED AND ON ANY THEORY OF 
 * LIABILITY, WHETHER IN CONTRACT, STRICT LIABILITY, OR TORT (INCLUDING NEGLIGENCE 
 * OR OTHERWISE) ARISING IN ANY WAY OUT OF THE USE OF THIS SOFTWARE, EVEN IF ADVISED 
 * OF THE POSSIBILITY OF SUCH DAMAGE.
 * ****************************************************************************** */

////////////////////
// Global Objects //
////////////////////
// Run the silent installer with default values?
$silent = isset($_GET['silent']) || (isset($argv) && in_array('silent', $argv));
// Response object for AJAX-driven installation
$response = array();
// Configuration values passed to PDOStatement::execute for parameter binding
$dbConfig = array();
// All configuration values, including statistics
$config = array();
// Values from the form sent back to the form, url-encoded, in GET parameters 
// (so it works w/o javascript, in the case of the user visiting initialize.php
// before the installation is complete)
$userData = array();
// Configuration/info variables from everywhere:
$confKeys = array(
	'dbHost',
	'dbName',
	'dbUser',
	'dbPass',
	'app',
	'currency',
	'currency2',
	'language',
	'adminUsername',
	'adminEmail',
	'adminPass',
	'adminPass2',
	'dummy_data',
	'receiveUpdates',
	'timezone',
	'unique_id',
	'edition',
	'webLeadUrl',
	'php_version',
	'user_agent',
	'GD_support',
	'buildDate',
	'apiKey',
	'db_type',
	'updaterVersion',
	'x2_version',
	'time',
	'dummy_data'
);
// Values that are safe to return in the configuration (in $_GET) in the case
// that the user visits initialize.php before installing or is not using JavaScript
$returnKeys = array(
	'dbHost',
	'dbName',
	'dbUser',
	'app',
	'currency',
	'currency2',
	'language',
	'adminUsername',
	'adminEmail',
	'dummy_data',
	'receiveUpdates',
	'timezone',
	'unique_id',
);
// Configuration keys to be used in $dbConfig. Must coincide with those in $config,
// and be present in protected/data/config.sql
$dbKeys = array(
	'adminEmail',
	'adminUsername',
	'adminPass',
	'apiKey',
	'currency',
	'time',
	'unique_id',
	'edition',
	'bulkEmail',
	'language',
	'timezone'
);
// Values gathered for statistical/anonymous survey purposes:
$sendArgs = array(
	'language',
	'currency',
	'x2_version',
	'dummy_data',
	'php_version',
	'db_type',
	'GD_support',
	'user_agent',
	'timezone',
	'unique_id'
);
// Old or inconsistent variable names in installConfig.php and the config file(s)
$confMap = array(
	'host' => 'dbHost',
	'db' => 'dbName',
	'dbname' => 'dbName',
	'email' => 'adminEmail',
	'user' => 'dbUser',
	'pass' => 'dbPass',
	'adminPassword' => 'adminPass',
	'x2Version' => 'x2_version',
	'lang' => 'language',
	'dummyData' => 'dummy_data',
	'appName' => 'app',
	'version' => 'x2_version',
);

/**
 * Function for communicating status messages to the installer
 * 
 * @param $message The message with which to respond
 * @param $error The error, if any
 */
function respond($message, $error = Null) {
	global $response, $silent;
	if ($error)
		$response['globalError'] = $error;
	if ($silent) {
		echo "$message\n";
	} else if (isset($_GET['stage']) || isset($_POST['testDb'])) {
		header('Content-Type: application/json');
		$response['message'] = $message;
		echo json_encode($response);
		exit(0);
	}
}

/**
 * Wrapper for "die": agnostic to whether the installation is web-based or silent or otherwise.
 */
function RIP($message) {
	global $silent, $response;
	if ($silent) {
		die($message . "\n");
	} else {
		$response['failed'] = 1;
		respond($message);
	}
}

/**
 * Error-handling function: displays info about what went horribly wrong if anything
 * @param type $no
 * @param type $st
 * @param type $fi
 * @param type $ln 
 */
function respondWithError($no, $st, $fi=Null, $ln=Null) {
	RIP("PHP Error [$no]: $st ($fi, L$ln)");
}

/**
 * Exception-handling function: displays full exception message, if any wasn't caught.
 */
function respondWithException($exception) {
	RIP("Uncaught exception with message: ".$exception->getMessage());
}

set_error_handler('respondWithError');
set_exception_handler('respondWithException');

// Test the connection and exit:
if (isset($_POST['testDb'])) {
	// First open the connection
	$con = null;
	try {
		$con = new PDO("mysql:host={$_POST['dbHost']};dbname={$_POST['dbName']}", $_POST['dbUser'], $_POST['dbPass'], array(PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION));
	} catch (PDOException $e) {
		RIP(installer_t('Could not connect to host or select database.'));
	}
	
	// Now test creating a table:
	try {
		$con->exec("CREATE TABLE IF NOT EXISTS `x2_test_table` (
			    `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
			    `a` varchar(10) NOT NULL,
			    PRIMARY KEY (`id`))");
	} catch(PDOException $e) {
		RIP(installer_tr('User {u} does not have adequate permisions on database {db}',array('{db}'=>$_POST['dbName'],'{u}'=>$_POST['dbUser'])).'; '.installer_t('cannot create tables'));
	}

	// Test inserting data:
	try {
		$con->exec("INSERT INTO `x2_test_table` VALUES (1,'a')");
	} catch(PDOException $e) {
		RIP(installer_tr('User {u} does not have adequate permisions on database {db}',array('{db}'=>$_POST['dbName'],'{u}'=>$_POST['dbUser'])).'; '.installer_t('cannot insert data'));
	}
	
	// Test deleting data:
	try {
		$con->exec("DELETE FROM `x2_test_table`");
	} catch(PDOException $e) {
		RIP(installer_tr('User {u} does not have adequate permisions on database {db}',array('{db}'=>$_POST['dbName'],'{u}'=>$_POST['dbUser'])).'; '.installer_t('cannot delete data'));
	}
	
	// Test removing the table:
	try {
		$con->exec("DROP TABLE `x2_test_table`");
	} catch (PDOException $e) {
		RIP(installer_tr('User {u} does not have adequate permisions on database {db}',array('{db}'=>$_POST['dbName'],'{u}'=>$_POST['dbUser'])).'; '.installer_t('cannot drop tables'));
	}
	
	respond(installer_t("Connection successful!"));
}

// Fill in the rest as normal
foreach (array_diff($confKeys, array_keys($confMap)) as $confKey) {
	$confMap[$confKey] = $confKey;
}
// Initialize config with empty values:
foreach ($confKeys as $key) {
	$config[$key] = Null;
}

$staticConfig = array(
	'editions' => 'protected/data/editions.php',
	'stageLabels' => 'protected/data/installStageLabels.php',
	'enabledModules' => 'protected/data/enabledModules.php',
	'dateFields' => 'protected/data/dateFields.php'
);
foreach ($staticConfig as $varName => $path) {
	$realpath = realpath($path);
	if ($realpath) {
		${$varName} = require_once($realpath);
	} else {
		RIP("Could not find static configuration file $path.");
	}
}

////////////////////////////////
// Load Install Configuration //
////////////////////////////////
// Get base values:

function baseConfig() {
	global $config, $confMap;
	$confFile = realpath('protected/config/X2Config.php');
	if ($confFile) {
		include($confFile);
		foreach ($confMap as $name2 => $name1) {
			if (isset(${$name2})) {
				$config[$name1] = ${$name2};
			}
		}
	} else {
		RIP('Could not find essential configuration file at protected/config/X2Config.php');
	}
}

function installConfig() {
	global $config, $confMap;
	if (file_exists('installConfig.php')) {
		require('installConfig.php');
	} else
		die(installer_t('Error: Installer config file not found.'));

	// Collect configuration values from the configuration file
	foreach ($confMap as $name2 => $name1)
		if (isset(${$name2}))
			$config[$name1] = ${$name2};
}

baseConfig();

if ($silent) {
	installConfig();
} else if ($_SERVER['REQUEST_METHOD'] == 'POST') {
	// Collect configuration values from install form
	foreach ($confKeys as $var)
		if (isset($_POST[$var]))
			$config[$var] = $_POST[$var];
	// Determine currency
	$config['currency2'] = strtoupper($config['currency2']);
	if ($config['currency'] == 'other')
		$config['currency'] = $config['currency2'];
	if (empty($config['currency']))
		$config['currency'] = 'USD';
	// Checkbox fields
	foreach (array('dummy_data', 'receiveUpdates') as $checkbox) {
		$config[$checkbox] = (isset($_POST[$checkbox]) && $_POST[$checkbox] == 1) ? 1 : 0;
	}
	$config['unique_id'] = isset($_POST['unique_id']) ? $_POST['unique_id'] : 'none';
	$config['webLeadUrl'] = $_SERVER['SERVER_NAME'] . $_SERVER['REQUEST_URI'];
}
$config['GD_support'] = function_exists('gd_info') ? '1' : '0';
$config['user_agent'] = isset($_SERVER['HTTP_USER_AGENT']) ? $_SERVER['HTTP_USER_AGENT'] : '';
$config['php_version'] = phpversion();
$config['db_type'] = 'MySQL';

///////////////////////////////////////////////////////
// Configuration common to both installation methods //
///////////////////////////////////////////////////////
// Deterine edition info
if (!empty($_POST['edition'])) {
	$config['edition'] = $_POST['edition'];
} else {
	$config['edition'] = 'opensource';
	foreach ($editions as $ed)
		if (file_exists("initialize_$ed.php"))
			$config['edition'] = $ed;
}
// Generate API Key
$config['apiKey'] = substr(str_shuffle(str_repeat('ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789', 16)), 0, 16);

// Set up language & translations:
if (empty($config['language']))
	$config['language'] = 'en';
$installMessageFile = "protected/messages/{$config['language']}/install.php";
$installMessages = array();
if (isset($installMessageFile) && file_exists($installMessageFile)) { // attempt to load installer messages
	$installMessages = include($installMessageFile);  // from the chosen language
	if (!is_array($installMessages))
		$installMessages = array();   // ...or return an empty array
}

// Timezone
if (empty($config['timezone']))
	$config['timezone'] = 'UTC';
date_default_timezone_set($config['timezone']);

// Email address for sending
if (!empty($config['adminEmail']))
	$config['bulkEmail'] = $config['adminEmail'];
else if(isset($_SERVER['HTTP_HOST']))
	$config['bulkEmail'] = 'contact@' . preg_replace('/^www\./', '', $_SERVER['HTTP_HOST']);
else
	$config['bulkEmail'] = 'contact@localhost';

// At this stage, all user-entered data should be avaliable. Populate response data:
foreach ($returnKeys as $key) {
	$userData[$key] = $config[$key];
}

// Generate config file content:
$gii = 1;
if ($gii == '1') {
	$gii = "array(\n\t'class'=>'system.gii.GiiModule',\n\t'password'=>'" . str_replace("'", "\\'", $config['adminPass']) . "', \n\t/* If the following is removed, Gii defaults to localhost only. Edit carefully to taste: */\n\t 'ipFilters'=>false,\n)";
} else {
	$gii = "array(\n\t'class'=>'system.gii.GiiModule',\n\t'password'=>'password',\n\t/* If the following is removed, Gii defaults to localhost only. Edit carefully to taste: */\n\t 'ipFilters'=>array('127.0.0.1', '::1'),\n)";
}
$config['webLeadUrl'] = is_int(strpos($config['webLeadUrl'], 'initialize.php')) ? substr($config['webLeadUrl'], 0, strpos($config['webLeadUrl'], 'initialize.php')) : $config['webLeadUrl'];
$X2Config = "<?php\n";
foreach (array('appName', 'email', 'host', 'user', 'pass', 'dbname', 'version') as $confKey) {
	$X2Config .= "\$$confKey = '{$config[$confMap[$confKey]]}';\n";
}
$X2Config .= "\$buildDate = {$config['buildDate']};\n\$updaterVersion = '{$config['updaterVersion']}';\n";
$X2Config .= (empty($config['language'])) ? '$language=null;' : "\$language='{$config['language']}';\n?>";

// Save config values to be inserted in the database:
$config['time'] = time();
foreach ($dbKeys as $property)
	$dbConfig['{' . $property . '}'] = $config[$property];

///////////////////////
// Declare Functions //
///////////////////////
/*
 * Translation function
 */
function installer_t($str) { // translates by looking up string in install.php language file
	global $installMessages;
	if (isset($installMessages[$str]) && $installMessages[$str] != '')  // if the chosen language is available
		return $installMessages[$str];   // and the message is in there, use it
	return $str;
}

/**
 * Translation function wrapper (for using parameters)
 */
function installer_tr($str, $params) {
	return strtr(installer_t($str), $params);
}

/**
 * Redirect to the installer with errors.
 */
function outputErrors() {
	global $response, $userData, $silent;
	if (!$silent) {
		if (!isset($_GET['stage'])) {
			if (isset($response['errors'])) {
				foreach ($response['errors'] as &$error)
					$error = urlencode($error);  // url encode errors

				if (count($response['errors']) > 0) {
					$errorData = implode('&errors%5B%5D=', $response['errors']);
					$url = preg_replace('/initialize/', 'install', $_SERVER['REQUEST_URI']);
					header("Location: $url?errors%5B%5D=" . $errorData . '&' . http_build_query($userData));
					die();
				}
			}
		}
	}
}

/**
 * Add an error message to the response array.
 * 
 * @global type $response
 * @param type $message 
 */
function addError($message) {
	global $response;
	if (!isset($response['errors'])) {
		$response['errors'] = array();
	}
	$response['errors'][] = $message;
}

$sqlError = '';

function addSqlError($message) {
	global $sqlError;
	if (empty($sqlError))
		$sqlError = $message;
}

/**
 * Backwards-compatible wrapper function for adding validation errors.
 * 
 * @param type $attr
 * @param type $error 
 */
function addValidationError($attr, $error) {
	global $response, $silent;
	if (isset($_GET['stage']) || $silent) {
		if (!isset($response['errors']))
			$response['errors'] = array();
		$response['errors'][$attr] = installer_t($error);
	} else {
		// Slip the validation error into the GET parameters as [attribute]--[errormessage]
		$response['errors'][] = "$attr--$error";
	}
}

/**
 * Delete files
 */
$donotDelete = array('.', '..', '.htaccess');
$noDelPat = '/('.implode('|',array_map(function($b){return str_replace('.','\.',$b);},$donotDelete)).')$/';
function rrmdir($path) {
	global $noDelPat;
	if (!preg_match($noDelPat, $path)) {
		if (is_dir($path)) {
			$objects = scandir($path);
			foreach ($objects as $object)
				if (!preg_match($noDelPat, $object))
					rrmdir($path . DIRECTORY_SEPARATOR . $object);
			reset($objects);
			rmdir($path);
		} else
			unlink($path);
	}
}

/**
 * Installs a named module
 * 
 * @global PDO $dbo
 * @param type $module 
 */
function installModule($module, $respond = True) {
	global $dbo;
	$moduleName = installer_t($module);
	$regPath = "protected/modules/$module/register.php";
	$regFile = realpath($regPath);
	if ($regFile) {
		$install = require_once($regFile);
		foreach ($install['install'] as $sql) {
			// Install a module.
			// For each element in the register script's "install" array, if it's a 
			// string, treat it as a path to an SQL script. Otherwise, if an array,
			// treat as a list of SQL statements.
			$sqlComm = $sql;
			if (is_string($sql)) {
				if (file_exists($sql)) {
					$sqlComm = explode('/*&*/', file_get_contents($sql));
				} else {
					RIP("Error installing module \"$module\"; file does not exist: $sql");
				}
			}
			foreach ($sqlComm as $sqlLine) {
				try {
					$statement = $dbo->prepare($sqlLine);
					$statement->execute() or RIP(installer_tr('Error installing module "{module}". SQL statement "{sql}" failed;', array('{sql}' => substr(trim($sqlLine), 0, 50) . (strlen(trim($sqlLine)) > 50 ? '...' : ''), '{module}' => $moduleName)) . implode(',', $statement->errorInfo()));
				} catch (PDOException $e) {
					RIP(installer_tr('Could not install module "{module}"; ', array('{module}' => $moduleName)) . $e->getMessage());
				}
			}
		}
		if ($respond)
			respond(installer_tr('Module "{module}" installed.', array('{module}' => $moduleName)));
	} else {
		RIP(installer_tr('Failed to install module "{module}"; could not find configuration file at {path}.', array('{module}'=>$moduleName,'{path}'=>$regPath)));
	}
}

/**
 * Runs a named piece of the installation.
 * 
 * @param $stage The named stage of installation.
 */
function installStage($stage) {
	global $editions, $silent, $dbo, $config, $dbConfig, $stageLabels, $response, $write, $X2Config, $enabledModules,$dateFields,$noDelPat;
	
	if ($stage == 'validate') {
		if ($config['dummy_data'] == 1 && $config['adminUsername'] != 'admin')
			addValidationError('adminUsername','Cannot change administrator username if installing with sample data.');
		else {
			if (empty($config['adminUsername']))
				addValidationError('adminUsername', 'Admin username cannot be blank.');
			elseif (is_int(strpos($config['adminUsername'], "'")))
				addValidationError('adminUsername', 'Admin username cannot contain apostrophes');
			elseif (preg_match('/^\d+$/', $config['adminUsername']))
				addValidationError('adminUsername', 'Admin username must contain at least one non-numeric character.');
		}
		if (empty($config['adminEmail']) || !preg_match('/^[A-Z0-9._%+-]+@[A-Z0-9.-]+\.[A-Z]{2,4}$/i', $config['adminEmail']))
			addValidationError('adminEmail', 'Please enter a valid email address.');
		if ($_POST['adminPass'] == '')
			addValidationError('adminPass', 'Admin password cannot be blank.');
		if (!isset($_POST['adminPass2']))
			addValidationError('adminPass2', 'Please confirm the admin password.');
		else if ($config['adminPass'] != $_POST['adminPass2'])
			addValidationError('adminPass2', 'Admin passwords did not match.');
		if (!empty($response['errors'])) {
			respond(installer_t('Please correct the following errors:'));
		}
	} else if ($stage == 'module') {
		if (isset($_GET['module'])) {
			// Install only a named module
			installModule($_GET['module']);
		} else {
			// Install all modules:
			foreach ($enabledModules as $module)
				installModule($module, $silent);
		}
	} else if ($stage == 'config') {
		// Configure with initial data and write files

		$contents = file_get_contents('webLeadConfig.php');
		$contents = preg_replace('/\$url=\'\'/', "\$url='{$config['webLeadUrl']}'", $contents);
		$contents = preg_replace('/\$user=\'\'/', "\$user='api'", $contents);
		$contents = preg_replace('/\$password=\'\'/', "\$password='{$config['apiKey']}'", $contents);
		file_put_contents('webLeadConfig.php', $contents);

		$filename = 'protected/config/X2Config.php';
		$handle = fopen($filename, 'w') or RIP(installer_t('Could not create configuration file.'));

		fwrite($handle, $X2Config);
		fclose($handle);

		$dbConfig['{adminPass}'] = md5($config['adminPass']);
		try {
			$sqlPath = 'protected/data/config.sql';
			$sqlFile = realpath($sqlPath);
			if ($sqlFile) {
				$sql = explode('/*&*/', strtr(file_get_contents($sqlFile), $dbConfig));
				foreach ($sql as $sqlLine) {
					$installConf = $dbo->prepare($sqlLine);
					if (!$installConf->execute())
						RIP(installer_t('Error applying initial configuration') . ': ' . implode(',', $installConf->errorInfo()));
				}
			} else {
				RIP(installer_t('Could not find database configuration script')." $sqlPath");
			}
		} catch (PDOException $e) {
			die($e->getMessage());
		}
	} else if ($stage == 'finalize') {
		/**
		 * Look for additional initialization files and perform final tasks
		 */
		foreach ($editions as $ed) // Add editional prefixes as necessary
			if (file_exists("initialize_$ed.php"))
				include("initialize_$ed.php");
	} else {
		// Look for a named SQL file and run it:
		$stagePath = "protected/data/$stage.sql";
		if($stage == 'dummy_data')
			$stageLabels['dummy_data'] = sprintf($stageLabels['dummy_data'], $config['dummy_data'] ? 'insert' : 'delete');
		if ((bool) ((int) $config['dummy_data']) || $stage != 'dummy_data') {
			if ($sqlFile = realpath($stagePath)) {
				$sql = explode('/*&*/', file_get_contents($sqlFile));
				foreach ($sql as $sqlLine) {
					$statement = $dbo->prepare($sqlLine);
					try {
						if (!$statement->execute())
							RIP(installer_tr('Could not {stage}. SQL statement "{sql}" from {file} failed', array('{stage}' => $stageLabels[$stage], '{sql}' => substr(trim($sqlLine), 0, 50) . (strlen(trim($sqlLine)) > 50 ? '...' : ''), '{file}' => $sqlFile)) . '; ' . implode(',', $statement->errorInfo()));
					} catch (PDOException $e) {
						RIP(installer_tr("Could not {stage}", array('{stage}' => $stageLabels[$stage])) . '; ' . $e->getMessage());
					}
				}
				// Hunt for init SQL files associated with other editions:
				foreach ($editions as $ed) {
					if ($sqlFile = realpath("protected/data/$stage-$ed.sql")) {
						$sql = explode('/*&*/', file_get_contents($sqlFile));
						foreach ($sql as $sqlLine) {
							$statement = $dbo->prepare($sqlLine);
							try {
								if (!$statement->execute())
									RIP(installer_tr('Could not {stage}. SQL statement "{sql}" from {file} failed', array('{stage}' => $stageLabels[$stage], '{sql}' => substr(trim($sqlLine), 0, 50) . (strlen($sqlLine) > 50 ? '...' : ''), '{file}' => $sqlFile)) . '; ' . implode(',', $statement->errorInfo()));
							} catch (PDOException $e) {
								RIP(installer_tr("Could not {stage}", array('{stage}' => $stageLabels[$stage])) . '; ' . $e->getMessage());
							}
						}
					}
				}

				if ($stage == 'dummy_data') {
					// Need to update the timestamp fields on all the sample data that has been inserted.
					$dateGen = @file_get_contents(realpath("protected/data/dummy_data_date")) or RIP("Sample data generation date not set.");
					$time = time();
					$time2 = $time*2;
					$timeDiff = $time - (int) trim($dateGen);
					foreach ($dateFields as $table => $fields) {
						foreach ($fields as $field) {
							$dbo->exec("UPDATE `$table` SET `$field`=`$field`+$timeDiff WHERE `$field` IS NOT NULL");
						}
						// Fix timestamps that are in the future.
						/*  
						$ordered = array('lastUpdated','createDate');
						if(count(array_intersect($ordered,$fields)) == count($ordered)) {
							$affected = 0;
							foreach($ordered as $field) {
								$affected += $dbo->exec("UPDATE `$table` SET `$field`=$time2-`$field` WHERE `$field` > $time");
							}
							if($affected)
								$dbo->exec("UPDATE `$table` set `lastUpdated`=`createDate`,`createDate`=`lastUpdated` WHERE `createDate` > `lastUpdated`");
						}
						 */
					}
				}
			} else {
				RIP(installer_t("Could not find installation stage database script") . " $stagePath");
			}
		} else {
			// This is the dummy data stage, and we need to clear out all unneeded files.
			$stageLabels[$stage] = sprintf($stageLabels[$stage], 'remove');
			if ($paths = @require_once(realpath('protected/data/dummy_data_files.php'))) {
				foreach ($paths as $pathClear) {
					if ($path = realpath($pathClear)) {
						if (is_dir($path)) {
							foreach(scandir($path) as $subPath)
								if(!preg_match($noDelPat,$path))
									rrmdir($path.DIRECTORY_SEPARATOR.$subPath);
						} else {
							unlink($path);
						}
					}
				}
			}
		}
	}
	if (in_array($stage, array_keys($stageLabels)) && $stage != 'finalize')
		respond(installer_tr("Completed: {stage}", array('{stage}' => $stageLabels[$stage])));
}

// Translate response messages
foreach (array_keys($stageLabels) as $stage) {
	$stageLabels[$stage] = installer_t($stageLabels[$stage]);
}

// App name:
$config['app'] = addslashes($config['app']);

if (!$silent) {
	// Ad-hoc validation in the no-javascript case
	if (!isset($_GET['stage'])) {
		if (empty($config['adminEmail']) || !preg_match('/^[A-Z0-9._%+-]+@[A-Z0-9.-]+\.[A-Z]{2,4}$/i', $config['adminEmail']))
			addValidationError('adminEmail', 'Please enter a valid email address.');

		if (empty($config['adminPass']))
			addValidationError('adminPass', 'Admin password cannot be blank.');
		else if ($config['adminPass'] != $config['adminPass2'])
			addValidationError('adminPass2', 'Admin passwords did not match.');
	}
}

// Establish database connection
try {
	$dbo = new PDO("mysql:host={$config['dbHost']};dbname={$config['dbName']}", $config['dbUser'], $config['dbPass']);
} catch (PDOException $e) {
	// Database connection failed. Send validation errors.
	foreach (array('dbHost' => 'Host Name', 'dbName' => 'Database Name', 'dbUser' => 'Username', 'dbPass' => 'Password') as $attr => $label) {
		if (empty($_POST[$attr])) {
			addValidationError($attr, installer_tr('{attr}: cannot be blank', array('{attr}' => installer_t($label))));
		} else {
			addValidationError($attr, installer_tr('{attr}: please check that it is correct', array('{attr}' => installer_t($label))));
		}
	}
	respond(installer_t('Database connection error'), htmlentities($e->getMessage()));
}

$complete = isset($_POST['complete']) ? $_POST['complete'] == 1 : False;

if (!$complete)
	outputErrors();

// Install everything all at once:
if (($silent || !isset($_GET['stage'])) && !$complete) {
	// Install core schema/data, modules, and configure:
	foreach (array('core', 'RBAC', 'timezoneData', 'module','config','dummy_data','finalize') as $component)
		installStage($component);
} else if (isset($_GET['stage'])) {
	installStage($_GET['stage']);
}

if (!$complete || $silent) {
	if (!empty($sqlError))
		$errors[] = 'MySQL Error: ' . $sqlError;
	outputErrors();
	respond('Installation complete.');
	if($silent && function_exists('curl_init')) {
		foreach ($sendArgs as $urlKey) {
			$stats[$urlKey] = $config[$urlKey];
			$stats['type'] = 'Silent';
		}
		$ch = curl_init('http://x2planet.com/installs/registry/activity?'.http_build_query($stats));
		curl_setopt($ch,CURLOPT_POST,0);
		curl_setopt($ch,CURLOPT_RETURNTRANSFER,1);
		$gif = curl_exec($ch);
	}
}

// Generate splash page
if (!$silent && $complete):
	foreach ($sendArgs as $urlKey) {
		$stats[$urlKey] = $config[$urlKey];
	}
	?><!DOCTYPE html>
	<html xmlns="http://www.w3.org/1999/xhtml" xml:lang="en" lang="en">
		<head>
			<meta charset="UTF-8" />
			<meta name="language" content="en" />
			<title><?php echo installer_t('Installation Complete'); ?></title>
			<?php $themeURL = 'themes/x2engine'; ?>
			<link rel="stylesheet" type="text/css" href="<?php echo $themeURL; ?>/css/screen.css" media="screen, projection" />
			<link rel="stylesheet" type="text/css" href="<?php echo $themeURL; ?>/css/main.css" />
			<link rel="stylesheet" type="text/css" href="<?php echo $themeURL; ?>/css/form.css" />
			<link rel="stylesheet" type="text/css" href="<?php echo $themeURL; ?>/css/install.css" />
			<link rel="icon" href="images/favicon.ico" type="image/x-icon">
				<link rel="shortcut icon" href="images/favicon.ico" type="image/x-icon" />
				<style type="text/css">
					body {
						background-color:#fff;
						padding-top:50px;
					}
				</style>
				<script type="text/javascript" src="js/jquery-1.6.2.min.js"></script>
				<script type="text/javascript" src="js/backgroundImage.js"></script>
		</head>
		<body>
		<!--<img id="bg" src="uploads/defaultBg.jpg" alt="">-->
			<div id="installer-box" style="padding-top:20px;">
				<h1><?php echo installer_t('Installation Complete!'); ?></h1>
				<div id="install-form" class="wide form">
					<ul>
						<li><?php echo installer_t('Able to connect to database'); ?></li>
						<li><?php echo installer_t('Dropped old X2Engine tables (if any)'); ?></li>
						<li><?php echo installer_t('Created new tables for X2Engine'); ?></li>
						<li><?php echo installer_t('Created login for admin account'); ?></li>
						<li><?php echo installer_t('Created config file'); ?></li>
					</ul>
					<h2><?php echo installer_t('Next Steps'); ?></h2>
					<ul>
						<li><?php echo installer_t('Log in to app'); ?></li>
						<li><?php echo installer_t('Create new users'); ?></li>
						<li><?php echo installer_t('Set up Cron Job to deal with action reminders (see readme)'); ?></li>
						<li><?php echo installer_t('Set location'); ?></li>
						<li><?php echo installer_t('Explore the app'); ?></li>
					</ul>
					<h3><a class="x2-button" href="index.php"><?php echo installer_t('Click here to log in to X2Engine'); ?></a></h3><br />
					<?php echo installer_t('X2Engine successfully installed on your web server!  You may now log in with username "admin" and the password you provided during the install.'); ?><br /><br />
				</div>
				<a href="http://www.x2engine.com"><?php echo installer_t('For help or more information - X2Engine.com'); ?></a><br /><br />
				<div id="footer">
					<div class="hr"></div>
					<!--<img src="images/x2engine_big.png">-->
					Copyright &copy; <?php echo date('Y'); ?><a href="http://www.x2engine.com">X2Engine Inc.</a><br />
					<?php echo installer_t('All Rights Reserved.'); ?>
					<img style="height:0;width:0" src="http://x2planet.com/installs/registry/activity?<?php echo http_build_query($stats); ?>">
				</div>
			</div>
		</body>
	</html>
	<?php
endif;
// Delete install files
foreach(array('install.php','installConfig.php','requirements.php','initialize_pro.php') as $file) 
	if (file_exists($file))
		unlink($file);
// Delete self
unlink(__FILE__);
?>
