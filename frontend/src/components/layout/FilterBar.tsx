"use client";

interface FilterBarProps {
  children?: React.ReactNode;
  onSearch?: () => void;
  onClear?: () => void;
}

export default function FilterBar({ children, onSearch, onClear }: FilterBarProps) {
  return (
    <div className="border-b border-zinc-200 bg-white px-6 py-3">
      <div className="flex flex-wrap items-center gap-3">
        <div className="flex items-center gap-2 text-sm font-medium text-zinc-600">
          <svg className="h-4 w-4 text-amber-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.293A1 1 0 013 6.586V4z" />
          </svg>
          ตัวกรอง
        </div>
        {children}
        <div className="ml-auto flex gap-2">
          {onSearch && (
            <button onClick={onSearch}
              className="flex items-center gap-1.5 rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700">
              <svg className="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
              </svg>
              ค้นหา
            </button>
          )}
          {onClear && (
            <button onClick={onClear}
              className="rounded-lg border border-zinc-300 px-4 py-2 text-sm text-zinc-600 hover:bg-zinc-50">
              ล้าง
            </button>
          )}
        </div>
      </div>
    </div>
  );
}

export function FilterSelect({ value, onChange, children, className }: {
  value: string; onChange: (v: string) => void; children: React.ReactNode; className?: string;
}) {
  return (
    <select value={value} onChange={(e) => onChange(e.target.value)}
      className={`rounded-lg border border-zinc-200 bg-white px-3 py-2 text-sm text-zinc-700 focus:border-amber-400 focus:outline-none ${className || ""}`}>
      {children}
    </select>
  );
}
