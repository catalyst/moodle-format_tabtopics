<?php
// This file is part of Moodle - https://moodle.org/
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
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

/**
 * *************************************************************************
 * *                 OOHOO Tab topics Course format                       **
 * *************************************************************************
 * @package     format_tabtopics
 * @subpackage  tabtopics                                                 **
 * @copyright   oohoo.biz                                                 **
 * @link        http://oohoo.biz                                          **
 * @author      Nicolas Bretin                                            **
 * @author      Braedan Jongerius                                         **
 * @author      Dustin Durand                                             **
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later  **
 * *************************************************************************
 * ************************************************************************ */

defined('MOODLE_INTERNAL') || die();
require_once($CFG->dirroot . '/course/format/lib.php');

use core\url;

/**
 * Main class for the course format Tab topics
 *
 * @package   format_tabtopics
 * @copyright oohoo.biz
 * @link      http://oohoo.biz
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class format_tabtopics extends core_courseformat\base {

    /**
     * Returns true if this course format uses sections
     *
     * @return bool
     */
    public function uses_sections() {
        return true;
    }

    /**
     * Returns the display name of the given section that the course prefers.
     *
     * Use section name is specified by user. Otherwise use default ("Topic #")
     *
     * @param int|stdClass $section Section object from database or just field section.section
     * @return string Display name that the course format prefers, e.g. "Topic 2"
     */
    public function get_section_name($section) {
        $section = $this->get_section($section);
        if ((string) $section->name !== '') {
            return format_string($section->name, true, ['context' => context_course::instance($this->courseid)]);
        } else if ($section->section == 0) {
            return get_string('section0name', 'format_tabtopics');
        } else {
            return get_string('topic') . ' ' . $section->section;
        }
    }

    /**
     * The URL to use for the specified course (with section)
     *
     * @param int|stdClass $section Section object from database or just field course_sections.section
     *     if omitted the course view page is returned
     * @param array $options options for view URL. At the moment core uses:
     *     'navigation' (bool) if true and section has no separate page, the function returns null
     *     'sr' (int) used by multipage formats to specify to which section to return
     * @return url
     */
    public function get_view_url($section, $options = []) {
        $course = $this->get_course();
        $url = new url('/course/view.php', ['id' => $course->id]);

        $sr = null;
        if (array_key_exists('sr', $options)) {
            $sr = $options['sr'];
        }
        if (is_object($section)) {
            $sectionno = $section->section;
        } else {
            $sectionno = $section;
        }
        if ($sectionno !== null) {
            if ($sr !== null) {
                if ($sr) {
                    $usercoursedisplay = COURSE_DISPLAY_MULTIPAGE;
                    $sectionno = $sr;
                } else {
                    $usercoursedisplay = COURSE_DISPLAY_SINGLEPAGE;
                }
            } else {
                $usercoursedisplay = $course->coursedisplay;
            }
            if ($sectionno != 0 && $usercoursedisplay == COURSE_DISPLAY_MULTIPAGE) {
                $url->param('section', $sectionno);
            } else {
                if (!empty($options['navigation'])) {
                    return new url('');
                }
                $url->set_anchor('section-' . $sectionno);
            }
        }
        return $url;
    }

    /**
     * Returns the information about the ajax support in the given source format
     *
     * The returned object's property (boolean)capable indicates that
     * the course format supports Moodle course ajax features.
     * The property (array)testedbrowsers can be used as a parameter for {@link ajaxenabled()}.
     *
     * @return stdClass
     */
    public function supports_ajax() {
        $ajaxsupport = new stdClass();
        $ajaxsupport->capable = true;
        $ajaxsupport->testedbrowsers = ['MSIE' => 6.0, 'Gecko' => 20061111, 'Safari' => 531, 'Chrome' => 6.0];
        return $ajaxsupport;
    }

    /**
     * Returns true if this course format supports components.
     *
     * @return bool
     */
    public function supports_components() {
        return true;
    }

    /**
     * Loads all of the course sections into the navigation
     *
     * @param global_navigation $navigation
     * @param navigation_node $node The course node within the navigation
     */
    public function extend_course_navigation($navigation, navigation_node $node) {
        global $PAGE;
        // If section is specified in course/view.php, make sure it is expanded in navigation.
        if ($navigation->includesectionnum === false) {
            $selectedsection = optional_param('section', null, PARAM_INT);
            if ($selectedsection !== null && (!defined('AJAX_SCRIPT') || AJAX_SCRIPT == '0') &&
                    $PAGE->url->compare(new moodle_url('/course/view.php'), URL_MATCH_BASE)) {
                $navigation->includesectionnum = $selectedsection;
            }
        }

        // Check if there are callbacks to extend course navigation.
        parent::extend_course_navigation($navigation, $node);
    }

    /**
     * Custom action after section has been moved in AJAX mode
     *
     * Used in course/rest.php
     *
     * @return array This will be passed in ajax respose
     */
    public function ajax_section_move() {
        global $PAGE;
        $titles = [];
        $course = $this->get_course();
        $modinfo = get_fast_modinfo($course);
        // phpcs:ignore moodle.Commenting.InlineComment.DocBlock
        /** @var format_tabtopics\output\renderer */
        $renderer = $this->get_renderer($PAGE);
        if ($renderer && ($sections = $modinfo->get_section_info_all())) {
            foreach ($sections as $number => $section) {
                $titles[$number] = $renderer->section_title($section, $course);
            }
        }
        return ['sectiontitles' => $titles, 'action' => 'move'];
    }

    /**
     * Returns the list of blocks to be automatically added for the newly created course
     *
     * @return array of default blocks, must contain two keys BLOCK_POS_LEFT and BLOCK_POS_RIGHT
     *     each of values is an array of block names (for left and right side columns)
     */
    public function get_default_blocks() {
        return [
            BLOCK_POS_LEFT => [],
            BLOCK_POS_RIGHT => ['search_forums', 'news_items', 'calendar_upcoming', 'recent_activity'],
        ];
    }

    /**
     * Definitions of the additional options that this course format uses for course
     *
     * TabTopics format uses the following options:
     * - coursedisplay
     * - numsections
     * - hiddensections
     * - is zero section a tab
     *
     * @param bool $foreditform
     * @return array of options
     */
    public function course_format_options($foreditform = false) {
        static $courseformatoptions = false;
        if ($courseformatoptions === false) {
            $courseconfig = get_config('moodlecourse');
            $courseformatoptions = [
                'numsections' => [
                    'default' => $courseconfig->numsections,
                    'type' => PARAM_INT,
                ],
                'hiddensections' => [
                    'default' => $courseconfig->hiddensections,
                    'type' => PARAM_INT,
                ],
                'coursedisplay' => [
                    'default' => $courseconfig->coursedisplay,
                    'type' => PARAM_INT,
                ],
                'isZeroTab' => [
                    'label' => new lang_string('tabtopics_zero_as_tab', 'format_tabtopics'),
                    'help' => 'tabtopics_zero_as_tab',
                    'element_type' => 'advcheckbox',
                    'default' => 0,
                    'element_attributes' => ['', ['group' => 1], [0, 1]],
                    'type' => PARAM_INT,
                ],
                'remember_last_tab_session' => [
                    'label' => new lang_string('tabtopics_remember_last_tab_session', 'format_tabtopics'),
                    'help' => 'tabtopics_remember_last_tab_session',
                    'element_type' => 'selectyesno',
                    'default' => 1,
                    'type' => PARAM_INT,
                ],
            ];
        }
        if ($foreditform && !isset($courseformatoptions['coursedisplay']['label'])) {
            $courseconfig = get_config('moodlecourse');
            $max = $courseconfig->maxsections;
            if (!isset($max) || !is_numeric($max)) {
                $max = 52;
            }
            $sectionmenu = [];
            for ($i = 0; $i <= $max; $i++) {
                $sectionmenu[$i] = "$i";
            }
            $courseformatoptionsedit = [
                'numsections' => [
                    'label' => new lang_string('numberweeks'),
                    'element_type' => 'select',
                    'element_attributes' => [$sectionmenu],
                ],
                'hiddensections' => [
                    'label' => new lang_string('hiddensections'),
                    'help' => 'hiddensections',
                    'help_component' => 'moodle',
                    'element_type' => 'select',
                    'element_attributes' => [
                        [
                            0 => new lang_string('hiddensectionscollapsed'),
                            1 => new lang_string('hiddensectionsinvisible'),
                        ],
                    ],
                ],
                'coursedisplay' => [
                    'label' => new lang_string('coursedisplay'),
                    'element_type' => 'select',
                    'element_attributes' => [
                        [
                            COURSE_DISPLAY_SINGLEPAGE => new lang_string('coursedisplay_single'),
                            COURSE_DISPLAY_MULTIPAGE => new lang_string('coursedisplay_multi'),
                        ],
                    ],
                    'help' => 'coursedisplay',
                    'help_component' => 'moodle',
                    'isZeroTab' => [
                        'label' => new lang_string('tabtopics_zero_as_tab', 'format_tabtopics'),
                        'help' => 'tabtopics_remember_last_tab_session',
                        'element_type' => 'advcheckbox',
                        'element_attributes' => ['', [], [0, 1]],
                        'type' => PARAM_INT,
                    ],
                    'remember_last_tab_session' => [
                        'label' => new lang_string('tabtopics_remember_last_tab_session', 'format_tabtopics'),
                        'help' => 'tabtopics_remember_last_tab_session',
                        'element_type' => 'selectyesno',
                        'type' => PARAM_INT,
                    ],
                ],
            ];
            $courseformatoptions = array_merge_recursive($courseformatoptions, $courseformatoptionsedit);
        }
        return $courseformatoptions;
    }

    /**
     * Returns whether the current course should have the section 0 as a header
     * or a tab. If for any reason the setting doesn't exist then it defaults to
     * header. (false)
     *
     * Defaults to false if setting doesn't exist.
     *
     * @return boolean
     */
    public function is_section_zero_tab() {
        global $DB;

        // Get course id.
        $courseid = $this->get_courseid();

        // Course id is zero id course doesn't exist - shouldn't happen.
        if ($courseid == 0) {
            return false;
        }

        // Get option.
        $option = $DB->get_record('course_format_options', ['courseid' => $courseid, 'name' => 'isZeroTab']);

        // If this value never existed, then we assume false.
        if (!$option) {
            return false;
        }

        return $option->value == 1 ? true : false;
    }

    /**
     * Returns whether the current course should save the last tab the user clicked or returned to the default tab
     *
     * Defaults to false if setting doesn't exist.
     *
     * @return boolean
     */
    public function is_remember_last_tab_session() {
        global $DB;

        // Get course id.
        $courseid = $this->get_courseid();

        // Course id is zero id course doesn't exist - shouldn't happen.
        if ($courseid == 0) {
            return false;
        }

        // Get option.
        $option = $DB->get_record('course_format_options', ['courseid' => $courseid, 'name' => 'remember_last_tab_session']);

        // If this value never existed, then we assume false.
        if (!$option) {
            return false;
        }

        return $option->value == 1 ? true : false;
    }

    /**
     * Updates format options for a course
     *
     * In case if course format was changed to 'tabtopics', we try to copy options
     * 'coursedisplay', 'numsections' and 'hiddensections' from the previous format.
     * If previous course format did not have 'numsections' option, we populate it with the
     * current number of sections
     *
     * @param stdClass|array $data return value from {@link moodleform::get_data()} or array with data
     * @param stdClass $oldcourse if this function is called from {@link update_course()}
     *     this object contains information about the course before update
     * @return bool whether there were any changes to the options values
     */
    public function update_course_format_options($data, $oldcourse = null) {
        global $DB;

        if ($oldcourse !== null) {
            $data = (array) $data;
            $oldcourse = (array) $oldcourse;
            $options = $this->course_format_options();
            foreach ($options as $key => $unused) {
                if (!array_key_exists($key, $data)) {
                    if (array_key_exists($key, $oldcourse)) {
                        $data[$key] = $oldcourse[$key];
                    } else if ($key === 'numsections') {
                        // If previous format does not have the field 'numsections'
                        // and $data['numsections'] is not set,
                        // we fill it with the maximum section number from the DB.
                        $maxsection = $DB->get_field_sql('SELECT max(section) from {course_sections}
                            WHERE course = ?', [$this->courseid]);
                        if ($maxsection) {
                            // If there are no sections, or just default 0-section, 'numsections' will be set to default.
                            $data['numsections'] = $maxsection;
                        }
                    }
                }
            }
        }
        return $this->update_format_options($data);
    }

    /**
     * Determines whether the unavaliable override is avaliable
     *
     * @param stdClass $thissection
     * @return boolean
     */
    public function is_unavailable_override($thissection) {
        $course = $this->get_course();
        if (!$course->hiddensections && $thissection->available) {
            return true;
        }
        return false;
    }

    /**
     * Determines whether the user can access or view the section
     *
     * @param stdClass $thissection
     * @return boolean true on visible to user, else false
     */
    public function check_user_access($thissection) {
        // Show the section if the user is permitted to access it, OR if it's not available
        // but showavailability is turned on (and there is some available info text).
        $showsection = $thissection->uservisible;

        if (!$showsection) {
            return false;
        }

        return true;
    }
}

/**
 * Implements callback inplace_editable() allowing to edit values in-place
 *
 * @package format_tabtopics
 * @param string $itemtype
 * @param int $itemid
 * @param mixed $newvalue
 * @return \core\output\inplace_editable
 */
function format_tabtopics_inplace_editable($itemtype, $itemid, $newvalue) {
    global $DB, $CFG;
    require_once($CFG->dirroot . '/course/lib.php');
    if ($itemtype === 'sectionname' || $itemtype === 'sectionnamenl') {
        $section = $DB->get_record_sql(
            'SELECT s.* FROM {course_sections} s JOIN {course} c ON s.course = c.id WHERE s.id = ? AND c.format = ?',
            [$itemid, 'tabtopics'], MUST_EXIST);
        return course_get_format($section->course)->inplace_editable_update_section_name($section, $itemtype, $newvalue);
    }
}
