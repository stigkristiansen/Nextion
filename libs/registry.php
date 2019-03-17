<?php
declare(strict_types=1);

class DeviceTypeRegistry{
    const classPrefix = 'DeviceType';
    const propertyPrefix = 'Device';
	
    private static $supportedDeviceTypes = [];
    
	public static function register(string $deviceType): void {
        //Check if the same service was already registered
        if (in_array($deviceType, self::$supportedDeviceTypes)) {
            throw new Exception('Cannot register deviceType! ' . $deviceType . ' is already registered.');
        }
        //Add to our static array
        self::$supportedDeviceTypes[] = $deviceType;
    }
	
    private $registerProperty = null;
    private $sendDebug = null;
    private $instanceID = 0;
	private $sendCommand = null;
    
	public function __construct(int $instanceID, callable $registerProperty, callable $sendDebug, callable $sendCommand) {
        $this->sendDebug = $sendDebug;
        $this->registerProperty = $registerProperty;
        $this->instanceID = $instanceID;
		$this->sendCommand = $sendCommand;
    }
    
	public function registerProperties(): void {
        //Add all deviceType specific properties
        foreach (self::$supportedDeviceTypes as $actionType) {
            ($this->registerProperty)(self::propertyPrefix . $actionType, '[]');
        }
    }
    
	public function updateProperties(): void {
        $ids = [];
        //Check that all IDs have distinct values and build an id array
        foreach (self::$supportedDeviceTypes as $actionType) {
            $datas = json_decode(IPS_GetProperty($this->instanceID, self::propertyPrefix . $actionType), true);
            foreach ($datas as $data) {
                //Skip over uninitialized zero values
                if ($data['ID'] != '') {
                    if (in_array($data['ID'], $ids)) {
                        throw new Exception('ID has to be unique for all devices');
                    }
                    $ids[] = $data['ID'];
                }
            }
        }
        //Sort array and determine highest value
        rsort($ids);
        //Start with zero
        $highestID = 0;
        //Highest value is first
        if ((count($ids) > 0) && ($ids[0] > 0)) {
            $highestID = $ids[0];
        }
        //Update all properties and ids which are currently empty
        $wasChanged = false;
        foreach (self::$supportedDeviceTypes as $actionType) {
            $wasUpdated = false;
            $datas = json_decode(IPS_GetProperty($this->instanceID, self::propertyPrefix . $actionType), true);
            foreach ($datas as &$data) {
                if ($data['ID'] == '') {
                    $data['ID'] = (string) (++$highestID);
                    $wasChanged = true;
                    $wasUpdated = true;
                }
            }
            if ($wasUpdated) {
                IPS_SetProperty($this->instanceID, self::propertyPrefix . $actionType, json_encode($datas));
            }
        }
        //This is dangerous. We need to be sure that we do not end in an endless loop!
        if ($wasChanged) {
            //Save. This will start a recursion. We need to be careful, that the recursion stops after this.
            IPS_ApplyChanges ($this->instanceID);
        }
    }
	
	public function getObjectIDs(){ 
        $result = [];
        // Add all variable IDs of all devices
        foreach (self::$supportedDeviceTypes as $deviceType) {
            $configurations = json_decode(IPS_GetProperty($this->instanceID, self::propertyPrefix . $deviceType), true);
            foreach ($configurations as $configuration) {
                $result = array_unique(array_merge($result, call_user_func(self::classPrefix . $deviceType . '::getObjectIDs', $configuration)));
            }
        }
        return $result;
    }
	
	 public function ReportState($variableUpdates){
		IPS_LogMessage('ReportState: ',"Inside Registry::ReportState"); 
		IPS_LogMessage('ReportState: ',"Variable(s) to update is/are: ". json_encode($variableUpdates));  
        $states = [];
        foreach (self::$supportedDeviceTypes as $deviceType) {
            $configurations = json_decode(IPS_GetProperty($this->instanceID, self::propertyPrefix . $deviceType), true);
            foreach ($configurations as $configuration) {
                $variableIDs = call_user_func(self::classPrefix . $deviceType . '::getObjectIDs', $configuration);
				IPS_LogMessage("ReportState","Trying to match: ".json_encode($variableIDs));
                if (count(array_intersect($variableUpdates, $variableIDs)) > 0) {
					IPS_LogMessage("ReportState","It was a match");
					IPS_LogMessage('ReportState','Calling '.self::classPrefix . $deviceType . '::doQuery');
                    $queryResult = call_user_func(self::classPrefix . $deviceType . '::doQuery', $configuration);
					IPS_LogMessage("ReportState","::doQuery returned: ".json_encode($queryResult));
                    if (!isset($queryResult['status']) || ($queryResult['status'] != 'ERROR')) {
						IPS_LogMessage("ReportState","Getting command to send...");
                        $states[$configuration['ID']] = call_user_func(self::classPrefix . $deviceType . '::doQuery', $configuration);
                    }
                }
            }
        }

		IPS_logMessage("ReportState","States: ".json_encode($states));
		
		foreach($states as $state) {
			($this->sendCommand)($state['command']);
		}
	 }
	 
