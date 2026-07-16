<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\AdminCustomerIndexRequest;
use App\Http\Requests\StoreCustomerNoteRequest;
use App\Models\CustomerNote;
use App\Services\AdminCustomerService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class CustomerController extends Controller
{
    public function index(AdminCustomerIndexRequest $request, AdminCustomerService $customers): View
    {
        $filters = $request->filters();

        return view('admin.customers.index', [
            'customers' => $customers->paginate($filters),
            'filters' => $filters,
            'filterOptions' => $customers->filterOptions(),
        ]);
    }

    public function show(string $email, AdminCustomerService $customers): View
    {
        return view('admin.customers.show', $customers->detail($email));
    }

    public function storeNote(StoreCustomerNoteRequest $request, string $email): RedirectResponse
    {
        CustomerNote::create([
            'customer_email' => $email,
            'admin_id' => $request->user()->id,
            'note' => $request->validated('note'),
        ]);

        return back()->with('success', 'Internal customer note added.');
    }

    public function export(AdminCustomerIndexRequest $request, AdminCustomerService $customers): StreamedResponse
    {
        $rows = $customers->exportRows($request->filters());

        return response()->streamDownload(function () use ($rows): void {
            $output = fopen('php://output', 'wb');
            fputcsv($output, ['Name', 'Email', 'Phone', 'Account Type', 'Total Orders', 'Paid Orders', 'Total Spent', 'Average Order Value', 'Last Order Date', 'Newsletter Status']);

            foreach ($rows as $customer) {
                fputcsv($output, [
                    $customer->customer_name ?: 'Customer',
                    $customer->customer_email,
                    $customer->customer_phone,
                    $customer->registered ? 'Registered' : 'Guest',
                    $customer->total_orders,
                    $customer->paid_orders,
                    number_format($customer->total_spent, 2, '.', ''),
                    number_format($customer->average_order_value, 2, '.', ''),
                    $customer->last_order_at,
                    $customer->newsletter_status,
                ]);
            }

            fclose($output);
        }, 'cherry-bellemont-customers.csv', ['Content-Type' => 'text/csv']);
    }
}
