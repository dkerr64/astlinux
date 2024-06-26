<?php
//####################################################################
// Copyright (C) 2021 David Kerr
//
// This is free software, licensed under the GNU General Public License
// version 3 as published by the Free Software Foundation; you can
// redistribute it and/or modify it under the terms of the GNU
// General Public License; and comes with ABSOLUTELY NO WARRANTY.

// vnstat Chartist graphs for AstLinux
// 02-12-2021 - Add graphs for hourly and daily traffic
//
//####################################################################

$VNSTAT_CONFIG = get_vnstat_config();

$VNSTAT_DEBUG=0;
$VNSTAT_RXTX = intval(getPREFdef($global_prefs, 'vnstat_rxtx'));
$VNSTAT_HOURS_MODE = intval(getPREFdef($global_prefs, 'vnstat_days'));
$VNSTAT_DAYS_MODE = intval(getPREFdef($global_prefs, 'vnstat_months'));
// Graphs default to 3 days and 2 months,
if ($VNSTAT_HOURS_MODE == 0) $VNSTAT_HOURS_MODE = 3;
if ($VNSTAT_DAYS_MODE == 0) $VNSTAT_DAYS_MODE = 2;
// Graphs  must not exceed available data in vnStat db
$VNSTAT_HOURS_MODE = min($VNSTAT_HOURS_MODE, max(1, $VNSTAT_CONFIG['HourlyDays']));
$VNSTAT_DAYS_MODE  = min($VNSTAT_DAYS_MODE,  max(1, round($VNSTAT_CONFIG['DailyDays'] / 31)));
// Decimal places to round tx/rx values to...
$VNSTAT_ROUND = (getPREFdef($global_prefs, 'vnstat_precision') == '') ? 2 : intval(getPREFdef($global_prefs, 'vnstat_precision'));

function msg_debug($msg) {
  global $VNSTAT_DEBUG;
  if ($VNSTAT_DEBUG > 0) {
    syslog(LOG_DEBUG,"vnStat chart: $msg");
  }
}
function msg_debug2($msg) {
  global $VNSTAT_DEBUG;
  if ($VNSTAT_DEBUG > 1) {
    syslog(LOG_DEBUG,"vnStat chart: $msg");
  }
}
function msg_error($msg) {
  syslog(LOG_ERROR,"vnStat chart: $msg");
}

//####################################################################
//
//
//####################################################################
putHtml('<link rel="stylesheet" href="../common/chartist/chartist.css">');
putHtml('<script type="text/javascript" src="../common/chartist/chartist.js"></script>');
putHtml('<link rel="stylesheet" href="../common/chartist/chartist-astlinux.css">');
putHtml('<script type="text/javascript" src="../common/chartist/chartist-plugin-targetline.js"></script>');


//####################################################################
//
//
//####################################################################
function get_vnstat_config() : array {
  // Sed... 1) remove # comments, 2) remove blank lines,
  // 3) replace spaces outside of double quotes with equal sign
  return(parse_ini_string(@shell_exec('vnstat --showconfig | sed -r -e \'s/^#.*//\' -e \'/^$/ d\' -e \'s/("[^"]*"|\\S+)\\s+/\\1=/g\'')));
}

function day_of_week(int $d, int $m, int $y, int $mode=1) {
  $DAY_OF_WEEK = array ('Sunday','Monday','Tuesday','Wednesday','Thursday','Friday','Saturday');
  $SHORT_DAY_OF_WEEK = array ('Sun','Mon','Tue','Wed','Thu','Fri','Sat');
  $t = array(0, 3, 2, 5, 0, 3, 5, 1, 4, 6, 2, 4);
  $y -= $m < 3;
  $d = (($y + intdiv($y,4) - intdiv($y,100) + intdiv($y,400) + $t[$m-1] + $d) % 7);
  if ($mode == 1) return $DAY_OF_WEEK[$d];
  elseif ($mode == 2) return $SHORT_DAY_OF_WEEK[$d];
  else return $d;
}

function days_in_month(int $m, int $y) {
  $ds = array (31,28,31,30,31,30,31,31,30,31,30,31);
  return ($ds[$m-1] + ($m == 2 && !($y % 4) && ($y % 100 || !($y % 400))));
}

function name_of_month(int $m, int $mode=1) {
  $MONTHS = array ('January','February','March','April','May','June','July','August','September','October','November','December');
  $SHORT_MONTHS = array ('Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec');
  if ($mode == 1) return $MONTHS[$m-1];
  else return $SHORT_MONTHS[$m-1];
}

