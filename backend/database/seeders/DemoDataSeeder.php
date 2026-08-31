<?php

namespace Database\Seeders;

use App\Models\AppNotification;
use App\Models\BoqItem;
use App\Models\BoqVersion;
use App\Models\Budget;
use App\Models\BudgetLine;
use App\Models\Company;
use App\Models\Contract;
use App\Models\CostCode;
use App\Models\CostLedgerEntry;
use App\Models\DailyReport;
use App\Models\DailyReportItem;
use App\Models\GoodsReceipt;
use App\Models\GoodsReceiptItem;
use App\Models\Permission;
use App\Models\ProgressBaseline;
use App\Models\ProgressClaim;
use App\Models\ProgressEntry;
use App\Models\Project;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\PurchaseRequest;
use App\Models\PurchaseRequestItem;
use App\Models\Role;
use App\Models\Supplier;
use App\Models\Uom;
use App\Models\User;
use App\Models\VariationOrder;
use App\Models\VariationOrderItem;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DemoDataSeeder extends Seeder
{
    public function run(): void
    {
        $company = Company::first();
        $admin = User::where('email', 'admin@boq.local')->first();
        $pmUser = User::where('email', 'pm@boq.local')->first();
        $p1 = Project::where('code', 'P0001')->first();
        $p2 = Project::where('code', 'P0002')->first();
        $p3 = Project::where('code', 'P0003')->first();
        $supplier = Supplier::where('code', 'SUP001')->first();
        $pr = PurchaseRequest::where('document_number', 'PR-2026-00001')->first();
        $contract = Contract::where('project_id', $p1->id)->first();
        $budget = Budget::where('document_number', 'BUD-2026-00001')->first();

        // ── อัปเดต PM ให้โครงการ ──
        Project::whereIn('code', ['P0001', 'P0002', 'P0003'])->update(['project_manager_id' => $pmUser->id]);

        // ── ผู้ขาย/ผู้รับเหมาเพิ่ม ──
        $subcon = Supplier::create([
            'company_id' => $company->id,
            'code' => 'SUP002',
            'name' => 'หจก. รับเหมาก่อสร้างสมบูรณ์',
            'type' => 'contractor',
            'contact_person' => 'คุณวิชัย',
            'phone' => '082-345-6789',
        ]);
        Supplier::create([
            'company_id' => $company->id,
            'code' => 'SUP003',
            'name' => 'บริษัท เครื่องจักรก่อสร้าง จำกัด',
            'type' => 'supplier',
            'contact_person' => 'คุณประเสริฐ',
            'phone' => '02-987-6543',
        ]);

        // ── Site Engineer user ──
        $siteRole = Role::create([
            'name' => 'site_engineer',
            'label' => 'Site Engineer',
            'description' => 'บันทึกรายงานหน้างาน',
        ]);
        $siteRole->permissions()->attach(
            Permission::whereIn('name', [
                'projects.view', 'site.view', 'site.create',
                'dashboard.project', 'masters.view',
            ])->pluck('id')
        );
        User::create([
            'company_id' => $company->id,
            'name' => 'วิรัช หน้างาน',
            'email' => 'site@boq.local',
            'password' => Hash::make('password'),
            'position' => 'Site Engineer',
            'is_active' => true,
        ])->roles()->attach($siteRole);

        // ── P1: PO + GR (จัดซื้อจัดจ้างครบ flow) ──
        $prItem = PurchaseRequestItem::where('purchase_request_id', $pr->id)->first();

        $po = PurchaseOrder::create([
            'company_id' => $company->id,
            'project_id' => $p1->id,
            'purchase_request_id' => $pr->id,
            'supplier_id' => $supplier->id,
            'document_number' => 'PO-2026-00001',
            'title' => 'สั่งซื้อคอนกรีต 240 ksc',
            'order_date' => '2026-03-20',
            'delivery_date' => '2026-04-01',
            'status' => 'issued',
            'total_amount' => 336000,
            'created_by' => $pmUser->id,
            'approved_by' => $admin->id,
            'approved_at' => now(),
            'issued_at' => now(),
        ]);

        $poItem = PurchaseOrderItem::create([
            'purchase_order_id' => $po->id,
            'purchase_request_item_id' => $prItem->id,
            'cost_code_id' => $prItem->cost_code_id,
            'cost_code' => 'STR',
            'description' => 'คอนกรีต 240 ksc',
            'uom_code' => 'M3',
            'quantity' => 120,
            'unit_price' => 2800,
            'amount' => 336000,
            'received_quantity' => 114,
            'sort_order' => 1,
        ]);

        CostLedgerEntry::create([
            'company_id' => $company->id,
            'project_id' => $p1->id,
            'cost_code_id' => $prItem->cost_code_id,
            'entry_type' => 'committed',
            'amount' => 336000,
            'running_balance' => 336000,
            'reference_type' => PurchaseOrder::class,
            'reference_id' => $po->id,
            'description' => "Committed - {$po->document_number}",
            'entry_date' => '2026-03-20',
            'created_by' => $admin->id,
        ]);

        $grAmount = 319200;
        $gr = GoodsReceipt::create([
            'company_id' => $company->id,
            'project_id' => $p1->id,
            'purchase_order_id' => $po->id,
            'supplier_id' => $supplier->id,
            'document_number' => 'GR-2026-00001',
            'receipt_date' => '2026-04-05',
            'status' => 'confirmed',
            'total_amount' => $grAmount,
            'created_by' => $pmUser->id,
            'confirmed_by' => $admin->id,
            'confirmed_at' => now(),
        ]);

        GoodsReceiptItem::create([
            'goods_receipt_id' => $gr->id,
            'purchase_order_item_id' => $poItem->id,
            'description' => 'คอนกรีต 240 ksc',
            'uom_code' => 'M3',
            'quantity' => 114,
            'unit_price' => 2800,
            'amount' => $grAmount,
            'sort_order' => 1,
        ]);

        CostLedgerEntry::create([
            'company_id' => $company->id,
            'project_id' => $p1->id,
            'cost_code_id' => $prItem->cost_code_id,
            'entry_type' => 'actual',
            'amount' => $grAmount,
            'running_balance' => $grAmount,
            'reference_type' => GoodsReceipt::class,
            'reference_id' => $gr->id,
            'description' => "Actual - {$gr->document_number}",
            'entry_date' => '2026-04-05',
            'created_by' => $admin->id,
        ]);

        // PO รออนุมัติ (สำหรับเมนูอนุมัติ)
        PurchaseOrder::create([
            'company_id' => $company->id,
            'project_id' => $p1->id,
            'supplier_id' => $subcon->id,
            'document_number' => 'PO-2026-00002',
            'title' => 'สั่งจ้างงานเหล็กเสริม',
            'order_date' => '2026-04-10',
            'delivery_date' => '2026-05-01',
            'status' => 'submitted',
            'total_amount' => 850000,
            'created_by' => $pmUser->id,
        ]);

        // ── P1: BOQ ฉบับ 2 รออนุมัติ ──
        $boq2 = BoqVersion::create([
            'company_id' => $company->id,
            'project_id' => $p1->id,
            'document_number' => 'BOQ-2026-00002',
            'version_number' => '2.0',
            'title' => 'BOQ ฉบับแก้ไข 2 — เพิ่มงานปรับปรุง',
            'status' => 'submitted',
            'total_amount' => 1200000,
            'created_by' => $pmUser->id,
            'submitted_at' => now(),
        ]);
        BoqItem::create([
            'company_id' => $company->id,
            'project_id' => $p1->id,
            'boq_version_id' => $boq2->id,
            'cost_code_id' => CostCode::where('code', 'ARC')->value('id'),
            'uom_id' => Uom::where('code', 'M2')->value('id'),
            'cost_code' => 'ARC',
            'item_code' => 'ARC-002',
            'description' => 'งานปรับปรุงผิวพื้น',
            'uom_code' => 'M2',
            'quantity' => 500,
            'material_rate' => 1800,
            'labor_rate' => 600,
            'equipment_rate' => 0,
            'unit_rate' => 2400,
            'amount' => 1200000,
            'sort_order' => 1,
        ]);

        // ── P1: เคลมงานรออนุมัติ ──
        ProgressClaim::create([
            'company_id' => $company->id,
            'project_id' => $p1->id,
            'contract_id' => $contract->id,
            'document_number' => 'CLM-2026-00002',
            'title' => 'เคลมงาน ณ 35%',
            'claim_date' => '2026-05-31',
            'period_month' => '2026-05-01',
            'progress_percent' => 35,
            'previous_percent' => 18,
            'gross_amount' => round(47500000 * 0.17, 2),
            'retention_percent' => 5,
            'retention_amount' => round(47500000 * 0.17 * 0.05, 2),
            'net_amount' => round(47500000 * 0.17 * 0.95, 2),
            'status' => 'submitted',
            'created_by' => $pmUser->id,
        ]);

        // ── P1: VO รออนุมัติ ──
        $voPending = VariationOrder::create([
            'company_id' => $company->id,
            'project_id' => $p1->id,
            'contract_id' => $contract->id,
            'document_number' => 'VO-2026-00002',
            'vo_number' => 'VO-P0001-002',
            'title' => 'เพิ่มงานลิฟต์ขนส่ง',
            'vo_type' => 'addition',
            'status' => 'submitted',
            'total_amount' => 1800000,
            'reason' => 'เพิ่มลิฟต์ตามแบบ Rev.B',
            'created_by' => $pmUser->id,
        ]);
        VariationOrderItem::create([
            'variation_order_id' => $voPending->id,
            'cost_code_id' => CostCode::where('code', 'MEC')->value('id'),
            'cost_code' => 'MEC',
            'description' => 'ลิฟต์ขนส่ง 2,000 kg',
            'uom_code' => 'LS',
            'quantity' => 1,
            'unit_price' => 1800000,
            'amount' => 1800000,
            'sort_order' => 1,
        ]);

        // ── P1: PR รออนุมัติ ──
        $prPending = PurchaseRequest::create([
            'company_id' => $company->id,
            'project_id' => $p1->id,
            'document_number' => 'PR-2026-00002',
            'title' => 'ขอซื้อเหล็กเสริม SD40',
            'description' => 'เหล็กเสริมสำหรับชั้น 4-5',
            'required_date' => '2026-05-15',
            'status' => 'submitted',
            'total_amount' => 420000,
            'created_by' => $pmUser->id,
        ]);
        PurchaseRequestItem::create([
            'purchase_request_id' => $prPending->id,
            'cost_code_id' => CostCode::where('code', 'STR')->value('id'),
            'cost_code' => 'STR',
            'description' => 'เหล็กเสริม SD40 รับ-แบน',
            'uom_code' => 'KG',
            'quantity' => 12000,
            'unit_price' => 35,
            'amount' => 420000,
            'sort_order' => 1,
        ]);

        // ── P2: โครงการ Factory — demo ครบชุด ──
        $boqP2 = BoqVersion::create([
            'company_id' => $company->id,
            'project_id' => $p2->id,
            'document_number' => 'BOQ-2026-00003',
            'version_number' => '1.0',
            'title' => 'BOQ ขยายโรงงาน',
            'status' => 'approved',
            'total_amount' => 38500000,
            'created_by' => $pmUser->id,
            'approved_by' => $admin->id,
            'approved_at' => now(),
        ]);
        BoqItem::create([
            'company_id' => $company->id,
            'project_id' => $p2->id,
            'boq_version_id' => $boqP2->id,
            'cost_code_id' => CostCode::where('code', 'STR')->value('id'),
            'uom_id' => Uom::where('code', 'M3')->value('id'),
            'cost_code' => 'STR',
            'item_code' => 'STR-P2-001',
            'description' => 'งานโครงสร้างคอนกรีต',
            'uom_code' => 'M3',
            'quantity' => 800,
            'material_rate' => 4000,
            'labor_rate' => 1500,
            'equipment_rate' => 600,
            'unit_rate' => 6100,
            'amount' => 4880000,
            'sort_order' => 1,
        ]);
        BoqItem::create([
            'company_id' => $company->id,
            'project_id' => $p2->id,
            'boq_version_id' => $boqP2->id,
            'cost_code_id' => CostCode::where('code', 'MEC')->value('id'),
            'uom_id' => Uom::where('code', 'LS')->value('id'),
            'cost_code' => 'MEC',
            'item_code' => 'MEC-P2-001',
            'description' => 'ระบบเครื่องจักรโรงงาน',
            'uom_code' => 'LS',
            'quantity' => 1,
            'unit_rate' => 8500000,
            'material_rate' => 8500000,
            'labor_rate' => 0,
            'equipment_rate' => 0,
            'amount' => 8500000,
            'sort_order' => 2,
        ]);

        $contractP2 = Contract::create([
            'company_id' => $company->id,
            'project_id' => $p2->id,
            'document_number' => 'CTR-2026-00002',
            'contract_number' => 'CTR-P0002-001',
            'title' => 'สัญญาขยายโรงงาน Factory Expansion',
            'client_name' => 'XYZ Manufacturing',
            'contract_value' => 52000000,
            'signed_date' => '2026-02-10',
            'start_date' => '2026-02-01',
            'end_date' => '2027-01-31',
            'retention_percent' => 5,
            'status' => 'active',
        ]);

        $budgetP2Total = 42000000;
        $budgetP2 = Budget::create([
            'company_id' => $company->id,
            'project_id' => $p2->id,
            'contract_id' => $contractP2->id,
            'boq_version_id' => $boqP2->id,
            'document_number' => 'BUD-2026-00002',
            'version_number' => '1.0',
            'title' => 'งบประมาณโครงการ P0002',
            'status' => 'submitted',
            'boq_total' => 38500000,
            'total_amount' => $budgetP2Total,
            'is_baseline' => false,
            'created_by' => $pmUser->id,
        ]);
        BudgetLine::create([
            'company_id' => $company->id,
            'project_id' => $p2->id,
            'budget_id' => $budgetP2->id,
            'cost_code_id' => CostCode::where('code', 'STR')->value('id'),
            'cost_code' => 'STR',
            'cost_code_name' => 'โครงสร้าง',
            'boq_amount' => 4880000,
            'budget_amount' => 5500000,
            'sort_order' => 1,
        ]);

        foreach (['2026-02-01' => [10, 8], '2026-03-01' => [20, 15], '2026-04-01' => [30, 22]] as $month => [$planned, $actual]) {
            ProgressBaseline::create([
                'company_id' => $company->id,
                'project_id' => $p2->id,
                'period_month' => $month,
                'planned_percent' => $planned,
                'planned_value' => round($budgetP2Total * $planned / 100, 2),
                'sort_order' => 1,
            ]);
            ProgressEntry::create([
                'company_id' => $company->id,
                'project_id' => $p2->id,
                'period_month' => $month,
                'actual_percent' => $actual,
                'earned_value' => round($budgetP2Total * $actual / 100, 2),
                'status' => 'approved',
                'created_by' => $pmUser->id,
                'approved_by' => $admin->id,
                'approved_at' => now(),
            ]);
        }

        CostLedgerEntry::create([
            'company_id' => $company->id,
            'project_id' => $p2->id,
            'cost_code_id' => CostCode::where('code', 'STR')->value('id'),
            'entry_type' => 'budget',
            'amount' => $budgetP2Total,
            'running_balance' => $budgetP2Total,
            'reference_type' => Budget::class,
            'reference_id' => $budgetP2->id,
            'description' => 'งบประมาณ P0002',
            'entry_date' => '2026-02-01',
            'created_by' => $admin->id,
        ]);
        CostLedgerEntry::create([
            'company_id' => $company->id,
            'project_id' => $p2->id,
            'entry_type' => 'committed',
            'amount' => 2500000,
            'running_balance' => 2500000,
            'description' => 'Committed - PO P0002',
            'entry_date' => '2026-03-15',
            'created_by' => $admin->id,
        ]);
        CostLedgerEntry::create([
            'company_id' => $company->id,
            'project_id' => $p2->id,
            'entry_type' => 'actual',
            'amount' => 1800000,
            'running_balance' => 1800000,
            'description' => 'Actual cost P0002',
            'entry_date' => '2026-04-01',
            'created_by' => $admin->id,
        ]);

        PurchaseRequest::create([
            'company_id' => $company->id,
            'project_id' => $p2->id,
            'document_number' => 'PR-2026-00003',
            'title' => 'ขอซื้อแผ่นเมทัลชีท',
            'status' => 'submitted',
            'total_amount' => 680000,
            'created_by' => $pmUser->id,
        ]);

        DailyReport::create([
            'company_id' => $company->id,
            'project_id' => $p2->id,
            'document_number' => 'DR-2026-00003',
            'report_date' => '2026-04-10',
            'weather' => 'rainy',
            'workforce_count' => 28,
            'summary' => 'งานหลังคาเมทัลชีท — หยุดงานช่วงบ่ายเพราะฝน',
            'status' => 'approved',
            'total_amount' => 70000,
            'created_by' => $pmUser->id,
            'approved_by' => $admin->id,
            'approved_at' => now(),
        ]);

        // ── P3: BOQ ร่าง ──
        BoqVersion::create([
            'company_id' => $company->id,
            'project_id' => $p3->id,
            'document_number' => 'BOQ-2026-00004',
            'version_number' => '1.0',
            'title' => 'BOQ อาคารสำนักงาน (ร่าง)',
            'status' => 'draft',
            'total_amount' => 25350000,
            'created_by' => $pmUser->id,
        ]);

        Contract::create([
            'company_id' => $company->id,
            'project_id' => $p3->id,
            'document_number' => 'CTR-2026-00003',
            'contract_number' => 'CTR-P0003-001',
            'title' => 'สัญญาก่อสร้าง Office Building',
            'client_name' => 'DEF Corp',
            'contract_value' => 30550000,
            'signed_date' => '2026-03-01',
            'start_date' => '2026-03-01',
            'end_date' => '2026-11-30',
            'retention_percent' => 5,
            'status' => 'active',
        ]);

        // ── การแจ้งเตือน ──
        $notifications = [
            ['title' => 'รายงานหน้างานรออนุมัติ', 'message' => 'DR-2026-00002 งานเหล็กเสริมชั้น 3 รอตรวจสอบ', 'link' => '/projects/1/daily-report'],
            ['title' => 'PO รออนุมัติ', 'message' => 'PO-2026-00002 สั่งจ้างงานเหล็กเสริม', 'link' => '/projects/1/po'],
            ['title' => 'เคลมงานรออนุมัติ', 'message' => 'CLM-2026-00002 เคลมงาน ณ 35%', 'link' => '/projects/1/billing'],
            ['title' => 'BOQ รออนุมัติ', 'message' => 'BOQ-2026-00002 ฉบับแก้ไข 2', 'link' => '/projects/1/boq'],
            ['title' => 'งบประมาณรออนุมัติ', 'message' => 'BUD-2026-00002 โครงการ P0002', 'link' => '/projects/2/budget'],
        ];
        foreach ($notifications as $n) {
            AppNotification::create([
                'company_id' => $company->id,
                'user_id' => $admin->id,
                'type' => 'approval',
                'title' => $n['title'],
                'message' => $n['message'],
                'link' => $n['link'],
            ]);
        }
        AppNotification::create([
            'company_id' => $company->id,
            'user_id' => $pmUser->id,
            'type' => 'info',
            'title' => 'ความคืบหน้าโครงการ P0001',
            'message' => 'ความคืบหน้าจริง 42% — SPI 0.84 ต่ำกว่าแผน',
            'link' => '/projects/1/dashboard',
            'read_at' => now(),
        ]);
    }
}
