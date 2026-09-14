<?php

use App\Actions\Reports\RequestReportExport;
use App\Actions\Store\CreateStore;
use App\Jobs\ProcessReportExport;
use App\Models\Member;
use App\Models\MembershipPlan;
use App\Models\PayoutRequest;
use App\Models\PayoutTransaction;
use App\Models\ReportExport;
use App\Models\RuleVersion;
use App\Models\Store;
use App\Models\StoreProfitDistribution;
use App\Models\StoreSale;
use App\Models\User;
use App\Services\ReportCatalog;
use Illuminate\Support\Facades\Storage;

/**
 * T-018 — `Docs/TEST.md` scenario 18: the queued export lifecycle, CSV
 * escaping correctness, zero-row completion, failure handling, role gating,
 * and a few per-report-type column-mapping spot-checks.
 */
function reSuperAdmin(): User
{
    return User::where('role', 'super_admin')->firstOrFail();
}

function reMember(string $customerId): Member
{
    $user = User::factory()->create(['role' => 'member']);
    $plan = MembershipPlan::where('code', 'E')->first();

    return Member::create([
        'user_id' => $user->id,
        'customer_id' => $customerId,
        'membership_plan_id' => $plan?->id,
        'status' => 'active',
        'activated_at' => now(),
    ]);
}

function reRunExport(ReportExport $export): ReportExport
{
    (new ProcessReportExport($export))->handle(app(ReportCatalog::class));

    return $export->fresh();
}

beforeEach(function () {
    $this->seed();
    Storage::fake('local');
});

test('a queued export processes to ready with the correct row count and correctly escaped CSV content', function () {
    $store = app(CreateStore::class)('A, B & Co.', null, null, null, 0, 0, reSuperAdmin());
    StoreSale::create([
        'store_id' => $store->id,
        'transaction_type' => 'new_sale',
        'item_name' => 'Gold Ring',
        'quantity' => 1,
        'sale_amount' => 10000,
        'total_invoice_amount' => 10000,
        'payment_source' => 'cash',
        'distribution_status' => 'pending',
        'status' => 'confirmed',
    ]);

    $export = app(RequestReportExport::class)('store-sales', 'csv', [], reSuperAdmin());
    expect($export->status)->toBe('pending');

    $export = reRunExport($export);

    expect($export->status)->toBe('ready');
    expect($export->row_count)->toBe(1);
    expect($export->file_path)->not->toBeNull();

    $content = Storage::disk('local')->get($export->file_path);
    $rows = array_map('str_getcsv', explode("\n", trim($content)));

    expect($rows[0])->toBe(['Store', 'Item', 'Member', 'Sale Amount', 'Total Invoice Amount', 'Distribution Status', 'Date']);
    expect($rows[1][0])->toBe('A, B & Co.'); // The comma inside the store name must round-trip as ONE field.
    expect($rows[1][1])->toBe('Gold Ring');
});

test('a report with zero matching rows still completes as ready with row_count 0', function () {
    $export = app(RequestReportExport::class)('membership', 'csv', ['customer_id' => 'NO-SUCH-MEMBER'], reSuperAdmin());

    $export = reRunExport($export);

    expect($export->status)->toBe('ready');
    expect($export->row_count)->toBe(0);
    expect($export->file_path)->not->toBeNull();
    expect(Storage::disk('local')->exists($export->file_path))->toBeTrue();
});

test('a failure mid-generation marks the export failed with an error message, never stuck processing', function () {
    $export = ReportExport::create([
        'requested_by' => reSuperAdmin()->id,
        'report_type' => 'not-a-real-report-type',
        'format' => 'csv',
        'filters' => [],
        'status' => 'pending',
        'requested_at' => now(),
    ]);

    $export = reRunExport($export);

    expect($export->status)->toBe('failed');
    expect($export->error_message)->not->toBeNull();
    expect($export->file_path)->toBeNull();
});