//####################################################################
//
//
//####################################################################
function define_vmstat_data_object() : array {
  $data = array(
    'labels' => array(),        // Labels to place on X axis
    'series' => array(          // Data value for each bar on chart
      array('name' => 'rx',
            'data' => array()   // Received data in bytes for each period
           ),
      array('name' => 'tx',
            'data' => array()   // Transmitted data in bytes for each period
           )
    ),
    'vnstat_index' => array(),  // each element is 0..23 (hour of day) or 1..31 (day of month)
    'vnstat_mode' => 0,         // 1 = last 24hrs/31days, 2..n = this period plus n-1 previous periods
    'vnstat_name' => '',        // Name assigned in vnstat (e.g eth0)
    'vnstat_alias' => '',       // Alias assigned in vnstat (e.g. External)
    'vnstat_data_count' => 0,   // Sample size of data (may be less than array length)
    'vnstat_max_rx' => 0,       // The highest value in the rx array
    'vnstat_max_tx' => 0,       // The highest value in the tx array
    'vnstat_total_rx' => 0,     // The sum of all values in the rx array
    'vnstat_total_tx' => 0,     // The smm of all values in the tx array
    'vnstat_average_rx' => 0,   // Average rx per period (hour or day)
    'vnstat_average_tx' => 0,   // Average tx per period (hour or day)
    'vnstat_estimated_rx' => 0, // Estimated rx to end of day or month (mode >=2)
    'vnstat_estimated_tx' => 0, // Estimated tx to end of day or month (mode >=2)
    'vnstat_cumulative' => 0,   // 1 = values are cumulative not each period
    'vnstat_update_time' => '', // Database updated at day/time string
    'ch_target_line' => 0       // Value for horizontal marker line on graph
  );
  return $data;
}

