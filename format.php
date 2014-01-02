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

// $Id: format.php,v 1.3.2.2 2011/06/06 19:17:32 eoincampbell Exp $

/**
 * Convert Word tables into Moodle Question XML format
 *
 * The wordtable class inherits from the XML question import class, rather than the
 * default question format class, as this minimises code duplication.
 *
 * This code converts quiz questions between structured Word tables and Moodle
 * Question XML format. The import facility converts Word files into XML
 * by using YAWC Online (www.yawconline.com), a Word to XML conversion service,
 * to convert the Word file into the Moodle Question XML vocabulary.
 *
 * The export facility also converts questions into Word files using an XSLT script
 * and an XSLT processor. The Word files are really just XHTML files with some
 * extra markup to get Word to open them and apply styles and formatting properly.
 *
 * @package questionbank
 * @subpackage importexport
 * @copyright 2010 Eoin Campbell
 * @author Eoin Campbell
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later (5)
 */


require_once("$CFG->libdir/xmlize.php");
require_once($CFG->dirroot.'/lib/uploadlib.php');

// wordtable just extends XML import/export
require_once("$CFG->dirroot/question/format/xml/format.php");

// Include XSLT processor functions
require_once("$CFG->dirroot/question/format/wordtable/xsl_emulate_xslt.inc");

class qformat_wordtable extends qformat_xml {

    private $wordtable_dir = "/question/format/wordtable/"; // Folder where WordTable is installed
    private $wordfile_template = 'wordfile_template.html';  // XHTML template file containing Word-compatible CSS style definitions
    private $mqxml2word_stylesheet1 = 'mqxml2word_pass1.xsl';      // XSLT stylesheet containing code to convert Moodle Question XML into XHTML
    private $mqxml2word_stylesheet2 = 'mqxml2word_pass2.xsl';      // XSLT stylesheet containing code to convert XHTML into Word-compatible XHTML for question export
    private $word2mqxml_server_url = 'http://www.yawconline.com/ye_convert1.php'; // URL of external server that does Word to Moodle Question XML conversion for question import

    public function mime_type() {
        return 'application/msword';
    }

    // IMPORT FUNCTIONS START HERE

