<?php

# Password user must enter to execute commands
$PASSWORD="password";

# If false, "intf=web" must be set in the HTTP REQUEST for a web interface to be shown.
$DEFAULT_WEB=false;

// Get the client's IP address
function get_ip() {
	if (isset($_SERVER["REMOTE_ADDR"]))
	    return $_SERVER["REMOTE_ADDR"];
	else if (isset($_SERVER["HTTP_X_FORWARDED_FOR"]))
	    return $_SERVER["HTTP_X_FORWARDED_FOR"];
	else if (isset($_SERVER["HTTP_CLIENT_IP"]))
	    return $_SERVER["HTTP_CLIENT_IP"];
}

// Web interface start
function webintf_pre($command, $name, $ip) {
	// TODO: <Insecure: implement challenge-response when you can be bothered.>
	$html = <<<'potato'
<?xml version="1.0" encoding="UTF-8"?>
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Strict//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-strict.dtd">
<html xmlns="http://www.w3.org/1999/xhtml">
	<head>
		<meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
		<meta name="author" content="Mark K Cowan, mark@battlesnake.co.uk"/>
		<meta name="generator" content="GNU nano 2.2.6"/>
		<title>Dynamic DNS interface</title>
		<!-- script language="javascript" type="text/javascript" src="sha1.js"/ -->
		<style type="text/css">
			div { margin-left: auto; margin-right: auto; display: table; }
			table { margin-left: auto; margin-right: auto; }
			table td:first-child { text-align: right; }
			table.status { font-size: 70%; }
			table.status td { padding-right: 1em; }
			table.status tr:first-child { font-weight: bold; }
			table.status tr+tr td:first-child { font-style: italic; }
			table.status td:first-child { text-align: right; }
			table tr td.error { color: #ff0000; font-weight: bold;  }
			tr.data td { border: 1px solid black; }
		</style>
	</head>
	<body><div>
		<h1>Dynamic DNS interface</h1>
		<form action="/" method="post"><table>
			<tr><td colspan="2"><hr/></td></tr>
			<tr><td>Command</td>
				<td>
					<select name="cmd">
						<option value=""></option>
						<option value="create">CREATE</option>
						<option value="update">UPDATE</option>
						<option value="remove">REMOVE</option>
						<option value="status">STATUS</option>
						<option value="status-all">STATUS-ALL</option>
					</select>
			</td></tr>
			<tr><td>Name</td><td><input type="text" name="name" value="$name"/></td></tr>
			<tr><td>Addr</td><td><input type="text" name="ip" value="$ip"/></td></tr>
			<tr><td>Auth</td><td><input type="password" name="auth" value=""/></td></tr>
			<tr><td>HTML output</td><td><input type="checkbox" name="intf" value="web" checked="checked"/></td></tr>
			<tr><td>&nbsp;</td><td><input type="submit" value="Execute"/></td></tr>
			<tr><td colspan="2"><hr/></td></tr>
potato;
	$html = str_replace('$name', "$name", $html);
	$html = str_replace('$ip', "$ip", $html);
	echo $html;
}

// Web interface end
function webintf_post($separator) {
	if ($separator)
		echo("\t\t\t<tr><td colspan=\"2\"><hr/></td></tr>\n");
	echo <<<'potato'
			<tr><td><small>Mark K Cowan,</small></td><td><small>mark@battlesnake.co.uk</small></td></tr>
		</table></form>
	</div></body>
</html>
potato;
}

// Get parameters
$command = $_REQUEST['cmd'];
$name = $_REQUEST['name'];

$ip = $_REQUEST['ip'];

// Get IP of client, if one wasn't specified
if (strlen($ip) == 0)
	$ip = get_ip();

// Was the web interface requested?
$webintf = ($_REQUEST['intf'] == 'web') || $DEFAULT_WEB;

// Web interface, no command specified
if ($webintf && !strlen($command)) {
	webintf_pre($command, $name, $ip);
	webintf_post(0);
}
// Web interface, command specified (auth needed)
else {
	$error = '';
	// Check unsecured password (TODO: Secure it!)
	if ($_REQUEST['auth'] != $PASSWORD) {
		header($_SERVER["SERVER_PROTOCOL"]." 404 Not Found");
		header("Status: 404 Not Found");
		$_SERVER['REDIRECT_STATUS'] = 404;
		$error = "Invalid authentication key";
		goto err;
	}
	// Validate parameters to prevent shell code injection
	if (strlen($command) == 0 || !preg_match('/^[A-Za-z-]+$/', $command)) {
		$error = "Parse error: invalid command \"$command\"";
		goto err;
	}
	if (strlen($ip) > 0 && !filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
		$error = "Parse error: invalid ip \"$ip\"";
		goto err;
	}
	if (strlen($name) > 0 && !preg_match('/^[A-Za-z0-9-]+\.d\.[A-Za-z0-9\.\-]+$/', $name)) {
		$error = "Parse error: invalid name \"$name\"";
		goto err;
	}
err:
	// Error handler
	if (strlen($error))
		if ($webintf) {
			webintf_pre($command, $name, $ip);
			echo("\t\t\t<tr><td colspan=\"2\" class=\"error\">$error</td></tr>\n");
			webintf_post(1);
		}
		else
			echo($error);
	// Success handler
	else {
		// Execute shell command
		$shellcmd = "/usr/local/bin/ddns-update.sh $command $name $ip";
		ob_start();
		$ret = -1;
		system($shellcmd, $ret);
		$result = ob_get_clean();
		// Shell failed to execute command
		if (is_null($result))
			echo("Failed to execute command");
		// Command was executed
		else {
			if ($webintf) {
				webintf_pre($command, $name, $ip);
				if ($command == 'status-all' || $command == 'status') {
					echo("\t\t\t<table class=\"status\">\n");
					echo("\t\t\t<tr><td>Subdomain</td><td>IP address</td><td>Time-to-live</td></tr>\n");
					echo("\t\t\t".
						'<tr><td>'.
							str_replace("\n", "</td></tr>\n\t\t\t<tr class=\"status\"><td>", 
							str_replace("\t", '</td><td>', 
								trim($result))).
						'</td></tr>'.
						"\t\t\t</table>\n"
						);
				}
				else {
					echo("\t\t\t<tr><td>Return value</td><td>$ret</td></tr>\n");
					echo("\t\t\t<tr><td>Output</td><td>$result</td></tr>\n");
				}
				webintf_post(1);
			}
			else
				echo($result);
		}
	}
	echo("\n");
}

?>
