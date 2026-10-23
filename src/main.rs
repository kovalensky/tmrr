// src/main.rs
use std::collections::{BTreeMap, HashMap, HashSet};
use std::env;
use std::fs::{self, File};
use std::io::{self, IsTerminal, Read, Write};
use std::path::{Path, PathBuf};
use std::process::{self, Command};
use std::sync::OnceLock;

use chrono::{Local, TimeZone};
use sha1::{Digest, Sha1};
use sha2::Sha256;

const VERSION: &str = "2.4g";
const BLOCK_SIZE: usize = 16384;
const HASH_LENGTH: usize = 32;

// ─────────────────────────────────────────
// Global config & messages
// ─────────────────────────────────────────

static CONFIG: OnceLock<Config> = OnceLock::new();
static MSGS: OnceLock<Messages> = OnceLock::new();

fn config() -> &'static Config { CONFIG.get().unwrap() }
fn msgs() -> &'static Messages { MSGS.get().unwrap() }

struct Config {
    locale: String,
    ansi: bool,
    log_path: PathBuf,
}

struct Messages {
    main: String,
    noraw: &'static str,
    invalid_torrent: &'static str,
    no_v2: &'static str,
    hint_v1: &'static str,
    root_hash: &'static str,
    calculation: &'static str,
    torrent_title: &'static str,
    file_location: &'static str,
    unfinished_files: &'static str,
    error_type: &'static str,
    note: &'static str,
    no_duplicates: &'static str,
    dup_found: &'static str,
    total_files: &'static str,
    total_dup_files: &'static str,
    magnet_proposal: &'static str,
    magnet_copy: &'static str,
    created_by_client: &'static str,
    lang_change: &'static str,
}

