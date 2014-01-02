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
<xsl:param name="moodle_url"/>


<xsl:variable name="ucase" select="'ABCDEFGHIJKLMNOPQRSTUVWXYZ'" />
<xsl:variable name="lcase" select="'abcdefghijklmnopqrstuvwxyz'" />
<xsl:variable name="pluginfiles_string" select="'@@PLUGINFILE@@/'"/>

<xsl:output method="xml" version="1.0" indent="yes" />

<!-- Match document root node, and read in and process Word-compatible XHTML template -->
<xsl:template match="/">
	<html>
		<xsl:variable name="category">
			<xsl:variable name="raw_category" select="normalize-space(/quiz/question[1]/category)"/>
		
			<xsl:choose>
			<xsl:when test="contains($raw_category, '$course$/')">
				<xsl:value-of select="substring-after($raw_category, '$course$/')"/>
			</xsl:when>
			<xsl:otherwise><xsl:value-of select="$raw_category"/></xsl:otherwise>
			</xsl:choose>
		</xsl:variable>
			
		<head>
			<title><xsl:value-of select="$category"/></title>
		</head>
		<body>
			<xsl:apply-templates select="/quiz/question"/>
		</body>
	</html>
</xsl:template>


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
	<p class="MsoBodyText">&#160;</p>
</xsl:template>


<!-- Handle the questions -->
<xsl:template match="question">
	<xsl:variable name="qtype">
		<xsl:choose>
		<xsl:when test="@type = 'truefalse'"><xsl:text>TF</xsl:text></xsl:when>
		<xsl:when test="@type = 'matching'"><xsl:text>MAT</xsl:text></xsl:when>
		<xsl:when test="@type = 'shortanswer'"><xsl:text>SA</xsl:text></xsl:when>
		<xsl:when test="@type = 'multichoice' and single = 'false'"><xsl:text>MA</xsl:text></xsl:when>
		<xsl:when test="@type = 'description'"><xsl:text>DE</xsl:text></xsl:when>
		<xsl:when test="@type = 'essay'"><xsl:text>ES</xsl:text></xsl:when>

		<!-- Not really supported as yet -->
		<xsl:when test="@type = 'cloze'"><xsl:text>CL</xsl:text></xsl:when>
		<xsl:when test="@type = 'numerical'"><xsl:text>NUM</xsl:text></xsl:when>

		<xsl:otherwise><xsl:text>MC</xsl:text></xsl:otherwise>
		</xsl:choose>
	</xsl:variable>

	
	<xsl:variable name="col2_body_label">
		<xsl:choose>
		<xsl:when test="$qtype = 'DE'">	<xsl:text></xsl:text></xsl:when>
		<xsl:when test="$qtype = 'ES'"><xsl:text></xsl:text></xsl:when>
		<xsl:when test="$qtype = 'CL'"><xsl:text>Wrong Answers</xsl:text></xsl:when>
		<xsl:when test="$qtype = 'MAT'">	<xsl:text>Item</xsl:text></xsl:when>
		<xsl:otherwise><xsl:text>Answers</xsl:text></xsl:otherwise>
		</xsl:choose>
	</xsl:variable>

	<xsl:comment>qtype = <xsl:value-of select="$qtype"/>; label = <xsl:value-of select="$col2_body_label"/></xsl:comment>
	<!-- Get the question stem and put it in the heading -->
	<h2 class="MsoHeading2">
			<xsl:value-of select="name"/>
	</h2>
	<p class="MsoBodyText"> </p>
