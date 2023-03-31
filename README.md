# TMRR — Torrent Merkle Root Reader
[![Branch](https://img.shields.io/badge/Version-1.1.5g-green.svg)](https://github.com/kovalensky/tmrr/releases)
[![Donate Monero](https://img.shields.io/badge/Donate-Monero-FF6600.svg)](https://monero/wallet/837ooBb4LrdGKd2qbzEsjt4SgdG9oCLJgjozRCyszB474pNrEzAftYdPL8EA75h7NqP4Zxmp2ikR3eggLeWcViCMVJxYpQ8)
[![Donate Bitcoin](https://img.shields.io/badge/Bitcoin-f7931a.svg)](https://bitcoin/wallet/1GWxFbfqHcMR4FEKy2P1sayPkFByGKGwCK)
[![Donate Ethereum](https://img.shields.io/badge/Ethereum-8c8c8c.svg)](https://ethereum/wallet/0x58dC9585BE36e855bA30609909f7D4Ef11313ee1)
<h1 align="center">
  <a href="#">
    <img src="https://i6.imageban.ru/out/2023/03/31/bd7858f71a504758b191b2e1c5d11d70.gif" alt="TMRR">
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
