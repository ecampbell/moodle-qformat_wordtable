Release notes
-------------

Date          Version   Comment
2015/08/04    3.2.1     Grab extra memory to allow very large Word files to be imported,
                        fix Essay and All-or-Nothing question type issues, etc.
2015/08/01    3.2.0     Support All-or-Nothing Multiple Choice import and export
2015/07/27    3.1.11    Support question export for all built-in question types
2015/07/25    3.1.10    Support question import in Lessons
2015/07/17    3.1.9     Minor change to delete old and unused configuration values from config_plugins table
2015/07/15    3.1.8     Improve export formatting to handle paragraph attributes better (alignment, etc.)
2015/07/13    3.1.7     On import, retain the size of images as defined within Word, and retain text alignment
2015/07/04    3.1.6     Support the new 'Require text' field added to Essay questions in Moodle 2.9
2015/07/03    3.1.5     Improve export of far-eastern languages like Chinese and Japanese
2015/04/02    3.1.4     Fix text direction in non-Arabic Word templates, handle strikethrough better
2015/03/11    3.1.3     Fix image handling, including external images not embedded in Word file, and hyperlinked images
2015/03/03    3.1.2     Fix for Moodle 2.7/2.8 to improve handling of attachments in essay questions
2015/03/03    3.1.1     Fix for Moodle 2.7/2.8 to enable question import, by deleting superfluous namespace attributes
2015/03/02    3.1.0     Keep original uploaded Word file in debug mode. Fix handling of Cloze questions.
                        Handle hyperlinks and other character formatting properly, support questions created 
                        in Word language versions other than English, fix Cloze questions.
2015/02/20    3.0.0     Support import of Word 2010 (.docx) documents, remove all question import limits,
                        and use of an external conversion server.
                        Also add support for tables and lists inside item components.
