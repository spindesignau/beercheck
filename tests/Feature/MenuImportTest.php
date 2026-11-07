<?php

namespace Tests\Feature;

use App\Models\Beer;
use App\Models\BeerPrice;
use App\Models\MenuImport;
use App\Models\User;
use App\Models\Venue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class MenuImportTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        $this->admin = User::factory()->admin()->create();
        Venue::factory()->create(['name' => 'The Green Man', 'suburb' => 'Fitzroy', 'postcode' => '3065']);
        Venue::factory()->create(['name' => 'The Railway Hotel', 'suburb' => 'Brunswick', 'postcode' => '3056']);
        Venue::factory()->create(['name' => 'The Railway Hotel', 'suburb' => 'Windsor', 'postcode' => '3181']);
        Beer::factory()->create(['brand' => 'Carlton', 'name' => 'Draught']);
    }

    private function csv(array $rows): UploadedFile
    {
        $lines = array_map(fn ($r) => implode(',', array_map(fn ($c) => str_contains($c, ',') ? '"'.$c.'"' : $c, $r)), $rows);

        return UploadedFile::fake()->createWithContent('menu.csv', "Beercheck menu export\n".implode("\n", $lines)."\n");
    }

    private function sampleFile(): UploadedFile
    {
        return $this->csv([
            ['Venue', 'Suburb', 'Postcode', 'Brewery', 'Beer', 'Style', 'ABV', 'Glass', 'Price'],
            ['The Green Man', 'Fitzroy', '3065', 'Carlton', 'Carlton Draught', 'Lager', '4.6%', 'Middy', '$7.50'],
            ['the green man', '', '3065', 'Carlton', 'Draught', 'Lager', '', 'Schooner (425ml)', '10.50'],
            ['The Green Man', 'Fitzroy', '', 'Hop Nation', 'The Buzz', 'Pale Ale', '5.2', 'pint', '13'],
            ['The Railway Hotel', 'Brunswick', '3056', 'Carlton', 'Draught', '', '', 'pint', '12'],
            ['The Railway Hotel', '', '', 'Carlton', 'Draught', '', '', 'pint', '12'],
            ['Nowhere Inn', '', '', 'Carlton', 'Draught', '', '', 'pint', '12'],
            ['The Green Man', '', '3065', 'Carlton', 'Draught', '', '', 'bucket', '12'],
            ['The Green Man', '', '3065', 'Carlton', 'Draught', '', '', 'pint', 'free'],
        ]);
    }

    public function test_preview_then_import_then_undo(): void
    {
        $this->actingAs($this->admin)->withoutVite();

        $preview = $this->post(route('admin.imports.preview'), ['file' => $this->sampleFile(), 'as_official' => 1])->assertOk();
        $result = $preview->viewData('result');
        $this->assertSame(8, $result['rows_read']);
        $this->assertSame(4, $result['prices_imported']);
        $this->assertSame(1, $result['beers_created']);
        $this->assertCount(4, $result['errors']);
        $messages = implode(' | ', array_column($result['errors'], 'message'));
        $this->assertStringContainsString('matches several venues', $messages);
        $this->assertStringContainsString('not found', $messages);
        $this->assertStringContainsString('Unknown size "bucket"', $messages);
        $this->assertStringContainsString('not a valid amount', $messages);
        $this->assertSame(0, BeerPrice::count(), 'preview must not write');

        $this->post(route('admin.imports.store'), [
            'token' => $preview->viewData('token'), 'source' => 'menu.csv', 'as_official' => 1,
        ])->assertRedirect(route('admin.imports.index'));

        $this->assertSame(4, BeerPrice::count());
        $this->assertTrue(BeerPrice::all()->every(fn ($p) => $p->is_venue_listed && $p->user_id === $this->admin->id));
        $this->assertSame(['pot' => 7.5, 'schooner' => 10.5],
            BeerPrice::whereHas('venue', fn ($q) => $q->where('name', 'The Green Man'))->whereHas('beer', fn ($q) => $q->where('name', 'Draught'))
                ->get()->mapWithKeys(fn ($p) => [$p->size->value => (float) $p->price])->sortKeys()->all());
        $buzz = Beer::firstWhere('name', 'The Buzz');
        $this->assertSame('approved', $buzz->status->value);
        $this->assertSame(5.2, $buzz->abv);
        $this->assertSame(0, $this->admin->fresh()->points, 'imports never award points');

        $import = MenuImport::firstOrFail();
        $this->assertSame(4, $import->error_count);

        // Re-importing the same menu skips unchanged prices.
        $again = $this->post(route('admin.imports.preview'), ['file' => $this->sampleFile(), 'as_official' => 1])->viewData('result');
        $this->assertSame(0, $again['prices_imported']);
        $this->assertSame(4, $again['duplicates_skipped']);

        $this->post(route('admin.imports.undo', $import))->assertRedirect();
        $this->assertSame(0, BeerPrice::count());
        $this->assertSame('undone', $import->fresh()->status);

        $this->get(route('admin.imports.index'))->assertOk()->assertSee('menu.csv');
        $this->get(route('admin.imports.template'))->assertOk()->assertDownload('beercheck-menu-template.csv');
    }

    public function test_file_without_required_columns_is_rejected(): void
    {
        $this->actingAs($this->admin)
            ->post(route('admin.imports.preview'), ['file' => $this->csv([['name', 'cost'], ['x', '1']])])
            ->assertSessionHasErrors('file');
    }

    public function test_google_sheets_link_is_converted_to_csv_export(): void
    {
        Http::fake(['docs.google.com/*' => Http::response(
            "venue,postcode,brand,beer,size,price\nThe Green Man,3065,Carlton,Draught,jug,30\n",
            200, ['Content-Type' => 'text/csv'],
        )]);

        $this->actingAs($this->admin)->withoutVite()
            ->post(route('admin.imports.preview'), ['sheet_url' => 'https://docs.google.com/spreadsheets/d/abc123XYZ/edit#gid=42'])
            ->assertOk()->assertSee('Import 1 prices');

        Http::assertSent(fn ($r) => $r->url() === 'https://docs.google.com/spreadsheets/d/abc123XYZ/export?format=csv&gid=42');
    }

    public function test_private_google_sheet_gives_a_helpful_error(): void
    {
        Http::fake(['docs.google.com/*' => Http::response('<html>Sign in</html>', 200, ['Content-Type' => 'text/html'])]);

        $this->actingAs($this->admin)
            ->post(route('admin.imports.preview'), ['sheet_url' => 'https://docs.google.com/spreadsheets/d/abc/edit'])
            ->assertSessionHasErrors(['file' => 'Could not download that sheet as CSV. In Google Sheets use Share → "Anyone with the link can view", then paste the link again.']);
    }

    public function test_cli_import(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'menu').'.csv';
        file_put_contents($path, "venue,postcode,brand,beer,size,price\nThe Green Man,3065,Carlton,Draught,pot,7\n");

        $this->artisan('app:import-menu', ['source' => $path, '--dry-run' => true])->assertSuccessful();
        $this->assertSame(0, BeerPrice::count());

        $this->artisan('app:import-menu', ['source' => $path])->assertSuccessful();
        $this->assertSame(1, BeerPrice::count());
        @unlink($path);
    }

    public function test_only_admins_can_import(): void
    {
        $this->actingAs(User::factory()->create())->get(route('admin.imports.index'))->assertForbidden();
    }
}
