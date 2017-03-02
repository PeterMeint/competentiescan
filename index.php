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
 * Version details
 *
 * @package    report
 * @subpackage competentiescan
 * @copyright  2017 Spring Instituut
 * @author     Peter Meint Heida <peter.meint.heida@springinstituut.nl>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('REPORT_MAX_TABLE_SIZE', 100);
require_once(dirname(dirname(dirname(__FILE__))).'/config.php');
require_once(dirname(__FILE__).'/lib.php');
require_once($CFG->libdir.'/tablelib.php');

$course_module = required_param('cm', PARAM_INT); // Format to display/print

global $COURSE, $DB, $CFG;

$coursecontext = context_course::instance($COURSE->id);

// General settings for the pagecontent
$PAGE->set_url('/report/competentiescan/index.php', array());
$PAGE->set_title(get_string('title', 'report_competentiescan'));
$PAGE->set_heading(get_string('header', 'report_competentiescan'));
$PAGE->set_context($coursecontext);
$PAGE->set_pagelayout('incourse');

/*******************************************
 *  Creation of tables starts              *
 *******************************************
 *  Table for advantages not familiar with *
 *******************************************/
$table_cannot = new flexible_table('competentiescan_rapport_can_not');
$table_cannot->define_columns(array('question','explanation'));
$table_cannot->column_style_all('text-align', 'left');
$table_cannot->define_headers(array(get_string('can_not','report_competentiescan'),get_string('explanation','report_competentiescan')));
$table_cannot->define_baseurl($PAGE->url);
$table_cannot->is_downloadable(false);
$table_cannot->sortable(false);
$table_cannot->pageable(false);
$table_cannot->column_style('question','width','20%');
$table_cannot->column_style('explanation','width','80%');

/************************************************
 *  Table for advantages a little familiar with *
 ************************************************/
$table_can_a_little = new flexible_table('table_competentiescan_can_a_little');
$table_can_a_little->define_columns(array('question','explanation'));
$table_can_a_little->column_style_all('text-align', 'left');
$table_can_a_little->define_headers(array(get_string('can_a_little','report_competentiescan'),get_string('explanation','report_competentiescan')));
$table_can_a_little->define_baseurl($PAGE->url);
$table_can_a_little->is_downloadable(false);
$table_can_a_little->sortable(false);
$table_can_a_little->pageable(false);
$table_can_a_little->column_style('question','width','20%');
$table_can_a_little->column_style('explanation','width','80%');

/***************************************
 *  Table for advantages familiar with *
 ***************************************/
$table_can = new flexible_table('table_competentiescan_can');
$table_can->define_columns(array('question','explanation'));
$table_can->column_style_all('text-align', 'left');
$table_can->define_headers(array(get_string('can','report_competentiescan'),get_string('explanation','report_competentiescan')));
$table_can->define_baseurl($PAGE->url);
$table_can->is_downloadable(false);
$table_can->sortable(false);
$table_can->pageable(false);
$table_can->column_style('question','width','20%');
$table_can->column_style('explanation','width','80%');

/****************************************************************************************
 *  From the beginning tables are marked not filled and therefore will not be displayed *
 ****************************************************************************************/
$table_cannot_filled = FALSE;
$table_can_a_little_filled = FALSE;
$table_can_filled = FALSE;

echo $OUTPUT->header();

/*************************************************************************************
 *  Query for getting the results of the CompetentieScan attempt of the current user *
 *************************************************************************************/
$query = "SELECT qa.id, quest.id as quest_id, cm.id as cm_id, quiz.id as quiz_id, quiz.name as quiz_name, quest.name as quest_name, quest.questiontext as quest_text, qa.timemodified as tijd_antwoord, qa.responsesummary as antwoord
FROM 
mdl_course_modules as cm,
mdl_quiz as quiz,
mdl_quiz_slots as qs,
mdl_question as quest,
mdl_question_attempts as qa,
mdl_question_attempt_steps as qas
WHERE cm.id = ?
AND cm.instance = quiz.id
AND quiz.id = qs.quizid
AND quest.id IN (SELECT quest2.id from mdl_question as quest2 WHERE qs.questionid = quest2.id OR qs.questionid = quest2.parent)
AND qa.questionid = quest.id
AND qas.questionattemptid = qa.id
AND qas.userid = ".$USER->id;

$records = $DB->get_records_sql($query,array($course_module));

/*************************************
 *  Setup all tables before filling  *
 *************************************/
$table_cannot->setup();
$table_can_a_little->setup();
$table_can->setup();

/********************************************************************************
 *  Loop to retrieve the answers and store the them in the corresponding table  *
 ********************************************************************************/
foreach ($records as $record) {
    // Get the number of subquestions by checking the question string
    $aantal_subvragen = substr_count($record->quest_text,'{#');

    // When there are subquestions, check if the subquestions can be found in the database
    if ($aantal_subvragen) {
        $child_questions = $DB->get_records('question', array('parent' => $record->quest_id));
        $nr_child_questions = count($child_questions);

        // If the number of subquestions matches with the database continue processing
        if ($aantal_subvragen == $nr_child_questions) {

            // Loop through the subquestions and retrieve the answers
            for($i = 1; $i <= $aantal_subvragen; $i++) {
                $start_pos = strpos($record->antwoord,"deel ".$i);

                if ($start_pos !== FALSE) {
                    $nr_chars_to_replace = strlen("deel ".$i.": ");
                    $record->antwoord = substr_replace($record->antwoord,'',$start_pos,$nr_chars_to_replace);

                    $eind_pos = strpos($record->antwoord,';');
                    if ($eind_pos !== FALSE) {
                        $record->antwoord = substr_replace($record->antwoord,'<br>',$eind_pos,2);
                    }
                }
            }
            // If the number of subquestions does not match with the database report an error
        } else {
            echo get_string('error_subquestions','report_competentiescan');
            $table_cannot_filled = FALSE;
            $table_can_a_little_filled = FALSE;
            $table_can_filled = FALSE;
            break;
        }

    }
    // Split the answer in choice and comment
    $antwoord = strstr($record->antwoord,'<br>',TRUE);
    $opmerking = strip_tags(strstr($record->antwoord,'<br>'), '<br \>');

    // Fill the the table corresponding with the choice and add the comment
    // Further mark that the table is filled with data
    switch ($antwoord) {
        case get_string('can_not','report_competentiescan'): $table_cannot->add_data(array('vraag' => $record->quest_name, 'antwoord' => $opmerking));
            $table_cannot_filled = TRUE;
            break;
        case get_string('can_a_little','report_competentiescan'): $table_can_a_little->add_data(array('vraag' => $record->quest_name, 'antwoord' => $opmerking));
            $table_can_a_little_filled = TRUE;
            break;
        case get_string('can','report_competentiescan'): $table_can->add_data(array('vraag' => $record->quest_name, 'antwoord' => $opmerking));
            $table_can_filled = TRUE;
            break;
    }
}

// If there is data in the table, then display the table
if ($table_cannot_filled) {
    $table_cannot->setup();
    $table_cannot->finish_output();
}
if ($table_can_a_little_filled) {
    $table_can_a_little->setup();
    $table_can_a_little->finish_output();
}
if ($table_can_filled) {
    $table_can->setup();
    $table_can->finish_output();
}
if(!$table_cannot_filled AND !$table_can_a_little_filled AND !$table_can_filled) {
    echo get_string('error_no_results', 'report_competentiescan');
}

echo $OUTPUT->footer();