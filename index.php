<?php 
if ( !session_id() ) {
	session_start();
}

set_time_limit(30);
ini_set("max_execution_time", 30);

if ( !empty($_REQUEST['debug']) ) {
	ini_set('display_errors', '1');
	ini_set('display_startup_errors', '1');
	error_reporting(E_ALL | E_STRICT);
}


// IP, которым разрешен доступ к API (для каждого IP пишем комментарий)
$white_list = array(
					'87.251.187.34', 	// офис на розы.л. 37
					'82.151.200.100', 	// онлайн
					'85.12.229.170', 	// finder
					);
if ( !in_array($_SERVER['REMOTE_ADDR'], $white_list) ) {
	die('Access denied!');
	die($_SERVER['SERVER_PROTOCOL'] . ' 404 Not Found'); //? what?
}

require $_SERVER['DOCUMENT_ROOT'] . 'admin/config/conf.db.main.pdo.php';
include_once $_SERVER['DOCUMENT_ROOT'] . 'admin/_system/functions.php';

if ( !empty($DBH) ) {
	$app = new API_ONLINE($DBH);
	$app->init();
}

class API_ONLINE {
	// Таблица черновиков
	private $sql_preorder = 'crm_preorder';
	// Токены для доступа к API
	private $tokens = array(
							'f564067a80f0285be2d5beae1e575614', // Тестовый токен, с ним создаются черновики для агента 9999
							'6cd31926f9b80daeafc3542235f7d9e3', 
							);
	// Методы для которых проверяем наличие agent_id в запросе
	private $methodsForCheckAgency = array('createPreOrder', 'create'); 
	// // Россия ( маркетинговая география)
	private $disabledCountries = array(56, 161, 25, 82, 21, 128, 61, 57, 162, 23, 33, 127, 53, 135, 129, 163, 136, 137, 130, 70, 142, 141, 27, 154, 32, 22, 155, 148, 156, 59, 140, 26, 157, 143, 147, 20, 158, 139, 24, 164, 159, 60, 160, 84, 138);
	// Вызываемый метод
	private $action = '';
	// Тип возвращаемых данных
	private $datatype = 'json';
	private $errors = array(
								1000 => 'Ошибка авторизации!',
								1001 => 'Нет данных о агенте!',
								1002 => 'Ошибка создания черновика!',
								1003 => 'Нет данных о туристе (отсутствует name)',
								1004 => 'Неизвестная ошибка!',
								1005 => 'Недостаточно данных (отсутствует order)',
								
								// Ошибки при создании заявки
								1100 => 'Ошибка создания заявки',
								1101 => 'Нет информации о операторе',
								1102 => 'Нет информации о стране прибытия',
								1103 => 'Нет информации о дате начала тура/дате вылета',
								1104 => 'Нет информации о стоимости тура/билета',
								1105 => 'Нет информации о валюте',
								1106 => 'Нет информации о категории отеля',
								1107 => 'Нет информации о наименовании отеля',
								1108 => 'Нет информации о размещении в отеле',
								1109 => 'Нет информации о питании в отеле',
							);

	function __construct($DBH) {
		$this->DBH = $DBH;
		$this->log_file = $_SERVER['DOCUMENT_ROOT'] . 'temp/api/log.txt';
		$this->path = explode('/', trim($_SERVER['REQUEST_URI'], '/'));

		// Действие
		if ( isset($this->path[1]) ) {
			$this->action = trim($this->path[1]);
		}
		elseif ( !empty($_POST['method']) ) {
			$this->action = trim($_POST['method']);
		}
		elseif ( !empty($_POST['action']) ) {
			$this->action = trim($_POST['action']);
		}

		// Токен для доуступа к API
		if ( !empty($_POST['token']) ) {
			$this->token = trim($_POST['token']);
		}
		// Тип возвращаемых данных
		if ( !empty($_POST['datatype']) ) {
			$this->datatype = trim($_POST['datatype']);
		}

	}

