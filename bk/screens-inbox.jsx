/* ===== BabyKawaii — Inbox (รวมแชตทุกช่องทาง) ===== */

const BK_CONVOS = (() => {
  const pf = window.BK_DATA.platforms.reduce((m, p) => { m[p.key] = p; return m; }, {});
  return [
    {
      id: 1, name: "คุณนภัสสร", platform: pf.tiktok, unread: 2, time: "10:42", online: true,
      preview: "บอดี้สูทลายกระต่าย ไซต์ 3-6M มีสีอะไรบ้างคะ",
      tint: "#FBE9EE",
      msgs: [
        { from: "them", text: "สวัสดีค่ะ สนใจบอดี้สูทลายกระต่ายค่ะ", time: "10:30" },
        { from: "me", text: "สวัสดีค่ะ 🌸 มีพร้อมส่งเลยนะคะ", time: "10:34" },
        { from: "them", text: "บอดี้สูทลายกระต่าย ไซต์ 3-6M มีสีอะไรบ้างคะ", time: "10:41" },
        { from: "them", text: "น้องน้ำหนัก 6 โลพอดีไหมคะ", time: "10:42" },
      ],
    },
    {
      id: 2, name: "คุณจิรายุ", platform: pf.facebook, unread: 0, time: "09:58", online: false,
      preview: "ได้รับของแล้วนะครับ น่ารักมากเลย ขอบคุณครับ",
      tint: "#E8EEF8",
      msgs: [
        { from: "them", text: "ของส่งถึงแล้วครับ", time: "09:55" },
        { from: "them", text: "ได้รับของแล้วนะครับ น่ารักมากเลย ขอบคุณครับ", time: "09:58" },
        { from: "me", text: "ขอบคุณมากเลยค่ะ 💕 ฝากรีวิวด้วยนะคะ", time: "09:59" },
      ],
    },
    {
      id: 3, name: "คุณพิมพ์มาดา", platform: pf.line, unread: 1, time: "เมื่อวาน", online: false,
      preview: "ชุดเซตหมีน้อยยังมีของไหมคะ อยากได้ 2 เซต",
      tint: "#E6F2EC",
      msgs: [
        { from: "them", text: "ชุดเซตหมีน้อยยังมีของไหมคะ อยากได้ 2 เซต", time: "เมื่อวาน 18:20" },
      ],
    },
    {
      id: 4, name: "คุณศุภางค์", platform: pf.instagram, unread: 0, time: "เมื่อวาน", online: false,
      preview: "โอนแล้วนะคะ รบกวนเช็กยอดด้วยค่า",
      tint: "#F3EEF6",
      msgs: [
        { from: "them", text: "โอนแล้วนะคะ รบกวนเช็กยอดด้วยค่า", time: "เมื่อวาน 14:02" },
        { from: "me", text: "ได้รับยอดเรียบร้อยค่ะ จัดส่งพรุ่งนี้เลยนะคะ 📦", time: "เมื่อวาน 14:10" },
      ],
    },
    {
      id: 5, name: "คุณกัญญาณัฐ", platform: pf.tiktok, unread: 0, time: "2 วันก่อน", online: false,
      preview: "ขอบคุณค่ะ เดี๋ยวสั่งเพิ่มนะคะ",
      tint: "#F8EFDD",
      msgs: [{ from: "them", text: "ขอบคุณค่ะ เดี๋ยวสั่งเพิ่มนะคะ", time: "2 วันก่อน" }],
    },
  ];
})();

const BK_QUICK = ["สวัสดีค่ะ 🌸 ยินดีให้บริการนะคะ", "มีพร้อมส่งเลยค่ะ", "ขอบคุณที่อุดหนุนนะคะ 💕", "รบกวนแจ้งไซต์และสีที่ต้องการค่ะ"];

