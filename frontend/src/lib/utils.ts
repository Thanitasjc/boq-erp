export function formatMoney(value: number): string {
  if (value >= 1_000_000) {
    return `฿ ${(value / 1_000_000).toFixed(2)} M`;
  }
  return `฿ ${value.toLocaleString("th-TH", { minimumFractionDigits: 2 })}`;
}

export function formatNumber(value: number): string {
  return value.toLocaleString("th-TH");
}

export function formatThaiDate(date: Date = new Date()): string {
  return date.toLocaleString("th-TH", {
    day: "numeric",
    month: "short",
    year: "numeric",
    hour: "2-digit",
    minute: "2-digit",
  });
}

export function statusColor(status: string): string {
  const colors: Record<string, string> = {
    active: "bg-green-100 text-green-800",
    planning: "bg-blue-100 text-blue-800",
    on_hold: "bg-yellow-100 text-yellow-800",
    completed: "bg-zinc-100 text-zinc-800",
    cancelled: "bg-red-100 text-red-800",
    draft: "bg-zinc-100 text-zinc-700",
    submitted: "bg-blue-100 text-blue-800",
    approved: "bg-green-100 text-green-800",
    rejected: "bg-red-100 text-red-800",
    issued: "bg-amber-100 text-amber-800",
    partially_received: "bg-purple-100 text-purple-800",
    confirmed: "bg-green-100 text-green-800",
  };
  return colors[status] || "bg-zinc-100 text-zinc-800";
}

export function statusLabel(status: string): string {
  const labels: Record<string, string> = {
    active: "ดำเนินการ",
    planning: "วางแผน",
    on_hold: "พักงาน",
    completed: "เสร็จสิ้น",
    cancelled: "ยกเลิก",
    draft: "ร่าง",
    submitted: "ส่งอนุมัติ",
    approved: "อนุมัติแล้ว",
    rejected: "ปฏิเสธ",
    issued: "ออก PO แล้ว",
    partially_received: "รับบางส่วน",
    invoiced: "ออก Invoice แล้ว",
    paid: "รับเงินแล้ว",
    confirmed: "ยืนยันแล้ว",
  };
  return labels[status] || status;
}

export function voTypeLabel(type: string): string {
  const labels: Record<string, string> = {
    addition: "เพิ่มงาน",
    omission: "ลดงาน",
    modification: "แก้ไขงาน",
  };
  return labels[type] || type;
}

export function itemTypeLabel(type: string): string {
  const labels: Record<string, string> = {
    material: "วัสดุ",
    labor: "แรงงาน",
    equipment: "เครื่องจักร",
  };
  return labels[type] || type;
}

export function approvalTypeLabel(type: string): string {
  const labels: Record<string, string> = {
    boq: "BOQ",
    budget: "งบประมาณ",
    pr: "ใบขอซื้อ",
    po: "ใบสั่งซื้อ",
    claim: "เคลมงาน",
    vo: "VO",
    daily_report: "รายงานประจำวัน",
  };
  return labels[type] || type;
}
