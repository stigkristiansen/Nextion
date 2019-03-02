<?php
declare(strict_types=1);

class DeviceTypeGenericSwitch {
    private static $implementedType = 'SWITCH';
    private static $implementedTraits = [
        'OnOff'
    ];
    
	private static $displayStatusPrefix = false;
    
	//use HelperDeviceType;
    
	public static function getPosition(){
        return 50;
    }
    
	public static function getCaption(){
        return 'Generic Switch';
    }
    
	public static function getTranslations(){
        return [
            'no' => [
                'Generic Switch' => 'Generisk bryter',
                'Variable'       => 'Variabel'
            ]
        ];
    }
	
	public static function getColumns(){
        $columns = [];
        foreach (self::$implementedTraits as $trait) {
            $columns = array_merge($columns, call_user_func('DeviceTrait' . $trait . '::getColumns'));
        }
        return $columns;
    }
}

DeviceTypeRegistry::register('GenericSwitch');

?>