<?php

/**
 * Join a BigBlueButton room
 *
 * Authors:
 *      Fred Dixon (ffdixon [at] blindsidenetworks [dt] org)
 *
 * @package   mod_bigbluebutton
 * @copyright 2010 Blindside Networks Inc.
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v2 or later
 */


require_once('../../config.php');
require_once('lib.php');


$id = optional_param('id', 0, PARAM_INT); // course_module ID, or
$l  = optional_param('l', 0, PARAM_INT);  // bigbluebutton instance ID

if ($id) {
    $PAGE->set_url('/mod/bigbluebutton/index.php', array('id'=>$id));
    if (! $cm = get_coursemodule_from_id('bigbluebutton', $id)) {
        print_error('invalidcoursemodule');
    }

    if (! $course = $DB->get_record("course", array("id"=>$cm->course))) {
        print_error('coursemisconf');
    }

    if (! $bigbluebutton = $DB->get_record("bigbluebutton", array("id"=>$cm->instance))) {
        print_error('invalidcoursemodule');
    }

} else {
    $PAGE->set_url('/mod/bigbluebutton/index.php', array('l'=>$l));
    if (! $bigbluebutton = $DB->get_record("bigbluebutton", array("id"=>$l))) {
        print_error('invalidcoursemodule');
    }
    if (! $course = $DB->get_record("course", array("id"=>$bigbluebutton->course)) ){
        print_error('coursemisconf');
    }
    if (! $cm = get_coursemodule_from_instance("bigbluebutton", $bigbluebutton->id, $course->id)) {
        print_error('invalidcoursemodule');
    }
}

require_login($course, true, $cm);
$context = get_context_instance(CONTEXT_MODULE, $cm->id);

//print_object( $context );
//
$moderator = has_capability('mod/bigbluebutton:moderate', $context);
//print "###".$endmeeting."###";

//$join = has_capability('mod/bigbluebutton:join', $context);
//print "###".$join."###";

//exit;

add_to_log($course->id, "bigbluebutton", "join", "view.php?id=$cm->id", "$bigbluebutton->id");
// add_to_log($course->id, "bigbluebutton", "join meeting", "view.php?course=$course->id&id=$USER->id", "$USER->id" );

/// Print the page header
$strbigbluebuttons = get_string('modulenameplural', 'bigbluebutton');
$strbigbluebutton  = get_string('modulename', 'bigbluebutton');

$navlinks = array();
$navlinks[] = array('name' => $strbigbluebuttons, 'link' => "index.php?id=$course->id", 'type' => 'activity');
$navlinks[] = array('name' => format_string($bigbluebutton->name), 'link' => '', 'type' => 'activityinstance');

$navigation = build_navigation($navlinks);

print_header_simple(format_string($bigbluebutton->name), '', $navigation, '', '', true,
update_module_button($cm->id, $course->id, $strbigbluebutton), navmenu($course, $cm));

//
// BigBlueButton Setup
//

$salt = trim($CFG->BigBlueButtonSecuritySalt);
$url = trim(trim($CFG->BigBlueButtonServerURL),'/').'/';
$logoutURL = $CFG->wwwroot;
$username = $USER->firstname.' '.$USER->lastname;
$userID = $USER->id;

$modPW = $bigbluebutton->moderatorpass;
$viewerPW = $bigbluebutton->viewerpass;
	
