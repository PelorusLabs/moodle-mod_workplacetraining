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
 * Activity view page for the mod_workplacetraining plugin.
 *
 * @package    mod_workplacetraining
 * @copyright  Pelorus Labs
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use mod_workplacetraining\local\evaluation;

require(__DIR__ . '/../../config.php');

$id = required_param('id', PARAM_INT);
[$course, $cm] = get_course_and_cm_from_cmid($id, 'workplacetraining');
$instance = $DB->get_record('workplacetraining', ['id' => $cm->instance], '*', MUST_EXIST);

require_login($course, true, $cm);
$context = context_module::instance($cm->id);
require_capability('mod/workplacetraining:view', $context);

$workplacetraining = $DB->get_record('workplacetraining', ['id' => $instance->id], '*', MUST_EXIST);

// Get the active evaluation for this user.
$evaluation = evaluation::get_active_evaluation($workplacetraining->id, $USER->id);
if ($evaluation === false) {
    // No active evaluation exists - create a new one (version 1).
    $evaluation = evaluation::get_record_create_if_not_exists($workplacetraining->id, $USER->id);
}

$PAGE->set_url('/mod/workplacetraining/view.php', ['id' => $cm->id]);
$PAGE->set_title(format_string($workplacetraining->name));
$PAGE->set_heading(format_string($course->fullname));

$completion = new completion_info($course);
$completion->set_module_viewed($cm);

echo $OUTPUT->header();

$renderer = $PAGE->get_renderer('mod_workplacetraining');
echo $renderer->user_view($workplacetraining, $evaluation, $context);

echo $OUTPUT->footer();
