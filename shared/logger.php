<?php

require __DIR__ . '/../vendor/autoload.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
use PHPMailer\PHPMailer\SMTP;

date_default_timezone_set('America/Detroit');

ini_set('error_reporting', E_ALL);
ini_set('display_errors', true);

function error_handler($errno, $errstr) {
    global $last_error;
    $last_error = $errstr;
}

set_error_handler('error_handler');

class logger {
    
    public static $my_version = '1.0';
    private $is_cli;
	
	public $config = array(
		"mailer"		=> "smtp",
		"host"			=> "xxxxxxxxxxxxxxxx",
		"port"			=> 587,
		"smtpauth"		=> true,
		"smtpuser"		=> "xxxxxxxxxxxxxxxxxxx",
		"smtppass"		=> "xxxxxxxxxxxxxxxxxxx",
		"smtpsecure"	=> PHPMailer::ENCRYPTION_STARTTLS,
		"to"			=> array("xxxxxxxxxxxxx"),
		"logfolder"		=> "logs",
		"loglife"		=> 7,
		"appname"		=> "",
        "mode"          => "dev"
		);
	
	private $start_time;
	private $log_dir;
	private $pidrunning;
	private $pidfilename;
	
    function __construct($appname, $cwd, $mode = "dev") {
        $this->is_cli = (php_sapi_name() === 'cli');
        
        // Remember the current working directory, because PHP will forget this when the
        // destructor runs
        //
        $this->config['logfolder'] = $cwd."/".$this->config['logfolder'];
        $this->config['mode'] = $mode;
        
		$this->log_dir = $this->config['logfolder'];
		if ( !file_exists($this->log_dir) ) {
			// create directory/folder uploads.
			if (!mkdir($this->log_dir, 0777, true)) {
				$this->logErrorMsg("Failed to create log directory: ".$last_error, true, false);
			}
		}
		
		$this->config['appname'] = $appname;
        $this->start_time = microtime(true);

        $this->logInfoMsg("Starting execution at ".date("Y-m-d H:i:s"));
        
		$this->pidfilename = $this->log_dir.'/'.$this->config['appname'] . '.pid';
		$this->pidrunning = false;
		
		// Only test for PID in CLI mode since Apache reuses PIDs
		if ($this->is_cli) {
            if(file_exists($this->pidfilename)) {
                $pid = (int)trim(file_get_contents($this->pidfilename));
                if(posix_kill($pid, 0)) {
                    $this->pidrunning = true;
                }
            }
           
            if(!$this->pidrunning) {
                $pid = getmypid();
                file_put_contents($this->pidfilename, $pid);
            } else {
                $this->logInfoMsg("Ending execution at ".date("Y-m-d H:i:s")." after ".$exec_time." seconds");
                exit();
            }
		}
    }

    // Rest of constructor and destructor remain the same...

    public function logLDAPChanges($msg, $email = false) {
        if ($this->config['mode'] == "dev") {
            return;
        }
        
        $fullmsg = "[".date("D M d H:i:s.u Y")."] [INFO] ".$msg;
        $subject = "LDAP update notification from application: ".strtoupper($this->config['appname']);
        
        $this->logMsg($fullmsg, 'ldap_user_changes');
        if ($email) mailMsg($subject, $fullmsg);
        
        if ($this->is_cli && $this->config['mode'] == "dev") {
            echo "".$fullmsg."<br>\n";
        }
    }
    
    public function logSQLChanges($msg, $email = false) {
        if ($this->config['mode'] == "dev") {
            return;
        }
        
        $fullmsg = "[".date("D M d H:i:s.u Y")."] [INFO] ".$msg;
        $subject = "SQL update notification from application: ".strtoupper($this->config['appname']);
        
        $this->logMsg($fullmsg, 'sql_user_changes');
        if ($email) mailMsg($subject, $fullmsg);
        
        if ($this->is_cli && $this->config['mode'] == "dev") {
            echo "".$fullmsg."<br>\n";
        }
    }
    
    public function logInfoMsg($msg, $email = false) {
        $fullmsg = "[".date("D M d H:i:s.u Y")."] [INFO] ".$msg;
        $subject = "Info Notification from application: ".strtoupper($this->config['appname']);
        
        $this->logMsg($fullmsg);
        if ($email) mailMsg($subject, $fullmsg);
        
        if ($this->is_cli && $this->config['mode'] == "dev") {
            echo "".$fullmsg."<br>\n";
        }
    }
    
