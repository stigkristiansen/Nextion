<?

require_once(__DIR__ . "/../libs/protocols.php");  
require_once(__DIR__ . "/../libs/logging.php");


class NextionDisplay extends IPSModule {
    private $registry = null;
	

	
	public function Create() {
        parent::Create();
        $this->RequireParent("{99DC304B-1DE7-4F4E-8CC1-F949ADA6FAF3}");
		
		$this->RegisterPropertyBoolean ("log", false );
		
		// register kernel messages
		$this->RegisterMessage(0, IPS_KERNELMESSAGE);
		


    }

    public function ApplyChanges() {
        parent::ApplyChanges();
        
        //$this->RegisterVariableBoolean( "Status", "Status", "", false );
		
		//$log = new Logging($this->ReadPropertyBoolean("log"), IPS_Getname($this->InstanceID));
		
		//$log->LogMessage("ReceiveDataFilter set to ".$receiveFilter);
		
    }
	
    public function ReceiveData($JSONString) {
		$data = json_decode($JSONString);
		$message = utf8_decode($data->Buffer);
		
		$log = new Logging($this->ReadPropertyBoolean("log"), IPS_Getname($this->InstanceID));
		
		$log->LogMessage("Received ".$message);
		
		if($data->DataID!="{63642483-512D-44D0-AD97-18FB03CD2503}") {
			$log->LogMessage("Got data from unsupportet parent! (unsupported GUID in DataID: "+$data->DataID+")");
			return;
		}
			
		$log->LogMessage("Analyzing the message...");
		
		
    }
	
	public function MessageSink($TimeStamp, $SenderID, $Message, $Data)
	{
		if ($Message == IPS_KERNELMESSAGE && $Data[0] == KR_READY) {
				// Send command to switch to welcome page
				
		}
	}
}

?>
