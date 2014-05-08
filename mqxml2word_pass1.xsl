<?xml version="1.0" encoding="UTF-8"?>
<!-- $Id: $ 
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

 * XSLT stylesheet to transform Moodle Question XML-formatted questions into Word-compatible HTML tables 
 *
 * @package questionbank
 * @subpackage importexport
 * @copyright 2010 Eoin Campbell
 * @author Eoin Campbell
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later (5)
-->
<xsl:stylesheet exclude-result-prefixes="htm"
	xmlns:xsl="http://www.w3.org/1999/XSL/Transform"
	xmlns:htm="http://www.w3.org/1999/xhtml"
	xmlns="http://www.w3.org/1999/xhtml"
	version="1.0">

<xsl:param name="course_name"/>
<xsl:param name="course_id"/>
<xsl:param name="author_name"/>
<xsl:param name="author_id"/>
<xsl:param name="institution_name"/>
<xsl:param name="moodle_language" select="'en'"/> <!-- Interface language for user -->
<xsl:param name="moodle_release"/> <!-- 1.9 or 2.x -->
<xsl:param name="moodle_textdirection" select="'ltr'"/> <!-- ltr/rtl, ltr except for Arabic, Hebrew, Urdu, Farsi, Maldivian (who knew?) -->
<xsl:param name="moodle_username"/> <!-- Username for login -->
<xsl:param name="moodle_url"/>      <!-- Location of Moodle site -->


<xsl:variable name="ucase" select="'ABCDEFGHIJKLMNOPQRSTUVWXYZ'" />
<xsl:variable name="lcase" select="'abcdefghijklmnopqrstuvwxyz'" />
<xsl:variable name="pluginfiles_string" select="'@@PLUGINFILE@@/'"/>

<xsl:output method="xml" version="1.0" indent="no" omit-xml-declaration="yes"/>

<!-- Moodle release is significant for the format of different questions
	Essay:
		1.9   - no grader info or response template
		2.1-4 - grader info but no response template
		2.5+  - grader info and response template

	Cloze:
		2.1-3 - no per-hint options
		2.4+  - per hint options, i.e. clear wrong responses, show number of correct responses

	Hints and Tags:
		1.9  - no hints or tags
		2.1+ - hints and tags
-->
<xsl:variable name="moodle_release_number">
	<xsl:choose>
	<xsl:when test="starts-with($moodle_release, '1')"><xsl:text>1</xsl:text></xsl:when>
	<xsl:when test="starts-with($moodle_release, '2.0')"><xsl:text>23</xsl:text></xsl:when>
	<xsl:when test="starts-with($moodle_release, '2.1')"><xsl:text>23</xsl:text></xsl:when>
	<xsl:when test="starts-with($moodle_release, '2.2')"><xsl:text>23</xsl:text></xsl:when>
	<xsl:when test="starts-with($moodle_release, '2.3')"><xsl:text>23</xsl:text></xsl:when>
	<xsl:when test="starts-with($moodle_release, '2.4')"><xsl:text>24</xsl:text></xsl:when>
	<xsl:otherwise><xsl:text>25</xsl:text></xsl:otherwise>
	</xsl:choose>
</xsl:variable>

<!-- Text labels from translated Moodle files - now stored in the input XML file -->
<xsl:variable name="moodle_labels" select="/container/moodlelabels"/>

<!-- Handle colon usage in French -->
<xsl:variable name="colon_string">
	<xsl:choose>
	<xsl:when test="starts-with($moodle_language, 'fr')"><xsl:text> :</xsl:text></xsl:when>
	<xsl:otherwise><xsl:text>:</xsl:text></xsl:otherwise>
	</xsl:choose>
</xsl:variable>
<xsl:variable name="blank_cell" select="'&#160;'"/>

<!-- Create the list of labels from text strings in Moodle, to maximise familiarity of Word file labels -->
<xsl:variable name="answer_label" select="$moodle_labels/data[@name = 'quiz_answer']"/>
<xsl:variable name="answers_label" select="$moodle_labels/data[@name = 'quiz_answers']"/>
<xsl:variable name="categoryname_label" select="$moodle_labels/data[@name = 'moodle_categoryname']"/>
<xsl:variable name="defaultmark_label">
	<xsl:choose>
	<xsl:when test="$moodle_release_number = '1'">
		<xsl:value-of select="concat($moodle_labels/data[@name = 'quiz_defaultgrade'], $colon_string)"/>
	</xsl:when>
	<xsl:otherwise><xsl:value-of select="concat($moodle_labels/data[@name = 'question_defaultmark'], $colon_string)"/></xsl:otherwise>
	</xsl:choose>
</xsl:variable>
<xsl:variable name="grade_label" select="$moodle_labels/data[@name = 'moodle_grade']"/>
<xsl:variable name="no_label" select="$moodle_labels/data[@name = 'moodle_no']"/>
<xsl:variable name="yes_label" select="$moodle_labels/data[@name = 'moodle_yes']"/>
<xsl:variable name="item_label" select="$moodle_labels/data[@name = 'grades_item']"/>
<xsl:variable name="penalty_label">
	<xsl:choose>
	<xsl:when test="$moodle_release_number = '1'">
		<xsl:value-of select="concat($moodle_labels/data[@name = 'quiz_penaltyfactor'], $colon_string)"/>
	</xsl:when>
	<xsl:otherwise><xsl:value-of select="concat($moodle_labels/data[@name = 'question_penaltyforeachincorrecttry'], $colon_string)"/></xsl:otherwise>
	</xsl:choose>
</xsl:variable>
<xsl:variable name="question_label" select="$moodle_labels/data[@name = 'moodle_question']"/>
<xsl:variable name="category_label">
	<xsl:choose>
	<xsl:when test="$moodle_release_number = '1'">
		<xsl:value-of select="$moodle_labels/data[@name = 'question_questioncategory']"/>
	</xsl:when>
	<xsl:otherwise><xsl:value-of select="$moodle_labels/data[@name = 'question_category']"/></xsl:otherwise>
	</xsl:choose>
</xsl:variable>
<xsl:variable name="tags_label" select="concat($moodle_labels/data[@name = 'moodle_tags'], $colon_string)"/>

<xsl:variable name="matching_shuffle_label" select="concat($moodle_labels/data[@name = 'quiz_shuffle'], $colon_string)"/>
<xsl:variable name="mcq_shuffleanswers_label" select="$moodle_labels/data[@name = 'qtype_multichoice_shuffleanswers']"/>
<xsl:variable name="answernumbering_label" select="$moodle_labels/data[@name = 'qtype_multichoice_answernumbering']"/>

<!-- Per-question feedback labels -->
<xsl:variable name="correctfeedback_label" select="concat($moodle_labels/data[@name = 'qtype_multichoice_correctfeedback'], $colon_string)"/>
<xsl:variable name="feedback_label" select="$moodle_labels/data[@name = 'moodle_feedback']"/>
<xsl:variable name="generalfeedback_label">
	<xsl:choose>
	<xsl:when test="$moodle_release_number = '1'">
		<xsl:value-of  select="concat($moodle_labels/data[@name = 'quiz_generalfeedback'], $colon_string)"/>
	</xsl:when>
	<xsl:otherwise>
		<xsl:value-of  select="concat($moodle_labels/data[@name = 'question_generalfeedback'], $colon_string)"/>
	</xsl:otherwise>
	</xsl:choose>
</xsl:variable>

<xsl:variable name="incorrectfeedback_label" select="concat($moodle_labels/data[@name = 'qtype_multichoice_incorrectfeedback'], $colon_string)"/>
<xsl:variable name="pcorrectfeedback_label" select="concat($moodle_labels/data[@name = 'qtype_multichoice_partiallycorrectfeedback'], $colon_string)"/>
<xsl:variable name="shownumcorrectfeedback_label" select="concat($moodle_labels/data[@name = 'question_shownumpartscorrectwhenfinished'], $colon_string)"/>

