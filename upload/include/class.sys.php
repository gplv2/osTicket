<?php
/*************************************************************************
    class.sys.php

    System core helper.

    Peter Rotich <peter@osticket.com>
    Copyright (c)  2006-2010 osTicket
    http://www.osticket.com

    Released under the GNU General Public License WITHOUT ANY WARRANTY.
    See LICENSE.TXT for details.

    vim: expandtab sw=4 ts=4 sts=4:
    $Id: $
**********************************************************************/

require_once(INCLUDE_DIR.'class.config.php'); //Config helper

define('LOG_WARN',LOG_WARNING);

class Sys extends Config {

    private $conf;

    var $loglevel=array(1=>'Error','Warning','Debug');

    function __construct($db) {
        // parent::__construct($db);
        if(isset($db)) {
            $this->db=$db;
            $this->conf= new Config($this->db);
        }
    }

    //Load configuration info.
    function getConfig() {
        return ($this->conf && $this->conf->getId())?$this->conf:null;
    }

    function alertAdmin($subject,$message,$log=false) {
                
        //Set admin's email address
        if(!$this->conf || !($to=$this->conf->getAdminEmail())) {
            $to=ADMIN_EMAIL;
        }

        //Try getting the alert email.
        $email=null;
        if($this->conf && !($email=$this->conf->getAlertEmail())) 
            $email=$this->conf->getDefaultEmail(); //will take the default email.

        if($email) {
            $email->send($to,$subject,$message);
        }else {//no luck - try the system mail.
            Email::sendmail($to,$subject,$message,sprintf('"osTicket Alerts"<%s>',$to));
        }

        //log the alert? Watch out for loops here.
        if($log && is_object($this->conf)) { //if $conf is not set then it means we don't have DB connection.
            Sys::log(LOG_CRIT,$subject,$message,false); //Log the enter...and make sure no alerts are resent.
        }

    }

    function log($priority,$title,$message,$alert=true) {

        switch($priority){ //We are providing only 3 levels of logs. Windows style.
            case LOG_EMERG:
            case LOG_ALERT: 
            case LOG_CRIT: 
            case LOG_ERR:
                $level=1;
                if($alert) {
                    Sys::alertAdmin($title,$message);
                }
                break;
            case LOG_WARN:
            case LOG_WARNING:
                //Warning...
                $level=2;
                break;
            case LOG_NOTICE:
            case LOG_INFO:
            case LOG_DEBUG:
            default:
                $level=3;
                //debug
        }
        //Save log based on system log level settings.
        if($this->conf && $this->conf->getLogLevel()>=$level){
            $loglevel=array(1=>'Error','Warning','Debug');
            $sql='INSERT INTO '.SYSLOG_TABLE.' SET created=NOW(),updated=NOW() '.
                 ',title='.db_input($title).
                 ',log_type='.db_input($loglevel[$level]).
                 ',log='.db_input($message).
                 ',ip_address='.db_input($_SERVER['REMOTE_ADDR']);
            //echo $sql;
            $this->db->db_query($sql);
        }
    }

    // Truncate logs
    function truncateLogs(){
        $sql='TRUNCATE '.SYSLOG_TABLE;
        $this->db->db_query($sql);
    }

    function purgeLogs(){

        if($this->conf && ($gp=$this->conf->getLogGraceperiod()) && is_numeric($gp)) {
            $sql='DELETE  FROM '.SYSLOG_TABLE.' WHERE DATE_ADD(created, INTERVAL '.$gp.' MONTH)<=NOW()';
            $this->db->db_query($sql);
        }

    }
}

?>
