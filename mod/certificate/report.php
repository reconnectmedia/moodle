<?php  // $Id: version.php,v 3.1.0

    require_once('../../config.php');
    require_once('lib.php');

    global $DB;

    $id   = required_param('id', PARAM_INT);          // Course module ID
    $sort = optional_param('sort', '', PARAM_RAW);
    $download = optional_param('download', '', PARAM_ALPHA);
    $action = optional_param('action', '', PARAM_ALPHA);
    $url = new moodle_url('/mod/certificate/report.php', array('id'=>$id));
    if ($download !== '') {
        $url->param('download', $download);
    }
    if ($action !== '') {
        $url->param('action', $action);
    }
    $PAGE->set_url($url);

    if (! $cm = get_coursemodule_from_id('certificate', $id)) {
            error('Course Module ID was incorrect');
    }

    if (! $course = $DB->get_record('course', array('id'=> $cm->course))) {
        error('Course is misconfigured');
    }

    require_login($course->id, false, $cm);
    $context = get_context_instance(CONTEXT_MODULE, $cm->id);
    require_capability('mod/certificate:manage', $context);

    if (! $certificate = $DB->get_record('certificate', array('id'=> $cm->instance))) {
        error('Certificate ID was incorrect');
    }


    $strcertificates = get_string('modulenameplural', 'certificate');
    $strcertificate  = get_string('modulename', 'certificate');
    $strto = get_string('awardedto', 'certificate');
    $strdate = get_string('receiveddate', 'certificate');
    $strgrade = get_string('grade','certificate');
    $strcode = get_string('code', 'certificate');
    $strreport= get_string('report', 'certificate');

    add_to_log($course->id, 'certificate', 'view', "report.php?id=$cm->id", '$certificate->id', $cm->id);

    if (!$download) {
        $PAGE->navbar->add($strreport);
        $PAGE->set_title(format_string($certificate->name).": $strreport");
        $PAGE->set_heading($course->fullname);
        echo $OUTPUT->header();
        /// Check to see if groups are being used in this choice
        $groupmode = groups_get_activity_groupmode($cm);
        if ($groupmode) {
            groups_get_activity_group($cm, true);
            groups_print_activity_menu($cm, $CFG->wwwroot . '/mod/certificate/report.php?id='.$id);
        }
    } else {
        $groupmode = groups_get_activity_groupmode($cm);
    }
    $sqlsort = 's.studentname ASC';
    //or to sort by date:
    // $sqlsort = 's.certdate ASC';
    if (!$users = certificate_get_issues($certificate->id, $sqlsort, $groupmode, $cm)) {
        notify('There are no issued certificates');
        die;
    }


    if ($download == "ods") {
        require_once("$CFG->libdir/odslib.class.php");

    /// Calculate file name
        $filename = clean_filename("$course->shortname ".strip_tags(format_string($certificate->name,true))).'.ods';
    /// Creating a workbook
        $workbook = new MoodleODSWorkbook("-");
    /// Send HTTP headers
        $workbook->send($filename);
    /// Creating the first worksheet
        $myxls =& $workbook->add_worksheet($strreport);

    /// Print names of all the fields
        $myxls->write_string(0,0,get_string("lastname"));
        $myxls->write_string(0,1,get_string("firstname"));
        $myxls->write_string(0,2,get_string("idnumber"));
        $myxls->write_string(0,3,get_string("group"));
        $myxls->write_string(0,4,$strdate);
        $myxls->write_string(0,5,$strgrade);
        $myxls->write_string(0,6,$strcode);


    /// generate the data for the body of the spreadsheet
        $i=0;
        $row=1;
        if ($users) {
            foreach ($users as $user) {
                $myxls->write_string($row,0,$user->lastname);
                $myxls->write_string($row,1,$user->firstname);
                $studentid=(!empty($user->idnumber) ? $user->idnumber : " ");
                $myxls->write_string($row,2,$studentid);
                $ug2 = '';
                if ($usergrps = groups_get_all_groups($course->id, $user->id)) {
                    foreach ($usergrps as $ug) {
                        $ug2 = $ug2. $ug->name;
                    }
                }
                $myxls->write_string($row,3,$ug2);
                $myxls->write_string($row,4,userdate($user->certdate));
                if ($user->reportgrade != null) {
                    $grade = $user->reportgrade;
                } else {
                    $grade = get_string('notapplicable','certificate');
                }
                $myxls->write_string($row,5,$grade);
                $myxls->write_string($row,6,$user->code);
                $row++;
            }
            $pos=6;
        }
    /// Close the workbook
        $workbook->close();
        exit;
    }

    //print spreadsheet if one is asked for:
    if ($download == "xls") {
        require_once("$CFG->libdir/excellib.class.php");

    /// Calculate file name
        $filename = clean_filename("$course->shortname ".strip_tags(format_string($certificate->name,true))).'.xls';
    /// Creating a workbook
        $workbook = new MoodleExcelWorkbook("-");
    /// Send HTTP headers
        $workbook->send($filename);
    /// Creating the first worksheet
        $myxls =& $workbook->add_worksheet($strreport);

    /// Print names of all the fields
        $myxls->write_string(0,0,get_string("lastname"));
        $myxls->write_string(0,1,get_string("firstname"));
        $myxls->write_string(0,2,get_string("idnumber"));
        $myxls->write_string(0,3,get_string("group"));
        $myxls->write_string(0,4,$strdate);
        $myxls->write_string(0,5,$strgrade);
        $myxls->write_string(0,6,$strcode);

    /// generate the data for the body of the spreadsheet
        $i=0;
        $row=1;
        if ($users) {
            foreach ($users as $user) {
                $myxls->write_string($row,0,$user->lastname);
                $myxls->write_string($row,1,$user->firstname);
                $studentid=(!empty($user->idnumber) ? $user->idnumber : " ");
                $myxls->write_string($row,2,$studentid);
                $ug2 = '';
                if ($usergrps = groups_get_all_groups($course->id, $user->id)) {
                    foreach ($usergrps as $ug) {
                        $ug2 = $ug2. $ug->name;
                    }
                }
                $myxls->write_string($row,3,$ug2);
                $myxls->write_string($row,4,userdate($user->certdate));
                if ($user->reportgrade != null) {
                    $grade = $user->reportgrade;
                } else {
                    $grade = get_string('notapplicable','certificate');
                }
                $myxls->write_string($row,5,$grade);
                $myxls->write_string($row,6,$user->code);
                $row++;
            }
            $pos=6;
        }
    /// Close the workbook
        $workbook->close();
        exit;
    }
    // print text file
    if ($download == "txt") {
        $filename = clean_filename("$course->shortname ".strip_tags(format_string($certificate->name,true))).'.txt';

        header("Content-Type: application/download\n");
        header("Content-Disposition: attachment; filename=\"$filename\"");
        header("Expires: 0");
        header("Cache-Control: must-revalidate,post-check=0,pre-check=0");
        header("Pragma: public");

        /// Print names of all the fields

        echo get_string("firstname")."\t".get_string("lastname") . "\t". get_string("idnumber") . "\t";
        echo get_string("group"). "\t";
        echo $strdate. "\t";
        echo $strgrade. "\t";
        echo $strcode. "\n";

        /// generate the data for the body of the spreadsheet
        $i=0;
        $row=1;
        if ($users) foreach ($users as $user) {
            echo $user->lastname;
            echo "\t".$user->firstname;
            $studentid = " ";
            if (!empty($user->idnumber)) {
                $studentid = $user->idnumber;
            }
            echo "\t". $studentid."\t";
            $ug2 = '';
            if ($usergrps = groups_get_all_groups($course->id, $user->id)) {
                foreach ($usergrps as $ug) {
                    $ug2 = $ug2. $ug->name;
                }
            }
            echo $ug2. "\t";
            echo userdate($user->certdate)."\t";
            if ($user->reportgrade != null) {
                $grade = $user->reportgrade;
            } else {
                $grade = get_string('notapplicable','certificate');
            }
            echo $grade."\t";
            echo $user->code."\n";
            $row++;
        }
        exit;
    }

    echo '<br />';
    echo $OUTPUT->heading(get_string('modulenameplural', 'certificate'));
    $table = new html_table();
    $table->width = "95%";
    $table->tablealign = "center";
    $table->head  = array ($strto, $strdate, $strgrade, $strcode);
    $table->align = array ("LEFT", "LEFT", "CENTER", "CENTER");
    foreach ($users as $user) {
        $name = $OUTPUT->user_picture($user).$user->studentname;
        $date = userdate($user->certdate).certificate_print_user_files($certificate, $user->id, $context->id);
        if ($user->reportgrade != null) {
            $grade = $user->reportgrade;
        } else {
            $grade = get_string('notapplicable','certificate');
        }
        $code = $user->code;
        $table->data[] = array ($name, $date, $grade, $code);
    }

    echo '<br />';
    echo html_writer::table($table);

   //now give links for downloading spreadsheets.
    echo "<br />\n";
    echo "<center><table class=\"downloadreport\"><tr>\n";
    echo "<td>";
        $downloadoptions = array();
        $options = array();
        $options["id"] = "$cm->id";
        $options["download"] = "ods";
    echo $OUTPUT->single_button(new moodle_url("report.php", $options), get_string("downloadods"));

    echo "</td><td>";
        $options["download"] = "xls";
    echo $OUTPUT->single_button(new moodle_url("report.php", $options), get_string("downloadexcel"));
    echo "</td><td>";
        $options["download"] = "txt";
    echo $OUTPUT->single_button(new moodle_url("report.php", $options), get_string("downloadtext"));
    echo "</td></tr></table></center>";

    echo $OUTPUT->footer($course);