<?php

//Initialization

// Buffer enable
ob_start();
// Language
$msg = lang();
// Timer variable
$sync = time();
// Error catcher
$err_status = [];

//Main

// Check for arguments
	if ($argc <= 2 || !in_array( $argv[1], ["e", "d", "c"] )) {
		die($msg["main"]);
		}

		// Extract hashes
		if($argv[1] == "e"){
			foreach(array_slice($argv , 2) as $file){
				
				if(file_exists($file)){
					$decoded = @bencode_decode(@file_get_contents($file));
				}
				
				if(!validity_tcheck($file)){
					continue;
				}
				
				echo "\r\n\r\n — {$msg["file_location"]}: $file —\r\n" . " — {$msg["torrent_title"]}: " . @$decoded["info"]["name"] . " — \r\n\r\n";

				cli_set_process_title($msg["cli_hash_extraction"] . " — $file");

				$torrent_size = 0;
				$filec = 0; // File count
				printFiles($decoded["info"]["file tree"]); // Pass all files dictionary
				echo "\r\n{$msg["total_files"]}: $filec (" . @formatBytes($torrent_size) . ")\r\n";
				
			}

		error_status();
		
	}

		// Find duplicates; be aware that duplicates inside single .torrent file are also shown
		if ($argv[1] == "d") {
			foreach(array_slice($argv, 2) as $file){
			
				if(file_exists($file)){
					$decoded = @bencode_decode(@file_get_contents($file));
				}
				
				if(!validity_tcheck($file)){
					continue;
				}
				
				$file_tree_array["  $file:  "] = $decoded["info"]["file tree"];
			}
				if(!empty($file_tree_array)){

					$hashes = [];
					$torrent_size = 0;
					$filec = 0;
					combine_keys($file_tree_array, $hashes);
					unset($file_tree_array);
					cli_set_process_title($msg["cli_dup_search"]);
					compare($hashes);
					
			}
				
		error_status();
		
	}

	
		// Calculate Merkle Root Hash
		if ($argv[1] == "c") {
			foreach(array_slice($argv, 2) as $file){
				if(is_file($file) && filesize($file) !== 0){
				
					$hash = new HasherV2($file, 2**14);
					
					echo "\r\n $file\r\n{$msg["root_hash"]}: " . @bin2hex($hash->root) . "\r\n\r\n ";
					
				
				}
				else{
					
					$err_status[$file] = $msg["noraw"];
					
				}
			}
			
		error_status();
	}