<!-- Default feedback text (2.5+ only) -->
<xsl:variable name="correctfeedback_default">
	<xsl:choose>
	<xsl:when test="$moodle_release_number &gt; '24'">
		<xsl:value-of select="$moodle_labels/data[@name = 'question_correctfeedbackdefault']"/>
	</xsl:when>
	<xsl:when test="starts-with($moodle_language, 'en')"><xsl:value-of select="'Your answer is correct'"/></xsl:when>
	<xsl:when test="starts-with($moodle_language, 'es')"><xsl:value-of select="'Respuesta correcta'"/></xsl:when>
	<xsl:otherwise><xsl:value-of select="$blank_cell"/></xsl:otherwise>
	</xsl:choose>
</xsl:variable>
<xsl:variable name="incorrectfeedback_default">
	<xsl:choose>
	<xsl:when test="$moodle_release_number &gt; '24'">
		<xsl:value-of select="$moodle_labels/data[@name = 'question_incorrectfeedbackdefault']"/>
	</xsl:when>
	<xsl:when test="starts-with($moodle_language, 'en')"><xsl:value-of select="'Your answer is incorrect'"/></xsl:when>
	<xsl:when test="starts-with($moodle_language, 'es')"><xsl:value-of select="'Respuesta incorrecta.'"/></xsl:when>
	<xsl:otherwise><xsl:value-of select="$blank_cell"/></xsl:otherwise>
	</xsl:choose>
</xsl:variable>
<xsl:variable name="pcorrectfeedback_default">
	<xsl:choose>
	<xsl:when test="$moodle_release_number &gt; '24'">
		<xsl:value-of select="$moodle_labels/data[@name = 'question_partiallycorrectfeedbackdefault']"/>
	</xsl:when>
	<xsl:when test="starts-with($moodle_language, 'en')"><xsl:value-of select="'Your answer is partially correct'"/></xsl:when>
	<xsl:when test="starts-with($moodle_language, 'es')"><xsl:value-of select="'Respuesta parcialmente correcta.'"/></xsl:when>
	<xsl:otherwise><xsl:value-of select="$blank_cell"/></xsl:otherwise>
	</xsl:choose>
</xsl:variable>

<!-- Hint labels; don't add a colon yet, because we'll suffix a specific hint number when printing -->
<xsl:variable name="hintn_label" select="$moodle_labels/data[@name = 'question_hintn']"/>
<xsl:variable name="hint_shownumpartscorrect_label" select="$moodle_labels/data[@name = 'question_shownumpartscorrect']"/>
<xsl:variable name="hint_clearwrongparts_label" select="$moodle_labels/data[@name = 'question_clearwrongparts']"/>

<!-- Description labels -->
<xsl:variable name="description_instructions">
	<xsl:choose>
	<xsl:when test="$moodle_release_number = '1'">
		<xsl:value-of select="$moodle_labels/data[@name = 'qformat_wordtable_description_instructions']"/>
	</xsl:when>
	<xsl:otherwise><xsl:value-of select="$moodle_labels/data[@name = 'qtype_description_pluginnamesummary']"/></xsl:otherwise>
	</xsl:choose>
</xsl:variable>

<!-- Essay question labels -->
<xsl:variable name="allowattachments_label" select="concat($moodle_labels/data[@name = 'qtype_essay_allowattachments'], $colon_string)"/>
<xsl:variable name="graderinfo_label" select="$moodle_labels/data[@name = 'qtype_essay_graderinfo']"/>
<xsl:variable name="responsetemplate_label" select="$moodle_labels/data[@name = 'qtype_essay_responsetemplate']"/>
<xsl:variable name="responsetemplate_help_label" select="$moodle_labels/data[@name = 'qtype_essay_responsetemplate_help']"/>
<xsl:variable name="responsefieldlines_label" select="concat($moodle_labels/data[@name = 'qtype_essay_responsefieldlines'], $colon_string)"/>
<xsl:variable name="responseformat_label" select="concat($moodle_labels/data[@name = 'qtype_essay_responseformat'], $colon_string)"/>
<xsl:variable name="format_html_label">
	<xsl:choose>
	<xsl:when test="$moodle_release_number = '1'">
		<xsl:value-of select="$moodle_labels/data[@name = 'moodle_formathtml']"/>
	</xsl:when>
	<xsl:otherwise><xsl:value-of select="$moodle_labels/data[@name = 'qtype_essay_formateditor']"/></xsl:otherwise>
	</xsl:choose>
</xsl:variable>
<xsl:variable name="format_plain_label">
	<xsl:choose>
	<xsl:when test="$moodle_release_number = '1'">
		<xsl:value-of select="$moodle_labels/data[@name = 'moodle_formatplain']"/>
	</xsl:when>
	<xsl:otherwise><xsl:value-of select="$moodle_labels/data[@name = 'qtype_essay_formatplain']"/></xsl:otherwise>
	</xsl:choose>
</xsl:variable>
<!-- Moodle 2.x only -->
<xsl:variable name="format_editorfilepicker_label" select="$moodle_labels/data[@name = 'qtype_essay_formateditorfilepicker']"/>
<xsl:variable name="format_mono_label" select="$moodle_labels/data[@name = 'qtype_essay_formatmonospaced']"/>
<!-- Moodle 1.9 only -->
<xsl:variable name="format_auto_label" select="$moodle_labels/data[@name = 'moodle_formattext']"/>
<xsl:variable name="format_markdown_label" select="$moodle_labels/data[@name = 'moodle_formatmarkdown']"/>
<xsl:variable name="essay_instructions">
	<xsl:choose>
	<xsl:when test="$moodle_release_number = '1'">
		<xsl:value-of select="$moodle_labels/data[@name = 'qformat_wordtable_essay_instructions']"/>
	</xsl:when>
	<xsl:otherwise><xsl:value-of select="$moodle_labels/data[@name = 'qtype_essay_pluginnamesummary']"/></xsl:otherwise>
	</xsl:choose>
</xsl:variable>

<!-- Matching question labels -->
<xsl:variable name="matching_instructions" select="$moodle_labels/data[@name = 'qtype_match_filloutthreeqsandtwoas']"/>

<!-- Multichoice/Multi-Answer question labels -->
<xsl:variable name="choice_label">
	<xsl:variable name="choice_text" select="$moodle_labels/data[@name = 'qtype_multichoice_choiceno']"/>
	<xsl:choose>
	<xsl:when test="$moodle_release_number = '1'">
		<xsl:value-of select="$moodle_labels/data[@name = 'quiz_choice']"/>
	</xsl:when>
	<xsl:when test="contains($choice_text, '{')">
		<xsl:value-of select="normalize-space(substring-before($choice_text, '{'))"/>
	</xsl:when>
	<xsl:otherwise><xsl:value-of select="$choice_text"/></xsl:otherwise>
	</xsl:choose>
</xsl:variable>
<xsl:variable name="multichoice_instructions">
	<xsl:choose>
	<xsl:when test="$moodle_release_number = '1'">
		<xsl:value-of select="concat($moodle_labels/data[@name = 'qformat_wordtable_multichoice_instructions'], ' (MC/MA)')"/>
	</xsl:when>
	<xsl:otherwise><xsl:value-of select="concat($moodle_labels/data[@name = 'qtype_multichoice_pluginnamesummary'], ' (MC/MA)')"/></xsl:otherwise>
	</xsl:choose>
</xsl:variable>

<!-- Short Answer question labels -->
<xsl:variable name="casesensitive_label">
	<xsl:choose>
	<xsl:when test="$moodle_release_number = '1'">
		<xsl:value-of select="concat($moodle_labels/data[@name = 'quiz_casesensitive'], $colon_string)"/>
	</xsl:when>
	<xsl:otherwise><xsl:value-of select="concat($moodle_labels/data[@name = 'qtype_shortanswer_casesensitive'], $colon_string)"/></xsl:otherwise>
	</xsl:choose>
