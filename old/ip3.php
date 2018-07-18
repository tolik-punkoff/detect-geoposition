<?php
// Переделано из примера [8^12] v 0.3
//Область заголовков
header('Content-type: text/plain; charset=utf8');
// ---------Область функций ---------

function isip($ip_str) //соответствие данных формату IP
{
  $ip_pattern="#(?:(?:25[0-5]|2[0-4][0-9]|[01]?[0-9][0-9]?)\.){3}(?:25[0-5]|2[0-4][0-9]|[01]?[0-9][0-9]?)#";
  $ret=FALSE;
 if (preg_match($ip_pattern,$ip_str)) 
  {
     $ret=TRUE;
  }
  return $ret;
}
// ---------Конец области функций ---------

// Подключаем SxGeo.php класс
include("SxGeo.php");


//проверка наличия переменной
if (!isset($_GET['ip'])) {
    echo 'ERROR|NOT DATA';   //не нашли - вывели сообщение об ошибке и прекратили работу
    die();  
  }  

$ip=$_GET['ip'];
// проверка на соответствие формату
if (!isip($ip))
{
    echo 'ERROR|NOT IP';   //не IP - вывели сообщение об ошибке и прекратили работу
    die();  	
}

// Создаем объект
// Первый параметр - имя файла с базой (используется оригинальная бинарная база SxGeo.dat)
// Второй параметр - режим работы: 
//     SXGEO_FILE   (работа с файлом базы, режим по умолчанию); 
//     SXGEO_BATCH (пакетная обработка, увеличивает скорость при обработке множества IP за раз)
//     SXGEO_MEMORY (кэширование БД в памяти, еще увеличивает скорость пакетной обработки, но требует больше памяти)
$SxGeo = new SxGeo('SxGeoCity.dat');
//$SxGeo = new SxGeo('SxGeoCity.dat', SXGEO_BATCH | SXGEO_MEMORY); // Самый производительный режим, если нужно обработать много IP за раз

$add_info = $SxGeo->getCityFull($ip); // Вся информация о городе
$main_info = $SxGeo->get($ip);         // Краткая информация о городе или код страны (если используется база SxGeo Country)

echo "IP|".$ip."\n";

echo "ISO_CODE|".$main_info['country']['iso']."\n";

echo "CITY|".$main_info['city']['name_en'].'|'.
	$main_info['city']['lat'].'|'.
	$main_info['city']['lon']."\n";

echo "COUNTRY_INFO|".$add_info['country']['name_en'].'|'.
	$add_info['country']['lat'].'|'.
	$add_info['country']['lon']."\n";

echo "REGION_INFO|".$add_info['region']['iso'].'|'.
	$add_info['region']['name_en']."\n";
