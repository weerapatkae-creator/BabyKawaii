# 🌸 BabyKawaii Shop - Admin Panel

## ระบบจัดการร้านค้าเสื้อผ้าเด็กแรกเกิด
ภาษาไทย | PHP + MySQL | Multi-Platform

---

## 📋 สิ่งที่ต้องมี (Requirements)

- PHP 7.4+ (แนะนำ PHP 8.0+)
- MySQL 5.7+ หรือ MariaDB 10.3+
- Web Server: Apache หรือ Nginx (หรือ XAMPP/WAMP/Laragon สำหรับ local)
- Extension: PDO, PDO_MySQL, GD (สำหรับ image)

---

## 🚀 ขั้นตอนการติดตั้ง

### 1. วางไฟล์
```
วางโฟลเดอร์ BabyKawaii-Admin ไว้ใน:
- XAMPP: C:/xampp/htdocs/BabyKawaii-Admin/
- Laragon: C:/laragon/www/BabyKawaii-Admin/
- Server: /var/www/html/BabyKawaii-Admin/
```

### 2. สร้างฐานข้อมูล
```sql
-- เปิด phpMyAdmin หรือ MySQL Client แล้วรัน:
-- ไฟล์: database/setup.sql
```
หรือ Import ผ่าน phpMyAdmin → Import → เลือกไฟล์ `database/setup.sql`

### 3. แก้ไขการตั้งค่า Database
เปิดไฟล์ `config/database.php` แล้วแก้ไข:
```php
define('DB_HOST', 'localhost');     // โฮสต์ฐานข้อมูล
define('DB_NAME', 'babykawaii_db'); // ชื่อฐานข้อมูล
define('DB_USER', 'root');          // ชื่อผู้ใช้ MySQL
define('DB_PASS', '');              // รหัสผ่าน MySQL
define('SITE_URL', 'http://localhost/BabyKawaii-Admin'); // URL ของเว็บ
```

### 4. สิทธิ์โฟลเดอร์ (Permissions)
```bash
chmod 755 assets/uploads/products/
chmod 755 assets/uploads/media/
```
หรือบน Windows: คลิกขวา → Properties → ยกเลิก Read-only

### 5. เข้าสู่ระบบ
- URL: `http://localhost/BabyKawaii-Admin/`
- Username: `admin`
- Password: `admin1234`
- **⚠️ เปลี่ยนรหัสผ่านทันทีหลังเข้าครั้งแรก!**

---

## 📁 โครงสร้างไฟล์

```
BabyKawaii-Admin/
├── index.php              # Redirect หน้าแรก
├── login.php              # หน้าล็อกอิน
├── logout.php             # ออกจากระบบ
├── dashboard.php          # แดชบอร์ดหลัก
├── config/
│   └── database.php       # ⚙️ การตั้งค่าฐานข้อมูล
├── includes/
│   ├── header.php         # ส่วนหัวเว็บ + Navbar
│   ├── sidebar.php        # เมนูด้านข้าง
│   └── footer.php         # ส่วนท้าย + Scripts
├── pages/
│   ├── products.php       # รายการสินค้าทั้งหมด
│   ├── product-add.php    # เพิ่ม/แก้ไขสินค้า
│   ├── stock.php          # จัดการสต็อก
│   ├── orders.php         # ออเดอร์/คำสั่งซื้อ
│   ├── sales.php          # ยอดขาย & วิเคราะห์
│   ├── platforms.php      # แพลตฟอร์มขาย
│   ├── media.php          # คลังสื่อ
│   ├── promotions.php     # โปรโมชั่น
│   └── settings.php       # ตั้งค่าร้าน
├── assets/
│   ├── css/style.css      # สไตล์หน้าเว็บ (Kawaii Theme)
│   ├── js/main.js         # JavaScript
│   └── uploads/
│       ├── products/      # รูปสินค้า
│       └── media/         # คลังสื่อ
└── database/
    └── setup.sql          # Script สร้างฐานข้อมูล
```

---

## 🌟 ฟีเจอร์ทั้งหมด

### 📊 Dashboard
- KPI Cards: ยอดขายวันนี้, เดือนนี้, สินค้า, ออเดอร์รอ
- กราฟยอดขาย 7 วัน
- สัดส่วนยอดขายต่อแพลตฟอร์ม
- สินค้าขายดี 5 อันดับ
- ออเดอร์ล่าสุด
- แจ้งเตือนสต็อกใกล้หมด

