/* ===== BabyKawaii — Media / Post Calendar / Promotions ===== */

/* ---------- ข้อมูล ---------- */
const BK_MEDIA = (() => {
  const tints = ["#FBE9EE", "#E8EEF8", "#E6F2EC", "#F8EFDD", "#F3EEF6", "#FBE9EE", "#E8EEF8", "#E6F2EC", "#F8EFDD", "#F3EEF6", "#FBE9EE", "#E6F2EC"];
  const names = [
    ["บอดี้สูทลายกระต่าย-ภาพหลัก", "img"], ["รีวิวลูกค้า-น้องมินนี่", "img"], ["คลิปเนื้อผ้ามัสลิน", "vid"],
    ["ชุดเซตหมีน้อย-แฟลตเลย์", "img"], ["ไลฟ์เปิดตัวคอลเลกชัน", "vid"], ["หมวกหูแมว-โคลสอัพ", "img"],
    ["แพ็กของขวัญแรกเกิด", "img"], ["คลิปแกะกล่อง-unboxing", "vid"], ["ชุดนอนลายดาว-ภาพหลัก", "img"],
    ["โปรโมชั่นวันแม่-แบนเนอร์", "img"], ["รีวิวคุณแม่-วิดีโอสั้น", "vid"], ["ผ้าห่มมัสลิน-ดีเทล", "img"],
  ];
  const tags = [["สินค้า", "บอดี้สูท"], ["รีวิว"], ["วิดีโอ", "ผ้า"], ["สินค้า", "เซต"], ["ไลฟ์"], ["สินค้า"], ["แพ็กเกจ"], ["วิดีโอ"], ["สินค้า", "ชุดนอน"], ["โปรโมชั่น"], ["รีวิว"], ["ดีเทล"]];
  return names.map((n, i) => ({
    id: i + 1, name: n[0], type: n[1], tint: tints[i], tags: tags[i],
    used: [3, 1, 5, 2, 8, 0, 4, 6, 2, 12, 3, 1][i],
    size: n[1] === "vid" ? ["0:18", "1:24", "0:42", "0:55", "0:30"][i % 5] : ["1.2MB", "0.8MB", "2.1MB", "1.6MB"][i % 4],
  }));
})();

const BK_POSTS = (() => {
  const pf = window.BK_DATA.platforms.reduce((m, p) => { m[p.key] = p; return m; }, {});
  return [
    { day: 2, pf: pf.tiktok, kind: "ไลฟ์", title: "ไลฟ์สดเปิดกล่องคอลเลกชันใหม่", time: "20:00", status: "done", media: "#F8EFDD" },
    { day: 5, pf: pf.facebook, kind: "โพสต์", title: "บอดี้สูทลายกระต่าย พร้อมส่ง", time: "10:30", status: "done", media: "#FBE9EE" },
    { day: 8, pf: pf.instagram, kind: "สตอรี่", title: "รีวิวจากคุณแม่ น้องมินนี่", time: "18:00", status: "done", media: "#E8EEF8" },
    { day: 12, pf: pf.tiktok, kind: "โพสต์", title: "คลิปเนื้อผ้ามัสลิน นุ่มมาก", time: "12:00", status: "done", media: "#E6F2EC" },
    { day: 15, pf: pf.line, kind: "บรอดแคสต์", title: "โปรกลางเดือน ลด 15%", time: "09:00", status: "done", media: "#F3EEF6" },
    { day: 20, pf: pf.facebook, kind: "โพสต์", title: "ชุดเซตหมีน้อย ของขวัญแรกเกิด", time: "11:00", status: "scheduled", media: "#FBE9EE" },
    { day: 22, pf: pf.tiktok, kind: "ไลฟ์", title: "ไลฟ์สดนาทีทอง ส่งฟรีทั้งร้าน", time: "20:00", status: "scheduled", media: "#F8EFDD" },
    { day: 26, pf: pf.instagram, kind: "โพสต์", title: "ชุดนอนลายดาว คอลใหม่", time: "17:30", status: "scheduled", media: "#E8EEF8" },
    { day: 29, pf: pf.facebook, kind: "โพสต์", title: "แพ็กของขวัญแรกเกิด สุดคุ้ม", time: "10:00", status: "scheduled", media: "#E6F2EC" },
    { day: 30, pf: pf.tiktok, kind: "โพสต์", title: "รีวิวแกะกล่อง unboxing", time: "19:00", status: "scheduled", media: "#F3EEF6" },
  ];
})();

