<?php

// Copyright (C) 2008-2018 Lonnie Abelbeck
// This is free software, licensed under the GNU General Public License
// version 3 as published by the Free Software Foundation; you can
// redistribute it and/or modify it under the terms of the GNU
// General Public License; and comes with ABSOLUTELY NO WARRANTY.

// functions.php for AstLinux
// 03-25-2008
// 04-02-2008, Added parseRCconfig()
// 04-03-2008, Added getETHinterfaces()
// 04-04-2008, Added getVARdef()
// 04-10-2008, Added getTITLEname()
// 08-20-2008, Added asteriskCMD()
// 09-06-2008, Added restartPROCESS()
// 12-12-2009, Added systemSHUTDOWN()
// 01-12-2012, Added asteriskURLrepo()
// 01-04-2014, Added statusPROCESS()
// 08-12-2017, Added is_IPV6addr()
// 08-15-2017, Added expandIPV6addr()
// 08-19-2017, Added getAstDB()
// 08-19-2017, Modify compressIPV6addr() to accept /xx prefix lengths
// 09-02-2017, Updates to includeTOPICinfo()
//
// System location of prefs file
$KD_PREFS_LOCATION = '/mnt/kd/webgui-prefs.txt';
$ONLINE_DOCS_URL = 'https://doc.astlinux-project.org?do=export_xhtmlbody';

// Start (or rejoin) browser session
session_start();

// Function: putHtml
// Put html string, with new-line
//
function putHtml($arg) {
  echo $arg, "\n";
}

// Function: putText
// Put text string (htmlspecialcharacters), with new-line
//
function putText($arg) {
  echo htmlspecialchars($arg), "\n";
}

// Function: shell
// Like system() without output buffer flush
//
function shell($cmd, &$return_val) {

  return(@exec($cmd, $shell_out, $return_val));
}

// Function: restartPROCESS
//
function restartPROCESS($process, $ret_good, $ret_fail, $start = 'start', $wait = '1') {
  $result = $ret_fail;
  $path = getenv('PATH');
  $pathOK = ($path !== FALSE && $path !== '');

  $cmd = 'cd /root';
  if ($process === 'pppoe') {
    if (is_executable('/usr/sbin/pppoe-restart')) {
      $cmd .= ';/usr/sbin/gen-rc-conf';
      $cmd .= ';/usr/sbin/pppoe-restart >/dev/null 2>/dev/null';
    } else {
      $cmd .= ';/usr/sbin/pppoe-stop >/dev/null 2>/dev/null';
      $cmd .= ';sleep 2';
      $cmd .= ';/usr/sbin/pppoe-start >/dev/null 2>/dev/null';
    }
  } elseif ($start === 'start') {
    $cmd .= ';service '.$process.' stop >/dev/null 2>/dev/null';
    $cmd .= ';sleep '.$wait;
    $cmd .= ';/usr/sbin/gen-rc-conf';
    $cmd .= ';service '.$process.' '.$start.' >/dev/null 2>/dev/null';
  } elseif ($start === 'reload') {
    $cmd .= ';service '.$process.' '.$start.' >/dev/null 2>/dev/null';
  } elseif ($start === 'apply') {
    $cmd .= ';/usr/sbin/gen-rc-conf';
  } elseif ($process === 'iptables') {
    $cmd .= ';/usr/sbin/gen-rc-conf';
    $cmd .= ';service iptables restart >/dev/null 2>/dev/null';
  } else {
    $cmd .= ';service '.$process.' stop >/dev/null 2>/dev/null';
    $cmd .= ';sleep '.$wait;
    $cmd .= ';/usr/sbin/gen-rc-conf';
    if ($process === 'openvpn' || $process === 'openvpnclient' ||
        $process === 'racoon' || $process === 'ipsec' ||
        $process === 'pptpd' || $process === 'wireguard') {
      $cmd .= ';service iptables restart >/dev/null 2>/dev/null';
    }
    $cmd .= ';service '.$process.' '.$start.' >/dev/null 2>/dev/null';
  }

  if ($pathOK) {
    putenv('PATH='.$path.':/sbin:/usr/sbin');
  }
  shell($cmd, $status);
  if ($pathOK) {
    putenv('PATH='.$path);
  }
  if ($status == 0) {
    $result = $ret_good;
  }
  return($result);
}

