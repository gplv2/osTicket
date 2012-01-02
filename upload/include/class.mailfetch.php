<?php
/*********************************************************************
  class.mailfetch.php

  mail fetcher class. Uses IMAP ext for now.

  Peter Rotich <peter@osticket.com>
  Copyright (c)  2006-2010 osTicket
  http://www.osticket.com

  Released under the GNU General Public License WITHOUT ANY WARRANTY.
  See LICENSE.TXT for details.

  vim: expandtab sw=4 ts=4 sts=4:
  $Id: $
 **********************************************************************/

require_once(INCLUDE_DIR.'class.mailparse.php');
require_once(INCLUDE_DIR.'class.ticket.php');
require_once(INCLUDE_DIR.'class.dept.php');

class MailFetcher {
    var $hostname;
    var $username;
    var $password;

    var $port;
    var $protocol;
    var $encryption;

    var $mbox;

    var $charset= 'UTF-8';

    function MailFetcher($username,$password,$hostname,$port,$protocol,$encryption='') {

        //if(!strcasecmp($protocol,'pop')) //force pop3, why ?  Imap seems to work fine...
        //$protocol='pop3';

        $this->hostname=$hostname;
        $this->username=$username;
        $this->password=$password;
        $this->protocol=strtolower($protocol);
        $this->port = $port;
        $this->encryption = $encryption;

        $this->serverstr=sprintf('{%s:%d/%s',$this->hostname,$this->port,strtolower($this->protocol));
            if(!strcasecmp($this->encryption,'SSL')){
                $this->serverstr.='/ssl';
            }
            $this->serverstr.='/novalidate-cert}INBOX'; //add other flags here as needed.

            //echo $this->serverstr;
            //Charset to convert the mail to.
            $this->charset='UTF-8';
            //Set timeouts 
            if(function_exists('imap_timeout')) {
                imap_timeout(1,20); //Open timeout.
            }
    }

    function connect() {
        return $this->open()?true:false;
    }

    function open() {

        //echo $this->serverstr;
        if($this->mbox && imap_ping($this->mbox))
            return $this->mbox;

        $this->mbox =@imap_open($this->serverstr,$this->username,$this->password);

        return $this->mbox;
    }

    function close() {
        imap_close($this->mbox,CL_EXPUNGE);
    }

    function mailcount(){
        return count(imap_headers($this->mbox));
    }


    function decode($encoding,$text) {

        switch($encoding) {
            case 1:
                $text=imap_8bit($text);
                break;
            case 2:
                $text=imap_binary($text);
                break;
            case 3:
                $text=imap_base64($text);
                break;
            case 4:
                $text=imap_qprint($text);
                break;
            case 5:
            default:
                $text=$text;
        } 
        return $text;
    }

    //Convert text to desired encoding..defaults to utf8
    function mime_encode($text,$charset=null,$enc='utf-8') { //Thank in part to afterburner  

        $encodings=array('UTF-8','WINDOWS-1251', 'ISO-8859-5', 'ISO-8859-1','KOI8-R');
        if(function_exists("iconv") and $text) {
            if($charset)
                return iconv($charset,$enc.'//IGNORE',$text);
            elseif(function_exists("mb_detect_encoding"))
                return iconv(mb_detect_encoding($text,$encodings),$enc,$text);
        }

        return utf8_encode($text);
    }

    //Generic decoder - mirrors imap_utf8
    function mime_decode($text) {
        $newString = '';
        $charset = "UTF-8";
        $elements=imap_mime_header_decode($text);
        for($i=0;$i<count($elements);$i++)
        {
            if ($elements[$i]->charset == 'default')
                $elements[$i]->charset = 'iso-8859-1';
            $newString .= iconv($elements[$i]->charset, $charset, $elements[$i]->text);
        }
        return $newString;
    }

    function getLastError(){
        return imap_last_error();
    }

    function getMimeType($struct) {
        $mimeType = array('TEXT', 'MULTIPART', 'MESSAGE', 'APPLICATION', 'AUDIO', 'IMAGE', 'VIDEO', 'OTHER');
        if(!$struct || !$struct->subtype)
            return 'TEXT/PLAIN';

        return $mimeType[(int) $struct->type].'/'.$struct->subtype;
    }

