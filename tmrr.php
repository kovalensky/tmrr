<?php

//Initialization

// Output buffer
ob_start();
// Language
$msg = lang();

//Main

	// Check for arguments
	if ($argc <= 2 || !in_array($argv[1], ['e', 'd', 'c'])) {
		die($msg['main']);
	}

		// Extract hashes
		if ($argv[1] === 'e') {
			foreach (array_slice($argv, 2) as $file) {

				$torrent = [];
				if (is_file($file)) {
					$torrent = bencode_decode(file_get_contents($file));
				}

				if (!tvalidity_check($file)) {
					continue;
				}

				cli_set_process_title("{$msg['cli_hash_extraction']}  —  $file");

				torrent_metainfo($file);
				printFiles($torrent['info']['file tree']); // Recursive array traversal

				echo "\r\n{$msg['total_files']}: $filec (" , formatBytes($torrent_size) , ")\r\n\r\n";
			}

			error_status();
		}

		// Find duplicates; be aware that duplicates inside single .torrent file are also shown
		if ($argv[1] === 'd') {
			foreach (array_slice($argv, 2) as $file) {

				$torrent = [];
				if (is_file($file)) {
					$torrent = bencode_decode(file_get_contents($file));
				}

				if (!tvalidity_check($file)) {
					continue;
				}

				$file_tree_array["  $file:  "] = $torrent['info']['file tree'];
			}

			if (!empty($file_tree_array)) {
				combine_keys($file_tree_array, $hashes);
				cli_set_process_title($msg['cli_dup_search']);
				compare();
			}

			error_status();
		}

		// Calculate BTMR (BitTorrent Merkle Root) hash
		if ($argv[1] === 'c') {
			foreach (array_slice($argv, 2) as $file) {

				if (is_file($file) && !empty($size = filesize($file))) {
					$hash = new HasherV2($file);
					echo "\r\n  $file (" , formatBytes($size) , ")\r\n {$msg['root_hash']}: {$hash->root}\r\n\r\n";
				}
				else{
					$err_status[$file] = $msg['noraw'];
				}
			}

			error_status();
		}


