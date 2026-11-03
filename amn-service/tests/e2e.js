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
    const push = await p.request.post("/api/push", { data: {} });
    check("anon /api/push → 401", push.status() === 401);
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
  check("no start/stop-shift buttons any more", (await tech3.page.locator("button:has-text('เริ่มงาน')").count()) === 0);
  await tech3.page.screenshot({ path: `${SHOTS}/mobile-home.png`, fullPage: true });

  // ถึงหน้างานแล้ว — shares the phone's location, office is notified
  await tech3.page.goto(`/m/jobs/${jobId}`);
  await tech3.page.click("button:has-text('ถึงหน้างานแล้ว')");
  await tech3.page.waitForSelector("text=สรุปงานที่ทำ", { timeout: 20000 });
  check("arrival press shows report form", true);
  await admin.page.goto(`/jobs/${jobId}`);
  check("office sees IN_PROGRESS after arrival", (await admin.page.locator("span.badge", { hasText: "กำลังทำ" }).count()) > 0);
  const arrive = (await admin.page.locator("text=📍 ถึง").first().textContent()) || "";
  check("arrival location + distance recorded", /ดูตำแหน่ง/.test(arrive) && /ห่างจากหน้างาน \d+ ม\./.test(arrive), arrive.replace(/\s+/g, " "));
  await admin.page.goto("/notifications");
  check("office notified: ช่างถึงหน้างานแล้ว", (await admin.page.locator("text=ช่างถึงหน้างานแล้ว").count()) > 0);

  // Hold — shares location again
  await tech3.page.fill("#summary", "E2E: วัดค่า NIBP คลาดเคลื่อน 15 mmHg ต้องเปลี่ยนปั๊มลม");
  await tech3.page.click("label:has-text('ยังไม่เสร็จ')");
  await tech3.page.fill("#holdReason", "รออะไหล่ปั๊มลม");
  await tech3.page.screenshot({ path: `${SHOTS}/mobile-report.png`, fullPage: true });
  await Promise.all([tech3.page.waitForURL(/\/m\?done=/, { timeout: 20000 }), tech3.page.click("button:has-text('บันทึก Hold')")]);
  check("hold submitted → back to /m with message", (await tech3.page.locator("text=บันทึก Hold งาน").count()) > 0);

  await sup.page.goto(`/jobs/${jobId}`);
  check("office sees ON_HOLD", (await sup.page.locator("span.badge", { hasText: "Hold (ยังไม่เสร็จ)" }).count()) > 0);
  const holdLine = (await sup.page.locator("text=⏸️ Hold").first().textContent()) || "";
  check("hold press location recorded", /ดูตำแหน่ง/.test(holdLine) && /ห่างจากหน้างาน/.test(holdLine), holdLine.replace(/\s+/g, " "));

  // second visit: arrive again → เสร็จแล้ว
  await tech3.page.goto(`/m/jobs/${jobId}`);
  await tech3.page.click("button:has-text('ถึงหน้างานแล้ว')");
  await tech3.page.waitForSelector("text=สรุปงานที่ทำ", { timeout: 20000 });
  await tech3.page.fill("#summary", "E2E: เปลี่ยนปั๊มลม NIBP สอบเทียบผ่าน");
  await tech3.page.click("label:has-text('เสร็จแล้ว')");
  await Promise.all([tech3.page.waitForURL(/\/m\?done=/, { timeout: 20000 }), tech3.page.click("button:has-text('ส่งงาน')")]);
  check("completed → message ส่งงาน", (await tech3.page.locator("text=เรียบร้อย").count()) > 0);

  await admin.page.goto(`/jobs/${jobId}`);
  check("office sees COMPLETED", (await admin.page.locator("span.badge", { hasText: "เสร็จแล้ว" }).count()) > 0);
  check("2 visits recorded", (await admin.page.locator("text=รายงานการเข้าหน้างาน (2 ครั้ง)").count()) === 1);
  const doneLine = (await admin.page.locator("text=✅ เสร็จ").first().textContent()) || "";
  check("finish press location recorded", /ดูตำแหน่ง/.test(doneLine), doneLine.replace(/\s+/g, " "));

  await tech3.page.goto(`/m/jobs/${jobId}`);
  check("no arrival button on completed job", (await tech3.page.locator("button:has-text('ถึงหน้างานแล้ว')").count()) === 0);

  // Location permission is required: a phone that blocks Location cannot press ถึงหน้างาน
  const tech1Job = await tech1.page.request.get("/m");
  check("TEC001 home loads", tech1Job.status() === 200);
  await tech1.page.goto("/m");
  await tech1.page.goto((await tech1.page.locator("a[href^='/m/jobs/']").first().getAttribute("href")) || "/m");
  await tech1.page.click("button:has-text('ถึงหน้างานแล้ว')");
  await tech1.page.waitForSelector("p[role=alert]", { timeout: 20000 });
  const deniedText = (await tech1.page.textContent("p[role=alert]")) || "";
  check("no Location permission → arrival blocked with instructions", deniedText.includes("ต้องอนุญาตให้แอปใช้ตำแหน่ง"), deniedText);
  check("…and nothing was recorded", (await tech1.page.locator("text=สรุปงานที่ทำ").count()) === 0);

  // tech history
  await tech3.page.goto("/m/history");
  check("history lists E2E visit", (await tech3.page.locator("text=E2E: เปลี่ยนปั๊มลม").count()) > 0);

  // ---------- supervisor cannot manage employees; manager can ----------
  check("supervisor blocked from /employees", (await status(sup.page, "/employees")).code === 307);
  const mgr = await login(browser, "MGR001");
  await mgr.page.goto("/employees");
  check("manager sees permission matrix", (await mgr.page.locator("text=สิทธิ์การเข้าถึงของแต่ละบทบาท").count()) === 1);
  check("visit export (with arrive/finish locations) downloads", (await mgr.page.request.get("/api/export/visits")).status() === 200);

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

  // ---------- Office map: where technicians pressed ถึง / เสร็จ ----------
  await mgr.page.setViewportSize({ width: 1366, height: 900 });
  await mgr.page.goto("/tracking");
  const t3row = mgr.page.locator("[data-person]", { hasText: "ศุภชัย บุญมา" });
  const t3text = (await t3row.textContent()) || "";
  check("map list shows TEC003's last press (เสร็จงาน)", t3text.includes("เสร็จงาน") && t3text.includes("4 ครั้ง"), t3text.replace(/\s+/g, " "));
  const onSiteRow = (await mgr.page.locator("[data-person]", { hasText: "ธนพล รุ่งเรือง" }).textContent()) || "";
  check("map list shows a technician currently on site", onSiteRow.includes("อยู่หน้างาน"), onSiteRow.replace(/\s+/g, " "));
  await t3row.click();
  await mgr.page.waitForURL(/employee=/);
  check("selected technician shows 4 numbered presses", (await mgr.page.locator("tbody tr").count()) === 4);
  check("map rendered", (await mgr.page.locator(".leaflet-container").count()) === 1);
  await mgr.page.waitForTimeout(1500);
  await mgr.page.screenshot({ path: `${SHOTS}/office-tracking.png`, fullPage: true });
  check("tracking API endpoints are gone", (await mgr.page.request.post("/api/location", { data: {} })).status() === 404);

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
