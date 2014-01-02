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
<xsl:stylesheet exclude-result-prefixes="htm o w"
	xmlns:xsl="http://www.w3.org/1999/XSL/Transform"
	xmlns:o="urn:schemas-microsoft-com:office:office"
	xmlns:w="urn:schemas-microsoft-com:office:word"
	xmlns:htm="http://www.w3.org/1999/xhtml"
	xmlns="http://www.w3.org/1999/xhtml"
	version="1.0">

<xsl:param name="htmltemplatefile" select="'wordfile_template.html'"/>
<xsl:param name="course_name"/>
<xsl:param name="course_id"/>
<xsl:param name="author_name"/>
<xsl:param name="author_id"/>
<xsl:param name="institution_name"/>
<xsl:param name="moodle_url"/>

<xsl:variable name="htmltemplate" select="document($htmltemplatefile)" />

<xsl:variable name="ucase" select="'ABCDEFGHIJKLMNOPQRSTUVWXYZ'" />
<xsl:variable name="lcase" select="'abcdefghijklmnopqrstuvwxyz'" />
<xsl:variable name="pluginfiles_string" select="'@@PLUGINFILE@@/'"/>
<xsl:variable name="embeddedbase64_string" select="'data:image/'"/>

<xsl:output method="xml" version="1.0" omit-xml-declaration="yes" encoding="ISO-8859-1" indent="yes" />


<!-- Read in the input XML into a variable, so that it can be processed -->
<xsl:variable name="data" select="/" />
<xsl:variable name="contains_embedded_images" select="count($data//htm:img[contains(@src, $pluginfiles_string) or starts-with(@src, $embeddedbase64_string)])"/>

<!-- Match document root node, and read in and process Word-compatible XHTML template -->
<xsl:template match="/">
    <xsl:apply-templates select="$htmltemplate/*" />
</xsl:template>


<!-- Place questions in XHTML template body -->
<xsl:template match="processing-instruction('replace')[.='insert-content']">
	<xsl:comment>HTML template parameter: <xsl:value-of select="$htmltemplatefile"/></xsl:comment>
	<xsl:comment>Institution: <xsl:value-of select="$institution_name"/></xsl:comment>
	<xsl:comment>Moodle URL: <xsl:value-of select="$moodle_url"/></xsl:comment>
	<xsl:comment>Course name: <xsl:value-of select="$course_name"/></xsl:comment>
	<xsl:comment>Course ID: <xsl:value-of select="$course_id"/></xsl:comment>
	<xsl:comment>Author name: <xsl:value-of select="$author_name"/></xsl:comment>
	<xsl:comment>Author ID: <xsl:value-of select="$author_id"/></xsl:comment>
	<xsl:comment>Contains embedded images: <xsl:value-of select="$contains_embedded_images"/></xsl:comment>
	
	<!-- Put the course name in as the title -->
	<p class="MsoTitle"><xsl:value-of select="normalize-space($course_name)"/></p>
	<p class="MsoBodyText">&#160;</p>

	<!-- Handle the questions -->
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
	<o:moodleCourseID><xsl:value-of select="$course_id"/></o:moodleCourseID>
	<o:moodleURL><xsl:value-of select="$moodle_url"/></o:moodleURL>
	<o:DC.Type><xsl:value-of select="'Question'"/></o:DC.Type>
	<o:moodleQuestionSeqNum><xsl:value-of select="count($data//htm:table) + 1"/></o:moodleQuestionSeqNum>
	<o:moodleImages><xsl:value-of select="$contains_embedded_images"/></o:moodleImages>
	<o:yawcToolbarBehaviour><xsl:value-of select="'doNothing'"/></o:yawcToolbarBehaviour>
	
	
</xsl:template>

<xsl:template match="processing-instruction('replace')[.='insert-institution']">
	<!-- Place category info and course name into document title -->
	<xsl:value-of select="$institution_name"/>
</xsl:template>


<xsl:template match="htm:p[not(@class)]">
	<p class="Cell">
		<xsl:apply-templates/>
	</p>
</xsl:template>



<!-- Read in the template and copy it to the output -->
<xsl:template match="html">
	<html
		xmlns:o="urn:schemas-microsoft-com:office:office"
		xmlns:w="urn:schemas-microsoft-com:office:word">
		<xsl:apply-templates select="*" />
	</html>
</xsl:template>


<!-- Handle the img element within the main component text by replacing it with a bookmark as a placeholder -->
<xsl:template match="htm:img" priority="2">

	<xsl:choose>
	<xsl:when test="contains(@src, $pluginfiles_string)">
		<!-- If generated from Moodle 2.x, images are handled neatly, using a reference to the data -->
		<xsl:text>&#x0a;</xsl:text>
		<a name="{concat('MQIMAGE_', generate-id())}" style="color:red;">x</a>
	</xsl:when>
	<xsl:when test="contains(@src, $embeddedbase64_string)">
		<!-- If imported from Word2MQXML, images are base64-encoded into the @src attribute -->
		<xsl:text>&#x0a;</xsl:text>
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
<!--		<xsl:message>
			<xsl:value-of select="concat('ImageTable Data: ', substring($image_data, 1, 50), '; format = ', $image_format)"/>
		</xsl:message>
-->
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

	<!--<xsl:message>
		<xsl:value-of select="concat('Matched @src: count = ', $image_data_count, '; title: ', $image_name, '; file_name: ', $image_file_name, '; format = ', $image_format, '; data = ', substring($image_data, 1, 40))"/>
	</xsl:message>-->
	<xsl:value-of select="$image_data"/>
</xsl:template>

<!-- Delete the supplementary paragraphs containing images within each quesetion component, as they are no longer needed -->
<xsl:template match="htm:p[@class = 'ImageFile']"/>

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