// Function: statusPROCESS
//
function statusPROCESS($process) {

  $str = '';
  $path = '/var/run/';
  $running = ' has Restarted and is Running';
  $stopped = ' is Stopped';

  if ($process === 'asterisk' || $process === 'prosody' || $process === 'slapd' ||
      $process === 'kamailio' || $process === 'stubby') {
    $path .= $process.'/';
  } elseif ($process === 'dynamicdns') {
    if (is_file($path.'ddclient.pid')) {
      $str = $running;
    } else {
      $str = $stopped;
    }
  } elseif ($process === 'stunnel') {
    if (is_file($path.$process.'/server.pid') || is_file($path.$process.'/client.pid')) {
      $str = $running;
    } else {
      $str = $stopped;
    }
  } elseif ($process === 'avahi') {
    if (is_file($path.'avahi-daemon/pid')) {
      $str = $running;
    } else {
      $str = $stopped;
    }
  } elseif ($process === 'ntpd') {
    if (is_file($path.'chronyd.pid')) {
      $str = $running;
    } else {
      $str = $stopped;
    }
  } elseif ($process === 'ipsec') {
    if (is_file($path.'charon.pid')) {
      $str = $running;
    } else {
      $str = $stopped;
    }
  } elseif ($process === 'ups') {
    if (is_file($path.'upsmon.pid')) {
      $str = $running;
    } else {
      $str = $stopped;
    }
  } elseif ($process === 'zabbix') {
    if (is_file($path.'zabbix_agentd.pid')) {
      $str = $running;
    } else {
      $str = $stopped;
    }
  } elseif ($process === 'dnscrypt') {
    if (is_file($path.'dnscrypt-proxy.pid')) {
      $str = $running;
    } else {
      $str = $stopped;
    }
  } elseif ($process === 'failover') {
    if (is_file($path.'wan-failover.pid')) {
      $str = $running;
    } else {
      $str = $stopped;
    }
  } elseif ($process === 'wireguard') {
    if (is_file('/var/lock/wireguard.lock')) {
      $str = $running;
    } else {
      $str = $stopped;
    }
  }
  if ($str === '') {
    if (is_file($path.$process.'.pid')) {
      $str = $running;
    } else {
      $str = $stopped;
    }
  }
  return($str);
}

// Function: systemSHUTDOWN
//
function systemSHUTDOWN($myself, $result) {
  $count_down_secs = 30;

  shell('/sbin/poweroff -d4 >/dev/null 2>/dev/null &', $status);
  if ($status == 0) {
    header('Location: '.$myself.'?count_down_secs='.$count_down_secs.'&shutdown&result='.$result);
    exit;
  }
}

// Function: systemREBOOT
//
function systemREBOOT($myself, $result, $setup = FALSE) {
  global $global_prefs;

  $count_down_secs = 130;

  if (($adjust = getPREFdef($global_prefs, 'system_reboot_timer_adjust')) !== '') {
    $count_down_secs += (int)$adjust;
  }

  $arch = system_image_arch();
  if ($arch === 'net4801' || $arch === 'wrap') {
    $count_down_secs += 20;
  }

  $cmd = '/sbin/kernel-reboot';
  if (! is_executable($cmd)
    || ((getPREFdef($global_prefs, 'system_reboot_classic_full') === 'yes') && $arch !== 'genx86_64-vm')
    || ((getPREFdef($global_prefs, 'system_reboot_vm_classic_full') !== 'no') && $arch === 'genx86_64-vm')) {
    $cmd = '/sbin/reboot';
    $count_down_secs += 30;
  }

  shell($cmd.' -d4 >/dev/null 2>/dev/null &', $status);
  if ($status == 0) {
    if ($setup) {
      $count_down_secs += 50;
      $opts = '&setup';
    } else {
      $opts = '';
    }
    header('Location: '.$myself.'?count_down_secs='.$count_down_secs.$opts.'&result='.$result);
    exit;
  }
}

// Function: scheduleREBOOT
//
function scheduleREBOOT($delay) {
  $time = time();

  shell('killall reboot >/dev/null 2>/dev/null', $status);

  $delay -= (int)date('G', $time);
  if ($delay > 0) {
    $delay *= 3600;
    $delay -= ((int)date('i', $time) * 60);
    shell('/sbin/reboot -d'.$delay.' >/dev/null 2>/dev/null &', $status);
  }
  return($status == 0 ? TRUE : FALSE);
}

// Function: session_manual_gc
//
function session_manual_gc() {

  if (! isset($_SESSION)) {
    if (($gc_maxlifetime = (int)ini_get('session.gc_maxlifetime')) > 0) {
      foreach (glob('/tmp/sess_*') as $globfile) {
        if (is_file($globfile)) {
          if ((time() - filemtime($globfile)) > $gc_maxlifetime) {
            @unlink($globfile);
          }
        }
      }
    }
  }
}

// Function: updateCRON
//
function updateCRON($user, $ret_good, $ret_fail) {
  $result = $ret_fail;

  shell('echo "'.$user.'" >/mnt/kd/crontabs/cron.update 2>/dev/null', $status);
  if ($status == 0) {
    $result = $ret_good;
  }
  return($result);
}

