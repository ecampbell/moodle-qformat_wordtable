Release notes
-------------

Date          Version   Comment
2014/07/10    2.9.2     Fix syntax error in db/install.php
2014/07/10    2.9.1     Fix error in registration process set-up, which prevented registration to set 10-question limit.

2014/06/17    2.9       Use default username and password for importing Word files to make Registration optional.
                        Handle named numeric entities (e.g. &nbsp;) by converting them to numeric entities 
                        instead (i.e. &#160;) to avoid XSLT processing errors.

2014/05/12    2.8.5     Handle invalid 'complete="true"' attribute in images, fix loop through all CDATA 
                        sections, keep tr elements when cleaning HTML manually.

2014/05/07    2.8.4     Fix error in image handling in Moodle 1.9, add work-around to cope with XSLT 
                        idiosyncrasies that insert namespace declaration on wrong element, breaking the
                        Word export facility. Fix mishandling of Hints in Short Answer export.

2014/05/04    2.8.3     Improve image handling to properly export any images used inside feedback text.

2014/05/03    2.8.2     Handle images with names that include spaces or other non-alphanumeric characters.

2014/05/02    2.8.1     CONTRIB-5028: clean up HTML markup better (using strip_tags) if the PHP tidy 
                        extension is not installed.

2014/04/28    2.8       Use the PHP tidy extension to ensure that any HTML inside CDATA sections is well-formed
                        XHTML, otherwise Word export fails.

2014/02/27    2.7.1     Add Word export support for RTL languages such as Arabic and Hebrew.

2014/02/03    2.7       Add Word export support for languages other than English, using labels in the language
                        of the users' current interface language selection. Also support new Moodle 2.x
                        question features such as Hints and Tags.

2014/01/03    2.6       Add support for including any images used in questions exported into a Word file, in a
                        two-stage process that also requires using a command in the Moodle2Word Word template to
                        embed the encoded images into the file.

2013/12/20    2.5       Improve handling of CDATA text that includes HTML markup, and the Moodle 1.9 question
                        textancillary image element.

2013/03/15    2.4       Improve handling of Cloze question formatting when exporting to Word format.


Word Table Overview
-------------------

Moodle2Word is a plugin that allows quiz questions to be exported from Moodle into a Word file.
The Word file can then be used to quickly review large numbers of questions
(either online or in print), or to prepare paper tests (where the answers and feedback are hidden).

Moodle2Word also supports importing questions from structured tables in Word directly into the Moodle question database.
The tables support all the question components (stem, answer options, option-specific and general feedback, hints, tags
and question meta-data such as penalties grades and other options), as well as embedded images. 
All the main question types except Numerical and Calculated questions are supported.
Unregistered sites can import up to 5 questions, and registered sites 10.
To remove these limits an annual subscription is required.

The Cloze question syntax is particularly useful, as it does not require any knowledge of the
arcane Moodle syntax; instead, use bold for drop-down menu items, and italic for fill-in text fields.

Word templates to support the plugin can be downloaded from the demonstration website
www.Moodle2Word.net, and are available for Word 2002/XP, 2003, 2007 and 2010 (Windows),
and Word 2004 and 2011 (MacOSX). The Windows templates also support a simple question preview facility,
as well as uploading questions from within Word.

Exported questions contain metadata labelled in the language of the user, and the text is in paragraphs
with the spell-check language also set to the same language has the user has chosen for their Moodle interface.
Both left-to-right and right-to-left languages (such as Arabic and Hebrew) are supported.
