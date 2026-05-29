/* ===== BabyKawaii — Main app ===== */

const DIR_MAP = { "การ์ดนุ่ม": "a", "เส้นสะอาด": "b", "กะทัดรัด": "c" };
const DENSITY_MAP = { "สบายตา": "regular", "กระชับ": "compact" };

const ACCENTS = {
  "โรสพาสเทล": ["#E8869B", "#D26A82"],
  "เทอร์ราคอตตา": ["#D08C73", "#B66E54"],
  "เซจกรีน": ["#7FA98C", "#5F8C6E"],
  "ฟ้าหม่น": ["#8499C4", "#6477A8"],
  "ลาเวนเดอร์": ["#9D8BC4", "#7C68A8"],
};

const TWEAK_DEFAULTS = /*EDITMODE-BEGIN*/{
  "direction": "การ์ดนุ่ม",
  "theme": "สว่าง",
  "accent": "โรสพาสเทล",
  "density": "สบายตา",
  "emoji": true
}/*EDITMODE-END*/;

const BK_BASE_DATA = window.BK_BASE_DATA || window.BK_DATA;
window.BK_BASE_DATA = BK_BASE_DATA;

function loadBKState(key, fallback) {
  try {
    const saved = localStorage.getItem(key);
    return saved ? JSON.parse(saved) : fallback;
  } catch (e) {
    return fallback;
  }
}

function productStatus(stock) {
  return stock <= 0 ? "out" : stock <= 5 ? "low" : "active";
}

function customerTier(orders) {
  return orders >= 15 ? "VIP" : orders >= 7 ? "เธเธฃเธฐเธเธณ" : "เนเธซเธกเน";
}

