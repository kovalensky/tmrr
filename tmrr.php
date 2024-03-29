<?php

//Initialization

// Output buffer
ob_start();
// Language & Settings
tmrr_init();

//Main

	// Check for arguments
	if ($argc <= 2 || !in_array($argv[1], ['e', 'd', 'c'])) {
		die($msg['main']);
	}

		// Get arguments
		$method = &$argv[1];
		$files = array_unique(array_slice($argv, 2));

		// Extract hashes
		if ($method === 'e') {
			foreach ($files as $file) {

				if (!torrent_validity($file)) {
					continue;
				}

				cli_set_process_title("{$msg['cli_hash_extraction']}  —  $file");

				torrent_metainfo($file);
				printFiles($torrent['info']['file tree']); // Recursive array traversal

				echo PHP_EOL, formatText($msg['total_files']), ": $filec (" , formatBytes($torrent_size) , ")\r\n\r\n";
			}

			error_status();
		}

		// Find duplicates; be aware that duplicates inside single .torrent file are also shown
		if ($method === 'd') {
			foreach ($files as $file) {

				if (!torrent_validity($file)) {
					continue;
				}

				$file_tree_array[formatText("  $file:  ")] = $torrent['info']['file tree']; // ﾂ
			}

			if (!empty($file_tree_array)) {
				combine_keys($file_tree_array, $hashes);
				cli_set_process_title($msg['cli_dup_search']);
				compare();
			}

			error_status();
		}

		// Calculate BTMR (BitTorrent Merkle Root) hash
		if ($method === 'c') {
			foreach ($files as $file) {

				if (is_file($file) && !empty($file_size = filesize($file))) {
					$hash = new HasherV2($file);
					$size = formatBytes($file_size);
					echo "\r\n  $file " , formatText("($size)") , "\r\n ", $h_text_string ??= formatText('BTMR'), ": {$hash->root}\r\n\r\n";
				} else {
					$err_status[$file] = $msg['noraw'];
				}
			}

			error_status();
		}


