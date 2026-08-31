"use client";

import { useEffect, useMemo, useState } from "react";
import MasterListPage, {
  MasterFormField,
} from "@/components/masters/MasterListPage";
import { CostCode, CostCodeCategory, mastersApi } from "@/lib/api";

export default function CostCodesPage() {
  const [categories, setCategories] = useState<CostCodeCategory[]>([]);

  useEffect(() => {
    mastersApi.costCodeCategories
      .list({ per_page: "100" })
      .then((res) => setCategories(res.data))
      .catch(() => setCategories([]));
  }, []);

  const categoryMap = useMemo(
    () => Object.fromEntries(categories.map((c) => [c.code, c.name])),
    [categories],
  );

  const activeOptions = useMemo(
    () =>
      categories
        .filter((c) => c.is_active)
        .map((c) => ({ value: c.code, label: c.name })),
    [categories],
  );

  const fields: MasterFormField[] = useMemo(
    () => [
      { key: "code", label: "รหัส", required: true, placeholder: "เช่น STR" },
      { key: "name", label: "ชื่อ (ไทย)", required: true },
      { key: "name_en", label: "ชื่อ (อังกฤษ)" },
      {
        key: "category",
        label: "หมวดหมู่",
        type: "select",
        required: true,
        options:
          activeOptions.length > 0
            ? activeOptions
            : [{ value: "other", label: "อื่นๆ" }],
      },
      {
        key: "is_active",
        label: "เปิดใช้งาน",
        type: "checkbox",
        editOnly: true,
      },
    ],
    [activeOptions],
  );

  return (
    <MasterListPage<CostCode>
      title="รหัสต้นทุน"
      subtitle="ข้อมูลหลัก — โครงสร้างรหัสต้นทุน (จัดการหมวดหมู่ที่เมนู หมวดหมู่รหัสต้นทุน)"
      endpoint="/masters/cost-codes"
      createLabel="+ เพิ่มรหัสต้นทุน"
      fields={fields}
      columns={[
        { key: "code", label: "รหัส" },
        { key: "name", label: "ชื่อ" },
        { key: "name_en", label: "ชื่อ (EN)" },
        {
          key: "category",
          label: "หมวดหมู่",
          render: (item) => categoryMap[item.category] ?? item.category,
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