// Function: includeTOPICinfo
// can be called as tt('topic','tooltip') to keep things compact
//
// Render an information (i) icon and a tooltip if you hover over it.
// Display help information in a popup window (if JavaScript enabled)
// or in a separate browser window/tab.
// if $topic is...
// - A valid URL (e.g. https://doc.astlinux-project.org/whatever) then
//   help page content is downloaded from that web site.
// - A relative link (first character is a /) then the topic is a
//   doc.astlinux-project.org dokuwiki page which may be saved locally.
//   If local version exists use that.  If not then wrap the topic in
//   full URL and get it from the astlinux dokuwiki web site.
// - Empty then only the tooltip is displayed when mouse is over icon,
//   clicking has no effect (no page to load).
// - None of the above, plain text.  Use the legacy mechanism to
//   find the help info by requesting the content from info.php script.
//
function tt($topic,$tooltip = '') {
  return(includeTOPICinfo($topic,$tooltip));
}
function includeTOPICinfo($topic,$tooltip = '') {
  global $global_prefs;
  global $ONLINE_DOCS_URL;

  $target = ' target="_blank"';
  if ($topic === '') {
    $link = '#!'; // an invalid id on this page, so does nothing.
    $target = ''; // make sure that new tab / window not opened.
  }
  else if (filter_var($topic, FILTER_VALIDATE_URL)) {
    // we were passed in a full valid URL. Use it.
    $link = $topic;
  }
  else if (strpos($topic,'/') === 0) {
    // We were passed a relative URL. Check if the file exists.
    // Link will start with either /userdoc:xxx or /devdoc:xxx  This
    // identifies which directory the local version will have been
    // saved in.
    $parts = explode('#',$topic);
    $topic = substr($parts[0],1); // remove slash at front
    $parts = explode(':',$topic); // capture part before colon
    $subdir = $parts[0];
    // This __FILE__ executes in /var/www/common so to get to the
    // right subdir need to look for e.g /var/www/common/../userdoc
    $file = dirname(__FILE__).'/../'.$subdir.'/'.$topic.'.html';
    if (is_file($file)) {
      // while file is in /var/www/userdoc/* we cannot include /userdoc/
      // in the URL path as that is not what native dokuwiki uses for
      // embedded links.  We will update lighttpd config to detect
      // userdoc:topic-name and get file from the right directory.
      $link='/'.$topic;
    }
    else {
      // File is not available on local AstLinux box.  We will expand
      // the topic name to a full URL and get of from the online
      // doc.astlinux-project.org dokuwiki web site.
      $docsite=getPREFdef($global_prefs, 'online_docs_url');
      if (empty($docsite)) $docsite=$ONLINE_DOCS_URL;
      $parts = explode('?',$docsite);
      if (substr($parts[0],-1) === "/") $parts[0] = substr($parts[0],0,-1); // remove trailing slash if present
      $link=$parts[0].'/'.$topic.'?'.$parts[1];
    }
  }
  else {
    // The original way of getting help information for the (i) anchors.
    $link = '/info.php?topic='.$topic;
  }

  // If we were passed a tooltip then set style properties and html
  // tags to display the tooltip if mouse hovers over the (i) image
  $class = '';
  if ($tooltip !== '') {
    $class = ' class="tooltip';
    if (strlen($tooltip) >= 50) $class .= ' tooltipwide';
    $class .= '"';
    $tooltip = '<b><em></em>'.$tooltip.'</b>';
  }

  // If enabled, display the help text in a popup window rather than
  // displaying in another browser tab or window.
  $onclick = '';
  if ($topic !== '' && getPREFdef($global_prefs, 'help_in_popup_window') !== 'no') {
    $onclick = ' onclick="delayPopup(event,this.href,650,250,\''.$topic.'\',true,0); return false;"';
  }

  $str = '<a href="'.$link.'"'.$target.$onclick.$class.'>';
  $str .= '<img src="/common/topicinfo.gif" alt="Info"/>'.$tooltip.'</a>';

  return($str);
}

// Function: inStringList
//
function inStringList($match, $str, $chr = ' ') {

  $strtokens = explode($chr, $str);
  foreach ($strtokens as $value) {
    if ((string)$value === (string)$match) {
      return(TRUE);
    }
  }
  return(FALSE);
}

// Function: secs2minsec
// Change seconds to min:sec format
//
function secs2minsec($secs) {
  $min = (string)((int)($secs / 60));
  $sec = (string)((int)($secs % 60));

  $min = str_pad($min, 1, '0', STR_PAD_LEFT);
  $sec = str_pad($sec, 2, '0', STR_PAD_LEFT);
  $minsec = $min.':'.$sec;

  return($minsec);
}