fn get_messages(locale: &str) -> Messages {
    match locale {
        "ru" => Messages {
            main: format!("\nСинтаксис:\n\n\n tmrr e <торрент-файл>\t*Извлекает хеши файлов из торрентов*\n\n tmrr d <торрент-файл>\t*Находит дубликаты файлов в торрент(ах)*\n\n tmrr c <ваш-файл>\t*Вычисляет хеш существующего файла*\n\n\n** Поддерживается пакетная обработка, как <файл1> <файл2>.. <файлN>.\n\n---\n\nВерсия: {} Грибовская\nАвтор: Коваленский Константин\n\n", VERSION),
            noraw: "Укажите расположение файла, он не должен быть пустым.",
            invalid_torrent: "Неопознанный .torrent файл.",
            no_v2: "Торрент файл не содержит признаки v2 / гибрида.",
            hint_v1: "Торренты v1 протокола не поддерживают привязку хешей файлов.",
            root_hash: "Хеш",
            calculation: "Вычисление",
            torrent_title: "Название раздачи",
            file_location: "Файл",
            unfinished_files: "Необработанные файлы",
            error_type: "Ошибка",
            note: "Заметка",
            no_duplicates: "Дубликатов не найдено.",
            dup_found: "найден в",
            total_files: "Количество файлов",
            total_dup_files: "Количество дубликатов",
            magnet_proposal: "Создать магнит ссылку для загрузки раздачи без дубликатов? Да ([Enter]) | Нет (n) : ",
            magnet_copy: "Скопируйте магнит ссылку в торрент клиент.",
            created_by_client: "Создан",
            lang_change: "Язык изменён на: Русский.",
        },
        "zh" => Messages {
            main: format!("\n请使用正确的格式, 例如:\n\n\n tmrr e <torrent 文件>\t*提取 .torrent 文件的哈希值*\n\n tmrr d <torrent 文件>\t*查找 .torrent 文件中的重复项*\n\n tmrr c <你的文件>\t*计算文件的哈希值*\n\n\n** 支持同时处理多个文件, 使用空格分隔 <文件1> <文件2>.. <文件N>.\n\n---\n\n版本号: {}\n作者: Constantine Kovalensky\n\n", VERSION),
            noraw: "文件无效或不存在",
            invalid_torrent: "无效的 torrent 文件",
            no_v2: "torrent 文件不是受支持的 v2 / hybrid 协议",
            hint_v1: "torrent v1 协议不支持内嵌文件哈希值",
            root_hash: "哈希值",
            calculation: "处理中",
            torrent_title: "文件标题",
            file_location: "文件名",
            unfinished_files: "未能处理的文件",
            error_type: "错误",
            note: "注释",
            no_duplicates: "文件中不包含重复项",
            dup_found: "存在于以下文件中",
            total_files: "总文件数量",
            total_dup_files: "重复文件数量",
            magnet_proposal: "是否创建一个已去重的磁力链? 是 ([Enter]) | 否 (n) : ",
            magnet_copy: "请将磁力链粘贴至您的任意客户端中",
            created_by_client: "来源",
            lang_change: "当前语言已被修改为: 中文。",
        },
        "de" => Messages {
            main: format!("\nBitte benutzen sie das richtige syntax, wie:\n\n\n tmrr e <Torrent-Datei>\t*Extrahieren sie die Datei hashes von .torrent Dateien*\n\n tmrr d <Torrent-Datei>\t*Finden sie Dublikate Dateien in .torrent Dateie(n)*\n\n tmrr c <deine-Datei>\t*Berechnet die hash der existierenden Datei*\n\n\n** Beitung vorgehen wird unterstützt, so wie <Datei1> <Datei2>.. <DateiN>.\n\n---\n\nVersion: {}\nAutor: Constantine Kovalensky\n\n", VERSION),
            noraw: "Dies ist keine zulässige Datei, ist sie leer?",
            invalid_torrent: "Unzulässige torrent Datei.",
            no_v2: "Dies ist eine unzulässige v2 / hybrid torrent.",
            hint_v1: "v1 protokoll torrents unterstützen keine eingebettenen Datei hashes.",
            root_hash: "Hash",
            calculation: "Prozessvorgang",
            torrent_title: "Name",
            file_location: "Datei",
            unfinished_files: "Unprozessierte Dateien",
            error_type: "Fehler Typ",
            note: "Notiz",
            no_duplicates: "Keine Dublikate wurden gefunden.",
            dup_found: "gefunden",
            total_files: "Gesamte Dateien",
            total_dup_files: "Doppelter Inhalt",
            magnet_proposal: "Erstelle einen Magneten download link ohne Dublikate? Ja ([Enter]) | Nein (n) : ",
            magnet_copy: "Kopieren sie diesen magneten link in ihr torrent Programm.",
            created_by_client: "Ertellt von",
            lang_change: "Sprache geändert zu: Deutsch.",
        },
        _ => Messages {
            main: format!("\nPlease use the correct syntax, as:\n\n\n tmrr e <torrent-file>\t*Extracts file hashes from .torrent files*\n\n tmrr d <torrent-file>\t*Finds duplicate files within .torrent file(s)*\n\n tmrr c <your-file>\t*Calculates the hash of existing files*\n\n\n** Batch processing is supported, such as <file1> <file2>.. <fileN>.\n\n---\n\nVersion: {}\nAuthor: Constantine Kovalensky\n\n", VERSION),
            noraw: "This is not a valid file, is it empty?",
            invalid_torrent: "Invalid torrent file.",
            no_v2: "This is an invalid v2 / hybrid torrent.",
            hint_v1: "v1 protocol torrents do not support embedding file hashes.",
            root_hash: "Hash",
            calculation: "Processing",
            torrent_title: "Name",
            file_location: "File",
            unfinished_files: "Unprocessed files",
            error_type: "Error type",
            note: "Note",
            no_duplicates: "No duplicates were found.",
            dup_found: "found in",
            total_files: "Total files",
            total_dup_files: "Duplicate count",
            magnet_proposal: "Create a magnet download link without duplicates? Yes ([Enter]) | No (n) : ",
            magnet_copy: "Paste this magnet link into your torrent client.",
            created_by_client: "Created by",
            lang_change: "Language changed to: English.",
        },
    }
}

// ─────────────────────────────────────────
// Config persistence
// ─────────────────────────────────────────

fn config_path() -> PathBuf {
    env::current_exe()
        .ok()
        .and_then(|p| p.parent().map(|d| d.to_path_buf()))
        .unwrap_or_else(|| PathBuf::from("."))
        .join("tmrr.ini")
}

fn load_config() -> Config {
    let path = config_path();
    let mut cfg = Config {
        locale: "en".to_string(),
        ansi: true,
        log_path: env::current_exe()
            .ok()
            .and_then(|p| p.parent().map(|d| d.to_path_buf()))
            .unwrap_or_else(|| PathBuf::from("."))
            .join("incident.log"),
    };
    if let Ok(content) = fs::read_to_string(&path) {
        for line in content.lines() {
            let line = line.trim();
            if line.is_empty() || line.starts_with('#') || line.starts_with('[') {
                continue;
            }
            if let Some((k, v)) = line.split_once('=') {
                let k = k.trim();
                let v = v.trim().trim_matches('"');
                match k {
                    "locale" => cfg.locale = v.to_string(),
                    "ansi" => cfg.ansi = matches!(v, "on" | "true" | "1"),
                    "log" => cfg.log_path = PathBuf::from(v),
                    _ => {}
                }
            }
        }
    }
    cfg
}

