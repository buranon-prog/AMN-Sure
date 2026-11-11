<?php
require_once __DIR__ . '/db.php';

/**
 * โหลดเนื้อหาหน้าแรกทั้งหมดจากตาราง site_content (query ครั้งเดียวต่อ request)
 */
function loadSiteContent(): array {
    static $content = null;
    if ($content === null) {
        $content = [];
        $stmt = getDb()->query('SELECT content_key, value_th, value_en FROM site_content');
        foreach ($stmt->fetchAll() as $row) {
            $content[$row['content_key']] = [
                'th' => $row['value_th'] ?? '',
                'en' => $row['value_en'] ?? '',
            ];
        }
    }
    return $content;
}

/**
 * ดึงค่าข้อความของ content_key ตามภาษาที่ระบุ (ค่าดิบ ยังไม่ผ่าน escape)
 */
function cf(string $key, string $lang = 'th'): string {
    $content = loadSiteContent();
    return $content[$key][$lang] ?? '';
}

/**
 * บันทึก/อัปเดตค่า content_key หนึ่งรายการ (ใช้ REPLACE INTO เพราะ content_key เป็น PRIMARY KEY)
 */
function saveContent(string $key, string $valueTh, ?string $valueEn = null): void {
    $stmt = getDb()->prepare('REPLACE INTO site_content (content_key, value_th, value_en) VALUES (?, ?, ?)');
    $stmt->execute([$key, $valueTh, $valueEn]);
}

/**
 * โหลดรายการสไลด์รูปภาพด้านบนสุดของหน้าแรก เรียงตาม sort_order
 */
function loadHeroSlides(): array {
    $stmt = getDb()->query('SELECT id, image_path, link_url FROM hero_slides ORDER BY sort_order, id');
    return $stmt->fetchAll();
}
