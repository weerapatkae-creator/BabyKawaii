/* ===== BabyKawaii — Mock data (ภาษาไทย) ===== */
window.BK_DATA = (function () {
  const shop = {
    name: "BabyKawaii",
    tagline: "เสื้อผ้าเด็กแรกเกิด",
    admin: "พิมพ์ชนก",
    role: "เจ้าของร้าน",
  };

  // KPI ภาพรวม
  const kpis = {
    salesToday: 8420,
    salesTodayDelta: +12.4,
    salesMonth: 184250,
    ordersMonth: 312,
    ordersMonthDelta: +8.1,
    products: 86,
    stockTotal: 1240,
    pending: 14,
    lowStock: 6,
    inventoryCost: 96400,
    cogsMonth: 71200,
    grossProfitMonth: 113050,
    marginMonth: 61.4,
    aov: 591,
  };

  // ยอดขาย 14 วันล่าสุด (บาท)
  const sales14 = [
    5200, 6100, 4800, 7300, 8900, 6400, 5900,
    7100, 9200, 6800, 7600, 10400, 8100, 8420,
  ];
  const salesDays = (() => {
    const out = [];
    const base = new Date(2026, 4, 29);
    for (let i = 13; i >= 0; i--) {
      const d = new Date(base);
      d.setDate(base.getDate() - i);
      out.push(`${d.getDate()}/${d.getMonth() + 1}`);
    }
    return out;
  })();

  // แพลตฟอร์ม
  const platforms = [
    { key: "tiktok", name: "TikTok Shop", emoji: "🎵", color: "#2C2A30", total: 74200, orders: 128, share: 40 },
    { key: "facebook", name: "Facebook", emoji: "📘", color: "#5b7bb4", total: 48900, orders: 84, share: 27 },
    { key: "line", name: "LINE OA", emoji: "💬", color: "#6FB89A", total: 33100, orders: 61, share: 18 },
    { key: "instagram", name: "Instagram", emoji: "📸", color: "#c98aa6", total: 18650, orders: 27, share: 10 },
    { key: "walkin", name: "หน้าร้าน", emoji: "🏠", color: "#D8A24A", total: 9400, orders: 12, share: 5 },
  ];

  const categories = ["บอดี้สูท", "ชุดนอน", "ชุดเซต", "หมวก & ถุงเท้า", "ผ้าห่ม & ของใช้"];
  const sizes = ["Preemie", "NB", "0-3M", "3-6M", "6-9M", "9-12M", "12-18M", "18-24M"];
  const colorsTH = ["ครีม", "ชมพูอ่อน", "ฟ้าพาสเทล", "เขียวมิ้นต์", "เหลืองนวล", "เทาอ่อน"];

  // สินค้า
  const productNames = [
    ["บอดี้สูทแขนยาว ลายกระต่าย", "บอดี้สูท"],
    ["ชุดนอนผ้ามัสลิน ลายเมฆ", "ชุดนอน"],
    ["ชุดเซตหมีน้อย 3 ชิ้น", "ชุดเซต"],
    ["หมวกไหมพรมหูแมว", "หมวก & ถุงเท้า"],
    ["บอดี้สูทแขนสั้น คอติดกระดุม", "บอดี้สูท"],
    ["ชุดนอนซิปยาว ลายดาว", "ชุดนอน"],
    ["ผ้าห่มมัสลิน 4 ชั้น", "ผ้าห่ม & ของใช้"],
    ["ถุงเท้าเด็ก แพ็ก 5 คู่", "หมวก & ถุงเท้า"],
    ["ชุดเซตกระต่ายน้อย 5 ชิ้น", "ชุดเซต"],
    ["บอดี้สูทแขนกุด ผ้าคอตตอน", "บอดี้สูท"],
    ["ชุดนอนหมีพูห์ ผ้าสำลี", "ชุดนอน"],
    ["ผ้ากันเปื้อนกันน้ำ ลายผลไม้", "ผ้าห่ม & ของใช้"],
  ];
  const products = productNames.map((p, i) => {
    const price = [259, 320, 590, 159, 199, 350, 290, 120, 790, 180, 380, 95][i];
    const cost = Math.round(price * (0.38 + (i % 3) * 0.04));
    const stock = [42, 18, 9, 0, 64, 27, 5, 88, 12, 33, 3, 51][i];
    return {
      id: i + 1,
      sku: `BK-${1000 + (i + 1) * 7}`,
      name: p[0],
      category: p[1],
      price,
      cost,
      stock,
      sold: [120, 86, 54, 31, 142, 73, 22, 210, 41, 98, 12, 64][i],
      status: stock === 0 ? "out" : stock <= 5 ? "low" : "active",
      tint: ["#FBE9EE", "#E8EEF8", "#E6F2EC", "#F8EFDD", "#FBE9EE", "#E8EEF8", "#E6F2EC", "#F3EEF6", "#FBE9EE", "#E8EEF8", "#F8EFDD", "#E6F2EC"][i],
    };
  });

  const bestSellers = [...products].sort((a, b) => b.sold - a.sold).slice(0, 5);

  // ออเดอร์
  const customers = ["คุณนภัสสร", "คุณจิรายุ", "คุณพิมพ์มาดา", "คุณธนกฤต", "คุณวรินทร", "คุณศุภางค์", "คุณกัญญาณัฐ", "คุณปุณยวีร์", "คุณชญานิษฐ์", "คุณรวิภา"];
  const statusOrder = ["pending", "confirmed", "packing", "shipped", "delivered"];
  const orders = Array.from({ length: 12 }).map((_, i) => {
    const pf = platforms[i % platforms.length];
    const st = ["pending", "pending", "confirmed", "packing", "shipped", "delivered", "delivered", "confirmed", "packing", "shipped", "pending", "cancelled"][i];
    const total = [590, 259, 950, 320, 1290, 480, 159, 790, 350, 620, 199, 380][i];
    return {
      id: i + 1,
      number: `#BK${26050 + i}`,
      customer: customers[i % customers.length],
      platform: pf,
      total,
      items: [1, 1, 2, 1, 3, 2, 1, 2, 1, 2, 1, 1][i],
      status: st,
      date: `${29 - (i % 6)}/05`,
      time: `${9 + i}:${(i * 7) % 60 < 10 ? "0" : ""}${(i * 7) % 60}`,
    };
  });

  const statusTH = {
    pending: "รอดำเนินการ",
    confirmed: "ยืนยันแล้ว",
    packing: "กำลังแพ็ค",
    shipped: "จัดส่งแล้ว",
    delivered: "ส่งถึงแล้ว",
    cancelled: "ยกเลิก",
  };

  // สต็อกใกล้หมด
  const lowStockItems = products
    .filter((p) => p.status !== "active")
    .map((p) => ({
      name: p.name,
      sku: p.sku,
      size: sizes[(p.id + 1) % sizes.length],
      color: colorsTH[p.id % colorsTH.length],
      qty: p.stock,
    }))
    .concat([
      { name: "ชุดนอนซิปยาว ลายดาว", sku: "BK-1042", size: "3-6M", color: "ชมพูอ่อน", qty: 4 },
      { name: "บอดี้สูทแขนยาว ลายกระต่าย", sku: "BK-1007", size: "NB", color: "ครีม", qty: 3 },
      { name: "ผ้าห่มมัสลิน 4 ชั้น", sku: "BK-1049", size: "—", color: "เขียวมิ้นต์", qty: 5 },
    ]);

  // ลูกค้า
  const customerList = customers.map((c, i) => ({
    id: i + 1,
    name: c,
    orders: [12, 8, 21, 4, 15, 6, 9, 3, 18, 7][i],
    spent: [7100, 4200, 14800, 1600, 9200, 2900, 5100, 980, 11200, 3400][i],
    platform: platforms[i % platforms.length],
    last: `${(i * 3) % 28 + 1}/05`,
    tier: [12, 8, 21, 4, 15, 6, 9, 3, 18, 7][i] >= 15 ? "VIP" : [12, 8, 21, 4, 15, 6, 9, 3, 18, 7][i] >= 7 ? "ประจำ" : "ใหม่",
  }));

  // ทีมงาน / ผู้ใช้งานระบบ
  const team = [
    { id: 1, name: "พิมพ์ชนก ศรีสุข", initial: "พ", role: "เจ้าของร้าน", roleKey: "owner", status: "online", closeRate: 71, orders: 142, sales: 184250, replyTime: "2 นาที", tint: "#FBE9EE", last: "ออนไลน์อยู่" },
    { id: 2, name: "ศิริพร ใจดี", initial: "ศ", role: "แอดมินขาย", roleKey: "admin", status: "away", closeRate: 74, orders: 110, sales: 138900, replyTime: "1.5 นาที", tint: "#E6F2EC", last: "5 นาทีที่แล้ว" },
    { id: 3, name: "ธนวัฒน์ พงษ์ไพร", initial: "ธ", role: "แอดมินขาย", roleKey: "admin", status: "online", closeRate: 68, orders: 98, sales: 121400, replyTime: "3 นาที", tint: "#E8EEF8", last: "ออนไลน์อยู่" },
    { id: 4, name: "รัตนา แก้วมณี", initial: "ร", role: "แอดมินขาย", roleKey: "admin", status: "offline", closeRate: 59, orders: 64, sales: 71200, replyTime: "5 นาที", tint: "#F3EEF6", last: "เมื่อวาน" },
    { id: 5, name: "กิตติพงษ์ ทองคำ", initial: "ก", role: "แพ็ค & จัดส่ง", roleKey: "staff", status: "online", closeRate: null, orders: null, sales: null, replyTime: "—", tint: "#F8EFDD", last: "ออนไลน์อยู่" },
  ];

  return { shop, kpis, sales14, salesDays, platforms, categories, sizes, colorsTH, products, bestSellers, orders, statusTH, lowStockItems, customerList, team };
})();