</xsl:variable>
<xsl:variable name="shortanswer_instructions" select="$moodle_labels/data[@name = 'qtype_shortanswer_filloutoneanswer']"/>

<!-- True/False question labels -->
<xsl:variable name="false_label" select="$moodle_labels/data[@name = 'qtype_truefalse_false']"/>
<xsl:variable name="true_label" select="$moodle_labels/data[@name = 'qtype_truefalse_true']"/>

<!-- Wordtable-specific instruction strings -->
<xsl:variable name="cloze_instructions" select="$moodle_labels/data[@name = 'qformat_wordtable_cloze_instructions']"/>
<xsl:variable name="truefalse_instructions" select="$moodle_labels/data[@name = 'qformat_wordtable_truefalse_instructions']"/>


<!-- Column widths -->
<xsl:variable name="col2_width" select="'width: 5.0cm'"/>
<xsl:variable name="col2_2span_width" select="'width: 6.0cm'"/>
<xsl:variable name="col3_width" select="'width: 6.0cm'"/>
<xsl:variable name="col3_2span_width" select="'width: 7.0cm'"/>

<!-- Match document root node, and read in and process Word-compatible XHTML template -->
<xsl:template match="/container/quiz">
	<html>
		<xsl:variable name="category">
			<xsl:variable name="raw_category" select="normalize-space(./question[1]/category)"/>
		
			<xsl:choose>
			<xsl:when test="contains($raw_category, '$course$/')">
				<xsl:value-of select="substring-after($raw_category, '$course$/')"/>
			</xsl:when>
			<xsl:otherwise><xsl:value-of select="$raw_category"/></xsl:otherwise>
			</xsl:choose>
		</xsl:variable>
			
		<head>
			<title><xsl:value-of select="concat($course_name, ', ', $category_label, $colon_string, ' ', $category)"/></title>
		</head>
		<body>
			<xsl:comment><xsl:value-of select="concat('Release: ', $moodle_release, '; rel_number: ', $moodle_release_number)"/></xsl:comment>
			<p class="MsoTitle"><xsl:value-of select="$course_name"/></p>
			<xsl:apply-templates select="./question"/>
		</body>
	</html>
</xsl:template>

<!-- Throw away extra wrapper elements included in container XML -->
<xsl:template match="/container/moodlelabels"/>

<!-- Omit any Numerical, Random or Calculated questions because we don't want to attempt to import them later -->
<xsl:template match="question[@type = 'numerical']"/>
<xsl:template match="question[starts-with(@type, 'calc')]"/>
<xsl:template match="question[starts-with(@type, 'random')]"/>

<!-- Category becomes a Heading 1 style -->
<!-- There can be lots of categories, but they can also be duplicated -->
<xsl:template match="question[@type = 'category']">
	<xsl:variable name="category">
		<xsl:variable name="raw_category" select="normalize-space(category)"/>
	
		<xsl:choose>
		<xsl:when test="contains($raw_category, '$course$/')">
			<xsl:value-of select="substring-after($raw_category, '$course$/')"/>
		</xsl:when>
		<xsl:otherwise><xsl:value-of select="$raw_category"/></xsl:otherwise>
		</xsl:choose>
	</xsl:variable>

	<h1 class="MsoHeading1"><xsl:value-of select="$category"/></h1>
</xsl:template>

<!-- Handle the questions -->
<xsl:template match="question">
	<xsl:variable name="qtype">
		<xsl:choose>
		<xsl:when test="@type = 'cloze'"><xsl:text>CL</xsl:text></xsl:when>
		<xsl:when test="@type = 'description'"><xsl:text>DE</xsl:text></xsl:when>
		<xsl:when test="@type = 'essay'"><xsl:text>ES</xsl:text></xsl:when>
		<xsl:when test="@type = 'matching'"><xsl:text>MAT</xsl:text></xsl:when>
		<xsl:when test="@type = 'multichoice' and single = 'false'"><xsl:text>MA</xsl:text></xsl:when>
		<xsl:when test="@type = 'multichoice' and single = 'true'"><xsl:text>MC</xsl:text></xsl:when>
		<xsl:when test="@type = 'shortanswer'"><xsl:text>SA</xsl:text></xsl:when>
		<xsl:when test="@type = 'truefalse'"><xsl:text>TF</xsl:text></xsl:when>
		</xsl:choose>
	</xsl:variable>


	<!-- Heading rows for metadata -->
	<xsl:variable name="weight">
		<xsl:choose>
		<xsl:when test="defaultgrade"><xsl:value-of select="number(defaultgrade)"/></xsl:when>
		<xsl:otherwise>1.0</xsl:otherwise>
		</xsl:choose>
	</xsl:variable>

	<xsl:variable name="numbering_flag">
		<xsl:choose>
		<xsl:when test="answernumbering = 'none'">0</xsl:when>
		<xsl:when test="answernumbering"><xsl:value-of select="substring(answernumbering, 1, 1)"/></xsl:when>
		</xsl:choose>
	</xsl:variable>

	<xsl:variable name="shuffleanswers_flag">
		<xsl:choose>
		<!-- shuffleanswers element might be duplicated in XML, or contain either 'true' or '1', so allow for these possibilities -->
		<xsl:when test="shuffleanswers[1] = 'true' or shuffleanswers[1] = '1'">
			<xsl:value-of select="$yes_label"/>
		</xsl:when>
		<xsl:otherwise><xsl:value-of select="$no_label"/></xsl:otherwise>
		</xsl:choose>
	</xsl:variable>

	<!-- Simplify the penalty value to keep it short, to fit it in the 4th column -->
	<xsl:variable name="penalty_value">
		<xsl:choose>
		<xsl:when test="starts-with(penalty, '1')">100</xsl:when>
		<xsl:when test="starts-with(penalty, '0.5')">50</xsl:when>
		<xsl:when test="starts-with(penalty, '0.3333333')">33.3</xsl:when>
		<xsl:when test="starts-with(penalty, '0.25')">25</xsl:when>
		<xsl:when test="starts-with(penalty, '0.2')">20</xsl:when>
		<xsl:when test="starts-with(penalty, '0.1')">10</xsl:when>
		<xsl:otherwise>0</xsl:otherwise>
		</xsl:choose>
	</xsl:variable>

	<!-- Column heading 1 is blank if question is not numbered, otherwise it includes a #, and importantly, a list number reset style -->
	<xsl:variable name="colheading1_label">
		<xsl:choose>
		<xsl:when test="$qtype = 'MA' or $qtype = 'MC' or $qtype = 'MAT'"><xsl:text>#</xsl:text></xsl:when>
		<xsl:otherwise><xsl:value-of select="$blank_cell"/></xsl:otherwise>
		</xsl:choose>
	</xsl:variable>
	<xsl:variable name="colheading1_style">
		<xsl:choose>
		<xsl:when test="$qtype = 'MA' or $qtype = 'MC'"><xsl:value-of select="'QFOptionReset'"/></xsl:when>
		<xsl:when test="$qtype = 'MAT'"><xsl:value-of select="'ListNumberReset'"/></xsl:when>
		<xsl:otherwise><xsl:value-of select="'Cell'"/></xsl:otherwise>
		</xsl:choose>
	</xsl:variable>

	<!-- Answer/Option column heading for most question types, distractors for Cloze, and the response template for Essays (2.5 and above) -->
	<xsl:variable name="colheading2_label">
		<xsl:choose>
		<xsl:when test="$qtype = 'CL'"><xsl:value-of select="$blank_cell"/></xsl:when>
		<xsl:when test="$qtype = 'DE'"><xsl:value-of select="$blank_cell"/></xsl:when>
		<xsl:when test="$qtype = 'ES' and $moodle_release_number &gt; '24'"><xsl:value-of select="$responsetemplate_label"/></xsl:when>
		<xsl:when test="$qtype = 'ES'"><xsl:value-of select="$blank_cell"/></xsl:when>
		<xsl:when test="$qtype = 'MAT'"><xsl:value-of select="$question_label"/></xsl:when>
		<xsl:otherwise><xsl:value-of select="$answers_label"/></xsl:otherwise>
		</xsl:choose>
	</xsl:variable>

	<!-- Option feedback and general feedback column heading -->
	<xsl:variable name="colheading3_label">
		<xsl:choose>
		<xsl:when test="$qtype = 'DE'"><xsl:value-of select="$blank_cell"/></xsl:when>
		<xsl:when test="$qtype = 'ES' and $moodle_release_number = '1'"><xsl:value-of select="$blank_cell"/></xsl:when>
		<xsl:when test="$qtype = 'ES'"><xsl:value-of select="$graderinfo_label"/></xsl:when>
		<xsl:when test="$qtype = 'MAT'"><xsl:value-of select="$answer_label"/></xsl:when>
		<xsl:when test="$qtype = 'MA' or $qtype = 'MC' or $qtype = 'SA' or $qtype = 'TF'"><xsl:value-of select="$feedback_label"/></xsl:when>
		<xsl:otherwise><xsl:value-of select="$blank_cell"/></xsl:otherwise>
		</xsl:choose>
	</xsl:variable>

	<!-- Grade column heading, or blank if no grade (CL, DE, ES, MAT) -->
	<xsl:variable name="colheading4_label">
		<xsl:choose>
		<xsl:when test="$qtype = 'MA' or $qtype = 'MC' or $qtype = 'SA' or $qtype = 'TF'">
			<xsl:value-of select="$grade_label"/>
		</xsl:when>
		<xsl:otherwise><xsl:value-of select="$blank_cell"/></xsl:otherwise>
		</xsl:choose>
	</xsl:variable>


