# 03 - Scope & Entity Taxonomy

## Core model

Semua objek yang bisa dicari dan diberi sentimen direpresentasikan sebagai `entity`.

### Entity types MVP

- `brand` - contoh Biznet Gio, Samsung, Telkomsel.
- `product` - contoh Samsung Galaxy A57, Honda HR-V.
- `service` - contoh VPS Biznet Gio, Hosting IDCloudHost, IndiHome.
- `person` - tokoh publik individual, contoh Joko Widodo, Raffi Ahmad, Jonatan Christie. Ditambahkan
  5 September 2026 untuk kategori Tokoh Publik (lihat ADR-010 di `docs/21` untuk override cakupan
  politik).

## Parent-child

Parent-child digunakan hanya untuk navigasi/entity matching, bukan untuk otomatis menurunkan sentiment.

Contoh:

```text
Biznet Gio (brand)
├── VPS Biznet Gio (service)
└── Cloud Hosting Biznet Gio (service)
```

Opini `Biznet Gio bagus` masuk ke Biznet Gio. Opini `VPS Biznet Gio bagus` masuk ke VPS Biznet Gio. Sistem tidak otomatis menyalin opini brand ke semua service.

## Category taxonomy MVP

- Technology
  - Smartphone
  - Cloud & Hosting
  - ISP & Telco
- Automotive
  - Mobil
  - Motor
- Digital Services
  - E-commerce
  - Ride Hailing
  - Logistics
  - Digital Finance (opsional seed terbatas)
- Consumer Brands
  - Brand Umum
- Tokoh Publik
  - Politisi
  - Selebriti & Artis
  - Atlet
  - Pengusaha
  - Kreator Konten

## Entity fields minimum

- name
- slug
- type
- category_id
- parent_id nullable
- aliases
- description pendek untuk disambiguasi
- image/logo optional
- status
- searchable
- rankable

## Alias examples

```text
Samsung Galaxy A57
- Galaxy A57
- Samsung A57
- A57 Samsung

IDCloudHost
- ID Cloud Host
- idcloudhost

VPS IDCloudHost
- IDCloudHost VPS
- VPS IDCLOUDHOST
```

## Rules

- Jangan membuat entity terlalu granular jika netizen tidak menyebutnya secara konsisten.
- Generic service lebih baik daripada plan/SKU yang cepat berubah.
- Entity baru dapat masuk dari admin atau suggestion queue setelah search demand terlihat.
- Kategori tidak butuh aspect taxonomy manual (battery/camera/... per Smartphone, dst); Top Suara
  Netijen (`docs/25`) menemukan tema langsung dari data, lintas semua entity type.
