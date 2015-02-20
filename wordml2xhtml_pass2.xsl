<?xml version="1.0" encoding="UTF-8"?>
<!--
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

 * XSLT stylesheet to transform rough XHTML derived from Word 2010 files into a more hierarchical format with divs wrapping each heading and table (question name and item)
 *
 * @package qformat_wordtable
 * @copyright 2010-2015 Eoin Campbell
 * @author Eoin Campbell
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later (5)
-->

<xsl:stylesheet
    xmlns="http://www.w3.org/1999/xhtml"
    xmlns:x="http://www.w3.org/1999/xhtml"
    xmlns:xsl="http://www.w3.org/1999/XSL/Transform"
    exclude-result-prefixes="x"
    version="1.0">
  <xsl:output method="xml" encoding="UTF-8" indent="yes" omit-xml-declaration="yes"/>
  <xsl:preserve-space elements="x:span x:p"/>

  <xsl:param name="course_id"/>
  <!--
  <xsl:include href="properties_misc.xsl"/>
  <xsl:include href="properties_files.xsl"/>
  <xsl:include href="properties_translations.xsl"/>
  <xsl:include href="functions.xsl"/>
-->

  <xsl:variable name="uppercase" select="'ABCDEFGHIJKLMNOPQRSTUVWXYZ'"/>
  <xsl:variable name="lowercase" select="'abcdefghijklmnopqrstuvwxyz'"/>

  <xsl:template match="/">
    <xsl:apply-templates/>
  </xsl:template>
  
  <!-- Start: Identity transformation -->
  
  
  <xsl:template match="*">
    <xsl:copy>
      <xsl:apply-templates select="@*"/>
      <xsl:apply-templates select="*"/>
    </xsl:copy>
  </xsl:template>

  <xsl:template match="@*|comment()|processing-instruction()">
    <xsl:copy/>
  </xsl:template>
  <!-- End: Identity transformation -->
  
  <xsl:template match="text()">
    <xsl:value-of select="translate(., '&#x2009;', '&#x202f;')"/>
  </xsl:template>
  
  <!-- Remove empty class attributes -->
  <xsl:template match="@class[.='']"/>
  
  <!-- Remove redundant style information, retaining only borders and widths on table cells -->
  <xsl:template match="@style[not(parent::x:table)]" priority="1"/>


   <!-- Delete superfluous spans that wrap the complete para content -->
  <xsl:template match="x:span[count(.//node()[self::span]) = count(.//node())]" priority="2"/>

  <!-- Out go horizontal bars -->
  <xsl:template match="x:p[@class='HorizontalBar']"/>

  
  <!-- For character level formatting - bold, italic, subscript, superscript - use semantic HTML rather than
          CSS styling -->

  <!-- bold style becomes <strong> -->
  <xsl:template match="x:span">
    <xsl:choose>
      <xsl:when test="@class='Strong-H' or contains(@style, 'font-weight:bold')">
        <strong>
          <xsl:apply-templates select="." mode="italic"/>
        </strong>
      </xsl:when>
      <xsl:when test="@class='EquationInline-H'">
        <span class="equation-inline">
          <xsl:apply-templates select="." mode="italic"/>
        </span>
      </xsl:when>
      <xsl:otherwise>
        <xsl:apply-templates select="." mode="italic"/>
      </xsl:otherwise>
    </xsl:choose>
  </xsl:template>
  
  <!-- italic style becomes <em> -->
  <xsl:template match="x:span" mode="italic">
    <xsl:choose>
      <xsl:when test="@class='Emphasis-H' or contains(@style, 'font-style:italic')">
        <em class="italic">
          <xsl:apply-templates select="." mode="style"/>
        </em>
      </xsl:when>
      <xsl:otherwise>
          <xsl:apply-templates select="." mode="style"/>
      </xsl:otherwise>
    </xsl:choose>
  </xsl:template>

  <!-- text decoration style -->
  <xsl:template match="x:span" mode="style">
    <!-- Test for 'text-decoration:underline', keep it if present, hand the remainder off for further processing-->
    <xsl:variable name="text-dec" select="'text-decoration:'"/>
    <xsl:variable name="style-pre-text-dec" select="substring-before(@style, $text-dec)"/>
    <xsl:variable name="style-post-text-dec" select="substring-after(substring-after(@style, $text-dec), ';')"/>
    <xsl:if test="contains(@style, 'text-decoration:underline')">
      <xsl:value-of select="concat($text-dec, substring-before(substring-after(@style, $text-dec), ';'))"/>
    </xsl:if>

    <!-- Assemble remainder of style attribute -->
    <xsl:variable name="filteredStyles" select="concat($style-pre-text-dec, ';', $style-post-text-dec)"/>
    <xsl:choose>
      <xsl:when test="string-length($filteredStyles) &gt; 1">
        <span>
          <xsl:attribute name="style">
            <xsl:value-of select="$filteredStyles"/>
          </xsl:attribute>
          <xsl:apply-templates select="." mode="valign"/>
        </span>
      </xsl:when>
      <xsl:otherwise>
        <xsl:apply-templates select="." mode="valign"/>
      </xsl:otherwise>
    </xsl:choose>
  </xsl:template>

  <!-- subscript style becomes <sub> and superscript style becomes <super> -->
  <xsl:template match="x:span" mode="valign">
    <xsl:choose>
      <xsl:when test="contains(@style, 'vertical-align:sub;')">
        <sub>
          <xsl:apply-templates select="node()"/>
        </sub>
      </xsl:when>
      <xsl:when test="contains(@style, 'vertical-align:super;')">
        <sup>
          <xsl:apply-templates select="node()"/>
        </sup>
      </xsl:when>
      <xsl:otherwise>
          <xsl:apply-templates/>
      </xsl:otherwise>
    </xsl:choose>
  </xsl:template>

  <xsl:template match="x:div[@class = 'level0']">
    <xsl:copy>
      <xsl:for-each select="@*[name() != 'style']">
        <xsl:apply-templates select="."/>
      </xsl:for-each>

      <xsl:apply-templates/>
    </xsl:copy>
  </xsl:template>
  
  <!-- Convert the Heading1 style into a <h1> element (i.e. question Category) -->
  <xsl:template match="x:p[@class = 'Heading1']" priority="2">
    <div class="level1">
      <h1>
        <xsl:apply-templates select="node()"/>
      </h1>
    </div>
  </xsl:template>

  <!-- Convert the Heading2 style into a <h2> element (i.e. question Name), and wrap it and the following table into a div -->
  <xsl:template match="x:p[@class = 'Heading2']" priority="2">
    <div class="level2">
      <h2>
        <xsl:apply-templates select="node()"/>
      </h2>

      <!-- Grab the next table following, and put it inside the same div, introducing a simple hierarchy to group the question name and body-->
      <xsl:apply-templates select="following::x:table[contains(@class, 'moodleQuestion')][1]" mode="moodleQuestion"/>
    </div>
  </xsl:template>

  <!-- Handle question tables in moodleQuestion mode, to wrap them inside a div with the previous heading 2 (question name) -->
  <xsl:template match="x:table[contains(@class, 'moodleQuestion')]" mode="moodleQuestion">
    <table class="moodleQuestion">
      <xsl:apply-templates/>
    </table>
  </xsl:template>

  <!-- Delete question tables in normal processing, as they are grabbed by the previous heading 2 style -->
  <xsl:template match="x:table[contains(@class, 'moodleQuestion')]"/>


