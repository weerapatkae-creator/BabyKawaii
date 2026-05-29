# Handoff: BabyKawaii Admin — รีดีไซน์ทั้งระบบให้เหมือน Prototype

> เป้าหมาย: แปลงเว็บแอดมิน BabyKawaii (PHP + MySQL) ให้หน้าตา **เหมือน prototype ทุกหน้า**
> โดย **คงฐานข้อมูล/คิวรี/ลอจิก/ฟังก์ชันเดิมทั้งหมด** เปลี่ยนเฉพาะ markup + CSS

อ่านไฟล์นี้ให้จบก่อนเริ่ม แล้วทำทีละหน้าตามตาราง "งานต่อหน้า" ด้านล่าง

---

## 0. บริบทโปรเจค

- **Repo ต้นทาง:** https://github.com/weerapatkae-creator/BabyKawaii (branch `master`)
- **สแตก:** PHP (vanilla, ไม่มี framework) + MySQL (PDO) + Bootstrap 5.3 + Font Awesome 6.4
- **โครงไฟล์:**
  - `config/database.php` — มี `getDB()` (คืน PDO), `requireLogin()`, `getSetting()`, `formatPrice()`, `formatDateTH()`, ค่าคงที่ `SITE_URL`, ใช้ `$_SESSION['admin_name'|'admin_role'|'admin_id']`
  - `includes/header.php` — เปิด `<html>`, navbar, เปิด `.wrapper` + include sidebar + เปิด `.main-content`
  - `includes/sidebar.php` — เมนู
  - `includes/footer.php` — ปิด div, โหลด Bootstrap JS + Chart.js + `main.js`, รับตัวแปร `$extraJs`, มี inbox badge poller (อย่าแก้)
  - `pages/*.php` — แต่ละหน้า เริ่มด้วย `require header.php` ... ปิดด้วย `include footer.php`
- **กราฟ:** ใช้ Chart.js (มากับ footer.php)

## 1. ไฟล์ในแพ็กนี้

```
prototype/                     ← ดีไซน์อ้างอิง (HTML/React) — "หน้าตาที่ต้องการ"
  BabyKawaii รีดีไซน์.html      ← เปิดในเบราว์เซอร์เพื่อดูดีไซน์จริงทุกหน้า
  bk/                          ← โค้ด React ของ prototype (อ่านเพื่อดูโครงสร้าง/คอมโพเนนต์)
  tweaks-panel.jsx
php-current/                   ← โค้ด PHP ที่เริ่มแปลงแล้ว (เอาไปต่อยอด/วางทับ repo)
  assets/css/style.css         ← ✅ ดีไซน์ระบบครบแล้ว (design tokens + ทุก component)
  includes/header.php          ← ✅ topbar ใหม่เสร็จแล้ว
  includes/sidebar.php         ← ✅ เมนูจัดกลุ่มเสร็จแล้ว
  dashboard.php                ← ✅ รีดีไซน์เต็มแล้ว (ใช้เป็น "ตัวอย่างมาตรฐาน")
  pages/*.php                  ← ⚠️ ปรับสีแล้ว แต่ยังไม่ได้จัดเลย์เอาต์ให้ตรง prototype
  login.php                    ← ปรับสีแล้ว
```

> **prototype = แหล่งความจริงของดีไซน์** (ไม่ใช่โค้ดที่ก๊อปตรงๆ เพราะเป็น React/mock)
> **php-current/dashboard.php = ตัวอย่างวิธีแปลงที่ถูกต้อง** — ทำหน้าอื่นให้เหมือนสไตล์นี้

---

## 2. กฎเหล็ก (ห้ามพลาด)

