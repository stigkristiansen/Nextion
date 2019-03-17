<?php
declare(strict_types=1);
class DeviceTraitOnOff
{
    const propertyPrefix = 'OnOff';
    use HelperSwitchDevice;
    
	public static function getColumns()
    {
        return [
            [
                'label' => 'Variable',
                'name'  => self::propertyPrefix . 'ID',
                'width' => '200px',
                'add'   => 0,
                'edit'  => [
                    'type' => 'SelectVariable'
                ]
            ]
        ];
    }
    public static function getStatus($configuration)
    {
        return self::getSwitchCompatibility($configuration[self::propertyPrefix . 'ID']);
    }
    public static function getStatusPrefix()
    {
        return 'Switch: ';
    }
    public static function doQuery($configuration){
		IPS_LogMessage('Inside DeviceTraitOnOff::doQuery');
        if (IPS_VariableExists($configuration[self::propertyPrefix . 'ID'])) {
			$command = $configuration['Mapping'];
			$value = self::getSwitchValue($configuration[self::propertyPrefix . 'ID']);
			$command.=".val=".($value?"1":"0");
            return [
				'command' => $command
            ];
        } else {
            return [];
        }
    }
    public static function doExecute($configuration, $Value)
    {
		if (self::switchDevice($configuration[self::propertyPrefix . 'ID'], $Value)) {
			$on = boolval($Value);
			
			return [
				'ids'    => [$configuration['ID']],
				'status' => 'SUCCESS',
				'states' => [
					'on'     => $on,
					'online' => true
				]
			];
		} else {
			return [
				'ids'       => [$configuration['ID']],
				'status'    => 'ERROR',
				'errorCode' => 'deviceTurnedOff'
			];
		}
    }

    public static function getObjectIDs($configuration)
    {
        return [
            $configuration[self::propertyPrefix . 'ID']
        ];
    }
	
	public static function getMappings($configuration) {
		return [
            $configuration['Mapping']
        ];
	}
	
    public static function supportedTraits($configuration)
    {
        return [
            'action.devices.traits.OnOff'
        ];
    }
    public static function supportedCommands()
    {
        return [
            'action.devices.commands.OnOff'
        ];
    }
    public static function getAttributes()
    {
        return [];
    }
}

?>