const BK_PROMOS = [
  { id: 1, name: "ส่งฟรีเมื่อครบ 500.-", type: "ส่งฟรี", value: "ส่งฟรี", code: "FREE500", from: "1 พ.ค.", to: "31 พ.ค.", status: "active", used: 142, tone: "mint" },
  { id: 2, name: "ลดทั้งร้านวันแม่", type: "ลด %", value: "15%", code: "MOM15", from: "10 พ.ค.", to: "15 พ.ค.", status: "active", used: 88, tone: "rose" },
  { id: 3, name: "Flash Sale นาทีทอง", type: "Flash Sale", value: "-30%", code: "FLASH30", from: "22 พ.ค.", to: "22 พ.ค.", status: "scheduled", used: 0, tone: "amber" },
  { id: 4, name: "เซตของขวัญแรกเกิด", type: "Bundle", value: "3 ชิ้น 790.-", code: "GIFT790", from: "1 พ.ค.", to: "30 มิ.ย.", status: "active", used: 54, tone: "violet" },
  { id: 5, name: "ลูกค้าใหม่ลด 50.-", type: "ลดเงิน", value: "฿50", code: "NEW50", from: "1 เม.ย.", to: "30 เม.ย.", status: "expired", used: 213, tone: "blue" },
  { id: 6, name: "วันเกิดน้อง รับส่วนลด", type: "คูปอง", value: "10%", code: "BDAY10", from: "ตลอดปี", to: "—", status: "active", used: 37, tone: "rose" },
];

