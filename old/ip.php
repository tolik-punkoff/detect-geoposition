<?php
// Переделано из примера [8^12]
header('Content-type: text/plain; charset=utf8');

// Подключаем SxGeo.php класс
include("SxGeo.php");
// Создаем объект
// Первый параметр - имя файла с базой (используется оригинальная бинарная база SxGeo.dat)
// Второй параметр - режим работы: 
//     SXGEO_FILE   (работа с файлом базы, режим по умолчанию); 
//     SXGEO_BATCH (пакетная обработка, увеличивает скорость при обработке множества IP за раз)
//     SXGEO_MEMORY (кэширование БД в памяти, еще увеличивает скорость пакетной обработки, но требует больше памяти)
$SxGeo = new SxGeo('SxGeoCity.dat');
//$SxGeo = new SxGeo('SxGeoCity.dat', SXGEO_BATCH | SXGEO_MEMORY); // Самый производительный режим, если нужно обработать много IP за раз

$ip = $_SERVER['REMOTE_ADDR'];

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
