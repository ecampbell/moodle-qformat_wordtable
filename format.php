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
 * Convert Word tables into Moodle Question XML format
 *
 * The wordtable class inherits from the XML question import class, rather than the
 * default question format class, as this minimises code duplication.
 *
 * This code converts quiz questions between structured Word tables and Moodle
 * Question XML format.
 *
 * The export facility also converts questions into Word files using an XSLT script
 * and an XSLT processor. The Word files are really just XHTML files with some
 * extra markup to get Word to open them and apply styles and formatting properly.
 *
 * @package qformat_wordtable
 * @copyright 2010-2015 Eoin Campbell
 * @author Eoin Campbell
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later (5)
 */


require_once("$CFG->libdir/xmlize.php");
require_once($CFG->dirroot.'/lib/uploadlib.php');

// Development: turn on all debug messages and strict warnings.
// define('DEBUG_WORDTABLE', E_ALL | E_STRICT);
define('DEBUG_WORDTABLE', 0);

// wordtable just extends XML import/export
require_once("$CFG->dirroot/question/format/xml/format.php");

// Include XSLT processor functions
require_once(__DIR__ . "/xsl_emulate_xslt.inc");

class qformat_wordtable extends qformat_xml {

    private $wordfile_template = 'wordfile_template.html';  // XHTML export template with Word-compatible CSS style definitions.
    private $mqxml2word_stylesheet1 = 'mqxml2word_pass1.xsl';      // Stylesheet to export Moodle Question XML into XHTML.
    private $mqxml2word_stylesheet2 = 'mqxml2word_pass2.xsl';      // Stylesheet to export XHTML into Word-compatible XHTML.

    private $word2mqxml_stylesheet1 = 'wordml2xhtml_pass1.xsl';      // Stylesheet to import XHTML into Word-compatible XHTML.
    private $word2mqxml_stylesheet2 = 'wordml2xhtml_pass2.xsl';      // Stylesheet to process XHTML during import.
    private $word2mqxml_stylesheet3 = 'xhtml2mqxml.xsl';      // Stylesheet to import XHTML into question XML.

    public function mime_type() {
        return 'application/vnd.openxmlformats-officedocument.wordprocessingml.document';
    }

    // IMPORT FUNCTIONS START HERE

