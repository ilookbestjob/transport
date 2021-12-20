<?php
include "message.class.php";

class messagelog
{
    private $log = [];
    private $message_types = ['Уведомление', "Предупреждение", "Ошибка", "Критическая ошибка"];
    private $postName, $postId, $displayLog, $txtLog, $baseLog, $level;

    private $LogSession;


    public $lastresponse;
    public $lastcomand;


    //Подключение БД
    private $server = '';
    private $base = '';
    private $user = '';
    private $bdpassword = '';

    public function __construct($postId, $postName, $displayLog, $txtLog, $baseLog, $level)
    {
        $this->postName = $postName;
        $this->postId = $postId;
        $this->displayLog = $displayLog;
        $this->txtLog = $txtLog;
        $this->baseLog = $baseLog;
        $this->level = $level;

        if ($baseLog) {
            $this->createLogBase($this->checkLogBase());
            $this->LogSession = $this->startBaseSession($postId);
        }
    }
    public function addLog($message_type, $message_postid, $message_context, $message_text, $message_level)
    {
        $message = new message();
        $message->message_date = Date('Y-m-d H:i:s');
        $message->message_type = $message_type;
        $message->message_postid = $message_postid;
        $message->message_context = $message_context;
        $message->message_text = $message_text;
        $message->message_level = $message_level;
        $this->log[] = $message;

        if ($this->baseLog) {
            $this->addLogtoBase($message_type, $message_context, $message_text, $message_level);
        }
    }



    public function displayLog($level)
    {
        $ctr = 0;

        foreach ($this->log as $logItem) {

            if ($logItem->message_level == $level) {
                $ctr++;
                echo  $ctr . " |  "  . $logItem->message_date . " |  "  . $this->message_types[$logItem->message_type] . " |  "  . $logItem->message_context . " |  "  . $logItem->message_text .   "</br></br>";
            }
        }
    }




    public function writeLog($file, $level)
    {
        $ctr = 0;
        $fileLink = fopen($file, 'wt');

        foreach ($this->log as $logItem) {

            if ($logItem->message_level == $level) {
                $ctr++;

                fwrite($fileLink, $ctr . " |  "  . $logItem->message_date . " |  "  . $this->message_types[$logItem->message_type] . " |  "  . $logItem->message_context . " |  "  . $logItem->message_text . PHP_EOL);
            }
        };
    }

    public function totalLog()
    {
        if ($this->displayLog) {
            $this->displayLog($this->level);
        }
        if ($this->txtLog) {
            $this->writeLog("LOG_" . Date('Y-m-d H-i-s') . "_" . $this->postName . ".txt", $this->level); // echo "LOG_".Date('Y-m-d H:i:s')."_".$this->postName;
        }
    }


    public function checkLogBase()
    {
        $BaseErrors = [];
        $connection = mysqli_connect($this->server, $this->user, $this->bdpassword, $this->base);

        $sql_result = mysqli_query($connection, "SHOW TABLES FROM `" . $this->base . "` like 'postapi_log';");

        if (mysqli_num_rows($sql_result) == 0) {
            $BaseErrors[] = 1;
        }

        $sql_result = mysqli_query($connection, "SHOW TABLES FROM `" . $this->base . "` like 'postapi_context';");

        if (mysqli_num_rows($sql_result) == 0) {
            $BaseErrors[] = 2;
        }

        $sql_result = mysqli_query($connection, "SHOW TABLES FROM `" . $this->base . "` like 'postapi_sessions';");

        if (mysqli_num_rows($sql_result) == 0) {
            $BaseErrors[] = 3;
        }

        $sql_result = mysqli_query($connection, "SHOW TABLES FROM `" . $this->base . "` like 'postapi_response';");

        if (mysqli_num_rows($sql_result) == 0) {
            $BaseErrors[] = 4;
        }

        return $BaseErrors;
    }


    public function startBaseSession($PostId, $SessionName = "")
    {
        $connection = mysqli_connect($this->server, $this->user, $this->bdpassword, $this->base);

        mysqli_query($connection, "SET NAMES cp1251");

        $SessionName = mb_convert_encoding($SessionName, 'windows-1251', 'utf-8');


        $sql = "INSERT INTO `postapi_sessions` (`postid`, `sessionname`, sessiondate) VALUES ('" . $PostId . "', '" . $SessionName .  "', '" . date("Y-m-d H:i:s") ."')";
        $sql_result = mysqli_query($connection, $sql);

        return mysqli_insert_id($connection);
    }


