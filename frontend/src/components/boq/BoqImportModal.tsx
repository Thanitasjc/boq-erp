"use client";

import { useState } from "react";
import { ImportPreview, boqApi } from "@/lib/api";

interface Props {
  projectId: number;
  versionId: number;
  onClose: () => void;
  onSuccess: () => void;
}

export default function BoqImportModal({ projectId, versionId, onClose, onSuccess }: Props) {
  const [file, setFile] = useState<File | null>(null);
  const [preview, setPreview] = useState<ImportPreview | null>(null);
  const [loading, setLoading] = useState(false);
  const [step, setStep] = useState<"upload" | "preview" | "done">("upload");
  const [result, setResult] = useState<{ success: number; failed: number; warning: number } | null>(null);
  const [replaceExisting, setReplaceExisting] = useState(false);

  const handleFileChange = async (e: React.ChangeEvent<HTMLInputElement>) => {
    const f = e.target.files?.[0];
    if (!f) return;
    setFile(f);
    setLoading(true);
    try {
      const data = await boqApi.importPreview(projectId, versionId, f);
      setPreview(data);
      setStep("preview");
    } catch {
      alert("Failed to preview file. Check format and try again.");
    } finally {
      setLoading(false);
    }
  };

  const handleImport = async () => {
    if (!file || !preview) return;
    setLoading(true);
    try {
      const res = await boqApi.importConfirm(
        projectId,
        versionId,
        file,
        preview.mapped_columns,
        replaceExisting,
      ) as { data: { summary: { success: number; failed: number; warning: number } } };
      setResult(res.data.summary);
      setStep("done");
    } catch {
      alert("Import failed.");
    } finally {
      setLoading(false);
    }
  };

  return (
    <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4">
      <div className="max-h-[90vh] w-full max-w-3xl overflow-y-auto rounded-2xl bg-white shadow-2xl">
        <div className="flex items-center justify-between border-b border-zinc-200 px-6 py-4">
          <h2 className="text-lg font-bold">Import BOQ from Excel</h2>
          <button onClick={onClose} className="text-zinc-400 hover:text-zinc-600">✕</button>
        </div>

        <div className="p-6">
          {step === "upload" && (
            <div className="text-center">
              <p className="mb-4 text-sm text-zinc-500">
                Upload .xlsx, .xls or .csv file. Columns will be auto-mapped.
              </p>
              <label className="inline-flex cursor-pointer items-center gap-2 rounded-lg bg-amber-400 px-6 py-3 text-sm font-semibold text-zinc-900 hover:bg-amber-500">
                {loading ? "Processing..." : "Choose File"}
                <input
                  type="file"
                  accept=".xlsx,.xls,.csv"
                  className="hidden"
                  onChange={handleFileChange}
                  disabled={loading}
                />
              </label>
            </div>
          )}

          {step === "preview" && preview && (
            <div>
              <div className="mb-4 grid grid-cols-4 gap-3">
                {(["total", "valid", "warning", "error"] as const).map((key) => (
                  <div key={key} className="rounded-lg bg-zinc-50 p-3 text-center">
                    <p className="text-xs capitalize text-zinc-500">{key}</p>
                    <p className="text-xl font-bold">{preview.summary[key]}</p>
                  </div>
                ))}
              </div>

              <label className="mb-4 flex items-center gap-2 text-sm">
                <input
                  type="checkbox"
                  checked={replaceExisting}
                  onChange={(e) => setReplaceExisting(e.target.checked)}
                />
                Replace existing items
              </label>

              <div className="mb-4 max-h-60 overflow-auto rounded-lg border border-zinc-200">
                <table className="w-full text-xs">
                  <thead className="sticky top-0 bg-zinc-50">
                    <tr>
                      <th className="px-2 py-2">Row</th>
                      <th className="px-2 py-2">Description</th>
                      <th className="px-2 py-2">Status</th>
                      <th className="px-2 py-2">Issues</th>
                    </tr>
                  </thead>
                  <tbody>
                    {preview.rows.slice(0, 50).map((row) => (
                      <tr key={row.row} className="border-t border-zinc-100">
                        <td className="px-2 py-1">{row.row}</td>
                        <td className="px-2 py-1">{row.data.description}</td>
                        <td className="px-2 py-1">
                          <span className={`rounded px-1.5 py-0.5 ${
                            row.status === "valid" ? "bg-green-100 text-green-800" :
                            row.status === "warning" ? "bg-yellow-100 text-yellow-800" :
                            "bg-red-100 text-red-800"
                          }`}>
                            {row.status}
                          </span>
                        </td>
                        <td className="px-2 py-1 text-red-600">
                          {[...row.errors, ...row.warnings].join(", ")}
                        </td>
                      </tr>
                    ))}
                  </tbody>
                </table>
              </div>

              <div className="flex justify-end gap-2">
                <button onClick={() => setStep("upload")} className="rounded-lg border px-4 py-2 text-sm">
                  Back
                </button>
                <button
                  onClick={handleImport}
                  disabled={loading || preview.summary.error === preview.summary.total}
                  className="rounded-lg bg-amber-400 px-4 py-2 text-sm font-semibold disabled:opacity-50"
                >
                  {loading ? "Importing..." : `Import ${preview.summary.valid + preview.summary.warning} rows`}
                </button>
              </div>
            </div>
          )}

          {step === "done" && result && (
            <div className="text-center">
              <p className="text-lg font-semibold text-green-600">Import Complete</p>
              <p className="mt-2 text-sm text-zinc-500">
                Success: {result.success} · Failed: {result.failed} · Warnings: {result.warning}
              </p>
              <button
                onClick={onSuccess}
                className="mt-6 rounded-lg bg-amber-400 px-6 py-2 text-sm font-semibold"
              >
                Done
              </button>
            </div>
          )}
        </div>
      </div>
    </div>
  );
}
