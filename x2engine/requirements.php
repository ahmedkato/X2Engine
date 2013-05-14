<?php

/*****************************************************************************************
 * X2CRM Open Source Edition is a customer relationship management program developed by
 * X2Engine, Inc. Copyright (C) 2011-2013 X2Engine Inc.
 * 
 * This program is free software; you can redistribute it and/or modify it under
 * the terms of the GNU Affero General Public License version 3 as published by the
 * Free Software Foundation with the addition of the following permission added
 * to Section 15 as permitted in Section 7(a): FOR ANY PART OF THE COVERED WORK
 * IN WHICH THE COPYRIGHT IS OWNED BY X2ENGINE, X2ENGINE DISCLAIMS THE WARRANTY
 * OF NON INFRINGEMENT OF THIRD PARTY RIGHTS.
 * 
 * This program is distributed in the hope that it will be useful, but WITHOUT
 * ANY WARRANTY; without even the implied warranty of MERCHANTABILITY or FITNESS
 * FOR A PARTICULAR PURPOSE.  See the GNU Affero General Public License for more
 * details.
 * 
 * You should have received a copy of the GNU Affero General Public License along with
 * this program; if not, see http://www.gnu.org/licenses or write to the Free
 * Software Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA
 * 02110-1301 USA.
 * 
 * You can contact X2Engine, Inc. P.O. Box 66752, Scotts Valley,
 * California 95067, USA. or at email address contact@x2engine.com.
 * 
 * The interactive user interfaces in modified source and object code versions
 * of this program must display Appropriate Legal Notices, as required under
 * Section 5 of the GNU Affero General Public License version 3.
 * 
 * In accordance with Section 7(b) of the GNU Affero General Public License version 3,
 * these Appropriate Legal Notices must retain the display of the "Powered by
 * X2Engine" logo. If the display of the logo is not reasonably feasible for
 * technical reasons, the Appropriate Legal Notices must display the words
 * "Powered by X2Engine".
 *****************************************************************************************/

//////////////////////////////
// X2CRM Requirements Check //
//////////////////////////////

$standalone = False;

if (!function_exists('installer_t')) {
	$standalone = True;

	// Declare the function since the script is not being used from within the installer
	function installer_t($msg) {
		return $msg;
	}

	// Get PHP info
	$phpInfoContent = array();
	ob_start();
	phpinfo();
	preg_match('%^.*(<style[^>]*>.*</style>).*<body>(.*)</body>.*$%ms', ob_get_contents(), $phpInfoContent);
	ob_end_clean();
}

/**
 * Test the consistency of the $_SERVER global.
 * 
 * This function, based on the similarly-named function of the Yii requirements 
 * check, validates several essential elements of $_SERVER
 * 
 * @author Qiang Xue <qiang.xue@gmail.com>
 * @link http://www.yiiframework.com/
 * @copyright Copyright &copy; 2008-2011 Yii Software LLC
 * @license http://www.yiiframework.com/license/
 * @global string $thisFile
 * @return string 
 */
function checkServerVar() {
	global $thisFile;
	$vars = array('HTTP_HOST', 'SERVER_NAME', 'SERVER_PORT', 'SCRIPT_NAME', 'SCRIPT_FILENAME', 'PHP_SELF', 'HTTP_ACCEPT', 'HTTP_USER_AGENT');
	$missing = array();
	foreach ($vars as $var) {
		if (!isset($_SERVER[$var]))
			$missing[] = $var;
	}
	if (!empty($missing))
		return installer_t('$_SERVER does not have {vars}.', array('{vars}' => implode(', ', $missing)));
	if (!isset($thisFile))
		$thisFile = __FILE__;
	if (realpath($_SERVER["SCRIPT_FILENAME"]) !== realpath($thisFile))
		return installer_t('$_SERVER["SCRIPT_FILENAME"] must be the same as the entry script file path.');

	if (!isset($_SERVER["REQUEST_URI"]) && isset($_SERVER["QUERY_STRING"]))
		return installer_t('Either $_SERVER["REQUEST_URI"] or $_SERVER["QUERY_STRING"] must exist.');

	if (!isset($_SERVER["PATH_INFO"]) && strpos($_SERVER["PHP_SELF"], $_SERVER["SCRIPT_NAME"]) !== 0)
		return installer_t('Unable to determine URL path info. Please make sure $_SERVER["PATH_INFO"] (or $_SERVER["PHP_SELF"] and $_SERVER["SCRIPT_NAME"]) contains proper value.');

	return '';
}

