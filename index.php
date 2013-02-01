<?php
//  Copyright (c) 2009 Facebook
//
//  Licensed under the Apache License, Version 2.0 (the "License");
//  you may not use this file except in compliance with the License.
//  You may obtain a copy of the License at
//
//      http://www.apache.org/licenses/LICENSE-2.0
//
//  Unless required by applicable law or agreed to in writing, software
//  distributed under the License is distributed on an "AS IS" BASIS,
//  WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
//  See the License for the specific language governing permissions and
//  limitations under the License.
//
//
// XHProf: A Hierarchical Profiler for PHP
//
// XHProf has two components:
//
//  * This module is the UI/reporting component, used
//    for viewing results of XHProf runs from a browser.
//
//  * Data collection component: This is implemented
//    as a PHP extension (XHProf).
//
//
//
// @author(s)  Kannan Muthukkaruppan
//             Changhao Jiang
//
// by default assume that xhprof_html & xhprof_lib directories
// are at the same level.
$GLOBALS['XHPROF_LIB_ROOT'] = dirname(__FILE__) . '/../xhprof_lib';
// require $GLOBALS['XHPROF_LIB_ROOT'] . '/display/xhprof.php';
//  Copyright (c) 2009 Facebook
//
//  Licensed under the Apache License, Version 2.0 (the "License");
//  you may not use this file except in compliance with the License.
//  You may obtain a copy of the License at
//
//      http://www.apache.org/licenses/LICENSE-2.0
//
//  Unless required by applicable law or agreed to in writing, software
//  distributed under the License is distributed on an "AS IS" BASIS,
//  WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
//  See the License for the specific language governing permissions and
//  limitations under the License.
//

//
// XHProf: A Hierarchical Profiler for PHP
//
// XHProf has two components:
//
//  * This module is the UI/reporting component, used
//    for viewing results of XHProf runs from a browser.
//
//  * Data collection component: This is implemented
//    as a PHP extension (XHProf).
//
// @author Kannan Muthukkaruppan
//

if (!isset($GLOBALS['XHPROF_LIB_ROOT'])) {
  // by default, the parent directory is XHPROF lib root
  $GLOBALS['XHPROF_LIB_ROOT'] = realpath(dirname(__FILE__) . '/..');
}

// require_once $GLOBALS['XHPROF_LIB_ROOT'].'/utils/xhprof_lib.php';
//  Copyright (c) 2009 Facebook
//
//  Licensed under the Apache License, Version 2.0 (the "License");
//  you may not use this file except in compliance with the License.
//  You may obtain a copy of the License at
//
//      http://www.apache.org/licenses/LICENSE-2.0
//
//  Unless required by applicable law or agreed to in writing, software
//  distributed under the License is distributed on an "AS IS" BASIS,
//  WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
//  See the License for the specific language governing permissions and
//  limitations under the License.
//

//
// This file contains various XHProf library (utility) functions.
// Do not add any display specific code here.
//

function xhprof_error($message) {
  error_log($message);
}

/*
 * The list of possible metrics collected as part of XHProf that
 * require inclusive/exclusive handling while reporting.
 *
 * @author Kannan
 */
function xhprof_get_possible_metrics() {
 static $possible_metrics =
   array("wt" => array("Wall", "microsecs", "walltime"),
         "ut" => array("User", "microsecs", "user cpu time"),
         "st" => array("Sys", "microsecs", "system cpu time"),
         "cpu" => array("Cpu", "microsecs", "cpu time"),
         "mu" => array("MUse", "bytes", "memory usage"),
         "pmu" => array("PMUse", "bytes", "peak memory usage"),
         "samples" => array("Samples", "samples", "cpu time"));
 return $possible_metrics;
}

/**
 * Initialize the metrics we'll display based on the information
 * in the raw data.
 *
 * @author Kannan
 */
function init_metrics($xhprof_data, $rep_symbol, $sort, $diff_report = false) {
  global $stats;
  global $pc_stats;
  global $metrics;
  global $diff_mode;
  global $sortable_columns;
  global $sort_col;
  global $display_calls;

  $diff_mode = $diff_report;

  if (!empty($sort)) {
    if (array_key_exists($sort, $sortable_columns)) {
      $sort_col = $sort;
    } else {
      print("Invalid Sort Key $sort specified in URL");
    }
  }

  // For C++ profiler runs, walltime attribute isn't present.
  // In that case, use "samples" as the default sort column.
  if (!isset($xhprof_data["main()"]["wt"])) {

    if ($sort_col == "wt") {
      $sort_col = "samples";
    }

    // C++ profiler data doesn't have call counts.
    // ideally we should check to see if "ct" metric
    // is present for "main()". But currently "ct"
    // metric is artificially set to 1. So, relying
    // on absence of "wt" metric instead.
    $display_calls = false;
  } else {
    $display_calls = true;
  }

  // parent/child report doesn't support exclusive times yet.
  // So, change sort hyperlinks to closest fit.
  if (!empty($rep_symbol)) {
    $sort_col = str_replace("excl_", "", $sort_col);
  }

  if ($display_calls) {
    $stats = array("fn", "ct", "Calls%");
  } else {
    $stats = array("fn");
  }

  $pc_stats = $stats;

  $possible_metrics = xhprof_get_possible_metrics($xhprof_data);
  foreach ($possible_metrics as $metric => $desc) {
    if (isset($xhprof_data["main()"][$metric])) {
      $metrics[] = $metric;
      // flat (top-level reports): we can compute
      // exclusive metrics reports as well.
      $stats[] = $metric;
      $stats[] = "I" . $desc[0] . "%";
      $stats[] = "excl_" . $metric;
      $stats[] = "E" . $desc[0] . "%";

      // parent/child report for a function: we can
      // only breakdown inclusive times correctly.
      $pc_stats[] = $metric;
      $pc_stats[] = "I" . $desc[0] . "%";
    }
  }
}

/*
 * Get the list of metrics present in $xhprof_data as an array.
 *
 * @author Kannan
 */
function xhprof_get_metrics($xhprof_data) {

  // get list of valid metrics
  $possible_metrics = xhprof_get_possible_metrics();

  // return those that are present in the raw data.
  // We'll just look at the root of the subtree for this.
  $metrics = array();
  foreach ($possible_metrics as $metric => $desc) {
    if (isset($xhprof_data["main()"][$metric])) {
      $metrics[] = $metric;
    }
  }

  return $metrics;
}

/**
 * Takes a parent/child function name encoded as
 * "a==>b" and returns array("a", "b").
 *
 * @author Kannan
 */
function xhprof_parse_parent_child($parent_child) {
  $ret = explode("==>", $parent_child);

  // Return if both parent and child are set
  if (isset($ret[1])) {
    return $ret;
  }

  return array(null, $ret[0]);
}

/**
 * Given parent & child function name, composes the key
 * in the format present in the raw data.
 *
 * @author Kannan
 */
function xhprof_build_parent_child_key($parent, $child) {
  if ($parent) {
    return $parent . "==>" . $child;
  } else {
    return $child;
  }
}


/**
 * Checks if XHProf raw data appears to be valid and not corrupted.
 *
 *  @param   int    $run_id        Run id of run to be pruned.
 *                                 [Used only for reporting errors.]
 *  @param   array  $raw_data      XHProf raw data to be pruned
 *                                 & validated.
 *
 *  @return  bool   true on success, false on failure
 *
 *  @author Kannan
 */
function xhprof_valid_run($run_id, $raw_data) {

  $main_info = $raw_data["main()"];
  if (empty($main_info)) {
    xhprof_error("XHProf: main() missing in raw data for Run ID: $run_id");
    return false;
  }

  // raw data should contain either wall time or samples information...
  if (isset($main_info["wt"])) {
    $metric = "wt";
  } else if (isset($main_info["samples"])) {
    $metric = "samples";
  } else {
    xhprof_error("XHProf: Wall Time information missing from Run ID: $run_id");
    return false;
  }

  foreach ($raw_data as $info) {
    $val = $info[$metric];

    // basic sanity checks...
    if ($val < 0) {
      xhprof_error("XHProf: $metric should not be negative: Run ID $run_id"
                   . serialize($info));
      return false;
    }
    if ($val > (86400000000)) {
      xhprof_error("XHProf: $metric > 1 day found in Run ID: $run_id "
                   . serialize($info));
      return false;
    }
  }
  return true;
}


/**
 * Return a trimmed version of the XHProf raw data. Note that the raw
 * data contains one entry for each unique parent/child function
 * combination.The trimmed version of raw data will only contain
 * entries where either the parent or child function is in the list
 * of $functions_to_keep.
 *
 * Note: Function main() is also always kept so that overall totals
 * can still be obtained from the trimmed version.
 *
 * @param  array  XHProf raw data
 * @param  array  array of function names
 *
 * @return array  Trimmed XHProf Report
 *
 * @author Kannan
 */
function xhprof_trim_run($raw_data, $functions_to_keep) {

  // convert list of functions to a hash with function as the key
  $function_map = array_fill_keys($functions_to_keep, 1);

  // always keep main() as well so that overall totals can still
  // be computed if need be.
  $function_map['main()'] = 1;

  $new_raw_data = array();
  foreach ($raw_data as $parent_child => $info) {
    list($parent, $child) = xhprof_parse_parent_child($parent_child);

    if (isset($function_map[$parent]) || isset($function_map[$child])) {
      $new_raw_data[$parent_child] = $info;
    }
  }

  return $new_raw_data;
}

/**
 * Takes raw XHProf data that was aggregated over "$num_runs" number
 * of runs averages/nomalizes the data. Essentially the various metrics
 * collected are divided by $num_runs.
 *
 * @author Kannan
 */
function xhprof_normalize_metrics($raw_data, $num_runs) {

  if (empty($raw_data) || ($num_runs == 0)) {
    return $raw_data;
  }

  $raw_data_total = array();

  if (isset($raw_data["==>main()"]) && isset($raw_data["main()"])) {
    xhprof_error("XHProf Error: both ==>main() and main() set in raw data...");
  }

  foreach ($raw_data as $parent_child => $info) {
    foreach ($info as $metric => $value) {
      $raw_data_total[$parent_child][$metric] = ($value / $num_runs);
    }
  }

  return $raw_data_total;
}


/**
 * Get raw data corresponding to specified array of runs
 * aggregated by certain weightage.
 *
 * Suppose you have run:5 corresponding to page1.php,
 *                  run:6 corresponding to page2.php,
 *             and  run:7 corresponding to page3.php
 *
 * and you want to accumulate these runs in a 2:4:1 ratio. You
 * can do so by calling:
 *
 *     xhprof_aggregate_runs(array(5, 6, 7), array(2, 4, 1));
 *
 * The above will return raw data for the runs aggregated
 * in 2:4:1 ratio.
 *
 *  @param object  $xhprof_runs_impl  An object that implements
 *                                    the iXHProfRuns interface
 *  @param  array  $runs            run ids of the XHProf runs..
 *  @param  array  $wts             integral (ideally) weights for $runs
 *  @param  string $source          source to fetch raw data for run from
 *  @param  bool   $use_script_name If true, a fake edge from main() to
 *                                  to __script::<scriptname> is introduced
 *                                  in the raw data so that after aggregations
 *                                  the script name is still preserved.
 *
 *  @return array  Return aggregated raw data
 *
 *  @author Kannan
 */
function xhprof_aggregate_runs($xhprof_runs_impl, $runs,
                               $wts, $source="phprof",
                               $use_script_name=false) {

  $raw_data_total = null;
  $raw_data       = null;
  $metrics        = array();

  $run_count = count($runs);
  $wts_count = count($wts);

  if (($run_count == 0) ||
      (($wts_count > 0) && ($run_count != $wts_count))) {
    return array('description' => 'Invalid input..',
                 'raw'  => null);
  }

  $bad_runs = array();
  foreach ($runs as $idx => $run_id) {

    $raw_data = $xhprof_runs_impl->get_run($run_id, $source, $description);

    // use the first run to derive what metrics to aggregate on.
    if ($idx == 0) {
      foreach ($raw_data["main()"] as $metric => $val) {
        if ($metric != "pmu") {
          // for now, just to keep data size small, skip "peak" memory usage
          // data while aggregating.
          // The "regular" memory usage data will still be tracked.
          if (isset($val)) {
            $metrics[] = $metric;
          }
        }
      }
    }

    if (!xhprof_valid_run($run_id, $raw_data)) {
      $bad_runs[] = $run_id;
      continue;
    }

    if ($use_script_name) {
      $page = $description;

      // create a fake function '__script::$page', and have and edge from
      // main() to '__script::$page'. We will also need edges to transfer
      // all edges originating from main() to now originate from
      // '__script::$page' to all function called from main().
      //
      // We also weight main() ever so slightly higher so that
      // it shows up above the new entry in reports sorted by
      // inclusive metrics or call counts.
      if ($page) {
        foreach ($raw_data["main()"] as $metric => $val) {
          $fake_edge[$metric] = $val;
          $new_main[$metric]  = $val + 0.00001;
        }
        $raw_data["main()"] = $new_main;
        $raw_data[xhprof_build_parent_child_key("main()",
                                                "__script::$page")]
          = $fake_edge;
      } else {
        $use_script_name = false;
      }
    }

    // if no weights specified, use 1 as the default weightage..
    $wt = ($wts_count == 0) ? 1 : $wts[$idx];

    // aggregate $raw_data into $raw_data_total with appropriate weight ($wt)
    foreach ($raw_data as $parent_child => $info) {
      if ($use_script_name) {
        // if this is an old edge originating from main(), it now
        // needs to be from '__script::$page'
        if (substr($parent_child, 0, 9) == "main()==>") {
          $child = substr($parent_child, 9);
          // ignore the newly added edge from main()
          if (substr($child, 0, 10) != "__script::") {
            $parent_child = xhprof_build_parent_child_key("__script::$page",
                                                          $child);
          }
        }
      }

      if (!isset($raw_data_total[$parent_child])) {
        foreach ($metrics as $metric) {
          $raw_data_total[$parent_child][$metric] = ($wt * $info[$metric]);
        }
      } else {
        foreach ($metrics as $metric) {
          $raw_data_total[$parent_child][$metric] += ($wt * $info[$metric]);
        }
      }
    }
  }

  $runs_string = implode(",", $runs);

  if (isset($wts)) {
    $wts_string  = "in the ratio (" . implode(":", $wts) . ")";
    $normalization_count = array_sum($wts);
  } else {
    $wts_string = "";
    $normalization_count = $run_count;
  }

  $run_count = $run_count - count($bad_runs);

  $data['description'] = "Aggregated Report for $run_count runs: ".
                         "$runs_string $wts_string\n";
  $data['raw'] = xhprof_normalize_metrics($raw_data_total,
                                          $normalization_count);
  $data['bad_runs'] = $bad_runs;

  return $data;
}


/**
 * Analyze hierarchical raw data, and compute per-function (flat)
 * inclusive and exclusive metrics.
 *
 * Also, store overall totals in the 2nd argument.
 *
 * @param  array $raw_data          XHProf format raw profiler data.
 * @param  array &$overall_totals   OUT argument for returning
 *                                  overall totals for various
 *                                  metrics.
 * @return array Returns a map from function name to its
 *               call count and inclusive & exclusive metrics
 *               (such as wall time, etc.).
 *
 * @author Kannan Muthukkaruppan
 */
function xhprof_compute_flat_info($raw_data, &$overall_totals) {

  global $display_calls;

  $metrics = xhprof_get_metrics($raw_data);

  $overall_totals = array("ct" => 0,
                           "wt" => 0,
                           "ut" => 0,
                           "st" => 0,
                           "cpu" => 0,
                           "mu" => 0,
                           "pmu" => 0,
                           "samples" => 0
                           );

  // compute inclusive times for each function
  $symbol_tab = xhprof_compute_inclusive_times($raw_data);

  /* total metric value is the metric value for "main()" */
  foreach ($metrics as $metric) {
    $overall_totals[$metric] = $symbol_tab["main()"][$metric];
  }

  /*
   * initialize exclusive (self) metric value to inclusive metric value
   * to start with.
   * In the same pass, also add up the total number of function calls.
   */
  foreach ($symbol_tab as $symbol => $info) {
    foreach ($metrics as $metric) {
      $symbol_tab[$symbol]["excl_" . $metric] = $symbol_tab[$symbol][$metric];
    }
    if ($display_calls) {
      /* keep track of total number of calls */
      $overall_totals["ct"] += $info["ct"];
    }
  }

  /* adjust exclusive times by deducting inclusive time of children */
  foreach ($raw_data as $parent_child => $info) {
    list($parent, $child) = xhprof_parse_parent_child($parent_child);

    if ($parent) {
      foreach ($metrics as $metric) {
        // make sure the parent exists hasn't been pruned.
        if (isset($symbol_tab[$parent])) {
          $symbol_tab[$parent]["excl_" . $metric] -= $info[$metric];
        }
      }
    }
  }

  return $symbol_tab;
}

/**
 * Hierarchical diff:
 * Compute and return difference of two call graphs: Run2 - Run1.
 *
 * @author Kannan
 */
function xhprof_compute_diff($xhprof_data1, $xhprof_data2) {
  global $display_calls;

  // use the second run to decide what metrics we will do the diff on
  $metrics = xhprof_get_metrics($xhprof_data2);

  $xhprof_delta = $xhprof_data2;

  foreach ($xhprof_data1 as $parent_child => $info) {

    if (!isset($xhprof_delta[$parent_child])) {

      // this pc combination was not present in run1;
      // initialize all values to zero.
      if ($display_calls) {
        $xhprof_delta[$parent_child] = array("ct" => 0);
      } else {
        $xhprof_delta[$parent_child] = array();
      }
      foreach ($metrics as $metric) {
        $xhprof_delta[$parent_child][$metric] = 0;
      }
    }

    if ($display_calls) {
      $xhprof_delta[$parent_child]["ct"] -= $info["ct"];
    }

    foreach ($metrics as $metric) {
      $xhprof_delta[$parent_child][$metric] -= $info[$metric];
    }
  }

  return $xhprof_delta;
}


/**
 * Compute inclusive metrics for function. This code was factored out
 * of xhprof_compute_flat_info().
 *
 * The raw data contains inclusive metrics of a function for each
 * unique parent function it is called from. The total inclusive metrics
 * for a function is therefore the sum of inclusive metrics for the
 * function across all parents.
 *
 * @return array  Returns a map of function name to total (across all parents)
 *                inclusive metrics for the function.
 *
 * @author Kannan
 */
function xhprof_compute_inclusive_times($raw_data) {
  global $display_calls;

  $metrics = xhprof_get_metrics($raw_data);

  $symbol_tab = array();

  /*
   * First compute inclusive time for each function and total
   * call count for each function across all parents the
   * function is called from.
   */
  foreach ($raw_data as $parent_child => $info) {

    list($parent, $child) = xhprof_parse_parent_child($parent_child);

    if ($parent == $child) {
      /*
       * XHProf PHP extension should never trigger this situation any more.
       * Recursion is handled in the XHProf PHP extension by giving nested
       * calls a unique recursion-depth appended name (for example, foo@1).
       */
      xhprof_error("Error in Raw Data: parent & child are both: $parent");
      return;
    }

    if (!isset($symbol_tab[$child])) {

      if ($display_calls) {
        $symbol_tab[$child] = array("ct" => $info["ct"]);
      } else {
        $symbol_tab[$child] = array();
      }
      foreach ($metrics as $metric) {
        $symbol_tab[$child][$metric] = $info[$metric];
      }
    } else {
      if ($display_calls) {
        /* increment call count for this child */
        $symbol_tab[$child]["ct"] += $info["ct"];
      }

      /* update inclusive times/metric for this child  */
      foreach ($metrics as $metric) {
        $symbol_tab[$child][$metric] += $info[$metric];
      }
    }
  }

  return $symbol_tab;
}


/*
 * Prunes XHProf raw data:
 *
 * Any node whose inclusive walltime accounts for less than $prune_percent
 * of total walltime is pruned. [It is possible that a child function isn't
 * pruned, but one or more of its parents get pruned. In such cases, when
 * viewing the child function's hierarchical information, the cost due to
 * the pruned parent(s) will be attributed to a special function/symbol
 * "__pruned__()".]
 *
 *  @param   array  $raw_data      XHProf raw data to be pruned & validated.
 *  @param   double $prune_percent Any edges that account for less than
 *                                 $prune_percent of time will be pruned
 *                                 from the raw data.
 *
 *  @return  array  Returns the pruned raw data.
 *
 *  @author Kannan
 */
function xhprof_prune_run($raw_data, $prune_percent) {

  $main_info = $raw_data["main()"];
  if (empty($main_info)) {
    xhprof_error("XHProf: main() missing in raw data");
    return false;
  }

  // raw data should contain either wall time or samples information...
  if (isset($main_info["wt"])) {
    $prune_metric = "wt";
  } else if (isset($main_info["samples"])) {
    $prune_metric = "samples";
  } else {
    xhprof_error("XHProf: for main() we must have either wt "
                 ."or samples attribute set");
    return false;
  }

  // determine the metrics present in the raw data..
  $metrics = array();
  foreach ($main_info as $metric => $val) {
    if (isset($val)) {
      $metrics[] = $metric;
    }
  }

  $prune_threshold = (($main_info[$prune_metric] * $prune_percent) / 100.0);

  init_metrics($raw_data, null, null, false);
  $flat_info = xhprof_compute_inclusive_times($raw_data);

  foreach ($raw_data as $parent_child => $info) {

    list($parent, $child) = xhprof_parse_parent_child($parent_child);

    // is this child's overall total from all parents less than threshold?
    if ($flat_info[$child][$prune_metric] < $prune_threshold) {
      unset($raw_data[$parent_child]); // prune the edge
    } else if ($parent &&
               ($parent != "__pruned__()") &&
               ($flat_info[$parent][$prune_metric] < $prune_threshold)) {

      // Parent's overall inclusive metric is less than a threshold.
      // All edges to the parent node will get nuked, and this child will
      // be a dangling child.
      // So instead change its parent to be a special function __pruned__().
      $pruned_edge = xhprof_build_parent_child_key("__pruned__()", $child);

      if (isset($raw_data[$pruned_edge])) {
        foreach ($metrics as $metric) {
          $raw_data[$pruned_edge][$metric]+=$raw_data[$parent_child][$metric];
        }
      } else {
        $raw_data[$pruned_edge] = $raw_data[$parent_child];
      }

      unset($raw_data[$parent_child]); // prune the edge
    }
  }

  return $raw_data;
}


