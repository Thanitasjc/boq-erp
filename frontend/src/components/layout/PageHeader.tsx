"use client";

import Link from "next/link";

interface Breadcrumb {
  label: string;
  href?: string;
}

interface PageHeaderProps {
  breadcrumbs?: Breadcrumb[];
  title: string;
  badge?: string;
  meta?: string;
  actions?: React.ReactNode;
}

export default function PageHeader({
  breadcrumbs,
  title,
  badge,
  meta,
  actions,
}: PageHeaderProps) {
  return (
    <div className="border-b border-zinc-200 bg-gradient-to-r from-slate-50 to-zinc-50 px-6 py-5">
      {breadcrumbs && breadcrumbs.length > 0 && (
        <nav className="mb-2 flex items-center gap-1.5 text-sm text-zinc-500">
          <svg className="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6" />
          </svg>
          {breadcrumbs.map((crumb, i) => (
            <span key={i} className="flex items-center gap-1.5">
              {i > 0 && <span className="text-zinc-300">›</span>}
              {crumb.href ? (
                <Link href={crumb.href} className="hover:text-amber-600">{crumb.label}</Link>
              ) : (
                <span className="text-zinc-700">{crumb.label}</span>
              )}
            </span>
          ))}
        </nav>
      )}

      <div className="flex items-start justify-between">
        <div>
          <div className="flex items-center gap-3">
            <h1 className="text-2xl font-bold text-zinc-900">{title}</h1>
            {badge && (
              <span className="rounded-md bg-amber-100 px-2.5 py-0.5 text-xs font-semibold text-amber-800">
                {badge}
              </span>
            )}
          </div>
          {meta && <p className="mt-1 text-sm text-zinc-500">{meta}</p>}
        </div>
        {actions && <div className="flex items-center gap-2">{actions}</div>}
      </div>
    </div>
  );
}

// Keep backward-compatible simple header
export function SimpleHeader({ title, subtitle, actions }: { title: string; subtitle?: string; actions?: React.ReactNode }) {
  return (
    <PageHeader
      title={title}
      meta={subtitle}
      actions={actions}
    />
  );
}
