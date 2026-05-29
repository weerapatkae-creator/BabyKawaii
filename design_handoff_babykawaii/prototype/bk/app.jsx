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

function BKApp() {
  const [t, setTweak] = useTweaks(TWEAK_DEFAULTS);
  const [page, setPage] = React.useState("dashboard");
  const [sbOpen, setSbOpen] = React.useState(false);

  const dir = DIR_MAP[t.direction] || "a";
  const theme = t.theme === "มืด" ? "dark" : "light";
  const density = DENSITY_MAP[t.density] || "regular";
  const accent = ACCENTS[t.accent] || ACCENTS["โรสพาสเทล"];

  React.useEffect(() => {
    document.body.setAttribute("data-emoji", t.emoji ? "on" : "off");
    document.body.setAttribute("data-theme", theme);
  }, [t.emoji, theme]);

  const toggleTheme = () => setTweak("theme", theme === "dark" ? "สว่าง" : "มืด");
  const go = (p) => { setPage(p); window.scrollTo({ top: 0 }); };

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
  if (page === "dashboard") screen = <Dashboard dir={dir} go={go} />;
  else if (page === "products") screen = <Products go={go} />;
  else if (page === "product-add") screen = <ProductAdd go={go} />;
  else if (page === "stock") screen = <Stock go={go} />;
  else if (page === "orders") screen = <Orders go={go} />;
  else if (page === "inbox") screen = <Inbox />;
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
      <Sidebar page={page} go={go} open={sbOpen} onClose={() => setSbOpen(false)} />
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
