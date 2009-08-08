<?php

function h($val) {
	return (htmlentities ($val, ENT_QUOTES, 'UTF-8'));
}

$tlog_arr = array ();
function tlog ($msg) {
	global $tlog_arr;
	$now = microtime (1);
	$tlog_arr[] = array (microtime (1), $msg);
}
tlog ("start");

function tlog_dump () {
	echo ("<div class='noprint'"
	      ."  style='border-bottom: 2px solid red;height:3em'>"
	      ."</div>");
	tlog ("done");
	global $tlog_arr;
	echo ("<div class='noprint tlog_report'>\n");
	echo ("<table>\n");
	echo ("<tr class='tlog_heading'>"
	      ."<th>Total</th>"
	      ."<th>Delta</th>"
	      ."<th>Message</th>"
	      ."</tr>\n");
	$start = $tlog_arr[0][0];
	$n = count ($tlog_arr);
	for ($i = 0; $i < $n; $i++) {
		$arr = $tlog_arr[$i];
		
		$ts = $arr[0];
		$msg = $arr[1];
		
		if ($i < $n - 1)
			$delta = $tlog_arr[$i+1][0] - $ts;
		else
			$delta = 0;
		
		$ts -= $start;
		echo (sprintf ("<tr>"
			       ."<td class='tlog_num'>%.3f</td>"
			       ."<td class='tlog_num'>%.3f</td>"
			       ."<td>%s</td></tr>\n",
			       $ts * 1000,
			       $delta * 1000,
			       h($msg)));
	}
	echo ("</table>\n");
	echo ("</div>\n");
}


function get_secs () {
	global $start_secs;

	$arr = gettimeofday ();
	$now = $arr['sec'] + $arr['usec'] / 1e6;
	return ($now - $start_secs);
}
$start_secs = get_secs ();


$couch_connections = array ();

function couch_connect ($dbname, $host = "localhost", $port = 5984) {
	global $couch_connections;

	$key = sprintf ("%s:%s", $host, $port);
	if (! isset ($couch_connections[$key])) {
		if (($f = fsockopen($host, $port, $errno, $errstr)) == NULL) {
			echo ("db connect error: $errno $errstr");
			return (NULL);
		}
		$couch_connections[$key] = $f;
	}

	$cdb = (object)NULL;
	$cdb->dbname = $dbname;
	$cdb->host = $host;
	$cdb->f = $couch_connections[$key];

	return ($cdb);
}

function couch_call ($cdb, $method, $url, $post_data = NULL) {
	$req = sprintf ("%s %s HTTP/1.1\r\n", $method, $url);
	$req .= sprintf ("Host: %s\r\n", $cdb->host);

	if ($post_data === NULL) {
		$req .= "\r\n";
	} else {
		$req .= sprintf ("Content-Length: %d\r\n\r\n",
				 strlen ($post_data));
		$req .= $post_data;
		$req .= "\r\n";
	}

	fwrite ($cdb->f, $req);

	$content_length = 0;
	$hdrs = array ();
	$chunked = 0;
	while (($hdr = fgets ($cdb->f)) != NULL) {
		$hdrs[] = trim ($hdr);
		if (sscanf ($hdr, "Content-Length: %d", $val) == 1)
			$content_length = $val;
		if (strncmp ($hdr, "Transfer-Encoding: chunked", 26) == 0)
			$chunked = 1;
		if (trim ($hdr) == "")
			break;
	}

	if ($content_length) {
		return (fread ($cdb->f, $content_length));
	}

	if ($chunked) {
		$ret = "";
		while (($chunk_size_hex = fgets ($cdb->f)) != 0) {
			sscanf ($chunk_size_hex, "%x", $n);
			$ret .= fread ($cdb->f, $n);
			fgets ($cdb->f);
		}
		while (($hdr = fgets ($cdb->f)) != "") {
			if (trim ($hdr) == "")
				break;
		}
		return ($ret);
	}

	echo ("couch_call error:<br/>");
	var_dump ($hdrs);
	exit ();
}

function couch_get ($cdb, $id) {
	$json = couch_call ($cdb, "GET",
			    sprintf ("/%s/%s", $cdb->dbname, $id));
	$ret = json_decode ($json);

	if (@$ret->error == "not_found") {
		$ret = (object)NULL;
		$ret->_id = $id;
		return ($ret);
	}
	return ($ret);
}

function couch_update ($cdb, $val) {
	$json = couch_call ($cdb, "PUT",
			    sprintf ("/%s/%s", $cdb->dbname, $val->_id),
			    json_encode ($val));
	return ($json);
}

$cdb = couch_connect ("pace");

tlog ("before get");

$id = "128";
$val = couch_get ($cdb, $id);

var_dump ($val);

$val->count = @$val->count + 1;
$x = couch_update ($cdb, $val);
var_dump ($x);

exit ();

$val = (object)NULL;
$val->foo = "bar1";

$x = couch_put ($cdb, $val);
var_dump ($x);

$ret = couch_get ($cdb, "dc1a0b60a873bee0dd9668a5762cdbe7");
var_dump ($ret);

$ret = couch_get ($cdb, "127");
var_dump ($ret);

echo ("ok");

tlog ("finish");
tlog_dump ();
exit ();



 // Get back document with the id 123
 $resp = $couch->send("GET", "/test/123");
 var_dump($resp);
 // response:
 // string(47) "{"_id":"123","_rev":"2039697587","data":"Foo"}
 // "

 // Delete our "test" database
 $resp = $couch->send("DELETE", "/test/");
 var_dump($resp);
 // response: string(12) "{"ok":true}
 //"

 class CouchSimple
 {
     function CouchSimple($options)
     {
         foreach($options AS $key => $value) {
             $this->$key = $value;
         }
     }

     function send($method, $url, $post_data = NULL)
     {
         $s = fsockopen($this->host, $this->port, $errno, $errstr);

         if(!$s) {
             echo "$errno: $errstr\n";
             return false;
         }

         $request = "$method $url HTTP/1.0\r\nHost: localhost\r\n";

         if($post_data) {
             $request .= "Content-Length: ".strlen($post_data)."\r\n\r\n";
             $request .= "$post_data\r\n";
         } else {
             $request .= "\r\n";
         }
         fwrite($s, $request);

         $response = "";
         while(!feof($s)) {
             $response .= fgets($s);
         }


         list($this->headers, $this->body) = explode("\r\n\r\n", $response);

         return $this->body;
     }
 }



?>
