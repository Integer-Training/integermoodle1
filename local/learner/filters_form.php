<?php
require_once("../../config.php");
require_once("$CFG->libdir/formslib.php");
class filters_inactive_form extends moodleform {
    //Add elements to form
    public function definition() {
        global $CFG,$DB;
 
        $mform = $this->_form; // Don't forget the underscore! 
        $mform->addElement('header', 'filterform', 'Filters >>');
        $mform->setExpanded('filterform', true);
        //
        //courses
        if(is_siteadmin($USER) && $USER->id != 2){
            $sql = 'select * from {course} where id>1 and visible=1';
            $courses_list = $DB->get_records_sql($sql);
        }else if($USER->id != 2){
            $courses_list = $this->local_get_teacher_courses();
        
        }else{
            throw new Exception("No Premissions", 1);
            
        }
        //print_object($courses_list);die;
        $areanames = array();                                                                                                  
        foreach ($courses_list as $rec) {                                                                          
            $areanames[$rec->id] = $rec->fullname;                                                                  
        }                                                                                                                           
        $options = array(                                                                                                           
            'multiple' => false,                                                  
            'noselectionstring' => get_string('allareas', 'search'),                                                                
        ); 
        //users
        if(is_siteadmin($USER) && $USER->id != 2){
            $sql = 'select * from {course} where id>1 and visible=1';
            $courses_list = $DB->get_records_sql($sql);
        }else if($USER->id != 2){
            $courses_list = $this->local_get_teacher_courses();
        
        }else{
            throw new Exception("No Premissions", 1);
            
        }
        //print_object($courses_list);die;
        $areanames = array();                                                                                                  
        foreach ($courses_list as $rec) { 
            $areanames[NULL] = '---Select---';                                                                         
            $areanames[$rec->id] = $rec->fullname;                                                                  
        }                                                                                                                           
        $options = array(                                                                                                           
            'multiple' => false,                                                  
            'noselectionstring' => get_string('allareas', 'search'),                                                                
        ); 
        $mform->addElement('autocomplete', 'courses', 'Course', $areanames, $options);
        //
        //users
       /* $options = array(                                                                                                           
            'multiple' => true,                                                  
            'noselectionstring' => get_string('allareas', 'search'),                                                                
        ); 
        $mform->addElement('autocomplete', 'users', 'Users', [], $options);*/
        //
        //$mform->addElement('date_time_selector', 'date','Choose Date');
        $this->add_action_buttons(true,'Apply');
    }
    //Custom validation should be added here
    function validation($data, $files) {
        return array();
    }
    //
    function local_get_teacher_courses(){
        global $DB,$COURSE,$USER;
        // Get context levels for courses.
        $contextlevel = CONTEXT_COURSE;

        // Find teacher roles (editingteacher, teacher, etc.)
        $teacherroles = $DB->get_records_sql(
            "SELECT id FROM {role} WHERE shortname IN ('editingteacher', 'teacher')"
        );

        if (empty($teacherroles)) {
            return [];
        }

        $roleids = array_keys($teacherroles);
        list($insql, $params) = $DB->get_in_or_equal($roleids, SQL_PARAMS_NAMED);

        // Query to get all courses where user has teacher roles.
        $sql = "SELECT c.id, c.fullname, c.shortname, c.startdate, c.enddate
                  FROM {course} c
                  JOIN {context} ctx ON ctx.instanceid = c.id AND ctx.contextlevel = :contextlevel
                  JOIN {role_assignments} ra ON ra.contextid = ctx.id
                 WHERE ra.userid = :userid AND ra.roleid $insql
              ORDER BY c.fullname ASC";

        $params['contextlevel'] = $contextlevel;
        $params['userid'] = $USER->id;

        return $DB->get_records_sql($sql, $params);
    }
}
//
class filters_form extends moodleform {
    //Add elements to form
    public function definition() {
        global $CFG,$DB;
 
        $mform = $this->_form; // Don't forget the underscore! 
        $mform->addElement('header', 'filterform', 'Filters >>');
        $mform->setExpanded('filterform', true);
        //
        //courses
        if(is_siteadmin($USER) && $USER->id != 2){
            $sql = 'select * from {course} where id>1 and visible=1';
            $courses_list = $DB->get_records_sql($sql);
        }else if($USER->id != 2){
            $courses_list = $this->local_get_teacher_courses();
        
        }else{
            throw new Exception("No Premissions", 1);
            
        }
        //print_object($courses_list);die;
        $areanames = array();                                                                                                  
        foreach ($courses_list as $rec) {                                                                          
            $areanames[$rec->id] = $rec->fullname;                                                                  
        }                                                                                                                           
        $options = array(                                                                                                           
            'multiple' => false,                                                  
            'noselectionstring' => get_string('allareas', 'search'),                                                                
        ); 
        //users
        if(is_siteadmin($USER) && $USER->id != 2){
            $sql = 'select * from {course} where id>1 and visible=1';
            $courses_list = $DB->get_records_sql($sql);
        }else if($USER->id != 2){
            $courses_list = $this->local_get_teacher_courses();
        
        }else{
            throw new Exception("No Premissions", 1);
            
        }
        //print_object($courses_list);die;
        $areanames = array();                                                                                                  
        foreach ($courses_list as $rec) { 
            $areanames[NULL] = '---Select---';                                                                         
            $areanames[$rec->id] = $rec->fullname;                                                                  
        }                                                                                                                           
        $options = array(                                                                                                           
            'multiple' => false,                                                  
            'noselectionstring' => get_string('allareas', 'search'),                                                                
        ); 
        $mform->addElement('autocomplete', 'courses', 'Course', $areanames, $options);
        //
        //users
        $options = array(                                                                                                           
            'multiple' => true,                                                  
            'noselectionstring' => get_string('allareas', 'search'),                                                                
        ); 
        $mform->addElement('autocomplete', 'users', 'Users', [], $options);
        //
        //$mform->addElement('date_time_selector', 'date','Choose Date');
        $this->add_action_buttons(true,'Apply');
    }
    //Custom validation should be added here
    function validation($data, $files) {
        return array();
    }
    //
    function local_get_teacher_courses(){
        global $DB,$COURSE,$USER;
        // Get context levels for courses.
        $contextlevel = CONTEXT_COURSE;

        // Find teacher roles (editingteacher, teacher, etc.)
        $teacherroles = $DB->get_records_sql(
            "SELECT id FROM {role} WHERE shortname IN ('editingteacher', 'teacher')"
        );

        if (empty($teacherroles)) {
            return [];
        }

        $roleids = array_keys($teacherroles);
        list($insql, $params) = $DB->get_in_or_equal($roleids, SQL_PARAMS_NAMED);

        // Query to get all courses where user has teacher roles.
        $sql = "SELECT c.id, c.fullname, c.shortname, c.startdate, c.enddate
                  FROM {course} c
                  JOIN {context} ctx ON ctx.instanceid = c.id AND ctx.contextlevel = :contextlevel
                  JOIN {role_assignments} ra ON ra.contextid = ctx.id
                 WHERE ra.userid = :userid AND ra.roleid $insql
              ORDER BY c.fullname ASC";

        $params['contextlevel'] = $contextlevel;
        $params['userid'] = $USER->id;

        return $DB->get_records_sql($sql, $params);
    }
}
//seesions
//seesions
class filters_session_form extends moodleform {
    //Add elements to form
    public function definition() {
        global $CFG,$DB,$USER;
        $cid = optional_param('id',0,PARAM_INT);
        //
        $mform = $this->_form; // Don't forget the underscore!
        $cid = $this->_customdata['id']; 
        $mform->addElement('header', 'filterform', 'Filters >>');
        $mform->setExpanded('filterform', true);
        //
        $mform->addElement('hidden','cid',$cid);
        //courses
        if(is_siteadmin($USER) && $USER->id != 2){
            $sql = 'select * from {course} where id='.$cid.' and visible=1';
            $courses_list = $DB->get_records_sql($sql);
        }else if($USER->id != 2){
            $courses_list = $DB->get_records('course_modules', ['course' => $cid,'module'=>24]);
        }else{
            throw new Exception("No Premissions", 1);
            
        }                                                                                                                          
        $options = array(                                                                                                           
            'multiple' => false,                                                  
            'noselectionstring' => get_string('allareas', 'search'),                                                                
        ); 

        $areanames = array();                                                                                                  
        foreach ($courses_list as $rec) { 
            $areanames[NULL] = '---Select---';                                                                         
            $areanames[$rec->instance] = $DB->get_field('hvp', 'name',['course' => $cid,'id'=>$rec->instance]);                                                                 
        }              

        $mform->addElement('autocomplete', 'cms', 'Activities', $areanames, $options);
        //
        //users
        $course_context = context_course::instance($cid);
        $groups = $DB->get_records('groups_members',['userid'=>$USER->id]);
        $actual_course_group = [];
        foreach($groups as $rec){
            $actual_course_group[] = $DB->get_record('groups',['id'=>$rec->groupid,'courseid'=>$cid]);
        }
       //
        $gm = array();
        foreach($actual_course_group as $group){
             $gm[] = $DB->get_records('groups_members',['groupid'=>$group->id]);;
        }
        $act_gm = [];
        foreach($gm as $grp_mem){
            foreach($grp_mem as $res){
              if($res->userid == $USER->id)
                continue;
                $act_gm[$res->userid] = $DB->get_field('user','email',['id'=>$res->userid]);
            }
        }
        //print_object($groups);die;
        $options = array(                                                                                                           
            'multiple' => true,                                                  
            'noselectionstring' => '---select---',                                                                
        ); 
        $mform->addElement('autocomplete', 'users', 'Users', $act_gm, $options);
        //
        $this->add_action_buttons(true,'Apply');
    }
    //Custom validation should be added here
    function validation($data, $files) {
        return array();
    }
    //
    function local_get_teacher_courses(){
        global $DB,$COURSE,$USER;
        // Get context levels for courses.
        $contextlevel = CONTEXT_COURSE;

        // Find teacher roles (editingteacher, teacher, etc.)
        $teacherroles = $DB->get_records_sql(
            "SELECT id FROM {role} WHERE shortname IN ('editingteacher', 'teacher')"
        );

        if (empty($teacherroles)) {
            return [];
        }

        $roleids = array_keys($teacherroles);
        list($insql, $params) = $DB->get_in_or_equal($roleids, SQL_PARAMS_NAMED);

        // Query to get all courses where user has teacher roles.
        $sql = "SELECT c.id, c.fullname, c.shortname, c.startdate, c.enddate
                  FROM {course} c
                  JOIN {context} ctx ON ctx.instanceid = c.id AND ctx.contextlevel = :contextlevel
                  JOIN {role_assignments} ra ON ra.contextid = ctx.id
                 WHERE ra.userid = :userid AND ra.roleid $insql
              ORDER BY c.fullname ASC";

        $params['contextlevel'] = $contextlevel;
        $params['userid'] = $USER->id;

        return $DB->get_records_sql($sql, $params);
    }
}
//sales form
class filters_sales_form extends moodleform {
  //Add elements to form
  public function definition() {
      global $CFG,$DB,$USER;
      $mform = $this->_form; // Don't forget the underscore! 
      //courses
      $mform->addElement('text', 'username', get_string('username'), 'size=\"20\"' . $purpose);

      $mform->addHelpButton('username', 'username', 'auth');

      $mform->addRule('username', 'please enter username in lower case', 'required','','server',false,false);

      $mform->setType('username', PARAM_RAW);
      //print_object($courses_list);die;
      $mform->addElement('text', 'firstname', get_string('firstname'), 'size=\"20\"');
      $mform->setType('firstname', PARAM_RAW);
      //
      $mform->addElement('text', 'lastname', get_string('lastname'), 'size=\"20\"');
      $mform->setType('lastname', PARAM_RAW);
      //
      $mform->addElement('text', 'email', get_string('email'), 'size=\"20\"');
      $mform->setType('email', PARAM_RAW);
      //
      //$purpose = user_edit_map_field_purpose($userid, 'password');

      /*$mform->addElement('passwordunmask', 'newpassword', get_string('newpassword'),

          'maxlength=\"'.MAX_PASSWORD_CHARACTERS.'\" size=\"20\"' . $purpose);

      $mform->addRule('newpassword', get_string('maximumchars', '', MAX_PASSWORD_CHARACTERS),

          'maxlength', MAX_PASSWORD_CHARACTERS, 'client');

      $mform->addHelpButton('newpassword', 'newpassword');

      $mform->setType('newpassword', core_user::get_property_type('password'));*/

      //

      $mform->addElement('text', 'phone1', 'Phone Number', 'size=\"20\"');
      $mform->setType('phone1', PARAM_RAW);
      //courses

      $sql = 'select * from {course} where id>1 and visible=1';

      $courses_list = $DB->get_records_sql($sql);

      foreach ($courses_list as $rec) {                                                                     

        $areanames[$rec->id] = $rec->fullname;                                                                  

      }                                                                                                                           
      $options = array(                                                                                                          
          'multiple' => true,                                                  

          'noselectionstring' => '---Select Courses---',                                                                

      );

      //

      $mform->addElement('autocomplete', 'courses', "Course's", $areanames, $options);
      $this->add_action_buttons(true,'Create');
  }

