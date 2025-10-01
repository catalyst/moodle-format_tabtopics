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
 * Tab topics format.
 *
 * @package     format_tabtopics
 * @subpackage  tabtopics
 * @copyright   oohoo.biz
 * @link        http://oohoo.biz
 * @author      Nicolas Bretin
 * @author      Braedan Jongerius
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/filelib.php');
require_once($CFG->libdir . '/completionlib.php');

$PAGE->requires->js('/course/format/tabtopics/module.js');

// Make sure all sections are created.
// phpcs:ignore moodle.Commenting.InlineComment.DocBlock
/** @var format_tabtopics */
$format = course_get_format($course);
$course = $format->get_course();
course_create_sections_if_missing($course, range(0, $course->numsections));
$context = context_course::instance($course->id);
// phpcs:ignore moodle.Commenting.InlineComment.DocBlock
/** @var format_tabtopics\output\renderer */
$tabtopicsrenderer = $PAGE->get_renderer('format_tabtopics');
$corerenderer = $PAGE->get_renderer('core', 'course');
$iszerotab = $format->is_section_zero_tab();
$isrememberlasttabsession = $format->is_remember_last_tab_session();

$topic = optional_param('topic', -1, PARAM_INT);

$jsmodule = [
    'name' => 'weekstabs',
    'fullpath' => '/course/format/tabtopics/module.js',
    'requires' => ['base', 'node', 'json', 'io', 'cookie'],
];

