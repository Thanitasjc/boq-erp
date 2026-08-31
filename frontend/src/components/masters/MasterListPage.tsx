"use client";

import { useCallback, useEffect, useState } from "react";
import Header from "@/components/layout/Header";
import { useAuth } from "@/contexts/AuthContext";
import { apiFetch, PaginatedResponse } from "@/lib/api";

export interface MasterFormField {
  key: string;
  label: string;
  type?: "text" | "email" | "textarea" | "select" | "checkbox" | "number";
  required?: boolean;
  options?: { value: string; label: string }[];
  editOnly?: boolean;
  placeholder?: string;
}

interface MasterListPageProps<T extends { id: number }> {
  title: string;
  subtitle: string;
  endpoint: string;
  columns: {
    key: keyof T | string;
    label: string;
    render?: (item: T) => React.ReactNode;
  }[];
  fields: MasterFormField[];
  createLabel?: string;
}

type FormValues = Record<string, string | boolean>;

function buildEmptyForm(fields: MasterFormField[]): FormValues {
  const form: FormValues = {};
  for (const field of fields) {
    if (field.type === "checkbox") {
      form[field.key] = true;
    } else if (field.type === "number") {
      form[field.key] = "0";
    } else if (field.type === "select" && field.options?.length) {
      form[field.key] = field.options[0].value;
    } else {
      form[field.key] = "";
    }
  }
  return form;
}

function itemToForm(item: Record<string, unknown>, fields: MasterFormField[]): FormValues {
  const form: FormValues = {};
  for (const field of fields) {
    const value = item[field.key];
    if (field.type === "checkbox") {
      form[field.key] = Boolean(value);
    } else {
      form[field.key] = value != null ? String(value) : "";
    }
  }
  return form;
}

function buildPayload(
  form: FormValues,
  fields: MasterFormField[],
  editing: boolean,
): Record<string, unknown> {
  const payload: Record<string, unknown> = {};
  for (const field of fields) {
    if (!editing && field.editOnly) continue;
    const value = form[field.key];
    if (field.type === "checkbox") {
      payload[field.key] = Boolean(value);
    } else if (field.type === "number") {
      const num = Number(value);
      if (!Number.isNaN(num)) payload[field.key] = num;
    } else if (typeof value === "string") {
      const trimmed = value.trim();
      if (trimmed || field.required) {
        payload[field.key] = trimmed;
      }
    }
  }
  return payload;
}