  //Custom validation should be added here

  public function validation($usernew, $files) {

      global $CFG, $DB,$USER;
      $usernew = (object)$usernew;
      $usernew->username = trim($usernew->username);
      $user = $DB->get_record('user', array('id' => $usernew->id));
      $err = array();

      /*if (!$user and !empty($usernew->createpassword)) {

          if ($usernew->suspended) {

              // Show some error because we can not mail suspended users.

              $err['suspended'] = get_string('error');

          }

      } else {

          if (!empty($usernew->newpassword)) {

              $errmsg = ''; // Prevent eclipse warning.

              if (!check_password_policy($usernew->newpassword, $errmsg, $usernew)) {

                  $err['newpassword'] = $errmsg;

              }

          } else if (!$user) {

              // Internal accounts require password!

              $err['newpassword'] = get_string('required');

          }

      }*/

      //

      if(empty($usernew->firstname)){

        $err['firstname'] = get_string('required');

      }
      //
      if(empty($usernew->lastname)){

         $err['lastname'] = get_string('required');

      }

        if (empty($usernew->username)) {

          // Might be only whitespace.

          $err['username'] = get_string('required');

        } else if (!$user or $user->username !== $usernew->username) {

          // Check new username does not exist.

          if ($DB->record_exists('user', array('username' => $usernew->username, 'mnethostid' => $CFG->mnet_localhost_id))) {

              $err['username'] = get_string('usernameexists');

          }

          // Check allowed characters.

          if ($usernew->username !== core_text::strtolower($usernew->username)) {

              $err['username'] = get_string('usernamelowercase');

          } else {

              if ($usernew->username !== core_user::clean_field($usernew->username, 'username')) {

                  $err['username'] = get_string('invalidusername');

              }

          }

        }

        if (!$user or (isset($usernew->email) && $user->email !== $usernew->email)) {

          if (!validate_email($usernew->email)) {

              $err['email'] = get_string('invalidemail');

          } else if (empty($CFG->allowaccountssameemail)) {

              // Make a case-insensitive query for the given email address.

              $select = $DB->sql_equal('email', ':email', false) . ' AND mnethostid = :mnethostid AND id <> :userid';

              $params = array(

                  'email' => $usernew->email,

                  'mnethostid' => $CFG->mnet_localhost_id,

                  'userid' => $usernew->id

              );

              // If there are other user(s) that already have the same email, show an error.

              if ($DB->record_exists_select('user', $select, $params)) {

                  $err['email'] = get_string('emailexists');

              }

          }

        }

       //

        if(empty($usernew->courses)){

          $err['courses'] = get_string('required');

        }

      // Next the customisable profile fields.

      //$err += profile_validation($usernew, $files);\n

        if (count($err) == 0) {
            return true;
        } else {
            return $err;
        }

    }
}