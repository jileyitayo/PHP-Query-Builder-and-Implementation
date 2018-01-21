<?php

/**
 * Created by PhpStorm.
 * User: JIL
 * Date: 20/11/2017
 * Time: 6:00 PM
 */

class Requests {
    use utility;
    private $queries;
    private $fillable_makerequest = ['requestaction', 'regno', 'matno', 'coursecode','requestdesc','semesterid','sessionid','coursetaughtby',
        'kost1','kost2','kosca','kosexam','kostotal','kosattendance',
//        'newt1','newt2','newca','newexam','newtotal','newattendance',
        'requestsemesterid','requestsessionid'];
    private $fillable_requestapproval = ['requestid', 'staffid', 'staffcollege', 'staffdepartment','staffprogram','staffremark', 'staffremarkdate', 'newt1', 'newt2', 'newca', 'newexam', 'newtotal', 'newattendance',
        'hod','hodapproval', 'hodremarks', 'hodapprovaldate', 'dean', 'deanrecommend', 'deanremarks', 'deanrecomdate', 'vc', 'vcrecommend', 'vcremarks', 'vcrecomdate'];
    private $fillable_approvals = ['hodapproval', 'hodremarks', 'hodapprovaldate', 'deanrecommend', 'deanremarks', 'deanrecomdate', 'vcrecommend', 'vcremarks', 'vcrecomdate'];
    private $fillable_updateLecturer = ['newt1', 'newt2', 'newca', 'newexam', 'newtotal', 'newattendance'];
    private $fillable_assignstaff = ['staffid', 'staffdepartment', 'staffprogram', 'staffcollege'];
    private $fillable_transaction = ['requestid','t1', 't2', 'ca', 'exam', 'total', 'attendance', 'koslecturer', 'staffassigned', 'role', 'actiontaken', 'remarks', 'remarkdate'];

    private $column_transaction = ['requestid','t1', 't2', 'ca', 'exam', 'total', 'attendance', 'koslecturer', 'staffassigned', 'role', 'actiontaken', 'remarks'];
    private $columns = [];
    private $userMessages = ['message' => '',  'rowCount' => 0, 'status' => '-1'];

    function __construct() {
        $this->queries = new Queries("requests");
    }

    public function makerequest($requests) {
            $formtag = "submit-request";
            $requests[$formtag] = $formtag; // for the query to work...:)
//        if(isset($requests[$formtag])){
            //get student details -
            $studentid = "3100"; // get the student id from session -> prefereable the student regno
            $studentdetails = $this->getStudentDetails($studentid);
            $requests['regno'] = $studentdetails['fno'];
            $requests['matno'] = $studentdetails['matno'];

            //get the course grade
            $requests['semesterid'] = $this->convertSemesterString($requests['semester']);
            $requests['sessionid'] = $this->getSessionid($requests['session'])['sessionid'];
            $coursecode = $requests['coursecode'];
            $coursedesc = $this->getCourseGrade($studentdetails['matno'], $coursecode, $requests['semesterid'], $requests['sessionid']);

            // get the lecturer who taught the course, as well as the hod of the department, program and the college
            // get the HOD, dean and vc

//            $vc = $this->getStaff("VC");
//            $dean = $this->getStaff("DEAN", $studentdetails);
//            $hod = $this->getStaff("HOD", $studentdetails['deptid'], $studentdetails['prgid']);

//            $koslecturer = $this->getStaff("", $studentdetails['deptid'], $studentdetails['prgid']);
            $koslecturer = $this->getStaffDetails($coursedesc['lecturer']);
            $requests['coursetaughtby'] = $koslecturer['staffid'];

            $requests['kost1'] = $coursedesc['t1'];
            $requests['kost2'] = $coursedesc['t2'];
            $requests['kosca'] = $coursedesc['ca'];
            $requests['kosexam'] = $coursedesc['exam'];
            $requests['kosattendance'] = $coursedesc['attendance'];
            $requests['kostotal'] = $coursedesc['ca'] + $coursedesc['exam'];

            // get current semester and session
            $requests['requestsemesterid'] = 1;
            $requests['requestsessionid'] = $this->getSessionSem()['sessionid'];

            print_r($requests);
            //get the action, coursecode, course description, session. session
            $this->userMessages = $this->queries->insert($requests,$formtag, $this->fillable_makerequest, $this->columns, 'AND');

        // also insert in to request approvals table
            $approvals=  new Queries('requestapprovals');

            $requests['staffid'] = $koslecturer['staffid'];
            $requests['requestid'] = $this->queries->getLastInsertedId();
            $requests['staffcollege'] =  $koslecturer['college'];
            $requests['staffdepartment'] =  $koslecturer['department'];
            $requests['staffprogram'] = $koslecturer['program'];
            $vc = $this->getStaff("VC");
            $dean = $this->getStaff("DEAN", $studentdetails['collegeid']);
            $hod = $this->getStaff("HOD", $studentdetails['deptid'], $studentdetails['prgid']);
            $requests['hod'] = $hod['staffid'];
            $requests['dean'] = $dean['staffid'];
            $requests['vc'] = $vc['staffid'];

            $this->userMessages = $approvals->insert($requests,$formtag, $this->fillable_requestapproval, $this->columns, 'AND', false);
//        }
    }

