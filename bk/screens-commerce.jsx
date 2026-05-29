/* ===== BabyKawaii — Orders / Sales ===== */

function Orders({ go }) {
  const d = window.BK_DATA;
  const [status, setStatus] = React.useState("all");
  const counts = d.orders.reduce((m, o) => { m[o.status] = (m[o.status] || 0) + 1; return m; }, {});
  const tabs = [["all", "ทั้งหมด", d.orders.length], ["pending", "รอดำเนินการ", counts.pending || 0], ["confirmed", "ยืนยันแล้ว", counts.confirmed || 0], ["packing", "กำลังแพ็ค", counts.packing || 0], ["shipped", "จัดส่งแล้ว", counts.shipped || 0], ["delivered", "ส่งถึงแล้ว", counts.delivered || 0]];
  const list = d.orders.filter((o) => status === "all" || o.status === status);
  const flow = ["pending", "confirmed", "packing", "shipped", "delivered"];

  return (
    <div className="bk-page">
      <PageHead title="ออเดอร์ / คำสั่งซื้อ" sub={`${d.orders.length} ออเดอร์ · รอดำเนินการ ${counts.pending || 0} รายการ`}>
        <button className="bk-btn bk-btn--ghost"><BKIcon name="download" size={15} /> ส่งออก</button>
        <button className="bk-btn bk-btn--primary"><BKIcon name="plus" size={15} /> บันทึกออเดอร์</button>
      </PageHead>

      <div style={{ display: "flex", gap: 7, flexWrap: "wrap", marginBottom: "var(--gap)" }}>
        {tabs.map(([k, l, c]) => (
          <button key={k} className={"bk-chip" + (status === k ? " is-on" : "")} onClick={() => setStatus(k)}>
            {l} <span className="bk-num" style={{ opacity: .6 }}>{c}</span>
          </button>
        ))}
      </div>

      <Card pad={false}>
        <div style={{ overflowX: "auto" }}>
          <table className="bk-table">
            <thead><tr><th>เลขออเดอร์</th><th>ลูกค้า</th><th>ช่องทาง</th><th className="bk-th-r">ชิ้น</th><th className="bk-th-r">ยอดรวม</th><th>เวลา</th><th>สถานะ</th><th></th></tr></thead>
            <tbody>
              {list.map((o) => (
                <tr key={o.id}>
                  <td className="bk-num" style={{ fontWeight: 700, color: "var(--accent-strong)" }}>{o.number}</td>
                  <td>{o.customer}</td>
                  <td><PlatformIcon pf={o.platform} size={22} tile /> <span className="bk-muted" style={{ fontSize: ".8rem", verticalAlign: "middle" }}>{o.platform.name}</span></td>
                  <td className="bk-num bk-td-r">{o.items}</td>
                  <td className="bk-num bk-td-r" style={{ fontWeight: 700 }}>{fmt(o.total)}</td>
                  <td className="bk-muted bk-num" style={{ fontSize: ".8rem" }}>{o.date} {o.time}</td>
                  <td><StatusBadge status={o.status} /></td>
                  <td className="bk-td-r">
                    <button className="bk-iconbtn" style={{ width: 30, height: 30, display: "inline-flex" }}><BKIcon name="chevronRight" size={15} /></button>
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      </Card>

      <div style={{ marginTop: "var(--gap)" }}>
        <Card title="ขั้นตอนการจัดส่ง" emoji="🚚">
          <div style={{ display: "flex", alignItems: "center", gap: 0, flexWrap: "wrap" }}>
            {flow.map((s, i) => (
              <React.Fragment key={s}>
                <div style={{ textAlign: "center", flex: "1 1 90px" }}>
                  <div style={{ width: 38, height: 38, margin: "0 auto", borderRadius: "50%", background: "var(--accent-soft)", color: "var(--accent-strong)", display: "flex", alignItems: "center", justifyContent: "center", fontWeight: 800 }} className="bk-num">{counts[s] || 0}</div>
                  <div className="bk-row__sub" style={{ marginTop: 6 }}>{d.statusTH[s]}</div>
                </div>
                {i < flow.length - 1 && <div style={{ flex: "0 0 24px", height: 2, background: "var(--border)" }} />}
              </React.Fragment>
            ))}
          </div>
        </Card>
      </div>
    </div>
  );
}

function Sales({ dir }) {
  const d = window.BK_DATA, k = d.kpis;
  const [period, setPeriod] = React.useState("14วัน");
  const kpiCards = [
    { icon: "coins", label: "รายได้รวม", value: fmt(k.salesMonth), delta: +8.1 },
    { icon: "bag", label: "จำนวนออเดอร์", value: fmtNum(k.ordersMonth), delta: +5.4 },
    { icon: "trend", label: "ยอดเฉลี่ย/ออเดอร์", value: fmt(k.aov), delta: +2.6 },
    { icon: "sparkles", label: "กำไรขั้นต้น", value: fmt(k.grossProfitMonth), delta: +11.2 },
  ];
  const sizeData = [["NB", 84], ["0-3M", 142], ["3-6M", 118], ["6-9M", 76], ["9-12M", 52], ["12-18M", 38], ["18-24M", 21]];
  const sizeMax = Math.max(...sizeData.map((s) => s[1]));

  return (
    <div className="bk-page">
      <PageHead title="ยอดขาย & วิเคราะห์" sub="ภาพรวมผลประกอบการและแนวโน้มการขาย">
        <div className="bk-segment">
          {["วันนี้", "7วัน", "14วัน", "เดือนนี้"].map((p) => (
            <button key={p} className={period === p ? "is-on" : ""} onClick={() => setPeriod(p)}>{p}</button>
          ))}
        </div>
        <button className="bk-btn bk-btn--ghost"><BKIcon name="download" size={15} /> ส่งออก</button>
      </PageHead>

      <div className="bk-stats" style={{ marginBottom: "var(--gap)" }}>
        {kpiCards.map((s, i) => <StatCard key={i} {...s} dir={dir} foot={null} spark={d.sales14} />)}
      </div>

      <div className="bk-grid-2" style={{ marginBottom: "var(--gap)" }}>
        <Card title="แนวโน้มยอดขาย" emoji="📈"
          action={<Badge tone="green" dot>+8.1% จากช่วงก่อน</Badge>}>
          {dir === "a"
            ? <BarChart data={d.sales14} labels={d.salesDays} height={240} />
            : <AreaChart data={d.sales14} labels={d.salesDays} height={240} />}
        </Card>
        <Card title="สัดส่วนช่องทางขาย" emoji="📊">
          <PlatformBreakdown dir={dir} />
        </Card>
      </div>

      <div className="bk-grid-2">
        <Card title="ขายดีตามไซต์" emoji="📐">
          <div className="bk-stack" style={{ ["--gap"]: "12px" }}>
            {sizeData.map(([s, v]) => (
              <div key={s} style={{ display: "flex", alignItems: "center", gap: 12 }}>
                <span style={{ width: 60, fontSize: ".82rem", fontWeight: 600 }}>{s}</span>
                <div style={{ flex: 1 }}><Bar value={v} max={sizeMax} /></div>
                <span className="bk-num bk-row__sub" style={{ width: 56, textAlign: "right" }}>{v} ชิ้น</span>
              </div>
            ))}
          </div>
        </Card>
        <Card title="สินค้าขายดี 5 อันดับ" emoji="🏆" pad={false}>
          {d.bestSellers.map((p, i) => (
            <div className="bk-row" key={p.id}>
              <div style={{ width: 22, fontWeight: 800, fontSize: i < 3 ? "1.05rem" : ".85rem", color: "var(--muted)", textAlign: "center" }}>{["🥇", "🥈", "🥉"][i] || "#" + (i + 1)}</div>
              <ProductThumb tint={p.tint} emoji="👶" />
              <div style={{ minWidth: 0, flex: 1 }}>
                <div className="bk-row__title bk-truncate">{p.name}</div>
                <div className="bk-row__sub">{p.category}</div>
              </div>
              <div style={{ width: 90 }}><Bar value={p.sold} max={d.bestSellers[0].sold} /></div>
              <span className="bk-num" style={{ fontWeight: 700, width: 56, textAlign: "right", fontSize: ".85rem" }}>{fmtNum(p.sold)}</span>
            </div>
          ))}
        </Card>
      </div>
    </div>
  );
}

Object.assign(window, { Orders, Sales });
