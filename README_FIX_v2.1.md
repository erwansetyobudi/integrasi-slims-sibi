# SIBI v2.1 — perbaikan aktivasi

- Migrasi dapat dijalankan kembali tanpa menghapus tabel `sibi_docs`.
- Kolom `biblio.sibi_doc_id` ditambahkan hanya jika belum ada, dengan default NULL.
- Indeks unik ditambahkan hanya bila belum ada.
- Tidak mengubah kolom inti `biblio.image` dan `search_biblio.image`.
- Rollback tidak menghapus data bibliografi.
- Fitur cover dan PDF massal v2 tetap dipertahankan.

Jika aktivasi masih gagal, periksa respons request aktivasi di tab Network > Response dan log PHP/server; pesan `toastr.js e.replace` biasanya hanya kesalahan sekunder ketika toastr menerima objek, bukan string.
