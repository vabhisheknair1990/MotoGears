<?php

namespace Tests\Feature\Admin;

use App\Models\Attribute;
use App\Models\AttributeValue;
use App\Models\Brand;
use App\Models\Category;
use App\Models\InventoryTransaction;
use App\Models\Product;
use App\Models\ProductImport;
use App\Models\Role;
use App\Models\User;
use App\Models\VehicleManufacturer;
use App\Models\VehicleModel;
use App\Models\VehicleVariant;
use App\Services\ProductImport\ImageFetchException;
use App\Services\ProductImport\RemoteImageFetcher;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\TestCase;

class ProductImportTest extends TestCase
{
    private const URL = self::API.'/admin/product-imports';

    private const PRODUCT_HEADERS = ['SKU *', 'Product name *', 'Category *', 'Brand *', 'Vehicle type', 'MRP (₹) *', 'Selling price (₹) *', 'GST %', 'Stock on hand', 'Filters', 'Specifications', 'Tags', 'Description', 'Active'];

    private User $admin;
    private Category $brakes;
    private Brand $bosch;
    private VehicleVariant $variant;
    private Product $existing;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        Storage::fake('public');
        $this->admin = $this->actingAsStaff();

        $parent = Category::factory()->create(['name' => 'Car Parts', 'vehicle_type' => 'car']);
        $this->brakes = Category::factory()->create(['name' => 'Brakes', 'parent_id' => $parent->id, 'vehicle_type' => 'car']);
        $this->bosch = Brand::factory()->create(['name' => 'Bosch']);
        $make = VehicleManufacturer::factory()->create(['name' => 'Maruti Suzuki']);
        $model = VehicleModel::factory()->create(['vehicle_manufacturer_id' => $make->id, 'name' => 'Swift']);
        $this->variant = VehicleVariant::factory()->create(['vehicle_model_id' => $model->id, 'name' => 'VXi 1.2', 'year_from' => 2018, 'year_to' => 2024]);
        $position = Attribute::create(['name' => 'Position', 'slug' => 'position']);
        AttributeValue::create(['attribute_id' => $position->id, 'value' => 'Front', 'slug' => 'front']);

