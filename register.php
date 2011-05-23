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

// $Id: register.php,v 1.2.2.1 2011/05/23 10:09:14 eoincampbell Exp $

// register.php - allows admin to register account on YAWC Online to enable Word to XML conversion

require_once('../../../config.php');
require_once('register_form.php');
require_once('version.php');
require_once($CFG->libdir.'/dmllib.php');

require_login();

require_capability('moodle/site:config', get_context_instance(CONTEXT_SYSTEM));


$PAGE->set_url('/question/format/wordtable/register.php');
$PAGE->set_pagelayout('admin'); // Set a default pagelayout

if (!$site = get_site()) {
    redirect("index.php");
}

if (!confirm_sesskey()) {
    print_error('confirmsesskeybad', 'error');
}

if (!$admin = get_admin()) {
    print_error("No admins");
}

if (!$admin->country and $CFG->country) {
    $admin->country = $CFG->country;
}

global $DB;
global $OUTPUT;

// Get the course ID so that we can return to the import page after registration
$courseid = optional_param('courseid', 0, PARAM_INT);
$returnurl = $CFG->wwwroot . ($courseid)? '/question/import.php?courseid=' . $courseid : "";

$stradministration = get_string("registration_administration", 'qformat_wordtable');
$strregistration = get_string("registration", 'qformat_wordtable');
$strregistrationinfo = get_string("registrationinfo", 'qformat_wordtable');
$navlinks = array();
$navlinks[] = array('name' => $stradministration, 'link' => "../$CFG->admin/index.php", 'type' => 'misc');
$navlinks[] = array('name' => $strregistration, 'link' => null, 'type' => 'misc');
$navigation = build_navigation($navlinks);
//$PAGE->set_context($PAGE->context);

$thispageurl = new moodle_url('/question/format/wordtable/register.php');
$reg_form = new wordtable_register_form($thispageurl);

// Get the number of users on this site to classify site by size
// Just send the general classification, not the actual number of users
// Administrators can override the value if they want, too
$count = $DB->count_records('user', array('deleted' => 0));
$sizeclass = 0;
if ($count > 500) $sizeclass = 1;
if ($count > 5000) $sizeclass = 2;

$site_defaults = array(
    'sitename' => format_string($site->fullname),
    'yolusername' => "mcq@" . $_SERVER["HTTP_HOST"],
    'courseid' => $courseid,
    'sitesize' => $sizeclass,
    'country' => $admin->country,
    'adminemail' => $admin->email,
    'adminname' => fullname($admin, true),
    'version' => $CFG->version,
    'release' => $CFG->release,
    'mailme' => 1,
    'public' => 2
);
/// Print the form
$PAGE->navbar->add($strregistration);
$PAGE->set_title("$site->shortname: $strregistration");
$PAGE->set_heading($strregistration);
$PAGE->set_cacheable(false);
echo $OUTPUT->header();
//print_header("$site->shortname: $strregistration", $site->fullname, $navigation);
echo $OUTPUT->heading($strregistration);

if ($from_form = $reg_form->get_data()) {
    // Send the data to Moodle2Word website for registration
    $m2w_registration_string = "http://www.moodle2word.net/m2w_register.php?";
    $m2w_registration_string .= "yolusername=" . urlencode($from_form->yolusername);
    $m2w_registration_string .= "&password=" . urlencode($from_form->password);
    $m2w_registration_string .= "&sitename=" . urlencode($from_form->sitename);
    $m2w_registration_string .= "&adminname=" . urlencode($from_form->adminname);
    $m2w_registration_string .= "&adminemail=" . urlencode($from_form->adminemail);
    $m2w_registration_string .= "&public=" . $from_form->public;
    $m2w_registration_string .= "&country=" . $from_form->country;
    $m2w_registration_string .= "&sitesize=" . $from_form->sitesize;
    $m2w_registration_string .= "&mailme=" . $from_form->mailme;
    $m2w_registration_string .= "&version=" . urlencode($from_form->version);
    $m2w_registration_string .= "&lang=" . $from_form->lang;
    $m2w_registration_string .= "&release=" . urlencode($from_form->release);

    //echo $OUTPUT->notification($m2w_registration_string);
    $reg_result = file_get_contents($m2w_registration_string);
    //echo $OUTPUT->notification($reg_result);
    if (!$reg_result || preg_match("/HTTP\/1.0 403 Forbidden/", $reg_result)) {
        echo $OUTPUT->notification(get_string('registrationincomplete', 'qformat_wordtable'));
    } else {
        echo $OUTPUT->notification(get_string('registrationcomplete', 'qformat_wordtable'));
        // Account is added, so safe to store the version, username and password
        set_config('username', $from_form->yolusername, 'qformat_wordtable');
        set_config('password', base64_encode($from_form->password), 'qformat_wordtable');

        // Return to the calling page so that Administrator can continue uploading a Word file
        redirect($returnurl);
    }


    echo $OUTPUT->footer();
} else {
    echo $OUTPUT->box($strregistrationinfo, "center", "70%");
    $reg_form->set_data($site_defaults);
    $reg_form->display();
}

?>
