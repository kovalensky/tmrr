<?php
// Constants for merkle calculation
const BLOCK_SIZE = 16384;
const HASH_SIZE = 32;
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

// Check for arguments

if (!isset($argv[1])) {
die("\r\nPlease use the correct syntax, as:\r\n\r\ntmrr.exe <torrent-file>		*Extracts root hashes from a .torrent file*\r\n\r\ntmrr.exe r <your-file>		*Calculates Merkle root hash of a raw file*\r\n\r\n---\r\nDev by Tikva on Rutracker.org\r\nTelegram: @vatruski\r\n\r\nGreetings from the Russian Federation!\r\n");
	}
if ($argv[1] == "r" && isset($argv[2])) {
// Do magic
$file = $argv[2];
	if(is_file($file)){
	$root = new HasherV2($file, BLOCK_SIZE);
	die( "Root hash of $file: " . @bin2hex($root->root) );
	}
	else{
		
		die("This is not a raw file comrade");
	}
	
	}
if($argv[1] == "r" && !isset($argv[2]) ){
		die("Please specify the location of the raw file comrade");
	}

// Since no "r" argument key provided this has to be a torrent file, let's extract hashes
	$torrent_file = @file_get_contents($argv[1]);
	$decoded = @bencode_decode($torrent_file);
		if(!isset($decoded["info"])){
			die("It looks like this is an invalid torrent file, are you sure comrade?");
		}

		if(!isset($decoded["meta version"]) && !isset($decoded["piece layers"])){
			die("It looks like this is an invalid hybrid/v2 file, probably a v1 torrent format that does not support root Merkle hashes, sorry comrade");
		}

$make = $decoded["info"]["file tree"]; // Pass all files dictionary

// Loop through all arrays saving locations and showing result
function printArrayNames($array, $parent = "") {
    foreach($array as $key => $value) {
        $current = $parent . "/" . $key;
        if(is_array($value) && strlen($key) !== 0) {
            printArrayNames($value, $current);
        } else {
		
            echo substr($current, 1, -1) . "\r\nRoot hash: " . bin2hex($value["pieces root"]) . " Size: " . $value["length"] . "\r\n\r\n";
			
		}
    }
}

printArrayNames($make);

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