    /**
     * Perform required pre-processing, i.e. convert Word file into XML
     *
     * Extract the WordProcessingML XML files from the .docx file, and use a sequence of XSLT
     * steps to convert it into Moodle Question XML
     *
     * @return boolean success
     */
    function importpreprocess() {
        global $CFG, $USER, $COURSE, $OUTPUT;
        $realfilename = "";
        $filename = "";

        // Handle question imports in Lesson module by using mform, not the question/format.php qformat_default class
        if(property_exists('qformat_default', 'realfilename')) {
            $realfilename = $this->realfilename;
        } else {
            global $mform;
            $realfilename = $mform->get_new_filename('questionfile');
        }
        if(property_exists('qformat_default', 'filename')) {
            $filename = $this->filename;
        } else {
            global $mform;
            $filename = "{$CFG->tempdir}/questionimport/{$realfilename}";
        }
        debugging(__FUNCTION__ . ":" . __LINE__ . ": Word file = $realfilename; path = $filename", DEBUG_WORDTABLE);
        // Give XSLT as much memory as possible, to enable larger Word files to be imported.
        raise_memory_limit(MEMORY_HUGE);

        // Check that the file is in Word 2010 format, not HTML, XML, or Word 2003
        if((substr($realfilename, -3, 3) == 'doc')) {
            echo $OUTPUT->notification(get_string('docnotsupported', 'qformat_wordtable', $realfilename));
            return false;
        }else if ((substr($realfilename, -3, 3) == 'xml')) {
            echo $OUTPUT->notification(get_string('xmlnotsupported', 'qformat_wordtable', $realfilename));
            return false;
        } else if ((stripos($realfilename, 'htm'))) {
            echo $OUTPUT->notification(get_string('htmlnotsupported', 'qformat_wordtable', $realfilename));
            return false;
        } else if ((stripos(file_get_contents($filename, 0, null, 0, 100), 'html'))) {
            echo $OUTPUT->notification(get_string('htmldocnotsupported', 'qformat_wordtable', $realfilename));
            return false;
        }

        // Stylesheet to convert WordML into initial XHTML format
        $stylesheet = __DIR__ . "/" . $this->word2mqxml_stylesheet1;

        // Check that XSLT is installed, and the XSLT stylesheet is present
        if (!class_exists('XSLTProcessor') || !function_exists('xslt_create')) {
            debugging(__FUNCTION__ . ":" . __LINE__ . ": XSLT not installed", DEBUG_WORDTABLE);
            echo $OUTPUT->notification(get_string('xsltunavailable', 'qformat_wordtable'));
            return false;
        } else if(!file_exists($stylesheet)) {
            // Stylesheet to transform WordML into XHTML doesn't exist
            debugging(__FUNCTION__ . ":" . __LINE__ . ": XSLT stylesheet missing: $stylesheet", DEBUG_WORDTABLE);
            echo $OUTPUT->notification(get_string('stylesheetunavailable', 'qformat_wordtable', $stylesheet));
            return false;
        }

        // Set common parameters for all XSLT transformations. Note that we cannot use arguments because the XSLT processor doesn't support them
        $parameters = array (
            'course_id' => $COURSE->id,
            'course_name' => $COURSE->fullname,
            'author_name' => $USER->firstname . ' ' . $USER->lastname,
            'moodle_country' => $USER->country,
            'moodle_language' => current_language(),
            'moodle_textdirection' => (right_to_left())? 'rtl': 'ltr',
            'moodle_release' => $CFG->release,
            'moodle_url' => $CFG->wwwroot . "/",
            'moodle_username' => $USER->username,
            'pluginname' => 'qformat_wordtable',
            'debug_flag' => DEBUG_WORDTABLE
        );

        // Pre-XSLT conversion preparation - re-package the XML and image content from the .docx Word file into one large XML file, to simplify XSLT processing

        // Initialise an XML string to use as a wrapper around all the XML files
        $xml_declaration = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>';
        $wordmlData = $xml_declaration . "\n<pass1Container>\n";
        $imageString = "";

        // Open the Word 2010 Zip-formatted file and extract the WordProcessingML XML files
        $zfh = zip_open($filename);
        if (is_resource($zfh)) {
            $zip_entry = zip_read($zfh);
            while ($zip_entry) {
                if (zip_entry_open($zfh, $zip_entry, "r")) {
                    $ze_filename = zip_entry_name($zip_entry);
                    $ze_filesize = zip_entry_filesize($zip_entry);
                    // debugging(__FUNCTION__ . ":" . __LINE__ . ": zip_entry_name = $ze_filename, size = $ze_filesize", DEBUG_WORDTABLE);

                    // Look for internal images
                    if (strpos($ze_filename, "media")) {
                        $imageFormat = substr($ze_filename, strrpos($ze_filename, ".") +1);
                        $imageData = zip_entry_read($zip_entry, $ze_filesize);
                        $imageName = basename($ze_filename);
                        $imageSuffix = strtolower(substr(strrchr($ze_filename, "."), 1));
                        // gif, png, jpg and jpeg handled OK, but bmp and other non-Internet formats are not
                        $imageMimeType = "image/";
                        if ($imageSuffix == 'gif' or $imageSuffix == 'png') {
                            $imageMimeType .= $imageSuffix;
                        }
                        if ($imageSuffix == 'jpg' or $imageSuffix == 'jpeg') {
                            $imageMimeType .= "jpeg";
                        }
                        // Handle recognised Internet formats only
                        if ($imageMimeType != '') {
                            // debugging(__FUNCTION__ . ":" . __LINE__ . ": media file name = $ze_filename, imageName = $imageName, imageSuffix = $imageSuffix, imageMimeType = $imageMimeType", DEBUG_WORDTABLE);
                            $imageString .= '<file filename="media/' . $imageName . '" mime-type="' . $imageMimeType . '">' . base64_encode($imageData) . "</file>\n";
                        }
                        else {
                            debugging(__FUNCTION__ . ":" . __LINE__ . ": ignore unsupported media file name $ze_filename, imageName = $imageName, imageSuffix = $imageSuffix, imageMimeType = $imageMimeType", DEBUG_WORDTABLE);
                        }
                    // Look for required XML files
                    } else {
                        // If a required XML file is encountered, read it, wrap it, remove the XML declaration, and add it to the XML string
                        switch ($ze_filename) {
                          case "word/document.xml":
                              $wordmlData .= "<wordmlContainer>" . str_replace($xml_declaration, "", zip_entry_read($zip_entry, $ze_filesize)) . "</wordmlContainer>\n";
                              break;
                          case "docProps/core.xml":
                              $wordmlData .= "<dublinCore>" . str_replace($xml_declaration, "", zip_entry_read($zip_entry, $ze_filesize)) . "</dublinCore>\n";
                              break;
                          case "docProps/custom.xml":
                              $wordmlData .= "<customProps>" . str_replace($xml_declaration, "", zip_entry_read($zip_entry, $ze_filesize)) . "</customProps>\n";
                              break;
                          case "word/styles.xml":
                              $wordmlData .= "<styleMap>" . str_replace($xml_declaration, "", zip_entry_read($zip_entry, $ze_filesize)) . "</styleMap>\n";
                              break;
                          case "word/_rels/document.xml.rels":
                              $wordmlData .= "<documentLinks>" . str_replace($xml_declaration, "", zip_entry_read($zip_entry, $ze_filesize)) . "</documentLinks>\n";
                              break;
                          case "word/footnotes.xml":
                              $wordmlData .= "<footnotesContainer>" . str_replace($xml_declaration, "", zip_entry_read($zip_entry, $ze_filesize)) . "</footnotesContainer>\n";
                              break;
                          case "word/_rels/footnotes.xml.rels":
                              $wordmlData .= "<footnoteLinks>" . str_replace($xml_declaration, zip_entry_read($zip_entry, $ze_filesize), "") . "</footnoteLinks>\n";
                              break;
                          /*
                          case "word/_rels/settings.xml.rels":
                              $wordmlData .= "<settingsLinks>" . str_replace($xml_declaration, "", zip_entry_read($zip_entry, $ze_filesize)) . "</settingsLinks>\n";
                              break;
                          */
                          default:
                              // debugging(__FUNCTION__ . ":" . __LINE__ . ": Ignore $ze_filename", DEBUG_WORDTABLE);
                        }
                    }
                } else { // Can't read the file from the Word .docx file
                    echo $OUTPUT->notification(get_string('cannotreadzippedfile', 'qformat_wordtable', $filename));
                    zip_close($zfh);
                    return false;
                }
                // Get the next file in the Zip package
                $zip_entry = zip_read($zfh);
            }  // End while
            zip_close($zfh);
        } else { // Can't open the Word .docx file for reading
            echo $OUTPUT->notification(get_string('cannotopentempfile', 'qformat_wordtable', $filename));
            $this->debug_unlink($filename);
            return false;
        }

        // Add Base64 images section and close the merged XML file
        $wordmlData .= "<imagesContainer>\n" . $imageString . "</imagesContainer>\n"  . "</pass1Container>";

        // Pass 1 - convert WordML into linear XHTML
        // Create a temporary file to store the merged WordML XML content to transform
        if (!($temp_wordml_filename = tempnam($CFG->dataroot . '/temp/', "w2q-"))) {
            debugging(__FUNCTION__ . ":" . __LINE__ . ": Cannot open temporary file ('$temp_wordml_filename') to store XML", DEBUG_WORDTABLE);
            echo $OUTPUT->notification(get_string('cannotopentempfile', 'qformat_wordtable', $temp_wordml_filename));
            return false;
        }

        // Write the WordML contents to be imported
        if (($nbytes = file_put_contents($temp_wordml_filename, $wordmlData)) == 0) {
            debugging(__FUNCTION__ . ":" . __LINE__ . ": Failed to save XML data to temporary file ('$temp_wordml_filename')", DEBUG_WORDTABLE);
            echo $OUTPUT->notification(get_string('cannotwritetotempfile', 'qformat_wordtable', $temp_wordml_filename . "(" . $nbytes . ")"));
            return false;
        }
        debugging(__FUNCTION__ . ":" . __LINE__ . ": XML data saved to $temp_wordml_filename", DEBUG_WORDTABLE);

        debugging(__FUNCTION__ . ":" . __LINE__ . ": Import XSLT Pass 1 with stylesheet \"" . $stylesheet . "\"", DEBUG_WORDTABLE);
        $xsltproc = xslt_create();
        if(!($xslt_output = xslt_process($xsltproc, $temp_wordml_filename, $stylesheet, null, null, $parameters))) {
            debugging(__FUNCTION__ . ":" . __LINE__ . ": Transformation failed", DEBUG_WORDTABLE);
            echo $OUTPUT->notification(get_string('transformationfailed', 'qformat_wordtable', "(XSLT: " . $stylesheet . "; XML: " . $temp_wordml_filename . ")"));
            $this->debug_unlink($temp_wordml_filename);
            return false;
        }
        $this->debug_unlink($temp_wordml_filename);
        debugging(__FUNCTION__ . ":" . __LINE__ . ": Import XSLT Pass 1 succeeded, XHTML output fragment = " . str_replace("\n", "", substr($xslt_output, 0, 200)), DEBUG_WORDTABLE);
        // Strip out some superfluous namespace declarations on paragraph elements, which Moodle 2.7/2.8 on Windows seems to throw in
        $xslt_output = str_replace('<p xmlns="http://www.w3.org/1999/xhtml"', '<p', $xslt_output);
        $xslt_output = str_replace(' xmlns=""', '', $xslt_output);

        // Write output of Pass 1 to a temporary file, for use in Pass 2
        $temp_xhtml_filename = $CFG->dataroot . '/temp/' . basename($temp_wordml_filename, ".tmp") . ".if1";
        if (($nbytes = file_put_contents($temp_xhtml_filename, $xslt_output )) == 0) {
            debugging(__FUNCTION__ . ":" . __LINE__ . ": Failed to save XHTML data to temporary file ('$temp_xhtml_filename')", DEBUG_WORDTABLE);
            echo $OUTPUT->notification(get_string('cannotwritetotempfile', 'qformat_wordtable', $temp_xhtml_filename . "(" . $nbytes . ")"));
            return false;
        }
        debugging(__FUNCTION__ . ":" . __LINE__ . ": Import Pass 1 output XHTML data saved to $temp_xhtml_filename", DEBUG_WORDTABLE);

        // Pass 2 - tidy up linear XHTML a bit
        // Prepare for Import Pass 2 XSLT transformation
        $stylesheet = __DIR__ . "/" . $this->word2mqxml_stylesheet2;
        debugging(__FUNCTION__ . ":" . __LINE__ . ": Import XSLT Pass 2 with stylesheet \"" . $stylesheet . "\"", DEBUG_WORDTABLE);
        if(!($xslt_output = xslt_process($xsltproc, $temp_xhtml_filename, $stylesheet, null, null, $parameters))) {
            debugging(__FUNCTION__ . ":" . __LINE__ . ": Import Pass 2 Transformation failed", DEBUG_WORDTABLE);
            echo $OUTPUT->notification(get_string('transformationfailed', 'qformat_wordtable', "(XSLT: " . $stylesheet . "; XHTML: " . $temp_xhtml_filename . ")"));
            $this->debug_unlink($temp_xhtml_filename);
            return false;
        }
        $this->debug_unlink($temp_xhtml_filename);
        debugging(__FUNCTION__ . ":" . __LINE__ . ": Import Pass 2 succeeded, XHTML output fragment = " . str_replace("\n", "", substr($xslt_output, 600, 500)), DEBUG_WORDTABLE);

        // Write the Pass 2 XHTML output to a temporary file
        $temp_xhtml_filename = $CFG->dataroot . '/temp/' . basename($temp_wordml_filename, ".tmp") . ".if2";
        if (($nbytes = file_put_contents($temp_xhtml_filename, "<pass3Container>\n" . $xslt_output . $this->get_text_labels() . "\n</pass3Container>")) == 0) {
        // f (($nbytes = file_put_contents($temp_xhtml_filename, $xslt_output)) == 0) {
            debugging(__FUNCTION__ . ":" . __LINE__ . ": Failed to save XHTML data to temporary file ('$temp_xhtml_filename')", DEBUG_WORDTABLE);
            echo $OUTPUT->notification(get_string('cannotwritetotempfile', 'qformat_wordtable', $temp_xhtml_filename . "(" . $nbytes . ")"));
            return false;
        }
        debugging(__FUNCTION__ . ":" . __LINE__ . ": Pass 2 output XHTML data saved to $temp_xhtml_filename", DEBUG_WORDTABLE);
        // file_put_contents($CFG->dataroot . '/temp/' . basename($temp_wordml_filename, ".tmp") . ".if2", "<pass3Container>\n" . $xslt_output . $this->get_text_labels() . "\n</pass3Container>");

        // Pass 3 - convert XHTML into Moodle Question XML
        // Prepare for Import Pass 3 XSLT transformation
        $stylesheet = __DIR__ . "/" . $this->word2mqxml_stylesheet3;
        debugging(__FUNCTION__ . ":" . __LINE__ . ": Import XSLT Pass 3 with stylesheet \"" . $stylesheet . "\"", DEBUG_WORDTABLE);
        if(!($xslt_output = xslt_process($xsltproc, $temp_xhtml_filename, $stylesheet, null, null, $parameters))) {
            debugging(__FUNCTION__ . ":" . __LINE__ . ": Import Pass 3 Transformation failed", DEBUG_WORDTABLE);
            echo $OUTPUT->notification(get_string('transformationfailed', 'qformat_wordtable', "(XSLT: " . $stylesheet . "; XHTML: " . $temp_xhtml_filename . ")"));
            $this->debug_unlink($temp_xhtml_filename);
            return false;
        }
        $this->debug_unlink($temp_xhtml_filename);
        debugging(__FUNCTION__ . ":" . __LINE__ . ": Import Pass 3 succeeded, Moodle Question XML output fragment = " . str_replace("\n", "", substr($xslt_output, 0, 200)), DEBUG_WORDTABLE);

        // Strip out most MathML element and attributes for compatibility with MathJax
        $xslt_output = str_replace('<mml:', '<', $xslt_output);
        $xslt_output = str_replace('</mml:', '</', $xslt_output);
        $xslt_output = str_replace(' mathvariant="normal"', '', $xslt_output);
        $xslt_output = str_replace(' xmlns:mml="http://www.w3.org/1998/Math/MathML"', '', $xslt_output);
        $mml_text_direction_attribute = (right_to_left())? ' dir="rtl"': '';
        $xslt_output = str_replace('<math>', '<math xmlns="http://www.w3.org/1998/Math/MathML"' . $mml_text_direction_attribute  . '>', $xslt_output);

        $temp_mqxml_filename = $CFG->dataroot . '/temp/' . basename($temp_wordml_filename, ".tmp") . ".xml";
        // Write the intermediate (Pass 1) XHTML contents to be transformed in Pass 2, using a temporary XML file, this time including the HTML template too
        if (($nbytes = file_put_contents($temp_mqxml_filename, $xslt_output)) == 0) {
            debugging(__FUNCTION__ . ":" . __LINE__ . ": Failed to save XHTML data to temporary file ('$temp_mqxml_filename')", DEBUG_WORDTABLE);
            echo $OUTPUT->notification(get_string('cannotwritetotempfile', 'qformat_wordtable', $temp_mqxml_filename . "(" . $nbytes . ")"));
            return false;
        }

        // Keep the original Word file for debugging if developer debugging enabled
        if (debugging(null, DEBUG_WORDTABLE)) {
            $copied_input_file = $CFG->dataroot . '/temp/' . basename($temp_wordml_filename, ".tmp") . ".docx";
            copy($filename, $copied_input_file);
            debugging(__FUNCTION__ . ":" . __LINE__ . ": Copied $filename to $copied_input_file", DEBUG_WORDTABLE);
        }

        // Now over-write the original Word file with the XML file, so that default XML file handling will work
        if(($fp = fopen($filename, "wb"))) {
            if(($nbytes = fwrite($fp, $xslt_output)) == 0) {
                echo $OUTPUT->notification(get_string('cannotwritetotempfile', 'qformat_wordtable', $filename));
                return false;
            }
            fclose($fp);
        }

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
        global $CFG, $USER, $COURSE;
        global $OUTPUT;

        debugging(__FUNCTION__ . '($content = "' . str_replace("\n", "", substr($content, 80, 500)) . ' ...")', DEBUG_WORDTABLE);

        // Stylesheet to convert Moodle Question XML into Word-compatible XHTML format
        $stylesheet = __DIR__ . "/" . $this->mqxml2word_stylesheet1;
        // XHTML template for Word file CSS styles formatting
        $htmltemplatefile_path = __DIR__ . "/" . $this->wordfile_template;

        // Check that XSLT is installed, and the XSLT stylesheet and XHTML template are present
        if (!class_exists('XSLTProcessor') || !function_exists('xslt_create')) {
            debugging(__FUNCTION__ . ":" . __LINE__ . ": XSLT not installed", DEBUG_WORDTABLE);
            echo $OUTPUT->notification(get_string('xsltunavailable', 'qformat_wordtable'));
            return false;
        } else if(!file_exists($stylesheet)) {
            // Stylesheet to transform Moodle Question XML into Word doesn't exist
            debugging(__FUNCTION__ . ":" . __LINE__ . ": XSLT stylesheet missing: $stylesheet", DEBUG_WORDTABLE);
            echo $OUTPUT->notification(get_string('stylesheetunavailable', 'qformat_wordtable', $stylesheet));
            return false;
        }

        // Check that there is some content to convert into Word
        if (!strlen($content)) {
            debugging(__FUNCTION__ . ":" . __LINE__ . ": No XML questions in category", DEBUG_WORDTABLE);
            echo $OUTPUT->notification(get_string('noquestions', 'qformat_wordtable'));
            return false;
        }

        debugging(__FUNCTION__ . ":" . __LINE__ . ": preflight checks complete, xmldata length = " . strlen($content), DEBUG_WORDTABLE);

        // Create a temporary file to store the XML content to transform
        if (!($temp_xml_filename = tempnam($CFG->dataroot . '/temp/', "q2w-"))) {
            debugging(__FUNCTION__ . ":" . __LINE__ . ": Cannot open temporary file ('$temp_xml_filename') to store XML", DEBUG_WORDTABLE);
            echo $OUTPUT->notification(get_string('cannotopentempfile', 'qformat_wordtable', $temp_xml_filename));
            return false;
        }

        // Maximise memory available so that very large question banks can be exported
        raise_memory_limit(MEMORY_HUGE);

        $clean_content = $this->clean_all_questions($content);
        // debugging(__FUNCTION__ . ":" . __LINE__ . ": Cleaned Question XML = |" . substr($clean_content, 0, 1000) . " ...|", DEBUG_WORDTABLE);

        // Write the XML contents to be transformed, and also include labels data, to avoid having to use document() inside XSLT
        if (($nbytes = file_put_contents($temp_xml_filename, "<container>\n<quiz>" . $clean_content . "</quiz>\n" . $this->get_text_labels() . "\n</container>")) == 0) {
            debugging(__FUNCTION__ . ":" . __LINE__ . ": Failed to save XML data to temporary file ('$temp_xml_filename')", DEBUG_WORDTABLE);
            echo $OUTPUT->notification(get_string('cannotwritetotempfile', 'qformat_wordtable', $temp_xml_filename . "(" . $nbytes . ")"));
            return false;
        }
        debugging(__FUNCTION__ . ":" . __LINE__ . ": XML data saved to $temp_xml_filename", DEBUG_WORDTABLE);

        // Set parameters for XSLT transformation. Note that we cannot use arguments though.
        $parameters = array (
            'course_id' => $COURSE->id,
            'course_name' => $COURSE->fullname,
            'author_name' => $USER->firstname . ' ' . $USER->lastname,
            'moodle_country' => $USER->country,
            'moodle_language' => current_language(),
            'moodle_textdirection' => (right_to_left())? 'rtl': 'ltr',
            'moodle_release' => $CFG->release,
            'moodle_url' => $CFG->wwwroot . "/",
            'moodle_username' => $USER->username,
            'debug_flag' => debugging('', DEBUG_WORDTABLE),
            'transformationfailed' => get_string('transformationfailed', 'qformat_wordtable', "(XSLT: $this->mqxml2word_stylesheet2)")
        );

        debugging(__FUNCTION__ . ":" . __LINE__ . ": Calling XSLT Pass 1 with stylesheet \"" . $stylesheet . "\"", DEBUG_WORDTABLE);
        $xsltproc = xslt_create();
        if(!($xslt_output = xslt_process($xsltproc, $temp_xml_filename, $stylesheet, null, null, $parameters))) {
            debugging(__FUNCTION__ . ":" . __LINE__ . ": Transformation failed", DEBUG_WORDTABLE);
            echo $OUTPUT->notification(get_string('transformationfailed', 'qformat_wordtable', "(XSLT: " . $stylesheet . "; XML: " . $temp_xml_filename . ")"));
            $this->debug_unlink($temp_xml_filename);
            return false;
        }
        $this->debug_unlink($temp_xml_filename);
        debugging(__FUNCTION__ . ":" . __LINE__ . ": Transformation Pass 1 succeeded, XHTML output fragment = " . str_replace("\n", "", substr($xslt_output, 0, 200)), DEBUG_WORDTABLE);

        $temp_xhtm_filename = $CFG->dataroot . '/temp/' . basename($temp_xml_filename, ".tmp") . ".xhtm";
        // Write the intermediate (Pass 1) XHTML contents to be transformed in Pass 2, this time including the HTML template too.
        if (($nbytes = file_put_contents($temp_xhtm_filename, "<container>\n" . $xslt_output . "\n<htmltemplate>\n" . file_get_contents($htmltemplatefile_path) . "\n</htmltemplate>\n" . $this->get_text_labels() . "\n</container>")) == 0) {
            debugging(__FUNCTION__ . ":" . __LINE__ . ": Failed to save XHTML data to temporary file ('$temp_xhtm_filename')", DEBUG_WORDTABLE);
            echo $OUTPUT->notification(get_string('cannotwritetotempfile', 'qformat_wordtable', $temp_xhtm_filename . "(" . $nbytes . ")"));
            return false;
        }
        debugging(__FUNCTION__ . ":" . __LINE__ . ": Intermediate XHTML data saved to $temp_xhtm_filename", DEBUG_WORDTABLE);

        // Prepare for Pass 2 XSLT transformation.
        $stylesheet = __DIR__ . "/" . $this->mqxml2word_stylesheet2;
        debugging(__FUNCTION__ . ":" . __LINE__ . ": Calling XSLT Pass 2 with stylesheet \"" . $stylesheet . "\"", DEBUG_WORDTABLE);
        if(!($xslt_output = xslt_process($xsltproc, $temp_xhtm_filename, $stylesheet, null, null, $parameters))) {
            debugging(__FUNCTION__ . ":" . __LINE__ . ": Pass 2 Transformation failed", DEBUG_WORDTABLE);
            echo $OUTPUT->notification(get_string('transformationfailed', 'qformat_wordtable', "(XSLT: " . $stylesheet . "; XHTML: " . $temp_xhtm_filename . ")"));
            $this->debug_unlink($temp_xhtm_filename);
            return false;
        }
        debugging(__FUNCTION__ . ":" . __LINE__ . ": Transformation Pass 2 succeeded, HTML output fragment = " . str_replace("\n", "", substr($xslt_output, 400, 100)), DEBUG_WORDTABLE);

        $this->debug_unlink($temp_xhtm_filename);

        // Strip out any redundant namespace attributes, which XSLT on Windows seems to add.
        $xslt_output = str_replace(' xmlns=""', '', $xslt_output);
        $xslt_output = str_replace(' xmlns="http://www.w3.org/1999/xhtml"', '', $xslt_output);

        // Strip off the XML declaration, if present, since Word doesn't like it.
        if (strncasecmp($xslt_output, "<?xml ", 5) == 0) {
            $content = substr($xslt_output, strpos($xslt_output, "\n"));
        } else {
            $content = $xslt_output;
        }

        return $content;
    }   // end presave_process

