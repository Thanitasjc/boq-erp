"use client";

import { useEffect, useState } from "react";
import PageHeader from "@/components/layout/PageHeader";
import { Project, ReportType, projectsApi, reportApi } from "@/lib/api";
import { useAuth } from "@/contexts/AuthContext";

export default function ReportsPage() {
  const { hasPermission } = useAuth();
  const [reports, setReports] = useState<ReportType[]>([]);
  const [projects, setProjects] = useState<Project[]>([]);
  const [selectedType, setSelectedType] = useState<string>("dashboard");
  const [projectId, setProjectId] = useState("");
  const [loading, setLoading] = useState(false);

  useEffect(() => {
    reportApi.list().then((res) => {
      const visible = res.data.filter((r) => hasPermission(r.permission));
      setReports(visible);
      if (visible.length > 0) setSelectedType(visible[0].type);
    });
    projectsApi.list().then((res) => setProjects(res.data));
  }, [hasPermission]);

  const selected = reports.find((r) => r.type === selectedType);

  const handleDownload = async () => {
    if (!selected) return;
    if (selected.requires_project && !projectId) return;
    setLoading(true);
    try {
      const params: Record<string, string> = {};
      if (projectId) params.project_id = projectId;
      const blob = await reportApi.download(selected.type, params);
      const url = URL.createObjectURL(blob);
      const a = document.createElement("a");
      a.href = url;
      a.download = `report-${selected.type}-${new Date().toISOString().slice(0, 10)}.xlsx`;
      a.click();
      URL.revokeObjectURL(url);
    } finally {
      setLoading(false);
    }
  };

  return (
    <>
      <PageHeader
        breadcrumbs={[{ label: "หน้าแรก", href: "/dashboard" }, { label: "ศูนย์รายงาน" }]}
        title="ศูนย์รายงาน"
        meta="ดาวน์โหลดรายงาน Excel ตามประเภท"
      />
      <div className="flex-1 overflow-y-auto p-6">
        <div className="grid gap-6 lg:grid-cols-3">
          <div className="space-y-2">
            <p className="text-sm font-medium text-zinc-700">ประเภทรายงาน</p>
            {reports.map((r) => (
              <button key={r.type} onClick={() => setSelectedType(r.type)}
                className={`block w-full rounded-lg border px-4 py-3 text-left text-sm transition-colors ${selectedType === r.type ? "border-amber-400 bg-amber-50 font-semibold" : "hover:bg-zinc-50"}`}>
                {r.label}
              </button>
            ))}
          </div>

          <div className="lg:col-span-2 rounded-xl border border-zinc-200 bg-white p-6 shadow-sm">
            {selected ? (
              <>
                <h2 className="text-lg font-bold">{selected.label}</h2>
                <p className="mt-1 text-sm text-zinc-500">
                  {selected.requires_project ? "เลือกโครงการก่อนดาวน์โหลด" : "ดาวน์โหลดรายงานระดับบริษัท"}
                </p>
                {selected.requires_project && (
                  <select value={projectId} onChange={(e) => setProjectId(e.target.value)} className="mt-4 w-full rounded-lg border px-3 py-2 text-sm">
                    <option value="">— เลือกโครงการ —</option>
                    {projects.map((p) => (
                      <option key={p.id} value={String(p.id)}>{p.code} — {p.name}</option>
                    ))}
                  </select>
                )}
                <button
                  onClick={handleDownload}
                  disabled={loading || (selected.requires_project && !projectId)}
                  className="mt-6 rounded-lg bg-amber-400 px-6 py-2.5 text-sm font-semibold text-zinc-900 disabled:opacity-50"
                >
                  {loading ? "กำลังสร้าง..." : "ดาวน์โหลด Excel"}
                </button>
              </>
            ) : (
              <p className="text-zinc-500">ไม่มีรายงานที่คุณมีสิทธิ์เข้าถึง</p>
            )}
          </div>
        </div>
      </div>
    </>
  );
}
