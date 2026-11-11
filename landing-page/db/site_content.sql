-- นำเข้าไฟล์นี้ผ่าน phpMyAdmin (แท็บ SQL) เพื่อเปิดใช้งานระบบแก้ไขหน้าแรกผ่านแอดมิน
-- ค่าเริ่มต้นด้านล่างตรงกับข้อความที่อยู่บนหน้าเว็บอยู่แล้วในปัจจุบัน (ไม่เปลี่ยนอะไรทันทีที่ติดตั้ง)

CREATE TABLE IF NOT EXISTS site_content (
    content_key VARCHAR(80) PRIMARY KEY,
    value_th TEXT,
    value_en TEXT,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO site_content (content_key, value_th, value_en) VALUES
('hero_eyebrow', 'Medical Equipment Specialist', 'Medical Equipment Specialist'),
('hero_heading', 'เครื่องมือแพทย์คุณภาพ<br>ที่คุณ<em>วางใจได้</em>', 'Quality Medical Equipment<br>You Can <em>Trust</em>'),
('hero_body', 'AMN SURE คือผู้เชี่ยวชาญด้านการประเมิน ซื้อ-ขาย และรับฝากขายเครื่องมือแพทย์มือสองจาก AMN ซึ่งผ่านการตรวจสอบมาตรฐานอย่างเข้มงวด เพื่อส่งมอบความมั่นใจให้กับสถานพยาบาลทุกขนาด', 'AMN SURE specializes in appraising, buying, selling, and consigning quality used medical equipment, rigorously inspected to bring confidence to healthcare facilities of every size.'),
('hero_cta_label', 'ติดต่อสอบถาม', 'Get in Touch'),

('about_eyebrow', 'About AMN Sure', 'About AMN Sure'),
('about_heading', 'มาตรฐานที่เชื่อถือได้<br>ในทุกการซื้อขาย', 'Trusted Standards<br>In Every Transaction'),
('about_body', 'เราให้บริการด้านเครื่องมือแพทย์มือสองแบบครบวงจร ตั้งแต่การรับซื้อ ตรวจสอบสภาพและคุณภาพ ซ่อมบำรุง ไปจนถึงการจัดจำหน่ายให้กับสถานพยาบาลทั่วประเทศ ด้วยทีมงานผู้เชี่ยวชาญเฉพาะทางและกระบวนการตรวจสอบที่เข้มงวด', 'We offer end-to-end used medical equipment services — from purchasing, condition and quality inspection, and maintenance, to distribution for healthcare facilities nationwide, backed by specialist teams and a rigorous inspection process.'),
('about_point_1', 'ตรวจสอบคุณภาพและความปลอดภัยทุกชิ้นก่อนส่งมอบ', 'Every item is quality- and safety-checked before delivery'),
('about_point_2', 'บริการรับฝากขายพร้อมระบบที่โปร่งใส ตรวจสอบได้', 'Consignment service with a transparent, verifiable process'),
('about_point_3', 'ทีมงานผู้เชี่ยวชาญให้คำปรึกษาตลอดกระบวนการ', 'Specialist team advising you throughout the process'),

('services_eyebrow', 'Our Services', 'Our Services'),
('services_heading', 'บริการของเรา', 'Our Services'),
('service_1_title', 'ซื้อ-ขายเครื่องมือแพทย์', 'Medical Equipment Trading'),
('service_2_title', 'รับฝากขาย (Consignment)', 'Consignment Service'),
('service_3_title', 'ตรวจสอบและรับรองคุณภาพ', 'Quality Inspection & Certification'),
('service_4_title', 'บริการหลังการขาย', 'After-Sales Service'),

('why_eyebrow', 'Why Choose Us', 'Why Choose Us'),
('why_heading', 'ทำไมต้อง SURE', 'Why Choose SURE'),
('why_1_title', 'มาตรฐาน', 'Standards'),
('why_1_body', 'ตรวจสอบคุณภาพทุกชิ้นตามมาตรฐานสากล', 'Every item inspected to international standards'),
('why_2_title', 'ราคายุติธรรม', 'Fair Pricing'),
('why_2_body', 'ประเมินราคาโปร่งใส คุ้มค่าทุกการลงทุน', 'Transparent valuation, real value for every investment'),
('why_3_title', 'บริการรวดเร็ว', 'Fast Service'),
('why_3_body', 'ทีมงานดูแลและตอบกลับอย่างรวดเร็วทันใจ', 'Our team responds and follows through quickly'),
('why_4_title', 'ซื่อสัตย์โปร่งใส', 'Honest & Transparent'),
('why_4_body', 'ดำเนินธุรกิจด้วยความจริงใจตรวจสอบได้', 'We do business with integrity you can verify'),

('cta_eyebrow', 'Get In Touch', 'Get In Touch'),
('cta_heading', 'พร้อมให้คำปรึกษา<br>ทุกความต้องการของคุณ', 'Ready to Assist<br>With All Your Needs'),
('cta_body', 'ติดต่อทีมงาน AMN Sure เพื่อสอบถามข้อมูลเครื่องมือแพทย์ ซื้อ-ขาย หรือฝากขาย ได้ทุกช่องทาง', 'Reach the AMN Sure team to ask about medical equipment, buying, selling, or consignment through any channel.'),
('cta_line_label', 'ติดต่อผ่าน LINE', 'Contact via LINE'),

('inquiry_eyebrow', 'Get A Quote', 'Get A Quote'),
('inquiry_heading', 'สนใจสินค้า?', 'Interested?'),
('inquiry_body', 'ฝากข้อมูลไว้ ทีมงาน AMN Sure จะติดต่อกลับโดยเร็วที่สุด', 'Leave your details and the AMN Sure team will get back to you shortly.'),

('footer_tagline', 'ผู้เชี่ยวชาญด้านการซื้อ-ขายและรับฝากขายเครื่องมือแพทย์มือสองคุณภาพ เพื่อสถานพยาบาลที่ไว้วางใจได้', 'Specialists in buying, selling, and consigning quality used medical equipment, for healthcare facilities that trust us.'),
('footer_address', '1168/19 อาคารลุมพินีทาวเวอร์ ชั้น 13 ถ.พระราม 4 แขวงทุ่งมหาเมฆ เขตสาทร กทม. 10120', '1168/19 Lumpini Tower, 13th Floor, Rama IV Road, Thung Maha Mek, Sathon, Bangkok 10120'),
('footer_bottom_tagline', 'ออกแบบด้วยความใส่ใจ เพื่อความเชื่อมั่นของคุณ', 'Crafted with care, built on trust'),

('line_modal_heading', 'เพิ่มเพื่อน LINE', 'Add us on LINE'),
('line_modal_body', 'สแกน QR Code เพื่อติดต่อ AMN Sure ผ่าน LINE', 'Scan the QR code to contact AMN Sure via LINE'),

('contact_phone', '081-235-4316', NULL),
('contact_email', 'sure@amnsure.co.th', NULL);
