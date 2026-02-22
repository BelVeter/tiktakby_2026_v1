<?php
namespace bb;
/*
 * Mysql database class - only one connection allowed
 */
class Db
{
    private $_connection;
    private static $_instance; //The single instance
    private $_host = 'localhost';
    // private $_host = '127.0.0.1';
    // private $_username = 'tiktakby_tiktak';
    // private $_password = 'Vai7evahch';
    // private $_database = 'tiktakby_tiktak';

    private $_username = 'root';
    private $_password = '';
    private $_database = 'tiktakby_2026_v1';

    /*
    Get an instance of the Database
    @return Instance
    */
    public static function getInstance()
    {
        if (!self::$_instance) { // If no instance then make one
            self::$_instance = new self();
        }
        return self::$_instance;
    }

    /**
     * @param $ar
     * @return string|null
     */
    public static function makeQueryConditionFromArray($ar)
    {
        if (count($ar) > 0) {
            $rez = "WHERE ";
            $i = 0;
            foreach ($ar as $close) {
                $i++;
                if ($i == 1) {
                    $rez .= $close;
                } else {
                    $rez .= " AND " . $close;
                }
            }
            return $rez;
        } else {
            return null;
        }

    }

    public static function startTransaction()
    {
        $mysqli = self::getInstance()->getConnection();

        //запускаем транзакцию
        $query_start = "START TRANSACTION";
        $result_start = $mysqli->query($query_start);
        if (!$result_start) {
            die('Сбой при доступе к базе данных: ' . $query_start . ' (' . $mysqli->connect_errno . ') ' . $mysqli->connect_error);
        }
    }

    public static function commitTransaction()
    {
        $mysqli = self::getInstance()->getConnection();

        //запускаем транзакцию
        $query_start = "COMMIT";
        $result_start = $mysqli->query($query_start);
        if (!$result_start) {
            die('Сбой при доступе к базе данных: ' . $query_start . ' (' . $mysqli->connect_errno . ') ' . $mysqli->connect_error);
        }
    }
    public static function rollBackTransaction()
    {
        $mysqli = self::getInstance()->getConnection();

        //запускаем транзакцию
        $query_start = "ROLLBACK";
        $result_start = $mysqli->query($query_start);
        if (!$result_start) {
            die('Сбой при доступе к базе данных: ' . $query_start . ' (' . $mysqli->connect_errno . ') ' . $mysqli->connect_error);
        }
    }

    private function __construct()
    {
        // Force pre-8.1 error reporting mode (returning false on query failures instead of throwing Exceptions)
        // This is necessary because legacy bb/ code relies heavily on `if (!$result)` instead of try/catch
        mysqli_report(MYSQLI_REPORT_OFF);

        $this->_connection = new \mysqli(
            $this->_host,
            $this->_username,
            $this->_password,
            $this->_database
        );
        // выбор правильной кодировки при работе с БД
        $this->_connection->set_charset("utf8mb4");

        $this->_connection->query('set collation_connection="utf8mb4_general_ci"'); // кодировка в которой будут посылаться служебные команды для сервера


        // Error handling
        if (mysqli_connect_error()) {
            trigger_error(
                "Failed to connect to MySQL: " . mysqli_connect_error(),
                E_USER_ERROR
            );
        }
    }
    // Magic method clone is empty to prevent duplication of connection
    private function __clone()
    {
    }
    // Get mysqli connection
    public function getConnection()
    {
        return $this->_connection;
    }
}

//To make a connection to the database and make a query simple use the lines:
//
//    $db = Database::getInstance();
//    $mysqli = $db->getConnection();
//    $sql_query = "SELECT foo FROM .....";
//    $result = $mysqli->query($sql_query);
//
?>