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
 * Create a course and enroll users with external course and users data.
 *
 * @package   local_webcourse
 * @copyright 2025 Maxwell Souza <maxwell.hygor01@gmail.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');
require_once('lib.php');

require_login();

$context = context_system::instance();
require_capability('moodle/site:config', $context);

$PAGE->set_url(new moodle_url('/local/webcourse/index.php'));
$PAGE->set_context($context);
$PAGE->set_title(get_string('pluginname', 'local_webcourse'));
$PAGE->set_heading(get_string('pluginname', 'local_webcourse'));

$endpoint = get_config('local_webcourse', 'endpoint');

$response = file_get_contents($endpoint);
$coursesdata = json_decode($response, true);

list($newcourses, $existingcourses, $existingcoursesjson) = local_webcourse_filter_existing_course($coursesdata);

if (optional_param('downloadcsv', 0, PARAM_INT) === 1) {
    $csvdata = optional_param('data', '', PARAM_RAW);
    $coursename = required_param('coursename', PARAM_TEXT);
    $usersdata = json_decode(urldecode($csvdata), true);

    local_webcourse_generate_csv($coursename, $usersdata);
    exit();
}

if (optional_param('confirm', 0, PARAM_INT) === 1) {
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



    echo $OUTPUT->header();
    echo html_writer::tag('h2', get_string('coursecreated', 'local_webcourse'));

    if (!empty($notfoundusers)) {
        $countnotfound = count($notfoundusers);
        echo html_writer::tag('p', get_string('usersnotfound', 'local_webcourse') . ": {$countnotfound}");

        $csvdata = urlencode(json_encode($notfoundusers));
        $csvurl = new moodle_url('/local/webcourse/index.php', [
            'downloadcsv' => 1,
            'coursename' => 'Users Not Found',
            'data' => $csvdata,
        ]);

        echo html_writer::tag(
            'p',
            html_writer::link($csvurl, get_string('downloadcsv', 'local_webcourse'), ['class' => 'btn btn-secondary'])
        );
    }

    echo $OUTPUT->footer();
    die();
}

echo $OUTPUT->header();



if (!empty($existingcoursesjson) || !empty($newcourses)) {
    $courseshtml = '';

    if (!empty($existingcoursesjson)) {
        $courseshtml .= html_writer::tag('h2', get_string('update_courses', 'local_webcourse') . ': '
            . count($existingcoursesjson));

        $courseshtml .= '<ul>';
        foreach ($existingcoursesjson as $course) {
            $coursename = clean_param($course['name'], PARAM_TEXT);

            $participants = [];
            foreach ($course['participants'] as $participant) {
                $username = clean_param($participant['username'], PARAM_USERNAME);
                $participants[] = $username;
            }

            $courseshtml .= "<li>" . " JSON - " . get_string('course_name', 'local_webcourse') . ": {$coursename}, " .
                get_string('participants_count', 'local_webcourse') . ": " . count($participants) . "</li>";
        }
        $courseshtml .= '</ul>';
        $courseshtml .= '<br>';
    }

    if (!empty($newcourses)) {
        $courseshtml .= html_writer::tag('h2', get_string('found_courses', 'local_webcourse') . ': ' . count($newcourses));

        $courseshtml .= '<ul>';
        foreach ($newcourses as $course) {
            $coursename = clean_param($course['name'], PARAM_TEXT);

            $participants = [];
            foreach ($course['participants'] as $participant) {
                $username = clean_param($participant['username'], PARAM_USERNAME);
                $participants[] = $username;
            }

            $courseshtml .= "<li>" . get_string('course_name', 'local_webcourse') . ": {$coursename}, " .
                get_string('participants_count', 'local_webcourse') . ": " . count($participants) . "</li>";
        }
        $courseshtml .= '</ul>';
        $courseshtml .= '<br>';
    }

    echo $courseshtml;

    $confirmurl = new moodle_url('/local/webcourse/index.php', ['confirm' => 1, 'courses' => json_encode($coursesdata)]);
    echo html_writer::tag('p', html_writer::link($confirmurl, get_string('confirmcreate', 'local_webcourse'),
        ['class' => 'btn btn-primary']));
} else {
    echo html_writer::tag('p', get_string('no_courses_found', 'local_webcourse'));
}

echo $OUTPUT->footer();
