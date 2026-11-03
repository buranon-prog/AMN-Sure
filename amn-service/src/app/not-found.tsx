export default function NotFound() {
  return (
    <div className="mx-auto max-w-md px-4 py-16 text-center">
      <h1 className="text-lg font-bold text-gray-900">ไม่พบหน้าที่ต้องการ</h1>
      <p className="mt-1 text-sm text-gray-500">ข้อมูลอาจถูกลบ หรือคุณไม่มีสิทธิ์เข้าถึง</p>
      <a href="/" className="btn btn-secondary mt-5">
        กลับหน้าหลัก
      </a>
    </div>
  );
}
