<?php
$shopName = 'BabyKawaii Shop';
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>นโยบายความเป็นส่วนตัว - <?= $shopName ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Noto+Sans+Thai:wght@400;600;700&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Noto Sans Thai', sans-serif; background: #faf7f9; color: #2c2a30; margin: 0; padding: 0; }
        .container { max-width: 800px; margin: 0 auto; padding: 48px 24px; }
        h1 { font-size: 1.8rem; font-weight: 700; color: #d26a82; margin-bottom: 8px; }
        h2 { font-size: 1.1rem; font-weight: 600; margin-top: 32px; margin-bottom: 8px; }
        p, li { line-height: 1.8; color: #444; font-size: 0.95rem; }
        ul { padding-left: 20px; }
        .updated { font-size: 0.85rem; color: #999; margin-bottom: 32px; }
        hr { border: none; border-top: 1px solid #efe9ee; margin: 32px 0; }
    </style>
</head>
<body>
<div class="container">
    <h1>นโยบายความเป็นส่วนตัว</h1>
    <p class="updated">อัปเดตล่าสุด: <?= date('d/m/Y') ?></p>

    <p><?= $shopName ?> ให้ความสำคัญกับความเป็นส่วนตัวของลูกค้าและผู้ใช้งาน นโยบายนี้อธิบายวิธีที่เราเก็บรวบรวม ใช้ และปกป้องข้อมูลส่วนบุคคลของคุณ</p>

    <h2>1. ข้อมูลที่เราเก็บรวบรวม</h2>
    <ul>
        <li>ชื่อและข้อมูลโปรไฟล์จาก Facebook / Instagram</li>
        <li>ข้อความที่ส่งผ่านช่องทาง Messenger และ DM</li>
        <li>ข้อมูลการสั่งซื้อสินค้า เช่น ชื่อ ที่อยู่ เบอร์โทรศัพท์</li>
    </ul>

    <h2>2. วัตถุประสงค์การใช้ข้อมูล</h2>
    <ul>
        <li>ตอบกลับข้อความและให้บริการลูกค้า</li>
        <li>จัดการคำสั่งซื้อและจัดส่งสินค้า</li>
        <li>ปรับปรุงคุณภาพบริการ</li>
    </ul>

    <h2>3. การแชร์ข้อมูล</h2>
    <p>เราไม่ขาย เช่า หรือแชร์ข้อมูลส่วนบุคคลของคุณให้กับบุคคลภายนอก ยกเว้นในกรณีที่จำเป็นต้องปฏิบัติตามกฎหมาย</p>

    <h2>4. การลบข้อมูล</h2>
    <p>หากต้องการให้ลบข้อมูลของคุณออกจากระบบ กรุณาติดต่อเราที่ <strong>doggybearie@gmail.com</strong> เราจะดำเนินการภายใน 30 วัน</p>

    <h2>5. ติดต่อเรา</h2>
    <p>หากมีคำถามเกี่ยวกับนโยบายนี้ ติดต่อได้ที่:<br>
    อีเมล: doggybearie@gmail.com</p>

    <hr>
    <p style="text-align:center;color:#bbb;font-size:0.8rem;">&copy; <?= date('Y') ?> <?= $shopName ?> · All rights reserved</p>
</div>
</body>
</html>