1. **ห้ามแก้** คิวรี SQL, ชื่อ/โครงตัวแปร PHP, ลอจิก, การ redirect, การ handle POST/GET — คัดลอกบล็อก PHP เดิมมาทั้งดุ้น แก้เฉพาะส่วน HTML ที่ echo ออกมา
2. **ห้ามเปลี่ยน** `id` ที่ผูกกับ JS: `sidebarToggle`, `headerInboxBtn`, `headerInboxBadge`, `headerInboxLabel`, `sidebarInboxBadge`, `toastContainer`, รวมถึง element id ที่ JS ในแต่ละหน้าอ้างถึง (เช็คใน `<script>` ท้ายไฟล์ก่อนเปลี่ยน class/id)
3. **คงฟังก์ชัน** `formatPrice()`, `formatDateTH()` — ใช้ต่อ
4. **ใช้คลาสจาก `style.css` ที่มีอยู่** เป็นหลัก (ดูรายการคลาสข้อ 4) แทนการเขียน inline style ใหม่ ถ้าต้อง custom ให้เพิ่มใน `style.css` ไม่ใช่ inline
5. **คง footer.php เดิม** (มี poller/Chart.js) — แค่ใช้ `$extraJs` ส่ง JS กราฟเหมือน dashboard.php
6. แถบ sidebar เป็น **โทนเข้ม** (เพราะโลโก้ `logo-light.svg` สีขาว) — อย่าเปลี่ยนเป็นพื้นอ่อน
7. ทดสอบทุกหน้าหลังแก้: เปิดหน้านั้น เทียบกับ prototype ให้ตรง (เลย์เอาต์ การ์ด ตาราง สี ระยะห่าง)

---

## 3. Design tokens (อยู่ใน `php-current/assets/css/style.css` แล้ว)

```
accent (โรส):  --pink #E8869B   --pink-dark #D26A82   --pink-light #FBE9EE
สถานะ:         เขียว #3AA088 / เหลือง #E0B25E / แดง #C5605F / ฟ้า #5A78B4 / ม่วง #8A6DB0
พื้น:          --bg #FAF7F9   --bg-2 #F3EEF2   --card-bg #FFF   --border #EFE9EE
ตัวอักษร:      --text #2C2A30   --text-muted #8A8490
sidebar:       --sidebar-bg #232029  (เข้ม)
radius:        --radius 16px   --radius-sm 11px
shadow:        --shadow (เงานุ่มบาง)
ฟอนต์:         Noto Sans Thai (โหลดใน header.php + style.css แล้ว)
```

## 4. คลาส CSS ที่ใช้ซ้ำได้ (มีใน style.css แล้ว)

- การ์ด: `.card` `.card-header` `.card-title` `.card-body`
- สถิติ: `.stat-card` (+ `.pink`/`.mint`/`.purple`/`.orange`) `.stat-label` `.stat-value` `.stat-icon` `.stat-change` (+`.up`/`.down`)
- ตาราง: `.table` (Bootstrap) — restyle แล้ว
- แบดจ์สถานะ: `.badge-status` + `.badge-active/-low/-out/-pending/-confirmed/-packing/-shipped/-delivered/-cancelled`
- ปุ่ม: `.btn-primary` `.btn-outline-pink` `.btn-kawaii` `.btn-success`
- สินค้า: `.product-card` `.product-info` `.product-name` `.product-price` `.product-no-img`
- สต็อก: `.size-grid` `.size-badge` (+`.size-ok/-low/-out`)
- แพลตฟอร์ม: `.platform-card` (+`.pf-tiktok/-facebook/-line/-instagram/-walkin`)
- คลังสื่อ: `.media-grid` `.media-item` `.media-info` `.media-overlay`
- โปรโมชั่น: `.promo-card` `.promo-badge` `.promo-name` `.promo-value` `.promo-dates`
- ปฏิทิน: `.calendar-grid` `.cal-day` (+`.today`) `.cal-event`
- ต้นทุน&กำไร: `.profit-tile` `.pt-label` `.pt-value` (+`.pt-blue/-amber/-green/-rose`) `.pt-bar`
- ฟอร์ม: `.form-control` `.form-select` `.form-label` — restyle แล้ว
- หัวหน้า: `.page-header` `.page-title` `.page-subtitle`

ถ้าต้องการคลาสใหม่ (เช่นของหน้า inbox) เพิ่มใน `style.css` ตามสไตล์เดียวกัน (ดูได้จาก prototype `bk/styles.css`)

