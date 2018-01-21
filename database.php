<?php
Class Database{
    private $host = "localhost";
    private $db_name = "counsel";
    private $production_db_name = "";
    private $username = "root";
    private $password = "";

    protected $db = '';

    public function connect(){
        $this->db = null;

        try{
            $this->db = new PDO("mysql:host=".$this->host.";dbname=".$this->db_name, $this->username, $this->password);
            $this->db->exec("set names utf8");
        } catch(PDOException $exception){
            echo "Connection error: " . $exception->getMessage();
        }

        return $this->db;
    }
}
