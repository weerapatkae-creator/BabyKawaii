/* ===== BabyKawaii — Dashboard ===== */

function StatCard({ icon, label, value, delta, foot, spark, dir }) {
  return (
    <div className="bk-stat">
      <div className="bk-stat__top">
        <div className="bk-stat__icon"><BKIcon name={icon} size={dir === "c" ? 15 : 17} /></div>
        {delta != null && <Delta value={delta} />}
      </div>
      <div className="bk-stat__label" style={{ marginTop: dir === "c" ? 8 : 12 }}>{label}</div>
      <div className="bk-stat__value bk-num">{value}</div>
      {dir === "b" && spark ? (
        <div style={{ marginTop: 10 }}><Sparkline data={spark} width={150} height={34} /></div>
      ) : foot ? (
        <div className="bk-stat__foot">{foot}</div>
      ) : null}
    </div>
  );
}

function AreaChart({ data, labels, height = 230 }) {
  const W = 760, H = height, padB = 26;
  const max = Math.max(...data) * 1.1, min = 0;
  const chartH = H - padB - 12;
  const range = max - min || 1;
  const pts = data.map((v, i) => {
    const x = (i / (data.length - 1)) * (W - 16) + 8;
    const y = 12 + chartH - ((v - min) / range) * chartH;
    return [x, y];
  });
  const line = pts.map((p, i) => (i ? "L" : "M") + p[0].toFixed(1) + " " + p[1].toFixed(1)).join(" ");
  const area = line + ` L${W - 8} ${12 + chartH} L8 ${12 + chartH} Z`;
  return (
    <svg viewBox={`0 0 ${W} ${H}`} style={{ width: "100%", height }} preserveAspectRatio="none">
      <defs>
        <linearGradient id="bkArea" x1="0" y1="0" x2="0" y2="1">
          <stop offset="0%" stopColor="var(--accent)" stopOpacity="0.22" />
          <stop offset="100%" stopColor="var(--accent)" stopOpacity="0" />
        </linearGradient>
      </defs>
      {[0, .25, .5, .75, 1].map((g, i) => {
        const y = 12 + chartH * (1 - g);
        return <line key={i} x1="8" x2={W - 8} y1={y} y2={y} className="bk-grid" />;
      })}
      <path d={area} fill="url(#bkArea)" />
      <path d={line} fill="none" stroke="var(--accent)" strokeWidth="2.5" strokeLinecap="round" strokeLinejoin="round" />
      {pts.map((p, i) => i % 2 === 0 || i === pts.length - 1 ? (
        <circle key={i} cx={p[0]} cy={p[1]} r={i === pts.length - 1 ? 4 : 2.5} fill="var(--accent)" stroke="var(--card)" strokeWidth="1.5" />
      ) : null)}
      {labels.map((l, i) => i % 2 === 0 ? (
        <text key={i} x={pts[i][0]} y={H - 6} textAnchor="middle" className="bk-bar-label">{l}</text>
      ) : null)}
    </svg>
  );
}

function PlatformBreakdown({ dir }) {
  const d = window.BK_DATA;
  if (dir === "b" || dir === "c") {
    return (
      <div className="bk-stack" style={{ ["--gap"]: dir === "c" ? "9px" : "13px" }}>
        {d.platforms.map((p) => (
          <div key={p.key}>
            <div style={{ display: "flex", justifyContent: "space-between", fontSize: ".82rem", marginBottom: 5 }}>
              <span style={{ display: "inline-flex", alignItems: "center", gap: 7 }}><PlatformIcon pf={p} size={16} />{p.name}</span>
              <span className="bk-num" style={{ fontWeight: 700 }}>{fmt(p.total)}</span>
            </div>
            <Bar value={p.share} color={p.color} />
          </div>
        ))}
      </div>
    );
  }
  const total = d.platforms.reduce((s, p) => s + p.total, 0);
  return (
    <div>
      <div style={{ display: "flex", justifyContent: "center", marginBottom: 16 }}>
        <Donut size={158} thickness={20}
          segments={d.platforms.map((p) => ({ value: p.total, color: p.color }))}
          centerLabel={fmtK(total)} centerSub="บาท / เดือน" />
      </div>
      <div className="bk-stack" style={{ ["--gap"]: "7px" }}>
        {d.platforms.map((p) => (
          <div key={p.key} style={{ display: "flex", alignItems: "center", gap: 8, fontSize: ".8rem" }}>
            <PlatformIcon pf={p} size={20} tile />
            <span>{p.name}</span>
            <span className="bk-num" style={{ marginLeft: "auto", fontWeight: 700 }}>{fmt(p.total)}</span>
            <span className="bk-muted bk-num" style={{ fontSize: ".72rem", width: 30, textAlign: "right" }}>{p.share}%</span>
          </div>
        ))}
      </div>
    </div>
  );
}