    /*
     * Delete temporary files if debugging disabled
     */
    private function debug_unlink($filename) {
        if (!debugging(null, DEBUG_WORDTABLE)) {
            unlink($filename);
        }
    }

    /**
     * Get all the text strings needed to fill in the Word file labels in a language-dependent way
     *
     * A string containing XML data, populated from the language folders, is returned
     *
     * @return string
     */
    private function get_text_labels() {

        global $CFG;

        debugging(__FUNCTION__ . "()", DEBUG_WORDTABLE);

        // Release-independent list of all strings required in the XSLT stylesheets for labels etc.
        $textstrings = array(
            'grades' => array('item'),
            'moodle' => array('categoryname', 'no', 'yes', 'feedback', 'format', 'formathtml', 'formatmarkdown', 'formatplain', 'formattext', 'grade', 'question', 'tags'),
            'qformat_wordtable' => array('cloze_instructions', 'cloze_distractor_column_label', 'cloze_feedback_column_label', 'cloze_mcformat_label', 'description_instructions', 'essay_instructions', 'interface_language_mismatch', 'multichoice_instructions', 'truefalse_instructions', 'transformationfailed', 'unsupported_instructions'),
            'qtype_description' => array('pluginnamesummary'),
            'qtype_essay' => array('allowattachments', 'graderinfo', 'formateditor', 'formateditorfilepicker', 'formatmonospaced', 'formatplain', 'pluginnamesummary', 'responsefieldlines', 'responseformat'),
            'qtype_match' => array('filloutthreeqsandtwoas'),
            'qtype_multichoice' => array('answernumbering', 'choiceno', 'correctfeedback', 'incorrectfeedback', 'partiallycorrectfeedback', 'pluginnamesummary', 'shuffleanswers'),
            'qtype_shortanswer' => array('casesensitive', 'filloutoneanswer'),
            'qtype_truefalse' => array('false', 'true'),
            'question' => array('category', 'clearwrongparts', 'defaultmark', 'generalfeedback', 'hintn','penaltyforeachincorrecttry', 'questioncategory','shownumpartscorrect', 'shownumpartscorrectwhenfinished'),
            'quiz' => array('answer', 'answers', 'casesensitive', 'correct', 'correctanswers', 'defaultgrade', 'incorrect', 'shuffle')
            );

        // Append Moodle release-specific text strings, thus avoiding any errors being generated when absent strings are requested
        if ($CFG->release < '2.0') {
            $textstrings['quiz'][] = 'choice';
            $textstrings['quiz'][] = 'penaltyfactor';
        } else if ($CFG->release >= '2.5') {
            $textstrings['qtype_essay'][] = 'responsetemplate';
            $textstrings['qtype_essay'][] = 'responsetemplate_help';
            $textstrings['qtype_match'][] = 'blanksforxmorequestions';
            $textstrings['question'][] = 'addmorechoiceblanks';
            $textstrings['question'][] = 'correctfeedbackdefault';
            $textstrings['question'][] = 'hintnoptions';
            $textstrings['question'][] = 'incorrectfeedbackdefault';
            $textstrings['question'][] = 'partiallycorrectfeedbackdefault';
        }
        if ($CFG->release >= '2.7') {
            $textstrings['qtype_essay'][] = 'attachmentsrequired';
            $textstrings['qtype_essay'][] = 'responserequired';
            $textstrings['qtype_essay'][] = 'responseisrequired';
            $textstrings['qtype_essay'][] = 'responsenotrequired';
        }

        // Add All-or-Nothing MCQ question type strings if present
        $qtype = question_bank::get_qtype('multichoiceset', false);
        if (is_object($qtype) && method_exists($qtype, 'import_from_wordtable')) {
            debugging(__FUNCTION__ . ":" . __LINE__ . ": multichoiceset exists", DEBUG_WORDTABLE);
            $textstrings['qtype_multichoiceset'][] = 'pluginnamesummary';
            $textstrings['qtype_multichoiceset'][] = 'showeachanswerfeedback';
        }

        $expout = "<moodlelabels>\n";
        foreach ($textstrings as $type_group => $group_array) {
            foreach ($group_array as $string_id) {
                $name_string = $type_group . '_' . $string_id;
                $expout .= '<data name="' . $name_string . '"><value>' . get_string($string_id, $type_group) . "</value></data>\n";
            }
        }
        $expout .= "</moodlelabels>";

        return $expout;
    }

