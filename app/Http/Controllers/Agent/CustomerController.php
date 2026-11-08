<?php

namespace App\Http\Controllers\Agent;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\Transaction;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class CustomerController extends Controller
{
    public function index()
    {
        $customers = Company::where('is_customer', true)->withCount('transactions')->orderBy('name')->get();
        $paid = Transaction::where('status', 'paid')->selectRaw('company_id, currency, sum(amount) total')->groupBy('company_id', 'currency')->get()->groupBy('company_id');

        return view('agent.customers.index', ['customers' => $customers, 'paid' => $paid]);
    }

    public function show(Company $company)
    {
        abort_unless($company->is_customer, 404);
        $company->load(['contacts', 'opportunities']);
        $tx = $company->transactions()->orderByDesc('occurred_on')->orderByDesc('id')->get();

        return view('agent.customers.show', [
            'company' => $company, 'transactions' => $tx,
            'totals' => $tx->where('status', 'paid')->groupBy('currency')->map->sum('amount'),
            'pending' => $tx->where('status', 'pending')->groupBy('currency')->map->sum('amount'),
        ]);
    }

    public function update(Request $request, Company $company): RedirectResponse
    {
        $company->update(['is_customer' => $request->boolean('is_customer', true)]);

        return back()->with('status', 'Updated.');
    }

    public function storeTransaction(Request $request, Company $company): RedirectResponse
    {
        $data = $request->validate([
            'description' => ['required', 'string', 'max:255'],
            'amount' => ['required', 'numeric', 'between:-999999999,999999999'],
            'currency' => ['required', 'alpha', 'size:3'],
            'status' => ['required', Rule::in(array_keys(Transaction::STATUSES))],
            'occurred_on' => ['required', 'date'],
            'reference' => ['nullable', 'string', 'max:120'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);
        $data['currency'] = strtoupper($data['currency']);
        $opp = $company->opportunities()->latest('id')->first();
        $company->transactions()->create($data + ['opportunity_id' => $opp?->id]);

        return back()->with('status', 'Transaction recorded.');
    }

    public function updateTransaction(Request $request, Transaction $transaction): RedirectResponse
    {
        $data = $request->validate(['status' => ['required', Rule::in(array_keys(Transaction::STATUSES))]]);
        $transaction->update($data);

        return back()->with('status', 'Transaction updated.');
    }

    public function destroyTransaction(Transaction $transaction): RedirectResponse
    {
        $transaction->delete();

        return back()->with('status', 'Transaction deleted.');
    }
}
