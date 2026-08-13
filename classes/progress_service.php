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
 * Progress service for fetching user course progress data.
 *
 * Performance notes (v1.x.x rewrite):
 *
 * Original code made ~444 DB queries for a student in 10 courses with 20 activities each because:
 *  - get_fast_modinfo($course, $userid) was called TWICE per course — once in get_course_progress()
 *    and once in get_activity_completions() — producing 20 modinfo loads for 10 courses.
 *  - completion->get_data($cm, true, $userid) was called per activity per course, twice over —
 *    400 calls for 10 courses × 20 activities × 2 methods.
 *  - The two methods did entirely identical modinfo + completion traversal back-to-back.
 *
 * The top-level get_user_progress() already bulk-loaded enrolment dates, course completion
 * dates, and categories correctly. Only the per-activity completion state loop was broken.
 *
 * Rewrite reduces to ~15 queries for the same scenario:
 *  1. enrol_get_users_courses
 *  2. bulk enrolment dates (already existed)
 *  3. bulk course_completions (already existed)
 *  4. bulk category names (already existed)
 *  5. get_bulk_user_completion_states — ONE query on course_modules_completion
 *     covering all courses and all CMs at once
 *  6. get_fast_modinfo($course, $userid) once per course (10 calls, not 20)
 *
 * @package    block_my_progress
 * @copyright  2025 AI Grader <support@lmshostingservices.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_my_progress;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/completionlib.php');

class progress_service {
    /** @var int Cache lifetime in seconds (30 seconds for fresher data) */
    const CACHE_LIFETIME = 30;

