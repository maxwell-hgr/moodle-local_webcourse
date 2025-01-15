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
 * Task for creating courses and enrolling users in Moodle based on external API data.
 *
 * @package   local_webcourse
 * @copyright 2025 Maxwell Souza <maxwell.hygor01@gmail.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_webcourse\task;

/**
 * Task for creating courses and enrolling users in Moodle based on external API data.
 *
 * This task fetches data from an external API to create courses and enroll users.
 * It filters out existing courses and only creates new ones, enrolling users as participants.
 * It is scheduled to run automatically via Moodle's cron.
 *
 * @package   local_webcourse
 * @copyright 2025 Maxwell Souza <maxwell.hygor01@gmail.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class create_course_enrol_users_task extends \core\task\scheduled_task {

    /**
     * Definition of the task name for display in the Cron.
     *
     * @return string The name of the task.
     */
    public function get_name() {
        return get_string('pluginname', 'local_webcourse');
    }

    /**
     * Main function for task execution.
     *
     * This function fetches course and participant data from an external API,
     * filters out existing courses, creates new courses if necessary, and
     * enrolls users as participants. It catches any errors that occur during
     * course creation and user enrollment and displays them in a friendly manner.
     *
     * @return void
     */
    public function execute() {
        global $CFG;

        require_once($CFG->dirroot . '/local/webcourse/lib.php');

        $endpoint = get_config('local_webcourse', 'endpoint');

        $response = file_get_contents($endpoint);
        $coursesdata = json_decode($response, true);

        list($newcourses, $existingcourses, $existingcoursesjson) = local_webcourse_filter_existing_course($coursesdata);

        $notfoundusers = [];

        if (!empty($newcourses)) {
            foreach ($newcourses as $course) {
                $coursename = clean_param($course['shortname'], PARAM_TEXT);

                $participants = [];
                foreach ($course['participants'] as $participant) {
                    $userdata = ['username' => clean_param($participant['username'], PARAM_USERNAME)];
                    if (isset($participant['roleid'])) {
                        $userdata['roleid'] = clean_param($participant['roleid'], PARAM_TEXT);
                    }
                    $participants[] = $userdata;
                }

                try {
                    $notfound = local_webcourse_create_course(
                        $coursename,
                        $coursename,
                        $participants,
                        'Curso criado automaticamente',
                        'topics'
                    );

                    $notfoundusers = merge_unique_notfoundusers($notfoundusers, $notfound);
                } catch (Exception $e) {
                    echo $OUTPUT->header();
                    echo html_writer::tag('p', get_string('coursecreationerror', 'local_webcourse') . ': ' . $e->getMessage());
                    echo $OUTPUT->footer();
                    die();
                }
            }
        }

        if (!empty($existingcourses)) {
            foreach ($existingcourses as $course) {
                $coursename = clean_param($course->shortname, PARAM_TEXT);

                $jsoncourse = null;
                foreach ($existingcoursesjson as $existingcourse) {
                    if ($existingcourse['shortname'] === $coursename) {
                        $jsoncourse = $existingcourse;
                        break;
                    }
                }

                $participants = [];
                foreach ($jsoncourse['participants'] as $participant) {
                    $userdata = ['username' => clean_param($participant['username'], PARAM_USERNAME)];
                    if (isset($participant['roleid'])) {
                        $userdata['roleid'] = clean_param($participant['roleid'], PARAM_TEXT);
                    }
                    $participants[] = $userdata;
                }

                try {
                    $notfound = check_and_enrol($course, $participants);
                    $notfoundusers = merge_unique_notfoundusers($notfoundusers, $notfound);
                } catch (Exception $e) {
                    echo $OUTPUT->header();
                    echo html_writer::tag('p', get_string('coursecreationerror', 'local_webcourse') . ': ' . $e->getMessage());
                    echo $OUTPUT->footer();
                    die();
                }
            }
        }
    }
}
