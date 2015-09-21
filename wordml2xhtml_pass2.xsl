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
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.    See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.    If not, see <http://www.gnu.org/licenses/>.

 * XSLT stylesheet to transform rough XHTML derived from Word 2010 files into a more hierarchical format with divs wrapping each heading and table (question name and item)
 *
 * @package qformat_wordtable
 * @copyright 2010-2015 Eoin Campbell
 * @author Eoin Campbell
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later (5)
-->

<xsl:stylesheet
    xmlns="http://www.w3.org/1999/xhtml"
    xmlns:x="http://www.w3.org/1999/xhtml"
    xmlns:xsl="http://www.w3.org/1999/XSL/Transform"
    xmlns:mml="http://www.w3.org/1998/Math/MathML"
    exclude-result-prefixes="x"
    version="1.0">
    <xsl:output method="xml" encoding="UTF-8" indent="no" omit-xml-declaration="yes"/>
    <xsl:preserve-space elements="x:span x:p"/>

    <xsl:param name="debug_flag" select="0"/>
    <xsl:param name="course_id"/>

    <xsl:variable name="uppercase" select="'ABCDEFGHIJKLMNOPQRSTUVWXYZ'"/>
    <xsl:variable name="lowercase" select="'abcdefghijklmnopqrstuvwxyz'"/>
    <!-- Output a newline before paras and cells when debugging turned on -->
    <xsl:variable name="debug_newline">
        <xsl:if test="$debug_flag &gt;= 1">
            <xsl:value-of select="'&#x0a;'"/>
        </xsl:if>
    </xsl:variable>

    <xsl:template match="/">
        <xsl:apply-templates/>
    </xsl:template>
    
    <!-- Start: Identity transformation -->
    <xsl:template match="*">
        <xsl:copy>
            <xsl:apply-templates select="@*"/>
            <xsl:apply-templates/>
        </xsl:copy>
    </xsl:template>

    <xsl:template match="@*|comment()|processing-instruction()">
        <xsl:copy/>
    </xsl:template>
    <!-- End: Identity transformation -->
    
    <xsl:template match="text()">
        <xsl:value-of select="translate(., '&#x2009;', '&#x202f;')"/>
    </xsl:template>
    
    <xsl:template match="@mathvariant"/>

    <!-- Remove empty class attributes -->
    <xsl:template match="@class[.='']"/>
    
    <!-- Remove redundant style information, retaining only borders and widths on table cells, and text direction in paragraphs-->
    <xsl:template match="@style[not(parent::x:table) and not(contains(., 'direction:'))]" priority="1"/>


     <!-- Delete superfluous spans that wrap the complete para content -->
    <xsl:template match="x:span[count(.//node()[self::span]) = count(.//node())]" priority="2"/>

    <!-- Out go horizontal bars -->
    <xsl:template match="x:p[@class='horizontalbar']"/>


    <!-- For character level formatting - bold, italic, subscript, superscript - use semantic HTML rather than CSS styling -->
    <!-- Convert style properties inside span element to elements instead -->
    <xsl:template match="x:span[@style]">
        <xsl:apply-templates select="." mode="styleProperty">
            <xsl:with-param name="styleProperty" select="@style"/>
        </xsl:apply-templates>
    </xsl:template>

    <!-- Span elements that contain only the class attribute are usually used for named character styles like Hyperlink, Strong and Emphasis -->
    <xsl:template match="x:span[@class and count(@*) = 1]">
        <xsl:apply-templates select="." mode="styleProperty">
            <xsl:with-param name="styleProperty" select="concat(@class, ';')"/>
        </xsl:apply-templates>
    </xsl:template>

    <!-- Recursive loop to convert style properties inside span element to elements instead -->
    <xsl:template match="x:span" mode="styleProperty">
        <xsl:param name="styleProperty"/>

        <!-- Get the first property in the list -->
        <xsl:variable name="stylePropertyFirst">
            <xsl:choose>
            <xsl:when test="contains($styleProperty, ';')">
                <xsl:value-of select="substring-before($styleProperty, ';')"/>
            </xsl:when>
            <xsl:otherwise>
            </xsl:otherwise>
            </xsl:choose>
        </xsl:variable>

        <!-- Get the remaining properties for passing on in recursive loop-->
        <xsl:variable name="stylePropertyRemainder">
            <xsl:choose>
            <xsl:when test="contains($styleProperty, ';')">
                <xsl:value-of select="substring-after($styleProperty, ';')"/>
            </xsl:when>
            <xsl:otherwise>
            </xsl:otherwise>
            </xsl:choose>
        </xsl:variable>

        <xsl:call-template name="debugComment">
            <xsl:with-param name="comment_text" select="concat('$stylePropertyRemainder = ', $stylePropertyRemainder, '; $stylePropertyFirst = ', $stylePropertyFirst)"/>
            <xsl:with-param name="inline" select="'true'"/>
            <xsl:with-param name="condition" select="contains($styleProperty, '-H') and $debug_flag &gt;= 2"/>
        </xsl:call-template>
        <xsl:choose>
        <xsl:when test="$styleProperty = ''">
            <!-- No styles left, so just process the children in the normal way -->
            <xsl:apply-templates select="node()"/>
        </xsl:when>
        <xsl:when test="$stylePropertyFirst = 'font-weight:bold' or $stylePropertyFirst = 'Strong-H'">
            <!-- Convert bold style to strong element -->
            <strong>
                <xsl:apply-templates select="." mode="styleProperty">
                    <xsl:with-param name="styleProperty" select="$stylePropertyRemainder"/>
                </xsl:apply-templates>
            </strong>
        </xsl:when>
        <xsl:when test="$stylePropertyFirst = 'font-style:italic' or $stylePropertyFirst = 'Emphasis-H'">
            <!-- Convert italic style to emphasis element -->
            <em>
                <xsl:apply-templates select="." mode="styleProperty">
                    <xsl:with-param name="styleProperty" select="$stylePropertyRemainder"/>
                </xsl:apply-templates>
            </em>
        </xsl:when>
        <xsl:when test="$stylePropertyFirst = 'text-decoration:underline' and (@class = 'Hyperlink-H' or @class = 'hyperlink-h')">
            <!-- Ignore underline style if it is in a hyperlink-->
            <xsl:apply-templates select="." mode="styleProperty">
                <xsl:with-param name="styleProperty" select="$stylePropertyRemainder"/>
            </xsl:apply-templates>
        </xsl:when>
        <xsl:when test="$stylePropertyFirst = 'text-decoration:underline'">
            <!-- Convert underline style to u element -->
            <u>
                <xsl:apply-templates select="." mode="styleProperty">
                    <xsl:with-param name="styleProperty" select="$stylePropertyRemainder"/>
                </xsl:apply-templates>
            </u>
        </xsl:when>
        <xsl:when test="$stylePropertyFirst = 'vertical-align:super'">
            <!-- Only superscript style present so no need for further x:span processing, and omit x:span element -->
            <sup>
                <xsl:apply-templates select="." mode="styleProperty">
                    <xsl:with-param name="styleProperty" select="$stylePropertyRemainder"/>
                </xsl:apply-templates>
            </sup>
        </xsl:when>
        <xsl:when test="$stylePropertyFirst = 'vertical-align:sub'">
            <!-- Only subscript style present so no need for further x:span processing, and omit x:span element -->
            <sub>
                <xsl:apply-templates select="." mode="styleProperty">
                    <xsl:with-param name="styleProperty" select="$stylePropertyRemainder"/>
                </xsl:apply-templates>
            </sub>
        </xsl:when>
        <xsl:when test="starts-with($stylePropertyFirst, 'direction:')">
            <!-- Handle inline text direction directive-->
            <xsl:variable name="textDirection" select="substring-after($stylePropertyFirst, 'direction:')"/>
            <span dir="{$textDirection}">
                <xsl:apply-templates select="." mode="styleProperty">
                    <xsl:with-param name="styleProperty" select="$stylePropertyRemainder"/>
                </xsl:apply-templates>
            </span>
        </xsl:when>
        <xsl:when test="$stylePropertyFirst = 'font-size:smaller' or $stylePropertyFirst = 'font-size:11pt' or $stylePropertyFirst = 'font-size:12pt' or $stylePropertyFirst = 'font-size:13pt' or $stylePropertyFirst = 'font-style:normal' or $stylePropertyFirst = 'font-weight:normal' or $stylePropertyFirst = 'font-size:1pt' or $stylePropertyFirst = 'unicode-bidi:embed'">
            <!-- Ignore smaller font size style, as it is only in sub and superscripts; ignore some odd styles in Arabic samples -->
            <xsl:apply-templates select="." mode="styleProperty">
                <xsl:with-param name="styleProperty" select="$stylePropertyRemainder"/>
            </xsl:apply-templates>
        </xsl:when>
        <xsl:otherwise>
            <!-- Keep any remaining styles, such as strikethrough or font size changes, using a span element with a style attribute containing only those styles not already handled -->
            <!--<xsl:comment><xsl:value-of select="concat('$stylePropertyRemainder = ', $stylePropertyRemainder, '; $stylePropertyFirst = ', $stylePropertyFirst)"/></xsl:comment>-->
            <span>
                <xsl:for-each select="@*">
                    <xsl:choose>
                    <xsl:when test="name() = 'style'">
                        <xsl:attribute name="style">
                            <xsl:value-of select="$stylePropertyFirst"/>
                        </xsl:attribute>
                    </xsl:when>
                    <xsl:otherwise>
                        <xsl:attribute name="{name()}">
                            <xsl:value-of select="."/>
                        </xsl:attribute>
                    </xsl:otherwise>
                    </xsl:choose>
                </xsl:for-each>
                <xsl:apply-templates select="." mode="styleProperty">
                    <xsl:with-param name="styleProperty" select="$stylePropertyRemainder"/>
                </xsl:apply-templates>
            </span>
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
    <xsl:template match="x:p[@class = 'heading1']" priority="2">
        <div class="level1">
            <h1>
                <xsl:apply-templates select="node()"/>
            </h1>
        </div>
    </xsl:template>

    <!-- Convert the Heading2 style into a <h2> element (i.e. question Name), and wrap it and the following table into a div -->
    <xsl:template match="x:p[@class = 'heading2']" priority="2">
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
        <xsl:value-of select="$debug_newline"/>
        <table class="moodleQuestion">
            <xsl:apply-templates/>
        </table>
    </xsl:template>

    <!-- Delete question tables in normal processing, as they are grabbed by the previous heading 2 style -->
    <xsl:template match="x:table[contains(@class, 'moodleQuestion')]"/>