// THIS IS THE CODE FOR GENERATING THE TABVIEW. ITS ONLY USED DURING NON EDITING.
if (!$PAGE->user_is_editing()) {
    echo '<script type="text/javascript">
        var is_remember_last_tab_session = ' . ($isrememberlasttabsession ? 'true' : 'false') . ';
    </script>';

    echo '
<style type="text/css" media="screen">
/* <![CDATA[ */
@import url("' . $CFG->wwwroot . '/course/format/tabtopics/tabtop.css");
/* ]]> */
</style>
';
    echo '
    <!--[if IE]>
    <div id = "maincontainer" style="display:">
    <![endif]-->

    <!--[if !IE]> <-->
    <div id = "maincontainer" style="display:none">
    <!--> <![endif]-->

    ';

    if (($marker >= 0) && has_capability('moodle/course:setcurrentsection', $context) && confirm_sesskey()) {
        $course->marker = $marker;
        $DB->set_field("course", "marker", $marker, ["id" => $course->id]);
    }

    $stradd = get_string('add');
    $stractivities = get_string('activities');
    $strshowalltopics = get_string('showalltopics', 'format_tabtopics');
    $strtopic = get_string('topic');
    $strgroups = get_string('groups');
    $strgroupmy = get_string('groupmy');
    $editing = $PAGE->user_is_editing();
    $strtopichide = get_string('hidetopicfromothers', 'format_tabtopics');
    $strtopicshow = get_string('showtopicfromothers', 'format_tabtopics');
    $strmarkthistopic = get_string('markthistopic', 'format_tabtopics');
    $strmarkedthistopic = get_string('markedthistopic', 'format_tabtopics');
    $strmoveup = get_string('moveup');
    $strmovedown = get_string('movedown');

    // If currently moving a file then show the current clipboard.
    // Not too sure what this does.
    if (ismoving($course->id)) {
        // Note, an ordered list would confuse - "1" could be the clipboard or summary.

        echo "<ul class='topicstabs'>\n";


        $stractivityclipboard = strip_tags(get_string('activityclipboard', '', $USER->activitycopyname));
        $strcancel = get_string('cancel');
        echo '<li class="clipboard">';
        echo $stractivityclipboard . '&nbsp;&nbsp;(<a href="mod.php?cancelcopy=true&amp;sesskey=' . sesskey() .
            '">' . $strcancel . '</a>)';
        echo "</li>\n";

        echo '</ul>';
    }

    // Insert the section 0.
    $section = 0;
    $thissection = $sections[$section];

    if ($thissection->summary || $thissection->sequence || $PAGE->user_is_editing()) {

        echo '<ul class="sectionul"><li id="sectiontd-0" class="section main yui3-dd-drop">';

        echo '<div class="content">';

        if (!empty($thissection->name)) {
            echo $OUTPUT->heading(format_string($thissection->name, true, ['context' => $context]), 3, 'sectionname');
        }

        echo '<div class="summary">';

        $summarytext = file_rewrite_pluginfile_urls(
            $thissection->summary,
            'pluginfile.php',
            $context->id,
            'course',
            'section',
            $thissection->id
        );
        $summaryformatoptions = new stdClass;
        $summaryformatoptions->noclean = true;
        $summaryformatoptions->overflowdiv = true;
        echo format_text($summarytext, $thissection->summaryformat, $summaryformatoptions);

        echo '</div>';

        // If section is not a tab, display as a header.
        if (!$iszerotab) {
            $widget = new \core_courseformat\output\local\content\section\cmlist($format, $thissection);
            echo $tabtopicsrenderer->render($widget);
        }

        echo '</div>';
        echo "</li>";
    }

    // If section is a tab we want to start sections at 0,
    // otherwise section 0 is a header and we start at section 1.
    $starttab = $iszerotab ? 0 : 1;

    // Now all the normal modules by topic.
    // Everything below uses "section" terminology - each "section" is a topic.
    $timenow = time();
    $section = $starttab;
    $sectionmenu = [];
    $num = $starttab;
    // This is the first div that yui looks at, top node.
    echo '<div id="sections">';

    // Begining of the unordered list.
    echo '<ul>';
    while ($section <= $course->numsections) {

        if (!empty($sections[$section])) {
            $thissection = $sections[$section];
        } else {
            // Create a new section structure.
            $thissection = new stdClass;
            $thissection->course = $course->id;
            $thissection->section = $section;
            $thissection->name = null;
            $thissection->summary = '';
            $thissection->summaryformat = FORMAT_HTML;
            $thissection->visible = 1;
        }

        // Check if the current section is visible to user.
        $unavaloverride = $format->is_unavailable_override($thissection);

        // Check if override is turned on (informs user section not avaliable).
        $useraccess = $format->check_user_access($thissection);

        // If don't have access AND override "not avaliable" message not on - slip tab.
        if (!$useraccess && !$unavaloverride) {
            $section++;
            continue;
        }

        // The default action is to set the name of each topic to null.
        $secname = $thissection->name;
        // This will set the name of undefined sections to a number.

        if ($secname == null) {
            $secname = $secname . $num;
            $num++;
        }

        if (has_capability('moodle/course:viewhiddensections', $context)
                || $thissection->visible || (!$thissection->visible && $unavaloverride)) {
            // Hidden for students.
            if ($course->marker == $section) {
                echo '<li id ="marker" class="markerselected"><a href="#section-' . $section .
                    '" id = "marker" class="markerselected">' . $secname . '</a></li>';
            } else {
                // Prints each section.
                echo '<li><a href="#section-' . $section . '">' . $secname . '</a></li>';
            }
        }
        $section++;
    }
    echo '</ul>';

    // Should be the div for content.
    echo '<div>';
    // This is the actual bits that we need.
    $section = $starttab;
    $sectionmenu = [];
    $num = $starttab;

    while ($section <= $course->numsections) {
        if (!empty($sections[$section])) {
            $thissection = $sections[$section];
        } else {
            // Create a new section structure.
            $thissection = new stdClass;
            $thissection->course = $course->id;
            $thissection->section = $section;
            $thissection->name = null;
            $thissection->summary = '';
            $thissection->summaryformat = FORMAT_HTML;
            $thissection->visible = 1;
            $thissection->id = $DB->insert_record('course_sections', $thissection);
        }

        // Check if the current section is visible to user.
        $unavaloverride = $format->is_unavailable_override($thissection);

        // Check if override is turned on (informs user section not avaliable).
        $useraccess = $format->check_user_access($thissection);

        // If don't have access AND override "not avaliable" message not on - slip tab.
        if (!$useraccess && !$unavaloverride) {
            $section++;
            continue;
        }

        // If user doesn't have access, but override is present - display not avaliable message.
        if (!$useraccess && $unavaloverride) {
            echo '<div id="section-' . $section . '">';
            echo '<div class="right side"></div>';

            echo '<div class="content">';
            echo $tabtopicsrenderer->section_hidden($section);
            echo '</div>';
            echo '</div>';

            $section++;
            continue;
        }

        $showsection = (has_capability('moodle/course:viewhiddensections', $context) ||
            $thissection->visible || !$course->hiddensections);

        if (!empty($displaysection) && $displaysection != $section) {
            // Check this topic is visible.
            if ($showsection) {
                $sectionmenu[$section] = get_section_name($course, $thissection);
            }
            $section++;
            continue;
        }

        if ($showsection) {
            // What is course marker?
            $currenttopic = ($course->marker == $section);
            $currenttext = '';
            if (!$thissection->visible) {
                $sectionstyle = ' hidden';
            } else if ($currenttopic) {
                $sectionstyle = ' current';
                $currenttext = get_accesshide(get_string('currenttopic', 'format_tabtopics'));
            } else {
                $sectionstyle = '';
            }
            // The default action is to set the name of each topic to null.
            $secname = $thissection->name;
            // This will set the name of undefined sections to a number.
            if ($secname == null) {
                $secname = $secname . $num;
                $num++;
            }

            if (has_capability('moodle/course:viewhiddensections', $context) || $thissection->visible) {
                echo '<div id="section-' . $section . '">';
                // Note, 'right side' is BEFORE content.
                echo '<div class="right side">';
                if ($PAGE->user_is_editing() && has_capability('moodle/course:update', $context)) {
                    if ($course->marker == $section) {
                        // Show the "light globe" on/off.
                        echo '<a href="view.php?id=' . $course->id . '&amp;marker=0&amp;sesskey=' . sesskey() . '#section-' .
                            $section . '" title="' . $strmarkedthistopic . '">' .
                            $OUTPUT->pix_icon('i/marked', $strmarkedthistopic) . '"</a><br />';
                    } else {
                        echo '<a href="view.php?id=' . $course->id . '&amp;marker=' . $section . '&amp;sesskey=' . sesskey() .
                            '#section-' . $section . '" title="' . $strmarkthistopic . '">' .
                            $OUTPUT->pix_icon('i/marker', $strmarkthistopic) . '"</a><br />';
                    }

                    if ($thissection->visible) {
                        // Show the hide/show eye.
                        echo '<a href="view.php?id=' . $course->id . '&amp;hide=' . $section . '&amp;sesskey=' . sesskey() .
                            '#section-' . $section . '" title="' . $strtopichide . '">' .
                            $OUTPUT->pix_icon('i/hide', $strtopichide, 'moodle', ['class' => 'icon hide']) . '"</a><br />';
                    } else {
                        echo '<a href="view.php?id=' . $course->id . '&amp;show=' . $section . '&amp;sesskey=' . sesskey() .
                            '#section-' . $section . '" title="' . $strtopicshow . '">' .
                            $OUTPUT->pix_icon('i/show', $strtopicshow, 'moodle', ['class' => 'icon show']) . '"</a><br />';
                    }
                    if ($section > 1) {
                        // Add a arrow to move section up.
                        echo '<a href="view.php?id=' . $course->id . '&amp;random=' . rand(1, 10000) .
                            '&amp;section=' . $section . '&amp;move=-1&amp;sesskey=' . sesskey() . '#section-' .
                            ($section - 1) . '" title="' . $strmoveup . '">' .
                            $OUTPUT->pix_icon('t/up', $strmoveup, 'moodle', ['class' => 'icon up']) . '"</a><br />';
                    }

                    if ($section < $course->numsections) {
                        // Add a arrow to move section down.
                        echo '<a href="view.php?id=' . $course->id . '&amp;random=' . rand(1, 10000) .
                            '&amp;section=' . $section . '&amp;move=1&amp;sesskey=' . sesskey() . '#section-' .
                            ($section + 1) . '" title="' . $strmovedown . '">' .
                            $OUTPUT->pix_icon('t/down', $strmovedown, 'moodle', ['class' => 'icon down']) . '"</a><br />';
                    }
                }
                echo '</div>';

                echo '<div class="content">';
                if (!has_capability('moodle/course:viewhiddensections', $context) && !$thissection->visible) {
                    // Hidden for students.
                    echo get_string('notavailable');
                } else {
                    $display = '';

                    // Display section if avaliable.
                    if (!is_null($thissection->name)) {
                        $display = $thissection->name;
                    }

                    // If not visible - show icon for people who can see it.
                    if (!$thissection->visible) {
                        $display .= $OUTPUT->pix_icon('i/show', $strtopicshow, 'moodle', ['style' => 'float:right']);
                    }

                    // Output header for section.
                    echo $OUTPUT->heading($display, 3, 'sectionname');

                    echo '<div class="summary">';
                    if ($thissection->summary) {
                        $summarytext = file_rewrite_pluginfile_urls($thissection->summary, 'pluginfile.php',
                            $context->id, 'course', 'section', $thissection->id);
                        $summaryformatoptions = new stdClass();
                        $summaryformatoptions->noclean = true;
                        $summaryformatoptions->overflowdiv = true;
                        echo format_text($summarytext, $thissection->summaryformat, $summaryformatoptions);
                    } else {
                        echo '&nbsp;';
                    }

                    echo '</div>';

                    $widget = new \core_courseformat\output\local\content\section\cmlist($format, $thissection);
                    echo $tabtopicsrenderer->render($widget);

                    echo '<br />';
                    if ($PAGE->user_is_editing()) {
                        $widget = new \core_courseformat\output\local\content\section\cmlist($format, $thissection);
                        echo $tabtopicsrenderer->render($widget);
                    }
                }

                if (has_capability('moodle/course:viewhiddensections', $context)) {
                    $availabilityclass = $format->get_output_classname('content\\section\\availability');
                    $availability = new $availabilityclass(
                        $format,
                        $thissection,
                    );
                    echo $corerenderer->render($availability);
                }

                echo '</div>';
                echo '</div>';
            }
        }
        unset($sections[$section]);
        $section++;
    }
    echo '</div>';
    echo '</div>';
    echo '</div>';

    if (!$displaysection && $PAGE->user_is_editing() && has_capability('moodle/course:update', $context)) {
        // Print stealth sections if present.
        $modinfo = get_fast_modinfo($course);
        foreach ($sections as $section => $thissection) {
            if (empty($modinfo->sections[$section])) {
                $section++;
                continue;
            }

            echo '<li id="section-' . $section . '" class="section main clearfix  yui3-dd-drop orphaned hidden">';

            echo '<div class="left side">';
            echo '</div>';
            // Note, 'right side' is BEFORE content.
            echo '<div class="right side">';
            echo '</div>';
            echo '<div class="content">';
            echo $OUTPUT->heading(get_string('orphanedactivities'), 3, 'sectionname');

            $widget = new \core_courseformat\output\local\content\section\cmlist($format, $thissection);
            echo $tabtopicsrenderer->render($widget);

            echo '</div>';
            echo "</li>\n";
        }
    }

    echo "</ul>\n";

    $PAGE->requires->js_init_call('M.tabtopics.init', [$course->id, $iszerotab], false, $jsmodule);

    if (!empty($sectionmenu)) {
        $select = new single_select(new moodle_url('/course/view.php', ['id' => $course->id]), 'topic', $sectionmenu);
        $select->label = get_string('jumpto');
        $select->class = 'jumpmenu';
        $select->formid = 'sectionmenu';
        echo $OUTPUT->render($select);
    }
} else {
    // If editing generate the sections like the topics.
    $renderer = $PAGE->get_renderer('format_topics');

    if (!is_null($displaysection)) {
        $format->set_sectionnum($displaysection);
    }
    $outputclass = $format->get_output_classname('content');
    $widget = new $outputclass($format);
    echo $renderer->render($widget);
}
