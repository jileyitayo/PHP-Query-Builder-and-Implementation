<?php
require_once('queries.php');
/**
 * Created by PhpStorm.
 * User: JIL
 * Date: 16/11/2017
 * Time: 6:58 AM
 */
class User extends Queries
{
    private $firstname;
    private $lastname;
    private $email;
    private $fillable = ['firstname', 'lastname', 'username', 'email', 'password', 'roleid'];
    private $columns = ['email'];
    private $user = array();
    public $authenticated = 0; //User LoggedIn
    protected $role;
    public $timeLoggedIn;
    protected $queries;
    private $usernamekey = "email";

    //table name is defined
    public $table = 'users';
    protected $message;

    public function __construct(){
        $this->queries = new Queries("users");
    }

    public static function login($table, $incomingarray,$messagetag) {
        $instance = new self();
        $instance->table = $table;
        if(isset($incomingarray[$messagetag])){
            if($messagetag != null) unset($incomingarray[$messagetag]);
            $array = $instance->cleanInputs($incomingarray);
            $user = $instance->loginUser( $array['email'], $array['password']);
            return $user;
        }
        $instance->message =  array('message' => "", 'rowCount' => 0, 'status' => 0);
        return $instance;
    }

    private function loginUser( $username, $password) {
        $usernamekey = $this->usernamekey;
        $passwordstatus = 0;
        $userspassword  = $this->select([$usernamekey,'password'])->get()->fetchAll(PDO::FETCH_ASSOC);
        foreach ($userspassword as $value){
            if(password_verify($password, $value['password']) && strtolower($value[$usernamekey]) == strtolower($username)) {
                $passwordstatus = 1;
                $password = $value['password'];
                break;
            }
        }
        if($passwordstatus == 1){
            $res = $this->select()->where([$usernamekey=> $username, 'password' => $password], 'AND')->get();
            $rowcount = $res->rowCount();
//            echo $rowcount;
            if($rowcount > 0){
                $row = $res->fetch(PDO::FETCH_ASSOC);
                foreach ($row as $key=> $columns){
                    $this->user[$key] = $columns;
                }
                $this->authenticated = 1;
                $this->timeLoggedIn = date("g:i a");
                $this->role = $this->user['roleid'];
                $this->user['timeLoggedIn'] = $this->timeLoggedIn;
                $_SESSION['counsel'] = $this->user;

                //for security reasons
                $_SESSION['counsel']['password']= '';
                return $this;
            }
        } else{
            //since its for login
            $this->msg = $this->customMessage('login-unsuccessful');
            $this->status = 0;
            $this->message = array('message' => $this->msg, 'rowCount' => 0, 'status' => $this->status);
            return $this;
        }

    }

    public function getRole(){
        if(isset($this->role)) return $this->role;
    }

    public function getUsers($regno = null, $likequery = false, $deactivated = false){
        if($regno == null) {
            if($deactivated == true){
                $allstudents = $this->queries->select()->where(['status' => '0'], 'AND', '')->get()->fetchAll(PDO::FETCH_ASSOC);
            } else
                $allstudents = $this->queries->select()->get()->fetchAll(PDO::FETCH_ASSOC);
        }
        else {
            if($likequery == true)
                $allstudents = $this->queries->select(["concat_ws( ' ', lastname, firstname, middlename, matno) as label"  , "regno as id", "concat_ws( ' ', lastname, firstname, middlename, matno) as value"])->where(['regno' => $regno, 'firstname' => $regno, 'lastname' => $regno, 'matno' => $regno, 'email' => $regno],'OR',' AND  `status` = 1 ', true)->get()->fetchAll(PDO::FETCH_ASSOC);
            else
                $allstudents = $this->queries->select()->where(['regno' => $regno],'AND')->get()->fetch(PDO::FETCH_ASSOC);
        }
        return $allstudents;
    }

    public function returnUser(){
        if(isset($_SESSION['counsel']))
            $this->user =  $this->getUser($_SESSION['counsel']);
        return $this;
    }

    public function showMessages(){
        if(isset($this->queries)) echo $this->queries->returnMessages();
    }

