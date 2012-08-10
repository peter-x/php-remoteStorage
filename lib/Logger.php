<?php

class Logger {

    private $_logFile;
    private $_sendMail;

    public function __construct($logFile, $sendMail = NULL) {
        $this->_logFile = $logFile;
        $this->_sendMail = $sendMail;
    }

    public function logMessage($level, $message) {
        switch($level) {
            case 10:
                $logLevel = "[INFO]   ";
                break;
            case 20:
                $logLevel = "[WARNING]";
                break;
            case 50:
                $logLevel = "[FATAL]  ";
                break;
        }
        $logMessage = date("c") . " " . $logLevel . " " . $message . PHP_EOL;
        if(FALSE === file_put_contents($this->_logFile, $logMessage, FILE_APPEND | LOCK_EX)) {
            throw new Exception("unable to write to log file");
        }

        if(NULL !== $this->_sendMail) {
            $mailSubject = $logLevel . " " . substr(strtok($message, PHP_EOL), 0, 20);
            $mailBody = $message;
            if(FALSE === mail($this->_sendMail, $mailSubject, $mailBody)) {
                throw new Exception("unable to mail log entry");
            }
        }
    }

    public function logInfo($message) {
        $this->logMessage(10, $message);
    }

    public function logWarn($message) {
        $this->logMessage(20, $message);
    }

    public function logFatal($message) {
        $this->logMessage(50, $message);
    }

}

?>