//Functions

	// Bencode library
	function bencode_decode($input) {
			if ($input === '') {
				return null;
			}

			$len = strlen($input);
			$pos = 0;
			try {
				$output = bencode_decode_r($input, $len, $pos);
			} catch (TypeError $e) {
				$output = null;
			}
			return $output;
		}

	function bencode_decode_r($input, $len, &$pos) {
		if ($pos >= $len) {
			return null;
		}

		$ch = $input[$pos];
		$output = array();
		
		if ($ch === 'd') {
			$pos++;

			while ($pos < $len && $input[$pos] !== 'e') {
				$key = bencode_decode_r($input, $len, $pos);
				$output[$key] = bencode_decode_r($input, $len, $pos);
			}

			$pos++;
			return $output;
		} elseif ($ch === 'l') {
			$pos++;

			while ($pos < $len && $input[$pos] !== 'e') {
				$output[] = bencode_decode_r($input, $len, $pos);
			}

			$pos++;
			return $output;
		} elseif ($ch === 'i') {
			$pos++;
			$endpos = strpos($input, 'e', $pos);
			$output = (int) substr($input, $pos, $endpos - $pos);
			$pos = $endpos + 1;
			return $output;
		} else {
			$len_str = '';
			$pos_start = $pos;

			while ($pos < $len && is_numeric($input[$pos])) {
				$len_str .= $input[$pos++];
			}

			$len = (int) $len_str;
			$pos++;
			$output = substr($input, $pos, $len);
			$pos += $len;
			return $output;
		}
	}


	// Loop through all arrays saving locations and showing result
	function printFiles($array, $parent = "") {
		global $msg, $torrent_size, $filec;
		foreach($array as $key => $value) {
			$current = $parent . "/" . $key;
			if(is_array($value) && strlen($key) !== 0) {
				printFiles($value, $current);
			} else {
				
				echo "\r\n " . substr($current, 1, -1) . ' (' . @formatBytes($value["length"]) . ")\r\n{$msg["root_hash"]}: " . @bin2hex($value["pieces root"]) . "\r\n";
				$torrent_size += $value["length"] ?? 0;
				$filec++;
				
			}
		}
	}
	
		
	// Comparator functions

	//Extract and combine hashes in one array
	function combine_keys($array, &$hashes, $parent_key = "") {
		global $torrent_size, $filec;
		foreach($array as $key => $value) {
			$current_key = $parent_key . "/" . $key;
			if(is_array($value) && strlen($key) !== 0) {
				combine_keys($value, $hashes, $current_key);
			} else {
				
				$hashes[ substr($current_key, 1, -1) ] = [
                "hash" => @bin2hex($value["pieces root"]) . " (" . @formatBytes($value["length"]) . ")",
                "size" => $value["length"] ?? 0
				];
				$torrent_size += $value["length"] ?? 0;
				$filec++;
			}
		}
	}

	//Create an array and find duplicates
	function compare() {
		global $msg, $hashes, $torrent_size, $filec, $argc;
		$dups_size = 0;
		
		foreach ($hashes as $key => $value) {
			$hash = &$value["hash"];
			if (isset($keys[$hash])) {
				$keys[$hash][] = $key;
				$dups_size += $value["size"];
				} else {
				$keys[$hash] = [$key];
				}
			}

		unset($hashes);
		$dup_hashes = 0;
		$filed = 0;
		
		if(!empty($keys)){
			foreach ($keys as $key => $value) {
				$count = count($value);
				if ( $count > 1 ) {
					echo "\r\n{$msg["root_hash"]} " . $key . " {$msg["dup_found"]}:\r\n\r\n" . implode("\r\n", $value) . "\r\n\r\n";
					$filed += $count;
					$dup_hashes++;
				}
			}
		}

		if(empty($dup_hashes)){
			echo "\r\n " . $msg["no_duplicates"] . "\r\n\r\n" . $msg["total_files"] . ": $filec (" . @formatBytes($torrent_size)  . ")\r\n";
		}
		else{
			echo "{$msg["total_files"]}: $filec (" . @formatBytes($torrent_size)  . ")\r\n{$msg["total_dup_files"]}: " . ($filed - $dup_hashes) . " (" . @formatBytes($dups_size) . ")";
			if($argc == 3){
				echo " | " . round(($dups_size / $torrent_size) * 100, 2) . "%\r\n";
			}
		}
			   
	}


	// Individual files Merkle computations
	class HasherV2 {
		private $path;
		public $root;
		private $piece_layer;
		private $layer_hashes;
		private $piece_length;
		private $num_blocks;
		
		public function __construct($path, $piece_length) {
		defined('BLOCK_SIZE') OR define('BLOCK_SIZE', $piece_length);
		defined('HASH_SIZE') OR define('HASH_SIZE', 32);
			$this->path = $path;
			$this->root = null;
			$this->piece_layer = null;
			$this->layer_hashes = [];
			$this->num_blocks = 1;
			$fd = fopen($this->path, 'rb');
			$this->process_file($fd, $this->path);
			fclose($fd);
		}

		private function process_file($fd, $filename = "") {
			global $sync;
			$size = fstat($fd)["size"];
			while (!feof($fd)) {
				$blocks = [];
				$leaf = fread($fd, BLOCK_SIZE);
				
				if(time() > $sync){
				timer(ftell($fd), $size, $filename);
				}
				
				$blocks[] = hash('sha256', $leaf, true);
				if (count($blocks) != $this->num_blocks) {
					$remaining = $this->num_blocks - count($blocks);
					if (count($this->layer_hashes) == 0) {
						$power2 = next_power_2(count($blocks));
						$remaining = $power2 - count($blocks);
					}
					$padding = array_fill(0, $remaining, str_repeat("\x00", HASH_SIZE));
					$blocks = array_merge($blocks, $padding);
				}
				$layer_hash = merkle_root($blocks);
				$this->layer_hashes[] = $layer_hash;
			}
			$this->_calculate_root();
		}
		private function _calculate_root() {
		  $this->piece_layer = "";
		  foreach ($this->layer_hashes as $hash) {
			$this->piece_layer .= $hash;
		  }

		  $hashes = count($this->layer_hashes);
		  if ($hashes > 1) {
			$pow2 = next_power_2($hashes);
			$remainder = $pow2 - $hashes;
			$pad_piece = array_fill(0, $this->num_blocks, str_repeat("\x00", HASH_SIZE));
			for ($i = 0; $i < $remainder; $i++) {
			  $this->layer_hashes[] = merkle_root($pad_piece);
			}
		  }
		  $this->root = merkle_root($this->layer_hashes);
		}
	}

	function next_power_2($value) {
		if (!($value & ($value - 1)) && $value) {
			return $value;
		}
		$start = 1;
		while ($start < $value) {
			$start <<= 1;
		}
		return $start;
	}

	function merkle_root($blocks) {
		if (count($blocks) > 0) {
			while (count($blocks) > 1) {
				for ($i = 0; $i < count($blocks); $i += 2) {
					$x = $blocks[$i];
					$y = isset($blocks[$i + 1]) ? $blocks[$i + 1] : '';
					$blocks[$i / 2] = hash('sha256', $x . $y, true);
				}
				$blocks = array_slice($blocks, 0, count($blocks) / 2);
			}
			return $blocks[0];
		}
		return $blocks;
	}
	

	// Timer function
	function timer($dose, $max, $filename){
		global $sync, $msg;
		
			$percent = round(($dose / $max) * 100);
			cli_set_process_title("{$msg["calculation"]} $percent%  —  $filename");
			$sync = time();
		}


	// Torrent validity checks
	function validity_tcheck($file){
		global $decoded, $msg, $err_status;
		if(!isset($decoded["info"])){
			$err_status[$file] = $msg["invalid_torrent"] . "\r\n";
			return false;
			}

		if(!isset($decoded["info"]["meta version"])){
			$err_status[$file] = $msg["no_v2"] . "\r\n";
			return false;
			}
			
		if(!isset($decoded["info"]["file tree"])){
			$err_status[$file] = $msg["invalid_torrent"] . "\r\n";
			return false;
			}
				
		return true;
	}


	// Represent bytes
	function formatBytes($bytes, $precision = 2) { 
		$units = ['B', 'KB', 'MB', 'GB', 'TB']; 

		$bytes = max($bytes, 0); 
		$pow = floor(($bytes ? log($bytes) : 0) / log(1024)); 
		$pow = min($pow, count($units) - 1); 
		$bytes /= pow(1024, $pow); 

		return round($bytes, $precision) . ' ' . $units[$pow]; 
	}


	// Error handler
	function error_status(){
		global $msg, $err_status;

		if(!empty($err_status)){
			
			echo "\r\n\r\n--- {$msg["unfinished_files"]}: ---\r\n";
			foreach($err_status as $key => $value){
				echo "\r\n{$msg["file_location"]}: $key \r\n" . "{$msg["error_type"]}: $value\r\n";
				}
			}
		
		die();
	}


	// Language option
	function lang(){
		global $argv;
		$version = "2.3g"; // Code name: Gribovskaya (Mushroom Pumpkin)
		$strings = [
			"ru"=>
			[
			"main" => "\r\nСинтаксис:\r\n\r\ntmrr e <торрент-файл>	*Извлекает хеши файлов из торрентов*\r\n\r\ntmrr d <торрент-файл>	*Находит дубликаты файлов в торрент(ах)*\r\n\r\ntmrr c <ваш-файл>	*Вычисляет хеш существующего файла*\r\n\r\n\r\n** Синтаксис поддерживает передачу нескольких файлов, как <файл1> <файл2>.. <файлN> для всех команд.\r\n\r\n---\r\n\r\nВерсия: $version Грибовская\r\nАвтор: Коваленский Константин\r\n\r\n",
			"noraw" => "Укажите расположение файла, он не должен быть пустым.",
			"invalid_torrent" => "Неопознанный .torrent файл.",
			"no_v2" => "Это торрент файл v1 формата, а не v2 или гибрид.\r\nv1 торренты не поддерживают показ хешей файлов.",
			"root_hash" => "Хеш",
			"calculation" => "Вычисление",
			"torrent_title" => "Название раздачи",
			"file_location" => "Файл",
			"unfinished_files" => "Необработанные файлы",
			"error_type" => "Ошибка",
			"no_duplicates" => "Дубликаты не найдены.",
			"dup_found" => "найден в",
			"total_files" => "Количество файлов",
			"total_dup_files" => "Количество дубликатов",
			"cli_dup_search" => "Поиск дубликатов",
			"cli_hash_extraction" => "Извлечение хешей",
			"cli_hash_calculation" => "Вычисление хеша"
			],
			
			"en" => [
			"main" => "\r\nPlease use the correct syntax, as:\r\n\r\ntmrr e <torrent-file>	*Extracts file hashes from .torrent files*\r\n\r\ntmrr d <torrent-file>	*Finds duplicate files within .torrent file(s)*\r\n\r\ntmrr c <your-file>	*Calculates the hash of existing files*\r\n\r\n\r\n** Syntax is supported for multiple files, as <file1> <file2>.. <fileN> for all commands accordingly.\r\n\r\n---\r\n\r\nVersion: $version\r\nAuthor: Constantine Kovalensky\r\n\r\n",
			"noraw" => "This is not a valid file, is it empty?",
			"invalid_torrent" => "Invalid torrent file.",
			"no_v2" => "This is an invalid hybrid or v2 torrent.\r\nv1 torrents do not support displaying file hashes.",
			"root_hash" => "Hash",
			"calculation" => "Processing",
			"torrent_title" => "Title",
			"file_location" => "File",
			"unfinished_files" => "Unprocessed files",
			"error_type" => "Error type",
			"no_duplicates" => "No duplicates were found.",
			"dup_found" => "found in",
			"total_files" => "Total files",
			"total_dup_files" => "Duplicate count",
			"cli_dup_search" => "Searching for duplicates",
			"cli_hash_extraction" => "Extracting file hashes",
			"cli_hash_calculation" => "Calculating the hash of"
			]
			];
			
			
			$locale_file = __DIR__ . DIRECTORY_SEPARATOR . "ru";
			
					if(@$argv[1] == "locale"){
						switch(@$argv[2]){
							case "en":
							@unlink($locale_file);
							die("Language changed to English.");
							case "ru":
							file_put_contents($locale_file, "");
							die("Язык был изменён на русский.");
					}
			}
			
			if(!file_exists($locale_file)){
				
				return $strings["en"];
				
				}
				else{
					
				return $strings["ru"];
				
				}
	}
			

		
