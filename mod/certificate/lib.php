<?php

// This file is part of Certificate module for Moodle - http://moodle.org/
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
 * Certificate module core interaction API
 *
 * @package    mod
 * @subpackage certificate
 * @copyright  Chardelle Busch, Mark Nelson
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once($CFG->dirroot.'/course/lib.php');
require_once($CFG->dirroot.'/grade/lib.php');
require_once($CFG->dirroot.'/grade/querylib.php');

/**
 * Add certificate instance.
 *
 * @param stdClass $certificate
 * @return int new certificate instance id
 */
function certificate_add_instance($certificate) {
    global $DB;
    $certificate->timemodified = time();

    if ($returnid = $DB->insert_record('certificate', $certificate)) {
        $certificate->id = $returnid;

        $event = NULL;
        $event->name        = $certificate->name;
        $event->description = '';
        $event->courseid    = $certificate->course;
        $event->groupid     = 0;
        $event->userid      = 0;
        $event->eventtype = 'course';
        $event->modulename  = 'certificate';
        $event->instance    = $returnid;
        add_event($event);
    }

    return $returnid;
}

/**
 * Update certificate instance.
 *
 * @param stdClass $certificate
 * @return bool true
 */
function certificate_update_instance($certificate) {
    global $DB;
    $certificate->timemodified = time();
    $certificate->id = $certificate->instance;

    if ($returnid = $DB->update_record('certificate', $certificate)) {

        if ($event->id = $DB->get_field('event', 'id', array('modulename'=> 'certificate', 'instance'=> $certificate->id))) {
            $event->name        = $certificate->name;
            update_event($event);
        } else {
            $event = NULL;
            $event->name        = $certificate->name;
            $event->description = '';
            $event->courseid    = $certificate->course;
            $event->groupid     = 0;
            $event->userid      = 0;
            $event->modulename  = 'certificate';
            $event->instance    = $certificate->id;

            add_event($event);
        }
    } else {
        $DB->delete_records('event', array('modulename'=>'certificate', 'instance'=> $certificate->id));
    }

    return $returnid;
}

/**
 *
 * Given an ID of an instance of this module,
 * this function will permanently delete the instance
 * and any data that depends on it.
 *
 * @param int $id
 * @return bool success
 */
function certificate_delete_instance($id) {
    global $CFG, $DB, $USER;

    if (!$certificate = $DB->get_record('certificate', array('id'=> $id))) {
        return false;
    }

    // Prepare file record object
    if (!$cm = get_coursemodule_from_instance('certificate', $id)) {
        return false;
    }

    $result = true;

    $DB->delete_records('certificate_issues', array('certificateid'=> $id));

    if (!$DB->delete_records('certificate', array('id'=> $id))) {
        $result = false;
    }

    $context = get_context_instance(CONTEXT_MODULE, $cm->id);

    $fs = get_file_storage();

    $fs->delete_area_files($context->id);

    return $result;
}

/**
 * This function is used by the reset_course_userdata function in moodlelib.
 * This function will remove all posts from the specified certificate
 * and clean up any related data.
 * 
 * Written by Jean-Michel Vedrine
 * 
 * @param $data the data submitted from the reset course.
 * @return array status array
 */
function certificate_reset_userdata($data) {
    global $CFG, $DB;

    $componentstr = get_string('modulenameplural', 'certificate');
    $status = array();

    if (!empty($data->reset_certificate)) {
        $certificatessql = "SELECT cert.id
                       FROM {certificate} cert
                       WHERE cert.course=?";

        $DB->delete_records_select('certificate_issues', "certificateid IN ($certificatessql)", array($data->courseid));
        $status[] = array('component'=>$componentstr, 'item'=>get_string('certificateremoved', 'certificate'), 'error'=>false);
    }

    // Updating dates - shift may be negative too
    if ($data->timeshift) {
        shift_course_mod_dates('certificate', array('timeopen', 'timeclose'), $data->timeshift, $data->courseid);
        $status[] = array('component'=>$componentstr, 'item'=>get_string('datechanged'), 'error'=>false);
    }

    return $status;
}

/**
 * Implementation of the function for printing the form elements that control
 * whether the course reset functionality affects the certificate.
 * 
 * Written by Jean-Michel Vedrine
 * 
 * @param $mform form passed by reference 
 */
function certificate_reset_course_form_definition(&$mform) {
    $mform->addElement('header', 'certificateheader', get_string('modulenameplural', 'certificate'));
    $mform->addElement('advcheckbox', 'reset_certificate', get_string('deletissuedcertificates','certificate'));
}

/**
 * Course reset form defaults.
 * 
 * Written by Jean-Michel Vedrine
 * 
 * @param $course
 * @return array
 */
function certificate_reset_course_form_defaults($course) {
    return array('reset_certificate'=>1);
}

/**
 * Returns information about received certificate.
 * Used for user activity reports.
 * 
 * @param stdClass $course
 * @param stdClass $user
 * @param stdClass $mod
 * @param stdClass $certificate
 * @return object|null
 */
function certificate_user_outline($course, $user, $mod, $certificate) {
    global $DB;
    if ($issue = $DB->get_record('certificate_issues', 'certificateid', $certificate->id, 'userid', $user->id)) {
        $result->info = get_string('issued', 'certificate');
        $result->time = $issue->certdate;
    }
    if (!$issue = $DB->get_record('certificate_issues', 'certificateid', $certificate->id, 'userid', $user->id)) {
        $result->info = get_string('notissued', 'certificate');
    }
    return $result;
}

/**
 * Returns information about received certificate.
 * Used for user activity reports.
 * 
 * @param stdClass $course
 * @param stdClass $user
 * @param stdClass $mod
 * @param stdClass $page
 * @return object|null
 */
function certificate_user_complete($course, $user, $mod, $certificate) {
   global $DB;
   if ($issue = $DB->get_record('certificate_issues', 'certificateid', $certificate->id, 'userid', $user->id)) {
        print_simple_box_start();
        echo get_string('issued', 'certificate').": ";
        echo userdate($issue->certdate);

        certificate_print_user_files($certificate->id, $user->id);

        echo '<br />';
        print_simple_box_end();
    } else {
        print_string('notissuedyet', 'certificate');
    }
}