    public function updateRequestLecturer($requests,$formtag,$key){
        //update the new scores in the request table
        $this->userMessages = $this->queries->update($requests, $formtag, $this->fillable_updateLecturer)->where(['requestid'=> $key])->perform();
        // update the approvals table with the staff remarks
        $query  = new Queries("requestapprovals");
        $this->fillable_updateLecturer[] = 'staffremark';
        $this->fillable_updateLecturer[] = 'staffremarkdate';
        $requests['staffremarkdate'] = date('Y-m-d H:i:s');
        $this->userMessages = $query->update($requests, $formtag, $this->fillable_updateLecturer,false)->where(['requestid'=> $key],"AND","")->perform();

        if(isset($requests[$formtag])){
            //get the staff id from the approvals table
            $res = $query->select(['staffid'])->where(['requestid' => $key], "AND", "")->get()->fetch(PDO::FETCH_ASSOC);
            //make transaction
            $action = "Lecturer has made validation of result\nAttendance: " .$requests['newattendance'] ." ;test1: " .$requests['newt1'] ." ; test 2: " .$requests['newt2'] ." ; C.A: " .$requests['newca'] ." ; Exam: " .$requests['newexam'] ."; Total: " .$requests['newtotal'];
            $this->maketransaction($key, $res['staffid'],$action , $requests['staffremark']);
        }
    }

    public function updateRequestApprove($requests,$formtag,$key,$staffid = null){
        $query  = new Queries("requestapprovals");
        if($formtag == "hodsapproval")
            $requests['hodapprovaldate'] = date('Y-m-d H:i:s');
        else if($formtag == "deansapproval")
            $requests['deanrecomdate'] = date('Y-m-d H:i:s');
        else if($formtag == "vcsapproval")
            $requests['vcrecomdate'] = date('Y-m-d H:i:s');
        $this->userMessages = $query->update($requests, $formtag, $this->fillable_approvals,false)->where(['requestid'=> $key],"AND","")->perform();

        if(isset($requests[$formtag])) {
            //make transaction
            $res = $query->select()->where(['requestid' => $key], "AND", "")->get()->fetch(PDO::FETCH_ASSOC);

            if($formtag == "hodsapproval"){
                $action = "HOD has made a recommendations";
                $this->maketransaction($key, $staffid,$action , $requests['hodremarks']);
            }
            else if($formtag == "deansapproval"){
                $action = "Dean has made recommendations";
                $this->maketransaction($key, $staffid,$action , $requests['deanremarks']);
            }

            else if($formtag == "vcsapproval"){
                $action = "vc has made the final approval to the request";
                $this->maketransaction($key, $staffid,$action , $requests['vcremarks']);
            }

            //update the status of the application
            if($formtag == "vcsapproval"){
                $updatereq = array();
                $updatereq[$formtag] = $formtag;
                $updatereq["requeststatus"] = $requests['vcrecommend'];
                $this->userMessages = $this->queries->update($updatereq, $formtag, ['requeststatus'])->where(['requestid'=> $key],"AND")->perform();
            }

        }
    }

    public function displayMessages() {
        if($this->userMessages != null){
            echo $this->showMessages($this->userMessages);
        }
    }

    public function getRequest($value, $key='requestid'){
        $res = $this->queries->select()->where([$key => $value], "AND")->get()->fetch(PDO::FETCH_ASSOC);
        return $res;
    }

    public function getAction($id){
        $query = new Queries("requestactions");
        $res = $query->select(['action'])->where(['actionid' => $id], "AND" ,"")->get()->fetch(PDO::FETCH_ASSOC);
        return $res['action'];
    }

