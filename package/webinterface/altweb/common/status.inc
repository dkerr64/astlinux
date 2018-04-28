<?php

// Copyright (C) 2008-2018 Lonnie Abelbeck
// This is free software, licensed under the GNU General Public License
// version 3 as published by the Free Software Foundation; you can
// redistribute it and/or modify it under the terms of the GNU
// General Public License; and comes with ABSOLUTELY NO WARRANTY.

// status.inc for AstLinux
// 03-01-2007
// 01-15-2008, Added ntpq support
// 01-21-2008, Added core show channels support
// 02-13-2008, Improved ntpq display format
// 03-31-2008, Reworked system stats, ntpd and syslog sections
// 04-08-2008, Added Preferneces Support
// 04-10-2008, Added Password Warning
// 04-12-2008, Added PPPoE Status
// 04-18-2008, Changed System Time to use PHP date
// 08-10-2008, Added Disk Usage and GUI Version
// 08-30-2008, Allow status.php to be authenticated
// 02-11-2009, Added Firewall States
// 04-14-2009, Added OpenVPN Client Status
// 06-15-2009, Added IPsec Associations
// 11-06-2009, Added Reboot Scheduled notice
// 05-05-2011, Added DAHDI Status and Hardware Monitoring sections
// 08-10-2011, Added APC UPS Status
// 01-02-2012, Added Jabber Status
// 07-07-2012, Added UPnP Status
// 09-28-2012, Added Adaptive Ban Plugin Status
// 09-28-2012, Added Latest System Logs/Hide Log Words
// 09-28-2012, Added Custom Asterisk Command
// 01-20-2013, Added XMPP Server Status
// 01-03-2014, Added NUT UPS Monitoring Status
// 07-02-2014, Added "Show PPPoE Logs" button
// 09-04-2014, Added Kamailio SIP Server Status
// 10-28-2014, Added S.M.A.R.T Monitoring Status
// 11-06-2014, Added Failover Status
// 10-14-2016, Added Check for default admin password
// 12-01-2016, Added chronyc to replace ntpq
// 05-15-2017, Added IPv6 Prefixes/Address
// 07-16-2017, Added ACME Certificates
// 07-31-2017, Added interface identifier to IPv6 prefix/address display
// 08-12-2017, Added IPv6 DHCP lease file listing
// 11-06-2017, Added WireGuard VPN Status
// 03-13-2018, Added Hardware system-vendor info
// 04-25-2018, Performance improvement using asynchronous system calls
// 04-28-2018, Move all the status sections into a separate .inc file
//

$myself = $_SERVER['PHP_SELF'];

require_once $COMMON.'functions.php';

require_once $COMMON.'version.php';

if (getPREFdef($global_prefs, 'status_require_auth') === 'yes') {
  if (! $STATUS_AUTH) {
    header('Location: /admin/status.php');
    exit;
  }
}

// Function: getDaemons
// Find running daemons
//
function getDaemons() {

  $status['asterisk'] = 0;
  $status['chronyd'] = 0;
  $status['miniupnpd'] = 0;
  $status['dnsmasq'] = 0;
  $status['openvpn'] = 0;
  $status['racoon'] = 0;
  $status['charon'] = 0;
  $status['pptpd'] = 0;
  $status['upsmon'] = 0;
  $status['syslogd'] = 0;
  $status['zabbix_agentd'] = 0;
  $status['zabbix_proxy'] = 0;
  $status['kamailio'] = 0;
  $status['reboot'] = 0;

  $output = array();
  @exec('ps -o comm=""', $output);

  foreach ($output as $line) {
    foreach ($status as $key => $value) {
      if ($key === $line) {
        $status["$key"]++;
        break;
      }
    }
  }
  return($status);
}

// Function: noASTURWstorage
//
function noASTURWstorage() {

  shell('mount 2>/dev/null | grep -q "/oldroot/mnt/asturw"', $status);

  return($status != 0);
}

// Function: adminDEFAULTpassword
//
function adminDEFAULTpassword() {

  $status = 0;

  if (($HTPASSWD = getPASSWDlocation()) !== '') {
    if (is_file($HTPASSWD)) {
      shell("/usr/sbin/check-default-passwd admin '$HTPASSWD' >/dev/null 2>/dev/null", $status);
    }
  }
  return($status == 0);
}

