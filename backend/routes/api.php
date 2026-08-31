<?php

use App\Http\Controllers\Api\ApprovalController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\BoqVersionController;
use App\Http\Controllers\Api\BudgetController;
use App\Http\Controllers\Api\ContractController;
use App\Http\Controllers\Api\CostCodeCategoryController;
use App\Http\Controllers\Api\CostCodeController;
use App\Http\Controllers\Api\DailyReportController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\CashDisbursementController;
use App\Http\Controllers\Api\FinanceController;
use App\Http\Controllers\Api\GoodsReceiptController;
use App\Http\Controllers\Api\ImportJobController;
use App\Http\Controllers\Api\PaymentReceiptController;
use App\Http\Controllers\Api\ProgressClaimController;
use App\Http\Controllers\Api\ProgressController;
use App\Http\Controllers\Api\ProjectController;
use App\Http\Controllers\Api\PurchaseOrderController;
use App\Http\Controllers\Api\PurchaseRequestController;
use App\Http\Controllers\Api\ReportController;
use App\Http\Controllers\Api\SearchController;
use App\Http\Controllers\Api\SupplierController;
use App\Http\Controllers\Api\UomController;
use App\Http\Controllers\Api\UserController;
use App\Http\Controllers\Api\VariationOrderController;
use Illuminate\Support\Facades\Route;

