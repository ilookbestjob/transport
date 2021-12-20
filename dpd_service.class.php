<?php

class err {
	public $error; 
}

$err= new err();

class DPD_service
{
	public $arMSG = array(); // массив-сообщение ('str' => текст_сообщения, 'type' => тип_сообщения (по дефолту: 0 - ошибка)
	private $IS_ACTIVE = 1; // флаг активности сервиса (0 - отключен, 1 - включен)
	private $IS_TEST = 0; // флаг тестирования (0 - работа, 1 - тест)
	private $SOAP_CLIENT; // SOAP-клиент
	private $MY_NUMBER = '1073000499'; // ЗАМЕНИТЬ НА СВОЙ!!! - клиентский номер в системе DPD (номер договора с DPD)
	private $MY_KEY = 'B1561FD96A86CB892823E8C90792485EE66DE96A'; // ЗАМЕНИТЬ НА СВОЙ!!! - уникальный ключ для авторизации

	private $arDPD_HOST = array(
		0 => 'http://ws.dpd.ru/services/', //рабочий хост
		1 => 'wstest.dpd.ru/services/' //тестовый хост
	);
	private $arSERVICE = array( //сервисы: название => адрес
		'getCitiesCashPay' => 'geography2', //География DPD (города доставки)
		'getTerminalsSelfDelivery2' => 'geography', //список терминалов DPD (TODO)
		'getTerminalsSelfDelivery2' => 'geography2', //список терминалов DPD (TODO)
		'getServiceCost' => 'calculator2', //Расчет стоимости
		'getServiceCost2' => 'calculator2', //Расчет стоимости
		'createOrder' => 'order2', //Создать заказ на доставку (TODO)
		'getOrderStatus' => 'order2', //Получить статус создания заказа (TODO)
		'getStatesByDPDOrder' => 'tracing',
		'getStatesByClient' => 'tracing',
		'cancelOrder' => 'order2', //Создать заказ на доставку (TODO)

	);

	/**
	 * Конструктор
	 *
	 * @access public
	 * @return void
	 */
	public function __construct()
	{
		$this->IS_TEST = $this->IS_TEST ? 1 : 0;
	}
	//--Все статусы
	public function getStatesByClient()
	{
		$obj = $this->_getDpdData('getStatesByClient', null, 'request');
		$res = $this->_parceObj2Arr($obj->return);
		return $res;
	}

	//--Статус заказа
	public function getStatesByDPDOrder($arData)
	{
		$obj = $this->_getDpdData('getStatesByDPDOrder', $arData, 1);
		$res = $this->_parceObj2Arr($obj->return);
		return $res;
	}
	/**
	 * Список городов доставки *
	 *
	 * @access public
	 * @return
	 */
	public function getCityList()
	{
		$client = new SoapClient($this->arDPD_HOST[0] . "geography2?wsdl");

		$arData['auth'] = array(
			'clientNumber' => $this->MY_NUMBER,
			'clientKey' => $this->MY_KEY
		);
		$arRequest['request'] = $arData; //помещаем наш масив авторизации в масив запроса request.
		$obj = $client->getParcelShops($arRequest);
		$res = $this->_parceObj2Arr($obj->return);
		return $res;
	}
	public function getTerminalList()
	{
		$client = new SoapClient($this->arDPD_HOST[0] . "geography2?wsdl");

		$arData['auth'] = array(
			'clientNumber' => $this->MY_NUMBER,
			'clientKey' => $this->MY_KEY
		);
		$arRequest['request'] = $arData; //помещаем наш масив авторизации в масив запроса request.
		$obj = $client->getTerminalsSelfDelivery2($arData);
		//$obj = $this->_getDpdData( 'getTerminalsSelfDelivery2' );
		// конверт $obj --> $arr
		//$res = $this->_parceObj2Arr( $obj->return );
		return $obj->return;
	}
	/**
	 * Определение стоимости доставки *
	 *
	 * @access public
	 * @param array   $arData // массив входных параметров*
	 * @return
	 */
	public function getServiceCost($arData)
	{
		global $err;
		// куда
		//if ($arData['delivery']['cityName']) {
			//$arData['delivery']['cityName'] = iconv( 'windows-1251', 'utf- 8', $arData['delivery']['cityName'] );
	//	}
		// откуда
		$arData['pickup'] = array(
			'cityId' => 196033774,
			//			'cityName' => iconv( 'windows-1251', 'utf-8', 'Петрозаводск' ),
			//'regionCode' => '66', //'countryCode' => 'RU',
		);
		// что делать с терминалом
		$arData['selfPickup'] = false; // Доставка ОТ терминала
		if (!array_key_exists('selfDelivery', $arData)) $arData['selfDelivery'] = true; // Доставка ДО терминала




		$arData['auth'] = array(
			'clientNumber' => $this->MY_NUMBER,
			'clientKey' => $this->MY_KEY
		);
		$arRequest['request'] = $arData; //помещаем наш масив авторизации в масив запроса request.
	
		try {
			$client = new SoapClient($this->arDPD_HOST[0] . "calculator2?wsdl");
			$obj = $client->getServiceCost2($arRequest);
		
		} catch (SoapFault $e) {
		
		$err->error="Ошибка получения данных dpdcityid=\"" . $arData['delivery']['cityId'] . "\"    $e</br>";
return $err;
		}

		// третий параметр - флаг упаковки запроса в общее поле "request"
		//	$obj = $this->_getDpdData( 'getServiceCost2', $arData,1);

		//echo $obj;
	
		// конверт $obj --> $arr
		//$res = $this->_parceObj2Arr($obj->return);
		return $obj;
	}