// Function: secs2hourminsec
// Change seconds to hour:min:sec format
//
function secs2hourminsec($secs) {
  $hour = (string)((int)($secs / 3600));
  $min = (string)((int)(($secs - (3600 * $hour)) / 60));
  $sec = (string)((int)(($secs - (3600 * $hour)) % 60));

  $hour = str_pad($hour, 1, '0', STR_PAD_LEFT);
  $min = str_pad($min, 2, '0', STR_PAD_LEFT);
  $sec = str_pad($sec, 2, '0', STR_PAD_LEFT);
  $hourminsec = $hour.':'.$min.':'.$sec;

  return($hourminsec);
}

// Function: getARNOplugins
//
//
function getARNOplugins() {
  $dir = '/mnt/kd/arno-iptables-firewall/plugins';
  if (! is_dir($dir)) {
    return(FALSE);
  }

  // Find the currently active plugins
  $active = array();
  $active_file = '/var/tmp/aif_active_plugins';
  if (is_file($active_file)) {
    $cmd = "sed -n -r -e 's|^.*/plugins/[0-9][0-9](.*)\\.plugin$|\\1|p' $active_file";
    @exec($cmd, $active);
  }

  $tmpfile = tempnam("/tmp", "PHP_");
  $cmd = 'grep -m1 -H \'^ENABLED=\' '.$dir.'/*.conf |';
  $cmd .= 'sed -e \'s/ENABLED=//\' -e \'s/"//g\'';
  $cmd .= ' >'.$tmpfile;
  @exec($cmd);
  $ph = @fopen($tmpfile, "r");
  while (! feof($ph)) {
    if (($line = trim(fgets($ph, 1024))) !== '') {
      if (($pos = strpos($line, ':')) !== FALSE) {
        $linetokens = explode(':', $line);
        if ($linetokens[1] === '0') {
          $value = '0~Disabled';
        } elseif ($linetokens[1] === '1')  {
          $value = '1~Enabled';
        } else {
          $value = '0~Undefined';
        }
        $plugin_name = basename($linetokens[0], '.conf');
        foreach ($active as $active_name) {
          if ($active_name === $plugin_name) {
            $value = substr($value, 0, 2).'Active';
            break;
          }
        }
        $plugins[$linetokens[0]] = $value;
      }
    }
  }
  fclose($ph);
  @unlink($tmpfile);

  if (is_null($plugins)) {
    return(FALSE);
  }
  return($plugins);
}

// Function: getETHinterfaces
//
//
function getETHinterfaces() {
  $id = 0;
  $output = array();
  $cmd = '/sbin/ip -o link show 2>/dev/null | cut -d\':\' -f2';
  @exec($cmd, $output);
  foreach ($output as $line) {
    $eth = trim($line);
    if (($pos = strpos($eth, '@')) !== FALSE) {
      $eth = substr($eth, 0, $pos);
    }
    if ($eth !== 'lo' &&
        strncmp($eth, 'ppp', 3) &&
        strncmp($eth, 'tun', 3) &&
        strncmp($eth, 'sit', 3) &&
        strncmp($eth, 'ip6tun', 6) &&
        strncmp($eth, 'dummy', 5)) {
          $eth_R[$id] = $eth;
          $id++;
    }
  }
  return($eth_R);
}

// Function: getVARdef
//
//
function getVARdef($db, $var, $cur = NULL) {
  $value = '';
  if (is_null($db)) {
    return($value);
  }
  if (isset($db['data']["$var"])) {
    return($db['data']["$var"]);
  }

  // no matches, check for currrent config
  if (is_null($cur)) {
    return($value);
  }
  if (isset($cur['data']["$var"])) {
    return($cur['data']["$var"]);
  }
  return($value);
}

// Function: string2RCconfig
//
function string2RCconfig($str) {

  if (get_magic_quotes_gpc()) {
    $str = stripslashes($str);
  }
  $str = str_replace('\\', '\\\\', $str);
  $str = str_replace('$', '\\$', $str);
  $str = str_replace('`', '\\`', $str);
  $str = str_replace('"', '\\"', $str);
  return($str);
}

// Function: RCconfig2string
//
function RCconfig2string($str) {

  $str = str_replace('\\$', '$', $str);
  $str = str_replace('\\`', '`', $str);
  $str = str_replace('\\"', '"', $str);
  $str = str_replace('\\\\', '\\', $str);
  return($str);
}

// Function: stripshellsafe
//
function stripshellsafe($str) {

  if (get_magic_quotes_gpc()) {
    $str = stripslashes($str);
  }
  $str = str_replace('$', '', $str);
  $str = str_replace('`', '', $str);
  $str = str_replace('"', '', $str);
  $str = str_replace('\\', '', $str);
  return($str);
}

// Function: tuq (Trim Un-Quote for Shell)
//
function tuq($str) {

  $str = stripshellsafe($str);
  $str = trim($str);
  return($str);
}

