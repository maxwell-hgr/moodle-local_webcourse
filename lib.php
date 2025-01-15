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
 * Library of functions for webcourse plugin.
 *
 * @package   local_webcourse
 * @copyright 2025 Maxwell Souza <maxwell.hygor01@gmail.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/course/lib.php');
require_once($CFG->dirroot . '/enrol/manual/locallib.php');


/**
 * Create a new course and enroll users into it.
 *
 * This function is responsible for creating a new course based on the provided information
 * and enrolling participants in the course. If a participant cannot be enrolled (e.g., if the user does not exist),
 * their username will be added to a "not found" list.
 *
 * @param string $fullname Complete name of the course.
 * @param string $shortname Short name of the course.
 * @param array $participants List of participants to enroll, each containing:
 *     - 'username' (string): Username of the participant.
 *     - 'roleid' (optional string): Role identifier, such as "student", "teacher".
 * @param string $summary Course summary.
 * @param string $format Course format (e.g., 'topics', 'weeks').
 *
 * @return array List of users who could not be found or enrolled.
 * @throws moodle_exception Throws an exception if there is an issue with course creation or enrollment.
 */
function local_webcourse_create_course($fullname, $shortname, $participants = [], $summary = '', $format = 'topics') {
    global $DB;

    $default_roleid = get_config('local_webcourse', 'roleid');
    $categoryid = get_config('local_webcourse', 'categoryid');

    $category = \core_course_category::get($categoryid, IGNORE_MISSING);
    if (!$category) {
        throw new moodle_exception('invalidcategoryid', 'error');
    }

    $course = new stdClass();
    $course->fullname = $fullname;
    $course->shortname = $shortname;
    $course->summary = $summary;
    $course->category = $categoryid;
    $course->format = $format;
    $course->visible = 1;

    $newcourse = create_course($course);

    $notfoundusers = enrol_participants_in_course($newcourse, $participants);

    return $notfoundusers;
}


/**
 * Convert a role identifier string to the corresponding Moodle role ID.
 *
 * This function converts user-friendly role strings (like 'student', 'professor', etc.)
 * into the corresponding Moodle role IDs used in the system.
 *
 * @param string $roleid_string Role identifier string (e.g., 'student', 'teacher').
 *
 * @return int Corresponding role ID for Moodle system.
 * @throws moodle_exception If the string is not recognized.
 */
function get_roleid_from_string($roleid_string) {
    if ($roleid_string === 'professor') {
        return 3;
    }
    return get_config('local_webcourse', 'roleid');
}


/**
 * Generate a CSV file for users who were not found during course enrollment.
 *
 * This function generates a CSV file listing users who could not be found in the system
 * during the course enrollment process. The CSV is output directly to the browser.
 *
 * @param string $coursename The name of the course for naming the CSV file.
 * @param array $usersdata List of users who could not be enrolled (e.g., due to invalid usernames).
 *
 * @return void The function will output a CSV directly.
 */
function local_webcourse_generate_csv($coursename, $usersdata) {
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="' . $coursename . '_notfound.csv"');

    $output = fopen('php://output', 'w');
    fputcsv($output, ['Username', 'Fullname']);

    foreach ($usersdata as $user) {
        fputcsv($output, $user);
    }

    fclose($output);
}


/**
 * Filter out courses that already exist based on their shortname.
 *
 * This function checks if a course already exists by checking its shortname.
 * It will also check which courses are ready for creation and which ones already exist.
 * It returns both the new courses and existing ones with the corresponding course data.
 *
 * @param array $coursesdata List of courses with course shortnames and other metadata.
 *
 * @return array An array containing:
 *     - `new_courses` (array): List of courses that need to be created.
 *     - `existing_courses` (array): List of already existing courses.
 *     - `existing_courses_json` (array): Original data for the existing courses in JSON format.
 */