//####################################################################
//
//
//####################################################################
function get_hour_data(array $vnstat_if, int $mode=1) : array {
  $data = define_vmstat_data_object();
  $labels = array(0,6,12,18);
  $pad_to_24hrs = ($mode == 1 ? 0 : 1);
  $seek_to_24hrs = ($mode == 1 ? 0 : 1);
  $requested_hours = $mode * 24;
  $data['vnstat_mode'] = $mode;
  $data['vnstat_name'] = $vnstat_if['name'];
  $data['vnstat_alias'] = $vnstat_if['alias'];
  // Find the date that data was last updated (should be today)
  $updated_minute = $vnstat_if['updated']['time']['minute'];
  $updated_hour = $vnstat_if['updated']['time']['hour'];
  $updated_day = $vnstat_if['updated']['date']['day'];
  $updated_month = $vnstat_if['updated']['date']['month'];
  $updated_year = $vnstat_if['updated']['date']['year'];
  $timestamp = mktime($updated_hour,$updated_minute,0,$updated_month,$updated_day,$updated_year);
  $data['vnstat_update_time'] = date('Y-m-d H:i T',$timestamp);
  $updated_hour_stamp = intdiv($timestamp,3600);
  // For estimating the remaining traffic to end-of-day, keep running totals.
  $est_average_rx_total = 0;
  $est_average_tx_total = 0;
  $est_average_count = 0;
  msg_debug2($vnstat_if['alias'] . " (" . $vnstat_if['name'] . "): Hour updated timestamp: $updated_hour_stamp at $updated_hour:$updated_minute hrs");
  // Explicitly unset this variable so we can detect first time though the loop.
  unset($hour);
  foreach ($vnstat_if['traffic']['hour'] as $record) {
    // We are looping through each hour of the traffic.
    $this_hour = $record['time']['hour'];
    $this_day = $record['date']['day'];
    $this_month = $record['date']['month'];
    $this_year = $record['date']['year'];
    $this_hour_stamp = intdiv(mktime($this_hour,0,0,$this_month,$this_day,$this_year),3600);
    $add_msg = "";  // Anything in this is added to end of debug messages.
    if (($mode > 1) && ($this_hour >= $updated_hour) &&
        ($this_hour_stamp < $updated_hour_stamp)) {
      // keep running total and only count it if one of rx/tx data
      // is not zero.  Note that the above condition includes hours
      // that are after the current hour of the day.  We do this to more
      // accurately estimate traffic to the end of day because data usage
      // can vary widely over 24 hours, we will base our estimate on averages
      // from similar time of day in the past.  So for example if it is 4pm now.
      // only count previous day(s) traffic between 4pm and midnight and average that.
      if (($record['rx'] != 0) || ($record['tx'] != 0)) {
        // exclude hours with no traffic
        $add_msg = "Include for est_average.";
        if ($this_hour == $updated_hour) {
          // if we are updating database multiple times per hour then we will
          // have partial data for the current hour, so for estimating remaining
          // only use fraction of the historical full hours
          $est_average_rx_total += $record['rx'] * (60-$updated_minute) / 60;
          $est_average_tx_total += $record['tx'] * (60-$updated_minute) / 60;
          $add_msg = $add_msg . "(partial hour)";
          // yes, increment by a fraction !
          $est_average_count += ((60-$updated_minute) / 60);
        } else {
          $est_average_rx_total += $record['rx'];
          $est_average_tx_total += $record['tx'];
          $est_average_count += 1;
        }
      }
    }
    // Skip all records over $requested_hours ago
    if (($updated_hour_stamp - $this_hour_stamp > $requested_hours) ||
        ($seek_to_24hrs && ($this_hour != 0))) {
      msg_debug2("Skipping data older than $requested_hours hours ($this_year/$this_month/$this_day $this_hour) $add_msg");
      continue;
    }
    else $seek_to_24hrs = 0;
    // initialize hour tracking variable if this is first time through.
    if (!isset($hour)) $hour = ($this_hour + 23) % 24;
    while (($hour = ($hour + 1) % 24) != $this_hour) {
      // If there are gaps in our data, insert the missing
      // records with zero rx/tx values.
      msg_debug2("Pad missing hour $hour ($this_hour)");
      // Add X-axis labels.  If midnight include the day name.
      $lbl = ($hour == 0) ? sprintf('%02d\n%s', $hour, day_of_week($this_day,$this_month,$this_year, 1)) : sprintf('%02d', $hour);
      if ($mode != 1 && !in_array($hour,$labels)) $lbl = '';
      array_push($data['labels'],$lbl);
      // Pad with zeros
      array_push($data['series'][0]['data'],0);
      array_push($data['series'][1]['data'],0);
      array_push($data['vnstat_index'],$hour);
    }
    msg_debug2("Add hour $hour ($this_hour) $add_msg");
    // Add X-axis labels.  If midnight include the day name.
    $lbl = ($hour == 0) ? sprintf('%02d\n%s', $hour, day_of_week($this_day,$this_month,$this_year, 1)) : sprintf('%02d', $hour);
    if ($mode != 1 && !in_array($hour,$labels)) $lbl = '';
    array_push($data['labels'],$lbl);
    // Add rx/tx data
    array_push($data['series'][0]['data'],$record['rx']);
    array_push($data['series'][1]['data'],$record['tx']);
    array_push($data['vnstat_index'],$hour);
    $data['vnstat_data_count']++;
    // Keep running totals
    $data['vnstat_max_rx'] = max($data['vnstat_max_rx'], $record['rx']);
    $data['vnstat_max_tx'] = max($data['vnstat_max_tx'], $record['tx']);
    $data['vnstat_total_rx'] += $record['rx'];
    $data['vnstat_total_tx'] += $record['tx'];
  }
  // End of looping through all our hourly data.  Calulate end values...
  $data['vnstat_average_rx'] = intdiv($data['vnstat_total_rx'], $data['vnstat_data_count']);
  $data['vnstat_average_tx'] = intdiv($data['vnstat_total_tx'], $data['vnstat_data_count']);
  if ($est_average_count > 0) {
    // Now calculate estimated traffic for the remainder of this day
    msg_debug2("calculate rx average with $est_average_rx_total / $est_average_count * (23 - $hour + ((60-$updated_minute) / 60)");
    $data['vnstat_estimated_rx'] = round($est_average_rx_total / $est_average_count * (23 - $hour + ((60-$updated_minute) / 60)));
    $data['vnstat_estimated_tx'] = round($est_average_tx_total / $est_average_count * (23 - $hour + ((60-$updated_minute) / 60)));
  }
  if ($pad_to_24hrs) {
    msg_debug2("Pad with " . (23 - $hour) . " hours to end of day");
    while ($hour++ < 23) {
      // Add X-axis labels.  If midnight include the day name.
      $lbl = ($hour == 0) ? sprintf('%02d\n%s', $hour, day_of_week($this_day,$this_month,$this_year, 1)) : sprintf('%02d', $hour);
      array_push($data['labels'],in_array($hour,$labels) ? $lbl : '');
      // Pad with zeros
      array_push($data['series'][0]['data'],0);
      array_push($data['series'][1]['data'],0);
      array_push($data['vnstat_index'],'');
    }
  }
  // now pad the front of our arrays to get the length we need
  $data['labels'] = array_pad($data['labels'],-$requested_hours,'');
  $data['series'][0]['data'] = array_pad($data['series'][0]['data'],-$requested_hours,0);
  $data['series'][1]['data'] = array_pad($data['series'][1]['data'],-$requested_hours,0);
  $data['vnstat_index'] = array_pad($data['vnstat_index'],-$requested_hours,'');
  return $data;
}