    public function getUser($studentArray){
        $resarray = array();
        if(isset($studentArray) && $studentArray != null){
            $this->firstname = $studentArray['firstname'];
            $this->lastname = $studentArray['lastname'];
            $this->email = $studentArray['email'];
            if(isset($studentArray['roleid']) && is_numeric($studentArray['roleid']))
                $this->role = $this->getUserRole($studentArray['roleid']);
            else if(isset($studentArray['role']))
                $this->role = $studentArray['role'];
            $resarray = array(
                'firstname' =>  $this->firstname,
                'lastname' =>  $this->lastname,
                'email' =>  $this->email,
                'role' =>  $this->role
            );
        }
        return $resarray;
    }

    public function getUserRole($id = null){
        $query = new Queries('roles');
        if($id == null){
            $res = $query->select(['role'])->where(['id'=> $this->user['role']], 'AND', null)->get();
        } else {
            $res = $query->select(['role'])->where(['id'=> $id], 'AND', null)->get()->fetch(PDO::FETCH_ASSOC);
        }
        $this->role = $res['role'];
        return $this->role;
    }

    public function createAccount($requests, $formtag){
        if(isset($requests['password'])){
            $options = [
                'cost' => 12,
            ];
            $tmp_password = $requests['password'];
            $requests['password'] = password_hash($tmp_password, PASSWORD_BCRYPT, $options);
        }
        //Confirm if these row exits - specify the columns you want to check
        $this->userMessages = $this->queries->insert($requests,$formtag, $this->fillable, $this->columns, 'AND');
    }


    //posts
    public function saveImage($requests, $formtag, $type = "profilepicture", $userid = null){ // $_FILES
        //application of song
//        $user->saveImage($_FILES, 'uploadportfolio', 'link');

        $report = "";

        if($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST[$formtag])){
            // Check if file was uploaded without errors
            if(isset($requests[$type]) && $requests[$type]["error"] == 0){
                $allowed = array("jpg" => "image/jpg", "jpeg" => "image/jpeg", "gif" => "image/gif", "png" => "image/png");
                $filename = $requests[$type]["name"];
                $filetype = $requests[$type]["type"];
                $filesize = $requests[$type]["size"];

//                print_r($filename . " " .$filetype . " " .$filesize);

                // Verify file extension
                $ext = pathinfo($filename, PATHINFO_EXTENSION);
                $ext = strtolower($ext);
                // Verify file size - 5MB maximum
                $maxsize = 5 * 1024 * 1024;
                if(!array_key_exists($ext, $allowed)) $report = "Error: Please select a valid file format.";

                else if($filesize > $maxsize) $report = "Error: File size is larger than the allowed limit.";

                // Verify MYME type of the file
                else if(in_array($filetype, $allowed)){
                    $shal = ($userid == null) ? sha1($this->user['id']) : sha1($userid);
                    $mainpath = "assets/img/users/" . (string) $shal . "/";
                    $desired_dir=$mainpath. $type;
                    // Check whether file exists before uploading it
//                    if(file_exists($desired_dir . "/" . $_FILES["profilepicture"]["name"])){ //http://localhost/
//                        $report = $_FILES["profilepicture"]["name"] . " is already exists.";
//                    } else
                    if(is_dir($mainpath) == false) mkdir("$mainpath", 0777);
                    if(is_dir($desired_dir) == false) mkdir("$desired_dir", 0777);// Create directory if it does not exist
                    $_POST[$type] = $requests[$type]['name'];
                    if($type == 'link'){ // for portfolio update
                        $postquery = new Queries("posts");
                        $fillable_post = ['caption', 'link', 'description', 'authorid'];
                        $_POST['authorid'] = $this->user['id'];

                        $postquery->insert($_POST, $formtag, $fillable_post);
                    } else {
                        $this->queries->update($_POST, $formtag, [$type])->where(['id' => $this->user['id']])->perform();

                    }

                    move_uploaded_file($_FILES[$type]["tmp_name"], $desired_dir . "/" . $_FILES[$type]["name"]);
                    $report = "Your file was uploaded successfully.";

                } else{
                    $report = "Error: There was a problem uploading your file. Please try again.";
                }
            } else{
                $report = "Error: " . $_FILES[$type]["error"];
            }

            $this->message = array('message' => $report, 'rowCount' => 1, 'status' => 1);
        }

    }

    public function getAllPosts(){
        $query = new Queries("posts");
        $res = $query->select()->orderby("createdat","DESC")->get();
        return $res->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getNoOfUsers(){
        $customQuery = "SELECT * FROM users WHERE (1 = 1 AND `status` = 1 AND (`roleid` = '1' OR `roleid` ='2'))";
        $stats = $this->customQuery($customQuery)->rowCount();
        return $stats;
    }

}