    function getHeaderInfo($mid) {

        $headerinfo=imap_headerinfo($this->mbox,$mid);
        // echo "headers   : " . print_r($headerinfo,true) . "\n";
        /* Patch the reply-to ignore bug that gets ignored ref: http://osticket.com/forums/showthread.php?t=497&highlight=getHeaderInfo */
        if (isset($headerinfo->reply_to[0])){
            $sender=$headerinfo->reply_to[0];
        } else {
            $sender=$headerinfo->from[0];
        }

        $tos = array();
        if(isset($headerinfo->to)) {
            foreach($headerinfo->to as $recipient) {
                if ($recipient->personal == ($recipient->mailbox.'@'.$recipient->host)) {
                    $tos[] = trim($recipient->mailbox.'@'.$recipient->host);
                } else {
                    $tos[] = trim($recipient->personal.' <'.$recipient->mailbox.'@'.$recipient->host.'>');
                }
            }
            $tos = trim(implode(', ', $tos));
        }

        $ccs = array();
        if(isset($headerinfo->cc)) {
            foreach($headerinfo->cc as $ccrecipient) {
                if ($ccrecipient->personal == ($ccrecipient->mailbox.'@'.$ccrecipient->host)) {
                    $ccs[] = trim($ccrecipient->mailbox.'@'.$ccrecipient->host);
                } else {
                    $ccs[] = trim($ccrecipient->personal.' <'.$ccrecipient->mailbox.'@'.$ccrecipient->host.'>');
                }
            }
            $ccs = trim(implode(', ', $ccs));
        }

        //Parse what we need...
        $header=array(
                'from' =>array('name' =>@$sender->personal,
                'email' =>strtolower($sender->mailbox).'@'.$sender->host),
                'to' =>$tos,
                'cc' =>$ccs,
                'subject' =>@$headerinfo->subject,
                'message_id' =>@$headerinfo->message_id, 'references' => Tools::cleanrefs(@$headerinfo->references),
                'in_reply_to' => Tools::cleanrefs(@$headerinfo->in_reply_to), 'followup_to' => Tools::cleanrefs(@$headerinfo->followup_to),
                'mid' =>$mid); 
        //'mid'    =>$headerinfo->message_id); 
        /* FIXME I think this $mid just turned from an INT into a STRING here, 
           possible bug in original code, mixup between message_id, the auto_increment field of the table 
           vs the real message_id of a mail. It is supposed to represent the number of the location of the mail 
           in the mailbox, not the damn real message_id.  So why bloody hell assign this.
         */
        return $header;
    }

    //search for specific mime type parts....encoding is the desired encoding.
    function getPart($mid,$mimeType,$encoding=false,$struct=null,$partNumber=false){

        if(!$struct && $mid) {
            $struct=@imap_fetchstructure($this->mbox, $mid);
        }
        //Match the mime type.
        if($struct && !$struct->ifdparameters && strcasecmp($mimeType,$this->getMimeType($struct))==0){
            $partNumber=$partNumber?$partNumber:1;
            if(($text=imap_fetchbody($this->mbox, $mid, $partNumber))){
                if($struct->encoding==3 or $struct->encoding==4) //base64 and qp decode.
                    $text=$this->decode($struct->encoding,$text);

                $charset=null;
                if($encoding) { //Convert text to desired mime encoding...
                    if($struct->ifparameters){
                        if(!strcasecmp($struct->parameters[0]->attribute,'CHARSET') && strcasecmp($struct->parameters[0]->value,'US-ASCII'))
                            $charset=trim($struct->parameters[0]->value);
                    }
                    $text=$this->mime_encode($text,$charset,$encoding);
                }
                return $text;
            }
        }
        //Do recursive search
        $text='';
        if($struct && $struct->parts){
            while(list($i, $substruct) = each($struct->parts)) {
                if($partNumber) 
                    $prefix = $partNumber . '.';
                if(($result=$this->getPart($mid,$mimeType,$encoding,$substruct,$prefix.($i+1))))
                    $text.=$result;
            }
        }
        return $text;
    }

    function getHeader($mid){
        return imap_fetchheader($this->mbox, $mid,FT_PREFETCHTEXT);
    }


