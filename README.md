# TMRR — Torrent Merkle Root Reader
<h1 align="center">
  <a href="#">
    <img src="https://i4.imageban.ru/out/2023/05/21/42ba453949a2b19a204baf2eb1c370a8.gif" alt="TMRR">
  </a>
</h1>

Useful for finding the sources of the same copies of files on different trackers & DHT indexers that support BitTorrent v2 protocol, thus reviving dead torrents, valuable Internet artifacts.

> [!NOTE]  
> Generating magnet download links without duplicates is currently not usable due to client features, the [ongoing](https://github.com/arvidn/libtorrent/issues/7439) issue with piece alignment files, hopefully it will be resolved soon.

Navigate to the Releases, and download your OS build.


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

Change the language (supported codes ```ru, de, en, zh```):
```
tmrr locale de
``` 

Reviews are welcome.