/**
 * Must return an array of user records (all data) who are participants
 * for a given instance of certificate.
 * 
 * @param int $certificateid
 * @return object list of participants
 */
function certificate_get_participants($certificateid) {
    global $CFG, $DB;

    //Get students
    $participants = $DB->get_records_sql("SELECT DISTINCT u.id, u.id
                                 FROM {user} u,
                                      {certificate_issues} a
                                 WHERE a.certificateid = '$certificateid' and
                                       u.id = a.userid");
    return $participants;
}

/**
 * @uses FEATURE_GROUPS
 * @uses FEATURE_GROUPINGS
 * @uses FEATURE_GROUPMEMBERSONLY
 * @uses FEATURE_MOD_INTRO
 * @uses FEATURE_COMPLETION_TRACKS_VIEWS
 * @uses FEATURE_GRADE_HAS_GRADE
 * @uses FEATURE_GRADE_OUTCOMES
 * @param string $feature FEATURE_xx constant for requested feature
 * @return mixed True if module supports feature, null if doesn't know
 */
function certificate_supports($feature) {
    switch($feature) {
        case FEATURE_GROUPS:                  return true;
        case FEATURE_GROUPINGS:               return true;
        case FEATURE_GROUPMEMBERSONLY:        return true;
        case FEATURE_MOD_INTRO:               return true;
        case FEATURE_COMPLETION_TRACKS_VIEWS: return true;
        case FEATURE_BACKUP_MOODLE2:          return true;

        default: return null;
    }
}

/**
 * Prints the header in view.  Used to help prevent FPDF header errors.
 * 
 * @param stdClass $course
 * @param stdClass $certificate
 * @param stdClass $cm
 * @return null
 */
function view_header($course, $certificate, $cm) {
    global $CFG, $DB, $PAGE, $OUTPUT;

    $PAGE->set_title(format_string($certificate->name));
    $PAGE->set_heading(format_string($course->fullname));
    echo $OUTPUT->header();

    $context = get_context_instance(CONTEXT_MODULE, $cm->id);
    if (has_capability('mod/certificate:manage', $context)) {
        $numusers = count(certificate_get_issues($certificate->id, '', '', $cm));
        echo '<div class="reportlink"><a href="report.php?id='.$cm->id.'">'.
              get_string('viewcertificateviews', 'certificate', $numusers).'</a></div>';
    }

    if (!empty($certificate->intro)) {
        global $OUTPUT;

        echo $OUTPUT->box(format_text($certificate->intro), 'generalbox', 'intro');
    }
}

/**
 * Function to be run periodically according to the moodle cron
 * TODO:This needs to be done                        
 */
function certificate_cron () {
    return true;
}

/**
 * Returns a list of teachers by group                
 * for sending email alerts to teachers              
 * 
 * @param stdClass $certificate
 * @param stdClass $user
 * @param stdClass $course
 * @param stdClass $cm
 * @return array                                     
 */
function certificate_get_teachers($certificate, $user, $course, $cm) {
    global $USER, $DB;

    $context = get_context_instance(CONTEXT_MODULE, $cm->id);
    $potteachers = get_users_by_capability($context, 'mod/certificate:manage', '', '', '', '', '', '', false, false);
    if (empty($potteachers)) {
        return array();
    }
    $teachers = array();
    if (groups_get_activity_groupmode($cm, $course) == SEPARATEGROUPS) {   // Separate groups are being used
        if ($groups = groups_get_all_groups($course->id, $user->id)) {  // Try to find all groups
            foreach ($groups as $group) {
                foreach ($potteachers as $t) {
                    if ($t->id == $user->id) {
                        continue; // do not send self
                    }
                    if (groups_is_member($group->id, $t->id)) {
                        $teachers[$t->id] = $t;
                    }
                }
            }
        } else {
            // user not in group, try to find teachers without group
            foreach ($potteachers as $t) {
                if ($t->id == $USER->id) {
                    continue; // do not send self
                }
                if (!groups_get_all_groups($course->id, $t->id)) { //ugly hack
                    $teachers[$t->id] = $t;
                }
            }
        }
    } else {
        foreach ($potteachers as $t) {
            if ($t->id == $USER->id) {
                continue; // do not send self
            }
            $teachers[$t->id] = $t;
        }
    }
    return $teachers;
}

/**
 * Alerts teachers by email of received certificates. First checks
 * whether the option to email teachers is set for this certificate.
 * 
 * @param stdClass $course
 * @param stdClass $certificate
 * @param stdClass $certrecord
 * @param stdClass $cm course module
 * @return null
 */
function certificate_email_teachers($course, $certificate, $certrecord, $cm) {
    global $USER, $CFG, $DB;

    if ($certificate->emailteachers == 0) {          // No need to do anything
        return;
    }
    $student = $certrecord->studentname;
    $user = $DB->get_record('user', array('id'=> $certrecord->userid));

    if ($teachers = certificate_get_teachers($certificate, $user, $course, $cm)) {

        $strawarded  = get_string('awarded', 'certificate');

        foreach ($teachers as $teacher) {
            unset($info);

            $info->student = $student;
            $info->course = format_string($course->fullname,true);
            $info->certificate = format_string($certificate->name,true);
            $info->url = $CFG->wwwroot.'/mod/certificate/report.php?id='.$cm->id;
            $from = $student;
            $postsubject = $strawarded.': '.$info->student.' -> '.$certificate->name;
            $posttext = certificate_email_teachers_text($info);
            $posthtml = ($teacher->mailformat == 1) ? certificate_email_teachers_html($info) : '';

            @email_to_user($teacher, $from, $postsubject, $posttext, $posthtml);  // If it fails, oh well, too bad.
        }
    }
}

/**
 * Alerts others by email of received certificates. First checks
 * whether the option to email others is set for this certificate.
 * Uses the email_teachers info.      
 * Code suggested by Eloy Lafuente 
 */
function certificate_email_others ($course, $certificate, $certrecord, $cm) {
    global $USER, $CFG, $DB;

    if ($certificate->emailothers) {
       $student = $certrecord->studentname;

       $others = explode(',', $certificate->emailothers);
        if ($others) {
            $strawarded  = get_string('awarded', 'certificate');
            foreach ($others as $other) {
                $other = trim($other);
                if (validate_email($other)) {
                    $destination->email = $other;
                    unset($info);
                    $info->student = $student;
                    $info->course = format_string($course->fullname,true);
                    $info->certificate = format_string($certificate->name,true);
                    $info->url = $CFG->wwwroot.'/mod/certificate/report.php?id='.$cm->id;
                    $from = $student;
                    $postsubject = $strawarded.': '.$info->student.' -> '.$certificate->name;
                    $posttext = certificate_email_teachers_text($info);
                    $posthtml = certificate_email_teachers_html($info);

                    @email_to_user($destination, $from, $postsubject, $posttext, $posthtml);  // If it fails, oh well, too bad.
                }
            }
        }
    }
}

/**
 * Creates the text content for emails to teachers -- needs to be finished with cron
 * 
 * @param $info object The info used by the 'emailteachermail' language string
 * @return string                                    
 */
function certificate_email_teachers_text($info) {
    $posttext = get_string('emailteachermail', 'certificate', $info)."\n";
    return $posttext;
}

/**
 * Creates the html content for emails to teachers
 * 
 * @param $info object The info used by the 'emailteachermailhtml' language string
 * @return string                                    
 */
function certificate_email_teachers_html($info) {
    $posthtml  = '<font face="sans-serif">';
    $posthtml .= '<p>'.get_string('emailteachermailhtml', 'certificate', $info).'</p>';
    $posthtml .= '</font>';
    return $posthtml;
}

/**
 * Sends the student their issued certificate from moddata as an email
 * attachment.
 * 
 * @param stdClass $user
 * @param stdClass $course
 * @param stdClass $certificate
 * @param stdClass $certrecord
 * @param stdClass $context
 */
function certificate_email_students($user, $course, $certificate, $certrecord, $context) {
    global $DB, $USER;
    if ($certrecord->mailed > 0)    {
        return;
    }

    // Get teachers
    if ($users = get_users_by_capability($context, 'moodle/course:update', 'u.*', 'u.id ASC',
                                     '', '', '', '', false, true)) {
        $users = sort_by_roleassignment_authority($users, $context);
        $teacher = array_shift($users);
    }

    // If we haven't found a teacher yet, look for a non-editing teacher in this course.
    if (empty($teacher) && $users = get_users_by_capability($context, 'moodle/course:update', 'u.*', 'u.id ASC',
                                     '', '', '', '', false, true)) {
        $users = sort_by_roleassignment_authority($users, $context);
        $teacher = array_shift($users);
    }

    $info->username = fullname($user);
    $info->certificate = format_string($certificate->name,true);
    $info->course = format_string($course->fullname,true);
    $from = fullname($teacher);
    $subject = $info->course.': '.$info->certificate;
    $message = get_string('emailstudenttext', 'certificate', $info)."\n";

    // Make the HTML version more XHTML happy  (&amp;)
    $messagehtml = text_to_html(get_string('emailstudenttext', 'certificate', $info));
    $user->mailformat = 0;  // Always send HTML version as well
    $filename = clean_filename($certificate->name.'.pdf');

    // Get hashed pathname
    $fs = get_file_storage();

    $component = 'mod_certificate';
    $filearea = 'issue';
    $filepath = '/';
    $files = $fs->get_area_files($context->id, $component, $filearea, $certificate->id);
    foreach ($files as $f) {
        $filepathname = $f->get_contenthash();
    }
    $attachment = 'filedir/'.certificate_path_from_hash($filepathname).'/'.$filepathname;
    $attachname = $filename;

    $DB->set_field('certificate_issues','mailed','1',array('certificateid'=> $certificate->id, 'userid'=> $user->id));
    return email_to_user($user, $from, $subject, $message, $messagehtml, $attachment, $attachname);
}

/**
 * Retrieve certificate path from hash
 * 
 * @param array $contenthash
 * @return string the path
 */
function certificate_path_from_hash($contenthash) {
    $l1 = $contenthash[0].$contenthash[1];
    $l2 = $contenthash[2].$contenthash[3];
    return "$l1/$l2";
}

/**
 * Serves certificate issues and other files.
 *
 * @param stdClass $course
 * @param stdClass $cm
 * @param stdClass $context
 * @param string $filearea
 * @param array $args
 * @param bool $forcedownload
 * @return bool false if file not found, does not return if found - just send the file
 */
function certificate_pluginfile($course, $cm, $context, $filearea, $args, $forcedownload) {
    global $CFG, $DB, $USER;

    if ($context->contextlevel != CONTEXT_MODULE) {
        return false;
    }

    if (!$certificate = $DB->get_record('certificate', array('id'=>$cm->instance))) {
        return false;
    }
    
    require_login($course, false, $cm);

    require_once($CFG->libdir.'/filelib.php');

    if ($filearea === 'issue') {
        $certrecord = (int)array_shift($args);

        if (!$certrecord = $DB->get_record('certificate_issues', array('id'=>$certrecord))) {
            return false;
        }

        if ($USER->id != $certrecord->userid and !has_capability('mod/certificate:manage', $context)) {
            return false;
        }

        $relativepath = implode('/', $args);
        $fullpath = "/{$context->id}/mod_certificate/issue/$certrecord->id/$relativepath";

        $fs = get_file_storage();
        if (!$file = $fs->get_file_by_hash(sha1($fullpath)) or $file->is_directory()) {
            return false;
        }
        send_stored_file($file, 0, 0, true); // download MUST be forced - security!
    }
}

/**
 * This function returns success or failure of file save
 *
 * @global object
 * @param string $pdf is the string contents of the pdf
 * @param int $certificateid the certificate id
 * @param string $filename pdf filename
 * @param int $contextid context id
 * @return bool
 */
function certificate_save_pdf($pdf, $certificateid, $filename, $contextid) {
    global $DB, $USER;

    if (empty($certificateid)) {
        return false;
    }

    if (empty($pdf)) {
        return true;   // Nothing to do
    }

    $fs = get_file_storage();

    // Prepare file record object
    $component = 'mod_certificate';
    $filearea = 'issue';
    $filepath = '/';
    $fileinfo = array(
        'contextid' => $contextid,   // ID of context
        'component' => $component,   // usually = table name
        'filearea' => $filearea,     // usually = table name
        'itemid' => $certificateid,  // usually = ID of row in table
        'filepath' => $filepath,     // any path beginning and ending in /
        'filename' => $filename,    // any filename
        'mimetype' => 'application/pdf',    // any filename
        'userid'    => $USER->id);

    // Check for file first
    if (!$fs->file_exists($contextid, $component, $filearea, $certificateid, $filepath, $filename)) {
        $fs->create_file_from_string($fileinfo, $pdf);
    }

    return true;
}

/**
 * Produces a list of links to the issued certificates.  Used for report.
 * 
 * @param stdClass $certificate
 * @param int $userid optional id of the user. If 0 then $USER->id is used.
 * @param stdClass $context
 * @return boolean, true if success printing
 */
function certificate_print_user_files($certificate, $userid=0, $context) {
    global $CFG, $DB, $OUTPUT;

    $output = '';
    $sql = 'SELECT MAX(timecreated) AS latest FROM {certificate_issues} '.
                           'WHERE userid = '.$userid.' and certificateid = '.$certificate->id.'';
            if ($record = $DB->get_record_sql($sql)) {
                $latest = $record->latest;
            }

    $certrecord = $DB->get_record('certificate_issues', array('certificateid'=>$certificate->id, 'userid'=>$userid, 'timecreated'=>$latest));
    $fs = get_file_storage();
    $browser = get_file_browser();

    $component = 'mod_certificate';
    $filearea = 'issue';
    $files = $fs->get_area_files($context, $component, $filearea, $certrecord->id);
    foreach ($files as $file) {
                $filename = $file->get_filename();
                $mimetype = $file->get_mimetype();
                $link = file_encode_url($CFG->wwwroot.'/pluginfile.php', '/'.$context.'/mod_certificate/issue/'.$certrecord->id.'/'.$filename);

        $output = '<img src="'.$OUTPUT->pix_url(file_mimetype_icon($file->get_mimetype())).'" height="16" width="16" alt="'.$file->get_mimetype().'" />&nbsp;'.
               '<a href="'.$link.'" >'.s($filename).'</a>';

    }
                $output .= '<br />';
        $output = '<div class="files">'.$output.'</div>';

        return $output;
}

/**
 * Returns a list of issued certificates - sorted for report.
 * 
 * @param stdClass $certificate
 * @param string $sort the sort order
 * @param boolean $groupmode are we in group mode ?
 * @param stdClass $cm the course module
 */
function certificate_get_issues($certificate, $sort="u.studentname ASC", $groupmode, $cm) {
    global $CFG, $DB;
    //get all users that can manage this certificate to exclude them from the report.
    $context = get_context_instance(CONTEXT_MODULE, $cm->id);
    $certmanagers = get_users_by_capability($context, 'mod/certificate:manage', 'u.id');

    //get all the users that have certificates issued.
    $users = $DB->get_records_sql("SELECT u.*,u.picture, s.code, s.timecreated, s.certdate, s.studentname, s.reportgrade
                              FROM {$CFG->prefix}certificate_issues s,
                                   {$CFG->prefix}user u
                             WHERE s.certificateid = '$certificate'
                               AND s.userid = u.id
                               AND s.certdate > 0
                            GROUP BY u.id");
    //now exclude all the certmanagers.
    foreach ($users as $id=>$user) {
        if (isset($certmanagers[$id])) { //exclude certmanagers.
            unset($users[$id]);
        }
    }

    // if groupmembersonly used, remove users who are not in any group
    if (!empty($users) and !empty($CFG->enablegroupings) and $cm->groupmembersonly) {
        if ($groupingusers = groups_get_grouping_members($cm->groupingid, 'u.id', 'u.id')) {
            $users = array_intersect($users, array_keys($groupingusers));
        }
    }

    if (!$groupmode) {
        return $users;
    } else {
        $currentgroup = groups_get_activity_group($cm);
        if ($currentgroup) {
            $groupusers = groups_get_members($currentgroup, 'u.*');
            if (empty($groupusers)) {
                return array();
            }
            foreach($groupusers as $id => $gpuser) {
                if (!isset($users[$id])) {
                    //remove this user as it isn't in the group!
                    unset($users[$id]);
                }
            }
        }

        return $users;
    }
}

/**
 * Returns a list of previously issued certificates--used for reissue.
 * 
 * @param int $certificateid
 * @param int $userid
 * @return int the number of attempts
 */
function certificate_get_attempts($certificateid, $userid) {
    global $DB;

    if ($issues = $DB->get_records('certificate_issues', array('certificateid'=>$certificateid, 'userid'=>$userid))) {
        return $issues;
    } else {
        return 0;
    }
}

/**
 * Prints a table of previously issued certificates--used for reissue.
 * 
 * @param int $certificate id
 * @param int $userid
 * @param null
 */
function certificate_print_attempts($certificateid, $userid) {
    global $OUTPUT, $DB;

    if (!$certificate = $DB->get_record('certificate', array('id'=> $certificateid))) {
        return false;
    }
    $attempts = certificate_get_attempts($certificateid, $userid);

    echo $OUTPUT->heading(get_string('summaryofattempts', 'certificate'));

    // Prepare table header
    $table = new html_table();
    $table->class = 'generaltable';
    $table->head = array(get_string('issued', 'certificate'));
    $table->align = array('left', 'left');
    $gradecolumn = $certificate->printgrade;
    if ($gradecolumn) {
        $table->head[] = get_string('grade');
        $table->align[] = 'center';
        $table->size[] = '';
    }
    // One row for each attempt
    foreach ($attempts as $attempt) {
        $row = array();

        // prepare strings for time taken and date completed
        $datecompleted = '';
        if ($attempt->certdate > 0) {
            // attempt has finished
            $datecompleted = userdate($attempt->certdate);
        }
        $row[] = $datecompleted;

        // Ouside the if because we may be showing feedback but not grades.
        if ($gradecolumn) {
            $attemptgrade = ($attempt->reportgrade);
                $row[] = $attemptgrade;
            } else {
                $row[] = '';
            }

            $table->data[$attempt->id] = $row;
    }
    echo html_writer::table($table);
}

/**
 * Inserts preliminary user data when a certificate is viewed.
 * Prevents form from issuing a certificate upon browser refresh.
 * 
 * @param stdClass $course
 * @param stdClass $user
 * @param stdClass $certificate
 * @return null
 */
function certificate_prepare_issue($course, $user, $certificate) {
    global $DB;

    if ($certificate->reissuecert == 0) { 
        if ($DB->record_exists('certificate_issues', array('certificateid'=>$certificate->id, 'userid'=>$user->id))) {
            return;
        }
    } else if ($DB->record_exists('certificate_issues', array('certificateid'=>$certificate->id, 'userid'=>$user->id, 'certdate'=>'0'))) {
        return;
    }

    $timecreated = time();
    $code = certificate_generate_code();
    $studentname = fullname($user);
    $newrec = new Object();
    $newrec->certificateid = $certificate->id;
    $newrec->userid = $user->id;
    $newrec->timecreated = $timecreated;
    $newrec->studentname = $studentname;
    $newrec->code = $code;
    $newrec->classname = $course->fullname;

    $DB->insert_record('certificate_issues', $newrec, false);
}

/**
 * Inserts final user data when a certificate is created.
 * 
 * @param stdClass $course
 * @param stdClass $certrecord
 * @param stdClass $cm
 */
function certificate_issue($course, $certrecord, $cm) {
    global $USER, $DB, $certificate;

    $sql = 'SELECT MAX(timecreated) AS latest FROM {certificate_issues} ' .
           'WHERE userid = '.$USER->id.' and certificateid = '.$certificate->id.'';
    if ($record = $DB->get_record_sql($sql)) {
        $latest = $record->latest;
    }
    $certrecord = $DB->get_record('certificate_issues', array('certificateid'=>$certificate->id, 'userid'=>$USER->id, 'timecreated'=>$latest));
    if ($certificate->printgrade) {
        if ($certificate->printgrade == 1) {
            $grade = certificate_print_course_grade($course);
        } else if($certificate->printgrade > 1) {
            $grade = certificate_print_mod_grade($course, $certificate->printgrade);
        }
        if ($certificate->gradefmt == 1) {
            $certrecord->reportgrade = $grade->percentage;
        }
        if ($certificate->gradefmt == 2) {
            $certrecord->reportgrade = $grade->points;
        }
        if ($certificate->gradefmt == 3) {
            $certrecord->reportgrade = $grade->letter;
        }
    }
    $date = certificate_generate_date($certificate, $course);
    $certrecord->certdate = $date;
    $DB->update_record('certificate_issues', $certrecord);
    certificate_email_teachers($course, $certificate, $certrecord, $cm);
    certificate_email_others($course, $certificate, $certrecord, $cm);
}

/**
 * Search through all the modules for grade data for mod_form.
 * 
 * @return array
 */
function certificate_get_mod_grades() {
    global $course, $CFG, $DB;

    $strgrade = get_string('grade', 'certificate');
    // Collect modules data
    get_all_mods($course->id, $mods, $modnames, $modnamesplural, $modnamesused);

    $printgrade = array();
    $sections = get_all_sections($course->id); // Sort everything the same as the course
    for ($i=0; $i<=$course->numsections; $i++) {
        // should always be true
        if (isset($sections[$i])) {
            $section = $sections[$i];
            if ($section->sequence) {
                switch ($course->format) {
                    case 'topics':
                    $sectionlabel = get_string('topic');
                    break;
                    case 'weeks':
                    $sectionlabel = get_string('week');
                    break;
                    default:
                    $sectionlabel = get_string('section');
                }

                $sectionmods = explode(",", $section->sequence);
                foreach ($sectionmods as $sectionmod) {
                    if (empty($mods[$sectionmod])) {
                        continue;
                    }
                    $mod = $mods[$sectionmod];
                    $mod->courseid = $course->id;
                    $instance = $DB->get_record($mod->modname, array('id'=> $mod->instance));
                    if ($grade_items = grade_get_grade_items_for_activity($mod)) {
                        $mod_item = grade_get_grades($course->id, 'mod', $mod->modname, $mod->instance);
$item = reset($mod_item->items);
                        if (isset($item->grademax)) {
                            $printgrade[$mod->id] = $sectionlabel.' '.$section->section.' : '.$instance->name.' '.$strgrade;
                        }
                    }
                }
            }
        }
    }
    if (isset($printgrade)) {
        $gradeoptions['0'] = get_string('no');
        $gradeoptions['1'] = get_string('coursegrade', 'certificate');
        foreach ($printgrade as $key => $value) {
            $gradeoptions[$key] = $value;
        }
    } else {
        $gradeoptions['0'] = get_string('nogrades', 'certificate');
    }
    return ($gradeoptions);
}

/**
 * Search through all the modules for grade dates for mod_form.
 * 
 * @return array
 */
function certificate_get_date() {
    global $course, $DB;
    $strgradedate = get_string('gradedate', 'certificate');
    // Collect modules data
    get_all_mods($course->id, $mods, $modnames, $modnamesplural, $modnamesused);

    $printgrade = array();
    $sections = get_all_sections($course->id); // Sort everything the same as the course
    for ($i=0; $i<=$course->numsections; $i++) {
        // should always be true
        if (isset($sections[$i])) {
            $section = $sections[$i];
            if ($section->sequence) {
                switch ($course->format) {
                    case "topics":
                    $sectionlabel = get_string("topic");
                    break;
                    case "weeks":
                    $sectionlabel = get_string("week");
                    break;
                    default:
                    $sectionlabel = get_string("section");
                }

                $sectionmods = explode(",", $section->sequence);
                foreach ($sectionmods as $sectionmod) {
                    if (empty($mods[$sectionmod])) {
                        continue;
                    }
                    $mod = $mods[$sectionmod];
                        $mod->courseid = $course->id;
                        $instance = $DB->get_record($mod->modname, array('id'=> $mod->instance));
                        if ($grade_items = grade_get_grade_items_for_activity($mod)) {
							$mod_item = grade_get_grades($course->id, 'mod', $mod->modname, $mod->instance);
    $item = reset($mod_item->items);
        if(isset($item->grademax)){

                            $printgrade[$mod->id] = $sectionlabel.' '.$section->section.' : '.$instance->name.' '.$strgradedate;
                        }
				    }
                }
            }
        }
    }
        $dateoptions['0'] = get_string('no');
        $dateoptions['1'] = get_string('issueddate', 'certificate');
        $dateoptions['2'] = get_string('courseenddate', 'certificate');
        foreach ($printgrade as $key => $value) {
            $dateoptions[$key] = $value;
    }
    return ($dateoptions);
}

/**
 * Get the course outcomes for for mod_form print outcome.
 * 
 */
function certificate_get_outcomes() {
    global $course, $DB;

    // get all outcomes in course
    $grade_seq = new grade_tree($course->id, false, true, '', false);
    if ($grade_items = $grade_seq->items) {
    // list of item for menu
    $printoutcome = array();
        foreach ($grade_items as $grade_item) {
            if(isset($grade_item->outcomeid)){
                $itemmodule = $grade_item->itemmodule;
                $printoutcome[$grade_item->id] = $itemmodule.': '.$grade_item->get_name();
            }
        }
    }
    if (isset($printoutcome)) {
        $outcomeoptions['0'] = get_string('no');
        foreach ($printoutcome as $key => $value) {
            $outcomeoptions[$key] = $value;
        }
    } else {
        $outcomeoptions['0'] = get_string('nooutcomes', 'certificate');
    }
    return ($outcomeoptions);
}

/**
 * Used for course participation report (in case certificate is added).
 * 
 * @return array
 */
function certificate_get_view_actions() {
    return array('view','view all','view report');
}

/**
 * Used for course participation report (in case certificate is added).
 * 
 * @return array
 */
function certificate_get_post_actions() {
    return array('received');
}

/**
 * Get certificate types indexed and sorted by name for mod_form.
 * 
 * @return array containing the certificate type
 */
function certificate_types() {
    $types = array();
    $names = get_list_of_plugins('mod/certificate/type');
    foreach ($names as $name) {
        $types[$name] = get_string('type'.$name, 'certificate');
    }
    asort($types);
    return $types;
}

/**
 * Get border images for mod_form.
 * 
 * @return array
 */
function certificate_get_borders () {
    global $CFG, $DB;
    // load border files
    $my_path = "$CFG->dirroot/mod/certificate/pix/borders";
    $borderstyleoptions = array();
    if ($handle = opendir($my_path)) {
        while (false !== ($file = readdir($handle))) {
        if (strpos($file, '.png',1)||strpos($file, '.jpg',1) ) {
                $i = strpos($file, '.');
                if($i > 1) {
                // Set the style name
                    $borderstyleoptions[$file] = substr($file, 0, $i);

                }
            }
        }
        closedir($handle);
    }

    // Sort borders
    ksort($borderstyleoptions);

    // Add default borders
    $borderstyleoptions[0] = get_string('no');
    return $borderstyleoptions;
    }

/**
 * Get seal images for mod_form.
 * 
 * @return array
 */
function certificate_get_seals () {
    global $CFG, $DB;

    $my_path = "$CFG->dirroot/mod/certificate/pix/seals";
        $sealoptions = array();
        if ($handle = opendir($my_path)) {
        while (false !== ($file = readdir($handle))) {
        if (strpos($file, '.png',1)||strpos($file, '.jpg',1) ) {
                $i = strpos($file, '.');
                if($i > 1) {
                    $sealoptions[$file] = substr($file, 0, $i);
                }
            }
        }
        closedir($handle);
    }
        ksort($sealoptions);

    $sealoptions[0] = get_string('no');
    return $sealoptions;
    }

/**
 * Get watermark images for mod_form.
 * 
 * @return array
 */
function certificate_get_watermarks () {
    global $CFG, $DB;
    // load watermark files
    $my_path = "$CFG->dirroot/mod/certificate/pix/watermarks";
    $wmarkoptions = array();
    if ($handle = opendir($my_path)) {
        while (false !== ($file = readdir($handle))) {
        if (strpos($file, '.png',1)||strpos($file, '.jpg',1) ) {
            $i = strpos($file, '.');
                if($i > 1) {
                    $wmarkoptions[$file] = substr($file, 0, $i);

                }
            }
        }
        closedir($handle);
    }

    // Order watermarks
    ksort($wmarkoptions);

    $wmarkoptions[0] = get_string('no');
    return $wmarkoptions;

    }

/**
 * Get signature images for mod_form.
 * 
 * @return array
 */
function certificate_get_signatures () {
    global $CFG, $DB;

    // load signature files
    $my_path = "$CFG->dirroot/mod/certificate/pix/signatures";
    $signatureoptions = array();
    if ($handle = opendir($my_path)) {
        while (false !== ($file = readdir($handle))) {
            if (strpos($file, '.png',1)||strpos($file, '.jpg',1) ) {
                $i = strpos($file, '.');
                if($i > 1) {
                    $signatureoptions[$file] = substr($file, 0, $i);
                }
            }
        }
        closedir($handle);
    }
    ksort($signatureoptions);

    $signatureoptions[0] = get_string('no');
    return $signatureoptions;
}

/**
 * Prepare to print an activity grade.
 * 
 * @param stdClass $course
 * @param int $moduleid
 * @return mixed
 */
function certificate_print_mod_grade($course, $moduleid) {
    global $USER, $DB;
    $cm = $DB->get_record('course_modules', array('id'=> $moduleid));
    $module = $DB->get_record('modules', array('id'=> $cm->module));

    if ($grade_item = grade_get_grades($course->id, 'mod', $module->name, $cm->instance, $USER->id)) {
        $item = reset($grade_item->items);
        $modinfo->name = utf8_decode($DB->get_field($module->name, 'name', array('id'=> $cm->instance)));
        $grade = $item->grades[$USER->id]->grade;
        $item->gradetype = GRADE_TYPE_VALUE;
        $item->courseid = $course->id;

        $modinfo->points = grade_format_gradevalue($grade, $item, true, GRADE_DISPLAY_TYPE_REAL, $decimals=2);
        $modinfo->percentage = grade_format_gradevalue($grade, $item, true, GRADE_DISPLAY_TYPE_PERCENTAGE, $decimals=2);
        $modinfo->letter = grade_format_gradevalue($grade, $item, true, GRADE_DISPLAY_TYPE_LETTER, $decimals=0);

        if ($grade) {
            $modinfo->dategraded = $item->grades[$USER->id]->dategraded;
        } else {
            $modinfo->dategraded = time();
        }
        return $modinfo;
    }

    return false;
}

/**
 * Prepare to print an outcome.
 * 
 * @param stdClass $course
 * @param int $moduleid
 * @return mixed
 */
function certificate_print_outcome($course, $id) {
    global $USER, $DB, $certificate;

$id = $certificate->printoutcome;
if ($grade_item = new grade_item(array('id'=>$id))) {
    $outcomeinfo->name = $grade_item->get_name();
    $outcome = new grade_grade(array('itemid'=>$grade_item->id, 'userid'=>$USER->id));
    $outcomeinfo->grade = grade_format_gradevalue($outcome->finalgrade, $grade_item, true, GRADE_DISPLAY_TYPE_REAL);
   return $outcomeinfo;
    }
    return false;
}

/**
 * Prepare to print the course grade.
 * 
 * @param stdClass $course
 * @return mixed
 */
function certificate_print_course_grade($course){
    global $USER, $DB;
 if ($course_item = grade_item::fetch_course_item($course->id)) {

    $grade = new grade_grade(array('itemid'=>$course_item->id, 'userid'=>$USER->id));
    $course_item->gradetype = GRADE_TYPE_VALUE;

        $coursegrade->points = grade_format_gradevalue($grade->finalgrade, $course_item, true, GRADE_DISPLAY_TYPE_REAL, $decimals=2);

        $coursegrade->percentage = grade_format_gradevalue($grade->finalgrade, $course_item, true, GRADE_DISPLAY_TYPE_PERCENTAGE, $decimals=2);

        $coursegrade->letter = grade_format_gradevalue($grade->finalgrade, $course_item, true, GRADE_DISPLAY_TYPE_LETTER, $decimals=0);

    return $coursegrade;
    }
    return false;
}

/**
 * Sends text to output given the following params.
 * @param int $x horizontal position
 * @param int $y vertical position
 * @param char $align L=left, C=center, R=right
 * @param string $font any available font in font directory
 * @param char $style ''=normal, B=bold, I=italic, U=underline
 * @param int $size font size in points
 * @param string $text the text to print
 * @return null
 */
function cert_printtext($x, $y, $align, $font, $style, $size, $text) {
    global $pdf;
    $pdf->setFont($font, $style, $size);
    $pdf->SetXY($x, $y);
    $pdf->writeHTMLCell(0, 0, '', '', $text, 0, 0, 0, true, $align);
}

/**
 * Creates rectangles for line border for A4 size paper.
 * 
 * @param stdClass $certificate
 * @param string $orientation
 */
function draw_frame($certificate, $orientation) {
    global $pdf, $certificate;

    if ($certificate->bordercolor > 0) {
        if ($certificate->bordercolor == 1) {
            $color = array(0, 0, 0); //black
        }
        if ($certificate->bordercolor == 2) {
            $color = array(153, 102, 51); //brown
        }
        if ($certificate->bordercolor == 3) {
            $color = array(0, 51, 204); //blue
        }
        if ($certificate->bordercolor == 4) {
            $color = array(0, 180, 0); //green
        }
        switch ($orientation) {
            case 'L':

            // create outer line border in selected color
            $pdf->SetLineStyle(array('width' => 1.5, 'color' => $color));
            $pdf->Rect(10, 10, 277, 190);

            // create middle line border in selected color
            $pdf->SetLineStyle(array('width' => 0.2, 'color' => $color));
            $pdf->Rect(13, 13, 271, 184);

            // create inner line border in selected color
            $pdf->SetLineStyle(array('width' => 1.0, 'color' => $color));
            $pdf->Rect(16, 16, 265, 178);
            break;

            case 'P':
            // create outer line border in selected color
            $pdf->SetLineStyle(array('width' => 1.5, 'color' => $color));
            $pdf->Rect(10, 10, 190, 277);

            // create middle line border in selected color
            $pdf->SetLineStyle(array('width' => 0.2, 'color' => $color));
            $pdf->Rect(13, 13, 184, 271);

            // create inner line border in selected color
            $pdf->SetLineStyle(array('width' => 1.0, 'color' => $color));
            $pdf->Rect(16, 16, 178, 265);
            break;
        }
    }
}

/**
 * Creates rectangles for line border for letter size paper.
 * 
 * @param stdclass $certificate
 * @param string $orientation
 */
function draw_frame_letter($certificate, $orientation) {
    global $pdf, $certificate;

    if($certificate->bordercolor > 0) {

         if ($certificate->bordercolor == 1)    {
            $color = array(0, 0, 0); //black
        }
            if ($certificate->bordercolor == 2)    {
            $color = array(153, 102, 51); //brown
        }
            if ($certificate->bordercolor == 3)    {
            $color = array(0, 51, 204); //blue
        }
            if ($certificate->bordercolor == 4)    {
            $color = array(0, 180, 0); //green
        }

        switch ($orientation) {
            case 'L':
            // create outer line border in selected color
            $pdf->SetLineStyle(array('width' => 4.25, 'color' => $color));
            $pdf->Rect(28, 28, 736, 556);

            // create middle line border in selected color
            $pdf->SetLineStyle(array('width' => 0.2, 'color' => $color));
            $pdf->Rect(37, 37, 718, 538);

            // create inner line border in selected color
            $pdf->SetLineStyle(array('width' => 2.8, 'color' => $color));
            $pdf->Rect(46, 46, 700, 520);
            break;

            case 'P':
            // create outer line border in selected color
            $pdf->SetLineStyle(array('width' => 1.5, 'color' => $color));
            $pdf->Rect( 25, 20, 561, 751);

            // create middle line border in selected color
            $pdf->SetLineStyle(array('width' => 0.2, 'color' => $color));
            $pdf->Rect( 40, 35, 531, 721);

            // create inner line border in selected color
            $pdf->SetLineStyle(array('width' => 1.0, 'color' => $color));
            $pdf->Rect( 51, 46, 509, 699);
            break;
        }
    }
}

/**
 * Prints border images from the borders folder in PNG or JPG formats.
 * 
 * @param int $border
 * @param string $orientation
 * @param int $x x position
 * @param int $y y position
 * @param int $w the width
 * @param int $h the height
 */
function print_border($border, $orientation, $x, $y, $w, $h) {
    global $CFG, $DB, $pdf;

    switch($border) {
        case '0':
        case '':
        break;
        default:
        switch ($orientation) {
            case 'L':
        if(file_exists("$CFG->dirroot/mod/certificate/pix/borders/$border")) {
            $pdf->Image("$CFG->dirroot/mod/certificate/pix/borders/$border", $x, $y, $w, $h);
        }
        break;
            case 'P':
        if(file_exists("$CFG->dirroot/mod/certificate/pix/borders/$border")) {
            $pdf->Image("$CFG->dirroot/mod/certificate/pix/borders/$border", $x, $y, $w, $h);
           }
            break;
        }
        break;
    }
}

/**
 * Prints border images for letter size paper.
 * 
 * @param int $border
 * @param string $orientation
 */
function print_border_letter($border, $orientation) {
    global $CFG, $DB, $pdf;

    switch($border) {
        case '0':
        case '':
        break;
        default:
        switch ($orientation) {
            case 'L':
        if(file_exists("$CFG->dirroot/mod/certificate/pix/borders/$border")) {
            $pdf->Image("$CFG->dirroot/mod/certificate/pix/borders/$border", 12, 10, 771, 594);
        }
        break;
            case 'P':
        if(file_exists("$CFG->dirroot/mod/certificate/pix/borders/$border")) {
            $pdf->Image("$CFG->dirroot/mod/certificate/pix/borders/$border", 10, 10, 594, 771);
            }
            break;
        }
        break;
    }
}

/**
 * Prints watermark images.
 * 
 * @param int $border
 * @param string $orientation
 * @param int $x x position
 * @param int $y y position
 * @param int $w the width
 * @param int $h the height                          
 */
function print_watermark($wmark, $orientation, $x, $y, $w, $h) {
    global $CFG, $DB, $pdf;

    switch($wmark) {
        case '0':
        case '':
        break;
        default:
        switch ($orientation) {
            case 'L':
            if(file_exists("$CFG->dirroot/mod/certificate/pix/watermarks/$wmark")) {
                $pdf->Image("$CFG->dirroot/mod/certificate/pix/watermarks/$wmark", $x, $y, $w, $h);
            }
            break;
            case 'P':
            if(file_exists("$CFG->dirroot/mod/certificate/pix/watermarks/$wmark")) {
                $pdf->Image("$CFG->dirroot/mod/certificate/pix/watermarks/$wmark", $x, $y, $w, $h);
           }
            break;
        }
        break;
    }
}

/**
 * Prints watermark images for letter size paper.
 * 
 * @param int $wmark
 * @param string $orientation
 * @param int $x x position
 * @param int $y y position
 * @param int $w the width
 * @param int $h the height
 */
function print_watermark_letter($wmark, $orientation, $x, $y, $w, $h) {
    global $CFG, $DB, $pdf;

    switch($wmark) {
        case '0':
        case '':
        break;
        default:
        switch ($orientation) {
            case 'L':
            if(file_exists("$CFG->dirroot/mod/certificate/pix/watermarks/$wmark")) {
                $pdf->Image("$CFG->dirroot/mod/certificate/pix/watermarks/$wmark", 160, 110, 500, 400);
            }
            break;
            case 'P':
            if(file_exists("$CFG->dirroot/mod/certificate/pix/watermarks/$wmark")) {
                $pdf->Image("$CFG->dirroot/mod/certificate/pix/watermarks/$wmark", 83, 130, 450, 480);
            }
            break;
        }
        break;
    }
}

/**
 * Prints signature images or a line.
 * 
 * @param int $sig
 * @param string $orientation
 * @param int $x x position
 * @param int $y y position
 * @param int $w the width
 * @param int $h the height
 */
function print_signature($sig, $orientation, $x, $y, $w, $h) {
    global $CFG, $DB, $pdf;

    switch ($orientation) {
        case 'L':
        switch($sig) {
            case '0':
            case '':
            break;
            default:
            if(file_exists("$CFG->dirroot/mod/certificate/pix/signatures/$sig")) {
                $pdf->Image("$CFG->dirroot/mod/certificate/pix/signatures/$sig", $x, $y, $w, $h);
            }
            break;
        }
        break;
        case 'P':
        switch($sig) {
            case '0':
            case '':
            break;
            default:
            if(file_exists("$CFG->dirroot/mod/certificate/pix/signatures/$sig")) {
                $pdf->Image("$CFG->dirroot/mod/certificate/pix/signatures/$sig", $x, $y, $w, $h);
            }
            break;
        }
        break;
    }
}

/**
 * Prints seal images.
 * 
 * @param int $seal
 * @param string $orientation
 * @param int $x x position
 * @param int $y y position
 * @param int $w the width
 * @param int $h the height
 */
function print_seal($seal, $orientation, $x, $y, $w, $h) {
    global $CFG, $DB, $pdf;

    switch($seal) {
        case '0':
        case '':
        break;
        default:
        switch ($orientation) {
            case 'L':
            if(file_exists("$CFG->dirroot/mod/certificate/pix/seals/$seal")) {
                $pdf->Image("$CFG->dirroot/mod/certificate/pix/seals/$seal", $x, $y, $w, $h);
            }
            break;
            case 'P':
            if(file_exists("$CFG->dirroot/mod/certificate/pix/seals/$seal")) {
                $pdf->Image("$CFG->dirroot/mod/certificate/pix/seals/$seal", $x, $y, $w, $h);
            }
            break;
        }
        break;
    }
}

/**
 * Prepare to be print the date -- defaults to time.
 * 
 * @param stdClass $certificate
 * @param stdClass $course
 * @return string the date
 */
function certificate_generate_date($certificate, $course) {
    $timecreated = time();
    if ($certificate->printdate == '0') {
        $certdate = $timecreated;
    }
    if ($certificate->printdate == '1') {
        $certdate = $timecreated;
    }
    if ($certificate->printdate > 1) {
        $modinfo = certificate_print_mod_grade($course, $certificate->printdate);
        $certdate = $modinfo->dategraded;
    }
    return $certdate;
}

/**
 * Generates a 10-digit code of random letters and numbers.
 * 
 * @return string
 */
function certificate_generate_code() {
    return (random_string(10));
}

