<?php

//require_once 'database.php';
//require_once 'queries.php';

trait utility{
    protected $message = array();

    private $messages = array(
        'login-user' => 'User Successfully Logged In',
        'register-student' => 'Student Registered Successfully',
        'register-admin' => 'Admin Registered Successfully',
        'register-merchant' => 'Merchant Registered Successfully',
        'register-user' => 'You have successfully registered',
        'login-unsuccessful' => 'Incorrect Login Details',
        'add-device' => 'Device Added Successfully',
        'edit-device' => 'Device Updated Successfully',
        'delete-device' => 'Device Deleted Successfully',
        'topup' => 'Student\'s Wallet has been Topped',
        'debit' => 'Student\'s Wallet has been Debited',
        'freeze-wallet' => 'Student\'s Account Freezed Successfully',
        'in-active-account' => 'Your account has been deactivated',
        'submit-request' => 'Request Submitted',
        'updateRequests' => 'Request Updated',
        'hodsapproval' => 'Request Updated',
        'deansapproval' => 'Request Updated',
        'transact' => 'Transaction made',
        'assignstaff' => 'Staff Assigned Successfully',
        'vcsapproval' => 'Request Updated'
    );

    private $fillables = [];

	private function dbconnect(){
		$database = new Database();
	    return $database->connect();
	}
//    public function except($array, $arraytoexclude){
//        unset($array[$messagetag])
//
//    }

    public function customMessage($key){
        return $this->messages[$key];
    }

    public function returnMessages($successcolortag = 'success', $failedcolortag = 'danger'){
        $colortag = ($this->message['status'] == 1) ? $successcolortag  : $failedcolortag;
        if($this->message['status'] == -1) return '';
        else if($this->message['message'] != "") return "<div class='alert alert-".$colortag." text-center'>".$this->message['message']."</div>";
        return "";
    }

//    public function showMessages($message, $successcolortag = 'success', $failedcolortag = 'danger'){
//        $colortag = ($message['status'] == 1) ? $successcolortag  : $failedcolortag;
//        if($message['status'] == -1) return '';
//        else if($message['message'] != "") return "<div class='alert alert-".$colortag." text-center'>".$message['message']."</div>";
//        return "";
//    }

    /**
     * @param $data
     * @return array|string - returns the cleaned input
     */
    protected function cleanInputs($data) {
        $clean_input = Array();
        if (is_array($data)) {
            foreach ($data as $k => $v) {
                $clean_input[$k] = $this->cleanInputs($v);
            }
        } else {
            $clean_input = trim(strip_tags($data));
//            $clean_input = addslashes($clean_input);
            $clean_input = htmlspecialchars($clean_input);
            $clean_input = filter_var($clean_input, FILTER_SANITIZE_STRING);
        }
        return $clean_input;
    }

    /**
     * @param $array - takes in an array
     * @return array - returns the string of the keys and the values.
     * use -> mainly in insert query
     */
    public function getkeyvalue($array, $fillable = null){
        $output = array();
        $mykeys = "";
        $myvalues = "";
        if($fillable != null)  $numItems = count($fillable);
        else  $numItems = count($array);
        $i = 0;
        $dataFound = 0;
        if($fillable != null);
        $dataFound = $this->getFillablePresent($array, $fillable);

        foreach ($array as $key => $value) {
            if(isset($fillable) && $fillable != null){
                if(in_array($key, $fillable)){
//                    echo '<br/>' . $key . ' is present';
                    $j =++$i;
                    if($j === $numItems || $j === $dataFound){
                        $mykeys .= "`". $key ."`";
                        $myvalues .= "'". $value . "'";
                    } else{
                        $mykeys .= "`". $key ."`,";
                        $myvalues .= "'". $value . "'" .",";
                    }
                }
            } else{
//                echo '<br/>' . $key . ' still here';
                $j =++$i;
                if($j === $numItems || $j === $dataFound){
                    $mykeys .= "`". $key ."`";
                    $myvalues .= "'". $value . "'";
                } else{
                    $mykeys .= "`". $key ."`,";
                    $myvalues .= "'". $value . "'" .",";
                }
            }
        }
        $output['keys'] = $mykeys;
        $output['values'] = $myvalues;
        return $output;
    }

    public function getFillablePresent($array, $fillable){ // this gets the number of values that are set in an array. and a fillable
        $datapresent = 0;
        foreach ($array as $key => $value) {
            if (isset($fillable) && $fillable != null) {
                if (in_array($key, $fillable)) {
                    ++$datapresent;
                }
            }
        }
        return $datapresent;
    }

