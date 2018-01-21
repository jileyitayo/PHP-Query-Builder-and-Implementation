<?php
include ("database.php");
include ("trait.php");

class Queries extends Database{

	use utility;

	//default table values
	private $testtable = "test";
	protected $currentdate = "";
	private $lastInsertedId;
	private $lastUpdatedId;

	//properties
	private $query = "";
	protected $db = "";
	public $table = "";
	protected $result;
    protected $status = "";
    protected $msg = "";
    protected $messagetag = "";
	private $count = ""; // this is the the row count of the table result;
	private $posts;
//	private $messagetag;
//	private $confirmrow;
//	private $operand;

	//constructors
	function __construct($tablename = null) {
		$this->query = "";
		$this->currentdate = date('Y-m-d H:i:s');
		$this->db = $this->dbconnect();
		$this->table = ($tablename != null) ? $tablename : $this->testtable;
		return $this;
	}


	//functions   this->

	//insert function

	//TODO
	//in addition. check if created_at has been created as a column in the table

	// $created_at = array('key' => ', created_at', 'value' => $this->currentdate);
	// $created_at['key']; $created_at['value'];

    /**
     * @param $incomingarray
     * @param $messagetag
     * @param null $fillable
     * @param null $columns
     * @param null $operand
     * @return array
     * This gigantic function performs the isset of a post of a particular form, cleans the post,
     * It checks if the posts already exists in the intending table,
     * It also inserts into the database, the set fillable array list of variables to insert to the database
     * And lastly, it inserts the fillables into the database returns the messages as described in the messagetags in the trait.php
     */
    public function insert($incomingarray, $messagetag, $fillable = null, $columns = null, $operand =null, $datestatus = true){
        if(isset($incomingarray[$messagetag])){
            $db = $this->dbconnect();
            /**
             * These are default fields based on the configuration of extra fields in the database
             */
            if($datestatus == true){
                $incomingarray['createdat'] = date('Y-m-d H:i:s');  // current state
                $incomingarray['modifiedat'] = date('Y-m-d H:i:s'); // current state
                $incomingarray['status'] = '1'; // active state

                if($fillable != null){
                    $fillable[] =  'createdat';
                    $fillable[] = 'modifiedat';
                    $fillable[] =  'status';
                }
            }


            $array = $this->cleanInputs($incomingarray);
            if($messagetag != null) unset($array[$messagetag]);

            $result = $this->getkeyvalue($array,$fillable);
            $mykeys = $result['keys'];
            $myvalues = $result['values'];

            $query = "INSERT INTO ". $this->table . " (". $mykeys .") VALUES (". $myvalues .")";
            if($columns != null && is_array($columns)){
                //this creates an array for the columns to confirm
                $outputarray = array();
                foreach ($columns as $value){
                    //set the value as incomingarray key
                    $outputarray[$value] = $incomingarray[$value];
                }
                if($datestatus == true)
                    $outputarray['status'] = '1'; // check for only active posts

                if($datestatus == true)
                    $result = $this->select()->where($outputarray, $operand)->get()->rowCount();
                else {
                    $result = $this->select()->where($outputarray, $operand, "")->get()->rowCount();
//                    echo $this->select()->where($outputarray, $operand, "")->getQuery();
                }
                if($result > 0){
                    $this->msg = "Data Already Exists";
                    $status = 0;
                    $this->message = array('message' => $this->msg, 'rowCount' => $result, 'status' => $status);
                    return $this->message;
                }
            }
//            echo '<Br/>insert query: ' . $query;
            $stmt = $db->prepare($query);
            $stmt->execute();
            $this->lastInsertedId = $db->lastInsertId();
            $this->count = $stmt->rowCount();

            if($this->count > 0) {
                $this->status = 1;
                if($messagetag == null) {
                    $this->msg = "Created Successfully";
                } else{
                    $this->msg = $this->customMessage($messagetag);
                }
            }
            else {
                $this->msg = "Could not Create";
                $this->status = 0;
            }

            $this->message = array('message' => $this->msg, 'rowCount' => $this->count, 'status' => $this->status);
//            print_r($this->message);
            return $this->message;
        }

        $this->message =  array('message' => "", 'rowCount' => 0, 'status' => 0);
        return $this->message;
    }

    //still in development -> its just lying around here for the mean time
    public function checkIfColumnExists($db, $table, $column){
        $db = $this->dbconnect();
        $query =  "SELECT * FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = '$db' AND TABLE_NAME = '$table' AND COLUMN_NAME = '$column'";

    }

    /**
     * @param null $columns
     * @return $this
     * This selects all the columns or a particular column
     */
	public function select($columns = null){
        $this->query = "";
        $this->db = $this->dbconnect();
		$columnlist = "";
		if($columns != null){ // select specific column array
			foreach ($columns as $key => $value) {
                $columnlist .= ($value == end($columns)) ? $value : $value ."," ;
			}
		} else{  //select all
			$columnlist = "*";
		}
		$this->query .= "SELECT ".$columnlist." FROM " . $this->table;
        return $this;
	}

    /**
     * @param $array
     * @param string $operand
     * @return $this
     * This perfoms a where query and returns this instance of hte class
     */
	public function where($array, $operand = 'AND', $status = ' AND  `status` = 1 ', $like = false){
	    $pae = $status ." AND ";
//	    print_r($array);
        $result = $this->combinekeyandvalue($array, $operand, null, $like);
        $this->query .= " WHERE (1 = 1 " . $pae ." (". $result . ") )";
//        echo $this->query;
        return $this;
	}

