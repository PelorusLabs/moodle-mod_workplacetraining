<?php
// This file is part of Moodle - https://moodle.org/
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
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

namespace mod_workplacetraining\reportbuilder\local\entities;

use coding_exception;
use core\output\html_writer;
use core_reportbuilder\local\entities\base;
use core_reportbuilder\local\filters\date;
use core_reportbuilder\local\filters\number;
use core_reportbuilder\local\filters\select;
use core_reportbuilder\local\helpers\database;
use core_reportbuilder\local\helpers\format;
use core_reportbuilder\local\report\column;
use core_reportbuilder\local\report\filter;
use lang_string;
use mod_workplacetraining\output\renderer;
use moodle_url;
use stdClass;

/**
 * Evaluation entity class implementation
 *
 * @package    mod_workplacetraining
 * @copyright  Pelorus Labs
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class evaluation extends base {
    /** @var int cmid Workplace training course module id */
    private $cmid;
    /** @var int wtid Workplace training id */
    private $wtid;

    /**
     * Construct the entity
     *
     * @param int $cmid
     * @param int $wtid
     */
    public function __construct(int $cmid, int $wtid) {
        $this->cmid = $cmid;
        $this->wtid = $wtid;
    }

    /**
     * Default tables used by the entity
     *
     * @return string[]
     */
    protected function get_default_tables(): array {
        return [
            'user_enrolments',
            'enrol',
            'role',
            'role_assignments',
            'context',
            'workplacetraining_evaluations',
            'workplacetraining',
            'course_modules',
        ];
    }

    /**
     * Default entity title
     *
     * @return lang_string
     */
    protected function get_default_entity_title(): lang_string {
        return new lang_string('entityevaluation', 'mod_workplacetraining');
    }

    /**
     * Initialise the entity
     *
     * @return base
     */
    public function initialise(): base {
        $columns = $this->get_all_columns();
        foreach ($columns as $column) {
            $this->add_column($column);
        }

        // All the filters defined by the entity can also be used as conditions.
        $filters = $this->get_all_filters();
        foreach ($filters as $filter) {
            $this
                ->add_filter($filter)
                ->add_condition($filter);
        }

        return $this;
    }

    /**
     * Get all columns for the entity
     *
     * @return array
     */
    protected function get_all_columns(): array {
        $evaluationsalias = $this->get_table_alias('workplacetraining_evaluations');
        $userenrolmentsalias = $this->get_table_alias('user_enrolments');
        $enrolalias = $this->get_table_alias('enrol');
        $rolealias = $this->get_table_alias('role');
        $roleassignmentalias = $this->get_table_alias('role_assignments');
        $contextalias = $this->get_table_alias('context');
        $coursemodulealias = $this->get_table_alias('course_modules');
        $workplacetrainingalias = $this->get_table_alias('workplacetraining');

        $this->add_join(
            "LEFT JOIN {workplacetraining_evaluations} {$evaluationsalias} ON
                            {$evaluationsalias}.userid = {$userenrolmentsalias}.userid
                            AND {$evaluationsalias}.active = 1
                            AND {$evaluationsalias}.wtid = {$this->wtid}"
        );
        $this->add_join(
            "JOIN {workplacetraining} {$workplacetrainingalias} ON {$workplacetrainingalias}.id = {$this->wtid}
                 JOIN {course_modules} {$coursemodulealias} ON
                    {$coursemodulealias}.id = {$this->cmid}
                    AND {$coursemodulealias}.instance = {$this->wtid}"
        );

        $this->add_join("INNER JOIN {enrol} {$enrolalias} ON {$enrolalias}.id = {$userenrolmentsalias}.enrolid
                            AND {$enrolalias}.courseid = {$coursemodulealias}.course");

        $this->add_join("JOIN {context} {$contextalias} ON {$contextalias}.instanceid = {$enrolalias}.courseid
                        AND {$contextalias}.contextlevel = " . CONTEXT_COURSE . "
                     JOIN {role_assignments} {$roleassignmentalias} ON {$roleassignmentalias}.contextid = {$contextalias}.id
                        AND {$roleassignmentalias}.userid = {$userenrolmentsalias}.userid
                     JOIN {role} {$rolealias} ON {$rolealias}.id = {$roleassignmentalias}.roleid");

        // Add completion data join.
        $responsealias = database::generate_alias();
        $sectionitemalias = database::generate_alias();
        $sectionalias = database::generate_alias();
        $wtevaluationsalias = database::generate_alias();
        $completiondatalias = database::generate_alias();
        $this->add_join("
                        LEFT JOIN (
                    SELECT {$responsealias}.userid, COUNT(DISTINCT {$responsealias}.itemid) as completed_count
                    FROM {workplacetraining_responses} $responsealias
                    JOIN {workplacetraining_section_items} {$sectionitemalias} ON {$sectionitemalias}.id = {$responsealias}.itemid
                    JOIN {workplacetraining_sections} {$sectionalias} ON {$sectionalias}.id = {$sectionitemalias}.sectionid
                        AND {$sectionalias}.wtid = {$this->wtid}
                    JOIN {workplacetraining_evaluations} {$wtevaluationsalias} ON
                        {$wtevaluationsalias}.userid = {$responsealias}.userid
                        AND {$responsealias}.version = {$wtevaluationsalias}.version
                        AND {$wtevaluationsalias}.active = 1
                        AND {$wtevaluationsalias}.wtid = {$sectionalias}.wtid
                    WHERE {$sectionitemalias}.isrequired = 1
                    AND {$responsealias}.completed = 1
                    GROUP BY {$responsealias}.userid
                ) $completiondatalias ON $completiondatalias.userid = {$evaluationsalias}.userid
        ");

        $columns[] = (new column(
            'finalised',
            new lang_string('finalised', 'mod_workplacetraining'),
            $this->get_entity_name(),
        ))
            ->add_joins($this->get_joins())
            ->set_type(column::TYPE_BOOLEAN)
            ->add_field("{$evaluationsalias}.finalised")
            ->add_field("{$evaluationsalias}.timefinalised")
            ->add_field("{$evaluationsalias}.finalisedby")
            ->add_callback(static function (?bool $value) {
                return $value ?? false;
            })
            ->add_callback([self::class, 'format_finalised'])
            ->set_is_sortable(true);

        $columns[] = (new column(
            'version',
            new lang_string('version', 'mod_workplacetraining'),
            $this->get_entity_name()
        ))
            ->add_joins($this->get_joins())
            ->set_type(column::TYPE_INTEGER)
            ->add_field("{$evaluationsalias}.version")
            ->add_callback(static function (?int $value) {
                return $value ?? 1;
            })
            ->set_is_sortable(true);

        $columns[] = (new column(
            'active',
            new lang_string('active', 'mod_workplacetraining'),
            $this->get_entity_name()
        ))
            ->add_joins($this->get_joins())
            ->set_type(column::TYPE_BOOLEAN)
            ->add_field("{$evaluationsalias}.active")
            ->add_callback(static function (?bool $value) {
                return $value ?? false;
            })
            ->add_callback([format::class, 'boolean_as_text'])
            ->set_is_sortable(true);

        $columns[] = (new column(
            'timecreated',
            new lang_string('timecreated', 'core_reportbuilder'),
            $this->get_entity_name()
        ))
            ->add_joins($this->get_joins())
            ->set_type(column::TYPE_TIMESTAMP)
            ->add_field("{$evaluationsalias}.timecreated")
            ->set_is_sortable(true);

        $columns[] = (new column(
            'timemodified',
            new lang_string('timemodified', 'core_reportbuilder'),
            $this->get_entity_name()
        ))
            ->add_joins($this->get_joins())
            ->set_type(column::TYPE_TIMESTAMP)
            ->add_field("{$evaluationsalias}.timemodified")
            ->set_is_sortable(true);

        $columns[] = (new column(
            'timefinalised',
            new lang_string('timefinalised', 'mod_workplacetraining'),
            $this->get_entity_name()
        ))
            ->add_joins($this->get_joins())
            ->set_type(column::TYPE_TIMESTAMP)
            ->add_field("{$evaluationsalias}.timefinalised")
            ->set_is_sortable(true);

        $columns[] = (new column(
            'evaluatelink',
            new lang_string('evaluatelink', 'mod_workplacetraining'),
            $this->get_entity_name()
        ))
            ->add_joins($this->get_joins())
            ->set_type(column::TYPE_TEXT)
            ->add_field("{$userenrolmentsalias}.userid")
            ->add_field("{$coursemodulealias}.id")
            ->set_callback([self::class, 'format_evaluatelink'])
            ->set_is_sortable(false);

        $columns[] = (new column(
            'completionstatus',
            new lang_string('completionstatus', 'mod_workplacetraining'),
            $this->get_entity_name()
        ))
            ->add_joins($this->get_joins())
            ->set_type(column::TYPE_TEXT)
            ->add_field("{$completiondatalias}.completed_count")
            ->add_field("{$userenrolmentsalias}.userid")
            ->add_field("{$workplacetrainingalias}.id")
            ->set_callback([self::class, 'format_completion_status'])
            ->set_is_sortable(true);

        return $columns;
    }

    /**
     * Get all filters for the entity
     *
     * @return array
     */
    protected function get_all_filters(): array {
        $evaluationsalias = $this->get_table_alias('workplacetraining_evaluations');

        $filters[] = (new filter(
            select::class,
            'finalised',
            new lang_string('finalised', 'mod_workplacetraining'),
            $this->get_entity_name(),
            "COALESCE({$evaluationsalias}.finalised, 0)"
        ))
            ->add_joins($this->get_joins())
            ->set_options([
                0 => new lang_string('no'),
                1 => new lang_string('yes'),
            ]);

        $filters[] = (new filter(
            date::class,
            'timecreated',
            new lang_string('timecreated', 'core_reportbuilder'),
            $this->get_entity_name(),
            "{$evaluationsalias}.timecreated"
        ))
            ->add_joins($this->get_joins());

        $filters[] = (new filter(
            date::class,
            'timemodified',
            new lang_string('timemodified', 'core_reportbuilder'),
            $this->get_entity_name(),
            "{$evaluationsalias}.timemodified"
        ))
            ->add_joins($this->get_joins());

        $filters[] = (new filter(
            date::class,
            'timefinalised',
            new lang_string('timefinalised', 'mod_workplacetraining'),
            $this->get_entity_name(),
            "{$evaluationsalias}.timefinalised"
        ))
            ->add_joins($this->get_joins());

        $filters[] = (new filter(
            number::class,
            'version',
            new lang_string('version', 'mod_workplacetraining'),
            $this->get_entity_name(),
            "COALESCE({$evaluationsalias}.version, 1)"
        ))
            ->add_joins($this->get_joins());

        $filters[] = (new filter(
            select::class,
            'active',
            new lang_string('active', 'mod_workplacetraining'),
            $this->get_entity_name(),
            "{$evaluationsalias}.active"
        ))
            ->add_joins($this->get_joins())
            ->set_options([
                0 => new lang_string('no'),
                1 => new lang_string('yes'),
            ]);

        return $filters;
    }

    /**
     * Get the course object for the current report.
     *
     * @return bool|\stdClass
     */
    private function get_course_helper(): bool|\stdClass {
        global $COURSE, $PAGE;

        if (!empty($COURSE) && $COURSE->id != SITEID) {
            return $COURSE;
        }

        // See if $PAGE is set, and if it relates to a course context.
        if (!empty($PAGE)) {
            try { // Don't trigger an exception if we can't get a coursecontext.
                $coursecontext = $PAGE->context->get_course_context(false);
                if (!empty($coursecontext) && !empty($coursecontext->instanceid) && $coursecontext->instanceid != SITEID) {
                    return get_course($coursecontext->instanceid);
                }
                // @codingStandardsIgnoreStart
            } catch (coding_exception $e) {
            }
            // @codingStandardsIgnoreEnd
        }

        return false;
    }

    /**
     * Returns formatted evaluate link
     *
     * @param string|null $value Unix timestamp
     * @param stdClass $row
     * @param string|null $format Format string for strftime
     * @return string
     */
    public static function format_evaluatelink(?string $value, stdClass $row, ?string $format = null): string {
        $evaluatelink = new moodle_url("/mod/workplacetraining/evaluate.php", ['cmid' => $row->id, 'userid' => $row->userid]);
        return html_writer::link($evaluatelink, get_string('evaluatelink', 'mod_workplacetraining'));
    }

    /**
     * Returns formatted finalised status
     *
     * @param bool|null $value
     * @param stdClass $row
     * @param string|null $format
     * @return string
     */
    public static function format_finalised(?bool $value, stdClass $row, ?string $format = null): string {
        global $DB;

        if ($row->finalised) {
            $finalisedby = $DB->get_record('user', ['id' => $row->finalisedby], '*', MUST_EXIST);

            return get_string(
                'finalisedby',
                'workplacetraining',
                ['name' => fullname($finalisedby),
                    'datetime' => userdate($row->timefinalised, get_string('strftimedatemonthabbr', 'langconfig'))]
            );
        } else {
            return get_string('unfinalised', 'workplacetraining');
        }
    }

    /**
     * Format completion status for evaluation
     *
     * @param string|null $value
     * @param stdClass $row
     * @param string|null $format
     * @return string
     */
    public static function format_completion_status(?string $value, stdClass $row, ?string $format = null): string {
        global $DB;

        $sql = "SELECT COUNT(si.id) as total
                FROM {workplacetraining_section_items} si
                JOIN {workplacetraining_sections} s ON s.id = si.sectionid
                WHERE s.wtid = :wtid AND si.isrequired = 1";
        $totalrequired = (int) $DB->get_field_sql($sql, ['wtid' => $row->id]);

        $completedcount = $row->completed_count ?? 0;

        return renderer::format_completion_status($completedcount, $totalrequired);
    }
}