//Functions

		//Parameters:

		// Language changed by — 'tmrr locale [code]' arguments | [code] = ru, en, zh, de)
		// Ansi — 'ansi [switch]' | [switch] = on / off | This disables ANSI escape sequences if your console does not support their correct display
		// Debugging — 'debug [switch]'

		function tmrr_init()
		{

			global $settings, $argv, $msg;

			$version = '2.4g'; // Code name: Gribovskaya (Mushroom Pumpkin)
			$strings = [
				'ru' => [
					'main' => "\r\nСинтаксис:\r\n\r\n\r\n tmrr e <торрент-файл>	*Извлекает хеши файлов из торрентов*\r\n\r\n tmrr d <торрент-файл>	*Находит дубликаты файлов в торрент(ах)*\r\n\r\n tmrr c <ваш-файл>	*Вычисляет хеш существующего файла*\r\n\r\n\r\n** Поддерживается пакетная обработка, как <файл1> <файл2>.. <файлN>.\r\n\r\n---\r\n\r\nВерсия: $version Грибовская\r\nАвтор: Коваленский Константин\r\n\r\n",
					'noraw' => 'Укажите расположение файла, он не должен быть пустым.', 'invalid_torrent' => 'Неопознанный .torrent файл.', 'no_v2' => 'Торрент файл не содержит признаки v2 / гибрида.',
					'hint_v1' => 'Торренты v1 протокола не поддерживают привязку хешей файлов.', 'root_hash' => 'Хеш', 'calculation' => 'Вычисление',
					'torrent_title' => 'Название раздачи', 'file_location' => 'Файл', 'unfinished_files' => 'Необработанные файлы',
					'error_type' => 'Ошибка', 'note' => 'Заметка', 'no_duplicates' => 'Дубликатов не найдено.',
					'dup_found' => 'найден в', 'total_files' => 'Количество файлов', 'total_dup_files' => 'Количество дубликатов',
					'cli_dup_search' => 'Поиск дубликатов', 'cli_hash_extraction' => 'Извлечение хешей', 'magnet_proposal' => 'Создать магнит ссылку для загрузки раздачи без дубликатов? Да ([Enter]) | Нет (n) : ',
					'magnet_copy' => 'Скопируйте магнит ссылку в торрент клиент.', 'created_by_client' => 'Создан', 'lang_change' => 'Язык изменён на: Русский.'
				],
				'en' => [
					'main' => "\r\nPlease use the correct syntax, as:\r\n\r\n\r\n tmrr e <torrent-file>	*Extracts file hashes from .torrent files*\r\n\r\n tmrr d <torrent-file>	*Finds duplicate files within .torrent file(s)*\r\n\r\n tmrr c <your-file>	*Calculates the hash of existing files*\r\n\r\n\r\n** Batch processing is supported, such as <file1> <file2>.. <fileN>.\r\n\r\n---\r\n\r\nVersion: $version\r\nAuthor: Constantine Kovalensky\r\n\r\n",
					'noraw' => 'This is not a valid file, is it empty?', 'invalid_torrent' => 'Invalid torrent file.', 'no_v2' => 'This is an invalid v2 / hybrid torrent.',
					'hint_v1' => 'v1 protocol torrents do not support embedding file hashes.', 'root_hash' => 'Hash', 'calculation' => 'Processing',
					'torrent_title' => 'Name', 'file_location' => 'File', 'unfinished_files' => 'Unprocessed files',
					'error_type' => 'Error type', 'note' => 'Note', 'no_duplicates' => 'No duplicates were found.',
					'dup_found' => 'found in', 'total_files' => 'Total files', 'total_dup_files' => 'Duplicate count',
					'cli_dup_search' => 'Searching for duplicates', 'cli_hash_extraction' => 'Extracting file hashes', 'magnet_proposal' => 'Create a magnet download link without duplicates? Yes ([Enter]) | No (n) : ',
					'magnet_copy' => 'Paste this magnet link into your torrent client.', 'created_by_client' => 'Created by', 'lang_change' => 'Language changed to: English.'
				],
				'zh' => [
					'main' => "\r\n请使用正确的格式, 例如:\r\n\r\n\r\n tmrr e <torrent 文件>	*提取 .torrent 文件的哈希值*\r\n\r\n tmrr d <torrent 文件>	*查找 .torrent 文件中的重复项*\r\n\r\n tmrr c <你的文件>	*计算文件的哈希值*\r\n\r\n\r\n** 支持同时处理多个文件, 使用空格分隔 <文件1> <文件2>.. <文件N>.\r\n\r\n---\r\n\r\n版本号: $version\r\n作者: Constantine Kovalensky\r\n\r\n",
					'noraw' => '文件无效或不存在', 'invalid_torrent' => '无效的 torrent 文件', 'no_v2' => 'torrent 文件不是受支持的 v2 / hybrid 协议',
					'hint_v1' => 'torrent v1 协议不支持内嵌文件哈希值', 'root_hash' => '哈希值', 'calculation' => '处理中',
					'torrent_title' => '文件标题', 'file_location' => '文件名', 'unfinished_files' => '未能处理的文件',
					'error_type' => '错误', 'note' => '注释', 'no_duplicates' => '文件中不包含重复项',
					'dup_found' => '存在于以下文件中', 'total_files' => '总文件数量', 'total_dup_files' => '重复文件数量',
					'cli_dup_search' => '正在查找重复项', 'cli_hash_extraction' => '正在计算文件哈希值', 'magnet_proposal' => '是否创建一个已去重的磁力链? 是 ([Enter]) | 否 (n) : ',
					'magnet_copy' => '请将磁力链粘贴至您的任意客户端中', 'created_by_client' => '来源', 'lang_change' => '当前语言已被修改为: 中文。'
				],
				'de' => [
					'main' => "\r\nBitte benutzen sie das richtige syntax, wie:\r\n\r\n\r\n tmrr e <Torrent-Datei>	*Extrahieren sie die Datei hashes von .torrent Dateien*\r\n\r\n tmrr d <Torrent-Datei>	*Finden sie Dublikate Dateien in .torrent Dateie(n)*\r\n\r\n tmrr c <deine-Datei>	*Berechnet die hash der existierenden Datei*\r\n\r\n\r\n** Beitung vorgehen wird unterstützt, so wie <Datei1> <Datei2>.. <DateiN>.\r\n\r\n---\r\n\r\nVersion: $version\r\nAutor: Constantine Kovalensky\r\n\r\n",
					'noraw' => 'Dies ist keine zulässige Datei, ist sie leer?', 'invalid_torrent' => 'Unzulässige torrent Datei.', 'no_v2' => 'Dies ist eine unzulässige v2 / hybrid torrent.',
					'hint_v1' => 'v1 protokoll torrents unterstützen keine eingebettenen Datei hashes.', 'root_hash' => 'Hash', 'calculation' => 'Prozessvorgang',
					'torrent_title' => 'Name', 'file_location' => 'Datei', 'unfinished_files' => 'Unprozessierte Dateien',
					'error_type' => 'Fehler Typ', 'note' => 'Notiz', 'no_duplicates' => 'Keine Dublikate wurden gefunden.',
					'dup_found' => 'gefunden', 'total_files' => 'Gesamte Dateien', 'total_dup_files' => 'Doppelter Inhalt',
					'cli_dup_search' => 'Suche nach Dublikaten', 'cli_hash_extraction' => 'Extrahiere Datei hashes', 'magnet_proposal' => 'Erstelle einen Magneten download link ohne Dublikate? Ja ([Enter]) | Nein (n) : ',
					'magnet_copy' => 'Kopieren sie diesen magneten link in ihr torrent Programm.', 'created_by_client' => 'Ertellt von', 'lang_change' => 'Sprache geändert zu: Deutsch.'
				]
			];

			// Settings && Constants
			$settings = [

				// User command line settings & variables
				'locale' => (($tmrr_lang = get_cfg_var('tmrr.locale')) && isset($strings[$tmrr_lang])) ? $tmrr_lang : 'en',
				'ansi' => (($tmrr_ansi_output = get_cfg_var('tmrr.ansi')) !== false) ? $tmrr_ansi_output : true,
				'log' => __DIR__ . '/incident.log',

				// Dev variables
				'time_zone' => (($tmrr_timezone = get_cfg_var('tmrr.time_zone')) !== false && !empty($tmrr_timezone)) ? $tmrr_timezone : set_timezone(),
				'api' => tmrr_api(),
				'output' => stream_isatty(STDOUT),

				'debug' => [
				'enabled' => (($tmrr_debugging = get_cfg_var('tmrr.debug')) !== false) ? $tmrr_debugging : false,
				'init_time' => microtime(true)
				]
			];

			if (PHP_MAJOR_VERSION < 8) {
				die(formatText('PHP >= 8 is required.', 176));
			}

			date_default_timezone_set($settings['time_zone']);

			if ($settings['api']) {
				$log_dir = &$settings['log'];
				ini_set('log_errors', 1);
				ini_set('error_log', $log_dir);

				if (filemtime($log_dir) + 2e6 < $settings['debug']['init_time']) {
					$log_size = filesize($log_dir);
					if ($log_size > 5e4) {
						file_put_contents($log_dir, 'Last log reset: ' . date('d M Y | G:i:s e'));
					} elseif ($log_size !== 0) {
						touch($log_dir);
					}
				}
			}

			if (isset($settings[$argv[1] ?? null], $argv[2])) {

				if (!$settings['api']) {
					die(formatText('Changing the settings currently works for standalone builds.', 178));
				}

				$pref = &$argv[1];
				$opt = &$argv[2];

				if ($pref === 'locale') {
					if (isset($strings[$opt])) {
						tmrr_set_preferences('tmrr.locale', $opt);
						die(formatText($strings[$opt]['lang_change'], 70));
					} else {
						die('Undefined language code "' . formatText($opt, 196) . '", supporting: '  . implode(', ', array_map('formatText', array_keys($strings), array_rand(array_flip(range(1, 229)), count($strings))))); // :>
					}
				}

				if ($pref === 'ansi') {
					if ($opt === 'on') {
						tmrr_set_preferences('tmrr.ansi', true);
						$settings['ansi'] = true;
						die(formatText("$pref => $opt", 70));
					} elseif ($opt === 'off') {
						tmrr_set_preferences('tmrr.ansi', false);
						die("$pref => $opt");
					}
				}

				if ($pref === 'log' && $opt === 'export') {
					if (filesize($settings['log']) === 0) {
						die(formatText('The log is empty, nothing to report', 178));
					}
					copy($settings['log'], 'tmrr_incident.log');
					die(formatText('The log has been exported to the current directory'));
				}

				if ($pref === 'debug') {
					if ($opt === 'on') {
						tmrr_set_preferences([
						['tmrr.debug', true],
						['display_errors', true, 'PHP']
						]);
						die(formatText("$pref => $opt", 196));
					} elseif ($opt === 'off') {
						tmrr_set_preferences([
						['tmrr.debug', false],
						['display_errors', false, 'PHP']
						]);
						die(formatText("$pref => $opt", 70));
					}
				}
			}

			$msg = $strings[$settings['locale']];
		}

		// Set preferences via php.ini configuration
		function tmrr_set_preferences($preference, $value = true, $section = 'tmrr')
		{
				$ini_file = __DIR__ . '/php.ini';
				$ini_settings = parse_ini_file($ini_file, true);
				if (is_array($preference)) {
					foreach ($preference as $value) {
						$ini_settings[$value[2] ?? $section][$value[0]] = $value[1];
					}
				} else {
					$ini_settings[$section][$preference] = $value;
				}
				write_ini_file($ini_settings, $ini_file);
		}

		// Detect standalone installation
		function tmrr_api()
		{
			if (get_cfg_var('tmrr.debug') !== false) {
				return true;
			}

			return false;
		}

		// Save a new ini configuration file
		function write_ini_file($ini_data, $file)
		{
			if (!is_array($ini_data) || empty($ini_data)) {
				return false;
			}

			$ini_string = [];

			$process_data = function ($data, $prefix = '') use (&$ini_string, &$process_data)
			{
				foreach ($data as $key => $val) {

					$current_key = $prefix ? "$prefix.$key" : $key;

					if (is_array($val)) {
						$ini_string[] = "[$current_key]";
						$process_data($val, $current_key);
					} elseif ($val === null || $val === '') {
						$ini_string[] = "$key = ";
					} elseif ($val === false) {
						$ini_string[] = "$key = 0";
					} else {
						$ini_string[] = "$key = " . ((is_numeric($val) || $val === true) ? $val : "\"$val\"");
					}
				}
			};

			$process_data($ini_data);

			file_put_contents($file, implode(PHP_EOL, $ini_string));
		}

		// Get && Set system's timezone
		function set_timezone()
		{
			if (!tmrr_api()) {
				return 'UTC';
			}

			$system_time = exec('time /T');

			$timezones = DateTimeZone::listIdentifiers();

			$system_time = DateTimeImmutable::createFromFormat('H:i', $system_time);

			foreach ($timezones as $timezone) {
				$dt = new DateTimeImmutable('now', new DateTimeZone($timezone));
				if ($dt->format('H:i') === $system_time->format('H:i')) {

					tmrr_set_preferences('tmrr.time_zone', $timezone);

					return $timezone;
				}
			}

			tmrr_set_preferences('tmrr.time_zone', 'UTC');

			return 'UTC';
		}

		// @Rhilip's bencode library (modified)
		function bencode_decode($data, &$pos = 0)
		{
			$data_len = strlen($data);
			$start_decode = ($pos === 0);

			if ($start_decode && (!is_string($data) || $data_len === 0)) {
				return null;
			}

			if ($pos >= $data_len) {
				return null;	
			}

			$currentChar = $data[$pos];
			if ($currentChar === 'd') {
				$pos++;
				$return = [];
				while ($currentChar !== 'e') {
					$key = bencode_decode($data, $pos);
					$value = bencode_decode($data, $pos);
					if ($key === null || $value === null) {
						break;
					}
					if (!is_string($key)) {
						break;
					} elseif (isset($return[$key])) {
						break;
					}
					$return[$key] = $value;
				}
				ksort($return, SORT_STRING);
				$pos++;
			} elseif ($currentChar === 'l') {
				$pos++;
				$return = [];
				while ($currentChar !== 'e') {
					$value = bencode_decode($data, $pos);
					if ($value === null) {
						break;
					}
					$return[] = $value;
				}
				$pos++;
			} elseif ($currentChar === 'i') {
				$pos++;
				$digits = strpos($data, 'e', $pos) - $pos;
				$value = substr($data, $pos, $digits);
				$return = $value;
				if ((string)(int)$return !== $return) {
					return null;
				}
				$return = (int)$return;
				$pos += $digits + 1;
			} else {
				$digits = strpos($data, ':', $pos) - $pos;
				$len = substr($data, $pos, $digits);
				if ((string)(int)$len !== $len) {
					return null;
				}
				$len = (int)$len;
				$pos += ($digits + 1);
				$return = substr($data, $pos, $len);
				if (strlen($return) !== $len) {
					return null;
				}
				$pos += $len;
			}

			if ($start_decode && $pos !== $data_len) {
				return null;
			}

			return $return;
		}

		function bencode_encode($data)
		{
			if (is_array($data)) {
				$return = '';
				if (array_is_list($data)) {
					$return .= 'l';
					foreach ($data as $value) {
						$return .= bencode_encode($value);
					}
				} else {
					$return .= 'd';
					ksort($data, SORT_STRING);
					foreach ($data as $key => $value) {
						$return .= bencode_encode((string)$key);
						$return .= bencode_encode($value);
					}
				}
				$return .= 'e';
			} elseif (is_integer($data)) {
				$return = 'i' . $data . 'e';
			} else {
				$return = strlen($data) . ':' . $data;
			}

			return $return;
		}

		// Torrent validity checks
		function torrent_validity($file)
		{
			global $torrent, $msg, $settings, $torrent_size, $filec, $err_status;

			$torrent = [];
			if (is_file($file)) {
				$torrent = bencode_decode(file_get_contents($file));
			}

			if (!isset($torrent['info'])) {
				$err_status[$file] = $msg['invalid_torrent'];

				return false;
			}

			if (($torrent['info']['meta version'] ?? 1) !== 2 || !is_array($torrent['info']['file tree'] ?? null)) { // BEP 0052
				$title = $client_date = $hash_v1 = $note_v1 = '';

				if (isset($torrent['info']['name'])) {
					$t_name = &$torrent['info']['name'];
					if (pathinfo($file, PATHINFO_FILENAME) !== $t_name) {
						$title = PHP_EOL . formatText($msg['torrent_title']) . ": $t_name";
					}
				}

				if (isset($torrent['creation date'], $torrent['created by'])) {
					$date = date('d M Y | G:i:s' . (($settings['time_zone'] === 'UTC') ? ' e' : ''), $torrent['creation date']);
					$client_date =  PHP_EOL . formatText($msg['created_by_client']) . ": {$torrent['created by']} ($date)";
				}

				if (isset($torrent['info']['pieces'])) {
					$info_hash_v1 = hash('sha1', bencode_encode($torrent['info']));
					$hash_v1 = PHP_EOL . formatText($msg['root_hash']) . ": $info_hash_v1";
				}

				if (!isset($torrent['info']['meta version'])) {
					$note_v1 = PHP_EOL . formatText($msg['note']) . ": {$msg['hint_v1']}";
				}

				$err_status[$file . $title . $hash_v1 . $client_date] = $msg['no_v2'] . $note_v1;

				return false;
			}

			$torrent_size = $filec = 0; // Valid torrent, init count variables

			return true;
		}

		// Print torrent name, client and creation date
		function torrent_metainfo($filename)
		{
			global $msg, $settings, $torrent;

			echo "\r\n — ", formatText($msg['file_location']), ": $filename —\r\n";

			if (isset($torrent['info']['name'])) { // BEP 0052
				$t_name = &$torrent['info']['name'];
				if (pathinfo($filename, PATHINFO_FILENAME) !== $t_name) {
					echo ' — ', formatText($msg['torrent_title']), ": $t_name —\r\n";
				}
			}

			if (isset($torrent['creation date'], $torrent['created by'])) {
				$creation_date = date('d M Y | G:i:s' . (($settings['time_zone'] === 'UTC') ? ' e' : ''), $torrent['creation date']);
				echo ' — ', formatText($msg['created_by_client']), ": {$torrent['created by']} ($creation_date) —\r\n";
			}
		}

		// Loop through nested array till the main file-info
		function printFiles($array, $parent = '')
		{
			global $msg, $torrent_size, $filec;

			foreach ($array as $key => $value) {
				$path = "$parent/$key";
				if (!isset($value[''])) {
					printFiles($value, $path);
				} else {
					$length = &$value['']['length'];
					$path = substr($path, 1);
					$size = formatBytes($length);
					$root = bin2hex($value['']['pieces root'] ?? '');
					echo "\r\n  $path ",  formatText("($size)"), "\r\n ", $h_text_string ??= formatText($msg['root_hash']), ": $root\r\n";
					$torrent_size += $length;
					++$filec;
				}
			}
		}

		// Comparator functions

		//Extract and combine hashes in one array
		function combine_keys($array, &$hashes, $parent_key = '')
		{
			global $torrent_size, $filec;

			foreach ($array as $key => $value) {
				$current_path = "$parent_key/$key";
				if (!isset($value[''])) {
					combine_keys($value, $hashes, $current_path);
				} else {
					$length = &$value['']['length'];
					$size = formatBytes($length);
					$root = bin2hex($value['']['pieces root'] ?? '');
					$hashes[substr($current_path, 1)] = [
						'hash' => "$root ($size)",
						'size' => $length,
						'pos' => $filec
					];
					$torrent_size += $length;
					++$filec;
				}
			}
		}

		//Create an array and find duplicates
		function compare()
		{
			global $msg, $hashes, $torrent, $torrent_size, $filec, $magnet, $settings, $argv, $argc;

			$t_size = formatBytes($torrent_size);
			$single_torrent = ($argc < 4);
			$dups_size = $dup_hashes = $filed = 0;

			if (!empty($hashes)) {

				foreach ($hashes as $key => $value) {
					$hash = &$value['hash'];
					if (isset($keys[$hash])) {
						$keys[$hash][] = $key;
						$dups_size += $value['size'];
					} else {
						$keys[$hash] = [$key];
						$magnet['indices'][] = $value['pos'];
					}
				}
			}

			if ($single_torrent) {
				torrent_metainfo($argv[2]);
			}

			if (!empty($keys)) {

				foreach ($keys as $key => $value) {
					$count = count($value);
					if ($count > 1) {
						$dups = implode(PHP_EOL, $value);
						echo "\r\n {$msg['root_hash']} $key {$msg['dup_found']}:\r\n\r\n$dups\r\n\r\n";
						$filed += $count;
						++$dup_hashes;
					}
				}
			}

			if (empty($dup_hashes)) {
				echo "\r\n " , formatText($msg['no_duplicates'], 178) , "\r\n\r\n", formatText($msg['total_files']), ": $filec ($t_size)\r\n";
			} else {
				$d_count = $filed - $dup_hashes;
				$d_sizes = formatBytes($dups_size);

				echo formatText($msg['total_files']), ": $filec ($t_size)\r\n" , formatText($msg['total_dup_files']), ": $d_count ($d_sizes)";

				if ($single_torrent) {

					if (!empty($torrent_size)) {
						$percentage = ($dups_size / $torrent_size) * 100;
						$precision = ($percentage >= 0.01) ? 2 : (!empty($percentage) ? abs(floor(log10($percentage))) : 0);
						echo ' | ' , number_format($percentage, $precision) , "%\r\n";
					}

					ob_end_flush();


					// Magnet handler
					cli_set_process_title($msg['magnet_proposal']);

					$cli_output = ($settings['output'] && $settings['ansi']);

					if ($cli_output) {
						echo "\r\n	" , formatText($msg['magnet_proposal'], 178) , "\r\n	";
					}

					$clean_cli = "\033[2A" . "\033[2K"; // Escape symbols for cleaning output

					$handle = fgets(fopen('php://stdin', 'r'));

					if ($handle === PHP_EOL) {

						$magnetL = magnet_gen();

						if ($cli_output) {
							echo $clean_cli;
						}

						echo PHP_EOL , formatText($magnetL, 70) , PHP_EOL;

						if ($settings['output']) {
							if (PHP_OS_FAMILY === 'Windows') {
								$command = 'start "" "' . $magnetL . '"';
								if (strlen($command) <= 8191) { // Windows command length limit
									exec($command);
								} else {
									echo "\r\n " , formatText($msg['magnet_copy'], 178) , PHP_EOL;
								}
							} elseif (PHP_OS_FAMILY === 'Linux') {
								$command = 'xdg-open "" "' . $magnetL . '"';
								exec($command);
							}
						}
					} elseif ($cli_output) {
						echo $clean_cli , "\033[1B" , "\033[2K";
					}
				} else {
					echo PHP_EOL;
				}
			}
		}

		// Generate a magnet link without duplicates
		function magnet_gen()
		{
			global $magnet, $torrent;

			$name = $trackers = $web_seeds = $info_hash_v1 = '';

			$indices = '&so=' . formatSeq($magnet['indices']); // BEP 0053

			if (isset($torrent['announce']) && !isset($torrent['announce-list'])) {
				$trackers = '&tr=' . urlencode($torrent['announce']);
			}

			if (isset($torrent['announce-list'])) {
				$tracker_list = &$torrent['announce-list'];
				if (count($tracker_list[0]) > 1) {
					$trackers .= '&tr=' . implode('&tr=', array_map('urlencode', $tracker_list[0]));
				} else {
					foreach ($tracker_list as $value) {
						$trackers .= '&tr=' . urlencode($value[0]); // For tracker lists created with tiers (priorities)
					}
				}
			}

			if (isset($torrent['url-list'])) {
				$url_list = &$torrent['url-list'];
				if (!is_array($url_list)) {
					$web_seeds = '&ws=' . urlencode($url_list);
				} else {
					foreach ($url_list as $value) {
						$web_seeds .= '&ws=' . urlencode($value);
					}
				}
			}

			if (isset($torrent['info']['name'])) {
				$name = '&dn=' . urlencode($torrent['info']['name']);
			}

			$bencoded_string = bencode_encode($torrent['info']);

			if (isset($torrent['info']['pieces'])) { // Hybrid torrent
				$info_hash_v1 = '&xt=urn:btih:' . hash('sha1', $bencoded_string);
			}

			$info_hash_v2 = hash('sha256', $bencoded_string);
			$hash = 'magnet:?xt=urn:btmh:1220' . $info_hash_v2 . $info_hash_v1;

			return $hash . $name . $trackers . $web_seeds . $indices;
		}

		// Individual files Merkle computations
		class HasherV2
		{

			public function __construct($path) // 16KiB blocks
			{
				!ob_get_level() || ob_end_flush();
				$this->BLOCK_SIZE = 2**14; // 16KiB
				$this->HASH_LENGTH = 32;
				$this->path = $path;
				$this->root = null;
				$this->layer_hashes = [];
				$this->padding = str_repeat("\x00", $this->HASH_LENGTH);
				$this->sync = time();
				$fd = fopen($this->path, 'rb');
				$this->process_file($fd, fstat($fd)['size'], $this->path);
				fclose($fd);
			}

			private function process_file($fd, $file_size, $filename = '')
			{
				global $msg;

				while (!feof($fd)) {
					$blocks = [];
					$leaf = fread($fd, $this->BLOCK_SIZE);

					if (time() > $this->sync) { // Show percentage status
						$percent = number_format((ftell($fd) / $file_size) * 100);
						cli_set_process_title("{$msg['calculation']} $percent%  —  $filename");
						$this->sync = time();
					}

					$blocks[] = hash('sha256', $leaf, true);
					$blocks_count = count($blocks);

					if ($blocks_count !== 1) {
						$remaining = 1 - $blocks_count;
						if (count($this->layer_hashes) === 0) {
							$power2 = next_power_2($blocks_count);
							$remaining = $power2 - $blocks_count;
						}
						$padding = array_fill(0, 1, $this->padding);
						$blocks = [...$blocks, ...array_slice($padding, 0, $remaining)];
					}
					$layer_hash = $this->merkle_root($blocks);
					$this->layer_hashes[] = $layer_hash;
				}

				$this->calculate_root();
			}

			private function calculate_root()
			{
				$hashes = count($this->layer_hashes);
				if ($hashes > 1) {
					$pow2 = $this->next_power_2($hashes);
					$remainder = $pow2 - $hashes;
					$pad_piece = array_fill(0, 1, $this->padding);
					while ($remainder > 0) {
						$this->layer_hashes[] = $this->merkle_root($pad_piece);
						$remainder--;
					}
				}

				$this->root = bin2hex($this->merkle_root($this->layer_hashes));
			}

			private function merkle_root($blocks)
			{
					while (count($blocks) > 1) {
						$blocks_count = count($blocks);
						$next_level_blocks = [];
						$i = 0;
						while ($i < $blocks_count) {
							$x = $blocks[$i];
							$y = ($i + 1 < $blocks_count) ? $blocks[$i + 1] : '';
							$next_level_blocks[] = hash('sha256', $x . $y, true);
							$i += 2;
						}
						$blocks = $next_level_blocks;
					}

					return $blocks[0];
			}

			private function next_power_2($value)
			{
				if (!($value & ($value - 1)) && $value) {
					return $value;
				}
				$start = 1;
				while ($start < $value) {
					$start <<= 1;
				}

				return $start;
			}
		}

		// Represent bytes
		function formatBytes($bytes, $precision = 2)
		{
			static $units = ['B', 'KB', 'MB', 'GB', 'TB'];

			$pow = min(floor(($bytes ? log($bytes) : 0) / log(1024)), 4);

			return round($bytes / (1024 ** $pow), $precision) . " $units[$pow]";
		}

		// Format coloured text
		function formatText($text, $colour = 250)
		{
			global $settings;

			if ($settings['output'] && $settings['ansi']) {
				return "\033[38;5;$colour" . "m$text\033[0m";
			}

			return $text;
		}

		// Represent sequences
		function formatSeq($numbers)
		{
			$sequences = [];
			$sequenceStart = null;
			$count = count($numbers);

			for ($index = 0; $index < $count; ++$index) {
				$number = $numbers[$index];

				if ($index === 0 || $number - $numbers[$index - 1] !== 1) {
					$sequenceStart = $number;
				}

				if ($index === $count - 1 || $numbers[$index + 1] - $number !== 1) {
					if ($sequenceStart !== $number) {
						$sequences[] = "$sequenceStart-$number";
					} else {
						$sequences[] = $number;
					}
				}
			}

			return implode(',', $sequences);
		}

		// Error & Debug handler
		function error_status()
		{
			global $msg, $err_status, $settings;

			if (!empty($err_status) && $settings['output']) {

				echo "\r\n\r\n--- " , formatText($msg['unfinished_files'], 196) , ": ---\r\n";

				foreach ($err_status as $key => $value) {

					echo PHP_EOL, formatText($msg['file_location']), ": $key\r\n", formatText($msg['error_type']), ": $value\r\n\r\n";

				}
			}

			if ($settings['debug']['enabled'] && $settings['output']) {
				echo PHP_EOL , formatText('Time', 130), ': ', number_format(($t = microtime(true) - $settings['debug']['init_time']), ($t < 0.01 ? abs(floor(log10($t))) : 1)), 's | ', formatText('Memory', 130), ': ' , formatBytes(memory_get_peak_usage()), PHP_EOL;
			}

			die();
		}