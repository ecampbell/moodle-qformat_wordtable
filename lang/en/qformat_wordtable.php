<?php
$string['pluginname'] = 'Microsoft Word 2010 table format (wordtable)';
$string['pluginname_help'] = 'This is a front-end for converting Microsoft Word 2010 files into Moodle Question XML format for import, and converting Moodle Question XML format into a format suitable for editing in Microsoft Word.';
$string['pluginname_link'] = 'qformat/wordtable';
$string['wordtable'] = 'Microsoft Word 2010 table format (wordtable)';
$string['wordtable_help'] = 'This is a front-end for converting Microsoft Word 2010 files into Moodle Question XML format for import, and converting Moodle Question XML format into an enhanced XHTML format for exporting into a format suitable for editing in Microsoft Word.';
$string['noquestions'] = 'No questions to export';
$string['tempfile'] = 'Temporary XML file: <b>{$a}</b>';
$string['templateunavailable'] = 'Word-compatible XHTML template <b>{$a}</b> is not available';
$string['xsltunavailable'] = 'You need the XSLT library installed in PHP to save this Word file';
$string['transformationfailed'] = 'XSLT transformation failed (<b>{$a}</b>)';
$string['stylesheetunavailable'] = 'XSLT Stylesheet <b>{$a}</b> is not available';
$string['cannotopentempfile'] = 'Cannot open temporary file <b>{$a}</b>';
$string['cannotreadzippedfile'] = 'Cannot read Zipped file <b>{$a}</b>';
$string['cannotwritetotempfile'] = 'Cannot write to temporary file <b>{$a}</b>';
$string['conversionfailed'] = 'Question import failed';
$string['conversionsucceeded2'] = 'Question import <b>succeeded</b>, <br>click the \'Continue\' button to continue.';
$string['conversionsucceeded'] = 'Question import <b>succeeded</b>, <br>click the <b>\'Close\'</b> button to continue.';
$string['xmlnotsupported'] = 'Files in XML format not supported: <b>{$a}</b>';
$string['docnotsupported'] = 'Files in Word 2003 format not supported: <b>{$a}</b>, use Moodle2Word 2.10 instead';
$string['htmlnotsupported'] = 'Files in HTML format not supported: <b>{$a}</b>';
$string['htmldocnotsupported'] = 'Incorrect Word format: please use <i>File>Save As...</i> to save <b>{$a}</b> in native Word 2010 (.docx) format and import again';
$string['preview_question_not_found'] = 'Preview question not found, name / course ID: {$a}';
$string['export_type_set'] = 'The question export type has been set to ';

$string['cloze_instructions'] = 'Use <strong>bold</strong> for dropdown menu items and <em>italic</em> for text field items.';
$string['cloze_distractor_column_label'] = 'Distractors';
$string['cloze_mcformat_label'] = 'Orientation (D = dropdown; V = vertical, H = horizontal radio buttons):';
$string['description_instructions'] = 'This is not actually a question. Instead it is a way to add some instructions, rubric or other content to the activity. This is similar to the way that labels can be used to add content to the course page.';
$string['essay_instructions'] = 'Allows a response of a few sentences or paragraphs. This must then be graded manually.';
$string['multichoice_instructions'] = 'Allows the selection of a single or multiple responses from a pre-defined list.';
$string['truefalse_instructions'] = 'Set grade \'100\' to the correct answer.';
?>