$canInstall = True;
$curl = true; // 
$tryAccess = true; // Attempt to access the internet from the web server.
$reqMessages = array_fill_keys(array(1, 2, 3), array()); // Severity levels
$rbm = installer_t("required but missing");

//////////////////////////////////////////////
// TOP PRIORITY: BIG IMPORTANT REQUIREMENTS // 
//////////////////////////////////////////////
// Check for a mismatch in directory ownership. Skip this step on Windows 
// and systems where posix functions are unavailable; in such cases there's no 
// reliable way to get the UID of the actual running process.
$uid = array_fill_keys(array('{id_own}', '{id_run}'), null);
$uid['{id_own}'] = fileowner(realpath(dirname(__FILE__)));
if(function_exists('posix_geteuid')){
	$uid['{id_run}'] = posix_geteuid();
	if($uid['{id_own}'] !== $uid['{id_run}']){
		$reqMessages[3][] = strtr(installer_t("PHP is running with user ID={id_run}, but this directory is owned by the system user with ID={id_own}."), $uid);
	}
}
// Check that the directory is writable. Print an error message one way or another.
if (!is_writable(realpath('.')) || !is_writable(realpath(__FILE__))) {
	$reqMessages[3][] = installer_t("This directory is not writable by PHP processes run by the webserver.");
}

// Check PHP version
if (!version_compare(PHP_VERSION, "5.3.0", ">=")) {
	$reqMessages[3][] = installer_t("Your server's PHP version") . ': ' . PHP_VERSION . '; ' . installer_t("version 5.3 or later is required");
}
// Check $_SERVER variable meets requirements of Yii
if (($message = checkServerVar()) !== '') {
	$reqMessages[3][] = installer_t($message);
}
// Check for existence of Reflection class
if (!class_exists('Reflection', false)) {
	$reqMessages[3][] = '<a href="http://php.net/manual/class.reflectionclass.php">PHP reflection class</a>: ' . $rbm;
} else if (extension_loaded("pcre")) {
	// Check PCRE library version
	$pcreReflector = new ReflectionExtension("pcre");
	ob_start();
	$pcreReflector->info();
	$pcreInfo = ob_get_clean();
	$matches = array();
	preg_match("/([\d\.]+) \d{4,}-\d{1,2}-\d{1,2}/", $pcreInfo, $matches);
	$thisVer = $matches[1];
	$reqVer = '7.4';
	if (version_compare($thisVer, $reqVer) < 0) {
		$reqMessages[3][] = strtr(installer_t("The version of the PCRE library included in this build of PHP is {thisVer}, but {reqVer} or later is required."), array('{thisVer}' => $thisVer, '{reqVer}' => $reqVer));
	}
} else {
	$reqMessages[3][] = '<a href="http://www.php.net/manual/book.pcre.php">PCRE extension</a>: ' . $rbm;
}
// Check for SPL extension
if (!extension_loaded("SPL")) {
	$reqMessages[3][] = '<a href="http://www.php.net/manual/book.spl.php">SPL</a>: ' . $rbm;
}
// Check for MySQL connecter
if (!extension_loaded('pdo_mysql')) {
	$reqMessages[3][] = '<a href="http://www.php.net/manual/ref.pdo-mysql.php">PDO MySQL extension</a>: ' . $rbm;
}
// Check for CType extension
if (!extension_loaded("ctype")) {
	$reqMessages[3][] = '<a href="http://www.php.net/manual/book.ctype.php">CType extension</a>: ' . $rbm;
}
// Check for multibyte-string extension
if (!extension_loaded("mbstring")) {
	$reqMessages[3][] = '<a href="http://www.php.net/manual/book.mbstring.php">Multibyte string extension</a>: ' . $rbm;
}
// Check for JSON extension:
if (!extension_loaded('json')) {
	$reqMessages[3][] = '<a href="http://www.php.net/manual/function.json-decode.php">json extension</a>: ' . $rbm;
}
// Miscellaneous functions:
$requiredFunctions = array(
	'mb_regex_encoding'
);
foreach($requiredFunctions as $function) {
	if(!function_exists($function))
		$reqMessages[3][] = installer_t('The following required PHP function is missing or disabled:'). " $function";
}

