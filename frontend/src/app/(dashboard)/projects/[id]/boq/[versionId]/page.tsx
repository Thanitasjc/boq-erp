"use client";

import { useCallback, useEffect, useState } from "react";
import Link from "next/link";
import { useParams } from "next/navigation";
import Header from "@/components/layout/Header";
import BoqImportModal from "@/components/boq/BoqImportModal";
import { BoqDetail, BoqItem, apiDownload, boqApi } from "@/lib/api";
import { formatMoney } from "@/lib/utils";
import { useAuth } from "@/contexts/AuthContext";

export default function BoqEditorPage() {
  const params = useParams();
  const projectId = Number(params.id);
  const versionId = Number(params.versionId);
  const { hasPermission } = useAuth();

  const [boq, setBoq] = useState<BoqDetail | null>(null);
  const [loading, setLoading] = useState(true);
  const [showImport, setShowImport] = useState(false);
  const [actionLoading, setActionLoading] = useState(false);

  const load = useCallback(() => {
    setLoading(true);
    boqApi
      .get(projectId, versionId)
      .then(setBoq)
      .catch(() => setBoq(null))
      .finally(() => setLoading(false));
  }, [projectId, versionId]);

  useEffect(() => {
    load();
  }, [load]);

  const handleSubmit = async () => {
    setActionLoading(true);
    try {
      await boqApi.submit(projectId, versionId);
      load();
    } finally {
      setActionLoading(false);
    }
  };

  const handleApprove = async () => {
    setActionLoading(true);
    try {
      await boqApi.approve(projectId, versionId);
      load();
    } finally {
      setActionLoading(false);
    }
  };

  const handleReject = async () => {
    const comment = prompt("Rejection reason:");
    if (!comment) return;
    setActionLoading(true);
    try {
      await boqApi.reject(projectId, versionId, comment);
      load();
    } finally {
      setActionLoading(false);
    }
  };

  const handleExport = async () => {
    const blob = await apiDownload(boqApi.exportUrl(projectId, versionId));
    const url = URL.createObjectURL(blob);
    const a = document.createElement("a");
    a.href = url;
    a.download = `BOQ_v${boq?.data.version_number}.xlsx`;
    a.click();
    URL.revokeObjectURL(url);
  };

  const handleDeleteItem = async (item: BoqItem) => {
    if (!confirm("Delete this item?")) return;
    await boqApi.deleteItem(projectId, versionId, item.id);
    load();
  };

  if (loading) {
    return (
      <div className="flex flex-1 items-center justify-center">
        <div className="h-8 w-8 animate-spin rounded-full border-4 border-amber-400 border-t-transparent" />
      </div>
    );
  }

  if (!boq) {
    return (
      <div className="flex flex-1 items-center justify-center text-red-500">
        BOQ not found
      </div>
    );
  }

  const { data: version, items } = boq;

  return (
    <>
      <Header
        title={`BOQ v${version.version_number}`}
        subtitle={version.title}
        actions={
          <div className="flex flex-wrap gap-2">
            <Link
              href={`/projects/${projectId}/boq`}
              className="rounded-lg border border-zinc-300 px-3 py-2 text-sm hover:bg-zinc-50"
            >
              ← Back
            </Link>
            {version.is_editable && hasPermission("boq.import") && (
              <button
                onClick={() => setShowImport(true)}
                className="rounded-lg border border-zinc-300 px-3 py-2 text-sm hover:bg-zinc-50"
              >
                Import Excel
              </button>
            )}
            {hasPermission("boq.export") && (
              <button
                onClick={handleExport}
                className="rounded-lg border border-zinc-300 px-3 py-2 text-sm hover:bg-zinc-50"
              >
                Export Excel
              </button>
            )}
            {version.is_editable && hasPermission("boq.edit") && version.status === "draft" && (
              <button
                onClick={handleSubmit}
                disabled={actionLoading || items.length === 0}
                className="rounded-lg bg-blue-600 px-3 py-2 text-sm font-medium text-white hover:bg-blue-700 disabled:opacity-50"
              >
                Submit
              </button>
            )}
            {version.status === "submitted" && hasPermission("boq.approve") && (
              <>
                <button
                  onClick={handleApprove}
                  disabled={actionLoading}
                  className="rounded-lg bg-green-600 px-3 py-2 text-sm font-medium text-white hover:bg-green-700"
                >
                  Approve
                </button>
                <button
                  onClick={handleReject}
                  disabled={actionLoading}
                  className="rounded-lg bg-red-600 px-3 py-2 text-sm font-medium text-white hover:bg-red-700"
                >
                  Reject
                </button>
              </>
            )}
          </div>
        }
      />

      <div className="flex-1 overflow-y-auto p-6">
        <div className="mb-4 grid grid-cols-2 gap-4 sm:grid-cols-4">
          <div className="rounded-xl border border-zinc-200 bg-white p-4">
            <p className="text-xs text-zinc-500">Status</p>
            <p className="mt-1 font-semibold capitalize">{version.status}</p>
          </div>
          <div className="rounded-xl border border-zinc-200 bg-white p-4">
            <p className="text-xs text-zinc-500">Items</p>
            <p className="mt-1 font-semibold">{items.length}</p>
          </div>
          <div className="rounded-xl border border-zinc-200 bg-white p-4">
            <p className="text-xs text-zinc-500">Total Amount</p>
            <p className="mt-1 font-semibold text-amber-600">{formatMoney(version.total_amount)}</p>
          </div>
          <div className="rounded-xl border border-zinc-200 bg-white p-4">
            <p className="text-xs text-zinc-500">Document No</p>
            <p className="mt-1 font-mono text-sm">{version.document_number}</p>
          </div>
        </div>

        {version.rejection_reason && (
          <div className="mb-4 rounded-lg bg-red-50 px-4 py-3 text-sm text-red-700">
            Rejected: {version.rejection_reason}
          </div>
        )}

        <div className="overflow-hidden rounded-xl border border-zinc-200 bg-white shadow-sm">
          <div className="overflow-x-auto">
            <table className="w-full min-w-[900px] text-sm">
              <thead>
                <tr className="border-b border-zinc-200 bg-zinc-50 text-left text-xs uppercase tracking-wider text-zinc-500">
                  <th className="px-3 py-3">#</th>
                  <th className="px-3 py-3">Cost Code</th>
                  <th className="px-3 py-3">Item</th>
                  <th className="px-3 py-3">Description</th>
                  <th className="px-3 py-3">UOM</th>
                  <th className="px-3 py-3 text-right">Qty</th>
                  <th className="px-3 py-3 text-right">Unit Rate</th>
                  <th className="px-3 py-3 text-right">Amount</th>
                  {version.is_editable && <th className="px-3 py-3"></th>}
                </tr>
              </thead>
              <tbody>
                {items.map((item, i) => (
                  <tr key={item.id} className="border-b border-zinc-50 hover:bg-zinc-50">
                    <td className="px-3 py-2 text-zinc-400">{i + 1}</td>
                    <td className="px-3 py-2 font-mono text-xs">{item.cost_code}</td>
                    <td className="px-3 py-2 font-mono text-xs">{item.item_code}</td>
                    <td className="px-3 py-2">{item.description}</td>
                    <td className="px-3 py-2">{item.uom_code}</td>
                    <td className="px-3 py-2 text-right">{item.quantity.toLocaleString()}</td>
                    <td className="px-3 py-2 text-right">{item.unit_rate.toLocaleString()}</td>
                    <td className="px-3 py-2 text-right font-medium">{formatMoney(item.amount)}</td>
                    {version.is_editable && (
                      <td className="px-3 py-2">
                        <button
                          onClick={() => handleDeleteItem(item)}
                          className="text-xs text-red-500 hover:text-red-700"
                        >
                          Delete
                        </button>
                      </td>
                    )}
                  </tr>
                ))}
              </tbody>
              <tfoot>
                <tr className="bg-zinc-50 font-semibold">
                  <td colSpan={7} className="px-3 py-3 text-right">Total</td>
                  <td className="px-3 py-3 text-right text-amber-600">
                    {formatMoney(version.total_amount)}
                  </td>
                  {version.is_editable && <td></td>}
                </tr>
              </tfoot>
            </table>
          </div>
        </div>
      </div>

      {showImport && (
        <BoqImportModal
          projectId={projectId}
          versionId={versionId}
          onClose={() => setShowImport(false)}
          onSuccess={() => {
            setShowImport(false);
            load();
          }}
        />
      )}
    </>
  );
}
