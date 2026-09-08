export type CsvColumn<T> = { key: string; label: string; value: (row: T) => unknown };

function escapeCell(value: unknown): string {
  if (value === null || value === undefined) return "";
  const str = value instanceof Date ? value.toISOString() : String(value);
  if (/[",\n]/.test(str)) {
    return `"${str.replace(/"/g, '""')}"`;
  }
  return str;
}

export function toCsv<T>(rows: T[], columns: CsvColumn<T>[]): string {
  const header = columns.map((c) => escapeCell(c.label)).join(",");
  const lines = rows.map((row) => columns.map((c) => escapeCell(c.value(row))).join(","));
  return [header, ...lines].join("\n");
}

export function csvResponse(filenameBase: string, csv: string) {
  const filename = `${filenameBase}-${new Date().toISOString().slice(0, 10)}.csv`;
  // Prepend a UTF-8 BOM so Excel opens Thai text correctly.
  return new Response("﻿" + csv, {
    headers: {
      "Content-Type": "text/csv; charset=utf-8",
      "Content-Disposition": `attachment; filename="${filename}"`,
    },
  });
}
