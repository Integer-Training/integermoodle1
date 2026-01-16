<?php
require_once("../../config.php");
require_once("$CFG->libdir/formslib.php");
class filter_form extends moodleform {
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
        //users
        $sql = 'select * from {user} where id>2 and suspended=0';
        $users_list = $DB->get_records_sql($sql);
                                                                                                                              
        $options = array(                                                                                                           
            'multiple' => false,                                                                
        ); 
        $areanames = array();                                                                                                  
        foreach ($users_list as $rec) { 
            $areanames[NULL] = '--Select Learner--';                                                                         
            $areanames[$rec->id] = $rec->firstname.' '.$rec->lastname;                                                                 
        }              
        $mform->addElement('select', 'user', 'Filter Marking by Learner:', $areanames);
        //
        //course
        $sql = 'select * from {course} where id>1 and visible=1';
        $users_list = $DB->get_records_sql($sql);
                                                                                                                              
        $options = array(                                                                                                           
            'multiple' => false,                                                                
        ); 
        $areanames = array();                                                                                                  
        foreach ($users_list as $rec) { 
            $areanames[NULL] = '--All Courses--';                                                                         
            $areanames[$rec->id] = substr($rec->fullname, 0, 50).'...';                                                                 
        } 
        $mform->addElement('select', 'course', 'Filter Marking by Course:', $areanames);
        //tutor
        $tutors_list = $this->local_get_teacher_courses();
        $areanames = array();                                                                                                  
        foreach ($tutors_list as $rec) { 
            $areanames[NULL] = '--Select Assessor--';                                                                         
            $areanames[$rec->id] = $rec->firstname.' '.$rec->lastname;                                                                 
        } 
        //print_object($tutors_list);die;
        $mform->addElement('select', 'tutor', 'Filter Marking by Assessors:', $areanames);
        //by dates
        // Start date
       $opts =  array(
                'startyear' => 2000, 
                'stopyear'  => 2050,
                'timezone'  => 99,
                'step'      => 1,
                'optional' => true,
            );
        $mform->addElement('date_selector', 'startdate', 'Marking From Date',$opts);
        $mform->setType('startdate', PARAM_INT);

        // End date
        $mform->addElement('date_selector', 'enddate', 'Marking To Date',$opts);
        $mform->setType('enddate', PARAM_INT);
        $this->add_action_buttons(true,'Apply filter');
    }
    //
    function local_get_teacher_courses(){
        global $DB,$COURSE,$USER;
        // Get context levels for courses.
        $contextlevel = CONTEXT_COURSE;

        // Find teacher roles (editingteacher, teacher, etc.)
        $teacherroles = $DB->get_records_sql(
            "SELECT id FROM {role} WHERE shortname IN ('teacher')"
        );

        if (empty($teacherroles)) {
            return [];
        }

        $roleids = array_keys($teacherroles);
        list($insql, $params) = $DB->get_in_or_equal($roleids, SQL_PARAMS_NAMED);

        // Query to get all courses where user has teacher roles.
        $sql = "SELECT u.id,u.firstname,u.lastname
                  FROM {course} c
                  JOIN {context} ctx ON ctx.instanceid = c.id AND ctx.contextlevel = :contextlevel
                  JOIN {role_assignments} ra ON ra.contextid = ctx.id
                  JOIN {user} u ON u.id=ra.userid
                 WHERE  ra.roleid $insql
              GROUP BY ra.userid";

        $params['contextlevel'] = $contextlevel;
        //$params['userid'] = $USER->id;

        return $DB->get_records_sql($sql, $params);
    }
    //Custom validation should be added here
    public function validation($data, $files) {
       // print_r($data);die;
        $errors = [];

        if (!empty($data)) {

            if ($data['enddate'] < $data['startdate']) {
                $errors['enddate'] = 'End date must be greater than start date';
            }
        }

        return $errors;
    }
}

//
class filter_reassign_form extends moodleform {
    //Add elements to form
    public function definition() {
        global $CFG,$DB,$USER;
        $cid = optional_param('id',0,PARAM_INT);
        //
        $mform = $this->_form; // Don't forget the underscore!
        $cid = $this->_customdata['id']; 
        //
        $mform->addElement('hidden','cid',$cid);
        //users                                                                                                                        
        $options = array(                                                                                                           
            'multiple' => false,                                                                
        ); 
        //course
        $sql = 'select * from {course} where id>1 and visible=1';
        $users_list = $DB->get_records_sql($sql);
        $areanames = array();                                                                                                  
        foreach ($users_list as $rec) { 
            $areanames[NULL] = '--Select Course--';                                                                         
            $areanames[$rec->id] = substr($rec->fullname, 0, 50).'...';                                                                 
        } 
        $mform->addElement('select', 'course', 'Choose a course to view learners', $areanames);
        //tutor
        
        $sql = 'SELECT u.*  FROM {role_assignments} ra
                JOIN {user} u ON u.id=ra.userid 
                WHERE ra.roleid = 3 AND ra.contextid = 1';
                $users_list = $DB->get_records_sql($sql);
                                                                                                                              
        $options = array(                                                                                                           
            'multiple' => false,                                                                
        ); 
        $areanames = array();                                                                                                  
        foreach ($users_list as $rec) { 
            $areanames[NULL] = '--Select Assessor--';                                                                         
            $areanames[$rec->id] = $rec->firstname.' '.$rec->lastname;                                                                 
        } 
        $mform->addElement('select', 'tutor', 'Filter by assessor', $areanames);
        
        $this->add_action_buttons(true,'Apply filter');
    }
    //Custom validation should be added here
    public function validation($data, $files) {
       // print_r($data);die;
        $errors = [];

        return $errors;
    }
}


