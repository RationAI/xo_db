<?php

global $db;
if (isset($db)) return;

require_once XO_DB_ROOT . 'config.php';
global $_DATA;
if (count($_GET) > 0) {
    $_DATA = $_GET;
} else if (count($_POST)) {
    $_DATA = $_POST;
} else {
    try {
        $_DATA = (array)json_decode(file_get_contents("php://input"));
    } catch (Exception $e) {
        //pass not a valid input
        $_DATA = array();
    }
}

class Database {
    /**
     * @var PDO
     */
    public $pdo;

    function __construct() {
        $this->pdo = new PDO(
            sprintf("%s:host=%s;dbname=%s;port=%s",
                XO_DB_DRIVER, XO_DB_HOST, XO_DB_NAME, XO_DB_PORT),
            XO_DB_USER,
            XO_DB_PASS,
            array(PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION)
        );
    }

    /**
     * @throws Exception
     */
    public function run($sql, $data=array()) {
        $stmt = $this->try($this->pdo->prepare($sql));
        $i = 1;
        foreach ($data as $_ => $input) {
            //consider $try->try(...)
            $stmt->bindValue($i++, $input[0], $input[1]);
        }
        $this->try($stmt->execute(), true);
        return $stmt;
    }

    /**
     * @throws Exception
     */
    public function read($sql, $data=array(), $mode=PDO::FETCH_ASSOC) : array {
        $stmt = $this->run($sql, $data);
        return $this->try($stmt->fetch($mode), []);
    }

    /**
     * @throws Exception
     */
    public function read_all($sql, $data=array(), $mode=PDO::FETCH_ASSOC) {
        $stmt = $this->run($sql, $data);
        return $this->try($stmt->fetchAll($mode), []);
    }

    /**
     * @throws Exception
     */
    public function try($result, $empty=null) {
        if ($result === false) {
            $err = $this->pdo->errorInfo();
            if ($err[0] !== 0 && $empty !== null) return $empty;
            throw new Exception(implode("-", $this->pdo->errorInfo()));
        }
        return $result;
    }

    public function lastInsertId() {
        return $this->pdo->lastInsertId();
    }
}


$db = new Database();