<!--
	<h2 class="MsoHeading2"><xsl:value-of select="normalize-space($stem)" disable-output-escaping="yes"/></h2>
	<p class="MsoBodyText">&#160;</p>	-->
	
	<!-- Get the answers -->
	<div class="TableDiv">
	<table border="1">
	<thead>
		<xsl:text>&#x0a;</xsl:text>
		<tr>
			<td colspan="3" style="width: 12.0cm">
				<xsl:choose>
				<xsl:when test="$qtype = 'CL'">
					<!-- Put Cloze text into the first option table cell, and convert special markup too-->
					<xsl:apply-templates select="questiontext/text|questiontext/file" mode="cloze"/>
				</xsl:when>
				<xsl:otherwise>
					<xsl:apply-templates select="questiontext/text|questiontext/file"/>
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
					<p class="ImageFile"><img src="{concat($image_format, image_base64)}" title="{image}"/></p>
				</xsl:if>
			</td>
			<td style="width: 1.0cm"><p class="QFType"><xsl:value-of select="$qtype" /></p></td>
		</tr>
		<xsl:text>&#x0a;</xsl:text>

		<!-- Heading row for answers -->
		<tr>
			<td style="width: 1.0cm"><p class="TableHead">#</p></td>
			<td style="width: 5.0cm"><p class="QFOptionReset"><xsl:value-of select="$col2_body_label"/></p></td>
			<xsl:choose>
			<xsl:when test="$qtype = 'CL'">
				<td style="width: 6.0cm"><p class="TableHead">Hints/Feedback</p></td>
				<td style="width: 1.0cm"><p class="TableHead">&#160;</p></td>
			</xsl:when>
			<xsl:when test="$qtype = 'DE' or $qtype = 'ES'">
				<td style="width: 6.0cm"><p class="TableHead">&#160;</p></td>
				<td style="width: 1.0cm"><p class="TableHead">&#160;</p></td>
			</xsl:when>
			<xsl:when test="$qtype = 'MAT'">
				<td style="width: 6.0cm"><p class="TableHead">Match</p></td>
				<td style="width: 1.0cm"><p class="TableHead">&#160;</p></td>
			</xsl:when>
			<xsl:otherwise>
				<td style="width: 6.0cm"><p class="TableHead">Hints/Feedback</p></td>
				<td style="width: 1.0cm"><p class="TableHead">Grade</p></td>
			</xsl:otherwise>
			</xsl:choose>
		</tr>
		<xsl:text>&#x0a;</xsl:text>
	</thead>
	<tbody>
	<xsl:text>&#x0a;</xsl:text>

	<!-- Handle the body, containing the options and feedback (for most questions) -->
	<xsl:choose>
	<xsl:when test="$qtype = 'DE' or $qtype = 'ES' or $qtype = 'CL'">
		<!-- Put in blank row  -->
		<tr>
			<td style="width: 1.0cm"><p class="Cell">&#160;</p></td>
			<td style="width: 5.0cm"><p class="Cell">&#160;</p></td>
			<td style="width: 6.0cm"><p class="Cell">&#160;</p></td>
			<td style="width: 1.0cm"><p class="Cell">&#160;</p></td>
		</tr>
	</xsl:when>
	<xsl:otherwise>
		<xsl:apply-templates select="answer|subquestion"/>
	</xsl:otherwise>
	</xsl:choose>
	<xsl:text>&#x0a;</xsl:text>

		
		<!-- Correct and Incorrect feedback for MC and MA questions only -->
		<xsl:if test="$qtype = 'MC' or $qtype = 'MA'">
			<tr>
				<td style="width: 1.0cm"><p class="Cell">&#160;</p></td>
				<th style="width: 5.0cm"><p class="TableRowHead">Correct Feedback:</p></th>
				<td style="width: 6.0cm"><xsl:apply-templates select="correctfeedback/*"/></td>
				<td style="width: 1.0cm"><p class="Cell">&#160;</p></td>
			</tr>
			<xsl:text>&#x0a;</xsl:text>
			<tr>
				<td style="width: 1.0cm"><p class="Cell">&#160;</p></td>
				<th style="width: 5.0cm"><p class="TableRowHead">Incorrect Feedback:</p></th>
				<td style="width: 6.0cm"><xsl:apply-templates select="incorrectfeedback/*"/></td>
				<td style="width: 1.0cm"><p class="Cell">&#160;</p></td>
			</tr>
			<xsl:text>&#x0a;</xsl:text>
		</xsl:if>
		<!-- Partially correct feedback for Multi-answer questions only -->
		<xsl:if test="$qtype = 'MA'">
			<tr>
				<td style="width: 1.0cm"><p class="Cell">&#160;</p></td>
				<th style="width: 5.0cm"><p class="TableRowHead">Partially Correct Feedback:</p></th>
				<td style="width: 6.0cm"><xsl:apply-templates select="partiallycorrectfeedback/*"/></td>
				<td style="width: 1.0cm"><p class="Cell">&#160;</p></td>
			</tr>
			<xsl:text>&#x0a;</xsl:text>
		</xsl:if>
		<!-- General feedback for all question types: MA, MAT, MC, TF, SA -->
		<xsl:if test="$qtype = 'MC' or $qtype = 'MA' or $qtype = 'MAT' or $qtype = 'SA' or $qtype = 'TF' or $qtype = 'CL'">
			<tr>
				<td style="width: 1.0cm"><p class="Cell">&#160;</p></td>
				<th style="width: 5.0cm"><p class="TableRowHead">General Feedback:</p></th>
				<td style="width: 6.0cm"><xsl:apply-templates select="generalfeedback/*"/></td>
				<td style="width: 1.0cm"><p class="Cell">&#160;</p></td>
			</tr>
		<xsl:text>&#x0a;</xsl:text>
		</xsl:if>

		<!-- Simple instructions feedback for some question types: MA, MAT, MC, TF, SA -->
		<xsl:variable name="instruction_text">
			<xsl:choose>
			<xsl:when test="$qtype = 'CL'">
				<xsl:text>Use bold for dropdown menu items and italic for text field items.</xsl:text>
			</xsl:when>
			<xsl:when test="$qtype = 'DE'">
				<xsl:text>This description will be displayed before the following questions.</xsl:text>
			</xsl:when>
			<xsl:when test="$qtype = 'ES'">
				<xsl:text>Don't forget to include the deadline!</xsl:text>
			</xsl:when>
			<xsl:when test="$qtype = 'MA'">
				<xsl:text>Enter two right and two wrong answers.</xsl:text>
			</xsl:when>
			<xsl:when test="$qtype = 'MAT'">
				<xsl:text>Replace each Item/Match pair with a matching word(s) pair.</xsl:text>
			</xsl:when>
			<xsl:when test="$qtype = 'MC'">
				<xsl:text>Replace 'Right answer' with the correct answer, and each 'Wrong answer' with a plausible alternative. Add hints or feedback for each wrong answer too.</xsl:text>
			</xsl:when>
			<xsl:when test="$qtype = 'SA'">
				<xsl:text>All answers should be right answers.  Allow for grammatical variations such as 'boat', 'a boat', the boat'.</xsl:text>
			</xsl:when>
			<xsl:when test="$qtype = 'TF'">
				<xsl:text>Swap 'True' and 'False' to put the right answer first. Do not include hints/feedback.</xsl:text>
			</xsl:when>
			<xsl:otherwise>
			</xsl:otherwise>
			</xsl:choose>
		</xsl:variable>
		
		<tr>
			<td colspan="3" style="width: 12.0cm"><p class="Cell"><i><xsl:value-of select="$instruction_text"/></i></p></td>
			<td style="width: 1.0cm"><p class="Cell">&#160;</p></td>
		</tr>
		<xsl:text>&#x0a;</xsl:text>

	</tbody>
	</table>
	</div>
	<!-- CONTRIB-2847: Insert an empty paragraph after the table so that the "Insert new question" facility works -->
	<p class="MsoNormal">&#160;</p>
