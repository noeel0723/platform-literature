# Panduan Demonstrasi Semantic Mapping Literahaven

Panduan ini membantu menjelaskan semantic mapping Literahaven dengan contoh yang dapat ditunjukkan langsung saat presentasi. Istilah "semantic" di project ini berarti sistem memahami bahwa beberapa record API dapat mewakili karya yang sama, bukan sekadar menyamakan teks secara bebas.

## Tujuan demonstrasi

Tunjukkan tiga hal berikut:

1. Satu karya dapat datang dari lebih dari satu API.
2. Literahaven menyimpan seluruh record sumber untuk menjaga provenance, tetapi membuat satu `canonical_work` sebagai identitas karya bersama.
3. Catalog dan global search menampilkan satu record terbaik, bukan duplikat dari setiap API.

## Contoh utama: The Silver Chair

Bayangkan Hardcover mengirim data berikut:

```text
Sumber: Hardcover
Judul: The Silver Chair
Author: C. S. Lewis
Jenis: novel
Tahun karya: 1953
ID sumber: hardcover-silver-chair
```

Google Books dapat mengirim edisi lain:

```text
Sumber: Google Books
Judul: The Silver Chair
Author: C. S. Lewis
Jenis: novel
Tahun edisi: 2002
ISBN: 9780064471091
```

Walaupun tahun dan ID sumber berbeda, keduanya dapat dipetakan ke karya canonical yang sama karena judul yang sudah dinormalisasi, author canonical, dan jenis literaturnya cocok. Untuk novel, perbedaan tahun dapat dianggap sebagai perbedaan edisi melalui metode `title_author_edition`.

## Alur yang dapat digambar saat menjelaskan

```text
Hardcover record ---------\
                           > canonical_work: The Silver Chair
Google Books edition -----/
                                  |
                                  v
                      preferred_literature_id
                                  |
                                  v
                   satu card terbaik di Catalog
```

Record Hardcover dan Google Books tidak dihapus. Hubungannya disimpan di `literature_source_mappings`, sedangkan asal tiap metadata disimpan pada `field_provenance`.

## Tahapan pemetaan di dalam aplikasi

### 1. Normalisasi

`LiteratureIdentityNormalizer` membersihkan judul menjadi bentuk pembanding yang konsisten. Huruf dibuat lowercase, spasi dirapikan, dan tanda baca dipisahkan. Contoh:

```text
"One Piece"  -> "one piece"
"One-Piece"  -> "one piece"
```

Identifier juga dinormalisasi. ISBN yang valid disimpan sebagai ISBN global. ID khusus sumber tetap diberi namespace, misalnya `source:hardcover`, sehingga ID angka dari Comic Vine tidak keliru dianggap sebagai ISBN.

### 2. Pencarian identitas kuat

Resolver lebih dahulu mencari identifier yang benar-benar sama, seperti ISBN atau Google Knowledge Graph ID. Jika satu kandidat ditemukan dan jenis literaturnya sama, record dipetakan dengan metode `external_identifier`.

### 3. Pencocokan gabungan

Jika tidak ada identifier bersama, sistem membandingkan kombinasi berikut:

- judul yang sudah dinormalisasi;
- author canonical;
- jenis literature;
- tahun terbit jika relevan.

Kombinasi judul + author + jenis + tahun menghasilkan metode `title_author_year`. Khusus novel, judul + author + jenis masih dapat menyatukan edisi berbeda melalui `title_author_edition`.

### 4. Perlindungan dari false merge

Sistem tidak melakukan auto-merge hanya karena dua judul terlihat mirip. Judul sama dengan author berbeda, author kosong, atau jenis berbeda tetap dibuat sebagai karya terpisah. Kasus ambigu diberi metode `ambiguous_title_separate`.

### 5. Pemilihan record terbaik

Setelah beberapa record terhubung ke satu karya, `CanonicalLiteratureProjector` memberi quality score. Penilaian mempertimbangkan prioritas sumber, kelengkapan author, identifier, synopsis, publisher, tahun, kategori, Knowledge Graph, dan kualitas cover. Hasil terbaik menjadi `preferred_literature_id` yang ditampilkan oleh Catalog dan Search.

## Demo aman dengan automated test

Jalankan dari root project:

