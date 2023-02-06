<?php
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

if (isset($argv[1]) && isset($argv[2]) OR !isset($argv[1])) {
die("\r\nPlease use the correct syntax, as TorrentMerkleReader.exe <torrent-file-name> \r\n\r\n---\r\nDev by Tikva on Rutracker.org\r\nTelegram: @vatruski\r\n\r\nGreetings from the Russian Federation!");
	}

$torrent_file = @file_get_contents($argv[1]);
$decoded = @bencode_decode($torrent_file);
if(!isset($decoded["info"])){
	die("It looks like this is an invalid torrent file, are you sure comrade?");
}

if(!isset($decoded["meta version"]) && !isset($decoded["piece layers"])){
	die("It looks like this is an invalid hybrid/v2 file, probably a v1 torrent format that does not support root Merkle hashes, sorry comrade");
}
$make = $decoded["info"]["file tree"]; 

// Loop through all arrays saving locations and showing result
function printArrayNames($array, $parent = "") {
    foreach($array as $key => $value) {
        $current = $parent . "/" . $key;
        if(is_array($value) && strlen($key) !== 0) {
            printArrayNames($value, $current);
        } else {
            echo $construct = $current . "\r\nRoot hash: " . bin2hex($value["pieces root"]) . " Size: " . $value["length"] . "\r\n\r\n";
			
		}
    }
}
printArrayNames($make);
