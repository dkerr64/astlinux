<?php
// status-load-section.inc
//
$myself = $_SERVER['PHP_SELF'];

require_once $COMMON.'functions.php';

require_once $COMMON.'version.php';

if (getPREFdef($global_prefs, 'status_require_auth') === 'yes') {
  if (! $STATUS_AUTH) {
    header('Location: /admin/status-load-section.php');
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

$daemon = getDaemons();

// Use popen() to start some longer-running tasks in parallel threads
//
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

include_once $_GET['sectionname'];;

?>
