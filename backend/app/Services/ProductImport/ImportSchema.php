<?php

namespace App\Services\ProductImport;

/**
 * Single source of truth for the import spreadsheet: sheet names, columns, which ones are
 * required, the help text shown in the template and which dropdown list each column uses.
 * The template builder, the instructions sheet and the reader all read from here.
 */
class ImportSchema
{
    public const PRODUCTS = 'Products';
    public const COMPATIBILITY = 'Compatibility';
    public const VARIANTS = 'Variants';
    public const FAQS = 'FAQs';
    public const LISTS = 'Lists';
    public const INSTRUCTIONS = 'Instructions';

    /** Rows whose SKU starts with this are sample rows and are always ignored. */
    public const EXAMPLE_PREFIX = 'EXAMPLE-';

    /** Typing this in an optional cell of an existing product empties that field. */
    public const CLEAR = 'CLEAR';

    public const MAX_PRODUCT_ROWS = 5000;
    public const MAX_CHILD_ROWS = 20000;
    public const MAX_IMAGES = 15;

    /**
     * key => [header, required when creating, help, example, width, list|null, format]
     * format: text | money | int | decimal | bool | long
     */
    public static function columns(string $sheet): array
    {
        return match ($sheet) {
            self::PRODUCTS => [
                'sku' => ['SKU', true, 'Your unique product code — letters, numbers, - and _ only. It is how existing products are matched, so never change it for a product.', 'EXAMPLE-BRK-001', 20, null, 'text'],
                'name' => ['Product name', true, 'Full product name shown in the store.', 'Bosch Front Brake Pads – Maruti Swift', 42, null, 'text'],
                'category' => ['Category', true, 'Pick from the dropdown (full path such as "Car Parts > Brakes").', null, 34, 'categories', 'text'],
                'brand' => ['Brand', true, 'Pick from the dropdown.', null, 18, 'brands', 'text'],
                'vehicle_type' => ['Vehicle type', false, 'Car, Motorcycle or Universal. Leave empty to use the category\'s type.', 'Car', 14, 'vehicle_types', 'text'],
                'is_universal' => ['Fits all vehicles', false, 'Yes = fits every vehicle (no Compatibility rows needed). Default No.', 'No', 12, 'yes_no', 'bool'],
                'part_number' => ['Part number', false, 'Manufacturer / OEM part number.', '0986AB1234', 18, null, 'text'],
                'mrp' => ['MRP (₹)', true, 'Maximum retail price, excluding GST.', 1899, 12, null, 'money'],
                'price' => ['Selling price (₹)', true, 'Your selling price excluding GST. Cannot be more than MRP.', 1499, 14, null, 'money'],
                'cost_price' => ['Cost price (₹)', false, 'What you paid — used for profit reports only, never shown to customers.', 1100, 13, null, 'money'],
                'tax_rate' => ['GST %', false, '0, 5, 12, 18 or 28. Default 18.', 18, 8, 'gst', 'int'],
                'stock' => ['Stock on hand', false, 'New products: opening stock. Existing products: stock is SET to this number and the change is logged in Inventory. Leave empty to keep the current stock.', 25, 12, null, 'int'],
                'low_stock_threshold' => ['Low-stock alert at', false, 'Alert when available stock drops to this number. Default 5.', 5, 12, null, 'int'],
                'allow_backorder' => ['Allow backorder', false, 'Yes = customers can order when out of stock. Default No.', 'No', 12, 'yes_no', 'bool'],
                'short_description' => ['Short description', false, 'One or two lines shown near the price (max 500 characters).', 'Low-dust ceramic pads with wear indicator.', 40, null, 'long'],
                'description' => ['Description', false, 'Full description. Line breaks are kept.', null, 50, null, 'long'],
                'attributes' => ['Filters', false, 'Shop filters as "Filter: Value", separated by | — e.g. Position: Front | Colour Temperature: 6000K. Filter names are on the Lists sheet; new values are added automatically.', 'Position: Front', 30, null, 'text'],
                'specifications' => ['Specifications', false, 'Spec table as "Label: Value", separated by |.', 'Pad thickness: 17.5 mm | Material: Ceramic', 40, null, 'long'],
                'whats_included' => ["What's included", false, 'Box contents separated by |.', '4 brake pads | Fitting clips', 30, null, 'text'],
                'tags' => ['Tags', false, 'Search tags separated by commas.', 'brakes, ceramic, swift', 24, null, 'text'],
                'position' => ['Position', false, 'Front / Rear / Left / Right etc.', 'Front', 12, null, 'text'],
                'material' => ['Material', false, null, 'Ceramic', 14, null, 'text'],
                'dimensions' => ['Dimensions', false, null, '120 x 50 x 17 mm', 16, null, 'text'],
                'weight_kg' => ['Weight (kg)', false, 'Shipping weight in kilograms.', 0.8, 11, null, 'decimal'],
                'warranty' => ['Warranty', false, null, '1 year manufacturer warranty', 22, null, 'text'],
                'installation_info' => ['Installation notes', false, null, null, 30, null, 'long'],
                'image_urls' => ['Image URLs', false, 'Public image links (JPG, PNG, WEBP or GIF, max 5 MB each) separated by |. The first is the main photo. Downloaded in the background. For existing products this list REPLACES the current photos; leave empty to keep them.', null, 50, null, 'long'],
                'video_url' => ['Video URL', false, 'YouTube or other video link.', null, 26, null, 'text'],
                'slug' => ['URL slug', false, 'Optional web address part, e.g. bosch-brake-pads-swift. Created from the name when empty.', null, 22, null, 'text'],
                'meta_title' => ['SEO title', false, 'Defaults to the product name.', null, 26, null, 'text'],
                'meta_description' => ['SEO description', false, 'Max 500 characters.', null, 34, null, 'long'],
                'is_active' => ['Active', false, 'Yes = visible in the store. Default Yes.', 'Yes', 9, 'yes_no', 'bool'],
                'is_featured' => ['Featured', false, 'Yes = shown in featured sections. Default No.', 'No', 9, 'yes_no', 'bool'],
            ],
            self::COMPATIBILITY => [
                'sku' => ['Product SKU', true, 'SKU of a product in the Products sheet or already in your store.', 'EXAMPLE-BRK-001', 20, null, 'text'],
                'vehicle' => ['Vehicle', true, 'Pick from the dropdown: a whole make, a model (all variants) or one exact variant.', null, 54, 'vehicles', 'text'],
                'year_from' => ['Year from', false, 'Optional — limit to vehicles made in/after this year.', null, 10, null, 'int'],
                'year_to' => ['Year to', false, 'Optional — limit to vehicles made in/before this year.', null, 10, null, 'int'],
                'notes' => ['Notes', false, 'Shown to customers, e.g. "Only for models with disc brakes".', null, 36, null, 'text'],
            ],
            self::VARIANTS => [
                'sku' => ['Product SKU', true, 'SKU of the main product.', 'EXAMPLE-BRK-001', 20, null, 'text'],
                'variant_sku' => ['Variant SKU', true, 'Unique code for this option.', 'EXAMPLE-BRK-001-RED', 22, null, 'text'],
                'variant_name' => ['Variant name', true, 'What the customer picks, e.g. "Red / Large".', 'Red', 22, null, 'text'],
                'options' => ['Options', false, '"Option: Value" pairs separated by |.', 'Colour: Red', 30, null, 'text'],
                'price_adjustment' => ['Price difference (₹)', false, 'Added to the product price (use a minus sign for cheaper options). Default 0.', 100, 14, null, 'money'],
                'is_active' => ['Active', false, 'Default Yes.', 'Yes', 9, 'yes_no', 'bool'],
            ],
            self::FAQS => [
                'sku' => ['Product SKU', true, 'SKU of the product.', 'EXAMPLE-BRK-001', 20, null, 'text'],
                'question' => ['Question', true, null, 'Does this fit the 2019 Swift?', 44, null, 'text'],
                'answer' => ['Answer', true, null, 'Yes, it fits all Swift models from 2018 onwards.', 60, null, 'long'],
            ],
            default => [],
        };
    }