    /**
     * Perform required pre-processing, i.e. convert Word file into XML
     *
     * Send the Word file to YAWC Online for conversion into XML, using CURL
     * functions. First check that the file has the right suffix (.doc) and format
     * (binary Word 2003) required by YAWC.
     *
     * A Zip file containing the Question XML file is returned, and this XML file content
     * is overwritten into the input file, so that the later steps just process the XML
     * in the normal way.
     *
     * @return boolean success
     */
    function importpreprocess() {
        global $CFG, $USER, $COURSE, $OUTPUT;
        // declare empty array to prevent each debug message from including a complete backtrace
        $backtrace = array();

        // Use the default Moodle temporary folder to store temporary files
        $tmpdir = $CFG->dataroot . '/temp/';
        debugging("importpreprocess(): Word file = $this->realfilename; path = $this->filename", DEBUG_DEVELOPER, $backtrace);

        // Check that the module is registered, and redirect to registration page if not
        if(!get_config('qformat_wordtable', 'username')) {
            debugging("importpreprocess(): Account not registered, redirecting to registration page", DEBUG_DEVELOPER, $backtrace);
            echo $OUTPUT->notification(get_string('registrationpage', 'qformat_wordtable'));
            $redirect_url = $CFG->wwwroot. $this->wordtable_dir . 'register.php?sesskey=' . $USER->sesskey . "&courseid=" . $this->course->id;
            redirect($redirect_url);
        }

        // Check that the file is in Word 2000/2003 format, not HTML, XML, or Word 2007
        if((substr($this->realfilename, -4, 4) == 'docx')) {
            echo $OUTPUT->notification(get_string('docxnotsupported', 'qformat_wordtable', $this->realfilename));
            return false;
        }else if ((substr($this->realfilename, -3, 3) == 'xml')) {
            echo $OUTPUT->notification(get_string('xmlnotsupported', 'qformat_wordtable', $this->realfilename));
            return false;
        } else if ((stripos($this->realfilename, 'htm'))) {
            echo $OUTPUT->notification(get_string('htmlnotsupported', 'qformat_wordtable', $this->realfilename));
            return false;
        } else if ((stripos(file_get_contents($this->filename, 0, null, 0, 100), 'html'))) {
            echo $OUTPUT->notification(get_string('htmldocnotsupported', 'qformat_wordtable', $this->realfilename));
            return false;
        }

        // Temporarily copy the Word file so it has a .doc suffix, which is required by YAWC
        // The uploaded file name has no suffix by default
        $temp_doc_filename = $tmpdir . clean_filename(basename($this->filename)) . "-" . $this->realfilename;
        debugging("importpreprocess(): File OK, copying to $temp_doc_filename", DEBUG_DEVELOPER, $backtrace);
        if (copy($this->filename, $temp_doc_filename)) {
            chmod($temp_doc_filename, 0666);
            clam_log_upload($temp_doc_filename, $COURSE);
            debugging("importpreprocess(): Copy succeeded, $this->filename copied to $temp_doc_filename", DEBUG_DEVELOPER, $backtrace);
        } else {
            echo $OUTPUT->notification(get_string("uploadproblem", "", $temp_doc_filename));
        }

        // Get the username and password required for YAWC Online
        $yol_username = get_config('qformat_wordtable', 'username');
        $yol_password = base64_decode(get_config('qformat_wordtable', 'password'));

        // Now send the file to YAWC  to convert it into Moodle Question XML inside a Zip file
        $yawc_post_data = array(
            "username" => $yol_username,
            "password" => $yol_password,
            "moodle_release" => $CFG->release,
            "downloadZip" => "0",
            "okUpload" => "Convert",
            "docFile" => "@" . $temp_doc_filename
        );
        debugging("importpreprocess(): YAWC POST data: " . print_r($yawc_post_data, true), DEBUG_DEVELOPER, $backtrace);

        // Check that cURL is available
        if (!function_exists('curl_version')) {
            debugging("importpreprocess(): cURL not available", DEBUG_DEVELOPER, $backtrace);
            echo $OUTPUT->notification(get_string('curlunavailable', 'qformat_wordtable', $zipfile));
            return false;
        }

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $this->word2mqxml_server_url);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $yawc_post_data);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION,1);
        debugging("importpreprocess(): Sending Word file for conversion using cURL", DEBUG_DEVELOPER, $backtrace);
        $yawczipdata = curl_exec($ch);
        // Check if any error occured
        if(curl_errno($ch)) {
            debugging("importpreprocess(): cURL failed with error: " . curl_error($ch), DEBUG_DEVELOPER, $backtrace);
            echo $OUTPUT->notification(get_string('curlerror', 'qformat_wordtable'));
        }
        curl_close ($ch);

        // Delete the temporary Word file once conversion complete
        debug_unlink($temp_doc_filename);

        // Check that a non-zero length file is returned, and the file is a Zip file
        debugging("importpreprocess(): ZIP file data type = " . substr($yawczipdata, 0, 2) . ", length = " . strlen($yawczipdata), DEBUG_DEVELOPER, $backtrace);
        if((strlen($yawczipdata) == 0) || (substr($yawczipdata, 0, 2) !== "PK")) {
            echo $OUTPUT->notification(get_string('conversionfailed', 'qformat_wordtable'));
            return false;
        }

        // Save the Zip file to a regular temporary file, so that we can extract its
        // contents using the PHP zip library
        $zipfile = tempnam($tmpdir, "wt-");
        debugging("importpreprocess(): zip file location = " . $zipfile, DEBUG_DEVELOPER, $backtrace);
        if(($fp = fopen($zipfile, "wb"))) {
            if(($nbytes = fwrite($fp, $yawczipdata)) == 0) {
                echo $OUTPUT->notification(get_string('cannotwritetotempfile', 'qformat_wordtable', $zipfile));
                return false;
            }
            fclose($fp);
        }

        // Open the Zip file and extract the Moodle Question XML file data
        $zfh = zip_open($zipfile);
        if ($zfh) {
            $xmlfile_found = false;
            while (!$xmlfile_found) {
                $zip_entry = zip_read($zfh);
                if (zip_entry_open($zfh, $zip_entry, "r")) {
                    $ze_filename = zip_entry_name($zip_entry);
                    $ze_file_suffix = substr($ze_filename, -3, 3);
                    $ze_filesize = zip_entry_filesize($zip_entry);
                    debugging("importpreprocess(): zip_entry_name = $ze_filename, $ze_file_suffix, $ze_filesize", DEBUG_DEVELOPER, $backtrace);
                    if($ze_file_suffix == "xml") {
                        $xmlfile_found = true;
                        // Found the XML file, so grab the data
                        $xmldata = zip_entry_read($zip_entry, $ze_filesize);
                        debugging("importpreprocess(): xmldata length = (" . strlen($xmldata) . ")", DEBUG_DEVELOPER, $backtrace);
                        zip_entry_close($zip_entry);
                        zip_close($zfh);
                        debug_unlink($zipfile);
                    }
                } else {
                    echo $OUTPUT->notification(get_string('cannotopentempfile', 'qformat_wordtable', $zipfile));
                    zip_close($zfh);
                    debug_unlink($zipfile);
                    return false;
                }
            }
        } else {
            echo $OUTPUT->notification(get_string('cannotopentempfile', 'qformat_wordtable', $zipfile));
            debug_unlink($zipfile);
            return false;
        }


        // Now over-write the original Word file with the XML file, so that default XML file handling will work
        if(($fp = fopen($this->filename, "wb"))) {
            if(($nbytes = fwrite($fp, $xmldata)) == 0) {
                echo $OUTPUT->notification(get_string('cannotwritetotempfile', 'qformat_wordtable', $this->filename));
                return false;
            }
            fclose($fp);
        }

        //echo $OUTPUT->notification(get_string('conversionsucceeded', 'qformat_wordtable'));
        return true;
    }   // end importpreprocess


    // EXPORT FUNCTIONS START HERE

    /**
     * Use a .doc file extension when exporting, so that Word is used to open the file
     * @return string file extension
     */
    function export_file_extension() {
        return ".doc";
    }


    /**
     * Convert the Moodle Question XML into Word-compatible XHTML format
     * just prior to the file being saved
     *
     * Use an XSLT script to do the job, as it is much easier to implement this,
     * and Moodle sites are guaranteed to have an XSLT processor available (I think).
     *
     * @param string  $content Question XML text
     * @return string Word-compatible XHTML text
     */
    function presave_process( $content ) {
        // override method to allow us convert to Word-compatible XHTML format
        global $CFG, $USER;
        global $OUTPUT;
        // declare empty array to prevent each debug message from including a complete backtrace
        $backtrace = array();

        debugging("presave_process(content = " . str_replace("\n", " ", substr($content, 80, 50)) . "):", DEBUG_DEVELOPER, $backtrace);

        // XSLT stylesheet to convert Moodle Question XML into Word-compatible XHTML format
        $stylesheet =  $CFG->dirroot . $this->wordtable_dir . $this->mqxml2word_stylesheet1;
        // XHTML template for Word file CSS styles formatting
        $htmltemplatefile_url = $CFG->wwwroot . $this->wordtable_dir . $this->wordfile_template;

        // Check that XSLT is installed, and the XSLT stylesheet and XHTML template are present
        if (!class_exists('XSLTProcessor') || !function_exists('xslt_create')) {
            debugging("presave_process(): XSLT not installed", DEBUG_DEVELOPER, $backtrace);
            echo $OUTPUT->notification(get_string('xsltunavailable', 'qformat_wordtable'));
            return false;
        } else if(!file_exists($stylesheet)) {
            // XSLT stylesheet to transform Moodle Question XML into Word doesn't exist
            debugging("presave_process(): XSLT stylesheet missing: $stylesheet", DEBUG_DEVELOPER, $backtrace);
            echo $OUTPUT->notification(get_string('stylesheetunavailable', 'qformat_wordtable', $stylesheet));
            return false;
        } else if(!file_get_contents($htmltemplatefile_url)) {
            // Word-compatible XHTML template doesn't exist, or is not accessible via HTTP call
            debugging("presave_process(): XHTML template missing: $htmltemplatefile_url", DEBUG_DEVELOPER, $backtrace);
            echo $OUTPUT->notification(get_string('templateunavailable', 'qformat_wordtable', $htmltemplatefile_url));
            return false;
        }

        // Check that there is some content to convert into Word
        if (!strlen($content)) {
            debugging("presave_process(): No XML questions in category", DEBUG_DEVELOPER, $backtrace);
            echo $OUTPUT->notification(get_string('noquestions', 'qformat_wordtable'));
            return false;
        }

        debugging("presave_process(): preflight checks complete, xmldata length = " . strlen($content), DEBUG_DEVELOPER, $backtrace);

        // Create a temporary file to store the XML content to transform
        if (!($temp_xml_filename = tempnam($CFG->dataroot . "/temp/", "m2w-"))) {
            debugging("presave_process(): Cannot open temporary file ('$temp_xml_filename') to store XML", DEBUG_DEVELOPER, $backtrace);
            echo $OUTPUT->notification(get_string('cannotopentempfile', 'qformat_wordtable', $temp_xml_filename));
            return false;
        }

        // Write the XML contents to be transformed
        if (($nbytes = file_put_contents($temp_xml_filename, "<quiz>" . $content . "</quiz>")) == 0) {
            debugging("presave_process(): Failed to save XML data to temporary file ('$temp_xml_filename')", DEBUG_DEVELOPER, $backtrace);
            echo $OUTPUT->notification(get_string('cannotwritetotempfile', 'qformat_wordtable', $temp_xml_filename . "(" . $nbytes . ")"));
            return false;
        }

        debugging("presave_process(): XML data saved to $temp_xml_filename", DEBUG_DEVELOPER, $backtrace);
        // Set parameters for XSLT transformation. Note that we cannot use arguments though
        // Use a web URL for the template name, to avoid problems with a Windows-style path
        $parameters = array (
            'htmltemplatefile' => $htmltemplatefile_url,
            'course_id' => $this->course->id,
            'course_name' => $this->course->fullname,
            'author_name' => $USER->firstname . ' ' . $USER->lastname,
            'moodle_url' => $CFG->wwwroot . "/",
            'moodle_release' => $CFG->release
        );

        debugging("presave_process(): Calling XSLT Pass 1 with stylesheet \"" . $stylesheet . "\" and template \"" . $parameters['htmltemplatefile'] . "\"", DEBUG_DEVELOPER, $backtrace);
        $xsltproc = xslt_create();
        if(!($xslt_output = xslt_process($xsltproc, $temp_xml_filename, $stylesheet, null, null, $parameters))) {
            debugging("presave_process(): Transformation failed", DEBUG_DEVELOPER, $backtrace);
            echo $OUTPUT->notification(get_string('transformationfailed', 'qformat_wordtable', "(XSLT: " . $stylesheet . "; XML: " . $temp_xml_filename . ")"));
            debug_unlink($temp_xml_filename);
            return false;
        }
        debugging("presave_process(): Transformation Pass 1 succeeded, XHTML output fragment = " . str_replace("\n", "", substr($xslt_output, 400, 100)), DEBUG_DEVELOPER, $backtrace);

        // Write the intermediate (Pass 1) XHTML contents to be transformed in Pass 2, re-using the temporary XML file
        if (($nbytes = file_put_contents($temp_xml_filename, $xslt_output)) == 0) {
            debugging("presave_process(): Failed to save XHTML data to temporary file ('$temp_xml_filename')", DEBUG_DEVELOPER, $backtrace);
            echo $OUTPUT->notification(get_string('cannotwritetotempfile', 'qformat_wordtable', $temp_xml_filename . "(" . $nbytes . ")"));
            return false;
        }
        debugging("presave_process(): Intermediate XHTML data saved to $temp_xml_filename", DEBUG_DEVELOPER, $backtrace);

        // Prepare for Pass 2 XSLT transformation
        $stylesheet =  $CFG->dirroot . $this->wordtable_dir . $this->mqxml2word_stylesheet2;
        debugging("presave_process(): Calling XSLT Pass 1 with stylesheet \"" . $stylesheet . "\" and template \"" . $parameters['htmltemplatefile'] . "\"", DEBUG_DEVELOPER, $backtrace);
        if(!($xslt_output = xslt_process($xsltproc, $temp_xml_filename, $stylesheet, null, null, $parameters))) {
            debugging("presave_process(): Pass 2 Transformation failed", DEBUG_DEVELOPER, $backtrace);
            echo $OUTPUT->notification(get_string('transformationfailed', 'qformat_wordtable', "(XSLT: " . $stylesheet . "; XHTML: " . $temp_xml_filename . ")"));
            debug_unlink($temp_xml_filename);
            return false;
        }
        debugging("presave_process(): Transformation Pass 2 succeeded, HTML output fragment = " . str_replace("\n", "", substr($xslt_output, 400, 100)), DEBUG_DEVELOPER, $backtrace);

        debug_unlink($temp_xml_filename);

        // Strip off the XML declaration, if present, since Word doesn't like it
        //$content = substr($xslt_output, strpos($xslt_output, ">"));
        if (strncasecmp($xslt_output, "<?xml ", 5) == 0) {
            debugging("presave_process(): Stripping out XML declaration (line 1)", DEBUG_DEVELOPER, $backtrace);
            $content = substr($xslt_output, strpos($xslt_output, "\n"));
        } else {
            $content = $xslt_output;
        }

        return $content;
    }   // end presave_process

}

function debug_unlink($filename) {
    // declare empty array to prevent each debug message from including a complete backtrace
    $backtrace = array();

    debugging("debug_unlink(\"" . $filename . "\")", DEBUG_DEVELOPER, $backtrace);
    if (!debugging()) {
        unlink($filename);
    }
}
?>

