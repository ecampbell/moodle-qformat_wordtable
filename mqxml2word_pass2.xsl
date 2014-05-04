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

 * XSLT stylesheet to wrap questions formatted as HTML tables with a Word-compatible wrapper that defines the styles, metadata, etc.
 *
 * @package questionbank
 * @subpackage importexport
 * @copyright 2010 Eoin Campbell
 * @author Eoin Campbell
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later (5)
-->
<xsl:stylesheet exclude-result-prefixes="htm o w"
	xmlns:xsl="http://www.w3.org/1999/XSL/Transform"
	xmlns:o="urn:schemas-microsoft-com:office:office"
	xmlns:w="urn:schemas-microsoft-com:office:word"
	xmlns:htm="http://www.w3.org/1999/xhtml"
	xmlns="http://www.w3.org/1999/xhtml"
	version="1.0">

<xsl:param name="course_name"/>
<xsl:param name="course_id"/>
<xsl:param name="author_name"/>
<xsl:param name="author_id"/>
<xsl:param name="institution_name"/>
<xsl:param name="moodle_country" select="'US'"/> <!-- Country of Moodle installation -->
<xsl:param name="moodle_language" select="'en'"/> <!-- Interface language for user -->
<xsl:param name="moodle_textdirection" select="'ltr'"/>  <!-- ltr/rtl, ltr except for Arabic, Hebrew, Urdu, Farsi, Maldivian (who knew?) -->
<xsl:param name="moodle_release"/>  <!-- 1.9 or 2.x -->
<xsl:param name="moodle_url"/>      <!-- Location of Moodle site -->
<xsl:param name="moodle_username"/> <!-- Username for login -->

<xsl:variable name="ucase" select="'ABCDEFGHIJKLMNOPQRSTUVWXYZ'" />
<xsl:variable name="lcase" select="'abcdefghijklmnopqrstuvwxyz'" />
<xsl:variable name="pluginfiles_string" select="'@@PLUGINFILE@@/'"/>
<xsl:variable name="embeddedbase64_string" select="'data:image/'"/>

<xsl:output method="xml" version="1.0" omit-xml-declaration="yes" encoding="ISO-8859-1" indent="yes" />

<!-- Text labels from translated Moodle files -->
<xsl:variable name="moodle_labels" select="/container/moodlelabels"/>
<!-- Word-compatible XHTML template into which the questions are inserted -->
<xsl:variable name="htmltemplate" select="/container/htmltemplate" />
<!-- Throw away the extra wrapper elements, now we've read them into variables -->
<xsl:template match="/container/moodlelabels"/>
<xsl:template match="/container/htmltemplate"/>

<!-- Read in the input XML into a variable, so that it can be processed -->
<xsl:variable name="data" select="/container/htm:container" />
<xsl:variable name="contains_embedded_images" select="count($data//htm:img[contains(@src, $pluginfiles_string) or starts-with(@src, $embeddedbase64_string)])"/>

<!-- Map raw language value into a Word-compatible version, removing anything after an underscore and capitalising -->
<xsl:variable name="moodle_language_value">
	<xsl:choose>
	<xsl:when test="contains($moodle_language, '_')">
		<xsl:value-of select="translate(substring-before($moodle_language, '_'), $lcase, $ucase)"/>
	</xsl:when>
	<xsl:otherwise>
		<xsl:value-of select="translate($moodle_language, $lcase, $ucase)"/>
	</xsl:otherwise>
	</xsl:choose>
</xsl:variable>
<!-- Match document root node, and read in and process Word-compatible XHTML template -->
<xsl:template match="/">
<!-- Set the language and text direction -->
	<html lang="{$moodle_language}" dir="{$moodle_textdirection}">
		<xsl:apply-templates select="$htmltemplate/htm:html/*" />
	</html>
</xsl:template>

