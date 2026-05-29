/* ===== BabyKawaii — UI primitives + charts ===== */

const fmt = (n) => "฿\u202F" + Number(n).toLocaleString("th-TH");
const fmtK = (n) => n >= 1000 ? (n / 1000).toFixed(n % 1000 === 0 ? 0 : 1) + "k" : String(n);
const fmtNum = (n) => Number(n).toLocaleString("th-TH");

/* ---------- Card ---------- */
function Card({ title, emoji, action, children, pad = true, className = "", style }) {
  return (
    <section className={"bk-card " + className} style={style}>
      {title && (
        <header className="bk-card__head">
          <h3 className="bk-card__title">
            {emoji && <span className="bk-emoji">{emoji}</span>}{title}
          </h3>
          {action}
        </header>
      )}
      <div className={pad ? "bk-card__body" : ""}>{children}</div>
    </section>
  );
}

/* ---------- Badge ---------- */
const STATUS_TONE = {
  pending: "amber", confirmed: "blue", packing: "violet",
  shipped: "mint", delivered: "green", cancelled: "red",
  active: "green", low: "amber", out: "red",
  VIP: "rose", "ประจำ": "blue", "ใหม่": "mint",
};
function Badge({ tone, children, dot }) {
  return (
    <span className={"bk-badge bk-badge--" + (tone || "neutral")}>
      {dot && <span className="bk-badge__dot" />}{children}
    </span>
  );
}
function StatusBadge({ status }) {
  return <Badge tone={STATUS_TONE[status]}>{window.BK_DATA.statusTH[status] || status}</Badge>;
}

/* ---------- Delta chip ---------- */
function Delta({ value }) {
  const up = value >= 0;
  return (
    <span className={"bk-delta " + (up ? "is-up" : "is-down")}>
      <BKIcon name={up ? "arrowUp" : "arrowDown"} size={12} stroke={2.4} />
      {Math.abs(value)}%
    </span>
  );
}

/* ---------- Bar chart (SVG) ---------- */
function BarChart({ data, labels, height = 220, accent = "var(--accent)" }) {
  const max = Math.max(...data, 1);
  const W = 720, H = height, padB = 26, padL = 8;
  const n = data.length;
  const gap = 8;
  const bw = (W - padL * 2 - gap * (n - 1)) / n;
  const chartH = H - padB - 10;
  const grid = [0, 0.25, 0.5, 0.75, 1];
  return (
    <svg viewBox={`0 0 ${W} ${H}`} className="bk-bars" preserveAspectRatio="none" style={{ width: "100%", height }}>
      {grid.map((g, i) => {
        const y = 10 + chartH * (1 - g);
        return <line key={i} x1={padL} x2={W - padL} y1={y} y2={y} className="bk-grid" />;
      })}
      {data.map((v, i) => {
        const h = (v / max) * chartH;
        const x = padL + i * (bw + gap);
        const y = 10 + chartH - h;
        const isLast = i === n - 1;
        return (
          <g key={i}>
            <rect x={x} y={y} width={bw} height={Math.max(h, 2)} rx={Math.min(bw / 2, 6)}
              fill={accent} fillOpacity={isLast ? 1 : 0.3} className="bk-bar" />
            {labels && <text x={x + bw / 2} y={H - 8} textAnchor="middle" className="bk-bar-label">{labels[i]}</text>}
          </g>
        );
      })}
    </svg>
  );
}

/* ---------- Donut ---------- */
function Donut({ segments, size = 150, thickness = 18, centerLabel, centerSub }) {
  const total = segments.reduce((s, x) => s + x.value, 0) || 1;
  const r = (size - thickness) / 2;
  const c = 2 * Math.PI * r;
  let acc = 0;
  return (
    <div className="bk-donut" style={{ width: size, height: size }}>
      <svg viewBox={`0 0 ${size} ${size}`} width={size} height={size}>
        <circle cx={size / 2} cy={size / 2} r={r} fill="none" stroke="var(--border)" strokeWidth={thickness} />
        {segments.map((s, i) => {
          const len = (s.value / total) * c;
          const el = (
            <circle key={i} cx={size / 2} cy={size / 2} r={r} fill="none"
              stroke={s.color} strokeWidth={thickness} strokeLinecap="round"
              strokeDasharray={`${Math.max(len - 3, 0)} ${c}`}
              strokeDashoffset={-acc}
              transform={`rotate(-90 ${size / 2} ${size / 2})`}
              style={{ transition: "stroke-dasharray .6s ease" }} />
          );
          acc += len;
          return el;
        })}
      </svg>
      {centerLabel && (
        <div className="bk-donut__center">
          <div className="bk-donut__val">{centerLabel}</div>
          {centerSub && <div className="bk-donut__sub">{centerSub}</div>}
        </div>
      )}
    </div>
  );
}

/* ---------- Sparkline ---------- */
function Sparkline({ data, width = 110, height = 36, accent = "var(--accent)" }) {
  const max = Math.max(...data), min = Math.min(...data);
  const range = max - min || 1;
  const pts = data.map((v, i) => {
    const x = (i / (data.length - 1)) * width;
    const y = height - 3 - ((v - min) / range) * (height - 6);
    return [x, y];
  });
  const line = pts.map((p, i) => (i ? "L" : "M") + p[0].toFixed(1) + " " + p[1].toFixed(1)).join(" ");
  const area = line + ` L${width} ${height} L0 ${height} Z`;
  const id = "spk" + Math.random().toString(36).slice(2, 7);
  return (
    <svg width={width} height={height} viewBox={`0 0 ${width} ${height}`} className="bk-spark">
      <defs>
        <linearGradient id={id} x1="0" y1="0" x2="0" y2="1">
          <stop offset="0%" stopColor={accent} stopOpacity="0.22" />
          <stop offset="100%" stopColor={accent} stopOpacity="0" />
        </linearGradient>
      </defs>
      <path d={area} fill={`url(#${id})`} />
      <path d={line} fill="none" stroke={accent} strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" />
      <circle cx={pts[pts.length - 1][0]} cy={pts[pts.length - 1][1]} r="2.6" fill={accent} />
    </svg>
  );
}

/* ---------- Progress ---------- */
function Bar({ value, max = 100, color = "var(--accent)" }) {
  return (
    <div className="bk-progress">
      <div className="bk-progress__fill" style={{ width: Math.min(100, (value / max) * 100) + "%", background: color }} />
    </div>
  );
}

/* ---------- Product thumb placeholder ---------- */
function ProductThumb({ tint, size = 44, radius = 10, emoji }) {
  return (
    <div className="bk-thumb" style={{ width: size, height: size, borderRadius: radius, background: tint }}>
      <span style={{ fontSize: size * 0.42, opacity: 0.85 }}>{emoji || "👕"}</span>
    </div>
  );
}

Object.assign(window, { fmt, fmtK, fmtNum, Card, Badge, StatusBadge, Delta, BarChart, Donut, Sparkline, Bar, ProductThumb, STATUS_TONE });
