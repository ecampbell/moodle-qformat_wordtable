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

// $Id: $

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



function xslt_create() {
       return new XSLTProcessor();
}

// We don't support arguments at present
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