<!-- Place questions in XHTML template body -->
<xsl:template match="processing-instruction('replace')[.='insert-content']">
	<xsl:comment>Institution: <xsl:value-of select="$institution_name"/></xsl:comment>
	<xsl:comment>Moodle language: <xsl:value-of select="$moodle_language"/></xsl:comment>
	<xsl:comment>Moodle URL: <xsl:value-of select="$moodle_url"/></xsl:comment>
	<xsl:comment>Course name: <xsl:value-of select="$course_name"/></xsl:comment>
	<xsl:comment>Course ID: <xsl:value-of select="$course_id"/></xsl:comment>
	<xsl:comment>Author name: <xsl:value-of select="$author_name"/></xsl:comment>
	<xsl:comment>Author ID: <xsl:value-of select="$author_id"/></xsl:comment>
	<xsl:comment>Author username: <xsl:value-of select="$moodle_username"/></xsl:comment>
	<xsl:comment>Contains embedded images: <xsl:value-of select="$contains_embedded_images"/></xsl:comment>
	
	<!-- Handle the question tables -->
	<xsl:apply-templates select="$data/htm:html/htm:body"/>

	<!-- Add a table for images, if present -->
	<xsl:if test="$contains_embedded_images != 0">
		<table border="1" style="display:none;"><thead>
		<tr><td colspan="7"><p class="Cell">&#160;</p></td><td><p class="QFType">Images</p></td></tr>
		<tr><td><p class="TableHead">ID</p></td><td><p class="TableHead">Name</p></td><td><p class="TableHead">Width</p></td><td><p class="TableHead">Height</p></td><td><p class="TableHead">Alt</p></td><td><p class="TableHead">Format</p></td><td><p class="TableHead">Encoding</p></td><td><p class="TableHead">Data</p></td></tr>
		</thead>
		<tbody>
			<!-- Get images exported from Moodle 2.x as file elements -->
			<xsl:for-each select="$data//htm:img[contains(@src, $pluginfiles_string)]">
				<!--<xsl:message><xsl:value-of select="concat('ImageTable:', @src)"/></xsl:message>-->
				<xsl:apply-templates select="." mode="ImageTable"/>
			</xsl:for-each>
			<!-- Get images imported from Word2XML conversion process as embedded base64 images -->
			<xsl:for-each select="$data//htm:img[starts-with(@src, $embeddedbase64_string)]">
				<xsl:if test="not(ancestor::htm:p/@class = 'ImageFile')">
					<xsl:apply-templates select="." mode="ImageTable"/>
				</xsl:if>
			</xsl:for-each>
		</tbody>
		</table>
	</xsl:if>
</xsl:template>

<!-- Metadata -->
<!-- Set the title property (File->Properties... Summary tab) -->
<xsl:template match="processing-instruction('replace')[.='insert-title']">
	<!-- Place category info and course name into document title -->
	<xsl:value-of select="$data/htm:html/htm:head/htm:title"/>
</xsl:template>

<!-- Set the author property -->
<xsl:template match="processing-instruction('replace')[.='insert-author']">
	<xsl:value-of select="$author_name"/>
</xsl:template>

<xsl:template match="processing-instruction('replace')[.='insert-meta']">
	<!-- Place category info and course name into document title -->
	<o:DC.Type><xsl:value-of select="'Question'"/></o:DC.Type>
	<o:moodleCategory><xsl:value-of select="$moodle_labels/data[@name = 'question_category']"/></o:moodleCategory>
	<o:moodleCourseID><xsl:value-of select="$course_id"/></o:moodleCourseID>
	<o:moodleImages><xsl:value-of select="$contains_embedded_images"/></o:moodleImages>
	<o:moodleLanguage><xsl:value-of select="$moodle_language"/></o:moodleLanguage>
	<o:moodleTextDirection><xsl:value-of select="$moodle_textdirection"/></o:moodleTextDirection>
	<o:moodleNo><xsl:value-of select="$moodle_labels/data[@name = 'moodle_no']"/></o:moodleNo>
	<o:moodleQuestion><xsl:value-of select="$moodle_labels/data[@name = 'moodle_question']"/></o:moodleQuestion>
	<o:moodleYes><xsl:value-of select="$moodle_labels/data[@name = 'moodle_yes']"/></o:moodleYes>
	<o:moodleQuestionSeqNum><xsl:value-of select="count($data//htm:table) + 1"/></o:moodleQuestionSeqNum>
	<o:moodleRelease><xsl:value-of select="$moodle_release"/></o:moodleRelease>
	<o:moodleURL><xsl:value-of select="$moodle_url"/></o:moodleURL>
	<o:moodleUsername><xsl:value-of select="$moodle_username"/></o:moodleUsername>
	<o:yawcToolbarBehaviour><xsl:value-of select="'doNothing'"/></o:yawcToolbarBehaviour>
