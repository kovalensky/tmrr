# TMRR — Torrent Merkle Root Reader
<h1 align="center">
  <a href="#">
    <img src="https://media.giphy.com/media/X1ia2q41vzZQImSPdr/giphy.gif" alt="TMRR">
  </a>
</h1>

Useful for finding the sources of the same copies of files on different trackers that support BitTorrent v2, thus reviving dead torrents.

Extract root hashes from a .torrent file:
```
tmrr.exe example.torrent
```
Calcute root hashes for raw files:
```
tmrr.exe r your_file
```
To save output:
```
tmrr.exe your_command > output.txt
```

For the Linux environment on which PHP is installed, clone this repository, then:
```
php exe.php your_command
```