fn save_config(cfg: &Config) {
    let content = format!(
        "locale = {}\nansi = {}\nlog = {}\n",
        cfg.locale,
        if cfg.ansi { "on" } else { "off" },
        cfg.log_path.display()
    );
    let _ = fs::write(config_path(), content);
}

// ─────────────────────────────────────────
// Bencode
// ─────────────────────────────────────────

#[derive(Debug, Clone)]
enum Value {
    Int(i64),
    Str(Vec<u8>),
    List(Vec<Value>),
    Dict(BTreeMap<Vec<u8>, Value>),
}

impl Value {
    fn as_dict(&self) -> Option<&BTreeMap<Vec<u8>, Value>> {
        if let Value::Dict(d) = self { Some(d) } else { None }
    }
    fn as_str(&self) -> Option<&[u8]> {
        if let Value::Str(s) = self { Some(s) } else { None }
    }
    fn as_int(&self) -> Option<i64> {
        if let Value::Int(i) = self { Some(*i) } else { None }
    }
    fn as_list(&self) -> Option<&Vec<Value>> {
        if let Value::List(l) = self { Some(l) } else { None }
    }
}

fn bencode_decode(data: &[u8]) -> Option<Value> {
    let mut pos = 0usize;
    let result = bencode_decode_at(data, &mut pos)?;
    if pos != data.len() { None } else { Some(result) }
}

fn bencode_decode_at(data: &[u8], pos: &mut usize) -> Option<Value> {
    if *pos >= data.len() { return None; }
    match data[*pos] {
        b'd' => {
            *pos += 1;
            let mut map = BTreeMap::new();
            while *pos < data.len() && data[*pos] != b'e' {
                let k = bencode_decode_at(data, pos)?;
                let v = bencode_decode_at(data, pos)?;
                let k = match k { Value::Str(s) => s, _ => return None };
                map.insert(k, v);
            }
            if *pos >= data.len() { return None; }
            *pos += 1;
            Some(Value::Dict(map))
        }
        b'l' => {
            *pos += 1;
            let mut vec = Vec::new();
            while *pos < data.len() && data[*pos] != b'e' {
                vec.push(bencode_decode_at(data, pos)?);
            }
            if *pos >= data.len() { return None; }
            *pos += 1;
            Some(Value::List(vec))
        }
        b'i' => {
            *pos += 1;
            let start = *pos;
            while *pos < data.len() && data[*pos] != b'e' { *pos += 1; }
            if *pos >= data.len() { return None; }
            let s = std::str::from_utf8(&data[start..*pos]).ok()?;
            let i: i64 = s.parse().ok()?;
            if i.to_string() != s { return None; } // reject non-canonical forms
            *pos += 1;
            Some(Value::Int(i))
        }
        b'0'..=b'9' => {
            let start = *pos;
            while *pos < data.len() && data[*pos] != b':' { *pos += 1; }
            if *pos >= data.len() { return None; }
            let len_str = std::str::from_utf8(&data[start..*pos]).ok()?;
            let len: usize = len_str.parse().ok()?;
            if len.to_string() != len_str { return None; }
            *pos += 1;
            if *pos + len > data.len() { return None; }
            let s = data[*pos..*pos + len].to_vec();
            *pos += len;
            Some(Value::Str(s))
        }
        _ => None,
    }
}

fn bencode_encode(v: &Value) -> Vec<u8> {
    let mut out = Vec::new();
    bencode_encode_into(v, &mut out);
    out
}

fn bencode_encode_into(v: &Value, out: &mut Vec<u8>) {
    match v {
        Value::Int(i) => {
            out.push(b'i');
            out.extend_from_slice(i.to_string().as_bytes());
            out.push(b'e');
        }
        Value::Str(s) => {
            out.extend_from_slice(s.len().to_string().as_bytes());
            out.push(b':');
            out.extend_from_slice(s);
        }
        Value::List(l) => {
            out.push(b'l');
            for item in l { bencode_encode_into(item, out); }
            out.push(b'e');
        }
        Value::Dict(d) => {
            out.push(b'd');
            for (k, v) in d {
                bencode_encode_into(&Value::Str(k.clone()), out);
                bencode_encode_into(v, out);
            }
            out.push(b'e');
        }
    }
}

