# TMRR — Torrent Merkle Root Reader
[![Donate Monero](https://img.shields.io/badge/Donate-Monero-FF6600.svg)](monero:837ooBb4LrdGKd2qbzEsjt4SgdG9oCLJgjozRCyszB474pNrEzAftYdPL8EA75h7NqP4Zxmp2ikR3eggLeWcViCMVJxYpQ8)
[![Donate Bitcoin](https://img.shields.io/badge/Donate-Bitcoin-f7931a.svg)](bitcoin:1GWxFbfqHcMR4FEKy2P1sayPkFByGKGwCK)
[![Donate Ethereum](https://img.shields.io/badge/Donate-Ethereum-8c8c8c.svg)](ethereum:0x58dC9585BE36e855bA30609909f7D4Ef11313ee1)
<h1 align="center">
  <a href="#">
    <img src="https://i1.imageban.ru/out/2023/03/10/00f84ad9d8c318f631dba7be6c8fdecd.gif" alt="TMRR">
  </a>
</h1>

Useful for finding the sources of the same copies of files on different trackers that support BitTorrent v2, thus reviving dead torrents.

Extract root hashes from a .torrent file:
```
tmrr.exe example.torrent
```
Calculate root hashes for raw files:
```
tmrr.exe r your_file
```
To save output:
```
tmrr.exe your_command > output.txt
```

For the Linux environment on which PHP is installed:
```
php exe.php your_command
```
