<?php
//Переделано из примера [8^12] v 0.6.2
//Область заголовков
header('Content-type: text/plain; charset=utf8');
//Область подключения внешних скриптов
include("SxGeo.php"); // Подключаем SxGeo.php класс
// --------- Область глобальных переменных ---------

// Создаем объект
// Первый параметр - имя файла с базой (используется оригинальная бинарная база SxGeo.dat)
// Второй параметр - режим работы: 
//     SXGEO_FILE   (работа с файлом базы, режим по умолчанию); 
//     SXGEO_BATCH (пакетная обработка, увеличивает скорость при обработке множества IP за раз)
//     SXGEO_MEMORY (кэширование БД в памяти, еще увеличивает скорость пакетной обработки, но требует больше памяти)
$SxGeo = new SxGeo('SxGeoCity.dat');
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

function get_all_info_ip() //получаем информацию о всех IP, из всех переменных HTTP_* сервера
{	
	global $ip_pattern;
	$ret="";
	foreach ($_SERVER as $k => $v) 
	{
		//если нашли в поле HTTP_* (HTTP_VIA, HTTP_X_FORWARDED_FOR и т.д.)
		//что-то похожее на IP
		if ((substr($k,0,5)=="HTTP_") AND (preg_match($ip_pattern,$v)))
		{
			preg_match_all($ip_pattern,$v,$matches); //вытаскиваем из строки все совпадения с шаблоном IP			
			foreach ($matches as $tmp) //preg_match_all выдает многомерный массив
			{
				foreach($tmp as $ip) //вытаскиваем каждый отдельный IP
				{
					$ret.=get_info_ip($k,$ip)."\n"; //получаем информацию для каждого IP
				}								
			}
		}		
	}
	return $ret;
}

function get_info_ip($field, $ip)
{	
	global $SxGeo;
	$retv=""; //возвращаемое значение
		
	// проверка на соответствие формату
	if (!isip($ip))
	{
		//не IP - записали в поле MESSAGE сообщение об ошибке и прекратили работу
		$retv="0.0.0.0|0|0|0|0|0|0|0|0|0|".$field."|ERROR_NOT_IP";
		return $retv;  	
	}

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
	
// ---------Конец области функций ---------

$ip="";

if (isset($_GET['specdiap'])) //список зарезервированных диапазонов IP-адресов
{
	echo "Special IP-address diapasons:\n";
	printspecdiap();
	
	die();
}

echo "IP|ISO_CODE|COUNTRY_NAME|CTNR_LAT|CTNR_LON|REGION_ISO|REGION_NAME|CITY_NAME|CTY_LAT|CTY_LON|FIELD|MESSAGE|\n";
echo "---START-DATA---\n";
//проверка наличия переменной, если она есть 
//проверяем переданный IP иначе ошибка
$help=false;
if (isset($_GET['ip']))
{
    $field="";
	if (isset($_GET['f']))
	{
		$field=$_GET['f'];
	}
	else
	{
		$field="MANUAL";
	}
	$ip=$_GET['ip'];
	echo get_info_ip($field,$ip)."\n";
}
else
{
	echo "0.0.0.0|0|0|0|0|0|0|0|0|0|".$field."|ERROR_NOT_IP \n";
	$help=true;
}

echo "---END-DATA---\n";

if ($help)
{
	echo "Use:\n";
	echo "onlysxgeo.php?ip=<ip-address>\n";
	echo "\n";
	echo "EXAMPLE: \n\n";
	echo "onlysxgeo.php?ip=8.8.8.8\n";
	echo "or \n";
	echo "onlysxgeo.php?ip=<ip-address>&f=FIELD_NAME\n";
	echo "for include Field name to answer, default FIELD value = MANUAL\n";	
	echo "or \n";
	echo "onlysxgeo.php?specdiap \n";
	echo "For get  Special IP-address diapasons list \n";
}
?>