/* คลังคำตอบ — เรียนรู้ว่าประโยคไหนปิดการขายดี + แอดมินคนไหนใช้ */
const BK_REPLIES_INIT = [
  { id: 1, text: "รับน้องไปเลยไหมคะ ไซต์นี้เหลือตัวสุดท้ายแล้วนะคะ 🌸", closeRate: 74, adminId: 2, uses: 312 },
  { id: 2, text: "พร้อมส่งเลยค่ะ โอนแล้วแพ็คส่งภายในวันนี้นะคะ 📦", closeRate: 71, adminId: 1, uses: 280 },
  { id: 3, text: "วันนี้มีโปรส่งฟรีเมื่อครบ 500.- พอดีเลยค่ะ คุ้มมากนะคะ", closeRate: 68, adminId: 3, uses: 195 },
  { id: 4, text: "เนื้อผ้านุ่มมากค่ะ ใส่สบาย ไม่ระคายผิวน้อง 💕", closeRate: 65, adminId: 2, uses: 164 },
  { id: 5, text: "ทักมาเดี๋ยวจัดส่วนลดพิเศษให้นะคะ 🎁", closeRate: 61, adminId: 4, uses: 98 },
];
const bkAdmin = (id) => (window.BK_DATA.team.find((t) => t.id === id) || { name: "—", initial: "?" });

/* ---------- Tool panels ---------- */
function toolStockTone(q) { return q === 0 ? "out" : q <= 5 ? "low" : "ok"; }

function StockTool() {
  const d = window.BK_DATA;
  const [q, setQ] = React.useState("");
  const list = d.products.filter((p) => p.name.includes(q)).slice(0, 5);
  return (
    <div>
      <div className="bk-search" style={{ maxWidth: "none", background: "var(--bg-2)", border: "1px solid transparent", marginBottom: 12 }}>
        <BKIcon name="search" size={15} />
        <input placeholder="ค้นหาสินค้าเพื่อเช็กสต็อก…" value={q} onChange={(e) => setQ(e.target.value)} />
      </div>
      <div className="bk-stack" style={{ ["--gap"]: "10px" }}>
        {list.map((p) => {
          const sizeStock = d.sizes.slice(0, 5).map((s, i) => ({ s, q: Math.max(0, (p.stock >> i) % 22) }));
          return (
            <div key={p.id} style={{ display: "flex", gap: 11, alignItems: "center" }}>
              <ProductThumb tint={p.tint} emoji="👶" size={38} />
              <div style={{ minWidth: 0, flex: 1 }}>
                <div className="bk-row__title bk-truncate">{p.name}</div>
                <div className="bk-sizes" style={{ marginTop: 4 }}>
                  {sizeStock.map((ss) => <span key={ss.s} className={"bk-size bk-size--" + toolStockTone(ss.q)} style={{ padding: "2px 8px", fontSize: ".7rem" }}>{ss.s}·{ss.q}</span>)}
                </div>
              </div>
            </div>
          );
        })}
      </div>
    </div>
  );
}

function ShipTool({ convo }) {
  const steps = [
    { k: "รับออเดอร์", done: true }, { k: "ยืนยันแล้ว", done: true },
    { k: "กำลังแพ็ค", done: true }, { k: "จัดส่งแล้ว", done: true, now: true }, { k: "ส่งถึงแล้ว", done: false },
  ];
  return (
    <div>
      <div style={{ display: "flex", justifyContent: "space-between", alignItems: "center", background: "var(--bg-2)", borderRadius: "var(--radius-sm)", padding: "11px 14px", marginBottom: 14 }}>
        <div>
          <div className="bk-row__sub">เลขพัสดุ · Kerry Express</div>
          <div className="bk-num" style={{ fontWeight: 700, letterSpacing: ".02em" }}>TH27 0561 8842 K</div>
        </div>
        <Badge tone="mint" dot>กำลังจัดส่ง</Badge>
      </div>
      <div style={{ position: "relative", paddingLeft: 8 }}>
        {steps.map((st, i) => (
          <div key={i} style={{ display: "flex", gap: 12, alignItems: "flex-start", paddingBottom: i < steps.length - 1 ? 16 : 0, position: "relative" }}>
            {i < steps.length - 1 && <span style={{ position: "absolute", left: 6, top: 16, bottom: 0, width: 2, background: st.done ? "var(--accent)" : "var(--border)" }} />}
            <span style={{ width: 14, height: 14, borderRadius: "50%", marginTop: 2, flexShrink: 0, background: st.done ? "var(--accent)" : "var(--card)", border: "2px solid " + (st.done ? "var(--accent)" : "var(--border-2)"), boxShadow: st.now ? "0 0 0 4px var(--accent-soft)" : "none" }} />
            <div>
              <div style={{ fontWeight: st.now ? 700 : 500, fontSize: ".86rem", color: st.done ? "var(--text)" : "var(--muted)" }}>{st.k}</div>
              {st.now && <div className="bk-row__sub">วันนี้ 11:20 · ศูนย์คัดแยกพัสดุ</div>}
            </div>
          </div>
        ))}
      </div>
    </div>
  );
}