    function getPriority($mid){
        return Mail_Parse::parsePriority($this->getHeader($mid));
    }

    function getBody($mid) {

        $body ='';
        if(!($body = $this->getpart($mid,'TEXT/PLAIN',$this->charset))) {
            if(($body = $this->getPart($mid,'TEXT/HTML',$this->charset))) {
                //Convert tags of interest before we striptags
                $body=str_replace("</DIV><DIV>", "\n", $body);
                $body=str_replace(array("<br>", "<br />", "<BR>", "<BR />"), "\n", $body);
                $body=Format::striptags($body); //Strip tags??
            }
        }
        return $body;
    }

    function createTicket($mid,$emailid=0){
        global $cfg;
        echo sprintf("[%s]: mid = %s,  emailid = %s\n",__METHOD__,$mid,$emailid);

        $mailinfo=$this->getHeaderInfo($mid);

        // Making sure we haven't done this email yet, since we can't trust the mid really we use the message_id*/
        $id=Ticket::getIdByRealMessageId(trim($mailinfo['message_id']),$mailinfo['from']['email']);
        if($mailinfo['mid'] && !empty($id)) {
            // Ok, we have already parsed this email message since it matches with our DB info, skip this 
            echo sprintf("[%s]: Ticket with id %s already exists\n",__METHOD__,$id);
            return false;
        }

        $var['name']=$this->mime_decode($mailinfo['from']['name']);
        $var['name']=$var['name']?$var['name']:$var['email']; //No name? use email
        $var['email']=$mailinfo['from']['email'];
        $var['cc']=$mailinfo['cc'];
        $var['to']=$mailinfo['to'];
        $var['message_id']=$mailinfo['message_id']; 
        $var['references']=$mailinfo['references'];
        $var['in_reply_to']=$mailinfo['in_reply_to'];
        $var['followup_to']=$mailinfo['followup_to'];
        $var['subject']=$mailinfo['subject']?$this->mime_decode($mailinfo['subject']):'[No Subject]';
        $var['message']=Format::stripEmptyLines($this->getBody($mid));
        $var['header']=$this->getHeader($mid);
        $var['emailId']=$emailid?$emailid:$cfg->getDefaultEmailId(); //ok to default?
        $var['mid']=$mid;

        if($cfg->useEmailPriority()) {
            $var['pri']=$this->getPriority($mid);
        }

        /*
        if(preg_match ("[[#][0-9]{1,10}]",$var['subject'],$regs) and !preg_match("[Frontend Trac]",$var['subject'])) 
        $extid=trim(preg_replace("/[^0-9]/", "", $regs[0]));
        echo sprintf("Match! %s, extid=%s\n",$var['subject'],$extid);
        $ticket= new Ticket(Ticket::getIdByExtId($extid));
        $ticket=null;

        //Allow mismatched emails?? For now NO.
        if(!$ticket || strcasecmp($ticket->getEmail(),$var['email']))
        $ticket=null;
        */

        // Scan the references mail-header for possible id's
        $ticket=new Ticket();
        $extid=null;
        $references=null;

        /* Screw the stuff in the subject, that should be last resort way
        Check the subject line for possible ID. message_id's are what you want */
        if(isset($var['references']) and strlen($var['references'])>0) {
            echo sprintf("[%s]: %s %s\n",__METHOD__,$var['references'],"References found");
            $references=trim($var['references']);
        } elseif(isset($var['in_reply_to']) and strlen($var['in_reply_to'])>0) {
            echo sprintf("[%s]: %s %s\n",__METHOD__,$var['references'],"In_Reply_To found");
            $references=trim($var['in_reply_to']);
        } elseif(isset($var['followup_to']) and strlen($var['followup_to'])>0) {
            echo sprintf("[%s]: %s %s\n",__METHOD__,$var['references'],"Followup found");
            $references=trim($var['followup_to']);
        } elseif(preg_match ("[[#][0-9]{1,10}]",$var['subject'],$regs)) {
            echo sprintf("[%s]: %s %s\n",__METHOD__,$var['references'],"Number found in Subject line");
            // Get the ext ticket number
            $extid=trim(preg_replace("/[^0-9]/", "", $regs[0]));
            if (isset($extid)) {
                echo sprintf("Match! %s, extid=%s\n",$var['subject'],$extid);
            }
        }  else {
            echo sprintf("[%s]: %s %s\n",__METHOD__,$mid,"No matching references/subject found");
            // exit;
        }

        // echo sprintf("[%s]: var = %s\n",__METHOD__,print_r($var,true));
        // throw new Exception;

        if(!empty($references)) {
            $t_id = Ticket::getIdByReferences($references);
            echo sprintf("[%s]: %s\n",__METHOD__,var_Dump($t_id,true)); 
        } else {
            // Only do this when there are no refs at all
            if(!empty($extid) and empty($references)) {
                $t_id = Ticket::getIdByExtId($extid,$var['message_id']);
            }
        }

        // echo "extid: " . $extid . "\n";
        // $ticket= new Ticket(Ticket::getIdByMessageId($extids,$mailinfo['from']['email']));
        // Allow mismatched emails?? 
        /*if(!$ticket || strcasecmp($ticket->getEmail(),$var['email'])) $ticket=null;*/
        // exit;

        $errors=array();
        echo sprintf("[%s]: %s\n",__METHOD__,var_Dump($ticket,true)); 

        if(empty($t_id)) {
            echo sprintf("[%s]: %s\n",__METHOD__,"No existing ticket found");
            if(!($ticket->create($var,$errors,'Email')) || $errors) {
                echo sprintf("[%s]: %s\n",__METHOD__,"Failed to create a ticket");
                return null;
            }
            $msgid=$ticket->getLastMsgId();
        } else {
            $ticket->load($t_id);
            echo sprintf("[%s]: %s\n",__METHOD__,"Existing ticket found.");
            $message=$var['message'];
            // Strip quoted reply...TODO: figure out how mail clients do it without special tag..
            if($cfg->stripQuotedReply() && ($tag=$cfg->getReplySeparator()) && strpos($var['message'],$tag)) {
                echo sprintf("[%s]: %s\n",__METHOD__,"Stripping off reply quotes.");
                list($message)=split($tag,$var['message']);
                // $tag=sprintf("/%s/",$tag);
                // list($message)=preg_split($tag,$var['message'],-1, PREG_SPLIT_NO_EMPTY);
            }
            echo sprintf("[%s]: %s\n",__METHOD__,"About to post a message update.");
            $msgid=$ticket->postMessage($message,'Email',$var['mid'],$var['header'],false,$var['to'],$var['cc'],$var['message_id']);
            // var_Dump($ticket);
            // throw new Exception;
            // exit;
        }
        //Save attachments if any.
        if($msgid && $cfg->allowEmailAttachments()){
            if(($struct = imap_fetchstructure($this->mbox,$mid)) && $struct->parts) {
                if($ticket->getLastMsgId()!=$msgid) {
                    $ticket->setLastMsgId($msgid);
                }
                $this->saveAttachments($ticket,$mid,$struct);
            }
        } 
        echo sprintf("[%s]: ticket = %s\n",__METHOD__,print_r($ticket,true));
        return $ticket;
    }