///////////////////////////////////////////////////////////
// MEDIUM-PRIORITY: IMPORTANT FUNCTIONALITY REQUIREMENTS //
///////////////////////////////////////////////////////////
// Check remote access methods
$curl = extension_loaded("curl") && function_exists('curl_init') && function_exists('curl_exec');
if (!$curl) {
	$curlMissingIssues = array(
		installer_t('Time zone widget will not work'),
		installer_t('Contact views may be inaccessible'),
		installer_t('Google integration will not work'),
		installer_t('Built-in error reporter will not work')
	);
	$reqMessages[2][] = '<a href="http://php.net/manual/book.curl.php">cURL</a>: ' . $rbm . '. ' . installer_t('This will result in the following issues:') . '<ul><li>' . implode('</li><li>', $curlMissingIssues) . '</li></ul>';
}
if (!(bool) (@ini_get('allow_url_fopen'))) {
	if (!$curl) {
		$tryAccess = false;
		$reqMessages[2][] = installer_t('The PHP configuration option "allow_url_fopen" is disabled in addition to the CURL extension missing. This means there is no possible way to make HTTP requests, and thus software updates will not work.');
	} else
		$reqMessages[2][] = installer_t('The PHP configuration option "allow_url_fopen" is disabled. CURL will be used for making all HTTP requests during updates.');
}
if ($tryAccess) {
	if (!(bool) @file_get_contents('http://google.com')) {
		$ch = curl_init('http://google.com');
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
		curl_setopt($ch, CURLOPT_POST, 0);
		$response = (bool) @curl_exec($ch);
		if (!$response) {
			$reqMessages[2][] = installer_t('This server is effectively cut off from the internet; (1) no outbound network route exists, or (2) local DNS resolution is failing, or (3) this server is behind a firewall that is preventing outbound requests. Software updates will not work.');
		}
	}
}
// Check the ability to make database backups during updates:
$canBackup = function_exists('proc_open');
if ($canBackup) {
	try {
		$ret = 0;
		$result = exec('mysqldump --help', $ret);
		if ($ret !== 0) {
			$result = exec('mysqldump.exe --help',$ret);
			$canBackup = $ret !== 0;
		}
	} catch (Exception $e) {
		$canBackup = false;
	}
}
if(!$canBackup) {
	$reqMessages[2][] = installer_t('The function proc_open and/or the "mysqldump" and "mysql" command line utilities are unavailable on this system. X2CRM will not be able to automatically make a backup of its database during software updates, or automatically restore its database in the event of a failed update.');
}
// Check the session save path:
$ssp = ini_get('session.save_path');
if (!is_writable($ssp)) {
	$reqMessages[2][] = strtr(installer_t('The path defined in session.save_path ({ssp}) is not writable. Uploading files via the media module will not work.'), array('{ssp}' => $ssp));
}

