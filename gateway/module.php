<?

require_once(__DIR__ . "/../libs/logging.php");
require_once(__DIR__ . "/../libs/protocols.php");
require_once(__DIR__ . "/../libs/registry.php");
include_once(__DIR__ . "/../helper/autoload.php");
include_once(__DIR__ . "/../traits/autoload.php");
include_once(__DIR__ . "/../types/autoload.php");



class NextionGateway extends IPSModule {
	const EndOfMessage = "\xFF\xFF\xFF";
	
    public function __construct($InstanceID)
    {
        parent::__construct($InstanceID);
        $this->registry = new DeviceTypeRegistry(
            $this->InstanceID,
            function ($Name, $Value) {
                $this->RegisterPropertyString($Name, $Value);
            },
            function ($Message, $Data, $Format) {
                $this->SendDebug($Message, $Data, $Format);
            },
			function ($Command) {
                $this->SendCommand($Command);
            }
        );
    }
    public function Create()
    {
        parent::Create();
        $this->RequireParent("{6DC3D946-0D31-450F-A8C6-C42DB8D7D4F1}");
        
        $this->RegisterPropertyBoolean ("log", false );
		
		$this->RegisterTimer('ReportStateTimer', 0, 'NHMI_ReportState($_IPS[\'TARGET\']);');
		$this->RegisterTimer('ProcessRequestTimer', 0, 'NHMI_ProcessRequests($_IPS[\'TARGET\']);');
		
		
		$this->registry->registerProperties();
    }

    public function ApplyChanges()
    {
        parent::ApplyChanges();
		
		$this->registry->updateProperties();
		
		$objectIDs = $this->registry->getObjectIDs();
				
		// Recreate references (version >5.1)
		if (method_exists($this, 'GetReferenceList')) {
            $refs = $this->GetReferenceList();
            foreach ($refs as $ref) {
                $this->UnregisterReference($ref);
            }
            foreach ($objectIDs as $id) {
                $this->RegisterReference($id);
            }
        }
		
		// Recreate subscription to updates
		foreach ($this->GetMessageList() as $variableID => $messages) {
            $this->UnregisterMessage($variableID, 10603 /* VM_UPDATE */);
        }
        foreach ($objectIDs as $variableID) {
            if (IPS_VariableExists($variableID)) {
                $this->RegisterMessage($variableID, 10603 /* VM_UPDATE */);
            }
        }
		
		$this->SetBuffer("SerialBuffer", "");
    }
	
	public function ProcessRequests(){
		$this->SetTimerInterval('ProcessRequestTimer', 0);
		
		$log = new Logging($this->ReadPropertyBoolean("log"), IPS_Getname($this->InstanceID));
		
		$log->LogMessage("ProcessRequest timer was triggered");
		
        $requests = $this->GetBuffer('Requests');
		
		if ($requests != '') {
			$this->SetBuffer('Requests', '');
            $this->registry->ProcessRequest(json_decode($requests, true));
        }
		
	}
	
    public function ReportState(){
		$this->SetTimerInterval('ReportStateTimer', 0);
		
		$log = new Logging($this->ReadPropertyBoolean("log"), IPS_Getname($this->InstanceID));
		
		$log->LogMessage("ReportState timer was triggered");
		
        $variableUpdates = $this->GetBuffer('VariableUpdates');
		$states = [];
        if ($variableUpdates != '') {
            $this->SetBuffer('VariableUpdates', '');
			$states = $this->registry->ReportState(json_decode($variableUpdates, true));
        }
    }

		
	public function MessageSink($timestamp, $senderID, $messageID, $data){
		$log = new Logging($this->ReadPropertyBoolean("log"), IPS_Getname($this->InstanceID));
		
		$log->LogMessage("Received ".(string)$messageID." from ".(string)$senderId);
		
        if ($messageID == 10603) {
            $currentVariableUpdatesString = $this->GetBuffer('VariableUpdates');
            $currentVariableUpdates = ($currentVariableUpdatesString == '') ? [] : json_decode($currentVariableUpdatesString, true);
            $currentVariableUpdates[] = $senderID;
            $this->SetBuffer('VariableUpdates', json_encode($currentVariableUpdates));
            $this->SetTimerInterval('ReportStateTimer', 500);
			
			$log->LogMessage("Variable updates: ".json_encode($currentVariableUpdates));
        }
    }
    