```powershell
php artisan test --compact tests/Feature/Services/Literature/SemanticLiteratureResolverTest.php
php artisan test --compact tests/Feature/Services/Literature/CanonicalLiteratureProjectionTest.php
```

Test memakai database testing, sehingga tidak mengubah catalog utama. Poin yang dapat dijelaskan dari hasil test:

- ISBN sama dari dua sumber menghasilkan satu canonical work.
- `One Piece` dan `One-Piece` dapat menjadi satu karya bila author, jenis, dan tahun sama.
- edisi novel dengan tahun berbeda dapat menjadi satu karya.
- judul sama dengan author berbeda tidak digabung otomatis.
- record terbaik dipilih tanpa menghapus provenance record lain.

Jika muncul pesan `could not find driver`, aktifkan ekstensi `pdo_sqlite` dan `sqlite3` pada `php.ini` milik PHP Laragon, lalu ulangi perintah test. Ini hanya kebutuhan database testing; aplikasi utama tetap dapat memakai MySQL.

## Demo melalui antarmuka

1. Buka Catalog dan pilih format `Novel`.
2. Cari satu karya populer, misalnya `The Silver Chair`.
3. Tunjukkan bahwa hasil tidak menampilkan satu card Hardcover dan satu card Google Books untuk karya yang sama.
4. Buka detail literature dan tunjukkan metadata lengkap yang diproyeksikan dari record terbaik.
5. Cari judul yang sama melalui Global Search untuk menunjukkan bahwa query tersebut juga memakai representasi canonical.

Catatan: hasil UI bergantung pada API key, ketersediaan API, dan data yang sudah tersinkron. Gunakan automated test sebagai demonstrasi deterministik bila internet atau API sedang tidak stabil.

## Memeriksa hasil pemetaan di database

Gunakan database viewer atau HeidiSQL dalam mode baca dan periksa tabel berikut:

| Tabel | Yang diperlihatkan |
| --- | --- |
| `literatures` | record mentah/operasional dari masing-masing sumber |
| `canonical_works` | satu identitas karya bersama dan record pilihan |
| `literature_source_mappings` | hubungan record API ke karya canonical, metode, confidence, quality score, dan provenance |
| `canonical_work_identifiers` | ISBN, Knowledge Graph ID, dan ID bernamespace sumber |
| `authors` dan `author_aliases` | author canonical dan variasi penulisan namanya |

Contoh query baca:

```sql
SELECT
    cw.canonical_title,
    cw.type,
    l.title AS source_title,
    api.key AS source,
    lsm.match_method,
    lsm.confidence,
    lsm.quality_score,
    cw.preferred_literature_id = l.id AS is_preferred
FROM canonical_works cw
JOIN literature_source_mappings lsm ON lsm.canonical_work_id = cw.id
JOIN literatures l ON l.id = lsm.literature_id
JOIN api_sources api ON api.id = lsm.api_source_id
WHERE cw.normalized_title = 'the silver chair'
ORDER BY lsm.quality_score DESC;
```

Query ini hanya membaca data. Nilai `is_preferred = 1` menandakan record yang dipakai sebagai card utama.

## Script penjelasan singkat untuk presentasi

> Hardcover dan Google Books bisa mengirim dua record berbeda untuk buku yang sama. Literahaven membersihkan judul dan identifier, lalu membandingkan identitas kuat seperti ISBN. Jika identifier tidak tersedia, sistem memakai kombinasi judul, author, jenis, dan tahun dengan aturan khusus untuk edisi novel. Kedua record sumber tetap disimpan agar asal data tidak hilang, tetapi keduanya menunjuk ke satu canonical work. Dari kelompok tersebut, sistem memilih metadata paling lengkap dan cover terbaik untuk ditampilkan. Sistem juga sengaja tidak menggabungkan karya hanya berdasarkan kemiripan judul supaya dua karya berbeda tidak salah disatukan.

## Batasan yang perlu disampaikan dengan jujur

- Semantic mapping ini berbasis aturan dan entity resolution, bukan model embedding atau AI generatif.
- Kualitas hasil tetap bergantung pada identifier dan author yang diberikan sumber API.
- Typo besar atau judul terjemahan yang tidak memiliki identifier bersama belum otomatis dianggap sama.
- Kasus ambigu sengaja dipisahkan agar lebih aman daripada melakukan merge yang salah.