// ─────────────────────────────────────────
// Torrent
// ─────────────────────────────────────────

struct Torrent {
    decoded: Value,
}

impl Torrent {
    fn get(&self, key: &[u8]) -> Option<&Value> {
        self.decoded.as_dict()?.get(key)
    }
    fn info(&self) -> Option<&BTreeMap<Vec<u8>, Value>> {
        self.get(b"info".as_slice())?.as_dict()
    }
    fn file_tree(&self) -> Option<&BTreeMap<Vec<u8>, Value>> {
        self.info()?.get(b"file tree".as_slice())?.as_dict()
    }
}

struct FileEntry {
    path: String,
    pieces_root: Vec<u8>,
    length: i64,
}

fn flatten_file_tree(tree: &BTreeMap<Vec<u8>, Value>) -> Vec<FileEntry> {
    let mut out = Vec::new();
    walk_tree(tree, "", &mut out);
    out
}

fn walk_tree(tree: &BTreeMap<Vec<u8>, Value>, parent: &str, out: &mut Vec<FileEntry>) {
    for (key, value) in tree {
        let name = String::from_utf8_lossy(key);
        let path = if parent.is_empty() { name.to_string() } else { format!("{}/{}", parent, name) };
        let dict = match value.as_dict() { Some(d) => d, None => continue };
        if let Some(Value::Dict(leaf)) = dict.get(b"".as_slice()) {
            let length = leaf.get(b"length".as_slice()).and_then(|v| v.as_int()).unwrap_or(0);
            let pieces_root = leaf.get(b"pieces root".as_slice())
                .and_then(|v| v.as_str()).map(|s| s.to_vec()).unwrap_or_default();
            out.push(FileEntry { path, pieces_root, length });
        } else {
            walk_tree(dict, &path, out);
        }
    }
}

// ─────────────────────────────────────────
// HasherV2 (BitTorrent v2 Merkle root)
// ─────────────────────────────────────────

struct HasherV2 {
    root: String,
}

impl HasherV2 {
    fn hash_file(path: &Path) -> io::Result<Self> {
        let mut file = File::open(path)?;
        let mut layer_hashes: Vec<Vec<u8>> = Vec::new();
        let padding = vec![0u8; HASH_LENGTH];
        let mut buf = vec![0u8; BLOCK_SIZE];
        loop {
            let n = file.read(&mut buf)?;
            if n == 0 { break; }
            let mut h = Sha256::new();
            h.update(&buf[..n]);
            layer_hashes.push(h.finalize().to_vec());
        }
        let count = layer_hashes.len();
        if count > 1 {
            let pow2 = next_power_2(count);
            for _ in 0..(pow2 - count) {
                layer_hashes.push(padding.clone());
            }
        }
        let root = hex::encode(merkle_root(&layer_hashes));
        Ok(HasherV2 { root })
    }
}

fn next_power_2(v: usize) -> usize {
    let mut p = 1;
    while p < v { p <<= 1; }
    p
}

fn merkle_root(blocks: &[Vec<u8>]) -> Vec<u8> {
    if blocks.is_empty() { return Vec::new(); }
    let mut cur: Vec<Vec<u8>> = blocks.to_vec();
    while cur.len() > 1 {
        let mut next = Vec::with_capacity((cur.len() + 1) / 2);
        let mut i = 0;
        while i < cur.len() {
            let mut h = Sha256::new();
            h.update(&cur[i]);
            if i + 1 < cur.len() { h.update(&cur[i + 1]); }
            next.push(h.finalize().to_vec());
            i += 2;
        }
        cur = next;
    }
    cur[0].clone()
}

// ─────────────────────────────────────────
// Formatting helpers
// ─────────────────────────────────────────

fn is_tty() -> bool { io::stdout().is_terminal() }

fn format_text_raw(text: &str, color: u8, ansi: bool, tty: bool) -> String {
    if ansi && tty {
        format!("\x1b[38;5;{}m{}\x1b[0m", color, text)
    } else {
        text.to_string()
    }
}

fn format_text(text: &str, color: u8) -> String {
    format_text_raw(text, color, config().ansi, is_tty())
}

fn format_bytes(bytes: i64) -> String {
    const UNITS: [&str; 5] = ["B", "KB", "MB", "GB", "TB"];
    let b = bytes as f64;
    let pow = if b > 0.0 {
        (b.log(1024.0).floor() as usize).min(4)
    } else { 0 };
    let v = b / 1024f64.powi(pow as i32);
    let rounded = (v * 100.0).round() / 100.0;
    format!("{} {}", rounded, UNITS[pow])
}

