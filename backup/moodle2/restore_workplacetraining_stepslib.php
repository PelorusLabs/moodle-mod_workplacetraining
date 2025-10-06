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
 * Structured steps to restore one workplacetraining activity
 *
 * @package    mod_workplacetraining
 * @copyright  Pelorus Labs
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class restore_workplacetraining_activity_structure_step extends restore_activity_structure_step {
    /**
     * Define the restore workplacetraining structure
     *
     * @return mixed
     */
    protected function define_structure() {

        $paths = [];
        $userinfo = $this->get_setting_value('userinfo');

        $paths[] = new restore_path_element('workplacetraining', '/activity/workplacetraining');
        $paths[] = new restore_path_element('workplacetraining_section', '/activity/workplacetraining/sections/section');
        $paths[] = new restore_path_element(
            'workplacetraining_section_item',
            '/activity/workplacetraining/sections/section/section_items/section_item'
        );
        $paths[] = new restore_path_element(
            'workplacetraining_item_config',
            '/activity/workplacetraining/sections/section/section_items/section_item/item_configs/config'
        );

        if ($userinfo) {
            $paths[] = new restore_path_element(
                'workplacetraining_response',
                '/activity/workplacetraining/sections/section/section_items/section_item/responses/response'
            );
            $paths[] = new restore_path_element(
                'workplacetraining_evaluation',
                '/activity/workplacetraining/evaluations/evaluation'
            );
        }

        // Return the paths wrapped into standard activity structure.
        return $this->prepare_activity_structure($paths);
    }

    /**
     * Process workplacetraining data
     *
     * @param $data
     * @return void
     */
    protected function process_workplacetraining($data) {
        global $DB;

        $data = (object) $data;
        $data->course = $this->get_courseid();

        // Insert the workplacetraining record.
        $newitemid = $DB->insert_record('workplacetraining', $data);

        // Immediately after inserting "activity" record, call this.
        $this->apply_activity_instance($newitemid);
    }

    /**
     * Process section data
     *
     * @param $data
     * @return void
     */
    protected function process_workplacetraining_section($data) {
        global $DB;

        $data = (object) $data;
        $oldid = $data->id;

        $data->wtid = $this->get_new_parentid('workplacetraining');
        $data->usermodified = $this->get_mappingid('user', $data->usermodified);

        // We'll remap parentsection after all sections have been restored.
        $newitemid = $DB->insert_record('workplacetraining_sections', $data);
        $this->set_mapping('workplacetraining_section', $oldid, $newitemid);
    }

    /**
     * Process section item data
     *
     * @param $data
     * @return void
     */
    protected function process_workplacetraining_section_item($data) {
        global $DB;

        $data = (object) $data;
        $oldid = $data->id;

        $data->sectionid = $this->get_new_parentid('workplacetraining_section');
        $data->usermodified = $this->get_mappingid('user', $data->usermodified);

        $newitemid = $DB->insert_record('workplacetraining_section_items', $data);
        $this->set_mapping('workplacetraining_section_item', $oldid, $newitemid);
    }

    /**
     * Process section item config data
     *
     * @param $data
     * @return void
     */
    protected function process_workplacetraining_item_config($data) {
        global $DB;

        $data = (object) $data;

        $data->itemid = $this->get_new_parentid('workplacetraining_section_item');

        $DB->insert_record('workplacetraining_item_config', $data);
    }

    /**
     * Process section item response data
     *
     * @param $data
     * @return void
     */
    protected function process_workplacetraining_response($data) {
        global $DB;

        $data = (object) $data;

        $data->itemid = $this->get_new_parentid('workplacetraining_section_item');
        $data->userid = $this->get_mappingid('user', $data->userid);
        $data->usermodified = $this->get_mappingid('user', $data->usermodified);

        $newresponseid = $DB->insert_record('workplacetraining_responses', $data);
        $this->set_mapping('workplacetraining_response', $data->id, $newresponseid, true);
    }

    /**
     * Process evaluation data
     *
     * @param $data
     * @return void
     */
    protected function process_workplacetraining_evaluation($data) {
        global $DB;

        $data = (object) $data;

        $data->wtid = $this->get_new_parentid('workplacetraining');
        $data->userid = $this->get_mappingid('user', $data->userid);
        $data->usermodified = $this->get_mappingid('user', $data->usermodified);
        $data->finalisedby = $this->get_mappingid('user', $data->finalisedby);

        $newevaluationid = $DB->insert_record('workplacetraining_evaluations', $data);
        $this->set_mapping('workplacetraining_evaluation', $data->id, $newevaluationid, true);
    }

    /**
     * Add related files and remap parentsection IDs
     *
     * @return void
     */
    protected function after_execute() {
        global $DB;

        $this->add_related_files('mod_workplacetraining', 'intro', null);

        $wtid = $this->get_new_parentid('workplacetraining');
        // Remap parent sections after all sections restored.
        $rs = $DB->get_recordset(
            'workplacetraining_sections',
            ['wtid' => $wtid],
            '',
            'id, parentsection'
        );

        foreach ($rs as $section) {
            if (!empty($section->parentsection)) {
                // Map old parent ID to new parent ID.
                $section->parentsection = $this->get_mappingid(
                    'workplacetraining_section',
                    $section->parentsection
                );
                $DB->update_record('workplacetraining_sections', $section);
            }
        }
        $rs->close();
    }
}