        $this->existing = $this->product(['sku' => 'OLD-001', 'name' => 'Old pads', 'category_id' => $this->brakes->id, 'brand_id' => $this->bosch->id, 'mrp' => 1000, 'price' => 900], 10);
    }

    // ── Template ──────────────────────────────────────────────────
    public function test_blank_template_has_sheets_dropdowns_and_live_lists(): void
    {
        $res = $this->get(self::URL.'/template')->assertOk();
        $this->assertStringContainsString('spreadsheetml', $res->headers->get('content-type'));

        $book = IOFactory::load($res->getFile()->getPathname());
        $this->assertSame(['Instructions', 'Products', 'Compatibility', 'Variants', 'FAQs', 'Lists'], $book->getSheetNames());

        $lists = $book->getSheetByName('Lists')->toArray();
        $column = fn (int $i) => array_filter(array_column($lists, $i));
        $this->assertContains('Car Parts > Brakes', $column(0));
        $this->assertContains('Bosch', $column(1));
        $this->assertContains('Maruti Suzuki > Swift > VXi 1.2 (2018-2024)', $column(2));
        $this->assertContains('Position: Front', $column(3));

        $products = $book->getSheetByName('Products');
        $this->assertSame('SKU *', $products->getCell('A1')->getValue());
        $this->assertSame('=CategoryList', $products->getCell('C5')->getDataValidation()->getFormula1());
        $this->assertSame('EXAMPLE-BRK-001', $products->getCell('A2')->getValue());
        $this->assertNotNull($book->getNamedRange('VehicleList'));
    }

    public function test_template_with_products_round_trips_without_changes(): void
    {
        $res = $this->get(self::URL.'/template?with_products=1')->assertOk();
        $path = $res->getFile()->getPathname();
        $row = IOFactory::load($path)->getSheetByName('Products')->rangeToArray('A2:H2')[0];
        $this->assertSame('OLD-001', $row[0]);
        $this->assertSame('Car Parts > Brakes', $row[2]);

        // Uploading the export untouched is a clean "update 1" with no errors.
        $import = $this->upload($path, 'export.xlsx');
        $this->assertSame(ProductImport::VALIDATED, $import['status']);
        $this->assertSame(['will_create' => 0, 'will_update' => 1, 'invalid' => 0], array_intersect_key($import['summary'], array_flip(['will_create', 'will_update', 'invalid'])));
    }

    // ── Check, then import ────────────────────────────────────────
    public function test_check_saves_nothing_then_import_creates_and_updates_everything(): void
    {
        $file = $this->workbook([
            'Products' => [
                self::PRODUCT_HEADERS,
                ['NEW-001', 'Ceramic pads', 'Car Parts > Brakes', 'bosch', '', 1899, 1499, 18, 25, 'Position: Rear', 'Thickness: 17 mm | Material: Ceramic', 'brakes, Ceramic', 'Great pads', 'Yes'],
                ['NEW-002', 'Brake fluid', 'brakes', 'Bosch', 'Universal', '₹ 499', '399', 12, '', '', '', '', '', 'No'],
                ['old-001', '', '', '', '', '', 850, '', 40, '', '', '', 'CLEAR', ''],
            ],
            'Compatibility' => [
                ['Product SKU *', 'Vehicle *', 'Year from', 'Year to', 'Notes'],
                ['NEW-001', 'Maruti Suzuki > Swift > VXi 1.2 (2018-2024)', 2019, '', 'Disc brake models'],
                ['NEW-001', 'maruti suzuki › swift', '', '', ''],
            ],
            'Variants' => [
                ['Product SKU *', 'Variant SKU *', 'Variant name *', 'Options', 'Price difference (₹)', 'Active'],
                ['NEW-001', 'NEW-001-RED', 'Red', 'Colour: Red', 100, 'Yes'],
            ],
            'FAQs' => [
                ['Product SKU *', 'Question *', 'Answer *'],
                ['NEW-001', 'Does it fit a 2019 Swift?', 'Yes.'],
            ],
        ]);

        $import = $this->upload($file);
        $this->assertSame(ProductImport::VALIDATED, $import['status'], json_encode($import));
        $this->assertSame(2, $import['summary']['will_create']);
        $this->assertSame(1, $import['summary']['will_update']);
        $this->assertSame(0, $import['summary']['invalid']);
        $this->assertTrue($import['can_start']);
        // Nothing was saved by the check — not even the new filter value.
        $this->assertDatabaseMissing('products', ['sku' => 'NEW-001']);
        $this->assertDatabaseMissing('attribute_values', ['value' => 'Rear']);
        $this->assertEquals(900, $this->existing->fresh()->price);
        $this->assertSame(10, $this->existing->inventory->fresh()->quantity);

        $this->postJson(self::URL.'/'.$import['id'].'/start')->assertOk();
        $done = $this->getJson(self::URL.'/'.$import['id'])->assertOk()->json('data');
        $this->assertSame(ProductImport::COMPLETED, $done['status'], json_encode($done['issues']));
        $this->assertSame([2, 1, 0], [$done['created_count'], $done['updated_count'], $done['failed_count']]);

        $p = Product::where('sku', 'NEW-001')->with(['compatibilities', 'variants', 'faqs', 'attributeValues', 'inventory'])->firstOrFail();
        $this->assertSame($this->brakes->id, $p->category_id);
        $this->assertSame('car', $p->vehicle_type);       // taken from the category
        $this->assertEquals(1499, $p->price);
        $this->assertSame(25, $p->inventory->quantity);
        $this->assertSame([['label' => 'Thickness', 'value' => '17 mm'], ['label' => 'Material', 'value' => 'Ceramic']], $p->specifications);
        $this->assertSame(['brakes', 'ceramic'], $p->tags);
        $this->assertSame(['Rear'], $p->attributeValues->pluck('value')->all());
        $this->assertCount(2, $p->compatibilities);
        $this->assertSame($this->variant->id, $p->compatibilities->firstWhere('year_from', 2019)->vehicle_variant_id);
        $this->assertNull($p->compatibilities->firstWhere('year_from', null)->vehicle_variant_id); // model-wide row
        $this->assertSame('NEW-001-RED', $p->variants->first()->sku);
        $this->assertSame(['Colour' => 'Red'], $p->variants->first()->options);
        $this->assertSame('Does it fit a 2019 Swift?', $p->faqs->first()->question);

        $fluid = Product::where('sku', 'NEW-002')->firstOrFail();
        $this->assertSame('universal', $fluid->vehicle_type);
        $this->assertEquals(499, $fluid->mrp);
        $this->assertFalse($fluid->is_active);

        // Existing product: only the filled cells changed, stock was set and logged.
        $old = $this->existing->fresh(['inventory']);
        $this->assertEquals(850, $old->price);
        $this->assertSame('Old pads', $old->name);
        $this->assertNull($old->description);              // CLEAR
        $this->assertSame(40, $old->inventory->quantity);
        $this->assertDatabaseHas('inventory_transactions', ['product_id' => $old->id, 'type' => 'manual_adjustment', 'quantity' => 30]);

        $this->assertSame(1, $this->admin->notifications()->where('data->product_import_id', $import['id'])->where('data->title', 'Product import finished')->count());
    }

    public function test_problems_are_reported_by_sheet_row_and_column_and_only_valid_products_import(): void
    {
        $file = $this->workbook([
            'Products' => [
                self::PRODUCT_HEADERS,
                ['GOOD-1', 'Good product', 'Car Parts > Brakes', 'Bosch', 'Car', 1000, 900, 18, 5, '', '', '', '', 'Yes'],
                ['BAD-1', '', 'Nope', 'Bosch', 'Car', 1000, 900, 18, '', '', '', '', '', ''],        // no name, unknown category
                ['BAD-2', 'Too pricey', 'Car Parts > Brakes', 'Bosch', 'Car', 500, 900, 7, -3, 'Colour: Blue', 'nonsense', '', '', 'Maybe'],
                ['GOOD-1', 'Duplicate', 'Car Parts > Brakes', 'Bosch', 'Car', 100, 90, 18, '', '', '', '', '', ''],
                ['', 'No SKU', 'Car Parts > Brakes', 'Bosch', 'Car', 100, 90, 18, '', '', '', '', '', ''],
            ],
            'Compatibility' => [
                ['Product SKU *', 'Vehicle *', 'Year from', 'Year to', 'Notes'],
                ['GHOST-9', 'Maruti Suzuki (all models)', '', '', ''],
                ['GOOD-1', 'Tata > Nexon', '', '', ''],
            ],
        ]);

        $import = $this->upload($file);
        $this->assertSame(ProductImport::VALIDATED, $import['status']);
        $this->assertSame(0, $import['summary']['will_create'], 'GOOD-1 has a bad compatibility row, so it is invalid too');
        $this->assertFalse($import['can_start']);

        $issues = collect($this->getJson(self::URL.'/'.$import['id'].'?level=error')->json('data.issues'));
        $find = fn (string $sheet, int $row, ?string $column) => $issues->first(fn ($i) => $i['sheet'] === $sheet && $i['row'] === $row && $i['column'] === $column);
        $this->assertStringContainsString('required for a new product', $find('Products', 3, 'Product name')['message']);
        $this->assertStringContainsString('"Nope" was not found', $find('Products', 3, 'Category')['message']);
        $this->assertStringContainsString('cannot be higher than MRP', $find('Products', 4, 'Selling price (₹)')['message']);
        $this->assertStringContainsString('0, 5, 12, 18 or 28', $find('Products', 4, 'GST %')['message']);
        $this->assertStringContainsString('whole number', $find('Products', 4, 'Stock on hand')['message']);
        $this->assertStringContainsString('Filter "Colour" does not exist', $find('Products', 4, 'Filters')['message']);
        $this->assertStringContainsString('Label: Value', $find('Products', 4, 'Specifications')['message']);
        $this->assertStringContainsString('Yes or No', $find('Products', 4, 'Active')['message']);
        $this->assertStringContainsString('already used on row 2', $find('Products', 5, 'SKU')['message']);
        $this->assertStringContainsString('SKU is required', $find('Products', 6, 'SKU')['message']);
        $this->assertStringContainsString('No product with SKU GHOST-9', $find('Compatibility', 2, 'Product SKU')['message']);
        $this->assertStringContainsString('"Tata > Nexon" was not found', $find('Compatibility', 3, 'Vehicle')['message']);

        $this->postJson(self::URL.'/'.$import['id'].'/start')->assertStatus(422);

        $report = $this->get(self::URL.'/'.$import['id'].'/report')->assertOk();
        $rows = IOFactory::load($report->getFile()->getPathname())->getActiveSheet()->toArray();
        $this->assertSame(['Type', 'Sheet', 'Row', 'SKU', 'Column', 'Problem'], $rows[0]);
        $this->assertGreaterThanOrEqual(12, count($rows) - 1);
    }

    public function test_partial_import_skips_invalid_products_and_reports_them(): void
    {
        $file = $this->workbook(['Products' => [
            self::PRODUCT_HEADERS,
            ['OK-1', 'Fine', 'Car Parts > Brakes', 'Bosch', 'Car', 1000, 900, 18, '', '', '', '', '', ''],
            ['NO-1', 'Broken', 'Car Parts > Brakes', 'Unknown brand', 'Car', 1000, 900, 18, '', '', '', '', '', ''],
        ]]);
        $import = $this->upload($file);
        $this->assertSame([1, 1], [$import['summary']['will_create'], $import['summary']['invalid']]);
        $this->assertTrue($import['can_start']);

        $this->postJson(self::URL.'/'.$import['id'].'/start')->assertOk();
        $done = $this->getJson(self::URL.'/'.$import['id'])->json('data');
        $this->assertSame(ProductImport::COMPLETED_WITH_ERRORS, $done['status']);
        $this->assertSame([1, 1], [$done['created_count'], $done['failed_count']]);
        $this->assertDatabaseHas('products', ['sku' => 'OK-1']);
        $this->assertDatabaseMissing('products', ['sku' => 'NO-1']);
    }

    public function test_modes_and_auto_start(): void
    {
        $rows = ['Products' => [
            self::PRODUCT_HEADERS,
            ['OLD-001', '', '', '', '', '', 700, '', '', '', '', '', '', ''],
            ['FRESH-1', 'Fresh', 'Car Parts > Brakes', 'Bosch', '', 300, 250, '', '', '', '', '', '', ''],
        ]];

        $create = $this->upload($this->workbook($rows), 'a.xlsx', ['mode' => 'create']);
        $this->assertSame([1, 0, 1], [$create['summary']['will_create'], $create['summary']['will_update'], $create['summary']['will_skip']]);

        $update = $this->upload($this->workbook($rows), 'b.xlsx', ['mode' => 'update', 'auto_start' => 1]);
        $this->assertSame(ProductImport::COMPLETED, $update['status'], 'auto start runs the import straight after a clean check');
        $this->assertSame([0, 1, 1], [$update['created_count'], $update['updated_count'], $update['skipped_count']]);
        $this->assertEquals(700, $this->existing->fresh()->price);
        $this->assertDatabaseMissing('products', ['sku' => 'FRESH-1']);
    }

    public function test_csv_files_are_accepted(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'csv');
        file_put_contents($path, "\u{FEFF}SKU,Product name,Category,Brand,MRP,Selling price\nCSV-1,From CSV,Car Parts > Brakes,Bosch,\"1,200\",999\n");
        $import = $this->upload($path, 'products.csv', ['auto_start' => 1]);
        $this->assertSame(ProductImport::COMPLETED, $import['status'], json_encode($import));
        $this->assertEquals(1200, Product::where('sku', 'CSV-1')->value('mrp'));
    }

    public function test_images_are_downloaded_in_the_background_and_failures_are_warnings(): void
    {
        config(['imports.allow_private_image_hosts' => true]);
        $img = imagecreatetruecolor(20, 20);
        ob_start();
        imagepng($img);
        $png = ob_get_clean();
        Http::fake([
            'images.example.com/ok.png' => Http::response($png, 200, ['Content-Type' => 'image/png']),
            'images.example.com/missing.png' => Http::response('', 404),
            'images.example.com/page.png' => Http::response('<html>not an image</html>', 200),
        ]);

        $headers = ['SKU', 'Product name', 'Category', 'Brand', 'MRP', 'Selling price', 'Image URLs'];
        $file = $this->workbook(['Products' => [$headers,
            ['IMG-1', 'With photos', 'Car Parts > Brakes', 'Bosch', 100, 90, 'https://images.example.com/ok.png | https://images.example.com/missing.png | https://images.example.com/page.png'],
        ]]);
        $import = $this->upload($file, 'img.xlsx', ['auto_start' => 1]);
        $this->assertSame(ProductImport::COMPLETED, $import['status']);
        $this->assertSame(2, $import['warning_count']);

        $p = Product::where('sku', 'IMG-1')->with('images')->firstOrFail();
        $this->assertCount(1, $p->images);
        $this->assertTrue($p->images->first()->is_primary);
        Storage::disk('public')->assertExists($p->images->first()->path);

        $warnings = collect($this->getJson(self::URL.'/'.$import['id'].'?level=warning')->json('data.issues'))->pluck('message')->implode(' ');
        $this->assertStringContainsString('server answered 404', $warnings);
        $this->assertStringContainsString('not a JPG, PNG, WEBP or GIF', $warnings);
    }

    public function test_image_fetcher_blocks_private_and_non_http_addresses(): void
    {
        config(['imports.allow_private_image_hosts' => false]);
        $fetcher = new RemoteImageFetcher;
        foreach (['http://127.0.0.1/a.png', 'http://10.0.0.5/a.png', 'http://[::1]/a.png', 'file:///etc/passwd', 'ftp://example.com/a.png', 'http://user:pw@example.com/a.png'] as $url) {
            try {
                $fetcher->assertAllowed($url);
                $this->fail("{$url} should be blocked");
            } catch (ImageFetchException) {
                $this->addToAssertionCount(1);
            }
        }
    }

    public function test_cancel_discard_and_unreadable_files(): void
    {
        $import = $this->upload($this->workbook(['Products' => [self::PRODUCT_HEADERS, ['X-1', 'X', 'Car Parts > Brakes', 'Bosch', '', 10, 9, '', '', '', '', '', '', '']]]));
        $this->postJson(self::URL.'/'.$import['id'].'/cancel')->assertOk()->assertJsonPath('data.status', ProductImport::CANCELLED);
        $this->postJson(self::URL.'/'.$import['id'].'/start')->assertStatus(409);
        $this->deleteJson(self::URL.'/'.$import['id'])->assertOk();
        $this->assertDatabaseMissing('product_imports', ['id' => $import['id']]);

        $junk = tempnam(sys_get_temp_dir(), 'junk');
        file_put_contents($junk, 'this is not a spreadsheet');
        $failed = $this->upload($junk, 'junk.xlsx');
        $this->assertSame(ProductImport::FAILED, $failed['status']);
        $this->assertStringContainsString('could not be opened', $failed['message']);

        $noSku = $this->workbook(['Products' => [['Name', 'Price'], ['A', 1]]]);
        $failed = $this->upload($noSku, 'nosku.xlsx');
        $this->assertSame(ProductImport::FAILED, $failed['status']);
        $this->assertStringContainsString('missing the "SKU" column', $failed['message']);

        $this->postJson(self::URL, ['file' => UploadedFile::fake()->create('notes.pdf', 10)])->assertStatus(422)->assertJsonPath('errors.file.0', 'Please upload an Excel file (.xlsx or .xls) or a .csv file.');
    }

    public function test_only_staff_with_product_permission_can_import(): void
    {
        Sanctum::actingAs($this->customer(), ['storefront']);
        $this->getJson(self::URL)->assertStatus(403);

        Sanctum::actingAs($this->staff(Role::ORDER_MANAGER), ['admin', 'storefront']);
        $this->getJson(self::URL.'/template')->assertStatus(403);
        $this->postJson(self::URL, [])->assertStatus(403);

        Sanctum::actingAs($this->staff(Role::CATALOG_MANAGER), ['admin', 'storefront']);
        $this->getJson(self::URL)->assertOk();
    }

    // ── Helpers ───────────────────────────────────────────────────
    /** @param array<string, list<list<mixed>>> $sheets */
    private function workbook(array $sheets): string
    {
        $book = new Spreadsheet;
        $book->removeSheetByIndex(0);
        foreach ($sheets as $name => $rows) {
            $ws = $book->createSheet()->setTitle($name);
            foreach ($rows as $r => $cells) {
                foreach ($cells as $c => $value) {
                    if ($value !== '' && $value !== null) {
                        $ws->setCellValue([$c + 1, $r + 1], $value);
                    }
                }
            }
        }
        $path = tempnam(sys_get_temp_dir(), 'imp').'.xlsx';
        (new Xlsx($book))->save($path);

        return $path;
    }

    /** Uploads a file (the sync test queue runs the check immediately) and returns the import payload. */
    private function upload(string $path, string $name = 'products.xlsx', array $extra = []): array
    {
        $file = new UploadedFile($path, $name, null, null, true);
        $id = $this->post(self::URL, ['file' => $file, ...$extra])->assertStatus(202)->json('data.id');

        return $this->getJson(self::URL.'/'.$id)->assertOk()->json('data');
    }
}