export default function MasterListPage<T extends { id: number }>({
  title,
  subtitle,
  endpoint,
  columns,
  fields,
  createLabel = "+ เพิ่มรายการ",
}: MasterListPageProps<T>) {
  const { hasPermission } = useAuth();
  const canEdit = hasPermission("masters.edit");

  const [items, setItems] = useState<T[]>([]);
  const [loading, setLoading] = useState(true);
  const [search, setSearch] = useState("");
  const [showForm, setShowForm] = useState(false);
  const [editing, setEditing] = useState<T | null>(null);
  const [form, setForm] = useState<FormValues>(() => buildEmptyForm(fields));
  const [saving, setSaving] = useState(false);
  const [error, setError] = useState("");

  const load = useCallback(() => {
    setLoading(true);
    const params = search ? `?search=${encodeURIComponent(search)}` : "";
    apiFetch<PaginatedResponse<T>>(`${endpoint}${params}`)
      .then((res) => setItems(res.data))
      .catch(() => setItems([]))
      .finally(() => setLoading(false));
  }, [endpoint, search]);

  useEffect(() => {
    load();
  }, [load]);

  const openCreate = () => {
    setEditing(null);
    setForm(buildEmptyForm(fields));
    setError("");
    setShowForm(true);
  };

  const openEdit = (item: T) => {
    setEditing(item);
    setForm(itemToForm(item as Record<string, unknown>, fields));
    setError("");
    setShowForm(true);
  };

  const handleSave = async () => {
    for (const field of fields) {
      if (field.editOnly && !editing) continue;
      if (!field.required) continue;
      const value = form[field.key];
      if (field.type === "checkbox") continue;
      if (!String(value ?? "").trim()) {
        setError(`กรุณากรอก${field.label}`);
        return;
      }
    }

    setSaving(true);
    setError("");
    try {
      const payload = buildPayload(form, fields, Boolean(editing));
      if (editing) {
        await apiFetch(`${endpoint}/${editing.id}`, {
          method: "PUT",
          body: JSON.stringify(payload),
        });
      } else {
        await apiFetch(endpoint, {
          method: "POST",
          body: JSON.stringify(payload),
        });
      }
      setShowForm(false);
      load();
    } catch (e) {
      setError(e instanceof Error ? e.message : "บันทึกไม่สำเร็จ");
    } finally {
      setSaving(false);
    }
  };

  const handleDelete = async (item: T) => {
    const name = String((item as Record<string, unknown>).name ?? item.id);
    if (!confirm(`ลบรายการ "${name}" ?`)) return;
    try {
      await apiFetch(`${endpoint}/${item.id}`, { method: "DELETE" });
      load();
    } catch (e) {
      alert(e instanceof Error ? e.message : "ลบไม่สำเร็จ");
    }
  };

  const visibleFields = fields.filter((f) => !f.editOnly || editing);

  return (
    <>
      <Header
        title={title}
        subtitle={subtitle}
        actions={
          canEdit ? (
            <button
              onClick={openCreate}
              className="rounded-lg bg-amber-400 px-4 py-2 text-sm font-semibold text-zinc-900 hover:bg-amber-500"
            >
              {createLabel}
            </button>
          ) : undefined
        }
      />
      <div className="flex-1 overflow-y-auto p-6">
        <div className="mb-4">
          <input
            type="text"
            placeholder="ค้นหา..."
            value={search}
            onChange={(e) => setSearch(e.target.value)}
            className="rounded-lg border border-zinc-300 px-3 py-2 text-sm focus:border-amber-400 focus:outline-none focus:ring-2 focus:ring-amber-400/20"
          />
        </div>

        {loading ? (
          <div className="flex justify-center py-12">
            <div className="h-8 w-8 animate-spin rounded-full border-4 border-amber-400 border-t-transparent" />
          </div>
        ) : items.length === 0 ? (
          <div className="rounded-xl border border-dashed border-zinc-300 bg-white py-16 text-center">
            <p className="text-zinc-500">ไม่พบข้อมูล</p>
            {canEdit && (
              <button
                onClick={openCreate}
                className="mt-3 text-sm font-medium text-amber-600 hover:underline"
              >
                + เพิ่มรายการแรก
              </button>
            )}
          </div>
        ) : (
          <div className="overflow-hidden rounded-xl border border-zinc-200 bg-white shadow-sm">
            <table className="w-full text-sm">
              <thead>
                <tr className="border-b border-zinc-200 bg-zinc-50 text-left text-xs uppercase tracking-wider text-zinc-500">
                  {columns.map((col) => (
                    <th key={String(col.key)} className="px-5 py-3">
                      {col.label}
                    </th>
                  ))}
                  {canEdit && (
                    <th className="px-5 py-3 text-right">การดำเนินการ</th>
                  )}
                </tr>
              </thead>
              <tbody>
                {items.map((item) => (
                  <tr
                    key={item.id}
                    className="border-b border-zinc-50 hover:bg-zinc-50"
                  >
                    {columns.map((col) => (
                      <td key={String(col.key)} className="px-5 py-3">
                        {col.render
                          ? col.render(item)
                          : String(
                              (item as Record<string, unknown>)[
                                col.key as string
                              ] ?? "—",
                            )}
                      </td>
                    ))}
                    {canEdit && (
                      <td className="px-5 py-3 text-right space-x-2">
                        <button
                          onClick={() => openEdit(item)}
                          className="text-xs text-amber-600 hover:underline"
                        >
                          แก้ไข
                        </button>
                        <button
                          onClick={() => handleDelete(item)}
                          className="text-xs text-red-600 hover:underline"
                        >
                          ลบ
                        </button>
                      </td>
                    )}
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        )}

        {showForm && (
          <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4">
            <div className="max-h-[90vh] w-full max-w-md overflow-y-auto rounded-xl bg-white p-6 shadow-xl">
              <h3 className="text-lg font-bold">
                {editing ? "แก้ไขรายการ" : "เพิ่มรายการใหม่"}
              </h3>
              <div className="mt-4 space-y-3">
                {visibleFields.map((field) => {
                  if (field.type === "checkbox") {
                    return (
                      <label
                        key={field.key}
                        className="flex items-center gap-2 text-sm"
                      >
                        <input
                          type="checkbox"
                          checked={Boolean(form[field.key])}
                          onChange={(e) =>
                            setForm({ ...form, [field.key]: e.target.checked })
                          }
                        />
                        {field.label}
                      </label>
                    );
                  }
                  if (field.type === "select") {
                    return (
                      <div key={field.key}>
                        <label className="mb-1 block text-sm font-medium">
                          {field.label}
                          {field.required && " *"}
                        </label>
                        <select
                          value={String(form[field.key] ?? "")}
                          onChange={(e) =>
                            setForm({ ...form, [field.key]: e.target.value })
                          }
                          className="w-full rounded-lg border px-3 py-2 text-sm"
                        >
                          {field.options?.map((opt) => (
                            <option key={opt.value} value={opt.value}>
                              {opt.label}
                            </option>
                          ))}
                        </select>
                      </div>
                    );
                  }
                  if (field.type === "textarea") {
                    return (
                      <div key={field.key}>
                        <label className="mb-1 block text-sm font-medium">
                          {field.label}
                          {field.required && " *"}
                        </label>
                        <textarea
                          value={String(form[field.key] ?? "")}
                          onChange={(e) =>
                            setForm({ ...form, [field.key]: e.target.value })
                          }
                          placeholder={field.placeholder}
                          rows={3}
                          className="w-full rounded-lg border px-3 py-2 text-sm"
                        />
                      </div>
                    );
                  }
                  if (field.type === "number") {
                    return (
                      <div key={field.key}>
                        <label className="mb-1 block text-sm font-medium">
                          {field.label}
                          {field.required && " *"}
                        </label>
                        <input
                          type="number"
                          min={0}
                          value={String(form[field.key] ?? "")}
                          onChange={(e) =>
                            setForm({ ...form, [field.key]: e.target.value })
                          }
                          placeholder={field.placeholder}
                          className="w-full rounded-lg border px-3 py-2 text-sm"
                        />
                      </div>
                    );
                  }
                  return (
                    <div key={field.key}>
                      <label className="mb-1 block text-sm font-medium">
                        {field.label}
                        {field.required && " *"}
                      </label>
                      <input
                        type={field.type === "email" ? "email" : "text"}
                        value={String(form[field.key] ?? "")}
                        onChange={(e) =>
                          setForm({ ...form, [field.key]: e.target.value })
                        }
                        placeholder={field.placeholder}
                        className="w-full rounded-lg border px-3 py-2 text-sm"
                      />
                    </div>
                  );
                })}
                {error && <p className="text-sm text-red-500">{error}</p>}
              </div>
              <div className="mt-6 flex justify-end gap-2">
                <button
                  onClick={() => setShowForm(false)}
                  className="rounded-lg border px-4 py-2 text-sm"
                >
                  ยกเลิก
                </button>
                <button
                  onClick={handleSave}
                  disabled={saving}
                  className="rounded-lg bg-amber-400 px-4 py-2 text-sm font-semibold disabled:opacity-50"
                >
                  {saving ? "กำลังบันทึก..." : "บันทึก"}
                </button>
              </div>
            </div>
          </div>
        )}
      </div>
    </>
  );
}
