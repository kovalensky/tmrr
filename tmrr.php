<?php

//Initialization

// Constants for merkle calculation
const BLOCK_SIZE = 16384;
const HASH_SIZE = 32;
// Timer variables
$interactive_pos = 0;
$sync = microtime(true);
$clear = "\r\x1b[K"; // Escape symbols for clearing output
// Language
$msg = lang();
// Error catcher
$err_status =[];
if(PHP_MAJOR_VERSION < 8 ){ die("PHP < 8 is not supported."); }
// Server checks
$server = false;
	if(php_sapi_name() !== "cli"){
		$server = true;
		timer(null, null, true); // Disable timer
		$clear = "<pre>";
		$argv = [];
		$argv[0] = "Server is up";
		$argv[1] = @$_GET["method"] ?? "1";
		if(!isset($_GET["method"])){
			$err_status["0"] = "Please set 'method' GET parameter, it can be empty or 'r' and 'c', please see https://github.com/kovalensky/tmrr.";
		}
		$i = 2;
		
		foreach(array_slice($_GET, 1) as $key => $value){
			if(str_starts_with($key, "tmrr")){
			$argv[$i] = $value;
			$i++;
			}
		}
	}

//Main
// Check for arguments
if (!isset($argv[1])) {
die($msg["main"]);
	}
	if ($argv[1] == "r") {
	// Calculate Merkle Root Hash
	foreach(array_slice($argv, 2) as $file){
	if(is_file($file) && filesize($file) !== 0){
	$root = new HasherV2($file, BLOCK_SIZE);
	echo $clear . "\r\n$file \r\n{$msg["root_hash"]}: " . @bin2hex($root->root) . "\r\n\r\n ";
	
	unset($root);
	}
	else{
		
		$err_status[$file] = $msg["noraw"];
	}
	}
	error_status($err_status);
	die();
}


	if ($argv[1] == "c" && count($argv) > 2) {
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
		$file_tree_array["### " .$file. " ###: \r\n"] = $decoded["info"]["file tree"];
	}
	$compared= [];
	combine_keys($file_tree_array, $compared);
	compare($compared);
	error_status($err_status);
	die();
	
}