	/**
	 *	Инициализация модуля
	 *
	 *	@access public
	 *	@return string
	 */
	public function init() {

		// авторизация / проверка токена
		$auth = $this->auth();

		// Ошибка авторизации!
		if ( !$auth ) {
			$message = $this->setError('AUTH', 1000);
			$this->show($message);
		}

		$this->setLog(date('d.m.y H:i') . ' ' . $_SERVER['REMOTE_ADDR'] . " - AUTH true");
		$this->setLog(date('d.m.y H:i') . ' ' . $_SERVER['REMOTE_ADDR'] . " - POST\r\n" . var_export($_POST, true));

		// Проверка, что мы знаем агента и что он существует.
		$this->agent_id = $this->checkAgency();

		// Вызываемые методы
		// Создание черновика
		if ( 'createPreOrder' == $this->action ) {
			if ( !empty($_POST['order']) ) {
				$this->order = $_POST['order'];
			}
			// Создание черновика заявки
			$res = $this->createPreOrder();
			if ( 'json' == $this->datatype ) {
				header("Content-Type: application/json; charset=utf-8");
				echo json_encode($res, 0777);
			}
			elseif ( 'array' == $this->datatype ) {
				var_export($res);
			}
			die();
		}

		// Справка по методам
		elseif ( 'help' == $this->action ) {
			echo $this->help();
		}

		// Получение списка стран
		elseif ( 'getCountries' == $this->action ) {
			$res = $this->getDictionary('countries');
			// Россия ( - вырезаем маркетинговую географию
			foreach ( $res as $key => $value ) {
				if ( in_array($key, $this->disabledCountries) and isset($res[$key]) ) {
					unset($res[$key]);
				}
			}
			$this->show($res);
		}

		// Получение справочника городов вылета
		elseif ( 'getCities' == $this->action ) {
			$res = $this->getDictionary('cities');
			$this->show($res);
		}

		// Поиск ID страны по названию
		elseif ( 'getCountryIdByName' == $this->action ) {
			$country_name = setArrayValue($_POST, 'name');
			$res = $this->getEntryIdByName('countries', 'country_id', $country_name);
			$this->show($res);
		}

		// Поиск ID города вылета по названию
		elseif ( 'getCityIdByName' == $this->action ) {
			$city_name = setArrayValue($_POST, 'name');
			$res = $this->getEntryIdByName('cities', 'city_id', $city_name);
			$this->show($res);
		}

		// Получение справочника городов вылета
		elseif ( 'getRegions' == $this->action ) {
			$res = $this->getDictionary('regions');
			$this->show($res);
		}

		// Получение списка регионов в стране
		elseif ( 'getRegionsByCountry' == $this->action ) {
			$country_id = intval(setArrayValue($_POST, 'country_id'));
			$res = $this->getRegionsByCountry($country_id);
			$this->show($res);
		}

		// Поиск ID региона по названию и стране
		elseif ( 'getRegionIdByName' == $this->action ) {
			$region_name = setArrayValue($_POST, 'name');
			$country_id = setArrayValue($_POST, 'country_id');
			$res = $this->getRegionIdByName($country_id, $region_name);
			$this->show($res);
		}

		// Получение справочника типов питания
		elseif ( 'getFoods' == $this->action ) {
			$res = $this->getDictionary('food');
			$this->show($res);
		}

		// Поиск ID типа питания по названию
		elseif ( 'getFoodIdByName' == $this->action ) {
			$name = setArrayValue($_POST, 'name');
			$res = $this->getEntryIdByName('food', 'food_id', $name);
			$this->show($res);
		}

		// Получение справочника аэропортов
		elseif ( 'getAirports' == $this->action ) {
			$res = $this->getDictionary('airports');
			$this->show($res);
		}

		// Поиск ID аэропорта по названию
		elseif ( 'getAirportIdByName' == $this->action ) {
			$name = setArrayValue($_POST, 'name');
			$res = $this->getEntryIdByName('airports', 'airport_id', $name);
			$this->show($res);
		}

		// Создание заявки
		elseif ( 'create' == $this->action ) {
			if ( !empty($_POST['order']) ) {
				$this->order = $_POST['order'];
			}
			// Создание черновика заявки
			$res = $this->create();
			$this->show($res);
		}

	}