//####################################################################
//
//
//####################################################################
function get_day_data(array $vnstat_if, int $mode=1) : array {
  $data = define_vmstat_data_object();
  $labels = array(1,7,14,21,28);
  $requested_days = $mode * 31;
  $requested_months = $mode;
  $pad_to_mth = ($mode == 1 ? 0 : 1);
  $seek_to_mth = ($mode == 1 ? 0 : 1);
  $seek_to_day = ($mode == 1 ? 1 : 0);
  $data['vnstat_mode'] = $mode;
  $data['vnstat_name'] = $vnstat_if['name'];
  $data['vnstat_alias'] = $vnstat_if['alias'];
  // Find the date that data was last updated (should be today)
  $updated_minute = $vnstat_if['updated']['time']['minute'];
  $updated_hour = $vnstat_if['updated']['time']['hour'];
  $updated_day = $vnstat_if['updated']['date']['day'];
  $updated_month = $vnstat_if['updated']['date']['month'];
  $updated_year = $vnstat_if['updated']['date']['year'];
  $timestamp = mktime($updated_hour,$updated_minute,0,$updated_month,$updated_day,$updated_year);
  $data['vnstat_update_time'] = date('Y-m-d H:i T',$timestamp);
  $updated_mth_stamp = $updated_year * 12 + $updated_month;
  $updated_day_stamp = intdiv($timestamp,86400);
  // For estimating the remaining traffic to end-of-month, keep running totals.
  $est_average_rx_total = 0;
  $est_average_tx_total = 0;
  $est_average_count = 0;
  // running count of total days in the months we are graphing
  $total_days = 0;

  msg_debug2($vnstat_if['alias'] . " (" . $vnstat_if['name'] . "): Day updated timestamp: $updated_mth_stamp, $updated_day_stamp at $updated_hour:$updated_minute hrs");
  // Explicitly unset these variables so we can detect first time though the loop.
  unset($days_in_month, $day);
  foreach ($vnstat_if['traffic']['day'] as $record) {
    // We are looping through each day of the traffic.
    // Start by recording current date and create timestamp
    $this_day = $record['date']['day'];
    $this_month = $record['date']['month'];
    $this_year = $record['date']['year'];
    $this_mth_stamp = $this_year*12 + $this_month;
    $this_day_stamp = intdiv(mktime(0,0,0,$this_month,$this_day,$this_year),86400);
    $add_msg = "";  // Anything in this is added to end of debug messages.

    // if first time through, find current days in month.
    if (!isset($days_in_month)) $days_in_month = days_in_month($this_month,$this_year);
    elseif (($mode > 1) && ($this_day_stamp < $updated_day_stamp)) {
      // if not first time thought, start to keep running total so we
      // can calculate averages. Note that the above condition excludes
      // the first and last days... which may contain partial day data.
      // Our daily average will be more accurate if we exclude those.
      if (($record['rx'] != 0) || ($record['tx'] != 0)) {
        // exclude days with no traffic
        $est_average_rx_total += $record['rx'];
        $est_average_tx_total += $record['tx'];
        $est_average_count++;
        $add_msg = "Include for est_average.";
      }
    }
    // Skip all records over $requested_months ago
    $add_msg = $add_msg . "(".$this_mth_stamp.")";
    if (($seek_to_mth && ($updated_mth_stamp - $this_mth_stamp >= $requested_months)) ||
        ($seek_to_day && ($updated_day_stamp - $this_day_stamp >= $requested_days))) {
      msg_debug2("Skipping data older than $requested_months months ($this_year/$this_month/$this_day) $add_msg");
      if ($this_day == 1) $days_in_month = days_in_month($this_month,$this_year);
      continue;
    }
    elseif ($seek_to_mth) {
      $seek_to_mth = 0; // we only want to come in here once.
      if ($pad_to_mth && $this_day > 1) {
        // We want our array of data to start on the first day of the month
        // even if the first data point we have is mid-month.  So pad the
        // array with empties.
        msg_debug2("Insert with " . ($this_day - 1) . " days from start of month");
        // Pad front of our chart with empty space to fill whole month
        $data['labels'] = array_fill(0,$this_day-1,'');
        $data['series'][0]['data'] = array_fill(0,$this_day-1,0);
        $data['series'][1]['data'] = array_fill(0,$this_day-1,0);
        $data['vnstat_index'] = array_fill(0,$this_day-1,'');
        // Add X-axis labels.  If first of month include the month name.
        foreach ($labels as $i) if ($i < $this_day) $data['labels'][$i-1] = $i;
        $data['labels'][0] = sprintf('%d\n%s', 1, name_of_month($this_month, 1));
      }
    }
    if (!isset($day)) {
      // initialize day tracking variables if this is first time through.
      $first_day = $this_day;
      $day = $this_day - 1;
    }
    while (($day = ($day % $days_in_month) + 1) != $this_day) {
      // If there are gaps in our data, insert the missing
      // records with zero rx/tx values.
      if ($day == 1) {
        // First day of a new month, recalculate days in month.
        $days_in_month = days_in_month($this_month,$this_year);
        $total_days += $days_in_month;
      }
      msg_debug2("Pad missing day $day ($this_day) ($this_year/$this_month), days = $days_in_month");
      // Add X-axis labels.  If first of month include the month name.
      $lbl = ($day == 1) ? sprintf('%d\n%s', $day, name_of_month($this_month, 1)) : $day;
      if ($mode != 1) {
        if (!in_array($day,$labels)) $lbl = '';
        else if ($day == 28 && $this_month == 2 && $updated_month != $this_month) $lbl = '';
      }
      array_push($data['labels'],$lbl);
      // Pad with zeros
      array_push($data['series'][0]['data'],0);
      array_push($data['series'][1]['data'],0);
      array_push($data['vnstat_index'],$day);
    }
    if ($this_day == 1) {
      // Another check for first day of a new month, recalculate days in month.
      $days_in_month = days_in_month($this_month,$this_year);
      $total_days += $days_in_month;
    }
    msg_debug2("Add day $day ($this_day) ($this_year/$this_month), days = $days_in_month. $add_msg");
    // Add X-axis labels.  If first of month include the month name.
    $lbl = ($day == 1) ? sprintf('%d\n%s', $day, name_of_month($this_month, 1)) : $day;
    if ($mode != 1) {
      if (!in_array($day,$labels)) $lbl = '';
      else if ($day == 28 && $this_month == 2 && $updated_month != $this_month) $lbl = '';
    }
    array_push($data['labels'],$lbl);
    // Add traffic data to array, update running totals, etc.
    array_push($data['series'][0]['data'],$record['rx']);
    array_push($data['series'][1]['data'],$record['tx']);
    array_push($data['vnstat_index'],$this_day);
    $data['vnstat_data_count']++;
    // Keep running totals
    $data['vnstat_max_rx'] = max($data['vnstat_max_rx'], $record['rx']);
    $data['vnstat_max_tx'] = max($data['vnstat_max_tx'], $record['tx']);
    $data['vnstat_total_rx'] += $record['rx'];
    $data['vnstat_total_tx'] += $record['tx'];
  }
  // Processed all data...
  // When calculating average need to account for data in current day being
  // only a partial day.  So calculate based on hours not days.
  $hrs = ($data['vnstat_data_count']-1)*24+$updated_hour+1;
  $data['vnstat_average_rx'] = intdiv($data['vnstat_total_rx'] * 24, $hrs);
  $data['vnstat_average_tx'] = intdiv($data['vnstat_total_tx'] * 24, $hrs);
  if ($est_average_count > 0) {
    // Now calculate estimated traffic for the remainder of this month.  Note that this is from
    // tomorrow onwards.  DOES NOT INCLUDE remainder of today... that needs to be added later.
    $data['vnstat_estimated_rx'] = intdiv($est_average_rx_total, $est_average_count) * ($days_in_month - $day);
    $data['vnstat_estimated_tx'] = intdiv($est_average_tx_total, $est_average_count) * ($days_in_month - $day);
  }
  if ($pad_to_mth) {
    // Pad zeros onto the end of the array to fill out to the end of the month
    msg_debug2("Pad with " . ($days_in_month - $day) . " days to end of month");
    while ($day++ < $days_in_month) {
      // Add X-axis labels.  If first of month include the month name.
      $lbl = ($day == 1) ? sprintf('%d\n%s', $day, name_of_month($this_month, 1)) : $day;
      array_push($data['labels'],in_array($day,$labels) ? $lbl : '');
      // Pad with zeros
      array_push($data['series'][0]['data'],0);
      array_push($data['series'][1]['data'],0);
      array_push($data['vnstat_index'],'');
    }
  }
  // now pad the front of our arrays to get the length we need
  $total_days = min($total_days,$requested_days);
  $cnt = count($data['vnstat_index']);
  msg_debug("Array size: $cnt, total days: $total_days, first day: $first_day");
  if ($cnt < $total_days) {
    $data['labels'] = array_pad($data['labels'],-$total_days,'');
    $data['series'][0]['data'] = array_pad($data['series'][0]['data'],-$total_days,0);
    $data['series'][1]['data'] = array_pad($data['series'][1]['data'],-$total_days,0);
    $data['vnstat_index'] = array_pad($data['vnstat_index'],-$total_days,'');
    $n = $first_day - ($total_days - $cnt);
    for ($i = 1; $i <= $total_days; $i++) if (($x=$i-$n) >= 0) {
      if ($i == 1) $data['labels'][$x] = sprintf('%d\n%s', 1, name_of_month(($this_month+23)%24, 1));
      else $data['labels'][$x] = $i;
    }
  }
  return $data;
}


