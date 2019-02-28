<?

require_once(__DIR__ . "/../libs/logging.php");
require_once(__DIR__ . "/../libs/protocols.php");

class NextionGateway extends IPSModule
{
    
    public function Create()
    {
        parent::Create();
        $this->RequireParent("{6DC3D946-0D31-450F-A8C6-C42DB8D7D4F1}");
        
        $this->RegisterPropertyBoolean ("log", false );
    }

    public function ApplyChanges()
    {
        parent::ApplyChanges();
    }
    
    public function ReceiveData($JSONString) {
		$incomingData = json_decode($JSONString);
		$incomingBuffer = utf8_decode($incomingData->Buffer);
			
		$log = new Logging($this->ReadPropertyBoolean("log"), IPS_Getname($this->InstanceID));
		$log->LogMessage("Incoming from serial: ".$incomingBuffer);
		
		if (!$this->Lock("ReceiveLock")) {
			$log->LogMessage("Buffer is already locked. Aborting message handling!");
			return false; 
		} else
			$log->LogMessage("Buffer is locked");

		$data = $this->GetBuffer("SerialBuffer");
		$data .= $incomingBuffer;
		$log->LogMessage("New buffer is: ".$data);
		
		$log->LogMessage("Searching for a complete message...");	
		
		$endOfMessage = chr(0xFF).chr(0xFF).chr(0xFF);
		$foundMessage = false;
		$arr = str_split($data);
		$max = sizeof($arr);
					
		$message = "";
		for($i=0;$i<$max-2;$i++) {
			$test = $arr[$i].$arr[$i+1].$arr[$i+2];
			if($test==$endOfMessage) {
				$foundMessage = true;
				break;
			}
			$message .= $arr[$i];
		}
	
		if($foundMessage) {
			$log->LogMessage("Found message: ".$message);

			$this->SetBuffer("SerialBuffer", "");
			$log->LogMessage("Buffer is reset");
			
			if(strlen($message)>1) { //length of 1 indicates a return code 
				try{
					$log->LogMessage("Sending the message to the connected child");
					$this->SendDataToChildren(json_encode(Array("DataID" => "{63642483-512D-44D0-AD97-18FB03CD2503}", "Buffer" => $message)));
					
				}catch(Exeption $ex){
					$log->LogMessageError("Failed to send message to the child. Error: ".$ex->getMessage());
					$this->Unlock("ReceiveLock");
			
					return false;
				}
			} else {
				$returnCode = ord($message);
				$log->LogMessage("Received a return code");
				$log->LogMessage("The return code was: 0x".strtoupper(str_pad(dechex($returnCode),2,'0',STR_PAD_LEFT)));
				
				if (!$this->Lock("ReturnCodeLock")) {
					$log->LogMessage("ReturnCode is already locked. Aborting message handling!");
					return false; 
				} else
					$log->LogMessage("ReturnCode is locked");
				
				$this->SetBuffer("ReturnCode", $returnCode);
				
				$this->Unlock("ReturnCodeLock");
			}
		} else {
			$log->LogMessage("No complete message yet...");
			
			$this->SetBuffer("SerialBuffer", $data);
			$log->LogMessage("Buffer is saved");
		}
				
		
		return true;
    }
	
	public function SendCommand(string $Command) {
		$log = new Logging($this->ReadPropertyBoolean("log"), IPS_Getname($this->InstanceID));
		
		$log->LogMessage("Sending command \"".$Command."\"");
		
		if (!$this->Lock("ReturnCodeLock")) {
			$log->LogMessage("ReturnCode is already locked. Aborting SendCommand!");
			return false; 
		} else
			$log->LogMessage("ReturnCode is locked");

		$this->SetBuffer("ReturnCode", "ValueNotSet");
		
		$this->Unlock("ReturnCodeLock");
		
		$endOfMessage = "\xFF\xFF\xFF";

		SPRT_SendText(IPS_GetInstanceParentId($this->InstanceID), $Command.$endOfMessage);
		
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
			$log->LogMessage("Waiting for return code timed out in SendCommand");
			return false;
		} 
					
		$log->LogMessage("The return code was received by SendCommand");
		return $returnCode;
		
	}
 
    private function Lock($ident){
        for ($i = 0; $i < 100; $i++){
            if (IPS_SemaphoreEnter("NHMI_".(string)$this->InstanceID.(string)$ident, 1)){
                return true;
            } else {
                $log = new Logging($this->ReadPropertyBoolean("log"), IPS_Getname($this->InstanceID));
				$log->LogMessage("Waiting for lock");
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