/**
 * Set one key in an array and return the array
 *
 * @author Kannan
 */
function xhprof_array_set($arr, $k, $v) {
  $arr[$k] = $v;
  return $arr;
}

/**
 * Removes/unsets one key in an array and return the array
 *
 * @author Kannan
 */
function xhprof_array_unset($arr, $k) {
  unset($arr[$k]);
  return $arr;
}

/**
 * Type definitions for URL params
 */
define('XHPROF_STRING_PARAM', 1);
define('XHPROF_UINT_PARAM',   2);
define('XHPROF_FLOAT_PARAM',  3);
define('XHPROF_BOOL_PARAM',   4);


/**
 * Internal helper function used by various
 * xhprof_get_param* flavors for various
 * types of parameters.
 *
 * @param string   name of the URL query string param
 *
 * @author Kannan
 */
function xhprof_get_param_helper($param) {
  $val = null;
  if (isset($_GET[$param]))
    $val = $_GET[$param];
  else if (isset($_POST[$param])) {
    $val = $_POST[$param];
  }
  return $val;
}

/**
 * Extracts value for string param $param from query
 * string. If param is not specified, return the
 * $default value.
 *
 * @author Kannan
 */
function xhprof_get_string_param($param, $default = '') {
  $val = xhprof_get_param_helper($param);

  if ($val === null)
    return $default;

  return $val;
}

/**
 * Extracts value for unsigned integer param $param from
 * query string. If param is not specified, return the
 * $default value.
 *
 * If value is not a valid unsigned integer, logs error
 * and returns null.
 *
 * @author Kannan
 */
function xhprof_get_uint_param($param, $default = 0) {
  $val = xhprof_get_param_helper($param);

  if ($val === null)
    $val = $default;

  // trim leading/trailing whitespace
  $val = trim($val);

  // if it only contains digits, then ok..
  if (ctype_digit($val)) {
    return $val;
  }

  xhprof_error("$param is $val. It must be an unsigned integer.");
  return null;
}


/**
 * Extracts value for a float param $param from
 * query string. If param is not specified, return
 * the $default value.
 *
 * If value is not a valid unsigned integer, logs error
 * and returns null.
 *
 * @author Kannan
 */
function xhprof_get_float_param($param, $default = 0) {
  $val = xhprof_get_param_helper($param);

  if ($val === null)
    $val = $default;

  // trim leading/trailing whitespace
  $val = trim($val);

  // TBD: confirm the value is indeed a float.
  if (true) // for now..
    return (float)$val;

  xhprof_error("$param is $val. It must be a float.");
  return null;
}

/**
 * Extracts value for a boolean param $param from
 * query string. If param is not specified, return
 * the $default value.
 *
 * If value is not a valid unsigned integer, logs error
 * and returns null.
 *
 * @author Kannan
 */
function xhprof_get_bool_param($param, $default = false) {
  $val = xhprof_get_param_helper($param);

  if ($val === null)
    $val = $default;

  // trim leading/trailing whitespace
  $val = trim($val);

  switch (strtolower($val)) {
  case '0':
  case '1':
    $val = (bool)$val;
    break;
  case 'true':
  case 'on':
  case 'yes':
    $val = true;
    break;
  case 'false':
  case 'off':
  case 'no':
    $val = false;
    break;
  default:
    xhprof_error("$param is $val. It must be a valid boolean string.");
    return null;
  }

  return $val;

}

/**
 * Initialize params from URL query string. The function
 * creates globals variables for each of the params
 * and if the URL query string doesn't specify a particular
 * param initializes them with the corresponding default
 * value specified in the input.
 *
 * @params array $params An array whose keys are the names
 *                       of URL params who value needs to
 *                       be retrieved from the URL query
 *                       string. PHP globals are created
 *                       with these names. The value is
 *                       itself an array with 2-elems (the
 *                       param type, and its default value).
 *                       If a param is not specified in the
 *                       query string the default value is
 *                       used.
 * @author Kannan
 */
function xhprof_param_init($params) {
  /* Create variables specified in $params keys, init defaults */
  foreach ($params as $k => $v) {
    switch ($v[0]) {
    case XHPROF_STRING_PARAM:
      $p = xhprof_get_string_param($k, $v[1]);
      break;
    case XHPROF_UINT_PARAM:
      $p = xhprof_get_uint_param($k, $v[1]);
      break;
    case XHPROF_FLOAT_PARAM:
      $p = xhprof_get_float_param($k, $v[1]);
      break;
    case XHPROF_BOOL_PARAM:
      $p = xhprof_get_bool_param($k, $v[1]);
      break;
    default:
      xhprof_error("Invalid param type passed to xhprof_param_init: "
                   . $v[0]);
      exit();
    }

    // create a global variable using the parameter name.
    $GLOBALS[$k] = $p;
  }
}


/**
 * Given a partial query string $q return matching function names in
 * specified XHProf run. This is used for the type ahead function
 * selector.
 *
 * @author Kannan
 */
function xhprof_get_matching_functions($q, $xhprof_data) {

  $matches = array();

  foreach ($xhprof_data as $parent_child => $info) {
    list($parent, $child) = xhprof_parse_parent_child($parent_child);
    if (stripos($parent, $q) !== false) {
      $matches[$parent] = 1;
    }
    if (stripos($child, $q) !== false) {
      $matches[$child] = 1;
    }
  }

  $res = array_keys($matches);

  // sort it so the answers are in some reliable order...
  asort($res);

  return ($res);
}

// require_once $GLOBALS['XHPROF_LIB_ROOT'].'/utils/callgraph_utils.php';
//  Copyright (c) 2009 Facebook
//
//  Licensed under the Apache License, Version 2.0 (the "License");
//  you may not use this file except in compliance with the License.
//  You may obtain a copy of the License at
//
//      http://www.apache.org/licenses/LICENSE-2.0
//
//  Unless required by applicable law or agreed to in writing, software
//  distributed under the License is distributed on an "AS IS" BASIS,
//  WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
//  See the License for the specific language governing permissions and
//  limitations under the License.
//

/*
 * This file contains callgraph image generation related XHProf utility
 * functions
 *
 */

// Supported ouput format
$xhprof_legal_image_types = array(
    "jpg" => 1,
    "gif" => 1,
    "png" => 1,
    "ps"  => 1,
    );

/**
 * Send an HTTP header with the response. You MUST use this function instead
 * of header() so that we can debug header issues because they're virtually
 * impossible to debug otherwise. If you try to commit header(), SVN will
 * reject your commit.
 *
 * @param string  HTTP header name, like 'Location'
 * @param string  HTTP header value, like 'http://www.example.com/'
 *
 */
function xhprof_http_header($name, $value) {

  if (!$name) {
    xhprof_error('http_header usage');
    return null;
  }

  if (!is_string($value)) {
    xhprof_error('http_header value not a string');
  }

  header($name.': '.$value, true);
}

/**
 * Genearte and send MIME header for the output image to client browser.
 *
 * @author cjiang
 */
function xhprof_generate_mime_header($type, $length) {
  switch ($type) {
    case 'jpg':
      $mime = 'image/jpeg';
      break;
    case 'gif':
      $mime = 'image/gif';
      break;
    case 'png':
      $mime = 'image/png';
      break;
    case 'ps':
      $mime = 'application/postscript';
    default:
      $mime = false;
  }

  if ($mime) {
    xhprof_http_header('Content-type', $mime);
    xhprof_http_header('Content-length', (string)$length);
  }
}

/**
 * Generate image according to DOT script. This function will spawn a process
 * with "dot" command and pipe the "dot_script" to it and pipe out the
 * generated image content.
 *
 * @param dot_script, string, the script for DOT to generate the image.
 * @param type, one of the supported image types, see
 * $xhprof_legal_image_types.
 * @returns, binary content of the generated image on success. empty string on
 *           failure.
 *
 * @author cjiang
 */
function xhprof_generate_image_by_dot($dot_script, $type) {
  $descriptorspec = array(
       // stdin is a pipe that the child will read from
       0 => array("pipe", "r"),
       // stdout is a pipe that the child will write to
       1 => array("pipe", "w"),
       // stderr is a pipe that the child will write to
       2 => array("pipe", "w")
       );

  $cmd = " dot -T".$type;

  $process = proc_open($cmd, $descriptorspec, $pipes, "/tmp", array());
  if (is_resource($process)) {
    fwrite($pipes[0], $dot_script);
    fclose($pipes[0]);

    $output = stream_get_contents($pipes[1]);

    $err = stream_get_contents($pipes[2]);
    if (!empty($err)) {
      print "failed to execute cmd: \"$cmd\". stderr: `$err'\n";
      exit;
    }

    fclose($pipes[2]);
    fclose($pipes[1]);
    proc_close($process);
    return $output;
  }
  print "failed to execute cmd \"$cmd\"";
  exit();
}

/*
 * Get the children list of all nodes.
 */
function xhprof_get_children_table($raw_data) {
  $children_table = array();
  foreach ($raw_data as $parent_child => $info) {
    list($parent, $child) = xhprof_parse_parent_child($parent_child);
    if (!isset($children_table[$parent])) {
      $children_table[$parent] = array($child);
    } else {
      $children_table[$parent][] = $child;
    }
  }
  return $children_table;
}

/**
 * Generate DOT script from the given raw phprof data.
 *
 * @param raw_data, phprof profile data.
 * @param threshold, float, the threshold value [0,1). The functions in the
 *                   raw_data whose exclusive wall times ratio are below the
 *                   threshold will be filtered out and won't apprear in the
 *                   generated image.
 * @param page, string(optional), the root node name. This can be used to
 *              replace the 'main()' as the root node.
 * @param func, string, the focus function.
 * @param critical_path, bool, whether or not to display critical path with
 *                             bold lines.
 * @returns, string, the DOT script to generate image.
 *
 * @author cjiang
 */
function xhprof_generate_dot_script($raw_data, $threshold, $source, $page,
                                    $func, $critical_path, $right=null,
                                    $left=null) {

  $max_width = 5;
  $max_height = 3.5;
  $max_fontsize = 35;
  $max_sizing_ratio = 20;

  $totals;

  if ($left === null) {
    // init_metrics($raw_data, null, null);
  }
  $sym_table = xhprof_compute_flat_info($raw_data, $totals);

  if ($critical_path) {
    $children_table = xhprof_get_children_table($raw_data);
    $node = "main()";
    $path = array();
    $path_edges = array();
    $visited = array();
    while ($node) {
      $visited[$node] = true;
      if (isset($children_table[$node])) {
        $max_child = null;
        foreach ($children_table[$node] as $child) {

          if (isset($visited[$child])) {
            continue;
          }
          if ($max_child === null ||
            abs($raw_data[xhprof_build_parent_child_key($node,
                                                        $child)]["wt"]) >
            abs($raw_data[xhprof_build_parent_child_key($node,
                                                        $max_child)]["wt"])) {
            $max_child = $child;
          }
        }
        if ($max_child !== null) {
          $path[$max_child] = true;
          $path_edges[xhprof_build_parent_child_key($node, $max_child)] = true;
        }
        $node = $max_child;
      } else {
        $node = null;
      }
    }
  }

  // if it is a benchmark callgraph, we make the benchmarked function the root.
 if ($source == "bm" && array_key_exists("main()", $sym_table)) {
    $total_times = $sym_table["main()"]["ct"];
    $remove_funcs = array("main()",
                          "hotprofiler_disable",
                          "call_user_func_array",
                          "xhprof_disable");

    foreach ($remove_funcs as $cur_del_func) {
      if (array_key_exists($cur_del_func, $sym_table) &&
          $sym_table[$cur_del_func]["ct"] == $total_times) {
        unset($sym_table[$cur_del_func]);
      }
    }
  }

  // use the function to filter out irrelevant functions.
  if (!empty($func)) {
    $interested_funcs = array();
    foreach ($raw_data as $parent_child => $info) {
      list($parent, $child) = xhprof_parse_parent_child($parent_child);
      if ($parent == $func || $child == $func) {
        $interested_funcs[$parent] = 1;
        $interested_funcs[$child] = 1;
      }
    }
    foreach ($sym_table as $symbol => $info) {
      if (!array_key_exists($symbol, $interested_funcs)) {
        unset($sym_table[$symbol]);
      }
    }
  }

  $result = "digraph call_graph {\n";

  // Filter out functions whose exclusive time ratio is below threshold, and
  // also assign a unique integer id for each function to be generated. In the
  // meantime, find the function with the most exclusive time (potentially the
  // performance bottleneck).
  $cur_id = 0; $max_wt = 0;
  foreach ($sym_table as $symbol => $info) {
    if (empty($func) && abs($info["wt"] / $totals["wt"]) < $threshold) {
      unset($sym_table[$symbol]);
      continue;
    }
    if ($max_wt == 0 || $max_wt < abs($info["excl_wt"])) {
      $max_wt = abs($info["excl_wt"]);
    }
    $sym_table[$symbol]["id"] = $cur_id;
    $cur_id ++;
  }

  // Generate all nodes' information.
  foreach ($sym_table as $symbol => $info) {
    if ($info["excl_wt"] == 0) {
      $sizing_factor = $max_sizing_ratio;
    } else {
      $sizing_factor = $max_wt / abs($info["excl_wt"]) ;
      if ($sizing_factor > $max_sizing_ratio) {
        $sizing_factor = $max_sizing_ratio;
      }
    }
    $fillcolor = (($sizing_factor < 1.5) ?
                  ", style=filled, fillcolor=red" : "");

    if ($critical_path) {
      // highlight nodes along critical path.
      if (!$fillcolor && array_key_exists($symbol, $path)) {
        $fillcolor = ", style=filled, fillcolor=yellow";
      }
    }

    $fontsize = ", fontsize="
               .(int)($max_fontsize / (($sizing_factor - 1) / 10 + 1));

    $width = ", width=".sprintf("%.1f", $max_width / $sizing_factor);
    $height = ", height=".sprintf("%.1f", $max_height / $sizing_factor);

    if ($symbol == "main()") {
      $shape = "octagon";
      $name = "Total: ".($totals["wt"] / 1000.0)." ms\\n";
      $name .= addslashes(isset($page) ? $page : $symbol);
    } else {
      $shape = "box";
      $name = addslashes($symbol)."\\nInc: ". sprintf("%.3f",$info["wt"] / 1000) .
              " ms (" . sprintf("%.1f%%", 100 * $info["wt"] / $totals["wt"]).")";
    }
    if ($left === null) {
      $label = ", label=\"".$name."\\nExcl: "
               .(sprintf("%.3f",$info["excl_wt"] / 1000.0))." ms ("
               .sprintf("%.1f%%", 100 * $info["excl_wt"] / $totals["wt"])
               . ")\\n".$info["ct"]." total calls\"";
    } else {
      if (isset($left[$symbol]) && isset($right[$symbol])) {
         $label = ", label=\"".addslashes($symbol).
                  "\\nInc: ".(sprintf("%.3f",$left[$symbol]["wt"] / 1000.0))
                  ." ms - "
                  .(sprintf("%.3f",$right[$symbol]["wt"] / 1000.0))." ms = "
                  .(sprintf("%.3f",$info["wt"] / 1000.0))." ms".
                  "\\nExcl: "
                  .(sprintf("%.3f",$left[$symbol]["excl_wt"] / 1000.0))
                  ." ms - ".(sprintf("%.3f",$right[$symbol]["excl_wt"] / 1000.0))
                   ." ms = ".(sprintf("%.3f",$info["excl_wt"] / 1000.0))." ms".
                  "\\nCalls: ".(sprintf("%.3f",$left[$symbol]["ct"]))." - "
                   .(sprintf("%.3f",$right[$symbol]["ct"]))." = "
                   .(sprintf("%.3f",$info["ct"]))."\"";
      } else if (isset($left[$symbol])) {
        $label = ", label=\"".addslashes($symbol).
                  "\\nInc: ".(sprintf("%.3f",$left[$symbol]["wt"] / 1000.0))
                   ." ms - 0 ms = ".(sprintf("%.3f",$info["wt"] / 1000.0))
                   ." ms"."\\nExcl: "
                   .(sprintf("%.3f",$left[$symbol]["excl_wt"] / 1000.0))
                   ." ms - 0 ms = "
                   .(sprintf("%.3f",$info["excl_wt"] / 1000.0))." ms".
                  "\\nCalls: ".(sprintf("%.3f",$left[$symbol]["ct"]))." - 0 = "
                  .(sprintf("%.3f",$info["ct"]))."\"";
      } else {
        $label = ", label=\"".addslashes($symbol).
                  "\\nInc: 0 ms - "
                  .(sprintf("%.3f",$right[$symbol]["wt"] / 1000.0))
                  ." ms = ".(sprintf("%.3f",$info["wt"] / 1000.0))." ms".
                  "\\nExcl: 0 ms - "
                  .(sprintf("%.3f",$right[$symbol]["excl_wt"] / 1000.0))
                  ." ms = ".(sprintf("%.3f",$info["excl_wt"] / 1000.0))." ms".
                  "\\nCalls: 0 - ".(sprintf("%.3f",$right[$symbol]["ct"]))
                  ." = ".(sprintf("%.3f",$info["ct"]))."\"";
      }
    }
    $result .= "N" . $sym_table[$symbol]["id"];
    $result .= "[shape=$shape ".$label.$width
               .$height.$fontsize.$fillcolor."];\n";
  }

  // Generate all the edges' information.
  foreach ($raw_data as $parent_child => $info) {
    list($parent, $child) = xhprof_parse_parent_child($parent_child);

    if (isset($sym_table[$parent]) && isset($sym_table[$child]) &&
        (empty($func) ||
         (!empty($func) && ($parent == $func || $child == $func)))) {

      $label = $info["ct"] == 1 ? $info["ct"]." call" : $info["ct"]." calls";

      $headlabel = $sym_table[$child]["wt"] > 0 ?
                  sprintf("%.1f%%", 100 * $info["wt"]
                                    / $sym_table[$child]["wt"])
                  : "0.0%";

      $taillabel = ($sym_table[$parent]["wt"] > 0) ?
        sprintf("%.1f%%",
                100 * $info["wt"] /
                ($sym_table[$parent]["wt"] - $sym_table["$parent"]["excl_wt"]))
        : "0.0%";

      $linewidth = 1;
      $arrow_size = 1;

      if ($critical_path &&
          isset($path_edges[xhprof_build_parent_child_key($parent, $child)])) {
        $linewidth = 10; $arrow_size = 2;
      }

      $result .= "N" . $sym_table[$parent]["id"] . " -> N"
                 . $sym_table[$child]["id"];
      $result .= "[arrowsize=$arrow_size, style=\"setlinewidth($linewidth)\","
                 ." label=\""
                 .$label."\", headlabel=\"".$headlabel
                 ."\", taillabel=\"".$taillabel."\" ]";
      $result .= ";\n";

    }
  }
  $result = $result . "\n}";

  return $result;
}

function  xhprof_render_diff_image($xhprof_runs_impl, $run1, $run2,
                                   $type, $threshold, $source) {
  $total1;
  $total2;

  $raw_data1 = $xhprof_runs_impl->get_run($run1, $source, $desc_unused);
  $raw_data2 = $xhprof_runs_impl->get_run($run2, $source, $desc_unused);

  // init_metrics($raw_data1, null, null);
  $children_table1 = xhprof_get_children_table($raw_data1);
  $children_table2 = xhprof_get_children_table($raw_data2);
  $symbol_tab1 = xhprof_compute_flat_info($raw_data1, $total1);
  $symbol_tab2 = xhprof_compute_flat_info($raw_data2, $total2);
  $run_delta = xhprof_compute_diff($raw_data1, $raw_data2);
  $script = xhprof_generate_dot_script($run_delta, $threshold, $source,
                                       null, null, true,
                                       $symbol_tab1, $symbol_tab2);
  $content = xhprof_generate_image_by_dot($script, $type);

  xhprof_generate_mime_header($type, strlen($content));
  echo $content;
}

/**
 * Generate image content from phprof run id.
 *
 * @param object  $xhprof_runs_impl  An object that implements
 *                                   the iXHProfRuns interface
 * @param run_id, integer, the unique id for the phprof run, this is the
 *                primary key for phprof database table.
 * @param type, string, one of the supported image types. See also
 *              $xhprof_legal_image_types.
 * @param threshold, float, the threshold value [0,1). The functions in the
 *                   raw_data whose exclusive wall times ratio are below the
 *                   threshold will be filtered out and won't apprear in the
 *                   generated image.
 * @param func, string, the focus function.
 * @returns, string, the DOT script to generate image.
 *
 * @author cjiang
 */
function xhprof_get_content_by_run($xhprof_runs_impl, $run_id, $type,
                                   $threshold, $func, $source,
                                   $critical_path) {
  if (!$run_id)
    return "";

  $raw_data = $xhprof_runs_impl->get_run($run_id, $source, $description);
  if (!$raw_data) {
    xhprof_error("Raw data is empty");
    return "";
  }

  $script = xhprof_generate_dot_script($raw_data, $threshold, $source,
                                       $description, $func, $critical_path);

  $content = xhprof_generate_image_by_dot($script, $type);
  return $content;
}

/**
 * Generate image from phprof run id and send it to client.
 *
 * @param object  $xhprof_runs_impl  An object that implements
 *                                   the iXHProfRuns interface
 * @param run_id, integer, the unique id for the phprof run, this is the
 *                primary key for phprof database table.
 * @param type, string, one of the supported image types. See also
 *              $xhprof_legal_image_types.
 * @param threshold, float, the threshold value [0,1). The functions in the
 *                   raw_data whose exclusive wall times ratio are below the
 *                   threshold will be filtered out and won't apprear in the
 *                   generated image.
 * @param func, string, the focus function.
 * @param bool, does this run correspond to a PHProfLive run or a dev run?
 * @author cjiang
 */
function xhprof_render_image($xhprof_runs_impl, $run_id, $type, $threshold,
                             $func, $source, $critical_path) {

  $content = xhprof_get_content_by_run($xhprof_runs_impl, $run_id, $type,
                                       $threshold,
                                       $func, $source, $critical_path);
  if (!$content) {
    print "Error: either we can not find profile data for run_id ".$run_id
          ." or the threshold ".$threshold." is too small or you do not"
          ." have 'dot' image generation utility installed.";
    exit();
  }

  xhprof_generate_mime_header($type, strlen($content));
  echo $content;
}

