# SIMANTAP - MENU & ROLE DOCUMENTATION

## 📋 DAFTAR ROLE DAN AKSESNYA

### 1. **ADMIN** 👨‍💼
**Hak Akses:** Penuh (Full Access)  
**Prefix Route:** `/admin`  
**Middleware:** `auth`, `role:admin`, `force.password.change`

#### Menu Utama Admin:

| Menu | Submenu | Fungsi | Fitur |
|------|---------|--------|-------|
| **Dashboard** | - | Ringkasan sistem | Analytics, statistik |
| **Manajemen Pengguna** | Akun Massal | Buat user dalam jumlah besar | Import batch |
| | Siswa | Kelola data siswa | Edit, hapus, filter |
| | User List | CRUD pengguna semua role | Aktif/nonaktif |
| **Data Kelas** | Peta Kelas | Atur penempatan siswa ke kelas | Pindah, keluarkan siswa |
| | Opsi Kelas | Lihat daftar kelas | Dropdown |
| **Mata Pelajaran** | Mata Pelajaran | CRUD mata pelajaran | Import Dapodik/Lokal, seed default |
| | Kelompok Mapel | Grup mata pelajaran | Kategori pembelajaran |
| | Mapping Rapor | Pemetaan mapel ke rapor | Konfigurasi |
| **Data Referensi** | Guru | Lihat daftar guru dari Dapodik | Read-only |
| | Pembelajaran | Master pembelajaran | Read-only |
| | Tanggal Rapor | Atur tanggal pembuatan rapor | CRUD |
| | Logo & TTD | Upload logo sekolah, tanda tangan | File management |
| | Foto Siswa | Upload batch foto siswa | Foto profil |
| | Ekstrakurikuler | CRUD kegiatan ekstrakurikuler | Aktivitas tambahan |
| **Ko-Kurikuler** | Tema | Master tema ko-kurikuler | CRUD |
| | Kegiatan | Master kegiatan ko-kurikuler | CRUD |
| | Kelompok | Buat grup anggota | Manage membership |
| **Penilaian** | Status Penilaian | Monitor progress penilaian | View-only |
| | Statistik Penilaian | Analisis data penilaian | Charts, metrics |
| **Konfigurasi** | Identitas Sekolah | Edit NPSN, nama, alamat sekolah | Profile |
| | Kode Akses | Atur kode akses untuk role | Access control |
| | Modul & Tampilan | Toggle fitur per role | Feature flags |
| | Bobot & Ketuntasan | Atur persentase penilaian | KKM, weights |
| **Integrasi** | Google Sheets | Connect/disconnect Google API | Sync data |
| | Penyimpanan Data | Koneksi Google Sheets | Active sheet ID |
| **Dapodik** | Import Dapodik | Tarik data dari Dapodik lokal | CSV, preview |
| | API Keys | Manajemen API keys untuk sync | Create, delete, toggle |
| | Semester | CRUD semester akademik | Activate, archive |
| | Log Dapodik | Lihat history import | Audit trail |
| | Download Bridge | Download aplikasi bridge | Desktop app |
| **Backup & Restore** | Export Data | Backup ke file | JSON, CSV |
| | Import Data | Restore dari file backup | Upload |
| **Log Sistem** | Log Aktivitas | Audit trail semua operasi | Timestamp, user, action |

**Total Menu:** 25+ menu

---

### 2. **GURU** 👨‍🏫
**Hak Akses:** Konten pembelajaran & nilai siswa  
**Prefix Route:** `/guru`  
**Middleware:** `auth`, `role:guru`, `force.password.change`

#### Menu Utama Guru:

| Menu | Submenu | Fungsi | Fitur |
|------|---------|--------|-------|
| **Dashboard** | - | Overview siswa & penilaian | Quick stats |
| **Data Siswa** | Daftar Siswa | Lihat siswa dalam kelas guru | CRUD, filter |
| | Pungut Siswa | Masukkan siswa ke kelas (one-time) | Bulk assign |
| | Unduh CSV | Export data siswa | Download |
| | Impor | Import siswa dari file | CSV upload |
| **Materi Ajar** | Materi | Upload & kelola materi pembelajaran | CRUD, file storage |
| **Kuis & Soal** | Kuis | Buat & kelola kuis/test | Toggle aktif, lihat hasil |
| | Hasil Kuis | Lihat hasil jawaban siswa | Score, analytics |
| **Penilaian** | Daftar Nilai | Input nilai untuk setiap siswa | Spreadsheet-like |
| | Input Nilai Cepat | Input nilai dengan grid interaktif | Paste dari Excel |
| | e-Rapor | Input deskripsi & nilai rapor | Auto-save, template |
| | Template e-Rapor | Download template | Excel format |
| | Import e-Rapor | Batch import nilai rapor | CSV/Excel |
| | Export e-Rapor | Export nilai rapor keluar | Download |
| **Laporan** | Daftar Nilai | Generate laporan nilai siswa | PDF |
| | Cetak e-Rapor | Generate raport siswa | PDF bulk |
| | Excel e-Rapor | Export raport ke Excel | Downloadable |
| **Kehadiran** | Kehadiran | Catat kehadiran siswa | CRUD |
| **Catatan Siswa** | Catatan | Beri catatan per siswa | Observasi |
| **Profil Lulusan** | Dimensi TKA | Kesiapan kematangan anak | Checklist |
| **Analisis Belajar** | Analisis Belajar | Analytics per siswa | Charts, insights |
| | Detail Siswa | Lihat detail progress siswa | Full profile |
| **Kebiasaan** | 7 Kebiasaan | Lihat catatan kebiasaan dari ortu | View-only |
| **Integrasi** | Google Sheets | Connect Google Sheets | Auto-sync nilai |
| | Disconnect | Putus koneksi Google | Data stops syncing |
| | Sync Full | Sinkronisasi semua data | Bulk update |
| **Pengaturan** | Preferensi Guru | Atur bobot penilaian guru | Personal settings |

