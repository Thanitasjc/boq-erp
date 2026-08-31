"use client";

import { useEffect, useState } from "react";
import Link from "next/link";
import { useParams } from "next/navigation";
import Header from "@/components/layout/Header";
import { BoqVersion, boqApi } from "@/lib/api";
import { formatMoney } from "@/lib/utils";
import { useAuth } from "@/contexts/AuthContext";

function statusBadge(status: string) {
  const styles: Record<string, string> = {
    draft: "bg-zinc-100 text-zinc-700",
    submitted: "bg-blue-100 text-blue-800",
    approved: "bg-green-100 text-green-800",
    rejected: "bg-red-100 text-red-800",
  };
  return styles[status] || "bg-zinc-100 text-zinc-700";
}

export default function BoqListPage() {
  const params = useParams();
  const projectId = Number(params.id);
  const { hasPermission } = useAuth();
  const [versions, setVersions] = useState<BoqVersion[]>([]);
  const [loading, setLoading] = useState(true);
  const [creating, setCreating] = useState(false);

  const load = () => {
    boqApi
      .list(projectId)
      .then((res) => setVersions(res.data))
      .catch(() => setVersions([]))
      .finally(() => setLoading(false));
  };

  useEffect(() => {
    load();
  }, [projectId]);

  const handleCreate = async () => {
    setCreating(true);
    try {
      await boqApi.create(projectId, { title: "New BOQ Version" });
      load();
    } finally {
      setCreating(false);
    }
  };

  return (
    <>
      <Header
        title="BOQ & Estimate"
        subtitle={`Project #${projectId}`}
        actions={
          <div className="flex gap-2">
            <Link
              href={`/projects/${projectId}/dashboard`}
              className="rounded-lg border border-zinc-300 px-4 py-2 text-sm hover:bg-zinc-50"
            >
              Back to Project
            </Link>
            {hasPermission("boq.create") && (
              <button
                onClick={handleCreate}
                disabled={creating}
                className="rounded-lg bg-amber-400 px-4 py-2 text-sm font-semibold text-zinc-900 hover:bg-amber-500 disabled:opacity-50"
              >
                {creating ? "Creating..." : "+ New BOQ Version"}
              </button>
            )}
          </div>
        }
      />
      <div className="flex-1 overflow-y-auto p-6">
        {loading ? (
          <div className="flex justify-center py-12">
            <div className="h-8 w-8 animate-spin rounded-full border-4 border-amber-400 border-t-transparent" />
          </div>
        ) : versions.length === 0 ? (
          <div className="rounded-xl border border-dashed border-zinc-300 bg-white py-16 text-center">
            <p className="text-zinc-500">No BOQ versions yet.</p>
            {hasPermission("boq.create") && (
              <button
                onClick={handleCreate}
                className="mt-4 rounded-lg bg-amber-400 px-4 py-2 text-sm font-semibold text-zinc-900"
              >
                Create First BOQ
              </button>
            )}
          </div>
        ) : (
          <div className="overflow-hidden rounded-xl border border-zinc-200 bg-white shadow-sm">
            <table className="w-full text-sm">
              <thead>
                <tr className="border-b border-zinc-200 bg-zinc-50 text-left text-xs uppercase tracking-wider text-zinc-500">
                  <th className="px-5 py-3">Doc No</th>
                  <th className="px-5 py-3">Version</th>
                  <th className="px-5 py-3">Title</th>
                  <th className="px-5 py-3">Status</th>
                  <th className="px-5 py-3 text-right">Items</th>
                  <th className="px-5 py-3 text-right">Total</th>
                  <th className="px-5 py-3"></th>
                </tr>
              </thead>
              <tbody>
                {versions.map((v) => (
                  <tr key={v.id} className="border-b border-zinc-50 hover:bg-zinc-50">
                    <td className="px-5 py-3 font-mono text-xs">{v.document_number}</td>
                    <td className="px-5 py-3 font-medium">v{v.version_number}</td>
                    <td className="px-5 py-3">{v.title}</td>
                    <td className="px-5 py-3">
                      <span className={`rounded-full px-2 py-0.5 text-xs font-medium capitalize ${statusBadge(v.status)}`}>
                        {v.status}
                      </span>
                    </td>
                    <td className="px-5 py-3 text-right">{v.items_count ?? 0}</td>
                    <td className="px-5 py-3 text-right font-medium">{formatMoney(v.total_amount)}</td>
                    <td className="px-5 py-3 text-right">
                      <Link
                        href={`/projects/${projectId}/boq/${v.id}`}
                        className="text-amber-600 hover:text-amber-700 font-medium"
                      >
                        Open →
                      </Link>
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        )}
      </div>
    </>
  );
}