    /**
     * Get all progress data for a user.
     *
     * @param int  $userid     The user ID
     * @param bool $forcefresh Force fresh data (skip cache)
     * @return array Template-ready data
     */
    public function get_user_progress($userid, $forcefresh = false) {
        global $DB, $CFG;

        $cache    = \cache::make('block_my_progress', 'userprogress');
        $cachekey = 'progress_' . $userid;

        if (!$forcefresh) {
            $cached = $cache->get($cachekey);
            if ($cached !== false) {
                return $cached;
            }
        }

        $courses = enrol_get_users_courses(
            $userid, true, 'id, fullname, shortname, category, enddate, visible'
        );

        if (empty($courses)) {
            return [
                'hascourses'      => false,
                'categories'      => [],
                'totalcourses'    => 0,
                'completedcount'  => 0,
                'inprogresscount' => 0,
                'notstartedcount' => 0,
            ];
        }

        $courseids = array_keys($courses);
        list($insql, $params) = $DB->get_in_or_equal($courseids, SQL_PARAMS_NAMED);
        $params['userid'] = $userid;

        // Bulk-load enrolment dates — one query for all courses.
        $enrolments = $DB->get_records_sql("
            SELECT ue.id, e.courseid, ue.timecreated AS enroldate, ue.timeend AS enrolend
              FROM {user_enrolments} ue
              JOIN {enrol} e ON e.id = ue.enrolid
             WHERE ue.userid = :userid AND e.courseid $insql
        ", $params);

        $enrolmap = [];
        foreach ($enrolments as $enrol) {
            $enrolmap[$enrol->courseid] = $enrol;
        }

        // Bulk-load course completion dates — one query for all courses.
        $completions = $DB->get_records_sql("
            SELECT cc.course, cc.timecompleted
              FROM {course_completions} cc
             WHERE cc.userid = :userid AND cc.course $insql
        ", $params);

        $completionmap = [];
        foreach ($completions as $comp) {
            $completionmap[$comp->course] = $comp->timecompleted;
        }

        $categories = $this->get_categories($courses);

        // RC1+RC2+RC3 fix: bulk-load ALL activity completion states for this user across
        // all enrolled courses in one SQL query. This replaces the N×M×2 pattern of
        // completion->get_data() calls that ran inside get_course_progress() and
        // get_activity_completions() separately for every course.
        $bulk_completions = $this->get_bulk_user_completion_states($userid, $courseids);

        $grouped         = [];
        $completedcount  = 0;
        $inprogresscount = 0;
        $notstartedcount = 0;

        foreach ($courses as $course) {
            $catid = $course->category;
            if (!isset($grouped[$catid])) {
                $grouped[$catid] = [
                    'categoryid'    => $catid,
                    'categoryname'  => $categories[$catid] ?? 'Uncategorized',
                    'courses'       => [],
                    'catcompleted'  => 0,
                    'catinprogress' => 0,
                    'catnotstarted' => 0,
                ];
            }

            $coursedata = $this->build_course_data(
                $course, $userid, $enrolmap, $completionmap,
                $bulk_completions[$course->id] ?? []
            );
            $grouped[$catid]['courses'][] = $coursedata;

            if ($coursedata['iscompleted']) {
                $completedcount++;
                $grouped[$catid]['catcompleted']++;
            } elseif ($coursedata['progress'] > 0) {
                $inprogresscount++;
                $grouped[$catid]['catinprogress']++;
            } else {
                $notstartedcount++;
                $grouped[$catid]['catnotstarted']++;
            }
        }

        foreach ($grouped as $catid => &$catdata) {
            $total    = count($catdata['courses']);
            $remaining = $total - $catdata['catcompleted'];
            $catdata['catremaining'] = $remaining;
            $catdata['coursecount']  = $total;

            if ($catdata['catcompleted'] == $total) {
                $catdata['catstatus']      = 'complete';
                $catdata['catstatusclass'] = 'mp-cat-complete';
                $catdata['catallcomplete'] = true;
            } elseif ($catdata['catinprogress'] > 0 || $catdata['catcompleted'] > 0) {
                $catdata['catstatus']      = 'inprogress';
                $catdata['catstatusclass'] = 'mp-cat-in-progress';
            } else {
                $catdata['catstatus']      = 'notstarted';
                $catdata['catstatusclass'] = 'mp-cat-not-started';
            }
        }
        unset($catdata);

        $result = [
            'hascourses'      => true,
            'categories'      => array_values($grouped),
            'totalcourses'    => count($courses),
            'completedcount'  => $completedcount,
            'inprogresscount' => $inprogresscount,
            'notstartedcount' => $notstartedcount,
        ];

        $cache->set($cachekey, $result);

        return $result;
    }

    /**
     * Build course data array for template.
     *
     * Rewritten to accept pre-loaded bulk completion data and call get_fast_modinfo()
     * once per course instead of twice (previously called separately in get_course_progress()
     * and get_activity_completions()).
     *
     * @param object $course           Course object
     * @param int    $userid           User ID
     * @param array  $enrolmap         Enrolment data map (courseid => enrolment object)
     * @param array  $completionmap    Course completion map (courseid => timecompleted)
     * @param array  $course_completions Bulk completion states for this course: [cmid => state]
     * @return array Course data for template
     */
    private function build_course_data($course, $userid, $enrolmap, $completionmap, array $course_completions) {

        $enroldate = isset($enrolmap[$course->id]) ? $enrolmap[$course->id]->enroldate : 0;
        $enrolend  = isset($enrolmap[$course->id]) ? $enrolmap[$course->id]->enrolend  : 0;
        $enddate   = $this->resolve_end_date($course->enddate, $enrolend);

        $timecompleted = $completionmap[$course->id] ?? null;
        $iscompleted   = !empty($timecompleted);

        $isoverdue = !$iscompleted && $enddate > 0 && $enddate < time();

        // RC1+RC2+RC3 fix: get_fast_modinfo called ONCE per course (not twice).
        // Progress percentage and activity list are both derived from the same modinfo
        // pass plus the pre-loaded bulk completion state map — zero completion->get_data() calls.
        $completion      = new \completion_info($course);
        $trackable_cmids = [];
        $cminfo_map      = [];

        if ($completion->is_enabled()) {
            $modinfo = get_fast_modinfo($course, $userid);
            foreach ($modinfo->get_cms() as $cm) {
                if ($cm->completion == COMPLETION_TRACKING_NONE) {
                    continue;
                }
                if (!$cm->uservisible) {
                    continue;
                }
                $trackable_cmids[]   = $cm->id;
                $cminfo_map[$cm->id] = $cm;
            }
        }

        $trackablecount = count($trackable_cmids);
        $user_completed = 0;
        foreach ($trackable_cmids as $cmid) {
            $state = $course_completions[$cmid] ?? COMPLETION_INCOMPLETE;
            if ($state == COMPLETION_COMPLETE || $state == COMPLETION_COMPLETE_PASS) {
                $user_completed++;
            }
        }
        $progress = $trackablecount > 0 ? (int)round(($user_completed / $trackablecount) * 100) : 0;

        // Build activity list from the same in-memory data — no extra DB queries.
        $activities = [];
        foreach ($cminfo_map as $cmid => $cm) {
            $state      = $course_completions[$cmid] ?? COMPLETION_INCOMPLETE;
            $iscomplete = ($state == COMPLETION_COMPLETE || $state == COMPLETION_COMPLETE_PASS);
            $isfailed   = ($state == COMPLETION_COMPLETE_FAIL);

            $activities[] = [
                'cmid'      => $cm->id,
                'name'      => format_string($cm->name),
                'modname'   => $cm->modname,
                'iscomplete' => $iscomplete,
                'isfailed'  => $isfailed,
                'ispending' => !$iscomplete && !$isfailed,
                'url'       => $cm->url ? $cm->url->out(false) : '',
            ];
        }

        $status        = $this->determine_status($progress, $iscompleted, $isoverdue);
        $motivationtext = $this->get_motivation_text($progress, $iscompleted);
        $courseurl     = new \moodle_url('/course/view.php', ['id' => $course->id]);
        $activitycount = count($activities);

        return [
            'courseid'        => $course->id,
            'coursename'      => format_string($course->fullname),
            'courseurl'       => $courseurl->out(false),
            'progress'        => $progress,
            'progresswidth'   => min(100, max(0, $progress)),
            'enroldate'       => $enroldate > 0 ? userdate($enroldate, get_string('strftimedateshort')) : '',
            'enddate'         => $enddate > 0 ? userdate($enddate, get_string('strftimedateshort')) : '',
            'enddatesort'     => $enddate > 0 ? date('Y-m-d', $enddate) : '9999-12-31',
            'hasenddate'      => $enddate > 0,
            'iscompleted'     => $iscompleted,
            'completeddate'   => $iscompleted ? userdate($timecompleted, get_string('strftimedateshort')) : '',
            'isoverdue'       => $isoverdue,
            'isinprogress'    => !$iscompleted && $progress > 0,
            'isnotstarted'    => !$iscompleted && $progress == 0,
            'status'          => $status,
            'statusclass'     => $this->get_status_class($status),
            'activities'      => array_slice($activities, 0, 5),
            'hasactivities'   => !empty($activities),
            'activitycount'   => $activitycount,
            'hasmore'         => $activitycount > 5,
            'morecount'       => max(0, $activitycount - 5),
            'allactivities'   => $activities,
            'motivationtext'  => $motivationtext,
            'showmotivation'  => !empty($motivationtext),
        ];
    }

    /**
     * Bulk-load all activity completion states for one user across multiple courses.
     *
     * Returns a nested map: $result[$courseid][$cmid] = completionstate (int).
     * Missing entries mean COMPLETION_INCOMPLETE (0).
     *
     * This replaces the N×M×2 completion->get_data() calls from the original code
     * (N courses × M activities × 2 methods = many calls for a user with many courses).
     *
     * @param int   $userid    The student user ID
     * @param int[] $courseids Course IDs to include
     * @return array Nested [courseid][cmid] => completionstate map
     */
    private function get_bulk_user_completion_states(int $userid, array $courseids): array {
        global $DB;

        if (empty($courseids)) {
            return [];
        }

        [$course_sql, $params] = $DB->get_in_or_equal($courseids, SQL_PARAMS_NAMED, 'c');
        $params['userid'] = $userid;

        $rows = $DB->get_records_sql(
            "SELECT cmc.id, cmc.coursemoduleid, cmc.completionstate, cm.course
               FROM {course_modules_completion} cmc
               JOIN {course_modules} cm ON cm.id = cmc.coursemoduleid
              WHERE cmc.userid = :userid
                AND cm.course $course_sql
                AND cm.completion > 0",
            $params
        );

        $result = [];
        foreach ($rows as $row) {
            $result[(int)$row->course][(int)$row->coursemoduleid] = (int)$row->completionstate;
        }
        return $result;
    }

    /**
     * Resolve course end date with fallback logic.
     *
     * @param int $courseenddate Course end date
     * @param int $enrolend      Enrolment end date
     * @return int End date timestamp or 0
     */
    private function resolve_end_date($courseenddate, $enrolend) {
        if ($courseenddate > 0) {
            return $courseenddate;
        }
        if ($enrolend > 0) {
            return $enrolend;
        }
        return 0;
    }

    /**
     * Determine course status.
     *
     * @param int  $progress    Progress percentage
     * @param bool $iscompleted Is course completed
     * @param bool $isoverdue   Is course overdue
     * @return string Status key
     */
    private function determine_status($progress, $iscompleted, $isoverdue) {
        if ($iscompleted) {
            return 'completed';
        }
        if ($isoverdue) {
            return 'overdue';
        }
        if ($progress > 0) {
            return 'inprogress';
        }
        return 'notstarted';
    }

    /**
     * Get CSS class for status.
     *
     * @param string $status Status key
     * @return string CSS class
     */
    private function get_status_class($status) {
        $classes = [
            'completed'  => 'mp-complete',
            'inprogress' => 'mp-in-progress',
            'notstarted' => 'mp-not-started',
            'overdue'    => 'mp-in-progress',
        ];
        return $classes[$status] ?? 'mp-not-started';
    }

    /**
     * Get motivation text based on progress.
     *
     * @param int  $progress    Progress percentage
     * @param bool $iscompleted Is completed
     * @return string Motivation text
     */
    private function get_motivation_text($progress, $iscompleted) {
        if ($iscompleted) {
            return get_string('completedcourse', 'block_my_progress');
        }
        if ($progress >= 90) {
            return get_string('almostthere', 'block_my_progress');
        }
        if ($progress >= 50) {
            return get_string('greatprogress', 'block_my_progress');
        }
        if ($progress >= 20) {
            return get_string('keepgoing', 'block_my_progress');
        }
        if ($progress > 0) {
            return get_string('juststarted', 'block_my_progress');
        }
        return '';
    }

    /**
     * Get category names for courses (single bulk query).
     *
     * @param array $courses Course objects
     * @return array Category ID => Name map
     */
    private function get_categories($courses) {
        global $DB;

        $catids = array_unique(array_column($courses, 'category'));
        if (empty($catids)) {
            return [];
        }

        list($insql, $params) = $DB->get_in_or_equal($catids, SQL_PARAMS_NAMED);
        $cats = $DB->get_records_sql("
            SELECT id, name FROM {course_categories} WHERE id $insql
        ", $params);

        $result = [];
        foreach ($cats as $cat) {
            $result[$cat->id] = format_string($cat->name);
        }
        return $result;
    }
}
