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
    private $mqxml2wordstylesheet1 = 'mqxml2wordpass1.xsl';

    /** @var string Stylesheet to import XHTML into question XML */
    private $word2mqxmlstylesheet3 = 'xhtml2mqxml.xsl';

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
        global $CFG, $USER, $COURSE, $OUTPUT;
        $realfilename = "";
        $filename = "";

        // Trace class.
        $trace = new html_progress_trace();

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

        // Set common parameters for all XSLT transformations. Note that the XSLT processor doesn't support $arguments.
        $parameters = array(
            'course_id' => $COURSE->id,
            'course_name' => $COURSE->fullname,
            'author_name' => $USER->firstname . ' ' . $USER->lastname,
            'moodle_country' => $USER->country,
            'moodle_language' => current_language(),
            'moodle_textdirection' => (right_to_left()) ? 'rtl' : 'ltr',
            'moodle_release' => $CFG->release,
            'moodle_url' => $CFG->wwwroot . "/",
            'moodle_username' => $USER->username,
            'username' => $USER->firstname . ' ' . $USER->lastname,
            'pluginname' => 'qformat_wordtable',
            'heading1stylelevel' => '1', // Default HTML heading element level for 'Heading 1' Word style.
            'imagehandling' => 'embedded', // Are images embedded or referenced.
            'debug_flag' => (debugging(null, DEBUG_DEVELOPER)) ? '1' : '0'
            );

        // Import the Word file into XHTML and an array of images.
        $imagesforzipping = array();
        $word2xml = new wordconverter();
        $word2xml->set_heading1styleoffset(1);
        $word2xml->set_imagehandling('embedded');
        $xsltoutput = $word2xml->import($filename, $imagesforzipping);

        // Get a temporary file and store the output.
        if (debugging(null, DEBUG_DEVELOPER)) {
            if (!($tempxmlfilename = tempnam($CFG->tempdir, "wcx")) || (file_put_contents($tempxmlfilename, $xsltoutput)) == 0) {
                throw new \moodle_exception(get_string('cannotopentempfile', 'qformat_wordtable', $tempxmlfilename));
            }
            $trace->output("file: $tempxmlfilename", 0);

        }
        // Pass 3 - convert XHTML into Moodle Question XML.
        // Prepare for Import Pass 3 XSLT transformation.
        $stylesheet = __DIR__ . "/" . $this->word2mqxmlstylesheet3;
        $xsltoutput = "<pass3Container>\n" . $xsltoutput . $this->get_text_labels() . "\n</pass3Container>";
        $mqxmldata = $word2xml->convert($xsltoutput, $stylesheet, $parameters);

        if ((strpos($mqxmldata, "</question>") === false)) {
            throw new \moodle_exception(get_string('noquestionsinfile', 'question'));
        }
        if (debugging(null, DEBUG_DEVELOPER)) {
            $trace->output("Question XML: " . substr($mqxmldata, 0, 1000), 0);
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
        global $CFG, $USER, $COURSE;
        global $OUTPUT;

        // Stylesheet to convert Moodle Question XML into Word-compatible XHTML format.
        $stylesheet = __DIR__ . "/" . $this->mqxml2wordstylesheet1;

        // Check that there is some content to convert into Word.
        if (!strlen($content)) {
            echo $OUTPUT->notification(get_string('noquestions', 'qformat_wordtable'));
        }

        $cleancontent = $this->clean_all_questions($content);

        // Set parameters for XSLT transformation. Note that we cannot use $arguments though.
        $parameters = array (
            'course_id' => $COURSE->id,
            'course_name' => $COURSE->fullname,
            'author_name' => $USER->firstname . ' ' . $USER->lastname,
            'moodle_country' => $USER->country,
            'moodle_language' => current_language(),
            'moodle_textdirection' => (right_to_left()) ? 'rtl' : 'ltr',
            'moodle_release' => $CFG->release,
            'moodle_url' => $CFG->wwwroot . "/",
            'moodle_username' => $USER->username,
            'debug_flag' => (debugging(null, DEBUG_DEVELOPER)) ? '1' : '0'
        );

        // Wrap the Moodle Question XML output in a container, along with the labels data, to get initial the XHTML.
        $questionxml = "<container>\n<quiz>" . $cleancontent . "</quiz>\n" . $this->get_text_labels() . "\n</container>";
        $word2xml = new wordconverter();
        $xhtmldata = $word2xml->convert($questionxml, $stylesheet, $parameters);

        // Now embed the the XHTML into a Word-compatible template.
        $content = $word2xml->export($xhtmldata, 'question', $this->get_text_labels(), 'embedded');

        return $content;
    }   // End presave_process function.

    /**
     * Get all the text strings needed to fill in the Word file labels in a language-dependent way
     *
     * A string containing XML data, populated from the language folders, is returned
     *
     * @return string
     */
    private function get_text_labels() {
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

        // Add All-or-Nothing MCQ question type strings if present.
        if (question_bank::is_qtype_installed('multichoiceset')) {
            $textstrings['qtype_multichoiceset'] = array('pluginnamesummary', 'showeachanswerfeedback');
        }

        $word2xml = new wordconverter();
        $expout = "<moodlelabels>\n";
        foreach ($textstrings as $typegroup => $grouparray) {
            foreach ($grouparray as $stringid) {
                $namestring = $typegroup . '_' . $stringid;
                // Clean up question type explanation, in case the default text has been overridden on the site.
                $cleantext = $word2xml->convert_to_xml(get_string($stringid, $typegroup));
                $expout .= '<data name="' . $namestring . '"><value>' . $cleantext . "</value></data>\n";
            }
        }
        $expout .= "</moodlelabels>";
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
        $word2xml = new wordconverter();

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