---

## 5. งานต่อหน้า — แปลงให้ตรง prototype

> วิธีดูดีไซน์เป้าหมาย: เปิด `prototype/BabyKawaii รีดีไซน์.html` แล้วคลิกเมนูไปหน้านั้น (หรือเปิด Tweaks เพื่อดูตัวเลือกเลย์เอาต์ — ใช้แนวทาง "การ์ดนุ่ม" เป็นค่าเริ่มต้น)

| # | หน้า PHP | ดูที่เมนู prototype | สิ่งที่ต้องทำ | สถานะ |
|---|---|---|---|---|
| 1 | `dashboard.php` | แดชบอร์ด | — | ✅ เสร็จ (ใช้เป็นแบบอ้างอิง) |
| 2 | `pages/products.php` | สินค้าทั้งหมด | grid `.product-card` + ปุ่มสลับการ์ด/ตาราง + แถบกรอง chip — ส่วนใหญ่ตรงแล้ว เก็บรายละเอียด badge/spacing | ⚠️ ปรับสีแล้ว |
| 3 | `pages/product-add.php` | เพิ่มสินค้า | ฟอร์ม 2 คอลัมน์: ซ้าย=ข้อมูล+สต็อกตามไซต์, ขวา=รูป+การ์ด "ราคา & กำไร" คำนวณสด | ⚠️ |
| 4 | `pages/stock.php` | จัดการสต็อก | การ์ดสถิติ 4 ช่อง + ตารางสต็อกแยกไซต์ใช้ `.size-badge` + แท็บกรอง (ทั้งหมด/ใกล้หมด/หมด) | ⚠️ |
| 5 | `pages/orders.php` | ออเดอร์ | แท็บ chip ตามสถานะ + ตารางออเดอร์ + โลโก้แพลตฟอร์ม + แถบขั้นตอนจัดส่ง | ⚠️ |
| 6 | `pages/inbox.php` | Inbox ข้อความ | layout 2 ฝั่ง (รายชื่อ+แชต) บน PC, มือถือกดเข้าแชตเต็มจอ + **แถบเครื่องมือ** (เช็คสต็อก/จัดส่ง/สร้างออเดอร์) + **คลังคำตอบ** — ดูข้อ 6 (ต้องมี backend) | ⚠️ |
| 7 | `pages/sales.php` | ยอดขาย & วิเคราะห์ | การ์ด KPI + กราฟแนวโน้ม + โดนัทช่องทาง + บาร์ขายดีตามไซต์ + อันดับสินค้าขายดี | ⚠️ ปรับสีแล้ว |
| 8 | `pages/customers.php` | ฐานข้อมูลลูกค้า | การ์ดสถิติ 3 ช่อง + ตารางลูกค้า + avatar + badge ระดับ (VIP/ประจำ/ใหม่) | ⚠️ ปรับสีแล้ว |
| 9 | `pages/platforms.php` | แพลตฟอร์มขาย | grid `.platform-card` + สถานะเชื่อมต่อ + ยอด/ออเดอร์ + bar สัดส่วน | ⚠️ |
| 10 | `pages/media.php` | คลังสื่อ | upload zone + `.media-grid` การ์ดรูป/วิดีโอ + แท็ก + เลือกหลายไฟล์ | ⚠️ |
| 11 | `pages/calendar.php` | ปฏิทินโพสต์ | `.calendar-grid` จุดสีตามแพลตฟอร์ม + แผงโพสต์รายวัน + คิวโพสต์ | ⚠️ ปรับสีแล้ว |
| 12 | `pages/promotions.php` | โปรโมชั่น | grid `.promo-card` + แท็บกรองสถานะ + โค้ด/วันที่/ยอดใช้ | ⚠️ |
| 13 | `pages/users.php` | จัดการผู้ใช้งาน | การ์ดสถิติ + การ์ด "ปิดการขายเก่งสุด" + ตารางทีม + bar อัตราปิดการขาย | ⚠️ ปรับสีแล้ว |
| 14 | `pages/settings.php` | ตั้งค่าร้าน | ฟอร์ม 2 คอลัมน์ + toggle การแจ้งเตือน + การแสดงผล | ⚠️ |
| 15 | `login.php` | (หน้า Login ใน prototype) | การ์ดกลางจอ + โลโก้ onesie + ฟิลด์ + ปุ่มไล่โทนโรส | ⚠️ ปรับสีแล้ว |
| 16 | `pages/platform-accounts.php`, `integrations.php`, `data-manage.php`, `deploy-guide.php`, `system-test.php`, `export.php`, `import.php`, `media-picker.php`, `order-print.php` | (ไม่มีใน prototype) | ใช้ดีไซน์ระบบเดียวกัน: ครอบด้วย `.card`/`.page-header`/`.table`/`.badge-status` ให้กลมกลืน | ⚠️ |