    /**
     * Clean HTML markup inside question text element content
     *
     * A string containing Moodle Question XML with clean HTML inside the text elements is returned
     *
     * @return string
     */
    private function clean_all_questions($input_string) {
        debugging(__FUNCTION__ . "(input_string = " . str_replace("\n", "", substr($input_string, 0, 200)) . " ...)", DEBUG_WORDTABLE);
        // Start assembling the cleaned output string, starting with empty
        $clean_output_string = "";

        // Split the string into questions in order to check the text fields for clean HTML
        $found_questions = preg_match_all('~(.*?)<question type="([^"]*)"[^>]*>(.*?)</question>~s', $input_string, $question_matches, PREG_SET_ORDER);
        $n_questions = count($question_matches);
        if ($found_questions === false or $found_questions == 0) {
            debugging(__FUNCTION__ . "() -> Cannot decompose questions", DEBUG_WORDTABLE);
            return $input_string;
        }
        // debugging(__FUNCTION__ . ":" . __LINE__ . ": " . $n_questions . " questions found", DEBUG_WORDTABLE);

        // Split the questions into text strings to check the HTML
        for ($i = 0; $i < $n_questions; $i++) {
            $question_type = $question_matches[$i][2];
            $question_content = $question_matches[$i][3];
            debugging(__FUNCTION__ . ":" . __LINE__ . ": Processing question " . $i . " of $n_questions, type $question_type, question length = " . strlen($question_content), DEBUG_WORDTABLE);
            // Split the question into chunks at CDATA boundaries, using an ungreedy search (?), and matching across newlines (s modifier)
            $found_cdata_sections = preg_match_all('~(.*?)<\!\[CDATA\[(.*?)\]\]>~s', $question_content, $cdata_matches, PREG_SET_ORDER);
            // Has the question been imported using WordTable? If so, assume it is clean and don't process it
            //$imported_from_wordtable = preg_match('~ImportFromWordTable~', $question_content);
            // if ($imported_from_wordtable and $imported_from_wordtable != 0) {
            //    debugging(__FUNCTION__ . ":" . __LINE__ . ": Skip cleaning previously imported question " . $i + 1, DEBUG_WORDTABLE);
            //    $clean_output_string .= $question_matches[$i][0];
            //} else if ($found_cdata_sections === false) {
            if ($found_cdata_sections === false) {
                debugging(__FUNCTION__ . ":" . __LINE__ . ": Cannot decompose CDATA sections in question " . $i + 1, DEBUG_WORDTABLE);
                $clean_output_string .= $question_matches[$i][0];
            } else if ($found_cdata_sections != 0) {
                $n_cdata_sections = count($cdata_matches);
                debugging(__FUNCTION__ . ":" . __LINE__ . ": " . $n_cdata_sections  . " CDATA sections found in question " . $i + 1 . ", question length = " . strlen($question_content), DEBUG_WORDTABLE);
                // Found CDATA sections, so first add the question start tag and then process the body
                $clean_output_string .= '<question type="' . $question_type . '">';

                // Process content of each CDATA section to clean the HTML
                for ($j = 0; $j < $n_cdata_sections; $j++) {
                    $cdata_content = $cdata_matches[$j][2];
                    $clean_cdata_content = $this->clean_html_text($cdata_matches[$j][2]);

                    // Add all the text before the first CDATA start boundary, and the cleaned string, to the output string
                    $clean_output_string .= $cdata_matches[$j][1] . '<![CDATA[' . $clean_cdata_content . ']]>' ;
                } // End CDATA section loop

                // Add the text after the last CDATA section closing delimiter
                $text_after_last_CDATA_string = substr($question_matches[$i][0], strrpos($question_matches[$i][0], "]]>") + 3);
                $clean_output_string .= $text_after_last_CDATA_string;
            } else {
                // debugging(__FUNCTION__ . ":" . __LINE__ . ": No CDATA sections in question " . $i + 1, DEBUG_WORDTABLE);
                $clean_output_string .= $question_matches[$i][0];
            }
        } // End question element loop

        debugging(__FUNCTION__ . "() -> " . str_replace("\n", "", substr($clean_output_string, 0, 200)), DEBUG_WORDTABLE);
        return $clean_output_string;
}