    /** Data sheets in the order they appear in the workbook. */
    public static function dataSheets(): array
    {
        return [self::PRODUCTS, self::COMPATIBILITY, self::VARIANTS, self::FAQS];
    }

    public static function header(string $sheet, string $key): string
    {
        return self::columns($sheet)[$key][0] ?? $key;
    }

    /** Header text as written in the template (required columns get a trailing *). */
    public static function headerLabel(string $sheet, string $key): string
    {
        $c = self::columns($sheet)[$key];

        return $c[0].($c[1] ? ' *' : '');
    }

    /** Lower-case alphanumerics only: "Selling price (₹) *" → "sellingprice". */
    public static function normaliseHeader(?string $text): string
    {
        $text = mb_strtolower(trim((string) $text));
        $text = preg_replace('/\(.*?\)/u', '', $text);

        return preg_replace('/[^a-z0-9]/', '', $text);
    }

    /** Header-text → key map, accepting both the friendly header and the internal key. */
    public static function headerMap(string $sheet): array
    {
        $map = [];
        foreach (self::columns($sheet) as $key => $c) {
            $map[self::normaliseHeader($c[0])] = $key;
            $map[self::normaliseHeader($key)] = $key;
        }
        $map['productsku'] = 'sku';
        // Common alternatives people type.
        if ($sheet === self::PRODUCTS) {
            $map += ['name' => 'name', 'title' => 'name', 'productname' => 'name', 'sellingprice' => 'price', 'saleprice' => 'price',
                'gst' => 'tax_rate', 'tax' => 'tax_rate', 'qty' => 'stock', 'quantity' => 'stock', 'stockquantity' => 'stock',
                'images' => 'image_urls', 'image' => 'image_urls', 'attributes' => 'attributes', 'universal' => 'is_universal',
                'status' => 'is_active', 'featured' => 'is_featured', 'weight' => 'weight_kg'];
        }

        return $map;
    }
}
