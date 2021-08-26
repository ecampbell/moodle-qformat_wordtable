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
 * @copyright 2010-2021 Eoin Campbell
 * @author Eoin Campbell
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later (5)
 */

defined('MOODLE_INTERNAL') || die();

require_once("$CFG->libdir/xmlize.php");
require_once($CFG->dirroot.'/lib/uploadlib.php');

// Development: turn on all debug messages and strict warnings.
// The wordtable plugin just extends XML import/export.
require_once("$CFG->dirroot/question/format/xml/format.php");

// Include Book tool Word import plugin wordconverter class and utility functions.
use \booktool_wordimport\wordconverter;
use \qformat_wordtable\mqxmlconverter;

/**
 * Importer for Microsoft Word table question format.
 *
 * See {@link https://docs.moodle.org/en/Word_table_format} for a description of the format.
 *
 * @copyright 2010-2021 Eoin Campbell
 * @author Eoin Campbell
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later (5)
 */
class qformat_wordtable extends qformat_xml {
    /** @var array Overrides to default XSLT parameters used for conversion */
    private $xsltparameters = array('pluginname' => 'qformat_wordtable',
            'heading1stylelevel' => 1, // Map "Heading 1" style to <h1> element.
            'imagehandling' => 'embedded' // Embed image data directly into the generated Moodle Question XML.
        );

    /**
     * Define required MIME-Type
     *
     * @return string MIME-Type
     */
    public function mime_type() {
        return 'application/vnd.openxmlformats-officedocument.wordprocessingml.document';
    }

    // IMPORT FUNCTIONS START HERE.

    /**
     * Perform required pre-processing, i.e. convert Word file into Moodle Question XML
     *
     * Extract the WordProcessingML XML files from the .docx file, and use a sequence of XSLT
     * steps to convert it into Moodle Question XML
     *
     * @return bool Success
     */
    public function importpreprocess() {
        global $CFG;
        $realfilename = "";
        $filename = "";

        // Handle question imports in Lesson module by using mform, not the question/format.php qformat_default class.
        if (property_exists('qformat_default', 'realfilename')) {
            $realfilename = $this->realfilename;
        } else {
            global $mform;
            $realfilename = $mform->get_new_filename('questionfile');
        }
        if (property_exists('qformat_default', 'filename')) {
            $filename = $this->filename;
        } else {
            global $mform;
            $filename = "{$CFG->tempdir}/questionimport/{$realfilename}";
        }
        $basefilename = basename($filename);
        $baserealfilename = basename($realfilename);

        // Check that the file is in Word 2010 format, not HTML, XML, or Word 2003.
        if ((substr($realfilename, -3, 3) == 'doc')) {
            throw new \moodle_exception(get_string('docnotsupported', 'qformat_wordtable', $baserealfilename));
            return false;
        } else if ((substr($realfilename, -3, 3) == 'xml')) {
            throw new \moodle_exception(get_string('xmlnotsupported', 'qformat_wordtable', $baserealfilename));
            return false;
        } else if ((stripos($realfilename, 'htm'))) {
            throw new \moodle_exception(get_string('htmlnotsupported', 'qformat_wordtable', $baserealfilename));
            return false;
        } else if ((stripos(file_get_contents($filename, 0, null, 0, 100), 'html'))) {
            throw new \moodle_exception(get_string('htmldocnotsupported', 'qformat_wordtable', $baserealfilename));
            return false;
        }

        // Import the Word file into XHTML and an array of images.
        $imagesforzipping = array();
        $word2xml = new wordconverter($this->xsltparameters['pluginname']);
        $word2xml->set_heading1styleoffset($this->xsltparameters['heading1stylelevel']);
        $word2xml->set_imagehandling($this->xsltparameters['imagehandling']);
        $xhtmldata = $word2xml->import($filename, $imagesforzipping, true);

        // Convert the returned array of images, if any, into a string.
        $imagestring = "";
        foreach ($imagesforzipping as $imagename => $imagedata) {
            $filetype = strtolower(pathinfo($imagename, PATHINFO_EXTENSION));
            $base64data = base64_encode($imagedata);
            $filedata = 'data:image/' . $filetype . ';base64,' . $base64data;
            // Embed the image name and data into the HTML.
            $imagestring .= '<img title="' . $imagename . '" src="' . $filedata . '"/>';
        }

        // Convert XHTML into Moodle Question XML.
        $xhtml2mqxml = new mqxmlconverter($this->xsltparameters['pluginname']);
        $mqxmldata = $xhtml2mqxml->import($xhtmldata, $imagestring, $this->xsltparameters);

        if ((strpos($mqxmldata, "</question>") === false)) {
            throw new \moodle_exception(get_string('noquestionsinfile', 'question'));
        }

        // Now over-write the original Word file with the XML file, so that default XML file handling will work.
        if (($fp = fopen($filename, "wb"))) {
            if (($nbytes = fwrite($fp, $mqxmldata)) == 0) {
                throw new moodle_exception(get_string('cannotwritetotempfile', 'qformat_wordtable', $basefilename));
            }
            fclose($fp);
        }

        return true;
    }   // End importpreprocess function.

    // EXPORT FUNCTIONS START HERE.

    /**
     * Use a .doc file extension when exporting, so that Word is used to open the file
     * @return string file extension
     */
    public function export_file_extension() {
        return ".doc";
    }

    /**
     * Convert the Moodle Question XML into Word-compatible XHTML format
     * just prior to the file being saved
     *
     * Use an XSLT script to do the job, as it is much easier to implement this,
     * and Moodle sites are guaranteed to have an XSLT processor available (I think).
     *
     * @param string $content Question XML text
     * @return string Word-compatible XHTML text
     */
    public function presave_process($content) {
        // Check that there are questions to convert.
        if (strpos($content, "</question>") === false) {
            throw new moodle_exception(get_string('noquestions', 'qformat_wordtable'));
            return $content;
        }

        // Convert the Moodle Question XML into Word-compatible XHTML.
        $mqxml2xhtml = new mqxmlconverter($this->xsltparameters['pluginname']);
        $xhtmldata = $mqxml2xhtml->export($content, $this->xsltparameters['pluginname'], $this->xsltparameters['imagehandling']);
        return $xhtmldata;
    }   // End presave_process function.
}