    function saveAttachments($ticket,$mid,$part,$index=0) {
        global $cfg;

        if($part && $part->ifdparameters && ($filename=$part->dparameters[0]->value)){ //attachment
            $index=$index?$index:1;
            if($ticket && $cfg->canUploadFileType($filename) && $cfg->getMaxFileSize()>=$part->bytes) {
                //extract the attachments...and do the magic.
                echo sprintf("[%s]: mid = %s,  imap = %s\n",__METHOD__,$mid,"fetchbody");
                $data=$this->decode($part->encoding, imap_fetchbody($this->mbox,$mid,$index));
                $ticket->saveAttachment($filename,$data,$ticket->getLastMsgId(),'M');
                return;
            }
            //TODO: Log failure??
        }

        //Recursive attachment search!
        if($part && $part->parts) {
            foreach($part->parts as $k=>$struct) {
                if($index) $prefix = $index.'.';
                $this->saveAttachments($ticket,$mid,$struct,$prefix.($k+1));
            }
        }

    }

    function fetchTickets($emailid,$max=500,$deletemsgs=false){

        echo sprintf("[%s]: emailid = %s,  imap = %s\n",__METHOD__,$emailid,"num_msg");
        $nummsgs=imap_num_msg($this->mbox);
        // echo "New Emails:  $nummsgs\n";
        $msgs=$errors=0;
        /* Big rant: Why would you want to reverse this ?  In fact, you want to process the oldest first since they might generate a new ticket which between intervals
a customer can already responded to.  Now in this case, the reply will trigger a ticket first or worse.  Since I'm checking all this stuff with a live support mailbox, nutter I am I have a real use case at hand guiding me to all these rants vs the code.  -- nothing personal */
        for($i=1; $i<=$nummsgs; $i++) { // process messages as they are sitting in that mailbox
            if($this->createTicket($i,$emailid)){
                imap_setflag_full($this->mbox, imap_uid($this->mbox,$i), "\\Seen", ST_UID); //IMAP only??
                if($deletemsgs)
                    imap_delete($this->mbox,$i);
                $msgs++;
                $errors=0; //We are only interested in consecutive errors.
            }else{
                echo sprintf("[%s]: emailid = %s,  imap = %s\n",__METHOD__,$emailid,"num_msg");
                $errors++;
            }
            if(($max && $msgs>=$max) || $errors>20)
                break;
        }

        if($deletemsgs) {
            @imap_expunge($this->mbox);
        }

        return $msgs;
    }

