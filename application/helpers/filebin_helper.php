<?php

function format_bytes($size)
{
	$suffixes = array('B', 'KiB', 'MiB', 'GiB', 'TiB' , 'PiB' , 'EiB', 'ZiB', 'YiB');
	$boundary = 2048.0;

	for ($suffix_pos = 0; $suffix_pos + 1 < count($suffixes); $suffix_pos++) {
		if ($size <= $boundary && $size >= -$boundary) {
			break;
		}
		$size /= 1024.0;
	}

	# don't print decimals for bytes
	if ($suffix_pos != 0) {
		return sprintf("%.2f%s", $size, $suffixes[$suffix_pos]);
	} else {
		return sprintf("%.0f%s", $size, $suffixes[$suffix_pos]);
	}
}

// Original source: http://www.phpfreaks.com/forums/index.php?topic=198274.msg895468#msg895468
function rangeDownload($file, $filename, $type)
{
	$fp = @fopen($file, 'r');

	$size	= filesize($file); // File size
	$length = $size;	   // Content length
	$start	= 0;		   // Start byte
	$end	= $size - 1;	   // End byte
	// Now that we've gotten so far without errors we send the accept range header
	/* At the moment we only support single ranges.
	 * Multiple ranges requires some more work to ensure it works correctly
	 * and comply with the spesifications: http://www.w3.org/Protocols/rfc2616/rfc2616-sec19.html#sec19.2
	 *
	 * Multirange support annouces itself with:
	 * header('Accept-Ranges: bytes');
	 *
	 * Multirange content must be sent with multipart/byteranges mediatype,
	 * (mediatype = mimetype)
	 * as well as a boundry header to indicate the various chunks of data.
	 */
	header("Accept-Ranges: 0-$length");
	// header('Accept-Ranges: bytes');
	// multipart/byteranges
	// http://www.w3.org/Protocols/rfc2616/rfc2616-sec19.html#sec19.2
	if (isset($_SERVER['HTTP_RANGE']))
	{
		$c_start = $start;
		$c_end	 = $end;
		// Extract the range string
		list(, $range) = explode('=', $_SERVER['HTTP_RANGE'], 2);
		// Make sure the client hasn't sent us a multibyte range
		if (strpos($range, ',') !== false)
		{
			// (?) Shoud this be issued here, or should the first
			// range be used? Or should the header be ignored and
			// we output the whole content?
			header('HTTP/1.1 416 Requested Range Not Satisfiable');
			header("Content-Range: bytes $start-$end/$size");
			// (?) Echo some info to the client?
			exit;
		}
		// If the range starts with an '-' we start from the beginning
		// If not, we forward the file pointer
		// And make sure to get the end byte if spesified
		if ($range{0} == '-')
		{
			// The n-number of the last bytes is requested
			$c_start = $size - substr($range, 1);
		}
		else
		{
			$range	= explode('-', $range);
			$c_start = $range[0];
			$c_end	 = (isset($range[1]) && is_numeric($range[1])) ? $range[1] : $size;
		}
		/* Check the range and make sure it's treated according to the specs.
		 * http://www.w3.org/Protocols/rfc2616/rfc2616-sec14.html
		 */
		// End bytes can not be larger than $end.
		$c_end = ($c_end > $end) ? $end : $c_end;
		// Validate the requested range and return an error if it's not correct.
		if ($c_start > $c_end || $c_start > $size - 1 || $c_end >= $size)
		{
			header('HTTP/1.1 416 Requested Range Not Satisfiable');
			header("Content-Range: bytes $start-$end/$size");
			// (?) Echo some info to the client?
			exit;
		}
		$start	= $c_start;
		$end	= $c_end;
		$length = $end - $start + 1; // Calculate new content length
		fseek($fp, $start);
		header('HTTP/1.1 206 Partial Content');
		// Notify the client the byte range we'll be outputting
		header("Content-Range: bytes $start-$end/$size");
	}
	header("Content-Length: $length");
	header("Content-disposition: inline; filename=\"".$filename."\"\n");
	header("Content-Type: ".$type."\n");

	// Start buffered download
	$buffer = 1024 * 8;
	while(!feof($fp) && ($p = ftell($fp)) <= $end)
	{
		if ($p + $buffer > $end)
		{
			// In case we're only outputtin a chunk, make sure we don't
			// read past the length
			$buffer = $end - $p + 1;
		}
		set_time_limit(0); // Reset time limit for big files
		echo fread($fp, $buffer);
		flush(); // Free up memory. Otherwise large files will trigger PHP's memory limit.
	}

	fclose($fp);
}