    public function getCourseGrade($studentmatno, $cousecode =null, $semester = null, $sessionid = null) { // from regdatamaster equiv
        $query = new Queries("regdatamasterreq"); // change to regdatamaster equiv -> the view
        if($cousecode == null && $semester == null && $sessionid == null){
            $res = $query->select()->where(['matno' => $studentmatno],"AND", "")->get()->fetchAll(PDO::FETCH_ASSOC);
        } else{ //coursecode and semester should not be null;
            $res = $query->select()->where(['matno' => $studentmatno, 'kos' => $cousecode, 'semid' => $semester, 'sessionid' => $sessionid], "AND", "")->get()->fetch(PDO::FETCH_ASSOC);
        }
        return $res;
    }

    public function getStudentDetails($studentregno) { // can be regno or matno
        $query = new Queries("reglstrequest");
        $res = $query->select()->where(['fno' => $studentregno], "AND", "")->get()->fetch(PDO::FETCH_ASSOC);
        return $res;
    }

    public function getRequestActions(){
        $query = new Queries("requestactions");
        $res = $query->select()->get()->fetchAll(PDO::FETCH_ASSOC);
        return $res;
    }

    public function getSessionid($session, $current = false){
        $query = new Queries("rptyears");
        if($current == true)   $query = new Queries("rptyear");
        if(strpos($session, '/') !== false ){ // if it has /
            $res = $query->select(['sessionid'])->where(['sessionyear' => $session],"AND", "")->get()->fetch(PDO::FETCH_ASSOC);
        } else { // if it does not have / -> if its just a number = numeric
            if(is_numeric($session)){
                $res = $query->select(['sessionyear'])->where(['sessionid' => $session],"AND", "")->get()->fetch(PDO::FETCH_ASSOC);
            }
        }
        return $res;
    }

    public function convertSemesterString($semester) {
        if($semester == "1st Semester" || $semester == "1st semester") $resSem = 1;
        else $resSem = 2;
        return $resSem;
    }

    public function getApprovals($requestid) {
        $query = new Queries("requestapprovals"); // appointment table
        $res = $query->select()->where(['requestid' => $requestid],"AND", "")->get()->fetch(PDO::FETCH_ASSOC);
        return $res;
    }

    public function getSessionSem(){
        $query = new Queries("rptyear");
        $res = $query->select()->get()->fetch(PDO::FETCH_ASSOC);
        return $res;
    }

    public function getStaffDetails($staffid){
        $lectQuery = new Queries("currentstaff"); // get the department and the programid
        $res = $lectQuery->select()->where(['staffid' => $staffid], "AND", "")->get()->fetch(PDO::FETCH_ASSOC);
        return $res;
    }

    public function getStaff($appointment, $coldept = null, $programid = null) {
        $query = new Queries("currentstaff");
        $res = array();
        if($appointment == 'VC'){
            $res = $query->select(['staffid'])->where(['appointment' => $appointment],"AND", "")->get()->fetch(PDO::FETCH_ASSOC);
        } else if($appointment == "DEAN" || $appointment == "HOD") {
             // get  the college
            if($coldept != null &&  $appointment == "DEAN"){
                $res = $query->select(['staffid'])->where(['appointment' => $appointment, 'collegeid' => $coldept], "AND", "")->get()->fetch(PDO::FETCH_ASSOC);
            } else if($coldept != null && $appointment == "HOD") {
                $res = $query->select(['staffid'])->where(['appointment' => $appointment, 'deptid' => $coldept], "AND", "")->get()->fetch(PDO::FETCH_ASSOC);
            }

        } else { // get the course lecturer
           // get the department and the programid
            if($programid != null) $res = $query->select()->where(['prgid' => $programid, 'deptid' => $coldept], "AND", "")->get()->fetch(PDO::FETCH_ASSOC);
        }
        return $res;
    }

    public function getSubmittedRequests($staffid){
        $res = array();
        //get the staff's role;
        $query = new Queries("currentstaff");
        $staff = $query->select(['appointment'])->where(['staffid' => $staffid],"AND", "")->get()->fetch(PDO::FETCH_ASSOC);
        $role = strtolower($staff['appointment']);
        $approvals = new Queries("requestapprovals");
        if($role == "vc" || $role == "dean" || $role == "hod") {
            $res = $approvals->select()->join("requests", "requestid", "requestid","left")->where(["$approvals->table`.`$role" => $staffid], "AND", "")->get()->fetchAll(PDO::FETCH_ASSOC);
        } else { // lecturer
            $res = $this->queries->select()->where(['coursetaughtby' => $staffid],"AND")->get()->fetchAll(PDO::FETCH_ASSOC);
        }
        return $res;

    }

