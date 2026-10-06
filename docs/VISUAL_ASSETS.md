# Daftar gambar visual FTS Corporate AI

Status: daftar kebutuhan. Saat ini seluruh gambar scene memakai **placeholder acak** (picsum.photos) agar fokus ada pada desain, animasi, dan transisi. Ilustrasi final dibuat belakangan.

## Cara kerja placeholder

- `.env` lokal: `SCENE_IMAGE_SOURCE=placeholder`. URL dibangun oleh `App\Modules\Experience\Support\SceneImages` dengan seed tetap (`https://picsum.photos/seed/corporate-{nama}/{lebar}/{tinggi}`), sehingga gambar tidak berubah antar request dan tidak memuat data pengguna.
- Production selalu memaksa `local` (gate rilis: tidak ada gambar remote). Jangan mengubahnya.
- Gambar ruang dipotong `object-fit: cover` dengan overlay gelap, dan foto karakter placeholder dipotong mengikuti bingkai arch, sehingga foto acak tetap rapi.

## Beralih ke gambar final

1. Taruh berkas di `public/assets/corporate/{nama}.{ext}` sesuai tabel.
2. Di `.env`: `SCENE_IMAGE_SOURCE=local` dan `SCENE_IMAGE_EXTENSION=webp` (atau `png`/`svg`).
3. Tidak ada perubahan kode. Untuk menggeser titik fokus crop, atur `object-position` pada `.thumb img` atau `.canvas-head img`.

## Daftar gambar

| Nama berkas | Dipakai di | Ukuran | Format | Isi / arahan |
|---|---|---|---|---|
| `reception.{ext}` | Thumbnail slide 01 di sorter | 1600×900 (16:9) | WebP/JPG, ≤ 300 KB | Lobi kantor modern yang cerah. Hanya tampil kecil (thumbnail), komposisi bebas. |
| `knowledge.{ext}` | Thumbnail sorter + header kanvas Knowledge Center | 1600×900 | WebP/JPG | Ruang baca/perpustakaan perusahaan: rak buku, meja panjang, lampu baca hangat. |
| `hr.{ext}` | Thumbnail sorter + header kanvas HR Desk | 1600×900 | WebP/JPG | Lounge atau ruang temu yang ramah: sofa, meja kecil, tanaman, jendela besar. |
| `it.{ext}` | Thumbnail sorter + header kanvas IT Helpdesk | 1600×900 | WebP/JPG | Meja helpdesk teknologi: monitor, rak perangkat, nuansa biru-indigo lembut. |
| `requests.{ext}` | Thumbnail sorter + header kanvas My Requests | 1600×900 | WebP/JPG | Konter layanan atau papan tugas yang rapi dengan nuansa teal/indigo lembut. |
| `assistant.{ext}` | **Karakter utama**: besar di arch pada slide reception dan presenter ruang; kecil sebagai avatar subtitle, bubble, dan header copilot; di panel showcase login | 1200×1600 (3:4) minimum | **PNG/WebP transparan** (final); foto persegi untuk placeholder | Setengah badan, menghadap penonton, senyum ramah, satu tangan terbuka seperti mempersilakan. Wajah di 30% bagian atas (avatar dipotong dengan `object-position: top`). Kepala boleh menembus tepi atas arch. Tepi bawah dipotong lurus. |
| `logo.svg` | Monogram merek (sudah ada) | vektor | SVG | Dipakai referensi; tanda di UI berupa ikon percikan (sparkles) di atas gradient token, bukan berkas gambar. |
| `og-image.{ext}` (opsional) | Pratinjau tautan | 1200×630 | JPG/PNG | Cover reception + nama produk. |

## Panduan komposisi (agar teks tetap terbaca)

- Header kanvas selalu mendapat overlay ink gelap (kiri paling gelap) sehingga teks putih terbaca; hindari area putih murni di kiri. Thumbnail sorter juga digelapkan di bawah.
- Warna dominan: biru-indigo, teal, dan sentuhan hangat agar selaras dengan token tema (`primary`, `mint`, `coral`). Hindari warna yang bertabrakan dengan indigo.
- Satu gaya visual untuk kelima ruang (fotografi interior bersih atau ilustrasi isometrik yang konsisten).
- Tidak ada teks atau logo di dalam gambar (teks UI diterjemahkan 3 bahasa).

## Contoh prompt pembuatan gambar

- Cover ruang: "Bright modern corporate interior, clean lines, soft daylight, indigo and teal accents, shallow depth of field, no people, no text, 16:9".
- Karakter: "Half-body portrait of a friendly professional AI assistant woman in a dark navy blazer, smiling, one hand open in a welcoming presenting gesture, facing camera, soft studio light, transparent background, clean illustration style".