/* ---------- คลังสื่อ ---------- */
function Media() {
  const [type, setType] = React.useState("all");
  const [sel, setSel] = React.useState([]);
  const list = BK_MEDIA.filter((m) => type === "all" || m.type === (type === "img" ? "img" : "vid"));
  const toggle = (id) => setSel((s) => s.includes(id) ? s.filter((x) => x !== id) : [...s, id]);

  return (
    <div className="bk-page">
      <PageHead title="คลังสื่อ" sub={`${BK_MEDIA.length} ไฟล์ · รูปภาพและวิดีโอสำหรับใช้โพสต์ขาย`}>
        {sel.length > 0 && <button className="bk-btn bk-btn--soft"><BKIcon name="calendar" size={15} /> ตั้งเวลาโพสต์ ({sel.length})</button>}
        <button className="bk-btn bk-btn--primary"><BKIcon name="plus" size={15} /> อัปโหลดสื่อ</button>
      </PageHead>

      <div style={{ border: "2px dashed var(--accent-soft-strong)", borderRadius: "var(--radius)", padding: "22px 16px", textAlign: "center", background: "var(--accent-soft)", color: "var(--accent-strong)", cursor: "pointer", marginBottom: "var(--gap)" }}>
        <BKIcon name="image" size={26} />
        <span style={{ fontWeight: 700, marginLeft: 10 }}>ลากรูปหรือวิดีโอมาวางที่นี่</span>
        <span className="bk-muted" style={{ marginLeft: 8, fontSize: ".82rem" }}>· รองรับ JPG, PNG, MP4 ไม่เกิน 50MB</span>
      </div>

      <div style={{ display: "flex", gap: 7, marginBottom: "var(--gap)" }}>
        {[["all", "ทั้งหมด"], ["img", "รูปภาพ"], ["vid", "วิดีโอ"]].map(([k, l]) => (
          <button key={k} className={"bk-chip" + (type === k ? " is-on" : "")} onClick={() => setType(k)}>{l}</button>
        ))}
      </div>

      <div className="bk-products" style={{ gridTemplateColumns: "repeat(auto-fill, minmax(176px, 1fr))" }}>
        {list.map((m) => (
          <div className="bk-product" key={m.id} onClick={() => toggle(m.id)} style={{ outline: sel.includes(m.id) ? "2px solid var(--accent)" : "none" }}>
            <div className="bk-product__img" style={{ background: m.tint, position: "relative" }}>
              <span style={{ fontSize: "2.4rem" }}>{m.type === "vid" ? "🎬" : "🖼️"}</span>
              <span style={{ position: "absolute", top: 8, left: 8 }}><Badge tone={m.type === "vid" ? "violet" : "blue"}>{m.type === "vid" ? "วิดีโอ " + m.size : "รูป"}</Badge></span>
              {sel.includes(m.id) && <span style={{ position: "absolute", top: 8, right: 8, width: 22, height: 22, borderRadius: "50%", background: "var(--accent)", color: "#fff", display: "flex", alignItems: "center", justifyContent: "center" }}><BKIcon name="check" size={13} /></span>}
            </div>
            <div className="bk-product__body">
              <div className="bk-row__title bk-truncate" style={{ marginBottom: 5 }}>{m.name}</div>
              <div style={{ display: "flex", gap: 5, flexWrap: "wrap", marginBottom: 6 }}>
                {m.tags.map((t) => <span key={t} className="bk-badge bk-badge--neutral" style={{ fontSize: ".66rem", padding: "2px 7px" }}>#{t}</span>)}
              </div>
              <div className="bk-row__sub">ใช้ในโพสต์แล้ว {m.used} ครั้ง</div>
            </div>
          </div>
        ))}
      </div>
    </div>
  );
}

/* ---------- ปฏิทินโพสต์ ---------- */
const TH_MONTHS = ["มกราคม", "กุมภาพันธ์", "มีนาคม", "เมษายน", "พฤษภาคม", "มิถุนายน", "กรกฎาคม", "สิงหาคม", "กันยายน", "ตุลาคม", "พฤศจิกายน", "ธันวาคม"];
const TH_DOW = ["อา", "จ", "อ", "พ", "พฤ", "ศ", "ส"];

function PostCalendar() {
  const [ym, setYm] = React.useState({ y: 2026, m: 4 }); // พ.ค. 2026
  const [selDay, setSelDay] = React.useState(29);
  const first = new Date(ym.y, ym.m, 1).getDay();
  const days = new Date(ym.y, ym.m + 1, 0).getDate();
  const isMay26 = ym.y === 2026 && ym.m === 4;
  const postsFor = (d) => (isMay26 ? BK_POSTS.filter((p) => p.day === d) : []);
  const cells = [];
  for (let i = 0; i < first; i++) cells.push(null);
  for (let d = 1; d <= days; d++) cells.push(d);

  const shift = (dir) => setYm((s) => {
    let m = s.m + dir, y = s.y;
    if (m < 0) { m = 11; y--; } if (m > 11) { m = 0; y++; }
    return { y, m };
  });
  const selPosts = postsFor(selDay);
  const upcoming = isMay26 ? BK_POSTS.filter((p) => p.status === "scheduled") : [];

  return (
    <div className="bk-page">
      <PageHead title="ปฏิทินโพสต์" sub="วางแผนคอนเทนต์ · ดึงสื่อจากคลังมาตั้งเวลาโพสต์ขายอัตโนมัติ">
        <button className="bk-btn bk-btn--ghost"><BKIcon name="image" size={15} /> เลือกจากคลังสื่อ</button>
        <button className="bk-btn bk-btn--primary"><BKIcon name="plus" size={15} /> ตั้งเวลาโพสต์</button>
      </PageHead>

      <div className="bk-grid-2" style={{ gridTemplateColumns: "1.7fr 1fr", alignItems: "start" }}>
        <Card pad={false}
          title={`${TH_MONTHS[ym.m]} ${ym.y + 543}`} emoji="🗓️"
          action={
            <div style={{ display: "flex", gap: 6 }}>
              <button className="bk-iconbtn" style={{ width: 32, height: 32 }} onClick={() => shift(-1)}><BKIcon name="chevronRight" size={15} style={{ transform: "rotate(180deg)" }} /></button>
              <button className="bk-iconbtn" style={{ width: 32, height: 32 }} onClick={() => shift(1)}><BKIcon name="chevronRight" size={15} /></button>
            </div>
          }>
          <div style={{ padding: "12px 14px" }}>
            <div className="bk-calgrid" style={{ marginBottom: 4 }}>
              {TH_DOW.map((d) => <div key={d} className="bk-cal-dow">{d}</div>)}
            </div>
            <div className="bk-calgrid">
              {cells.map((d, i) => {
                if (d === null) return <div key={i} />;
                const ps = postsFor(d);
                const today = isMay26 && d === 29;
                return (
                  <button key={i} className={"bk-cal-day" + (selDay === d ? " is-sel" : "") + (today ? " is-today" : "")} onClick={() => setSelDay(d)}>
                    <span className="bk-cal-num bk-num">{d}</span>
                    <div className="bk-cal-dots">
                      {ps.slice(0, 3).map((p, j) => <span key={j} style={{ background: p.pf.color }} />)}
                    </div>
                  </button>
                );
              })}
            </div>
          </div>
        </Card>

        <div className="bk-stack">
          <Card title={`โพสต์วันที่ ${selDay} ${TH_MONTHS[ym.m]}`} emoji="📌" pad={false}>
            {selPosts.length === 0 ? (
              <div className="bk-empty"><span className="bk-empty__emoji">🗓️</span>ยังไม่มีโพสต์ในวันนี้<div style={{ marginTop: 12 }}><button className="bk-btn bk-btn--soft bk-btn--sm"><BKIcon name="plus" size={13} /> ตั้งเวลาโพสต์</button></div></div>
            ) : selPosts.map((p, i) => (
              <div className="bk-row" key={i}>
                <div className="bk-thumb" style={{ width: 42, height: 42, borderRadius: 10, background: p.media, position: "relative" }}>
                  <span style={{ fontSize: "1.2rem" }}>{p.kind === "ไลฟ์" ? "🎬" : "🖼️"}</span>
                  <span className="bk-convo__pf" style={{ background: p.pf.color, width: 17, height: 17 }}><svg viewBox="0 0 24 24" fill="#fff" width="10" height="10"><path fillRule="evenodd" clipRule="evenodd" d={window.BK_BRAND[p.pf.key]} /></svg></span>
                </div>
                <div style={{ minWidth: 0, flex: 1 }}>
                  <div className="bk-row__title bk-truncate">{p.title}</div>
                  <div className="bk-row__sub">{p.kind} · {p.time} น.</div>
                </div>
                <Badge tone={p.status === "done" ? "green" : "amber"} dot>{p.status === "done" ? "โพสต์แล้ว" : "ตั้งเวลาไว้"}</Badge>
              </div>
            ))}
          </Card>

          <Card title="คิวโพสต์ที่จะมาถึง" emoji="⏰" pad={false}>
            {upcoming.length === 0 ? <div className="bk-empty"><span className="bk-empty__emoji">✅</span>ไม่มีคิวโพสต์</div> :
              upcoming.map((p, i) => (
                <div className="bk-row" key={i}>
                  <PlatformIcon pf={p.pf} size={26} tile />
                  <div style={{ minWidth: 0, flex: 1 }}>
                    <div className="bk-row__title bk-truncate">{p.title}</div>
                    <div className="bk-row__sub">{p.day} {TH_MONTHS[ym.m]} · {p.time} น.</div>
                  </div>
                  <span className="bk-badge bk-badge--rose" style={{ fontSize: ".64rem" }}>ดึงจากคลังสื่อ</span>
                </div>
              ))}
          </Card>
        </div>
      </div>
    </div>
  );
}

/* ---------- โปรโมชั่น ---------- */
function Promotions() {
  const [f, setF] = React.useState("all");
  const list = BK_PROMOS.filter((p) => f === "all" || p.status === f);
  const stTH = { active: "กำลังใช้งาน", scheduled: "ตั้งเวลาไว้", expired: "หมดอายุ" };
  const stTone = { active: "green", scheduled: "amber", expired: "neutral" };

  return (
    <div className="bk-page">
      <PageHead title="โปรโมชั่น" sub={`${BK_PROMOS.filter(p=>p.status==="active").length} โปรกำลังใช้งาน · กระตุ้นยอดขายทุกช่องทาง`}>
        <button className="bk-btn bk-btn--primary"><BKIcon name="plus" size={15} /> สร้างโปรโมชั่น</button>
      </PageHead>

      <div style={{ display: "flex", gap: 7, marginBottom: "var(--gap)" }}>
        {[["all", "ทั้งหมด"], ["active", "กำลังใช้งาน"], ["scheduled", "ตั้งเวลาไว้"], ["expired", "หมดอายุ"]].map(([k, l]) => (
          <button key={k} className={"bk-chip" + (f === k ? " is-on" : "")} onClick={() => setF(k)}>{l}</button>
        ))}
      </div>

      <div className="bk-products" style={{ gridTemplateColumns: "repeat(auto-fill, minmax(280px, 1fr))" }}>
        {list.map((p) => (
          <div className="bk-promo" key={p.id} style={{ opacity: p.status === "expired" ? 0.62 : 1 }}>
            <div style={{ display: "flex", justifyContent: "space-between", alignItems: "flex-start", marginBottom: 10 }}>
              <span className="bk-badge" style={{ background: "var(--t-" + p.tone + "-bg)", color: "var(--t-" + p.tone + ")" }}>{p.type}</span>
              <Badge tone={stTone[p.status]} dot>{stTH[p.status]}</Badge>
            </div>
            <div style={{ fontWeight: 700, fontSize: ".96rem", marginBottom: 2 }}>{p.name}</div>
            <div className="bk-num" style={{ fontSize: "1.8rem", fontWeight: 800, color: "var(--t-" + p.tone + ")", letterSpacing: "-.02em" }}>{p.value}</div>
            <div style={{ display: "flex", alignItems: "center", gap: 8, margin: "10px 0" }}>
              <span className="bk-promo__code bk-num">{p.code}</span>
              <button className="bk-iconbtn" style={{ width: 30, height: 30 }} title="คัดลอกโค้ด"><BKIcon name="edit" size={13} /></button>
            </div>
            <div style={{ display: "flex", justifyContent: "space-between", alignItems: "center", borderTop: "1px solid var(--border)", paddingTop: 10 }}>
              <span className="bk-row__sub"><BKIcon name="calendar" size={12} /> {p.from} – {p.to}</span>
              <span className="bk-row__sub bk-num">ใช้แล้ว {p.used}</span>
            </div>
          </div>
        ))}
      </div>
    </div>
  );
}

Object.assign(window, { Media, PostCalendar, Promotions });
