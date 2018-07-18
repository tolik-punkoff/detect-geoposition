<?php
//Переделано из примера [8^12] v 0.6
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
	$ret=1;
	//Частные IP
	if (chkdiapip ($user_ip,'10.0.0.0','10.255.255.255'))
	{
		$ret="WRN_IP_PRIVATE_ADDRESS 10.0.0.0-10.255.255.255";
		return $ret;
	}
	if (chkdiapip ($user_ip,'172.16.0.0','172.31.255.255'))
	{
		$ret="WRN_IP_PRIVATE_ADDRESS 172.16.0.0-172.31.255.255";
		return $ret;
	}
	if (chkdiapip ($user_ip,'192.168.0.0','192.168.255.255'))
	{
		$ret="WRN_IP_PRIVATE_ADDRESS 192.168.0.0-192.168.255.255";
		return $ret;
	}
	//Wrong IP
	if (chkdiapip ($user_ip,'0.0.0.0','0.255.255.255'))
	{
		$ret="WRN_IP_WRONG_ADDRESS 0.0.0.0-0.255.255.255" ;
		return $ret;
	}
	//IP  LOOPBACK
	if (chkdiapip ($user_ip,'127.0.0.0','127.255.255.255'))
	{
		$ret="WRN_IP_LOOPBACK_ADDRESS 127.0.0.0-127.255.255.255";
		return $ret;
	}

	return $ret;
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
		$retv=$field."|0.0.0.0|ERROR_NOT_IP|0|0|0|0|0|0|0|0|0|";
		return $retv;  	
	}

	//проверяем, не попал ли IP в особый диапазон
	$check_diap = get_spec_diap($ip);
	if ($check_diap!=1)
	{
		$retv=$field."|".$ip."|".$check_diap."|0|0|0|0|0|0|0|0|0|";
		return $retv;
	}

	$add_info = $SxGeo->getCityFull($ip); // Вся информация о городе
	$main_info = $SxGeo->get($ip);         // Краткая информация о городе или код страны (если используется база SxGeo Country)

	//"FIELD|IP|MESSAGE|ISO_CODE|COUNTRY_NAME|CTNR_LAT|CTNR_LON|REGION_ISO|REGION_NAME|CITY_NAME|CTY_LAT|CTY_LON|\n";
	$retv=$field."|".$ip."|OK|".$main_info['country']['iso']."|".$add_info['country']['name_en']."|".
		$add_info['country']['lat']."|".$add_info['country']['lon']."|".
		$add_info['region']['iso']."|".$add_info['region']['name_en']."|".
		$main_info['city']['name_en'].'|'.$main_info['city']['lat']."|".$main_info['city']['lon'].'|';

	return $retv;
}

function print_data_structure()
{
	echo "FIELD=Поле с данными об IP\n";
	echo "IP=IP-адрес\n";
	echo "MESSAGE=Сообщение об ошибке\n";
	echo "ISO_CODE=Код страны (ISO)\n";
	echo "COUNTRY_NAME=Страна\n";
	echo "CTNR_LAT=Широта страны\n";
	echo "CTNR_LON=Долгота страны\n";
	echo "REGION_ISO=Код региона (ISO)\n";
	echo "REGION_NAME=Регион\n";
	echo "CITY_NAME=Город\n";
	echo "CTY_LAT=Широта города\n";
	echo "CTY_LON=Долгота города\n";
}
// ---------Конец области функций ---------

$ip="";

if (isset($_GET['ds'])) //запрос структуры данных
{
	print_data_structure();
	die();
}

echo "FIELD|IP|MESSAGE|ISO_CODE|COUNTRY_NAME|CTNR_LAT|CTNR_LON|REGION_ISO|REGION_NAME|CITY_NAME|CTY_LAT|CTY_LON|\n";
echo '---START-MAIN-DATA---'."\n";
//проверка наличия переменной, если она есть 
//проверяем не тот IP с которого зашли а переданный
if (isset($_GET['ip'])) 
{
    $ip=$_GET['ip'];
	echo get_info_ip("MANUAL",$ip)."\n";
	echo '---END-MAIN-DATA---'."\n";
	die();
}
  
$ip = $_SERVER['REMOTE_ADDR'];
echo get_info_ip("REMOTE_ADDR",$ip)."\n";
echo '---END-MAIN-DATA---'."\n";

echo '---START-ADD-DATA---'."\n";
$ip=get_all_info_ip();
echo $ip;
echo '---END-ADD-DATA---'."\n";
?>