// Since no "r" or "c" argument key provided this has to be a torrent file, let's extract hashes
	if(isset($argv[1])){
		if($server) {	echo $clear; 	unset($argv[1]);	}
		foreach(array_slice($argv , 1) as $key => $file){
		if($server){
		if(!file_exists($file)){
		$err_status[$key] = "Please provide correct file locations inside tmrr[i]_files GET parameters, see https://github.com/kovalensky/tmrr.";
		continue;
		}
		}
		$decoded = @bencode_decode(@file_get_contents($file));
		if(!isset($decoded["info"])){
			$err_status[$file] = $msg["invalid_torrent"] . "\r\n";
			continue;
		}

		if(!isset($decoded["info"]["meta version"])){
			$err_status[$file] = $msg["no_v2"] . "\r\n";
			continue;
		}

		echo "\r\n\r\n### $file ###\r\n" . "### {$msg["torrent_title"]}: " . @$decoded["info"]["name"] . " ###\r\n";
		printArrayNames($decoded["info"]["file tree"]); // Pass all files dictionary
		
		unset($decoded);
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
    $output = bencode_decode_r($input, $len, $pos);
    return $output;
}

function bencode_decode_r($input, $len, &$pos) {
    switch ($input[$pos]) {
        case 'd':
            $output = array();
            $pos++;

            while ($input[$pos] !== 'e') {
                $key = bencode_decode_r($input, $len, $pos);
                $output[$key] = bencode_decode_r($input, $len, $pos);
            }

            $pos++;
            return $output;

        case 'l':
            $output = array();
            $pos++;

            while ($input[$pos] !== 'e') {
                $output[] = bencode_decode_r($input, $len, $pos);
            }

            $pos++;
            return $output;

        case 'i':
            $output = '';
            $pos++;

            while ($input[$pos] !== 'e') {
                $output .= $input[$pos];
                $pos++;
            }

            $pos++;
            return (int) $output;

        default:
            $len_str = '';
            $pos_start = $pos;

            while (is_numeric($input[$pos])) {
                $len_str .= $input[$pos];
                $pos++;
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
	global $msg, $clear;
    foreach($array as $key => $value) {
        $current = $parent . "/" . $key;
        if(is_array($value) && strlen($key) !== 0) {
            printArrayNames($value, $current);
        } else {
			
            echo "\r\n" . substr($current, 1, -1) . "\r\n{$msg["root_hash"]}: " . @bin2hex($value["pieces root"]) . " {$msg["size"]}: " . formatBytes($value["length"]) . "\r\n";
			
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
        $this->process_file($fd);
        fclose($fd);
    }

    private function process_file($fd) {
        while (!feof($fd)) {
            $blocks = [];
            $leaf = fread($fd, BLOCK_SIZE);
            if (!$leaf) {
                break;
            }
            $size = fstat($fd)["size"];
            timer(ftell($fd), $size);
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

//Extract and combine functions in one array
function combine_keys($array, &$compared, $parent_key = "") {
    foreach($array as $key => $value) {
        $current_key = $parent_key . "/" . $key;
        if(is_array($value) && strlen($key) !== 0) {
            combine_keys($value, $compared, $current_key);
        } else {
  
            $compared[substr($current_key, 1, -1)] = @bin2hex($value["pieces root"]);
        }
    }
}

// Do comparation
function compare($array) {
    global $msg, $clear, $argv;	
	
    $duplicates = [];
    $count = count($array);
    $array_iterator = 0;
	$total_iterations = $count * ($count - 1) / 2;
    for ($i = 0; $i < $count; $i++) {
        $key1 = array_keys($array)[$i];
        $value1 = $array[$key1];
        for ($j = $i + 1; $j < $count; $j++) {
			timer($array_iterator, $total_iterations); $array_iterator++;
            $key2 = array_keys($array)[$j];
            $value2 = $array[$key2];
            
            if ($value1 === $value2) {
                if (!array_key_exists($value1, $duplicates)) {
                    $duplicates[$value1] = [$key1, $key2];
                } else {
                    $duplicates[$value1][] = $key2;
                }
                
                break;
            }
        }
    }
    if(!empty($duplicates)){
        foreach ($duplicates as $value => $keys) {
        echo $clear .  "\r\n{$msg["root_hash"]} $value {$msg["dup_found"]}:\r\n";
            foreach ($keys as $key) {
                echo $key. "\r\n\r\n";
            }
        }
    }
    else{
        echo $clear. "\r\n" . $msg["no_duplicates"] . "\r\n";
    }
}






// Timer function
function timer($dose, $max, $disable=false){
    global $interactive_pos, $sync, $clear, $msg;
	if ($disable == true)
	{ 		
		$interactive_pos = false;
	}
    
    if((microtime(true) - $sync) >= 0.09 && $interactive_pos !== false ){
		$symbols = ['|', '/', '—', '\\'];
		
        $percent = round(($dose / $max) * 100);
        echo $clear . "	" . $symbols[$interactive_pos % 4] ." {$msg["calculation"]} $percent%";
        $interactive_pos++;
        $sync = microtime(true);
    }
	else{	return false;	}
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
	global $msg;
	if(!empty($err_status)){
	echo "\r\n\r\n--- {$msg["unfinished_files"]}: ---\r\n";
	foreach($err_status as $key => $value){
		echo "\r\n{$msg["file_location"]}: $key \r\n" . "{$msg["error_type"]}: $value\r\n";
		}
	}
}

// Language option
function lang(){
global $argv;
$version = "1.1.7g";
$strings = array(
	"rus"=>
	[
	"main" => "\r\nСинтаксис:\r\n\r\ntmrr.exe <торрент-файл>		*Извлекает хеши файлов из торрентов*\r\n\r\ntmrr.exe c <торрент-файл>	*Находит дубликаты файлов в торрентах*\r\n\r\ntmrr.exe r <ваш-файл>		*Вычисляет корневой Merkle хеш файла*\r\n\r\n** Синтаксис поддерживает передачу нескольких файлов, как <файл1> <файл2>.. <файл5> для всех команд.\r\n\r\n---\r\n\r\nВерсия: $version Грибовская\r\nРазработчик: Tykov на трекере NNMClub\r\nTelegram: @vatruski\r\nhttps://github.com/kovalensky/tmrr\r\n\r\n",
	"noraw" => "Укажите расположение файла, он не должен быть пустым.",
	"invalid_torrent" => ".torrent файл содержит ошибки.",
	"no_v2" => "Это торрент файл v1 формата, а не v2 или гибрид. Торренты v1 формата не поддерживают вычисление Merkle хешей файлов.",
	"root_hash" => "Хеш",
	"calculation" => "Вычисление",
	"size" => "Размер",
	"torrent_title" => "Название раздачи",
	"file_location" => "Файл",
	"unfinished_files" => "Необработанные файлы",
	"error_type" => "Ошибка",
	"no_duplicates" => "Дубликаты не найдены.",
	"dup_found" => "найден в"
	],
	
	"eng" => [
	"main" => "\r\nPlease use the correct syntax, as:\r\n\r\ntmrr.exe <torrent-file>		*Extracts root hashes from .torrent files*\r\n\r\ntmrr.exe c <torrent-file>	*Compares duplicate files within .torrent files*\r\n\r\ntmrr.exe r <your-file>		*Calculates Merkle root hash of raw files*\r\n\r\n** Syntax is supported for multiple files, as <file1> <file2>.. <file5> for all commands accordingly.\r\n\r\n---\r\n\r\nVersion: $version\r\nDev by Tikva on Rutracker.org\r\nTelegram: @vatruski\r\nhttps://github.com/kovalensky/tmrr\r\n\r\nGreetings from the Russian Federation!\r\n",
	"noraw" => "This is not a raw file comrade, is it empty?",
	"invalid_torrent" => "It looks like this is an invalid torrent file, are you sure comrade?",
	"no_v2" => "It looks like this is an invalid hybrid/v2 file, probably a v1 torrent format that does not support root Merkle hashes, sorry comrade.",
	"root_hash" => "Hash",
	"calculation" => "Calculating",
	"size" => "Size",
	"torrent_title" => "Title",
	"file_location" => "File",
	"unfinished_files" => "Unprocessed files",
	"error_type" => "Error type",
	"no_duplicates" => "No duplicates were found.",
	"dup_found" => "found in"
	]
	);

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
	