// Function: noASTERISKsounds
//
function noASTERISKsounds($asterisk) {
  $status = 0;

  if ($asterisk > 0) {
    shell("find /var/lib/asterisk/sounds -name 'vm-*' 2>/dev/null | grep -q '/vm-'", $status);
  }

  return($status != 0);
}

// Function: getIPV6address
//
function getIPV6address() {

  if (! is_file('/proc/net/if_inet6')) {
    return(NULL);
  }

  $cmd  = '. /etc/rc.conf';
  $cmd .= ';/sbin/ip -6 -o addr show dev "$EXTIF" scope global 2>/dev/null';
  $cmd .= ' | awk \'$3 == "inet6" { split($4, field, "/"); print field[1]; nextfile; }\'';
  $cmd .= ';echo "#next#"';
  $cmd .= ';/sbin/ip -6 -o addr show scope global 2>/dev/null';
  $cmd .= ' | awk \'$2 != "\'"$EXTIF"\'" && $3 == "inet6" { print $4," ["$2"]"; }\'';

  $output = array();
  @exec($cmd, $output);

  $status['address'] = '';

  $state = 0;
  $pnum = 0;
  foreach ($output as $line) {
    if ($line[0] === '#') {
      $state++;
      continue;
    }
    if ($state == 0) {          // IPv6 Address
      $status['address'] = $line;
    } elseif ($state == 1) {    // IPv6 Prefixes
      if (preg_match('/^[0-9a-fA-F:]+\/([0-9]+)/', $line, $match)) {
        if ($match[1] >= 32 && $match[1] <= 64) {
          $status['prefixes'][$pnum] = $line;
          $pnum++;
        }
      }
    }
  }
  return($status);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $result = 1;
  if (isset($_POST['submit_pppoe'])) {
    $result = restartPROCESS('pppoe', 10, 99);
  } elseif (isset($_POST['show_pppoe_logs'])) {
    header('Location: '.$myself.'?show_pppoe_logs');
    exit;
  }
  header('Location: '.$myself.'?result='.$result);
  exit;
} else { // Start of HTTP GET
$ACCESS_RIGHTS = 'all';
require_once $COMMON.'header.php';

$daemon = getDaemons();

// Use popen() to start some longer-running tasks in parallel threads
//
if (!getPREFdef($global_prefs, 'async_load_status_page') !== 'no') {
  // Only fire off the popen()'s if not loading status sections asynchronously.
  // If async load then this will be done in the "included" file.
  if (getPREFdef($global_prefs, 'status_disk_usage') !== 'no') {
    $cmd = 'df -h /tmp | grep "^ *[A-Z][a-z]"';
    $cmd .= '; df -h | grep "^/dev/[sh]d[a-h][0-9]" | sort -k 1,1';
    $status_disk_usage = @popen($cmd, 'r');
  }
  if (is_dir('/mnt/kd/acme')) {
    if (getPREFdef($global_prefs, 'status_show_acme_certificates') === 'yes') {
      $cmd = '/usr/sbin/acme-client --list';
      $status_show_acme_certificates = @popen($cmd, 'r');
    }
  }
  if ($daemon['chronyd'] > 0) {
    if (getPREFdef($global_prefs, 'status_ntp_sessions') !== 'no') {
      $cmd = '/usr/bin/chronyc sources';
      $status_ntp_sessions = @popen($cmd, 'r');
    }
  }
  if ($daemon['charon'] > 0) {
    if (getPREFdef($global_prefs, 'status_ipsec_associations') !== 'no') {
    $cmd = '/usr/sbin/ipsec status';
    $status_ipsec_associations = @popen($cmd, 'r');
    }
  }
  if (is_file('/var/lock/wireguard.lock')) {
    if (getPREFdef($global_prefs, 'status_wireguard_vpn') !== 'no') {
      $cmd = '/usr/bin/wg show | sed -n \'/^peer/,$ p\'';
      $status_wireguard_vpn = @popen($cmd, 'r');
    }
  }
  if (is_file('/var/run/prosody/prosody.pid')) {
    if (getPREFdef($global_prefs, 'status_show_xmpp_server') !== 'no') {
      $cmd = '. /etc/rc.conf; if [ "$XMPP_ENABLE_S2S" = "yes" ]';
      $cmd .= "; then /usr/bin/prosodycmd 'server:version()' 'server:uptime()' 'c2s:show()' 's2s:show()'";
      $cmd .= "; else /usr/bin/prosodycmd 'server:version()' 'server:uptime()' 'c2s:show()'";
      $cmd .= '; fi';
      $status_show_xmpp_server = @popen($cmd, 'r');
    }
  }
  if (is_file('/var/lock/aif_adaptive_ban.lock')) {
    if (getPREFdef($global_prefs, 'status_show_adaptive_ban') === 'yes') {
      $cmd = '. /etc/rc.conf; if [ "$IPV6" = "yes" ]; then ipv6="1"; else ipv6="0"; fi';
      $cmd .= '; /usr/share/arno-iptables-firewall/plugins/adaptive-ban-helper status /usr/sbin/iptables /usr/sbin/ip6tables $ipv6';
      $status_show_adaptive_ban = @popen($cmd, 'r');
    }
  }
  if (is_executable('/usr/bin/sensors')) {
    if (getPREFdef($global_prefs, 'status_show_hardware_monitoring') === 'yes') {
      $cmd = '/usr/bin/sensors';
      $status_show_hardware_monitoring = @popen($cmd, 'r');
    }
  }
  if (is_executable('/usr/sbin/smart-status')) {
    if (getPREFdef($global_prefs, 'status_show_smart_monitoring') === 'yes') {
      $cmd = '/usr/sbin/smart-status attributes';
      $status_show_smart_monitoring = @popen($cmd, 'r');
    }
  }
  if ($daemon['syslogd'] > 0) {
    if (getPREFdef($global_prefs, 'status_show_system_logs') !== 'no') {
      if (($str = getPREFdef($global_prefs, 'status_exclude_logs_cmdstr')) !== '') {
        $exclude_words = explode(' ', $str);
        $exclude_logs = '';
        foreach ($exclude_words as $exclude_word) {
          if ($exclude_word !== '') {
            $exclude_word = strtr($exclude_word, '$`[]\'\\', '......');   // map special chars to dot
            $exclude_logs .= " -e '$exclude_word'";
          }
        }
        $cmd = 'grep -v -i '.$exclude_logs.' /var/log/messages | tail -n 30';
      } else {
        $cmd = 'tail -n 30 /var/log/messages';
      }
      $status_show_system_logs = @popen($cmd, 'r');
    }
  }
}

// Build the HTML for the main block of the page
// Start with general system info.
//
putHtml("<center>");
if (isset($_GET['result'])) {
  $result = $_GET['result'];
  if ($result == 10) {
    putHtml('<p style="color: green;">PPPoE has Restarted.</p>');
  } elseif ($result == 99) {
    putHtml('<p style="color: red;">PPPoE Restart Failed.</p>');
  } else {
    putHtml('<p style="color: orange;">No Action.</p>');
  }
} elseif (noASTURWstorage()) {
  putHtml('<p style="color: red;">Notice: No Persistent File Storage, click <a href="/admin/setup.php" class="headerText">Installation Setup</a></p>');
} elseif (adminDEFAULTpassword()) {
  putHtml('<p style="color: red;">Notice: Management Password is a known default, click <a href="/admin/system.php" class="headerText">System</a>to change the "admin" password.</p>');
} elseif (noASTERISKsounds($daemon['asterisk'])) {
  putHtml('<p style="color: red;">Notice: No Core Asterisk Sounds, click <a href="/admin/system.php" class="headerText">System</a>then via "Asterisk Sounds Packages", Upgrade/Install "core" and "moh" sounds.</p>');
} else {
  putHtml("<p>&nbsp;</p>");
}
putHtml("</center>");

$i = 0;
putHtml('<center><table width="100%" class="status"><tr><td><center>');
putHtml('<table width="100%" class="datatable">');
// Hostname
echo '<tr ', ($i++ % 2 == 0) ? 'class="dtrow0"' : 'class="dtrow1"', '>';
echo '<td class="dialogText" style="text-align: right; font-weight: bold;">', 'Hostname:', '</td>';
echo '<td style="text-align: left;">', $_SERVER['SERVER_NAME'], '</td>';
// IP Address
$line = trim(shell_exec('. /etc/rc.conf;/sbin/ip -o addr show dev "$EXTIF" 2>/dev/null | awk \'$3 == "inet" { split($4, field, "/"); print field[1]; }\''));
if ($line !== '') {
  echo '<td class="dialogText" style="text-align: right; font-weight: bold;">', 'IPv4 Address:', '</td>';
  echo '<td style="text-align: left;">', $line, '</td>';
} else {
  echo '<td>&nbsp;</td><td>&nbsp;</td>';
}
putHtml('</tr>');
// IPv6
if (! is_null($ipv6_addr = getIPV6address())) {
  echo '<tr ', ($i++ % 2 == 0) ? 'class="dtrow0"' : 'class="dtrow1"', '>';
  if ((count($ipv6_addr['prefixes'])) > 0) {
    echo '<td class="dialogText" style="text-align: right; font-weight: bold;">', 'IPv6 Prefixes:', '</td>';
    echo '<td style="text-align: left;">';
    foreach ($ipv6_addr['prefixes'] as $line) {
      echo $line, '<br />';
    }
    echo '</td>';
  } else {
    echo '<td>&nbsp;</td><td>&nbsp;</td>';
  }
  echo '<td class="dialogText" style="text-align: right; font-weight: bold;">', 'IPv6 Address:', '</td>';
  echo '<td style="text-align: left;">', ($ipv6_addr['address'] !== '' ? $ipv6_addr['address'] : 'Link-local'), '</td>';
  putHtml('</tr>');
}
// DNS
if (isDNS_TLS() || isDNSCRYPT()) {
  echo '<tr ', ($i++ % 2 == 0) ? 'class="dtrow0"' : 'class="dtrow1"', '>';
  echo '<td class="dialogText" style="text-align: right; font-weight: bold;">', 'DNS:', '</td>';
  putHtml('<td style="text-align: left;" colspan="3">'.(isDNS_TLS() ? 'DNS-TLS' : 'DNSCrypt').' Proxy Server Enabled</td></tr>');
} else {
  $rconf = is_file('/etc/resolv-extern.conf') ? '/etc/resolv-extern.conf' : '/etc/resolv.conf';
  if (($fp = @fopen($rconf, 'r')) !== FALSE) {
    while (! feof($fp)) {
      if (($line = trim(fgets($fp, 1024))) !== '') {
        echo '<tr ', ($i++ % 2 == 0) ? 'class="dtrow0"' : 'class="dtrow1"', '>';
        echo '<td class="dialogText" style="text-align: right; font-weight: bold;">', 'DNS:', '</td>';
        echo '<td style="text-align: left;" colspan="3">', $line, '</td>';
        putHtml('</tr>');
      }
    }
    fclose($fp);
  }
}
// System Time
echo '<tr ', ($i++ % 2 == 0) ? 'class="dtrow0"' : 'class="dtrow1"', '>';
echo '<td class="dialogText" style="text-align: right; font-weight: bold;">', 'System Time:', '</td>';
echo '<td style="text-align: left;">', date('l, M d H:i:s T Y'), '</td>';
$line = trim(shell_exec('grep "^Mem[TA]" /proc/meminfo 2>/dev/null'));
$mstr = '';
if (($pos = strpos($line, 'MemTotal:')) !== FALSE) {
  $mem = (int)(((int)trim(substr($line, $pos + 9)) + 512)/1024);
  $mstr .= $mem.' MB, ';
}
if (($pos = strpos($line, 'MemAvailable:')) !== FALSE) {
  $mem = (int)(((int)trim(substr($line, $pos + 13)) + 512)/1024);
  $mstr .= 'Available '.$mem.' MB';
}
echo '<td class="dialogText" style="text-align: right; font-weight: bold;">', 'RAM Memory:', '</td>';
echo '<td style="text-align: left;">', $mstr, '</td>';
putHtml('</tr>');
// uptime
$line = trim(shell_exec('uptime'));
if (preg_match('/^.*up *(.*)load average: *(.*)/i', $line, $matches)) {
  $delayreboot = ($daemon['reboot'] > 0) ? ' - REBOOT SCHEDULED' : '';
  echo '<tr ', ($i++ % 2 == 0) ? 'class="dtrow0"' : 'class="dtrow1"', '>';
  echo '<td class="dialogText" style="text-align: right; font-weight: bold;">', 'System Uptime:', '</td>';
  echo '<td style="text-align: left;">', rtrim($matches[1], ', ').$delayreboot, '</td>';
  echo '<td class="dialogText" style="text-align: right; font-weight: bold;">', 'Load Average:', '</td>';
  echo '<td style="text-align: left;">', $matches[2], '</td>';
  putHtml('</tr>');
}
// astlinux-release
echo '<tr ', ($i++ % 2 == 0) ? 'class="dtrow0"' : 'class="dtrow1"', '>';
echo '<td class="dialogText" style="text-align: right; font-weight: bold;">', 'AstLinux Release:', '</td>';
echo '<td style="text-align: left;">';
if (($fp = @fopen('/etc/astlinux-release', 'r')) !== FALSE) {
  if (! feof($fp)) {
    $line = trim(fgets($fp, 1024)).' '.trim(shell_exec('uname -m'));
    if ($daemon['asterisk'] > 0) {
      $line .= ' - '.trim(shell_exec('/usr/sbin/asterisk -V'));
    }
    echo $line;
  }
  fclose($fp);
}
putHtml('</td>');
if (is_file('/oldroot/cdrom/runnix')) {
  echo '<td class="dialogText" style="text-align: right; font-weight: bold;">', 'Runnix Release:', '</td>';
  echo '<td style="text-align: left;">';
  $line = '';
  if (($fp = @fopen('/oldroot/cdrom/ver', 'r')) !== FALSE) {
    if (! feof($fp)) {
      $line = trim(fgets($fp, 1024));
    }
    fclose($fp);
  } else {
    $line = 'runnix-0.2.1';
  }
  echo $line, '</td>';
} else {
  echo '<td>&nbsp;</td><td>&nbsp;</td>';
}
putHtml('</tr>');

$license = (getPREFdef($global_prefs, 'status_require_auth') === 'yes') ? '/admin/license.php' : '/license.php';
if (is_file('/etc/system-vendor')) {
  // hardware
  echo '<tr ', ($i++ % 2 == 0) ? 'class="dtrow0"' : 'class="dtrow1"', '>';
  echo '<td class="dialogText" style="text-align: right; font-weight: bold;">', 'Hardware:', '</td>';
  echo '<td style="text-align: left;">', htmlspecialchars(trim(@file_get_contents('/etc/system-vendor'))), '</td>';
  // gui version - license
  echo '<td class="dialogText" style="text-align: right; font-weight: bold;">', 'GUI Version:', '</td>';
  echo '<td style="text-align: left;">', $GUI_VERSION, '&nbsp;&nbsp;&ndash;&nbsp;&nbsp;';
  echo '<a href="'.$license.'" class="headerText">Show Licenses</a>', '</td>';
  putHtml('</tr>');
} else {
  // gui version
  echo '<tr ', ($i++ % 2 == 0) ? 'class="dtrow0"' : 'class="dtrow1"', '>';
  echo '<td class="dialogText" style="text-align: right; font-weight: bold;">', 'GUI Version:', '</td>';
  echo '<td style="text-align: left;">', $GUI_VERSION, '</td>';
  // license
  echo '<td class="dialogText" style="text-align: right; font-weight: bold;">', 'License:', '</td>';
  echo '<td style="text-align: left;">', '<a href="'.$license.'" class="headerText">Show Licenses</a>', '</td>';
  putHtml('</tr>');
}
// end of block
putHtml('</table>');
putHtml('</center></td></tr></table></center>');

// Now do all the status sections
//
// Status section can either be included directly into the HTML and
// sent down to the browser as a single large page. 
// Or the section can be asynchronously loaded using Javascript/AJAX
// methods for improved page load performance (recommended)
//
$name = 'status_sections';
// replace underscores with dashes in filename.
$filename = $COMMON.str_replace('_','-',$name).'.inc';
if (getPREFdef($global_prefs, 'async_load_status_page') !== 'no') {
  $url = 'status-load-section.php?sectionname='.$filename;
  putHtml("<span id='$name'><br>Loading...<br></span>");
  putHtml("<script language='JavaScript' type='text/javascript'>");
  putHtml("jx.load('$url',function(data){document.getElementById('$name').innerHTML=data;});");
  putHtml("</script>");
}
else {
  require_once $filename;
}

putHtml('<p style="color: orange;">Asterisk&reg; is a registered trademark of Digium, Inc.</p>');
} // End of HTTP GET
require_once $COMMON.'footer.php';

?>
