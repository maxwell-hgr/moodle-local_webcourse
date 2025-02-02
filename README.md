# Moodle Plugin: Local WEBCOURSE
## Description
This plugin integrates Moodle with an external microservice to automatically create courses and enroll users. It validates users, generates a CSV file for non-existent users, and automates the course creation process. Additionally, the plugin now includes a scheduled task within Moodle, which runs periodically via cron, to ensure that the courses in Moodle are always in sync with the courses available from the external endpoint.
**The plugin mirrors its courses with an external endpoint, which is very useful for institutions that use an external academic system outside of Moodle.**

## Installation
1. Copy the plugin folder to moodle_root/local/.
2. Access the Moodle admin panel to finalize the installation.
3. Configure the external API endpoint in the plugin settings.

## Features
 - Fetches course data from an external microservice.
 - Creates courses automatically from the courses array in the response.
 - Enrolls users in courses automatically.
 - Generates a CSV of missing users that were not found in Moodle.
 - Includes a cron task to synchronize the courses in Moodle periodically with the courses from the microservice.

## Requirements
 - Moodle 4.x or higher.
 - PHP 7.3 or higher.
 - Admin permission.

## Usage
1. Set the correct endpoint for your API request.
2. Access the plugin page in Moodle: **Site administration -> Courses -> WEBSERVICE Integration - COURSE**
3. Confirm courses creation.
4. Download the CSV report of missing users, if applicable.
5. The cron task will run periodically to sync courses from the microservice to Moodle.

## Json Format
```json
{
    "courses": [
        {
            "shortname": "unique_field1",
            "name": "Exemple name - Geography Course",
            "participants": [
                { "username": "00000000074" },
                { "username": "00000000076" },
                { "username": "00000000077" },
                { "username": "00000000078", "roleid": "teacher" },
                { "username": "00000000069" }
            ]
        },
        {
            "shortname": "unique_field2",
            "name": "Exemple name - Physics Course",
            "participants": [
                { "username": "00000000023", "roleid": "teacher" },
                { "username": "00000000076" },
                { "username": "00000000087"},
                { "username": "00000000078"}
            ]
        },
        {
            "shortname": "unique_field3",
            "name": "Exemple name - Math Course",
            "participants": [
                { "username": "00000000023", "roleid": "professor" },
                { "username": "00000000076" },
                { "username": "00000000087"},
                { "username": "00000000078"}
            ]
        }
    ]
}
```

## Error Handling
 - Displays errors for invalid inputs or missing API responses.
 - Warns about missing users with a downloadable CSV report.

## File Structure
 - index.php: Handles user interaction and workflow.
 - lib.php: Helper functions for course creation and enrollment.
 - lang/en/local_webcourse.php: Language strings.
 - classes/task/create_course_enrol_users_task: Task responsible for periodic course synchronization.

## License
This project is licensed under the GNU General Public License.

## Author
Maxwell H. S. Souza
