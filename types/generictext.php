<?php
declare(strict_types=1);

class DeviceTypeGenericSwitch {
    private static $implementedType = 'TEXT';
    private static $implementedTraits = [
        'Text'
    ];
	    
	private static $displayStatusPrefix = false;
    
	use HelperDeviceType;
    
	public static function getPosition(){
        return 50;
    }
    
	public static function getCaption(){
        return 'Text';
    }
	

}

DeviceTypeRegistry::register('GenericText');

?>