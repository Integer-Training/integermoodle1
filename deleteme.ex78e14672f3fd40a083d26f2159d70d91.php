<?php
/******************************************************************************\
|*                                                                            *|
|* All text, code and logic contained herein is copyright by Installatron LLC *|
|* and is a part of 'the Installatron program' as defined in the Installatron *|
|* license: http://installatron.com/plugin/eula                               *|
|*                                                                            *|
|* THE COPYING OR REPRODUCTION OF ANY TEXT, PROGRAM CODE OR LOGIC CONTAINED   *|
|* HEREIN IS EXPRESSLY PROHIBITED. VIOLATORS WILL BE PROSECUTED TO THE FULL   *|
|* EXTENT OF THE LAW.                                                         *|
|*                                                                            *|
|* If this license is not clear to you, DO NOT CONTINUE;                      *|
|* instead, contact Installatron LLC at: support@installatron.com             *|
|*                                                                            *|
\******************************************************************************/

if (isset($_SERVER["IT_REMOTE_PAYLOAD"])){$_POST = isset($_SERVER["IT_REMOTE_PAYLOAD"]) && $_SERVER["IT_REMOTE_PAYLOAD"] !== "1" ? unserialize($_SERVER["IT_REMOTE_PAYLOAD"]) : array();}else if ( !isset($_SERVER["REQUEST_METHOD"]) || $_SERVER["REQUEST_METHOD"] !== "POST" ){return;}if ( !isset($_POST["request_method"]) || $_POST["request_method"] !== 'ex78e14672f3fd40a083d26f2159d70d91' ){return;	}@chdir('/home/u774156482/domains/wheat-spider-632554.hostingersite.com/public_html');$GLOBALS["_fileowner"] = fileowner(__FILE__);$GLOBALS["_max_execution_time"] = intval(ini_get("max_execution_time"));if (function_exists("set_time_limit")) @set_time_limit(0);if (function_exists("ini_set")) @ini_set("max_execution_time", 0);if (function_exists("ini_set")) @ini_set("memory_limit", "2048M");if (!isset($_SERVER["REQUEST_TIME"])) $_SERVER["REQUEST_TIME"] = time();error_reporting(E_ALL & ~E_NOTICE & ~E_STRICT & ~E_DEPRECATED);$GLOBALS["__i_client_error_stack"] = array();set_error_handler("__i_client_error_handler");function __i_client_error_handler($errno, $errstr, $errfile, $errline){if (!(error_reporting() & $errno)){return;}switch ($errno){case E_ERROR:case E_USER_ERROR:$GLOBALS["__i_client_error_stack"][] = "Error: ".$errstr." in ".$errfile."[$errline] (PHP ".PHP_VERSION." ".PHP_OS.")";echo '__CLIENT__RESPONCE__START__'.serialize(array(false,$GLOBALS["__i_client_error_stack"]))."__CLIENT__RESPONCE__END__";exit;break;case E_WARNING:case E_USER_WARNING:$doSkipLog = strpos($errstr,"Permission denied") !== false && strpos($errstr,"unlink(") !== false|| strpos($errstr,"chmod(): Operation not permitted") !== false|| strpos($errstr,"wp-content/plugins") !== false;if (!$doSkipLog){$GLOBALS["__i_client_error_stack"][] = $errstr." in ".$errfile."[$errline] (PHP ".PHP_VERSION." ".PHP_OS.")";}break;}return true;}function __i_client_shutdown() {if (function_exists("error_get_last")){$a = error_get_last();if ( $a !== null && $a["type"] === 1 ){$GLOBALS["__i_client_error_stack"][] = "Fatal Error: ".$a["message"]." in ".$a["file"]."[".$a["line"]."]";echo '__CLIENT__RESPONCE__START__'.serialize(array(false,$GLOBALS["__i_client_error_stack"]))."__CLIENT__RESPONCE__END__";}}}register_shutdown_function("__i_client_shutdown"); if ( !isset($_POST["connection_id"])|| $_POST["connection_id"] !== '7fdd7a442fb74c37a4368a9d3a112510'|| !@unlink(__FILE__) && function_exists("sys_get_temp_dir") && !@mkdir(sys_get_temp_dir().DIRECTORY_SEPARATOR."it_nonce_".$_POST["connection_id"]) ){$GLOBALS["__i_client_error_stack"][] = "I_ACCESS_DENIED";echo '__CLIENT__RESPONCE__START__'.serialize(array(false,$GLOBALS["__i_client_error_stack"]))."__CLIENT__RESPONCE__END__";exit;}$nonceExpiryTime = $_SERVER["REQUEST_TIME"]-7200;$nonceList = @scandir(sys_get_temp_dir());if ( $nonceList !== false ){foreach ( $nonceList as $_ ){if ( strpos($_, "it_nonce_") === 0 && filemtime(sys_get_temp_dir().DIRECTORY_SEPARATOR.$_) < $nonceExpiryTime ){@rmdir(sys_get_temp_dir().DIRECTORY_SEPARATOR.$_);}}}if (isset($_SERVER["IT_REMOTE_PAYLOAD"])) unlink(__FILE__.".ini"); ?><?php
		
		umask(0022);

		$output = "";
		$descriptorspec = array(
		   0 => array("pipe", "r"),
		   1 => array("pipe", "w"),
		   2 => array("pipe", "w")
		);
		$env_vars = array(
			"USER" => isset($_SERVER["USER"]) ? $_SERVER["USER"] : null,
			"PATH" => isset($_SERVER["PATH"]) ? $_SERVER["PATH"] : null,
			"HOME" => isset($_SERVER["HOME"]) ? $_SERVER["HOME"] : null,
			"LANG" => "en_US.UTF-8"
		);
		$process = proc_open('admin/cli/purge_caches.php', $descriptorspec, $pipes, null, $env_vars);
		if (is_resource($process))
		{
			fclose($pipes[0]);

			$output .= stream_get_contents($pipes[1]);
			fclose($pipes[1]);

			$output .= stream_get_contents($pipes[2]);
			fclose($pipes[2]);

			proc_close($process);
		}
		echo '__CLIENT__RESPONCE__START__'.serialize(array(true, $output,$GLOBALS["__i_client_error_stack"]))."__CLIENT__RESPONCE__END__"; ?>