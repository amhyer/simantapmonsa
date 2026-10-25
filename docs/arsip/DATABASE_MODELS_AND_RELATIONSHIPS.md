# SIMANTAP - DATABASE MODELS & RELATIONSHIPS

## 📊 COMPLETE MODEL LIST (31 Eloquent Models)

```
Models Organization:
├── User Management
│   ├── User
│   └── ApiKey
├── Academic Structure
│   ├── Semester
│   ├── Rombel (Kelas/Class)
│   ├── MataPelajaran (Subject)
│   ├── JadwalPelajaran (Schedule)
│   └── TanggalRapor (Report Card Date)
├── People
│   ├── Siswa (Student)
│   ├── Ptk (Teachers/Staff)
│   └── OrangTuaSiswa (Student's Parents)
├── Learning Content
│   ├── Materi (Teaching Materials)
│   ├── Kuis (Quiz)
│   └── Dimensi (Competency Dimension/TKA)
├── Assessment
│   ├── Nilai (Grade/Score)
│   ├── NilaiErapot (e-Report Card Value)
│   ├── HasilKuis (Quiz Result)
│   ├── Kehadiran (Attendance)
│   ├── Catatan (Notes/Comments)
│   ├── Kebiasaan (Habits/Behaviors)
│   └── PengaturanGuru (Teacher Settings)
├── Co-Curricular (Ko-Kurikuler)
│   ├── TemaKokurikuler (Theme)
│   ├── KegiatanKokurikuler (Activity)
│   └── KelompokKokurikuler (Group)
├── Dapodik Integration
│   ├── DapodikConfig (API Configuration)
│   ├── DapodikImportLog (Import History)
│   └── DapodikSyncLog (Sync Log)
├── System Settings
│   ├── SekolahSettings (School Profile)
│   └── Ekstrakurikuler (Extracurricular)
└── Reports
    ├── RingkasanGuru (Teacher Summary)
    └── Aktivitas (Activity Log)
```

---

## 🔗 MODEL RELATIONSHIPS

### **User** (Central Authentication)
```
User (Primary)
├─ has many → ApiKey (API authentication)
├─ has one → Siswa (if role: siswa)
├─ has one → Ptk (if role: guru)
├─ has many → Nilai (created_by teacher)
├─ has many → Materi (created_by teacher)
├─ has many → Kehadiran (created_by teacher)
├─ has many → Catatan (created_by teacher)
├─ has many → Kebiasaan (created_by ortu)
└─ has many → DapodikSyncLog (triggered_by admin)
```

### **Semester** (Academic Term)
```
Semester
├─ has many → Rombel
├─ has many → JadwalPelajaran
├─ has many → Nilai
├─ has many → NilaiErapot
├─ has many → Kuis
└─ has one → TanggalRapor
```

### **Rombel** (Class/Classroom)
```
Rombel (Class Group)
├─ belongs to → Semester
├─ has many → Siswa (students in class)
├─ has many → JadwalPelajaran (class schedule)
├─ has many → Kehadiran (attendance records)
├─ has many → Catatan (notes for class)
├─ has many → KegiatanKokurikuler (co-curricular activities)
└─ has one → Ptk (wali kelas - homeroom teacher)
```

### **Siswa** (Student)
```
Siswa (Student)
├─ belongs to → User (user_id)
├─ belongs to → Rombel (current class)
├─ has many → Nilai (grades)
├─ has many → NilaiErapot (report card grades)
├─ has many → HasilKuis (quiz results)
├─ has many → Kehadiran (attendance)
├─ has many → Catatan (teacher notes)
├─ has many → Kebiasaan (from parents)
├─ has many → OrangTuaSiswa (parents)
└─ has many → KelompokKokurikuler (co-curricular groups)
```

### **Ptk** (Teachers/Staff)
```
Ptk (Teacher/Staff)
├─ belongs to → User (user_id)
├─ has many → JadwalPelajaran (teaching schedule)
├─ has many → Nilai (grades entered)
├─ has many → Materi (materials created)
├─ has many → Kuis (quizzes created)
├─ has many → Kehadiran (attendance recorded)
├─ has many → Catatan (notes entered)
├─ has many → RingkasanGuru (teacher summary)
└─ has many → Rombel (wali_kelas/homeroom)
```

### **MataPelajaran** (Subject)
```
MataPelajaran (Subject)
├─ has many → JadwalPelajaran (schedule instances)
├─ has many → Materi (teaching materials)
├─ has many → Nilai (grades)
├─ has many → NilaiErapot (report card grades)
├─ has many → HasilKuis (related quizzes)
├─ has many → Kuis (quizzes created)
└─ belongs to → KelompokMapel (subject group)
```

### **JadwalPelajaran** (Class Schedule)
```
JadwalPelajaran (Schedule)
├─ belongs to → Semester
├─ belongs to → Rombel
├─ belongs to → MataPelajaran
├─ belongs to → Ptk (guru_id - teacher)
└─ has many → HasilKuis (for this subject)
```

