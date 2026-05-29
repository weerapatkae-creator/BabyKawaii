/* ===== BabyKawaii — Login / Customers / Platforms / Settings ===== */

function Login({ go, theme, toggleTheme }) {
  return (
    <div style={{ minHeight: "100vh", display: "flex", alignItems: "center", justifyContent: "center", padding: 24, position: "relative", background: "radial-gradient(1200px 600px at 80% -10%, var(--accent-soft), transparent), var(--bg)" }}>
      <button className="bk-iconbtn" onClick={toggleTheme} style={{ position: "absolute", top: 20, right: 20 }}>
        <BKIcon name={theme === "dark" ? "sun" : "moon"} size={17} />
      </button>
      <div style={{ width: "100%", maxWidth: 380 }}>
        <div style={{ textAlign: "center", marginBottom: 22 }}>
          <div style={{ display: "inline-flex", padding: 14, borderRadius: 22, background: "var(--card)", boxShadow: "var(--shadow)", marginBottom: 14 }}>
            <BKBrandMark size={40} />
          </div>
          <div style={{ fontWeight: 800, fontSize: "1.5rem", letterSpacing: "-.02em" }}>Baby<span style={{ color: "var(--accent-strong)" }}>kawaii</span></div>
          <div className="bk-muted" style={{ fontSize: ".88rem", marginTop: 2 }}>ระบบจัดการร้านเสื้อผ้าเด็กแรกเกิด</div>
        </div>
        <Card>
          <div style={{ fontWeight: 700, fontSize: "1.05rem", marginBottom: 4 }}>ยินดีต้อนรับกลับมา 🌸</div>
          <div className="bk-muted" style={{ fontSize: ".84rem", marginBottom: 18 }}>เข้าสู่ระบบเพื่อจัดการร้านของคุณ</div>
          <div className="bk-field">
            <label className="bk-label">ชื่อผู้ใช้</label>
            <input className="bk-input" defaultValue="admin" />
          </div>
          <div className="bk-field">
            <label className="bk-label">รหัสผ่าน</label>
            <input className="bk-input" type="password" defaultValue="········" />
          </div>
          <div style={{ display: "flex", justifyContent: "space-between", alignItems: "center", margin: "4px 0 18px" }}>
            <label style={{ display: "flex", alignItems: "center", gap: 7, fontSize: ".82rem", cursor: "pointer" }}>
              <input type="checkbox" defaultChecked style={{ accentColor: "var(--accent)" }} /> จดจำฉันไว้
            </label>
            <a style={{ fontSize: ".82rem", color: "var(--accent-strong)", textDecoration: "none", cursor: "pointer" }}>ลืมรหัสผ่าน?</a>
          </div>
          <button className="bk-btn bk-btn--primary" style={{ width: "100%", justifyContent: "center", padding: "11px" }} onClick={() => go("dashboard")}>
            เข้าสู่ระบบ <BKIcon name="chevronRight" size={15} />
          </button>
        </Card>
        <div className="bk-muted" style={{ textAlign: "center", fontSize: ".76rem", marginTop: 16 }}>BabyKawaii Shop · จัดการขายหลายช่องทางในที่เดียว</div>
      </div>
    </div>
  );
}

