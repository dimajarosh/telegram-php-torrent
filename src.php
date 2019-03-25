<?php
	require_once __DIR__.'/vendor/autoload.php';
	require_once 'simple_html_dom.php';
	
	use React\EventLoop\Factory;
	use unreal4u\TelegramAPI\HttpClientRequestHandler;	
	use unreal4u\TelegramAPI\Telegram\Methods\SendMessage;
	use unreal4u\TelegramAPI\Telegram\Methods\SendPhoto;
	use unreal4u\TelegramAPI\Telegram\Types\Custom\InputFile;
	use unreal4u\TelegramAPI\Telegram\Methods\GetUpdates;
	use \unreal4u\TelegramAPI\Abstracts\TraversableCustomType;
	use unreal4u\TelegramAPI\TgLog;
	use Transmission\Transmission;

	function download($url) {
		$ch = curl_init();
		curl_setopt($ch, CURLOPT_URL, $url);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
		$output = curl_exec($ch);
		curl_close($ch);
		$html = new simple_html_dom();
		$html->load($output);
		return $html;
	}

	function correct_date($date) {
		$months = ['Янв' => '01',
				'Фев' => '02',
				'Мар' => '03',
				'Апр' => '04',
				'Мая' => '05',
				'Июн' => '06',
				'Июл' => '07',
				'Авг' => '08',
				'Сен' => '09',
				'Окт' => '10',
				'Ноя' => '11',
				'Дек' => '12'];
		$day = substr($date, 0, 2);
		foreach (array_keys($months) as $m) {
			if(strstr($date, $m)) {
				$month = $months[$m];
			}
		}
		$year = '20'.substr($date, -2);
		$correct = "$year.$month.$day";
		return $correct;
	}

	function insert_data($date, $id, $name, $name_other, $quality, $audio, $value, $img, $genre, $duration, $voice, $about) {
		$query = "INSERT INTO torrents_2018 (data, id_torrent, name_cyrylic, name_original, quality, audio, value, img, genre, duration, voice, about) VALUES ('".$date."', $id, '".$name."', '".$name_other."', '".$quality."', '".$audio."', '".$value."', '".$img."', '".$genre."', '".$duration."', '".$voice."', '".$about."')";
		return $query;
	}

	function parsing_torrents($db, $site, $films) {
		$new = [];
		// echo 1235;
		$count_new_torrent = 0;
		foreach ($films as $key => $film) {
			echo "Processing: $key <br>";
			flush();
			$date = $film->children(0)->plaintext;
			if(count($film->find('a')) > 1) {
				$name = $film->find('a')[1];
			}
			else {
				$name = $film->find('a')[0];
			}
			$id = explode('/', $name->href)[2];
			$link = 'http://'.$site.'/download/'.$id;
			$name = $name->innertext;
			list($name, $detail) = explode('(', $name);
			$check = explode('|', $detail);
			if(count($check) > 1) {
				$audio = trim(array_pop($check));
			}
			else {
				$audio = "";
			}
			$quality = trim(explode(')', $check[0])[1]);
			if(strpos($name, '/')){
				list($name, $name_other) = explode('/', $name, 2);
				$name_other = trim($name_other);
			}
			elseif(strpos($name, '|')){
				list($name, $name_other) = explode('|', $name, 2);
				$name_other = trim($name_other);
			}
			else {
				$name_other = NULL;
			}
			$name = trim($name);
			$value = html_entity_decode($film->children(3)->innertext);
			if(strstr($quality, '720') || strstr($quality, '1080') || strstr($quality, '2160')) {
				$query = "SELECT * FROM torrents_2018 WHERE '{$id}' = id_torrent";
				$result = mysqli_query($db, $query) or die("Помилка пошуку в базу даних" . mysqli_error($db)); 
				if($result->num_rows == 0) {
					$html_detail = download('http://'.$site.'/torrent/'.$id);
					$table = $html_detail->find('table[id=details]');
					$table = $table[0]->children(0)->children(1);
					$img = $table->find('img')[0]->src;
					foreach ($table->find('div') as $div) {
						$div->outertext = '';
					}
					$trs = explode('<br />', $table);
					$genre = "";
					$duration = "";
					$voice = "";
					$about = "";
					foreach ($trs as $index => $tr) {
						if(strstr($tr, 'Жанр')) {
							$genre = trim(strip_tags($tr));
						}
						if(strstr($tr, 'Аудио') ||  strstr($tr, 'Aудио') || strstr($tr, 'Звук')) {
							$voice .= trim(strip_tags($tr));
							$voice .= " \n ";
						}
						if(strstr($tr, 'Продолжительность')) {
							$duration = trim(strip_tags($tr));
						}

						if(strstr($tr, 'О фильме') || strstr($tr, 'Описание')) {
							$about = trim(strip_tags($tr));
							if(mb_strlen(trim(strip_tags($tr))) < 15) {
								$about .= ' ';
								$about .= trim(strip_tags($trs[$index+1]));
							}
						}
					}
					$date = correct_date($date);
					$query = insert_data($date, $id, $name, $name_other, $quality, $audio, $value, $img, $genre, $duration, $voice, $about);
					mysqli_query($db, $query) or die("Помилка запису в базу даних" . mysqli_error($db)); 
					$temping = ["date" => $date,
								"id" => $id,
								"name" => $name,
								"name_other" => $name_other,
								"quality" => $quality,
								"audio" => $audio,
								"value" => $value,
								"img" => $img,
								"genre" => $genre,
								"duration" => $duration,
								"voice" => $voice,
								"about" => $about];
					// array_push($temping, $date, $id, $name, $name_other, $quality, $audio, $value, $img, $genre, $duration, $voice, $about);
					array_push($new, $temping);
					unset($genre, $duration, $voice, $about);
				}
				else {
					$count_new_torrent = 1;
				}
			}
		}
		return array($count_new_torrent, $new);
	}

	function read_pages($site, $year, $db) {
		// $films = [];
		$output = [];
		for($i = 0; $i!=-1; $i++) {
			// echo 123;
			flush();
			$html = download('http://'.$site.'/search/'. $i .'/1/0/0/'.$year);
			// echo 1234;
			// flush();
			echo "Reading: $i pages <br>";
			flush();
			$gai = $html->find('tr[class=gai]');
			if(count($gai) == 0) {
				break;
			}
			$tum = $html->find('tr[class=tum]');
			$films = array_merge($gai, $tum);
			list($count_new_torrent, $result) = parsing_torrents($db, $site, $films);
			$output = array_merge($output, $result);
			echo $count_new_torrent . "<br>";
			if($count_new_torrent == 1) {
				break;
			}
		}
		return $output;
	}

	function create_table($db) {
		$query = "CREATE TABLE torrents_2018 
		(id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
		data DATE,
		id_torrent INT,
		name_cyrylic VARCHAR(255),
		name_original VARCHAR(255),
		quality VARCHAR(255),
		audio VARCHAR(255),
		value VARCHAR(255),
		img VARCHAR(255),
		genre VARCHAR(255),
		duration VARCHAR(255),
		voice TEXT,
		about TEXT
		) ENGINE=InnoDB DEFAULT CHARSET=utf8";
		mysqli_query($db, $query) or die("Error creating table <br>" . mysqli_error($db)); 
	}

	function drop_table($db) {
		$query = "DROP TABLE torrents_2018";
		mysqli_query($db, $query) or die("Error drop table <br>" . mysqli_error($db)); 
	}

	function download_img($url, $id) {
		$ch = curl_init($url);
		$fp = fopen($id.'.png', 'wb');
		curl_setopt($ch, CURLOPT_FILE, $fp);
		curl_setopt($ch, CURLOPT_HEADER, 0);
		curl_exec($ch);
		curl_close($ch);
		fclose($fp);
		return $id.'.png';
	} 

	function send_telegram_films($captions) {
		foreach ($captions as $caption) {
			$loop = Factory::create();
			$tgLog = new TgLog('707146344:AAFxJ6-_tsYKSCACMGsmV6IYH2ftktG0ehk', new HttpClientRequestHandler($loop));
			$sendPhoto = new SendPhoto();
			$sendPhoto->chat_id = 390506633;
			$sendPhoto->photo = new InputFile(download_img($caption["img"],$caption["id"]));
			$str_caption = "";
			foreach ($caption as $key => $value) {
				if($key!= "img")
					$str_caption .= $value."\n";
			}
			$sendPhoto->caption = $str_caption;
			$promise = $tgLog->performApiRequest($sendPhoto);
			$promise->then(
			    function ($response) {
			        // echo '<pre>';
			        // var_dump($response);
			        // echo '</pre>';
			    },
			    function (\Exception $exception) {
			        // Onoes, an exception occurred...
			        echo 'Exception ' . get_class($exception) . ' caught, message: ' . $exception->getMessage();
			    }
			);
			$loop->run();
		}
	}

	function send_message_to_user($msg) {
		$loop = Factory::create();
		$tgLog = new TgLog('707146344:AAFxJ6-_tsYKSCACMGsmV6IYH2ftktG0ehk', new HttpClientRequestHandler($loop));
		$sendMessage = new SendMessage();
		$sendMessage->chat_id = 390506633;
		$sendMessage->text = $msg;//'Hello world to the user... from a specialized getMessage file';
		$promise = $tgLog->performApiRequest($sendMessage);
		$promise->then(
		    function ($response) {
		        // echo '<pre>';
		        // var_dump($response);
		        // echo '</pre>';
		    },
		    function (\Exception $exception) {
		        // Onoes, an exception occurred...
		        echo 'Exception ' . get_class($exception) . ' caught, message: ' . $exception->getMessage();
		    }
		);
		$loop->run();
	}

	function get_messages_telegram($id) {
		$messages = [];
		$loop = Factory::create();
		$tgLog = new TgLog('707146344:AAFxJ6-_tsYKSCACMGsmV6IYH2ftktG0ehk', new HttpClientRequestHandler($loop));
		$getUpdates = new GetUpdates();
		// If using this method, send an offset (AKA last known update_id) to avoid getting duplicate update notifications.
		$getUpdates->offset = $id;
		$updatePromise = $tgLog->performApiRequest($getUpdates);
		$updatePromise->then(
		    function (TraversableCustomType $updatesArray) use(&$messages) {
		    	// var_dump($updatesArray);
		    	// flush();
		        foreach ($updatesArray as $update) {
		            // echo '<pre>';
		            // var_dump($update->update_id);
		            // global $id;
		            // $id = $update->update_id+1;
		            // echo $id;
		            // var_dump($update->message->text);
		            // echo '</pre>';
		            // flush();
		            // send_message_to_user($update->update_id);
		        }
		        // send_message_to_user("\n");
		        // global $messages;
		        $messages = $updatesArray;
		        // echo $messages;
		    },
		    function (\Exception $exception) {
		        // Onoes, an exception occurred...
		        echo 'Exception ' . get_class($exception) . ' caught, message: ' . $exception->getMessage();
		    }
		);
		$loop->run();
		// var_dump($messages);
		return $messages;
	}

	function downloading_torrents($transmission) {
		// echo 123;
		$queue = $transmission->all();
		$output = [];
		foreach ($queue as $torrent) {
			if($torrent->isDownloading()) {
				$str = "";
				$str .= "ID: ".$torrent->getId()."\n".
						"Name: ".$torrent->getName()."\n".
						"Size: ".$torrent->getSize()."\n".
						"Percentages: ".$torrent->getPercentDone()."\n".
						"Speed: ".($torrent->getDownloadRate()/1024)."\n".
						"Time: ".intdiv($torrent->getEta(),3600)." hours ".
						(intdiv($torrent->getEta(),60)%60)." minutes"."\n";
				array_push($output, $str);
			}
		}
		return $output;
	}

	function stopped_torrents($transmission) {
		$queue = $transmission->all();
		$output = [];
		foreach ($queue as $torrent) {
			if($torrent->isStopped()) {
				$str = "";
				$str .= "ID: ".$torrent->getId()."\n".
						"Name: ".$torrent->getName()."\n".
						"Size: ".$torrent->getSize()."\n".
						"Percentages: ".$torrent->getPercentDone()."\n";
				// echo "$str <br>";
				array_push($output, $str);
			}
		}
		return $output;
	}

	function status_torrent($id) {
		switch($id) {
			case 0:
				return "Stopped";
			case 4:
				return "Downloading";
			case 6:
				return "Done";
		}
	}

	function all_torrents($transmission) {
		$queue = $transmission->all();
		$output = [];
		foreach ($queue as $torrent) {
			$str = "";
			$str .= "ID: ".$torrent->getId()."\n".
					"Name: ".$torrent->getName()."\n".
					"Status: ".status_torrent($torrent->getStatus())."\n".
					"Size: ".$torrent->getSize()."\n".
					"Percentages: ".$torrent->getPercentDone()."\n".
					"Speed: ".($torrent->getDownloadRate()/1024)."\n".
					"Time: ".intdiv($torrent->getEta(),3600)." hours ".
					(intdiv($torrent->getEta(),60)%60)." minutes"."\n";
			// echo "$str <br>";
			array_push($output, $str);
		}
		// send_message_to_user(count($output));
		// flush();
		// sleep(100); 
		return $output;
	}

	function set_pause($transmission, int $id) {
		$torrent = $transmission->get($id);
		send_message_to_user(count($torrent));
		$transmission->stop($torrent);
		send_message_to_user("Stopped ".$id);
	}

	function set_start($transmission, int $id) {
		$torrent = $transmission->get($id);
		send_message_to_user(count($torrent));
		$transmission->start($torrent);
		send_message_to_user("Started ".$id);
	}

	function download_torrent($transmission, $id) {
		$torrent = $transmission->add("http://the-rutor.org/download/".$id."/");
		send_message_to_user($torrent->getId()."\n");
	}

	function start_to_download($transmission) {
		$torrents = $transmission->all();
		if(count($torrents) !=0 ) {
			$flag = 0;
			foreach ($torrents as $torrent) {
				if($torrent->isDownloading()) {
					$flag = 1;
				}
			}
			if($flag != 0) {
				$transmission->start($torrents[0]);
			}
		}
	}

	function done_delete_torrent($transmission) {
		$queue = $transmission->all();
		$output = [];
		foreach ($queue as $torrent) {
			if($torrent->getStatus() == 6) {
				$str = "";
				$str .= "ID: ".$torrent->getId()."\n".
					"Name: ".$torrent->getName()."\n".
					"Downloaded";
				array_push($output, $str);
				$transmission->remove($torrent);
			}
		}
		start_to_download($transmission);
		return $output;
	}
?>