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
 * @package qformat_wordtable
 * @copyright 2010-2015 Eoin Campbell
 * @copyright 2005 Ziba Scott
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */


// This file is copied from moodle/backup/bb/xsl_emulate_xslt.inc

// This file adds xslt_xxx emulation functions.
// It is intended for systems, e.g. those running PHP 5, where:
// 1) The XSLT library is not installed.
// 2) The XSL library is installed.
//
// Note that not everything is implemented.
// In particular, only the bare minimum to support the BB conversion is here.

// This silliness is required to prevent PHP from evaluating the function() blocks before processing the return;s
if(true) {

    if(function_exists('xslt_create')) return;     // xslt_create() already exists, so emulation isn't needed.
    if(!class_exists('XSLTProcessor')) return;     // There is no XSLTProcessor class, so emulation isn't possible.
    if(!class_exists('DOMDocument')) return;       // There is no DOMDocument class, so emulation isn't possible.

    /**
     * Dummy function to call XSLT processor if not already defined
     *
     * @return XSLTProcessor object
     */
    function xslt_create() {
        return new XSLTProcessor();
    }


    /**
     * Mimic xslt_create function call using XSLTProcessor
     *
     * @param $proc XSLT object
     * @param $xmlfile XML file to process
     * @param $xslfile to process XML with
     * @param $resultfile containing output
     * @param $unsupported_args dummy
     * @param $parameters dummy
     * @return boolean true if transformation succeeded
     */
    function xslt_process($proc, $xmlfile, $xslfile, $resultfile = null, $unsupported_args = null, $parameters = null) {
        $doc = new DOMDocument;
        $doc->load($xmlfile);
        $xsl = new DOMDocument;
        $xsl->load($xslfile);

        // Set the html template file parameter
        foreach ($parameters as $param_name => $param_value) {
            $proc->setParameter('', $param_name, $param_value);
        }

        $proc->importStylesheet($xsl);

       // Squash warnings here because xsl complains about COURSE_ACCESS tags which really are invalid XML (multiple root elements)
       if($resultfile !== null) {
               $fp = fopen($resultfile, 'w');
               fwrite($fp, @$proc->transformToXML($doc));
               fclose($fp);
               return true;
       } else {
               return @$proc->transformToXML($doc);
       }
    }

} // end if(true)