### 👕 จัดการสินค้า
- เพิ่ม/แก้ไข/ลบสินค้า
- อัปโหลดรูปภาพสินค้า
- จัดการสต็อกตามไซต์ × สี
- คำนวณกำไร/ต้นทุน
- หมวดหมู่: บอดี้สูท, ชุดนอน, ชุดเซต ฯลฯ
- ไซต์: Premature, NB, 0-3M, 3-6M, 6-9M, 9-12M, 12-18M, 18-24M

### 📦 จัดการสต็อก
- ดูสต็อกทุกรายการ
- กรอง: ใกล้หมด / หมดสต็อก
- เติมสต็อก (Restock)
- ปรับสต็อก (Adjust)
- บันทึกประวัติการเคลื่อนไหว

### 🛍️ ออเดอร์ / คำสั่งซื้อ
- บันทึกออเดอร์จากทุกแพลตฟอร์ม
- อัปเดตสถานะ: รอ → ยืนยัน → แพ็ค → จัดส่ง → ส่งถึง
- ใส่เลขพัสดุ (Tracking)
- กรองตามแพลตฟอร์ม, วันที่, สถานะ

### 📈 ยอดขาย & วิเคราะห์
- KPI: รายได้, ออเดอร์, AOV
- กราฟแนวโน้มยอดขาย
- เปรียบเทียบยอดตามแพลตฟอร์ม
- สินค้าขายดี / ขายดีตามไซต์
- กรองตามช่วงเวลา: วันนี้, 7 วัน, เดือนนี้, ปีนี้

### 🔗 แพลตฟอร์มขาย
- Facebook Page
- TikTok Shop
- Instagram
- Line Official
- หน้าร้าน
- เพิ่มแพลตฟอร์มเองได้
- เคล็ดลับแต่ละแพลตฟอร์ม

### 🖼️ คลังสื่อ
- อัปโหลดรูปภาพ & วิดีโอโปรโมท
- Drag & Drop
- Tag สำหรับจัดหมวดหมู่
- เชื่อมกับสินค้าและแพลตฟอร์ม

### 🏷️ โปรโมชั่น
- ลด % / ลดจำนวนเงิน
- จัดส่งฟรี
- Bundle Deal
- Flash Sale
- Coupon Code
- กำหนดวันเริ่ม-สิ้นสุด

### ⚙️ ตั้งค่าร้าน
- ข้อมูลร้านค้า
- Social Media Links
- ตั้งค่าแจ้งเตือนสต็อก
- ค่าจัดส่ง
- เปลี่ยนรหัสผ่าน

---

## 💡 เคล็ดลับการขายเสื้อผ้าเด็กออนไลน์

### สิ่งที่ควรทำเพิ่มเติม
1. **ถ่ายรูปสินค้า** บนพื้นหลังสีขาวหรือพาสเทล
2. **วิดีโอสั้น** แสดงเนื้อผ้า ความนุ่ม ขนาด
3. **รีวิวจากลูกค้า** ขอรูปเด็กสวมใส่จริง
4. **Size Guide** บอกน้ำหนัก/ความยาวเด็กที่เหมาะ
5. **Packaging** ใส่ถุงน่ารัก การ์ดขอบคุณ
6. **Line Group** สำหรับลูกค้าประจำ
7. **Birthday Campaign** ส่งข้อความในวันเกิดลูก
8. **Newborn Bundle** ชุดของขวัญสำหรับเด็กแรกเกิด

---

## 🔧 การแก้ปัญหาเบื้องต้น

**ไม่สามารถเข้าระบบได้:**
- ตรวจสอบ username/password ใน database.php
- ตรวจสอบว่า import setup.sql แล้ว

**อัปโหลดรูปไม่ได้:**
- ตรวจสอบสิทธิ์โฟลเดอร์ uploads/
- ตรวจสอบ file_uploads = On ใน php.ini

**กราฟไม่แสดง:**
- ตรวจสอบการเชื่อมต่ออินเทอร์เน็ต (โหลด Chart.js จาก CDN)

---

*BabyKawaii Shop Admin v1.0 | สร้างด้วย ❤️*
