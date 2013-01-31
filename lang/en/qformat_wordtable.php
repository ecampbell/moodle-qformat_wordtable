<?php
$string['pluginname'] = 'Microsoft Word table format (wordtable)';
$string['pluginname_help'] = 'This is a front-end for converting Microsoft Word 2003 binary format into Moodle Question XML format for import, and converting Moodle Question XML format into an enhanced XHTML format for exporting into a format suitable for editing in Microsoft Word.';
$string['pluginname_link'] = 'qformat/wordtable';
$string['wordtable'] = 'Microsoft Word table format (wordtable)';
$string['wordtable_help'] = 'This is a front-end for converting Microsoft Word 2003 binary format into Moodle Question XML format for import, and converting Moodle Question XML format into an enhanced XHTML format for exporting into a format suitable for editing in Microsoft Word.';
$string['noquestions'] = 'No questions to export';
$string['tempfile'] = 'Temporary XML file: <b>{$a}</b>';
$string['templateunavailable'] = 'Word-compatible XHTML template <b>{$a}</b> is not available';
$string['xsltunavailable'] = 'You need the XSLT library installed in PHP to save this Word file';
$string['curlunavailable'] = 'You need the cURL library installed in PHP to import this Word file';
$string['curlerror'] = 'cURL failed: Word file not converted into XML. Network/firewall problem?';
$string['transformationfailed'] = 'XSLT transformation failed (<b>{$a}</b>)';
$string['stylesheetunavailable'] = 'XSLT Stylesheet <b>{$a}</b> is not available';
$string['cannotopentempfile'] = 'Cannot open temporary file <b>{$a}</b>';
$string['cannotwritetotempfile'] = 'Cannot write to temporary file <b>{$a}</b>';
$string['conversionfailed'] = 'Question import failed';
$string['conversionsucceeded2'] = 'Question import <b>succeeded</b>, <br>click the \'Continue\' button to continue.';
$string['conversionsucceeded'] = 'Question import <b>succeeded</b>, <br>click the <b>\'Close\'</b> button to continue.';
$string['xmlnotsupported'] = 'Files in XML format not supported: <b>{$a}</b>';
$string['docxnotsupported'] = 'Files in Word 2007 format not supported: <b>{$a}</b>';
$string['htmlnotsupported'] = 'Files in HTML format not supported: <b>{$a}</b>';
$string['htmldocnotsupported'] = 'Incorrect Word format: please use <i>File>Save As...</i> to save <b>{$a}</b> in native Word format and import again';
$string['preview_question_not_found'] = 'Preview question not found, name / course ID: {$a}';
$string['registration_administration'] = 'Moodle2Word Administration';
$string['registration'] = 'Registration';
$string['registrationinfo'] = '<p>You must register Moodle2Word in order to enable the import of Word documents. You do not need to register if you only wish to export questions into Word format. Registration is free, and allows Word files containing up to 5 questions to be imported. However, to import larger numbers of questions, you must pay an annual subscription.</p>
<p>If you choose, you can allow your site name, country and URL to be added to the public list of sites using Moodle2Word.</p>
';
$string['registrationinfotitle'] = 'Moodle2Word Registration Information';

$string['registrationno'] = 'No, I do not want to receive email';
$string['registrationpage'] = 'Redirecting to registration page to enable Word imports';
$string['registrationsend'] = 'Send registration information to www.moodle2word.net';
$string['registrationyes'] = 'Yes, please notify me about important issues';

$string['registrationcomplete'] = 'Registration successful, Word import available';
$string['registrationincomplete'] = 'Registration unsuccessful, Word import not available';
$string['registrationpasswordsdonotmatch'] = 'The passwords do not match';

$string['subscription'] = 'Subscription type';
$string['subtype_free'] = 'Free';
$string['subtype_unlimited'] = 'Paid';
$string['subtype_help'] = ' (No limits, but no support)';
?>

