"use client";

import PageHeader, { SimpleHeader } from "@/components/layout/PageHeader";

interface HeaderProps {
  title: string;
  subtitle?: string;
  actions?: React.ReactNode;
  breadcrumbs?: { label: string; href?: string }[];
  badge?: string;
}

export default function Header({ title, subtitle, actions, breadcrumbs, badge }: HeaderProps) {
  if (breadcrumbs || badge) {
    return (
      <PageHeader
        breadcrumbs={breadcrumbs}
        title={title}
        badge={badge}
        meta={subtitle}
        actions={actions}
      />
    );
  }

  return <SimpleHeader title={title} subtitle={subtitle} actions={actions} />;
}