// require_once $GLOBALS['XHPROF_LIB_ROOT'].'/utils/xhprof_runs.php';
//
//  Copyright (c) 2009 Facebook
//
//  Licensed under the Apache License, Version 2.0 (the "License");
//  you may not use this file except in compliance with the License.
//  You may obtain a copy of the License at
//
//      http://www.apache.org/licenses/LICENSE-2.0
//
//  Unless required by applicable law or agreed to in writing, software
//  distributed under the License is distributed on an "AS IS" BASIS,
//  WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
//  See the License for the specific language governing permissions and
//  limitations under the License.
//

//
// This file defines the interface iXHProfRuns and also provides a default
// implementation of the interface (class XHProfRuns).
//

/**
 * iXHProfRuns interface for getting/saving a XHProf run.
 *
 * Clients can either use the default implementation,
 * namely XHProfRuns_Default, of this interface or define
 * their own implementation.
 *
 * @author Kannan
 */
interface iXHProfRuns {

  /**
   * Returns XHProf data given a run id ($run) of a given
   * type ($type).
   *
   * Also, a brief description of the run is returned via the
   * $run_desc out parameter.
   */
  public function get_run($run_id, $type, &$run_desc);

  /**
   * Save XHProf data for a profiler run of specified type
   * ($type).
   *
   * The caller may optionally pass in run_id (which they
   * promise to be unique). If a run_id is not passed in,
   * the implementation of this method must generated a
   * unique run id for this saved XHProf run.
   *
   * Returns the run id for the saved XHProf run.
   *
   */
  public function save_run($xhprof_data, $type, $run_id = null);
}


/**
 * XHProfRuns_Default is the default implementation of the
 * iXHProfRuns interface for saving/fetching XHProf runs.
 *
 * It stores/retrieves runs to/from a filesystem directory
 * specified by the "xhprof.output_dir" ini parameter.
 *
 * @author Kannan
 */
class XHProfRuns_Default implements iXHProfRuns {

  private $dir = '';
  private $suffix = 'xhprof';

  private function gen_run_id($type) {
    return uniqid();
  }

  private function file_name($run_id, $type) {

    $file = "$run_id.$type." . $this->suffix;

    if (!empty($this->dir)) {
      $file = $this->dir . "/" . $file;
    }
    return $file;
  }

  public function __construct($dir = null) {

    // if user hasn't passed a directory location,
    // we use the xhprof.output_dir ini setting
    // if specified, else we default to the directory
    // in which the error_log file resides.

    if (empty($dir)) {
      $dir = ini_get("xhprof.output_dir");
      if (empty($dir)) {

        // some default that at least works on unix...
        $dir = "/tmp";

        xhprof_error("Warning: Must specify directory location for XHProf runs. ".
                     "Trying {$dir} as default. You can either pass the " .
                     "directory location as an argument to the constructor ".
                     "for XHProfRuns_Default() or set xhprof.output_dir ".
                     "ini param.");
      }
    }
    $this->dir = $dir;
  }

  public function get_run($run_id, $type, &$run_desc) {
    $file_name = $this->file_name($run_id, $type);

    if (!file_exists($file_name)) {
      xhprof_error("Could not find file $file_name");
      $run_desc = "Invalid Run Id = $run_id";
      return null;
    }

    $contents = file_get_contents($file_name);
    $run_desc = "XHProf Run (Namespace=$type)";
    return unserialize($contents);
  }

  public function save_run($xhprof_data, $type, $run_id = null) {

    // Use PHP serialize function to store the XHProf's
    // raw profiler data.
    $xhprof_data = serialize($xhprof_data);

    if ($run_id === null) {
      $run_id = $this->gen_run_id($type);
    }

    $file_name = $this->file_name($run_id, $type);
    $file = fopen($file_name, 'w');

    if ($file) {
      fwrite($file, $xhprof_data);
      fclose($file);
    } else {
      xhprof_error("Could not open $file_name\n");
    }

    // echo "Saved run in {$file_name}.\nRun id = {$run_id}.\n";
    return $run_id;
  }

  function list_runs() {
    if (is_dir($this->dir)) {
        echo "<hr/>Existing runs:\n<ul>\n";
        foreach (glob("{$this->dir}/*.{$this->suffix}") as $file) {
            list($run,$source) = explode('.', basename($file));
            echo '<li><a href="' . htmlentities($_SERVER['SCRIPT_NAME'])
                . '?run=' . htmlentities($run) . '&source='
                . htmlentities($source) . '">'
                . htmlentities(basename($file)) . "</a><small> "
                . date("Y-m-d H:i:s", filemtime($file)) . "</small></li>\n";
        }
        echo "</ul>\n";
    }
  }
}


/**
 * Our coding convention disallows relative paths in hrefs.
 * Get the base URL path from the SCRIPT_NAME.
 */
$base_path = rtrim(dirname($_SERVER['SCRIPT_NAME']), "/");


/**
 * Generate references to required stylesheets & javascript.
 *
 * If the calling script (such as index.php) resides in
 * a different location that than 'xhprof_html' directory the
 * caller must provide the URL path to 'xhprof_html' directory
 * so that the correct location of the style sheets/javascript
 * can be specified in the generated HTML.
 *
 */
function xhprof_include_js_css($ui_dir_url_path = null) {

  if (empty($ui_dir_url_path)) {
    $ui_dir_url_path = rtrim(dirname($_SERVER['SCRIPT_NAME']), "/");
  }

  // style sheets
  echo "<link href='$ui_dir_url_path/css/xhprof.css' rel='stylesheet' ".
    " type='text/css' />";
  echo "<link href='$ui_dir_url_path/jquery/jquery.tooltip.css' ".
    " rel='stylesheet' type='text/css' />";
  echo "<link href='$ui_dir_url_path/jquery/jquery.autocomplete.css' ".
    " rel='stylesheet' type='text/css' />";

  // javascript
  echo "<script src='$ui_dir_url_path/jquery/jquery-1.2.6.js'>".
       "</script>";
  echo "<script src='$ui_dir_url_path/jquery/jquery.tooltip.js'>".
       "</script>";
  echo "<script src='$ui_dir_url_path/jquery/jquery.autocomplete.js'>"
       ."</script>";
  echo "<script src='$ui_dir_url_path/js/xhprof_report.js'></script>";
}


/*
 * Formats call counts for XHProf reports.
 *
 * Description:
 * Call counts in single-run reports are integer values.
 * However, call counts for aggregated reports can be
 * fractional. This function will print integer values
 * without decimal point, but with commas etc.
 *
 *   4000 ==> 4,000
 *
 * It'll round fractional values to decimal precision of 3
 *   4000.1212 ==> 4,000.121
 *   4000.0001 ==> 4,000
 *
 */
function xhprof_count_format($num) {
  $num = round($num, 3);
  if (round($num) == $num) {
    return number_format($num);
  } else {
    return number_format($num, 3);
  }
}

function xhprof_percent_format($s, $precision = 1) {
  return sprintf('%.'.$precision.'f%%', 100 * $s);
}

/**
 * Implodes the text for a bunch of actions (such as links, forms,
 * into a HTML list and returns the text.
 */
function xhprof_render_actions($actions) {
  $out = array();

  if (count($actions)) {
    $out[] = '<ul class="xhprof_actions">';
    foreach ($actions as $action) {
      $out[] = '<li>'.$action.'</li>';
    }
    $out[] = '</ul>';
  }

  return implode('', $out);
}


/**
 * @param html-str $content  the text/image/innerhtml/whatever for the link
 * @param raw-str  $href
 * @param raw-str  $class
 * @param raw-str  $id
 * @param raw-str  $title
 * @param raw-str  $target
 * @param raw-str  $onclick
 * @param raw-str  $style
 * @param raw-str  $access
 * @param raw-str  $onmouseover
 * @param raw-str  $onmouseout
 * @param raw-str  $onmousedown
 * @param raw-str  $dir
 * @param raw-str  $rel
 */
function xhprof_render_link($content, $href, $class='', $id='', $title='',
                            $target='',
                            $onclick='', $style='', $access='', $onmouseover='',
                            $onmouseout='', $onmousedown='') {

  if (!$content) {
    return '';
  }

  if ($href) {
    $link = '<a href="' . ($href) . '"';
  } else {
    $link = '<span';
  }

  if ($class) {
    $link .= ' class="' . ($class) . '"';
  }
  if ($id) {
    $link .= ' id="' . ($id) . '"';
  }
  if ($title) {
    $link .= ' title="' . ($title) . '"';
  }
  if ($target) {
    $link .= ' target="' . ($target) . '"';
  }
  if ($onclick && $href) {
    $link .= ' onclick="' . ($onclick) . '"';
  }
  if ($style && $href) {
    $link .= ' style="' . ($style) . '"';
  }
  if ($access && $href) {
    $link .= ' accesskey="' . ($access) . '"';
  }
  if ($onmouseover) {
    $link .= ' onmouseover="' . ($onmouseover) . '"';
  }
  if ($onmouseout) {
    $link .= ' onmouseout="' . ($onmouseout) . '"';
  }
  if ($onmousedown) {
    $link .= ' onmousedown="' . ($onmousedown) . '"';
  }

  $link .= '>';
  $link .= $content;
  if ($href) {
    $link .= '</a>';
  } else {
    $link .= '</span>';
  }

  return $link;
}


// default column to sort on -- wall time
$sort_col = "wt";

// default is "single run" report
$diff_mode = false;

// call count data present?
$display_calls = true;

// The following column headers are sortable
$sortable_columns = array("fn" => 1,
                          "ct" => 1,
                          "wt" => 1,
                          "excl_wt" => 1,
                          "ut" => 1,
                          "excl_ut" => 1,
                          "st" => 1,
                          "excl_st" => 1,
                          "mu" => 1,
                          "excl_mu" => 1,
                          "pmu" => 1,
                          "excl_pmu" => 1,
                          "cpu" => 1,
                          "excl_cpu" => 1,
                          "samples" => 1,
                          "excl_samples" => 1
                          );

// Textual descriptions for column headers in "single run" mode
$descriptions = array(
                      "fn" => "Function Name",
                      "ct" =>  "Calls",
                      "Calls%" => "Calls%",

                      "wt" => "Incl. Wall Time<br>(microsec)",
                      "IWall%" => "IWall%",
                      "excl_wt" => "Excl. Wall Time<br>(microsec)",
                      "EWall%" => "EWall%",

                      "ut" => "Incl. User<br>(microsecs)",
                      "IUser%" => "IUser%",
                      "excl_ut" => "Excl. User<br>(microsec)",
                      "EUser%" => "EUser%",

                      "st" => "Incl. Sys <br>(microsec)",
                      "ISys%" => "ISys%",
                      "excl_st" => "Excl. Sys <br>(microsec)",
                      "ESys%" => "ESys%",

                      "cpu" => "Incl. CPU<br>(microsecs)",
                      "ICpu%" => "ICpu%",
                      "excl_cpu" => "Excl. CPU<br>(microsec)",
                      "ECpu%" => "ECPU%",

                      "mu" => "Incl.<br>MemUse<br>(bytes)",
                      "IMUse%" => "IMemUse%",
                      "excl_mu" => "Excl.<br>MemUse<br>(bytes)",
                      "EMUse%" => "EMemUse%",

                      "pmu" => "Incl.<br> PeakMemUse<br>(bytes)",
                      "IPMUse%" => "IPeakMemUse%",
                      "excl_pmu" => "Excl.<br>PeakMemUse<br>(bytes)",
                      "EPMUse%" => "EPeakMemUse%",

                      "samples" => "Incl. Samples",
                      "ISamples%" => "ISamples%",
                      "excl_samples" => "Excl. Samples",
                      "ESamples%" => "ESamples%",
                      );

// Formatting Callback Functions...
$format_cbk = array(
                      "fn" => "",
                      "ct" => "xhprof_count_format",
                      "Calls%" => "xhprof_percent_format",

                      "wt" => "number_format",
                      "IWall%" => "xhprof_percent_format",
                      "excl_wt" => "number_format",
                      "EWall%" => "xhprof_percent_format",

                      "ut" => "number_format",
                      "IUser%" => "xhprof_percent_format",
                      "excl_ut" => "number_format",
                      "EUser%" => "xhprof_percent_format",

                      "st" => "number_format",
                      "ISys%" => "xhprof_percent_format",
                      "excl_st" => "number_format",
                      "ESys%" => "xhprof_percent_format",

                      "cpu" => "number_format",
                      "ICpu%" => "xhprof_percent_format",
                      "excl_cpu" => "number_format",
                      "ECpu%" => "xhprof_percent_format",

                      "mu" => "number_format",
                      "IMUse%" => "xhprof_percent_format",
                      "excl_mu" => "number_format",
                      "EMUse%" => "xhprof_percent_format",

                      "pmu" => "number_format",
                      "IPMUse%" => "xhprof_percent_format",
                      "excl_pmu" => "number_format",
                      "EPMUse%" => "xhprof_percent_format",

                      "samples" => "number_format",
                      "ISamples%" => "xhprof_percent_format",
                      "excl_samples" => "number_format",
                      "ESamples%" => "xhprof_percent_format",
                      );


// Textual descriptions for column headers in "diff" mode
$diff_descriptions = array(
                      "fn" => "Function Name",
                      "ct" =>  "Calls Diff",
                      "Calls%" => "Calls<br>Diff%",

                      "wt" => "Incl. Wall<br>Diff<br>(microsec)",
                      "IWall%" => "IWall<br> Diff%",
                      "excl_wt" => "Excl. Wall<br>Diff<br>(microsec)",
                      "EWall%" => "EWall<br>Diff%",

                      "ut" => "Incl. User Diff<br>(microsec)",
                      "IUser%" => "IUser<br>Diff%",
                      "excl_ut" => "Excl. User<br>Diff<br>(microsec)",
                      "EUser%" => "EUser<br>Diff%",

                      "cpu" => "Incl. CPU Diff<br>(microsec)",
                      "ICpu%" => "ICpu<br>Diff%",
                      "excl_cpu" => "Excl. CPU<br>Diff<br>(microsec)",
                      "ECpu%" => "ECpu<br>Diff%",

                      "st" => "Incl. Sys Diff<br>(microsec)",
                      "ISys%" => "ISys<br>Diff%",
                      "excl_st" => "Excl. Sys Diff<br>(microsec)",
                      "ESys%" => "ESys<br>Diff%",

                      "mu" => "Incl.<br>MemUse<br>Diff<br>(bytes)",
                      "IMUse%" => "IMemUse<br>Diff%",
                      "excl_mu" => "Excl.<br>MemUse<br>Diff<br>(bytes)",
                      "EMUse%" => "EMemUse<br>Diff%",

                      "pmu" => "Incl.<br> PeakMemUse<br>Diff<br>(bytes)",
                      "IPMUse%" => "IPeakMemUse<br>Diff%",
                      "excl_pmu" => "Excl.<br>PeakMemUse<br>Diff<br>(bytes)",
                      "EPMUse%" => "EPeakMemUse<br>Diff%",

                      "samples" => "Incl. Samples Diff",
                      "ISamples%" => "ISamples Diff%",
                      "excl_samples" => "Excl. Samples Diff",
                      "ESamples%" => "ESamples Diff%",
                      );

// columns that'll be displayed in a top-level report
$stats = array();

// columns that'll be displayed in a function's parent/child report
$pc_stats = array();

// Various total counts
$totals = 0;
$totals_1 = 0;
$totals_2 = 0;

/*
 * The subset of $possible_metrics that is present in the raw profile data.
 */
$metrics = null;

/**
 * Callback comparison operator (passed to usort() for sorting array of
 * tuples) that compares array elements based on the sort column
 * specified in $sort_col (global parameter).
 *
 * @author Kannan
 */
function sort_cbk($a, $b) {
  global $sort_col;
  global $diff_mode;

  if ($sort_col == "fn") {

    // case insensitive ascending sort for function names
    $left = strtoupper($a["fn"]);
    $right = strtoupper($b["fn"]);

    if ($left == $right)
      return 0;
    return ($left < $right) ? -1 : 1;

  } else {

    // descending sort for all others
    $left = $a[$sort_col];
    $right = $b[$sort_col];

    // if diff mode, sort by absolute value of regression/improvement
    if ($diff_mode) {
      $left = abs($left);
      $right = abs($right);
    }

    if ($left == $right)
      return 0;
    return ($left > $right) ? -1 : 1;
  }
}

/**
 * Get the appropriate description for a statistic
 * (depending upon whether we are in diff report mode
 * or single run report mode).
 *
 * @author Kannan
 */
function stat_description($stat) {
  global $descriptions;
  global $diff_descriptions;
  global $diff_mode;

  if ($diff_mode) {
    return $diff_descriptions[$stat];
  } else {
    return $descriptions[$stat];
  }
}


/**
 * Analyze raw data & generate the profiler report
 * (common for both single run mode and diff mode).
 *
 * @author: Kannan
 */
function profiler_report ($url_params,
                          $rep_symbol,
                          $sort,
                          $run1,
                          $run1_desc,
                          $run1_data,
                          $run2 = 0,
                          $run2_desc = "",
                          $run2_data = array()) {
  global $totals;
  global $totals_1;
  global $totals_2;
  global $stats;
  global $pc_stats;
  global $diff_mode;
  global $base_path;

  // if we are reporting on a specific function, we can trim down
  // the report(s) to just stuff that is relevant to this function.
  // That way compute_flat_info()/compute_diff() etc. do not have
  // to needlessly work hard on churning irrelevant data.
  if (!empty($rep_symbol)) {
    $run1_data = xhprof_trim_run($run1_data, array($rep_symbol));
    if ($diff_mode) {
      $run2_data = xhprof_trim_run($run2_data, array($rep_symbol));
    }
  }

  if ($diff_mode) {
    $run_delta = xhprof_compute_diff($run1_data, $run2_data);
    $symbol_tab  = xhprof_compute_flat_info($run_delta, $totals);
    $symbol_tab1 = xhprof_compute_flat_info($run1_data, $totals_1);
    $symbol_tab2 = xhprof_compute_flat_info($run2_data, $totals_2);
  } else {
    $symbol_tab = xhprof_compute_flat_info($run1_data, $totals);
  }

  $run1_txt = sprintf("<b>Run #%s:</b> %s",
                      $run1, $run1_desc);

  $base_url_params = xhprof_array_unset(xhprof_array_unset($url_params,
                                                           'symbol'),
                                        'all');

  $top_link_query_string = "$base_path/?" . http_build_query($base_url_params);

  if ($diff_mode) {
    $diff_text = "Diff";
    $base_url_params = xhprof_array_unset($base_url_params, 'run1');
    $base_url_params = xhprof_array_unset($base_url_params, 'run2');
    $run1_link = xhprof_render_link('View Run #' . $run1,
                           "$base_path/?" .
                           http_build_query(xhprof_array_set($base_url_params,
                                                      'run',
                                                      $run1)));
    $run2_txt = sprintf("<b>Run #%s:</b> %s",
                        $run2, $run2_desc);

    $run2_link = xhprof_render_link('View Run #' . $run2,
                                    "$base_path/?" .
                        http_build_query(xhprof_array_set($base_url_params,
                                                          'run',
                                                          $run2)));
  } else {
    $diff_text = "Run";
  }

  // set up the action links for operations that can be done on this report
  $links = array();
  $links [] =  xhprof_render_link("View Top Level $diff_text Report",
                                 $top_link_query_string);

  if ($diff_mode) {
    $inverted_params = $url_params;
    $inverted_params['run1'] = $url_params['run2'];
    $inverted_params['run2'] = $url_params['run1'];

    // view the different runs or invert the current diff
    $links [] = $run1_link;
    $links [] = $run2_link;
    $links [] = xhprof_render_link('Invert ' . $diff_text . ' Report',
                           "$base_path/?".
                           http_build_query($inverted_params));
  }

  // lookup function typeahead form
  $links [] = '<input class="function_typeahead" ' .
              ' type="input" size="40" maxlength="100" />';

  echo xhprof_render_actions($links);


  echo
    '<dl class=phprof_report_info>' .
    '  <dt>' . $diff_text . ' Report</dt>' .
    '  <dd>' . ($diff_mode ?
                $run1_txt . '<br><b>vs.</b><br>' . $run2_txt :
                $run1_txt) .
    '  </dd>' .
    '  <dt>Tip</dt>' .
    '  <dd>Click a function name below to drill down.</dd>' .
    '</dl>' .
    '<div style="clear: both; margin: 3em 0em;"></div>';

  // data tables
  if (!empty($rep_symbol)) {
    if (!isset($symbol_tab[$rep_symbol])) {
      echo "<hr>Symbol <b>$rep_symbol</b> not found in XHProf run</b><hr>";
      return;
    }

    /* single function report with parent/child information */
    if ($diff_mode) {
      $info1 = isset($symbol_tab1[$rep_symbol]) ?
                       $symbol_tab1[$rep_symbol] : null;
      $info2 = isset($symbol_tab2[$rep_symbol]) ?
                       $symbol_tab2[$rep_symbol] : null;
      symbol_report($url_params, $run_delta, $symbol_tab[$rep_symbol],
                    $sort, $rep_symbol,
                    $run1, $info1,
                    $run2, $info2);
    } else {
      symbol_report($url_params, $run1_data, $symbol_tab[$rep_symbol],
                    $sort, $rep_symbol, $run1);
    }
  } else {
    /* flat top-level report of all functions */
    full_report($url_params, $symbol_tab, $sort, $run1, $run2);
  }
}

/**
 * Computes percentage for a pair of values, and returns it
 * in string format.
 */
function pct($a, $b) {
  if ($b == 0) {
    return "N/A";
  } else {
    $res = (round(($a * 1000 / $b)) / 10);
    return $res;
  }
}

/**
 * Given a number, returns the td class to use for display.
 *
 * For instance, negative numbers in diff reports comparing two runs (run1 & run2)
 * represent improvement from run1 to run2. We use green to display those deltas,
 * and red for regression deltas.
 */
function get_print_class($num, $bold) {
  global $vbar;
  global $vbbar;
  global $vrbar;
  global $vgbar;
  global $diff_mode;

  if ($bold) {
    if ($diff_mode) {
      if ($num <= 0) {
        $class = $vgbar; // green (improvement)
      } else {
        $class = $vrbar; // red (regression)
      }
    } else {
      $class = $vbbar; // blue
    }
  }
  else {
    $class = $vbar;  // default (black)
  }

  return $class;
}

/**
 * Prints a <td> element with a numeric value.
 */