<!-- Handle simple unnested lists, as long as they use the explicit "List Number" or "List Bullet" styles -->

  <!-- Assemble numbered lists -->
  <xsl:template match="x:p[starts-with(@class, 'ListNumber')]" priority="2">
    <xsl:if test="not(starts-with(preceding-sibling::x:p[1]/@class, 'ListNumber'))">
      <!-- First item in a list, so wrap it in a ol, and drag in the rest of the items -->
      <ol>
        <li>
          <xsl:apply-templates/>
        </li>

        <!-- Recursively process following paragraphs until we hit one that isn't a list item -->
        <xsl:apply-templates select="following::x:p[1]" mode="listItem">
          <xsl:with-param name="listType" select="'ListNumber'"/>
        </xsl:apply-templates>
      </ol>
    </xsl:if>
    <!-- Silently ignore the item if it is not the first -->
  </xsl:template>

  <!-- Assemble bullet lists -->
  <xsl:template match="x:p[starts-with(@class, 'ListBullet')]" priority="2">
    <xsl:if test="not(starts-with(preceding-sibling::x:p[1]/@class, 'ListBullet'))">
      <!-- First item in a list, so wrap it in a ul, and drag in the rest of the items -->
      <ul>
        <li>
          <xsl:apply-templates/>
        </li>

        <!-- Recursively process following paragraphs until we hit one that isn't a list item -->
        <xsl:apply-templates select="following::x:p[1]" mode="listItem">
          <xsl:with-param name="listType" select="'ListBullet'"/>
        </xsl:apply-templates>
      </ul>
    </xsl:if>
    <!-- Silently ignore the item if it is not the first -->
  </xsl:template>

  <!-- Output a list item only if it has the right class -->
  <xsl:template match="x:p" mode="listItem">
    <xsl:param name="listType"/>

    <xsl:choose>
    <xsl:when test="starts-with(@class, $listType)">
      <li><xsl:comment>listItem</xsl:comment>
        <xsl:apply-templates/>
      </li>
        <!-- Recursively process following paragraphs until we hit one that isn't a list item -->
        <xsl:apply-templates select="following::x:p[1]" mode="listItem">
          <xsl:with-param name="listType" select="$listType"/>
        </xsl:apply-templates>
    </xsl:when>
    </xsl:choose>
  </xsl:template>

  <!-- Paragraphs -->
  <xsl:template match="x:p">
    <xsl:element name="p">

      <xsl:apply-templates select="node()"/>
    </xsl:element>
  </xsl:template>


  <!-- Delete any temporary ToC Ids to enable differences to be checked more easily, reduce clutter -->
  <xsl:template match="x:a[starts-with(translate(@name, 'ABCDEFGHIJKLMNOPQRSTUVWXYZ', 'abcdefghijklmnopqrstuvwxyz'), '_toc') and @class = 'bookmarkStart' and count(@*) =3 and not(node())]" priority="4"/>
  <!-- Delete any spurious OLE_LINK bookmarks that Word inserts -->
  <xsl:template match="x:a[starts-with(translate(@name, 'ABCDEFGHIJKLMNOPQRSTUVWXYZ', 'abcdefghijklmnopqrstuvwxyz'), 'ole_link') and @class = 'bookmarkStart']" priority="4"/>
  <xsl:template match="x:a[starts-with(translate(@name, 'ABCDEFGHIJKLMNOPQRSTUVWXYZ', 'abcdefghijklmnopqrstuvwxyz'), '_goback') and @class = 'bookmarkStart']" priority="4"/>