<!-- Get the question name and put it in the heading -->
	<h2 class="MsoHeading2"><xsl:value-of select="normalize-space(name)"/></h2>
	<p class="MsoBodyText"> </p>
	
	<!-- Generate the table containing the question stem and the answers -->
	<div class="TableDiv">
	<table border="1" dir="{$moodle_textdirection}">
	<thead>
		<xsl:text>&#x0a;</xsl:text>
		<tr>
			<td colspan="3" style="width: 12.0cm">
				<xsl:choose>
				<xsl:when test="$qtype = 'CL'">
					<!-- Put Cloze text into the first option table cell, and convert special markup too-->
					<xsl:apply-templates select="questiontext/*" mode="cloze"/>
				</xsl:when>
				<xsl:otherwise>
					<xsl:apply-templates select="questiontext/*"/>
				</xsl:otherwise>
				</xsl:choose>

				<!-- Handle supplementary image for question text, as implemented in Moodle 1.9 -->
				<xsl:if test="image and image != ''">
					<xsl:variable name="image_file_suffix">
						<xsl:value-of select="translate(substring-after(image, '.'), $ucase, $lcase)"/>
					</xsl:variable>
					<xsl:variable name="image_format">
						<xsl:value-of select="concat('data:image/', $image_file_suffix, ';base64,')"/>
					</xsl:variable>
					<p><img src="{concat($pluginfiles_string, image)}"/></p>

					<!-- Emit the image in the supplementary format, to be removed later -->
					<p class="ImageFile"><img src="{concat($image_format, normalize-space(image_base64))}" title="{image}"/></p>
				</xsl:if>
			</td>
			<td style="width: 1.0cm"><p class="QFType"><xsl:value-of select="$qtype" /></p></td>
		</tr>
		<xsl:text>&#x0a;</xsl:text>

		<!-- Handle heading rows for various metadata specific to each question -->
		<!-- Default mark / Default grade / Question weighting, i.e. total marks available for question -->
		<xsl:if test="$qtype = 'ES' or $qtype = 'MA' or $qtype = 'MAT' or $qtype = 'MC' or $qtype = 'SA' or $qtype = 'TF'">
			<tr>
				<td colspan="3" style="width: 12.0cm"><p class="TableRowHead" style="text-align: right"><xsl:value-of select="$defaultmark_label"/></p></td>
					<td style="width: 1.0cm"><p class="Cell"><xsl:value-of select="$weight"/></p></td>
			</tr>
			<xsl:text>&#x0a;</xsl:text>
		</xsl:if>
		<!-- Shuffle the choices? -->
		<xsl:if test="$qtype = 'MAT'">
			<tr>
				<td colspan="3" style="width: 12.0cm"><p class="TableRowHead" style="text-align: right"><xsl:value-of select="$matching_shuffle_label"/></p></td>
					<td style="width: 1.0cm"><p class="Cell"><xsl:value-of select="$shuffleanswers_flag"/></p></td>
			</tr>
			<xsl:text>&#x0a;</xsl:text>
		</xsl:if>
		<xsl:if test="$qtype = 'MA' or $qtype = 'MC'">
			<tr>
				<td colspan="3" style="width: 12.0cm"><p class="TableRowHead" style="text-align: right"><xsl:value-of select="$mcq_shuffleanswers_label"/></p></td>
					<td style="width: 1.0cm"><p class="Cell"><xsl:value-of select="$shuffleanswers_flag"/></p></td>
			</tr>
			<xsl:text>&#x0a;</xsl:text>
		</xsl:if>

		<!-- Number the choices, and if so, how? May be alphabetic, numeric or roman -->
		<xsl:if test="$qtype = 'MC' or $qtype = 'MA'">
			<tr>
				<td colspan="3" style="width: 12.0cm"><p class="TableRowHead" style="text-align: right"><xsl:value-of select="$answernumbering_label"/></p></td>
					<td style="width: 1.0cm"><p class="Cell"><xsl:value-of select="$numbering_flag"/></p></td>
			</tr>
			<xsl:text>&#x0a;</xsl:text>
		</xsl:if>

		<!-- Essay questions in Moodle 2.x have 3 specific fields, for Response field format, Attachments, and Number of lines -->
		<xsl:if test="$qtype = 'ES' and $moodle_release_number &gt; '1'">
			<tr>
				<td colspan="3" style="width: 12.0cm"><p class="TableRowHead" style="text-align: right"><xsl:value-of select="$responseformat_label"/></p></td>
					<td style="width: 1.0cm">
					<p class="Cell"><xsl:choose>
							<xsl:when test="responseformat = 'monospaced'">
								<xsl:value-of select="$format_mono_label"/>
							</xsl:when>
							<xsl:when test="responseformat = 'editorfilepicker'">
								<xsl:value-of select="$format_editorfilepicker_label"/>
							</xsl:when>
							<xsl:when test="responseformat = 'plain'">
								<xsl:value-of select="$format_plain_label"/>
							</xsl:when>
							<xsl:when test="responseformat = 'editor'">
								<xsl:value-of select="$format_html_label"/>
							</xsl:when>
							<xsl:when test="$moodle_release_number = '1' and questiontext/@format = 'markdown'">
								<xsl:value-of select="$format_markdown_label"/>
							</xsl:when>
							<xsl:when test="$moodle_release_number = '1' and questiontext/@format = 'moodle_auto_format'">
								<xsl:value-of select="$format_auto_label"/>
							</xsl:when>
							<xsl:when test="$moodle_release_number = '1' and questiontext/@format = 'plain_text'">
								<xsl:value-of select="$format_plain_label"/>
							</xsl:when>
							<xsl:when test="$moodle_release_number = '1' and questiontext/@format = 'html'">
								<xsl:value-of select="$format_html_label"/>
							</xsl:when>
							<xsl:otherwise><xsl:value-of select="$format_editor_label"/></xsl:otherwise>
							</xsl:choose></p>
					</td>
			</tr>
			<xsl:text>&#x0a;</xsl:text>

			<tr>
				<td colspan="3" style="width: 12.0cm"><p class="TableRowHead" style="text-align: right"><xsl:value-of select="$responsefieldlines_label"/></p></td>
					<td style="width: 1.0cm">
					<p class="Cell">
							<xsl:choose>
							<xsl:when test="responsefieldlines">
								<xsl:value-of select="responsefieldlines"/>
							</xsl:when>
							<xsl:otherwise><xsl:text>15</xsl:text></xsl:otherwise>
							</xsl:choose>
						</p>
					</td>
			</tr>
			<xsl:text>&#x0a;</xsl:text>
			<!-- Attachments -->
			<tr>
				<td colspan="3" style="width: 12.0cm"><p class="TableRowHead" style="text-align: right"><xsl:value-of select="$allowattachments_label"/></p></td>
					<td style="width: 1.0cm">
					<p class="Cell">
							<xsl:choose>
							<xsl:when test="attachments">
								<xsl:value-of select="attachments"/>
							</xsl:when>
							<xsl:otherwise><xsl:text>0</xsl:text></xsl:otherwise>
							</xsl:choose>
						</p>
					</td>
			</tr>
			<xsl:text>&#x0a;</xsl:text>
		</xsl:if>

		<!-- Short answers: are they case-sensitive? -->
		<xsl:if test="$qtype = 'SA'">
			<xsl:variable name="casesensitive_flag">
				<xsl:choose>
				<xsl:when test="usecase = 0"><xsl:value-of select="$no_label"/></xsl:when>
				<xsl:otherwise><xsl:value-of select="$yes_label"/></xsl:otherwise>
				</xsl:choose>
			</xsl:variable>

			<tr>
				<td colspan="3" style="width: 12.0cm"><p class="TableRowHead" style="text-align: right"><xsl:value-of select="$casesensitive_label"/></p></td>
					<td style="width: 1.0cm"><p class="Cell"><xsl:value-of select="$casesensitive_flag"/></p></td>
			</tr>
			<xsl:text>&#x0a;</xsl:text>
		</xsl:if>

		<!-- Penalty for each incorrect try: Don't include for True/False, as it is always 100% in this case -->
		<xsl:if test="$qtype = 'CL' or $qtype = 'MA' or $qtype = 'MAT' or $qtype = 'MC' or $qtype = 'SA'">
			<tr>
				<td colspan="3" style="width: 12.0cm"><p class="TableRowHead" style="text-align: right"><xsl:value-of select="$penalty_label"/></p></td>
					<td style="width: 1.0cm"><p class="Cell"><xsl:value-of select="$penalty_value"/></p></td>
			</tr>
			<xsl:text>&#x0a;</xsl:text>
		</xsl:if>

		<!-- Show number of correct responses when finished (Moodle 2.x only) -->
		<xsl:if test="($qtype = 'MA' or $qtype = 'MAT') and $moodle_release_number &gt; '1'">
			<tr>
				<td colspan="3" style="width: 12.0cm"><p class="TableRowHead" style="text-align: right"><xsl:value-of select="$shownumcorrectfeedback_label"/></p></td>
				<td style="width: 1.0cm">
						<p class="Cell">
							<xsl:choose>
							<xsl:when test="shownumcorrect">
								<xsl:value-of select="$yes_label"/>
							</xsl:when>
							<xsl:otherwise><xsl:value-of select="$no_label"/></xsl:otherwise>
							</xsl:choose>
						</p>
				</td>
			</tr>
			<xsl:text>&#x0a;</xsl:text>
		</xsl:if>

		<!-- Heading row for answers -->
		<tr>
			<td style="width: 1.0cm"><p class="{$colheading1_style}"><xsl:value-of select="$colheading1_label"/></p></td>
			<td style="{$col2_width}"><p class="TableHead"><xsl:value-of select="$colheading2_label"/></p></td>
			<td style="{$col3_width}"><p class="TableHead"><xsl:value-of select="$colheading3_label"/></p></td>
			<td style="width: 1.0cm"><p class="TableHead"><xsl:value-of select="$colheading4_label"/></p></td>
		</tr>
		<xsl:text>&#x0a;</xsl:text>
	</thead>
	<tbody>
	<xsl:text>&#x0a;</xsl:text>

		<!-- Handle the body, containing the options and feedback (for most questions) -->

		<!-- The first body row is the most complicated depending on the question, so do the special cases first -->
		<xsl:choose>
		<xsl:when test="$qtype = 'CL'">
			<!-- Cloze questions should ideally have distractors in the rows, but that's too complicated at the moment, so leave it blank
			<tr>
				<td style="width: 1.0cm"><p class="Cell"><xsl:value-of select="$blank_cell"/></p></td>
				<td style="{$col2_width}"><p class="Cell"><xsl:value-of select="$blank_cell"/></p></td>
				<td style="{$col3_width}"><p class="Cell"><xsl:value-of select="$blank_cell"/></p></td>
				<td style="width: 1.0cm"><p class="Cell"><xsl:value-of select="$blank_cell"/></p></td>
			</tr>
			-->
		</xsl:when>
		<xsl:when test="$qtype = 'ES'">
			<!-- Essay questions in Moodle 2.5+ have a response template and information for graders, so put in a row for these -->
			<tr>
				<td style="width: 1.0cm"><p class="Cell"><xsl:value-of select="$blank_cell"/></p></td>
				<td style="{$col2_width}">
				<!-- Essay questions in Moodle 1.9 to 2.3 have no response template, so leave it out -->
					<xsl:choose>
					<xsl:when test="$moodle_release_number &lt; '25'">
						<p class="Cell"><xsl:value-of select="$blank_cell"/></p>
					</xsl:when>
					<xsl:when test="$moodle_release_number &gt; '24' and responsetemplate and normalize-space(responsetemplate) = ''">
						<p class="Cell"><xsl:value-of select="$responsetemplate_help_label"/></p>
					</xsl:when>
					<xsl:when test="responsetemplate and responsetemplate/@format and responsetemplate/@format = 'html'">
						<xsl:apply-templates select="responsetemplate"/>
					</xsl:when>
					<xsl:when test="responsetemplate and responsetemplate/@format and responsetemplate/@format != 'html'">
						<p class="Cell"><xsl:apply-templates select="responsetemplate"/></p>
					</xsl:when>
					<xsl:otherwise>
						<!-- No essay response template, so it's probably an older version of Moodle. -->
						<p class="Cell"><xsl:value-of select="$blank_cell"/></p>
					</xsl:otherwise>
					</xsl:choose>
				</td>
				<td style="{$col3_width}">
					<xsl:choose>
					<xsl:when test="$moodle_release_number &gt; '1' and graderinfo and graderinfo = ''">
						<p class="Cell"><xsl:value-of select="$blank_cell"/></p>
					</xsl:when>
					<xsl:when test="$moodle_release_number &gt; '1' and graderinfo and graderinfo/@format and graderinfo/@format = 'html'">
						<xsl:apply-templates select="graderinfo/*"/>
					</xsl:when>
					<xsl:when test="$moodle_release_number &gt; '1' and graderinfo and graderinfo/@format and graderinfo/@format != 'html'">
						<p class="Cell"><xsl:apply-templates select="graderinfo/*"/></p>
					</xsl:when>
					<xsl:otherwise>
						<!-- No information for essay graders, so it's probably an older version of Moodle. -->
						<p class="Cell"><xsl:value-of select="$blank_cell"/></p>
					</xsl:otherwise>
					</xsl:choose>
				</td>
				<!-- No grade info used in essays -->
				<td style="width: 1.0cm"><p class="Cell"><xsl:value-of select="$blank_cell"/></p></td>
			</tr>
		</xsl:when>
		<xsl:otherwise>
			<!-- Special cases done, so for other question types, loop through the answers -->
			<xsl:apply-templates select="answer|subquestion">
				<xsl:with-param name="qtype" select="$qtype"/>
				<xsl:with-param name="numbering_flag" select="$numbering_flag"/>
			</xsl:apply-templates>
		</xsl:otherwise>
		</xsl:choose>
		<xsl:text>&#x0a;</xsl:text>

		<!-- General feedback for all question types: MA, MAT, MC, TF, SA -->
		<xsl:if test="$qtype = 'CL' or $qtype = 'DE' or $qtype = 'ES' or $qtype = 'MC' or $qtype = 'MA' or $qtype = 'MAT' or $qtype = 'SA' or $qtype = 'TF'">
			<tr>
				<td style="width: 1.0cm"><p class="Cell"><xsl:value-of select="$blank_cell"/></p></td>
				<th style="{$col2_width}"><p class="TableRowHead"><xsl:value-of select="$generalfeedback_label"/></p></th>
				<td style="{$col3_width}">
					<xsl:choose>
					<xsl:when test="generalfeedback/text = ''">
						<p class="Cell"><xsl:value-of select="$blank_cell"/></p>
					</xsl:when>
					<xsl:otherwise>
						<xsl:apply-templates select="generalfeedback/*"/>
					</xsl:otherwise>
					</xsl:choose>
				
				</td>
				<td style="width: 1.0cm"><p class="Cell"><xsl:value-of select="$blank_cell"/></p></td>
			</tr>
		<xsl:text>&#x0a;</xsl:text>
		</xsl:if>

		<!-- Correct and Incorrect feedback for MA, MAT and MC questions only -->
		<xsl:if test="$qtype = 'MA' or $qtype = 'MC' or ($qtype = 'MAT' and $moodle_release_number &gt; '1')">
			<tr>
				<td style="width: 1.0cm"><p class="Cell"><xsl:value-of select="$blank_cell"/></p></td>
				<th style="{$col2_width}"><p class="TableRowHead"><xsl:value-of select="$correctfeedback_label"/></p></th>
				<td style="{$col3_width}">
					<xsl:choose>
					<xsl:when test="normalize-space(correctfeedback/text) = ''">
						<p class="Cell"><xsl:value-of select="$correctfeedback_default"/></p>
					</xsl:when>
					<xsl:otherwise><xsl:apply-templates select="correctfeedback/*"/></xsl:otherwise>
					</xsl:choose>
				</td>
				<td style="width: 1.0cm"><p class="Cell"><xsl:value-of select="$blank_cell"/></p></td>
			</tr>
			<xsl:text>&#x0a;</xsl:text>
			<tr>
				<td style="width: 1.0cm"><p class="Cell"><xsl:value-of select="$blank_cell"/></p></td>
				<th style="{$col2_width}"><p class="TableRowHead"><xsl:value-of select="$incorrectfeedback_label"/></p></th>
				<td style="{$col3_width}">
					<xsl:choose>
					<xsl:when test="normalize-space(incorrectfeedback/text) = ''">
						<p class="Cell"><xsl:value-of select="$incorrectfeedback_default"/></p>
					</xsl:when>
					<xsl:otherwise><xsl:apply-templates select="incorrectfeedback/*"/></xsl:otherwise>
					</xsl:choose>
				</td>
				<td style="width: 1.0cm"><p class="Cell"><xsl:value-of select="$blank_cell"/></p></td>
			</tr>
			<xsl:text>&#x0a;</xsl:text>
		</xsl:if>
		<!-- Partially correct feedback for MA (Multi-answer) and MAT questions only -->
		<xsl:if test="$qtype = 'MA' or ($qtype = 'MAT' and $moodle_release_number &gt; '1')">
			<tr>
				<td style="width: 1.0cm"><p class="Cell"><xsl:value-of select="$blank_cell"/></p></td>
				<th style="{$col2_width}"><p class="TableRowHead"><xsl:value-of select="$pcorrectfeedback_label"/></p></th>
				<td style="{$col3_width}">
					<xsl:choose>
					<xsl:when test="normalize-space(partiallycorrectfeedback/text) = ''">
						<p class="Cell"><xsl:value-of select="$pcorrectfeedback_default"/></p>
					</xsl:when>
					<xsl:otherwise><xsl:apply-templates select="partiallycorrectfeedback/*"/></xsl:otherwise>
					</xsl:choose>
				</td>
				<td style="width: 1.0cm"><p class="Cell"><xsl:value-of select="$blank_cell"/></p></td>
			</tr>
			<xsl:text>&#x0a;</xsl:text>
		</xsl:if>

		<!-- Hints rows (added in Moodle 2.x) for CL MA MAT MC SA questions -->
		<xsl:if test="$moodle_release_number &gt; '1'">
			<xsl:for-each select="hint[text != '']">
				<!-- Define a label for the hint text row (row 1 of 3) -->
				<xsl:variable name="hint_number_label" select="concat(substring-before($hintn_label, '{no}'), position())"/>
				<tr>
					<td style="width: 1.0cm"><p class="Cell"><xsl:value-of select="$blank_cell"/></p></td>
					<th style="{$col2_width}"><p class="TableRowHead"><xsl:value-of select="concat($hint_number_label, $colon_string)"/></p></th>
					<td style="{$col3_width}">
						<xsl:apply-templates/>
					</td>
					<td style="width: 1.0cm"><p class="Cell"><xsl:value-of select="$blank_cell"/></p></td>
				</tr>
				<xsl:text>&#x0a;</xsl:text>
				<!-- Most question types allow for some fields on the behaviour of hints, but SA doesn't, and CL only in 2.4+ -->
				<xsl:if test="($qtype = 'MA' or $qtype = 'MAT' or $qtype = 'MC') or ($qtype = 'CL' and $moodle_release_number &gt; '23')">
					<tr>
						<td style="width: 1.0cm"><p class="Cell"><xsl:value-of select="$blank_cell"/></p></td>
						<th style="{$col2_width}"><p class="TableRowHead"><xsl:value-of select="concat($hint_shownumpartscorrect_label, ' (', $hint_number_label, ')', $colon_string)"/></p></th>
						<td style="{$col3_width}"><p class="Cell">
							<xsl:choose>
							<xsl:when test="shownumcorrect">
								<xsl:value-of select="$yes_label"/>
							</xsl:when>
							<xsl:otherwise><xsl:value-of select="$no_label"/></xsl:otherwise>
							</xsl:choose>
						</p></td>
						<td style="width: 1.0cm"><p class="Cell"><xsl:value-of select="$blank_cell"/></p></td>
					</tr>
					<xsl:text>&#x0a;</xsl:text>
					<tr>
						<td style="width: 1.0cm"><p class="Cell"><xsl:value-of select="$blank_cell"/></p></td>
						<th style="{$col2_width}"><p class="TableRowHead"><xsl:value-of select="concat($hint_clearwrongparts_label, ' (', $hint_number_label, ')', $colon_string)"/></p></th>
						<td style="{$col3_width}"><p class="Cell">
							<xsl:choose>
							<xsl:when test="clearwrong">
								<xsl:value-of select="$yes_label"/>
							</xsl:when>
							<xsl:otherwise><xsl:value-of select="$no_label"/></xsl:otherwise>
							</xsl:choose>
						</p></td>
						<td style="width: 1.0cm"><p class="Cell"><xsl:value-of select="$blank_cell"/></p></td>
					</tr>
					<xsl:text>&#x0a;</xsl:text>
				</xsl:if>
			</xsl:for-each>

			<!-- Include 1 empty hint row even if there are no hints, or if hint elements are present, but only have flags set -->
			<xsl:if test="(not(hint) or hint/text = '') and ($qtype = 'CL' or $qtype = 'MA' or $qtype = 'MAT' or $qtype = 'MC' or $qtype = 'SA')">
				<!-- Define a label for the hint text row (row 1 of 3) -->
				<xsl:variable name="hint_number_label" select="concat(substring-before($hintn_label, '{no}'), 1)"/>
				<tr>
					<td style="width: 1.0cm"><p class="Cell"><xsl:value-of select="$blank_cell"/></p></td>
					<th style="{$col2_width}"><p class="TableRowHead"><xsl:value-of select="concat($hint_number_label, $colon_string)"/></p></th>
					<td style="{$col3_width}"><p class="Cell"><xsl:value-of select="$blank_cell"/></p></td>
					<td style="width: 1.0cm"><p class="Cell"><xsl:value-of select="$blank_cell"/></p></td>
				</tr>
				<xsl:text>&#x0a;</xsl:text>
				<!-- Most question types allow for some fields on the behaviour of hints, but SA doesn't, and CL only in 2.4+ -->
				<xsl:if test="($qtype = 'MA' or $qtype = 'MAT' or $qtype = 'MC') or ($qtype = 'CL' and $moodle_release_number &gt;= '24')">
					<tr>
						<td style="width: 1.0cm"><p class="Cell"><xsl:value-of select="$blank_cell"/></p></td>
						<th style="{$col2_width}"><p class="TableRowHead"><xsl:value-of select="concat($hint_shownumpartscorrect_label, ' (', $hint_number_label, ')', $colon_string)"/></p></th>
						<td style="{$col3_width}"><p class="Cell"><xsl:value-of select="$no_label"/></p></td>
						<td style="width: 1.0cm"><p class="Cell"><xsl:value-of select="$blank_cell"/></p></td>
					</tr>
					<xsl:text>&#x0a;</xsl:text>
					<tr>
						<td style="width: 1.0cm"><p class="Cell"><xsl:value-of select="$blank_cell"/></p></td>
						<th style="{$col2_width}"><p class="TableRowHead"><xsl:value-of select="concat($hint_clearwrongparts_label, ' (', $hint_number_label, ')', $colon_string)"/></p></th>
						<td style="{$col3_width}"><p class="Cell"><xsl:value-of select="$no_label"/></p></td>
						<td style="width: 1.0cm"><p class="Cell"><xsl:value-of select="$blank_cell"/></p></td>
					</tr>
				</xsl:if>
				<xsl:text>&#x0a;</xsl:text>
			</xsl:if> 
			<!-- End Hint processing -->

			<!-- Tags row (added in Moodle 2.x) -->
			<tr>
				<td style="width: 1.0cm"><p class="Cell"><xsl:value-of select="$blank_cell"/></p></td>
				<th style="{$col2_width}"><p class="TableRowHead"><xsl:value-of select="$tags_label"/></p></th>
				<td style="{$col3_width}">
					<p class="Cell">
							<xsl:choose>
							<xsl:when test="tags[tag = '']">
								<!-- tag element present but empty -->
								<xsl:value-of select="$blank_cell"/>
							</xsl:when>
							<xsl:when test="tags/tag">
								<!-- tag element present and not empty -->
									<xsl:for-each select="tags/tag">
										<xsl:value-of select="normalize-space(.)"/>
										<xsl:if test="position() != last()">
											<xsl:text>, </xsl:text>
										</xsl:if>
									</xsl:for-each>
							</xsl:when>
							<xsl:otherwise><xsl:value-of select="$blank_cell"/></xsl:otherwise>
							</xsl:choose>
					</p>
				</td>
				<td style="width: 1.0cm"><p class="Cell"><xsl:value-of select="$blank_cell"/></p></td>
			</tr>
			<xsl:text>&#x0a;</xsl:text>
		</xsl:if>

		<!-- Simple instructions feedback for some question types: MA, MAT, MC, TF, SA -->
		<xsl:variable name="instruction_text">
			<xsl:choose>
			<xsl:when test="$qtype = 'CL'"><xsl:value-of select="$cloze_instructions"/></xsl:when>
			<xsl:when test="$qtype = 'DE'"><xsl:value-of select="$description_instructions"/></xsl:when>
			<xsl:when test="$qtype = 'ES'"><xsl:value-of select="$essay_instructions"/></xsl:when>
			<xsl:when test="$qtype = 'MA'"><xsl:value-of select="$multichoice_instructions"/></xsl:when>
			<xsl:when test="$qtype = 'MAT'"><xsl:value-of select="$matching_instructions"/></xsl:when>
			<xsl:when test="$qtype = 'MC'"><xsl:value-of select="$multichoice_instructions"/></xsl:when>
			<xsl:when test="$qtype = 'SA'"><xsl:value-of select="$shortanswer_instructions"/></xsl:when>
			<xsl:when test="$qtype = 'TF'"><xsl:value-of select="$truefalse_instructions"/></xsl:when>
			<xsl:otherwise>
			</xsl:otherwise>
			</xsl:choose>
		</xsl:variable>
		
		<tr>
			<td colspan="3" style="width: 12.0cm"><p class="Cell"><i><xsl:value-of select="$instruction_text"/></i></p></td>
			<td style="width: 1.0cm"><p class="Cell"><xsl:value-of select="$blank_cell"/></p></td>
		</tr>
		<xsl:text>&#x0a;</xsl:text>

	</tbody>
	</table>
	</div>
	<!-- CONTRIB-2847: Insert an empty paragraph after the table so that the "Insert new question" facility works -->
	<p class="MsoNormal"><xsl:value-of select="$blank_cell"/></p>