function OrderTool({ onCreate, onDone }) {
  const d = window.BK_DATA;
  const [pi, setPi] = React.useState(0);
  const [size, setSize] = React.useState("0-3M");
  const [qty, setQty] = React.useState(1);
  const p = d.products[pi];
  const total = p.price * qty;
  return (
    <div>
      <div className="bk-field">
        <label className="bk-label">เลือกสินค้า</label>
        <div style={{ display: "flex", gap: 7, flexWrap: "wrap" }}>
          {d.products.slice(0, 5).map((x, i) => (
            <button key={x.id} className={"bk-chip" + (pi === i ? " is-on" : "")} onClick={() => setPi(i)}>{x.name.length > 16 ? x.name.slice(0, 15) + "…" : x.name}</button>
          ))}
        </div>
      </div>
      <div style={{ display: "grid", gridTemplateColumns: "1fr auto", gap: 14, alignItems: "end" }}>
        <div className="bk-field" style={{ marginBottom: 0 }}>
          <label className="bk-label">ไซต์</label>
          <div style={{ display: "flex", gap: 6, flexWrap: "wrap" }}>
            {d.sizes.slice(1, 6).map((s) => <button key={s} className={"bk-chip" + (size === s ? " is-on" : "")} onClick={() => setSize(s)} style={{ padding: "5px 11px" }}>{s}</button>)}
          </div>
        </div>
        <div className="bk-field" style={{ marginBottom: 0 }}>
          <label className="bk-label">จำนวน</label>
          <div style={{ display: "flex", alignItems: "center", gap: 8 }}>
            <button className="bk-iconbtn" style={{ width: 32, height: 32 }} onClick={() => setQty((q) => Math.max(1, q - 1))}><BKIcon name="x" size={13} /></button>
            <span className="bk-num" style={{ fontWeight: 700, minWidth: 22, textAlign: "center" }}>{qty}</span>
            <button className="bk-iconbtn" style={{ width: 32, height: 32 }} onClick={() => setQty((q) => q + 1)}><BKIcon name="plus" size={13} /></button>
          </div>
        </div>
      </div>
      <div style={{ display: "flex", alignItems: "center", justifyContent: "space-between", marginTop: 16, padding: "12px 14px", borderRadius: "var(--radius-sm)", background: "var(--accent-soft)" }}>
        <span style={{ color: "var(--accent-strong)", fontWeight: 600 }}>ยอดรวม</span>
        <span className="bk-num" style={{ fontWeight: 800, fontSize: "1.3rem", color: "var(--accent-strong)" }}>{fmt(total)}</span>
      </div>
      <button className="bk-btn bk-btn--primary" style={{ width: "100%", justifyContent: "center", marginTop: 12 }} onClick={() => {
        const order = onCreate ? onCreate({ product: p, size, qty, total }) : null;
        onDone && onDone(order);
      }}>
        <BKIcon name="check" size={15} /> สร้างคำสั่งซื้อ
      </button>
    </div>
  );
}