	/**
	 *	Проверка, что мы знаем агента и что он существует.
	 *
	 *	@access private
	 *	@return array
	 */
	private function checkAgency() {
		$result = 0;
		if ( in_array($this->action, $this->methodsForCheckAgency) ) { // для каких методов выполнять проверку
			if ( empty($_POST['agent_id']) ) {
				$message = $this->setError('agent_id', 1001);
				$this->show($message);
			}
			else {
				$result = $_POST['agent_id'];
			}
			// fix for test token
			if ( !empty($this->token) and 'f564067a80f0285be2d5beae1e575614' == $this->token ) {
				$result = 9999;
			}
		}

		return $result;
	}

	/**
	 *	Поиск ID региона по названию и стране
	 *
	 *	@access private
	 *	@return array
	 */
	private function create() {
		$result = array('success' => false, 'result' => array('code' => 1004, 'message' => $this->errors[1004]));

		// Проверка, что данных для создания заявки достаточно
		if ( $this->createCheckData() ) {
			$sql = 'INSERT . . . ';
		}

		return $result;
	}

	/**
	 *	Проверка, что данных для создания заявки достаточно (заполнены все обязательные поля)
	 *	Список обязательных полей:
	 *+		order, 
	 *+			oper_id|oper, country_id|country, dateTourStart, 
	 *+ 			prices
	 *+ 				price, currency
	 *			если есть hotel 
	 *+				categoryId|category, hotelId|hotelName, accommodationId|accommodationName, foodId|foodName
	 *			tourists (минимум 1 турист обязательно должен быть, на которого оформляется договор)
	 *				gender, fullNameRus|fullNameEng, passportSerial, passportNumber, passportIssuedBy, passportIssuedByDate 
	 *
	 *	@access private
	 *	@return array
	 */
	private function createCheckData() {
		$result = true;
		if ( !empty($this->order) ) {
			// Оператор
			if ( empty($this->order['oper_id']) and empty($this->order['oper']) ) {
				$message = $this->setError('oper_id', 1101);
				$this->show($message);
			}
			// Страна прибытия
			if ( empty($this->order['country_id']) and empty($this->order['country']) ) {
				$message = $this->setError('country', 1102);
				$this->show($message);
			}
			// Дата наала тура/дата вылета
			if ( empty($this->order['dateTourStart']) ) {
				$message = $this->setError('dateTourStart', 1103);
				$this->show($message);
			}
			// Стоимость
			if ( empty($this->order['prices']) ) {
				$message = $this->setError('prices', 1104);
				$this->show($message);
			}
			else {
				// Стоимость
				if ( empty($this->order['prices']['price']) ) {
					$message = $this->setError('price', 1104);
					$this->show($message);
				}
				// Валюта
				// unset($this->order['prices']['currency']);
				if ( empty($this->order['prices']['currency']) ) {
					$message = $this->setError('currency', 1105);
					$this->show($message);
				}
			}
			// Отель (опционально, если массив есть, то проверяем поля)
			if ( !empty($this->order['hotel']) ) {
				// Категория отеля (звездность)
				if ( empty($this->order['hotel']['categoryId']) and empty($this->order['hotel']['category']) ) {
					$message = $this->setError('category', 1106);
					$this->show($message);
				}
				// Название отеля
				if ( empty($this->order['hotel']['hotelId']) and empty($this->order['hotel']['hotelName']) ) {
					$message = $this->setError('hotelName', 1107);
					$this->show($message);
				}
				// Размещение
				if ( empty($this->order['hotel']['accommodationId']) and empty($this->order['hotel']['accommodationName']) ) {
					$message = $this->setError('accommodation', 1108);
					$this->show($message);
				}
				// Питание
				if ( empty($this->order['hotel']['foodId']) and empty($this->order['hotel']['foodName']) ) {
					$message = $this->setError('accommodation', 1109);
					$this->show($message);
				}
			}
			// Туристы
			if ( empty($this->order['tourists']) ) {
				
			}
			else {
				
			}
		}
		else {
			$result = false;
			$message = $this->setError('AUTH', 1005); // Недостаточно данных (отсутствует order)
			$this->show($message);
		}

		return $result;
	}