// Function: tuqp (Trim Un-Quote for Prefs)
//
function tuqp($str) {

  if (get_magic_quotes_gpc()) {
    $str = stripslashes($str);
  }
  $str = str_replace('"', '', $str);
  $str = str_replace('\\', '', $str);
  $str = trim($str);
  return($str);
}

// Function: tuqd (Trim Un-Quote for Data)
//
function tuqd($str) {

  if (get_magic_quotes_gpc()) {
    $str = stripslashes($str);
  }
  $str = str_replace('"', '', $str);
  $str = str_replace('\\', '', $str);
  $str = trim($str);
  return($str);
}

// Function: parseRCconfig
//
function parseRCconf($conffile) {

  $tmpfile = tempnam("/tmp", "PHP_");
  @exec("sed -e 's/^#.*//' -e '/^$/ d' ".$conffile.' >'.$tmpfile);
  $ph = @fopen($tmpfile, "r");
  while (! feof($ph)) {
    if (($line = trim(fgets($ph, 1024))) !== '') {
      if (($pos = strpos($line, '=')) !== FALSE) {
        $var = trim(substr($line, 0, $pos), ' ');
        $line = substr($line, ($pos + 1));
        if (($begin = strpos($line, '"')) !== FALSE) {
          if (($end = strrpos($line, '"')) !== FALSE) {
            if ($begin == $end) {  // multi-line definition, single quote
              while (! feof($ph)) {
                if (($qstr = rtrim(fgets($ph, 1024))) !== '') {
                  if (($end = strrpos($qstr, '"')) !== FALSE && ! ($end > 0 && substr($qstr, $end - 1, 1) === '\\')) {
                    if (($pos = strpos($qstr, '#', $end)) !== FALSE) {
                      $qstr = substr($qstr, 0, $pos);
                    }
                    $line .= "\n".$qstr;
                    break;
                  } else {  // no quote, comments not allowed
                    $line .= "\n".$qstr;
                  }
                }
              }
            } else {  // single-line with quotes
              if (($pos = strpos($line, '#', $end)) !== FALSE) {
                $line = substr($line, 0, $pos);
              }
            }
          }
        } else {  // single-line with no quotes
          if (($pos = strpos($line, '#')) !== FALSE) {
            $line = substr($line, 0, $pos);
          }
        }
        $value = trim($line, ' ');
        if (substr($value, 0, 1) === '"' && substr($value, -1, 1) === '"') {
          $value = substr($value, 1, strlen($value) - 2);
          $value = trim($value, ' ');
        }
        if ($var === 'NTPSERV' || $var === 'NTPSERVS') {
          if (is_file('/mnt/kd/chrony.conf')) {
            $value = '#NTP server is specified in /mnt/kd/chrony.conf';
          }
        }
        if ($var === 'UPS_DRIVER' || $var === 'UPS_DRIVER_PORT') {
          if (is_file('/mnt/kd/ups/ups.conf')) {
            $value = '#UPS driver is specified in /mnt/kd/ups/ups.conf';
          }
        }
        if ($var === 'ASTBACK_PATHS' ||
                  $var === 'ASTBACK_FILE' ||
                  $var === 'AUTOMODS' ||
                  $var === 'ISSUE' ||
                  $var === 'NETISSUE') {
          $var = '';
        }
        if ($var !== '') {
          $db['data']["$var"] = $value;
        }
      }
    }
  }
  fclose($ph);
  @unlink($tmpfile);

  $db['conffile'] = $conffile;
  return($db);
}

// Function: get_HOSTNAME_DOMAIN
//
function get_HOSTNAME_DOMAIN() {
  $hostname_domain = '';

  // System location of gui.network.conf file
  $NETCONFFILE = '/mnt/kd/rc.conf.d/gui.network.conf';

  if (is_file($NETCONFFILE)) {
    $netvars = parseRCconf($NETCONFFILE);
    if (($hostname = getVARdef($netvars, 'HOSTNAME')) !== '') {
      if (($domain = getVARdef($netvars, 'DOMAIN')) !== '') {
        $hostname_domain = $hostname.'.'.$domain;
      }
    }
  }
  return($hostname_domain);
}

// Function: asteriskURLrepo
//
function asteriskURLrepo() {

  $version = trim(shell_exec('/usr/sbin/asterisk -V'));

  if (($i = strpos($version, ' ')) !== FALSE) {
    $ver3 = substr($version, $i + 1, 3);
  } else {
    $ver3 = '';
  }

  $str = 'https://mirror.astlinux-project.org/';

  if ($ver3 === '1.4') {
    $str .= 'firmware-1.x';
  } elseif ($ver3 === '1.8') {
    $str .= 'ast18-firmware-1.x';
  } elseif ($ver3 === '11.') {
    $str .= 'ast11-firmware-1.x';
  } else {
    $str .= 'ast13-firmware-1.x';
  }
  return($str);
}

