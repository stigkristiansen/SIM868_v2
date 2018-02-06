<?

require_once(__DIR__ . "/../libs/logging.php");

class SIM868SmsV2 extends IPSModule
{
    
    public function Create(){
        parent::Create();
        
		$this->RequireParent("{70F64F80-3F5F-4193-A0CE-9C926AB6EE89}");
        
        $this->RegisterPropertyBoolean ("log", true);
		$this->RegisterPropertyString("SMSCommands", "");
	}

    public function ApplyChanges(){
        parent::ApplyChanges();
        
    }
	
	public function GetSMSCommands(){
		return $this->ReadPropertyString("SMSCommands");
	}

    public function ReceiveData($JSONString) {
		$incomingData = json_decode($JSONString);
		$incomingBuffer = utf8_decode($incomingData->Buffer);
				
		$log = new Logging($this->ReadPropertyBoolean("log"), IPS_Getname($this->InstanceID));
		
		$log->LogMessage("Received data: ".$incomingBuffer); 
		
		if(preg_match_all('/^\r\n\+CMTI: \"(SM|ME)\",([0-9]+)\r\n$/', $incomingBuffer, $matches, PREG_SET_ORDER, 0)!=0) {
			$log->LogMessage("Incoming message. Evaluating...");
			
			$readCommand = "AT+CMGR=".$matches[0][2];
			$deleteCommand = "AT+CMGD=".$matches[0][2];
			$log->LogMessage("Read command is: ".$readCommand);
			$log->LogMessage("Delete command is: ".$deleteCommand);
			$this->SendATCommand("AT+CMGF=1");
			//$message = $this->SendATCommand($readCommand);
			//$this->SendATCommand($deleteCommand);
			
			$log->LogMessage("The incomming message was: ".$message);
			
		} else
			$log->LogMessage("Unknown command!");
    }
	
	private function SendATCommand($Command) {
		if(!$this->EvaluateParent())
			return "ERROR";
		
		$log = new Logging($this->ReadPropertyBoolean("log"), IPS_Getname($this->InstanceID));
		
		$log->LogMessage("Sending command \"".$Command."\"to parent gateway...");
		return $this->SendDataToParent(json_encode(Array("DataID" => "{FC5541DE-14A9-4D5C-A3CF-6C769B8832CA}", "Buffer" => $Command)));
	}
	
	Public function SendCommand(string $Command) {
		return $this->SendATCommand($Command);
	}
	
		
	
	private function EvaluateParent() {
    	$log = new Logging($this->ReadPropertyBoolean("log"), IPS_Getname($this->InstanceID));
		
		$instance = IPS_GetInstance($this->InstanceID);
		$parentGUID = IPS_GetInstance($instance['ConnectionID'])['ModuleInfo']['ModuleID'];
		if ($parentGUID == '{70F64F80-3F5F-4193-A0CE-9C926AB6EE89}') {
			$log->LogMessage("The parent is supported");
			return true;
		} else
			$log->LogMessageError("The parent is not supported");
		
		return false;
	}
}

?>
