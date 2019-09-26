<?php
ini_set('error_reporting', E_ALL);
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);

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
$SxGeo = new SxGeo('SxGeo.dat');
//$SxGeo = new SxGeo('SxGeoCity.dat', SXGEO_BATCH | SXGEO_MEMORY); // Самый производительный режим, если нужно обработать много IP за раз
// --------- Конец области глобальных переменных ---------

// --------- Область функций ---------

function GetCountryName($ccode)
{
	$handle = @fopen("iso.csv", "r");
	$ctr = 1;
	$cname = "";
	if ($handle) 
	{		
		while (($buf = fgets($handle)) !== false) 
		{
			//echo "====".trim($buf)."====\n";
			$expl = explode(";",trim($buf));
			if (count($expl) < 2)
			{
				$cname = "Format error in string # ".$ctr;
				return $cname;
			}
			
			if ($expl[1] == $ccode)
			{
				return $expl[0];
			}
			$ctr++;			
		}
		if (!feof($handle)) 
		{
			$cname = "Error reading string # ".$ctr;
			return $cname;
		}
		fclose($handle);
	}
	
	return $cname;
}

function get_info_ip($ip)
{	
	global $SxGeo;
	
	$ccode = $SxGeo->get($ip);         // Краткая информация о городе или код страны (если используется база SxGeo Country)
	$cname = GetCountryName($ccode);	
	
	$retv='{"ip":"'.$ip.'","country":"'.$cname.'","cc":"'.$ccode.'"}';

	return $retv;
}

	
// ---------Конец области функций ---------

$ip="";

//данные из REMOTE_ADDR  
$ip = $_SERVER['REMOTE_ADDR'];
echo get_info_ip($ip);


?>