# TMRR — Torrent Merkle Root Reader
<h1 align="center">
  <a href="#">
    <img src="https://i5.imageban.ru/out/2023/03/09/77730d0f3d77e6e5b3fbd538f7303e92.gif" alt="TMRR">
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