</xsl:template>

<!-- Handle True/False question rows as a special case, as they only contain 'true' or 'false', which should be translated -->
<xsl:template match="answer[ancestor::question/@type = 'truefalse']" priority="2">
	<tr>
		<td style="width: 1.0cm"><p class="Cell"><xsl:value-of select="$blank_cell"/></p></td>
		<td style="{$col2_width}">
			<xsl:choose>
			<xsl:when test="text = 'true'">
				<p class="Cell"><xsl:value-of select="$true_label"/></p>
			</xsl:when>
			<xsl:when test="text = 'false'">
				<p class="Cell"><xsl:value-of select="$false_label"/></p>
			</xsl:when>
			</xsl:choose>
		</td>
		<td style="{$col3_width}"><p class="QFFeedback"><xsl:apply-templates select="feedback/*"/></p></td>
		<td style="width: 1.0cm"><p class="QFGrade"><xsl:value-of select="@fraction"/></p></td>
	</tr>
</xsl:template>

<!-- Handle standard question rows -->
<xsl:template match="answer[not(ancestor::subquestion)]|subquestion">
	<xsl:param name="qtype"/>
	<xsl:param name="numbering_flag"/>

	<!-- The 1st column contains a list item for MA and MC, and is blank for other questions. Use the paragraph style to control the enumeration -->
	<xsl:variable name="numbercolumn_class">
		<xsl:choose>
		<xsl:when test="$qtype = 'MAT'">
			<xsl:text>MsoListNumber</xsl:text>
		</xsl:when>
		<xsl:when test="$qtype = 'SA'">
			<xsl:text>Cell</xsl:text>
		</xsl:when>
		<xsl:otherwise><xsl:text>QFOption</xsl:text></xsl:otherwise>
		</xsl:choose>
	</xsl:variable>

	<!-- Simplify the percentage score value for an answer to keep it short, to fit it in the 4th column -->
	<xsl:variable name="grade_value">
		<xsl:choose>
		<xsl:when test="@fraction = '83.33333'"><xsl:text>83.3</xsl:text></xsl:when>
		<xsl:when test="@fraction = '66.66667'"><xsl:text>66.6</xsl:text></xsl:when>
		<xsl:when test="@fraction = '33.33333'"><xsl:text>33.3</xsl:text></xsl:when>
		<xsl:when test="@fraction = '16.66667'"><xsl:text>16.6</xsl:text></xsl:when>
		<xsl:when test="@fraction = '14.28571'"><xsl:text>14.3</xsl:text></xsl:when>
		<xsl:when test="@fraction = '11.11111'"><xsl:text>11.1</xsl:text></xsl:when>
		<xsl:when test="@fraction = '-83.33333'"><xsl:text>-83.3</xsl:text></xsl:when>
		<xsl:when test="@fraction = '-66.66667'"><xsl:text>-66.6</xsl:text></xsl:when>
		<xsl:when test="@fraction = '-33.33333'"><xsl:text>-33.3</xsl:text></xsl:when>
		<xsl:when test="@fraction = '-16.66667'"><xsl:text>-16.6</xsl:text></xsl:when>
		<xsl:when test="@fraction = '-14.28571'"><xsl:text>-14.3</xsl:text></xsl:when>
		<xsl:when test="@fraction = '-11.11111'"><xsl:text>-11.1</xsl:text></xsl:when>
		<xsl:otherwise><xsl:value-of select="@fraction"/></xsl:otherwise>
		</xsl:choose>
	</xsl:variable>

	<!-- Process body row columns 1 and 2, for MA, MC, MAT and SA -->
	<tr>
		<td style="width: 1.0cm"><p class="{$numbercolumn_class}"><xsl:value-of select="$blank_cell"/></p></td>
		<xsl:choose>
		<xsl:when test="$qtype = 'SA'">
			<td style="{$col2_width}"><p class="Cell"><xsl:value-of select="normalize-space(text)"/></p></td>
		</xsl:when>
		<xsl:otherwise>
			<td style="{$col2_width}"><xsl:apply-templates select="text|file"/></td>
		</xsl:otherwise>
		</xsl:choose>

		<!-- Process body row columns 3 and 4 -->
		<xsl:choose>
		<xsl:when test="$qtype = 'MAT'">
			<td style="{$col3_width}"><p class="Cell"><xsl:value-of select="answer/*"/></p></td>
			<td style="width: 1.0cm"><p class="Cell"><xsl:value-of select="$blank_cell"/></p></td>
		</xsl:when>
		<xsl:otherwise>
			<td style="{$col3_width}"><p class="QFFeedback"><xsl:apply-templates select="feedback/*"/></p></td>
			<td style="width: 1.0cm"><p class="QFGrade"><xsl:value-of select="$grade_value"/></p></td>
		</xsl:otherwise>
		</xsl:choose>
	</tr>
