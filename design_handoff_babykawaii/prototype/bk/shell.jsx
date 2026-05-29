/* ===== BabyKawaii — App shell (sidebar + topbar) ===== */

const BK_NAV = [
  { section: "ภาพรวม" },
  { id: "dashboard", label: "แดชบอร์ด", icon: "grid" },
  { id: "orders", label: "ออเดอร์", icon: "bag", badge: 14 },
  { id: "inbox", label: "Inbox ข้อความ", icon: "chat", badge: 3 },
  { id: "sales", label: "ยอดขาย & วิเคราะห์", icon: "trend" },
  { section: "คลังสินค้า" },
  { id: "products", label: "สินค้าทั้งหมด", icon: "shirt" },
  { id: "product-add", label: "เพิ่มสินค้า", icon: "plusCircle" },
  { id: "stock", label: "จัดการสต็อก", icon: "box", badge: 6 },
  { section: "การตลาด & สื่อ" },
  { id: "media", label: "คลังสื่อ", icon: "image" },
  { id: "calendar", label: "ปฏิทินโพสต์", icon: "calendar" },
  { id: "promotions", label: "โปรโมชั่น", icon: "tag" },
  { section: "ลูกค้า & ช่องทาง" },
  { id: "customers", label: "ฐานข้อมูลลูกค้า", icon: "users" },
  { id: "platforms", label: "แพลตฟอร์มขาย", icon: "share" },
  { id: "settings", label: "ตั้งค่าร้าน", icon: "settings" },
  { section: "จัดการระบบ" },
  { id: "users", label: "จัดการผู้ใช้งาน", icon: "users" },
];

function Sidebar({ page, go, open, onClose }) {
  const d = window.BK_DATA;
  return (
    <aside className={"bk-sidebar" + (open ? " is-open" : "")}>
      <div className="bk-sidebar__brand">
        <BKBrandMark size={26} />
        <div>
          <div className="bk-brand-name">Baby<b>kawaii</b></div>
          <div className="bk-brand-sub">{d.shop.tagline}</div>
        </div>
      </div>
      <nav className="bk-nav">
        {BK_NAV.map((item, i) =>
          item.section ? (
            <div key={i} className="bk-nav__label">{item.section}</div>
          ) : (
            <button key={item.id}
              className={"bk-nav__item" + (page === item.id ? " is-active" : "")}
              onClick={() => { go(item.id); onClose && onClose(); }}>
              <BKIcon name={item.icon} size={18} />
              <span>{item.label}</span>
              {item.badge ? <span className="bk-nav__badge bk-num">{item.badge}</span> : null}
            </button>
          )
        )}
      </nav>
      <div className="bk-sidebar__foot">
        <div className="bk-userchip">
          <div className="bk-avatar">พ</div>
          <div style={{ minWidth: 0 }}>
            <div className="bk-row__title bk-truncate">{d.shop.admin}</div>
            <div className="bk-row__sub">{d.shop.role}</div>
          </div>
          <button className="bk-iconbtn" style={{ marginLeft: "auto", width: 32, height: 32 }}
            title="ออกจากระบบ" onClick={() => go("login")}>
            <BKIcon name="logout" size={15} />
          </button>
        </div>
      </div>
    </aside>
  );
}

function Topbar({ onMenu, theme, toggleTheme, title }) {
  return (
    <div className="bk-topbar">
      <button className="bk-iconbtn bk-menu-btn" onClick={onMenu}><BKIcon name="menu" size={18} /></button>
      <div className="bk-search">
        <BKIcon name="search" size={16} />
        <input placeholder="ค้นหาสินค้า ออเดอร์ ลูกค้า…" />
      </div>
      <div style={{ marginLeft: "auto", display: "flex", gap: 9, alignItems: "center" }}>
        <button className="bk-iconbtn" onClick={toggleTheme} title="สลับโหมดสว่าง/มืด">
          <BKIcon name={theme === "dark" ? "sun" : "moon"} size={17} />
        </button>
        <button className="bk-iconbtn" title="การแจ้งเตือน">
          <BKIcon name="bell" size={17} />
          <span className="bk-iconbtn__dot" />
        </button>
        <button className="bk-btn bk-btn--primary bk-btn--sm bk-hide-mobile" style={{ marginLeft: 2 }}>
          <BKIcon name="plus" size={15} /> ออเดอร์ใหม่
        </button>
      </div>
    </div>
  );
}

function PageHead({ title, sub, children }) {
  return (
    <div className="bk-page__head">
      <div>
        <h1 className="bk-page__title">{title}</h1>
        {sub && <div className="bk-page__sub">{sub}</div>}
      </div>
      {children && <div className="bk-actions">{children}</div>}
    </div>
  );
}

Object.assign(window, { Sidebar, Topbar, PageHead, BK_NAV });
