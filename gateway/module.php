<?

require_once(__DIR__ . "/../libs/logging.php");

class SIM868GatewayV2 extends IPSModule
{
    
    public function Create()
    {
        parent::Create();
        $this->RequireParent("{6DC3D946-0D31-450F-A8C6-C42DB8D7D4F1}");
        
        $this->RegisterPropertyBoolean ("log", true);
		$this->RegisterPropertyString ("pin", "");
    }

    public function ApplyChanges(){
        parent::ApplyChanges();
             
    }

	public function ForwardData ($JSONString) {
		$incomingData = json_decode($JSONString);
		$incomingBuffer = utf8_decode($incomingData->Buffer);
		
		$log = new Logging($this->ReadPropertyBoolean("log"), IPS_Getname($this->InstanceID));
		$log->LogMessage("Received data from child"); 
		
		return $this->SendCommand($incomingBuffer);
	}
	
    public function ReceiveData($JSONString) {
		$incomingData = json_decode($JSONString);
		$incomingBuffer = utf8_decode($incomingData->Buffer);
				
		$log = new Logging($this->ReadPropertyBoolean("log"), IPS_Getname($this->InstanceID));
		$log->LogMessage("Received data from parent"); 
		
		$buffer = $this->GetBuffer("Buffer");
		$buffer .= $incomingBuffer;
		
		if (!$this->Lock("BufferLock")) { 
			$log->LogMessage("Buffer is already locked. Aborting message handling!"); 
            return false;  
		} else
			$log->LogMessage("Buffer is locked");
		
		$log->LogMessage("Buffer is \"".$buffer."\"");
		
		$patternsToSearchFor = array(array("pattern" => "/^\r\n\+CMTI: \"(SM|ME)\",([0-9]+)\r\n$/","forward" => true),
								     array("pattern" => "/^\r\nERROR\r\n$/","forward" => false),
								     array("pattern" => "/^\r\nNORMAL POWER DOWN\r\n$/","forward" => false),
								     array("pattern" => "/^AT\+CMGR=(\d{1,2})\r\r\n\+CMGR: \"REC.+\",\"(.+)\",\"\",\".+\"\r\n(.+)\r\n\r\nOK\r\n$/i","forward" => false),
									 array("pattern" => "/\r\nOK\r\n$/","forward" => false),
									 array("pattern" => "/^AT\+CMGS=\"\d+\".+>$/is","forward" => false)
									);
							
		$log->LogMessage("Searching for complete message...");
		//AT+CMGS="95064534"<CR><CR><LF>> 
		$forwardToChildern = false;
		$foundCompleteMessage = false;
		foreach ($patternsToSearchFor as $pattern){
			$log->LogMessage("Using RegEx for pattern match: ".$pattern['pattern']);
			if(preg_match_all($pattern['pattern'], $buffer, $matches, PREG_SET_ORDER, 0)!=0) {
					$log->LogMessage("Found complete message using RegEx");
					
					$foundCompleteMessage = true;
					$completeMessage = $buffer;
					$forwardToChildern = $pattern['forward'];
					$this->SetBuffer("completemessage", $completeMessage);
														
					$buffer = "";
					break;
			}
		}				
		
		$this->SetBuffer("Buffer", $buffer);
		
		$this->Unlock("BufferLock"); 
		
		if($foundCompleteMessage) {
			$this->SetInProgress(false);
			
			if($forwardToChildern) {
				$log->LogMessage("Forwarding complete message to children");
				$this->SendDataToChildren(json_encode(Array("DataID" => "{1AD9130C-C5B2-4C0D-9A3F-40D5F1337898}", "Buffer" => $completeMessage)));
				$log->LogMessage("Forwarding complete message to children completed");
			}
				
			
		}
		
	}
	
	public function SendCommand(string $Command) {
		if(!$this->EvaluateParent())
			return false;
				
		$log = new Logging($this->ReadPropertyBoolean("log"), IPS_Getname($this->InstanceID));
		
		if (!$this->Lock("BufferLock")) { 
			$log->LogMessage("Buffer is already locked. Aborting message handling!"); 
            return false;  
		} else
			$log->LogMessage("Buffer is locked");
		
		$log->LogMessage("Resetting buffer");
		$this->SetBuffer("Buffer", '');
		
		$this->Unlock("BufferLock");
				
		$log->LogMessage("Sending command \"".$Command."\"");
		$buffer = $Command.chr(13).chr(10);
		
		$return = "ERROR";
		try{
			$this->SetInProgress(true);
			$this->SendDataToParent(json_encode(Array("DataID" => "{79827379-F36E-4ADA-8A95-5F8D1DC92FA9}", "Buffer" => $buffer)));
			if($this->WaitForResponse(1000))
				$return = $this->GetBuffer("completemessage");	
		} catch (Exeption $ex) {
			$log->LogMessageError("Failed to send the command \"".$Command."\" . Error: ".$ex->getMessage());
			$this->SetInProgress(false);
		}
		
		return $return;
	}
	
