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

/**
 * Post-install script for manual graded question behaviour.
 * @package   qformat_wordtable
 * @copyright 2014 Eoin Campbell
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */


defined('MOODLE_INTERNAL') || die();


/**
 * Post-install script
 */
function xmldb_qformat_wordtable_install() {

    // Add a default account for YAWC Online conversion service to the plugins configuration table
    // Place the YAWC Online web server URL into the table, instead of hardcoding it

    // URL of external server that does Word to Moodle Question XML conversion for question import
    set_config('converter_url', 'http://www.yawconline.com/ye_convert1.php', 'qformat_wordtable');
    // URL of external server for registering a unique login/password if upgrading from free conversion service
    set_config('registration_url', 'http://www1.moodle2word.net/m2w_register.php');
    // Default username and password for free conversion service
    set_config('username', 'unregistered@doc2mqxml.edu', 'qformat_wordtable');
    set_config('password', base64_encode('5questionlimit'), 'qformat_wordtable');
}
