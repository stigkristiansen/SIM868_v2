<?

require_once(__DIR__ . "/../libs/logging.php");

class SIM868Gateway_v2 extends IPSModule
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
				
		$wordsToSearchFor = array("\r\nOK\r\n", "\r\n+CMTI: \"SM\",","\r\nERROR\r\n", "\r\nNORMAL POWER DOWN\r\n");
		foreach ($wordsToSearchFor as $word) {
			$log->LogMessage("Searching for \"".preg_replace("/(\r\n)+|\r+|\n+/i", " ", $word)."\" in \"".preg_replace("/(\r\n)+|\r+|\n+/i", " ", $buffer)."\"");
			$length = strlen($buffer)-strlen($word);
			$pos = strpos($buffer, $word);
			
			if($pos!== false)
				break;
		}
		
		$foundComplete = false;
		if($pos === $length || ($pos===0 && $word=="\r\n+CMTI: \"SM\"," && substr($buffer, strlen($buffer)-2) === "\r\n" )) {
			$buffer = preg_replace("/(\r\n)+|\r+|\n+/i", " ", $buffer);
			$buffer = trim(preg_replace("/\s+/", " ", $buffer));
			
			$log->LogMessage("Found a complete messge \"".$buffer."\"");
									
			$foundComplete = true;
			$completeMessage = $buffer;
						
			$buffer = "";
			
		} else {
			$incomingBuffer = preg_replace("/(\r\n)+|\r+|\n+/i", " ", $incomingBuffer);
			$log->LogMessage("Received part of message: ", $incomingBuffer);
		}
		
		$this->SetBuffer("Buffer", $buffer);
		
		$this->Unlock("BufferLock"); 
		
		if($foundComplete) {
			//$this->SendDataToChildren(json_encode(Array("DataID" => "{27E8784A-DF07-4142-9C77-281BF411EEB7}", "Buffer" => $completeMessage)));
			$this->SetInProgress(false);
		}
		
		return true;
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
		
		$status = false;
		try{
			$this->SetInProgress(true);
			$this->SendDataToParent(json_encode(Array("DataID" => "{79827379-F36E-4ADA-8A95-5F8D1DC92FA9}", "Buffer" => $buffer)));
			$status = $this->WaitForResponse(1000);
				
		} catch (Exeption $ex) {
			$log->LogMessageError("Failed to send the command \"".$Command."\" . Error: ".$ex->getMessage());
			$this->SetInProgress(false);
		}
		
		return $status;
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
		
		$this->SetBuffer("SendingInProgress", $State);
		
		$log->LogMessage("InProgress flag is set to \"".$stringValue."\"");
		
		$this->Unlock("InProgressLock");
	}
	
	private function GetInProgress() {
		$log = new Logging($this->ReadPropertyBoolean("log"), IPS_Getname($this->InstanceID));
		
		$state = (boolean)$this->GetBuffer("SendingInProgress");
		
		if($State)
			$stringValue = "true";
		else
			$stringValue = "false";
		
		$log->LogMessage("GetInProgress: InProgress flag is \"".$stringValue."\"");
		
		return $state;
		
		if($stringValue === "true")
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
				$log->LogMessage("InProgress flag is \"true\"");
			else
				$log->LogMessage("InProgress flag is \"false\"");
 			 
 			if(!$inProgress) { 
 				$log->LogMessage("A sending was completed"); 
 				return true; 
 			} else 
 				$log->LogMessage("Waiting for sending to complete..."); 
 				 
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
				$log->LogMessageError("The parent I/O port is not supported");
		} else
			$log->LogMessageError("The parent I/O port is not active.");
		
		return false;
	}
}

?>
