# 01 - Product Vision

## Problem

Opini publik tentang brand, produk, dan layanan tersebar di video, komentar, forum, komunitas dan jejaring sosial. User harus membuka banyak halaman untuk mendapatkan gambaran apakah percakapan publik cenderung positif atau negatif. Situs review biasanya hanya melihat review yang dikirim langsung ke platform; social listening tools umumnya ditujukan untuk perusahaan dan mahal.

## Vision

SuaraNetijen menjadi **indeks sentimen publik Indonesia**: user cukup mencari sebuah entitas dan langsung melihat kecenderungan opini netizen yang sudah diindeks.

## Core promise

> Cari apa pun. Lihat apa kata netijen.

## What SuaraNetijen is

- Search engine untuk entity sentiment.
- Crawler/indexer opini publik.
- Historical sentiment database.
- Ranking engine berdasarkan sentimen.
- Theme index yang merangkum tema paling sering dibicarakan netijen per entity (`docs/25`).
- Community rating platform sebagai data first-party terpisah.

## What SuaraNetijen is not

- Bukan lembaga survei.
- Bukan situs spesifikasi produk.
- Bukan price comparison.
- Bukan fact checker.
- Bukan review verification service.
- Bukan expert review publication.
- Bukan social network.

## Product principles

1. **Opinion, not truth.** Sistem mengukur kecenderungan opini, bukan menentukan opini yang benar.
2. **Derived index first.** Konten mentah hanya dibutuhkan untuk pemrosesan; indeks turunannya adalah aset utama.
3. **Simple public metrics.** Jangan membuat skor turunan yang sulit dijelaskan.
4. **Source diversity.** Coverage lintas source lebih penting daripada bergantung pada satu platform.
5. **No hidden mixing.** Rating manual tidak dicampur ke sentiment crawler.
6. **Volume transparency.** Tampilkan jumlah opini agar user tahu dasar data.
7. **Entity-centric.** Brand, product dan service menggunakan engine yang sama.
8. **Mobile-first and SEO-first.** Search adalah pintu utama, category/top-list adalah discovery dan acquisition layer.

## Long-term moat

- Historical sentiment time series.
- Entity aliases dan entity graph Indonesia.
- Source adapters dan crawl history.
- Canonical theme dictionary Bahasa Indonesia (`docs/25`), tumbuh seiring waktu.
- First-party ratings.
- Search demand data.
- Ranking history per kategori.
