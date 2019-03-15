<?php
declare(strict_types=1);

trait HelperTextDevice {
    private static function getTextCompatibility($variableID)
    {
        if (!IPS_VariableExists($variableID)) {
            return 'Missing';
        }
        $targetVariable = IPS_GetVariable($variableID);
        //if ($targetVariable['VariableType'] != 0 /* Boolean */) {
        //    return 'Bool required';
        //}
        if ($targetVariable['VariableCustomAction'] != 0) {
            $profileAction = $targetVariable['VariableCustomAction'];
        } else {
            $profileAction = $targetVariable['VariableAction'];
        }
        if (!($profileAction > 10000)) {
            return 'Action required';
        }
        return 'OK';
    }
    private static function getTextValue($variableID){
        return GetValue($variableID);
    }
	
    private static function changeText($variableID, $value){
		IPS_LogMessage("ChangeDevice","Changing text to ".(string)$value);
		
        if (!IPS_VariableExists($variableID)) {
            return false;
        }
        $targetVariable = IPS_GetVariable($variableID);
        if ($targetVariable['VariableCustomProfile'] != '') {
            $profileName = $targetVariable['VariableCustomProfile'];
        } else {
            $profileName = $targetVariable['VariableProfile'];
        }
        if ($targetVariable['VariableCustomAction'] != 0) {
            $profileAction = $targetVariable['VariableCustomAction'];
        } else {
            $profileAction = $targetVariable['VariableAction'];
        }
        if ($profileAction < 10000) {
            return false;
        }
        if ($targetVariable['VariableType'] == 0 /* Boolean */) {
            $value = boolval($value);
        } else {
            return false;
        }
		
		IPS_LogMessage("ChangeDevice","Changing text to ".(string)$value);
		
        // Revert value for reversed profile
        if (preg_match('/\.Reversed$/', $profileName)) {
            $value = !$value;
        }
        if (IPS_InstanceExists($profileAction)) {
            IPS_RunScriptText('IPS_RequestAction(' . var_export($profileAction, true) . ', ' . var_export(IPS_GetObject($variableID)['ObjectIdent'], true) . ', ' . var_export($value, true) . ');');
        } elseif (IPS_ScriptExists($profileAction)) {
            IPS_RunScriptEx($profileAction, ['VARIABLE' => $variableID, 'VALUE' => $value, 'SENDER' => 'VoiceControl']);
        } else {
            return false;
        }
        return true;
    }
}
?>