    public function join($tablename, $homekey, $foreignkey, $direction = "left"){
	    if($direction == "left") $joindirection = " LEFT ";
        else if ($direction == "right")  $joindirection = " RIGHT ";
        else if ($direction == "inner")  $joindirection = " INNER ";
        else if ($direction == "outer")  $joindirection = " OUTER ";
        else  $joindirection = " LEFT ";
        $this->query .= "$joindirection JOIN $tablename ON $this->table.$homekey = $tablename.$foreignkey ";
        //example of use
//        $query->select() ->join("appointmenthistory", "idno", "idno")->where(["$query->table`.`idno" => "1902"], "AND", "")->get()->fetchAll(PDO::FETCH_ASSOC);
        return $this;
    }

    public function orderby($key, $order = "ASC"){
        $this->query .= " ORDER BY `$key` $order";
        // usage
//        $query->select()->orderby("date", "ASC")->get();
        return $this;
    }
    /**
     * @return PDOStatement
     * This performs the final query of this statements so far
     */
	public function get(){
//	    echo $this->query;
        $this->result = $this->db->prepare($this->query);
        $this->result->execute();
        // this is required so that it can perform multiple queries in this class
//        echo '<br/>query: ' . $this->query;
//        $this->query = "";
	    return  $this->result;
	}

	public function getLastInsertedId(){
	    return $this->lastInsertedId;
	}

	public function update($incomingarray, $messagetag, $fillable = null,$datestatus = true){
        $this->posts = $incomingarray;
        if(isset($incomingarray[$messagetag])){
            $this->query = "";
            $this->messagetag = $messagetag;
            $db = $this->dbconnect();
            /**
             * These are default fields based on the configuration of extra fields in the database
             */
            if($datestatus == true){
                $incomingarray['modifiedat'] = date('Y-m-d H:i:s'); // current state
                if($fillable != null) $fillable[] = 'modifiedat';
            }

            $array = $this->cleanInputs($incomingarray);
            if($messagetag != null) unset($array[$messagetag]);

            $result = $this->combinekeyandvalue($array, $operand = ',',$fillable);

            $this->query = "UPDATE ". $this->table . " SET " .$result;
            //echo $this->query;



//            $stmt = $db->prepare($query);
//            //$stmt->execute();
//            $this->lastUpdatedId = $db->lastInsertId();
//            $this->count = $stmt->rowCount();
//
//            if($this->count > 0) {
//                $this->status = 1;
//                if($messagetag == null) {
//                    $this->msg = "Updated Successfully";
//                } else{
//                    $this->msg = $this->customMessage($messagetag);
//                }
//            }
//            else {
//                $this->msg = "Could not Update";
//                $this->status = 0;
//            }

            $this->message = array('message' => $this->msg, 'rowCount' => $this->count, 'status' => $this->status);
            return $this;
        }

        $this->message =  array('message' => "", 'rowCount' => 0, 'status' => 0);
        return $this;
	}

	//this changes the status to 0
    public function delete($incomingarray, $messagetag, $fillable = null){
        $this->posts = $incomingarray;

        if(isset($incomingarray[$messagetag])){
            $this->query = "";
            $this->messagetag = $messagetag;
            $db = $this->dbconnect();
            /**
             * These are default fields based on the configuration of extra fields in the database
             */
            $incomingarray['modifiedat'] = date('Y-m-d H:i:s'); // current state

            if($fillable != null) $fillable[] = 'modifiedat';

            $array = $this->cleanInputs($incomingarray);
            if($messagetag != null) unset($array[$messagetag]);

            $result = $this->combinekeyandvalue($array, $operand = ',',$fillable);
            $this->query = "UPDATE ". $this->table . " SET " .$result;
            //echo $this->query;

            $this->message = array('message' => $this->msg, 'rowCount' => $this->count, 'status' => $this->status);
            return $this;
        }

        $this->message =  array('message' => "", 'rowCount' => 0, 'status' => 0);
        return $this;
    }

	// used for updates
	public function perform(){
	    if(isset($this->posts[$this->messagetag])){
            $stmt = $this->db->prepare($this->query);
//            echo $this->query;
            $stmt->execute();
            $this->lastUpdatedId = $this->db->lastInsertId();
            $this->count = $stmt->rowCount();
            $this->query = "";

            if($this->count > 0) {
                $this->status = 1;
                if($this->messagetag == null) {
                    $this->msg = "Updated Successfully";
                } else{
                    $this->msg = $this->customMessage($this->messagetag);
                }
            }
            else {
                $this->msg = "Cannot Update";
                $this->status = 0;
            }
            $this->message = array('message' => $this->msg, 'rowCount' => $this->count, 'status' => $this->status);
            return $this->message;
        }
        $this->message =  array('message' => "", 'rowCount' => 0, 'status' => 0);
        return $this->message;
	}

    /**
     * @param $username
     * @param $activity
     * @param $comment
     * @param null $where
     * This logs the activities performed on this system
     * TODO
     */
    public function log($username, $activity, $comment, $where = null){
        //define log table
        $logtable = "epaylog";
//        Int
        $fillables = ['userid', 'username', 'activity', 'comment', 'appmodule'];

        //$this->insert();
    }

    public function getQuery(){
        return $this->query;
    }

    public function customQuery($query){
        $db = $this->dbconnect();
        $stmt = $db->prepare($query);
        $stmt->execute();
        return $stmt;
    }
}

?>