    public function logErrorMsg($msg, $email = true, $log = true) {
        $fullmsg = "[".date("D M d H:i:s.u Y")."] [ERROR] ".$msg;
        $subject = "Error Notification from application: ".strtoupper($this->config['appname']);
        
        if ($log) $this->logMsg($fullmsg);
        if ($email) $this->mailMsg($subject, $fullmsg);
        
        if ($this->is_cli && $this->config['mode'] == "dev") {
            echo "".$fullmsg."<br>\n";
        }
    }
    
    public function logAlertMsg($msg, $email = false) {
        $fullmsg = "[".date("D M d H:i:s.u Y")."] [ALERT] ".$msg;
        $subject = "Alert Notification from application: ".strtoupper($this->config['appname']);
        
        $this->logMsg($fullmsg);
        if ($email) $this->mailMsg($subject, $fullmsg);
        
        if ($this->is_cli && $this->config['mode'] == "dev") {
            echo "".$fullmsg."<br>\n";
        }
    }
	
	private function logPurge() {
	    
	    //echo "Log folder: ".$this->config['logfolder']."<br>\n";

		if (file_exists($this->config['logfolder'])) {
			foreach (new DirectoryIterator($this->config['logfolder']) as $fileInfo) {
				if ($fileInfo->isDot()) {
					continue;
				}
				
				$age = time() - $fileInfo->getCTime();
				$agedays = $age / 86400;
				$maxage = $this->config['loglife'] * 86400;

				if ($fileInfo->isFile() && ($age >= $maxage)) {
			        $this->logInfoMsg("Purging log file: ".$fileInfo->getRealPath().", age is ".$agedays." days.");
					unlink($fileInfo->getRealPath());
				}
			}
		}
	}
	
	public function mailData($recipientlist, $subject, $body, $attachmentlist ) {
	    
		//Instantiation and passing "true" enables exceptions
		//
		$mail = new PHPMailer(true);

		try {
			// Server settings
			//
			$mail->IsSMTP();
			$mail->Mailer = $this->config['mailer'];
			$mail->Host = $this->config['host'];
			$mail->Port = $this->config['port'];
			$mail->SMTPAuth = $this->config['smtpauth'];
			$mail->Username = $this->config['smtpuser'];
			$mail->Password = $this->config['smtppass'];
			$mail->SMTPSecure = $this->config['smtpsecure'];

			// Recipients
			//
			$mail->setFrom($this->config['smtpuser']);
			
			foreach($recipientlist as $recipent) {
                $mail->addAddress($recipent);
			}
			
			foreach($attachmentlist as $attachment) {
                $mail->addAttachment($attachment);
			}
			
			$mail->addReplyTo($this->config['smtpuser']);
			
			// Content
			//
			$mail->isHTML(true);
			$mail->Subject = $subject;
			$mail->Body = $body;

			$mail->send();
			
		} catch (Exception $e) {
			$this->logErrorMsg("Message could not be sent. Mailer Error: ".$mail->ErrorInfo, false);
		}
		
	}
	
	private function mailMsg($subject, $message) {
	    
        if ( $this->config['mode'] == "dev") {
            // Do not send emails when running in dev mode
            //
	    	return;
		}
		
		//Instantiation and passing "true" enables exceptions
		//
		$mail = new PHPMailer(true);

		try {
			// Server settings
			//
			$mail->IsSMTP();
			$mail->Mailer = $this->config['mailer'];
			$mail->Host = $this->config['host'];
			$mail->Port = $this->config['port'];
			$mail->SMTPAuth = $this->config['smtpauth'];
			$mail->Username = $this->config['smtpuser'];
			$mail->Password = $this->config['smtppass'];
			$mail->SMTPSecure = $this->config['smtpsecure'];

			// Recipients
			//
			$mail->setFrom($this->config['smtpuser']);
			
			foreach($this->config['to'] as $recipent) {
                $mail->addAddress($recipent);
			}
			
			$mail->addReplyTo($this->config['smtpuser']);

			// Content
			//
			$mail->isHTML(true);
			$mail->Subject = $subject;
			$mail->Body = $message;

			$mail->send();
			
		} catch (Exception $e) {
			$this->logErrorMsg("Message could not be sent. Mailer Error: ".$mail->ErrorInfo, false);
		}
	}
	
	private function logMsg( $message, $appoverride = "" ) {
		global $last_error;
		
		$log_dir = $this->config['logfolder'];
		if ($appoverride == "") {
	    	$log_file_data = $log_dir.'/'.$this->config['appname'].'_' . date('d-M-Y') . '.log';
		} else {
	    	$log_file_data = $log_dir.'/'.$appoverride.'_'.date('d-M-Y') . '.log';
		}
		file_put_contents($log_file_data, $message . "\n", FILE_APPEND);
	}
}


?>
