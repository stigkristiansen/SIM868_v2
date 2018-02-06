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
        
        //$this->RegisterVariableString("LastSendt", "LastSendt");
        //$this->RegisterVariableString("Queue", "Queue");
		//$this->RegisterVariableString("Buffer", "Buffer");
		//$this->RegisterVariableString("InProgress", "InProgress");
        
        //IPS_SetHidden($this->GetIDForIdent('LastSendt'), true);
        //IPS_SetHidden($this->GetIDForIdent('Queue'), true);
		//IPS_SetHidden($this->GetIDForIdent('Buffer'), true);
		//IPS_SetHidden($this->GetIDForIdent('InProgress'), true);
		
		// Create Script
    }
	
	public function GetSMSCommands(){
		return $this->ReadPropertyString("SMSCommands");
	}

    public function ReceiveData($JSONString) {
		$incomingData = json_decode($JSONString);
		$incomingBuffer = utf8_decode($incomingData->Buffer);
				
		$log = new Logging($this->ReadPropertyBoolean("log"), IPS_Getname($this->InstanceID));
		$log->LogMessage("Received data: ".$incomingBuffer); 
		
    }
	
	private function SendATCommand($Command) {
		$log = new Logging($this->ReadPropertyBoolean("log"), IPS_Getname($this->InstanceID));
		
		$log->LogMessage("Sending command \"".$Command."\"to parent gateway...");
		$this->SendDataToParent(json_encode(Array("DataID" => "{FC5541DE-14A9-4D5C-A3CF-6C769B8832CA}", "Buffer" => $Command)));
	
	}
	
	Public function SendCommand(string $Command) {
		$this->SendATCommand($Command);
	}
	
		
	private function Lock($Ident){
		$log = new Logging($this->ReadPropertyBoolean("log"), IPS_Getname($this->InstanceID));
		for ($i = 0; $i < 100; $i++){
			if (IPS_SemaphoreEnter($this->BuildSemaphoreName($Ident), 1)){
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

    private function Unlock($Ident)
    {
        IPS_SemaphoreLeave($this->BuildSemaphoreName($Ident));
		$log = new Logging($this->ReadPropertyBoolean("log"), IPS_Getname($this->InstanceID));
		$log->LogMessage("Semaphore ".$Ident." is cleared");
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
