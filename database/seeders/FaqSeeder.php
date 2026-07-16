<?php

namespace Database\Seeders;

use App\Models\Faq;
use Illuminate\Database\Seeder;

class FaqSeeder extends Seeder
{
    /** Editable example FAQs for the Cherry Bellemont storefront. */
    public function run(): void
    {
        $faqs = [
            ['category' => 'Orders', 'sort_order' => 10, 'question' => 'How do I place an order?', 'answer' => 'Browse the Collection, add your preferred item to the shopping bag, proceed to checkout, enter your delivery details, select a payment method, and complete your order.'],
            ['category' => 'Payments', 'sort_order' => 20, 'question' => 'What payment methods do you accept?', 'answer' => 'Cherry Bellemont accepts secure card payments through Stripe and manual DuitNow QR payments with receipt verification.'],
            ['category' => 'Payments', 'sort_order' => 30, 'question' => 'How does DuitNow payment verification work?', 'answer' => 'After placing your order, scan the displayed DuitNow QR code, pay the exact order total, upload your receipt, and wait for administrator approval.'],
            ['category' => 'Shipping', 'sort_order' => 40, 'question' => 'How long does delivery take?', 'answer' => 'Delivery time depends on the selected delivery method and destination. Estimated delivery information is shown during checkout.'],
            ['category' => 'Shipping', 'sort_order' => 50, 'question' => 'Can I track my order?', 'answer' => 'Yes. Once your order is shipped, the courier name and tracking number will appear on your secure order page.'],
            ['category' => 'Orders', 'sort_order' => 60, 'question' => 'Can I cancel an order?', 'answer' => 'Please contact Cherry Bellemont as soon as possible. Orders that have already been packed or shipped may not be eligible for cancellation.'],
            ['category' => 'Orders', 'sort_order' => 70, 'question' => 'How do I use a coupon?', 'answer' => 'Enter your coupon code in the cart or checkout page and select Apply. Eligible discounts will appear in the order summary.'],
            ['category' => 'Contact', 'sort_order' => 80, 'question' => 'How do I contact Cherry Bellemont?', 'answer' => 'Use the contact details shown on the website footer or contact page for assistance.'],
        ];

        foreach ($faqs as $faq) {
            Faq::updateOrCreate(['question' => $faq['question']], $faq + ['is_active' => true]);
        }
    }
}
