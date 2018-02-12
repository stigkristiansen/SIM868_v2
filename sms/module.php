<?

require_once(__DIR__ . "/../libs/logging.php");

class SIM868SmsV2 extends IPSModule
{
    
    public function Create(){
        parent::Create();
        
		$this->RequireParent("{70F64F80-3F5F-4193-A0CE-9C926AB6EE89}");
        
        $this->RegisterPropertyBoolean ("log", true);
		$this->RegisterPropertyString("smscommands", "");
		$this->RegisterPropertyString("smssenders", "");
				
		$script = file_get_contents(__DIR__ . "/../libs/_Dispatch.php");
		$this->RegisterScript("dispatch", "_Dispatch", $script, 0);
	}

    public function ApplyChanges(){
        parent::ApplyChanges();
        
    }
	
	public function GetSMSAcceptedSenders(){
		return $this->ReadPropertyString("smssenders");
	}
	
	public function GetSMSCommands(){
		return $this->ReadPropertyString("smscommands");
	}
	
	Public function SendCommand(string $Command) {
		return $this->SendATCommand($Command);
	}
		
	public function SendSMS(string $Receiver, string $Message) {
		$this->SendATCommand("AT");
		$this->SendATCommand("ATE0");
		$this->SendATCommand("AT+CMGF=1");
		$this->SendATCommand("AT+CMGS=\"".$Receiver."\"");
		$this->SendATCommand($Message.chr(0x1a));
	}
	
	public function OpenSim(string $PinCode) {
		
	}
	
	public function ChangeSimCode(string $OldPinCode, string $NewPinCode) {
		
	}
	
	public function EnablePinCode(string PinCode, boolean $State) {
		
	}

    public function ReceiveData($JSONString) {
		$incomingData = json_decode($JSONString);
		$incomingBuffer = utf8_decode($incomingData->Buffer);
				
		$log = new Logging($this->ReadPropertyBoolean("log"), IPS_Getname($this->InstanceID));
		
		$log->LogMessage("Received a message: ".$incomingBuffer); 
		
		if(preg_match_all('/^\r\n\+CMTI: \"(SM|ME)\",([0-9]+)\r\n$/', $incomingBuffer, $matches, PREG_SET_ORDER, 0)!=0) {
			$log->LogMessage("Sending the message to dispatch");
		
			$parameters = Array("InstanceId" => $this->InstanceID, "Message" => $incomingBuffer, "Log" => $this->ReadPropertyBoolean("log"));
			IPS_RunScriptEx($this->GetIDForIdent("dispatch"), $parameters);
						
		} else
			$log->LogMessage("The incoming message is not supported!");
    }
	
	private function SendATCommand($Command) {
		if(!$this->EvaluateParent())
			return "ERROR";
		
		$log = new Logging($this->ReadPropertyBoolean("log"), IPS_Getname($this->InstanceID));
		
		$log->LogMessage("Sending command \"".$Command."\" to parent gateway...");
		return $this->SendDataToParent(json_encode(Array("DataID" => "{FC5541DE-14A9-4D5C-A3CF-6C769B8832CA}", "Buffer" => utf8_encode($Command))));
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
