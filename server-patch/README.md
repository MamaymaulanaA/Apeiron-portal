# Perubahan yang perlu diterapkan di License Server

Berkas di folder ini **bukan bagian dari portal**. Ia ditujukan untuk repo
server (`server-apeiron`) dan tidak dijalankan oleh aplikasi ini. Diletakkan di
sini supaya ikut terlacak versi dan bisa ditinjau sebelum diunggah.

## Yang harus dilakukan

Unggah satu berkas:

```
server-patch/api/portal_license.php   →   <root server>/api/portal_license.php
```

Tidak ada perubahan database. Tidak ada berkas server lain yang disentuh.
`create_license.php`, `activate.php`, `deactivate.php`, dan `check.php`
dibiarkan apa adanya.

## Kenapa perlu berkas baru

Portal butuh menjawab pertanyaan "apa keadaan lisensi ini". Endpoint yang ada
menjawab pertanyaan lain:

| Endpoint | Menjawab | Kenapa tidak bisa dipakai portal |
|---|---|---|
| `check.php` | Apakah lisensi aktif **di situs ini** | Mewajibkan `site_url` dan menentukan status dari ada tidaknya baris `activations` untuk situs itu. Portal bukan situs pelanggan, jadi jawabannya akan selalu `inactive` — menyesatkan, bukan sekadar kurang lengkap. |
| `create_license.php` | Buatkan lisensi baru | Menulis, bukan membaca. |
| `health.php` | Server hidup? | Tidak tahu apa-apa soal lisensi tertentu. |

`portal_license.php` **hanya membaca** — tidak ada satu pun `UPDATE` atau
`INSERT` di dalamnya. Aktivasi, validasi, dan perubahan status tetap sepenuhnya
milik endpoint yang sudah ada.

## Bentuk permintaan dan jawaban

```
POST /api/portal_license.php
X-API-Key: <kunci portal>
Content-Type: application/json

{ "license_key": "APEIRON-…", "product_id": "apeiron-kit" }
```

```json
{
  "success": true,
  "data": {
    "status": "active",
    "is_lifetime": false,
    "duration_label": "1 Tahun",
    "issued_at": "2026-08-21 12:30:00",
    "activated_at": "2026-08-22 09:15:00",
    "expires_at": "2027-08-21 12:30:00",
    "days_remaining": 327,
    "activations_used": 1,
    "activation_limit": 1
  }
}
```

`days_remaining` dan `duration_label` dihitung **di server**, bukan di portal —
supaya angkanya mengikuti jam yang sama dengan yang menentukan kedaluwarsa
sebenarnya.

## Sebelum diunggah, portal tetap jalan

Tanpa berkas ini server mengembalikan 404. Portal menanganinya sebagai "status
belum diketahui" dan menampilkan pesan apa adanya — bukan menampilkan lisensi
yang masih berlaku sebagai mati, dan bukan pula halaman rusak.

## Catatan: Lifetime belum pernah terjadi

`create_license.php` baris 135 selalu menetapkan masa berlaku satu tahun:

```php
$expires = date('Y-m-d H:i:s', strtotime('+1 year'));
```

Tidak ada kolom `license_type` di tabel `licenses`, dan tidak ada jalur yang
menghasilkan lisensi tanpa tanggal berakhir. Jadi hari ini server tidak pernah
mengembalikan Lifetime.

`portal_license.php` menilai Lifetime dari `expires` yang kosong — cara yang
sama dengan penilaian kedaluwarsa di `check.php`. Portal sudah menampilkan
Lifetime dengan benar begitu server mengirimkannya; yang belum ada adalah cara
membuatnya.

Kalau Lifetime memang mau dijual, dua hal yang perlu diubah **di server**,
bukan di portal:

1. `create_license.php` menerima parameter durasi, dan menyimpan `expires`
   NULL untuk Lifetime.
2. Lebih baik lagi: tambahkan kolom `license_type` pada tabel `licenses`
   sehingga tipe lisensi menjadi data tersimpan, bukan turunan dari selisih
   tanggal. `portal_license.php` sudah menandai di komentarnya bahwa kolom itu
   yang harus dipakai begitu tersedia.
