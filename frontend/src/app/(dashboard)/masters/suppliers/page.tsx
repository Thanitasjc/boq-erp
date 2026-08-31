"use client";

import MasterListPage, {
  MasterFormField,
} from "@/components/masters/MasterListPage";
import { Supplier } from "@/lib/api";

const TYPE_LABELS: Record<string, string> = {
  supplier: "ผู้ขาย",
  contractor: "ผู้รับเหมา",
  both: "ทั้งสอง",
};

const fields: MasterFormField[] = [
  { key: "code", label: "รหัส", required: true, placeholder: "เช่น SUP-001" },
  { key: "name", label: "ชื่อบริษัท/ร้าน", required: true },
  {
    key: "type",
    label: "ประเภท",
    type: "select",
    required: true,
    options: Object.entries(TYPE_LABELS).map(([value, label]) => ({
      value,
      label,
    })),
  },
  { key: "tax_id", label: "เลขประจำตัวผู้เสียภาษี" },
  { key: "contact_person", label: "ผู้ติดต่อ" },
  { key: "phone", label: "เบอร์โทร" },
  { key: "email", label: "อีเมล", type: "email" },
  { key: "address", label: "ที่อยู่", type: "textarea" },
  { key: "is_active", label: "เปิดใช้งาน", type: "checkbox", editOnly: true },
];

export default function SuppliersPage() {
  return (
    <MasterListPage<Supplier>
      title="ผู้ขาย / ผู้รับเหมา"
      subtitle="ข้อมูลหลัก — ผู้ขายและผู้รับเหมา"
      endpoint="/masters/suppliers"
      createLabel="+ เพิ่มผู้ขาย/ผู้รับเหมา"
      fields={fields}
      columns={[
        { key: "code", label: "รหัส" },
        { key: "name", label: "ชื่อ" },
        {
          key: "type",
          label: "ประเภท",
          render: (item) => TYPE_LABELS[item.type] ?? item.type,
        },
        { key: "contact_person", label: "ผู้ติดต่อ" },
        { key: "phone", label: "เบอร์โทร" },
        {
          key: "is_active",
          label: "สถานะ",
          render: (item) => (
            <span
              className={`rounded-full px-2 py-0.5 text-xs font-medium ${
                item.is_active
                  ? "bg-green-100 text-green-800"
                  : "bg-red-100 text-red-800"
              }`}
            >
              {item.is_active ? "ใช้งาน" : "ปิดใช้งาน"}
            </span>
          ),
        },
      ]}
    />
  );
}