function print_td_num($num, $fmt_func, $bold=false, $attributes=null) {

  $class = get_print_class($num, $bold);

  if (!empty($fmt_func)) {
    $num = call_user_func($fmt_func, $num);
  }

  print("<td $attributes $class>$num</td>\n");
}

/**
 * Prints a <td> element with a pecentage.
 */
function print_td_pct($numer, $denom, $bold=false, $attributes=null) {
  global $vbar;
  global $vbbar;
  global $diff_mode;

  $class = get_print_class($numer, $bold);

  if ($denom == 0) {
    $pct = "N/A%";
  } else {
    $pct = xhprof_percent_format($numer / abs($denom));
  }

  print("<td $attributes $class>$pct</td>\n");
}

/**
 * Print "flat" data corresponding to one function.
 *
 * @author Kannan
 */
function print_function_info($url_params, $info, $sort, $run1, $run2) {
  static $odd_even = 0;

  global $totals;
  global $sort_col;
  global $metrics;
  global $format_cbk;
  global $display_calls;
  global $base_path;

  // Toggle $odd_or_even
  $odd_even = 1 - $odd_even;

  if ($odd_even) {
    print("<tr>");
  }
  else {
    print('<tr bgcolor="#e5e5e5">');
  }

  $href = "$base_path/?" .
           http_build_query(xhprof_array_set($url_params,
                                             'symbol', $info["fn"]));

  print('<td>');
  print(xhprof_render_link($info["fn"], $href));
  print_source_link($info);
  print("</td>\n");

  if ($display_calls) {
    // Call Count..
    print_td_num($info["ct"], $format_cbk["ct"], ($sort_col == "ct"));
    print_td_pct($info["ct"], $totals["ct"], ($sort_col == "ct"));
  }

  // Other metrics..
  foreach ($metrics as $metric) {
    // Inclusive metric
    print_td_num($info[$metric], $format_cbk[$metric],
                 ($sort_col == $metric));
    print_td_pct($info[$metric], $totals[$metric],
                 ($sort_col == $metric));

    // Exclusive Metric
    print_td_num($info["excl_" . $metric],
                 $format_cbk["excl_" . $metric],
                 ($sort_col == "excl_" . $metric));
    print_td_pct($info["excl_" . $metric],
                 $totals[$metric],
                 ($sort_col == "excl_" . $metric));
  }

  print("</tr>\n");
}

/**
 * Print non-hierarchical (flat-view) of profiler data.
 *
 * @author Kannan
 */
function print_flat_data($url_params, $title, $flat_data, $sort, $run1, $run2, $limit) {

  global $stats;
  global $sortable_columns;
  global $vwbar;
  global $base_path;

  $size  = count($flat_data);
  if (!$limit) {              // no limit
    $limit = $size;
    $display_link = "";
  } else {
    $display_link = xhprof_render_link(" [ <b class=bubble>display all </b>]",
                                       "$base_path/?" .
                                       http_build_query(xhprof_array_set($url_params,
                                                                         'all', 1)));
  }

  print("<h3 align=center>$title $display_link</h3><br>");

  print('<table border=1 cellpadding=2 cellspacing=1 width="90%" '
        .'rules=rows bordercolor="#bdc7d8" align=center>');
  print('<tr bgcolor="#bdc7d8" align=right>');

  foreach ($stats as $stat) {
    $desc = stat_description($stat);
    if (array_key_exists($stat, $sortable_columns)) {
      $href = "$base_path/?"
              . http_build_query(xhprof_array_set($url_params, 'sort', $stat));
      $header = xhprof_render_link($desc, $href);
    } else {
      $header = $desc;
    }

    if ($stat == "fn")
      print("<th align=left><nobr>$header</th>");
    else print("<th " . $vwbar . "><nobr>$header</th>");
  }
  print("</tr>\n");

  if ($limit >= 0) {
    $limit = min($size, $limit);
    for ($i = 0; $i < $limit; $i++) {
      print_function_info($url_params, $flat_data[$i], $sort, $run1, $run2);
    }
  } else {
    // if $limit is negative, print abs($limit) items starting from the end
    $limit = min($size, abs($limit));
    for ($i = 0; $i < $limit; $i++) {
      print_function_info($url_params, $flat_data[$size - $i - 1], $sort, $run1, $run2);
    }
  }
  print("</table>");

  // let's print the display all link at the bottom as well...
  if ($display_link) {
    echo '<div style="text-align: left; padding: 2em">' . $display_link . '</div>';
  }

}

/**
 * Generates a tabular report for all functions. This is the top-level report.
 *
 * @author Kannan
 */
function full_report($url_params, $symbol_tab, $sort, $run1, $run2) {
  global $vwbar;
  global $vbar;
  global $totals;
  global $totals_1;
  global $totals_2;
  global $metrics;
  global $diff_mode;
  global $descriptions;
  global $sort_col;
  global $format_cbk;
  global $display_calls;
  global $base_path;

  $possible_metrics = xhprof_get_possible_metrics();

  if ($diff_mode) {

    $base_url_params = xhprof_array_unset(xhprof_array_unset($url_params,
                                                             'run1'),
                                          'run2');
    $href1 = "$base_path/?" .
      http_build_query(xhprof_array_set($base_url_params,
                                        'run', $run1));
    $href2 = "$base_path/?" .
      http_build_query(xhprof_array_set($base_url_params,
                                        'run', $run2));

    print("<h3><center>Overall Diff Summary</center></h3>");
    print('<table border=1 cellpadding=2 cellspacing=1 width="30%" '
          .'rules=rows bordercolor="#bdc7d8" align=center>' . "\n");
    print('<tr bgcolor="#bdc7d8" align=right>');
    print("<th></th>");
    print("<th $vwbar>" . xhprof_render_link("Run #$run1", $href1) . "</th>");
    print("<th $vwbar>" . xhprof_render_link("Run #$run2", $href2) . "</th>");
    print("<th $vwbar>Diff</th>");
    print("<th $vwbar>Diff%</th>");
    print('</tr>');

    if ($display_calls) {
      print('<tr>');
      print("<td>Number of Function Calls</td>");
      print_td_num($totals_1["ct"], $format_cbk["ct"]);
      print_td_num($totals_2["ct"], $format_cbk["ct"]);
      print_td_num($totals_2["ct"] - $totals_1["ct"], $format_cbk["ct"], true);
      print_td_pct($totals_2["ct"] - $totals_1["ct"], $totals_1["ct"], true);
      print('</tr>');
    }

    foreach ($metrics as $metric) {
      $m = $metric;
      print('<tr>');
      print("<td>" . str_replace("<br>", " ", $descriptions[$m]) . "</td>");
      print_td_num($totals_1[$m], $format_cbk[$m]);
      print_td_num($totals_2[$m], $format_cbk[$m]);
      print_td_num($totals_2[$m] - $totals_1[$m], $format_cbk[$m], true);
      print_td_pct($totals_2[$m] - $totals_1[$m], $totals_1[$m], true);
      print('<tr>');
    }
    print('</table>');

    $callgraph_report_title = '[View Regressions/Improvements using Callgraph Diff]';

  } else {
    print("<p><center>\n");

    print('<table cellpadding=2 cellspacing=1 width="30%" '
          .'bgcolor="#bdc7d8" align=center>' . "\n");
    echo "<tr>";
    echo "<th style='text-align:right'>Overall Summary</th>";
    echo "<th></th>";
    echo "</tr>";

    foreach ($metrics as $metric) {
      echo "<tr>";
      echo "<td style='text-align:right; font-weight:bold'>Total "
            . str_replace("<br>", " ", stat_description($metric)) . ":</td>";
      echo "<td>" . number_format($totals[$metric]) .  " "
           . $possible_metrics[$metric][1] . "</td>";
      echo "</tr>";
    }

    if ($display_calls) {
      echo "<tr>";
      echo "<td style='text-align:right; font-weight:bold'>Number of Function Calls:</td>";
      echo "<td>" . number_format($totals['ct']) . "</td>";
      echo "</tr>";
    }

    echo "</table>";
    print("</center></p>\n");

    $callgraph_report_title = '[View Full Callgraph]';
  }

  print("<center><br><h3>" .
        xhprof_render_link($callgraph_report_title,
                    "$base_path/?q=callgraph&". http_build_query($url_params))
        . "</h3></center>");


  $flat_data = array();
  foreach ($symbol_tab as $symbol => $info) {
    $tmp = $info;
    $tmp["fn"] = $symbol;
    $flat_data[] = $tmp;
  }
  usort($flat_data, 'sort_cbk');

  print("<br>");

  if (!empty($url_params['all'])) {
    $all = true;
    $limit = 0;    // display all rows
  } else {
    $all = false;
    $limit = 100;  // display only limited number of rows
  }

  $desc = str_replace("<br>", " ", $descriptions[$sort_col]);

  if ($diff_mode) {
    if ($all) {
      $title = "Total Diff Report: '
               .'Sorted by absolute value of regression/improvement in $desc";
    } else {
      $title = "Top 100 <i style='color:red'>Regressions</i>/"
               . "<i style='color:green'>Improvements</i>: "
               . "Sorted by $desc Diff";
    }
  } else {
    if ($all) {
      $title = "Sorted by $desc";
    } else {
      $title = "Displaying top $limit functions: Sorted by $desc";
    }
  }
  print_flat_data($url_params, $title, $flat_data, $sort, $run1, $run2, $limit);
}


/**
 * Return attribute names and values to be used by javascript tooltip.
 */
function get_tooltip_attributes($type, $metric) {
  return "type='$type' metric='$metric'";
}

/**
 * Print info for a parent or child function in the
 * parent & children report.
 *
 * @author Kannan
 */
function pc_info($info, $base_ct, $base_info, $parent) {
  global $sort_col;
  global $metrics;
  global $format_cbk;
  global $display_calls;

  if ($parent)
    $type = "Parent";
  else $type = "Child";

  if ($display_calls) {
    $mouseoverct = get_tooltip_attributes($type, "ct");
    /* call count */
    print_td_num($info["ct"], $format_cbk["ct"], ($sort_col == "ct"), $mouseoverct);
    print_td_pct($info["ct"], $base_ct, ($sort_col == "ct"), $mouseoverct);
  }

  /* Inclusive metric values  */
  foreach ($metrics as $metric) {
    print_td_num($info[$metric], $format_cbk[$metric],
                 ($sort_col == $metric),
                 get_tooltip_attributes($type, $metric));
    print_td_pct($info[$metric], $base_info[$metric], ($sort_col == $metric),
                 get_tooltip_attributes($type, $metric));
  }
}

function print_pc_array($url_params, $results, $base_ct, $base_info, $parent,
                        $run1, $run2) {
  global $base_path;

  // Construct section title
  if ($parent) {
    $title = 'Parent function';
  }
  else {
    $title = 'Child function';
  }
  if (count($results) > 1) {
    $title .= 's';
  }

  print("<tr bgcolor='#e0e0ff'><td>");
  print("<b><i><center>" . $title . "</center></i></b>");
  print("</td></tr>");

  $odd_even = 0;
  foreach ($results as $info) {
    $href = "$base_path/?" .
      http_build_query(xhprof_array_set($url_params,
                                        'symbol', $info["fn"]));

    $odd_even = 1 - $odd_even;

    if ($odd_even) {
      print('<tr>');
    }
    else {
      print('<tr bgcolor="#e5e5e5">');
    }

    print("<td>" . xhprof_render_link($info["fn"], $href));
    print_source_link($info);
    print("</td>");
    pc_info($info, $base_ct, $base_info, $parent);
    print("</tr>");
  }
}

function print_source_link($info) {
  if (strncmp($info['fn'], 'run_init', 8) && $info['fn'] !== 'main()') {
	if (defined('XHPROF_SYMBOL_LOOKUP_URL')) {
      $link = xhprof_render_link(
        'source',
        XHPROF_SYMBOL_LOOKUP_URL . '?symbol='.rawurlencode($info["fn"]));
      print(' ('.$link.')');
    }
  }
}


function print_symbol_summary($symbol_info, $stat, $base) {

  $val = $symbol_info[$stat];
  $desc = str_replace("<br>", " ", stat_description($stat));

  print("$desc: </td>");
  print(number_format($val));
  print(" (" . pct($val, $base) . "% of overall)");
  if (substr($stat, 0, 4) == "excl") {
    $func_base = $symbol_info[str_replace("excl_", "", $stat)];
    print(" (" . pct($val, $func_base) . "% of this function)");
  }
  print("<br>");
}

/**
 * Generates a report for a single function/symbol.
 *
 * @author Kannan
 */
function symbol_report($url_params,
                       $run_data, $symbol_info, $sort, $rep_symbol,
                       $run1,
                       $symbol_info1 = null,
                       $run2 = 0,
                       $symbol_info2 = null) {
  global $vwbar;
  global $vbar;
  global $totals;
  global $pc_stats;
  global $sortable_columns;
  global $metrics;
  global $diff_mode;
  global $descriptions;
  global $format_cbk;
  global $sort_col;
  global $display_calls;
  global $base_path;

  $possible_metrics = xhprof_get_possible_metrics();

  if ($diff_mode) {
    $diff_text = "<b>Diff</b>";
    $regr_impr = "<i style='color:red'>Regression</i>/<i style='color:green'>Improvement</i>";
  } else {
    $diff_text = "";
    $regr_impr = "";
  }

  if ($diff_mode) {

    $base_url_params = xhprof_array_unset(xhprof_array_unset($url_params,
                                                             'run1'),
                                          'run2');
    $href1 = "$base_path?"
      . http_build_query(xhprof_array_set($base_url_params, 'run', $run1));
    $href2 = "$base_path?"
      . http_build_query(xhprof_array_set($base_url_params, 'run', $run2));

    print("<h3 align=center>$regr_impr summary for $rep_symbol<br><br></h3>");
    print('<table border=1 cellpadding=2 cellspacing=1 width="30%" '
          .'rules=rows bordercolor="#bdc7d8" align=center>' . "\n");
    print('<tr bgcolor="#bdc7d8" align=right>');
    print("<th align=left>$rep_symbol</th>");
    print("<th $vwbar><a href=" . $href1 . ">Run #$run1</a></th>");
    print("<th $vwbar><a href=" . $href2 . ">Run #$run2</a></th>");
    print("<th $vwbar>Diff</th>");
    print("<th $vwbar>Diff%</th>");
    print('</tr>');
    print('<tr>');

    if ($display_calls) {
      print("<td>Number of Function Calls</td>");
      print_td_num($symbol_info1["ct"], $format_cbk["ct"]);
      print_td_num($symbol_info2["ct"], $format_cbk["ct"]);
      print_td_num($symbol_info2["ct"] - $symbol_info1["ct"],
                   $format_cbk["ct"], true);
      print_td_pct($symbol_info2["ct"] - $symbol_info1["ct"],
                   $symbol_info1["ct"], true);
      print('</tr>');
    }


    foreach ($metrics as $metric) {
      $m = $metric;

      // Inclusive stat for metric
      print('<tr>');
      print("<td>" . str_replace("<br>", " ", $descriptions[$m]) . "</td>");
      print_td_num($symbol_info1[$m], $format_cbk[$m]);
      print_td_num($symbol_info2[$m], $format_cbk[$m]);
      print_td_num($symbol_info2[$m] - $symbol_info1[$m], $format_cbk[$m], true);
      print_td_pct($symbol_info2[$m] - $symbol_info1[$m], $symbol_info1[$m], true);
      print('</tr>');

      // AVG (per call) Inclusive stat for metric
      print('<tr>');
      print("<td>" . str_replace("<br>", " ", $descriptions[$m]) . " per call </td>");
      $avg_info1 = 'N/A';
      $avg_info2 = 'N/A';
      if ($symbol_info1['ct'] > 0) {
        $avg_info1 = ($symbol_info1[$m] / $symbol_info1['ct']);
      }
      if ($symbol_info2['ct'] > 0) {
        $avg_info2 = ($symbol_info2[$m] / $symbol_info2['ct']);
      }
      print_td_num($avg_info1, $format_cbk[$m]);
      print_td_num($avg_info2, $format_cbk[$m]);
      print_td_num($avg_info2 - $avg_info1, $format_cbk[$m], true);
      print_td_pct($avg_info2 - $avg_info1, $avg_info1, true);
      print('</tr>');

      // Exclusive stat for metric
      $m = "excl_" . $metric;
      print('<tr style="border-bottom: 1px solid black;">');
      print("<td>" . str_replace("<br>", " ", $descriptions[$m]) . "</td>");
      print_td_num($symbol_info1[$m], $format_cbk[$m]);
      print_td_num($symbol_info2[$m], $format_cbk[$m]);
      print_td_num($symbol_info2[$m] - $symbol_info1[$m], $format_cbk[$m], true);
      print_td_pct($symbol_info2[$m] - $symbol_info1[$m], $symbol_info1[$m], true);
      print('</tr>');
    }

    print('</table>');
  }

  print("<br><h4><center>");
  print("Parent/Child $regr_impr report for <b>$rep_symbol</b>");

  $callgraph_href = "$base_path/?q=callgraph&"
    . http_build_query(xhprof_array_set($url_params, 'func', $rep_symbol));

  print(" <a href='$callgraph_href'>[View Callgraph $diff_text]</a><br>");

  print("</center></h4><br>");

  print('<table border=1 cellpadding=2 cellspacing=1 width="90%" '
        .'rules=rows bordercolor="#bdc7d8" align=center>' . "\n");
  print('<tr bgcolor="#bdc7d8" align=right>');

  foreach ($pc_stats as $stat) {
    $desc = stat_description($stat);
    if (array_key_exists($stat, $sortable_columns)) {

      $href = "$base_path/?" .
        http_build_query(xhprof_array_set($url_params,
                                          'sort', $stat));
      $header = xhprof_render_link($desc, $href);
    } else {
      $header = $desc;
    }

    if ($stat == "fn")
      print("<th align=left><nobr>$header</th>");
    else print("<th " . $vwbar . "><nobr>$header</th>");
  }
  print("</tr>");

  print("<tr bgcolor='#e0e0ff'><td>");
  print("<b><i><center>Current Function</center></i></b>");
  print("</td></tr>");

  print("<tr>");
  // make this a self-reference to facilitate copy-pasting snippets to e-mails
  print("<td><a href=''>$rep_symbol</a>");
  print_source_link(array('fn' => $rep_symbol));
  print("</td>");

  if ($display_calls) {
    // Call Count
    print_td_num($symbol_info["ct"], $format_cbk["ct"]);
    print_td_pct($symbol_info["ct"], $totals["ct"]);
  }

  // Inclusive Metrics for current function
  foreach ($metrics as $metric) {
    print_td_num($symbol_info[$metric], $format_cbk[$metric], ($sort_col == $metric));
    print_td_pct($symbol_info[$metric], $totals[$metric], ($sort_col == $metric));
  }
  print("</tr>");

  print("<tr bgcolor='#ffffff'>");
  print("<td style='text-align:right;color:blue'>"
        ."Exclusive Metrics $diff_text for Current Function</td>");

  if ($display_calls) {
    // Call Count
    print("<td $vbar></td>");
    print("<td $vbar></td>");
  }

  // Exclusive Metrics for current function
  foreach ($metrics as $metric) {
    print_td_num($symbol_info["excl_" . $metric], $format_cbk["excl_" . $metric],
                 ($sort_col == $metric),
                 get_tooltip_attributes("Child", $metric));
    print_td_pct($symbol_info["excl_" . $metric], $symbol_info[$metric],
                 ($sort_col == $metric),
                 get_tooltip_attributes("Child", $metric));
  }
  print("</tr>");

  // list of callers/parent functions
  $results = array();
  if ($display_calls) {
    $base_ct = $symbol_info["ct"];
  } else {
    $base_ct = 0;
  }
  foreach ($metrics as $metric) {
    $base_info[$metric] = $symbol_info[$metric];
  }
  foreach ($run_data as $parent_child => $info) {
    list($parent, $child) = xhprof_parse_parent_child($parent_child);
    if (($child == $rep_symbol) && ($parent)) {
      $info_tmp = $info;
      $info_tmp["fn"] = $parent;
      $results[] = $info_tmp;
    }
  }
  usort($results, 'sort_cbk');

  if (count($results) > 0) {
    print_pc_array($url_params, $results, $base_ct, $base_info, true,
                   $run1, $run2);
  }

  // list of callees/child functions
  $results = array();
  $base_ct = 0;
  foreach ($run_data as $parent_child => $info) {
    list($parent, $child) = xhprof_parse_parent_child($parent_child);
    if ($parent == $rep_symbol) {
      $info_tmp = $info;
      $info_tmp["fn"] = $child;
      $results[] = $info_tmp;
      if ($display_calls) {
        $base_ct += $info["ct"];
      }
    }
  }
  usort($results, 'sort_cbk');

  if (count($results)) {
    print_pc_array($url_params, $results, $base_ct, $base_info, false,
                   $run1, $run2);
  }

  print("</table>");

  // These will be used for pop-up tips/help.
  // Related javascript code is in: xhprof_report.js
  print("\n");
  print('<script language="javascript">' . "\n");
  print("var func_name = '\"" . $rep_symbol . "\"';\n");
  print("var total_child_ct  = " . $base_ct . ";\n");
  if ($display_calls) {
    print("var func_ct   = " . $symbol_info["ct"] . ";\n");
  }
  print("var func_metrics = new Array();\n");
  print("var metrics_col  = new Array();\n");
  print("var metrics_desc  = new Array();\n");
  if ($diff_mode) {
    print("var diff_mode = true;\n");
  } else {
    print("var diff_mode = false;\n");
  }
  $column_index = 3; // First three columns are Func Name, Calls, Calls%
  foreach ($metrics as $metric) {
    print("func_metrics[\"" . $metric . "\"] = " . round($symbol_info[$metric]) . ";\n");
    print("metrics_col[\"". $metric . "\"] = " . $column_index . ";\n");
    print("metrics_desc[\"". $metric . "\"] = \"" . $possible_metrics[$metric][2] . "\";\n");

    // each metric has two columns..
    $column_index += 2;
  }
  print('</script>');
  print("\n");

}

/**
 * Generate the profiler report for a single run.
 *
 * @author Kannan
 */
function profiler_single_run_report ($url_params,
                                     $xhprof_data,
                                     $run_desc,
                                     $rep_symbol,
                                     $sort,
                                     $run) {

  init_metrics($xhprof_data, $rep_symbol, $sort, false);

  profiler_report($url_params, $rep_symbol, $sort, $run, $run_desc,
                  $xhprof_data);
}



