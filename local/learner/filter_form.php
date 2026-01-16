<?php
require_once("../../config.php");
require_once("$CFG->libdir/formslib.php");
class filter_form extends moodleform {
    //Add elements to form
    public function definition() {
        global $CFG,$DB;
 
        $mform = $this->_form; // Don't forget the underscore! 
        $mform->addElement('header', 'filterform', 'Filters >>');
        $mform->setExpanded('filterform', true);
        $mform->addElement('text', 'firstname', get_string('firstname', 'local_learner'), ['size'=>20]);
        //
        //$mform->addElement('text', 'lastname', get_string('lastname', 'local_learner'), ['size'=>20]);
        //
        $mform->addElement('text', 'email', get_string('email', 'local_learner'), ['size'=>20]);
        //
        $select = $mform->addElement('selectyesno', 'isactive','Suspended?');
        $select->setSelected(1);
        //courses
        $sql = 'select * from {course} where id>1 and visible=1';
        $courses_list = $DB->get_records_sql($sql);
        $areanames = array();                                                                                                  
        foreach ($courses_list as $rec) { 
            $areanames[NULL] = '---select course---';                                                                         
            $areanames[$rec->id] = $rec->fullname;                                                                  
        }                                                                                                                           
        $options = array(                                                                                                           
            'multiple' => false,                                                  
            'noselectionstring' => get_string('allareas', 'search'),                                                                
        );  
        $mform->addElement('autocomplete', 'courses', 'Course', $areanames, $options);
        //
        $this->add_action_buttons(true,get_string('submit'));
    }
    //Custom validation should be added here
    function validation($data, $files) {
        return array();
    }
}


















        

//         $mform->addElement('text', 'fname', get_string('firstname')); // Add elements to your form
//         $mform->setType('fname', PARAM_RAW);                   //Set type of element
          

//         $mform->addElement('text', 'lname', get_string('lastname')); 
//         $mform->setType('lname', PARAM_RAW);                  


//         $mform->addElement('text', 'email', get_string('email')); 
//         $mform->setType('email', PARAM_RAW);                  


//         $radioarray=array();
//         $radioarray[] = $mform->createElement('radio', 'malefemale', '', get_string('male','local_user'), 1);
//         $radioarray[] = $mform->createElement('radio', 'malefemale', '', get_string('female', 'local_user'), 0);
//         $mform->addGroup($radioarray, 'radioar', '', array(' '), false);


//         $select = $mform->addElement('select', 'state', get_string('state', 'local_user'), array('TS', 'AP', 'MH'));
//         if($id > 0){
//             $toform = $DB->get_record('registration',array('id'=>$id));

//             if($toform->state == 1){
//                 $district = array('KRN','VJW','Guntur');

//             }elseif($toform->state == 2){
//                 $district = array('abc','xyz','mno');

//             }else{
//                 $district = array('SDPT', 'MDK', 'WRNGL');

//             }
//         }

//         $select = $mform->addElement('select', 'district', get_string('district', 'local_user'), $district);


//         $mform->addElement('textarea', 'address', get_string("address"), 'wrap="virtual" rows="20" cols="50"');


//         $mform->addElement('text', 'phoneno', get_string('phone'));
//         $mform->setType('phoneno', PARAM_INT);                  

        
    

        

// $this->add_action_buttons(true,get_string('submit'));

//     }
//     //Custom validation should be added here
//     function validation($data, $files) {
//         return array();
//     }
// }
