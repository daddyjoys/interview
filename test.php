<?php 
ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
error_reporting(E_ALL | E_STRICT);

die();

$post = array(
				'token' 	=> 'f564067a80f0285be2d5beae1e575614',
				'agent_id' 	=> 9999,
				'data' 	=> array(
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
	// $url = 'http://online-rosstour.ru/api/create';
	// $c = new curl($url);
	// $c->setopt(CURLOPT_RETURNTRANSFER, true);
	// $c->setopt(CURLOPT_URL, $url);
	// $c->setopt(CURLOPT_POST, true);
	// $c->setopt(CURLOPT_POSTFIELDS, $c->asPostString($post));
	// $res = $c->exec();

	$headers = array();
	$headers[] = "Content-type: text/json";
	$headers[] = "charset=utf-8";
	$headers[] = "Content-length: " . strlen(json_encode($post));

	$res = '';
	$url = 'http://online-rosstour.ru/api/create';
	$ch = curl_init();
	$ch = curl_init();
	curl_setopt($ch, CURLOPT_URL, $url);
	curl_setopt($ch, CURLOPT_ENCODING, 'gzip,deflate');
	curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
	curl_setopt($ch, CURLOPT_HEADER, 0);
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
	curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 30);
	curl_setopt($ch, CURLOPT_TIMEOUT, 30);
	curl_setopt($ch, CURLOPT_POST, 1);
	// curl_setopt($ch, CURLOPT_POSTFIELDS, $post);
	curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($post));
	$res = curl_exec($ch);
	v3($res);

	return $res;
}

$request = json_encode(array('method' => 'getOrder'));
$request = array('method' => 'getOrder');
// $request = array('method' => 'create');

$data = queryCurl($request);
v3($data);

/**
 *	test
 *
 *	@access private
 *	@return 
 */
function queryCurl($request = '') {
	// $result = json_encode(array('getOrder', 'params' => array('')));
	$result = '';
	$url = 'http://online-rosstour.ru/api/';

	$headers = array();
	$headers[] = "Content-type: text/json";
	$headers[] = "charset=utf-8";
	$headers[] = "Content-length: " . strlen($request);

	$ch = curl_init();
	curl_setopt($ch, CURLOPT_URL, $url);
	curl_setopt($ch, CURLOPT_ENCODING, 'gzip,deflate');
	curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
	curl_setopt($ch, CURLOPT_HEADER, 0);
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
	curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 30);
	curl_setopt($ch, CURLOPT_TIMEOUT, 30);
	curl_setopt($ch, CURLOPT_POST, 1);
	curl_setopt($ch, CURLOPT_POSTFIELDS, $request);

	$res = curl_exec($ch);
	if ( !empty($res) ) {
		$result = $res;
	}

	return $result;
}

function v3($arr) {
	echo('<pre style="background:wheat;font-size:13px;border:1px dashed rgb(13, 125, 212);background: rgb(217, 241, 255);padding: 3px 10px;margin:15px;">');
	var_export($arr);
	echo('</pre>');
}
?>