/**
 * Generate the profiler report for diff mode (delta between two runs).
 *
 * @author Kannan
 */
function profiler_diff_report($url_params,
                              $xhprof_data1,
                              $run1_desc,
                              $xhprof_data2,
                              $run2_desc,
                              $rep_symbol,
                              $sort,
                              $run1,
                              $run2) {


  // Initialize what metrics we'll display based on data in Run2
  init_metrics($xhprof_data2, $rep_symbol, $sort, true);

  profiler_report($url_params,
                  $rep_symbol,
                  $sort,
                  $run1,
                  $run1_desc,
                  $xhprof_data1,
                  $run2,
                  $run2_desc,
                  $xhprof_data2);
}


/**
 * Generate a XHProf Display View given the various URL parameters
 * as arguments. The first argument is an object that implements
 * the iXHProfRuns interface.
 *
 * @param object  $xhprof_runs_impl  An object that implements
 *                                   the iXHProfRuns interface
 *.
 * @param array   $url_params   Array of non-default URL params.
 *
 * @param string  $source       Category/type of the run. The source in
 *                              combination with the run id uniquely
 *                              determines a profiler run.
 *
 * @param string  $run          run id, or comma separated sequence of
 *                              run ids. The latter is used if an aggregate
 *                              report of the runs is desired.
 *
 * @param string  $wts          Comma separate list of integers.
 *                              Represents the weighted ratio in
 *                              which which a set of runs will be
 *                              aggregated. [Used only for aggregate
 *                              reports.]
 *
 * @param string  $symbol       Function symbol. If non-empty then the
 *                              parent/child view of this function is
 *                              displayed. If empty, a flat-profile view
 *                              of the functions is displayed.
 *
 * @param string  $run1         Base run id (for diff reports)
 *
 * @param string  $run2         New run id (for diff reports)
 *
 */
function displayXHProfReport($xhprof_runs_impl, $url_params, $source,
                             $run, $wts, $symbol, $sort, $run1, $run2) {

  if ($run) {                              // specific run to display?

    // run may be a single run or a comma separate list of runs
    // that'll be aggregated. If "wts" (a comma separated list
    // of integral weights is specified), the runs will be
    // aggregated in that ratio.
    //
    $runs_array = explode(",", $run);

    if (count($runs_array) == 1) {
      $xhprof_data = $xhprof_runs_impl->get_run($runs_array[0],
                                                $source,
                                                $description);
    } else {
      if (!empty($wts)) {
        $wts_array  = explode(",", $wts);
      } else {
        $wts_array = null;
      }
      $data = xhprof_aggregate_runs($xhprof_runs_impl,
                                    $runs_array, $wts_array, $source, false);
      $xhprof_data = $data['raw'];
      $description = $data['description'];
    }


    profiler_single_run_report($url_params,
                               $xhprof_data,
                               $description,
                               $symbol,
                               $sort,
                               $run);

  } else if ($run1 && $run2) {                  // diff report for two runs

    $xhprof_data1 = $xhprof_runs_impl->get_run($run1, $source, $description1);
    $xhprof_data2 = $xhprof_runs_impl->get_run($run2, $source, $description2);

    profiler_diff_report($url_params,
                         $xhprof_data1,
                         $description1,
                         $xhprof_data2,
                         $description2,
                         $symbol,
                         $sort,
                         $run1,
                         $run2);

  } else {
    echo "No XHProf runs specified in the URL.";
    if (method_exists($xhprof_runs_impl, 'list_runs')) {
      $xhprof_runs_impl->list_runs();
    }
  }
}

// param name, its type, and default value
$params = array('run' => array(XHPROF_STRING_PARAM, ''), 'wts' => array(XHPROF_STRING_PARAM, ''), 'symbol' => array(XHPROF_STRING_PARAM, ''), 'sort' => array(XHPROF_STRING_PARAM, 'wt'), 'run1' => array(XHPROF_STRING_PARAM, ''), 'run2' => array(XHPROF_STRING_PARAM, ''), 'source' => array(XHPROF_STRING_PARAM, 'xhprof'), 'all' => array(XHPROF_UINT_PARAM, 0));
// pull values of these params, and create named globals for each param
xhprof_param_init($params);
/* reset params to be a array of variable names to values
   by the end of this page, param should only contain values that need
   to be preserved for the next page. unset all unwanted keys in $params.
 */
foreach ($params as $k => $v) {
    $params[$k] = ${$k};
    // unset key from params that are using default values. So URLs aren't
    // ridiculously long.
    if ($params[$k] == $v[1]) {
        unset($params[$k]);
    }
}