function ProfitStrip() {
  const k = window.BK_DATA.kpis;
  const items = [
    { emoji: "📦", label: "มูลค่าสต็อกในมือ", value: fmt(k.inventoryCost), tone: "blue" },
    { emoji: "🧾", label: "ต้นทุนขายเดือนนี้", value: fmt(k.cogsMonth), tone: "amber" },
    { emoji: "✨", label: "กำไรขั้นต้นเดือนนี้", value: "+" + fmt(k.grossProfitMonth), tone: "green" },
    { emoji: "📊", label: "อัตรากำไรขั้นต้น", value: k.marginMonth + "%", tone: "rose", bar: k.marginMonth },
  ];
  return (
    <Card title="ต้นทุน & กำไร" emoji="💰"
      action={<Badge tone="neutral">เฉพาะเจ้าของร้าน</Badge>}>
      <div className="bk-profit-grid">
        {items.map((it, i) => (
          <div key={i}>
            <div className="bk-row__sub" style={{ marginBottom: 4 }}><span className="bk-emoji">{it.emoji}</span>{it.label}</div>
            <div className="bk-num" style={{ fontSize: "1.25rem", fontWeight: 800, color: "var(--t-" + it.tone + ")" }}>{it.value}</div>
            {it.bar != null && <div style={{ marginTop: 7 }}><Bar value={it.bar} color={"var(--t-" + it.tone + ")"} /></div>}
          </div>
        ))}
      </div>
    </Card>
  );
}