fn format_date(ts: i64) -> String {
    Local.timestamp_opt(ts, 0).single()
        .map(|d| d.format("%d %b %Y | %H:%M:%S").to_string())
        .unwrap_or_else(|| "?".to_string())
}

fn format_seq(numbers: &[usize]) -> String {
    let mut seqs = Vec::new();
    let mut start = 0usize;
    let n = numbers.len();
    for i in 0..n {
        let cur = numbers[i];
        if i == 0 || cur != numbers[i - 1] + 1 { start = cur; }
        if i == n - 1 || numbers[i + 1] != cur + 1 {
            if start != cur { seqs.push(format!("{}-{}", start, cur)); }
            else { seqs.push(cur.to_string()); }
        }
    }
    seqs.join(",")
}

fn urlencode(data: &[u8]) -> String {
    const HEX: &[u8; 16] = b"0123456789ABCDEF";
    let mut out = String::with_capacity(data.len());
    for &b in data {
        match b {
            b'A'..=b'Z' | b'a'..=b'z' | b'0'..=b'9' | b'-' | b'_' | b'.' => out.push(b as char),
            b' ' => out.push('+'),
            _ => {
                out.push('%');
                out.push(HEX[(b >> 4) as usize] as char);
                out.push(HEX[(b & 0x0F) as usize] as char);
            }
        }
    }
    out
}

fn sha1_hex(data: &[u8]) -> String {
    let mut h = Sha1::new();
    h.update(data);
    hex::encode(h.finalize())
}

fn die(msg: &str) -> ! {
    println!("{}", msg);
    process::exit(0);
}

// ─────────────────────────────────────────
// Torrent loading & validation
// ─────────────────────────────────────────

fn load_torrent(file: &str, errors: &mut Vec<(String, String)>) -> Option<Torrent> {
    let data = match fs::read(file) {
        Ok(d) => d,
        Err(_) => {
            errors.push((file.to_string(), msgs().invalid_torrent.to_string()));
            return None;
        }
    };
    let decoded = match bencode_decode(&data) {
        Some(d) => d,
        None => {
            errors.push((file.to_string(), msgs().invalid_torrent.to_string()));
            return None;
        }
    };
    let torrent = Torrent { decoded };
    let info = match torrent.info() {
        Some(i) => i,
        None => {
            errors.push((file.to_string(), msgs().invalid_torrent.to_string()));
            return None;
        }
    };
    let is_v2 = matches!(info.get(b"meta version".as_slice()), Some(Value::Int(2)))
        && matches!(info.get(b"file tree".as_slice()), Some(Value::Dict(_)));

    if !is_v2 {
        let mut key = file.to_string();

        // Title (if filename stem differs from internal name)
        if let Some(Value::Str(name)) = info.get(b"name".as_slice()) {
            let stem = Path::new(file).file_stem().and_then(|s| s.to_str()).unwrap_or("");
            let name_s = String::from_utf8_lossy(name);
            if stem != name_s {
                key.push_str(&format!("\n{}: {}", format_text(msgs().torrent_title, 250), name_s));
            }
        }
        // v1 info hash (hybrid)
        if info.contains_key(b"pieces".as_slice()) {
            let v1 = sha1_hex(&bencode_encode(&Value::Dict(info.clone())));
            key.push_str(&format!("\n{}: {}", format_text(msgs().root_hash, 250), v1));
        }
        // Creation metadata
        if let (Some(Value::Int(date)), Some(Value::Str(client))) = (
            torrent.get(b"creation date".as_slice()),
            torrent.get(b"created by".as_slice()),
        ) {
            key.push_str(&format!("\n{}: {} ({})",
                format_text(msgs().created_by_client, 250),
                String::from_utf8_lossy(client),
                format_date(*date)));
        }

        let mut message = msgs().no_v2.to_string();
        if !info.contains_key(b"meta version".as_slice()) {
            message.push_str(&format!("\n{}: {}", format_text(msgs().note, 250), msgs().hint_v1));
        }
        errors.push((key, message));
        return None;
    }
    Some(torrent)
}

