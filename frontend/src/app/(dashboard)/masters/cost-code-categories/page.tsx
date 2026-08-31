"use client";

import MasterListPage, {
  MasterFormField,
} from "@/components/masters/MasterListPage";
import { CostCodeCategory } from "@/lib/api";

const fields: MasterFormField[] = [
  {
    key: "code",
    label: "รหัส (slug)",
    required: true,
    placeholder: "เช่น structure, electrical",
  },
  { key: "name", label: "ชื่อ (ไทย)", required: true },
  { key: "name_en", label: "ชื่อ (อังกฤษ)" },
  {
    key: "sort_order",
    label: "ลำดับการแสดง",
    type: "number",
    placeholder: "0",
  },
  { key: "is_active", label: "เปิดใช้งาน", type: "checkbox", editOnly: true },
];

export default function CostCodeCategoriesPage() {
  return (
    <MasterListPage<CostCodeCategory>
      title="หมวดหมู่รหัสต้นทุน"
      subtitle="ข้อมูลหลัก — จัดการหมวดหมู่รหัสต้นทุน"
      endpoint="/masters/cost-code-categories"
      createLabel="+ เพิ่มหมวดหมู่"
      fields={fields}
      columns={[
        { key: "code", label: "รหัส" },
        { key: "name", label: "ชื่อ" },
        { key: "name_en", label: "ชื่อ (EN)" },
        { key: "sort_order", label: "ลำดับ" },
        {
          key: "cost_codes_count",
          label: "ใช้งานในรหัสต้นทุน",
          render: (item) => item.cost_codes_count ?? 0,
        },
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
