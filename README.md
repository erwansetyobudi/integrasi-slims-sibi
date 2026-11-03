# SLiMS SIBI Plugin
Plugin untuk mengintegrasikan metadata SIBI (Sistem Informasi Perbukuan Indonesia) dari Pusat Perbukuan Kementerian Pendidikan Dasar dan Menengah langsung ke dalam aplikasi SLiMS.

## Instalasi
1. Ekstrak file plugin
2. Letakkan folder `sibi` ke dalam folder `plugins` pada instalasi SLiMS Anda.
3. Buat folder `sibi_docs` di bawah direktori repository untuk menyimpan file e-books yang didownload dari SIBI.
4. Pastikan agar folder `sibi_docs` bisa ditulis (writable).
5. Login sebagai admin ke dalam aplikasi SLiMS dan masuk ke menu System -> Plugins.
6. Aktifkan plugin SIBI dengan menekan switch Enable.
7. Menu SIBI akan tersedia pada modul Biblografi pada bagian bawah.

## Cara Menggunakan
1. Masuk ke modul Bibliografi -> SIBI.
2. Klik tombol "Harvest Metadata SIBI" pada pojok kanan atas.
3. Setelah proses Harvest berhasil, sinkronisasi metadata SIBI yang sudah tersimpan ke SLiMS dengan menekan tombol "Sync Metadata SIBI ke SLiMS". 
4. Agar hasil sinkronisasi metadata bisa dicari, lakukan pengindeksan bibliografis melalui menu System -> Biblio Indexes. Klik pada tombol Update Index pada pojok kanan atas.
5. Setelah proses sinkronisasi selesai, selanjutnya file e-book setiap metadata yang diimpor bisa diunduh satu per satu dengan menekan tombol "Unduh" pada setiap baris. Proses download setiap koleksi digital dapat memakan waktu yang cukup lama.


## Pembaruan: Unduh cover dan PDF secara massal

1. **Backup database dan direktori repository/images** sebelum memperbarui plugin.
2. Ganti folder plugin lama dengan folder ini, pertahankan migrasi dan data lama. Tidak perlu menjalankan ulang migrasi pada instalasi yang sudah aktif.
3. Pilih **Harvest Metadata SIBI** kemudian **Sync Metadata SIBI ke SLiMS**.
4. Klik **Unduh Cover + PDF Massal**. Browser memproses satu buku per permintaan dan menampilkan progres, keberhasilan, dan kesalahan. Biarkan halaman tetap terbuka. Tombol **Hentikan** menghentikan setelah buku yang sedang diproses selesai.
5. Berkas cover disimpan di `images/docs/sibi_<ID>.<ekstensi>` dan direferensikan pada `biblio.image`. PDF disimpan di `repository/sibi_docs/sibi_<ID>.pdf`, didaftarkan pada `files` dan dihubungkan ke `biblio_attachment`.
6. Jalankan **System → Biblio Indexes → Update Index** agar perubahan muncul konsisten dalam pencarian/OPAC.

Proses dapat diulang: file yang sudah ada tidak diunduh ulang, dan relasi PDF dicek sebelum ditambahkan. Sinkronisasi bibliografi tidak lagi menggunakan `REPLACE INTO` yang dapat mengganti `biblio_id` dan menghilangkan relasi lama. Metadata dan file yang sudah dikurasi tidak ditimpa oleh proses pengunduhan.

**Persyaratan:** ekstensi PHP cURL/SSL yang valid, direktori `images/docs` dan `repository/sibi_docs` dapat ditulis oleh server, koneksi HTTPS ke host media SIBI. Unduhan dibatasi ke `static.sc.cloudapp.web.id` dan `static-sc.cloudapp.web.id` dan divalidasi sebagai PDF/gambar. Plugin tidak mengubah hak cipta atau izin akses sumber; periksa ketentuan penggunaan sumber sebelum menyediakan berkas secara publik. Pengujian end-to-end pada server tujuan tetap diperlukan.
