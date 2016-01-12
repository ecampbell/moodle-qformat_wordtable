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
 * Unit tests for the Moodle WordTable format.
 *
 * @package    qformat_wordtable
 * @copyright  2016 Eoin Campbell
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */


defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->libdir . '/questionlib.php');
require_once($CFG->dirroot . '/question/format/xml/format.php');
require_once($CFG->dirroot . '/question/format/wordtable/format.php');
require_once($CFG->dirroot . '/question/engine/tests/helpers.php');
require_once($CFG->dirroot . '/question/format/xml/tests/xmlformat_test.php');
require_once($CFG->dirroot . '/tag/lib.php');
require_once('helpers.php');


/**
 * Unit tests for the Word import/export class.
 *
 * @copyright  2009 The Open University
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class qformat_wordtable_test extends question_testcase {

    /**
     * Test if the imported XML input is the same as the expected XML (ignoring newlines).
     *
     * @param string $expectedxml as defined.
     * @param string $xml as returned by import pre-process.
     * @return mixed Boolean true/false, or some error indicator.
     */
    public function assert_same_xml($expectedxml, $xml) {
        $this->assertEquals(str_replace("\r\n", "\n", $expectedxml),
                str_replace("\r\n", "\n", $xml));
    }

    /**
     * Test if the exported HTML output is the same as the expected HTML (ignoring newlines).
     *
     * @param string $expectedhtml as defined.
     * @param string $html as returned by presave_process.
     * @return mixed Boolean true/false, or some error indicator.
     */
    public function assert_same_html($expectedhtml, $html) {
        // $html = str_replace("\r\n", "\n", substr($html, strpos($html, '<h2 ')));
        // $this->assertEquals(str_replace("\r\n", "\n", $expectedhtml), $html);
        $html = substr($html, strpos($html, '<h2 '));
        $this->assertEquals($expectedhtml, $html);
    }

    /**
     * Test if the imported XML for a Description question matches the expected content.
     */
    public function test_import_description() {
        $xml = '  <question type="description">
    <name>
      <text>A description</text>
    </name>
    <questiontext format="html">
      <text>The question text.</text>
    </questiontext>
    <generalfeedback>
      <text>Here is some general feedback.</text>
    </generalfeedback>
    <defaultgrade>0</defaultgrade>
    <penalty>0</penalty>
    <hidden>0</hidden>
    <tags>
      <tag><text>tagDescription</text></tag>
      <tag><text>tagTest</text></tag>
    </tags>
  </question>';
        $xmldata = xmlize($xml);

        $importer = new qformat_wordtable();
        $q = $importer->import_description($xmldata['question']);

        $expectedq = new stdClass();
        $expectedq->qtype = 'description';
        $expectedq->name = 'A description';
        $expectedq->questiontext = 'The question text.';
        $expectedq->questiontextformat = FORMAT_HTML;
        $expectedq->generalfeedback = 'Here is some general feedback.';
        $expectedq->defaultmark = 0;
        $expectedq->length = 0;
        $expectedq->penalty = 0;
        $expectedq->tags = array('tagDescription', 'tagTest');

        $this->assert(new question_check_specified_fields_expectation($expectedq), $q);
    }

    /**
     * Test if the exported HTML for a Description question matches the expected output.
     */
    public function test_export_description() {
        $description_xml = '<question type="description">
    <name>
      <text>A description</text>
    </name>
    <questiontext format="html">
      <text>The question text.</text>
    </questiontext>
    <generalfeedback format="html">
      <text>Here is some general feedback.</text>
    </generalfeedback>
    <defaultgrade>0</defaultgrade>
    <penalty>0</penalty>
    <hidden>0</hidden>
  </question>
';

        $expectedhtml = '<h2 class="MsoHeading2">A description</h2><p class="MsoBodyText"/><div class="TableDiv"><table border="1" dir="ltr"><thead>
<tr><td colspan="3" style="width: 12.0cm"><p class="Cell">The question text.</p></td><td style="width: 1.0cm"><p class="QFType">DE</p></td></tr>
<tr><td style="width: 1.0cm"><p class="Cell">' . "\xa0" . '</p></td><td style="width: 5.0cm"><p class="TableHead">' . "\xa0" . '</p></td><td style="width: 6.0cm"><p class="TableHead">' . "\xa0" . '</p></td><td style="width: 1.0cm"><p class="TableHead">' . "\xa0" . '</p></td></tr>
</thead><tbody>

<tr><td style="width: 1.0cm"><p class="Cell">' . "\xa0" . '</p></td><th style="width: 5.0cm"><p class="TableRowHead">Tags:</p></th><td style="width: 6.0cm"><p class="Cell">' . "\xa0" . '</p></td><td style="width: 1.0cm"><p class="Cell">' . "\xa0" . '</p></td></tr>
<tr><td colspan="3" style="width: 12.0cm"><p class="Cell"><i>This is not actually a question. Instead it is a way to add some instructions, rubric or other content to the activity. This is similar to the way that labels can be used to add content to the course page.</i></p></td><td style="width: 1.0cm"><p class="Cell">' . "\xa0" . '</p></td></tr>
</tbody></table></div><p class="MsoNormal">' . "\xa0" . '</p>
  </body>
</html>
';
        //$user = $this->getDataGenerator()->create_user();
        $this->setGuestUser();
        $exporter = new qformat_wordtable();
        $html = $exporter->presave_process($description_xml);

        $this->assert_same_html($expectedhtml, $html);
    }
}
