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
 * Privacy Subsystem implementation for local_webcourse.
 *
 * @package   local_webcourse
 * @copyright 2024 Maxwell Souza <maxwell.hygor01@gmail.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_webcourse\privacy;

/**
 * Privacy Subsystem for local_webcourse.
 *
 * @package   local_webcourse
 * @copyright 2024 Maxwell Souza <maxwell.hygor01@gmail.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class provider implements \core_privacy\local\metadata\null_provider {
    /**
     * Returns the reason associated with the privacy metadata.
     *
     * This function returns a predefined string that represents the reason for
     * the privacy metadata. It is used to provide information about the
     * privacy handling of the data in the plugin.
     *
     * @return string A string representing the privacy metadata reason, in this case 'privacy:metadata'.
     */
    public static function get_reason(): string {
        return 'privacy:metadata';
    }
}