</xsl:template>

<!-- Handle MQXML text elements, which may consist only of a CDATA section -->
<xsl:template match="text">
	<xsl:variable name="text_string">
		<xsl:variable name="raw_text" select="normalize-space(.)"/>
		
		<xsl:choose>
		<!-- If the string is wrapped in <p>...</p>, get rid of it -->
		<xsl:when test="starts-with($raw_text, '&lt;p&gt;') and substring($raw_text, -4) = '&lt;/p&gt;'">
			<!-- 7 = string-length('<p>') + string-length('</p>') </p> -->
			<xsl:value-of select="substring($raw_text, 4, string-length($raw_text) - 7)"/>
		</xsl:when>
		<xsl:when test="starts-with($raw_text, '&lt;table')">
			<!-- Add a blank paragraph before the table, -->
			<xsl:value-of select="concat('&lt;p&gt;', $blank_cell, '&lt;/p&gt;', $raw_text)"/>
		</xsl:when>
		<xsl:when test="$raw_text = ''"><xsl:value-of select="$blank_cell"/></xsl:when>
		<xsl:otherwise><xsl:value-of select="$raw_text"/></xsl:otherwise>
		</xsl:choose>
	</xsl:variable>
	
	<xsl:value-of select="$text_string" disable-output-escaping="yes"/>