// Function: asteriskERROR
//
function asteriskERROR($result) {

  if ($result == 1101) {
    $str = 'The "manager.conf" file is not enabled for 127.0.0.1 on port 5038.';
  } elseif ($result == 1102) {
    $str = 'The "manager.conf" file does not have the [webinterface] user defined properly.';
  } else {
    $str = 'Asterisk not responding.';
  }
  return($str);
}

// Function: asteriskMGR
//
function asteriskMGR($cmd, $fname) {

  if (($socket = @fsockopen('127.0.0.1', '5038', $errno, $errstr, 5)) === FALSE) {
    return(1101);
  }
  fputs($socket, "Action: login\r\n");
  fputs($socket, "Username: webinterface\r\n");
  fputs($socket, "Secret: webinterface\r\n");
  fputs($socket, "Events: off\r\n\r\n");

  fputs($socket, "Action: command\r\n");
  fputs($socket, "Command: ".$cmd."\r\n\r\n");

  fputs($socket, "Action: logoff\r\n\r\n");

  stream_set_timeout($socket, 5);
  $info = stream_get_meta_data($socket);
  while (! feof($socket) && ! $info['timed_out']) {
    $line = fgets($socket, 256);
    $info = stream_get_meta_data($socket);
    if (strncasecmp($line, 'Response: Error', 15) == 0) {
      while (! feof($socket) && ! $info['timed_out']) {
        fgets($socket, 256);
        $info = stream_get_meta_data($socket);
      }
      fclose($socket);
      return(1102);
    }
    if (strncasecmp($line, 'Privilege: Command', 18) == 0) {
      break;
    }
  }
  // begin command data
  if ($fname !== '') {
    if (($fp = @fopen($fname,"wb")) !== FALSE) {
      while (! feof($socket) && ! $info['timed_out']) {
        $line = fgets($socket, 256);
        $info = stream_get_meta_data($socket);
        if (strncasecmp($line, '--END COMMAND--', 15) == 0) {
          break;
        }
        fwrite($fp, $line);
      }
      fclose($fp);
    }
  }
  // end command data
  while (! feof($socket) && ! $info['timed_out']) {
    fgets($socket, 256);
    $info = stream_get_meta_data($socket);
  }
  fclose($socket);

  return($info['timed_out'] ? 1103 : 0);
}

// Function: asteriskCMD
//
function asteriskCMD($cmd, $fname) {
  global $global_prefs;

  if (getPREFdef($global_prefs, 'status_asterisk_manager') === 'no') {
    $cmd = str_replace('"', '\"', $cmd);
    if ($fname === '') {
      $fname = '/dev/null';
    }
    shell('/usr/sbin/asterisk -rnx "'.$cmd.'" >'.$fname, $status);
  } else {
    $status = asteriskMGR($cmd, $fname);
  }
  return($status);
}

// Function: parseAstDB
//
function parseAstDB($family) {
  $id = 0;
  $db['family'] = $family;
  $tmpfile = tempnam("/tmp", "PHP_");
  $status = asteriskCMD('database show '.$family, $tmpfile);
  if (($db['status'] = $status) == 0) {
    $ph = @fopen($tmpfile, "r");
    while (! feof($ph)) {
      if (($line = trim(fgets($ph, 1024))) !== '') {
        if (($pos = strpos($line, ': ')) !== FALSE) {
          $keystr = substr($line, 0, $pos);
          $valuestr = substr($line, ($pos + 2));
          $keytokens = explode('/', $keystr);
          $db['data'][$id]['key'] = trim($keytokens[2]);
          $db['data'][$id]['value'] = trim($valuestr);
          $id++;
        }
      }
    }
    fclose($ph);
  }
  @unlink($tmpfile);

  return($db);
}

// Function: putAstDB
//
function putAstDB($family, $key, $value) {
  $status = asteriskCMD('database put '.$family.' '.$key.' "'.$value.'"', '');
  return($status);
}

// Function: delAstDB
//
function delAstDB($family, $key) {
  $status = asteriskCMD('database del '.$family.' '.$key, '');
  return($status);
}

// Function: getRebootDelayMenu
//
function getRebootDelayMenu() {
  $start = (int)date('G');
  $start = ($start % 2 == 0) ? $start + 1 : $start + 2;

  $menuitems['Now'] = 0;
  for ($i = 0; $i < 24; $i += 2) {
    $key = str_pad(($start % 24), 2, '0', STR_PAD_LEFT).':00';
    $menuitems[$key] = $start;
    $start += 2;
  }
  $menuitems['Cancel'] = -1;

  return($menuitems);
}

