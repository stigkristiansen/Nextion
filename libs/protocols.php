<?

function GetParameter($Parameter, $Message) {
	$arr = explode (";", $Message);
	$max = sizeof($arr);

	for($i=0;$i<$max;$i++){
	   if(stripos($arr[$i], $Parameter."=")!==false){
			break;
	   }
	}

	if($i<$max){
		$startPos = stripos($arr[$i], "=")+1;
		$value = substr($arr[$i], $startPos);
		return $value;
	} else {
	   return "";
	}
}

function ConvertTemperature($Value) {
	$temp = (hexdec($Value) & 0x00FF)/10;
	if(($Value & 0x8000)==0x8000)
		$temp *= -1;
	
	return $temp;
}

function SupportedMessage($Message) {
	$data = explode(";", $Message);
	$protocol = $data[2];
	
	switch(strtolower($protocol)) {
		case "fineoffset":
			return true;
			break;
		case "oregon temphygro":
			return true;
			break;
		case "newkaku":
			return true;
			break;
	}
	
	return false;
}



?>