////////////////////////////////////////////////////////////
// LOW PRIORITY: MISCELLANEOUS FUNCTIONALITY REQUIREMENTS //
////////////////////////////////////////////////////////////
// Check the availability of email delivery messages.
$canDo = array();
$canDo['phpmail'] = @ini_get('sendmail_path') && function_exists('mail');
if ($canDo['phpmail']) {
	// Check for valid, existing sendmail_path
	$smpath = explode(' ', ini_get('sendmail_path'));
	$smpath = $smpath[0];
	$canDo['phpmail'] = is_executable($smpath);
}
$canDo['shell'] = function_exists('escapeshellcmd') && function_exists('escapeshellarg') && function_exists('popen');
if (function_exists('is_executable')) {
	$canDo['sendmail'] = is_executable('/usr/sbin/sendmail');
	$canDo['qmail'] = is_executable('/var/qmail/bin/sendmail');
} else {
	$canDo['sendmail'] = false;
	$canDo['qmail'] = false;
}
if (!($canDo['phpmail'] || (($canDo['sendmail'] || $canDo['qmail'] ) && $canDo['shell']))) {
	$reqMessages[1][] = installer_t("No methods for sending email are available on this server. As a result of this, none of X2CRM's email-related functionality will work unless using the SMTP method of delivery.");
} else {
	if (!($canDo['shell'] && ($canDo['sendmail'] || $canDo['qmail']))) {
		$reqMessages[1][] = installer_t('The "sendmail" and "qmail" methods for email delivery cannot be used on this server.');
	}
	if (!$canDo['phpmail'])
		$reqMessages[1][] = installer_t('The "PHP Mail" method will not work because E-mail delivery in PHP is disabled on this webserver.');
}
// Check for Zip extension
if (!extension_loaded('zip')) {
	$reqMessages[1][] = '<a href="http://php.net/manual/book.zip.php">Zip</a>: ' . $rbm . '. ' . installer_t('This will result in the inability to import and export custom modules.');
}
// Check for fileinfo extension
if (!extension_loaded('fileinfo')) {
	$reqMessages[1][] = '<a href="http://php.net/manual/book.fileinfo.php">Fileinfo</a>: ' . $rbm . '. ' . installer_t('Image previews and MIME info for uploaded files in the media module will not be available.');
}
// Check for GD exension
if (!extension_loaded('gd')) {
	$reqMessages[1][] = '<a href="http://php.net/manual/book.image.php">GD</a>: ' . $rbm . '. ' . installer_t('Security captchas and will not work, and the media module will not be able to detect or display the dimensions of uploaded images.');
}

if ($standalone) {
	echo "<html><header><title>X2CRM System Requirements Check</title>{$phpInfoContent[1]}</head><body>";
	echo '<div style="width: 680px; border:1px solid #DDD; margin: 25px auto 25px auto; padding: 20px;font-family:sans-serif;">';
}

$hasMessages = array_reduce($reqMessages, function($count, $arr) {
			return $count || (bool) count($arr);
		});
$canInstall = !(bool) count($reqMessages[3]);

if (!$canInstall) {
	echo '<div style="width: 100%; text-align:center;"><h1>' . installer_t('Cannot install X2CRM') . "</h1></div>\n";
	echo "<strong>" . installer_t('Unfortunately, your server does not meet the minimum system requirements for installation;') . "</strong><br />";
} else if ($hasMessages) {
	echo '<div style="width: 100%; text-align:center;"><h1>' . installer_t('Note the following:') . '</h1></div>';
} else if ($standalone) {
	echo '<div style="width: 100%; text-align:center;"><h1>' . installer_t('This webserver can run X2CRM!') . '</h1></div>';
}

$severityClasses = array(
	1 => 'minor',
	2 => 'major',
	3 => 'critical'
);
$severityStyles = array(
	1 => 'color:black',
	2 => 'color:#CF5A00',
	3 => 'color: #DD0000'
);