function Customers() {
  const d = window.BK_DATA;
  const totalSpent = d.customerList.reduce((s, c) => s + c.spent, 0);
  return (
    <div className="bk-page">
      <PageHead title="ฐานข้อมูลลูกค้า" sub={`${d.customerList.length} ลูกค้า · VIP ${d.customerList.filter(c=>c.tier==="VIP").length} คน`}>
        <button className="bk-btn bk-btn--ghost"><BKIcon name="download" size={15} /> ส่งออก</button>
        <button className="bk-btn bk-btn--primary"><BKIcon name="plus" size={15} /> เพิ่มลูกค้า</button>
      </PageHead>

      <div className="bk-stats" style={{ gridTemplateColumns: "repeat(3,1fr)", marginBottom: "var(--gap)" }}>
        {[
          { icon: "users", label: "ลูกค้าทั้งหมด", value: fmtNum(d.customerList.length) + " คน" },
          { icon: "heart", label: "ลูกค้า VIP", value: d.customerList.filter(c=>c.tier==="VIP").length + " คน" },
          { icon: "coins", label: "ยอดซื้อสะสมรวม", value: fmt(totalSpent) },
        ].map((s, i) => (
          <div className="bk-stat" key={i}>
            <div className="bk-stat__top"><div className="bk-stat__icon"><BKIcon name={s.icon} size={16} /></div></div>
            <div className="bk-stat__label" style={{ marginTop: 10 }}>{s.label}</div>
            <div className="bk-stat__value bk-num" style={{ fontSize: "1.4rem" }}>{s.value}</div>
          </div>
        ))}
      </div>

      <Card pad={false}>
        <div style={{ overflowX: "auto" }}>
          <table className="bk-table">
            <thead><tr><th>ลูกค้า</th><th>ระดับ</th><th>ช่องทางหลัก</th><th className="bk-th-r">ออเดอร์</th><th className="bk-th-r">ยอดซื้อสะสม</th><th>ซื้อล่าสุด</th></tr></thead>
            <tbody>
              {d.customerList.map((c) => (
                <tr key={c.id}>
                  <td>
                    <div style={{ display: "flex", alignItems: "center", gap: 11 }}>
                      <div className="bk-avatar" style={{ width: 36, height: 36, background: "var(--accent-soft)", color: "var(--accent-strong)" }}>{c.name.replace("คุณ", "")[0]}</div>
                      <div className="bk-row__title">{c.name}</div>
                    </div>
                  </td>
                  <td><Badge tone={STATUS_TONE[c.tier]} dot>{c.tier}</Badge></td>
                  <td><PlatformIcon pf={c.platform} size={22} tile /> <span className="bk-muted" style={{ fontSize: ".8rem", verticalAlign: "middle" }}>{c.platform.name}</span></td>
                  <td className="bk-num bk-td-r">{c.orders}</td>
                  <td className="bk-num bk-td-r" style={{ fontWeight: 700, color: "var(--accent-strong)" }}>{fmt(c.spent)}</td>
                  <td className="bk-muted bk-num" style={{ fontSize: ".8rem" }}>{c.last}</td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      </Card>
    </div>
  );
}

function Platforms() {
  const d = window.BK_DATA;
  const connected = { tiktok: true, facebook: true, line: true, instagram: false, walkin: true };
  return (
    <div className="bk-page">
      <PageHead title="แพลตฟอร์มขาย" sub="เชื่อมต่อและดูผลการขายแต่ละช่องทางในที่เดียว">
        <button className="bk-btn bk-btn--primary"><BKIcon name="plus" size={15} /> เพิ่มช่องทาง</button>
      </PageHead>

      <div className="bk-products" style={{ gridTemplateColumns: "repeat(auto-fill, minmax(250px, 1fr))" }}>
        {d.platforms.map((p) => (
          <Card key={p.key}>
            <div style={{ display: "flex", alignItems: "center", gap: 12, marginBottom: 16 }}>
              <div style={{ width: 46, height: 46, borderRadius: 13, background: p.color, color: "#fff", display: "flex", alignItems: "center", justifyContent: "center" }}>
                <svg width="24" height="24" viewBox="0 0 24 24" fill="#fff"><path fillRule="evenodd" clipRule="evenodd" d={window.BK_BRAND[p.key]} /></svg>
              </div>
              <div style={{ flex: 1 }}>
                <div style={{ fontWeight: 700 }}>{p.name}</div>
                {connected[p.key]
                  ? <Badge tone="green" dot>เชื่อมต่อแล้ว</Badge>
                  : <Badge tone="neutral" dot>ยังไม่เชื่อมต่อ</Badge>}
              </div>
            </div>
            <div style={{ display: "flex", gap: 20, marginBottom: 16 }}>
              <div>
                <div className="bk-row__sub">ยอดขายเดือนนี้</div>
                <div className="bk-num" style={{ fontWeight: 800, fontSize: "1.25rem" }}>{fmt(p.total)}</div>
              </div>
              <div>
                <div className="bk-row__sub">ออเดอร์</div>
                <div className="bk-num" style={{ fontWeight: 800, fontSize: "1.25rem" }}>{p.orders}</div>
              </div>
            </div>
            <div style={{ marginBottom: 14 }}><Bar value={p.share} color={p.color} /></div>
            <button className={"bk-btn " + (connected[p.key] ? "bk-btn--ghost" : "bk-btn--primary")} style={{ width: "100%", justifyContent: "center" }}>
              {connected[p.key] ? <><BKIcon name="settings" size={14} /> ตั้งค่า</> : <><BKIcon name="plug" size={14} /> เชื่อมต่อ</>}
            </button>
          </Card>
        ))}
      </div>
    </div>
  );
}

function Settings({ theme, toggleTheme }) {
  const [notif, setNotif] = React.useState({ low: true, order: true, daily: false, review: true });
  const Toggle = ({ on, onClick }) => (
    <button onClick={onClick} style={{ width: 42, height: 24, borderRadius: 20, border: "none", cursor: "pointer", background: on ? "var(--accent)" : "var(--border-2)", position: "relative", transition: "background .2s", flexShrink: 0 }}>
      <span style={{ position: "absolute", top: 3, left: on ? 21 : 3, width: 18, height: 18, borderRadius: "50%", background: "#fff", transition: "left .2s", boxShadow: "0 1px 3px rgba(0,0,0,.2)" }} />
    </button>
  );
  const rows = [["low", "แจ้งเตือนสต็อกใกล้หมด", "ส่งแจ้งเตือนเมื่อสินค้าเหลือน้อยกว่าที่กำหนด"], ["order", "ออเดอร์ใหม่", "แจ้งทันทีเมื่อมีออเดอร์เข้ามาจากทุกช่องทาง"], ["daily", "สรุปยอดขายรายวัน", "ส่งสรุปยอดขายทุกเย็นเวลา 20:00 น."], ["review", "รีวิวจากลูกค้า", "แจ้งเตือนเมื่อมีรีวิวหรือข้อความใหม่"]];

  return (
    <div className="bk-page">
      <PageHead title="ตั้งค่าร้าน" sub="จัดการข้อมูลร้าน การแจ้งเตือน และการแสดงผล">
        <button className="bk-btn bk-btn--primary"><BKIcon name="check" size={15} /> บันทึกการตั้งค่า</button>
      </PageHead>

      <div className="bk-grid-2" style={{ alignItems: "start" }}>
        <div className="bk-stack">
          <Card title="ข้อมูลร้านค้า" emoji="🏪">
            <div className="bk-field">
              <label className="bk-label">ชื่อร้าน</label>
              <input className="bk-input" defaultValue="BabyKawaii — เสื้อผ้าเด็กแรกเกิด" />
            </div>
            <div style={{ display: "grid", gridTemplateColumns: "1fr 1fr", gap: 14 }}>
              <div className="bk-field"><label className="bk-label">เบอร์โทร</label><input className="bk-input bk-num" defaultValue="08x-xxx-xxxx" /></div>
              <div className="bk-field"><label className="bk-label">LINE ID</label><input className="bk-input" defaultValue="@babykawaii" /></div>
            </div>
            <div className="bk-field" style={{ marginBottom: 0 }}>
              <label className="bk-label">ที่อยู่จัดส่ง</label>
              <textarea className="bk-textarea" rows={2} defaultValue="123 ถนนสุขุมวิท แขวงคลองเตย เขตคลองเตย กรุงเทพฯ 10110" />
            </div>
          </Card>

          <Card title="การแจ้งเตือน" emoji="🔔">
            {rows.map(([key, title, sub], i) => (
              <div key={key} className="bk-row" style={{ padding: "12px 0", borderBottom: i < rows.length - 1 ? "1px solid var(--border)" : "none" }}>
                <div style={{ flex: 1 }}>
                  <div className="bk-row__title">{title}</div>
                  <div className="bk-row__sub">{sub}</div>
                </div>
                <Toggle on={notif[key]} onClick={() => setNotif((n) => ({ ...n, [key]: !n[key] }))} />
              </div>
            ))}
          </Card>
        </div>

        <div className="bk-stack">
          <Card title="การแสดงผล" emoji="🎨">
            <div className="bk-row" style={{ padding: "4px 0 14px", borderBottom: "1px solid var(--border)" }}>
              <div style={{ flex: 1 }}>
                <div className="bk-row__title">โหมดมืด</div>
                <div className="bk-row__sub">สลับธีมสว่าง / มืด</div>
              </div>
              <Toggle on={theme === "dark"} onClick={toggleTheme} />
            </div>
            <div className="bk-muted" style={{ fontSize: ".82rem", marginTop: 14 }}>
              💡 ปรับธีมสี เลย์เอาต์ และความหนาแน่นของข้อมูลเพิ่มเติมได้จากแผง <b style={{ color: "var(--accent-strong)" }}>Tweaks</b> มุมขวาบน
            </div>
          </Card>

          <Card title="ค่าจัดส่ง" emoji="📮">
            <div style={{ display: "grid", gridTemplateColumns: "1fr 1fr", gap: 14 }}>
              <div className="bk-field" style={{ marginBottom: 0 }}><label className="bk-label">ค่าส่งมาตรฐาน (฿)</label><input className="bk-input bk-num" defaultValue="40" /></div>
              <div className="bk-field" style={{ marginBottom: 0 }}><label className="bk-label">ส่งฟรีเมื่อซื้อครบ (฿)</label><input className="bk-input bk-num" defaultValue="500" /></div>
            </div>
          </Card>
        </div>
      </div>
    </div>
  );
}

function Users() {
  const d = window.BK_DATA;
  const sellers = d.team.filter((t) => t.closeRate != null);
  const best = sellers.reduce((a, b) => (b.closeRate > a.closeRate ? b : a), sellers[0]);
  const roleTone = { owner: "rose", admin: "blue", staff: "amber" };
  const stTone = { online: "green", away: "amber", offline: "neutral" };
  const stTH = { online: "ออนไลน์", away: "ไม่อยู่", offline: "ออฟไลน์" };

  return (
    <div className="bk-page">
      <PageHead title="จัดการผู้ใช้งาน" sub={`${d.team.length} บัญชี · ${d.team.filter(t=>t.status==="online").length} คนออนไลน์`}>
        <button className="bk-btn bk-btn--ghost"><BKIcon name="settings" size={15} /> ตั้งค่าสิทธิ์</button>
        <button className="bk-btn bk-btn--primary"><BKIcon name="plus" size={15} /> เพิ่มผู้ใช้</button>
      </PageHead>

      <div className="bk-grid-2" style={{ marginBottom: "var(--gap)", alignItems: "stretch" }}>
        <div className="bk-stats" style={{ gridTemplateColumns: "repeat(3,1fr)" }}>
          {[
            { icon: "users", label: "ผู้ใช้งานทั้งหมด", value: d.team.length + " คน" },
            { icon: "chat", label: "พนักงานขาย", value: sellers.length + " คน" },
            { icon: "check", label: "ออนไลน์ตอนนี้", value: d.team.filter(t=>t.status==="online").length + " คน" },
          ].map((s, i) => (
            <div className="bk-stat" key={i}>
              <div className="bk-stat__top"><div className="bk-stat__icon"><BKIcon name={s.icon} size={16} /></div></div>
              <div className="bk-stat__label" style={{ marginTop: 10 }}>{s.label}</div>
              <div className="bk-stat__value bk-num" style={{ fontSize: "1.4rem" }}>{s.value}</div>
            </div>
          ))}
        </div>
        <Card style={{ background: "linear-gradient(135deg, var(--accent-soft), var(--card))" }}>
          <div style={{ display: "flex", alignItems: "center", gap: 14 }}>
            <div className="bk-avatar" style={{ width: 52, height: 52, fontSize: "1.2rem" }}>{best.initial}</div>
            <div style={{ flex: 1 }}>
              <div className="bk-row__sub" style={{ color: "var(--accent-strong)", fontWeight: 700 }}>🏆 ปิดการขายเก่งสุดเดือนนี้</div>
              <div style={{ fontWeight: 800, fontSize: "1.1rem" }}>{best.name}</div>
              <div className="bk-row__sub">{best.role} · ตอบเฉลี่ย {best.replyTime}</div>
            </div>
            <div style={{ textAlign: "right" }}>
              <div className="bk-num" style={{ fontSize: "2rem", fontWeight: 800, color: "var(--accent-strong)", lineHeight: 1 }}>{best.closeRate}%</div>
              <div className="bk-row__sub">อัตราปิดการขาย</div>
            </div>
          </div>
        </Card>
      </div>

      <Card pad={false} title="รายชื่อผู้ใช้งาน" emoji="👥">
        <div style={{ overflowX: "auto" }}>
          <table className="bk-table">
            <thead><tr><th>ผู้ใช้งาน</th><th>บทบาท</th><th>สถานะ</th><th style={{ minWidth: 130 }}>อัตราปิดการขาย</th><th className="bk-th-r">ออเดอร์</th><th className="bk-th-r">ยอดขาย</th><th>ตอบเฉลี่ย</th><th></th></tr></thead>
            <tbody>
              {d.team.map((t) => (
                <tr key={t.id}>
                  <td>
                    <div style={{ display: "flex", alignItems: "center", gap: 11 }}>
                      <div style={{ position: "relative" }}>
                        <div className="bk-avatar" style={{ width: 38, height: 38, background: t.tint, color: "var(--text)" }}>{t.initial}</div>
                        <span style={{ position: "absolute", right: -1, bottom: -1, width: 11, height: 11, borderRadius: "50%", border: "2px solid var(--card)", background: t.status === "online" ? "var(--t-green)" : t.status === "away" ? "var(--t-amber)" : "var(--muted)" }} />
                      </div>
                      <div>
                        <div className="bk-row__title">{t.name}</div>
                        <div className="bk-row__sub">{t.last}</div>
                      </div>
                    </div>
                  </td>
                  <td><Badge tone={roleTone[t.roleKey]}>{t.role}</Badge></td>
                  <td><Badge tone={stTone[t.status]} dot>{stTH[t.status]}</Badge></td>
                  <td>
                    {t.closeRate != null ? (
                      <div style={{ display: "flex", alignItems: "center", gap: 9 }}>
                        <div style={{ flex: 1, minWidth: 60 }}><Bar value={t.closeRate} color={t.closeRate >= 70 ? "var(--t-green)" : "var(--accent)"} /></div>
                        <span className="bk-num" style={{ fontWeight: 700, fontSize: ".82rem" }}>{t.closeRate}%</span>
                      </div>
                    ) : <span className="bk-muted">—</span>}
                  </td>
                  <td className="bk-num bk-td-r">{t.orders != null ? fmtNum(t.orders) : "—"}</td>
                  <td className="bk-num bk-td-r" style={{ fontWeight: 700 }}>{t.sales != null ? fmt(t.sales) : "—"}</td>
                  <td className="bk-muted">{t.replyTime}</td>
                  <td className="bk-td-r"><button className="bk-iconbtn" style={{ width: 30, height: 30, display: "inline-flex" }}><BKIcon name="dots" size={15} /></button></td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      </Card>
    </div>
  );
}

Object.assign(window, { Login, Customers, Platforms, Settings, Users });