function RepliesTool({ replies, setReplies, onUse }) {
  const sorted = [...replies].sort((a, b) => b.closeRate - a.closeRate);
  const best = sorted[0];
  const [editId, setEditId] = React.useState(null);
  const [tmp, setTmp] = React.useState("");
  const [adding, setAdding] = React.useState("");

  const saveEdit = (id) => { setReplies((r) => r.map((x) => x.id === id ? { ...x, text: tmp } : x)); setEditId(null); };
  const addNew = () => {
    if (!adding.trim()) return;
    setReplies((r) => [...r, { id: Date.now(), text: adding.trim(), closeRate: 0, adminId: 1, uses: 0, isNew: true }]);
    setAdding("");
  };

  return (
    <div>
      <div style={{ display: "flex", gap: 10, alignItems: "flex-start", background: "var(--accent-soft)", borderRadius: "var(--radius-sm)", padding: "12px 14px", marginBottom: 14 }}>
        <BKIcon name="sparkles" size={18} style={{ color: "var(--accent-strong)", marginTop: 2 }} />
        <div style={{ fontSize: ".83rem", color: "var(--accent-strong)", lineHeight: 1.5 }}>
          ระบบเรียนรู้จากแชตจริง — ประโยคปิดการขายดีที่สุดตอนนี้คือ <b>“{best.text.slice(0, 22)}…”</b> โดย <b>{bkAdmin(best.adminId).name}</b> ปิดได้ <b className="bk-num">{best.closeRate}%</b>
        </div>
      </div>

      <div className="bk-stack" style={{ ["--gap"]: "10px" }}>
        {sorted.map((r, i) => {
          const ad = bkAdmin(r.adminId);
          return (
            <div key={r.id} style={{ border: "1px solid var(--border)", borderRadius: "var(--radius-sm)", padding: "11px 13px", background: i === 0 && !r.isNew ? "var(--card-2)" : "var(--card)" }}>
              <div style={{ display: "flex", alignItems: "center", gap: 8, marginBottom: 7 }}>
                {i === 0 && !r.isNew && <Badge tone="green" dot>ปิดดีที่สุด</Badge>}
                {r.isNew && <Badge tone="amber" dot>ใหม่ · กำลังเก็บสถิติ</Badge>}
                <span className="bk-row__sub" style={{ marginLeft: "auto", display: "flex", alignItems: "center", gap: 6 }}>
                  <span className="bk-avatar" style={{ width: 20, height: 20, fontSize: ".62rem", background: ad.tint, color: "var(--text)" }}>{ad.initial}</span>
                  {ad.name.split(" ")[0]} · ใช้ {fmtNum(r.uses)} ครั้ง
                </span>
              </div>
              {editId === r.id ? (
                <div style={{ display: "flex", gap: 7 }}>
                  <input className="bk-input" value={tmp} onChange={(e) => setTmp(e.target.value)} autoFocus />
                  <button className="bk-btn bk-btn--primary bk-btn--sm" onClick={() => saveEdit(r.id)}><BKIcon name="check" size={14} /></button>
                </div>
              ) : (
                <div style={{ fontSize: ".88rem", marginBottom: 9 }}>{r.text}</div>
              )}
              {editId !== r.id && (
                <div style={{ display: "flex", alignItems: "center", gap: 10 }}>
                  <div style={{ flex: 1, display: "flex", alignItems: "center", gap: 8 }}>
                    <div style={{ flex: 1, maxWidth: 120 }}><Bar value={r.closeRate} color={r.closeRate >= 70 ? "var(--t-green)" : "var(--accent)"} /></div>
                    <span className="bk-num bk-row__sub">{r.closeRate ? "ปิด " + r.closeRate + "%" : "ยังไม่มีข้อมูล"}</span>
                  </div>
                  <button className="bk-iconbtn" style={{ width: 30, height: 30 }} onClick={() => { setEditId(r.id); setTmp(r.text); }}><BKIcon name="edit" size={13} /></button>
                  <button className="bk-btn bk-btn--soft bk-btn--sm" onClick={() => onUse(r.text)}>ใช้</button>
                </div>
              )}
            </div>
          );
        })}
      </div>

      <div style={{ display: "flex", gap: 7, marginTop: 14 }}>
        <input className="bk-input" placeholder="เพิ่มคำตอบใหม่ที่อยากให้แอดมินใช้…" value={adding} onChange={(e) => setAdding(e.target.value)} onKeyDown={(e) => { if (e.key === "Enter") addNew(); }} />
        <button className="bk-btn bk-btn--primary bk-btn--sm" onClick={addNew}><BKIcon name="plus" size={14} /> เพิ่ม</button>
      </div>
    </div>
  );
}

