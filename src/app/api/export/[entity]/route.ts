import { NextRequest } from "next/server";
import { auth } from "@/auth";
import { canView } from "@/lib/rbac";
import { getExporter, buildCsvForExporter } from "@/lib/exporters";
import { csvResponse } from "@/lib/csv";

export async function GET(req: NextRequest, { params }: { params: { entity: string } }) {
  const session = await auth();
  if (!session?.user) {
    return new Response("Unauthorized", { status: 401 });
  }

  const exporter = getExporter(params.entity);
  if (!exporter) {
    return new Response("Unknown export", { status: 404 });
  }

  if (!canView(session.user.role, session.user.permissions, exporter.module)) {
    return new Response("Forbidden — คุณไม่มีสิทธิ์ดูข้อมูลส่วนนี้", { status: 403 });
  }

  const csv = await buildCsvForExporter(exporter, req.nextUrl.searchParams);
  return csvResponse(exporter.key, csv);
}
