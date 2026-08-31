<?php

namespace Database\Seeders;

use App\Models\BoqItem;
use App\Models\BoqVersion;
use App\Models\Budget;
use App\Models\BudgetLine;
use App\Models\Company;
use App\Models\Contract;
use App\Models\DailyReport;
use App\Models\DailyReportItem;
use App\Models\CashDisbursement;
use App\Models\CostCode;
use App\Models\CostCodeCategory;
use App\Models\CostLedgerEntry;
use App\Models\PaymentReceipt;
use App\Models\Permission;
use App\Models\Project;
use App\Models\ProgressBaseline;
use App\Models\ProgressEntry;
use App\Models\ProgressClaim;
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

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $company = Company::create([
            'code' => 'BUG',
            'name' => 'บริษัท บุญไพโรจน์ ก่อสร้าง จำกัด',
            'name_en' => 'BUGpairoj Construction Co., Ltd.',
            'tax_id' => '0123456789012',
            'address' => '123 ถนนสุขุมวิท กรุงเทพฯ 10110',
            'phone' => '02-123-4567',
            'email' => 'info@bugpairoj.co.th',
        ]);

        $permissions = [
            ['name' => 'projects.view', 'module' => 'projects', 'label' => 'View Projects'],
            ['name' => 'projects.create', 'module' => 'projects', 'label' => 'Create Projects'],
            ['name' => 'projects.edit', 'module' => 'projects', 'label' => 'Edit Projects'],
            ['name' => 'projects.delete', 'module' => 'projects', 'label' => 'Delete Projects'],
            ['name' => 'boq.view', 'module' => 'boq', 'label' => 'View BOQ'],
            ['name' => 'boq.create', 'module' => 'boq', 'label' => 'Create BOQ'],
            ['name' => 'boq.edit', 'module' => 'boq', 'label' => 'Edit BOQ'],
            ['name' => 'boq.import', 'module' => 'boq', 'label' => 'Import BOQ'],
            ['name' => 'boq.export', 'module' => 'boq', 'label' => 'Export BOQ'],
            ['name' => 'boq.approve', 'module' => 'boq', 'label' => 'Approve BOQ'],
            ['name' => 'boq.delete', 'module' => 'boq', 'label' => 'Delete BOQ'],
            ['name' => 'budget.view', 'module' => 'budget', 'label' => 'View Budget'],
            ['name' => 'budget.create', 'module' => 'budget', 'label' => 'Create Budget'],
            ['name' => 'budget.edit', 'module' => 'budget', 'label' => 'Edit Budget'],
            ['name' => 'budget.approve', 'module' => 'budget', 'label' => 'Approve Budget'],
            ['name' => 'contract.view', 'module' => 'contract', 'label' => 'View Contract'],
            ['name' => 'contract.edit', 'module' => 'contract', 'label' => 'Edit Contract'],
            ['name' => 'procurement.view', 'module' => 'procurement', 'label' => 'View Procurement'],
            ['name' => 'procurement.create', 'module' => 'procurement', 'label' => 'Create PR/PO'],
            ['name' => 'procurement.approve', 'module' => 'procurement', 'label' => 'Approve PR/PO'],
            ['name' => 'finance.view', 'module' => 'finance', 'label' => 'View Finance'],
            ['name' => 'finance.create', 'module' => 'finance', 'label' => 'Create Billing'],
            ['name' => 'finance.approve', 'module' => 'finance', 'label' => 'Approve Billing'],
            ['name' => 'vo.view', 'module' => 'vo', 'label' => 'View Variation Orders'],
            ['name' => 'vo.create', 'module' => 'vo', 'label' => 'Create VO'],
            ['name' => 'vo.approve', 'module' => 'vo', 'label' => 'Approve VO'],
            ['name' => 'site.view', 'module' => 'site', 'label' => 'View Daily Reports'],
            ['name' => 'site.create', 'module' => 'site', 'label' => 'Create Daily Reports'],
            ['name' => 'site.approve', 'module' => 'site', 'label' => 'Approve Daily Reports'],
            ['name' => 'reports.view', 'module' => 'reports', 'label' => 'View Reports'],
            ['name' => 'masters.view', 'module' => 'masters', 'label' => 'View Master Data'],
            ['name' => 'masters.edit', 'module' => 'masters', 'label' => 'Edit Master Data'],
            ['name' => 'dashboard.company', 'module' => 'dashboard', 'label' => 'Company Dashboard'],
            ['name' => 'dashboard.project', 'module' => 'dashboard', 'label' => 'Project Dashboard'],
            ['name' => 'admin.users', 'module' => 'admin', 'label' => 'Manage Users'],
            ['name' => 'admin.settings', 'module' => 'admin', 'label' => 'System Settings'],
        ];

        foreach ($permissions as $perm) {
            Permission::create($perm);
        }

        $superAdmin = Role::create([
            'name' => 'super_admin',
            'label' => 'Super Admin',
            'description' => 'Full system access',
        ]);
        $superAdmin->permissions()->attach(Permission::pluck('id'));

        $pm = Role::create([
            'name' => 'project_manager',
            'label' => 'Project Manager',
            'description' => 'Manage projects and approve transactions',
        ]);
        $pm->permissions()->attach(
            Permission::whereIn('name', [
                'projects.view', 'projects.create', 'projects.edit',
                'boq.view', 'budget.view', 'budget.approve',
                'procurement.view', 'procurement.create', 'procurement.approve',
                'finance.view', 'finance.create',
                'vo.view', 'vo.create',
                'site.view', 'site.create',
                'reports.view',
                'dashboard.company', 'dashboard.project', 'masters.view',
            ])->pluck('id')
        );

        $admin = User::create([
            'company_id' => $company->id,
            'name' => 'Al Pairoj',
            'email' => 'admin@boq.local',
            'password' => Hash::make('password'),
            'position' => 'Super Admin',
            'is_active' => true,
        ]);
        $admin->roles()->attach($superAdmin);

        $pmUser = User::create([
            'company_id' => $company->id,
            'name' => 'สมชาย วงศ์ดี',
            'email' => 'pm@boq.local',
            'password' => Hash::make('password'),
            'position' => 'Project Manager',
            'is_active' => true,
        ]);
        $pmUser->roles()->attach($pm);

        $categories = [
            ['code' => 'structure', 'name' => 'โครงสร้าง', 'name_en' => 'Structure', 'sort_order' => 1],
            ['code' => 'architecture', 'name' => 'สถาปัตยกรรม', 'name_en' => 'Architecture', 'sort_order' => 2],
            ['code' => 'electrical', 'name' => 'ไฟฟ้า', 'name_en' => 'Electrical', 'sort_order' => 3],
            ['code' => 'sanitation', 'name' => 'สุขาภิบาล', 'name_en' => 'Sanitation', 'sort_order' => 4],
            ['code' => 'mechanical', 'name' => 'เครื่องกล', 'name_en' => 'Mechanical', 'sort_order' => 5],
            ['code' => 'other', 'name' => 'อื่นๆ', 'name_en' => 'Other', 'sort_order' => 99],
        ];
        foreach ($categories as $category) {
            CostCodeCategory::create([...$category, 'company_id' => $company->id]);
        }

        $costCodes = [
            ['code' => 'STR', 'name' => 'โครงสร้าง', 'category' => 'structure'],
            ['code' => 'ARC', 'name' => 'สถาปัตยกรรม', 'category' => 'architecture'],
            ['code' => 'ELE', 'name' => 'ไฟฟ้า', 'category' => 'electrical'],
            ['code' => 'SAN', 'name' => 'สุขาภิบาล', 'category' => 'sanitation'],
            ['code' => 'MEC', 'name' => 'เครื่องกล', 'category' => 'mechanical'],
        ];
        foreach ($costCodes as $i => $cc) {
            CostCode::create([...$cc, 'company_id' => $company->id, 'sort_order' => $i + 1]);
        }

        $uoms = [
            ['code' => 'EA', 'name' => 'ชิ้น', 'name_en' => 'Each'],
            ['code' => 'M', 'name' => 'เมตร', 'name_en' => 'Meter'],
            ['code' => 'M2', 'name' => 'ตารางเมตร', 'name_en' => 'Sq.Meter'],
            ['code' => 'M3', 'name' => 'ลูกบาศก์เมตร', 'name_en' => 'Cu.Meter'],
            ['code' => 'KG', 'name' => 'กิโลกรัม', 'name_en' => 'Kilogram'],
            ['code' => 'LS', 'name' => 'เหมา', 'name_en' => 'Lump Sum'],
        ];
        foreach ($uoms as $uom) {
            Uom::create([...$uom, 'company_id' => $company->id]);
        }

        Supplier::create([
            'company_id' => $company->id,
            'code' => 'SUP001',
            'name' => 'บริษัท วัสดุก่อสร้าง จำกัด',
            'type' => 'supplier',
            'contact_person' => 'คุณสมชาย',
            'phone' => '081-234-5678',
        ]);

        $projects = [
            [
                'code' => 'P0001',
                'name' => 'Warehouse Project',
                'client_name' => 'ABC Logistics',
                'status' => 'active',
                'contract_value' => 45000000,
                'original_budget' => 38000000,
                'revised_budget' => 38000000,
                'start_date' => '2026-01-01',
                'end_date' => '2026-12-31',
            ],
            [
                'code' => 'P0002',
                'name' => 'Factory Expansion',
                'client_name' => 'XYZ Manufacturing',
                'status' => 'active',
                'contract_value' => 52000000,
                'original_budget' => 42000000,
                'revised_budget' => 43500000,
                'start_date' => '2026-02-01',
                'end_date' => '2027-01-31',
            ],
            [
                'code' => 'P0003',
                'name' => 'Office Building',
                'client_name' => 'DEF Corp',
                'status' => 'active',
                'contract_value' => 30550000,
                'original_budget' => 25350000,
                'revised_budget' => 25350000,
                'start_date' => '2026-03-01',
                'end_date' => '2026-11-30',
            ],
            [
                'code' => 'P0004',
                'name' => 'Parking Structure',
                'client_name' => 'GHI Properties',
                'status' => 'planning',
                'contract_value' => 0,
                'original_budget' => 0,
                'revised_budget' => 0,
                'start_date' => '2026-06-01',
                'end_date' => '2027-05-31',
            ],
        ];

        $createdProjects = [];
        foreach ($projects as $project) {
            $createdProjects[] = Project::create([...$project, 'company_id' => $company->id, 'project_manager_id' => $admin->id]);
        }

        $p1 = $createdProjects[0];

        $boq = BoqVersion::create([
            'company_id' => $company->id,
            'project_id' => $p1->id,
            'document_number' => 'BOQ-2026-00001',
            'version_number' => '1.0',
            'title' => 'BOQ โครงสร้างอาคาร',
            'status' => 'approved',
            'created_by' => $admin->id,
            'approved_by' => $admin->id,
            'approved_at' => now(),
        ]);

        $items = [
            ['cost_code' => 'STR', 'item_code' => 'STR-001', 'description' => 'งานเทคอนกรีตฐานราก', 'uom_code' => 'M3', 'quantity' => 120, 'material_rate' => 2800, 'labor_rate' => 1200, 'equipment_rate' => 500],
            ['cost_code' => 'STR', 'item_code' => 'STR-002', 'description' => 'งานโครงสร้างคอนกรีตเสริมเหล็ก', 'uom_code' => 'M3', 'quantity' => 350, 'material_rate' => 4200, 'labor_rate' => 1800, 'equipment_rate' => 800],
            ['cost_code' => 'ARC', 'item_code' => 'ARC-001', 'description' => 'งานก่ออิฐมวลเบา', 'uom_code' => 'M2', 'quantity' => 2500, 'material_rate' => 450, 'labor_rate' => 180, 'equipment_rate' => 0],
            ['cost_code' => 'ELE', 'item_code' => 'ELE-001', 'description' => 'งานระบบไฟฟ้าแรงต่ำ', 'uom_code' => 'LS', 'quantity' => 1, 'unit_rate' => 3500000],
        ];

        $total = 0;
        foreach ($items as $i => $item) {
            $qty = $item['quantity'];
            $unitRate = $item['unit_rate'] ?? ($item['material_rate'] + $item['labor_rate'] + ($item['equipment_rate'] ?? 0));
            $amount = round($qty * $unitRate, 2);
            $total += $amount;

            BoqItem::create([
                'company_id' => $company->id,
                'project_id' => $p1->id,
                'boq_version_id' => $boq->id,
                'cost_code_id' => CostCode::where('code', $item['cost_code'])->value('id'),
                'uom_id' => Uom::where('code', $item['uom_code'])->value('id'),
                'cost_code' => $item['cost_code'],
                'item_code' => $item['item_code'],
                'description' => $item['description'],
                'uom_code' => $item['uom_code'],
                'quantity' => $qty,
                'material_rate' => $item['material_rate'] ?? 0,
                'labor_rate' => $item['labor_rate'] ?? 0,
                'equipment_rate' => $item['equipment_rate'] ?? 0,
                'unit_rate' => $unitRate,
                'amount' => $amount,
                'sort_order' => $i + 1,
            ]);
        }

        $boq->update(['total_amount' => $total]);

        Contract::create([
            'company_id' => $company->id,
            'project_id' => $p1->id,
            'document_number' => 'CTR-2026-00001',
            'contract_number' => 'CTR-P0001-001',
            'title' => 'สัญญาก่อสร้าง Warehouse Project',
            'client_name' => 'ABC Logistics',
            'contract_value' => 45000000,
            'signed_date' => '2026-01-15',
            'start_date' => '2026-01-01',
            'end_date' => '2026-12-31',
            'retention_percent' => 5,
            'status' => 'active',
        ]);

        $contract = Contract::where('project_id', $p1->id)->first();
        $budget = Budget::create([
            'company_id' => $company->id,
            'project_id' => $p1->id,
            'contract_id' => $contract->id,
            'boq_version_id' => $boq->id,
            'document_number' => 'BUD-2026-00001',
            'version_number' => '1.0',
            'title' => 'งบประมาณจาก BOQ v1.0',
            'status' => 'approved',
            'boq_total' => $total,
            'contingency_percent' => 5,
            'contingency_amount' => round($total * 0.05, 2),
            'markup_percent' => 10,
            'markup_amount' => round(($total + round($total * 0.05, 2)) * 0.10, 2),
            'total_amount' => round($total * 1.05 * 1.10, 2),
            'is_baseline' => true,
            'created_by' => $admin->id,
            'approved_by' => $admin->id,
            'approved_at' => now(),
        ]);

        $budgetTotal = 0;
        $grouped = $boq->items()->get()->groupBy('cost_code');
        $sortOrder = 0;
        foreach ($grouped as $costCode => $items) {
            $boqAmount = $items->sum('amount');
            $lineContingency = round($boqAmount * 0.05, 2);
            $lineSubtotal = $boqAmount + $lineContingency;
            $lineMarkup = round($lineSubtotal * 0.10, 2);
            $budgetAmount = $lineSubtotal + $lineMarkup;
            $budgetTotal += $budgetAmount;

            $cc = CostCode::where('code', $costCode)->first();
            BudgetLine::create([
                'company_id' => $company->id,
                'project_id' => $p1->id,
                'budget_id' => $budget->id,
                'cost_code_id' => $cc?->id,
                'cost_code' => $costCode,
                'cost_code_name' => $cc?->name,
                'boq_amount' => $boqAmount,
                'budget_amount' => $budgetAmount,
                'sort_order' => ++$sortOrder,
            ]);

            CostLedgerEntry::create([
                'company_id' => $company->id,
                'project_id' => $p1->id,
                'cost_code_id' => $cc?->id,
                'entry_type' => 'budget',
                'amount' => $budgetAmount,
                'running_balance' => $budgetAmount,
                'reference_type' => Budget::class,
                'reference_id' => $budget->id,
                'description' => "งบประมาณ baseline - {$costCode}",
                'entry_date' => now()->toDateString(),
                'created_by' => $admin->id,
            ]);
        }

        $budget->update(['total_amount' => $budgetTotal]);
        $p1->update(['original_budget' => $budgetTotal, 'revised_budget' => $budgetTotal]);

        $supplier = Supplier::first();
        $pr = PurchaseRequest::create([
            'company_id' => $company->id,
            'project_id' => $p1->id,
            'document_number' => 'PR-2026-00001',
            'title' => 'ขอซื้อวัสดุคอนกรีต',
            'description' => 'วัสดุสำหรับงานเทคอนกรีตฐานราก',
            'required_date' => '2026-03-15',
            'status' => 'approved',
            'total_amount' => 336000,
            'created_by' => $admin->id,
            'approved_by' => $admin->id,
            'approved_at' => now(),
        ]);

        PurchaseRequestItem::create([
            'purchase_request_id' => $pr->id,
            'cost_code_id' => CostCode::where('code', 'STR')->value('id'),
            'cost_code' => 'STR',
            'description' => 'คอนกรีต 240 ksc',
            'uom_code' => 'M3',
            'quantity' => 120,
            'unit_price' => 2800,
            'amount' => 336000,
            'sort_order' => 1,
        ]);

        $months = [
            ['month' => '2026-01-01', 'planned' => 8.33, 'actual' => 5],
            ['month' => '2026-02-01', 'planned' => 16.67, 'actual' => 12],
            ['month' => '2026-03-01', 'planned' => 25.00, 'actual' => 18],
            ['month' => '2026-04-01', 'planned' => 33.33, 'actual' => 28],
            ['month' => '2026-05-01', 'planned' => 41.67, 'actual' => 35],
            ['month' => '2026-06-01', 'planned' => 50.00, 'actual' => 42],
        ];

        foreach ($months as $i => $m) {
            ProgressBaseline::create([
                'company_id' => $company->id,
                'project_id' => $p1->id,
                'period_month' => $m['month'],
                'planned_percent' => $m['planned'],
                'planned_value' => round($budgetTotal * $m['planned'] / 100, 2),
                'sort_order' => $i + 1,
            ]);

            ProgressEntry::create([
                'company_id' => $company->id,
                'project_id' => $p1->id,
                'period_month' => $m['month'],
                'actual_percent' => $m['actual'],
                'earned_value' => round($budgetTotal * $m['actual'] / 100, 2),
                'status' => 'approved',
                'created_by' => $admin->id,
                'approved_by' => $admin->id,
                'approved_at' => now(),
            ]);
        }

        $claim = ProgressClaim::create([
            'company_id' => $company->id,
            'project_id' => $p1->id,
            'contract_id' => $contract->id,
            'document_number' => 'CLM-2026-00001',
            'title' => 'เคลมงาน ณ 18%',
            'claim_date' => '2026-03-31',
            'period_month' => '2026-03-01',
            'progress_percent' => 18,
            'previous_percent' => 0,
            'gross_amount' => round(45000000 * 0.18, 2),
            'retention_percent' => 5,
            'retention_amount' => round(45000000 * 0.18 * 0.05, 2),
            'net_amount' => round(45000000 * 0.18 * 0.95, 2),
            'status' => 'approved',
            'created_by' => $admin->id,
            'approved_by' => $admin->id,
            'approved_at' => now(),
        ]);

        CostLedgerEntry::create([
            'company_id' => $company->id,
            'project_id' => $p1->id,
            'entry_type' => 'billing',
            'amount' => $claim->net_amount,
            'running_balance' => $claim->net_amount,
            'reference_type' => ProgressClaim::class,
            'reference_id' => $claim->id,
            'description' => "Billing - {$claim->document_number}",
            'entry_date' => '2026-03-31',
            'created_by' => $admin->id,
        ]);

        $receipt = PaymentReceipt::create([
            'company_id' => $company->id,
            'project_id' => $p1->id,
            'progress_claim_id' => $claim->id,
            'document_number' => 'RCP-2026-00001',
            'payment_date' => '2026-04-15',
            'amount' => $claim->net_amount,
            'payment_method' => 'transfer',
            'reference_no' => 'TRX-20260415-001',
            'status' => 'confirmed',
            'created_by' => $admin->id,
            'confirmed_by' => $admin->id,
            'confirmed_at' => now(),
        ]);

        CostLedgerEntry::create([
            'company_id' => $company->id,
            'project_id' => $p1->id,
            'entry_type' => 'cash_in',
            'amount' => $receipt->amount,
            'running_balance' => $receipt->amount,
            'reference_type' => PaymentReceipt::class,
            'reference_id' => $receipt->id,
            'description' => "Cash In - {$receipt->document_number}",
            'entry_date' => '2026-04-15',
            'created_by' => $admin->id,
        ]);

        $claim->update(['status' => 'paid']);

        $disbursement = CashDisbursement::create([
            'company_id' => $company->id,
            'project_id' => $p1->id,
            'document_number' => 'DIS-2026-00001',
            'disbursement_date' => '2026-04-20',
            'amount' => 319200,
            'payee' => 'บริษัท วัสดุก่อสร้าง จำกัด',
            'description' => 'จ่ายค่าวัสดุคอนกรีต',
            'status' => 'confirmed',
            'created_by' => $admin->id,
            'confirmed_by' => $admin->id,
            'confirmed_at' => now(),
        ]);

        CostLedgerEntry::create([
            'company_id' => $company->id,
            'project_id' => $p1->id,
            'entry_type' => 'cash_out',
            'amount' => $disbursement->amount,
            'running_balance' => $disbursement->amount,
            'reference_type' => CashDisbursement::class,
            'reference_id' => $disbursement->id,
            'description' => "Cash Out - {$disbursement->document_number}",
            'entry_date' => '2026-04-20',
            'created_by' => $admin->id,
        ]);

        $vo = VariationOrder::create([
            'company_id' => $company->id,
            'project_id' => $p1->id,
            'contract_id' => $contract->id,
            'document_number' => 'VO-2026-00001',
            'vo_number' => 'VO-P0001-001',
            'title' => 'เพิ่มงานระบบสำรองไฟ',
            'description' => 'เพิ่มงานติดตั้งระบบสำรองไฟตามคำขอเจ้าของโครงการ',
            'vo_type' => 'addition',
            'status' => 'approved',
            'total_amount' => 2500000,
            'reason' => 'Owner request - backup power system',
            'created_by' => $admin->id,
            'approved_by' => $admin->id,
            'approved_at' => now(),
        ]);

        VariationOrderItem::create([
            'variation_order_id' => $vo->id,
            'cost_code_id' => CostCode::where('code', 'ELE')->value('id'),
            'cost_code' => 'ELE',
            'description' => 'งานระบบสำรองไฟ Generator 100 KVA',
            'uom_code' => 'LS',
            'quantity' => 1,
            'unit_price' => 2500000,
            'amount' => 2500000,
            'sort_order' => 1,
        ]);

        CostLedgerEntry::create([
            'company_id' => $company->id,
            'project_id' => $p1->id,
            'cost_code_id' => CostCode::where('code', 'ELE')->value('id'),
            'entry_type' => 'revision',
            'amount' => 2500000,
            'running_balance' => 2500000,
            'reference_type' => VariationOrder::class,
            'reference_id' => $vo->id,
            'description' => "VO {$vo->document_number} - งานระบบสำรองไฟ",
            'entry_date' => now()->toDateString(),
            'created_by' => $admin->id,
        ]);

        $p1->update([
            'revised_budget' => (float) $p1->revised_budget + 2500000,
            'contract_value' => (float) $p1->contract_value + 2500000,
        ]);
        $contract->update(['contract_value' => (float) $contract->contract_value + 2500000]);

        $dailyReport = DailyReport::create([
            'company_id' => $company->id,
            'project_id' => $p1->id,
            'document_number' => 'DR-2026-00001',
            'report_date' => '2026-04-15',
            'weather' => 'sunny',
            'workforce_count' => 45,
            'summary' => 'งานเทคอนกรีตชั้น 2 อาคาร A เสร็จตามแผน',
            'status' => 'approved',
            'total_amount' => 125000,
            'created_by' => $admin->id,
            'approved_by' => $admin->id,
            'approved_at' => now(),
        ]);

        DailyReportItem::create([
            'daily_report_id' => $dailyReport->id,
            'item_type' => 'labor',
            'cost_code_id' => CostCode::where('code', 'STR')->value('id'),
            'cost_code' => 'STR',
            'description' => 'ค่าแรงงานก่อสร้าง',
            'uom_code' => 'EA',
            'quantity' => 45,
            'unit_cost' => 2500,
            'amount' => 112500,
            'sort_order' => 1,
        ]);

        DailyReportItem::create([
            'daily_report_id' => $dailyReport->id,
            'item_type' => 'material',
            'cost_code_id' => CostCode::where('code', 'STR')->value('id'),
            'cost_code' => 'STR',
            'description' => 'คอนกรีต 240 ksc',
            'uom_code' => 'M3',
            'quantity' => 5,
            'unit_cost' => 2500,
            'amount' => 12500,
            'sort_order' => 2,
        ]);

        $dailyReportPending = DailyReport::create([
            'company_id' => $company->id,
            'project_id' => $p1->id,
            'document_number' => 'DR-2026-00002',
            'report_date' => '2026-04-16',
            'weather' => 'cloudy',
            'workforce_count' => 38,
            'summary' => 'งานเหล็กเสริมชั้น 3 รอตรวจสอบ',
            'status' => 'submitted',
            'total_amount' => 95000,
            'created_by' => $admin->id,
        ]);

        DailyReportItem::create([
            'daily_report_id' => $dailyReportPending->id,
            'item_type' => 'labor',
            'cost_code_id' => CostCode::where('code', 'STR')->value('id'),
            'cost_code' => 'STR',
            'description' => 'ค่าแรงงานเหล็กเสริม',
            'uom_code' => 'EA',
            'quantity' => 38,
            'unit_cost' => 2500,
            'amount' => 95000,
            'sort_order' => 1,
        ]);

        $this->call(DemoDataSeeder::class);
    }
}