function Inbox({ go, onCreateOrder }) {
  const [active, setActive] = React.useState(BK_CONVOS[0].id);
  const [draft, setDraft] = React.useState("");
  const [openThread, setOpenThread] = React.useState(false);
  const [tool, setTool] = React.useState(null);
  const [replies, setReplies] = React.useState(BK_REPLIES_INIT);
  const convo = BK_CONVOS.find((c) => c.id === active);
  const totalUnread = BK_CONVOS.reduce((s, c) => s + c.unread, 0);

  const openConvo = (id) => { setActive(id); setOpenThread(true); setTool(null); };
  const TOOLS = [
    { k: "replies", label: "คลังคำตอบ", icon: "sparkles" },
    { k: "stock", label: "เช็คสต็อก", icon: "box" },
    { k: "ship", label: "เช็คการจัดส่ง", icon: "truck" },
    { k: "order", label: "สร้างคำสั่งซื้อ", icon: "bag" },
  ];
  const toolTitle = { replies: "คลังคำตอบอัจฉริยะ", stock: "เช็คสต็อกด่วน", ship: "ติดตามการจัดส่ง", order: "สร้างคำสั่งซื้อ" };
  const topReplies = [...replies].sort((a, b) => b.closeRate - a.closeRate).slice(0, 4);

  return (
    <div className="bk-page" style={{ paddingBottom: 0 }}>
      <PageHead title="Inbox ข้อความ" sub={`รวมแชตจากทุกช่องทาง · ยังไม่ได้อ่าน ${totalUnread} ข้อความ`}>
        <button className="bk-btn bk-btn--ghost"><BKIcon name="filter" size={15} /> กรองช่องทาง</button>
      </PageHead>

      <div className={"bk-inbox" + (openThread ? " is-thread" : "")}>
        {/* conversation list */}
        <div className="bk-inbox__list">
          <div className="bk-inbox__search">
            <div className="bk-search" style={{ maxWidth: "none", background: "var(--bg-2)", border: "1px solid transparent" }}>
              <BKIcon name="search" size={15} />
              <input placeholder="ค้นหาลูกค้า…" />
            </div>
          </div>
          <div className="bk-inbox__convos">
            {BK_CONVOS.map((c) => (
              <button key={c.id} className={"bk-convo" + (c.id === active ? " is-active" : "")} onClick={() => openConvo(c.id)}>
                <div style={{ position: "relative" }}>
                  <div className="bk-avatar" style={{ width: 42, height: 42, background: c.tint, color: "var(--text)" }}>{c.name.replace("คุณ", "")[0]}</div>
                  <span className="bk-convo__pf" style={{ background: c.platform.color }}><svg viewBox="0 0 24 24" fill="#fff" width="11" height="11"><path fillRule="evenodd" clipRule="evenodd" d={window.BK_BRAND[c.platform.key]} /></svg></span>
                </div>
                <div style={{ minWidth: 0, flex: 1 }}>
                  <div style={{ display: "flex", justifyContent: "space-between", gap: 6 }}>
                    <span className="bk-row__title bk-truncate">{c.name}</span>
                    <span className="bk-row__sub" style={{ flexShrink: 0 }}>{c.time}</span>
                  </div>
                  <div style={{ display: "flex", justifyContent: "space-between", gap: 6, marginTop: 2, alignItems: "center" }}>
                    <span className="bk-convo__preview bk-truncate">{c.preview}</span>
                    {c.unread > 0 && <span className="bk-nav__badge bk-num" style={{ marginLeft: 0 }}>{c.unread}</span>}
                  </div>
                </div>
              </button>
            ))}
          </div>
        </div>

        {/* thread */}
        <div className="bk-inbox__thread">
          <div className="bk-thread__head">
            <button className="bk-iconbtn bk-thread__back" style={{ width: 34, height: 34 }} onClick={() => setOpenThread(false)}><BKIcon name="chevronRight" size={16} style={{ transform: "rotate(180deg)" }} /></button>
            <div style={{ position: "relative" }}>
              <div className="bk-avatar" style={{ width: 40, height: 40, background: convo.tint, color: "var(--text)" }}>{convo.name.replace("คุณ", "")[0]}</div>
              <span className="bk-convo__pf" style={{ background: convo.platform.color }}><svg viewBox="0 0 24 24" fill="#fff" width="11" height="11"><path fillRule="evenodd" clipRule="evenodd" d={window.BK_BRAND[convo.platform.key]} /></svg></span>
            </div>
            <div style={{ flex: 1, minWidth: 0 }}>
              <div className="bk-row__title">{convo.name}</div>
              <div className="bk-row__sub">{convo.online ? "🟢 กำลังออนไลน์" : "ผ่าน " + convo.platform.name}</div>
            </div>
            <button className="bk-iconbtn" style={{ width: 34, height: 34 }}><BKIcon name="dots" size={16} /></button>
          </div>

          {/* toolbar */}
          <div className="bk-thread__tools">
            {TOOLS.map((t) => (
              <button key={t.k} className={"bk-tool-btn" + (tool === t.k ? " is-on" : "")} onClick={() => setTool(tool === t.k ? null : t.k)}>
                <BKIcon name={t.icon} size={15} /> {t.label}
              </button>
            ))}
          </div>

          <div className="bk-thread__body">
            <div className="bk-thread__day">วันนี้</div>
            {convo.msgs.map((m, i) => (
              <div key={i} className={"bk-msg " + (m.from === "me" ? "is-me" : "is-them")}>
                <div className="bk-bubble">{m.text}</div>
                <div className="bk-msg__time">{m.time}</div>
              </div>
            ))}
          </div>

          {/* tool sheet */}
          {tool && (
            <div className="bk-tool-sheet">
              <div className="bk-tool-sheet__head">
                <span style={{ fontWeight: 700, display: "flex", alignItems: "center", gap: 8 }}>
                  <BKIcon name={TOOLS.find((x) => x.k === tool).icon} size={16} /> {toolTitle[tool]}
                </span>
                <button className="bk-iconbtn" style={{ width: 30, height: 30 }} onClick={() => setTool(null)}><BKIcon name="x" size={14} /></button>
              </div>
              <div className="bk-tool-sheet__body">
                {tool === "replies" && <RepliesTool replies={replies} setReplies={setReplies} onUse={(t) => { setDraft(t); setTool(null); }} />}
                {tool === "stock" && <StockTool />}
                {tool === "ship" && <ShipTool convo={convo} />}
                {tool === "order" && <OrderTool
                  onCreate={(payload) => onCreateOrder ? onCreateOrder({ ...payload, convo }) : null}
                  onDone={(order) => {
                    setTool(null);
                    if (order) {
                      setDraft(`สร้างคำสั่งซื้อ ${order.number} แล้ว`);
                    }
                  }}
                />}
              </div>
            </div>
          )}

          <div className="bk-thread__compose">
            <div className="bk-quick">
              <button className="bk-chip bk-chip--sm" style={{ borderColor: "var(--accent)", color: "var(--accent-strong)" }} onClick={() => setTool("replies")}>
                <BKIcon name="sparkles" size={12} /> คลังคำตอบ
              </button>
              {topReplies.map((r) => (
                <button key={r.id} className="bk-chip bk-chip--sm" onClick={() => setDraft(r.text)} title={r.text}>
                  {r.text.length > 22 ? r.text.slice(0, 21) + "…" : r.text}
                  {r.closeRate > 0 && <span className="bk-num" style={{ color: "var(--t-green)", fontWeight: 700, marginLeft: 4 }}>{r.closeRate}%</span>}
                </button>
              ))}
            </div>
            <div className="bk-compose__row">
              <button className="bk-iconbtn" style={{ width: 38, height: 38 }}><BKIcon name="image" size={17} /></button>
              <input className="bk-input" placeholder="พิมพ์ข้อความ…" value={draft} onChange={(e) => setDraft(e.target.value)}
                onKeyDown={(e) => { if (e.key === "Enter") setDraft(""); }} />
              <button className="bk-btn bk-btn--primary" onClick={() => setDraft("")}><BKIcon name="chevronRight" size={16} /> ส่ง</button>
            </div>
          </div>
        </div>
      </div>
    </div>
  );
}

Object.assign(window, { Inbox, BK_CONVOS });
