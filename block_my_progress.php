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
 * My Progress block - Shows course progress for students and teachers.
 *
 * @package    block_my_progress
 * @copyright  2025 AI Grader <support@lmshostingservices.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

class block_my_progress extends block_base {
    /**
     * Initialize the block.
     */
    public function init() {
        $this->title = get_string('pluginname', 'block_my_progress');
    }

    /**
     * Allow multiple instances.
     *
     * @return bool
     */
    public function instance_allow_multiple() {
        return false;
    }

    /**
     * Define where the block can be added.
     *
     * @return array
     */
    public function applicable_formats() {
        return [
            'my' => true,
            'site-index' => true,
            'course-view' => true,
            'course' => true,
            'all' => false,
        ];
    }

    /**
     * Get block content.
     *
     * @return stdClass
     */
    public function get_content() {
        global $USER, $OUTPUT, $PAGE;

        if ($this->content !== null) {
            return $this->content;
        }

        $this->content = new stdClass();
        $this->content->text = '';
        $this->content->footer = '';

        if (!isloggedin() || isguestuser()) {
            return $this->content;
        }

        $PAGE->requires->css('/blocks/my_progress/styles.css');
        $PAGE->requires->js_call_amd('block_my_progress/my_progress', 'init');

        $service = new \block_my_progress\progress_service();
        $data = $service->get_user_progress($USER->id);

        $this->content->text = $OUTPUT->render_from_template('block_my_progress/my_progress', $data);

        return $this->content;
    }

    /**
     * Enable global configuration.
     *
     * @return bool
     */
    public function has_config() {
        return false;
    }

    /**
     * Show Moodle's default block header.
     *
     * @return bool
     */
    public function hide_header() {
        return false;
    }

    /**
     * Get ARIA role.
     *
     * @return string
     */
    public function get_aria_role() {
        return 'navigation';
    }
}
