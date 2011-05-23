<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

// $Id: debug.php,v 1.1.2.1 2011/05/23 10:09:57 eoincampbell Exp $

/**
 * Debug functions to assist testing on remote sites
 *
 * @author Eoin Campbell
 * @package wordtable
 **/

require_once('debug_flag.php');

$wtinstallation_folder = dirname($_SERVER['SCRIPT_FILENAME']) ;
$wtdebug_handle = NULL;


$wtdebug_file = "";

function debug_init($debug_file) {
    global $wtdebug_file, $wtdebug_flag, $wtdebug_handle, $wtinstallation_folder, $CFG;

    $wtdebug_file = $debug_file;

    // Write debug info if a debug file exists
    $debug = file_exists($wtinstallation_folder . "debug.txt");
    if ($debug) {
        if (!$wtdebug_handle) {
            echo $OUTPUT->notification("Debugging file open failed: " . $wtdebug_file . ", using screen\n");
            $wtdebug_flag = $debug;
            return false;
        } else {
            $wtdebug_flag = $debug;
            return $wtdebug_handle;
        }
    }

    return false;
}

function debug_write($string) {
    global $wtdebug_file, $wtdebug_flag, $wtdebug_handle;

    if ($wtdebug_flag) {
        if ($wtdebug_handle) {
            fwrite($wtdebug_handle, $string);
        } else {
            echo $OUTPUT->notification($string);
        }
    }
}

function debug_on() {
    global $wtdebug_file, $wtdebug_flag, $wtdebug_handle;

    return $wtdebug_flag;
}

function debug_unlink($filename) {
    global $wtdebug_file, $wtdebug_flag, $wtdebug_handle;

    debug_write("WTDebug: debug_unlink(filename = " . $filename . ") wtdebug_flag = " . $wtdebug_flag . "\n" );
    if ($wtdebug_flag < 2) {
        unlink($filename);
    } else {
        debug_write("WTDebug: did not delete " . $filename . "\n");
    }
}

function debug_close() {
    global $wtdebug_file, $wtdebug_flag, $wtdebug_handle;

    if ($wtdebug_flag and $wtdebug_handle) {
        fclose($wtdebug_handle);
    }
    return true;
}

?>