fn print_torrent_metainfo(file: &str, torrent: &Torrent) {
    println!("\n — {}: {} —", format_text(msgs().file_location, 250), file);
    if let Some(Value::Str(name)) = torrent.info().and_then(|i| i.get(b"name".as_slice())) {
        let stem = Path::new(file).file_stem().and_then(|s| s.to_str()).unwrap_or("");
        let name_s = String::from_utf8_lossy(name);
        if stem != name_s {
            println!(" — {}: {} —", format_text(msgs().torrent_title, 250), name_s);
        }
    }
    if let (Some(Value::Int(date)), Some(Value::Str(client))) = (
        torrent.get(b"creation date".as_slice()),
        torrent.get(b"created by".as_slice()),
    ) {
        println!(" — {}: {} ({}) —",
            format_text(msgs().created_by_client, 250),
            String::from_utf8_lossy(client),
            format_date(*date));
    }
}

// ─────────────────────────────────────────
// Commands
// ─────────────────────────────────────────

fn cmd_e(files: &[String]) {
    let mut errors: Vec<(String, String)> = Vec::new();

    for file in files {
        let torrent = match load_torrent(file, &mut errors) { Some(t) => t, None => continue };
        print_torrent_metainfo(file, &torrent);
        let tree = match torrent.file_tree() { Some(t) => t, None => continue };
        let entries = flatten_file_tree(tree);

        let mut total_size: i64 = 0;
        let mut count: usize = 0;
        for entry in &entries {
            let size = format_bytes(entry.length);
            let root = hex::encode(&entry.pieces_root);
            println!("\n  {} ({})\n {}: {}",
                entry.path,
                format_text(&size, 250),
                format_text(msgs().root_hash, 250),
                root);
            total_size += entry.length;
            count += 1;
        }
        println!("\n{}: {} ({})\n",
            format_text(msgs().total_files, 250),
            count,
            format_bytes(total_size));
    }
    error_status(&errors);
}

struct HashEntry {
    path: String,
    hash_str: String,
    size: i64,
    pos: usize,
}

fn collect_hashes(
    tree: &BTreeMap<Vec<u8>, Value>,
    prefix: &str,
    parent: &str,
    out: &mut Vec<HashEntry>,
    filec: &mut usize,
    total_size: &mut i64,
) {
    for (key, value) in tree {
        let name = String::from_utf8_lossy(key);
        let path = if parent.is_empty() { name.to_string() } else { format!("{}/{}", parent, name) };
        let dict = match value.as_dict() { Some(d) => d, None => continue };
        if let Some(Value::Dict(leaf)) = dict.get(b"".as_slice()) {
            let length = leaf.get(b"length".as_slice()).and_then(|v| v.as_int()).unwrap_or(0);
            let pieces_root = leaf.get(b"pieces root".as_slice())
                .and_then(|v| v.as_str()).map(|s| s.to_vec()).unwrap_or_default();
            let hash_str = format!("{} ({})", hex::encode(&pieces_root), format_bytes(length));
            let display = format!("{}/{}", prefix, path);
            out.push(HashEntry { path: display, hash_str, size: length, pos: *filec });
            *total_size += length;
            *filec += 1;
        } else {
            collect_hashes(dict, prefix, &path, out, filec, total_size);
        }
    }
}

fn cmd_d(files: &[String], single: bool) {
    let mut errors: Vec<(String, String)> = Vec::new();
    let mut torrents: Vec<(String, Torrent)> = Vec::new();
    for file in files {
        if let Some(t) = load_torrent(file, &mut errors) {
            torrents.push((file.clone(), t));
        }
    }
    if torrents.is_empty() {
        error_status(&errors);
        return;
    }

    let mut hashes: Vec<HashEntry> = Vec::new();
    let mut filec: usize = 0;
    let mut torrent_size: i64 = 0;
    for (file, torrent) in &torrents {
        let tree = match torrent.file_tree() { Some(t) => t, None => continue };
        let prefix = format_text(&format!("  {}:  ", file), 250);
        collect_hashes(tree, &prefix, "", &mut hashes, &mut filec, &mut torrent_size);
    }

    // Build ordered groups
    let mut groups: Vec<(String, Vec<usize>)> = Vec::new();
    let mut lookup: HashMap<String, usize> = HashMap::new();
    let mut magnet_indices: Vec<usize> = Vec::new();
    let mut dups_size: i64 = 0;
    for (i, entry) in hashes.iter().enumerate() {
        if let Some(&g) = lookup.get(&entry.hash_str) {
            groups[g].1.push(i);
            dups_size += entry.size;
        } else {
            lookup.insert(entry.hash_str.clone(), groups.len());
            groups.push((entry.hash_str.clone(), vec![i]));
            magnet_indices.push(entry.pos);
        }
    }

    if single {
        print_torrent_metainfo(&torrents[0].0, &torrents[0].1);
    }

    let mut filed = 0usize;
    let mut dup_hashes = 0usize;
    for (hash_str, indices) in &groups {
        if indices.len() > 1 {
            let paths: Vec<&str> = indices.iter().map(|&i| hashes[i].path.as_str()).collect();
            println!("\n {} {} {}:\n\n{}\n",
                msgs().root_hash, hash_str, msgs().dup_found, paths.join("\n"));
            filed += indices.len();
            dup_hashes += 1;
        }
    }

    if dup_hashes == 0 {
        println!("\n {}\n\n{}: {} ({})\n",
            format_text(msgs().no_duplicates, 178),
            format_text(msgs().total_files, 250),
            filec,
            format_bytes(torrent_size));
    } else {
        let d_count = filed - dup_hashes;
        let d_sizes = format_bytes(dups_size);
        print!("{}: {} ({})\n{}: {} ({})",
            format_text(msgs().total_files, 250),
            filec,
            format_bytes(torrent_size),
            format_text(msgs().total_dup_files, 250),
            d_count,
            d_sizes);

        if single {
            if torrent_size > 0 {
                let pct = (dups_size as f64 / torrent_size as f64) * 100.0;
                let prec = if pct >= 0.01 { 2 } else if pct > 0.0 { (-pct.log10().floor()) as usize } else { 0 };
                println!(" | {:.*}%", prec, pct);
            } else {
                println!();
            }
            prompt_magnet(&torrents[0].1, &magnet_indices);
        } else {
            println!();
        }
    }
    error_status(&errors);
}

