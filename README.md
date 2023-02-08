# TMRR — Torrent Merkle Root Reader

Standalone windows tool to read contents of BitTorrent v2 compatible .torrent files and show their Merkle root hash.

<h1 align="center">
  <a href="#">
    <img src="https://media.giphy.com/media/kTi46X3FSLI3Q0Zn7j/giphy.gif" alt="TMRR">
  </a>
</h1>

Usage:
```
tmrr.exe example.torrent
```
To save output:
```
tmrr.exe example.torrent > output.txt
```

Useful for finding the sources of the same copies of files on different trackers that support BitTorrent v2, thus reviving dead torrents.