</xsl:template>

<xsl:template match="processing-instruction('replace')[.='insert-language']">
	<!-- Set the language of each style to be whatever is defined in Moodle, to assist spell-checking -->
	<xsl:value-of select="concat($moodle_language_value, '-', $moodle_country)"/>
</xsl:template>

<!-- Look for table cells with just text, and wrap them in a Cell paragraph style -->
<xsl:template match="htm:td">
	<td>
		<xsl:call-template name="copyAttributes"/>
		<xsl:choose>
		<xsl:when test="count(*) = 0">
			<p class="Cell">
				<xsl:apply-templates/>
			</p>
		</xsl:when>
		<xsl:otherwise><xsl:apply-templates/></xsl:otherwise>
		</xsl:choose>
	</td>
</xsl:template>

<!-- Any paragraphs without an explicit class are set to have the Cell style -->
<xsl:template match="htm:p[not(@class)]">
	<p class="Cell">
		<xsl:apply-templates/>
	</p>
</xsl:template>

<!-- Handle the img element within the main component text by replacing it with a bookmark as a placeholder -->
<xsl:template match="htm:img" priority="2">
	<xsl:choose>
	<xsl:when test="contains(@src, $pluginfiles_string)">
		<!-- Generated from Moodle 2.x, so images are handled neatly, using a reference to the data -->
		<a name="{concat('MQIMAGE_', generate-id())}" style="color:red;">x</a>
	</xsl:when>
	<xsl:when test="contains(@src, $embeddedbase64_string)">
		<!-- If imported from Word2MQXML, images are base64-encoded into the @src attribute -->
		<a name="{concat('MQIMAGE_', generate-id())}" style="color:red;">x</a>
	</xsl:when>
	<xsl:otherwise>
		<img>
			<xsl:call-template name="copyAttributes"/>
		</img>
	</xsl:otherwise>
	</xsl:choose>
</xsl:template>


