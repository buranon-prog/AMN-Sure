// End-to-end check of AMN Service against a running server seeded with demo data.
// Usage: BASE=http://localhost:3000 SHOTS=/tmp node tests/e2e.js  (needs the `playwright` package + Chromium)
// It creates jobs and flips roles back and forth — run it against a dev database only.
const { chromium, devices } = require("playwright");

const BASE = process.env.BASE || "http://localhost:3100";
const PW = "ChangeMe123!";
const SHOTS = process.env.SHOTS || ".";
const results = [];
function check(name, cond, extra = "") {
  results.push({ name, ok: !!cond });
  console.log(`${cond ? "PASS" : "FAIL"}  ${name}${extra ? "  — " + extra : ""}`);
}

async function login(browser, code, opts = {}) {
  const ctx = await browser.newContext({ baseURL: BASE, locale: "th-TH", timezoneId: "Asia/Bangkok", ...opts });
  const page = await ctx.newPage();
  page.on("pageerror", (e) => console.log(`  [pageerror ${code}]`, e.message));
  await page.goto("/login");
  await page.fill("#employeeCode", code.toLowerCase()); // case-insensitive login
  await page.fill("#password", PW);
  await Promise.all([
    page.waitForURL((u) => u.pathname === "/m" || u.pathname === "/dashboard", { timeout: 15000 }),
    page.click("button[type=submit]"),
  ]);
  return { ctx, page };
}

async function status(page, path) {
  const r = await page.request.get(path, { maxRedirects: 0 });
  return { code: r.status(), loc: r.headers()["location"] || "" };
}