if ($hasMessages) {
	echo "\n<ul>";
	foreach ($reqMessages as $severity => $messages) {
		foreach ($messages as $message) {
			echo "<li style=\"{$severityStyles[$severity]}\">$message</li>";
		}
	}
	echo "</ul>\n";
	echo "<div style=\"text-align:center;\">( Severity legend: <strong>" . array_reduce(array_keys($severityStyles), function($str, $severity) use($severityClasses,$severityStyles) {
				return $str . "<span style=\"{$severityStyles[$severity]}\">{$severityClasses[$severity]}</span>&nbsp;";
			}) . "</strong>)<br />\n";
	if ($canInstall)
		echo '<br />'.installer_t("All other essential requirements were met.").'&nbsp;';
	echo '</div><br />';
}


if ($standalone) {
	$imgData = 'iVBORw0KGgoAAAANSUhEUgAAAGkAAAAfCAYAAADk+ePmAAAACXBIWXMAAAsTAAALEwEAmpwYAAAAGXRFWHRTb2Z0d2FyZQBBZG9iZSBJbWFnZVJlYWR5ccllPAAADMlJREFUeNrs' .
			'W3lwVGUS/82RZBISyMkRwhESIISjEBJAgWRQbhiuci3AqsUVarfcWlZR1xL+2HJry9oqdNUtj0UFQdSwoELCLcoKBUgOi3AkkkyCYi4SSELOSTIzyWz3N/Ne3nszCVnd2qHKNNU1' .
			'731nf91fd/++7wUdiI4cOTKIfp4hfoJ4NPrJ39RAnEn8hsViuaLzGOhMZGTk1Pj4eAwZMqRfRX6mtrY2VFRUoKSkhI1lNrIHsYFmzJwpGnS5XP1a8jMFmUxISEyEwWgML7p+PVPP' .
			'IS4hIQGurq5fFNtaW/Hujh2q9zdef/2+knHUyJEwmUyj2ZNGh0dE/OI8qLy8HMOHD5fXze+JtHvvNz0EBweDjSSs5g86c+Ys6uvrSFlxuHbtKjZt2oTKykrk5OQI4Tg2r1mzBm++' .
			'+SY2b96MnNxcXLvqbnfw4EFMnjIFIdSO29fX18NsNnMcF/14zClTJuPEiRPyu9mcLr/zPOnUXlq71WoV5dkXs2VZtm/fjhdeeEGU79y5U8jgDzL6Mw/VkYEoHyJ1Rqp4tpKCT548' .
			'iY0bNwojHTp0COWUQF0eGa+SgZhabTZqXw8O0xkZGVi/fj0p9ppoW8HKT08XXsH9Fy9eLPpwO1ubTZQz79q1C7GxsfLalf0kWWLJ02rr6pBLm2MRjeMPPbloTr3kSf7gSlJqakqK' .
			'eK4nZbi6XEgkxZuCgtw7nATkZ/4tsZZg0qRJiKTQzMZKm5uGivIK0e8QeVVdbR2VzRXvCWPGiP6lpOizZ84I71m3dq2YT6rjdhHh4bIsPIeyLnbYMGFERlnKfv9v9rsnSUimoqKS' .
			'EyRxEOrq6oU8VZVVwls4X5rIqzgErVy1Cv+kXc27K4WMW0W7nxW5YuVKGZmyB0rr4ecFCxeqoC3X5eXlqdop63helkUgLDLM7t27sXLlKr/piGc1UKh4iVEE76Sfy699/A0iBgYj' .
			'elDwPdtyDikoKBC/Tc1NWEjKDAsLE4Jdzs93ly1YCKPRAIfTKZBOBBmMhZaeuX3N7du4fPkympqahPECjEaM9KzHZArGxW8u4vbtGsTEDBbti4uKEBYaJp5HKtbNctwoLZVl4TKj' .
			'MQDFxUVYunTp/0Q/P4VrqqvBh1nXHAoTP5feOZCHzDNFCA0JxG9WPABL2rhe2xcWFqKqqgoLFiy4bw+Uez/8EJYVK4S3+osYKLnDXZdvVy7+sZaSrQMGgx4GvU5wUnyMV7t/5/2A' .
			'Q18XYUjUAPx4qxE7My+h8nYTfrtmeo+T2yj5J09I7nFuf9O3FBKXL7dg6NBhfpWRHcoNwV2+IXheYRXe+TRPeEdocCAGBAfg2ccfxNTx3VdH331fi7f25yJ1Yizyi25BT4a0Ozph' .
			'a3fgVm0z3iYP+/rbm2i22eU+YTTevJTReOrRsaq5z58/j9ra2h4FnjNnDqKjo1FEIYtZIg5PISEh8ruyXuqjJZ6rrKwM5cQSjU9KQpKHH5o9W4yRnZMt6qRypszMTLkPh8xp06ap' .
			'xpbqeV6eX9vHF62ifNtTVurVk9YtmoiC0hpcKq6Wy/YevYLxo+YhKNCI8ppG/CMjB7HRYbCS14WGBJEhA5GSTMk8fTzWbvscza1u4xgN+u5Q0uHE8QulOJdfhoyXV2NoVKhbcefO' .
			'UQ4o7nEhycnJBNmjcP36dRzOypLLdfSPw5JEynqpj0T5+ZewL2MfARTvzcBGqZg+HUnjkwRQUI5joPPauHHjxXOWQuG8OZJeeVWAG4mk+gkTJggjsX6z7mGkNavXwNnZ6RM46CVP' .
			'6olffOIh4UVymCIPyThRQL92fHjkKoVCHToFVKTfzi4MjQ7FH9fNwp4jl2HQ6RAeGiR4+OAwpE8bJb8zc/jMOFkgEn5P3qz1fdFOg7ROnfqCPPCOLLOq3tOH+fz5c3iLDsaSgVjB' .
			'rEiJpffOrk6f88jja8J2VlamSmfac05f1uaCy6f+BYBxe1LPg4SYjNiyfgZez8jtTmbkXaUV9RTa9ALNcf6JIyPcbW7HK08vBDkZrlprMHBAkNznb5vnY+KYaFwuvoU/7zgrlzeR' .
			'p7GxHM5OsSCJtm3bhrHj1OCDz1HOTqeqHSuWFXXk8GH8esMGWTFK4vXV0dnnX/v2de9c8gyG54GBge7t6nZJtDMU9+jD1zgq3XjmPnXqFB6ZP18gTi/lk8zafnv37vVs7G7qpPX3' .
			'ZAd9t7V75hkTh2Hp7ARVRwflHfac+kYbhpH3dNid+OvvH0ZQAOckBx5fPBEbLFPxq/nJMFP+mZQQg7b2drFnOCdJHBJkFMb2Ugi92+12FTucDrdMinZzPciUc0yJ1epV7/Ks76sv' .
			'vxSIjWk6hbTlFouY10nwng0vmJ6NAQHda/dx+lfKyeMEe8LcB7s+8KBmV699hEEorGnXJm0+L5YPs31AL6vN41B44w6FOad7yxG1dxDyo4U2kgdtsDyAkUMGotXmVoQlLVEoQafT' .
			'iTYdJEgrIcXDZ60UPgPkcceNjBI7iGVQSsH5qeDaNZUMywhtaXf4iBEj5Gc6TuCZLVvUSnG5xNhl5eXda1m9Gh0d9j5dyag9Sf3OIIdBw4ULF8R5qrTEijEJiV6bTduPQUSnIv9E' .
			'RkXhwQcf6jHEe24c7h0zTUEGLJgZj6yzJZ796TYU2QBrF0/G7Kkj0NzSClnV9KN06eraVnxyolCERFOQ0X0jEBSAFeYk8jy7WwaFUtgzvDYKhaj2jg6V8qIIQc2aNQvZ2dki8V+5' .
			'ckVV7/Ksj71MotjY4QRe2sXzsaNHcfzYMdU8zz//J8QnjPHh3d56WrZ8uTAS0/79+/Hi1m1eeUzbL0sBeiSAMZvQZO/AgXfxPbiipglf5dxU5RkmJ4W8xBGRaGltFR7hq29e4S3s' .
			'+DwfdxraqL1LcKDRgKfXzyIvc1HodIp2fdnZop0mni1ZukwOO58eOKCG3C54jc1Kk2SDj2kNBoPPOrmPgviC2DxvnnhmSJ+bmyPKvGTu49q0DBf65kltFOLeO3gFkYNCRA7S0msf' .
			'X8SLG2YSLNd71X1+2oqz+RWqstHDBmELnbdCQ4zCuMpdI9HWrVspdGjzoMOtYI2VIiIjYDbPw4kTx0UIamxoUNVzH1YcX/0wXbp0CUm0e5lmzJyByZMnCc+VPELqo81KvvTEyl2y' .
			'ZClyyJM55/Eve7c0F9dr++3Zs0dcdSmJ04FvO7ju7UktdAjd/lGuyC0l5XUCgg8KNaljc4MNxy5879X3/cyrOErlfJCV2JI2Hi//YT5MBKpaODyqdk23UjhU2ii/Kdlud7jbasIZ' .
			'l6WZzfIO/pJAgraevz3JNySnT8seFhERidjhcQgbONCrjxcI8OFJLnFHaMLiJUvkMxrfmvfmSRw5tGtjdOfTBuiDJ310vBBl1U0YHDnAHQqMeoLNHXg4NR6nc7+X2x2/cIMQXDQS' .
			'hrsvSfO+q8YX2T+oxkqIi6Rc4MCOz3IISXXH38fmj/PyJA4d/N1IG1qYvYAByc836IsWL6GD6icCFmvr09LSxWcL6dD6/nvvYhEplr/OeoEET5++5KQuafz0dJyh8e+SBynn7/Lh' .
			'SUUEMji3KonlCFYciL2BQw8x88BXxfi26DZiY8JQeaeZDqAmNLZ04KXfmckYg1BaXgtr2V25/Ssf5eCNZx+mfKPD259dVl0Fidtta7VgLfGFbHNLi8pKyjONRPzJgD1GpU+F/Cmp' .
			'qcIQVVWVXvX8yWPtuvU0bob7rEfI8ZoGPWr7aL9O+NKT+xzk8lztrMbuD3b1WC/R31991Wuc5557HrFxcf/djcMpAgn7ThWJBG8tq0fUoGDKR2146tEUJMaF425DIx57ZCyFLYM8' .
			'YIvNgbc+zceNigbcrGoUl7N9Yf4c4T6V955g+aLXVzul3Ct93IFJdSmpKXjyyY0+7/K0h9Te5vF1o8A8cdJE8bX4p9w46D1r87pxYATNnyoSPXdSSvrLrmykJI/ApaIqCnWhaKYQ' .
			'NyE+BptWPYA7tXUyXLz+Qz2+KaiB3elCOx1oOZytmDsWe49dpdBo6NNN7/vblqGWwkRNTTX0vbSLjomBTm8QIcXpsMtw2qGBrhXlZQRiAnusNxJ642ukGzduwGG3y4bhz+VjE8cK' .
			'eN7e3qGaR5pbOX5IyACEE2jhHCORso+yXimTL/IlJ1Ml9WMj3R05Oj6cP5apdi0peEBwiIinep1e/uVbA0ZZqjMUJc7AgAAvtNKbUGoY36nOI/0kE9/Ss2Uy6+tqn4gZrP7L1S6y' .
			'foOjqU8D2Qh62jxXLmro3t6v5Z9BfI9ot3c0sJFeamluXqXX68PDwyMoNhr6tXOfGOh2jQBZz+g8d16j+IxFbOY/wOgn/xJf9HY6nTfZQBaLJUunrPT88f7UfjX5nRr4f1NIL/8R' .
			'YABtitvxQEn6dgAAAABJRU5ErkJggg==';
	echo '<img style="display:block;margin-left:278px;float:none;" src="data:image/png;base64,'.$imgData.'"><br /><br />';
	echo $phpInfoContent[2];
	echo '</div></body></html>';
}
?>
