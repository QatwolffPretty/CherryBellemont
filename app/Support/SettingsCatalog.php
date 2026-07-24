<?php

namespace App\Support;

class SettingsCatalog
{
    /** @return array<string, array{type:string,default:mixed,public:bool,description:string}> */
    public static function definitions(): array
    {
        return [
            'store.company_name' => self::d('string', config('store.company_name'), true, 'Public store name.'),
            'store.tagline' => self::d('string', 'Crafted with Elegance. Designed for Every Occasion.', true, 'Short brand tagline.'),
            'store.description' => self::d('text', 'Discover timeless pieces and quiet distinction from Cherry Bellemont.', true, 'Store description.'),
            'store.support_email' => self::d('email', config('store.support_email'), true, 'Customer support email.'),
            'store.support_phone' => self::d('string', '', true, 'Customer support phone.'),
            'store.business_hours' => self::d('text', "Monday – Friday\n9:00 AM – 6:00 PM (MYT)\nSaturday\n10:00 AM – 3:00 PM\nSunday & Public Holidays\nClosed", true, 'Displayed business hours.'),
            'store.country' => self::d('string', 'Malaysia', true, 'Store country.'),
            'store.currency' => self::d('string', 'MYR', true, 'Display currency. Historical order values are not converted.'),
            'store.logo_light' => self::d('image', null, true, 'Light logo for dark backgrounds.'),
            'store.logo_dark' => self::d('image', null, true, 'Dark logo for light backgrounds.'),
            'store.favicon' => self::d('image', null, true, 'Browser favicon.'),
            'social.threads_url' => self::d('url', config('store.threads_url'), true, 'Threads profile URL.'),
            'social.instagram_url' => self::d('url', config('store.instagram_url'), true, 'Instagram profile URL.'),
            'social.facebook_url' => self::d('url', config('store.facebook_url'), true, 'Facebook page URL.'),
            'contact.general_email' => self::d('email', config('store.general_email'), true, 'General enquiries email.'),
            'contact.support_email' => self::d('email', config('store.support_email'), true, 'Customer support email.'),
            'contact.phone' => self::d('string', '', true, 'Public phone number.'),
            'contact.whatsapp' => self::d('string', '', true, 'Public WhatsApp contact.'),
            'contact.address' => self::d('text', config('store.business_address'), true, 'Business address.'),
            'duitnow.account_name' => self::d('string', config('duitnow.account_name'), true, 'Public DuitNow account name.'),
            'duitnow.bank_name' => self::d('string', config('duitnow.bank_name'), true, 'Public bank name.'),
            'duitnow.public_id' => self::d('string', config('duitnow.id'), true, 'Public DuitNow identifier.'),
            'duitnow.qr_image' => self::d('image', config('duitnow.qr_path'), true, 'Public DuitNow QR image.'),
            'duitnow.instructions' => self::d('text', config('duitnow.payment_instructions'), true, 'Customer payment instructions.'),
            'payment.stripe_display_name' => self::d('string', 'Card Payment by Stripe', true, 'Public Stripe payment label.'),
            'payment.stripe_enabled' => self::d('boolean', true, true, 'Allow new Stripe checkouts.'),
            'payment.duitnow_display_name' => self::d('string', 'DuitNow manual payment', true, 'Public DuitNow payment label.'),
            'payment.duitnow_enabled' => self::d('boolean', true, true, 'Allow new DuitNow orders.'),
            'shipping.self_pickup_enabled' => self::d('boolean', true, true, 'Display self pickup where configured.'),
            'shipping.self_pickup_name' => self::d('string', 'Self Pickup', true, 'Pickup display name.'),
            'shipping.self_pickup_address' => self::d('text', '', true, 'Pickup address and collection instructions.'),
            'shipping.free_shipping_threshold' => self::d('decimal', '0.00', true, 'Optional future-order threshold; 0 disables it.'),
            'shipping.default_processing_days' => self::d('integer', 2, true, 'Displayed processing days.'),
            'shipment.default_courier_id' => self::d('integer', 0, false, 'Optional default courier ID for newly created outbound shipments. 0 means no default.'),
            'shipment.default_processing_days' => self::d('integer', 2, true, 'Default estimated delivery lead time for new shipments.'),
            'shipment.customer_tracking_enabled' => self::d('boolean', true, true, 'Allow guests to view secure shipment tracking.'),
            'shipment.manual_events_enabled' => self::d('boolean', true, false, 'Allow administrators to add manual shipment timeline events.'),
            'shipment.delivery_email_enabled' => self::d('boolean', true, false, 'Send queued customer shipment updates.'),
            'gift.enabled' => self::d('boolean', true, true, 'Offer the Signature Gift Experience.'),
            'gift.wrap_price' => self::d('decimal', '30.00', true, 'Future-order gift wrapping price in MYR.'),
            'gift.title' => self::d('string', 'Cherry Bellemont Signature Gift Experience', true, 'Gift experience title.'),
            'gift.description' => self::d('text', 'Your order will be presented in Cherry Bellemont signature wrapping with premium tissue, ribbon, and a personalised gift card.', true, 'Gift experience description.'),
            'gift.message_max_length' => self::d('integer', 250, true, 'Maximum personalised gift message length.'),
            'returns.window_days' => self::d('integer', config('store.returns.return_window_days', 14), true, 'Days from delivery for new return requests.'),
            'returns.damaged_report_days' => self::d('integer', config('store.returns.damaged_item_report_days', 7), true, 'Damaged-item reporting period.'),
            'returns.maximum_images' => self::d('integer', config('store.returns.maximum_return_images', 5), true, 'Maximum return evidence images.'),
            'returns.maximum_image_size_mb' => self::d('integer', config('store.returns.maximum_return_image_size_mb', 5), true, 'Maximum return evidence image size.'),
            'returns.contact_email' => self::d('email', config('store.support_email'), true, 'Returns contact email.'),
            'newsletter.section_enabled' => self::d('boolean', true, true, 'Show the public newsletter section.'),
            'newsletter.eyebrow' => self::d('string', 'Exclusive Access', true, 'Newsletter eyebrow text.'),
            'newsletter.heading' => self::d('string', 'Join the Cherry Bellemont Community', true, 'Newsletter heading.'),
            'newsletter.description' => self::d('text', 'Be the first to discover new arrivals, exclusive collections, private promotions, styling inspiration, and exclusive Cherry Bellemont updates delivered directly to your inbox.', true, 'Newsletter description.'),
            'newsletter.sender_name' => self::d('string', config('mail.from.name'), false, 'Campaign sender name.'),
            'newsletter.sender_email' => self::d('email', config('mail.from.address'), false, 'Campaign sender email.'),
            'seo.default_title' => self::d('string', config('store.company_name'), true, 'Default page title.'),
            'seo.default_description' => self::d('text', 'Discover timeless pieces and quiet distinction from Cherry Bellemont.', true, 'Default meta description.'),
            'seo.default_og_image' => self::d('image', null, true, 'Default Open Graph image.'),
            'seo.organization_name' => self::d('string', config('store.company_name'), true, 'Structured-data organisation name.'),
            'footer.copyright_text' => self::d('string', 'All Rights Reserved', true, 'Footer copyright suffix.'),
            'footer.show_social_links' => self::d('boolean', true, true, 'Show footer social links.'),
            'footer.show_contact_email' => self::d('boolean', true, true, 'Show footer contact details.'),
            'footer.show_business_hours' => self::d('boolean', true, true, 'Show footer business hours.'),
            'inventory.low_stock_threshold' => self::d('integer', config('store.low_stock_threshold', 3), false, 'Low-stock alert threshold.'),
            'inventory.back_in_stock_enabled' => self::d('boolean', true, false, 'Enable back-in-stock request and mail workflows.'),
        ];
    }

    public static function has(string $key): bool { return array_key_exists($key, self::definitions()); }
    public static function definition(string $key): ?array { return self::definitions()[$key] ?? null; }
    public static function groups(): array { return ['store' => 'General', 'contact' => 'Contact & Social', 'social' => 'Contact & Social', 'payment' => 'Payments Display', 'duitnow' => 'Payments Display', 'shipping' => 'Shipping & Pickup', 'shipment' => 'Courier & Shipments', 'gift' => 'Gift Experience', 'returns' => 'Returns', 'newsletter' => 'Newsletter', 'seo' => 'SEO', 'footer' => 'Footer', 'inventory' => 'Inventory']; }
    private static function d(string $type, mixed $default, bool $public, string $description): array { return compact('type', 'default', 'public', 'description'); }
}