	/**
	 *	Поиск ID региона по названию и стране
	 *
	 *	@access private
	 *	@return array
	 */
	private function getRegionIdByName($country_id, $name) {
		$result = array();
		if ( !empty($country_id) and !empty($name) ) {
			$dictionary_data = $this->getRegionsByCountry($country_id);
			if ( !empty($dictionary_data) and is_array($dictionary_data) ) {
				foreach ( $dictionary_data as $key => $value ) {
					$dictionary_data[$key] = mb_strtoupper($value);
				}
				$id = array_search(mb_strtoupper(trim($name)), $dictionary_data);
				if ( !empty($id) ) {
					$result = array('region_id' => $id);
				}
			}
		}

		return $result;
	}

	/**
	 *	Получение регионов в стране
	 *
	 *	@access private
	 *	@return array
	 */
	private function getRegionsByCountry($country_id) {
		$result = array();
		if ( !empty($country_id) ) {
			$regions_full = getDictionaryData($this->DBH, 'regions_full');
			$regions = array();
			foreach ( $regions_full as $key => $value ) {
				if ( $country_id == $value['country_id'] ) {
					$regions[$key] = $value['value'];
				}
			}
		}
		if ( !empty($regions) ) {
			$result = $regions;
		}

		return $result;
	}

	/**
	 *	Получение справочника городов вылета
	 *
	 *	@access private
	 *	@return array
	 */
	private function getDictionary($dictionary) {
		$result = array();
		$res = getDictionaryData($this->DBH, $dictionary);
		if ( !empty($res) ) {
			$result = $res;
		}

		return $result;
	}

	/**
	 *	Поиск ID по названию
	 *
	 *	@access private
	 *	@return array
	 */
	private function getEntryIdByName($dictionary, $field_name,  $name) {
		$result = array();
		if ( !empty($name) ) {
			$dictionary_data = $this->getDictionary($dictionary);

			// fix for operators, accommodation
			if ( 'operators' == $dictionary ) {
				$dictionary_data = getOneArrayFromDictionary($dictionary_data, 'value');
			}

			if ( !empty($dictionary_data) and is_array($dictionary_data) ) {
				foreach ( $dictionary_data as $key => $value ) {
					$dictionary_data[$key] = mb_strtoupper($value);
				}
				$id = array_search(mb_strtoupper(trim($name)), $dictionary_data);
				if ( !empty($id) ) {
					$result = array($field_name => $id);
				}
			}
		}

		return $result;
	}

	/**
	 *	Авторизация / проверка токена
	 *
	 *	@access private
	 *	@return string
	 */
	private function help() {
		return '
				<h4>Создание черновика заявки</h4>
				<p>Меиод: <b>createPreOrder<b></p>
				<p>Входящие данные: </p>
					<table border="1">
						<tr>
							<td style="padding:3px 5px;">token</td>
							<td style="padding:3px 5px;">Токен для доступа к API</td>
						</tr>
						<tr>
							<td style="padding:3px 5px;">agent_id</td>
							<td style="padding:3px 5px;">ID агента</td>
						</tr>
					</table>
				';
	}

	/**
	 *	Авторизация / проверка токена
	 *
	 *	@access public
	 *	@return string
	 */
	public function auth() {
		if ( in_array($this->token, $this->tokens) ) {
			return true;
		}
		return false;
	}

