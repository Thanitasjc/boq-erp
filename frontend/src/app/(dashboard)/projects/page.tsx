"use client";

import { useCallback, useEffect, useState } from "react";
import Link from "next/link";
import { useRouter, useSearchParams } from "next/navigation";
import Header from "@/components/layout/Header";
import { useAuth } from "@/contexts/AuthContext";
import { useProjectContext } from "@/contexts/ProjectContext";
import { Project, projectsApi } from "@/lib/api";
import { formatMoney, statusColor, statusLabel } from "@/lib/utils";

const emptyForm = {
  name: "",
  client_name: "",
  status: "planning",
  start_date: "",
  end_date: "",
  contract_value: 0,
  location: "",
  description: "",
};

export default function ProjectsPage() {
  const router = useRouter();
  const searchParams = useSearchParams();
  const { hasPermission } = useAuth();
  const { setSelectedProjectId } = useProjectContext();
  const [projects, setProjects] = useState<Project[]>([]);
  const [loading, setLoading] = useState(true);
  const [search, setSearch] = useState("");
  const [statusFilter, setStatusFilter] = useState("");
  const [showForm, setShowForm] = useState(false);
  const [saving, setSaving] = useState(false);
  const [form, setForm] = useState(emptyForm);
  const [error, setError] = useState("");

  const load = useCallback(() => {
    setLoading(true);
    const params: Record<string, string> = {};
    if (search) params.search = search;
    if (statusFilter) params.status = statusFilter;

    projectsApi
      .list(params)
      .then((res) => setProjects(res.data))
      .catch(() => setProjects([]))
      .finally(() => setLoading(false));
  }, [search, statusFilter]);

  useEffect(() => { load(); }, [load]);

  useEffect(() => {
    if (searchParams.get("new") === "1" && hasPermission("projects.create")) {
      setShowForm(true);
    }
  }, [searchParams, hasPermission]);

  const handleCreate = async () => {
    if (!form.name.trim()) {
      setError("กรุณากรอกชื่อโครงการ");
      return;
    }
    setSaving(true);
    setError("");
    try {
      const res = await projectsApi.create({
        name: form.name.trim(),
        client_name: form.client_name || undefined,
        status: form.status,
        start_date: form.start_date || undefined,
        end_date: form.end_date || undefined,
        contract_value: form.contract_value || undefined,
        location: form.location || undefined,
        description: form.description || undefined,
      });
      setShowForm(false);
      setForm(emptyForm);
      setSelectedProjectId(res.data.id);
      router.push(`/projects/${res.data.id}/dashboard`);
    } catch (e) {
      setError(e instanceof Error ? e.message : "ไม่สามารถสร้างโครงการได้");
    } finally {
      setSaving(false);
    }
  };

  return (
    <>
      <Header
        title="โครงการ"
        subtitle="จัดการโครงการก่อสร้างทั้งหมด"
        actions={
          hasPermission("projects.create") ? (
            <button
              onClick={() => setShowForm(true)}
              className="rounded-lg bg-amber-400 px-4 py-2 text-sm font-semibold text-zinc-900 hover:bg-amber-500"
            >
              + โครงการใหม่
            </button>
          ) : undefined
        }
      />
      <div className="flex-1 overflow-y-auto p-6">
        <div className="mb-4 flex flex-wrap gap-3">
          <input
            type="text"
            placeholder="ค้นหาโครงการ..."
            value={search}
            onChange={(e) => setSearch(e.target.value)}
            className="rounded-lg border border-zinc-300 px-3 py-2 text-sm focus:border-amber-400 focus:outline-none focus:ring-2 focus:ring-amber-400/20"
          />
          <select
            value={statusFilter}
            onChange={(e) => setStatusFilter(e.target.value)}
            className="rounded-lg border border-zinc-300 px-3 py-2 text-sm focus:border-amber-400 focus:outline-none"
          >
            <option value="">ทุกสถานะ</option>
            <option value="planning">วางแผน</option>
            <option value="active">ดำเนินการ</option>
            <option value="on_hold">พักงาน</option>
            <option value="completed">เสร็จสิ้น</option>
          </select>
        </div>

        {loading ? (
          <div className="flex justify-center py-12">
            <div className="h-8 w-8 animate-spin rounded-full border-4 border-amber-400 border-t-transparent" />
          </div>
        ) : projects.length === 0 ? (
          <div className="rounded-xl border border-dashed border-zinc-300 bg-white py-16 text-center">
            <p className="text-zinc-500">ไม่พบโครงการ</p>
            {hasPermission("projects.create") && (
              <button onClick={() => setShowForm(true)} className="mt-4 text-sm text-amber-600 hover:underline">
                + สร้างโครงการแรก
              </button>
            )}
          </div>
        ) : (
          <div className="overflow-hidden rounded-xl border border-zinc-200 bg-white shadow-sm">
            <table className="w-full text-sm">
              <thead>
                <tr className="border-b border-zinc-200 bg-zinc-50 text-left text-xs uppercase tracking-wider text-zinc-500">
                  <th className="px-5 py-3">รหัส</th>
                  <th className="px-5 py-3">ชื่อโครงการ</th>
                  <th className="px-5 py-3">ลูกค้า</th>
                  <th className="px-5 py-3">สถานะ</th>
                  <th className="px-5 py-3">PM</th>
                  <th className="px-5 py-3 text-right">มูลค่าสัญญา</th>
                  <th className="px-5 py-3 text-right">งบประมาณ</th>
                  <th className="px-5 py-3 text-right">เมนู</th>
                </tr>
              </thead>
              <tbody>
                {projects.map((p) => (
                  <tr key={p.id} className="border-b border-zinc-50 hover:bg-zinc-50">
                    <td className="px-5 py-3 font-mono text-xs font-medium">{p.code}</td>
                    <td className="px-5 py-3 font-medium">{p.name}</td>
                    <td className="px-5 py-3 text-zinc-500">{p.client_name || "—"}</td>
                    <td className="px-5 py-3">
                      <span className={`rounded-full px-2 py-0.5 text-xs font-medium ${statusColor(p.status)}`}>
                        {statusLabel(p.status)}
                      </span>
                    </td>
                    <td className="px-5 py-3 text-zinc-500">{p.project_manager?.name || "—"}</td>
                    <td className="px-5 py-3 text-right font-medium">{formatMoney(p.contract_value)}</td>
                    <td className="px-5 py-3 text-right">{formatMoney(p.revised_budget)}</td>
                    <td className="px-5 py-3 text-right whitespace-nowrap">
                      <Link href={`/projects/${p.id}/dashboard`} className="mr-2 text-amber-600 hover:text-amber-700 font-medium">Dashboard</Link>
                      <Link href={`/projects/${p.id}/boq`} className="mr-2 text-amber-600 hover:text-amber-700 font-medium">BOQ</Link>
                      <Link href={`/projects/${p.id}/contract`} className="mr-2 text-amber-600 hover:text-amber-700 font-medium">สัญญา</Link>
                      <Link href={`/projects/${p.id}/budget`} className="mr-2 text-amber-600 hover:text-amber-700 font-medium">งบ</Link>
                      <Link href={`/projects/${p.id}/pr`} className="mr-2 text-amber-600 hover:text-amber-700 font-medium">PR</Link>
                      <Link href={`/projects/${p.id}/po`} className="mr-2 text-amber-600 hover:text-amber-700 font-medium">PO</Link>
                      <Link href={`/projects/${p.id}/gr`} className="mr-2 text-amber-600 hover:text-amber-700 font-medium">GR</Link>
                      <Link href={`/projects/${p.id}/billing`} className="mr-2 text-amber-600 hover:text-amber-700 font-medium">การเงิน</Link>
                      <Link href={`/projects/${p.id}/vo`} className="mr-2 text-amber-600 hover:text-amber-700 font-medium">VO</Link>
                      <Link href={`/projects/${p.id}/daily-report`} className="text-amber-600 hover:text-amber-700 font-medium">หน้างาน</Link>
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        )}

        {showForm && (
          <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4">
            <div className="max-h-[90vh] w-full max-w-lg overflow-y-auto rounded-xl bg-white p-6 shadow-xl">
              <h3 className="text-lg font-bold">สร้างโครงการใหม่</h3>
              <p className="mt-1 text-sm text-zinc-500">ระบบจะสร้างรหัสโครงการอัตโนมัติ (PRJ-YYYY-#####)</p>
              <div className="mt-4 space-y-3">
                <input
                  placeholder="ชื่อโครงการ *"
                  value={form.name}
                  onChange={(e) => setForm({ ...form, name: e.target.value })}
                  className="w-full rounded-lg border px-3 py-2 text-sm"
                />
                <input
                  placeholder="ชื่อลูกค้า"
                  value={form.client_name}
                  onChange={(e) => setForm({ ...form, client_name: e.target.value })}
                  className="w-full rounded-lg border px-3 py-2 text-sm"
                />
                <input
                  placeholder="สถานที่"
                  value={form.location}
                  onChange={(e) => setForm({ ...form, location: e.target.value })}
                  className="w-full rounded-lg border px-3 py-2 text-sm"
                />
                <div className="grid grid-cols-2 gap-3">
                  <input
                    type="date"
                    value={form.start_date}
                    onChange={(e) => setForm({ ...form, start_date: e.target.value })}
                    className="rounded-lg border px-3 py-2 text-sm"
                  />
                  <input
                    type="date"
                    value={form.end_date}
                    onChange={(e) => setForm({ ...form, end_date: e.target.value })}
                    className="rounded-lg border px-3 py-2 text-sm"
                  />
                </div>
                <input
                  type="number"
                  placeholder="มูลค่าสัญญา (บาท)"
                  value={form.contract_value || ""}
                  onChange={(e) => setForm({ ...form, contract_value: Number(e.target.value) })}
                  className="w-full rounded-lg border px-3 py-2 text-sm"
                />
                <select
                  value={form.status}
                  onChange={(e) => setForm({ ...form, status: e.target.value })}
                  className="w-full rounded-lg border px-3 py-2 text-sm"
                >
                  <option value="planning">วางแผน</option>
                  <option value="active">ดำเนินการ</option>
                  <option value="on_hold">พักงาน</option>
                </select>
                <textarea
                  placeholder="รายละเอียด"
                  value={form.description}
                  onChange={(e) => setForm({ ...form, description: e.target.value })}
                  className="w-full rounded-lg border px-3 py-2 text-sm"
                  rows={2}
                />
                {error && <p className="text-sm text-red-500">{error}</p>}
              </div>
              <div className="mt-6 flex justify-end gap-2">
                <button onClick={() => { setShowForm(false); setError(""); }} className="rounded-lg border px-4 py-2 text-sm">ยกเลิก</button>
                <button
                  onClick={handleCreate}
                  disabled={saving || !form.name.trim()}
                  className="rounded-lg bg-amber-400 px-4 py-2 text-sm font-semibold text-zinc-900 disabled:opacity-50"
                >
                  {saving ? "กำลังบันทึก..." : "สร้างโครงการ"}
                </button>
              </div>
            </div>
          </div>
        )}
      </div>
    </>
  );
}
