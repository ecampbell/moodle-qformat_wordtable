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

// wordtable just extends XML import/export
require_once("$CFG->dirroot/question/format/xml/format.php");

// Include XSLT processor functions
require_once(dirname(__FILE__) . "/xsl_emulate_xslt.inc");

class qformat_wordtable extends qformat_xml {

    private $wordtable_dir = "/question/format/wordtable/"; // Folder where WordTable is installed, relative to Moodle $CFG->dirroot
    private $wordfile_template = 'wordfile_template.html';  // XHTML template file containing Word-compatible CSS style definitions
    private $mqxml2word_stylesheet1 = 'mqxml2word_pass1.xsl';      // XSLT stylesheet containing code to convert Moodle Question XML into XHTML
    private $mqxml2word_stylesheet2 = 'mqxml2word_pass2.xsl';      // XSLT stylesheet containing code to convert XHTML into Word-compatible XHTML for question export
    private $word2mqxml_server_url = 'http://www.yawconline.com/ye_convert1.php'; // URL of external server that does Word to Moodle Question XML conversion for question import

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
        global $CFG, $USER, $COURSE;

        debugging(__FUNCTION__ . "(): Word file = $this->realfilename; path = $this->filename", DEBUG_DEVELOPER);

        // Check that the module is registered, and redirect to registration page if not
        if(!record_exists('config', 'name', 'qformat_wordtable_version')) {
            debugging(__FUNCTION__ . ":" . __LINE__ . ": Account not registered, redirecting to registration page", DEBUG_DEVELOPER);
            notify(get_string('registrationpage', 'qformat_wordtable'));
            $redirect_url = $CFG->wwwroot. $this->wordtable_dir . 'register.php?sesskey=' . $USER->sesskey . "&courseid=" . $this->course->id;
            redirect($redirect_url);
        }

        // Check that the file is in Word 2000/2003 format, not HTML, XML, or Word 2007
        if((substr($this->realfilename, -4, 4) == 'docx')) {
            notify(get_string('docxnotsupported', 'qformat_wordtable', $this->realfilename));
            return false;
        }else if ((substr($this->realfilename, -3, 3) == 'xml')) {
            notify(get_string('xmlnotsupported', 'qformat_wordtable', $this->realfilename));
            return false;
        } else if ((stripos($this->realfilename, 'htm'))) {
            notify(get_string('htmlnotsupported', 'qformat_wordtable', $this->realfilename));
            return false;
        } else if ((stripos(file_get_contents($this->filename, 0, null, 0, 100), 'html'))) {
            notify(get_string('htmldocnotsupported', 'qformat_wordtable', $this->realfilename));
            return false;
        }

        // Temporarily copy the Word file to ensure it has a .doc suffix, which is required by YAWC
        // The uploaded file name may not have a .doc suffix, depending on platform and version
        $temp_doc_filename = $CFG->dataroot . '/temp/' . strval(rand(10, 90)) . "-" . $this->realfilename;
        debugging(__FUNCTION__ . ":" . __LINE__ . ": File OK, copying to $temp_doc_filename", DEBUG_DEVELOPER);
        if (copy($this->filename, $temp_doc_filename)) {
            chmod($temp_doc_filename, 0666);
            clam_log_upload($temp_doc_filename, $COURSE);
            debugging(__FUNCTION__ . ":" . __LINE__ . ": Copy succeeded, $this->filename copied to $temp_doc_filename", DEBUG_DEVELOPER);
        } else {
            notify(get_string("uploadproblem", "", $temp_doc_filename));
        }

        // Get the username and password required for YAWC Online
        $yol_username = get_record('config', 'name', 'qformat_wordtable_username')->value;
        $yol_password = get_record('config', 'name', 'qformat_wordtable_password')->value;

