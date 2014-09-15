<?php // You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Moodle frontpage.
 *
 * @package    core
 * @copyright  1999 onwards Martin Dougiamas (http://dougiamas.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
global $DB;
    if (!file_exists('../config.php')) {
        die;
    }

    require_once('../config.php');
    require_once($CFG->dirroot .'/course/lib.php');
    require_once($CFG->libdir .'/filelib.php');
    

    redirect_if_major_upgrade_required();

    $urlparams = array();
    if (!empty($CFG->defaulthomepage) && ($CFG->defaulthomepage == HOMEPAGE_MY) && optional_param('redirect', 1, PARAM_BOOL) === 0) {
        $urlparams['redirect'] = 0;
    }
    $PAGE->set_url('/', $urlparams);
    $PAGE->set_course($SITE);

    if ($CFG->forcelogin) {
        require_login();
    } else {
        user_accesstime_log();
    }

    $hassiteconfig = has_capability('moodle/site:config', get_context_instance(CONTEXT_SYSTEM));

/// If the site is currently under maintenance, then print a message
    if (!empty($CFG->maintenance_enabled) and !$hassiteconfig) {
        print_maintenance_message();
    }

    if ($hassiteconfig && moodle_needs_upgrading()) {
        redirect($CFG->wwwroot .'/'. $CFG->admin .'/index.php');
    }

    if (get_home_page() != HOMEPAGE_SITE) {
        // Redirect logged-in users to My Moodle overview if required
        if (optional_param('setdefaulthome', false, PARAM_BOOL)) {
            set_user_preference('user_home_page_preference', HOMEPAGE_SITE);
        } else if (!empty($CFG->defaulthomepage) && ($CFG->defaulthomepage == HOMEPAGE_MY) && optional_param('redirect', 1, PARAM_BOOL) === 1) {
            redirect($CFG->wwwroot .'/my/');
        } else if (!empty($CFG->defaulthomepage) && ($CFG->defaulthomepage == HOMEPAGE_USER)) {
            $PAGE->settingsnav->get('usercurrentsettings')->add(get_string('makethismyhome'), new moodle_url('/', array('setdefaulthome'=>true)), navigation_node::TYPE_SETTING);
        }
    }

    if (isloggedin()) {
        add_to_log(SITEID, 'course', 'view', 'view.php?id='.SITEID, SITEID);
    }

/// If the hub plugin is installed then we let it take over the homepage here
    if (get_config('local_hub', 'hubenabled') && file_exists($CFG->dirroot.'/local/hub/lib.php')) {
        require_once($CFG->dirroot.'/local/hub/lib.php');
        $hub = new local_hub();
        $continue = $hub->display_homepage();
        //display_homepage() return true if the hub home page is not displayed
        //mostly when search form is not displayed for not logged users
        if (empty($continue)) {
            exit;
        }
    }

    $PAGE->set_pagetype('site-index');
    $PAGE->set_other_editing_capability('moodle/course:manageactivities');
    $PAGE->set_docs_path('');
    $PAGE->set_pagelayout('frontpage');
    $editing = $PAGE->user_is_editing();
    $PAGE->set_title($SITE->fullname);
    $PAGE->set_heading($SITE->fullname);
    echo $OUTPUT->header();



    if (isloggedin() and !isguestuser()) {
       
	//THIS IS WHERE THE LOGGED IN CONTENT STARTS
	
		$username = $USER->username;
		$managerid = $USER->id;
		
		
			
	$link = mysql_connect('localhost', $CFG->dbuser, $CFG->dbpass);
if (!$link) {
    die('Could not connect: ' . mysql_error());
}

$db_selected = mysql_select_db($CFG->dbname, $link);
if (!$db_selected) {
    die ('Can\'t use ' . $CFG->dbname .' : ' . mysql_error());
}
	//Find Manager Group ID
		$MG_query = 'Select groupid FROM mdl_groups_members WHERE userid = '. $managerid;
		$resultMG = mysql_query($MG_query);
if($resultMG === FALSE) {
    die(mysql_error()); // TODO: better error handling
}
		
		$MG_row = mysql_fetch_row($resultMG);
		
//Getting a group name from manager id...
		$GN_query = 'SELECT name FROM mdl_groups WHERE id="'. $MG_row[0] .'"';
		$resultGN = mysql_query($GN_query);
	if($resultGN === FALSE) {
    	die('GN:' . mysql_error()); // TODO: better error handling
	}
		//This is the Group name to Filter by
		$GN_row = mysql_fetch_row($resultGN);


	//Starts Page Building Code
		echo "<h2>Reports For Manager: " . $USER->firstname . " " . $USER->lastname . "</h2>";

$query = "SELECT DISTINCT u.firstname AS 'First' , u.lastname AS 'Last', u.city AS 'Site' , c.fullname AS 'Course', cc.name AS 'Category', CASE WHEN gi.itemtype = 'Course'   THEN c.fullname + ' Course Total'  ELSE gi.itemname END AS 'Training', ROUND(gg.finalgrade,2) AS Score,ROUND(gg.rawgrademax,2) AS Max, ROUND(gg.finalgrade / gg.rawgrademax * 100 ,2) AS Percentage,IF (ROUND(gg.finalgrade / gg.rawgrademax * 100 ,2) > 79,'Yes' , 'No') AS Pass,IF (gg.finalgrade > 1, FROM_UNIXTIME(cd.timecompleted, '%m/%d/%Y'), '') AS 'Date' FROM mdl_course AS c JOIN mdl_context AS ctx ON c.id = ctx.instanceid JOIN mdl_role_assignments AS ra ON ra.contextid = ctx.id JOIN mdl_user AS u ON u.id = ra.userid JOIN mdl_grade_grades AS gg ON gg.userid = u.id JOIN mdl_grade_items AS gi ON gi.id = gg.itemid JOIN mdl_course_categories AS cc ON cc.id = c.category JOIN mdl_course_completion_crit_compl AS cd ON cd.userid  = u.id AND cd.course = c.id JOIN mdl_groups_members AS grm ON grm.userid = u.id WHERE grm.groupid = ". $MG_row[0] ." AND gi.courseid = c.id AND gi.itemname != 'Attendance' ORDER BY `Date` DESC" ;



$result = mysql_query($query);
if($result === FALSE) {
    die(mysql_error()); // TODO: better error handling
}

echo "<table border='1'><tr><td>Name</td><td>Course</td><td>Score</td><td>Max</td><td>Pass?</td><td>Date</td></tr>";

while ($row = mysql_fetch_array($result)) {
	
    printf("<tr><td> %s %s </td><td> %s </td><td> %s </td><td> %s </td><td> %s </td><td> %s </td></tr>", $row['First'], $row['Last'], $row['Course'], $row['Score'], $row['Max'],  $row['Pass'], $row['Date']);  
	
}
echo "</table>";


mysql_close($link);
	//THIS IS THE END OF LOGGED IN CONTENT

   } else {
        echo "Not logged In";
    }

 echo $OUTPUT->footer();

?>