	private function SetInProgress($State) {
		$log = new Logging($this->ReadPropertyBoolean("log"), IPS_Getname($this->InstanceID));
		
		if($State)
			$stringValue = "true";
		else
			$stringValue = "false";
		
		if (!$this->Lock("InProgressLock")) { 
			$log->LogMessage("InProgressLock is already locked. Aborting message handling!"); 
            return false;  
		} else
			$log->LogMessage("InProgressLock is locked");
		
		$log->LogMessage("SetInProgress: InProgress flag is set to \"".$stringValue."\"");
		
		$this->SetBuffer("SendingInProgress", $stringValue);
		
		$newState = $this->GetBuffer("SendingInProgress");
		
		$log->LogMessage("SetInProgress: Checking InProgress flag. It is set to \"".$newState."\"");
		
		$this->Unlock("InProgressLock");
	}
	
	private function GetInProgress() {
		$log = new Logging($this->ReadPropertyBoolean("log"), IPS_Getname($this->InstanceID));
		
		$state = $this->GetBuffer("SendingInProgress");
		
		$log->LogMessage("GetInProgress: InProgress flag is \"".$state."\"");
		
		if($state == "true")
			return true;
		else	
			return false;
		
	}
	
	private function WaitForResponse ($Timeout) {
		$log = new Logging($this->ReadPropertyBoolean("log"), IPS_Getname($this->InstanceID));
			
		$iteration = intval($Timeout/100);
 		for($x=0;$x<$iteration;$x++) { 
 			$inProgress = $this->GetInProgress();
			
			if($inProgress)
				$log->LogMessage("WaitForResponse: InProgress flag is \"true\"");
			else
				$log->LogMessage("WaitForResponse: InProgress flag is \"false\"");
 			 
 			if(!$inProgress) { 
 				$log->LogMessage("WaitForResponse: A sending was completed"); 
 				return true; 
 			} else 
 				$log->LogMessage("WaitForResponse: Waiting for sending to complete..."); 
 				 
 			IPS_Sleep(100); 
 		} 
		
		return false;
	}

    
	private function Lock($ident){
		$log = new Logging($this->ReadPropertyBoolean("log"), IPS_Getname($this->InstanceID));
		for ($i = 0; $i < 100; $i++){
			if (IPS_SemaphoreEnter("SM868GW_" . (string) $this->InstanceID . (string) $ident, 1)){
				$log->LogMessage("Semaphore ".$ident." is set"); 
				return true;
			} else {
				if($i==0)
					$log->LogMessage("Waiting for lock...");
				IPS_Sleep(mt_rand(1, 5));
			}
		}
        
        $log->LogMessage($ident." is already locked"); 
        return false;
    }

    private function Unlock($ident)
    {
        IPS_SemaphoreLeave("SM868GW_" . (string) $this->InstanceID . (string) $ident);
		$log = new Logging($this->ReadPropertyBoolean("log"), IPS_Getname($this->InstanceID));
		$log->LogMessage("Semaphore ".$ident." is cleared");
    }
	
	private function HasActiveParent(){
        $instance = IPS_GetInstance($this->InstanceID);
        if ($instance['ConnectionID'] > 0){
            $parent = IPS_GetInstance($instance['ConnectionID']);
            if ($parent['InstanceStatus'] == 102)
                return true;
        }
        return false;
    }
	
	private function EvaluateParent() {
    	$log = new Logging($this->ReadPropertyBoolean("log"), IPS_Getname($this->InstanceID));
		
		if($this->HasActiveParent()) {
            $instance = IPS_GetInstance($this->InstanceID);
            $parentGUID = IPS_GetInstance($instance['ConnectionID'])['ModuleInfo']['ModuleID'];
            if ($parentGUID == '{6DC3D946-0D31-450F-A8C6-C42DB8D7D4F1}') {
				$log->LogMessage("The parent I/O port is active and supported");
				return true;
			} else
				$log->LogMessageError("The parent is not supported");
		} else
			$log->LogMessageError("The parent is not active.");
		
		return false;
	}
}

?>
