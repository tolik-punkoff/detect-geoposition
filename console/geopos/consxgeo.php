<?php
//Переделано из примера 
//(L)[8^12] v 0.8 console
//Область подключения внешних скриптов
include("SxGeoCon.php"); // Подключаем SxGeo.php класс
// --------- Область глобальных переменных ---------

// Создаем объект
// Первый параметр - имя файла с базой (используется оригинальная бинарная база SxGeo.dat)
// Второй параметр - режим работы: 
//     SXGEO_FILE   (работа с файлом базы, режим по умолчанию); 
//     SXGEO_BATCH (пакетная обработка, увеличивает скорость при обработке множества IP за раз)
//     SXGEO_MEMORY (кэширование БД в памяти, еще увеличивает скорость пакетной обработки, но требует больше памяти)
$SxGeo = "";
//$SxGeo = new SxGeo('SxGeoCity.dat', SXGEO_BATCH | SXGEO_MEMORY); // Самый производительный режим, если нужно обработать много IP за раз

//регулярное выражение для IP
$ip_pattern="#(?:(?:25[0-5]|2[0-4][0-9]|[01]?[0-9][0-9]?)\.){3}(?:25[0-5]|2[0-4][0-9]|[01]?[0-9][0-9]?)#";

//список зарезервированных диапазонов IP-адресов
$spec_list = array(										
					array ("0.0.0.0","0.255.255.255", "Current network"),
					array ("255.255.255.255","255.255.255.255", "Broadcast"),
					array ("255.0.0.0","255.255.255.255", "Reserved by the IETF, broadcast"),
					array ("10.0.0.0","10.255.255.255", "Private network"),
					array ("100.64.0.0","100.127.255.255", "Shared Address Space"),
					array ("127.0.0.0","127.255.255.255", "Loopback"),
					array ("169.254.0.0","169.254.255.255", "Link-local"),
					array ("172.16.0.0","172.31.255.255", "Private network"),
					array ("192.0.0.0","192.0.0.7", "DS-Lite"),
					array ("192.0.0.170","192.0.0.170", "NAT64"),
					array ("192.0.0.171","192.0.0.171", "DNS64"),
					array ("192.0.2.0","192.0.2.255", "Documentation example"),
					array ("192.0.0.0","192.0.0.255", "Reserved by the IETF"),										
					array ("192.88.99.0","192.88.99.255", "Anycast"),
					array ("192.88.99.1","192.88.99.1", "IPv6 to IPv4 Incapsulation"),
					array ("192.168.0.0","192.168.255.255", "Private network"),
					array ("198.51.100.0","198.51.100.255", "Documentation example"),
					array ("198.18.0.0","198.19.255.255", "Test IP"),
					array ("203.0.113.0","203.0.113.255", "Documentation example"),
					array ("224.0.0.0","224.255.255.255", "Multicast"),
					array ("240.0.0.0","240.255.255.255", "Future reserved")					
					);

// --------- Конец области глобальных переменных ---------

// --------- Область функций ---------

function isip($ip_str) //соответствие данных формату IP
{	
	global $ip_pattern;
	$ret=FALSE;
	if (preg_match($ip_pattern,$ip_str)) 
	{
		$ret=TRUE;
	}
	return $ret;
}

function chkdiapip ($user_ip, $ip_from, $ip_to) //попадает ли ip в нужный диапазон
{
	return ( ip2long($user_ip)>=ip2long($ip_from) && ip2long($user_ip)<=ip2long($ip_to) );
}

function get_spec_diap ($user_ip) //определение, попал ли IP в специальный диапазон
{
	global $spec_list;
	
	for ($i=0;$i<sizeof($spec_list);$i++)
		{
			$item = $spec_list[$i];
			if (chkdiapip($user_ip, $item[0], $item[1]))
			{
				return $item[2];
			}
		}
		
		return -1;
}

