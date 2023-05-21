# TMRR — Torrent Merkle Root Reader
[![Branch](https://img.shields.io/badge/Version-2.0g-green.svg)](https://github.com/kovalensky/tmrr/releases)
[![Donate Monero](https://img.shields.io/badge/Donate-Monero-FF6600.svg)](https://monero/wallet/837ooBb4LrdGKd2qbzEsjt4SgdG9oCLJgjozRCyszB474pNrEzAftYdPL8EA75h7NqP4Zxmp2ikR3eggLeWcViCMVJxYpQ8)
[![Donate Bitcoin](https://img.shields.io/badge/Bitcoin-f7931a.svg)](https://bitcoin/wallet/1GWxFbfqHcMR4FEKy2P1sayPkFByGKGwCK)
[![Donate Ethereum](https://img.shields.io/badge/Ethereum-8c8c8c.svg)](https://ethereum/wallet/0x58dC9585BE36e855bA30609909f7D4Ef11313ee1)
<h1 align="center">
  <a href="#">
    <img src="https://i7.imageban.ru/out/2023/05/21/7a47b1441779590c2905d3dc1bd7fc5e.gif" alt="TMRR">
  </a>
</h1>

[Documentation for web usage](https://github.com/kovalensky/tmrr/wiki/Web-usage)

Useful for finding the sources of the same copies of files on different trackers & DHT indexers that support BitTorrent v2 protocol, thus reviving dead torrents, valuable Internet artifacts.

Extract file hashes from .torrent files:
```
tmrr e example.torrent
```
Calculate hashes for existing files:
```
tmrr c your_file
```
Find duplicates within .torrent file(s):
```
tmrr d <example.torrent> <example2.torrent>.. <exampleN.torrent>
```
To save a large output:
```
tmrr your_command > output.txt
```
For the Linux environment on which PHP is installed:
```
php tmrr.php your_command
```

If this tool has somehow helped you in finding important files, you can express your gratitude by starring this repository 🍀.
