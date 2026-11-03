type Named = { firstName: string; lastName: string; nickname?: string | null };

/** "สมชาย ใจดี (เหน่ง)" */
export function fullName(e: Named) {
  const base = `${e.firstName} ${e.lastName}`.trim();
  return e.nickname ? `${base} (${e.nickname})` : base;
}

/** What colleagues actually call each other: nickname, else first name. */
export function shortName(e: Named) {
  return e.nickname || e.firstName;
}