    /**
     * Clean HTML content
     *
     * A string containing clean XHTML is returned
     *
     * @return string
     */
    private function clean_html_text($cdata_string) {
        debugging(__FUNCTION__ . "(cdata_string = \"" . substr($cdata_string, 0, 100) . "\")", DEBUG_WORDTABLE);
        // Wrap the string in a HTML wrapper, load it into a new DOM document as HTML, but save as XML
        $doc = new DOMDocument();
        $doc->loadHTML('<html><body>' . $cdata_string . '</body></html>');
        $doc->getElementsByTagName('html')->item(0)->setAttribute('xmlns','http://www.w3.org/1999/xhtml');
        $xml = $doc->saveXML();
        // debugging(__FUNCTION__ . ":" . __LINE__ . ": xml: |" . str_replace("\n", "", $xml) . "|", DEBUG_WORDTABLE);

        $body_start = stripos($xml, '<body>') + strlen('<body>');
        $body_length = strripos($xml, '</body>') - $body_start;
        // debugging(__FUNCTION__ . ":" . __LINE__ . ": body_start = {$body_start}, body_length = {$body_length}", DEBUG_WORDTABLE);
        if ($body_start || $body_length) {
            $clean_xhtml = substr($xml, $body_start, $body_length);
            debugging(__FUNCTION__ . ":" . __LINE__ . ": clean xhtml: |" . $clean_xhtml . "|", DEBUG_WORDTABLE);
        } else {
            debugging(__FUNCTION__ . "() -> Invalid XHTML, using original cdata string", DEBUG_WORDTABLE);
            $clean_xhtml = $cdata_string;
        }

        // Fix up filenames after @@PLUGINFILE@@ to replace URL-encoded characters with ordinary characters
        $found_pluginfilenames = preg_match_all('~(.*?)<img src="@@PLUGINFILE@@/([^"]*)(.*)~s', $clean_xhtml, $pluginfile_matches, PREG_SET_ORDER);
        $n_matches = count($pluginfile_matches);
        if ($found_pluginfilenames and $found_pluginfilenames != 0) {
            $urldecoded_string = "";
            // Process the possibly-URL-escaped filename so that it matches the name in the file element
            for ($i = 0; $i < $n_matches; $i++) {
                // Decode the filename and add the surrounding text
                $decoded_filename = urldecode($pluginfile_matches[$i][2]);
                $urldecoded_string .= $pluginfile_matches[$i][1] . '<img src="@@PLUGINFILE@@/' . $decoded_filename . $pluginfile_matches[$i][3];
            }
            $clean_xhtml = $urldecoded_string;
        }

        // Strip soft hyphens (0xAD, or decimal 173)
        $clean_xhtml = preg_replace('/\xad/u', '', $clean_xhtml);

        debugging(__FUNCTION__ . "() -> |" . str_replace("\n", "", substr($clean_xhtml, 0, 100)) . " ...|", DEBUG_WORDTABLE);
        return $clean_xhtml;
    }

}
?>