</xsl:template>

<!-- Handle Cloze text -->
<xsl:template match="text" mode="cloze">
	<xsl:variable name="text_string">
		<xsl:variable name="raw_text" select="normalize-space(.)"/>
		
		<xsl:choose>
		<!-- If the string is wrapped in <p>...</p>, get rid of it -->
		<xsl:when test="starts-with($raw_text, '&lt;p&gt;') and substring($raw_text, -4) = '&lt;/p&gt;'">
			<!-- 7 = string-length('<p>') + string-length('</p>') </p> -->
			<xsl:value-of select="substring($raw_text, 4, string-length($raw_text) - 7)"/>
		</xsl:when>
		<xsl:when test="$raw_text = ''"><xsl:value-of select="$blank_cell"/></xsl:when>
		<xsl:otherwise><xsl:value-of select="$raw_text"/></xsl:otherwise>
		</xsl:choose>
	</xsl:variable>

	<xsl:call-template name="convert_cloze_string">
		<xsl:with-param name="cloze_string" select="$text_string"/>
	</xsl:call-template>
	
	<!--<xsl:value-of select="$text_string" disable-output-escaping="yes"/>-->
</xsl:template>

<!-- Convert Cloze text strings -->
<xsl:template name="convert_cloze_string">
	<xsl:param name="cloze_string"/>
	
	<xsl:choose>
	<xsl:when test="contains($cloze_string, '{')">
		<!-- Copy the text prior to the embedded question -->
		<xsl:value-of select="substring-before($cloze_string, '{')" disable-output-escaping="yes"/>
		
		<!-- Process the embedded cloze -->
		<xsl:call-template name="convert_cloze_item">
			<xsl:with-param name="cloze_item" 
				select="substring-before(substring-after($cloze_string, '{'), '}')"/>
		</xsl:call-template>
		<!-- Recurse through the string again -->
		<xsl:call-template name="convert_cloze_string">
			<xsl:with-param name="cloze_string" select="substring-after($cloze_string, '}')"/>
		</xsl:call-template>
	</xsl:when>
	<xsl:otherwise>
		<xsl:value-of select="$cloze_string" disable-output-escaping="yes"/>
	</xsl:otherwise>
	</xsl:choose>