function get_info_ip($field, $ip)
{	
	global $SxGeo;
	$retv=""; //возвращаемое значение
	
	//проверяем, не попал ли IP в особый диапазон
	$check_diap = get_spec_diap($ip);
	if ($check_diap!=-1)
	{
		$retv=$ip."|0|0|0|0|0|0|0|0|0|".$field."|WRN:".$check_diap;
		return $retv;
	}

	$add_info = $SxGeo->getCityFull($ip); // Вся информация о городе
	$main_info = $SxGeo->get($ip);         // Краткая информация о городе или код страны (если используется база SxGeo Country)

	//"IP|ISO_CODE|COUNTRY_NAME|CTNR_LAT|CTNR_LON|REGION_ISO|REGION_NAME|CITY_NAME|CTY_LAT|CTY_LON|FIELD|MESSAGE|\n";
	$retv=$ip."|".$main_info['country']['iso']."|".$add_info['country']['name_en']."|".
		$add_info['country']['lat']."|".$add_info['country']['lon']."|".
		$add_info['region']['iso']."|".$add_info['region']['name_en']."|".
		$main_info['city']['name_en']."|".$main_info['city']['lat']."|".$main_info['city']['lon']."|".$field."|OK";

	return $retv;
}

function printspecdiap()
{
	global $spec_list;
	
	for ($i=0;$i<sizeof($spec_list);$i++)
	{
		$item = $spec_list[$i];
		echo $item[0]."\t\t".$item[1]."\t\t\t".$item[2], "\t\t\t\n";
	}
}

function print_help($this_name)
{
	echo "Use:\n";
	echo $this_name."<ip-address> <database-path> [FIELD_NAME]\n";
	echo $this_name." --spec\n";
	echo $this_name." --help\n";
	
	echo "\n";
	echo "--help - This help \n";
	echo "--spec - For get special IP-address diapasons list \n";
	echo "<ip-address> - IPv4 Address (e.g. 8.8.8.8)\n";
	echo "<database-path> - path to SxGeoCity.dat\n";
	echo "[FIELD_NAME] - for include Field name to answer, default FIELD_NAME value = MANUAL\n";
	echo " \n";
	echo "EXIT CODES:\n";
	echo "0 - OK\n";
	echo "1 - Warning\n";
	echo "3 - print info\n";
	echo "4 - error or wrong parameters\n";
	
	echo "\n";
	echo "Answer structure: \n";
	echo "IP|ISO_CODE|COUNTRY_NAME|CTNR_LAT|CTNR_LON|\n";
	echo "REGION_ISO|REGION_NAME|CITY_NAME|CTY_LAT|CTY_LON\n";
	echo "|FIELD|MESSAGE|\n";

}
	
// ---------Конец области функций ---------

if ($_SERVER['argc'] < 3) //недостаточно обязательных параметров
{
	print_help($_SERVER['argv'][0]);
	exit(4);
}

switch ($_SERVER['argv'][1])
{
	case "--help":
	{
		print_help($_SERVER['argv'][0]);
		exit(3);
	}
	
	case "--spec":
	{
		printspecdiap();
		exit(3);
	}
}

//проверили ключи и их наличие, не один не совпал - анализируем параметр на предмет, что это IP
$ip = $_SERVER['argv'][1];
if (!isip($ip))
{	
	echo "Wrong IP";
	exit (4);
}
//устанавливаем и проверяем путь к БД
$databasepath = $_SERVER['argv'][2];
if (!file_exists($databasepath))
{
	echo "Database file ".$databasepath." not found";
	exit (4);
}

//создаем объект SxGeo
$SxGeo = new SxGeo($databasepath);

//устанавливаем имя поля
$field="MANUAL";
if ($_SERVER['argc'] > 3)
{
	$field=$_SERVER['argv'][3];
}

//получаем информацию об IP
$info = get_info_ip($field,$ip);

//выводим результат в консоль
echo "---START-DATA---\n";
echo $info."\n";
echo "---END-DATA---\n";

//проверяем, не быдо ли Warning'а и генерируем соответственно ExitCode
if (strpos ($info,"WRN")===false)
	exit (0);
else
	exit (1);

?>