    public function approveRequest($decision, $comment, $formtag){
        $requests = array();
        $requests[$formtag] = $formtag;
        $approvaldate = date("Y-m-d H:i:s");

        //update the database with this approval
        $approvalQuery = new Queries("requestapprovals");
        $this->userMessages = $this->queries->update($requests, $formtag, [''])->where(['requestid'=> $key])->perform();
    }

    public function getStaffAppointment($staffid){
        $query = new Queries("currentstaff");
        $staff = $query->select(['appointment'])->where(['staffid' => $staffid],"AND", "")->get()->fetch(PDO::FETCH_ASSOC);
        $role = strtolower($staff['appointment']);
        if($role == "" || $role == null){
            $role = 'lecturer';
        }
        return $role;
    }

    public function maketransaction($requestid, $staffid, $action, $remark){
        $requests = array();
        $formtag = 'transact';
        $requests[$formtag] = $formtag;
        $transaction = new Queries("requesttransaction");

        //get request info
        $mrequest = $this->getRequest($requestid);
        $requests['requestid'] = $requestid;
        $requests['t1'] = $mrequest['newt1'];
        $requests['t2'] = $mrequest['newt2'];
        $requests['ca'] = $mrequest['newca'];
        $requests['exam'] = $mrequest['newexam'];
        $requests['total'] = $mrequest['newtotal'];
        $requests['attendance'] = $mrequest['newattendance'];
        $requests['koslecturer'] = $mrequest['coursetaughtby'];
        $requests['staffassigned'] = $staffid;

        // get the role of the staff
        $role = $this->getStaffAppointment($staffid);

        $requests['role'] = ($role == "") ? "Lecturer" : $role;
        $requests['actiontaken'] = $action;
        $requests['remarks'] = $remark;
        $requests['remarkdate'] = date("Y-m-d H:i:s");


        $this->userMessages = $transaction->insert($requests,$formtag, $this->fillable_transaction, $this->column_transaction, 'AND', false);
    }

    public function getTransactionHistory($requestid){
        $transaction = new Queries("requesttransaction");
        $res = $transaction->select()->where(['requestid' => $requestid], "AND", "")->orderby("remarkdate", "ASC")->get()->fetchAll(PDO::FETCH_ASSOC);
        return $res;
    }

    public function getdepartmentstaff($koscode){
        //get the program, department and college of the course

        //get all the staff in that department


    }

    public function assignstaff($requests,$formtag, $requestid, $staffid){
        //get the staff details - department and college
        if($requests[$formtag]){
            $staffassigned = $requests['staffid'];
            $requests['modifiedat'] = date('Y-m-d H:i:s');
            $requests['coursetaughtby'] = $staffassigned;

            //update the request table - coursetaughtby column
            $this->queries->update($requests, $formtag, ['coursetaughtby', 'modifiedat'])->where(['requestid'=>$requestid],"AND")->perform();

            //approval table
            $approvals = new Queries("requestapprovals");
            $staffdetails = $this->getStaffDetails($staffassigned);
            $requests['staffprogram'] =$staffdetails['program'];
            $requests['staffdepartment'] =$staffdetails['department'];
            $requests['staffcollege'] =$staffdetails['college'];

            // update the requestsapproval table - staffid, staffcollege, staffdepartment, staffprogram
            $approvals->update($requests, $formtag, $this->fillable_assignstaff, "")->where(['requestid'=>$requestid],"AND",'')->perform();

            // insert into the requesttransaction the action that was taken.
            $action = "HOD assigned the $staffassigned to the request with id $requestid";
            $transaction = new Queries("requesttransaction");

            //get request info
            $mrequest = $this->getRequest($requestid);
            $requests['t1'] = $mrequest['kost1'];
            $requests['t2'] = $mrequest['kost2'];
            $requests['ca'] = $mrequest['kosca'];
            $requests['exam'] = $mrequest['kosexam'];
            $requests['total'] = $mrequest['kostotal'];
            $requests['attendance'] = $mrequest['kosattendance'];
            $requests['koslecturer'] = $staffassigned;
            $requests['staffassigned'] = $staffid;
            $requests['role'] = $this->getStaffAppointment($staffid);
            $requests['actiontaken'] = $action;
            $requests['remarks'] = "";
            $requests['remarkdate'] =  $requests['modifiedat'];

            $this->userMessages = $transaction->insert($requests,$formtag, $this->fillable_transaction, $this->column_transaction, 'AND', false);
        }
    }

    public function getalldepartments(){
        $departments = new Queries("departments");
        $result = $departments->select()->get()->fetchAll(PDO::FETCH_ASSOC);
        return $result;
    }
}