fn open_magnet(magnet: &str) {
    #[cfg(target_os = "windows")]
    {
        use std::os::windows::process::CommandExt;
        const CREATE_NO_WINDOW: u32 = 0x0800_0000;

        // Build the command line exactly like the PHP version does.
        // The magnet link MUST be wrapped in double quotes: otherwise
        // cmd.exe treats the '&' characters between magnet parameters
        // as command separators and the handler receives a truncated
        // URL (only the part before the first '&').
        let command_line = format!("/C start \"\" \"{}\"", magnet);

        if command_line.len() <= 8191 {
            let _ = Command::new("cmd")
                .raw_arg(&command_line)
                .creation_flags(CREATE_NO_WINDOW)
                .spawn();
        } else {
            println!("\r\n {} ", format_text(msgs().magnet_copy, 178));
        }
    }

    #[cfg(target_os = "linux")]
    {
        let _ = Command::new("xdg-open").arg(magnet).spawn();
    }

    #[cfg(target_os = "macos")]
    {
        let _ = Command::new("open").arg(magnet).spawn();
    }
}

fn prompt_magnet(torrent: &Torrent, indices: &[usize]) {
    let ansi = config().ansi && is_tty();
    if ansi {
        print!("\n\t{}\n\t", format_text(msgs().magnet_proposal, 178));
        let _ = io::stdout().flush();
    }
    let clean = "\x1b[2A\x1b[2K";
    let mut input = String::new();
    let _ = io::stdin().read_line(&mut input);
    let trimmed = input.trim_end_matches(['\r', '\n']);

    if trimmed.is_empty() {
        let magnet = magnet_gen(torrent, indices);
        if ansi { print!("{}", clean); }
        println!("\n{}\n", format_text(&magnet, 70));
        if is_tty() {
			open_magnet(&magnet)
        }
    } else if ansi {
        print!("{}\x1b[1B\x1b[2K", clean);
    }
}

fn magnet_gen(torrent: &Torrent, indices: &[usize]) -> String {
    let info = match torrent.info() { Some(i) => i, None => return String::new() };
    let info_bencoded = bencode_encode(&Value::Dict(info.clone()));

    let mut s = String::from("magnet:?xt=urn:btmh:1220");
    s.push_str(&hex::encode(Sha256::digest(&info_bencoded)));

    if info.contains_key(b"pieces".as_slice()) {
        s.push_str("&xt=urn:btih:");
        s.push_str(&sha1_hex(&info_bencoded));
    }

    if let Some(Value::Str(name)) = info.get(b"name".as_slice()) {
        s.push_str("&dn=");
        s.push_str(&urlencode(name));
    }

    // Trackers
    if torrent.get(b"announce-list".as_slice()).is_none() {
        if let Some(Value::Str(ann)) = torrent.get(b"announce".as_slice()) {
            s.push_str("&tr=");
            s.push_str(&urlencode(ann));
        }
    }
    if let Some(Value::List(tiers)) = torrent.get(b"announce-list".as_slice()) {
        let first = tiers.first().and_then(|t| t.as_list());
        match first {
            Some(f) if f.len() > 1 => {
                for t in f {
                    if let Value::Str(b) = t {
                        s.push_str("&tr=");
                        s.push_str(&urlencode(b));
                    }
                }
            }
            _ => {
                for tier in tiers {
                    if let Value::List(t) = tier {
                        if let Some(Value::Str(b)) = t.first() {
                            s.push_str("&tr=");
                            s.push_str(&urlencode(b));
                        }
                    }
                }
            }
        }
    }

    // Web seeds
    match torrent.get(b"url-list".as_slice()) {
        Some(Value::Str(u)) => {
            s.push_str("&ws=");
            s.push_str(&urlencode(u));
        }
        Some(Value::List(urls)) => {
            for u in urls {
                if let Value::Str(b) = u {
                    s.push_str("&ws=");
                    s.push_str(&urlencode(b));
                }
            }
        }
        _ => {}
    }

    s.push_str("&so=");
    s.push_str(&format_seq(indices));
    s
}