function even_odd($reset = false)
{
	static $counter = 1;

	if ($reset) {
		$counter = 1;
	}

	if ($counter++%2 == 0) {
		return 'even';
	} else {
		return 'odd';
	}
}

// Source: http://hu.php.net/manual/en/function.str-pad.php#71558
// This is a multibyte enabled str_pad
function mb_str_pad($ps_input, $pn_pad_length, $ps_pad_string = " ", $pn_pad_type = STR_PAD_RIGHT, $ps_encoding = NULL)
{
	$ret = "";

	if (is_null($ps_encoding))
		$ps_encoding = mb_internal_encoding();

	$hn_length_of_padding = $pn_pad_length - mb_strlen($ps_input, $ps_encoding);
	$hn_psLength = mb_strlen($ps_pad_string, $ps_encoding); // pad string length

	if ($hn_psLength <= 0 || $hn_length_of_padding <= 0) {
		// Padding string equal to 0:
		//
		$ret = $ps_input;
		}
	else {
		$hn_repeatCount = floor($hn_length_of_padding / $hn_psLength); // how many times repeat

		if ($pn_pad_type == STR_PAD_BOTH) {
			$hs_lastStrLeft = "";
			$hs_lastStrRight = "";
			$hn_repeatCountLeft = $hn_repeatCountRight = ($hn_repeatCount - $hn_repeatCount % 2) / 2;

			$hs_lastStrLength = $hn_length_of_padding - 2 * $hn_repeatCountLeft * $hn_psLength; // the rest length to pad
			$hs_lastStrLeftLength = $hs_lastStrRightLength = floor($hs_lastStrLength / 2);			// the rest length divide to 2 parts
			$hs_lastStrRightLength += $hs_lastStrLength % 2; // the last char add to right side

			$hs_lastStrLeft = mb_substr($ps_pad_string, 0, $hs_lastStrLeftLength, $ps_encoding);
			$hs_lastStrRight = mb_substr($ps_pad_string, 0, $hs_lastStrRightLength, $ps_encoding);

			$ret = str_repeat($ps_pad_string, $hn_repeatCountLeft) . $hs_lastStrLeft;
			$ret .= $ps_input;
			$ret .= str_repeat($ps_pad_string, $hn_repeatCountRight) . $hs_lastStrRight;
			}
		else {
			$hs_lastStr = mb_substr($ps_pad_string, 0, $hn_length_of_padding % $hn_psLength, $ps_encoding); // last part of pad string

			if ($pn_pad_type == STR_PAD_LEFT)
				$ret = str_repeat($ps_pad_string, $hn_repeatCount) . $hs_lastStr . $ps_input;
			else
				$ret = $ps_input . str_repeat($ps_pad_string, $hn_repeatCount) . $hs_lastStr;
			}
		}

	return $ret;
}

function is_cli_client($override = null)
{
	static $is_cli = null;

	if ($override !== null) {
		$is_cli = $override;
	}

	if ($is_cli === null) {
		$is_cli = false;
		// official client uses "fb-client/$version" as useragent
		$clients = array("fb-client", "libcurl", "pyfb", "curl/");
		foreach ($clients as $client) {
			if (isset($_SERVER['HTTP_USER_AGENT']) && strpos($_SERVER['HTTP_USER_AGENT'], $client) !== false) {
				$is_cli =  true;
			}
		}
	}
	return $is_cli;
}