	/* Оформить заказ	 */
	public function createOrder($arData)
	{
		$obj = $this->_getDpdData('createOrder', $arData, 2);
		// конверт $obj --> $arr
		$res = $this->_parceObj2Arr($obj->return);
		return $res;
	}
	/* Отменить заказ	 */
	public function cancelOrder($arData)
	{
		$obj = $this->_getDpdData('cancelOrder', $arData, 2);
		// конверт $obj --> $arr
		$res = $this->_parceObj2Arr($obj->return);
		return $res;
	}

















	// PRIVATE ------------------------
	/**
	 * Коннект с соответствующим сервисом *
	 *
	 * @access private
	 * @param string  $method_name
	 * свойства класса $this->arSERVICE) * @return bool
	 * Запрашиваемый метод сервиса (см. ключ
	 * Результат инициализации (если положительный - появится свойство $this->SOAP_CLIENT, иначе $this->arMSG)
	 */
	private function _connect2Dpd($method_name)
	{
		if (!$this->IS_ACTIVE) return false;
		if (!$service = $this->arSERVICE[$method_name]) {
			$this->arMSG['str'] = 'В свойствах класса нет сервиса "' . $method_name . '"';
			return false;
		}
		$host = 'http://' . $this->arDPD_HOST[$this->IS_TEST] . $service . '?WSDL';
		try {
			// Soap-подключение к сервису

			//			echo $host."<br>";
			$this->SOAP_CLIENT = new SoapClient($host);
			if (!$this->SOAP_CLIENT) throw new Exception('XEPPP');
		} catch (Exception $ex) {
			//			echo "ERRRORR: ".$service;
			$this->arMSG['str'] = 'Не удалось подключиться к сервисам DPD ' . $service;
			return false;
		}
		//		echo "!!!!!!!!!!";
		return true;
	}
	/**
	 * Запрос данных в методе сервиса *
	 *
	 * @access private
	 * @param string  $method_name Название метода Dpd-сервиса (см.$arSERVICE)
	 * @param array   $arData      Массив параметров, передаваемых в метод
	 * @param integer $is_request  флаг упаковки запроса в поле 'request'
	 * @return XZ_obj Объект, полученный от сервиса
	 */
	private function _getDpdData($method_name, $arData = array(), $is_request = 0)
	{
		if (!$this->_connect2Dpd($method_name)) {
			echo "err";
			return false;
		}
		//		echo "!!!";
		$arData['auth'] = array('clientNumber' => $this->MY_NUMBER, 'clientKey' => $this->MY_KEY,);
		// упаковка запроса в поле 'request'
		if ($is_request == 2) $arRequest['orders'] = $arData;
		else if ($is_request) $arRequest['request'] = $arData;
		else $arRequest = $arData;
		//		if ($is_request) $arRequest[$is_request] = $arData; else $arRequest = $arData;
		try {
			//			echo $method_name.": ";
			//			print_r($arRequest);
			//eval("\$obj = \$this->SOAP_CLIENT->\$method_name(\$arRequest);");
			$obj = $this->SOAP_CLIENT->$method_name($arRequest);
			//			print_r($this->arMSG['str']);
			//			var_dump ($obj);
			//echo "#####".$obj;
			//print_r($obj);
			if (!$obj) throw new Exception('XEPPP');
		} catch (Exception $ex) {
			$this->arMSG['str'] = 'Не удалось вызвать метод ' . $method_name . ' / ' . $ex;
			//echo "!!!!!2".$ex;
		}

		return $obj ? $obj : false;
	}

	/**
	 * Парсер объекта в массив (рекурсия) *
	 *
	 * @access private
	 * @param object  $obj   Объект
	 * @param integer $isUTF Флаг необходимости конвертирования строк из UTF в WIN (0|1), по-дефолту "1" - конвертить
	 * @param array   $arr   Внутренний cлужебный массив для обеспечения рекурсии
	 * @return array
	 */
	private function _parceObj2Arr($obj, $isUTF = 1, $arr = array())
	{
		$isUTF = $isUTF ? 1 : 0;
		if (is_object($obj) || is_array($obj)) {
			$arr = array();
			for (reset($obj); list($k, $v) = each($obj);) {
				if ($k === "GLOBALS") continue;
				$arr[$k] = $this->_parceObj2Arr($v, $isUTF, $arr);
			}
			return $arr;
		} elseif (gettype($obj) == 'boolean') {
			return $obj ? 'true' : 'false';
		} else {
			// конверт строк: utf-8 --> windows-1251
			if ($isUTF && gettype($obj) == 'string')
				$obj = iconv('utf-8', 'windows-1251', $obj);
			return $obj;
		}
	}
}
