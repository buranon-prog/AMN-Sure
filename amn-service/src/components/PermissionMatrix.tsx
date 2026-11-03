import { ROLES, ROLE_LABEL } from "@/lib/constants";
import { PERMISSION_MATRIX } from "@/lib/permissions";

export default function PermissionMatrix() {
  return (
    <div className="overflow-x-auto">
      <table className="data-table">
        <thead>
          <tr>
            <th>สิทธิ์</th>
            {ROLES.map((r) => (
              <th key={r} className="text-center">
                {ROLE_LABEL[r]}
              </th>
            ))}
          </tr>
        </thead>
        <tbody>
          {PERMISSION_MATRIX.map((p) => (
            <tr key={p.label}>
              <td>{p.label}</td>
              {ROLES.map((r) => (
                <td key={r} className="text-center">
                  {p.check(r) ? (
                    <span className="font-bold text-emerald-600" aria-label="ได้">
                      ✓
                    </span>
                  ) : (
                    <span className="text-gray-300" aria-label="ไม่ได้">
                      —
                    </span>
                  )}
                </td>
              ))}
            </tr>
          ))}
        </tbody>
      </table>
    </div>
  );
}