//Functions

		// Language option (Activated by 'locale [code]' arguments. [code] = en | ru), works in Windows builds
		function lang()
		{
			global $argv, $settings;

			$version = '2.3g'; // Code name: Gribovskaya (Mushroom Pumpkin)
			$strings = [
				'ru' => [
					'main' => "\r\nСинтаксис:\r\n\r\n\r\n tmrr e <торрент-файл>	*Извлекает хеши файлов из торрентов*\r\n\r\n tmrr d <торрент-файл>	*Находит дубликаты файлов в торрент(ах)*\r\n\r\n tmrr c <ваш-файл>	*Вычисляет хеш существующего файла*\r\n\r\n\r\n** Поддерживается пакетная обработка, как <файл1> <файл2>.. <файлN>.\r\n\r\n---\r\n\r\nВерсия: $version Грибовская\r\nАвтор: Коваленский Константин\r\n\r\n",
					'noraw' => 'Укажите расположение файла, он не должен быть пустым.',
					'invalid_torrent' => 'Неопознанный .torrent файл.',
					'no_v2' => 'Торрент файл не содержит признаки v2 / гибрида.',
					'hint_v1' => 'Торренты v1 протокола не поддерживают привязку хешей файлов.',
					'root_hash' => 'Хеш',
					'calculation' => 'Вычисление',
					'torrent_title' => 'Название раздачи',
					'file_location' => 'Файл',
					'unfinished_files' => 'Необработанные файлы',
					'error_type' => 'Ошибка',
					'note' => 'Заметка',
					'no_duplicates' => 'Дубликатов не найдено.',
					'dup_found' => 'найден в',
					'total_files' => 'Количество файлов',
					'total_dup_files' => 'Количество дубликатов',
					'cli_dup_search' => 'Поиск дубликатов',
					'cli_hash_extraction' => 'Извлечение хешей',
					'magnet_proposal' => 'Создать магнит ссылку для загрузки раздачи без дубликатов? Да (d) | Нет (n) : ',
					'magnet_copy' => 'Скопируйте магнит ссылку в торрент клиент.',
					'created_by_client' => 'Создан',
					'lang_change' => 'Язык изменён на русский.'
				],
				'en' => [
					'main' => "\r\nPlease use the correct syntax, as:\r\n\r\n\r\n tmrr e <torrent-file>	*Extracts file hashes from .torrent files*\r\n\r\n tmrr d <torrent-file>	*Finds duplicate files within .torrent file(s)*\r\n\r\n tmrr c <your-file>	*Calculates the hash of existing files*\r\n\r\n\r\n** Batch processing is supported, such as <file1> <file2>.. <fileN>.\r\n\r\n---\r\n\r\nVersion: $version\r\nAuthor: Constantine Kovalensky\r\n\r\n",
					'noraw' => 'This is not a valid file, is it empty?',
					'invalid_torrent' => 'Invalid torrent file.',
					'no_v2' => 'This is an invalid v2 / hybrid torrent.',
					'hint_v1' => 'v1 protocol torrents do not support embedding file hashes.',
					'root_hash' => 'Hash',
					'calculation' => 'Processing',
					'torrent_title' => 'Name',
					'file_location' => 'File',
					'unfinished_files' => 'Unprocessed files',
					'error_type' => 'Error type',
					'note' => 'Note',
					'no_duplicates' => 'No duplicates were found.',
					'dup_found' => 'found in',
					'total_files' => 'Total files',
					'total_dup_files' => 'Duplicate count',
					'cli_dup_search' => 'Searching for duplicates',
					'cli_hash_extraction' => 'Extracting file hashes',
					'magnet_proposal' => 'Create a magnet download link without duplicates? Yes (y) | No (n) : ',
					'magnet_copy' => 'Paste this magnet link into your torrent client.',
					'created_by_client' => 'Created by',
					'lang_change' => 'Language changed to English.'
				],
				'zh' => [
					'main' => "\r\n请使用正确的格式, 例如:\r\n\r\n\r\n tmrr e <torrent 文件>  *计算 .torrent 文件的哈希值*\r\n\r\n tmrr d <torrent 文件>  *查找 .torrent 文件中的重复项*\r\n\r\n tmrr c <你的文件>	*计算文件的哈希值*\r\n\r\n\r\n** 支持同时处理多个文件, 使用空格分隔 <文件1> <文件2>.. <文件N>.\r\n\r\n---\r\n\r\n版本号: $version\r\n作者: Constantine Kovalensky\r\n\r\n",
					'noraw' => '文件无效或不存在',
					'invalid_torrent' => '无效的 torrent 文件',
					'no_v2' => 'torrent 文件不是受支持的 v2 / hybrid 协议',
					'hint_v1' => 'torrent v1 协议不支持内嵌文件哈希值',
					'root_hash' => '哈希值',
					'calculation' => '处理中',
					'torrent_title' => '文件标题',
					'file_location' => '文件名',
					'unfinished_files' => '未能处理的文件',
					'error_type' => '错误',
					'note' => '注释',
					'no_duplicates' => '文件中不包含重复项',
					'dup_found' => '存在于以下文件中',
					'total_files' => '总文件数量',
					'total_dup_files' => '重复文件数量',
					'cli_dup_search' => '正在查找重复项',
					'cli_hash_extraction' => '正在计算文件哈希值',
					'magnet_proposal' => '是否创建一个已去重的磁力链? 是 (y) | 否 (n) : ',
					'magnet_copy' => '请将磁力链粘贴至您的任意客户端中',
					'created_by_client' => '来源',
					'lang_change' => '当前语言已被修改为: 中文'
				]
			];

			$settings = [
			'lang' => (($tmrr_lang = get_cfg_var('tmrr.language')) && array_key_exists($tmrr_lang, $strings)) ? $tmrr_lang : 'en',
			'colour' => (($tmrr_colour = get_cfg_var('tmrr.coloured_mode')) !== false) ? $tmrr_colour : true
			];

			// Great and mighty, free and sincere Russian language.
			// I.S. Turgenev's poem (1882)

			if (($argv[1] ?? null) === 'locale' && isset($argv[2])) {
				$ini_file = __DIR__ . '/php.ini';

				if (is_file($ini_file)) {
					$ini_settings = parse_ini_file($ini_file, true);
					if (array_key_exists($argv[2], $strings)) {

						$ini_settings['tmrr']['tmrr.language'] = $argv[2];
						write_ini_file($ini_settings, $ini_file);
						die(formatText($strings[$argv[2]]['lang_change'], 32));

					}
					else{
						die(formatText('Invalid language code', 31));
					}
				}
		}

			return $strings[$settings['lang']];
		}

		// Save a new ini configuration file
		function write_ini_file($ini_data, $file) {

			if (!is_array($ini_data) || empty($ini_data)) {
				return false;
			}

			$ini_string = [];

			$process_data = function ($data, $prefix = '') use (&$ini_string, &$process_data) {
				foreach ($data as $key => $val) {
					$current_key = $prefix ? "$prefix.$key" : $key;

					if (is_array($val)) {
						$ini_string[] = "[$current_key]";
						$process_data($val, $current_key);
					}
					elseif ($val === null || $val === false || $val === '') {
						$ini_string[] = "$key = 0";
					}
					else {
						$ini_string[] = "$key = " . (is_numeric($val) ? $val : "$val");
					}
				}
			};

			$process_data($ini_data);

			file_put_contents($file, implode(PHP_EOL, $ini_string));
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
					}
					elseif (isset($return[$key])) {
						break;
					}
					$return[$key] = $value;
				}
				ksort($return, SORT_STRING);
				$pos++;
			}
			elseif ($currentChar === 'l') {
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
			}
			elseif ($currentChar === 'i') {
				$pos++;
				$digits = strpos($data, 'e', $pos) - $pos;
				$value = substr($data, $pos, $digits);
				$return = checkInteger($value);
				if ($return < 0) {
					return null;
				}
				$pos += $digits + 1;
			}
			else{
				$digits = strpos($data, ':', $pos) - $pos;
				$len = checkInteger(substr($data, $pos, $digits));
				if ($len < 0) {
					return null;
				}
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

		function checkInteger($value)
		{
			$int = (int)$value;
			if ((string)$int !== $value) {
				return -1;
			}
			return $int;
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
				}
				else{
					$return .= 'd';
					ksort($data, SORT_STRING);
					foreach ($data as $key => $value) {
						$return .= bencode_encode((string)$key);
						$return .= bencode_encode($value);
					}
				}
				$return .= 'e';
			}
			elseif (is_integer($data)) {
				$return = 'i' . $data . 'e';
			}
			else{
				$return = strlen($data) . ':' . $data;
			}

			return $return;
		}

		// Torrent validity checks
		function tvalidity_check($file)
		{
			global $torrent, $msg, $torrent_size, $filec, $err_status;

			if (!isset($torrent['info'])) {

				$err_status[$file] = $msg['invalid_torrent'];

				return false;
			}

			if (($torrent['info']['meta version'] ?? null) !== 2 || !isset($torrent['info']['file tree'])) { // BEP 0052

				$title = $client_date = $hash_v1 = $note_v1 = '';

				if (isset($torrent['info']['name'])) {
					$t_name = &$torrent['info']['name'];
					if (pathinfo($file, PATHINFO_FILENAME) !== $t_name) {
						$title = "\r\n{$msg['torrent_title']}: $t_name";
					}
				}

				if (isset($torrent['creation date'], $torrent['created by'])) {
					$date = date("d M Y | G:i:s T", $torrent['creation date']);
					$client_date =  "\r\n{$msg['created_by_client']}: {$torrent['created by']} ($date)";
				}

				if (isset($torrent['info']['pieces'])) {
					$info_hash_v1 = hash('sha1', bencode_encode($torrent['info']));
					$hash_v1 = "\r\n{$msg['root_hash']}: $info_hash_v1";
				}

				if (!isset($torrent['info']['meta version'])) {
					$note_v1 = "\r\n{$msg['note']}: {$msg['hint_v1']}";
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
			global $msg, $torrent;

			echo "\r\n — {$msg['file_location']}: $filename —\r\n";

			if (isset($torrent['info']['name'])) { // BEP 0052
				$t_name = &$torrent['info']['name'];
				if (pathinfo($filename, PATHINFO_FILENAME) !== $t_name) {
					echo " — {$msg['torrent_title']}: $t_name —\r\n";
				}
			}

			if (isset($torrent['creation date'], $torrent['created by'])) {
				$creation_date = date("d M Y | G:i:s T", $torrent['creation date']);
				echo " — {$msg['created_by_client']}: {$torrent['created by']} ($creation_date) —\r\n";
			}
		}

		// Loop through all arrays saving locations and showing result
		function printFiles($array, $parent = '')
		{
			global $msg, $torrent_size, $filec;

			foreach ($array as $key => $value) {
				$current = "$parent/$key";
				if (is_array($value) && !empty($key)) {
					printFiles($value, $current);
				}
				else{
					$length = &$value['length'];
					$path = substr($current, 1, -1);
					$size = formatBytes($length);
					$root = bin2hex($value['pieces root'] ?? '');
					echo "\r\n  $path ($size)\r\n {$msg['root_hash']}: $root\r\n";
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
				$current_key = "$parent_key/$key";
				if (is_array($value) && !empty($key)) {
					combine_keys($value, $hashes, $current_key);
				}
				else{
					$length = &$value['length'];
					$size = formatBytes($length);
					$root = bin2hex($value['pieces root'] ?? '');
					$hashes[substr($current_key, 1, -1)] = [
						'hash' => "$root ($size)",
						'size' => $length,
						'pos' => $filec ?? 0
					];
					$torrent_size += $length;
					++$filec;
				}
			}
		}

		//Create an array and find duplicates
		function compare()
		{
			global $msg, $hashes, $torrent, $torrent_size, $filec, $magnet, $argv, $argc;

			$t_size = formatBytes($torrent_size);
			$single_torrent = $argc < 4 ? true : false;
			$dups_size = $dup_hashes = $filed = 0;

			if (!empty($hashes)) {

				foreach ($hashes as $key => $value) {
					$hash = &$value['hash'];
					if (isset($keys[$hash])) {
						$keys[$hash][] = $key;
						$dups_size += $value['size'];
					}
					else{
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
						$dups = implode("\r\n", $value);
						echo "\r\n {$msg['root_hash']} $key {$msg['dup_found']}:\r\n\r\n$dups\r\n\r\n";
						$filed += $count;
						++$dup_hashes;
					}
				}
			}

			if (empty($dup_hashes)) {
				echo "\r\n " , formatText($msg['no_duplicates'], 33) , "\r\n\r\n{$msg['total_files']}: $filec ($t_size)\r\n";
			}
			else{
				$d_count = $filed - $dup_hashes;
				$d_sizes = formatBytes($dups_size);
				echo "{$msg['total_files']}: $filec ($t_size)\r\n{$msg['total_dup_files']}: $d_count ($d_sizes)";

				if ($single_torrent) {

					if (!empty($torrent_size)) {

						$percentage = ($dups_size / $torrent_size) * 100;
						$precision = ($percentage >= 0.01) ? 2 : (!empty($percentage) ? abs(floor(log10($percentage))) : 0);
						echo ' | ' , number_format($percentage, $precision) , "%\r\n";
					}

					ob_end_flush();


					// Magnet handler
					cli_set_process_title($msg['magnet_proposal']);

					$cli_output = stream_isatty(STDOUT);

					if ($cli_output) {
						echo "\r\n	" , formatText($msg['magnet_proposal'], 33) , "\r\n	";
					}

					$clean_cli = "\033[2A" . "\033[2K"; // Escape symbols for cleaning output
					$acceptance_symbol = ['y', 'd'];

					$handle = strtolower(fgets(fopen('php://stdin', 'r')));

					if (in_array(trim($handle), $acceptance_symbol) || $handle === PHP_EOL) {
						$magnetL = magnet_gen();
						if ($cli_output) {
							echo $clean_cli;
						}

						echo "\r\n" , formatText($magnetL, 32) , "\r\n";

						if ($cli_output) {
							if (PHP_OS_FAMILY === 'Windows') {
								$command = 'start "" "' . $magnetL . '"';
								if (strlen($command) <= 8191) { // Windows command length limit
									@exec($command);
								}
								else{
									echo "\r\n " , formatText($msg['magnet_copy'], 33) , "\r\n";
								}
							}
							elseif (PHP_OS_FAMILY === 'Linux') {
								$command = 'xdg-open "" "' . $magnetL . '"';
								@exec($command);
							}
						}
					}
					elseif ($cli_output) {
						echo $clean_cli , "\033[1B" , "\033[2K";
					}
				}
				else{
					echo "\r\n";
				}
			}
		}

		// Generate a magnet link without duplicates
		function magnet_gen()
		{
			global $magnet, $torrent;

			$indices = '&so=' . formatSeq($magnet['indices']); // BEP 0053

			$name = $trackers = $web_seeds = $info_hash_v1 = '';

			if (isset($torrent['announce']) && !isset($torrent['announce-list'])) {
				$trackers = '&tr=' . urlencode($torrent['announce']);
			}

			if (isset($torrent['announce-list'])) {
				$tracker_list = &$torrent['announce-list'];
				if (count($tracker_list[0]) > 1) {
					$trackers .= '&tr=' . implode('&tr=', array_map('urlencode', $tracker_list[0]));
				}
				else{
					foreach ($tracker_list as $value) {
						$trackers .= '&tr=' . urlencode($value[0]); // For tracker lists created with additional space separations
					}
				}
			}

			if (isset($torrent['url-list'])) {
				$url_list = &$torrent['url-list'];
				if (!is_array($url_list)) {
					$web_seeds = '&ws=' . urlencode($url_list);
				}
				else{
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
			private $path;
			public $root;
			private $piece_layer;
			private $layer_hashes;
			private $num_blocks;

			public function __construct($path, $piece_length = 16384) // 16KiB blocks
			{
				!ob_get_level() || ob_end_flush();
				defined('BLOCK_SIZE') || define('BLOCK_SIZE', $piece_length);
				defined('HASH_SIZE') || define('HASH_SIZE', 32);
				$this->path = $path;
				$this->root = null;
				$this->piece_layer = null;
				$this->layer_hashes = [];
				$this->num_blocks = 1;
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
					$leaf = fread($fd, BLOCK_SIZE);

					if (time() > $this->sync) { // Show percentage status
						$percent = number_format((ftell($fd) / $file_size) * 100);
						cli_set_process_title("{$msg['calculation']} $percent%  —  $filename");
						$this->sync = time();
					}

					$blocks[] = hash('sha256', $leaf, true);
					$blocks_count = count($blocks);
					if ($blocks_count !== $this->num_blocks) {
						$remaining = $this->num_blocks - $blocks_count;
						if (count($this->layer_hashes) === 0) {
							$power2 = next_power_2($blocks_count);
							$remaining = $power2 - $blocks_count;
						}
						$padding = array_fill(0, $this->num_blocks, str_repeat("\x00", HASH_SIZE));
						$blocks = array_merge($blocks, array_slice($padding, 0, $remaining));
					}
					$layer_hash = $this->merkle_root($blocks);
					$this->layer_hashes[] = $layer_hash;
				}
				$this->calculate_root();
			}

			private function calculate_root()
			{
				$this->piece_layer = implode('', $this->layer_hashes);
				$hashes = count($this->layer_hashes);
				if ($hashes > 1) {
					$pow2 = $this->next_power_2($hashes);
					$remainder = $pow2 - $hashes;
					$pad_piece = array_fill(0, $this->num_blocks, str_repeat("\x00", HASH_SIZE));
					while ($remainder > 0) {
						$this->layer_hashes[] = $this->merkle_root($pad_piece);
						$remainder--;
					}
				}
				$this->root = bin2hex($this->merkle_root($this->layer_hashes));
			}

			private function merkle_root($blocks)
			{
				if (count($blocks) > 0) {
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
				return $blocks;
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

			$pow = min(floor(($bytes ? log($bytes) : 0) / log(1024)), count($units) - 1);

			return round($bytes / (1024 ** $pow), $precision) . " $units[$pow]";
		}

		// Format coloured text
		function formatText($text, $color)
		{
			global $settings;

			if (stream_isatty(STDOUT) && $settings['colour']) {
				return "\033[$color" . "m$text\033[0m";
			}
			else{
				return $text;
			}
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
					}
					else{
						$sequences[] = $number;
					}
				}
			}

			return implode(',', $sequences);
		}

		// Error handler
		function error_status()
		{
			global $msg, $err_status;

			if (!empty($err_status)) {

				echo "\r\n\r\n--- " , formatText($msg['unfinished_files'], 31) , ": ---\r\n";

				foreach ($err_status as $key => $value) {

					echo "\r\n{$msg['file_location']}: $key\r\n{$msg['error_type']}: $value\r\n\r\n";

				}
			}

			die();
		}