	 public function doExecuteDevice($deviceID, $deviceCommand, $deviceParams)
    {
        //Add all deviceType specific properties
        foreach (self::$supportedDeviceTypes as $deviceType) {
            $configurations = json_decode(IPS_GetProperty($this->instanceID, self::propertyPrefix . $deviceType), true);
            foreach ($configurations as $configuration) {
                if ($configuration['ID'] == $deviceID) { // Må fikses vi har variabelID 
                    return call_user_func(self::classPrefix . $deviceType . '::doExecute', $configuration, $deviceCommand, $deviceParams, $emulateStatus);
                }
            }
        }
        //Return an device not found error
        return [
            'ids'       => [$deviceID],
            'status'    => 'ERROR',
            'errorCode' => 'deviceNotFound'
        ];
    }
	 
	public function ProcessRequest($requests) {
		IPS_LogMessage('ProcessRequest', "Inside Registry::ProcessRequest"); 
		IPS_LogMessage('ProcessRequest', 'Requests: '.json_encode($requests));
		$variableUpdates = [];
		foreach($requests as $request){
			$validRequest = false;
			foreach (self::$supportedDeviceTypes as $deviceType) {
				IPS_LogMessage('ProcessRequest','Searching through all configuration');
				IPS_LogMessage('ProcessRequest','The mapping to search for is: '.$request['mapping']);
				$configurations = json_decode(IPS_GetProperty($this->instanceID, self::propertyPrefix . $deviceType), true);
				foreach ($configurations as $configuration) {
					IPS_LogMessage('ProcessRequest','Got the configuration: '.json_encode($configuration));
					$mapping = call_user_func(self::classPrefix . $deviceType . '::getMappings', $configuration);
					IPS_LogMessage('ProcessRequest','Comparing to: '.$mapping[0]);
					if(strtoupper($mapping[0])==strtoupper($request['mapping'])) {
						IPS_LogMessage('ProcessRequest','Found device');
						$validRequest = true;
						switch(strtoupper($request['command'])){
							case 'GETVALUE':
								IPS_LogMessage('ProcessRequest','Processing a GetValue');
								IPS_LogMessage('ProcessRequest','Calling '.self::classPrefix . $deviceType . '::doQuery');
								$queryResult = call_user_func(self::classPrefix . $deviceType . '::doQuery', $configuration);
								IPS_LogMessage('ProcessRequest','doQuery returned: '. json_encode($queryResult));
								if (!isset($queryResult['status']) || ($queryResult['status'] != 'ERROR')) {
									($this->sendCommand)($queryResult['command']);
								} else
									throw new Exception('Invalid device!');
								break;
							case 'SETVALUE':
								IPS_LogMessage('ProcessRequest','Processing a SetValue');
								$queryResult = call_user_func(self::classPrefix . $deviceType . '::doExecute', $configuration, $request['value']);
								break;
							default:
								throw new Exception('Unsupported command received from Nextion');
						}
						
						break;
						
					}
				}
			}
			
			if(!$validRequest)
				throw new Exception('Invalid request received from Nextion');
			
		}
		
		
	}
	
	public function getConfigurationForm(): array {
        $form = [];
        $sortedDeviceTypes = self::$supportedDeviceTypes;
        uasort($sortedDeviceTypes, function ($a, $b) {
            $posA = call_user_func(self::classPrefix . $a . '::getPosition');
            $posB = call_user_func(self::classPrefix . $b . '::getPosition');
            return ($posA < $posB) ? -1 : 1;
        });
		
        foreach ($sortedDeviceTypes as $deviceType) {
            $columns = [
                [
                    'label' => 'ID',
                    'name'  => 'ID',
                    'width' => '35px',
                    'add'   => '',
                    'save'  => true
                ],
                [
                    'label' => 'Name',
                    'name'  => 'Name',
                    'width' => 'auto',
                    'add'   => '',
                    'edit'  => [
                        'type' => 'ValidationTextBox'
                    ]
                ], //We will insert the custom columns here
                [
					'label' => 'Nextion object mapping',
                    'name'  => 'Mapping',
                    'width' => 'auto',
                    'add'   => '',
                    'edit'  => [
                        'type' => 'ValidationTextBox'
                    ]
				],
				[
                    'label' => 'Status',
                    'name'  => 'Status',
                    'width' => '200px',
                    'add'   => '-'
                ]
            ];
			
            array_splice($columns, 2, 0, call_user_func(self::classPrefix . $deviceType . '::getColumns'));
            $values = [];
            $configurations = json_decode(IPS_GetProperty($this->instanceID, self::propertyPrefix . $deviceType), true);
            foreach ($configurations as $configuration) {
                $values[] = [
                    'Status' => call_user_func(self::classPrefix . $deviceType . '::getStatus', $configuration)
                ];
            }
            $form[] = [
                'type'    => 'ExpansionPanel',
                'caption' => call_user_func(self::classPrefix . $deviceType . '::getCaption'),
                'items'   => [[
                    'type'     => 'List',
                    'name'     => self::propertyPrefix . $deviceType,
                    'rowCount' => 5,
                    'add'      => true,
                    'delete'   => true,
                    'sort'     => [
                        'column'    => 'Name',
                        'direction' => 'ascending'
                    ],
                    'columns' => $columns,
                    'values'  => $values
                ]]
            ];
        }
        return $form;
    }
	
	
}
	
?>