#!/usr/bin/env bash
# สร้างไฟล์ zip สำหรับอัปโหลดขึ้น cPanel (แตกไฟล์ใน public_html/crmsure.amnsure.co.th)
# ไม่รวม: tests/, tools/ (ข้อมูลตัวอย่าง), app/config.php, log ในเครื่อง, README.md, สคริปต์นี้
set -euo pipefail
cd "$(dirname "$0")"
VERSION=$(php -r 'define("APP_DIR","app"); preg_match("/APP_VERSION\x27, \x27([^\x27]+)/", file_get_contents("app/bootstrap.php"), $m); echo $m[1];')
OUT_DIR="../dist"
OUT="$OUT_DIR/amnsure-sure-v$VERSION.zip"
mkdir -p "$OUT_DIR"
rm -f "$OUT"
STAGE=$(mktemp -d)
trap 'rm -rf "$STAGE"' EXIT
rsync -a \
  --exclude 'tests/' --exclude 'tools/' --exclude 'app/config.php' --exclude 'build.sh' --exclude '.gitignore' --exclude 'README.md' \
  --exclude 'storage/logs/*' \
  ./ "$STAGE/"
cp storage/logs/index.html "$STAGE/storage/logs/index.html"
(cd "$STAGE" && zip -qr -X "$OLDPWD/$OUT" . )
echo "สร้างแล้ว: $OUT ($(du -h "$OUT" | cut -f1))"
unzip -l "$OUT" | tail -1