<!-- Handle simple unnested lists, as long as they use the explicit "List Number" or "List Bullet" styles -->

    <!-- Assemble numbered lists -->
    <xsl:template match="x:p[starts-with(@class, 'listnumber')]" priority="2">
        <xsl:if test="not(starts-with(preceding-sibling::x:p[1]/@class, 'listnumber'))">
            <!-- First item in a list, so wrap it in a ol, and drag in the rest of the items -->
            <ol>
                <li>
                    <xsl:apply-templates/>
                </li>

                <!-- Recursively process following paragraphs until we hit one that isn't a list item -->
                <xsl:apply-templates select="following::x:p[1]" mode="listItem">
                    <xsl:with-param name="listType" select="'listnumber'"/>
                </xsl:apply-templates>
            </ol>
        </xsl:if>
        <!-- Silently ignore the item if it is not the first -->
    </xsl:template>

    <!-- Assemble bullet lists -->
    <xsl:template match="x:p[starts-with(@class, 'listbullet')]" priority="2">
        <xsl:if test="not(starts-with(preceding-sibling::x:p[1]/@class, 'listbullet'))">
            <!-- First item in a list, so wrap it in a ul, and drag in the rest of the items -->
            <xsl:value-of select="$debug_newline"/>
            <ul>
                <xsl:value-of select="$debug_newline"/>
                <li>
                    <xsl:apply-templates/>
                </li>

                <!-- Recursively process following paragraphs until we hit one that isn't a list item -->
                <xsl:apply-templates select="following::x:p[1]" mode="listItem">
                    <xsl:with-param name="listType" select="'listbullet'"/>
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
            <xsl:value-of select="$debug_newline"/>
            <li>
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
        <p>
            <!-- Keep text direction if specified -->
            <xsl:if test="contains(@style, 'direction:')">
                <xsl:attribute name="dir">
                    <xsl:value-of select="substring-before(substring-after(@style, 'direction:'), ';')"/>
                </xsl:attribute>
            </xsl:if>
            <!-- Keep text alignment if specified -->
            <xsl:if test="contains(@style, 'text-align:')">
                <xsl:attribute name="style">
                    <xsl:value-of select="concat('text-align:', substring-before(substring-after(@style, 'text-align:'), ';'))"/>
                </xsl:attribute>
            </xsl:if>

            <xsl:apply-templates select="node()"/>
        </p>
    </xsl:template>


    <!-- Delete any temporary ToC Ids to enable differences to be checked more easily, reduce clutter -->
    <xsl:template match="x:a[starts-with(translate(@name, 'ABCDEFGHIJKLMNOPQRSTUVWXYZ', 'abcdefghijklmnopqrstuvwxyz'), '_toc') and @class = 'bookmarkStart' and count(@*) =3 and not(node())]" priority="4"/>
    <!-- Delete any spurious OLE_LINK bookmarks that Word inserts -->
    <xsl:template match="x:a[starts-with(translate(@name, 'ABCDEFGHIJKLMNOPQRSTUVWXYZ', 'abcdefghijklmnopqrstuvwxyz'), 'ole_link') and @class = 'bookmarkStart']" priority="4"/>
    <xsl:template match="x:a[starts-with(translate(@name, 'ABCDEFGHIJKLMNOPQRSTUVWXYZ', 'abcdefghijklmnopqrstuvwxyz'), '_goback') and @class = 'bookmarkStart']" priority="4"/>

    <xsl:template match="x:a[@class='bookmarkEnd' and not(node())]" priority="2"/>
    <xsl:template match="x:a[@href='\* MERGEFORMAT']" priority="2"/>
    
    <!-- Convert table body cells containing headings into th's -->
    <xsl:template match="x:td[contains(x:p[1]/@class, 'tablerowhead')]">
        <xsl:value-of select="$debug_newline"/>
        <th>
            <xsl:apply-templates select="@*"/>
            <xsl:apply-templates select="*"/>
        </th>
    </xsl:template>

    <!-- Table cells -->
    <xsl:template match="x:td">
        <xsl:value-of select="$debug_newline"/>
        <td>
            <xsl:apply-templates select="node()"/>
        </td>
    </xsl:template>

    <xsl:template match="@name[parent::x:a]">
        <xsl:attribute name="name">
            <xsl:value-of select="translate(., $uppercase, $lowercase)"/>
        </xsl:attribute>
    </xsl:template>

<!-- Include debugging information in the output -->
<xsl:template name="debugComment">
    <xsl:param name="comment_text"/>
    <xsl:param name="inline" select="'false'"/>
    <xsl:param name="condition" select="'true'"/>

    <xsl:if test="boolean($condition) and $debug_flag &gt;= 1">
        <xsl:if test="$inline = 'false'"><xsl:text>&#x0a;</xsl:text></xsl:if>
        <xsl:comment><xsl:value-of select="concat('Debug: ', $comment_text)"/></xsl:comment>
        <xsl:if test="$inline = 'false'"><xsl:text>&#x0a;</xsl:text></xsl:if>
    </xsl:if>
</xsl:template>
</xsl:stylesheet>