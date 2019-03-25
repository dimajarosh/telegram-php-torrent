<?php 
	require_once 'connection.php'; 
	require_once __DIR__.'/vendor/autoload.php';
	require_once 'simple_html_dom.php';
	require_once 'src.php';
	use Transmission\Transmission;

	$connection_mysql = mysqli_connect($host, $user, $password, $database) 
	    or die("Помилка доступу до бази даних" . mysqli_error($connection_mysql));
	

	ini_set('error_reporting', E_ALL);
	ini_set('display_errors', 1);
	ini_set('display_startup_errors', 1);
	ini_set('max_execution_time', 0);

	$site = 'the-rutor.org';
	$year = 2018;
	// create_table($connection_mysql);
	$transmission = new Transmission('0.0.0.0', 9091);
	$session = $transmission->getSession();

	$session->setDownloadDir('/home/dmytro/Завантаження');
	$session->setIncompleteDir('/home/dmytro/Завантаження');
	$session->setIncompleteDirEnabled(true);
	$session->save();

	$update = mktime(18,34,0);
	if($update <= time()+2) {
		$update += 86400;
	}
	$id = 285665237;
	while(1) {
		if($update <= time()) {
			$new_torrent = read_pages($site, $year, $connection_mysql);
			if(count($new_torrent) != 0) {
				send_telegram_films($new_torrent);
			}
			$update += 86400;
			send_message_to_user("Updating was done");
		}
		if(mktime(1,0,0) < time() && mktime(6,0,0) > time()) {
			start_to_download($transmission);
		}
		$removed = done_delete_torrent($transmission);
		if(count($removed) != 0) {
			foreach ($removed as $torrent) {
				send_message_to_user($torrent);
			}
		}

		$output = get_messages_telegram($id);
		foreach ($output as $message) {
			if(strstr($message->message->text, "/news") !== FALSE) {
				$new_torrent = read_pages($site, $year, $connection_mysql);
				if(count($new_torrent) != 0) {
					send_telegram_films($new_torrent);
				}
			}
			elseif(strstr($message->message->text, "/download ") !== FALSE) {
				$id_download = preg_replace('/[^0-9]/', '', $message->message->text);
				download_torrent($transmission, $id_download);			
			}
			elseif(strstr($message->message->text, "/downloading") !== FALSE) {
				$msgs_downloading = downloading_torrents($transmission);
				foreach ($msggs_downloading as $msg) {
					send_message_to_user($msg);
				}
			}
			elseif(strstr($message->message->text, "/stopped") !== FALSE) {
				$msgs_stopped = stopped_torrents($transmission);
				foreach ($msgs_stopped as $msg) {
					send_message_to_user($msg);
				}
			}
			elseif(strstr($message->message->text, "/p ") !== FALSE) {
				$id_p = preg_replace('/[^0-9]/', '', $message->message->text);
				set_pause($transmission, $id_p);
			}
			elseif(strstr($message->message->text, "/all") !== FALSE) {
				$msgs_all = all_torrents($transmission);
				// send_message_to_user(count($msgs_all));
				foreach ($msgs_all as $msg) {
					send_message_to_user($msg);
				}
			}
			elseif(strstr($message->message->text, "/d ") !== FALSE) {
				$id_d = preg_replace('/[^0-9]/', '', $message->message->text);
				set_start($transmission, $id_d);
			}
			else {
				send_message_to_user("Incorrect command");
			}
			$id = $message->update_id+1;
		}
		// sleep(5);
	}
	// $transmission->add("http://the-rutor.org/download/687618/");
	// set_start($transmission, 3);
	// $torrent = $transmission->all();
	// var_dump($torrent[0]->isFinished());
	// downloading_torrents($transmission);
	// pausing_torrents($transmission);
	// all_torrents($transmission);

	// $new_torrent = read_pages($site, $year, $connection_mysql);
	// if(count($new_torrent) != 0) {
	// 	send_telegram_films($new_torrent);
	// }
	
	// drop_table($connection_mysql);
	// create_table($connection_mysql);

	// $query = "SELECT * FROM torrents_2018 WHERE id_torrent=687352";
	// $result = mysqli_query($connection_mysql, $query) or die("Ошибка " . mysqli_error($connection_mysql));
	// $result = $result->fetch_assoc();
	// $loop = Factory::create();
	// $tgLog = new TgLog('707146344:AAFxJ6-_tsYKSCACMGsmV6IYH2ftktG0ehk', new HttpClientRequestHandler($loop));
	// $sendMessage = new SendMessage();
	// $sendMessage->chat_id = 390506633;
	// $sendMessage->text = $result["name_cyrylic"]. "\n". $result["data"];//'Hello world to the user... from a specialized getMessage file';
	// $promise = $tgLog->performApiRequest($sendMessage);
	// $promise->then(
	//     function ($response) {
	//         echo '<pre>';
	//         var_dump($response);
	//         echo '</pre>';
	//     },
	//     function (\Exception $exception) {
	//         // Onoes, an exception occurred...
	//         echo 'Exception ' . get_class($exception) . ' caught, message: ' . $exception->getMessage();
	//     }
	// );
	// $loop->run();
	// $query = "SELECT * FROM torrents_2018 WHERE id_torrent=687585";
	// $result = mysqli_query($connection_mysql, $query) or die("Ошибка " . mysqli_error($connection_mysql));
	// $result = $result->fetch_assoc();
	// $caption = "ID: ".$result['id_torrent']."\n".
	// 			"Name: ".$result['name_cyrylic']."\n".
	// 			"Quality: ".$result['quality']."\n".
	// 			"Audio: ".$result['audio']."\n".
	// 			"Value: ".$result['value'];
	// $ch = curl_init($result['img']);
	// $fp = fopen('logo.png', 'wb');
	// curl_setopt($ch, CURLOPT_FILE, $fp);
	// curl_setopt($ch, CURLOPT_HEADER, 0);
	// curl_exec($ch);
	// curl_close($ch);
	// fclose($fp);
	// unset($fp);

	// $loop = Factory::create();
	// $tgLog = new TgLog('707146344:AAFxJ6-_tsYKSCACMGsmV6IYH2ftktG0ehk', new HttpClientRequestHandler($loop));
	// $sendPhoto = new SendPhoto();
	// $sendPhoto->chat_id = 390506633;
	// $sendPhoto->photo = new InputFile('logo.png');
	// $sendPhoto->caption = $caption;
	// $promise = $tgLog->performApiRequest($sendPhoto);
	// $promise->then(
	//     function ($response) {
	//         echo '<pre>';
	//         var_dump($response);
	//         echo '</pre>';
	//     },
	//     function (\Exception $exception) {
	//         // Onoes, an exception occurred...
	//         echo 'Exception ' . get_class($exception) . ' caught, message: ' . $exception->getMessage();
	//     }
	// );
	// $loop->run();
	// unset($loop);
	// unlink('logo.png');
	mysqli_close($connection_mysql);

?>