### **Nilai** (Grade/Score)
```
Nilai (Grade)
├─ belongs to → Siswa
├─ belongs to → MataPelajaran
├─ belongs to → Semester
├─ belongs to → Ptk (guru_id - who entered)
└─ belongs to → User (created_by)
```

### **NilaiErapot** (e-Report Card Grade)
```
NilaiErapot (e-Rapor Grade)
├─ belongs to → Siswa
├─ belongs to → MataPelajaran
├─ belongs to → Semester
├─ belongs to → Ptk (guru_id)
└─ has deskripsi (text description)
```

### **Kuis** (Quiz)
```
Kuis (Quiz)
├─ belongs to → MataPelajaran
├─ belongs to → Semester
├─ belongs to → Ptk (guru_id)
└─ has many → HasilKuis (student attempts)
```

### **HasilKuis** (Quiz Result)
```
HasilKuis (Quiz Result)
├─ belongs to → Kuis
├─ belongs to → Siswa
├─ belongs to → JadwalPelajaran
└─ has skor (score)
```

### **Materi** (Teaching Material)
```
Materi (Material)
├─ belongs to → MataPelajaran
├─ belongs to → Semester
├─ belongs to → Ptk (guru_id)
└─ has file (URL/path to file)
```

### **Kehadiran** (Attendance)
```
Kehadiran (Attendance)
├─ belongs to → Siswa
├─ belongs to → Rombel
├─ belongs to → MataPelajaran (optional)
├─ belongs to → Semester
├─ belongs to → Ptk (guru_id)
└─ has status (hadir/alpha/izin/sakit/tdk-diisi)
```

### **Catatan** (Notes/Comments)
```
Catatan (Note)
├─ belongs to → Siswa
├─ belongs to → Rombel
├─ belongs to → Ptk (guru_id)
└─ has isi_catatan (text content)
```

### **Kebiasaan** (Habits/7 Kebiasaan)
```
Kebiasaan (Habit)
├─ belongs to → Siswa
├─ belongs to → User (submitted_by - parent)
├─ belongs to → OrangTuaSiswa (parent link)
└─ has habit_data (JSON values for 7 habits)
```

### **Dimensi** (TKA/Competency Dimension)
```
Dimensi (TKA Readiness)
├─ belongs to → Siswa
├─ belongs to → Semester
├─ belongs to → Ptk (guru_id)
└─ has checklist items (readiness indicators)
```

### **KelompokKokurikuler** (Co-Curricular Group)
```
KelompokKokurikuler (Group)
├─ belongs to → TemaKokurikuler
├─ belongs to → KegiatanKokurikuler
├─ belongs to → Ptk (pembimbing_id - advisor)
└─ has many → Siswa (members)
```

### **TemaKokurikuler** (Co-Curricular Theme)
```
TemaKokurikuler (Theme)
├─ has many → KegiatanKokurikuler
└─ has many → KelompokKokurikuler
```

### **KegiatanKokurikuler** (Co-Curricular Activity)
```
KegiatanKokurikuler (Activity)
├─ belongs to → TemaKokurikuler
├─ has many → KelompokKokurikuler
└─ belongs to → Rombel
```

### **OrangTuaSiswa** (Student's Parents)
```
OrangTuaSiswa (Parent)
├─ belongs to → Siswa
├─ belongs to → User (parent user_id)
└─ has hubungan (relationship: ayah/ibu/wali)
```

### **Dapodik Models** (Integration)
```
DapodikConfig
├─ stores API credentials
└─ belongs to → SekolahSettings

DapodikImportLog
├─ tracks import operations
├─ has status (pending/processing/success/error)
└─ has error_message

DapodikSyncLog
├─ tracks sync operations
├─ belongs to → User (triggered_by)
├─ has tipe enum (siswa/gtk/rombel/mapel/jadwal/nilai/semua/full-sync)
└─ has response JSON
```

### **System Settings**
```
SekolahSettings
├─ has npsn
├─ has nama_sekolah
├─ has alamat
├─ has logo
├─ has google_sheet_id
└─ has konfigurasi JSON

PengaturanGuru
├─ belongs to → Ptk
└─ has bobot penilaian (assessment weights)

TanggalRapor
├─ belongs to → Semester
├─ has tanggal_mulai
└─ has tanggal_selesai
```

---

## 📈 DATA FLOW RELATIONSHIPS

### **Student Data Flow**
```
Siswa
  ├─ has Nilai (from Guru)
  ├─ has NilaiErapot (from Guru)
  ├─ has HasilKuis (from taking Kuis)
  ├─ has Kehadiran (from Guru)
  ├─ has Catatan (from Guru)
  ├─ has Kebiasaan (from OrangTua)
  ├─ has Dimensi/TKA (from Guru)
  └─ belongs to Rombel
```

### **Teaching Flow**
```
Ptk (Guru)
  ├─ creates Materi
  ├─ creates Kuis
  ├─ inputs Nilai & NilaiErapot
  ├─ records Kehadiran & Catatan
  ├─ has JadwalPelajaran (schedule)
  └─ wali_kelas for Rombel
```

### **Assessment Flow**
```
Semester
  ├─ has Nilai & NilaiErapot (period based)
  ├─ has HasilKuis (period based)
  ├─ has Kehadiran (period based)
  ├─ has TanggalRapor (deadline)
  └─ has Dimensi/TKA (period based)
```

### **Dapodik Import Flow**
```
Dapodik Source
  ├─ Import → Siswa
  ├─ Import → Ptk
  ├─ Import → Rombel
  ├─ Import → MataPelajaran
  ├─ Import → JadwalPelajaran
  └─ Log → DapodikSyncLog
```

---

## 🔐 PERMISSION MODEL MAPPING

```
User.peran (role) determines access to models:

ADMIN
  └─ Can access/modify: All models

GURU
  ├─ owns: Nilai, NilaiErapot, Materi, Kuis, Kehadiran, Catatan, Dimensi
  ├─ reads: Siswa, Ptk (himself), Rombel, MataPelajaran, JadwalPelajaran
  └─ cannot: User creation, DapodikConfig

SISWA
  ├─ reads: Own Nilai, NilaiErapot, Materi, HasilKuis, Kehadiran, Catatan
  ├─ writes: HasilKuis (submit quiz)
  └─ cannot: Write Nilai, Kehadiran, Materi

ORTU
  ├─ reads: Child's Nilai, NilaiErapot, Kehadiran, Catatan, Kebiasaan
  ├─ writes: Kebiasaan (7 habits)
  └─ multi-child: Can have many Siswa linked

KEPSEK
  └─ reads only: All aggregated data (no writes except monitoring)
```

---

## 📊 MODEL STATISTICS

| Category | Count | Examples |
|----------|-------|----------|
| User & Auth | 2 | User, ApiKey |
| Academic Structure | 5 | Semester, Rombel, MataPelajaran, Jadwal, TanggalRapor |
| People | 3 | Siswa, Ptk, OrangTuaSiswa |
| Learning | 3 | Materi, Kuis, Dimensi |
| Assessment | 8 | Nilai, NilaiErapot, HasilKuis, Kehadiran, Catatan, Kebiasaan, PengaturanGuru, Aktivitas |
| Co-Curricular | 3 | TemaKokurikuler, KegiatanKokurikuler, KelompokKokurikuler |
| Dapodik | 3 | DapodikConfig, DapodikImportLog, DapodikSyncLog |
| System | 2 | SekolahSettings, Ekstrakurikuler |
| **TOTAL** | **31** | Eloquent Models |

---

## 🎯 KEY RELATIONSHIPS SUMMARY

| Model | Primary Relationships |
|-------|----------------------|
| Siswa | User, Rombel, Nilai, NilaiErapot, HasilKuis, Kehadiran, Catatan, Kebiasaan |
| Ptk | User, JadwalPelajaran, Nilai, Materi, Kuis |
| Rombel | Semester, Siswa, JadwalPelajaran, Kehadiran |
| MataPelajaran | JadwalPelajaran, Nilai, NilaiErapot, Materi, Kuis |
| Semester | Rombel, Nilai, NilaiErapot, Kuis, JadwalPelajaran, TanggalRapor |
| User | Siswa, Ptk, ApiKey, OrangTuaSiswa |

---

## 💾 TABLE COUNT & SIZE (Estimated)

Production would typically have:
- Core tables: 31
- Pivot tables: 5-10 (for many-to-many)
- Log tables: 3+ (activity tracking)
- **Total: ~40-45 tables**

Size estimate (1000 students, 50 teachers):
- Student data: 1000 records
- Teacher data: 50 records
- Grades: 50,000+ records (varies by assessment)
- Attendance: 200,000+ records
- **Total size: 500MB - 2GB** (depending on file storage)

---

## 🔄 COMMON QUERIES

### Get Student Grades
```
Siswa → has Nilai/NilaiErapot → belongs to MataPelajaran, Semester
```

### Get Class Schedule
```
Rombel → has JadwalPelajaran → belongs to Ptk, MataPelajaran, Semester
```

### Get Quiz Results
```
Kuis → has HasilKuis → belongs to Siswa, JadwalPelajaran
```

### Get Teacher's Students
```
Ptk (wali_kelas) → has Rombel → has Siswa
Ptk (guru_mapel) → has JadwalPelajaran → belongs to Rombel → has Siswa
```

### Get Attendance Summary
```
Kehadiran → Siswa, Semester, MataPelajaran/Rombel
```