    public function addLogtoBase($message_type, $message_context, $message_text, $message_level)
    {



        $connection = mysqli_connect($this->server, $this->user, $this->bdpassword, $this->base);

        mysqli_query($connection, "SET NAMES cp1251");

        $message_text = mb_convert_encoding($message_text, 'windows-1251', 'utf-8');


        $sql = "INSERT INTO `postapi_log` (`logsession`, `logtype`, `logcontext`, `logtext`, `loglevel`) VALUES ('" . ($this->LogSession == "" ? "0" : $this->LogSession) . "', '" . $message_type . "', '" . ($this->getContextId($message_context) == "" ? "0" : $this->getContextId($message_context)) . "', '" . $message_text . "', '" . $message_level . "')";
        $sql_result = mysqli_query($connection, $sql);
            return $sql_result;
    }

    public function getContextId($context)
    {
        $connection = mysqli_connect($this->server, $this->user, $this->bdpassword, $this->base);

        mysqli_query($connection, "SET NAMES cp1251");

        $context = mb_convert_encoding($context, 'windows-1251', 'utf-8');

        $sql = "select * from `postapi_context` where context='" . $context . "'";
        $sql_result = mysqli_query($connection, $sql);

        if (mysqli_num_rows($sql_result) == 0) {


            $sql = "insert into `postapi_context` (`context`) VALUES ('" . $context . "')";
            $sql_result = mysqli_query($connection, $sql);
            echo mysqli_error($connection);
            return mysqli_insert_id($connection);
        } else {
            $sql_row = mysqli_fetch_array($sql_result);
            return $sql_row['id'];
        }
    }


    public function createLogBase($BaseErrors)
    {
        $connection = mysqli_connect($this->server, $this->user, $this->bdpassword, $this->base);
        $sql_result = "";
        foreach ($BaseErrors as $BaseError) {
            switch ($BaseError) {
                case 1:

                    $sql = "CREATE TABLE `nordcom`.`postapi_log` ( `id` INT NOT NULL AUTO_INCREMENT , `logsession` INT NOT NULL DEFAULT '0',  `logtype` INT NOT NULL DEFAULT '0' , `logcontext` VARCHAR(255) NOT NULL DEFAULT '' , `logtext` VARCHAR(512) NOT NULL DEFAULT '' , `loglevel` INT NOT NULL DEFAULT '0' , PRIMARY KEY (`id`))";
                    $sql_result = mysqli_query($connection, $sql);
                    break;
                case 2:

                    $sql = "CREATE TABLE `nordcom`.`postapi_context` ( `id` INT NOT NULL AUTO_INCREMENT , `context` VARCHAR(255) NOT NULL DEFAULT '' , PRIMARY KEY (`id`))";
                    $sql_result = mysqli_query($connection, $sql);
                    break;
                case 3:

                    $sql = "CREATE TABLE `nordcom`.`postapi_sessions` ( `id` INT NOT NULL AUTO_INCREMENT , `sessiondate` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ,`postid` INT NOT NULL DEFAULT '0',   `sessionname` VARCHAR(255) NOT NULL DEFAULT '' , PRIMARY KEY (`id`))";
                    $sql_result = mysqli_query($connection, $sql);
                    break;

                case 4:

                    $sql = "CREATE TABLE `nordcom`.`postapi_response` ( `id` INT NOT NULL AUTO_INCREMENT ,  `logsession` INT NOT NULL DEFAULT '0',  `command` VARCHAR(512) NOT NULL DEFAULT '' ,  `response` VARCHAR(512) NOT NULL DEFAULT '',  PRIMARY KEY (`id`))";
                    $sql_result = mysqli_query($connection, $sql);
                    break;
            }
        }

        return $sql_result;
    }


    public function getLastAPIstruct()
    {
        $connection = mysqli_connect($this->server, $this->user, $this->bdpassword, $this->base);

        $sql = "select presponse.logsesion, presponse.command, presponse.commans, session.postid, session.id, presponse.id as pid from `nordcom`.`postapi_response` as presponse,  `nordcom`.`postapi_sessions` as session where session.id=presponse.logsession and session.postid=" . $this->postid . "order by pid desc limit 1";
        $sql_result = mysqli_query($connection, $sql);
        $sql_row = mysqli_fetch_array($sql_result);

        return  $sql_row;
    }

    public function analyzeAPIstruct()
    {
    }

    public function addAPIstruct($command, $newAPI)
    {

        $lastAPIstruct = $this->getLastAPIstruct();


        if ($this->lastresponse != $newAPI || $this->lastcomand != $command) {


            $connection = mysqli_connect($this->server, $this->user, $this->bdpassword, $this->base);


            $sql = "insert into `postapi_response` (`logsession`,`command`,`response`) VALUES ('" . $this->logsession . "','" . $command . "','" . APItools::prepareResponseStructure($newAPI) . "')";
            $sql_result = mysqli_query($connection, $sql);

            $this->lastresponse = $newAPI;
            $this->lastcomand = $command;
        }
    }
}