<xsl:template match="x:a">
  <xsl:variable name="context" select="concat('&quot;', ., ' &quot; {', @href, '}')"/>

  <!-- Check that hyperlinks don't start with a quotation character &#34;-->
  <xsl:if test="starts-with(@href, '&#34;')">
    <xsl:message>
      <xsl:value-of select="concat('Warning: &quot;', $inputFolder, '.docx&quot;; Hyperlink starts with &#34; character, context: ', $context)"/>
    </xsl:message>
  </xsl:if>

</xsl:template>


  <!-- Give index markers an id for navigation purposes -->
  <xsl:template match="x:a[@class = 'bookmarkStart']" priority="2">
    <xsl:copy>
      <xsl:apply-templates select="@*"/>
      <xsl:attribute name="id">
        <xsl:value-of select="concat('b', $course_id)"/>
        <xsl:number count="x:a[@class = 'bookmarkStart']" level="any" format="0001"/>
      </xsl:attribute>
    </xsl:copy>
  </xsl:template>
  
  <xsl:template match="x:a[@class='bookmarkEnd' and not(node())]" priority="2"/>
  <!-- This tries to handle the case where the Word file was saved with <Alt>+<F9> enabled to see field text, but it doesn't work.
  -->
  <xsl:template match="x:a[count(@*) = 1 and @href and not(starts-with(@href, 'PAGEREF')) and not(node())]"/>
  <xsl:template match="x:a[@href='\* MERGEFORMAT']" priority="2"/>
  
  <!-- Convert table body cells containing headings into th's -->
  <xsl:template match="x:td[contains(x:p[1]/@class, 'TableRowHead')]">
    <th>
      <xsl:apply-templates select="@*"/>
      <xsl:apply-templates select="*"/>
    </th>
  </xsl:template>

  <xsl:template match="@name[parent::x:a]">
    <xsl:attribute name="name">
      <xsl:value-of select="translate(., $uppercase, $lowercase)"/>
    </xsl:attribute>
  </xsl:template>

</xsl:stylesheet>