</xsl:template>

<!-- Handle standard question rows -->
<xsl:template match="answer|subquestion">
	<tr>
		<td style="width: 1.0cm"><p class="QFOption">&#160;</p></td>
		<td style="width: 5.0cm"><xsl:apply-templates select="text|file"/></td>
		<xsl:choose>
		<xsl:when test="contains(name(), 'subquestion')">
			<td style="width: 6.0cm"><xsl:apply-templates select="answer/text|answer/file"/></td>
			<td style="width: 1.0cm"><p class="QFGrade">&#160;</p></td>
		</xsl:when>
		<xsl:otherwise>
			<td style="width: 6.0cm"><p class="QFFeedback"><xsl:apply-templates select="feedback/text|feedback/file"/></p></td>
			<td style="width: 1.0cm"><p class="QFGrade"><xsl:value-of select="@fraction"/></p></td>
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
			<!-- Add a blank paragraph before the table,  -->
			<xsl:value-of select="concat('&lt;p&gt;&#160;&lt;/p&gt;', $raw_text)"/>
		</xsl:when>
		<xsl:when test="$raw_text = ''"><xsl:text>&#160;</xsl:text></xsl:when>
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
		<xsl:when test="$raw_text = ''"><xsl:text>&#160;</xsl:text></xsl:when>
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

<!-- Handle images associated with '@@PLUGINFILE@@' keyword by including them in temporary supplementary paragraphs in whatever
     component they occur in -->
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
	<xsl:comment><xsl:value-of select="."  /></xsl:comment>
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