//####################################################################
// Function:
//
//####################################################################
function get_vnstat_data_as_json($vnstat_interfaces = 'eth0') : string {
  global $VNSTAT_HOURS_MODE;
  global $VNSTAT_DAYS_MODE;
  $vnstat_interfaces = str_replace(',', ' ',$vnstat_interfaces);
  $interfaces = explode(' ',$vnstat_interfaces);
  $data = array();

  // Request all the hours / day data that we can get... the more we have
  // the better our estimate daily/monthly calculations will be.
  $hours = ($VNSTAT_HOURS_MODE > 0) ? json_decode(@exec('/usr/bin/vnstat --json h'), TRUE) : null;
  $days = ($VNSTAT_DAYS_MODE > 0) ? json_decode(@exec('/usr/bin/vnstat --json d'), TRUE) : null;
  foreach ($interfaces as $if) {
    // Well this is faily convoluted, I could have done a foreach over both of xxx['interfaces'] but
    // that would be less elegant especially as I need to take data from 'hours' and add it to 'days'
    $data[$if]['hours'] = get_hour_data($hours['interfaces'][array_search($if,array_column($hours['interfaces'],'name'))], $VNSTAT_HOURS_MODE);
    $data[$if]['days'] = get_day_data($days['interfaces'][array_search($if,array_column($hours['interfaces'],'name'))], $VNSTAT_DAYS_MODE);
    // The estimated remaining traffic for 'days' starts at midnight tonight. Therefore
    // we have to add on the estimated traffic for the remaining 'hours' of today.
    $data[$if]['days']['vnstat_estimated_rx'] += $data[$if]['hours']['vnstat_estimated_rx'];
    $data[$if]['days']['vnstat_estimated_tx'] += $data[$if]['hours']['vnstat_estimated_tx'];
  }
  return json_encode($data);
}