(async () => {
  const browser = await chromium.launch();

  // ---------- unauthenticated ----------
  {
    const ctx = await browser.newContext({ baseURL: BASE });
    const p = await ctx.newPage();
    const r = await status(p, "/dashboard");
    check("anon /dashboard redirects to login", r.code === 307 && r.loc.includes("/login"));
    const api = await p.request.post("/api/location", { data: { lat: 13, lng: 100 } });
    check("anon /api/location → 401 JSON", api.status() === 401);
    const exp = await p.request.get("/api/export/jobs");
    check("anon export → 401", exp.status() === 401);
    // wrong password
    await p.goto("/login");
    await p.fill("#employeeCode", "TEC004");
    await p.fill("#password", "wrong-password");
    await p.click("button[type=submit]");
    await p.waitForSelector("p[role=alert]");
    check("wrong password shows error", (await p.textContent("p[role=alert]")).includes("ไม่ถูกต้อง"));
    await ctx.close();
  }

  // ---------- TECHNICIAN access limits ----------
  const phone = devices["Pixel 7"];
  const tech1 = await login(browser, "TEC001", { ...phone });
  check("technician lands on /m", new URL(tech1.page.url()).pathname === "/m", tech1.page.url());
  for (const path of ["/dashboard", "/jobs", "/jobs/new", "/customers", "/employees", "/tracking", "/export"]) {
    const r = await status(tech1.page, path);
    check(`technician blocked from ${path}`, r.code === 307 && r.loc.includes("/m"), `${r.code} ${r.loc}`);
  }
  const techExport = await tech1.page.request.get("/api/export/jobs");
  check("technician export → 403", techExport.status() === 403);

  // ---------- ADMIN creates a job ----------
  const admin = await login(browser, "ADM001");
  check("admin lands on /dashboard", new URL(admin.page.url()).pathname === "/dashboard");
  check("admin blocked from /employees", (await status(admin.page, "/employees")).code === 307);
  const admPings = await admin.page.request.get("/api/export/location-pings");
  check("admin cannot export location history (403)", admPings.status() === 403);
  check("admin can export jobs CSV", (await admin.page.request.get("/api/export/jobs")).status() === 200);

  await admin.page.goto("/jobs/new");
  await admin.page.fill('input[aria-label="ค้นหาเครื่อง"]', "M-0002");
  await admin.page.selectOption('select[name="machineId"]', { index: 0 });
  const tomorrow = new Date(Date.now() + 24 * 3600e3 + 7 * 3600e3).toISOString().slice(0, 10);
  await admin.page.fill('input[name="scheduledAt"]', `${tomorrow}T10:30`);
  await admin.page.fill('textarea[name="problem"]', "E2E: ค่า NIBP คลาดเคลื่อน");
  await admin.page.selectOption('select[name="priority"]', "HIGH");
  await Promise.all([admin.page.waitForURL(/\/jobs\/[^/]+\?created=1/), admin.page.click("text=บันทึกงาน")]);
  const jobUrl = admin.page.url().split("?")[0];
  const jobId = jobUrl.split("/").pop();
  const header = await admin.page.textContent("h1");
  check("job created with JOB- number", /JOB-\d{4}-\d{4}/.test(header), header);
  check("admin sees no assign panel", (await admin.page.locator("text=จ่ายงานตามความถนัด").count()) === 0);

  // ---------- SUPERVISOR assigns by skill ----------
  const sup = await login(browser, "SUP001");
  await sup.page.goto("/dashboard");
  const bell = await sup.page.getAttribute('a[href="/notifications"] >> nth=0', "aria-label");
  check("supervisor notified of new job", /การแจ้งเตือน [1-9]/.test(bell || ""), bell);
  await sup.page.goto(`/jobs/${jobId}`);
  const firstCandidate = (await sup.page.locator("section:has-text('จ่ายงานตามความถนัด') li").first().textContent()) || "";
  check("top candidate for MX450 is a level-4 monitor tech", /ชำนาญ/.test(firstCandidate), firstCandidate.slice(0, 80));
  const row = sup.page.locator("section:has-text('จ่ายงานตามความถนัด') li", { hasText: "ศุภชัย" });
  await row.locator("button:has-text('จ่ายงาน')").click();
  await sup.page.waitForSelector("text=★");
  await sup.page.waitForTimeout(500);
  check("job status → จ่ายงานแล้ว", (await sup.page.locator("span.badge", { hasText: "จ่ายงานแล้ว" }).count()) > 0);
  await sup.page.screenshot({ path: `${SHOTS}/office-job-assigned.png`, fullPage: true });

  // ---------- TECHNICIAN (TEC003) gets notified, checks in, holds, completes ----------
  const siteGeo = { latitude: 13.7369, longitude: 100.5603, accuracy: 10 };
  const tech3 = await login(browser, "TEC003", { ...phone, geolocation: siteGeo, permissions: ["geolocation"] });
  const tBell = await tech3.page.getAttribute('a[href="/notifications"]', "aria-label");
  check("technician sees unread notification", /การแจ้งเตือน [1-9]/.test(tBell || ""), tBell);
  await tech3.page.goto("/notifications");
  check("notification says ได้รับงานใหม่", (await tech3.page.locator("text=ได้รับงานใหม่").count()) > 0);

  // technician cannot open a job not assigned to them
  const other = await tech3.page.request.get(`/m/jobs/${jobId}`);
  check("technician can open own job", other.status() === 200);
  const tech1Other = await tech1.page.request.get(`/m/jobs/${jobId}`);
  check("other technician gets 404 for job not theirs", tech1Other.status() === 404);

  await tech3.page.goto("/m");
  await tech3.page.click("button:has-text('เริ่มงาน')");
  await tech3.page.waitForSelector("button:has-text('เลิกงาน')", { timeout: 20000 });
  check("shift started", true);
  await tech3.page.waitForSelector("text=/แชร์ตำแหน่งสด|นอกช่วงเวลาติดตาม/", { timeout: 20000 }).catch(() => {});
  const trackTxt = await tech3.page.textContent("header");
  check("tracker shows sharing status", /แชร์ตำแหน่งสด|นอกช่วงเวลา/.test(trackTxt), trackTxt.replace(/\s+/g, " ").slice(0, 120));
  await tech3.page.screenshot({ path: `${SHOTS}/mobile-home.png`, fullPage: true });

  // check in
  await tech3.page.goto(`/m/jobs/${jobId}`);
  await tech3.page.click("text=เช็คอินถึงหน้างาน");
  await tech3.page.waitForSelector("text=สรุปงานที่ทำ", { timeout: 20000 });
  check("check-in shows report form", true);

  // report without outcome → browser validation keeps us here; fill HOLD
  await tech3.page.fill("#summary", "E2E: วัดค่า NIBP คลาดเคลื่อน 15 mmHg ต้องเปลี่ยนปั๊มลม");
  await tech3.page.click("text=ยังไม่เสร็จ");
  await tech3.page.fill("#holdReason", "รออะไหล่ปั๊มลม");
  await tech3.page.screenshot({ path: `${SHOTS}/mobile-report.png`, fullPage: true });
  await Promise.all([tech3.page.waitForURL(/\/m\?done=/, { timeout: 20000 }), tech3.page.click("button:has-text('บันทึก Hold')")]);
  check("hold submitted → back to /m with message", (await tech3.page.locator("text=บันทึก Hold งาน").count()) > 0);

  await sup.page.goto(`/jobs/${jobId}`);
  check("office sees ON_HOLD", (await sup.page.locator("span.badge", { hasText: "Hold (ยังไม่เสร็จ)" }).count()) > 0);
  const dist = await sup.page.locator("text=ห่างจากหน้างาน").first().textContent();
  check("visit records distance from site", /ห่างจากหน้างาน \d+ ม\./.test(dist || ""), dist);

  // second visit → complete
  await tech3.page.goto(`/m/jobs/${jobId}`);
  await tech3.page.click("text=เช็คอินถึงหน้างาน");
  await tech3.page.waitForSelector("text=สรุปงานที่ทำ", { timeout: 20000 });
  await tech3.page.fill("#summary", "E2E: เปลี่ยนปั๊มลม NIBP สอบเทียบผ่าน");
  await tech3.page.click("text=เสร็จแล้ว");
  await Promise.all([tech3.page.waitForURL(/\/m\?done=/, { timeout: 20000 }), tech3.page.click("button:has-text('ส่งงาน')")]);
  check("completed → message ส่งงาน", (await tech3.page.locator("text=เรียบร้อย").count()) > 0);

  await admin.page.goto(`/jobs/${jobId}`);
  check("office sees COMPLETED", (await admin.page.locator("span.badge", { hasText: "เสร็จแล้ว" }).count()) > 0);
  check("2 visits recorded", (await admin.page.locator("text=รายงานการเข้าหน้างาน (2 ครั้ง)").count()) === 1);
  const aBell = await admin.page.getAttribute('a[href="/notifications"] >> nth=0', "aria-label");
  check("admin notified on hold/complete", /การแจ้งเตือน [1-9]/.test(aBell || ""), aBell);

  // completed job: technician cannot check in again (server-side guard via direct action is covered by UI absence)
  await tech3.page.goto(`/m/jobs/${jobId}`);
  check("no check-in button on completed job", (await tech3.page.locator("text=เช็คอินถึงหน้างาน").count()) === 0);

  // location API: accepted once, then throttled
  const ping1 = await tech3.page.request.post("/api/location", { data: { lat: 13.74, lng: 100.55, accuracy: 20 } });
  const j1 = await ping1.json();
  check("location ping handled", ping1.status() === 200, JSON.stringify(j1));
  const ping2 = await tech3.page.request.post("/api/location", { data: { lat: 13.74, lng: 100.55, accuracy: 20 } });
  const j2 = await ping2.json();
  check("rapid second ping throttled or outside hours", j2.accepted === false, JSON.stringify(j2));
  // end shift
  await tech3.page.goto("/m");
  tech3.page.once("dialog", (d) => d.accept());
  await tech3.page.click("button:has-text('เลิกงาน')");
  await tech3.page.waitForSelector("button:has-text('เริ่มงาน')", { timeout: 20000 });
  const ping3 = await tech3.page.request.post("/api/location", { data: { lat: 13.74, lng: 100.55 } });
  check("after ending shift, location is rejected", (await ping3.json()).reason === "NO_SHIFT");

  // tech history
  await tech3.page.goto("/m/history");
  check("history lists E2E visit", (await tech3.page.locator("text=E2E: เปลี่ยนปั๊มลม").count()) > 0);

  // ---------- supervisor cannot manage employees; manager can ----------
  check("supervisor blocked from /employees", (await status(sup.page, "/employees")).code === 307);
  const mgr = await login(browser, "MGR001");
  await mgr.page.goto("/employees");
  check("manager sees permission matrix", (await mgr.page.locator("text=สิทธิ์การเข้าถึงของแต่ละบทบาท").count()) === 1);
  check("manager can export location history", (await mgr.page.request.get("/api/export/location-pings")).status() === 200);

  const tech4 = await login(browser, "TEC004", { ...phone });
  // Guard: a technician with open jobs cannot be moved to a non-field role
  await mgr.page.goto("/employees");
  await mgr.page.click("text=TEC004");
  await mgr.page.check('input[name="role"][value="ADMIN"]');
  await mgr.page.click("form:has(input[name=role]) button[type=submit]");
  await mgr.page.waitForSelector("p[role=alert]");
  check("cannot demote technician who still has open jobs", (await mgr.page.textContent("p[role=alert]")).includes("งานค้าง"));

  // Role change takes effect immediately, no re-login: SUP002 → ADMIN loses the field screens
  const sup2 = await login(browser, "SUP002", { ...phone });
  check("SUP002 (supervisor) can open /m", (await status(sup2.page, "/m")).code === 200);
  await mgr.page.goto("/employees");
  await mgr.page.click("text=SUP002");
  await mgr.page.check('input[name="role"][value="ADMIN"]');
  await mgr.page.click("form:has(input[name=role]) button[type=submit]");
  await mgr.page.waitForSelector("text=สิทธิ์ใหม่มีผลทันที");
  check("SUP002 now ADMIN is blocked from /m without re-login", (await status(sup2.page, "/m")).code === 307);
  await mgr.page.check('input[name="role"][value="SUPERVISOR"]');
  await mgr.page.click("form:has(input[name=role]) button[type=submit]");
  await mgr.page.waitForTimeout(800);
  check("SUP002 restored to SUPERVISOR", (await status(sup2.page, "/m")).code === 200);

  // Deactivation kicks the user out immediately
  await mgr.page.goto("/employees");
  await mgr.page.click("text=TEC004");
  mgr.page.once("dialog", (d) => d.accept());
  await mgr.page.click("button:has-text('ปิดบัญชี')");
  await mgr.page.waitForSelector("text=เข้าสู่ระบบไม่ได้ทันที");
  const t4 = await status(tech4.page, "/m");
  check("deactivated TEC004 is sent to login immediately", t4.code === 307 && t4.loc.includes("/login"), `${t4.code} ${t4.loc}`);
  await mgr.page.click("button:has-text('เปิดใช้งานบัญชี')");
  await mgr.page.waitForSelector("text=เปิดใช้งานบัญชีแล้ว");

  // ---------- REALTIME tracking ----------
  const { request } = require("playwright");
  const api = await request.newContext({ baseURL: BASE });
  async function tokenFor(page) {
    await page.goto("/m/tracker");
    await page.click("button:has-text('สร้างรหัสเชื่อมต่อ')");
    const input = page.locator("div:has(> div:text-is('Device identifier')) input");
    await input.waitFor({ timeout: 15000 });
    return input.inputValue();
  }
  const tok1 = await tokenFor(tech1.page);
  check("technician can create a tracking-app token", tok1.length >= 30);

  await mgr.page.setViewportSize({ width: 1366, height: 900 });
  await mgr.page.goto("/tracking");
  await mgr.page.waitForSelector("text=เชื่อมต่อสด", { timeout: 15000 });
  check("office map connects to live stream", true);
  const pinSel = '.leaflet-marker-icon[title="อนุชา แก้วใส (เบิร์ด)"]';
  await mgr.page.waitForSelector(pinSel);
  const before = await mgr.page.getAttribute(pinSel, "style");

  const t0 = Math.floor(Date.now() / 1000);
  const own = await api.post(`/api/track?token=${tok1}`, {
    data: { _type: "location", lat: 13.7465, lon: 100.5349, tst: t0, acc: 8, vel: 42, cog: 90, batt: 77, tid: "B1" },
  });
  check("OwnTracks point stored", own.status() === 200 && (await own.text()) === "[]" && own.headers()["x-tracking-result"] === "STORED:1", own.headers()["x-tracking-result"]);

  const row1 = mgr.page.locator('[data-person]', { hasText: "อนุชา แก้วใส" });
  await row1.locator("text=42 กม./ชม.").waitFor({ timeout: 10000 }).catch(() => {});
  const rowText = (await row1.textContent()) || "";
  check("office list updates live (speed, battery, LIVE, background icon)", /42 กม\.\/ชม\./.test(rowText) && rowText.includes("77%") && rowText.includes("LIVE") && rowText.includes("📲"), rowText.replace(/\s+/g, " "));
  await mgr.page.waitForTimeout(1200); // marker glides for ~0.9 s
  const after = await mgr.page.getAttribute(pinSel, "style");
  check("map pin moved without reloading the page", before !== after);

  const osm = await api.get(`/api/track?id=${tok1}&lat=13.7480&lon=100.5390&timestamp=${t0 + 10}&speed=10&bearing=45&batt=76`);
  check("OsmAnd/Traccar query point stored", osm.headers()["x-tracking-result"] === "STORED:1", osm.headers()["x-tracking-result"]);
  const trJson = await api.post("/api/track", {
    data: {
      device_id: tok1,
      location: { timestamp: new Date((t0 + 20) * 1000).toISOString(), coords: { latitude: 13.749, longitude: 100.541, speed: 5, heading: 180, accuracy: 6 }, battery: { level: 0.75 } },
    },
  });
  check("Traccar JSON point stored", trJson.headers()["x-tracking-result"] === "STORED:1", trJson.headers()["x-tracking-result"]);
  const bad = await api.post("/api/track?token=this-is-not-a-valid-token-123", { data: { _type: "location", lat: 13, lon: 100, tst: t0 } });
  check("unknown token rejected (401)", bad.status() === 401);
  const tok4 = await tokenFor(tech4.page);
  const noShift = await api.post(`/api/track?token=${tok4}`, { data: { _type: "location", lat: 13.75, lon: 100.5, tst: t0 } });
  check("background point without an open shift is dropped", noShift.status() === 200 && noShift.headers()["x-tracking-result"] === "NO_SHIFT", noShift.headers()["x-tracking-result"]);

  // Trail for the selected technician includes the background-app points
  await row1.click();
  await mgr.page.waitForSelector("td:text-is('แอปติดตามเบื้องหลัง')", { timeout: 10000 });
  check("selected technician's trail lists background-app points", (await mgr.page.locator("td:text-is('แอปติดตามเบื้องหลัง')").count()) >= 3);
  await mgr.page.waitForTimeout(1500); // let the map finish its zoom animation
  await mgr.page.screenshot({ path: `${SHOTS}/office-tracking-live.png`, fullPage: true });

  // Web app on the phone streams while open: move the phone, office sees it
  const supPhone = await login(browser, "SUP001", { ...phone, geolocation: { latitude: 13.7563, longitude: 100.5018, accuracy: 10 }, permissions: ["geolocation"] });
  await supPhone.page.goto("/m"); // supervisors land on the office dashboard
  await supPhone.page.waitForSelector("text=แชร์ตำแหน่งสด", { timeout: 20000 });
  check("phone web app shows live sharing", true);
  await supPhone.page.screenshot({ path: `${SHOTS}/mobile-live.png` });
  await mgr.page.click("button:has-text('ดูทุกคน')");
  const supPin = '.leaflet-marker-icon[title="สมชาย ทองดี (เหน่ง)"]';
  const supRow = mgr.page.locator('[data-person]', { hasText: "สมชาย ทองดี" });
  await supRow.locator("[data-live]").waitFor({ timeout: 20000 }).catch(() => {});
  check("phone web app point shows as LIVE in the office", (await supRow.locator("[data-live]").count()) === 1);
  await mgr.page.waitForTimeout(1200);
  const supBefore = await mgr.page.getAttribute(supPin, "style");
  await supPhone.page.waitForTimeout(8000); // moving cadence: ≥ 8 s between points
  await supPhone.ctx.setGeolocation({ latitude: 13.7300, longitude: 100.5230, accuracy: 10 });
  let moved = false;
  for (let i = 0; i < 20 && !moved; i++) {
    await mgr.page.waitForTimeout(1000);
    moved = (await mgr.page.getAttribute(supPin, "style")) !== supBefore;
  }
  check("phone movement moves the pin on the office map within seconds", moved);
  await api.dispose();

  // ---------- screenshots of office pages ----------
  await mgr.page.setViewportSize({ width: 1366, height: 900 });
  for (const [path, name] of [
    ["/dashboard", "office-dashboard"],
    ["/jobs", "office-jobs"],
    ["/technicians", "office-technicians"],
  ]) {
    await mgr.page.goto(path);
    await mgr.page.waitForTimeout(path === "/tracking" ? 2500 : 300);
    await mgr.page.screenshot({ path: `${SHOTS}/${name}.png`, fullPage: true });
  }

  // admin on a phone: office layout works on mobile
  const admPhone = await login(browser, "ADM001", { ...phone });
  await admPhone.page.goto("/dashboard");
  await admPhone.page.screenshot({ path: `${SHOTS}/office-phone.png`, fullPage: false });
  const overflow = await admPhone.page.evaluate(() => document.documentElement.scrollWidth > window.innerWidth + 1);
  check("office dashboard has no horizontal page scroll on phone", !overflow);

  await browser.close();
  const failed = results.filter((r) => !r.ok);
  console.log(`\n${results.length - failed.length}/${results.length} checks passed`);
  process.exit(failed.length ? 1 : 0);
})().catch((e) => {
  console.error(e);
  process.exit(2);
});