**ขั้นตอนแปลงแต่ละหน้า:**
1. เปิดไฟล์ PHP เดิม — แยกส่วน "PHP logic (บน)" กับ "HTML output (ล่าง)"
2. คงส่วน PHP logic ไว้ทั้งหมด
3. เขียน HTML output ใหม่ตาม prototype โดยใช้คลาสข้อ 4 + ตัวแปร PHP เดิม
4. ถ้ามีกราฟ ส่งผ่าน `$extraJs` แล้ว `include footer.php` (ดู dashboard.php)
5. เทียบกับ prototype หน้านั้นให้ตรง → ทดสอบรัน

---

## 6. ฟีเจอร์ใหม่ที่ต้องมี backend (เกินงานหน้าตา)

ใน prototype หน้า **Inbox** มีฟีเจอร์ที่ของจริงยังไม่มี ต้องเพิ่ม DB + โค้ด:

- **แถบเครื่องมือในแชต**
  - เช็คสต็อก: query `stock` ตาม keyword (AJAX) แสดงคงเหลือแยกไซต์
  - เช็คการจัดส่ง: ดึงเลขพัสดุ/สถานะจาก `orders`
  - สร้างคำสั่งซื้อ: ฟอร์มสร้าง order จากในแชต (insert `orders` + `order_items`)
- **คลังคำตอบอัจฉริยะ (Smart Replies)** — ต้องมีตารางใหม่ เช่น
  ```sql
  CREATE TABLE quick_replies (
    id INT AUTO_INCREMENT PRIMARY KEY,
    text TEXT, admin_id INT,
    uses INT DEFAULT 0, closed_count INT DEFAULT 0,
    close_rate DECIMAL(5,2) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
  );
  ```
  - แอดมินแก้/เพิ่มคำตอบได้, ระบบนับ uses/closed เพื่อคำนวณ `close_rate`, แสดงประโยคที่ปิดดีสุด + ใครใช้
- หน้า **users**: สถิติ `close_rate` รายแอดมิน ต้องคำนวณจาก orders ที่ปิดได้ผูกกับ admin ผู้ดูแลแชต

> ถ้า scope จำกัด ทำส่วน "หน้าตา" (ข้อ 5) ให้ครบก่อน แล้วค่อยทำฟีเจอร์ backend ข้อ 6

---

## 7. วิธีรัน/ทดสอบ

1. วางไฟล์จาก `php-current/` ทับ repo (สำรองก่อน) — ได้ดีไซน์ระบบ + dashboard ทันที
2. ตั้ง local PHP+MySQL (ดู `INSTALL.md` ใน repo) แล้ว import `database/setup-complete.sql`
3. เปิดแต่ละหน้า เทียบกับ `prototype/BabyKawaii รีดีไซน์.html` หน้าเดียวกัน
4. แก้ทีละหน้าตามตารางข้อ 5 จนครบ

เกณฑ์ "เหมือน": เลย์เอาต์ (กริด/คอลัมน์), การ์ด, ตาราง, แบดจ์, สี, ระยะห่าง, ฟอนต์ ตรงกับ prototype
