import type { Employee, JobEvent } from "@prisma/client";
import { EVENT_LABEL } from "@/lib/constants";
import { formatDateTime } from "@/lib/datetime";
import { shortName } from "@/lib/people";

export default function EventLog({ events }: { events: (JobEvent & { actor: Employee | null })[] }) {
  return (
    <ol className="relative space-y-3 border-l border-gray-200 pl-4">
      {events.map((e) => (
        <li key={e.id} className="text-sm">
          <span className="absolute -left-[5px] mt-1.5 h-2.5 w-2.5 rounded-full bg-gray-300" />
          <div className="text-xs text-gray-400">
            {formatDateTime(e.createdAt)} · {e.actor ? shortName(e.actor) : "ระบบ"}
          </div>
          <div className="text-gray-700">
            <span className="font-semibold">{EVENT_LABEL[e.type] ?? e.type}</span> — {e.message}
          </div>
        </li>
      ))}
    </ol>
  );
}