function BKApp() {
  const [t, setTweak] = useTweaks(TWEAK_DEFAULTS);
  const [page, setPage] = React.useState("dashboard");
  const [sbOpen, setSbOpen] = React.useState(false);
  const [orders, setOrders] = React.useState(() => loadBKState("bk-orders", BK_BASE_DATA.orders));
  const [products, setProducts] = React.useState(() => loadBKState("bk-products", BK_BASE_DATA.products));
  const [customers, setCustomers] = React.useState(() => loadBKState("bk-customers", BK_BASE_DATA.customerList));

  const dir = DIR_MAP[t.direction] || "a";
  const theme = t.theme === "มืด" ? "dark" : "light";
  const density = DENSITY_MAP[t.density] || "regular";
  const accent = ACCENTS[t.accent] || ACCENTS["โรสพาสเทล"];

  React.useEffect(() => {
    document.body.setAttribute("data-emoji", t.emoji ? "on" : "off");
    document.body.setAttribute("data-theme", theme);
  }, [t.emoji, theme]);

  React.useEffect(() => {
    localStorage.setItem("bk-orders", JSON.stringify(orders));
  }, [orders]);

  React.useEffect(() => {
    localStorage.setItem("bk-products", JSON.stringify(products));
  }, [products]);

  React.useEffect(() => {
    localStorage.setItem("bk-customers", JSON.stringify(customers));
  }, [customers]);

  const liveData = React.useMemo(() => {
    const stockTotal = products.reduce((sum, product) => sum + product.stock, 0);
    const salesTotal = orders.reduce((sum, order) => order.status === "cancelled" ? sum : sum + order.total, 0);
    const platforms = BK_BASE_DATA.platforms.map((platform) => {
      const platformOrders = orders.filter((order) => order.platform && order.platform.key === platform.key && order.status !== "cancelled");
      const total = platformOrders.reduce((sum, order) => sum + order.total, 0);
      return {
        ...platform,
        total: total || platform.total,
        orders: platformOrders.length || platform.orders,
        share: salesTotal ? Math.round(((total || platform.total) / salesTotal) * 100) : platform.share,
      };
    });
    const lowStockItems = products
      .filter((product) => product.stock <= 5)
      .map((product) => ({
        name: product.name,
        sku: product.sku,
        size: BK_BASE_DATA.sizes[(product.id + 1) % BK_BASE_DATA.sizes.length],
        color: BK_BASE_DATA.colorsTH[product.id % BK_BASE_DATA.colorsTH.length],
        qty: product.stock,
      }));
    const activeOrders = orders.filter((order) => order.status !== "cancelled");
    const grossProfitMonth = activeOrders.reduce((sum, order) => {
      const product = products.find((p) => p.name === order.product);
      return sum + (product ? Math.max(0, product.price - product.cost) * (order.items || 1) : Math.round(order.total * .55));
    }, 0);
    return {
      ...BK_BASE_DATA,
      platforms,
      products,
      orders,
      customerList: customers,
      bestSellers: [...products].sort((a, b) => b.sold - a.sold).slice(0, 5),
      lowStockItems,
      kpis: {
        ...BK_BASE_DATA.kpis,
        products: products.length,
        stockTotal,
        salesMonth: salesTotal,
        ordersMonth: activeOrders.length,
        aov: activeOrders.length ? Math.round(salesTotal / activeOrders.length) : 0,
        grossProfitMonth,
        lowStock: lowStockItems.length,
        pending: orders.filter((order) => order.status === "pending").length,
      },
    };
  }, [products, orders, customers]);
  window.BK_DATA = liveData;

  const toggleTheme = () => setTweak("theme", theme === "dark" ? "สว่าง" : "มืด");
  const go = (p) => { setPage(p); window.scrollTo({ top: 0 }); };
  const pendingOrders = orders.filter((order) => order.status === "pending").length;
  const unreadMessages = (window.BK_CONVOS || []).reduce((sum, convo) => sum + (convo.unread || 0), 0);
  const lowStockCount = liveData.lowStockItems.filter((item) => item.qty <= 5).length;
  const badges = { orders: pendingOrders, inbox: unreadMessages, stock: lowStockCount };
  const createOrder = ({ convo, product, size, qty, total }) => {
    const maxNumber = orders.reduce((max, order) => {
      const n = Number(String(order.number || "").replace(/\D/g, ""));
      return Number.isFinite(n) ? Math.max(max, n) : max;
    }, 26049);
    const now = new Date();
    const order = {
      id: Date.now(),
      number: `#BK${maxNumber + 1}`,
      customer: convo.name,
      platform: convo.platform,
      total,
      items: qty,
      status: "pending",
      date: `${now.getDate()}/${String(now.getMonth() + 1).padStart(2, "0")}`,
      time: `${String(now.getHours()).padStart(2, "0")}:${String(now.getMinutes()).padStart(2, "0")}`,
      product: product.name,
      size,
    };
    setOrders((list) => [order, ...list]);
    setProducts((list) => list.map((item) => item.id === product.id ? { ...item, stock: Math.max(0, item.stock - qty), status: item.stock - qty <= 0 ? "out" : item.stock - qty <= 5 ? "low" : "active" } : item));
    setCustomers((list) => {
      const found = list.find((customer) => customer.name === convo.name);
      const last = order.date;
      if (found) {
        return list.map((customer) => {
          if (customer.name !== convo.name) return customer;
          const nextOrders = customer.orders + 1;
          return { ...customer, orders: nextOrders, spent: customer.spent + total, platform: convo.platform, last, tier: customerTier(nextOrders) };
        });
      }
      return [{
        id: Date.now(),
        name: convo.name,
        orders: 1,
        spent: total,
        platform: convo.platform,
        last,
        tier: customerTier(1),
      }, ...list];
    });
    return order;
  };
  const createProduct = (product) => {
    setProducts((list) => {
      const id = Math.max(0, ...list.map((item) => item.id || 0)) + 1;
      const stock = Math.max(0, Number(product.stock) || 0);
      const next = {
        id,
        sku: product.sku || `BK-${1000 + id * 7}`,
        name: product.name,
        category: product.category,
        price: Number(product.price) || 0,
        cost: Number(product.cost) || 0,
        stock,
        sold: 0,
        status: productStatus(stock),
        tint: product.tint || ["#FBE9EE", "#E8EEF8", "#E6F2EC", "#F8EFDD"][id % 4],
      };
      return [next, ...list];
    });
    go("products");
  };
  const restockProduct = (productId, amount = 10) => {
    setProducts((list) => list.map((item) => {
      if (item.id !== productId) return item;
      const stock = item.stock + amount;
      return { ...item, stock, status: productStatus(stock) };
    }));
  };
  const advanceOrder = (orderId) => {
    const flow = ["pending", "confirmed", "packing", "shipped", "delivered"];
    setOrders((list) => list.map((order) => {
      if (order.id !== orderId || order.status === "cancelled") return order;
      const index = flow.indexOf(order.status);
      return index === -1 || index === flow.length - 1 ? order : { ...order, status: flow[index + 1] };
    }));
  };

  const rootStyle = { "--accent": accent[0], "--accent-strong": accent[1] };

  if (page === "login") {
    return (
      <div data-theme={theme} data-dir={dir} data-density={density} style={{ ...rootStyle, minHeight: "100vh" }}>
        <Login go={go} theme={theme} toggleTheme={toggleTheme} />
        <BKTweaks t={t} setTweak={setTweak} />
      </div>
    );
  }

  let screen;
  if (page === "dashboard") screen = <Dashboard dir={dir} go={go} orders={orders} pendingOrders={pendingOrders} lowStockCount={lowStockCount} />;
  else if (page === "products") screen = <Products go={go} />;
  else if (page === "product-add") screen = <ProductAdd go={go} onSaveProduct={createProduct} />;
  else if (page === "stock") screen = <Stock go={go} onRestock={restockProduct} />;
  else if (page === "orders") screen = <Orders go={go} orders={orders} onAdvanceOrder={advanceOrder} />;
  else if (page === "inbox") screen = <Inbox go={go} onCreateOrder={createOrder} />;
  else if (page === "sales") screen = <Sales dir={dir} />;
  else if (page === "customers") screen = <Customers />;
  else if (page === "media") screen = <Media />;
  else if (page === "calendar") screen = <PostCalendar />;
  else if (page === "promotions") screen = <Promotions />;
  else if (page === "users") screen = <Users />;
  else if (page === "platforms") screen = <Platforms />;
  else if (page === "settings") screen = <Settings theme={theme} toggleTheme={toggleTheme} />;
  else screen = <Dashboard dir={dir} go={go} />;

  return (
    <div className="bk-app" data-theme={theme} data-dir={dir} data-density={density} style={rootStyle}>
      <Sidebar page={page} go={go} open={sbOpen} onClose={() => setSbOpen(false)} badges={badges} />
      {sbOpen && <div className="bk-scrim" onClick={() => setSbOpen(false)} />}
      <div className="bk-main">
        <Topbar onMenu={() => setSbOpen(true)} theme={theme} toggleTheme={toggleTheme} />
        {screen}
      </div>
      <BKTweaks t={t} setTweak={setTweak} />
    </div>
  );
}

function BKTweaks({ t, setTweak }) {
  return (
    <TweaksPanel>
      <TweakSection label="เลย์เอาต์ & การจัดวาง" />
      <TweakRadio label="แนวทางดีไซน์" value={t.direction}
        options={["การ์ดนุ่ม", "เส้นสะอาด", "กะทัดรัด"]}
        onChange={(v) => setTweak("direction", v)} />
      <TweakRadio label="ความหนาแน่น" value={t.density}
        options={["สบายตา", "กระชับ"]}
        onChange={(v) => setTweak("density", v)} />
      <TweakSection label="ธีม & สี" />
      <TweakRadio label="โหมด" value={t.theme}
        options={["สว่าง", "มืด"]}
        onChange={(v) => setTweak("theme", v)} />
      <TweakSelect label="สีหลัก" value={t.accent}
        options={Object.keys(ACCENTS)}
        onChange={(v) => setTweak("accent", v)} />
      <TweakSection label="รายละเอียด" />
      <TweakToggle label="แสดงอิโมจิน่ารัก" value={t.emoji}
        onChange={(v) => setTweak("emoji", v)} />
    </TweaksPanel>
  );
}

ReactDOM.createRoot(document.getElementById("root")).render(<BKApp />);
