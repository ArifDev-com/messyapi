<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\Bill;
use App\Models\Customer;
use App\Models\Dealer;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BillBalanceTest extends TestCase
{
    use RefreshDatabase;

    protected $admin;
    protected $dealer;
    protected $customer;
    protected $product;
    protected $account;

    protected function setUp(): void
    {
        parent::setUp();

        // Create admin user
        $this->admin = User::factory()->create(['role' => 'admin']);

        // Create dealer with initial balances
        $this->dealer = Dealer::factory()->create([
            'due_balance' => 1000.00,
            'advance_balance' => 500.00,
            'commission_balance' => 200.00,
        ]);

        // Create customer
        $this->customer = Customer::factory()->create([
            'due_balance' => 0.00,
            'advance_balance' => 0.00,
        ]);

        // Create product
        $this->product = Product::factory()->create([
            'price' => 100.00,
            'stock_quantity' => 100,
        ]);

        // Create account
        $this->account = Account::factory()->create([
            'type' => 'Bank',
            'balance' => 5000.00,
        ]);
    }

    public function dealer_balance_reflects_correctly_after_bill_editing()
    {
        // Create initial bill for dealer
        $billData = [
            'billable_type' => 'App\Models\Dealer',
            'billable_id' => $this->dealer->id,
            'salesman_id' => $this->admin->id,
            'bill_date' => now()->format('Y-m-d'),
            'due_date' => now()->addDays(30)->format('Y-m-d'),
            'items' => [
                [
                    'product_id' => $this->product->id,
                    'quantity' => 2,
                    'unit_price' => 100.00,
                    'tax_percentage' => 0,
                    'tax_type' => 'exclusive',
                    'discount_value' => 0,
                    'discount_type' => 'fixed',
                    'commission_rate' => 5,
                    'size_inc' => null,
                    'pieces' => null,
                ]
            ],
            'payments' => [
                [
                    'payment_method' => 'Cash',
                    'account_id' => $this->account->id,
                    'amount' => 100.00,
                    'notes' => 'Initial payment',
                ]
            ],
            'transport_charge' => 20.00,
            'notes' => 'Original dealer bill',
        ];

        // Create the bill
        $response = $this->actingAs($this->admin)->post('/bills', $billData);
        $response->assertRedirect();
        $bill = Bill::latest()->first();
        $this->assertNotNull($bill, 'Bill should be created successfully');

        // Record balances after creation
        $initialDealerDue = $this->dealer->fresh()->due_balance;
        $initialDealerAdvance = $this->dealer->fresh()->advance_balance;
        $initialDealerCommission = $this->dealer->fresh()->commission_balance;
        $initialAccountBalance = $this->account->fresh()->balance;

        // Verify bill amounts
        $this->assertEquals($initialDealerDue, 1000 + 110);
        $this->assertEquals($initialDealerAdvance, 500);
        $this->assertEquals($initialDealerCommission, 200);
        $this->assertEquals($initialAccountBalance, 5000 + 100);

        // Edit the bill - increase quantity and add commission payment
        $updateData = [
            'billable_type' => 'App\Models\Dealer',
            'billable_id' => $this->dealer->id,
            'salesman_id' => $this->admin->id,
            'bill_date' => now()->format('Y-m-d'),
            'due_date' => now()->addDays(45)->format('Y-m-d'),
            'items' => [
                [
                    'product_id' => $this->product->id,
                    'quantity' => 4, // Increased from 2 to 4
                    'unit_price' => 100.00,
                    'tax_percentage' => 0,
                    'tax_type' => 'exclusive',
                    'discount_value' => 0,
                    'discount_type' => 'fixed',
                    'commission_rate' => 5,
                    'size_inc' => null,
                    'pieces' => null,
                ]
            ],
            'payments' => [
                [
                    'payment_method' => 'Cash',
                    'account_id' => $this->account->id,
                    'amount' => 150.00, // Increased from 100 to 150
                    'notes' => 'Updated payment',
                ],
                [
                    'payment_method' => 'Commission',
                    'amount' => 50.00, // New commission payment
                    'notes' => 'Using commission balance',
                ]
            ],
            'transport_charge' => 20.00,
            'notes' => 'Updated dealer bill',
        ];

        // Update the bill
        $this->actingAs($this->admin)->put('/bills/' . $bill->id, $updateData);

        // Refresh models
        $bill->refresh();
        $this->dealer->refresh();
        $this->account->refresh();

        // Calculate expected values after update
        // 4 items * (100 - 5% commission) = 4 * 95 = 380 + transport 20 = 400 total
        $expectedTotalAmount = (4 * 95.00) + 20.00; // 400.00
        $expectedTotalPaid = 150.00 + 50.00; // 200.00
        $expectedDueAmount = $expectedTotalAmount - $expectedTotalPaid; // 200.00



        // Verify bill amounts
        $this->assertEquals($expectedTotalAmount, $bill->total_amount);
        $this->assertEquals($expectedTotalPaid, $bill->total_paid);
        $this->assertEquals($expectedDueAmount, $bill->due_amount);

        // Verify dealer balances reflect the changes correctly
        // Due balance should be: initial_due + new_due_amount (after reversing old impacts)
        $expectedDealerDue = 1000.00 + $expectedDueAmount; // Initial 1000 + 200
        $this->assertEquals($expectedDealerDue, $this->dealer->due_balance);

        // Advance balance should remain unchanged (no advance payments in this edit)
        $this->assertEquals(500.00, $this->dealer->advance_balance);

        // Commission balance should be: initial_commission - commission_payment
        $expectedDealerCommission = 200.00 - 50.00; // Initial 200 - 50 commission payment
        $this->assertEquals($expectedDealerCommission, $this->dealer->commission_balance);

        // Account balance should be: initial_after_creation + new_cash - old_cash
        $expectedAccountBalance = $initialAccountBalance + 150.00 - 100.00; // 5100 + 150 - 100 = 5150
        $this->assertEquals($expectedAccountBalance, $this->account->balance);
    }

    public function customer_balance_reflects_correctly_after_bill_editing()
    {
        // Create initial bill for customer
        $billData = [
            'billable_type' => 'App\Models\Customer',
            'billable_id' => $this->customer->id,
            'salesman_id' => $this->admin->id,
            'bill_date' => now()->format('Y-m-d'),
            'due_date' => now()->addDays(30)->format('Y-m-d'),
            'items' => [
                [
                    'product_id' => $this->product->id,
                    'quantity' => 2,
                    'unit_price' => 100.00,
                    'tax_percentage' => 0,
                    'tax_type' => 'exclusive',
                    'discount_value' => 20,
                    'discount_type' => 'fixed',
                    'commission_rate' => 0, // No commission for customers
                    'size_inc' => null,
                    'pieces' => null,
                ]
            ],
            'payments' => [
                [
                    'payment_method' => 'Cash',
                    'account_id' => $this->account->id,
                    'amount' => 100.00,
                    'notes' => 'Initial customer payment',
                ]
            ],
            'transport_charge' => 10.00,
            'notes' => 'Original customer bill',
        ];

        // Create the bill
        $response = $this->actingAs($this->admin)
            ->post('/bills', $billData);
        $response->assertRedirect();

        $bill = Bill::latest()->first();
        $this->assertNotNull($bill, 'Bill should be created successfully');

        // Record balances after creation
        $initialCustomerDue = $this->customer->fresh()->due_balance;
        $initialCustomerAdvance = $this->customer->fresh()->advance_balance;
        $initialAccountBalance = $this->account->fresh()->balance;
        $this->assertEquals($initialCustomerDue, 70);
        $this->assertEquals($initialCustomerAdvance, 0);
        $this->assertEquals($initialAccountBalance, 5100);

        // Edit the bill - increase price and payment
        $updateData = [
            'billable_type' => 'App\Models\Customer',
            'billable_id' => $this->customer->id,
            'salesman_id' => $this->admin->id,
            'bill_date' => now()->format('Y-m-d'),
            'due_date' => now()->addDays(45)->format('Y-m-d'),
            'items' => [
                [
                    'product_id' => $this->product->id,
                    'quantity' => 1,
                    'unit_price' => 300.00, // Increased from 200 to 300
                    'tax_percentage' => 10, // Added tax
                    'tax_type' => 'exclusive',
                    'discount_value' => 50, // Increased discount
                    'discount_type' => 'fixed',
                    'commission_rate' => 0,
                    'size_inc' => null,
                    'pieces' => null,
                ]
            ],
            'payments' => [
                [
                    'payment_method' => 'Cash',
                    'account_id' => $this->account->id,
                    'amount' => 200.00, // Increased from 100 to 200
                    'notes' => 'Updated customer payment',
                ]
            ],
            'transport_charge' => 25.00, // Increased transport
            'notes' => 'Updated customer bill',
        ];

        // Update the bill
        $this->actingAs($this->admin)->put('/bills/' . $bill->id, $updateData);

        // Refresh models
        $bill->refresh();
        $this->customer->refresh();
        $this->account->refresh();

        // Calculate expected values after update
        // Item: 300 * 1.10 (tax) = 330, then -50 discount = 280 + transport 25 = 305 total
        $expectedTotalAmount = 305; // 280 + 25 = 305.00
        $expectedTotalPaid = 200.00;
        $expectedDueAmount = $expectedTotalAmount - $expectedTotalPaid; // 105.00

        // Verify bill amounts
        $this->assertEquals($expectedTotalAmount, $bill->total_amount);
        $this->assertEquals($expectedTotalPaid, $bill->total_paid);
        $this->assertEquals($expectedDueAmount, $bill->due_amount);

        $this->assertEquals($expectedDueAmount, $this->customer->due_balance);

        // Customer advance balance should remain unchanged (customers don't use advance payments for bills)
        $this->assertEquals(0, $this->customer->advance_balance);
        $this->assertEquals(5200, $this->account->balance);
    }

    public function test_bill_editing_with_multiple_payment_types_updates_all_balances_correctly()
    {
        // Create initial bill for dealer with multiple payment types
        $billData = [
            'billable_type' => 'App\Models\Dealer',
            'billable_id' => $this->dealer->id,
            'salesman_id' => $this->admin->id,
            'bill_date' => now()->format('Y-m-d'),
            'due_date' => now()->addDays(30)->format('Y-m-d'),
            'items' => [
                [
                    'product_id' => $this->product->id,
                    'quantity' => 5,
                    'unit_price' => 100.00,
                    'tax_percentage' => 0,
                    'tax_type' => 'exclusive',
                    'discount_value' => 0,
                    'discount_type' => 'fixed',
                    'commission_rate' => 5,
                    'size_inc' => null,
                    'pieces' => null,
                ]
            ],
            'payments' => [
                [
                    'payment_method' => 'Cash',
                    'account_id' => $this->account->id,
                    'amount' => 100.00,
                    'notes' => 'Cash payment',
                ],
                [
                    'payment_method' => 'Advance',
                    'amount' => 50.00,
                    'notes' => 'Advance payment',
                ],
                [
                    'payment_method' => 'Commission',
                    'amount' => 25.00,
                    'notes' => 'Commission payment',
                ]
            ],
            'transport_charge' => 25.00,
            'notes' => 'Multi-payment bill',
        ];

        // Create the bill
        $this->actingAs($this->admin)->post('/bills', $billData);
        $bill = Bill::latest()->first();

        // Record initial balances before editing
        $initialDealerDue = $this->dealer->fresh()->due_balance;
        $initialDealerAdvance = $this->dealer->fresh()->advance_balance;
        $initialDealerCommission = $this->dealer->fresh()->commission_balance;
        $initialAccountBalance = $this->account->fresh()->balance;

        // Edit the bill - change items and modify payments
        $updateData = [
            'billable_type' => 'App\Models\Dealer',
            'billable_id' => $this->dealer->id,
            'salesman_id' => $this->admin->id,
            'bill_date' => now()->format('Y-m-d'),
            'due_date' => now()->addDays(60)->format('Y-m-d'),
            'items' => [
                [
                    'product_id' => $this->product->id,
                    'quantity' => 8, // Increased quantity
                    'unit_price' => 120.00, // Increased price
                    'tax_percentage' => 5,
                    'tax_type' => 'exclusive',
                    'discount_value' => 20,
                    'discount_type' => 'fixed',
                    'commission_rate' => 8, // Increased commission
                    'size_inc' => null,
                    'pieces' => null,
                ]
            ],
            'payments' => [
                [
                    'payment_method' => 'Cash',
                    'account_id' => $this->account->id,
                    'amount' => 200.00, // Increased cash
                    'notes' => 'Updated cash payment',
                ],
                [
                    'payment_method' => 'Advance',
                    'amount' => 100.00, // Increased advance
                    'notes' => 'Updated advance payment',
                ],
                // Removed commission payment
            ],
            'transport_charge' => 40.00, // Increased transport
            'notes' => 'Updated multi-payment bill',
        ];
        // Update the bill
        $this->actingAs($this->admin)->put('/bills/' . $bill->id, $updateData);

        // Refresh models
        $bill->refresh();
        $this->dealer->refresh();
        $this->account->refresh();

        // Calculate expected values after update
        // Item calculation: 120 * 1.05 = 126, -20 discount = 106, -8% commission = 106 - 8.48 = 97.52
        // 8 items * 97.52 = 781.76 + transport 40 = 821.76 total
        $itemPrice = 120.00;
        $taxAmount = $itemPrice * 0.05; // 6.00
        $priceWithTax = $itemPrice + $taxAmount; // 126.00
        $priceAfterDiscount = $priceWithTax - 20.00; // 106.00
        $commissionAmount = $priceAfterDiscount * 0.08; // 8.48
        $finalItemPrice = $priceAfterDiscount - $commissionAmount; // 97.52
        $expectedTotalAmount = (8 * $finalItemPrice) + 40.00; // 821.76
        $expectedTotalPaid = 200.00 + 100.00; // 300.00 (cash + advance)
        $expectedDueAmount = $expectedTotalAmount - $expectedTotalPaid; // 521.76

        // Verify bill amounts
        $this->assertEquals(round($expectedTotalAmount, 2), $bill->total_amount);
        $this->assertEquals($expectedTotalPaid, $bill->total_paid);
        $this->assertEquals(round($expectedDueAmount, 2), $bill->due_amount);

        $this->assertEquals($expectedDueAmount + 1000, $this->dealer->due_balance);
        $this->assertEquals(500 - 100, $this->dealer->advance_balance);
        $this->assertEquals(200, $this->dealer->commission_balance);
        $expectedAccountBalance = $initialAccountBalance + 200.00 - 100.00;
        $this->assertEquals($expectedAccountBalance, $this->account->balance);

        // Verify payments
        $this->assertEquals(2, $bill->payments()->count());
        $this->assertEquals(1, $bill->payments()->where('payment_method', 'Cash')->count());
        $this->assertEquals(1, $bill->payments()->where('payment_method', 'Advance')->count());
        $this->assertEquals(0, $bill->payments()->where('payment_method', 'Commission')->count());
    }
}