**Total Menu:** 24+ menu

---

### 3. **SISWA** 👨‍🎓
**Hak Akses:** Dashboard pembelajaran personal  
**Prefix Route:** `/siswa`  
**Middleware:** `auth`, `role:siswa`, `force.password.change`

#### Menu Siswa:

| Menu | Submenu | Fungsi | Fitur |
|------|---------|--------|-------|
| **Dashboard** | - | Ringkasan nilai & kuis | Quick view |
| **Materi** | Daftar Materi | Lihat materi dari guru | Browse |
| | Detail Materi | Baca materi lengkap | Download files |
| **Kuis** | Daftar Kuis | Lihat kuis yang tersedia | Filter |
| | Ikuti Kuis | Jawab soal kuis | Timed, auto-submit |
| | Submit Jawaban | Kirim jawaban | Score instant |
| **Nilai** | Rekap Nilai | Lihat semua nilai | Table view |
| | Detail Nilai | Nilai per mata pelajaran | Historical |
| **Profil** | Profil Saya | Lihat data pribadi | Read-only mostly |
| | Edit Profil | Update data pribadi | Photo, contact |

**Total Menu:** 10 menu  
**Access Level:** Read-mostly, limited updates

---

### 4. **ORANG TUA** 👨‍👩‍👧
**Hak Akses:** Monitor perkembangan anak  
**Prefix Route:** `/ortu`  
**Middleware:** `auth`, `role:ortu`, `force.password.change`

#### Menu Orang Tua:

| Menu | Submenu | Fungsi | Fitur |
|------|---------|--------|-------|
| **Dashboard** | - | Ringkasan nilai anak | Overview |
| **Nilai** | Daftar Nilai | Lihat nilai anak | All subjects |
| | Detail Nilai | Breakdown per mata pelajaran | Historical |
| **Kehadiran** | Kehadiran | Monitor absensi anak | Calendar |
| **Catatan** | Catatan Siswa | Lihat catatan guru tentang anak | Observasi |
| **Rekap** | Rekap Nilai | Ringkasan lengkap nilai | Summary |
| **Laporan** | Laporan e-Rapor | Lihat raport anak | PDF view |
| **Kebiasaan** | 7 Kebiasaan | Kirim laporan kebiasaan anak | Form submission |

**Total Menu:** 8 menu  
**Access Level:** Read-only (monitor saja), submit kebiasaan

---

### 5. **KEPSEK (Kepala Sekolah)** 🎓
**Hak Akses:** Manajemen & monitoring sekolah  
**Prefix Route:** `/kepsek`  
**Middleware:** `auth`, `role:kepsek`, `force.password.change`

#### Menu Kepsek:

| Menu | Submenu | Fungsi | Fitur |
|------|---------|--------|-------|
| **Dashboard** | - | Overview sekolah | KPIs |
| | Pantau | Real-time monitoring | Live stats |
| **Peta Kelas** | Daftar Peta Kelas | Lihat penempatan semua siswa | Grid view |
| | Detail Guru | Per-guru siswa list | Export CSV |
| **Rekap Penilaian** | Rekap Total | Ringkasan penilaian semua guru | Charts |
| | Detail Guru | Penilaian per guru | Drill-down |
| | Unduh CSV | Export laporan | Spreadsheet |
| **Hasil Belajar** | Report Pembelajaran | Analytics pembelajaran sekolah | Metrics |
| **Kebiasaan** | Report Kebiasaan | Analisis kebiasaan siswa | Aggregated |
| **Aktivitas** | Aktivitas Sekolah | Log/timeline aktivitas | Audit trail |

**Total Menu:** 10 menu  
**Access Level:** Read-only (oversight), reporting

---

## 🔒 RINGKASAN PERMISSION MATRIX

