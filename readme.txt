Создание черновика заявки
url: http://online-rosstour.ru/api/

	Метод: createPreOrder
	Для вызова метода посылаем запрос на url: http://online-rosstour.ru/api/createPreOrder

		Входящие данные:
			token - Токен для доступа к API. String
			agent_id - ID агента системы бронирования. Integer

			order - массив данных для создания заявки. Array
				name 				-  ФИО туриста. Обязательный параметр. String
				name_eng 			-  ФИО на латинице. Не обязательный. String
				birthday 			-  Дата рождения. Не обязательный. String
				gender 				-  Пол ( 1 - Мужской, 2 - Женский ). Не обязательный. Integer
				city_id 			-  Город отправления ( id из нашего справочника ). Не обязательный. Integer
				country_id 			-  Страна прибытия ( id из нашего справочника ). Не обязательный. Integer
				phone 				-  Телефон туриста для связи. Не обязательный. String
				email 				-  Email туриста для связи. Не обязательный. String
				date_start 			-  Дата начала тура / вылета. Не обязательный. String
				nights_count 		-  Количество ночей. Не обязательный. Integer
				approximate_sum 	-  Бюджет (руб.) или сумма тура. Не обязательный. Integer
				accommodation 		-  Размещение (id размещения из нашего справочника). Не обязательный. Integer
				channel 			-  Канал обращения. Не обязательный. Integer
				comment 			-  Комментарий. Не обязательный. String
				count_tourists 		-  Количество туристов. Не обязательный. Integer
				oper 				-  Туроператор ( id из нашего справочника ). Не обязательный. Integer
				region 				-  Регион ( id из нашего справочника ). Не обязательный. Integer
				airport 			-  Аэропорт ( id из нашего справочника ). Не обязательный. Integer
				category 			-  Категория отеля ( id из нашего справочника ). Не обязательный. Integer
				hotel 				-  Отель ( id из нашего справочника ). Не обязательный. Integer
				food 				-  Питание ( id из нашего справочника ). Не обязательный. Integer
				manager_id 			-  Менеджер. Не обязательный. Integer
				currency 			-  Валюта (EUR, USD, руб). Не обязательный. String

		Ответ сервера имеет вид: '{ "success": boolean, "text": string }', где success - флаг успешности выполнения запроса, text - id созданного черновика
		Пример: { "success": true, "text": 1065 }

Пример:

	$post = array(
					'token' 	=> 'f564067a80f0285be2d5beae1e575614',
					'agent_id' 	=> 9999,
					'order' 	=> array(
											'name' 			=> 'Верига',
											'name_eng' 		=> 'Veriga',
											'gender' 		=> 1,
											'birthday' 		=> '22.01.1977',
											'date_start' 	=> '11.03.2017',
											'approximate_sum' => 20000,
											'phone' 		=> '+79193897066',
											'email' 		=> 'it@rosstour.ru',
											'count_tourists' => 1,
											'oper' 			=> 11,
											'country' 		=> 69,
											'region' 		=> 310,
											'airport' 		=> 134,
											'city' 			=> 35,
											'category'		=> 4,
											'hotel' 		=> 71022,
											'accommodation' => 1,
											'food' 			=> 2,
											'comment' 		=> 'comment',
											'nights_count' 	=> 1,
										),
				);
	send($post);

	function send($post) {
		$url = 'http://online-rosstour.ru/api/createPreOrder';
		$c = new curl($url);
		$c->setopt(CURLOPT_RETURNTRANSFER, true);
		$c->setopt(CURLOPT_URL, $url);
		$c->setopt(CURLOPT_POST, true);
		$c->setopt(CURLOPT_POSTFIELDS, $c->asPostString($post));
		$res = $c->exec();
		
		return $res;
	}