	/**
	 *	Создание черновика заявки
	 *
	 *	@access private
	 *	@return array
	 */
	private function createPreOrder() {
		$result = array('success' => false, 'result' => array('code' => 1004, 'message' => $this->errors[1004]));
		if ( !empty($this->order) and !empty($this->agent_id) ) {

			if ( !empty($this->order['name']) ) {

				// Город отправления
				$city_name = setArrayValue($this->order, 'city');
				$city_id = setArrayValue($this->getEntryIdByName('cities', 'city_id', $city_name), 'city_id');

				// Страна прибытия
				$country_name = setArrayValue($this->order, 'country');
				$country_id = setArrayValue($this->getEntryIdByName('countries', 'country_id', $country_name), 'country_id');

				// Регион
				$region_name = setArrayValue($this->order, 'region');
				$region_id = setArrayValue($this->getRegionIdByName($country_id, $region_name), 'region_id');

				// Аэропорт
				$region_name = setArrayValue($this->order, 'airport');
				$airport_id = setArrayValue($this->getEntryIdByName('airports', 'airport_id', $region_name), 'airport_id');

				// Оператор
				$oper_name = setArrayValue($this->order, 'oper');
				$oper_id = setArrayValue($this->getEntryIdByName('operators', 'operator_id', $oper_name), 'operator_id');

				// Размещение
				$accommodation_name = setArrayValue($this->order, 'accommodation');
				$accommodation_id = setArrayValue($this->getEntryIdByName('accommodation', 'accommodation_id', $accommodation_name), 'accommodation_id');

				// Категория
				$category_name = setArrayValue($this->order, 'category');
				$category_id = setArrayValue($this->getEntryIdByName('categories', 'category_id', $category_name), 'category_id');

				// Питание
				$food_name = setArrayValue($this->order, 'food');
				$food_id = setArrayValue($this->getEntryIdByName('food', 'food_id', $food_name), 'food_id');

				// $comment = setArrayValue($this->order, 'comment') . "\r\n";
				$comment = '';

				$arr_fields = array('gender' 			=> 'Пол', 
									'date_start' 		=> 'Дата начала тура с', 
									'approximate_sum' 	=> 'Стоимость тура', 
									'comment' 			=> 'Комментарий с формы поиска', 
									'nights_count' 		=> 'Ночей', 
									'count_tourists' 	=> 'Туристов', 
									'name' 				=> 'ФИО туриста', 
									'name_eng' 			=> 'ФИО туриста(лат.)', 
									'food' 				=> 'Питание', 
									'hotel' 			=> 'Отель', 
									'city' 				=> 'Город отправления', 
									'country' 			=> 'Страна прибытия', 
									'region' 			=> 'Регион', 
									'airport' 			=> 'Аэропорт', 
									'oper' 				=> 'Оператор', 
									'accommodation' 	=> 'Размещение', 
									'category' 			=> 'Категория'
								);
				foreach ( $this->order as $key => $value ) {
					if ( 'tourists' == $key and !empty($value) and is_array($value) ) {
						$comment .= "Туристы: " . implode("\r\n", $value);
						continue;
					}
					elseif ( 'gender' == $key ) {
						if ( 1 == $value ) {
							$value = 'Мужчина';
						}
						else {
							$value = 'Женщина';
						}
					}
					if ( isset($arr_fields[$key]) ) {
						$comment .= $arr_fields[$key] . ': ' . $value . "\r\n";
					}
					else {
						$comment .= $key . ': ' . $value . "\r\n";
					}
				}

				$sql = 'INSERT INTO ' . $this->sql_preorder . ' 
							SET date = :date, 
								agency_id = :agency_id, 
								name = :name, 
								name_eng = :name_eng, 
								birthday = :birthday, 
								gender = :gender, 
								city_id = :city_id, 
								country_id = :country_id, 
								phone = :phone, 
								email = :email, 
								date_start = :date_start, 
								nights_count = :nights_count, 
								approximate_sum = :approximate_sum, 
								accommodation = :accommodation, 
								channel = :channel, 
								comment = :comment,
								status = 0,
								count_tourists = :count_tourists,
								oper = :oper,
								region = :region,
								airport = :airport,
								category = :category,
								hotel = :hotel,
								food = :food,
								manager_id = :manager_id,
								currency = :currency
							';

				// status - Статус обращения

				// Дата начала тура / вылета
				$date_start = '';
				if ( !empty($this->order['date_start']) ) {
					$date_start = date('Y-m-d', strtotime(trim($this->order['date_start'])));
				}

				$arr = array(	'date' 			=> date('Y-m-d'), 
								'agency_id' 	=> $this->agent_id, 								// id агента
								'name' 			=> trim($this->order['name']), 						// ФИО туриста
								'name_eng' 		=> setArrayValue($this->order, 'name_eng'), 		// ФИО на латинице
								'birthday' 		=> setArrayValue($this->order, 'birthday'), 		// Дата рождения
								'gender' 		=> intval(setArrayValue($this->order, 'gender')), 	// Пол ( 1 - Мужской, 2 - Женский ) id из нашего справочника
								'city_id' 		=> $city_id, 										// Город отправления
								'country_id' 	=> $country_id, 									// Страна прибытия ( id из нашего справочника )
								'phone' 		=> setArrayValue($this->order, 'phone'), 			// Телефон для связи
								'email' 		=> setArrayValue($this->order, 'email'), 			// Email для связи
								'date_start' 	=> $date_start, 									// Дата начала тура / вылета
								'nights_count' 	=> intval(setArrayValue($this->order, 'nights_count')), // Количество ночей
								'approximate_sum' => intval(setArrayValue($this->order, 'approximate_sum')),// Бюджет (руб.), сумма тура
								'accommodation' => $accommodation_id, 										// Размещение
								'channel' 		=> setArrayValue($this->order, 'channel'), 					// Канал обращения
								'comment' 		=> $comment, 					// Комментарий
								'count_tourists' => intval(setArrayValue($this->order, 'count_tourists')), 	// Количество туристов
								'oper' 			=> $oper_id, 												// Туроператор
								'region' 		=> $region_id, 												// Регион
								'airport' 		=> $airport_id, 											// Аэропорт
								'category' 		=> $category_id, 											// Категория отеля
								'hotel' 		=> '', 														// Отель
								'food' 			=> $food_id, 												// Питание
								'manager_id' 	=> setArrayValue($_SESSION, 'manager_id'), 					// Манагер
								'currency' 		=> setArrayValue($this->order, 'currency'), 				// Валюта
							);
				// v3($arr);

				$res = $this->DBH->prepare($sql)->execute($arr);

				if ( !empty($res) ) {
					$lastInsertId = $this->DBH->lastInsertId();
					$result = $this->setSuccess('createPreOrder', array('preorder_id' => $lastInsertId));
				}
				else {
					$result = $this->setError('createPreOrder (res)', 1002);
				}

			}
			else {
				$result = $this->setError('createPreOrder (order[name])', 1003);
			}
		}
		else {
			$result = $this->setError('createPreOrder order', 1005);
		}

		return $result;
	}

