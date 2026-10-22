<?php
// RBAC: role → capability (ตาราง docs/crm-v1/03-modules-rbac.md)
// ตรวจสิทธิ์ใน service layer ทุกครั้ง ไม่ใช่แค่ซ่อนปุ่มในหน้าเว็บ

const ROLE_CODES = [
    'GM', 'SALES_COORDINATOR', 'SALES_EXECUTIVE', 'SALES_DIRECTOR',
    'SERVICE_ENGINEER', 'SERVICE_DIRECTOR', 'MARKETING', 'MANAGEMENT', 'ADMIN',
];

const ROLE_NAMES = [
    'GM' => 'ผู้จัดการทั่วไป (GM)',
    'SALES_COORDINATOR' => 'ผู้ประสานงานขาย (Sales Coordinator)',
    'SALES_EXECUTIVE' => 'พนักงานขาย (Sales Executive)',
    'SALES_DIRECTOR' => 'ผู้อำนวยการฝ่ายขาย (Sales Director)',
    'SERVICE_ENGINEER' => 'วิศวกรบริการ (Service Engineer)',
    'SERVICE_DIRECTOR' => 'ผู้อำนวยการฝ่ายบริการ (Service Director)',
    'MARKETING' => 'การตลาด (Marketing)',
    'MANAGEMENT' => 'ผู้บริหาร (Management)',
    'ADMIN' => 'ผู้ดูแลระบบ (Admin)',
];

/**
 * สิทธิ์ทั้งหมด
 * finance.view = เห็นข้อมูลการเงินที่อ่อนไหว: ต้นทุน, cost sheet, valuation, GP, ราคาซื้อสูงสุด, ราคาขายต่ำสุด, ต้นทุนบัญชี
 */
const ALL_CAPS = [
    'customer.view', 'customer.edit',
    'lead.view', 'lead.edit',
    'device.view', 'device.edit', 'device.override_status', 'device.opening_stock',
    'inspection.request', 'inspection.view', 'inspection.edit',
    'service_history.view', 'service_history.edit',
    'finance.view',
    'cost_sheet.edit', 'valuation.edit', 'valuation.recommended_view',
    'negotiation.view', 'negotiation.edit',
    'acquisition.submit', 'acquisition.approve', 'acquisition.create',
    'inventory.view', 'inventory.edit',
    'sales.view', 'sales.edit',
    'quotation.view', 'quotation.edit', 'quotation.approve', 'quotation.approve_below_min',
    'deposit.view', 'deposit.edit', 'deposit.verify',
    'contract.view', 'contract.edit',
    'transaction.view', 'transaction.cancel',
    'checklist.COMMERCIAL', 'checklist.SALES', 'checklist.MARKETING', 'checklist.TECHNICAL',
    'qc.edit', 'delivery.edit', 'installation.edit',
    'job.view', 'job.edit', 'service_case.view', 'service_case.edit',
    'task.view_all', 'record.void',
    'audit.view', 'admin.users', 'admin.master_data', 'admin.settings',
];

