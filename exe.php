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

//Main
// Check for arguments
if (!isset($argv[1])) {
die($msg["main"]);
	}
	if ($argv[1] == "r") {
	$err_status = [];
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
	if(!empty($err_status)){
	echo "\r\n\r\n--- {$msg["unfinished_files"]}: --\r\n";
	foreach($err_status as $key => $value){
		echo "\r\n{$msg["file_location"]}: $key \r\n" . "{$msg["error_type"]}: $value\r\n";
		}
	}
	die();
}

// Since no "r" argument key provided this has to be a torrent file, let's extract hashes
		$err_status = [];
		foreach(array_slice($argv , 1) as $file){
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
if(!empty($err_status)){
	echo "\r\n\r\n--- {$msg["unfinished_files"]}: ---\r\n";
	foreach($err_status as $key => $value){
		echo "\r\n{$msg["file_location"]}: $key \r\n" . "{$msg["error_type"]}: $value";
	}
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
	global $msg;
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
// Timer function
function timer($dose, $max){

    global $interactive_pos, $sync, $clear, $msg;
    $symbols = ['|', '/', '—', '\\'];
	
    if((microtime(true) - $sync) >= 0.09 ){

        $percent = round(($dose / $max) * 100);
        echo $clear . "	" . $symbols[$interactive_pos % 4] ." {$msg["calculation"]} $percent%";
        $interactive_pos++;
        $sync = microtime(true);
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

// Language option
function lang(){
$version = "1.1.5g";
$strings = array(
	"rus"=>
	[
	"main" => "\r\nСинтаксис:\r\n\r\ntmrr.exe <файл.torrent>		*Извлекает хеши файлов из торрента*\r\n\r\ntmrr.exe r <ваш_файл>		*Вычисляет корневой Merkle хеш файла*\r\n\r\nСинтаксис поддерживает передачу нескольких файлов <файл1> <файл2>.. <файл5>\r\n\r\n---\r\n\r\nВерсия: $version Грибовская\r\nРазработчик: Tykov на трекере NNMClub\r\nTelegram: @vatruski\r\n\r\n",
	"noraw" => "Укажите расположение файла, он не должен быть пустым.",
	"invalid_torrent" => ".torrent файл содержит ошибки.",
	"no_v2" => "Это торрент файл v1 формата, а не v2 или гибрид. Торренты v1 формата не поддерживают вычисление Merkle хешей файлов.",
	"root_hash" => "Хеш",
	"calculation" => "Вычисление",
	"size" => "Размер",
	"torrent_title" => "Название раздачи",
	"file_location" => "Файл",
	"unfinished_files" => "Необработанные файлы",
	"error_type" => "Ошибка"
	],
	
	"eng" => [
	"main" => "\r\nPlease use the correct syntax, as:\r\n\r\ntmrr.exe <torrent-file>		*Extracts root hashes from a .torrent file*\r\n\r\ntmrr.exe r <your-file>		*Calculates Merkle root hash of a raw file*\r\n\r\nSyntax is supported for multiple files, as <file1> <file2>.. <file5>\r\n\r\n---\r\n\r\nVersion: $version\r\nDev by Tikva on Rutracker.org\r\nTelegram: @vatruski\r\nhttps://github.com/kovalensky/tmrr\r\n\r\nGreetings from the Russian Federation!\r\n",
	"noraw" => "This is not a raw file comrade, is it empty?",
	"invalid_torrent" => "It looks like this is an invalid torrent file, are you sure comrade?",
	"no_v2" => "It looks like this is an invalid hybrid/v2 file, probably a v1 torrent format that does not support root Merkle hashes, sorry comrade.",
	"root_hash" => "Hash",
	"calculation" => "Calculating",
	"size" => "Size",
	"torrent_title" => "Title",
	"file_location" => "File",
	"unfinished_files" => "Unprocessed files",
	"error_type" => "Error type"
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
	
	$lang = @file_get_contents($locale_file);
		if($lang == "0419"){
			return $strings["rus"];
	}
		else{
			return $strings["eng"];
	}
	}
	