    /**
     * @param $array - takes in an array
     * @return string
     * key = 'value'
     */
    public function combinekeyandvalue($array, $operand = 'AND',$fillable = null, $like = false){
        $output = "";
        if($fillable != null)  $numItems = count($fillable);
        else  $numItems = count($array);
        $i = 0;
        $dataFound = 0;
        if($fillable != null);
        $dataFound = $this->getFillablePresent($array, $fillable);
        foreach ($array as $key => $value) {
            if(isset($fillable) && $fillable != null){
                if(in_array($key, $fillable)){
                    if($like == true){
                        $j =++$i;
                        if($j === $numItems || $j === $dataFound){
                            $output .= "`" . $key . "` LIKE '%". $value . "%'";
                        } else{
                            $output .= "`" . $key . "` LIKE '%". $value . "%' " . $operand . " ";
                        }
                    } else {
                        $j =++$i;
                        if($j === $numItems || $j === $dataFound){
                            $output .= "`" . $key . "` ='". $value . "'";
                        } else{
                            $output .= "`" . $key . "` ='". $value . "' " . $operand . " ";
                        }
//                        echo $output;
                    }

                }
            } else{
                if($like == true){
                    if(++$i === $numItems){
                        $output .= "`" . $key . "` LIKE '%". $value . "%'";
                    } else{
                        $output .= "`" . $key . "` LIKE '%". $value . "%' " . $operand . " ";
                    }
                } else {
                    if(++$i === $numItems){
                        $output .= "`" . $key . "` ='". $value . "'";
                    } else{
                        $output .= "`" . $key . "` ='". $value . "' " . $operand . " ";
                    }
                }

            }
        }
        return $output;
    }

    /**
     * This gets the countries data
     */
    public function getCountries(){
        //select country and states
        $countries = new Queries('countries'); // initialize query for countries
        $allcountries = $countries->select(['countryid', 'country'])->get()->fetchAll(PDO::FETCH_ASSOC);
        return $allcountries;
    }
    
    public function getPrograms($id = null, $department = true){
        $program = new Queries('program'); // initialize query for countries
        if($id == null){
            $allprograms = $program->select()->get()->fetchAll(PDO::FETCH_ASSOC);
        } else {
            if($department == true){
                $allprograms = $program->select()->where(['departmentid' => $id],'AND')->get()->fetch(PDO::FETCH_ASSOC);
            } else {
                $allprograms = $program->select()->where(['id' => $id],'AND')->get()->fetch(PDO::FETCH_ASSOC);
            }
        }
        return $allprograms;
    }
    
    public function getDepartments($id = null, $college = true){
        $department = new Queries('department'); // initialize query for countries
        if($id == null){
            $alldepartments = $department->select()->get()->fetchAll(PDO::FETCH_ASSOC);
        } else {
            if($college == true){
                $alldepartments = $department->select()->where(['collegeid' => $id],'AND')->get()->fetch(PDO::FETCH_ASSOC);
            } else {
                $alldepartments = $department->select()->where(['id' => $id],'AND')->get()->fetch(PDO::FETCH_ASSOC);
            }
        }
        return $alldepartments;
    }
    
    public function getColleges($id = null){
        //select country and states
        $college = new Queries('college'); // initialize query for countries
        if($id == null){
            $allcolleges = $college->select()->get()->fetchAll(PDO::FETCH_ASSOC);
        } else {
            $allcolleges = $college->select()->where(['id' => $id],'AND')->get()->fetch(PDO::FETCH_ASSOC);
        }
        return $allcolleges;
    }

    public function getUnit($id = null){
        //select country and states
        $unit = new Queries('unit'); // initialize query for countries
        if($id == null){
            $allunits = $unit->select()->get()->fetchAll(PDO::FETCH_ASSOC);
        } else {
            $allunits = $unit->select()->where(['id' => $id],'AND')->get()->fetch(PDO::FETCH_ASSOC);
        }
        return $allunits;
    }

    public function getHalls($id = null){
        //select country and states
        $hall = new Queries('hall'); // initialize query for countries
        if($id == null){
            $allhalls = $hall->select()->get()->fetchAll(PDO::FETCH_ASSOC);
        } else {
            $allhalls = $hall->select()->where(['id' => $id],'AND', '')->get()->fetch(PDO::FETCH_ASSOC);
        }
        return $allhalls;
    }

    public function readableDate($dbdate){
    	return date("d-M-Y g:i a", strtotime($dbdate));
    }


    public static function redirect($link){
        echo "<script>window.location.href = '".$link."';</script>";
    }

//    function logguser($staffid,$comment, $appid, $menuid)
//    {
//        $date = date("Y-m-d H:i:s");
//        $get_user = get_user($_SESSION['loginid']);
//        if(!empty($get_user))
//            $userid =implode(',', array_map(function($el){ return $el['idno']; }, $get_user));
//        else if(empty($get_user)){
//            $stmt = $conn->prepare("select * from reglist where fno in (select userid from login where loginid in (" . $_SESSION['loginid'] . "))");
//            $stmt->execute();
//            $data = $stmt->fetch(PDO::FETCH_ASSOC);
//            $userid = $data['fno'];
//        }
//        $response = "";
//        try{
//            $strSQL1="insert into portallog (username,staffid, comments,date, appid, menuid) values('$staffid','$userid','$comment', '$date', '$appid', '$menuid')";
//            //echo $strSQL1;
//            $conn->exec($strSQL1);
//            $last_id = $conn->lastInsertId();
//            $response ='done';
//        }
//        catch(PDOException $e) {
//            echo "Error: Loging Portal ";
//        }
//        return $response;
//    }

}

?>