// Function: pad_ipv4_str
//
function pad_ipv4_str($ip) {
  $str = $ip;

  if (strpos($ip, ':') === FALSE && strpos($ip, '.') !== FALSE) {
    $tokens = explode('.', $ip);
    if (count($tokens) == 4) {
      $str = str_pad($tokens[0], 3, '0', STR_PAD_LEFT).'.'.
             str_pad($tokens[1], 3, '0', STR_PAD_LEFT).'.'.
             str_pad($tokens[2], 3, '0', STR_PAD_LEFT).'.'.
             str_pad($tokens[3], 3, '0', STR_PAD_LEFT);
    }
  }
  return($str);
}

// Function: compressIPV6addr
// Accepts both IPv4 and IPv6 with/without CIDR/prefix lengths
// Returns compressed IPv4 or compressed IPv6
function compressIPV6addr($addr) {
  $addr=preg_replace('/\b0+(?=\d)/', '', $addr); // remove leading zeros
  $parts=explode("/",$addr); // separate CIDR/prefix length
  $addr=inet_ntop(inet_pton($parts[0])); // compress the address
  if (!empty($parts[1])) {
    $addr=$addr."/".$parts[1];  // add back in the CIDR/prefix length
  }
  return($addr);
}

// Function: expandIPV6addr
// Accepts both IPv4 and IPv6 with/without CIDR/prefix lengths
// Returns IPv6 for both with adjusted prefix lengths for IPv4 input
function expandIPV6addr($addr){
	$addr=preg_replace('/\b0+(?=\d)/', '', $addr); // remove leading zeros
  $parts=explode("/",$addr); // separate CIDR/prefix length
  $addr=$parts[0];
  if (filter_var($addr, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
    $addr='::'.$addr;  // convert IPv4 address to IPv6 notation
    if (!empty($parts[1])) {
	    $parts[1]=$parts[1]+96;  // adjust the CIDR to IPv6 prefix length
	  }
  }
  if (filter_var($addr, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
    $hex = unpack("H*hex", inet_pton($addr));
    $addr = substr(preg_replace("/([A-f0-9]{4})/", "$1:", $hex['hex']), 0, -1);
  }
  if (!empty($parts[1])) {
    $addr=$addr."/".$parts[1];  // add back in the CIDR/prefix length
  }
  return $addr;
}


// Function: is_IPV6addr
//
function is_IPV6addr($addr) {
  $parts=explode("/",$addr); // separate CIDR/prefix length
  if (filter_var($parts[0], FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
    return(true);
  }
  return(false);
}

// Function: is_addon_package
//
function is_addon_package($pkg) {

  $pkg_dir = '/stat/var/packages/'.$pkg;
  return(is_dir($pkg_dir));
}

// Function: is_mac2vendor
//
function is_mac2vendor() {

  $mac_vendor_db = '/usr/share/oui-db';
  return(is_dir($mac_vendor_db));
}

// Function: mac2vendor
//
function mac2vendor($mac) {

  $vendor = '';
  $mac_vendor_db = '/usr/share/oui-db';
  if (is_dir($mac_vendor_db)) {
    $match = strtoupper(str_replace(':', '', $mac));
    $match = substr($match, 0, 6);
    if (strlen($match) == 6) {
      if (($lines = @file($mac_vendor_db.'/xxxxx'.$match[5], FILE_IGNORE_NEW_LINES)) !== FALSE) {
        if (($grep = current(preg_grep("/^$match~/", $lines))) !== FALSE) {
          $vendor = substr($grep, 7);
        }
      }
    }
  }
  return($vendor);
}

// Function: getAstDB
//
function getAstDB($family, $key) {
  $tmpfile = tempnam("/tmp", "PHP_");
  $result = null;
  if ((asteriskCMD('database get '.$family.' '.$key.'"', $tmpfile)) == 0) {
    $ph = @fopen($tmpfile, "r");
    while (! feof($ph)) {
      if ($line = trim(fgets($ph, 1024))) {
        if (strncasecmp($line, 'Value: ', 6) == 0) {
          $result = substr($line,7);
        }
      }
    }
    fclose($ph);
    @unlink($tmpfile);
  }
  return($result);
}

// Function: getPREFdef
//
function getPREFdef($db, $var)
{
  $value = '';
  if (isset($db['data']["$var"])) {
    return($db['data']["$var"]);
  }
  return($value);
}

// Function: isDNS_TLS
//
function isDNS_TLS()
{
  return(is_file('/var/run/stubby/stubby.pid'));
}

// Function: isDNSCRYPT
//
function isDNSCRYPT()
{
  return(is_file('/var/run/dnscrypt-proxy.pid'));
}

// Function: getTABname
//
function getTABname()
{
  if (isset($_SERVER['SCRIPT_NAME'])) {
    $str_R = basename($_SERVER['SCRIPT_NAME'], '.php');
  } else {
    $str_R = '';
  }
  return($str_R);
}

// Function: getPHPusername
//
function getPHPusername()
{
  if (isset($_SERVER['REMOTE_USER'])) {
    $str_R = $_SERVER['REMOTE_USER'];
  } else {
    $str_R = '';
  }
  return($str_R);
}

// Function: getSYSlocation
//
function getSYSlocation($base = '')
{
  if (($end = strrpos($_SERVER['SCRIPT_FILENAME'], $_SERVER['SCRIPT_NAME'])) === FALSE) {
    $str_R = '';
  } else {
    if (($str_R = substr($_SERVER['SCRIPT_FILENAME'], 0, $end)) !== '') {
      $str_R .= $base;
    }
  }
  return($str_R);
}

// Function: getPASSWDlocation
//
function getPASSWDlocation()
{
  if (($end = strrpos($_SERVER['SCRIPT_FILENAME'], $_SERVER['SCRIPT_NAME'])) === FALSE) {
    $str_R = '';
  } else {
    if (($str_R = substr($_SERVER['SCRIPT_FILENAME'], 0, $end)) !== '') {
      $str_R .= '/admin/.htpasswd';
    }
  }
  return($str_R);
}

// Function: getPREFSlocation
//
function getPREFSlocation()
{
  global $KD_PREFS_LOCATION;

  if (is_file($KD_PREFS_LOCATION)) {
    $str_R = $KD_PREFS_LOCATION;
  } elseif (($end = strrpos($_SERVER['SCRIPT_FILENAME'], $_SERVER['SCRIPT_NAME'])) === FALSE) {
    $str_R = '';
  } else {
    if (($str_R = substr($_SERVER['SCRIPT_FILENAME'], 0, $end)) !== '') {
      $str_R .= '/prefs.txt';
    }
  }
  return($str_R);
}

// Function: parsePrefs
//
function parsePrefs($pfile)
{
  if ($pfile !== '') {
    if (is_file($pfile)) {
      if (($ph = @fopen($pfile, "r")) !== FALSE) {
        while (! feof($ph)) {
          if (($line = trim(fgets($ph, 1024))) !== '') {
            if ($line[0] !== '#') {
              if (($pos = strpos($line, '=')) !== FALSE) {
                $var = trim(substr($line, 0, $pos), ' ');
                $value = trim(substr($line, ($pos + 1)), '" ');
                if ($var !== '' && $value !== '') {
                  $db['data']["$var"] = $value;
                }
              }
            }
          }
        }
        fclose($ph);
      }
    }
  }
  return($db);
}

// Function: system_image_arch
//
function system_image_arch() {

  $arch = '';
  if (($cmdline = trim(@file_get_contents('/proc/cmdline'))) !== '') {
    $tokens = explode(' ', $cmdline);
    foreach ($tokens as $value) {
      $cmd = explode('=', $value);
      if ($cmd[0] === 'astlinux' && $cmd[1] != '') {
        $arch = $cmd[1];
        break;
      }
    }
  }
  return ($arch);
}

// Function: system_timezone
//
function system_timezone() {

  if (($tz = trim(@file_get_contents('/etc/timezone'))) === '') {
    $tz = @date_default_timezone_get();
  }
  return ($tz);
}

// Set system timezone if not in php.ini
if (ini_get('date.timezone') == '') {
  date_default_timezone_set(system_timezone());
}

// Set globals
$global_prefs = parsePrefs(getPREFSlocation());
$global_user = getPHPusername();
$global_admin = ($global_user === '' || $global_user === 'admin');
$global_staff = ($global_admin || $global_user === 'staff');
$global_staff_disable_voicemail = ($global_user === 'staff' && (getPREFdef($global_prefs, 'tab_voicemail_disable_staff') === 'yes'));
$global_staff_disable_monitor = ($global_user === 'staff' && (getPREFdef($global_prefs, 'tab_monitor_disable_staff') === 'yes'));
$global_staff_disable_followme = ($global_user === 'staff' && (getPREFdef($global_prefs, 'tab_followme_disable_staff') === 'yes'));
$global_staff_enable_sqldata = ($global_user === 'staff' && (getPREFdef($global_prefs, 'tab_sqldata_disable_staff') === 'no'));
$global_staff_disable_staff = ($global_user === 'staff' && (getPREFdef($global_prefs, 'tab_staff_disable_staff') === 'yes'));
$global_staff_enable_dnshosts = ($global_user === 'staff' && (getPREFdef($global_prefs, 'tab_dnshosts_disable_staff') === 'no'));
$global_staff_enable_xmpp = ($global_user === 'staff' && (getPREFdef($global_prefs, 'tab_xmpp_disable_staff') === 'no'));
$global_staff_enable_cli = ($global_user === 'staff' && (getPREFdef($global_prefs, 'tab_cli_disable_staff') === 'no'));
?>
