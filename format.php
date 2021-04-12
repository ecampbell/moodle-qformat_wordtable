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
require_once($CFG->dirroot . '/mod/book/tool/wordimport/locallib.php');
use \booktool_wordimport\wordconverter;

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
    /** @var string Stylesheet to export Moodle Question XML into XHTML */
    private $mqxml2xhtmlstylesheet = 'mqxml2xhtml.xsl';

    /** @var string Stylesheet to import XHTML into question XML */
    private $xhtml2mqxmlstylesheet = 'xhtml2mqxml.xsl';

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
        global $CFG, $OUTPUT;
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
            echo $OUTPUT->notification(get_string('docnotsupported', 'qformat_wordtable', $baserealfilename));
            return false;
        } else if ((substr($realfilename, -3, 3) == 'xml')) {
            echo $OUTPUT->notification(get_string('xmlnotsupported', 'qformat_wordtable', $baserealfilename));
            return false;
        } else if ((stripos($realfilename, 'htm'))) {
            echo $OUTPUT->notification(get_string('htmlnotsupported', 'qformat_wordtable', $baserealfilename));
            return false;
        } else if ((stripos(file_get_contents($filename, 0, null, 0, 100), 'html'))) {
            echo $OUTPUT->notification(get_string('htmldocnotsupported', 'qformat_wordtable', $baserealfilename));
            return false;
        }


        // Import the Word file into XHTML and an array of images.
        $imagesforzipping = array();
        $word2xml = new wordconverter($this->xsltparameters['pluginname']);
        $word2xml->set_heading1styleoffset($this->xsltparameters['heading1stylelevel']);
        $word2xml->set_imagehandling($this->xsltparameters['imagehandling']);
        $xsltoutput = $word2xml->import($filename, $imagesforzipping);

        // Pass 3 - convert XHTML into Moodle Question XML.
        // Prepare for Import Pass 3 XSLT transformation.
        $stylesheet = __DIR__ . "/" . $this->xhtml2mqxmlstylesheet;
        $xsltoutput = "<pass3Container>\n" . $xsltoutput . $this->get_question_labels() . "\n</pass3Container>";
        $mqxmldata = $word2xml->convert($xsltoutput, $stylesheet, $this->xsltparameters);

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
    public function presave_process( $content ) {
        // Override method to allow us convert to Word-compatible XHTML format.
        global $OUTPUT;

        // Stylesheet to convert Moodle Question XML into XHTML tables.
        $stylesheet = __DIR__ . "/" . $this->mqxml2xhtmlstylesheet;

        // Check that there are questions to convert.
        if (strpos($content, "</question>") === false) {
            echo $OUTPUT->notification(get_string('noquestions', 'qformat_wordtable'));
            return $content;
        }

        // Fields within a question may contain badly formatted HTML inside CDATA sections, so fix them up.
        $cleancontent = $this->clean_all_questions($content);

        // Wrap the Moodle Question XML and the labels data in a single XML container for processing into XHTML tables.
        $moodlelabels = $this->get_question_labels();
        $questionxml = "<container>\n<quiz>" . $cleancontent . "</quiz>\n" . $moodlelabels . "\n</container>";
        $word2xml = new wordconverter($this->xsltparameters['pluginname']);
        $xhtmldata = $word2xml->convert($questionxml, $stylesheet);
        $xhtmldata = "<html><head><title>Fred</title></head><body>" . $word2xml->body_only($xhtmldata) . "</body></html>";

        // Embed the XHTML tables into a Word-compatible template document with styling information, etc.
        $content = $word2xml->export($xhtmldata, 'question', $moodlelabels, 'embedded');
        return $content;
    }   // End presave_process function.

    /**
     * Get the XSLT stylesheet for converting XHTML tables into Moodle Question XML
     *
     * @return string Path to stylesheet
     */
    public function get_import_stylesheet() {
        return __DIR__ . "/" . $this->xhtml2mqxmlstylesheet;
    }

    /**
     * Get the XSLT stylesheet for converting Moodle Question XML into XHTML tables
     *
     * @return string Path to stylesheet
     */
    public function get_export_stylesheet() {
        return __DIR__ . "/" . $this->mqxml2xhtmlstylesheet;
    }

    /**
     * Get the core question text strings needed to fill in table labels
     *
     * A string containing XML data, populated from the language folders, is returned
     *
     * @return string
     */
    public function get_core_question_labels() {
        global $CFG;

        // Release-independent list of all strings required in the XSLT stylesheets for labels etc.
        $textstrings = array(
            'grades' => array('item'),
            'moodle' => array('categoryname', 'no', 'yes', 'feedback', 'format', 'formathtml', 'formatmarkdown',
                            'formatplain', 'formattext', 'grade', 'question', 'tags'),
            'qformat_wordtable' => array('cloze_instructions', 'cloze_distractor_column_label', 'cloze_feedback_column_label',
                            'cloze_mcformat_label', 'description_instructions', 'essay_instructions',
                            'interface_language_mismatch', 'multichoice_instructions', 'truefalse_instructions',
                            'transformationfailed', 'unsupported_instructions'),
            'qtype_description' => array('pluginnamesummary'),
            'qtype_ddimageortext' => array('pluginnamesummary', 'bgimage', 'dropbackground', 'dropzoneheader',
                    'draggableitem', 'infinite', 'label', 'shuffleimages', 'xleft', 'ytop'),
            'qtype_ddmarker' => array('pluginnamesummary', 'bgimage', 'clearwrongparts', 'coords',
                'dropbackground', 'dropzoneheader', 'infinite', 'marker', 'noofdrags', 'shape_circle',
                'shape_polygon', 'shape_rectangle', 'shape', 'showmisplaced', 'stateincorrectlyplaced'),
            'qtype_ddwtos' => array('pluginnamesummary', 'infinite'),
            'qtype_essay' => array('acceptedfiletypes', 'allowattachments', 'attachmentsrequired', 'formatnoinline',
                            'graderinfo', 'formateditor', 'formateditorfilepicker',
                            'formatmonospaced', 'formatplain', 'pluginnamesummary', 'responsefieldlines', 'responseformat',
                            'responseisrequired', 'responsenotrequired',
                            'responserequired', 'responsetemplate', 'responsetemplate_help'),
            'qtype_gapselect' => array('pluginnamesummary', 'errornoslots', 'group', 'shuffle'),
            'qtype_match' => array('blanksforxmorequestions', 'filloutthreeqsandtwoas'),
            'qtype_multichoice' => array('answernumbering', 'choiceno', 'correctfeedback', 'incorrectfeedback',
                            'partiallycorrectfeedback', 'pluginnamesummary', 'shuffleanswers'),
            'qtype_shortanswer' => array('casesensitive', 'filloutoneanswer'),
            'qtype_truefalse' => array('false', 'true'),
            'question' => array('addmorechoiceblanks', 'category', 'clearwrongparts', 'correctfeedbackdefault',
                            'defaultmark', 'generalfeedback', 'hintn', 'hintnoptions',
                            'incorrectfeedbackdefault', 'partiallycorrectfeedbackdefault',
                            'penaltyforeachincorrecttry', 'questioncategory', 'shownumpartscorrect',
                            'shownumpartscorrectwhenfinished'),
            'quiz' => array('answer', 'answers', 'casesensitive', 'correct', 'correctanswers',
                            'defaultgrade', 'incorrect', 'shuffle')
            );

        if ($CFG->release >= '3.6') {
            // Add support for new optional ID number field added in Moodle 3.6.
            $textstrings['question'][] = 'idnumber';
        }

        $expout = "<moodlelabels>\n";
        foreach ($textstrings as $typegroup => $grouparray) {
            foreach ($grouparray as $stringid) {
                $namestring = $typegroup . '_' . $stringid;
                // Clean up question type explanation, in case the default text has been overridden on the site.
                $cleantext = get_string($stringid, $typegroup);
                $expout .= '<data name="' . $namestring . '"><value>' . $cleantext . "</value></data>\n";
            }
        }
        $expout .= "</moodlelabels>";

        // Ensure the XML is well-formed, as the standard clean text strings may have been overwritten on some sites.
        $word2xml = new wordconverter($this->xsltparameters['pluginname']);
        $expout = $word2xml->convert_to_xml($expout);
        $expout = str_replace("<br>", "<br/>", $expout);

        return $expout;
    }

    /**
     * Get the core and contributed question text strings needed to fill in table labels
     *
     * A string containing XML data, populated from the language folders, is returned
     *
     * @return string
     */
    private function get_question_labels() {
        global $CFG;

        // Add All-or-Nothing MCQ question type strings if present.
        if (is_object(question_bank::get_qtype('multichoiceset', false))) {
           $textstrings['qtype_multichoiceset'] = array('pluginnamesummary', 'showeachanswerfeedback');
        }

        // Get the core question labels, and strip out the closing element so more can be added.
        $expout = str_replace("</moodlelabels>", "", get_contributed_question_labels());
        foreach ($textstrings as $typegroup => $grouparray) {
            foreach ($grouparray as $stringid) {
                $namestring = $typegroup . '_' . $stringid;
                // Clean up question type explanation, in case the default text has been overridden on the site.
                $cleantext = get_string($stringid, $typegroup);
                $expout .= '<data name="' . $namestring . '"><value>' . $cleantext . "</value></data>\n";
            }
        }
        $expout .= "</moodlelabels>";
        $word2xml = new wordconverter($this->xsltparameters['pluginname']);
        $expout = $word2xml->convert_to_xml($expout);
        $expout = str_replace("<br>", "<br/>", $expout);

        return $expout;
    }

    /**
     * Clean HTML markup inside question text element content
     *
     * A string containing Moodle Question XML with clean HTML inside the text elements is returned.
     *
     * @param string $questionxmlstring Question XML text
     * @return string
     */
    private function clean_all_questions($questionxmlstring) {
        // Start assembling the cleaned output string, starting with empty.
        $cleanquestionxml = "";
        $word2xml = new wordconverter($this->xsltparameters['pluginname']);

        // Split the string into questions in order to check the text fields for clean HTML.
        $foundquestions = preg_match_all('~(.*?)<question type="([^"]*)"[^>]*>(.*?)</question>~s', $questionxmlstring,
                            $questionmatches, PREG_SET_ORDER);
        $numquestions = count($questionmatches);
        if ($foundquestions === false or $foundquestions == 0) {
            return $questionxmlstring;
        }

        // Split the questions into text strings to check the HTML.
        for ($i = 0; $i < $numquestions; $i++) {
            $qtype = $questionmatches[$i][2];
            $questioncontent = $questionmatches[$i][3];
            // Split the question into chunks at CDATA boundaries, using ungreedy (?) and matching across newlines (s modifier).
            $foundcdatasections = preg_match_all('~(.*?)<\!\[CDATA\[(.*?)\]\]>~s', $questioncontent, $cdatamatches, PREG_SET_ORDER);
            if ($foundcdatasections === false) {
                $cleanquestionxml .= $questionmatches[$i][0];
            } else if ($foundcdatasections != 0) {
                $numcdatasections = count($cdatamatches);
                // Found CDATA sections, so first add the question start tag and then process the body.
                $cleanquestionxml .= '<question type="' . $qtype . '">';

                // Process content of each CDATA section to clean the HTML.
                for ($j = 0; $j < $numcdatasections; $j++) {
                    $cleancdatacontent = $word2xml->clean_html_text($cdatamatches[$j][2]);

                    // Add all the text before the first CDATA start boundary, and the cleaned string, to the output string.
                    $cleanquestionxml .= $cdatamatches[$j][1] . '<![CDATA[' . $cleancdatacontent . ']]>';
                } // End CDATA section loop.

                // Add the text after the last CDATA section closing delimiter.
                $textafterlastcdata = substr($questionmatches[$i][0], strrpos($questionmatches[$i][0], "]]>") + 3);
                $cleanquestionxml .= $textafterlastcdata;
            } else {
                $cleanquestionxml .= $questionmatches[$i][0];
            }
        } // End question element loop.

        return $cleanquestionxml;
    }
}
