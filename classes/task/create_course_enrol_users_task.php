<?php

namespace local_webcourse\task;

defined('MOODLE_INTERNAL') || die();

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

        list($new_courses, $existing_courses, $existing_courses_json) = local_webcourse_filter_existing_course($coursesdata);

        $notfoundusers = [];

        if(!empty($new_courses)){
            foreach ($new_courses as $course) {
                $coursename = clean_param($course['shortname'], PARAM_TEXT);

                $participants = [];
                foreach ($course['participants'] as $participant) {
                    $user_data = ['username' => clean_param($participant['username'], PARAM_USERNAME)];
                    if (isset($participant['roleid'])) {
                        $user_data['roleid'] = clean_param($participant['roleid'], PARAM_TEXT);
                    }
                    $participants[] = $user_data;
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

        if(!empty($existing_courses)){
            foreach ($existing_courses as $course) {
                $coursename = clean_param($course->shortname, PARAM_TEXT);

                $json_course = null;
                foreach ($existing_courses_json as $existing_course) {
                    if ($existing_course['shortname'] === $coursename) {
                        $json_course = $existing_course;
                        break;
                    }
                }

                $participants = [];
                foreach ($json_course['participants'] as $participant) {
                    $user_data = ['username' => clean_param($participant['username'], PARAM_USERNAME)];
                    if (isset($participant['roleid'])) {
                        $user_data['roleid'] = clean_param($participant['roleid'], PARAM_TEXT);
                    }
                    $participants[] = $user_data;
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