function Dashboard({ dir, go }) {
  const d = window.BK_DATA, k = d.kpis;
  const stats = [
    { icon: "coins", label: "ยอดขายวันนี้", value: fmt(k.salesToday), delta: k.salesTodayDelta, spark: d.sales14.slice(-7), foot: <span><BKIcon name="calendar" size={12} /> 29 พ.ค. 2569</span> },
    { icon: "trend", label: "ยอดขายเดือนนี้", value: fmt(k.salesMonth), spark: d.sales14, foot: <span><BKIcon name="bag" size={12} /> {fmtNum(k.ordersMonth)} ออเดอร์</span> },
    { icon: "shirt", label: "สินค้าทั้งหมด", value: fmtNum(k.products), spark: [60,64,70,72,78,82,86], foot: <span><BKIcon name="box" size={12} /> สต็อกรวม {fmtNum(k.stockTotal)} ชิ้น</span> },
    { icon: "truck", label: "ออเดอร์รอดำเนินการ", value: fmtNum(k.pending), spark: [8,11,9,14,12,15,14], foot: <span style={{ color: "var(--t-amber)" }}><BKIcon name="alert" size={12} /> สต็อกใกล้หมด {k.lowStock} รายการ</span> },
  ];
  return (
    <div className="bk-page">
      <PageHead title="แดชบอร์ด" sub={`สวัสดีคุณ${d.shop.admin} 🌸 วันนี้วันศุกร์ที่ 29 พฤษภาคม 2569`}>
        <button className="bk-btn bk-btn--ghost"><BKIcon name="download" size={15} /> ส่งออกรายงาน</button>
        <button className="bk-btn bk-btn--primary" onClick={() => go("product-add")}><BKIcon name="plus" size={15} /> เพิ่มสินค้า</button>
      </PageHead>

      <div className="bk-stats" style={{ marginBottom: "var(--gap)" }}>
        {stats.map((s, i) => <StatCard key={i} {...s} dir={dir} />)}
      </div>

      <div style={{ marginBottom: "var(--gap)" }}><ProfitStrip /></div>

      <div className="bk-grid-2" style={{ marginBottom: "var(--gap)" }}>
        <Card title="ยอดขาย 14 วันล่าสุด" emoji="📈"
          action={<button className="bk-btn bk-btn--soft bk-btn--sm" onClick={() => go("sales")}>ดูรายละเอียด <BKIcon name="chevronRight" size={13} /></button>}>
          {dir === "a"
            ? <BarChart data={d.sales14} labels={d.salesDays} height={232} />
            : <AreaChart data={d.sales14} labels={d.salesDays} height={232} />}
        </Card>
        <Card title="ยอดขายตามแพลตฟอร์ม" emoji="📊">
          <PlatformBreakdown dir={dir} />
        </Card>
      </div>

      <div className="bk-grid-3b">
        <Card title="สินค้าขายดีเดือนนี้" emoji="🏆" pad={false}
          action={<button className="bk-btn bk-btn--soft bk-btn--sm" onClick={() => go("sales")}>ทั้งหมด</button>}>
          {d.bestSellers.map((p, i) => (
            <div className="bk-row" key={p.id}>
              <div style={{ width: 22, fontWeight: 800, fontSize: i < 3 ? "1.05rem" : ".85rem", color: "var(--muted)", textAlign: "center" }}>
                {["🥇", "🥈", "🥉"][i] || "#" + (i + 1)}
              </div>
              <ProductThumb tint={p.tint} emoji="👶" />
              <div style={{ minWidth: 0, flex: 1 }}>
                <div className="bk-row__title bk-truncate">{p.name}</div>
                <div className="bk-row__sub">{p.category}</div>
              </div>
              <div style={{ textAlign: "right" }}>
                <div className="bk-num" style={{ fontWeight: 700, color: "var(--accent-strong)", fontSize: ".85rem" }}>{fmtNum(p.sold)} ชิ้น</div>
                <div className="bk-row__sub bk-num">{fmt(p.price * p.sold)}</div>
              </div>
            </div>
          ))}
        </Card>

        <Card title="ออเดอร์ล่าสุด" emoji="📦" pad={false}
          action={<button className="bk-btn bk-btn--soft bk-btn--sm" onClick={() => go("orders")}>ทั้งหมด</button>}>
          <div style={{ overflowX: "auto" }}>
            <table className="bk-table">
              <thead><tr><th>เลขออเดอร์</th><th>ช่องทาง</th><th className="bk-th-r">ยอด</th><th>สถานะ</th></tr></thead>
              <tbody>
                {d.orders.slice(0, 6).map((o) => (
                  <tr key={o.id} style={{ cursor: "pointer" }} onClick={() => go("orders")}>
                    <td>
                      <div style={{ fontWeight: 700, color: "var(--accent-strong)", fontSize: ".82rem" }} className="bk-num">{o.number}</div>
                      <div className="bk-row__sub">{o.customer}</div>
                    </td>
                    <td title={o.platform.name}><PlatformIcon pf={o.platform} size={22} tile /></td>
                    <td className="bk-num bk-td-r" style={{ fontWeight: 700 }}>{fmt(o.total)}</td>
                    <td><StatusBadge status={o.status} /></td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        </Card>

        <Card title="สต็อกใกล้หมด" emoji="⚠️" pad={false}
          action={<button className="bk-btn bk-btn--soft bk-btn--sm" onClick={() => go("stock")}>จัดการ</button>}>
          {d.lowStockItems.slice(0, 6).map((s, i) => (
            <div className="bk-row" key={i}>
              <div style={{ minWidth: 0, flex: 1 }}>
                <div className="bk-row__title bk-truncate">{s.name}</div>
                <div className="bk-row__sub">{s.size} · {s.color}</div>
              </div>
              <Badge tone={s.qty === 0 ? "red" : "amber"} dot>{s.qty === 0 ? "หมด" : s.qty + " ชิ้น"}</Badge>
            </div>
          ))}
        </Card>
      </div>
    </div>
  );
}

Object.assign(window, { Dashboard, StatCard, AreaChart });
