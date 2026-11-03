// Seed: accounts for every role + (optionally) demo customers, machines and jobs.
//
//   npm run db:seed                    → accounts + demo data
//   SEED_DEMO=false npm run db:seed    → only the first manager account (for production)
//   SEED_PASSWORD=... npm run db:seed  → initial password for seeded accounts
//
// Safe to re-run: accounts/catalog are upserted, demo jobs are only created
// when the database has no jobs yet.

import { PrismaClient } from "@prisma/client";
import bcrypt from "bcryptjs";
import { dateKey, parseLocalDateTime } from "../src/lib/datetime";

const prisma = new PrismaClient();
const PASSWORD = process.env.SEED_PASSWORD || "ChangeMe123!";
const DEMO = process.env.SEED_DEMO !== "false";

const MIN = 60_000;
const HOUR = 60 * MIN;
const DAY = 24 * HOUR;

function today(hhmm: string, plusDays = 0) {
  const base = parseLocalDateTime(`${dateKey()}T${hhmm}`)!;
  return new Date(base.getTime() + plusDays * DAY);
}

async function main() {
  const passwordHash = await bcrypt.hash(PASSWORD, 10);

  const people = [
    { employeeCode: "MGR001", firstName: "วิชัย", lastName: "ศรีสุข", nickname: null, role: "MANAGER", position: "ผู้จัดการแผนก Services", phone: "081-000-0001" },
    ...(DEMO
      ? [
          { employeeCode: "ADM001", firstName: "กมลวรรณ", lastName: "ใจดี", nickname: "แนน", role: "ADMIN", position: "แอดมินงานบริการ", phone: "081-000-0002" },
          { employeeCode: "SUP001", firstName: "สมชาย", lastName: "ทองดี", nickname: "เหน่ง", role: "SUPERVISOR", position: "หัวหน้าช่าง", phone: "081-000-0003" },
          { employeeCode: "SUP002", firstName: "ประเสริฐ", lastName: "มั่นคง", nickname: "โด่ง", role: "SUPERVISOR", position: "หัวหน้าช่าง", phone: "081-000-0004" },
          { employeeCode: "TEC001", firstName: "อนุชา", lastName: "แก้วใส", nickname: "เบิร์ด", role: "TECHNICIAN", position: "ช่างเทคนิค", phone: "081-000-0011" },
          { employeeCode: "TEC002", firstName: "ธนพล", lastName: "รุ่งเรือง", nickname: "ต้น", role: "TECHNICIAN", position: "ช่างเทคนิค", phone: "081-000-0012" },
          { employeeCode: "TEC003", firstName: "ศุภชัย", lastName: "บุญมา", nickname: "เอ็ม", role: "TECHNICIAN", position: "ช่างเทคนิค", phone: "081-000-0013" },
          { employeeCode: "TEC004", firstName: "ณัฐวุฒิ", lastName: "พรหมมา", nickname: "บอส", role: "TECHNICIAN", position: "ช่างเทคนิคฝึกหัด", phone: "081-000-0014" },
        ]
      : []),
  ];

  const emp: Record<string, string> = {};
  for (const p of people) {
    const row = await prisma.employee.upsert({
      where: { employeeCode: p.employeeCode },
      update: {},
      create: { ...p, passwordHash },
    });
    emp[p.employeeCode] = row.id;
  }

  if (!DEMO) {
    console.log(`Seed complete. Manager login: MGR001 / ${PASSWORD} — change it right away.`);
    return;
  }

  // ---------- Catalog ----------
  const modelDefs = [
    { key: "US1", brand: "GE Healthcare", model: "Voluson E8", category: "เครื่องอัลตราซาวด์" },
    { key: "US2", brand: "Philips", model: "EPIQ 7", category: "เครื่องอัลตราซาวด์" },
    { key: "DEF", brand: "Mindray", model: "BeneHeart D6", category: "เครื่องกระตุกหัวใจ" },
    { key: "MON", brand: "Philips", model: "IntelliVue MX450", category: "เครื่องติดตามสัญญาณชีพ" },
    { key: "HD", brand: "Fresenius", model: "4008S", category: "เครื่องฟอกไต" },
    { key: "VEN", brand: "Dräger", model: "Savina 300", category: "เครื่องช่วยหายใจ" },
  ];
  const model: Record<string, string> = {};
  for (const m of modelDefs) {
    const { key, ...data } = m;
    const row = await prisma.machineModel.upsert({
      where: { brand_model: { brand: data.brand, model: data.model } },
      update: {},
      create: data,
    });
    model[key] = row.id;
  }

  const skills: [string, string, number][] = [
    ["SUP001", "US1", 5], ["SUP001", "US2", 4], ["SUP001", "MON", 4], ["SUP001", "DEF", 3],
    ["SUP002", "HD", 5], ["SUP002", "VEN", 5], ["SUP002", "MON", 3],
    ["TEC001", "US1", 4], ["TEC001", "US2", 3], ["TEC001", "MON", 2],
    ["TEC002", "HD", 4], ["TEC002", "VEN", 3],
    ["TEC003", "DEF", 4], ["TEC003", "MON", 4], ["TEC003", "US2", 2],
    ["TEC004", "MON", 2], ["TEC004", "DEF", 1],
  ];
  for (const [code, m, level] of skills) {
    await prisma.employeeSkill.upsert({
      where: { employeeId_machineModelId: { employeeId: emp[code], machineModelId: model[m] } },
      update: {},
      create: { employeeId: emp[code], machineModelId: model[m], level },
    });
  }

  if ((await prisma.job.count()) > 0) {
    console.log("Jobs already exist — skipped demo customers/jobs.");
    return;
  }

  // ---------- Customers, sites, machines (fictional) ----------
  const c1 = await prisma.customer.create({
    data: {
      customerCode: "C-0001",
      name: "โรงพยาบาลตัวอย่างสุขุมวิท",
      type: "HOSPITAL",
      contactName: "คุณพรทิพย์ (ฝ่ายเครื่องมือแพทย์)",
      contactPhone: "02-000-1001",
      sites: {
        create: [
          { name: "อาคารหลัก", address: "ถนนสุขุมวิท แขวงคลองเตยเหนือ เขตวัฒนา", province: "กรุงเทพฯ", latitude: 13.7367, longitude: 100.56, contactName: "คุณพรทิพย์", contactPhone: "02-000-1001" },
          { name: "สาขาบางนา", address: "ถนนบางนา-ตราด กม.3", province: "กรุงเทพฯ", latitude: 13.668, longitude: 100.634, contactName: "คุณวิไล", contactPhone: "02-000-1002" },
        ],
      },
    },
    include: { sites: true },
  });
  const c2 = await prisma.customer.create({
    data: {
      customerCode: "C-0002",
      name: "คลินิกหัวใจดี",
      type: "CLINIC",
      contactName: "นพ.ธีระ",
      contactPhone: "02-000-2001",
      sites: { create: [{ name: "สำนักงาน", address: "ถนนพระราม 4 เขตปทุมวัน", province: "กรุงเทพฯ", latitude: 13.7308, longitude: 100.5359, contactPhone: "02-000-2001" }] },
    },
    include: { sites: true },
  });
  const c3 = await prisma.customer.create({
    data: {
      customerCode: "C-0003",
      name: "ศูนย์ไตเทียมรังสิต",
      type: "CLINIC",
      contactName: "คุณสุนีย์",
      contactPhone: "02-000-3001",
      sites: { create: [{ name: "สาขารังสิต", address: "ถนนพหลโยธิน ต.ประชาธิปัตย์ อ.ธัญบุรี", province: "ปทุมธานี", latitude: 13.987, longitude: 100.617, contactName: "คุณสุนีย์", contactPhone: "02-000-3001" }] },
    },
    include: { sites: true },
  });
  const c4 = await prisma.customer.create({
    data: {
      customerCode: "C-0004",
      name: "โรงพยาบาลตัวอย่างนนทบุรี",
      type: "HOSPITAL",
      contactName: "คุณอรุณ",
      contactPhone: "02-000-4001",
      sites: { create: [{ name: "อาคารผู้ป่วยใน", address: "ถนนรัตนาธิเบศร์ อ.เมือง", province: "นนทบุรี", latitude: 13.859, longitude: 100.521, contactName: "คุณอรุณ", contactPhone: "02-000-4001" }] },
    },
    include: { sites: true },
  });

  const machineDefs = [
    { code: "M-0001", site: c1.sites[0].id, model: "US1", serial: "VE8-11872", loc: "อาคาร A ชั้น 3 ห้องสูตินรีเวช" },
    { code: "M-0002", site: c1.sites[0].id, model: "MON", serial: "MX4-55120", loc: "ICU ชั้น 5 เตียง 3" },
    { code: "M-0003", site: c1.sites[1].id, model: "US2", serial: "EP7-30019", loc: "แผนกรังสี ชั้น 1" },
    { code: "M-0004", site: c2.sites[0].id, model: "DEF", serial: "BD6-77231", loc: "ห้องฉุกเฉิน" },
    { code: "M-0005", site: c3.sites[0].id, model: "HD", serial: "4008-90311", loc: "ห้องฟอกไต เครื่องที่ 4" },
    { code: "M-0006", site: c3.sites[0].id, model: "HD", serial: "4008-90318", loc: "ห้องฟอกไต เครื่องที่ 7" },
    { code: "M-0007", site: c4.sites[0].id, model: "VEN", serial: "SV3-11207", loc: "ICU อายุรกรรม" },
    { code: "M-0008", site: c4.sites[0].id, model: "MON", serial: "MX4-55331", loc: "หอผู้ป่วยใน ชั้น 4" },
  ];
  const machine: Record<string, string> = {};
  for (const m of machineDefs) {
    const row = await prisma.machine.create({
      data: {
        machineCode: m.code,
        siteId: m.site,
        machineModelId: model[m.model],
        serialNumber: m.serial,
        locationDetail: m.loc,
        installDate: new Date(Date.now() - 600 * DAY),
        warrantyEnd: new Date(Date.now() + (m.code === "M-0004" ? -30 : 200) * DAY),
      },
    });
    machine[m.code] = row.id;
  }

  // ---------- Jobs in every state ----------
  const now = Date.now();
  const admin = emp.ADM001;
  const sup = emp.SUP001;
  let seq = 0;
  const ym = dateKey().slice(2, 7).replace("-", "");
  const nextNo = () => `JOB-${ym}-${String(++seq).padStart(4, "0")}`;

  type Visit = {
    who: string;
    inAt: Date;
    outAt?: Date;
    summary?: string;
    outcome?: "HOLD" | "COMPLETED";
    hold?: string;
    next?: Date;
    lat?: number;
    lng?: number;
    dist?: number;
  };
  async function job(opts: {
    machine: string;
    type?: string;
    priority?: string;
    problem: string;
    scheduledAt: Date;
    status: string;
    techs?: string[];
    createdAt?: Date;
    visits?: Visit[];
    resolution?: string;
  }) {
    const created = opts.createdAt ?? new Date(opts.scheduledAt.getTime() - DAY);
    const completed = opts.status === "COMPLETED" ? opts.visits?.find((v) => v.outcome === "COMPLETED")?.outAt : undefined;
    const j = await prisma.job.create({
      data: {
        jobNo: nextNo(),
        machineId: machine[opts.machine],
        type: opts.type ?? "REPAIR",
        priority: opts.priority ?? "NORMAL",
        problem: opts.problem,
        scheduledAt: opts.scheduledAt,
        status: opts.status,
        createdById: admin,
        createdAt: created,
        resolution: opts.resolution ?? null,
        completedAt: completed ?? null,
        assignedById: opts.techs?.length ? sup : null,
        assignedAt: opts.techs?.length ? new Date(created.getTime() + HOUR) : null,
        reporterName: "เจ้าหน้าที่หน้างาน",
      },
    });
    await prisma.jobEvent.create({ data: { jobId: j.id, actorId: admin, type: "CREATED", message: "ลงงาน", createdAt: created } });
    for (const [i, code] of (opts.techs ?? []).entries()) {
      await prisma.jobAssignment.create({ data: { jobId: j.id, employeeId: emp[code], isLead: i === 0, assignedById: sup } });
      await prisma.jobEvent.create({ data: { jobId: j.id, actorId: sup, type: "ASSIGNED", message: `จ่ายงานให้ ${code}`, createdAt: new Date(created.getTime() + HOUR) } });
    }
    for (const v of opts.visits ?? []) {
      await prisma.jobVisit.create({
        data: {
          jobId: j.id,
          employeeId: emp[v.who],
          checkInAt: v.inAt,
          checkInLat: v.lat ?? null,
          checkInLng: v.lng ?? null,
          checkInAccuracy: v.lat ? 12 : null,
          checkInDistanceM: v.dist ?? null,
          checkOutAt: v.outAt ?? null,
          summary: v.summary ?? null,
          outcome: v.outcome ?? null,
          holdReason: v.hold ?? null,
          nextVisitAt: v.next ?? null,
        },
      });
      await prisma.jobEvent.create({ data: { jobId: j.id, actorId: emp[v.who], type: "CHECK_IN", message: "เช็คอินหน้างาน", createdAt: v.inAt } });
      if (v.outcome) {
        await prisma.jobEvent.create({
          data: { jobId: j.id, actorId: emp[v.who], type: v.outcome, message: v.outcome === "HOLD" ? v.hold! : "ส่งงาน", createdAt: v.outAt },
        });
      }
    }
    return j;
  }

  await job({ machine: "M-0004", priority: "HIGH", problem: "เครื่องไม่ชาร์จพลังงาน มีเสียงเตือน Battery Fault", scheduledAt: today("10:00", 1), status: "NEW", createdAt: new Date(now - 2 * HOUR) });
  await job({ machine: "M-0007", type: "PM", problem: "บำรุงรักษาตามรอบ 6 เดือน + สอบเทียบ Flow sensor", scheduledAt: today("09:00", 2), status: "NEW", createdAt: new Date(now - 5 * HOUR) });
  const assigned = await job({ machine: "M-0001", problem: "ภาพจากหัวโพรบ 4D มีเส้นรบกวน", scheduledAt: today("13:30"), status: "ASSIGNED", techs: ["TEC001"] });
  await job({ machine: "M-0003", type: "INSPECTION", problem: "ตรวจเช็คก่อนหมดประกัน", scheduledAt: today("10:00", 3), status: "ASSIGNED", techs: ["TEC001", "TEC004"] });
  const inProgress = await job({
    machine: "M-0005",
    priority: "URGENT",
    problem: "Conductivity alarm ระหว่างฟอกไต",
    scheduledAt: today("09:30"),
    status: "IN_PROGRESS",
    techs: ["TEC002"],
    visits: [{ who: "TEC002", inAt: new Date(now - 50 * MIN), lat: 13.9873, lng: 100.6168, dist: 40 }],
  });
  await job({
    machine: "M-0002",
    problem: "SpO2 module อ่านค่าไม่ขึ้นเป็นบางครั้ง",
    scheduledAt: today("10:00", 3),
    status: "ON_HOLD",
    techs: ["TEC003"],
    createdAt: new Date(now - 4 * DAY),
    visits: [
      {
        who: "TEC003",
        inAt: new Date(now - 2 * DAY - 3 * HOUR),
        outAt: new Date(now - 2 * DAY - HOUR),
        summary: "ตรวจพบ SpO2 module เสื่อม ทดลองเปลี่ยนสายแล้วอาการยังอยู่",
        outcome: "HOLD",
        hold: "รออะไหล่ SpO2 module จากบริษัทแม่",
        next: today("10:00", 3),
        lat: 13.7369,
        lng: 100.5603,
        dist: 35,
      },
    ],
  });

  const doneDefs: { m: string; tech: string[]; days: number; problem: string; fix: string; two?: boolean; type?: string }[] = [
    { m: "M-0006", tech: ["SUP002"], days: 1, problem: "ปั๊มเลือดมีเสียงดัง", fix: "เปลี่ยนลูกปืนปั๊มเลือด ทดสอบเดินเครื่อง 30 นาที ปกติ" },
    { m: "M-0008", tech: ["TEC003"], days: 3, problem: "หน้าจอกระพริบ", fix: "ขันสายแพหน้าจอใหม่ ทำความสะอาดคอนเนคเตอร์ ใช้งานปกติ" },
    { m: "M-0001", tech: ["TEC001", "SUP001"], days: 6, problem: "เปิดเครื่องไม่ติด", fix: "เปลี่ยน Power supply board ทดสอบภาพครบทุกโหมด", two: true },
    { m: "M-0004", tech: ["TEC003"], days: 9, problem: "PM ประจำปี", fix: "ทดสอบพลังงาน 360J ผ่าน, เปลี่ยนแบตเตอรี่", type: "PM" },
    { m: "M-0007", tech: ["SUP002", "TEC002"], days: 12, problem: "Alarm O2 sensor", fix: "เปลี่ยน O2 sensor และสอบเทียบ" },
    { m: "M-0005", tech: ["TEC002"], days: 35, problem: "อุณหภูมิน้ำยาไม่ถึง", fix: "เปลี่ยน Heater rod" },
    { m: "M-0003", tech: ["TEC001"], days: 40, problem: "ปุ่ม Freeze ไม่ทำงาน", fix: "เปลี่ยน keyboard assembly" },
  ];
  for (const d of doneDefs) {
    const end = new Date(now - d.days * DAY);
    const visits: Visit[] = d.two
      ? [
          { who: d.tech[0], inAt: new Date(end.getTime() - 2 * DAY - 2 * HOUR), outAt: new Date(end.getTime() - 2 * DAY), summary: "ตรวจพบ Power supply เสีย สั่งอะไหล่", outcome: "HOLD", hold: "รออะไหล่ Power supply" },
          { who: d.tech[0], inAt: new Date(end.getTime() - 2 * HOUR), outAt: end, summary: d.fix, outcome: "COMPLETED" },
        ]
      : [{ who: d.tech[0], inAt: new Date(end.getTime() - 2 * HOUR), outAt: end, summary: d.fix, outcome: "COMPLETED" }];
    await job({
      machine: d.m,
      type: d.type,
      problem: d.problem,
      scheduledAt: visits[0].inAt,
      status: "COMPLETED",
      techs: d.tech,
      createdAt: new Date(visits[0].inAt.getTime() - DAY),
      visits,
      resolution: d.fix,
    });
  }

  // ---------- Today's shifts + location trail ----------
  const startAt = new Date(now - 3 * HOUR);
  const trails: Record<string, [number, number][]> = {
    TEC001: [[13.745, 100.534], [13.742, 100.541], [13.739, 100.549], [13.7372, 100.5562], [13.7368, 100.5598]],
    TEC002: [[13.92, 100.6], [13.95, 100.607], [13.975, 100.613], [13.9873, 100.6168]],
    SUP001: [[13.7563, 100.5018], [13.756, 100.502]],
  };
  for (const [code, pts] of Object.entries(trails)) {
    const shift = await prisma.shift.create({
      data: { employeeId: emp[code], workDate: dateKey(), startAt, startLat: pts[0][0], startLng: pts[0][1] },
    });
    for (const [i, [lat, lng]] of pts.entries()) {
      await prisma.locationPing.create({
        data: {
          employeeId: emp[code],
          shiftId: shift.id,
          latitude: lat,
          longitude: lng,
          accuracy: 15,
          source: i === 0 ? "SHIFT_START" : "TRACKING",
          recordedAt: new Date(startAt.getTime() + ((now - 5 * MIN - startAt.getTime()) * i) / Math.max(1, pts.length - 1)),
          jobId: code === "TEC002" && i === pts.length - 1 ? inProgress.id : null,
        },
      });
    }
  }

  await prisma.notification.create({
    data: {
      employeeId: emp.TEC001,
      jobId: assigned.id,
      type: "JOB_ASSIGNED",
      title: `ได้รับงานใหม่ ${assigned.jobNo}`,
      body: `${c1.name} (อาคารหลัก) · GE Healthcare Voluson E8`,
      url: `/m/jobs/${assigned.id}`,
    },
  });

  console.log("Seed complete (demo data).");
  console.log(`All accounts use password: ${PASSWORD}`);
  console.log("  MGR001 ผู้จัดการ · ADM001 แอดมิน · SUP001/SUP002 หัวหน้าช่าง · TEC001–TEC004 ช่าง");
}

main()
  .catch((e) => {
    console.error(e);
    process.exit(1);
  })
  .finally(() => prisma.$disconnect());
