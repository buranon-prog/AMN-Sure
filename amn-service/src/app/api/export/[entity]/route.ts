import { getCurrentUser } from "@/lib/session";
import { EXPORTERS, buildCsv } from "@/lib/exporters";
import { dateKey } from "@/lib/datetime";

export async function GET(_req: Request, { params }: { params: { entity: string } }) {
  const user = await getCurrentUser();
  if (!user) return new Response("Unauthorized", { status: 401 });
  const e = EXPORTERS.find((x) => x.key === params.entity);
  if (!e) return new Response("Not found", { status: 404 });
  if (!e.allowed(user.role)) return new Response("Forbidden", { status: 403 });

  const csv = await buildCsv(e);
  return new Response(csv, {
    headers: {
      "Content-Type": "text/csv; charset=utf-8",
      "Content-Disposition": `attachment; filename="amn-${e.key}-${dateKey()}.csv"`,
      "Cache-Control": "no-store",
    },
  });
}
