"use client";

import MasterListPage, {
  MasterFormField,
} from "@/components/masters/MasterListPage";
import { Uom } from "@/lib/api";

const fields: MasterFormField[] = [
  { key: "code", label: "รหัส", required: true, placeholder: "เช่น EA, M2" },
  { key: "name", label: "ชื่อ (ไทย)", required: true },
  { key: "name_en", label: "ชื่อ (อังกฤษ)" },
  { key: "is_active", label: "เปิดใช้งาน", type: "checkbox", editOnly: true },
];

export default function UomsPage() {
  return (
    <MasterListPage<Uom>
      title="หน่วยนับ"
      subtitle="ข้อมูลหลัก — หน่วยวัด (UOM)"
      endpoint="/masters/uoms"
      createLabel="+ เพิ่มหน่วยนับ"
      fields={fields}
      columns={[
        { key: "code", label: "รหัส" },
        { key: "name", label: "ชื่อ" },
        { key: "name_en", label: "ชื่อ (EN)" },
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