    function fetchMail(){
        global $cfg;

        if(!$cfg->canFetchMail())
            return;

        //We require imap ext to fetch emails via IMAP/POP3
        if(!function_exists('imap_open')) {
            $msg='PHP must be compiled with IMAP extension enabled for IMAP/POP3 fetch to work!';
            Sys::log(LOG_WARN,'Mail Fetch Error',$msg);
            return;
        }

        $MAX_ERRORS=5; //Max errors before we start delayed fetch attempts - hardcoded for now.
        // Apparantly an error-checking query
        $sql=' SELECT email_id,mail_host,mail_port,mail_protocol,mail_encryption,mail_delete,mail_errors,userid,userpass FROM '.EMAIL_TABLE.
            ' WHERE mail_active=1 AND (mail_errors<='.$MAX_ERRORS.' OR (TIME_TO_SEC(TIMEDIFF(NOW(),mail_lasterror))>5*60) )'.
            ' AND (mail_lastfetch IS NULL OR TIME_TO_SEC(TIMEDIFF(NOW(),mail_lastfetch))>mail_fetchfreq*60) ';
        //echo $sql;
        if(!($accounts=db_query($sql)) || !db_num_rows($accounts))
            return;

        //TODO: Lock the table here?? which table and why since DB's generally take care of that whole locking thing for you ?
        while($row=db_fetch_array($accounts)) {
            $fetcher = new MailFetcher($row['userid'],Misc::decrypt($row['userpass'],SECRET_SALT),
                    $row['mail_host'],$row['mail_port'],$row['mail_protocol'],$row['mail_encryption']);
            if($fetcher->connect()){   
                $fetcher->fetchTickets($row['email_id'],$row['mail_fetchmax'],$row['mail_delete']?true:false);
                $fetcher->close();
                db_query('UPDATE '.EMAIL_TABLE.' SET mail_errors=0, mail_lastfetch=NOW() WHERE email_id='.db_input($row['email_id']));
            }else{
                $errors=$row['mail_errors']+1;
                db_query('UPDATE '.EMAIL_TABLE.' SET mail_errors=mail_errors+1, mail_lasterror=NOW() WHERE email_id='.db_input($row['email_id']));
                if($errors>=$MAX_ERRORS){
                    //We've reached the MAX consecutive errors...will attempt logins at delayed intervals
                    $msg="\nThe system is having trouble fetching emails from the following mail account: \n".
                        "\nUser: ".$row['userid'].
                        "\nHost: ".$row['mail_host'].
                        "\nError: ".$fetcher->getLastError().
                        "\n\n ".$errors.' consecutive errors. Maximum of '.$MAX_ERRORS. ' allowed'.
                        "\n\n This could be connection issues related to the host. Next delayed login attempt in aprox. 10 minutes";
                    Sys::alertAdmin('Mail Fetch Failure Alert',$msg,true);
                }
            }
        }
    }
}
?>