<!-- Create a row in the embedded image table with all image metadata -->
<xsl:template match="htm:img" mode="ImageTable">
	<xsl:variable name="image_id" select="generate-id()"/>
	<xsl:variable name="image_file_name">
		<xsl:choose>
		<xsl:when test="contains(@src, $pluginfiles_string)">
			<!-- Image exported from Moodle 2.x, i.e. <img src="@@PLUGINFILE@@/filename.gif"/>-->
			<xsl:value-of select="substring-after(@src, $pluginfiles_string)"/>
		</xsl:when>
		<xsl:otherwise> <!-- No name as the image is embedded in the text, i.e. <img src="data:image/gif;base64,{base64 data}"/> -->
			<xsl:value-of select="''"/>
		</xsl:otherwise>
		</xsl:choose>
	</xsl:variable>

	<xsl:variable name="image_data">
		<xsl:choose>
		<xsl:when test="contains(@src, $pluginfiles_string)">
			<!-- Image exported from Moodle 2.x, i.e. 
				 <img src="@@PLUGINFILE@@/filename.gif"/> <file name="filename.gif" encoding="base64">{base64 data}</file> -->
			<xsl:value-of select="substring-after(ancestor::htm:td//htm:p[@class = 'ImageFile' and htm:img/@title = $image_file_name]/htm:img/@src, ',')"/>
		</xsl:when>
		<xsl:when test="contains(@src, $embeddedbase64_string)">
			<!-- Image embedded in text as it was imported using Word2MQXML, i.e. <img src="data:image/gif;base64,{base64 data}"/> -->
			<xsl:value-of select="substring-after(@src, ';base64,')"/>
		</xsl:when>
		</xsl:choose>
	</xsl:variable>

	<xsl:variable name="image_format">
		<xsl:choose>
		<xsl:when test="contains(@src, $pluginfiles_string)">
			<!-- Image exported from Moodle 2.x, i.e. 
				 <img src="@@PLUGINFILE@@/filename.gif"/> <file name="filename.gif" encoding="base64">{base64 data}</file> -->
			<xsl:value-of select="substring-after(substring-before(ancestor::htm:td//htm:p[@class = 'ImageFile' and htm:img/@title = $image_file_name]/htm:img/@src, ';'), 'data:image/')"/>
		</xsl:when>
		<xsl:when test="contains(@src, $embeddedbase64_string)">
			<!-- Image embedded in text as it was imported using Word2MQXML, i.e. <img src="data:image/gif;base64,{base64 data}"/> -->
			<xsl:value-of select="substring-before(substring-after(@src, $embeddedbase64_string), ';')"/>
		</xsl:when>
		</xsl:choose>
	</xsl:variable>

	<xsl:variable name="image_encoding">
		<xsl:choose>
		<xsl:when test="contains(@src, $pluginfiles_string)">
			<xsl:value-of select="substring-after(substring-before(ancestor::htm:td//htm:p[@class = 'ImageFile' and htm:img/@title = $image_file_name]/htm:img/@src, ','), ';')"/>
		</xsl:when>
		<xsl:otherwise> <!-- Always Base 64 -->
			<xsl:value-of select="'base64'"/>
		</xsl:otherwise>
		</xsl:choose>
	</xsl:variable>

	<xsl:text>&#x0a;</xsl:text>
	<tr>
		<td><p class="Cell"><xsl:value-of select="$image_id"/></p></td>
		<td><p class="Cell"><xsl:value-of select="$image_file_name"/></p></td>
		<td><p class="Cell"><xsl:value-of select="@width"/></p></td>
		<td><p class="Cell"><xsl:value-of select="@height"/></p></td>
		<td><p class="Cell"><xsl:value-of select="@alt"/></p></td>
		<td><p class="Cell"><xsl:value-of select="$image_format"/></p></td>
		<td><p class="Cell"><xsl:value-of select="$image_encoding"/></p></td>
		<td><p class="Cell"><xsl:value-of select="$image_data"/></p></td>
	</tr>
</xsl:template>


<!-- Handle the @src attribute of images in the main component text -->
<xsl:template match="htm:img/@src">
	<xsl:variable name="image_file_name" select="substring-after(., $pluginfiles_string)"/>
	<xsl:variable name="image_data_count" select="count(ancestor::htm:td[1]//htm:p[@class = 'ImageFile'])"/>
	<xsl:variable name="image_name" select="ancestor::htm:td//htm:p[@class = 'ImageFile' and htm:img/@title = $image_file_name]/htm:img/@title"/>
	<xsl:variable name="image_data" select="ancestor::htm:td//htm:p[@class = 'ImageFile']/htm:img/@src"/>
	<xsl:variable name="image_format" select="substring-before(substring-after('data:image/', $image_data), ';')"/>
	<xsl:variable name="image_encoding" select="substring-after(substring-before(',', $image_data), ';')"/>

	<xsl:value-of select="$image_data"/>
</xsl:template>

<!-- Delete the supplementary paragraphs containing images within each question component, as they are no longer needed -->
<xsl:template match="htm:p[@class = 'ImageFile']"/>

<!-- Preserve comments for style definitions -->
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