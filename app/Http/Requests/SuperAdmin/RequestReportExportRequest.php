<?php

namespace App\Http\Requests\SuperAdmin;

use Illuminate\Foundation\Http\FormRequest;

class RequestReportExportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'report_type' => ['required', 'string', 'in:membership,emi,level-income,pair-reward,draw,booster,payment-in,payment-out,wallet-ledger,store-sales,store-distribution'],
            'format' => ['required', 'string', 'in:csv,xlsx,pdf'],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date'],
            'customer_id' => ['nullable', 'string'],
            'store_id' => ['nullable', 'integer'],
        ];
    }
}
