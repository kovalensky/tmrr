# TMRR — Torrent Merkle Root Reader
[![Branch](https://img.shields.io/badge/Version-1.1.7g-green.svg)](https://github.com/kovalensky/tmrr/releases)
[![Donate Monero](https://img.shields.io/badge/Donate-Monero-FF6600.svg)](https://monero/wallet/837ooBb4LrdGKd2qbzEsjt4SgdG9oCLJgjozRCyszB474pNrEzAftYdPL8EA75h7NqP4Zxmp2ikR3eggLeWcViCMVJxYpQ8)
[![Donate Bitcoin](https://img.shields.io/badge/Bitcoin-f7931a.svg)](https://bitcoin/wallet/1GWxFbfqHcMR4FEKy2P1sayPkFByGKGwCK)
[![Donate Ethereum](https://img.shields.io/badge/Ethereum-8c8c8c.svg)](https://ethereum/wallet/0x58dC9585BE36e855bA30609909f7D4Ef11313ee1)
<h1 align="center">
  <a href="#">
    <img src="https://i2.imageban.ru/out/2023/04/01/1afb2dee6d47c0dac332357a9ff8277f.gif" alt="TMRR">
  </a>
</h1>

Useful for finding the sources of the same copies of files on different trackers that support BitTorrent v2, thus reviving dead torrents.

Extract root hashes from .torrent files:
```
tmrr.exe example.torrent
```
Calculate root hashes for raw files:
```
tmrr.exe r your_file
```
Compare .torrent files for duplicates:
```
tmrr.exe c <example.torrent> <example2.torrent>.. <example5.torrent>
```
To save big output:
```
tmrr.exe your_command > output.txt
```
Web Server use:
```
https://server/exe.php?method=[your_method]&tmrr_file=<file_location>&tmrr_file2=<file_location>..
```
For the Linux environment on which PHP is installed:
```
php exe.php your_command
```

If this tool has somehow helped you in finding important files, you can express your gratitude by starring this repository or making a donation🍀.
