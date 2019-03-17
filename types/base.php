<?php
declare(strict_types=1);

trait HelperDeviceTypeColumns
{
    public static function getColumns()
    {
        $columns = [];
        foreach (self::$implementedTraits as $trait) {
            $columns = array_merge($columns, call_user_func('DeviceTrait' . $trait . '::getColumns'));
        }
        return $columns;
    }
}
trait HelperDeviceTypeStatus
{
    public static function getStatus($configuration)
    {
        if ($configuration['Name'] == '') {
            return 'No name';
        }
        foreach (self::$implementedTraits as $trait) {
            $status = call_user_func('DeviceTrait' . $trait . '::getStatus', $configuration);
            if ($status != 'OK') {
                if (self::$displayStatusPrefix) {
                    return call_user_func('DeviceTrait' . $trait . '::getStatusPrefix') . $status;
                } else {
                    return $status;
                }
            }
        }
        return 'OK';
    }
}
trait HelperDeviceTypeSync
{
    public static function doSync($configuration)
    {
        $sync = [
            'id'     => strval($configuration['ID']),
            'type'   => 'action.devices.types.' . self::$implementedType,
            'traits' => [
            ],
            'name' => [
                'name' => $configuration['Name']
            ],
            'willReportState' => false
        ];
        $attributes = [];
        foreach (self::$implementedTraits as $trait) {
            $traits = call_user_func('DeviceTrait' . $trait . '::supportedTraits', $configuration);
            if (count($traits) > 0) {
                $sync['traits'] = array_merge($sync['traits'], call_user_func('DeviceTrait' . $trait . '::supportedTraits', $configuration));
                $attributes = array_merge($attributes, call_user_func('DeviceTrait' . $trait . '::getAttributes'));
            }
        }
        if (count($attributes) > 0) {
            $sync['attributes'] = $attributes;
        }
        return $sync;
    }
}
trait HelperDeviceTypeQuery
{
    public static function doQuery($configuration){
        $query = [];
        foreach (self::$implementedTraits as $trait) {
			IPS_LogMessage('HelperDeviceType','Inside HelperDeviceType::doQuery');
			IPS_LogMessage('HelperDeviceType','Calling '.'DeviceTrait' . $trait . '::doQuery');
			$result = call_user_func('DeviceTrait' . $trait . '::doQuery', $configuration);
			IPS_LogMessage('HelperDeviceType','DeviceTrait' . $trait . '::doQuery returned: ' . $result);
            $query = array_merge($query, $result);
        }
        $query['online'] = count($query) > 0;
        return $query;
    }
}
trait HelperDeviceTypeExecute
{
    public static function doExecute($configuration, $Value)
    {
        foreach (self::$implementedTraits as $trait) {
            return call_user_func('DeviceTrait' . $trait . '::doExecute', $configuration, $Value);
        }
    }
}
trait HelperDeviceTypeGetVariables
{
    public static function getObjectIDs($configuration)
    {
        $result = [];
        foreach (self::$implementedTraits as $trait) {
            $result = array_unique(array_merge($result, call_user_func('DeviceTrait' . $trait . '::getObjectIDs', $configuration)));
        }
        return $result;
    }
}
trait HelperDeviceTypeMappings
{
    public static function getMappings($configuration)
    {
        $result = [];
        foreach (self::$implementedTraits as $trait) {
            $result = array_unique(array_merge($result, call_user_func('DeviceTrait' . $trait . '::getMappings', $configuration)));
        }
        return $result;
    }
}
trait HelperDeviceType
{
    use HelperDeviceTypeGetVariables;
	use HelperDeviceTypeMappings;
    use HelperDeviceTypeColumns;
    use HelperDeviceTypeStatus;
    use HelperDeviceTypeSync;
    use HelperDeviceTypeQuery;
    use HelperDeviceTypeExecute;
}

?>