Route::post('/login', [AuthController::class, 'login']);

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/user', [AuthController::class, 'user']);

    Route::get('/dashboard/company', [DashboardController::class, 'company']);
    Route::get('/dashboard/company/export', [DashboardController::class, 'export']);
    Route::get('/approvals/pending', [ApprovalController::class, 'pending']);
    Route::get('/approvals/count', [ApprovalController::class, 'count']);
    Route::get('/reports', [ReportController::class, 'index']);
    Route::get('/reports/{type}/download', [ReportController::class, 'download']);

    Route::get('/admin/users', [UserController::class, 'index']);
    Route::post('/admin/users', [UserController::class, 'store']);
    Route::put('/admin/users/{user}', [UserController::class, 'update']);
    Route::delete('/admin/users/{user}', [UserController::class, 'destroy']);
    Route::get('/admin/roles', [UserController::class, 'roles']);

    Route::get('/search', [SearchController::class, 'index']);
    Route::get('/notifications', [ProgressController::class, 'notifications']);
    Route::post('/notifications/{id}/read', [ProgressController::class, 'markNotificationRead']);

    Route::apiResource('projects', ProjectController::class);

    Route::prefix('projects/{project}')->group(function () {
        Route::get('boq-versions', [BoqVersionController::class, 'index']);
        Route::post('boq-versions', [BoqVersionController::class, 'store']);
        Route::get('boq-versions/{boqVersion}', [BoqVersionController::class, 'show']);
        Route::put('boq-versions/{boqVersion}', [BoqVersionController::class, 'update']);
        Route::delete('boq-versions/{boqVersion}', [BoqVersionController::class, 'destroy']);
        Route::post('boq-versions/{boqVersion}/duplicate', [BoqVersionController::class, 'duplicate']);
        Route::post('boq-versions/{boqVersion}/submit', [BoqVersionController::class, 'submit']);
        Route::post('boq-versions/{boqVersion}/approve', [BoqVersionController::class, 'approve']);
        Route::post('boq-versions/{boqVersion}/reject', [BoqVersionController::class, 'reject']);
        Route::post('boq-versions/{boqVersion}/import/preview', [BoqVersionController::class, 'importPreview']);
        Route::post('boq-versions/{boqVersion}/import/confirm', [BoqVersionController::class, 'importConfirm']);
        Route::get('boq-versions/{boqVersion}/export', [BoqVersionController::class, 'export']);

        Route::post('boq-versions/{boqVersion}/items', [BoqVersionController::class, 'storeItem']);
        Route::put('boq-versions/{boqVersion}/items/{item}', [BoqVersionController::class, 'updateItem']);
        Route::delete('boq-versions/{boqVersion}/items/{item}', [BoqVersionController::class, 'destroyItem']);

        Route::get('contract', [ContractController::class, 'show']);
        Route::post('contract', [ContractController::class, 'store']);
        Route::put('contract', [ContractController::class, 'update']);

        Route::get('budgets', [BudgetController::class, 'index']);
        Route::post('budgets/generate', [BudgetController::class, 'generate']);
        Route::get('budgets/approved-boq-versions', [BudgetController::class, 'approvedBoqVersions']);
        Route::get('budgets/{budget}', [BudgetController::class, 'show']);
        Route::post('budgets/{budget}/submit', [BudgetController::class, 'submit']);
        Route::post('budgets/{budget}/approve', [BudgetController::class, 'approve']);
        Route::post('budgets/{budget}/reject', [BudgetController::class, 'reject']);
        Route::get('budgets/{budget}/export', [BudgetController::class, 'export']);
        Route::get('cost-ledger', [BudgetController::class, 'ledger']);

        Route::get('purchase-requests', [PurchaseRequestController::class, 'index']);
        Route::post('purchase-requests', [PurchaseRequestController::class, 'store']);
        Route::get('purchase-requests/{purchaseRequest}', [PurchaseRequestController::class, 'show']);
        Route::put('purchase-requests/{purchaseRequest}', [PurchaseRequestController::class, 'update']);
        Route::post('purchase-requests/{purchaseRequest}/submit', [PurchaseRequestController::class, 'submit']);
        Route::post('purchase-requests/{purchaseRequest}/approve', [PurchaseRequestController::class, 'approve']);
        Route::post('purchase-requests/{purchaseRequest}/reject', [PurchaseRequestController::class, 'reject']);

        Route::get('purchase-orders', [PurchaseOrderController::class, 'index']);
        Route::post('purchase-orders', [PurchaseOrderController::class, 'store']);
        Route::get('purchase-orders/{purchaseOrder}', [PurchaseOrderController::class, 'show']);
        Route::post('purchase-orders/{purchaseOrder}/submit', [PurchaseOrderController::class, 'submit']);
        Route::post('purchase-orders/{purchaseOrder}/approve', [PurchaseOrderController::class, 'approve']);
        Route::post('purchase-orders/{purchaseOrder}/issue', [PurchaseOrderController::class, 'issue']);
        Route::post('purchase-orders/{purchaseOrder}/reject', [PurchaseOrderController::class, 'reject']);

        Route::get('goods-receipts', [GoodsReceiptController::class, 'index']);
        Route::post('goods-receipts', [GoodsReceiptController::class, 'store']);
        Route::get('goods-receipts/issuable-orders', [GoodsReceiptController::class, 'issuableOrders']);
        Route::get('goods-receipts/{goodsReceipt}', [GoodsReceiptController::class, 'show']);
        Route::post('goods-receipts/{goodsReceipt}/confirm', [GoodsReceiptController::class, 'confirm']);

        Route::get('dashboard', [ProgressController::class, 'dashboard']);
        Route::get('progress/scurve', [ProgressController::class, 'scurve']);
        Route::get('progress', [ProgressController::class, 'index']);
        Route::post('progress', [ProgressController::class, 'store']);
        Route::post('progress/generate-baseline', [ProgressController::class, 'generateBaseline']);

        Route::get('finance/summary', [FinanceController::class, 'summary']);
        Route::get('finance/cash-flow', [FinanceController::class, 'cashFlow']);

        Route::get('progress-claims', [ProgressClaimController::class, 'index']);
        Route::post('progress-claims', [ProgressClaimController::class, 'store']);
        Route::get('progress-claims/{progressClaim}', [ProgressClaimController::class, 'show']);
        Route::post('progress-claims/{progressClaim}/submit', [ProgressClaimController::class, 'submit']);
        Route::post('progress-claims/{progressClaim}/approve', [ProgressClaimController::class, 'approve']);
        Route::post('progress-claims/{progressClaim}/reject', [ProgressClaimController::class, 'reject']);
        Route::post('progress-claims/{progressClaim}/invoice', [ProgressClaimController::class, 'invoice']);

        Route::get('payment-receipts', [PaymentReceiptController::class, 'index']);
        Route::post('payment-receipts', [PaymentReceiptController::class, 'store']);
        Route::post('payment-receipts/{paymentReceipt}/confirm', [PaymentReceiptController::class, 'confirm']);

        Route::get('cash-disbursements', [CashDisbursementController::class, 'index']);
        Route::post('cash-disbursements', [CashDisbursementController::class, 'store']);
        Route::post('cash-disbursements/{cashDisbursement}/confirm', [CashDisbursementController::class, 'confirm']);

        Route::get('variation-orders/summary', [VariationOrderController::class, 'summary']);
        Route::get('variation-orders', [VariationOrderController::class, 'index']);
        Route::post('variation-orders', [VariationOrderController::class, 'store']);
        Route::get('variation-orders/{variationOrder}', [VariationOrderController::class, 'show']);
        Route::put('variation-orders/{variationOrder}', [VariationOrderController::class, 'update']);
        Route::post('variation-orders/{variationOrder}/submit', [VariationOrderController::class, 'submit']);
        Route::post('variation-orders/{variationOrder}/approve', [VariationOrderController::class, 'approve']);
        Route::post('variation-orders/{variationOrder}/reject', [VariationOrderController::class, 'reject']);

        Route::get('daily-reports/summary', [DailyReportController::class, 'summary']);
        Route::get('daily-reports', [DailyReportController::class, 'index']);
        Route::post('daily-reports', [DailyReportController::class, 'store']);
        Route::get('daily-reports/{dailyReport}', [DailyReportController::class, 'show']);
        Route::put('daily-reports/{dailyReport}', [DailyReportController::class, 'update']);
        Route::post('daily-reports/{dailyReport}/submit', [DailyReportController::class, 'submit']);
        Route::post('daily-reports/{dailyReport}/approve', [DailyReportController::class, 'approve']);
        Route::post('daily-reports/{dailyReport}/reject', [DailyReportController::class, 'reject']);
    });

    Route::get('/imports', [ImportJobController::class, 'index']);

    Route::prefix('masters')->group(function () {
        Route::apiResource('cost-code-categories', CostCodeCategoryController::class);
        Route::apiResource('cost-codes', CostCodeController::class);
        Route::apiResource('uoms', UomController::class);
        Route::apiResource('suppliers', SupplierController::class);
    });
});
