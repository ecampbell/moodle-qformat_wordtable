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
 * Short-answer question type upgrade code.
 *
 * @package    qformat_wordtable
 * @copyright  2014 Eoin Campbell
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */


defined('MOODLE_INTERNAL') || die();


/**
 * Upgrade code for the essay question type.
 * @param int $oldversion the version we are upgrading from.
 */
function xmldb_qformat_wordtable_upgrade($oldversion) {

    debugging(__FUNCTION__ . "(oldversion = $oldversion)", DEBUG_DEVELOPER);
    if ($oldversion < 2014051603) {
        debugging(__FUNCTION__ . ":" . __LINE__ . ": Upgrading from $oldversion", DEBUG_DEVELOPER);
        // URL of external server that does Word to Moodle Question XML conversion for question import
        set_config('converter_url', 'http://www.yawconline.com/ye_convert1.php', 'qformat_wordtable');
        // URL of external server for registering a unique login/password if upgrading from free conversion service
        set_config('registration_url', 'http://www1.moodle2word.net/m2w_register.php', 'qformat_wordtable');

        if(!get_config('qformat_wordtable', 'username')) {
            debugging(__FUNCTION__ . ":" . __LINE__ . ": not registered", DEBUG_DEVELOPER);
            // Default username and password for free conversion service
            set_config('username', 'unregistered@doc2mqxml.edu', 'qformat_wordtable');
            set_config('password', base64_encode('5questionlimit'), 'qformat_wordtable');
        }
    }


    return true;
}