fn cmd_c(files: &[String]) {
    let mut errors: Vec<(String, String)> = Vec::new();
    for file in files {
        let meta = match fs::metadata(file) {
            Ok(m) if m.is_file() && m.len() > 0 => m,
            _ => {
                errors.push((file.clone(), msgs().noraw.to_string()));
                continue;
            }
        };
        match HasherV2::hash_file(Path::new(file)) {
            Ok(hasher) => {
                let size = format_bytes(meta.len() as i64);
                println!("\n  {} ({})\n BTMR: {}\n",
                    file,
                    format_text(&size, 250),
                    hasher.root);
            }
            Err(e) => { errors.push((file.clone(), format!("{}", e))); }
        }
    }
    error_status(&errors);
}

fn error_status(errors: &[(String, String)]) {
    if errors.is_empty() || !is_tty() { return; }
    println!("\n\n--- {}: ---", format_text(msgs().unfinished_files, 196));
    for (k, v) in errors {
        println!("\n{}: {}\n{}: {}\n",
            format_text(msgs().file_location, 250), k,
            format_text(msgs().error_type, 250), v);
    }
}

// ─────────────────────────────────────────
// Entry point
// ─────────────────────────────────────────

fn main() {
    let mut cfg = load_config();
    let args: Vec<String> = env::args().collect();

    // Config-modifying subcommands
    if args.len() >= 3 {
        match args[1].as_str() {
            "locale" => {
                let lang = &args[2];
                if !["ru", "en", "zh", "de"].contains(&lang.as_str()) {
                    die(&format!("Undefined language code \"{}\", supporting: ru, en, zh, de", lang));
                }
                cfg.locale = lang.clone();
                save_config(&cfg);
                let m = get_messages(&cfg.locale);
                die(&format_text_raw(&m.lang_change, 70, cfg.ansi, is_tty()));
            }
            "ansi" => {
                if args[2] == "on" || args[2] == "off" {
                    cfg.ansi = args[2] == "on";
                    save_config(&cfg);
                    let color = if args[2] == "on" { 70 } else { 250 };
                    die(&format_text_raw(&format!("ansi => {}", args[2]), color, cfg.ansi, is_tty()));
                }
            }
            "log" if args[2] == "export" => {
                let empty = !cfg.log_path.exists()
                    || fs::metadata(&cfg.log_path).map(|m| m.len() == 0).unwrap_or(true);
                if empty {
                    die(&format_text_raw("The log is empty, nothing to report", 178, cfg.ansi, is_tty()));
                }
                let _ = fs::copy(&cfg.log_path, "tmrr_incident.log");
                die(&format_text_raw("The log has been exported to the current directory", 250, cfg.ansi, is_tty()));
            }
            _ => {}
        }
    }

    let messages = get_messages(&cfg.locale);
    CONFIG.set(cfg).ok();
    MSGS.set(messages).ok();

    if args.len() <= 2 || !["e", "d", "c"].contains(&args[1].as_str()) {
        die(&msgs().main);
    }

    let method = args[1].clone();
    let single = args.len() < 4;

    // Deduplicate file arguments, preserving order
    let mut seen = HashSet::new();
    let mut files: Vec<String> = Vec::new();
    for f in &args[2..] {
        if seen.insert(f.clone()) {
            files.push(f.clone());
        }
    }

    match method.as_str() {
        "e" => cmd_e(&files),
        "d" => cmd_d(&files, single),
        "c" => cmd_c(&files),
        _ => unreachable!(),
    }
}