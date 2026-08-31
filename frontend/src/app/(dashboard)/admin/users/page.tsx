"use client";

import { useEffect, useState } from "react";
import Header from "@/components/layout/Header";
import { useAuth } from "@/contexts/AuthContext";
import { AdminUser, Role, usersApi } from "@/lib/api";

const emptyForm = {
  name: "",
  email: "",
  password: "",
  phone: "",
  position: "",
  is_active: true,
  role_ids: [] as number[],
};

export default function AdminUsersPage() {
  const { user: currentUser, hasPermission } = useAuth();
  const [users, setUsers] = useState<AdminUser[]>([]);
  const [roles, setRoles] = useState<Role[]>([]);
  const [loading, setLoading] = useState(true);
  const [search, setSearch] = useState("");
  const [showForm, setShowForm] = useState(false);
  const [editing, setEditing] = useState<AdminUser | null>(null);
  const [form, setForm] = useState(emptyForm);
  const [saving, setSaving] = useState(false);
  const [error, setError] = useState("");

  const load = () => {
    const params: Record<string, string> = {};
    if (search) params.search = search;
    Promise.all([usersApi.list(params), usersApi.roles()])
      .then(([u, r]) => {
        setUsers(u.data);
        setRoles(r.data);
      })
      .finally(() => setLoading(false));
  };

  useEffect(() => { load(); }, [search]);

  const openCreate = () => {
    setEditing(null);
    setForm({ ...emptyForm, role_ids: roles[0] ? [roles[0].id] : [] });
    setError("");
    setShowForm(true);
  };

  const openEdit = (u: AdminUser) => {
    setEditing(u);
    setForm({
      name: u.name,
      email: u.email,
      password: "",
      phone: u.phone || "",
      position: u.position || "",
      is_active: u.is_active,
      role_ids: u.roles.map((r) => r.id),
    });
    setError("");
    setShowForm(true);
  };

  const toggleRole = (roleId: number) => {
    setForm((prev) => ({
      ...prev,
      role_ids: prev.role_ids.includes(roleId)
        ? prev.role_ids.filter((id) => id !== roleId)
        : [...prev.role_ids, roleId],
    }));
  };

  const handleSave = async () => {
    if (!form.name.trim() || !form.email.trim()) {
      setError("กรุณากรอกชื่อและอีเมล");
      return;
    }
    if (!editing && !form.password) {
      setError("กรุณากรอกรหัสผ่าน");
      return;
    }
    if (form.role_ids.length === 0) {
      setError("กรุณาเลือก Role อย่างน้อย 1 รายการ");
      return;
    }

    setSaving(true);
    setError("");
    try {
      if (editing) {
        const payload: Record<string, unknown> = {
          name: form.name,
          email: form.email,
          phone: form.phone || undefined,
          position: form.position || undefined,
          is_active: form.is_active,
          role_ids: form.role_ids,
        };
        if (form.password) payload.password = form.password;
        await usersApi.update(editing.id, payload);
      } else {
        await usersApi.create({
          name: form.name,
          email: form.email,
          password: form.password,
          phone: form.phone || undefined,
          position: form.position || undefined,
          is_active: form.is_active,
          role_ids: form.role_ids,
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

  const handleDelete = async (u: AdminUser) => {
    if (!confirm(`ลบผู้ใช้ ${u.name} (${u.email}) ?`)) return;
    try {
      await usersApi.delete(u.id);
      load();
    } catch (e) {
      alert(e instanceof Error ? e.message : "ลบไม่สำเร็จ");
    }
  };

  if (!hasPermission("admin.users")) {
    return (
      <div className="flex flex-1 items-center justify-center">
        <p className="text-sm text-red-500">ไม่มีสิทธิ์เข้าถึงหน้านี้</p>
      </div>
    );
  }

  return (
    <>
      <Header
        title="จัดการผู้ใช้"
        subtitle="เพิ่ม ลบ และกำหนด Role สำหรับเข้าสู่ระบบ"
        actions={
          <button onClick={openCreate} className="rounded-lg bg-amber-400 px-4 py-2 text-sm font-semibold text-zinc-900 hover:bg-amber-500">
            + เพิ่มผู้ใช้
          </button>
        }
      />
      <div className="flex-1 overflow-y-auto p-6">
        <div className="mb-4">
          <input
            type="text"
            placeholder="ค้นหาชื่อหรืออีเมล..."
            value={search}
            onChange={(e) => setSearch(e.target.value)}
            className="rounded-lg border border-zinc-300 px-3 py-2 text-sm focus:border-amber-400 focus:outline-none focus:ring-2 focus:ring-amber-400/20"
          />
        </div>

        {loading ? (
          <div className="flex justify-center py-12">
            <div className="h-8 w-8 animate-spin rounded-full border-4 border-amber-400 border-t-transparent" />
          </div>
        ) : (
          <div className="overflow-hidden rounded-xl border border-zinc-200 bg-white shadow-sm">
            <table className="w-full text-sm">
              <thead>
                <tr className="border-b bg-zinc-50 text-left text-xs uppercase text-zinc-500">
                  <th className="px-5 py-3">ชื่อ</th>
                  <th className="px-5 py-3">อีเมล</th>
                  <th className="px-5 py-3">ตำแหน่ง</th>
                  <th className="px-5 py-3">Role</th>
                  <th className="px-5 py-3">สถานะ</th>
                  <th className="px-5 py-3 text-right">การดำเนินการ</th>
                </tr>
              </thead>
              <tbody>
                {users.map((u) => (
                  <tr key={u.id} className="border-b hover:bg-zinc-50">
                    <td className="px-5 py-3 font-medium">{u.name}</td>
                    <td className="px-5 py-3 text-zinc-600">{u.email}</td>
                    <td className="px-5 py-3 text-zinc-500">{u.position || "—"}</td>
                    <td className="px-5 py-3">
                      <div className="flex flex-wrap gap-1">
                        {u.roles.map((r) => (
                          <span key={r.id} className="rounded-full bg-zinc-100 px-2 py-0.5 text-xs">{r.label}</span>
                        ))}
                      </div>
                    </td>
                    <td className="px-5 py-3">
                      <span className={`rounded-full px-2 py-0.5 text-xs font-medium ${u.is_active ? "bg-green-100 text-green-800" : "bg-red-100 text-red-800"}`}>
                        {u.is_active ? "ใช้งาน" : "ปิดใช้งาน"}
                      </span>
                    </td>
                    <td className="px-5 py-3 text-right space-x-2">
                      <button onClick={() => openEdit(u)} className="text-amber-600 hover:underline text-xs">แก้ไข</button>
                      {u.id !== currentUser?.id && (
                        <button onClick={() => handleDelete(u)} className="text-red-600 hover:underline text-xs">ลบ</button>
                      )}
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        )}

        {showForm && (
          <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4">
            <div className="max-h-[90vh] w-full max-w-md overflow-y-auto rounded-xl bg-white p-6 shadow-xl">
              <h3 className="text-lg font-bold">{editing ? "แก้ไขผู้ใช้" : "เพิ่มผู้ใช้ใหม่"}</h3>
              <div className="mt-4 space-y-3">
                <input placeholder="ชื่อ-นามสกุล *" value={form.name} onChange={(e) => setForm({ ...form, name: e.target.value })} className="w-full rounded-lg border px-3 py-2 text-sm" />
                <input type="email" placeholder="อีเมล *" value={form.email} onChange={(e) => setForm({ ...form, email: e.target.value })} className="w-full rounded-lg border px-3 py-2 text-sm" />
                <input type="password" placeholder={editing ? "รหัสผ่านใหม่ (เว้นว่างถ้าไม่เปลี่ยน)" : "รหัสผ่าน *"} value={form.password} onChange={(e) => setForm({ ...form, password: e.target.value })} className="w-full rounded-lg border px-3 py-2 text-sm" />
                <input placeholder="เบอร์โทร" value={form.phone} onChange={(e) => setForm({ ...form, phone: e.target.value })} className="w-full rounded-lg border px-3 py-2 text-sm" />
                <input placeholder="ตำแหน่ง" value={form.position} onChange={(e) => setForm({ ...form, position: e.target.value })} className="w-full rounded-lg border px-3 py-2 text-sm" />
                <label className="flex items-center gap-2 text-sm">
                  <input type="checkbox" checked={form.is_active} onChange={(e) => setForm({ ...form, is_active: e.target.checked })} />
                  เปิดใช้งาน (สามารถ Login ได้)
                </label>
                <div>
                  <p className="mb-2 text-sm font-medium">Role *</p>
                  <div className="space-y-2">
                    {roles.map((r) => (
                      <label key={r.id} className="flex items-start gap-2 rounded-lg border p-3 text-sm cursor-pointer hover:bg-zinc-50">
                        <input type="checkbox" checked={form.role_ids.includes(r.id)} onChange={() => toggleRole(r.id)} className="mt-0.5" />
                        <div>
                          <p className="font-medium">{r.label}</p>
                          {r.description && <p className="text-xs text-zinc-500">{r.description}</p>}
                        </div>
                      </label>
                    ))}
                  </div>
                </div>
                {error && <p className="text-sm text-red-500">{error}</p>}
              </div>
              <div className="mt-6 flex justify-end gap-2">
                <button onClick={() => setShowForm(false)} className="rounded-lg border px-4 py-2 text-sm">ยกเลิก</button>
                <button onClick={handleSave} disabled={saving} className="rounded-lg bg-amber-400 px-4 py-2 text-sm font-semibold disabled:opacity-50">
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