        // Now send the file to YAWC  to convert it into Moodle Question XML inside a Zip file
        $yawc_post_data = array(
            "username" => $yol_username,
            "password" => base64_decode($yol_password),
            "moodle_release" => $CFG->release,
            "downloadZip" => "0",
            "okUpload" => "Convert",
            "docFile" => "@" . $temp_doc_filename
        );
        debugging(__FUNCTION__ . ":" . __LINE__ . ": YAWC POST data: " . print_r($yawc_post_data, true), DEBUG_DEVELOPER);

        // Check that cURL is available
        if (!function_exists('curl_version')) {
            debugging(__FUNCTION__ . ":" . __LINE__ . ": cURL not available", DEBUG_DEVELOPER);
            echo $OUTPUT->notification(get_string('curlunavailable', 'qformat_wordtable'));
            return false;
        }

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $this->word2mqxml_server_url);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $yawc_post_data);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION,1);
        curl_setopt($ch, CURLOPT_HEADER, 0);
        debugging(__FUNCTION__ . ":" . __LINE__ . ": Sending Word file for conversion using cURL", DEBUG_DEVELOPER);
        $yawczipdata = curl_exec($ch);
        // Check if any error occured
        if(curl_errno($ch)) {
            debugging(__FUNCTION__ . ":" . __LINE__ . ": cURL failed with error: " . curl_error($ch), DEBUG_DEVELOPER);
            echo $OUTPUT->notification(get_string('curlerror', 'qformat_wordtable'));
        }
        curl_close ($ch);

        // Delete the temporary Word file once conversion complete
        $this->debug_unlink($temp_doc_filename);

        // Check that a non-zero length file is returned, and the file is a Zip file
        debugging(__FUNCTION__ . ":" . __LINE__ . ": ZIP file data type = " . substr($yawczipdata, 0, 2) . ", length = " . strlen($yawczipdata), DEBUG_DEVELOPER);
        if((strlen($yawczipdata) == 0) || (substr($yawczipdata, 0, 2) !== "PK")) {
            notify(get_string('conversionfailed', 'qformat_wordtable'));
            return false;
        }

        // Save the Zip file to a regular temporary file, so that we can extract its
        // contents using the PHP zip library
        $zipfile = tempnam($CFG->dataroot . '/temp/', "wtz-");
        debugging(__FUNCTION__ . ":" . __LINE__ . ": zip file location = " . $zipfile, DEBUG_DEVELOPER);
        if(($fp = fopen($zipfile, "wb"))) {
            if(($nbytes = fwrite($fp, $yawczipdata)) == 0) {
                notify(get_string('cannotwritetotempfile', 'qformat_wordtable', $zipfile));
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
                    debugging(__FUNCTION__ . ":" . __LINE__ . ": zip_entry_name = $ze_filename, $ze_file_suffix, $ze_filesize", DEBUG_DEVELOPER);
                    if($ze_file_suffix == "xml") {
                        $xmlfile_found = true;
                        // Found the XML file, so grab the data
                        $xmldata = zip_entry_read($zip_entry, $ze_filesize);
                        debugging(__FUNCTION__ . ":" . __LINE__ . ": xmldata length = (" . strlen($xmldata) . ")", DEBUG_DEVELOPER);
                        zip_entry_close($zip_entry);
                        zip_close($zfh);
                        $this->debug_unlink($zipfile);
                    }
                } else {
                    notify(get_string('cannotopentempfile', 'qformat_wordtable', $zipfile));
                    zip_close($zfh);
                    $this->debug_unlink($zipfile);
                    return false;
                }
            }
        } else {
            notify(get_string('cannotopentempfile', 'qformat_wordtable', $zipfile));
            $this->debug_unlink($zipfile);
            return false;
        }


        // Now over-write the original Word file with the XML file, so that default XML file handling will work
        if(($fp = fopen($this->filename, "wb"))) {
            if(($nbytes = fwrite($fp, $xmldata)) == 0) {
                notify(get_string('cannotwritetotempfile', 'qformat_wordtable', $this->filename));
                return false;
            }
            fclose($fp);
        }

        //notify(get_string('conversionsucceeded', 'qformat_wordtable'));
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

        debugging(__FUNCTION__ . '($content = "' . str_replace("\n", "", substr($content, 80, 50)) . ' ...")', DEBUG_DEVELOPER);

        // XSLT stylesheet to convert Moodle Question XML into Word-compatible XHTML format
        $stylesheet =  dirname(__FILE__) . "/" . $this->mqxml2word_stylesheet1;
        // XHTML template for Word file CSS styles formatting
        $htmltemplatefile_path = dirname(__FILE__) . "/" . $this->wordfile_template;

        // Check that XSLT is installed, and the XSLT stylesheet and XHTML template are present
        if (!class_exists('XSLTProcessor') || !function_exists('xslt_create')) {
            debugging(__FUNCTION__ . ":" . __LINE__ . ": XSLT not installed", DEBUG_DEVELOPER);
            notify(get_string('xsltunavailable', 'qformat_wordtable'));
            return false;
        } else if(!file_exists($stylesheet)) {
            // XSLT stylesheet to transform Moodle Question XML into Word doesn't exist
            debugging(__FUNCTION__ . ":" . __LINE__ . ": XSLT stylesheet missing: $stylesheet", DEBUG_DEVELOPER);
            notify(get_string('stylesheetunavailable', 'qformat_wordtable', $stylesheet));
            return false;
        }

        // Check that there is some content to convert into Word
        if (!strlen($content)) {
            debugging(__FUNCTION__ . ":" . __LINE__ . ": No XML questions in category", DEBUG_DEVELOPER);
            notify(get_string('noquestions', 'qformat_wordtable'));
            return false;
        }

        debugging(__FUNCTION__ . ":" . __LINE__ . ": preflight checks complete, xmldata length = " . strlen($content), DEBUG_DEVELOPER);

        // Create a temporary file to store the XML content to transform
        if (!($temp_xml_filename = tempnam($CFG->dataroot . '/temp/', "wt1-"))) {
            debugging(__FUNCTION__ . ":" . __LINE__ . ": Cannot open temporary file ('$temp_xml_filename') to store XML", DEBUG_DEVELOPER);
            notify(get_string('cannotopentempfile', 'qformat_wordtable', $temp_xml_filename));
            return false;
        }

         $clean_content = $this->clean_all_questions($content);
         //debugging(__FUNCTION__ . ":" . __LINE__ . ": Cleaned Question XML = |" . substr($clean_content, 0, 1000) . " ...|", DEBUG_DEVELOPER);

        // Write the XML contents to be transformed, and also include labels data, to avoid having to use document() inside XSLT
        if (($nbytes = file_put_contents($temp_xml_filename, "<container>\n<quiz>" . $clean_content . "</quiz>\n" . $this->get_text_labels() . "\n</container>")) == 0) {
            debugging(__FUNCTION__ . ":" . __LINE__ . ": Failed to save XML data to temporary file ('$temp_xml_filename')", DEBUG_DEVELOPER);
            notify(get_string('cannotwritetotempfile', 'qformat_wordtable', $temp_xml_filename . "(" . $nbytes . ")"));
            return false;
        }
        debugging(__FUNCTION__ . ":" . __LINE__ . ": XML data saved to $temp_xml_filename", DEBUG_DEVELOPER);

        // Get the locale, so we can set the language and locale in Word for better spell-checking
        $locale_country = $CFG->country;
        if (empty($CFG->country) or $CFG->country == 0 or $CFG->country == '0') {
            $admin_user_config = get_admin();
            $locale_country = $admin_user_config->country;
        }
        // Set parameters for XSLT transformation. Note that we cannot use arguments though
        $parameters = array (
            'course_id' => $this->course->id,
            'course_name' => $this->course->fullname,
            'author_name' => $USER->firstname . ' ' . $USER->lastname,
            'moodle_country' => $locale_country,
            'moodle_language' => current_language(),
            'moodle_textdirection' => (right_to_left())? 'rtl': 'ltr',
            'moodle_release' => $CFG->release,
            'moodle_url' => $CFG->wwwroot . "/",
            'moodle_username' => $USER->username,
            'transformationfailed' => get_string('transformationfailed', 'qformat_wordtable', "(XSLT: $this->mqxml2word_stylesheet2)")
        );

        debugging(__FUNCTION__ . ":" . __LINE__ . ": Calling XSLT Pass 1 with stylesheet \"" . $stylesheet . "\"", DEBUG_DEVELOPER);
        $xsltproc = xslt_create();
        if(!($xslt_output = xslt_process($xsltproc, $temp_xml_filename, $stylesheet, null, null, $parameters))) {
            debugging(__FUNCTION__ . ":" . __LINE__ . ": Transformation failed", DEBUG_DEVELOPER);
            notify(get_string('transformationfailed', 'qformat_wordtable', "(XSLT: " . $stylesheet . "; XML: " . $temp_xml_filename . ")"));
            $this->debug_unlink($temp_xml_filename);
            return false;
        }
        $this->debug_unlink($temp_xml_filename);
        debugging(__FUNCTION__ . ":" . __LINE__ . ": Transformation Pass 1 succeeded, XHTML output fragment = " . str_replace("\n", "", substr($xslt_output, 0, 200)), DEBUG_DEVELOPER);

        $temp_xml_filename = tempnam($CFG->dataroot . '/temp/', "wt2-");
        // Write the intermediate (Pass 1) XHTML contents to be transformed in Pass 2, using a temporary XML file, this time including the HTML template too
        if (($nbytes = file_put_contents($temp_xml_filename, "<container>\n" . $xslt_output . "\n<htmltemplate>\n" . file_get_contents($htmltemplatefile_path) . "\n</htmltemplate>\n" . $this->get_text_labels() . "\n</container>")) == 0) {
            debugging(__FUNCTION__ . ":" . __LINE__ . ": Failed to save XHTML data to temporary file ('$temp_xml_filename')", DEBUG_DEVELOPER);
            notify(get_string('cannotwritetotempfile', 'qformat_wordtable', $temp_xml_filename . "(" . $nbytes . ")"));
			return false;
        }
        debugging(__FUNCTION__ . ":" . __LINE__ . ": Intermediate XHTML data saved to $temp_xml_filename", DEBUG_DEVELOPER);

        // Prepare for Pass 2 XSLT transformation
        $stylesheet =  dirname(__FILE__) . "/" . $this->mqxml2word_stylesheet2;
        debugging(__FUNCTION__ . ":" . __LINE__ . ": Calling XSLT Pass 2 with stylesheet \"" . $stylesheet . "\"", DEBUG_DEVELOPER);
        if(!($xslt_output = xslt_process($xsltproc, $temp_xml_filename, $stylesheet, null, null, $parameters))) {
            debugging(__FUNCTION__ . ":" . __LINE__ . ": Pass 2 Transformation failed", DEBUG_DEVELOPER);
            notify(get_string('transformationfailed', 'qformat_wordtable', "(XSLT: " . $stylesheet . "; XHTML: " . $temp_xml_filename . ")"));
            $this->debug_unlink($temp_xml_filename);
            return false;
        }
        debugging(__FUNCTION__ . ":" . __LINE__ . ": Transformation Pass 2 succeeded, HTML output fragment = " . str_replace("\n", "", substr($xslt_output, 400, 100)), DEBUG_DEVELOPER);

        $this->debug_unlink($temp_xml_filename);

        // Strip off the XML declaration, if present, since Word doesn't like it
        //$content = substr($xslt_output, strpos($xslt_output, ">"));
        if (strncasecmp($xslt_output, "<?xml ", 5) == 0) {
            debugging(__FUNCTION__ . ":" . __LINE__ . ": Stripping out XML declaration (line 1)", DEBUG_DEVELOPER);
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
        if (!debugging(null, DEBUG_DEVELOPER)) {
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
        $textstrings = array(
            'assignment' => array('uploaderror', 'uploadafile', 'uploadfiletoobig'),
            'grades' => array('item'),
            'moodle' => array('categoryname', 'no', 'yes', 'feedback', 'format', 'formathtml', 'formatmarkdown', 'formatplain', 'formattext', 'grade', 'question', 'tags', 'uploadserverlimit', 'uploadedfile'),
            'qformat_wordtable' => array('cloze_instructions', 'description_instructions', 'essay_instructions', 'multichoice_instructions', 'truefalse_instructions'),
            'qtype_calculated' => array('addmoreanswerblanks'),
            'qtype_match' => array('blanksforxmorequestions', 'filloutthreeqsandtwoas'),
            'qtype_multichoice' => array('answerhowmany', 'answernumbering', 'answersingleno', 'answersingleyes', 'choiceno', 'correctfeedback', 'fillouttwochoices', 'incorrectfeedback', 'partiallycorrectfeedback', 'shuffleanswers'),
            'qtype_shortanswer' => array('addmoreanswerblanks', 'filloutoneanswer'),
            'qtype_truefalse' => array('false', 'true'),
            'question' => array('addmorechoiceblanks', 'category', 'combinedfeedback', 'defaultmark', 'fillincorrect', 'flagged', 'flagthisquestion', 'incorrect', 'partiallycorrect', 'questions', 'questionx', 'questioncategory', 'questiontext', 'specificfeedback', 'shownumpartscorrect', 'shownumpartscorrectwhenfinished'),
            'quiz' => array('answer', 'answers', 'casesensitive', 'choice', 'correct', 'correctanswers', 'defaultgrade', 'feedback', 'generalfeedback', 'incorrect', 'penaltyfactor', 'shuffle')
            );

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

        debugging(__FUNCTION__ . "(input_string = " . str_replace("\n", "", substr($input_string, 0, 1000)) . " ...)", DEBUG_DEVELOPER);
        // Start assembling the cleaned output string. First add the text before the first question
        $found_category = preg_match('~(.*)<question~s', $input_string, $pre_question_match);
        $clean_output_string =  "";

        // Split the string into questions in order to check the text fields for clean HTML
        $found_questions = preg_match_all('~(.*?)<question type="([^"]*)"[^>]*>(.*?)</question>~s', $input_string, $question_matches, PREG_SET_ORDER);
        $n_questions = count($question_matches);
        if ($found_questions === FALSE or $found_questions == 0) {
            //debugging(__FUNCTION__ . ":" . __LINE__ . ": Cannot decompose questions", DEBUG_DEVELOPER);
            return $input_string;
        }
        //debugging(__FUNCTION__ . ":" . __LINE__ . ": " . $n_questions . " questions found", DEBUG_DEVELOPER);

        // Split the questions into text strings to check the HTML
        for ($i = 0; $i < $n_questions; $i++) {
            //debugging(__FUNCTION__ . ":" . __LINE__ . ": pre-question = |" . $question_matches[$i][1] . "|", DEBUG_DEVELOPER);
            //debugging(__FUNCTION__ . ":" . __LINE__ . ": post-question = |" . $question_matches[$i][4] . "|", DEBUG_DEVELOPER);
            
            $question_type = $question_matches[$i][2];
            if ($question_type === 'category') {
                $clean_output_string .= '<question type="category">' . $question_matches[$i][3] . "</question>";
            } else {
                // Standard question type with text fields, so split the question into chunks at CDATA boundaries, using an ungreedy search (?), and matching across newlines (s modifier)
                $found_text_fields = preg_match_all('~(.*?)<\!\[CDATA\[(.*?)\]\]>~s', $question_matches[$i][3], $cdata_matches, PREG_SET_ORDER);
                $n_text_fields = count($cdata_matches);
                if ($found_text_fields === FALSE or $found_text_fields == 0) {
                    //debugging(__FUNCTION__ . ":" . __LINE__ . ": Cannot decompose text elements in question", DEBUG_DEVELOPER);
                    return $input_string;
                }

                // Before processing what's inside the CDATA section, add the question start tag
                $clean_output_string .= '<question type="' . $question_type . '">';

                // Process content of each CDATA section to clean the HTML
                for ($j = 0; $j < $n_text_fields; $j++) {
                    $cdata_content = $cdata_matches[$j][2];
                    $clean_cdata_content = $this->clean_html_text($cdata_matches[$j][2]);

                    // Add all the text before the first CDATA start boundary, and the cleaned string, to the output string
                    $clean_output_string .= $cdata_matches[$j][1] . '<![CDATA[' . $clean_cdata_content . ']]>' ;
                } // End CDATA section handling

                // Add the text after the last CDATA section closing delimiter
                $text_after_last_CDATA_string = substr($question_matches[$i][0], strrpos($question_matches[$i][0], "]]>") + 3);
                $clean_output_string .= $text_after_last_CDATA_string;

                //$clean_output_string .= $question_matches[$i];
            }

        } // End question element handling

        debugging(__FUNCTION__ . "() -> " . substr($clean_output_string, 0, 1000) . "..." . substr($clean_output_string, -1000), DEBUG_DEVELOPER);
        return $clean_output_string;
}

    /**
     * Clean HTML content
     *
     * A string containing clean XHTML is returned
     *
     * @return string
     */
    private function clean_html_text($text_content_string) {
        $tidy_type = "strip_tags";

        // Check if Tidy extension loaded, and use it to clean the CDATA section if present
        if (extension_loaded('tidy')) {
            // cf. http://tidy.sourceforge.net/docs/quickref.html
            $tidy_type = "tidy";
            $tidy_config = array(
                'bare' => true, // Strip Microsoft Word 2000-specific markup
                'clean' => true, // Replace presentational with structural tags 
                'word-2000' => true, // Strip out other Microsoft Word gunk
                'drop-font-tags' => true, // Discard font
                'drop-proprietary-attributes' => true, // Discard font
                'output-xhtml' => true, // Output XML, to format empty elements properly
                'show-body-only'   => true,
            );
            $clean_html = tidy_repair_string($text_content_string, $tidy_config, 'utf8');
        } else { 
            // Tidy not available, so just strip most HTML tags except character-level markup and table tags
            $clean_html = strip_tags($text_content_string, "<b><br><em><i><img><strong><sub><sup><u><table><tbody><td><th><thead>");

            // The strip_tags function treats empty elements like HTML, not XHTML, so fix <br> and <img src=""> manually (i.e. <br/>, <img/>)
            $clean_html = preg_replace('~<img([^>]*?)/?>~si', '<img$1/>', $clean_html, PREG_SET_ORDER);
            $clean_html = preg_replace('~<br([^>]*?)/?>~si', '<br/>', $clean_html, PREG_SET_ORDER);
        }

        // Fix up filenames after @@PLUGINFILE@@ to replace URL-encoded characters with ordinary characters
        $found_pluginfilenames = preg_match_all('~(.*?)<img src="@@PLUGINFILE@@/([^"]*)(.*)~s', $clean_html, $pluginfile_matches, PREG_SET_ORDER);
        $n_matches = count($pluginfile_matches);
        if ($found_pluginfilenames !== FALSE and $found_pluginfilenames != 0) {
            $urldecoded_string = "";
            // Process the possibly-URL-escaped filename so that it matches the name in the file element
            for ($i = 0; $i < $n_matches; $i++) {
                // Decode the filename and add the surrounding text
                $decoded_filename = urldecode($pluginfile_matches[$i][2]);
                $urldecoded_string .= $pluginfile_matches[$i][1] . '<img src="@@PLUGINFILE@@/' . $decoded_filename . $pluginfile_matches[$i][3];
            }
            $clean_html = $urldecoded_string;
        }

        // Strip soft hyphens (0xAD, or decimal 173)
        $clean_html = preg_replace('/\xad/u', '', $clean_html);

        debugging(__FUNCTION__ . "() [using " . $tidy_type . "] -> |" . $clean_html . "|", DEBUG_DEVELOPER);
        return $clean_html;
    }
}
?>