| Feature | Admin | Guru | Siswa | Ortu | Kepsek |
|---------|-------|------|-------|------|--------|
| Kelola Pengguna | ✅ | ❌ | ❌ | ❌ | ❌ |
| Kelola Kelas | ✅ | ❌ | ❌ | ❌ | ❌ |
| Kelola Mapel | ✅ | ❌ | ❌ | ❌ | ❌ |
| Input Nilai | ❌ | ✅ | ❌ | ❌ | ❌ |
| Lihat Nilai | ✅ | ✅ | ✅ | ✅ | ✅ |
| Input Kehadiran | ❌ | ✅ | ❌ | ❌ | ❌ |
| Lihat Kehadiran | ❌ | ✅ | ❌ | ✅ | ❌ |
| Upload Materi | ❌ | ✅ | ❌ | ❌ | ❌ |
| Akses Materi | ❌ | ✅ | ✅ | ❌ | ❌ |
| Ikuti Kuis | ❌ | ❌ | ✅ | ❌ | ❌ |
| Buat Kuis | ❌ | ✅ | ❌ | ❌ | ❌ |
| Input Kebiasaan | ❌ | ❌ | ❌ | ✅ | ❌ |
| Lihat Kebiasaan | ❌ | ✅ | ❌ | ✅ | ✅ |
| Oversight/Report | ❌ | ❌ | ❌ | ❌ | ✅ |
| Manajemen Sistem | ✅ | ❌ | ❌ | ❌ | ❌ |

---

## 📌 FITUR KHUSUS PER ROLE

### **Admin - Special Features**
- Bulk user creation (akun massal)
- Dapodik bridge integration & download
- Google Sheets connection management
- Full backup/restore capability
- API key management
- System configuration (weights, access codes)
- Data logging & audit trail

### **Guru - Special Features**
- Quick grade input (paste from Excel)
- Auto-save e-Rapor (deskripsi)
- Bulk PDF export (all student report cards)
- Google Sheets auto-sync
- Student data bulk import/export
- 7 Kebiasaan monitoring

### **Siswa - Special Features**
- Timed quizzes (auto-submit)
- Instant score feedback
- Personal learning materials access
- Profile self-edit (limited)

### **Orang Tua - Special Features**
- 7 Kebiasaan submission (participate in habit tracking)
- Multi-child support (can view multiple children)
- Calendar-based attendance view

### **Kepsek - Special Features**
- Real-time monitoring dashboard
- Per-teacher detail drilling
- CSV export for further analysis
- Aggregate analytics & reports

---

## 🔐 PUBLIC & UNAUTHENTICATED ROUTES

| Route | Purpose | Access |
|-------|---------|--------|
| `/` | Landing page | Public |
| `/demo` | Demo access | Public |
| `/login` | User login | Unauthenticated only |
| `/register` | Self-registration | Unauthenticated only |
| `/tka-statistik` | TKA statistics view | Public (read-only) |
| `/api/tka-statistik` | TKA API | Public (read-only) |

---

## 📊 FORCED PASSWORD CHANGE

**Middleware Applied:** `force.password.change` on all authenticated routes  
**Trigger:** `users.force_password_change` flag  
**Route:**
- GET `/force-password-change` - Show form
- PUT `/force-password-change` - Submit change

**Behavior:** Blocks all other routes until password changed

---

## 🔗 ROUTE ORGANIZATION

### Prefix Structure:
```
/                    → Public/Landing
/auth                → Authentication
/admin               → Admin dashboard & management
/guru                → Teacher dashboard & tools
/siswa               → Student dashboard & learning
/ortu                → Parent dashboard & monitoring
/kepsek              → Principal dashboard & oversight
/api                 → Backend API
```

### Total Routes: **120+**

---

## ✨ ROLE HIERARCHY (Authority)

```
Admin (Full)
  ↓ (Can manage)
Guru (Content Creator)
  ↓ (Managed by)
Siswa (Learner)

Kepsek (Observer)
  ↓ (Sees all)
Guru, Siswa

Ortu (Parent)
  ↓ (Sees own child)
Siswa
```

---

## 📱 Role-Based Dashboard Redirect

**Route:** `/dashboard`

```
Admin    → /admin/dashboard
Guru     → /guru/dashboard
Siswa    → /siswa/dashboard
Ortu     → /ortu/dashboard
Kepsek   → /kepsek/dashboard
Unknown  → /login
```

---

## 🎯 SUMMARY

| Role | Purpose | Menus | Key Action |
|------|---------|-------|-----------|
| **Admin** | System management | 25+ | Configure, import, backup |
| **Guru** | Teaching & grading | 24+ | Input nilai, upload materi |
| **Siswa** | Learning | 10 | Take kuis, view grades |
| **Ortu** | Parent monitoring | 8 | Monitor anak, submit kebiasaan |
| **Kepsek** | School oversight | 10 | Monitor semua guru/siswa |