//####################################################################
// Function:
//
//####################################################################
function vnstat_graph_javascript($vnstat_interfaces = 'eth0', $element) {
  global $VNSTAT_CONFIG;
  global $VNSTAT_RXTX;
  global $VNSTAT_ROUND;
  ?>
  <script>
    //----------------------------------------------------------------
    // Function to do a deep copy of a Javascript object or array
    function cloneObject(obj) {
      if (obj === undefined) return undefined;
      var clone = Array.isArray(obj) ? [] : {};
      for(var i in obj) {
        if(typeof(obj[i])=="object" && obj[i] != null)
          clone[i] = cloneObject(obj[i]);
        else
          clone[i] = obj[i];
      }
      return clone;
    }

    //----------------------------------------------------------------
    // Function to sum up all the rx and tx data and recalculate
    // the maximum value in the array.
    function createTotalsData(d) {
      if (d === undefined) return;
      var rx_total = 0;
      var tx_total = 0;
      var last_index = 0;
      for (var i in d.vnstat_index) {
        if ((d.vnstat_mode != 1) && ((d.vnstat_index[i] == '') || (d.vnstat_index[i] < last_index))) {
          d.vnstat_max_rx = Math.max(d.vnstat_max_rx,rx_total);
          d.vnstat_max_tx = Math.max(d.vnstat_max_tx,tx_total);
          d.vnstat_last_rx = rx_total > 0 ? rx_total : d.vnstat_last_rx
          d.vnstat_last_tx = tx_total > 0 ? tx_total : d.vnstat_last_tx;
          rx_total = tx_total = 0;
        }
        rx_total = d.series[0].data[i] = d.series[0].data[i] + rx_total;
        tx_total = d.series[1].data[i] = d.series[1].data[i] + tx_total;
        last_index = d.vnstat_index[i];
      }
      d.vnstat_last_rx = rx_total > 0 ? rx_total : d.vnstat_last_rx
      d.vnstat_last_tx = tx_total > 0 ? tx_total : d.vnstat_last_tx;
      d.vnstat_max_rx = Math.max(d.vnstat_max_rx,rx_total);
      d.vnstat_max_tx = Math.max(d.vnstat_max_tx,tx_total);
      d.vnstat_cumulative = 1;
      d.ch_target_line = (d.vnstat_mode > 1) ? d.vnstat_estimated_rx +  d.vnstat_last_rx +
                                               d.vnstat_estimated_tx +  d.vnstat_last_tx
                                             : 0;
    }

    //----------------------------------------------------------------
    // Function to normalize the data based on order of magnitude of the maximum
    // values in the data series arrays.  We use this to figure out whether
    // to display KB, MB, GB, TB on the vertical axis.  We also divide our data (bytes)
    // to match this, we do it here so that Chartist 'onlyInteger" setting works.
    function normalizeByOrderOfMagnitude(d) {
      if (d === undefined) return;
      var kb_divisor = <?php echo ($VNSTAT_CONFIG['UnitMode'] == 2 ? 1000 : 1024)?>;
      var byte_labels = <?php echo ($VNSTAT_CONFIG['UnitMode'] == 0 ? '[ "B", "KiB", "MiB", "GiB", "TiB", "PiB" ]' : '[ "B", "KB", "MB", "GB", "TB", "PB" ]')?>;
      d.oom = Math.floor(Math.floor(Math.log(Math.max(d.ch_target_line,d.vnstat_max_rx + d.vnstat_max_tx)) / Math.LN10) / 3);
      d.byte_label = byte_labels[d.oom];
      var divisor = Math.pow(kb_divisor, d.oom);
      if (divisor > 1) {
        for (var i in d.vnstat_index) {
          d.series[0].data[i] /= divisor;
          d.series[1].data[i] /= divisor;
        }
        d.vnstat_max_rx /= divisor;
        d.vnstat_max_tx /= divisor;
        d.vnstat_last_rx /= divisor;
        d.vnstat_last_tx /= divisor;
        d.vnstat_total_rx /= divisor;
        d.vnstat_total_tx /= divisor;
        d.vnstat_average_rx /= divisor;
        d.vnstat_average_tx /= divisor;
        d.vnstat_estimated_rx /= divisor;
        d.vnstat_estimated_tx /= divisor;
        d.ch_target_line /= divisor;
      }
    }

    //----------------------------------------------------------------
    // Function to create "target" line on chart.
    function createTargetLine(d, txt, type) {
      // type == 'average' or 'estimated'
      if ((type === 'estimated') && (d.vnstat_mode < 2)) return;
      var rx = d['vnstat_'+type+'_rx'];
      var tx = d['vnstat_'+type+'_tx'];
      var prec = <?php echo $VNSTAT_ROUND?>;
      rx += (type === 'estimated') ? d.vnstat_last_rx : 0;
      tx += (type === 'estimated') ? d.vnstat_last_tx : 0;
      var rxtx = <?php echo $VNSTAT_RXTX?>;
      d.ch_target_line = rx + tx;
      d.ch_target_text = txt + d.ch_target_line.toFixed(prec) + d.byte_label +
                         (rxtx ? (" (rx: " + rx.toFixed(prec) + d.byte_label +
                                   " tx: " + tx.toFixed(prec) + d.byte_label + ")") : "");
    }

    //----------------------------------------------------------------
    // Function to place labels on the cumulative charts to show
    // total for each day or month.
    function periodTotalLabels(context) {
      var d = context.options.vnstat_data;
      var prec = <?php echo $VNSTAT_ROUND?>;

      if (!d.vnstat_cumulative) return;
      // convert each unit for X and Y into pixels
      var ux = (context.chartRect.x2 - context.chartRect.x1) / d.vnstat_index.length;
      var uy = (context.chartRect.y2 - context.chartRect.y1) / (context.bounds.max - context.bounds.min);
      // find first index on our array that is start of a day or start of a month
      var start = 0;  // days start at 00:00 hrs
      var i = d.vnstat_index.indexOf(start,1);
      // if 00:00 hrs not found then months start on 1st day
      if (i < 0) i = d.vnstat_index.indexOf(++start,1);
      // so 'start' is now 0 or 1 and 'i' is index to the array.
      // find the last non-zero value in our data.
      var last = d.vnstat_index.length;
      while (last-- && !d.vnstat_index[last]);
      // initialise our label text with name of day or month
      var txt = d.labels[0].toString();
      txt = (d.vnstat_mode > 1) ? txt.substr(txt.indexOf('\n')+1) + '<br>' : 'Total<br>';
      // so we now have from i[ndex] to last to work with
      while ((i > 0) || (last > 0)) {
        // if i < 0 then we are at the last value in the data
        if (i <= 0) { i = last+1; last = -1; }
        if ((d.vnstat_mode > 1) || (last == -1)) {
          var rx = d.series[0].data[i-1];
          var tx = d.series[1].data[i-1];
          var tt = rx + tx;
          var rxtx = <?php echo $VNSTAT_RXTX?>;
          var label = txt + tt.toFixed(prec) + d.byte_label +
                   (rxtx ? ("<br>rx: " + rx.toFixed(prec) + d.byte_label +
                            "<br>tx: " + tx.toFixed(prec) + d.byte_label ) : "");
          // Use foreignObject rather than text so we can apply HTML styles (like background)
          var span = document.createElement('span');
          span.setAttribute("class", "ct-subtotal-label");
          span.innerHTML = label;
          var fobj = context.svg.foreignObject(span, { x: -1000, y: -1000 }, '', false);
          var rect = span.getBoundingClientRect();
          fobj._node.setAttribute('x', Math.min(context.chartRect.x2 - rect.width, context.chartRect.x1 + ux * i) + 'px');
          fobj._node.setAttribute('y', context.chartRect.y1 + Math.max((context.chartRect.y2 - context.chartRect.y1) / 2, Math.min(uy * tt,-rect.height)) + 'px');
          fobj._node.setAttribute('width', Math.ceil(rect.width) + 'px');
          fobj._node.setAttribute('height', Math.ceil(rect.height) + 'px');
        }
        // Capture name of day or month for next label
        txt = (d.labels[i]) ? d.labels[i].toString() : '';
        txt = (d.vnstat_mode > 1) ? txt.substr(txt.indexOf('\n')+1) + '<br>' : 'Total<br>';
        // Now seek to the next start of day or start of month
        i = d.vnstat_index.indexOf(start,i+1);
      }
    }


    //----------------------------------------------------------------
    //----------------------------------------------------------------
    // Copy constants from the PHP domain into the browser Javascript domain
    var data_json = '<?php echo get_vnstat_data_as_json($vnstat_interfaces)?>';
    var vnstat_interfaces = '<?php echo $vnstat_interfaces ?>';
    var element = '<?php echo $element ?>';

    // We need to adjust the width of bars in our chart based on the number
    // of bars displayed.  Chartist requires this be done in CSS styles
    // 24hrs, 31days, 48hrs, 62days, 72hrs, etc. etc.
    var bar_css_index = [ 25, 31, 48, 62, 72, 93, 96, 124, 144, 168 ];
    var bar_css = [ "ct-bar24", "ct-bar31", "ct-bar48", "ct-bar62", "ct-bar72", "ct-bar93", "ct-bar96", "ct-bar124", "ct-bar144", "ct-bar168" ];
    // elemId must match names of objects inside iif_data.  Order determines order of <div> elements
    var elemId = [ "days_total", "days", "hours_total", "hours" ];
    var status_panel = document.getElementById(element);
    var tbody = status_panel.parentElement;
    while (tbody.nodeName != 'TBODY') tbody = tbody.parentElement;

    // Loop through the data selecting only those interfaces that
    // we want a graph for.
    var interfaces = vnstat_interfaces.split(/[ ,]+/);  // make list an array
    var data = JSON.parse(data_json);
    var rxtx = <?php echo $VNSTAT_RXTX?>;
    var title_text = "";
    var updated_text = "unknown";
    interfaces.forEach(function (iif) {
      var iif_data = data[iif];
      if (!iif_data) return;  // No data for this interface name.
      // clone the hours and days objects because we are going to create two graphs
      // with one requiring we add up all values (replacing each period value).
      if (iif_data.hours) {
        iif_data.hours_total = cloneObject(iif_data.hours);
        createTotalsData(iif_data.hours_total);
        normalizeByOrderOfMagnitude(iif_data.hours);
        normalizeByOrderOfMagnitude(iif_data.hours_total);
        createTargetLine(iif_data.hours,"Average hourly traffic: ", "average");
        createTargetLine(iif_data.hours_total,"Today's estimated traffic: ", "estimated");
        title_text = iif_data.hours.vnstat_alias + " (" + iif_data.hours.vnstat_name + ")";
        updated_text = iif_data.hours.vnstat_update_time;
      }
      if (iif_data.days) {
        iif_data.days_total = cloneObject(iif_data.days);
        createTotalsData(iif_data.days_total);
        normalizeByOrderOfMagnitude(iif_data.days);
        normalizeByOrderOfMagnitude(iif_data.days_total);
        createTargetLine(iif_data.days,"Average daily traffic: ", "average");
        createTargetLine(iif_data.days_total,"This month's estimated traffic: ", "estimated");
        title_text = iif_data.days.vnstat_alias + " (" + iif_data.days.vnstat_name + ")";
        updated_text = iif_data.days.vnstat_update_time;
      }      

      var title_row = tbody.insertRow(1);
      var cell = title_row.insertCell(0);
      cell.style.paddingBottom = "0px";
      cell.innerHTML = '<h2 style="text-align:left; width:30%; display:inline-block; margin-block-start:0px; margin-block-end:0px">'+
        title_text+
        '</h2>'+
        '<span style="text-align:right; font-size:1em; width:70%; display:inline-block; margin-block-start:0px; margin-block-end:0px">'+
        'Database updated: '+updated_text+
        '</span>'+
        '<br>'+
        '<span style="text-align:center; width:100%; display: inline-block; margin-top: 0.2em">'+
        '<span style="display: inline-block; outline: 1px solid #54545C;">'+
        '<span class="ct-series-a">'+
        '<svg width="3em" height="0.8em"><rect x="0.5em" y="0.2em" width="2em" height="0.6em"></svg>'+
        'Received&nbsp;data </span>'+
        '<span class="ct-series-b">'+
        '<svg width="3em" height="0.8em"><rect x="0.5em" y="0.2em" width="2em" height="0.6em"></svg>'+
        'Transmitted&nbsp;data&nbsp;</span>'+
        '</span></span>';
      // Create <div> elements for each graph and insert them into the DOM
      elemId.forEach(function (elem) {
        var d = iif_data[elem];
        if (d === undefined) return;
        var id = iif + elem;
        var div = document.createElement('div');

        div.setAttribute("id", id);
        div.setAttribute("class", "ct-chart ct-major-seventh ct-astlinux-box");

        status_panel.insertBefore(div, status_panel.childNodes[0]);

        var l = d.vnstat_index.length;
        var css = bar_css[ bar_css_index.findIndex(function (val) { return val >= l; }) ];
        var chOptions = {
          stackBars: true,
          axisY: {
            onlyInteger: ((d.vnstat_max_rx + d.vnstat_max_rx) > 4) ? true : false,
            labelInterpolationFnc: function(value) {
              return (value + d.byte_label);
            },
          },
          axisX: {
            showGrid: false
          },
          classNames: {
            bar: css
          },
          vnstat_data: d,
        };
        if (d.ch_target_line > 0) {
          // Make sure the Y axis goes high enough to accommodate the target line
          chOptions.axisY.high = Math.max(d.ch_target_line * 1.02 ,(d.vnstat_max_rx + d.vnstat_max_tx));
          chOptions.plugins = [ Chartist.plugins.ctTargetLine({
            value: d.ch_target_line,
            label: d.ch_target_text,
            textAlign: 'right',
            labelOffset: { y: 1 }
          }) ];
        }
        d.chart = new Chartist.Bar("#" + id, d, chOptions);
        // Now add labels to the chart for totals for each day or month
        d.chart.on('created', periodTotalLabels);
      });
    });
  </script>
  <?php
}

?>