if( $moderator ) {
	//
	// Join as Moderator
	//
	print "<br />".get_string('view_login_moderator', 'bigbluebutton' )."<br /><br />";
	print "<center><img src='pix/loading.gif' /></center>";
	
	$response = BigBlueButton::createMeetingArray( "" , $bigbluebutton->meetingid, "", $modPW, $viewerPW, $salt, $url, $logoutURL );

	if (!$response) {
		// If the server is unreachable, then prompts the user of the necessary action
		print_error( 'Unable to join the meeting. Please check the url of the bigbluebutton server AND check to see if the bigbluebutton server is running.' );
	}

	if( $response['returncode'] == "FAILED" ) {
		// The meeting was not created
		if ($response['messageKey'] == "checksumError"){
			print_error( get_string( 'index_checksum_error', 'bigbluebutton' ));
		}
		else {
			print_error( $response['message'] );
		}
	}

	$joinURL = BigBlueButton::joinURL($bigbluebutton->meetingid, $username, $modPW, $salt, $url, $userID);
	redirect( $joinURL );

} else {
	//
	// Login as a viewer, check if we need to wait
	//

	// "Viewer";
	if( $bigbluebutton->wait ) {
		// check if the session is running; if not, user is not allowed to join
		// print "MeeingID: #".$bigbluebutton->meetingid."#<br>";
		$arr = BigBlueButton::getMeetingInfoArray( $bigbluebutton->meetingid, $modPW, $url, $salt );
		$joinURL = BigBlueButton::joinURL( $bigbluebutton->meetingid, $username, $viewerPW, $salt, $url, $userID);

		// print_object( $arr );
		// print "Is Meeting runnign: #".BigBlueButton::isMeetingRunning( $bigbluebutton->meetingid,  $url, $salt )."#<br>";
		// print "BBB";
		
		if( BigBlueButton::isMeetingRunning( $bigbluebutton->meetingid, $url, $salt ) == "true" ) {
			//
			// since the meeting is already running, we just join the session
			//
			print "<br />".get_string('view_login_viewer', 'bigbluebutton' )."<br /><br />";
			print "<center><img src='pix/loading.gif' /></center>";
			
			redirect( $joinURL );

		} else {
			print "<br />".get_string('view_wait', 'bigbluebutton' )."<br /><br />";
			print '<center><img src="pix/polling.gif"></center>';
		}
?>
<p></p>
<script type="text/javascript" src="http://ajax.googleapis.com/ajax/libs/jquery/1.4.2/jquery.min.js"></script>
<script type="text/javascript" src="heartbeat.js"></script>
<!-- script type="text/javascript" src="md5.js"></script -->
<!--script type="text/javascript" src="jquery.xml2json.js"></script -->
<script type="text/javascript" >
                        $(document).ready(function(){
                        $.jheartbeat.set({
                        url: "<?php echo $CFG->wwwroot ?>/mod/bigbluebutton/test.php?name=<?echo $bigbluebutton->meetingid; ?>",
                        delay: 5000
                        }, function() {
                                mycallback();
                        });
                        });
                function mycallback() {
                        // Not elegant, but works around a bug in IE8
                        var isMeeting = ($("#HeartBeatDIV").text().search("true")  > 0 );
                        if ( isMeeting ) {
                                window.location = "<?php echo $joinURL ?>";
                        }
                }
</script>
<?php
	} else {
	
	//
	// Join as Viewer, no wait check
	//

	print "<br />".get_string('view_login_viewer', 'bigbluebutton' )."<br /><br />";
	print "<center><img src='pix/loading.gif' /></center>";
	
	$response = BigBlueButton::createMeetingArray( "" , $bigbluebutton->meetingid, "", $modPW, $viewerPW, $salt, $url, $logoutURL );

	if (!$response) {
		// If the server is unreachable, then prompts the user of the necessary action
		print_error( 'Unable to join the meeting. Please contact your administrator.' );
	}

	if( $response['returncode'] == "FAILED" ) {
		// The meeting was not created
		if ($response['messageKey'] == "checksumError"){
			print_error( get_string( 'index_checksum_error', 'bigbluebutton' ));
		}
		else {
			print_error( $response['message'] );
		}
	}

	$joinURL = BigBlueButton::joinURL($bigbluebutton->meetingid, $username, $viewerPW, $salt, $url, $userID);
	redirect( $joinURL );

	}
}

// Finish the page
//print_footer($course);
$OUTPUT->footer();

?>