function random_alphanum($min_length, $max_length = null)
{
	$random = '';
	$char_list = "ABCDEFGHIJKLMNOPQRSTUVWXYZ";
	$char_list .= "abcdefghijklmnopqrstuvwxyz";
	$char_list .= "1234567890";

	if ($max_length === null) {
		$max_length = $min_length;
	}
	$length = mt_rand($min_length, $max_length);

	for($i = 0; $i < $max_length; $i++) {
		if (strlen($random) == $length) break;
		$random .= substr($char_list, mt_rand(0, strlen($char_list) - 1), 1);
	}
	return $random;
}

function link_with_mtime($file)
{
	$link = base_url($file);

	if (file_exists(FCPATH.$file)) {
		$link .= "?".filemtime(FCPATH.$file);
	}

	return $link;
}

function include_js($file)
{
	static $included = array();
	if (in_array($file, $included) || $file === null) {
		return "";
	}
	return "<script src=\"".link_with_mtime($file)."\"></script>\n";
}

// kind of hacky, but works well enough for now
function register_js_include($file, $return = false)
{
	static $list = "";
	$list .= include_js($file);
	if ($return) {
		return $list;
	}
}

function include_registered_js()
{
	return register_js_include(null, true);
}

function handle_etag($etag)
{
	$etag = strtolower($etag);
	$modified = true;

	if(isset($_SERVER['HTTP_IF_NONE_MATCH'])) {
		$oldtag = trim(strtolower($_SERVER['HTTP_IF_NONE_MATCH']), '"');
		if($oldtag == $etag) {
			$modified = false;
		} else {
			$modified = true;
		}
	}

	header('Etag: "'.$etag.'"');

	if (!$modified) {
		header("HTTP/1.1 304 Not Modified");
		exit();
	}
}

// Reference: http://php.net/manual/en/features.file-upload.multiple.php#109437
// This is a little different because we don't care about the fieldname
function getNormalizedFILES()
{
	$newfiles = array();
	$ret = array();

	foreach($_FILES as $fieldname => $fieldvalue)
		foreach($fieldvalue as $paramname => $paramvalue)
			foreach((array)$paramvalue as $index => $value)
				$newfiles[$fieldname][$index][$paramname] = $value;

	$i = 0;
	foreach ($newfiles as $fieldname => $field) {
		foreach ($field as $file) {
			// skip empty fields
			if ($file["error"] === 4) {
				continue;
			}
			$ret[$i] = $file;
			$ret[$i]["formfield"] = $fieldname;
			$i++;
		}
	}

	return $ret;
}

// Allow simple checking inside views
function auth_driver_function_implemented($function)
{
	static $result = array();
	if (isset($result[$function])) {
		return $result[$function];
	}

	$CI =& get_instance();
	$CI->load->driver("duser");
	$result[$function] = $CI->duser->is_implemented($function);;

	return $result[$function];
}

function user_logged_in()
{
	$CI =& get_instance();
	$CI->load->model("muser");
	return $CI->muser->logged_in();
}

function send_json_reply($array, $status = "success")
{
	$reply = array();
	$reply["status"] = $status;
	$reply["data"] = $array;

	$CI =& get_instance();
	$CI->output->set_content_type('application/json');
	$CI->output->set_output(json_encode($reply));
}

function static_storage($key, $value = null)
{
	static $storage = array();

	if ($value !== null) {
		$storage[$key] = $value;
	}

	if (!isset($storage[$key])) {
		$storage[$key] = null;
	}

	return $storage[$key];
}

function stateful_client()
{
	$CI =& get_instance();

	if ($CI->input->post("apikey")) {
		return false;
	}

	if (is_cli_client()) {
		return false;
	}

	return true;
}

# vim: set noet:
