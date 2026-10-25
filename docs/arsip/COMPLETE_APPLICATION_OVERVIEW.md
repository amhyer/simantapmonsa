# SIMANTAP APPLICATION - COMPLETE OVERVIEW

**Status:** 🚀 Production-Ready Student Information Management System  
**Version:** Laravel 11 + Vue.js  
**Target:** Indonesian K-12 Schools (Sekolah Dasar - Sekolah Menengah Atas)

---

## 🎯 APPLICATION PURPOSE

SIMANTAP (Sistem Informasi Manajemen Akademik Terpadu) is a comprehensive **Student Information Management System** designed to:

1. **Manage Student Data** - Registration, classification, tracking
2. **Handle Academic Assessments** - Grades, quizzes, attendance
3. **Generate Report Cards** - e-Rapor (electronic report cards)
4. **Track Habits & Behaviors** - "7 Kebiasaan" (7 Habits) framework
5. **Integrate with Dapodik** - Indonesian national education database
6. **Support Multi-Stakeholder Collaboration** - Admin, Teachers, Students, Parents, Principal

---

## 👥 5 PRIMARY ROLES

### 1️⃣ **ADMIN** (System Administrator)
**Purpose:** System configuration, user management, data import/backup  
**Access Level:** FULL SYSTEM  
**Primary Actions:**
- Create/delete user accounts (bulk operations available)
- Manage school settings (name, NPSN, logo, address)
- Configure assessment weights & grading scales
- Import data from Dapodik (national education DB)
- Manage API keys for integrations
- Backup & restore database
- View system logs & audit trails

**Key Features:**
- Bulk user creation (up to 1000 at once)
- Google Sheets integration management
- Dapodik bridge download & setup
- Email configuration
- Role-based access control
- System performance monitoring

**Typical Users:** IT Staff, System Managers

---

### 2️⃣ **GURU** (Teacher)
**Purpose:** Teaching, grading, content management, reporting  
**Access Level:** CLASS MANAGEMENT + GRADING  
**Primary Actions:**
- Upload teaching materials (Materi Ajar)
- Create quizzes & view student answers
- Input student grades (nilai)
- Record attendance & behavioral notes
- Generate report cards (e-Rapor)
- Monitor 7 Kebiasaan (habits from parents)
- Analyze student learning progress

**Key Features:**
- Quick input mode (paste grades from Excel)
- Auto-save e-Rapor descriptions
- Bulk PDF report card export
- Google Sheets auto-sync (bidirectional)
- Student data import/export
- Grade analytics & trends
- Quiz result analysis

**Typical Users:** Teachers, Subject Instructors

---

### 3️⃣ **SISWA** (Student)
**Purpose:** Learning & performance tracking  
**Access Level:** PERSONAL LEARNING DASHBOARD  
**Primary Actions:**
- Access teaching materials (Materi)
- Take quizzes (Kuis)
- View grades & report card
- Update personal profile (limited)
- View attendance record

**Key Features:**
- Personal materials library
- Timed quiz interface with auto-submission
- Instant score feedback
- Grade history view
- Profile photo upload

**Typical Users:** K-12 Students (SD, SMP, SMA)

---

### 4️⃣ **ORANG TUA** (Parent/Guardian)
**Purpose:** Monitor child's academic progress  
**Access Level:** CHILD-SPECIFIC VIEWING + HABIT SUBMISSION  
**Primary Actions:**
- View child's grades (all subjects & semesters)
- Monitor attendance records
- Read teacher notes/comments about child
- Submit 7 Kebiasaan (habit tracking)
- View report card (e-Rapor)
- See class schedule & teachers

**Key Features:**
- Multi-child support (if parent of multiple students)
- Calendar-based attendance view
- Notifications (on grade entry, attendance issues)
- Habit submission form (weekly/monthly)
- Download report cards (PDF)

**Typical Users:** Parents, Guardians, Wali (Custodians)

---

### 5️⃣ **KEPSEK** (Principal/School Manager)
**Purpose:** School-wide oversight & reporting  
**Access Level:** READ-ONLY AGGREGATED VIEW  
**Primary Actions:**
- Monitor all teachers' progress (class count, grade counts)
- View class distribution (Peta Kelas)
- Analyze student learning outcomes aggregated
- Review school-wide habit tracking data
- View activity logs (staff actions)
- Export reports for higher authorities
- Real-time monitoring dashboard

**Key Features:**
- Real-time KPI dashboard
- Teacher drill-down (see each teacher's students & grades)
- CSV export for reporting (to Ministry of Education)
- School-wide analytics
- Habit summary aggregation
- Student classification reports

**Typical Users:** Principal (Kepala Sekolah), Vice Principal

---

## 📊 DATABASE OVERVIEW (31 Models)

### Core Academic Data
- **Semester** - Academic terms/semesters
- **Rombel** - Classes/classrooms
- **MataPelajaran** - Subjects/courses
- **JadwalPelajaran** - Class schedule (subject + teacher + class + time)
- **TanggalRapor** - Report card deadlines

### People Data
- **User** - All system users (unified auth)
- **Siswa** - Student profiles
- **Ptk** - Teachers & staff
- **OrangTuaSiswa** - Student's parents/guardians

### Learning Content
- **Materi** - Teaching materials (uploaded by teachers)
- **Kuis** - Quiz/test questions
- **Materi** - Learning resources

### Assessment Data
- **Nilai** - Grades/scores (subject-based)
- **NilaiErapot** - e-Report card grades (with descriptions)
- **HasilKuis** - Quiz attempt results
- **Kehadiran** - Attendance records
- **Catatan** - Teacher notes/comments about student
- **Kebiasaan** - Habit tracking (7 Kebiasaan) from parents
- **Dimensi** - TKA (Tingkat Kematangan Anak - child readiness) checklist

### Co-Curricular (Ko-Kurikuler)
- **TemaKokurikuler** - Co-curricular themes
- **KegiatanKokurikuler** - Activities
- **KelompokKokurikuler** - Activity groups

### System & Configuration
- **SekolahSettings** - School profile & settings
- **ApiKey** - API authentication keys
- **PengaturanGuru** - Teacher's personal preferences
- **Ekstrakurikuler** - Extracurricular programs
- **RingkasanGuru** - Teacher performance summary

### Dapodik Integration
- **DapodikConfig** - API credentials
- **DapodikImportLog** - Import history & status
- **DapodikSyncLog** - Sync operation logs

### Logging
- **Aktivitas** - System activity log

---

## 🔄 TYPICAL WORKFLOWS

### **Workflow 1: Start of Academic Year (Admin)**
1. Admin creates Semester record
2. Admin creates Rombel (classes)
3. Admin imports student data from Dapodik OR creates manually
4. Admin creates/assigns teachers (Ptk)
5. Admin sets up JadwalPelajaran (class schedules)
6. Admin sets TanggalRapor (report deadlines)

### **Workflow 2: Student Grades Entry (Guru)**
1. Teacher logs in → goes to "Input Nilai"
2. Teacher selects semester, subject, class
3. Teacher enters grades (can paste from Excel)
4. System auto-calculates some grades if rules configured
5. Teacher submits → saved to Nilai table
6. Teacher can also input e-Rapor (NilaiErapot) with descriptions
7. Generate PDF reports when ready

### **Workflow 3: Student Learning (Siswa)**
1. Student logs in → sees Dashboard with "Tugas" (to-do)
2. Student accesses Materi (materials) from his subjects
3. Student takes available Kuis (quiz)
4. System auto-scores multiple choice, shows results
5. Student views Nilai (grades) from all teachers
6. Student views Kehadiran (attendance)

### **Workflow 4: Parent Monitoring (Orang Tua)**
1. Parent logs in with link to child
2. Parent views child's Nilai (all grades)
3. Parent sees Kehadiran calendar
4. Parent reads Catatan from teachers
5. Parent fills out Kebiasaan form (weekly/monthly)
6. Parent downloads child's e-Rapor (PDF)

### **Workflow 5: Principal Oversight (Kepsek)**
1. Kepsek logs in → sees Dashboard
2. Kepsek views "Pantau" (live monitoring of entries)
3. Kepsek views Peta Kelas (student distribution)
4. Kepsek views Rekap (aggregated grades by teacher)
5. Kepsek can drill into specific teacher's data
6. Kepsek generates reports for Ministry

---

## 🔐 SECURITY & ACCESS CONTROL

### Authentication
- Email/Password login with forced password change on first login
- Session-based (Laravel sessions)
- Throttled login attempts (10 per minute)

### Authorization (Role-Based Access Control - RBAC)
```
Route Middleware: role:admin|guru|siswa|ortu|kepsek
Each route protected by user's peran (role)
```

### Data Access Rules
- **Students** → Can only see own data (grades, attendance, materials)
- **Teachers** → Can only see their assigned students/subjects
- **Parents** → Can only see their child's data
- **Admin** → Can see everything
- **Principal** → Read-only view of all data

### API Key Authentication
- API endpoints use X-API-Key header
- For Dapodik Bridge integration
- Can be enabled/disabled per key
- Tracks usage

---

## 📱 USER INTERFACES

### Web Dashboard (Main)
- Responsive design (mobile-friendly)
- Vue.js for interactive components
- Real-time updates where needed
- PDF generation (report cards, certificates)
- Excel export/import

### Admin Panel
- User management table with bulk operations
- School settings form
- Data import wizard for Dapodik
- Configuration checkboxes (feature toggles)

### Teacher Portal
- Grade input spreadsheet (like Excel)
- Drag-drop file uploads (materials, grade files)
- Bulk PDF export (20+ pages in seconds)
- Google Sheets real-time sync button

### Student Portal
- Personal learning dashboard
- Quiz interface (time limit, progress bar)
- Grade report card view (semester cards)
- Material library with search

### Parent Portal
- Child performance cards
- Calendar attendance view
- Habit tracking form
- Report card download links

### Principal Dashboard
- KPI cards (# students, # teachers, completion %)
- Real-time monitoring (who entered grades today)
- Charts (grade distribution, pass/fail rates)
- Export buttons

---

## 🚀 KEY FEATURES

### 1. **Dapodik Integration**
- Auto-import from Dapodik (Ministry database)
- Sync students, teachers, subjects, classes
- Handles data conflicts & duplicates
- Logs all sync operations
- Supports both WebService API & local database

### 2. **e-Rapor (Digital Report Card)**
- Generate beautiful PDF report cards
- Subject grades + descriptions
- Attendance summary
- Behavioral notes
- School letterhead & official seals
- Digital signature support

### 3. **7 Kebiasaan (Habit Tracking)**
- Track child's 7 key habits (Indonesian education framework)
- Parent submission via form
- Teacher viewing/feedback
- Aggregated reports for school

### 4. **Assessment Management**
- Multiple grade types (nilai, nilai_erapor)
- Automatic grade calculations
- Grade normalization
- KKM (passing score) enforcement
- Assessment weights configuration

### 5. **Content Management**
- Upload teaching materials
- Create quizzes with auto-scoring
- Material versioning
- Student access control by class

### 6. **Google Sheets Integration**
- Bi-directional sync with Google Sheets
- Auto-update grades in Sheets
- Teacher can maintain their own Sheets

### 7. **Bulk Operations**
- Import 1000s of students at once
- Bulk PDF generation (all student reports in one click)
- Bulk user account creation
- Batch data import/export

### 8. **Reporting**
- Student report cards
- Teacher performance summary
- School-wide analytics
- Ministry-compliance reports
- CSV/PDF export

### 9. **Activity Logging**
- Track who did what and when
- Audit trail for data changes
- Admin activity review
- Security investigation support

### 10. **Mobile-Friendly**
- Responsive design
- Works on tablets & phones
- Touch-optimized interfaces
- Offline capability (limited)

---

## 📊 STATISTICS

| Metric | Value |
|--------|-------|
| Eloquent Models | 31 |
| Database Tables | ~45 |
| Web Routes | 120+ |
| API Endpoints | 15+ |
| Roles | 5 |
| Primary Features | 10+ |
| Languages Support | Indonesian (with English infrastructure) |
| Supported School Types | All (SD, SMP, SMA) |
| Max Students (tested) | 5000+ |
| Max Teachers (tested) | 500+ |

---

## 🛠️ TECHNOLOGY STACK

### Backend
- **Framework:** Laravel 11
- **PHP:** 8.2+
- **Database:** SQLite (dev), PostgreSQL/MySQL (production)
- **Auth:** Laravel Sanctum + Session-based

### Frontend
- **Vue.js** 3
- **Tailwind CSS** for styling
- **Inertia.js** for SPA-like experience
- **Chart.js** for analytics
- **FilePond** for file uploads

### Integration
- **Dapodik:** XML/JSON WebService + CSV import
- **Google Sheets:** OAuth2 API
- **PDF:** mPDF / DomPDF
- **Excel:** PhpSpreadsheet

### DevOps
- **Docker** support (Dockerfile provided)
- **Queue:** Database-based
- **Cache:** Redis (optional)
- **Storage:** Local disk + S3 support

---

## 📋 GETTING STARTED

### First-Time Setup (Admin)
1. Create super-admin account
2. Configure school settings (NPSN, name, logo)
3. Set academic year & semesters
4. Import student/teacher data (Dapodik or CSV)
5. Create classes (Rombel) & assign teachers
6. Set report card deadlines
7. Configure assessment weights

### For Teachers
1. Login with assigned credentials
2. Change password (forced on first login)
3. View assigned students & subjects
4. Upload materials → Create quizzes → Enter grades

### For Students
1. Login with student ID + password
2. Change password (forced on first login)
3. View materials & grades
4. Take available quizzes

### For Parents
1. Receive login link from school
2. Change password (forced on first login)
3. Link to child (if not auto-linked)
4. View child's grades & submit habits

### For Principal
1. Login with admin credentials
2. Access principal dashboard (read-only)
3. View real-time monitoring
4. Generate reports

---

## 💡 USE CASES

### Use Case 1: Teacher Entering Grades
**Actor:** Guru (Teacher)  
**Goal:** Enter semester grades for all students in class  
**Steps:**
1. Go to "Input Nilai Cepat"
2. Select semester, subject, class
3. Grid appears with student names
4. Enter grades (or paste from Excel)
5. Click "Simpan" (Save)
6. System validates & saves
7. Notifications sent to parents

### Use Case 2: Parent Monitoring Progress
**Actor:** Orang Tua (Parent)  
**Goal:** Check child's progress regularly  
**Steps:**
1. Login to "Orang Tua" portal
2. See dashboard with grade summary
3. Click on subject to see details
4. View teacher's notes & feedback
5. Fill out "7 Kebiasaan" form if due
6. Download current semester's report card

### Use Case 3: Admin Importing Student Data
**Actor:** Admin  
**Goal:** Bulk import students from Dapodik  
**Steps:**
1. Go to Admin → Dapodik → Import
2. Upload Dapodik CSV file
3. Preview shows data to be imported
4. Click "Import" → system processes
5. Logs show # successfully imported, # errors
6. Duplicate check & conflict resolution
7. Students now ready for assignment to classes

### Use Case 4: Principal Generating Report
**Actor:** Kepsek (Principal)  
**Goal:** Get ministry-compliance report on student performance  
**Steps:**
1. Go to Kepsek → Rekap Penilaian
2. Select semester & date range
3. See aggregated pass/fail rates
4. Drill down by teacher to see details
5. Export to CSV for ministry submission
6. Print for school records

---

## 🎯 SUMMARY

SIMANTAP is a **comprehensive, production-ready Student Information Management System** built for Indonesian schools. It provides:

✅ **For Admin:** Full system control, Dapodik integration, bulk operations  
✅ **For Teachers:** Easy grading, content management, reporting  
✅ **For Students:** Access to materials, take quizzes, view grades  
✅ **For Parents:** Monitor child progress, submit habit tracking  
✅ **For Principal:** School-wide oversight, analytics, compliance reporting  

With **31 models**, **120+ routes**, **5 roles**, and **10+ major features**, SIMANTAP provides everything needed for modern K-12 education management in Indonesia.

---

**Last Updated:** 2026-01-13  
**Maintained By:** SIMANTAP Development Team  
**License:** [Proprietary/Open Source - TBD]  
**Support:** Contact school IT administrator
