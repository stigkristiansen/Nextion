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
	

}

DeviceTypeRegistry::register('GenericSwitch');

?>