function local_webcourse_filter_existing_course($coursesdata) {
    global $DB;

    $new_courses = [];
    $existing_courses = [];
    $existing_courses_json = [];

    foreach ($coursesdata as $course) {
        $shortname = clean_param($course['shortname'], PARAM_TEXT);

        // Check if course already exists by shortname
        if ($DB->record_exists('course', ['shortname' => $shortname])) {
            $existing_course = $DB->get_record('course', ['shortname' => $shortname]);

            $existing_courses[] = $existing_course;
            $existing_courses_json[] = $course;
        } else {
            $new_courses[] = $course;
        }
    }

    return [$new_courses, $existing_courses, $existing_courses_json];
}


/**
 * Check if participants are enrolled in a course and enrol if necessary.
 *
 * This function checks if all participants in the course list are already enrolled in the course.
 * It only adds those that are not yet enrolled.
 *
 * @param object $course Course object to check.
 * @param array $participants List of participants to check, each with a 'username' and an optional 'roleid'.
 *
 * @return bool True if all participants are enrolled, false otherwise.
 */
function check_and_enrol($course, $participants = []) {
    global $DB;

    $new_participants = [];
    $notfoundusers = [];

    if (empty($participants)) {
        return $notfoundusers;
    }

    $context = context_course::instance($course->id);
    $enrolled_users = get_enrolled_users($context);

    $enrolled_usernames = array_values(array_map(function($user) {
        return $user->username;
    }, $enrolled_users));

    foreach ($participants as $participant) {
        if (!in_array($participant['username'], $enrolled_usernames)) {
            $new_participants[] = $participant;
        }
    }

    return enrol_participants_in_course($course, $new_participants);
}


/**
 * Enrol participants into the given course, adding their roles.
 *
 * This function enrolls participants in the given course, assigning the specified role
 * or the default role for the plugin. It also handles cases where the participant's username
 * is invalid or not found in Moodle.
 *
 * @param object $course The course to enroll users in.
 * @param array $participants List of participants to enroll with usernames and role IDs.
 *
 * @return array List of users who could not be found or enrolled.
 */
function enrol_participants_in_course($course, $participants) {
    global $DB;

    $manualenrol = enrol_get_plugin('manual');

    $instances = enrol_get_instances($course->id, true);
    $manualinstance = null;

    foreach ($instances as $instance) {
        if ($instance->enrol === 'manual') {
            $manualinstance = $instance;
            break;
        }
    }

    if (!$manualinstance) {
        throw new moodle_exception('noenrolmentplugin', 'error');
    }

    $notfoundusers = [];
    if (empty($course->id)) {
        throw new moodle_exception('invalidcourse', 'local_plugin');
    }

    $context = context_course::instance($course->id);

    foreach ($participants as $participant) {
        $username = clean_param($participant['username'], PARAM_USERNAME);
        $user = $DB->get_record('user', ['username' => $username]);

        if ($user) {
            $roleid = isset($participant['roleid']) ? get_roleid_from_string($participant['roleid']) : get_config('local_webcourse', 'roleid');
            if (!is_enrolled($context, $user->id)) {
                $manualenrol->enrol_user($manualinstance, $user->id, $roleid);
            }
        } else {
            $notfoundusers[] = $participant;
        }
    }

    return $notfoundusers;
}

/**
 * Merge unique users into the $notfoundusers array.
 *
 * @param array $existing_users The array of existing not found users.
 * @param array $new_users The array of new users to be added.
 * @return array The updated array with unique users.
 */
function merge_unique_notfoundusers(array $existing_users, array $new_users): array {
    $unique_users = $existing_users;

    foreach ($new_users as $new_user) {
        $exists = false;

        if (isset($new_user['username'])) {
            foreach ($unique_users as $existing_user) {
                if (isset($existing_user['username'])) {
                    if ($existing_user['username'] === $new_user['username']) {
                        $exists = true;
                        break;
                    }
                }
            }
        }

        if (!$exists) {
            $unique_users[] = $new_user;
        }
    }

    return $unique_users;
}