    public function ReceiveData($JSONString) {
		$incomingData = json_decode($JSONString);
		$incomingBuffer = utf8_decode($incomingData->Buffer);
			
		$log = new Logging($this->ReadPropertyBoolean("log"), IPS_Getname($this->InstanceID));
		$log->LogMessage("Incoming from serial: ".$incomingBuffer);
		
		if (!$this->Lock("SerialBuffer")) {
			$log->LogMessage("Lock \"SerialBuffer\" is already locked. Aborting message handling!");
			return false; 
		} else
			$log->LogMessage("Lock \"SerialBuffer\" is locked");

		$data = $this->GetBuffer("SerialBuffer");
		$data .= $incomingBuffer;
		$log->LogMessage("New buffer is: ".$data);
		
		$log->LogMessage("Searching for a complete message...");	
		
		$foundMessage = false;
		$arr = str_split($data);
		$max = sizeof($arr);
					
		$message = "";
		for($i=0;$i<$max-2;$i++) {
			$test = $arr[$i].$arr[$i+1].$arr[$i+2];
			if($test==self::EndOfMessage) {
				$foundMessage = true;
				break;
			}
			$message .= $arr[$i];
		}
		
		if(strlen($message+3)<strlen($data))
				$buffer = substr($data, $i+3);
			else
				$buffer = "";
	
		if($foundMessage) {
			$log->LogMessage("Found message: ".$message);

			$this->SetBuffer("SerialBuffer", $buffer);
			$log->LogMessage(strlen($buffer>0)?"New buffer is ".$buffer:"Buffer is reset");
			$this->Unlock("SerialBuffer");
			
			$log->LogMessage("Analyzing the incoming message...");
			if(strlen($message)>1) { //length of 1 indicates a return code 
				$currentRequestsString = $this->GetBuffer('Requests');
				$currentRequests = ($currentRequestsString == '') ? [] : json_decode($currentRequestsString, true);
				$currentRequests[] = json_decode($message, true);	
				
				$this->SetBuffer('Requests', json_encode($currentRequests));
										
				$this->SetTimerInterval('ProcessRequestTimer', 500);
			
				
			} else {
				$returnCode = ord($message);
				$log->LogMessage("The message received was a return code");
				$log->LogMessage("The return code was 0x".strtoupper(str_pad(dechex($returnCode),2,'0',STR_PAD_LEFT)));
				
				if (!$this->Lock("ReturnCode")) {
					$log->LogMessage("\"ReturnCode\" is already locked. Aborting message handling!");
				} else
					$log->LogMessage("Lock \"ReturnCode\" is locked");
				
				$this->SetBuffer("ReturnCode", $returnCode);
				$log->LogMessage("Updated \"ReturnCode\" to received return code");
				$this->Unlock("ReturnCode");
			}
		} else {
			$log->LogMessage("No complete message yet...");
			
			$this->SetBuffer("SerialBuffer", $data);
			$log->LogMessage("Buffer is saved");
		}
    }
	
	public function SendCommand(string $Command) {
		$log = new Logging($this->ReadPropertyBoolean("log"), IPS_Getname($this->InstanceID));
		
		$log->LogMessage("Sending command \"".$Command."\"");
		
		if (!$this->Lock("ReturnCode")) {
			$log->LogMessage("\"ReturnCode\" is already locked. Aborting SendCommand!");
			return false; 
		} else
			$log->LogMessage("Lock \"ReturnCode\" is locked");

		
		$this->SetBuffer("ReturnCode", "ValueNotSet");
		$log->LogMessage("ReturnCode is set to \"Not Set\"");
		
		$this->Unlock("ReturnCode");
		
		

		SPRT_SendText(IPS_GetInstanceParentId($this->InstanceID), $Command.self::EndOfMessage);
		
		$log->LogMessage("The command was sent");
		
		$loopCount = 1;
		$returnCode = $this->GetBuffer("ReturnCode");
		$log->LogMessage("SendCommand is waiting for a return code...");
		while ($returnCode=="ValueNotSet" && $loopCount < 100) {
			IPS_Sleep(mt_rand(1, 5));
			
			$returnCode = $this->GetBuffer("ReturnCode");
			$loopCount++;
		}
		
		if($loopCount==100) {
			$log->LogMessage("Waiting for a return code timed out in SendCommand");
			return false;
		} 
					
		$log->LogMessage("The return code was received by SendCommand");
		return $returnCode;
		
	}
	
	public function GetConfigurationForm() {
        
        $logging = [
			[
				'type' => 'CheckBox',
				'name' => 'log',
				'caption' => 'Enable logging'
			]
		];
		
		$deviceTypes = $this->registry->getConfigurationForm();
        return json_encode(['elements'      => array_merge($deviceTypes, $logging)]); //,
                            
							//'translations'  => $this->registry->getTranslations()]);
    }
 
    private function Lock($ident){
        for ($i = 0; $i < 100; $i++){
            if (IPS_SemaphoreEnter("NHMI_".(string)$this->InstanceID.(string)$ident, 1)){
                return true;
            } else {
                $log = new Logging($this->ReadPropertyBoolean("log"), IPS_Getname($this->InstanceID));
				$log->LogMessage("Waiting for lock \"".$ident."\"");
				IPS_Sleep(mt_rand(1, 5));
            }
        }
        return false;
    }

    private function Unlock($ident){
        IPS_SemaphoreLeave("NHMI_".(string)$this->InstanceID.(string)$ident);
		$log = new Logging($this->ReadPropertyBoolean("log"), IPS_Getname($this->InstanceID));
		$log->LogMessage("Lock \"".$ident."\" is released");
    }
}

?>
