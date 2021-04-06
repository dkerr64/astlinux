<?php
//####################################################################
// Copyright (C) 2021 Lonnie Abelbeck
//
// This is free software, licensed under the GNU General Public License
// version 3 as published by the Free Software Foundation; you can
// redistribute it and/or modify it under the terms of the GNU
// General Public License; and comes with ABSOLUTELY NO WARRANTY.

// vnstat.php for AstLinux
// 02-05-2021
// 02-12-2021 - Add graphs for hourly and daily traffic
//
//####################################################################
$myself = $_SERVER['PHP_SELF'];

require_once '../common/functions.php';

//####################################################################
// Function: display_section
//
//####################################################################
function display_section($output, $label) {

 // putHtml('<div style="float: left;">');
  putHtml('<div class="vnstat-txt-div">');
  putHtml('<h2 style="margin-block-start:0px">'.$label.':</h2>');
  putHtml('<pre class="vnstat-txt-pre">');

  while (! feof($output)) {
    if (($line = fgets($output, 1024)) !== FALSE) {
      if (($line = rtrim($line)) === '#next#') {
        break;
      }
      putText(rtrim($line));
    }
  }
  putHtml("</pre>");
  putHtml("</div>");
}


//####################################################################
// Main...
//
//####################################################################
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $result = 1;
  if (! $global_staff) {
    $result = 999;
  }
  header('Location: '.$myself.'?result='.$result);
  exit;
} else { // Start of HTTP GET
  $ACCESS_RIGHTS = 'staff';
  require_once '../common/header.php';

  $iface_opt = '';

  if (is_executable('/usr/bin/vnstat')) {
    @exec('/usr/bin/vnstat --dbiflist 1', $dbiflist_array, $dbiflist_status);

    if ($dbiflist_status != 0) {
      unset($dbiflist_array);
    } elseif (isset($dbiflist_array)) {
      if (count($dbiflist_array) == 1) {
        $iface = $dbiflist_array[0];
        if ($iface != '') {
          $iface_opt = ' -i '.$iface;
        }
        unset($dbiflist_array);
      } elseif (isset($_GET['iface'])) {
        $match_iface = $_GET['iface'];
        foreach ($dbiflist_array as $iface) {
          if ($iface != '' && $iface === $match_iface) {
            $iface_opt = ' -i '.$iface;
            break;
          }
        }
      }
    }

    $cmd = '/usr/bin/vnstat -hg'.$iface_opt;
    $cmd .= '; echo "#next#"';
    $cmd .= '; /usr/bin/vnstat'.$iface_opt;
    $cmd .= '; echo "#next#"';
    $cmd .= '; /usr/bin/vnstat -m'.$iface_opt;
    $cmd .= '; echo "#next#"';
    $cmd .= '; /usr/bin/vnstat -t'.$iface_opt;
    $cmd .= '; echo "#next#"';
    $cmd .= '; /usr/bin/vnstat -d'.$iface_opt;
    $cmd .= '; echo "#next#"';
    $cmd .= '; /usr/bin/vnstat -y'.$iface_opt;
    $cmd .= '; echo "#next#"';
    $vnstat_output = @popen($cmd, 'r');
    require_once '../common/chartist/chartist-vnstat.php';
  }

  putHtml('<center>');
  if (! is_file('/var/run/vnstat/vnstat.pid')) {
    putHtml('<p style="color: red;">The vnStat Daemon is not running.</p>');
  } else {
    putHtml('<p>&nbsp;</p>');
  }
  putHtml('</center>');
  putHtml('<center>');

  putHtml('<table class="status" style="width:100%"><tr><td style="text-align: center;">');
  putHtml('<h2>View Network Statistics:</h2>');
  if (isset($dbiflist_array)) {
    foreach ($dbiflist_array as $iface) {
      if ($iface != '') {
        putHtml('<a href="'.$myself.'?iface='.$iface.'" class="headerText">'.$iface.'</a>');
      }
    }
  }
 // putHtml('</td></tr><tr><td>');

  if (isset($vnstat_output)) {
    if ($vnstat_output !== FALSE) {
      putHtml('</td></tr><tr><td id="status-panel" class="vnstat-graphs">');
      // Now transition to Javascript to build the Chartist graphs...
      vnstat_graph_javascript($_GET['iface'], 'status-panel');
      // Add a new row to the table for the rest...
      putHtml('</td></tr><tr><td>');
      putHtml('<div class="vnstat-txt">');

      display_section($vnstat_output, "Hours Graph");

      display_section($vnstat_output, ($iface_opt !== '') ? "Summary" : "All Monitored Summary");

      display_section($vnstat_output, "Months");

      display_section($vnstat_output, "Top Days");

      display_section($vnstat_output, "Days");

      display_section($vnstat_output, "Years");

      putHtml('</div>');
      while (! feof($vnstat_output)) {
        fgets($vnstat_output, 1024);
      }
      pclose($vnstat_output);
    }
  } else {
    putHtml('<p style="color: red;">The vnStat package is not installed.</p>');
  }

  putHtml('</td></tr></table>');
  putHtml('</center>');
} // End of HTTP GET

require_once '../common/footer.php';

?>