	/**
	 *	"Показывает" сообщение
	 *
	 *	@access private
	 *	@return array
	 */
	private function show($message) {
		if ( !empty($message) ) {
			if ( 'json' == $this->datatype ) {
				header("Content-Type: application/json; charset=utf-8");
				echo json_encode($message, 0777);
			}
			elseif ( 'array' == $this->datatype ) {
				var_export($message);
			}
			die();
		}
	}

	/**
	 *	Запишем лог ошибки + вернем массив с текстом ошибки
	 *
	 *	@access public
	 *	@return string
	 */
	public function setError($title, $code = 1004) {
		$result = array('success' => false, 'result' => array('code' => $code, 'message' => $this->errors[$code]));
		$this->setLog(date('d.m.y H:i') . ' ' . $_SERVER['REMOTE_ADDR'] . ' - ERROR ' . $title . '\r\n error code' . $code);

		return $result;
	}

	/**
	 *	Запишем лог ошибки + вернем массив с текстом ошибки
	 *
	 *	@access public
	 *	@return string
	 */
	public function setSuccess($title, $data = '') {
		$result = array('success' => true, 'result' => $data);
		$this->setLog(date('d.m.y H:i') . ' ' . $_SERVER['REMOTE_ADDR'] . ' - ' . $title . ' ' . var_export($data, true));

		return $result;
	}

/**
	 *	Логирование результатов
	 *
	 *	@access private
	 *	@params string
	 *	@return boolean
	 */
	private function setLog($value) {
		$result = FALSE;
		if ( $fp = fopen($this->log_file, 'a+') ) {
			fwrite($fp, "\r\n" . $value);
			fclose($fp);
			$result = TRUE;
		}
		return $result;
	}

}

function v3($arr) {
	echo('<pre style="background:wheat;font-size:13px;border:1px dotted rgb(13, 125, 212);background: rgb(217, 241, 255);padding: 3px 10px;margin:15px;">');
	var_export($arr);
	echo('</pre>');
}
?>