function role_caps(): array
{
    $sales_common = [
        'customer.view', 'customer.edit', 'lead.view', 'lead.edit', 'device.view',
        'inspection.request', 'inspection.view', 'service_history.view', 'negotiation.view', 'inventory.view',
        'sales.view', 'quotation.view', 'quotation.edit', 'deposit.view', 'contract.view',
        'transaction.view', 'checklist.COMMERCIAL', 'checklist.SALES', 'service_case.view', 'job.view',
    ];
    $service_common = [
        'customer.view', 'lead.view', 'device.view', 'device.edit',
        'inspection.view', 'inspection.edit', 'service_history.view', 'service_history.edit',
        'inventory.view', 'sales.view', 'transaction.view', 'checklist.TECHNICAL',
        'qc.edit', 'delivery.edit', 'installation.edit',
        'job.view', 'job.edit', 'service_case.view', 'service_case.edit',
    ];
    $view_all = [
        'customer.view', 'lead.view', 'device.view', 'inspection.view', 'service_history.view',
        'finance.view', 'negotiation.view', 'inventory.view', 'sales.view', 'quotation.view',
        'deposit.view', 'contract.view', 'transaction.view', 'job.view', 'service_case.view',
        'task.view_all', 'audit.view',
    ];
    return [
        'GM' => ALL_CAPS,
        'SALES_COORDINATOR' => array_merge($sales_common, [
            'device.edit', 'acquisition.create', 'deposit.edit', 'contract.edit',
        ]),
        'SALES_EXECUTIVE' => array_merge($sales_common, [
            'negotiation.edit', 'acquisition.submit', 'sales.edit', 'valuation.recommended_view',
        ]),
        'SALES_DIRECTOR' => array_merge($sales_common, [
            'finance.view', 'valuation.edit', 'valuation.recommended_view', 'negotiation.edit',
            'acquisition.submit', 'sales.edit', 'quotation.approve', 'contract.edit', 'inventory.edit',
        ]),
        'SERVICE_ENGINEER' => $service_common,
        'SERVICE_DIRECTOR' => array_merge($service_common, [
            'finance.view', 'cost_sheet.edit', 'negotiation.view',
        ]),
        'MARKETING' => [
            'customer.view', 'lead.view', 'device.view', 'inventory.view', 'sales.view',
            'quotation.view', 'transaction.view', 'checklist.MARKETING',
        ],
        'MANAGEMENT' => $view_all,
        'ADMIN' => [
            'customer.view', 'device.view', 'audit.view', 'admin.users', 'admin.master_data', 'admin.settings',
            'device.override_status',
        ],
    ];
}

function caps_for_roles(array $roles): array
{
    $map = role_caps();
    $caps = [];
    foreach ($roles as $r) {
        foreach ($map[$r] ?? [] as $c) $caps[$c] = true;
    }
    return $caps;
}

function can(string $cap): bool
{
    $u = current_user();
    return $u !== null && isset($u['caps'][$cap]);
}

/** true ถ้ามีสิทธิ์อย่างน้อยหนึ่งอย่าง */
function can_any(string ...$caps): bool
{
    foreach ($caps as $c) if (can($c)) return true;
    return false;
}

function has_role(string $role): bool
{
    $u = current_user();
    return $u !== null && in_array($role, $u['roles'], true);
}

/** ต้องมีสิทธิ์อย่างน้อยหนึ่งอย่าง ไม่งั้นโยน ForbiddenError */
function require_cap(string ...$caps): void
{
    if (!current_user()) throw new ForbiddenError('กรุณาเข้าสู่ระบบ');
    if (!can_any(...$caps)) {
        throw new ForbiddenError('คุณไม่มีสิทธิ์ทำรายการนี้');
    }
}

// ฟิลด์การเงินที่ต้องตัดออกถ้าไม่มี finance.view (ตัดตั้งแต่ server ก่อนส่งให้หน้าเว็บ)
const SENSITIVE_FIELDS = [
    'device_service_history' => ['cost'],
    'ma_records' => ['cost'],
    'cost_sheets' => ['total_estimated_cost'],
    'cost_sheet_items' => ['amount'],
    'valuations' => [
        'total_cost_snapshot', 'acquisition_assumption_snapshot', 'market_selling_price', 'historical_selling_price',
        'fair_market_value', 'recommended_acq_price', 'max_acq_price', 'target_selling_price',
        'min_selling_price', 'expected_gp', 'expected_gp_margin',
    ],
    'inventory' => ['book_cost'],
];

/** ลบฟิลด์ที่ผู้ใช้ไม่มีสิทธิ์เห็นออกจาก record (หรือรายการ record) */
function redact(string $table, ?array $rowOrRows): ?array
{
    if ($rowOrRows === null || can('finance.view')) return $rowOrRows;
    $fields = SENSITIVE_FIELDS[$table] ?? [];
    if (!$fields) return $rowOrRows;
    // Sales Executive เห็นราคาแนะนำให้ซื้อได้ (ใช้ต่อรอง) แต่ไม่เห็นราคาสูงสุด
    if ($table === 'valuations' && can('valuation.recommended_view')) {
        $fields = array_values(array_diff($fields, ['recommended_acq_price']));
    }
    $strip = function (array $r) use ($fields) {
        foreach ($fields as $f) unset($r[$f]);
        return $r;
    };
    $isList = array_keys($rowOrRows) === range(0, count($rowOrRows) - 1);
    if ($isList && $rowOrRows && is_array(reset($rowOrRows))) return array_map($strip, $rowOrRows);
    return $strip($rowOrRows);
}
