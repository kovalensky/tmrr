<?php

//Initialization

// Constants for file Merkle calculation
const BLOCK_SIZE = 16384;
const HASH_SIZE = 32;
// Timer variables
$sync = time();
// Language
$msg = lang();
// Error catcher
$err_status =[];

// Server checks
	$server = false;
	if(php_sapi_name() !== "cli"){
		ob_start();
		$server = true;
		if(count($tmrr_process) < 2 || !in_array( $tmrr_process[0], ["e", "d", "c"] )){
			die("Please provide parameters inside \$tmrr_process variable, see https://github.com/kovalensky/tmrr/wiki/Web-usage");
		}
		$tmrr_result = [];
		$tmrr_error = [];
		$argv = [];
		$argv[0] = "Server is up";
		$argv[1] = $tmrr_process[0];
		$i = 2;
		
		foreach(array_slice($tmrr_process, 1) as $value){
			$argv[$i] = $value;
			$i++;	
	}
		$argc = count($argv);
	}
	

//Main

// Check for arguments
	if ($argc <= 2 || !in_array( $argv[1], ["e", "d", "c"] )) {
		die($msg["main"]);
		}

		// Extract hashes
		if($argv[1] == "e"){
			$filec = 0;
			foreach(array_slice($argv , 2) as $file){
				$decoded = @bencode_decode(@file_get_contents($file));
				if(!isset($decoded["info"])){
					$err_status[$file] = $msg["invalid_torrent"] . "\r\n";
					continue;
				}

				if(!isset($decoded["info"]["meta version"])){
					$err_status[$file] = $msg["no_v2"] . "\r\n";
					continue;
				}

				echo "\r\n\r\n — File: " . file_base($file) . " —\r\n" . " — {$msg["torrent_title"]}: " . @$decoded["info"]["name"] . " — \r\n\r\n";
					if(!$server){
						cli_set_process_title($msg["cli_hash_extraction"] . " — $file");
					}
				printArrayNames($decoded["info"]["file tree"]); // Pass all files dictionary
				echo "\r\n{$msg["total_files"]}: $filec\r\n"; $filec = 0;
				
				unset($decoded);
		}

		error_status($err_status);
		
	}

		// Find duplicates; be aware that duplicates inside single .torrent file are also shown
		if ($argv[1] == "d") {
			$file_tree_array = [];
			foreach(array_slice($argv, 2) as $file){
			
				$decoded = @bencode_decode(@file_get_contents($file));
				if(!isset($decoded["info"])){
					$err_status[$file] = $msg["invalid_torrent"] . "\r\n";
					continue;
				}

				if(!isset($decoded["info"]["meta version"])){
					$err_status[$file] = $msg["no_v2"] . "\r\n";
					continue;
				}
				$file_tree_array[" " . file_base($file). ":  "] = $decoded["info"]["file tree"];
			}
				if(!empty($file_tree_array)){

					$hashes = [];
					$filec = 0;
					combine_keys($file_tree_array, $hashes);
					unset($file_tree_array);
					if(!$server){
						cli_set_process_title($msg["cli_dup_search"]);
						}
					compare($hashes, $filec);
					
				}
			error_status($err_status);
	}

	
		// Calculate Merkle Root Hash
		if ($argv[1] == "c") {
			foreach(array_slice($argv, 2) as $file){
				if(is_file($file) && filesize($file) !== 0){
					$root = new HasherV2($file, BLOCK_SIZE);
					$file = file_base($file); // Hide paths for web usage
					echo "\r\n $file \r\n{$msg["root_hash"]}: " . @bin2hex($root->root) . "\r\n\r\n ";
					
					unset($root);
				
				}
			else{
				$err_status[$file] = $msg["noraw"];
				}
			}
		error_status($err_status);
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

		if ($ch === 'd') {
			$output = array();
			$pos++;

			while ($pos < $len && $input[$pos] !== 'e') {
				$key = bencode_decode_r($input, $len, $pos);
				$output[$key] = bencode_decode_r($input, $len, $pos);
			}

			$pos++;
			return $output;
		} elseif ($ch === 'l') {
			$output = array();
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
	function printArrayNames($array, $parent = "") {
		global $msg, $filec;
		foreach($array as $key => $value) {
			$current = $parent . "/" . $key;
			if(is_array($value) && strlen($key) !== 0) {
				printArrayNames($value, $current);
			} else {
			
				echo "\r\n " . substr($current, 1, -1) . ' (' . formatBytes($value["length"]) . ")\r\n{$msg["root_hash"]}: " . @bin2hex($value["pieces root"]) . "\r\n";
				
				$filec++;
			}
		}
	}
		


	// Individual files Merkle computations
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
	// File hasher
	class HasherV2 {
		private $path;
		public $root;
		private $piece_layer;
		private $layer_hashes;
		private $piece_length;
		private $num_blocks;
		public function __construct($path, $piece_length) {
			$this->path = $path;
			$this->root = null;
			$this->piece_layer = null;
			$this->layer_hashes = [];
			$this->piece_length = $piece_length;
			$this->num_blocks = $piece_length / BLOCK_SIZE;
			$fd = fopen($this->path, 'rb');
			$this->process_file($fd, $this->path);
			fclose($fd);
		}

		private function process_file($fd, $filename = "") {
			$size = fstat($fd)["size"];
			while (!feof($fd)) {
				$blocks = [];
				$leaf = fread($fd, BLOCK_SIZE);
				
				timer(ftell($fd), $size, $filename);
				
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

	// Comparator functions

	//Extract and combine hashes in one array
	function combine_keys($array, &$hashes, $parent_key = "") {
		global $filec;
		foreach($array as $key => $value) {
			$current_key = $parent_key . "/" . $key;
			if(is_array($value) && strlen($key) !== 0) {
				combine_keys($value, $hashes, $current_key);
			} else {
	  
				$hashes[ substr($current_key, 1, -1) ] = @bin2hex($value["pieces root"]) . " (" . formatBytes($value["length"]) . ")";
				$filec++;
			}
		}
	}

	// Do comparation
	function compare($array, $filec) {
		global $msg;
		
			foreach ($array as $key => $value) {
				if (isset($keys[$value])) {
					$keys[$value][] = $key;
				} else {
					$keys[$value] = array($key);
				}
		}
		$dup_hashes = 0;
		$filed = 0;
		foreach ($keys as $key => $value) {
			if (count($value) > 1) {
				echo "\r\n{$msg["root_hash"]} " . $key . " {$msg["dup_found"]}:\r\n\r\n" . implode("\r\n", $value) . "\r\n\r\n";
				$filed += count($value);
				$dup_hashes++;
			}
		}

		if($filed == 0){
			echo "\r\n " . $msg["no_duplicates"] . "\r\n\r\n" . $msg["total_files"] . ": $filec\r\n";
		}
		else{
			echo "{$msg["total_files"]}: $filec\r\n{$msg["total_dup_files"]}: " . ($filed - $dup_hashes) . "\r\n";
		}
			   
	}




	// Timer function
	function timer($dose, $max, $filename){
		global $sync, $server, $msg;
	
		if((time() - $sync) >= 1 && !$server ){
			$percent = round(($dose / $max) * 100);
			cli_set_process_title("{$msg["calculation"]} $percent% — $filename");
			$sync = time();
			}
		}

	// Represent bytes
	function formatBytes($bytes, $precision = 2) { 
		$units = array('B', 'KB', 'MB', 'GB', 'TB'); 

		$bytes = max($bytes, 0); 
		$pow = floor(($bytes ? log($bytes) : 0) / log(1024)); 
		$pow = min($pow, count($units) - 1); 
		$bytes /= pow(1024, $pow); 

		return round($bytes, $precision) . ' ' . $units[$pow]; 
	}

	// Error handler
	function error_status($err_status){
		global $msg, $server, $tmrr_result, $tmrr_error;
			if($server){
				$tmrr_error = $err_status;
				$tmrr_result[0] = ob_get_clean();
				return;
			}
		if(!empty($err_status)){
			echo "\r\n\r\n--- {$msg["unfinished_files"]}: ---\r\n";
			foreach($err_status as $key => $value){
				echo "\r\n{$msg["file_location"]}: $key \r\n" . "{$msg["error_type"]}: $value\r\n";
				}
			}
			die();
	}

	// Base name calculation for web usage
	function file_base($string){
		global $server;
		if($server){
			return basename($string);
		}
		return $string;
	}

	// Language option
	function lang(){
		global $argv;
		$version = "2.0g"; // Code name: Gribovskaya Pumpkin
		$strings = array(
			"rus"=>
			[
			"main" => "\r\nСинтаксис:\r\n\r\ntmrr e <торрент-файл>	*Извлекает хеши файлов из торрентов*\r\n\r\ntmrr d <торрент-файл>	*Находит дубликаты файлов в торрент(ах)*\r\n\r\ntmrr c <ваш-файл>	*Вычисляет хеш существующего файла*\r\n\r\n\r\n** Синтаксис поддерживает передачу нескольких файлов, как <файл1> <файл2>.. <файлN> для всех команд.\r\n\r\n---\r\n\r\nВерсия: $version Грибовская\r\nАвтор: Коваленский Константин\r\n\r\n",
			"noraw" => "Укажите расположение файла, он не должен быть пустым.",
			"invalid_torrent" => ".torrent файл содержит ошибки.",
			"no_v2" => "Это торрент файл v1 формата, а не v2 или гибрид.\r\nv1 торренты не поддерживают показ хешей файлов.",
			"root_hash" => "Хеш",
			"calculation" => "Вычисление",
			"torrent_title" => "Название раздачи",
			"file_location" => "Файл",
			"unfinished_files" => "Необработанные файлы",
			"error_type" => "Ошибка",
			"no_duplicates" => "Дубликаты не найдены.",
			"dup_found" => "найден в",
			"total_files" => "Общее количество файлов",
			"total_dup_files" => "Количество дубликатов",
			"cli_dup_search" => "Поиск дубликатов",
			"cli_hash_extraction" => "Извлечение хешей",
			"cli_hash_calculation" => "Вычисление хеша"
			],
			
			"eng" => [
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
			);
			
			// For GitHub repository this tool is only distributed in English.
			
			return $strings["eng"];
			
			// Checkout NNMClub or Rutracker.org for multilingual versions.
			
			if(PHP_OS !== "WINNT"){
				return $strings["eng"]; // Linux bypass
			}
			
			$locale_file = __DIR__ . DIRECTORY_SEPARATOR . "locale";
			
			if(!file_exists($locale_file)){
				$get_lang = @shell_exec('reg query "hklm\system\controlset001\control\nls\language" /v Installlanguage');
				if (@preg_match('/\bREG_SZ\s+(\w+)\b/', $get_lang, $matches)) {
					
					@file_put_contents($locale_file, $matches[1]);
				
				}
				else{
					
					@file_put_contents($locale_file, "0409");
				
				}
			}
				if(@$argv[1] == "locale"){
					switch(@$argv[2]){
						case "en":
						file_put_contents($locale_file, "0409");
						die("Language changed to English.");
						case "ru":
						file_put_contents($locale_file, "0419");
						die("Язык был изменён на русский.");
					}
			}
			
			$lang = @file_get_contents($locale_file);
				if($lang == "0419"){
					return $strings["rus"];
			}
				else{
					return $strings["eng"];
			}
		}
		