if (@$_GET['q'] === 'callgraph') {
 
//  Copyright (c) 2009 Facebook
//
//  Licensed under the Apache License, Version 2.0 (the "License");
//  you may not use this file except in compliance with the License.
//  You may obtain a copy of the License at
//
//      http://www.apache.org/licenses/LICENSE-2.0
//
//  Unless required by applicable law or agreed to in writing, software
//  distributed under the License is distributed on an "AS IS" BASIS,
//  WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
//  See the License for the specific language governing permissions and
//  limitations under the License.
//

/**
 *
 * A callgraph generator for XHProf.
 *
 * * This file is part of the UI/reporting component,
 *   used for viewing results of XHProf runs from a
 *   browser.
 *
 * Modification History:
 *  02/15/2008 - cjiang  - The first version of callgraph visualizer
 *                         based on Graphviz's DOT tool.
 *
 * @author Changhao Jiang (cjiang@facebook.com)
 */

// by default assume that xhprof_html & xhprof_lib directories
// are at the same level.
$GLOBALS['XHPROF_LIB_ROOT'] = dirname(__FILE__) . '/../xhprof_lib';

// require_once $GLOBALS['XHPROF_LIB_ROOT'].'/display/xhprof.php';

ini_set('max_execution_time', 100);

$params = array(// run id param
  'run' => array(XHPROF_STRING_PARAM, ''),

                // source/namespace/type of run
                'source' => array(XHPROF_STRING_PARAM, 'xhprof'),

                // the focus function, if it is set, only directly
                // parents/children functions of it will be shown.
                'func' => array(XHPROF_STRING_PARAM, ''),

                // image type, can be 'jpg', 'gif', 'ps', 'png'
                'type' => array(XHPROF_STRING_PARAM, 'png'),

                // only functions whose exclusive time over the total time
                // is larger than this threshold will be shown.
                // default is 0.01.
                'threshold' => array(XHPROF_FLOAT_PARAM, 0.01),

                // whether to show critical_path
                'critical' => array(XHPROF_BOOL_PARAM, true),

                // first run in diff mode.
                'run1' => array(XHPROF_STRING_PARAM, ''),

                // second run in diff mode.
                'run2' => array(XHPROF_STRING_PARAM, '')
              );

// pull values of these params, and create named globals for each param
xhprof_param_init($params);

// if invalid value specified for threshold, then use the default
if ($threshold < 0 || $threshold > 1) {
  $threshold = $params['threshold'][1];
}

// if invalid value specified for type, use the default
if (!array_key_exists($type, $xhprof_legal_image_types)) {
  $type = $params['type'][1]; // default image type.
}

$xhprof_runs_impl = new XHProfRuns_Default();

if (!empty($run)) {
  // single run call graph image generation
  xhprof_render_image($xhprof_runs_impl, $run, $type,
    $threshold, $func, $source, $critical);
} else {
  // diff report call graph image generation
  xhprof_render_diff_image($xhprof_runs_impl, $run1, $run2,
    $type, $threshold, $source);
}
}
else {
?>
<html>
<head><title>XHProf: Hierarchical Profiler Report</title>
<style>
    td.sorted{color:#00f}td.vbar,th.vbar{text-align:right;border-left:solid 1px #bdc7d8}td.vbbar,th.vbar{text-align:right;border-left:solid 1px #bdc7d8;color:blue}td.vrbar{text-align:right;border-left:solid 1px #bdc7d8;color:red}td.vgbar{text-align:right;border-left:solid 1px #bdc7d8;color:green}td.vwbar,th.vwbar{text-align:right;border-left:solid 1px white}td.vwlbar,th.vwlbar{text-align:left;border-left:solid 1px white}p.blue{color:blue}.bubble{background-color:#c3d9ff}ul.xhprof_actions{float:right;padding-left:16px;list-style-image:none;list-style-type:none;margin:10px 10px 10px 3em;position:relative}ul.xhprof_actions li{border-bottom:1px solid #d8dfea}ul.xhprof_actions li a:hover{background:#3b5998 none repeat scroll 0 0;color:#fff}#tooltip{position:absolute;z-index:3000;border:1px solid #111;background-color:lightyellow;padding:5px;opacity:.9}#tooltip h3,#tooltip div{margin:0}.ac_results{padding:0;border:1px solid black;background-color:white;overflow:hidden;z-index:99999}.ac_results ul{width:100%;list-style-position:outside;list-style:none;padding:0;margin:0}.ac_results li{margin:0;padding:2px 5px;cursor:default;display:block;font:menu;font-size:12px;line-height:16px;overflow:hidden}.ac_loading{background:white url('indicator.gif') right center no-repeat}.ac_odd{background-color:#eee}.ac_over{background-color:#0a246a;color:white}
</style>
</head>
<body>
<?php
$vbar = ' class="vbar"';
$vwbar = ' class="vwbar"';
$vwlbar = ' class="vwlbar"';
$vbbar = ' class="vbbar"';
$vrbar = ' class="vrbar"';
$vgbar = ' class="vgbar"';
$xhprof_runs_impl = new XHProfRuns_Default();
displayXHProfReport($xhprof_runs_impl, $params, $source, $run, $wts, $symbol, $sort, $run1, $run2);
}
?>
<script>
function stringAbs(e){return e.replace("-","")}function isNegative(e){return e.indexOf("-")==0}function addCommas(e){e+="",x=e.split("."),x1=x[0],x2=x.length>1?"."+x[1]:"";var t=/(\d+)(\d{3})/;while(t.test(x1))x1=x1.replace(t,"$1,$2");return x1+x2}function ParentRowToolTip(e,t){var n,r,i,o,u;return row=e.parentNode,tds=row.getElementsByTagName("td"),parent_func=tds[0].innerHTML,diff_mode?u=" diff ":u="",s="<center>",t=="ct"?(parent_ct=tds[1].innerHTML,parent_ct_pct=tds[2].innerHTML,func_ct=addCommas(func_ct),diff_mode?(s+="There are "+stringAbs(parent_ct)+(isNegative(parent_ct)?" fewer ":" more ")+" calls to "+func_name+" from "+parent_func+"<br>",text=" of diff in calls "):text=" of calls ",s+=parent_ct_pct+text+"("+parent_ct+"/"+func_ct+") to "+func_name+" are from "+parent_func+"<br>"):(o=metrics_col[t],r=tds[o].innerHTML,i=tds[o+1].innerHTML,n=addCommas(func_metrics[t]),s+=i+"("+r+"/"+n+") of "+metrics_desc[t]+(diff_mode?isNegative(r)?" decrease":" increase":"")+" in "+func_name+" is due to calls from "+parent_func+"<br>"),s+="</center>",s}function ChildRowToolTip(e,t){var n,r,i,o,u;return row=e.parentNode,tds=row.getElementsByTagName("td"),child_func=tds[0].innerHTML,diff_mode?u=" diff ":u="",s="<center>",t=="ct"?(child_ct=tds[1].innerHTML,child_ct_pct=tds[2].innerHTML,s+=func_name+" called "+child_func+" "+stringAbs(child_ct)+(diff_mode?isNegative(child_ct)?" fewer":" more":"")+" times.<br>",s+="This accounts for "+child_ct_pct+" ("+child_ct+"/"+total_child_ct+") of function calls made by "+func_name+"."):(o=metrics_col[t],r=tds[o].innerHTML,i=tds[o+1].innerHTML,n=addCommas(func_metrics[t]),child_func.indexOf("Exclusive Metrics")!=-1?(s+="The exclusive "+metrics_desc[t]+u+" for "+func_name+" is "+r+" <br>",s+="which is "+i+" of the inclusive "+metrics_desc[t]+u+" for "+func_name+" ("+n+")."):(s+=child_func+" when called from "+func_name+" takes "+stringAbs(r)+(diff_mode?isNegative(r)?" less":" more":"")+" of "+metrics_desc[t]+" <br>",s+="which is "+i+" of the inclusive "+metrics_desc[t]+u+" for "+func_name+" ("+n+").")),s+="</center>",s}(function(){function evalScript(e,t){t.src?jQuery.ajax({url:t.src,async:!1,dataType:"script"}):jQuery.globalEval(t.text||t.textContent||t.innerHTML||""),t.parentNode&&t.parentNode.removeChild(t)}function now(){return+(new Date)}function num(e,t){return e[0]&&parseInt(jQuery.curCSS(e[0],t,!0),10)||0}function bindReady(){if(readyBound)return;readyBound=!0,document.addEventListener&&!jQuery.browser.opera&&document.addEventListener("DOMContentLoaded",jQuery.ready,!1),jQuery.browser.msie&&window==top&&function(){if(jQuery.isReady)return;try{document.documentElement.doScroll("left")}catch(e){setTimeout(arguments.callee,0);return}jQuery.ready()}(),jQuery.browser.opera&&document.addEventListener("DOMContentLoaded",function(){if(jQuery.isReady)return;for(var e=0;e<document.styleSheets.length;e++)if(document.styleSheets[e].disabled){setTimeout(arguments.callee,0);return}jQuery.ready()},!1);if(jQuery.browser.safari){var e;(function(){if(jQuery.isReady)return;if(document.readyState!="loaded"&&document.readyState!="complete"){setTimeout(arguments.callee,0);return}e===undefined&&(e=jQuery("style, link[rel=stylesheet]").length);if(document.styleSheets.length!=e){setTimeout(arguments.callee,0);return}jQuery.ready()})()}jQuery.event.add(window,"load",jQuery.ready)}var _jQuery=window.jQuery,_$=window.$,jQuery=window.jQuery=window.$=function(e,t){return new jQuery.fn.init(e,t)},quickExpr=/^[^<]*(<(.|\s)+>)[^>]*$|^#(\w+)$/,isSimple=/^.[^:#\[\.]*$/,undefined;jQuery.fn=jQuery.prototype={init:function(e,t){e=e||document;if(e.nodeType)return this[0]=e,this.length=1,this;if(typeof e=="string"){var n=quickExpr.exec(e);if(!n||!n[1]&&!!t)return jQuery(t).find(e);if(n[1])e=jQuery.clean([n[1]],t);else{var r=document.getElementById(n[3]);if(r)return r.id!=n[3]?jQuery().find(e):jQuery(r);e=[]}}else if(jQuery.isFunction(e))return jQuery(document)[jQuery.fn.ready?"ready":"load"](e);return this.setArray(jQuery.makeArray(e))},jquery:"1.2.6",size:function(){return this.length},length:0,get:function(e){return e==undefined?jQuery.makeArray(this):this[e]},pushStack:function(e){var t=jQuery(e);return t.prevObject=this,t},setArray:function(e){return this.length=0,Array.prototype.push.apply(this,e),this},each:function(e,t){return jQuery.each(this,e,t)},index:function(e){var t=-1;return jQuery.inArray(e&&e.jquery?e[0]:e,this)},attr:function(e,t,n){var r=e;if(e.constructor==String){if(t===undefined)return this[0]&&jQuery[n||"attr"](this[0],e);r={},r[e]=t}return this.each(function(t){for(e in r)jQuery.attr(n?this.style:this,e,jQuery.prop(this,r[e],n,t,e))})},css:function(e,t){return(e=="width"||e=="height")&&parseFloat(t)<0&&(t=undefined),this.attr(e,t,"curCSS")},text:function(e){if(typeof e!="object"&&e!=null)return this.empty().append((this[0]&&this[0].ownerDocument||document).createTextNode(e));var t="";return jQuery.each(e||this,function(){jQuery.each(this.childNodes,function(){this.nodeType!=8&&(t+=this.nodeType!=1?this.nodeValue:jQuery.fn.text([this]))})}),t},wrapAll:function(e){return this[0]&&jQuery(e,this[0].ownerDocument).clone().insertBefore(this[0]).map(function(){var e=this;while(e.firstChild)e=e.firstChild;return e}).append(this),this},wrapInner:function(e){return this.each(function(){jQuery(this).contents().wrapAll(e)})},wrap:function(e){return this.each(function(){jQuery(this).wrapAll(e)})},append:function(){return this.domManip(arguments,!0,!1,function(e){this.nodeType==1&&this.appendChild(e)})},prepend:function(){return this.domManip(arguments,!0,!0,function(e){this.nodeType==1&&this.insertBefore(e,this.firstChild)})},before:function(){return this.domManip(arguments,!1,!1,function(e){this.parentNode.insertBefore(e,this)})},after:function(){return this.domManip(arguments,!1,!0,function(e){this.parentNode.insertBefore(e,this.nextSibling)})},end:function(){return this.prevObject||jQuery([])},find:function(e){var t=jQuery.map(this,function(t){return jQuery.find(e,t)});return this.pushStack(/[^+>] [^+>]/.test(e)||e.indexOf("..")>-1?jQuery.unique(t):t)},clone:function(e){var t=this.map(function(){if(jQuery.browser.msie&&!jQuery.isXMLDoc(this)){var e=this.cloneNode(!0),t=document.createElement("div");return t.appendChild(e),jQuery.clean([t.innerHTML])[0]}return this.cloneNode(!0)}),n=t.find("*").andSelf().each(function(){this[expando]!=undefined&&(this[expando]=null)});return e===!0&&this.find("*").andSelf().each(function(e){if(this.nodeType==3)return;var t=jQuery.data(this,"events");for(var r in t)for(var i in t[r])jQuery.event.add(n[e],r,t[r][i],t[r][i].data)}),t},filter:function(e){return this.pushStack(jQuery.isFunction(e)&&jQuery.grep(this,function(t,n){return e.call(t,n)})||jQuery.multiFilter(e,this))},not:function(e){if(e.constructor==String){if(isSimple.test(e))return this.pushStack(jQuery.multiFilter(e,this,!0));e=jQuery.multiFilter(e,this)}var t=e.length&&e[e.length-1]!==undefined&&!e.nodeType;return this.filter(function(){return t?jQuery.inArray(this,e)<0:this!=e})},add:function(e){return this.pushStack(jQuery.unique(jQuery.merge(this.get(),typeof e=="string"?jQuery(e):jQuery.makeArray(e))))},is:function(e){return!!e&&jQuery.multiFilter(e,this).length>0},hasClass:function(e){return this.is("."+e)},val:function(e){if(e==undefined){if(this.length){var t=this[0];if(jQuery.nodeName(t,"select")){var n=t.selectedIndex,r=[],i=t.options,s=t.type=="select-one";if(n<0)return null;for(var o=s?n:0,u=s?n+1:i.length;o<u;o++){var a=i[o];if(a.selected){e=jQuery.browser.msie&&!a.attributes.value.specified?a.text:a.value;if(s)return e;r.push(e)}}return r}return(this[0].value||"").replace(/\r/g,"")}return undefined}return e.constructor==Number&&(e+=""),this.each(function(){if(this.nodeType!=1)return;if(e.constructor==Array&&/radio|checkbox/.test(this.type))this.checked=jQuery.inArray(this.value,e)>=0||jQuery.inArray(this.name,e)>=0;else if(jQuery.nodeName(this,"select")){var t=jQuery.makeArray(e);jQuery("option",this).each(function(){this.selected=jQuery.inArray(this.value,t)>=0||jQuery.inArray(this.text,t)>=0}),t.length||(this.selectedIndex=-1)}else this.value=e})},html:function(e){return e==undefined?this[0]?this[0].innerHTML:null:this.empty().append(e)},replaceWith:function(e){return this.after(e).remove()},eq:function(e){return this.slice(e,e+1)},slice:function(){return this.pushStack(Array.prototype.slice.apply(this,arguments))},map:function(e){return this.pushStack(jQuery.map(this,function(t,n){return e.call(t,n,t)}))},andSelf:function(){return this.add(this.prevObject)},data:function(e,t){var n=e.split(".");n[1]=n[1]?"."+n[1]:"";if(t===undefined){var r=this.triggerHandler("getData"+n[1]+"!",[n[0]]);return r===undefined&&this.length&&(r=jQuery.data(this[0],e)),r===undefined&&n[1]?this.data(n[0]):r}return this.trigger("setData"+n[1]+"!",[n[0],t]).each(function(){jQuery.data(this,e,t)})},removeData:function(e){return this.each(function(){jQuery.removeData(this,e)})},domManip:function(e,t,n,r){var i=this.length>1,s;return this.each(function(){s||(s=jQuery.clean(e,this.ownerDocument),n&&s.reverse());var o=this;t&&jQuery.nodeName(this,"table")&&jQuery.nodeName(s[0],"tr")&&(o=this.getElementsByTagName("tbody")[0]||this.appendChild(this.ownerDocument.createElement("tbody")));var u=jQuery([]);jQuery.each(s,function(){var e=i?jQuery(this).clone(!0)[0]:this;jQuery.nodeName(e,"script")?u=u.add(e):(e.nodeType==1&&(u=u.add(jQuery("script",e).remove())),r.call(o,e))}),u.each(evalScript)})}},jQuery.fn.init.prototype=jQuery.fn,jQuery.extend=jQuery.fn.extend=function(){var e=arguments[0]||{},t=1,n=arguments.length,r=!1,i;e.constructor==Boolean&&(r=e,e=arguments[1]||{},t=2),typeof e!="object"&&typeof e!="function"&&(e={}),n==t&&(e=this,--t);for(;t<n;t++)if((i=arguments[t])!=null)for(var s in i){var o=e[s],u=i[s];if(e===u)continue;r&&u&&typeof u=="object"&&!u.nodeType?e[s]=jQuery.extend(r,o||(u.length!=null?[]:{}),u):u!==undefined&&(e[s]=u)}return e};var expando="jQuery"+now(),uuid=0,windowData={},exclude=/z-?index|font-?weight|opacity|zoom|line-?height/i,defaultView=document.defaultView||{};jQuery.extend({noConflict:function(e){return window.$=_$,e&&(window.jQuery=_jQuery),jQuery},isFunction:function(e){return!!e&&typeof e!="string"&&!e.nodeName&&e.constructor!=Array&&/^[\s[]?function/.test(e+"")},isXMLDoc:function(e){return e.documentElement&&!e.body||e.tagName&&e.ownerDocument&&!e.ownerDocument.body},globalEval:function(e){e=jQuery.trim(e);if(e){var t=document.getElementsByTagName("head")[0]||document.documentElement,n=document.createElement("script");n.type="text/javascript",jQuery.browser.msie?n.text=e:n.appendChild(document.createTextNode(e)),t.insertBefore(n,t.firstChild),t.removeChild(n)}},nodeName:function(e,t){return e.nodeName&&e.nodeName.toUpperCase()==t.toUpperCase()},cache:{},data:function(e,t,n){e=e==window?windowData:e;var r=e[expando];return r||(r=e[expando]=++uuid),t&&!jQuery.cache[r]&&(jQuery.cache[r]={}),n!==undefined&&(jQuery.cache[r][t]=n),t?jQuery.cache[r][t]:r},removeData:function(e,t){e=e==window?windowData:e;var n=e[expando];if(t){if(jQuery.cache[n]){delete jQuery.cache[n][t],t="";for(t in jQuery.cache[n])break;t||jQuery.removeData(e)}}else{try{delete e[expando]}catch(r){e.removeAttribute&&e.removeAttribute(expando)}delete jQuery.cache[n]}},each:function(e,t,n){var r,i=0,s=e.length;if(n){if(s==undefined){for(r in e)if(t.apply(e[r],n)===!1)break}else for(;i<s;)if(t.apply(e[i++],n)===!1)break}else if(s==undefined){for(r in e)if(t.call(e[r],r,e[r])===!1)break}else for(var o=e[0];i<s&&t.call(o,i,o)!==!1;o=e[++i]);return e},prop:function(e,t,n,r,i){return jQuery.isFunction(t)&&(t=t.call(e,r)),t&&t.constructor==Number&&n=="curCSS"&&!exclude.test(i)?t+"px":t},className:{add:function(e,t){jQuery.each((t||"").split(/\s+/),function(t,n){e.nodeType==1&&!jQuery.className.has(e.className,n)&&(e.className+=(e.className?" ":"")+n)})},remove:function(e,t){e.nodeType==1&&(e.className=t!=undefined?jQuery.grep(e.className.split(/\s+/),function(e){return!jQuery.className.has(t,e)}).join(" "):"")},has:function(e,t){return jQuery.inArray(t,(e.className||e).toString().split(/\s+/))>-1}},swap:function(e,t,n){var r={};for(var i in t)r[i]=e.style[i],e.style[i]=t[i];n.call(e);for(var i in t)e.style[i]=r[i]},css:function(e,t,n){if(t=="width"||t=="height"){var r,i={position:"absolute",visibility:"hidden",display:"block"},s=t=="width"?["Left","Right"]:["Top","Bottom"];function o(){r=t=="width"?e.offsetWidth:e.offsetHeight;var n=0,i=0;jQuery.each(s,function(){n+=parseFloat(jQuery.curCSS(e,"padding"+this,!0))||0,i+=parseFloat(jQuery.curCSS(e,"border"+this+"Width",!0))||0}),r-=Math.round(n+i)}return jQuery(e).is(":visible")?o():jQuery.swap(e,i,o),Math.max(0,r)}return jQuery.curCSS(e,t,n)},curCSS:function(e,t,n){function s(e){if(!jQuery.browser.safari)return!1;var t=defaultView.getComputedStyle(e,null);return!t||t.getPropertyValue("color")==""}var r,i=e.style;if(t=="opacity"&&jQuery.browser.msie)return r=jQuery.attr(i,"opacity"),r==""?"1":r;if(jQuery.browser.opera&&t=="display"){var o=i.outline;i.outline="0 solid black",i.outline=o}t.match(/float/i)&&(t=styleFloat);if(!n&&i&&i[t])r=i[t];else if(defaultView.getComputedStyle){t.match(/float/i)&&(t="float"),t=t.replace(/([A-Z])/g,"-$1").toLowerCase();var u=defaultView.getComputedStyle(e,null);if(u&&!s(e))r=u.getPropertyValue(t);else{var a=[],f=[],l=e,c=0;for(;l&&s(l);l=l.parentNode)f.unshift(l);for(;c<f.length;c++)s(f[c])&&(a[c]=f[c].style.display,f[c].style.display="block");r=t=="display"&&a[f.length-1]!=null?"none":u&&u.getPropertyValue(t)||"";for(c=0;c<a.length;c++)a[c]!=null&&(f[c].style.display=a[c])}t=="opacity"&&r==""&&(r="1")}else if(e.currentStyle){var h=t.replace(/\-(\w)/g,function(e,t){return t.toUpperCase()});r=e.currentStyle[t]||e.currentStyle[h];if(!/^\d+(px)?$/i.test(r)&&/^\d/.test(r)){var p=i.left,d=e.runtimeStyle.left;e.runtimeStyle.left=e.currentStyle.left,i.left=r||0,r=i.pixelLeft+"px",i.left=p,e.runtimeStyle.left=d}}return r},clean:function(e,t){var n=[];return t=t||document,typeof t.createElement=="undefined"&&(t=t.ownerDocument||t[0]&&t[0].ownerDocument||document),jQuery.each(e,function(e,r){if(!r)return;r.constructor==Number&&(r+="");if(typeof r=="string"){r=r.replace(/(<(\w+)[^>]*?)\/>/g,function(e,t,n){return n.match(/^(abbr|br|col|img|input|link|meta|param|hr|area|embed)$/i)?e:t+"></"+n+">"});var i=jQuery.trim(r).toLowerCase(),s=t.createElement("div"),o=!i.indexOf("<opt")&&[1,"<select multiple='multiple'>","</select>"]||!i.indexOf("<leg")&&[1,"<fieldset>","</fieldset>"]||i.match(/^<(thead|tbody|tfoot|colg|cap)/)&&[1,"<table>","</table>"]||!i.indexOf("<tr")&&[2,"<table><tbody>","</tbody></table>"]||(!i.indexOf("<td")||!i.indexOf("<th"))&&[3,"<table><tbody><tr>","</tr></tbody></table>"]||!i.indexOf("<col")&&[2,"<table><tbody></tbody><colgroup>","</colgroup></table>"]||jQuery.browser.msie&&[1,"div<div>","</div>"]||[0,"",""];s.innerHTML=o[1]+r+o[2];while(o[0]--)s=s.lastChild;if(jQuery.browser.msie){var u=!i.indexOf("<table")&&i.indexOf("<tbody")<0?s.firstChild&&s.firstChild.childNodes:o[1]=="<table>"&&i.indexOf("<tbody")<0?s.childNodes:[];for(var a=u.length-1;a>=0;--a)jQuery.nodeName(u[a],"tbody")&&!u[a].childNodes.length&&u[a].parentNode.removeChild(u[a]);/^\s/.test(r)&&s.insertBefore(t.createTextNode(r.match(/^\s*/)[0]),s.firstChild)}r=jQuery.makeArray(s.childNodes)}if(r.length===0&&!jQuery.nodeName(r,"form")&&!jQuery.nodeName(r,"select"))return;r[0]==undefined||jQuery.nodeName(r,"form")||r.options?n.push(r):n=jQuery.merge(n,r)}),n},attr:function(e,t,n){if(!e||e.nodeType==3||e.nodeType==8)return undefined;var r=!jQuery.isXMLDoc(e),i=n!==undefined,s=jQuery.browser.msie;t=r&&jQuery.props[t]||t;if(e.tagName){var o=/href|src|style/.test(t);t=="selected"&&jQuery.browser.safari&&e.parentNode.selectedIndex;if(t in e&&r&&!o){if(i){if(t=="type"&&jQuery.nodeName(e,"input")&&e.parentNode)throw"type property can't be changed";e[t]=n}return jQuery.nodeName(e,"form")&&e.getAttributeNode(t)?e.getAttributeNode(t).nodeValue:e[t]}if(s&&r&&t=="style")return jQuery.attr(e.style,"cssText",n);i&&e.setAttribute(t,""+n);var u=s&&r&&o?e.getAttribute(t,2):e.getAttribute(t);return u===null?undefined:u}return s&&t=="opacity"?(i&&(e.zoom=1,e.filter=(e.filter||"").replace(/alpha\([^)]*\)/,"")+(parseInt(n)+""=="NaN"?"":"alpha(opacity="+n*100+")")),e.filter&&e.filter.indexOf("opacity=")>=0?parseFloat(e.filter.match(/opacity=([^)]*)/)[1])/100+"":""):(t=t.replace(/-([a-z])/ig,function(e,t){return t.toUpperCase()}),i&&(e[t]=n),e[t])},trim:function(e){return(e||"").replace(/^\s+|\s+$/g,"")},makeArray:function(e){var t=[];if(e!=null){var n=e.length;if(n==null||e.split||e.setInterval||e.call)t[0]=e;else while(n)t[--n]=e[n]}return t},inArray:function(e,t){for(var n=0,r=t.length;n<r;n++)if(t[n]===e)return n;return-1},merge:function(e,t){var n=0,r,i=e.length;if(jQuery.browser.msie)while(r=t[n++])r.nodeType!=8&&(e[i++]=r);else while(r=t[n++])e[i++]=r;return e},unique:function(e){var t=[],n={};try{for(var r=0,i=e.length;r<i;r++){var s=jQuery.data(e[r]);n[s]||(n[s]=!0,t.push(e[r]))}}catch(o){t=e}return t},grep:function(e,t,n){var r=[];for(var i=0,s=e.length;i<s;i++)!n!=!t(e[i],i)&&r.push(e[i]);return r},map:function(e,t){var n=[];for(var r=0,i=e.length;r<i;r++){var s=t(e[r],r);s!=null&&(n[n.length]=s)}return n.concat.apply([],n)}});var userAgent=navigator.userAgent.toLowerCase();jQuery.browser={version:(userAgent.match(/.+(?:rv|it|ra|ie)[\/: ]([\d.]+)/)||[])[1],safari:/webkit/.test(userAgent),opera:/opera/.test(userAgent),msie:/msie/.test(userAgent)&&!/opera/.test(userAgent),mozilla:/mozilla/.test(userAgent)&&!/(compatible|webkit)/.test(userAgent)};var styleFloat=jQuery.browser.msie?"styleFloat":"cssFloat";jQuery.extend({boxModel:!jQuery.browser.msie||document.compatMode=="CSS1Compat",props:{"for":"htmlFor","class":"className","float":styleFloat,cssFloat:styleFloat,styleFloat:styleFloat,readonly:"readOnly",maxlength:"maxLength",cellspacing:"cellSpacing"}}),jQuery.each({parent:function(e){return e.parentNode},parents:function(e){return jQuery.dir(e,"parentNode")},next:function(e){return jQuery.nth(e,2,"nextSibling")},prev:function(e){return jQuery.nth(e,2,"previousSibling")},nextAll:function(e){return jQuery.dir(e,"nextSibling")},prevAll:function(e){return jQuery.dir(e,"previousSibling")},siblings:function(e){return jQuery.sibling(e.parentNode.firstChild,e)},children:function(e){return jQuery.sibling(e.firstChild)},contents:function(e){return jQuery.nodeName(e,"iframe")?e.contentDocument||e.contentWindow.document:jQuery.makeArray(e.childNodes)}},function(e,t){jQuery.fn[e]=function(e){var n=jQuery.map(this,t);return e&&typeof e=="string"&&(n=jQuery.multiFilter(e,n)),this.pushStack(jQuery.unique(n))}}),jQuery.each({appendTo:"append",prependTo:"prepend",insertBefore:"before",insertAfter:"after",replaceAll:"replaceWith"},function(e,t){jQuery.fn[e]=function(){var e=arguments;return this.each(function(){for(var n=0,r=e.length;n<r;n++)jQuery(e[n])[t](this)})}}),jQuery.each({removeAttr:function(e){jQuery.attr(this,e,""),this.nodeType==1&&this.removeAttribute(e)},addClass:function(e){jQuery.className.add(this,e)},removeClass:function(e){jQuery.className.remove(this,e)},toggleClass:function(e){jQuery.className[jQuery.className.has(this,e)?"remove":"add"](this,e)},remove:function(e){if(!e||jQuery.filter(e,[this]).r.length)jQuery("*",this).add(this).each(function(){jQuery.event.remove(this),jQuery.removeData(this)}),this.parentNode&&this.parentNode.removeChild(this)},empty:function(){jQuery(">*",this).remove();while(this.firstChild)this.removeChild(this.firstChild)}},function(e,t){jQuery.fn[e]=function(){return this.each(t,arguments)}}),jQuery.each(["Height","Width"],function(e,t){var n=t.toLowerCase();jQuery.fn[n]=function(e){return this[0]==window?jQuery.browser.opera&&document.body["client"+t]||jQuery.browser.safari&&window["inner"+t]||document.compatMode=="CSS1Compat"&&document.documentElement["client"+t]||document.body["client"+t]:this[0]==document?Math.max(Math.max(document.body["scroll"+t],document.documentElement["scroll"+t]),Math.max(document.body["offset"+t],document.documentElement["offset"+t])):e==undefined?this.length?jQuery.css(this[0],n):null:this.css(n,e.constructor==String?e:e+"px")}});var chars=jQuery.browser.safari&&parseInt(jQuery.browser.version)<417?"(?:[\\w*_-]|\\\\.)":"(?:[\\wĨ-￿*_-]|\\\\.)",quickChild=new RegExp("^>\\s*("+chars+"+)"),quickID=new RegExp("^("+chars+"+)(#)("+chars+"+)"),quickClass=new RegExp("^([#.]?)("+chars+"*)");jQuery.extend({expr:{"":function(e,t,n){return n[2]=="*"||jQuery.nodeName(e,n[2])},"#":function(e,t,n){return e.getAttribute("id")==n[2]},":":{lt:function(e,t,n){return t<n[3]-0},gt:function(e,t,n){return t>n[3]-0},nth:function(e,t,n){return n[3]-0==t},eq:function(e,t,n){return n[3]-0==t},first:function(e,t){return t==0},last:function(e,t,n,r){return t==r.length-1},even:function(e,t){return t%2==0},odd:function(e,t){return t%2},"first-child":function(e){return e.parentNode.getElementsByTagName("*")[0]==e},"last-child":function(e){return jQuery.nth(e.parentNode.lastChild,1,"previousSibling")==e},"only-child":function(e){return!jQuery.nth(e.parentNode.lastChild,2,"previousSibling")},parent:function(e){return e.firstChild},empty:function(e){return!e.firstChild},contains:function(e,t,n){return(e.textContent||e.innerText||jQuery(e).text()||"").indexOf(n[3])>=0},visible:function(e){return"hidden"!=e.type&&jQuery.css(e,"display")!="none"&&jQuery.css(e,"visibility")!="hidden"},hidden:function(e){return"hidden"==e.type||jQuery.css(e,"display")=="none"||jQuery.css(e,"visibility")=="hidden"},enabled:function(e){return!e.disabled},disabled:function(e){return e.disabled},checked:function(e){return e.checked},selected:function(e){return e.selected||jQuery.attr(e,"selected")},text:function(e){return"text"==e.type},radio:function(e){return"radio"==e.type},checkbox:function(e){return"checkbox"==e.type},file:function(e){return"file"==e.type},password:function(e){return"password"==e.type},submit:function(e){return"submit"==e.type},image:function(e){return"image"==e.type},reset:function(e){return"reset"==e.type},button:function(e){return"button"==e.type||jQuery.nodeName(e,"button")},input:function(e){return/input|select|textarea|button/i.test(e.nodeName)},has:function(e,t,n){return jQuery.find(n[3],e).length},header:function(e){return/h\d/i.test(e.nodeName)},animated:function(e){return jQuery.grep(jQuery.timers,function(t){return e==t.elem}).length}}},parse:[/^(\[) *@?([\w-]+) *([!*$^~=]*) *('?"?)(.*?)\4 *\]/,/^(:)([\w-]+)\("?'?(.*?(\(.*?\))?[^(]*?)"?'?\)/,new RegExp("^([:.#]*)("+chars+"+)")],multiFilter:function(e,t,n){var r,i=[];while(e&&e!=r){r=e;var s=jQuery.filter(e,t,n);e=s.t.replace(/^\s*,\s*/,""),i=n?t=s.r:jQuery.merge(i,s.r)}return i},find:function(e,t){if(typeof e!="string")return[e];if(t&&t.nodeType!=1&&t.nodeType!=9)return[];t=t||document;var n=[t],r=[],i,s;while(e&&i!=e){var o=[];i=e,e=jQuery.trim(e);var u=!1,a=quickChild,f=a.exec(e);if(f){s=f[1].toUpperCase();for(var l=0;n[l];l++)for(var c=n[l].firstChild;c;c=c.nextSibling)c.nodeType==1&&(s=="*"||c.nodeName.toUpperCase()==s)&&o.push(c);n=o,e=e.replace(a,"");if(e.indexOf(" ")==0)continue;u=!0}else{a=/^([>+~])\s*(\w*)/i;if((f=a.exec(e))!=null){o=[];var h={};s=f[2].toUpperCase(),f=f[1];for(var p=0,d=n.length;p<d;p++){var v=f=="~"||f=="+"?n[p].nextSibling:n[p].firstChild;for(;v;v=v.nextSibling)if(v.nodeType==1){var m=jQuery.data(v);if(f=="~"&&h[m])break;if(!s||v.nodeName.toUpperCase()==s)f=="~"&&(h[m]=!0),o.push(v);if(f=="+")break}}n=o,e=jQuery.trim(e.replace(a,"")),u=!0}}if(e&&!u)if(!e.indexOf(","))t==n[0]&&n.shift(),r=jQuery.merge(r,n),o=n=[t],e=" "+e.substr(1,e.length);else{var g=quickID,f=g.exec(e);f?f=[0,f[2],f[3],f[1]]:(g=quickClass,f=g.exec(e)),f[2]=f[2].replace(/\\/g,"");var y=n[n.length-1];if(f[1]=="#"&&y&&y.getElementById&&!jQuery.isXMLDoc(y)){var b=y.getElementById(f[2]);(jQuery.browser.msie||jQuery.browser.opera)&&b&&typeof b.id=="string"&&b.id!=f[2]&&(b=jQuery('[@id="'+f[2]+'"]',y)[0]),n=o=b&&(!f[3]||jQuery.nodeName(b,f[3]))?[b]:[]}else{for(var l=0;n[l];l++){var w=f[1]=="#"&&f[3]?f[3]:f[1]!=""||f[0]==""?"*":f[2];w=="*"&&n[l].nodeName.toLowerCase()=="object"&&(w="param"),o=jQuery.merge(o,n[l].getElementsByTagName(w))}f[1]=="."&&(o=jQuery.classFilter(o,f[2]));if(f[1]=="#"){var E=[];for(var l=0;o[l];l++)if(o[l].getAttribute("id")==f[2]){E=[o[l]];break}o=E}n=o}e=e.replace(g,"")}if(e){var S=jQuery.filter(e,o);n=o=S.r,e=jQuery.trim(S.t)}}return e&&(n=[]),n&&t==n[0]&&n.shift(),r=jQuery.merge(r,n),r},classFilter:function(e,t,n){t=" "+t+" ";var r=[];for(var i=0;e[i];i++){var s=(" "+e[i].className+" ").indexOf(t)>=0;(!n&&s||n&&!s)&&r.push(e[i])}return r},filter:function(t,r,not){var last;while(t&&t!=last){last=t;var p=jQuery.parse,m;for(var i=0;p[i];i++){m=p[i].exec(t);if(m){t=t.substring(m[0].length),m[2]=m[2].replace(/\\/g,"");break}}if(!m)break;if(m[1]==":"&&m[2]=="not")r=isSimple.test(m[3])?jQuery.filter(m[3],r,!0).r:jQuery(r).not(m[3]);else if(m[1]==".")r=jQuery.classFilter(r,m[2],not);else if(m[1]=="["){var tmp=[],type=m[3];for(var i=0,rl=r.length;i<rl;i++){var a=r[i],z=a[jQuery.props[m[2]]||m[2]];if(z==null||/href|src|selected/.test(m[2]))z=jQuery.attr(a,m[2])||"";(type==""&&!!z||type=="="&&z==m[5]||type=="!="&&z!=m[5]||type=="^="&&z&&!z.indexOf(m[5])||type=="$="&&z.substr(z.length-m[5].length)==m[5]||(type=="*="||type=="~=")&&z.indexOf(m[5])>=0)^not&&tmp.push(a)}r=tmp}else if(m[1]==":"&&m[2]=="nth-child"){var merge={},tmp=[],test=/(-?)(\d*)n((?:\+|-)?\d*)/.exec(m[3]=="even"&&"2n"||m[3]=="odd"&&"2n+1"||!/\D/.test(m[3])&&"0n+"+m[3]||m[3]),first=test[1]+(test[2]||1)-0,last=test[3]-0;for(var i=0,rl=r.length;i<rl;i++){var node=r[i],parentNode=node.parentNode,id=jQuery.data(parentNode);if(!merge[id]){var c=1;for(var n=parentNode.firstChild;n;n=n.nextSibling)n.nodeType==1&&(n.nodeIndex=c++);merge[id]=!0}var add=!1;first==0?node.nodeIndex==last&&(add=!0):(node.nodeIndex-last)%first==0&&(node.nodeIndex-last)/first>=0&&(add=!0),add^not&&tmp.push(node)}r=tmp}else{var fn=jQuery.expr[m[1]];typeof fn=="object"&&(fn=fn[m[2]]),typeof fn=="string"&&(fn=eval("false||function(a,i){return "+fn+";}")),r=jQuery.grep(r,function(e,t){return fn(e,t,m,r)},not)}}return{r:r,t:t}},dir:function(e,t){var n=[],r=e[t];while(r&&r!=document)r.nodeType==1&&n.push(r),r=r[t];return n},nth:function(e,t,n,r){t=t||1;var i=0;for(;e;e=e[n])if(e.nodeType==1&&++i==t)break;return e},sibling:function(e,t){var n=[];for(;e;e=e.nextSibling)e.nodeType==1&&e!=t&&n.push(e);return n}}),jQuery.event={add:function(e,t,n,r){if(e.nodeType==3||e.nodeType==8)return;jQuery.browser.msie&&e.setInterval&&(e=window),n.guid||(n.guid=this.guid++);if(r!=undefined){var i=n;n=this.proxy(i,function(){return i.apply(this,arguments)}),n.data=r}var s=jQuery.data(e,"events")||jQuery.data(e,"events",{}),o=jQuery.data(e,"handle")||jQuery.data(e,"handle",function(){if(typeof jQuery!="undefined"&&!jQuery.event.triggered)return jQuery.event.handle.apply(arguments.callee.elem,arguments)});o.elem=e,jQuery.each(t.split(/\s+/),function(t,r){var i=r.split(".");r=i[0],n.type=i[1];var u=s[r];if(!u){u=s[r]={};if(!jQuery.event.special[r]||jQuery.event.special[r].setup.call(e)===!1)e.addEventListener?e.addEventListener(r,o,!1):e.attachEvent&&e.attachEvent("on"+r,o)}u[n.guid]=n,jQuery.event.global[r]=!0}),e=null},guid:1,global:{},remove:function(e,t,n){if(e.nodeType==3||e.nodeType==8)return;var r=jQuery.data(e,"events"),i,s;if(r){if(t==undefined||typeof t=="string"&&t.charAt(0)==".")for(var o in r)this.remove(e,o+(t||""));else t.type&&(n=t.handler,t=t.type),jQuery.each(t.split(/\s+/),function(t,s){var o=s.split(".");s=o[0];if(r[s]){if(n)delete r[s][n.guid];else for(n in r[s])(!o[1]||r[s][n].type==o[1])&&delete r[s][n];for(i in r[s])break;if(!i){if(!jQuery.event.special[s]||jQuery.event.special[s].teardown.call(e)===!1)e.removeEventListener?e.removeEventListener(s,jQuery.data(e,"handle"),!1):e.detachEvent&&e.detachEvent("on"+s,jQuery.data(e,"handle"));i=null,delete r[s]}}});for(i in r)break;if(!i){var u=jQuery.data(e,"handle");u&&(u.elem=null),jQuery.removeData(e,"events"),jQuery.removeData(e,"handle")}}},trigger:function(e,t,n,r,i){t=jQuery.makeArray(t);if(e.indexOf("!")>=0){e=e.slice(0,-1);var s=!0}if(!n)this.global[e]&&jQuery("*").add([window,document]).trigger(e,t);else{if(n.nodeType==3||n.nodeType==8)return undefined;var o,u,a=jQuery.isFunction(n[e]||null),f=!t[0]||!t[0].preventDefault;f&&(t.unshift({type:e,target:n,preventDefault:function(){},stopPropagation:function(){},timeStamp:now()}),t[0][expando]=!0),t[0].type=e,s&&(t[0].exclusive=!0);var l=jQuery.data(n,"handle");l&&(o=l.apply(n,t)),(!a||jQuery.nodeName(n,"a")&&e=="click")&&n["on"+e]&&n["on"+e].apply(n,t)===!1&&(o=!1),f&&t.shift(),i&&jQuery.isFunction(i)&&(u=i.apply(n,o==null?t:t.concat(o)),u!==undefined&&(o=u));if(a&&r!==!1&&o!==!1&&(!jQuery.nodeName(n,"a")||e!="click")){this.triggered=!0;try{n[e]()}catch(c){}}this.triggered=!1}return o},handle:function(e){var t,n,r,i,s;e=arguments[0]=jQuery.event.fix(e||window.event),r=e.type.split("."),e.type=r[0],r=r[1],i=!r&&!e.exclusive,s=(jQuery.data(this,"events")||{})[e.type];for(var o in s){var u=s[o];if(i||u.type==r)e.handler=u,e.data=u.data,n=u.apply(this,arguments),t!==!1&&(t=n),n===!1&&(e.preventDefault(),e.stopPropagation())}return t},fix:function(e){if(e[expando]==1)return e;var t=e;e={originalEvent:t};var n="altKey attrChange attrName bubbles button cancelable charCode clientX clientY ctrlKey currentTarget data detail eventPhase fromElement handler keyCode metaKey newValue originalTarget pageX pageY prevValue relatedNode relatedTarget screenX screenY shiftKey srcElement target timeStamp toElement type view wheelDelta which".split(" ");for(var r=n.length;r;r--)e[n[r]]=t[n[r]];e[expando]=!0,e.preventDefault=function(){t.preventDefault&&t.preventDefault(),t.returnValue=!1},e.stopPropagation=function(){t.stopPropagation&&t.stopPropagation(),t.cancelBubble=!0},e.timeStamp=e.timeStamp||now(),e.target||(e.target=e.srcElement||document),e.target.nodeType==3&&(e.target=e.target.parentNode),!e.relatedTarget&&e.fromElement&&(e.relatedTarget=e.fromElement==e.target?e.toElement:e.fromElement);if(e.pageX==null&&e.clientX!=null){var i=document.documentElement,s=document.body;e.pageX=e.clientX+(i&&i.scrollLeft||s&&s.scrollLeft||0)-(i.clientLeft||0),e.pageY=e.clientY+(i&&i.scrollTop||s&&s.scrollTop||0)-(i.clientTop||0)}return!e.which&&(e.charCode||e.charCode===0?e.charCode:e.keyCode)&&(e.which=e.charCode||e.keyCode),!e.metaKey&&e.ctrlKey&&(e.metaKey=e.ctrlKey),!e.which&&e.button&&(e.which=e.button&1?1:e.button&2?3:e.button&4?2:0),e},proxy:function(e,t){return t.guid=e.guid=e.guid||t.guid||this.guid++,t},special:{ready:{setup:function(){bindReady();return},teardown:function(){return}},mouseenter:{setup:function(){return jQuery.browser.msie?!1:(jQuery(this).bind("mouseover",jQuery.event.special.mouseenter.handler),!0)},teardown:function(){return jQuery.browser.msie?!1:(jQuery(this).unbind("mouseover",jQuery.event.special.mouseenter.handler),!0)},handler:function(e){return withinElement(e,this)?!0:(e.type="mouseenter",jQuery.event.handle.apply(this,arguments))}},mouseleave:{setup:function(){return jQuery.browser.msie?!1:(jQuery(this).bind("mouseout",jQuery.event.special.mouseleave.handler),!0)},teardown:function(){return jQuery.browser.msie?!1:(jQuery(this).unbind("mouseout",jQuery.event.special.mouseleave.handler),!0)},handler:function(e){return withinElement(e,this)?!0:(e.type="mouseleave",jQuery.event.handle.apply(this,arguments))}}}},jQuery.fn.extend({bind:function(e,t,n){return e=="unload"?this.one(e,t,n):this.each(function(){jQuery.event.add(this,e,n||t,n&&t)})},one:function(e,t,n){var r=jQuery.event.proxy(n||t,function(e){return jQuery(this).unbind(e,r),(n||t).apply(this,arguments)});return this.each(function(){jQuery.event.add(this,e,r,n&&t)})},unbind:function(e,t){return this.each(function(){jQuery.event.remove(this,e,t)})},trigger:function(e,t,n){return this.each(function(){jQuery.event.trigger(e,t,this,!0,n)})},triggerHandler:function(e,t,n){return this[0]&&jQuery.event.trigger(e,t,this[0],!1,n)},toggle:function(e){var t=arguments,n=1;while(n<t.length)jQuery.event.proxy(e,t[n++]);return this.click(jQuery.event.proxy(e,function(e){return this.lastToggle=(this.lastToggle||0)%n,e.preventDefault(),t[this.lastToggle++].apply(this,arguments)||!1}))},hover:function(e,t){return this.bind("mouseenter",e).bind("mouseleave",t)},ready:function(e){return bindReady(),jQuery.isReady?e.call(document,jQuery):jQuery.readyList.push(function(){return e.call(this,jQuery)}),this}}),jQuery.extend({isReady:!1,readyList:[],ready:function(){jQuery.isReady||(jQuery.isReady=!0,jQuery.readyList&&(jQuery.each(jQuery.readyList,function(){this.call(document)}),jQuery.readyList=null),jQuery(document).triggerHandler("ready"))}});var readyBound=!1;jQuery.each("blur,focus,load,resize,scroll,unload,click,dblclick,mousedown,mouseup,mousemove,mouseover,mouseout,change,select,submit,keydown,keypress,keyup,error"
.split(","),function(e,t){jQuery.fn[t]=function(e){return e?this.bind(t,e):this.trigger(t)}});var withinElement=function(e,t){var n=e.relatedTarget;while(n&&n!=t)try{n=n.parentNode}catch(r){n=t}return n==t};jQuery(window).bind("unload",function(){jQuery("*").add(document).unbind()}),jQuery.fn.extend({_load:jQuery.fn.load,load:function(e,t,n){if(typeof e!="string")return this._load(e);var r=e.indexOf(" ");if(r>=0){var i=e.slice(r,e.length);e=e.slice(0,r)}n=n||function(){};var s="GET";t&&(jQuery.isFunction(t)?(n=t,t=null):(t=jQuery.param(t),s="POST"));var o=this;return jQuery.ajax({url:e,type:s,dataType:"html",data:t,complete:function(e,t){(t=="success"||t=="notmodified")&&o.html(i?jQuery("<div/>").append(e.responseText.replace(/<script(.|\s)*?\/script>/g,"")).find(i):e.responseText),o.each(n,[e.responseText,t,e])}}),this},serialize:function(){return jQuery.param(this.serializeArray())},serializeArray:function(){return this.map(function(){return jQuery.nodeName(this,"form")?jQuery.makeArray(this.elements):this}).filter(function(){return this.name&&!this.disabled&&(this.checked||/select|textarea/i.test(this.nodeName)||/text|hidden|password/i.test(this.type))}).map(function(e,t){var n=jQuery(this).val();return n==null?null:n.constructor==Array?jQuery.map(n,function(e,n){return{name:t.name,value:e}}):{name:t.name,value:n}}).get()}}),jQuery.each("ajaxStart,ajaxStop,ajaxComplete,ajaxError,ajaxSuccess,ajaxSend".split(","),function(e,t){jQuery.fn[t]=function(e){return this.bind(t,e)}});var jsc=now();jQuery.extend({get:function(e,t,n,r){return jQuery.isFunction(t)&&(n=t,t=null),jQuery.ajax({type:"GET",url:e,data:t,success:n,dataType:r})},getScript:function(e,t){return jQuery.get(e,null,t,"script")},getJSON:function(e,t,n){return jQuery.get(e,t,n,"json")},post:function(e,t,n,r){return jQuery.isFunction(t)&&(n=t,t={}),jQuery.ajax({type:"POST",url:e,data:t,success:n,dataType:r})},ajaxSetup:function(e){jQuery.extend(jQuery.ajaxSettings,e)},ajaxSettings:{url:location.href,global:!0,type:"GET",timeout:0,contentType:"application/x-www-form-urlencoded",processData:!0,async:!0,data:null,username:null,password:null,accepts:{xml:"application/xml, text/xml",html:"text/html",script:"text/javascript, application/javascript",json:"application/json, text/javascript",text:"text/plain",_default:"*/*"}},lastModified:{},ajax:function(e){function g(){e.success&&e.success(i,r),e.global&&jQuery.event.trigger("ajaxSuccess",[p,e])}function y(){e.complete&&e.complete(p,r),e.global&&jQuery.event.trigger("ajaxComplete",[p,e]),e.global&&!--jQuery.active&&jQuery.event.trigger("ajaxStop")}e=jQuery.extend(!0,e,jQuery.extend(!0,{},jQuery.ajaxSettings,e));var t,n=/=\?(&|$)/g,r,i,s=e.type.toUpperCase();e.data&&e.processData&&typeof e.data!="string"&&(e.data=jQuery.param(e.data));if(e.dataType=="jsonp"){if(s=="GET")e.url.match(n)||(e.url+=(e.url.match(/\?/)?"&":"?")+(e.jsonp||"callback")+"=?");else if(!e.data||!e.data.match(n))e.data=(e.data?e.data+"&":"")+(e.jsonp||"callback")+"=?";e.dataType="json"}e.dataType=="json"&&(e.data&&e.data.match(n)||e.url.match(n))&&(t="jsonp"+jsc++,e.data&&(e.data=(e.data+"").replace(n,"="+t+"$1")),e.url=e.url.replace(n,"="+t+"$1"),e.dataType="script",window[t]=function(e){i=e,g(),y(),window[t]=undefined;try{delete window[t]}catch(n){}f&&f.removeChild(l)}),e.dataType=="script"&&e.cache==null&&(e.cache=!1);if(e.cache===!1&&s=="GET"){var o=now(),u=e.url.replace(/(\?|&)_=.*?(&|$)/,"$1_="+o+"$2");e.url=u+(u==e.url?(e.url.match(/\?/)?"&":"?")+"_="+o:"")}e.data&&s=="GET"&&(e.url+=(e.url.match(/\?/)?"&":"?")+e.data,e.data=null),e.global&&!(jQuery.active++)&&jQuery.event.trigger("ajaxStart");var a=/^(?:\w+:)?\/\/([^\/?#]+)/;if(e.dataType=="script"&&s=="GET"&&a.test(e.url)&&a.exec(e.url)[1]!=location.host){var f=document.getElementsByTagName("head")[0],l=document.createElement("script");l.src=e.url,e.scriptCharset&&(l.charset=e.scriptCharset);if(!t){var c=!1;l.onload=l.onreadystatechange=function(){!c&&(!this.readyState||this.readyState=="loaded"||this.readyState=="complete")&&(c=!0,g(),y(),f.removeChild(l))}}return f.appendChild(l),undefined}var h=!1,p=window.ActiveXObject?new ActiveXObject("Microsoft.XMLHTTP"):new XMLHttpRequest;e.username?p.open(s,e.url,e.async,e.username,e.password):p.open(s,e.url,e.async);try{e.data&&p.setRequestHeader("Content-Type",e.contentType),e.ifModified&&p.setRequestHeader("If-Modified-Since",jQuery.lastModified[e.url]||"Thu, 01 Jan 1970 00:00:00 GMT"),p.setRequestHeader("X-Requested-With","XMLHttpRequest"),p.setRequestHeader("Accept",e.dataType&&e.accepts[e.dataType]?e.accepts[e.dataType]+", */*":e.accepts._default)}catch(d){}if(e.beforeSend&&e.beforeSend(p,e)===!1)return e.global&&jQuery.active--,p.abort(),!1;e.global&&jQuery.event.trigger("ajaxSend",[p,e]);var v=function(n){if(!h&&p&&(p.readyState==4||n=="timeout")){h=!0,m&&(clearInterval(m),m=null),r=n=="timeout"&&"timeout"||!jQuery.httpSuccess(p)&&"error"||e.ifModified&&jQuery.httpNotModified(p,e.url)&&"notmodified"||"success";if(r=="success")try{i=jQuery.httpData(p,e.dataType,e.dataFilter)}catch(s){r="parsererror"}if(r=="success"){var o;try{o=p.getResponseHeader("Last-Modified")}catch(s){}e.ifModified&&o&&(jQuery.lastModified[e.url]=o),t||g()}else jQuery.handleError(e,p,r);y(),e.async&&(p=null)}};if(e.async){var m=setInterval(v,13);e.timeout>0&&setTimeout(function(){p&&(p.abort(),h||v("timeout"))},e.timeout)}try{p.send(e.data)}catch(d){jQuery.handleError(e,p,null,d)}return e.async||v(),p},handleError:function(e,t,n,r){e.error&&e.error(t,n,r),e.global&&jQuery.event.trigger("ajaxError",[t,e,r])},active:0,httpSuccess:function(e){try{return!e.status&&location.protocol=="file:"||e.status>=200&&e.status<300||e.status==304||e.status==1223||jQuery.browser.safari&&e.status==undefined}catch(t){}return!1},httpNotModified:function(e,t){try{var n=e.getResponseHeader("Last-Modified");return e.status==304||n==jQuery.lastModified[t]||jQuery.browser.safari&&e.status==undefined}catch(r){}return!1},httpData:function(xhr,type,filter){var ct=xhr.getResponseHeader("content-type"),xml=type=="xml"||!type&&ct&&ct.indexOf("xml")>=0,data=xml?xhr.responseXML:xhr.responseText;if(xml&&data.documentElement.tagName=="parsererror")throw"parsererror";return filter&&(data=filter(data,type)),type=="script"&&jQuery.globalEval(data),type=="json"&&(data=eval("("+data+")")),data},param:function(e){var t=[];if(e.constructor==Array||e.jquery)jQuery.each(e,function(){t.push(encodeURIComponent(this.name)+"="+encodeURIComponent(this.value))});else for(var n in e)e[n]&&e[n].constructor==Array?jQuery.each(e[n],function(){t.push(encodeURIComponent(n)+"="+encodeURIComponent(this))}):t.push(encodeURIComponent(n)+"="+encodeURIComponent(jQuery.isFunction(e[n])?e[n]():e[n]));return t.join("&").replace(/%20/g,"+")}}),jQuery.fn.extend({show:function(e,t){return e?this.animate({height:"show",width:"show",opacity:"show"},e,t):this.filter(":hidden").each(function(){this.style.display=this.oldblock||"";if(jQuery.css(this,"display")=="none"){var e=jQuery("<"+this.tagName+" />").appendTo("body");this.style.display=e.css("display"),this.style.display=="none"&&(this.style.display="block"),e.remove()}}).end()},hide:function(e,t){return e?this.animate({height:"hide",width:"hide",opacity:"hide"},e,t):this.filter(":visible").each(function(){this.oldblock=this.oldblock||jQuery.css(this,"display"),this.style.display="none"}).end()},_toggle:jQuery.fn.toggle,toggle:function(e,t){return jQuery.isFunction(e)&&jQuery.isFunction(t)?this._toggle.apply(this,arguments):e?this.animate({height:"toggle",width:"toggle",opacity:"toggle"},e,t):this.each(function(){jQuery(this)[jQuery(this).is(":hidden")?"show":"hide"]()})},slideDown:function(e,t){return this.animate({height:"show"},e,t)},slideUp:function(e,t){return this.animate({height:"hide"},e,t)},slideToggle:function(e,t){return this.animate({height:"toggle"},e,t)},fadeIn:function(e,t){return this.animate({opacity:"show"},e,t)},fadeOut:function(e,t){return this.animate({opacity:"hide"},e,t)},fadeTo:function(e,t,n){return this.animate({opacity:t},e,n)},animate:function(e,t,n,r){var i=jQuery.speed(t,n,r);return this[i.queue===!1?"each":"queue"](function(){if(this.nodeType!=1)return!1;var t=jQuery.extend({},i),n,r=jQuery(this).is(":hidden"),s=this;for(n in e){if(e[n]=="hide"&&r||e[n]=="show"&&!r)return t.complete.call(this);if(n=="height"||n=="width")t.display=jQuery.css(this,"display"),t.overflow=this.style.overflow}return t.overflow!=null&&(this.style.overflow="hidden"),t.curAnim=jQuery.extend({},e),jQuery.each(e,function(n,i){var o=new jQuery.fx(s,t,n);if(/toggle|show|hide/.test(i))o[i=="toggle"?r?"show":"hide":i](e);else{var u=i.toString().match(/^([+-]=)?([\d+-.]+)(.*)$/),a=o.cur(!0)||0;if(u){var f=parseFloat(u[2]),l=u[3]||"px";l!="px"&&(s.style[n]=(f||1)+l,a=(f||1)/o.cur(!0)*a,s.style[n]=a+l),u[1]&&(f=(u[1]=="-="?-1:1)*f+a),o.custom(a,f,l)}else o.custom(a,i,"")}}),!0})},queue:function(e,t){if(jQuery.isFunction(e)||e&&e.constructor==Array)t=e,e="fx";return!e||typeof e=="string"&&!t?queue(this[0],e):this.each(function(){t.constructor==Array?queue(this,e,t):(queue(this,e).push(t),queue(this,e).length==1&&t.call(this))})},stop:function(e,t){var n=jQuery.timers;return e&&this.queue([]),this.each(function(){for(var e=n.length-1;e>=0;e--)n[e].elem==this&&(t&&n[e](!0),n.splice(e,1))}),t||this.dequeue(),this}});var queue=function(e,t,n){if(e){t=t||"fx";var r=jQuery.data(e,t+"queue");if(!r||n)r=jQuery.data(e,t+"queue",jQuery.makeArray(n))}return r};jQuery.fn.dequeue=function(e){return e=e||"fx",this.each(function(){var t=queue(this,e);t.shift(),t.length&&t[0].call(this)})},jQuery.extend({speed:function(e,t,n){var r=e&&e.constructor==Object?e:{complete:n||!n&&t||jQuery.isFunction(e)&&e,duration:e,easing:n&&t||t&&t.constructor!=Function&&t};return r.duration=(r.duration&&r.duration.constructor==Number?r.duration:jQuery.fx.speeds[r.duration])||jQuery.fx.speeds.def,r.old=r.complete,r.complete=function(){r.queue!==!1&&jQuery(this).dequeue(),jQuery.isFunction(r.old)&&r.old.call(this)},r},easing:{linear:function(e,t,n,r){return n+r*e},swing:function(e,t,n,r){return(-Math.cos(e*Math.PI)/2+.5)*r+n}},timers:[],timerId:null,fx:function(e,t,n){this.options=t,this.elem=e,this.prop=n,t.orig||(t.orig={})}}),jQuery.fx.prototype={update:function(){this.options.step&&this.options.step.call(this.elem,this.now,this),(jQuery.fx.step[this.prop]||jQuery.fx.step._default)(this);if(this.prop=="height"||this.prop=="width")this.elem.style.display="block"},cur:function(e){if(this.elem[this.prop]!=null&&this.elem.style[this.prop]==null)return this.elem[this.prop];var t=parseFloat(jQuery.css(this.elem,this.prop,e));return t&&t>-1e4?t:parseFloat(jQuery.curCSS(this.elem,this.prop))||0},custom:function(e,t,n){function i(e){return r.step(e)}this.startTime=now(),this.start=e,this.end=t,this.unit=n||this.unit||"px",this.now=this.start,this.pos=this.state=0,this.update();var r=this;i.elem=this.elem,jQuery.timers.push(i),jQuery.timerId==null&&(jQuery.timerId=setInterval(function(){var e=jQuery.timers;for(var t=0;t<e.length;t++)e[t]()||e.splice(t--,1);e.length||(clearInterval(jQuery.timerId),jQuery.timerId=null)},13))},show:function(){this.options.orig[this.prop]=jQuery.attr(this.elem.style,this.prop),this.options.show=!0,this.custom(0,this.cur());if(this.prop=="width"||this.prop=="height")this.elem.style[this.prop]="1px";jQuery(this.elem).show()},hide:function(){this.options.orig[this.prop]=jQuery.attr(this.elem.style,this.prop),this.options.hide=!0,this.custom(this.cur(),0)},step:function(e){var t=now();if(e||t>this.options.duration+this.startTime){this.now=this.end,this.pos=this.state=1,this.update(),this.options.curAnim[this.prop]=!0;var n=!0;for(var r in this.options.curAnim)this.options.curAnim[r]!==!0&&(n=!1);if(n){this.options.display!=null&&(this.elem.style.overflow=this.options.overflow,this.elem.style.display=this.options.display,jQuery.css(this.elem,"display")=="none"&&(this.elem.style.display="block")),this.options.hide&&(this.elem.style.display="none");if(this.options.hide||this.options.show)for(var i in this.options.curAnim)jQuery.attr(this.elem.style,i,this.options.orig[i])}return n&&this.options.complete.call(this.elem),!1}var s=t-this.startTime;return this.state=s/this.options.duration,this.pos=jQuery.easing[this.options.easing||(jQuery.easing.swing?"swing":"linear")](this.state,s,0,1,this.options.duration),this.now=this.start+(this.end-this.start)*this.pos,this.update(),!0}},jQuery.extend(jQuery.fx,{speeds:{slow:600,fast:200,def:400},step:{scrollLeft:function(e){e.elem.scrollLeft=e.now},scrollTop:function(e){e.elem.scrollTop=e.now},opacity:function(e){jQuery.attr(e.elem.style,"opacity",e.now)},_default:function(e){e.elem.style[e.prop]=e.now+e.unit}}}),jQuery.fn.offset=function(){function border(e){add(jQuery.curCSS(e,"borderLeftWidth",!0),jQuery.curCSS(e,"borderTopWidth",!0))}function add(e,t){left+=parseInt(e,10)||0,top+=parseInt(t,10)||0}var left=0,top=0,elem=this[0],results;if(elem)with(jQuery.browser){var parent=elem.parentNode,offsetChild=elem,offsetParent=elem.offsetParent,doc=elem.ownerDocument,safari2=safari&&parseInt(version)<522&&!/adobeair/i.test(userAgent),css=jQuery.curCSS,fixed=css(elem,"position")=="fixed";if(elem.getBoundingClientRect){var box=elem.getBoundingClientRect();add(box.left+Math.max(doc.documentElement.scrollLeft,doc.body.scrollLeft),box.top+Math.max(doc.documentElement.scrollTop,doc.body.scrollTop)),add(-doc.documentElement.clientLeft,-doc.documentElement.clientTop)}else{add(elem.offsetLeft,elem.offsetTop);while(offsetParent)add(offsetParent.offsetLeft,offsetParent.offsetTop),(mozilla&&!/^t(able|d|h)$/i.test(offsetParent.tagName)||safari&&!safari2)&&border(offsetParent),!fixed&&css(offsetParent,"position")=="fixed"&&(fixed=!0),offsetChild=/^body$/i.test(offsetParent.tagName)?offsetChild:offsetParent,offsetParent=offsetParent.offsetParent;while(parent&&parent.tagName&&!/^body|html$/i.test(parent.tagName))/^inline|table.*$/i.test(css(parent,"display"))||add(-parent.scrollLeft,-parent.scrollTop),mozilla&&css(parent,"overflow")!="visible"&&border(parent),parent=parent.parentNode;(safari2&&(fixed||css(offsetChild,"position")=="absolute")||mozilla&&css(offsetChild,"position")!="absolute")&&add(-doc.body.offsetLeft,-doc.body.offsetTop),fixed&&add(Math.max(doc.documentElement.scrollLeft,doc.body.scrollLeft),Math.max(doc.documentElement.scrollTop,doc.body.scrollTop))}results={top:top,left:left}}return results},jQuery.fn.extend({position:function(){var e=0,t=0,n;if(this[0]){var r=this.offsetParent(),i=this.offset(),s=/^body|html$/i.test(r[0].tagName)?{top:0,left:0}:r.offset();i.top-=num(this,"marginTop"),i.left-=num(this,"marginLeft"),s.top+=num(r,"borderTopWidth"),s.left+=num(r,"borderLeftWidth"),n={top:i.top-s.top,left:i.left-s.left}}return n},offsetParent:function(){var e=this[0].offsetParent;while(e&&!/^body|html$/i.test(e.tagName)&&jQuery.css(e,"position")=="static")e=e.offsetParent;return jQuery(e)}}),jQuery.each(["Left","Top"],function(e,t){var n="scroll"+t;jQuery.fn[n]=function(t){if(!this[0])return;return t!=undefined?this.each(function(){this==window||this==document?window.scrollTo(e?jQuery(window).scrollLeft():t,e?t:jQuery(window).scrollTop()):this[n]=t}):this[0]==window||this[0]==document?self[e?"pageYOffset":"pageXOffset"]||jQuery.boxModel&&document.documentElement[n]||document.body[n]:this[0][n]}}),jQuery.each(["Height","Width"],function(e,t){var n=e?"Left":"Top",r=e?"Right":"Bottom";jQuery.fn["inner"+t]=function(){return this[t.toLowerCase()]()+num(this,"padding"+n)+num(this,"padding"+r)},jQuery.fn["outer"+t]=function(e){return this["inner"+t]()+num(this,"border"+n+"Width")+num(this,"border"+r+"Width")+(e?num(this,"margin"+n)+num(this,"margin"+r):0)}})})(),function(e){function u(n){if(t.parent)return;t.parent=e('<div id="'+n.id+'"><h3></h3><div class="body"></div><div class="url"></div></div>').appendTo(document.body).hide(),e.fn.bgiframe&&t.parent.bgiframe(),t.title=e("h3",t.parent),t.body=e("div.body",t.parent),t.url=e("div.url",t.parent)}function a(t){return e.data(t,"tooltip")}function f(t){a(this).delay?i=setTimeout(c,a(this).delay):c(),o=!!a(this).track,e(document.body).bind("mousemove",h),h(t)}function l(){if(e.tooltip.blocked||this==n||!this.tooltipText&&!a(this).bodyHandler)return;n=this,r=this.tooltipText;if(a(this).bodyHandler){t.title.hide();var i=a(this).bodyHandler.call(this);i.nodeType||i.jquery?t.body.empty().append(i):t.body.html(i),t.body.show()}else if(a(this).showBody){var s=r.split(a(this).showBody);t.title.html(s.shift()).show(),t.body.empty();for(var o=0,u;u=s[o];o++)o>0&&t.body.append("<br/>"),t.body.append(u);t.body.hideWhenEmpty()}else t.title.html(r).show(),t.body.hide();a(this).showURL&&e(this).url()?t.url.html(e(this).url().replace("http://","")).show():t.url.hide(),t.parent.addClass(a(this).extraClass),a(this).fixPNG&&t.parent.fixPNG(),f.apply(this,arguments)}function c(){i=null,(!s||!e.fn.bgiframe)&&a(n).fade?t.parent.is(":animated")?t.parent.stop().show().fadeTo(a(n).fade,n.tOpacity):t.parent.is(":visible")?t.parent.fadeTo(a(n).fade,n.tOpacity):t.parent.fadeIn(a(n).fade):t.parent.show(),h()}function h(r){if(e.tooltip.blocked)return;if(r&&r.target.tagName=="OPTION")return;!o&&t.parent.is(":visible")&&e(document.body).unbind("mousemove",h);if(n==null){e(document.body).unbind("mousemove",h);return}t.parent.removeClass("viewport-right").removeClass("viewport-bottom");var i=t.parent[0].offsetLeft,s=t.parent[0].offsetTop;if(r){i=r.pageX+a(n).left,s=r.pageY+a(n).top;var u="auto";a(n).positionLeft&&(u=e(window).width()-i,i="auto"),t.parent.css({left:i,right:u,top:s})}var f=p(),l=t.parent[0];f.x+f.cx<l.offsetLeft+l.offsetWidth&&(i-=l.offsetWidth+20+a(n).left,t.parent.css({left:i+"px"}).addClass("viewport-right")),f.y+f.cy<l.offsetTop+l.offsetHeight&&(s-=l.offsetHeight+20+a(n).top,t.parent.css({top:s+"px"}).addClass("viewport-bottom"))}function p(){return{x:e(window).scrollLeft(),y:e(window).scrollTop(),cx:e(window).width(),cy:e(window).height()}}function d(r){function u(){t.parent.removeClass(o.extraClass).hide().css("opacity","")}if(e.tooltip.blocked)return;i&&clearTimeout(i),n=null;var o=a(this);(!s||!e.fn.bgiframe)&&o.fade?t.parent.is(":animated")?t.parent.stop().fadeTo(o.fade,0,u):t.parent.stop().fadeOut(o.fade,u):u(),a(this).fixPNG&&t.parent.unfixPNG()}var t={},n,r,i,s=e.browser.msie&&/MSIE\s(5\.5|6\.)/.test(navigator.userAgent),o=!1;e.tooltip={blocked:!1,defaults:{delay:200,fade:!1,showURL:!0,extraClass:"",top:15,left:15,id:"tooltip"},block:function(){e.tooltip.blocked=!e.tooltip.blocked}},e.fn.extend({tooltip:function(n){return n=e.extend({},e.tooltip.defaults,n),u(n),this.each(function(){e.data(this,"tooltip",n),this.tOpacity=t.parent.css("opacity"),this.tooltipText=this.title,e(this).removeAttr("title"),this.alt=""}).mouseover(l).mouseout(d).click(d)},fixPNG:s?function(){return this.each(function(){var t=e(this).css("backgroundImage");t.match(/^url\(["']?(.*\.png)["']?\)$/i)&&(t=RegExp.$1,e(this).css({backgroundImage:"none",filter:"progid:DXImageTransform.Microsoft.AlphaImageLoader(enabled=true, sizingMethod=crop, src='"+t+"')"}).each(function(){var t=e(this).css("position");t!="absolute"&&t!="relative"&&e(this).css("position","relative")}))})}:function(){return this},unfixPNG:s?function(){return this.each(function(){e(this).css({filter:"",backgroundImage:""})})}:function(){return this},hideWhenEmpty:function(){return this.each(function(){e(this)[e(this).html()?"show":"hide"]()})},url:function(){return this.attr("href")||this.attr("src")}})}(jQuery),function(e){e.fn.extend({autocomplete:function(t,n){var r=typeof t=="string";return n=e.extend({},e.Autocompleter.defaults,{url:r?t:null,data:r?null:t,delay:r?e.Autocompleter.defaults.delay:10,max:n&&!n.scroll?10:150},n),n.highlight=n.highlight||function(e){return e},n.formatMatch=n.formatMatch||n.formatItem,this.each(function(){new e.Autocompleter(this,n)})},result:function(e){return this.bind("result",e)},search:function(e){return this.trigger("search",[e])},flushCache:function(){return this.trigger("flushCache")},setOptions:function(e){return this.trigger("setOptions",[e])},unautocomplete:function(){return this.trigger("unautocomplete")}}),e.Autocompleter=function(t,n){function p(){var e=c.selected();if(!e)return!1;var t=e.result;o=t;if(n.multiple){var r=v(i.val());r.length>1&&(t=r.slice(0,r.length-1).join(n.multipleSeparator)+n.multipleSeparator+t),t+=n.multipleSeparator}return i.val(t),b(),i.trigger("result",[e.data,e.value]),!0}function d(e,t){if(f==r.DEL){c.hide();return}var s=i.val();if(!t&&s==o)return;o=s,s=m(s),s.length>=n.minChars?(i.addClass(n.loadingClass),n.matchCase||(s=s.toLowerCase()),E(s,w,b)):(x(),c.hide())}function v(t){if(!t)return[""];var r=t.split(n.multipleSeparator),i=[];return e.each(r,function(t,n){e.trim(n)&&(i[t]=e.trim(n))}),i}function m(e){if(!n.multiple)return e;var t=v(e);return t[t.length-1]}function g(s,u){n.autoFill&&m(i.val()).toLowerCase()==s.toLowerCase()&&f!=r.BACKSPACE&&(i.val(i.val()+u.substring(m(o).length)),e.Autocompleter.Selection(t,o.length,o.length+u.length))}function y(){clearTimeout(s),s=setTimeout(b,200)}function b(){var r=c.visible();c.hide(),clearTimeout(s),x(),n.mustMatch&&i.search(function(e){if(!e)if(n.multiple){var t=v(i.val()).slice(0,-1);i.val(t.join(n.multipleSeparator)+(t.length?n.multipleSeparator:""))}else i.val("")}),r&&e.Autocompleter.Selection(t,t.value.length,t.value.length)}function w(e,t){t&&t.length&&a?(x(),c.display(t,e),g(e,t[0].value),c.show()):b()}function E(r,i,s){n.matchCase||(r=r.toLowerCase());var o=u.load(r);if(o&&o.length)i(r,o);else if(typeof n.url=="string"&&n.url.length>0){var a={timestamp:+(new Date)};e.each(n.extraParams,function(e,t){a[e]=typeof t=="function"?t():t}),e.ajax({mode:"abort",port:"autocomplete"+t.name,dataType:n.dataType,url:n.url,data:e.extend({q:m(r),limit:n.max},a),success:function(e){var t=n.parse&&n.parse(e)||S(e);u.add(r,t),i(r,t)}})}else c.emptyList(),s(r)}function S(t){var r=[],i=t.split("\n");for(var s=0;s<i.length;s++){var o=e.trim(i[s]);o&&(o=o.split("|"),r[r.length]={data:o,value:o[0],result:n.formatResult&&n.formatResult(o,o[0])||o[0]})}return r}function x(){i.removeClass(n.loadingClass)}var r={UP:38,DOWN:40,DEL:46,TAB:9,RETURN:13,ESC:27,COMMA:188,PAGEUP:33,PAGEDOWN:34,BACKSPACE:8},i=e(t).attr("autocomplete","off").addClass(n.inputClass),s,o="",u=e.Autocompleter.Cache(n),a=0,f,l={mouseDownOnSelect:!1},c=e.Autocompleter.Select(n,t,p,l),h;e.browser.opera&&e(t.form).bind("submit.autocomplete",function(){if(h)return h=!1,!1}),i.bind((e.browser.opera?"keypress":"keydown")+".autocomplete",function(t){f=t.keyCode;switch(t.keyCode){case r.UP:t.preventDefault(),c.visible()?c.prev():d(0,!0);break;case r.DOWN:t.preventDefault(),c.visible()?c.next():d(0,!0);break;case r.PAGEUP:t.preventDefault(),c.visible()?c.pageUp():d(0,!0);break;case r.PAGEDOWN:t.preventDefault(),c.visible()?c.pageDown():d(0,!0);break;case n.multiple&&e.trim(n.multipleSeparator)==","&&r.COMMA:case r.TAB:case r.RETURN:if(p())return t.preventDefault(),h=!0,!1;break;case r.ESC:c.hide();break;default:clearTimeout(s),s=setTimeout(d,n.delay)}}).focus(function(){a++}).blur(function(){a=0,l.mouseDownOnSelect||y()}).click(function(){a++>1&&!c.visible()&&d(0,!0)}).bind("search",function(){function n(e,n){var r;if(n&&n.length)for(var s=0;s<n.length;s++)if(n[s].result.toLowerCase()==e.toLowerCase()){r=n[s];break}typeof t=="function"?t(r):i.trigger("result",r&&[r.data,r.value])}var t=arguments.length>1?arguments[1]:null;e.each(v(i.val()),function(e,t){E(t,n,n)})}).bind("flushCache",function(){u.flush()}).bind("setOptions",function(){e.extend(n,arguments[1]),"data"in arguments[1]&&u.populate()}).bind("unautocomplete",function(){c.unbind(),i.unbind(),e(t.form).unbind(".autocomplete")})},e.Autocompleter.defaults={inputClass:"ac_input",resultsClass:"ac_results",loadingClass:"ac_loading",minChars:1,delay:400,matchCase:!1,matchSubset:!0,matchContains:!1,cacheLength:10,max:100,mustMatch:!1,extraParams:{},selectFirst:!0,formatItem:function(e){return e[0]},formatMatch:null,autoFill:!1,width:0,multiple:!1,multipleSeparator:", ",highlight:function(e,t){return e.replace(new RegExp("(?![^&;]+;)(?!<[^<>]*)("+t.replace(/([\^\$\(\)\[\]\{\}\*\.\+\?\|\\])/gi,"\\$1")+")(?![^<>]*>)(?![^&;]+;)","gi"),"<strong>$1</strong>")},scroll:!0,scrollHeight:180},e.Autocompleter.Cache=function(t){function i(e,n){t.matchCase||(e=e.toLowerCase());var r=e.indexOf(n);return r==-1?!1:r==0||t.matchContains}function s(e,i){r>t.cacheLength&&u(),n[e]||r++,n[e]=i}function o(){if(!t.data)return!1;var n={},r=0;t.url||(t.cacheLength=1),n[""]=[];for(var i=0,o=t.data.length;i<o;i++){var u=t.data[i];u=typeof u=="string"?[u]:u;var a=t.formatMatch(u,i+1,t.data.length);if(a===!1)continue;var f=a.charAt(0).toLowerCase();n[f]||(n[f]=[]);var l={value:a,data:u,result:t.formatResult&&t.formatResult(u)||a};n[f].push(l),r++<t.max&&n[""].push(l)}e.each(n,function(e,n){t.cacheLength++,s(e,n)})}function u(){n={},r=0}var n={},r=0;return setTimeout(o,25),{flush:u,add:s,populate:o,load:function(s){if(!t.cacheLength||!r)return null;if(!t.url&&t.matchContains){var o=[];for(var u in n)if(u.length>0){var a=n[u];e.each(a,function(e,t){i(t.value,s)&&o.push(t)})}return o}if(n[s])return n[s];if(t.matchSubset)for(var f=s.length-1;f>=t.minChars;f--){var a=n[s.substr(0,f)];if(a){var o=[];return e.each(a,function(e,t){i(t.value,s)&&(o[o.length]=t)}),o}}return null}}},e.Autocompleter.Select=function(t,n,r,i){function p(){if(!l)return;c=e("<div/>").hide().addClass(t.resultsClass).css("position","absolute").appendTo(document.body),h=e("<ul/>").appendTo(c).mouseover(function(t){d(t).nodeName&&d(t).nodeName.toUpperCase()=="LI"&&(u=e("li",h).removeClass(s.ACTIVE).index(d(t)),e(d(t)).addClass(s.ACTIVE))}).click(function(t){return e(d(t)).addClass(s.ACTIVE),r(),n.focus(),!1}).mousedown(function(){i.mouseDownOnSelect=!0}).mouseup(function(){i.mouseDownOnSelect=!1}),t.width>0&&c.css("width",t.width),l=!1}function d(e){var t=e.target;while(t&&t.tagName!="LI")t=t.parentNode;return t?t:[]}function v(e){o.slice(u,u+1).removeClass(s.ACTIVE),m(e);var n=o.slice(u,u+1).addClass(s.ACTIVE);if(t.scroll){var r=0;o.slice(0,u).each(function(){r+=this.offsetHeight}),r+n[0].offsetHeight-h.scrollTop()>h[0].clientHeight?h.scrollTop(r+n[0].offsetHeight-h.innerHeight()):r<h.scrollTop()&&h.scrollTop(r)}}function m(e){u+=e,u<0?u=o.size()-1:u>=o.size()&&(u=0)}function g(e){return t.max&&t.max<e?t.max:e}function y(){h.empty();var n=g(a.length);for(var r=0;r<n;r++){if(!a[r])continue;var i=t.formatItem(a[r].data,r+1,n,a[r].value,f);if(i===!1)continue;var l=e("<li/>").html(t.highlight(i,f)).addClass(r%2==0?"ac_even":"ac_odd").appendTo(h)[0];e.data(l,"ac_data",a[r])}o=h.find("li"),t.selectFirst&&(o.slice(0,1).addClass(s.ACTIVE),u=0),e.fn.bgiframe&&h.bgiframe()}var s={ACTIVE:"ac_over"},o,u=-1,a,f="",l=!0,c,h;return{display:function(e,t){p(),a=e,f=t,y()},next:function(){v(1)},prev:function(){v(-1)},pageUp:function(){u!=0&&u-8<0?v(-u):v(-8)},pageDown:function(){u!=o.size()-1&&u+8>o.size()?v(o.size()-1-u):v(8)},hide:function(){c&&c.hide(),o&&o.removeClass(s.ACTIVE),u=-1},visible:function(){return c&&c.is(":visible")},current:function(){return this.visible()&&(o.filter("."+s.ACTIVE)[0]||t.selectFirst&&o[0])},show:function(){var r=e(n).offset();c.css({width:typeof t.width=="string"||t.width>0?t.width:e(n).width(),top:r.top+n.offsetHeight,left:r.left}).show();if(t.scroll){h.scrollTop(0),h.css({maxHeight:t.scrollHeight,overflow:"auto"});if(e.browser.msie&&typeof document.body.style.maxHeight=="undefined"){var i=0;o.each(function(){i+=this.offsetHeight});var s=i>t.scrollHeight;h.css("height",s?t.scrollHeight:i),s||o.width(h.width()-parseInt(o.css("padding-left"))-parseInt(o.css("padding-right")))}}},selected:function(){var t=o&&o.filter("."+s.ACTIVE).removeClass(s.ACTIVE);return t&&t.length&&e.data(t[0],"ac_data")},emptyList:function(){h&&h.empty()},unbind:function(){c&&c.remove()}}},e.Autocompleter.Selection=function(e,t,n){if(e.createTextRange){var r=e.createTextRange();r.collapse(!0),r.moveStart("character",t),r.moveEnd("character",n),r.select()}else e.setSelectionRange?e.setSelectionRange(t,n):e.selectionStart&&(e.selectionStart=t,e.selectionEnd=n);e.focus()}}(jQuery),$(document).ready(function(){$("td[@metric]").tooltip({bodyHandler:function(){var e=$(this).attr("type"),t=$(this).attr("metric");if(e=="Parent")return ParentRowToolTip(this,t);if(e=="Child")return ChildRowToolTip(this,t)},showURL:!1});var e={};$.each(location.search.replace("?","").split("&"),function(t,n){var r=n.split("=");e[r[0]]=r[1]}),$("input.function_typeahead").autocomplete("typeahead.php",{extraParams:e}).result(function(t,n){e.symbol=n,location.search="?"+jQuery.param(e)})});
</script>
</body>
</html>
