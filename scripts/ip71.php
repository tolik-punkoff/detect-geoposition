<?php
//Переделано из примера [8^12] v 0.7.1 Добавлено оформление
//Область заголовков
header('Content-type: text/html; charset=utf8');
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
					array ("192.88.99.1","192.88.99.1", "IPv6 to IPv4 Incapsulation"),
					array ("192.88.99.0","192.88.99.255", "Anycast"),
					array ("192.168.0.0","192.168.255.255", "Private network"),
					array ("198.51.100.0","198.51.100.255", "Documentation example"),
					array ("198.18.0.0","198.19.255.255", "Test IP"),
					array ("203.0.113.0","203.0.113.255", "Documentation example"),
					array ("224.0.0.0","224.255.255.255", "Multicast"),
					array ("240.0.0.0","240.255.255.255", "Future reserved")					
					);

// --------- Конец области глобальных переменных ---------

// --------- Область функций ---------

function isip($ip)
{
	//преобразуем в нижний регистр, на случай шестнадцатиричных чисел
	$ip=strtoupper($ip);
	//ip2long в некоторых версиях php 
	//некорректно реагирует на адрес 255.255.255.255
	//делаем небольшую заглушку
	if ($ip == '255.255.255.255'||$ip == '0xff.0xff.0xff.0xff'||
	   $ip == '0377.0377.0377.0377')
	   {
		   return true;
	   }
	
	$tolong=ip2long($ip);
	
	if ($tolong == -1||$tolong===FALSE) return FALSE;
	else return TRUE;
	
}

function fulladdr($ip)
{
	//преобразует неполные адреса в полные
	//для информации
	$tolong=ip2long($ip);
	return long2ip ($tolong);
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
		$retv="<tr><td>0.0.0.0</td><td>-</td><td>-</td><td>-</td><td>-</td><td>-</td><td>-</td><td>-</td><td>-</td><td>"
		."-</td><td>".$field."</td><td>"."<font color='red'>Не IP</font>"."</td></tr>";
		return $retv;  	
	}
	
	$fa=fulladdr($ip); //получаем полный адрес на случай если тот был неполным
	if ($fa!=$ip) //если ввели неполный адрес
	{
		$fa = $fa." [".$ip."]";
	}
	
	//проверяем, не попал ли IP в особый диапазон
	$check_diap = get_spec_diap($ip);
	if ($check_diap!=-1)
	{
		$retv="<tr><td>".$fa."</td><td>-</td><td>-</td><td>-</td><td>-</td><td>-</td><td>-</td><td>-</td><td>-</td><td>"
		."-</td><td>".$field."</td><td>"."<font color='yellow'>".$check_diap."</font>"."</td></tr>";
		return $retv;
	}

	$add_info = $SxGeo->getCityFull($ip); // Вся информация о городе
	$main_info = $SxGeo->get($ip);         // Краткая информация о городе или код страны (если используется база SxGeo Country)

	//"FIELD|IP|MESSAGE|ISO_CODE|COUNTRY_NAME|CTNR_LAT|CTNR_LON|REGION_ISO|REGION_NAME|CITY_NAME|CTY_LAT|CTY_LON|\n";
	$retv="<tr><td>".$fa."</td><td>".$main_info['country']['iso']."</td><td>".$add_info['country']['name_en']."</td><td>".
		$add_info['country']['lat']."</td><td>".$add_info['country']['lon']."</td><td>".
		$add_info['region']['iso']."</td><td>".$add_info['region']['name_en']."</td><td>".
		$main_info['city']['name_en']."</td><td>".$main_info['city']['lat']."</td><td>".$main_info['city']['lon'].'</td><td>'
		.$field."</td><td>"."<font color='lime'>OK</font>"."</td></tr>";

	return $retv;
}
function print_top() //печатает заголовок и верх страницы
{
	echo "<html>";
	echo "<head>";
	echo "<meta http-equiv='Content-Type' content='text/html; charset=utf-8'>";
	echo "<style type='text/css'>
			TABLE {
			width: 300px; /* Ширина таблицы */
			border-collapse: collapse; /* Убираем двойные линии между ячейками */
			}
			TD, TH {
			padding: 3px; /* Поля вокруг содержимого таблицы */
			border: 1px solid gold; /* Параметры рамки */
			font: 12pt/10pt monospace;
			}
			#footer {
			position: fixed; /* Фиксированное положение */
			left: 0; bottom: 0; /* Левый нижний угол */
			padding: 10px; /* Поля вокруг текста */	
			width: 100%; /* Ширина слоя */
			}
			</style>";
	echo "<title>IP-адрес и географическое положение</title>";
	echo "</head>";
	echo "<body bgcolor='black' text='silver'>";
	echo "<center><h2>IP-адрес пользователя</h2></center>"."\n";
	echo "<table align='center'>";
	echo "<tr><td colspan='12'><center><b>Основная информация</b></center></td></tr>"."\n";
	echo "<tr><th>IP-адрес</th><th>Код страны (ISO)</th><th>Страна</th><th>Широта</th><th>Долгота</th>
		  <th>Регион (ISO)</th><th>Регион</th>
		  <th>Город</th><th>Широта</th><th>Долгота</th>
		  <th>Источник</th><th>Статус</th></tr>";
}
function print_bottom() //печатает низ страницы
{
	echo "</table>";
	echo "</body>";
	echo "</html>";
	echo "<div id='footer'>";
	echo "* Не анонимные прокси могут передавать ваш IP в дополнительных полях HTTP<br> \n";
	echo "(L) 8^12";
	echo "</div>";
}
// ---------Конец области функций ---------

$ip="";
print_top(); //печатаем верх страницы

//проверка наличия переменной с введенным вручную IP
if (isset($_GET['ip'])) 
  {
    $ip=$_GET['ip'];
	echo get_info_ip("Вручную",$ip)."\n";
	print_bottom();
	die();
  }

$ip = $_SERVER['REMOTE_ADDR']; 
echo get_info_ip("REMOTE_ADDR",$ip)."\n";
echo "<tr><td colspan='12'><center><b>Дополнительная информация (по данным заголовков HTTP)*</b></center></td></tr>"."\n";
$ip=get_all_info_ip();
echo $ip;

print_bottom();
?>