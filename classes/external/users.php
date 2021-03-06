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
 * Timeline Social course format.
 *
 * @package    format_timeline
 * @copyright  2020 onwards Willian Mano {@link http://conecti.me}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace format_timeline\external;

defined('MOODLE_INTERNAL') || die();

use external_api;
use external_value;
use external_function_parameters;
use external_single_structure;
use external_multiple_structure;
use context_course;
use format_timeline\local\user;
use user_picture;

/**
 * Users
 *
 * @copyright  2020 onwards Willian Mano {@link http://conecti.me}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class users extends external_api {
    /**
     * Get enrolled users parameters
     *
     * @return external_function_parameters
     */
    public static function enrolledusers_parameters() {
        return new external_function_parameters([
            'search' => new external_single_structure([
                'courseid' => new external_value(PARAM_INT, 'The course id', VALUE_REQUIRED),
                'name' => new external_value(PARAM_TEXT, 'The user name', VALUE_REQUIRED)
            ])
        ]);
    }

    /**
     * Get the list of all course's users
     *
     * @param $search
     *
     * @return array
     *
     * @throws \coding_exception
     * @throws \dml_exception
     * @throws \invalid_parameter_exception
     * @throws \moodle_exception
     */
    public static function enrolledusers($search) {
        global $DB, $PAGE;

        self::validate_parameters(self::enrolledusers_parameters(), ['search' => $search]);

        $search = (object)$search;

        $course = $DB->get_record('course', ['id' => $search->courseid], '*', MUST_EXIST);
        $context = context_course::instance($course->id);

        $PAGE->set_context($context);

        if (!is_enrolled($context) && !is_siteadmin()) {
            return [];
        }

        $users = user::getall_by_name($search->name, $course, $context);

        $returndata = [];

        foreach ($users as $user) {
            $userpicture = new \user_picture($user);
            $returndata[] = [
                'id' => $user->id,
                'username' => $user->username,
                'fullname' => fullname($user),
                'picture' => $userpicture->get_url($PAGE)->out()
            ];
        }

        return [
            'users' => $returndata
        ];
    }

    /**
     * Get enrolled users return fields
     *
     * @return external_single_structure
     */
    public static function enrolledusers_returns() {
        return new external_function_parameters(
            array(
                'users' => new external_multiple_structure(
                    new external_single_structure(
                        array(
                            'id' => new external_value(PARAM_INT, 'The user id'),
                            'username' => new external_value(PARAM_TEXT, "The user username"),
                            'fullname' => new external_value(PARAM_TEXT, "The user fullname"),
                            'picture' => new external_value(PARAM_TEXT, "The user picture url")
                        )
                    )
                )
            )
        );
    }

    protected static function get_basic_search_conditions($search, $context) {
        global $DB, $CFG;

        // Add some additional sensible conditions
        $tests = ["u.id <> :guestid", 'u.deleted = 0', 'u.confirmed = 1'];
        $params = ['guestid' => $CFG->siteguest];

        if (!empty($search)) {
            $conditions = get_extra_user_fields($context);
            foreach (get_all_user_name_fields() as $field) {
                $conditions[] = 'u.'.$field;
            }

            $conditions[] = $DB->sql_fullname('u.firstname', 'u.lastname');

            $searchparam = '%' . $search . '%';

            $i = 0;
            foreach ($conditions as $key => $condition) {
                $conditions[$key] = $DB->sql_like($condition, ":con{$i}00", false);
                $params["con{$i}00"] = $searchparam;
                $i++;
            }

            $tests[] = '(' . implode(' OR ', $conditions) . ')';
        }

        $wherecondition = implode(' AND ', $tests);

        $extrafields = get_extra_user_fields($context, ['username', 'lastaccess']);
        $extrafields[] = 'username';
        $extrafields[] = 'lastaccess';
        $extrafields[] = 'maildisplay';

        $ufields = user_picture::fields('u', $extrafields);

        return [$ufields, $params, $wherecondition];
    }
}