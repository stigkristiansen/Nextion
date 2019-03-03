<?php
declare(strict_types=1);

class DeviceTypeGenericSwitch {
    private static $implementedType = 'SWITCH';
    private static $implementedTraits = [
        'OnOff'
    ];
    
	private static $displayStatusPrefix = false;
    
	use HelperDeviceType;
    
	public static function getPosition(){
        return 50;
    }
    
	public static function getCaption(){
        return 'Dual-state button';
    }
    
	public static function getTranslations(){
        return [
            'no' => [
                'Generic Switch' => 'Flip-bryter',
                'Variable'       => 'Variabel'
            ]
        ];
    }
}

DeviceTypeRegistry::register('GenericSwitch');

?>