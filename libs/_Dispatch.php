<?

$moduleInstanceId = $_IPS['InstanceId'];
$message = $_IPS['Message'];
$log = $_IPS['Log'];

$moduleName = IPS_GetObject($moduleInstanceId)['ObjectName'];

if(preg_match_all('/\+CMTI: \"(SM|ME)\",([0-9]+)$/', $arr[$i], $matches, PREG_SET_ORDER, 0)!=0) {
	IPS_LogMessage(" Processing incoming message...");
	$readCommand = "AT+CMGR=".$matches[0][2];
	$deleteCommand = "AT+CMGD=".$matches[0][2];
	LogMessage("Read command is: ".$readCommand);
	LogMessage("Delete command is: ".$deleteCommand);
	SIM868SMSv2_SendCommand($moduleInstanceId, "AT+CMGF=1");
	$smsMessage = SIM868SMSv2_SendCommand($moduleInstanceId, $readCommand);
	SIM868SMSv2_SendCommand($moduleInstanceId, $deleteCommand);
	
	LogMessage("Retrieved the SMS: ". $smsMessage);
} else
	LogMessage("Unkonwn message");	

function LogMessage($Message) {
	if($log)
		IPS_Message($moduleName, $Message);
}
	
?>
	
