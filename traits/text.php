<?php
declare(strict_types=1);
class DeviceTraitText
{
    const propertyPrefix = 'Text';
    use HelperTextDevice;
    
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
        return self::getTextCompatibility($configuration[self::propertyPrefix . 'ID']);
    }
    public static function getStatusPrefix()
    {
        return 'Text: ';
    }
    public static function doQuery($configuration)
    {
		IPS_LogMessage('Inside DeviceTraitText::doQuery');
        if (IPS_VariableExists($configuration[self::propertyPrefix . 'ID'])) {
			$command = $configuration['Mapping'];
			$value = self::getTextValue($configuration[self::propertyPrefix . 'ID']);
			$command.=".txt=".$value;
            return [
				'command' => $command
            ];
        } else {
            return [];
        }
    }
    public static function doExecute($configuration, $Value)
    {
		if (self::changeText($configuration[self::propertyPrefix . 'ID'], $Value)) {
			$text = $Value;
			
			return [
				'ids'    => [$configuration['ID']],
				'status' => 'SUCCESS',
				'states' => [
					'text<'     => $text,
					'online' => true
				]
			];
		} else {
			return [
				'ids'       => [$configuration['ID']],
				'status'    => 'ERROR',
				'errorCode' => 'deviceNotResponding'
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
            'action.devices.traits.Text'
        ];
    }
    public static function supportedCommands()
    {
        return [
            'action.devices.commands.Text'
        ];
    }
    public static function getAttributes()
    {
        return [];
    }
}

?>