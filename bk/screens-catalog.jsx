/* ===== BabyKawaii — Products / Add product / Stock ===== */
const { useState } = React;

function Products({ go }) {
  const d = window.BK_DATA;
  const [cat, setCat] = useState("ทั้งหมด");
  const [view, setView] = useState("grid");
  const [q, setQ] = useState("");
  const cats = ["ทั้งหมด", ...d.categories];
  let list = d.products.filter((p) => (cat === "ทั้งหมด" || p.category === cat) && p.name.includes(q));

  const statusBadge = (p) =>
    p.status === "out" ? <Badge tone="red" dot>หมดสต็อก</Badge> :
    p.status === "low" ? <Badge tone="amber" dot>ใกล้หมด</Badge> :
    <Badge tone="green" dot>พร้อมขาย</Badge>;

  return (
    <div className="bk-page">
      <PageHead title="สินค้าทั้งหมด" sub={`${d.products.length} รายการ · พร้อมขาย ${d.products.filter(p=>p.status==="active").length} รายการ`}>
        <div className="bk-segment">
          <button className={view === "grid" ? "is-on" : ""} onClick={() => setView("grid")}>การ์ด</button>
          <button className={view === "list" ? "is-on" : ""} onClick={() => setView("list")}>ตาราง</button>
        </div>
        <button className="bk-btn bk-btn--primary" onClick={() => go("product-add")}><BKIcon name="plus" size={15} /> เพิ่มสินค้า</button>
      </PageHead>

      <div style={{ display: "flex", gap: 10, alignItems: "center", marginBottom: "var(--gap)", flexWrap: "wrap" }}>
        <div className="bk-search" style={{ maxWidth: 280, background: "var(--card)", border: "1px solid var(--border)" }}>
          <BKIcon name="search" size={16} />
          <input placeholder="ค้นหาชื่อสินค้า…" value={q} onChange={(e) => setQ(e.target.value)} />
        </div>
        <div style={{ display: "flex", gap: 7, flexWrap: "wrap" }}>
          {cats.map((c) => (
            <button key={c} className={"bk-chip" + (cat === c ? " is-on" : "")} onClick={() => setCat(c)}>{c}</button>
          ))}
        </div>
      </div>

      {view === "grid" ? (
        <div className="bk-products">
          {list.map((p) => (
            <div className="bk-product" key={p.id} onClick={() => go("product-add")}>
              <div className="bk-product__img" style={{ background: p.tint }}>
                👶
                <div style={{ position: "absolute", top: 9, right: 9 }}>{statusBadge(p)}</div>
              </div>
              <div className="bk-product__body">
                <div className="bk-row__sub bk-num" style={{ marginBottom: 2 }}>{p.sku}</div>
                <div className="bk-row__title bk-truncate" style={{ marginBottom: 6 }}>{p.name}</div>
                <div style={{ display: "flex", alignItems: "baseline", justifyContent: "space-between" }}>
                  <span className="bk-num" style={{ fontWeight: 800, color: "var(--accent-strong)", fontSize: "1rem" }}>{fmt(p.price)}</span>
                  <span className="bk-row__sub bk-num">คงเหลือ {p.stock}</span>
                </div>
              </div>
            </div>
          ))}
        </div>
      ) : (
        <Card pad={false}>
          <div style={{ overflowX: "auto" }}>
            <table className="bk-table">
              <thead><tr><th>สินค้า</th><th>หมวดหมู่</th><th className="bk-th-r">ราคา</th><th className="bk-th-r">ทุน</th><th className="bk-th-r">คงเหลือ</th><th className="bk-th-r">ขายแล้ว</th><th>สถานะ</th></tr></thead>
              <tbody>
                {list.map((p) => (
                  <tr key={p.id} style={{ cursor: "pointer" }} onClick={() => go("product-add")}>
                    <td>
                      <div style={{ display: "flex", alignItems: "center", gap: 11 }}>
                        <ProductThumb tint={p.tint} emoji="👶" />
                        <div>
                          <div className="bk-row__title">{p.name}</div>
                          <div className="bk-row__sub bk-num">{p.sku}</div>
                        </div>
                      </div>
                    </td>
                    <td className="bk-muted">{p.category}</td>
                    <td className="bk-num bk-td-r" style={{ fontWeight: 700, color: "var(--accent-strong)" }}>{fmt(p.price)}</td>
                    <td className="bk-num bk-td-r bk-muted">{fmt(p.cost)}</td>
                    <td className="bk-num bk-td-r">{p.stock}</td>
                    <td className="bk-num bk-td-r bk-muted">{fmtNum(p.sold)}</td>
                    <td>{statusBadge(p)}</td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        </Card>
      )}
    </div>
  );
}

function ProductAdd({ go, onSaveProduct }) {
  const d = window.BK_DATA;
  const nextId = Math.max(0, ...d.products.map((p) => p.id || 0)) + 1;
  const [name, setName] = useState("บอดี้สูทแขนยาว ลายกระต่าย");
  const [sku, setSku] = useState(`BK-${1000 + nextId * 7}`);
  const [brand, setBrand] = useState("คอตตอน 100%");
  const [stock, setStock] = useState(24);
  const [cat, setCat] = useState("บอดี้สูท");
  const [price, setPrice] = useState(290);
  const [cost, setCost] = useState(120);
  const [activeSizes, setActiveSizes] = useState(["NB", "0-3M", "3-6M", "6-9M"]);
  const profit = price - cost;
  const margin = price > 0 ? Math.round((profit / price) * 100) : 0;
  const toggleSize = (s) => setActiveSizes((a) => a.includes(s) ? a.filter(x => x !== s) : [...a, s]);
  const saveProduct = () => {
    onSaveProduct && onSaveProduct({ name, sku, brand, category: cat, price, cost, stock });
  };

  return (
    <div className="bk-page">
      <PageHead title="เพิ่มสินค้าใหม่" sub="กรอกรายละเอียดสินค้า รูปภาพ และจัดการสต็อกตามไซต์">
        <button className="bk-btn bk-btn--ghost" onClick={() => go("products")}>ยกเลิก</button>
        <button className="bk-btn bk-btn--primary" onClick={saveProduct} disabled={!name.trim()}><BKIcon name="check" size={15} /> บันทึกสินค้า</button>
      </PageHead>

      <div className="bk-grid-2" style={{ alignItems: "start" }}>
        <div className="bk-stack">
          <Card title="ข้อมูลสินค้า" emoji="👕">
            <div className="bk-field">
              <label className="bk-label">ชื่อสินค้า</label>
              <input className="bk-input" value={name} onChange={(e) => setName(e.target.value)} />
            </div>
            <div style={{ display: "grid", gridTemplateColumns: "1fr 1fr", gap: 14 }}>
              <div className="bk-field">
                <label className="bk-label">รหัสสินค้า (SKU)</label>
                <input className="bk-input bk-num" value={sku} onChange={(e) => setSku(e.target.value)} />
              </div>
              <div className="bk-field">
                <label className="bk-label">แบรนด์ผ้า</label>
                <input className="bk-input" value={brand} onChange={(e) => setBrand(e.target.value)} />
              </div>
            </div>
            <div className="bk-field" style={{ marginBottom: 0 }}>
              <label className="bk-label">หมวดหมู่</label>
              <div style={{ display: "flex", gap: 7, flexWrap: "wrap" }}>
                {d.categories.map((c) => (
                  <button key={c} className={"bk-chip" + (cat === c ? " is-on" : "")} onClick={() => setCat(c)}>{c}</button>
                ))}
              </div>
            </div>
            <div className="bk-field" style={{ marginTop: 14, marginBottom: 0 }}>
              <label className="bk-label">สต็อกเริ่มต้นรวม</label>
              <input className="bk-input bk-num" type="number" min="0" value={stock} onChange={(e) => setStock(+e.target.value)} />
            </div>
          </Card>

          <Card title="สต็อกตามไซต์" emoji="📐"
            action={<span className="bk-row__sub">เลือกไซต์ที่มีจำหน่าย</span>}>
            <div className="bk-sizes" style={{ marginBottom: 16 }}>
              {d.sizes.map((s) => (
                <button key={s} className={"bk-chip" + (activeSizes.includes(s) ? " is-on" : "")} onClick={() => toggleSize(s)}>{s}</button>
              ))}
            </div>
            <div style={{ overflowX: "auto" }}>
              <table className="bk-table" style={{ border: "1px solid var(--border)", borderRadius: "var(--radius-sm)" }}>
                <thead><tr><th>ไซต์</th>{d.colorsTH.slice(0, 4).map((c) => <th key={c} className="bk-th-r">{c}</th>)}</tr></thead>
                <tbody>
                  {activeSizes.map((s) => (
                    <tr key={s}>
                      <td style={{ fontWeight: 700 }}>{s}</td>
                      {d.colorsTH.slice(0, 4).map((c) => (
                        <td key={c} className="bk-td-r"><input className="bk-input bk-num" style={{ width: 64, padding: "5px 8px", textAlign: "right", display: "inline-block" }} defaultValue={Math.floor(Math.random() * 20)} /></td>
                      ))}
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
          </Card>
        </div>

        <div className="bk-stack">
          <Card title="รูปภาพสินค้า" emoji="📸">
            <div style={{ border: "2px dashed var(--accent-soft-strong)", borderRadius: "var(--radius-sm)", padding: "30px 16px", textAlign: "center", background: "var(--accent-soft)", color: "var(--accent-strong)", cursor: "pointer" }}>
              <BKIcon name="image" size={30} />
              <div style={{ fontWeight: 700, marginTop: 8 }}>ลากรูปมาวาง หรือคลิกเพื่ออัปโหลด</div>
              <div className="bk-row__sub" style={{ color: "var(--muted)" }}>JPG, PNG ไม่เกิน 5MB · แนะนำพื้นหลังพาสเทล</div>
            </div>
            <div style={{ display: "grid", gridTemplateColumns: "repeat(4,1fr)", gap: 8, marginTop: 12 }}>
              {["#FBE9EE", "#E8EEF8", "#E6F2EC", "#F8EFDD"].map((t, i) => (
                <div key={i} style={{ aspectRatio: "1", borderRadius: "var(--radius-xs)", background: t, display: "flex", alignItems: "center", justifyContent: "center", fontSize: "1.4rem", border: i === 0 ? "2px solid var(--accent)" : "1px solid var(--border)" }}>👶</div>
              ))}
            </div>
          </Card>

          <Card title="ราคา & กำไร" emoji="💰">
            <div style={{ display: "grid", gridTemplateColumns: "1fr 1fr", gap: 14 }}>
              <div className="bk-field" style={{ marginBottom: 0 }}>
                <label className="bk-label">ราคาขาย (฿)</label>
                <input className="bk-input bk-num" type="number" value={price} onChange={(e) => setPrice(+e.target.value)} />
              </div>
              <div className="bk-field" style={{ marginBottom: 0 }}>
                <label className="bk-label">ต้นทุน (฿)</label>
                <input className="bk-input bk-num" type="number" value={cost} onChange={(e) => setCost(+e.target.value)} />
              </div>
            </div>
            <div style={{ marginTop: 16, padding: 14, borderRadius: "var(--radius-sm)", background: "var(--accent-soft)" }}>
              <div style={{ display: "flex", justifyContent: "space-between", alignItems: "center" }}>
                <span className="bk-row__sub" style={{ color: "var(--accent-strong)" }}>กำไรต่อชิ้น</span>
                <span className="bk-num" style={{ fontWeight: 800, fontSize: "1.4rem", color: "var(--accent-strong)" }}>{fmt(profit)}</span>
              </div>
              <div style={{ marginTop: 10 }}><Bar value={margin} color="var(--accent-strong)" /></div>
              <div className="bk-row__sub" style={{ marginTop: 6, color: "var(--accent-strong)" }}>อัตรากำไร {margin}%</div>
            </div>
          </Card>
        </div>
      </div>
    </div>
  );
}

function sizeTone(q) { return q === 0 ? "out" : q <= 5 ? "low" : "ok"; }

function Stock({ go, onRestock }) {
  const d = window.BK_DATA;
  const [filter, setFilter] = useState("all");
  const rows = d.products.map((p) => {
    const sizeStock = d.sizes.slice(0, 6).map((s, i) => ({ size: s, qty: Math.max(0, (p.stock >> i) % 24) }));
    return { ...p, sizeStock };
  });
  const filtered = rows.filter((r) => filter === "all" || (filter === "low" && r.status === "low") || (filter === "out" && r.status === "out"));

  return (
    <div className="bk-page">
      <PageHead title="จัดการสต็อก" sub={`สต็อกรวม ${fmtNum(d.kpis.stockTotal)} ชิ้น · ใกล้หมด ${d.kpis.lowStock} รายการ`}>
        <button className="bk-btn bk-btn--ghost"><BKIcon name="download" size={15} /> นำเข้า Excel</button>
        <button className="bk-btn bk-btn--primary" onClick={() => filtered.forEach((row) => onRestock && onRestock(row.id, 10))}><BKIcon name="plus" size={15} /> เติมสต็อก</button>
      </PageHead>

      <div className="bk-stats" style={{ gridTemplateColumns: "repeat(4,1fr)", marginBottom: "var(--gap)" }}>
        {[
          { icon: "box", label: "สต็อกรวมทั้งหมด", value: fmtNum(d.kpis.stockTotal) + " ชิ้น", tone: "blue" },
          { icon: "check", label: "พร้อมขาย", value: d.products.filter(p=>p.status==="active").length + " รายการ", tone: "green" },
          { icon: "alert", label: "ใกล้หมด (≤5)", value: d.kpis.lowStock + " รายการ", tone: "amber" },
          { icon: "x", label: "หมดสต็อก", value: d.products.filter(p=>p.status==="out").length + " รายการ", tone: "red" },
        ].map((s, i) => (
          <div className="bk-stat" key={i}>
            <div className="bk-stat__top"><div className="bk-stat__icon" style={{ background: "var(--t-" + s.tone + "-bg)", color: "var(--t-" + s.tone + ")" }}><BKIcon name={s.icon} size={16} /></div></div>
            <div className="bk-stat__label" style={{ marginTop: 10 }}>{s.label}</div>
            <div className="bk-stat__value bk-num" style={{ fontSize: "1.35rem" }}>{s.value}</div>
          </div>
        ))}
      </div>

      <Card pad={false}
        title="รายการสต็อก" emoji="📦"
        action={
          <div className="bk-segment">
            {[["all", "ทั้งหมด"], ["low", "ใกล้หมด"], ["out", "หมด"]].map(([k, l]) => (
              <button key={k} className={filter === k ? "is-on" : ""} onClick={() => setFilter(k)}>{l}</button>
            ))}
          </div>
        }>
        <div style={{ overflowX: "auto" }}>
          <table className="bk-table">
            <thead><tr><th>สินค้า</th><th>สต็อกแยกไซต์</th><th className="bk-th-r">รวม</th><th></th></tr></thead>
            <tbody>
              {filtered.map((r) => (
                <tr key={r.id}>
                  <td>
                    <div style={{ display: "flex", alignItems: "center", gap: 11 }}>
                      <ProductThumb tint={r.tint} emoji="👶" />
                      <div>
                        <div className="bk-row__title">{r.name}</div>
                        <div className="bk-row__sub bk-num">{r.sku} · {r.category}</div>
                      </div>
                    </div>
                  </td>
                  <td>
                    <div className="bk-sizes">
                      {r.sizeStock.map((ss) => (
                        <span key={ss.size} className={"bk-size bk-size--" + sizeTone(ss.qty)}>{ss.size} · {ss.qty}</span>
                      ))}
                    </div>
                  </td>
                  <td className="bk-num bk-td-r" style={{ fontWeight: 700 }}>{r.stock}</td>
                  <td className="bk-td-r"><button className="bk-btn bk-btn--soft bk-btn--sm" onClick={() => onRestock && onRestock(r.id, 10)}>เติมสต็อก +10</button></td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      </Card>
    </div>
  );
}

Object.assign(window, { Products, ProductAdd, Stock });