test('an xlsx and a pdf export both complete as ready', function () {
    reMember('RE-FMT01');

    $xlsx = reRunExport(app(RequestReportExport::class)('membership', 'xlsx', [], reSuperAdmin()));
    $pdf = reRunExport(app(RequestReportExport::class)('membership', 'pdf', [], reSuperAdmin()));

    expect($xlsx->status)->toBe('ready');
    expect($xlsx->file_path)->toEndWith('.xlsx');
    expect($pdf->status)->toBe('ready');
    expect($pdf->file_path)->toEndWith('.pdf');
});

test('Payment Out report maps the recipient to the payout member, not a raw snapshot field', function () {
    $member = reMember('RE-PO01');
    $payoutRequest = PayoutRequest::create([
        'member_id' => $member->id,
        'requested_amount' => 5000,
        'status' => 'processed',
    ]);
    PayoutTransaction::create([
        'payout_request_id' => $payoutRequest->id,
        'amount_snapshot' => 5000,
        'beneficiary_snapshot' => ['name' => 'Some Bank Detail'],
        'method' => 'bank_transfer',
        'reference' => 'REF-001',
        'tds_amount' => 0,
        'processing_fee' => 0,
        'status' => 'processed',
        'processed_by' => reSuperAdmin()->id,
        'processed_at' => now(),
    ]);

    $export = reRunExport(app(RequestReportExport::class)('payment-out', 'csv', [], reSuperAdmin()));

    $content = Storage::disk('local')->get($export->file_path);
    $rows = array_map('str_getcsv', explode("\n", trim($content)));

    expect($rows[1][0])->toBe('RE-PO01');
    expect($rows[1][3])->toBe('REF-001');
});

test('Store Distribution report exposes the beneficiary level via beneficiary_type', function () {
    $store = app(CreateStore::class)('RE Distribution Store', null, null, null, 0, 0, reSuperAdmin());
    $owner = reMember('RE-SD01');
    $sale = StoreSale::create([
        'store_id' => $store->id,
        'transaction_type' => 'new_sale',
        'item_name' => 'Bangle',
        'quantity' => 1,
        'sale_amount' => 20000,
        'total_invoice_amount' => 20000,
        'payment_source' => 'cash',
        'distribution_status' => 'processed',
        'status' => 'confirmed',
    ]);
    StoreProfitDistribution::create([
        'store_sale_id' => $sale->id,
        'beneficiary_type' => 'store_owner',
        'beneficiary_member_id' => $owner->id,
        'rate_percent' => 2,
        'amount' => 400,
        'rule_version_id' => RuleVersion::where('is_active', true)->firstOrFail()->id,
    ]);

    $export = reRunExport(app(RequestReportExport::class)('store-distribution', 'csv', [], reSuperAdmin()));

    $content = Storage::disk('local')->get($export->file_path);
    $rows = array_map('str_getcsv', explode("\n", trim($content)));

    expect($rows[1][1])->toBe('store_owner');
    expect($rows[1][2])->toBe('RE-SD01');
    expect($rows[1][4])->toBe('400.00');
});

test('a member and an admin are both forbidden from every reports route, including downloading another user\'s export', function () {
    $member = reMember('RE-ROLE-MEMBER');
    $admin = User::factory()->create(['role' => 'admin']);
    $export = reRunExport(app(RequestReportExport::class)('membership', 'csv', [], reSuperAdmin()));

    $this->actingAs($member->user)->get('/super-admin/reports')->assertForbidden();
    $this->actingAs($admin)->get('/super-admin/reports')->assertForbidden();
    $this->actingAs($member->user)->post('/super-admin/reports', ['report_type' => 'membership', 'format' => 'csv'])->assertForbidden();
    $this->actingAs($admin)->get("/super-admin/reports/{$export->id}/download")->assertForbidden();
});

test('a not-ready export cannot be downloaded', function () {
    // Created directly, bypassing RequestReportExport, so the queued job
    // never runs (this project's test env uses QUEUE_CONNECTION=sync,
    // which would otherwise process it immediately).
    $export = ReportExport::create([
        'requested_by' => reSuperAdmin()->id,
        'report_type' => 'membership',
        'format' => 'csv',
        'filters' => [],
        'status' => 'pending',
        'requested_at' => now(),
    ]);

    $this->actingAs(reSuperAdmin())->get("/super-admin/reports/{$export->id}/download")->assertNotFound();
});