</xsl:template>

<!-- Convert embedded NUMERICAL, SHORTANSWER or MULTICHOICE into markup-->
<xsl:template name="convert_cloze_item">
	<xsl:param name="cloze_item"/>
	
	<xsl:choose>
	<xsl:when test="contains($cloze_item, 'NUMERICAL')">
		<u>
			<xsl:call-template name="format_cloze_item">
				<xsl:with-param name="cloze_item" select="substring-after($cloze_item, 'NUMERICAL:')"/>
			</xsl:call-template>
		</u>
	</xsl:when>
	<xsl:when test="contains($cloze_item, 'SHORTANSWER')">
		<i>
			<xsl:call-template name="format_cloze_item">
				<xsl:with-param name="cloze_item" select="substring-after($cloze_item, 'SHORTANSWER:')"/>
			</xsl:call-template>
		</i>
	</xsl:when>
	<xsl:when test="contains($cloze_item, 'MULTICHOICE')">
		<b>
			<xsl:call-template name="format_cloze_item">
				<xsl:with-param name="cloze_item" select="substring-after($cloze_item, 'MULTICHOICE:')"/>
			</xsl:call-template>
		</b>
	</xsl:when>
	</xsl:choose>
</xsl:template>

<!-- Cloze items simply get converted into formatted text, retaining the horrible Moodle ~ markup formatting -->
<xsl:template name="format_cloze_item">
	<xsl:param name="cloze_item"/>
	<xsl:value-of select="$cloze_item"/>
</xsl:template>

<!-- Handle images associated with '@@PLUGINFILE@@' keyword by including them in temporary supplementary paragraphs in whatever component they occur in -->
<xsl:template match="file">
	<xsl:variable name="image_file_suffix">
		<xsl:value-of select="translate(substring-after(@name, '.'), $ucase, $lcase)"/>
	</xsl:variable>
	<xsl:variable name="image_format">
		<xsl:value-of select="concat('data:image/', $image_file_suffix, ';', @encoding, ',')"/>
	</xsl:variable>
	<p class="ImageFile"><img src="{concat($image_format, .)}" title="{@name}"/></p>
</xsl:template>

<!-- got to preserve comments for style definitions -->
<xsl:template match="comment()">
	<xsl:comment><xsl:value-of select="."/></xsl:comment>
</xsl:template>

<!-- Identity transformations -->
<xsl:template match="*">
	<xsl:element name="{name()}">
		<xsl:call-template name="copyAttributes" />
		<xsl:apply-templates select="node()"/>
	</xsl:element>
</xsl:template>

<xsl:template name="copyAttributes">
	<xsl:for-each select="@*">
		<xsl:attribute name="{name()}"><xsl:value-of select="."/></xsl:attribute>
	</xsl:for-each>
</xsl:template>

</xsl:stylesheet>
