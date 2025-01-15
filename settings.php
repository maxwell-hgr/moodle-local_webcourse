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
 * Webcourse admin settings and defaults.
 *
 * @package   local_webcourse
 * @copyright 2025 Maxwell Souza <maxwell.hygor01@gmail.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

if ($hassiteconfig) {
    $ADMIN->add('courses', new admin_externalpage(
        'local_webcourse',
        get_string('pluginname', 'local_webcourse'),
        new moodle_url('/local/webcourse/index.php')
    ));

    $settings = new admin_settingpage('local_webcourse_settings', get_string('pluginname', 'local_webcourse'));

    $settings->add(new admin_setting_configtext(
        'local_webcourse/endpoint',
        get_string('endpoint', 'local_webcourse'),
        get_string('endpoint_desc', 'local_webcourse'),
        '',
        PARAM_URL
    ));

    $roles = role_get_names();
    $roleoptions = [];
    foreach ($roles as $roleid => $roledata) {
        $roleoptions[$roleid] = format_string($roledata->localname ?? $roledata->shortname, true);
    }

    $settings->add(new admin_setting_configselect(
        'local_webcourse/roleid',
        get_string('roleid', 'local_webcourse'),
        get_string('roleid_desc', 'local_webcourse'),
        5,
        $roleoptions
    ));


    $categories = $DB->get_records_menu('course_categories', null, 'name ASC', 'id, name');
    $settings->add(new admin_setting_configselect(
        'local_webcourse/categoryid',
        get_string('categoryid', 'local_webcourse'),
        get_string('categoryid_desc', 'local_webcourse'),
        1,
        $categories ?: []
    ));

    $ADMIN->add('localplugins', $settings);
}
