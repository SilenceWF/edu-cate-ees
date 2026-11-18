<?php

namespace Tests\Feature;

use App\Http\Controllers\BrandController;
use App\Models\Policy;
use App\Models\User;
use App\Packs\PackLoader;
use App\Services\Progress;
use App\Support\Logo;
use App\Support\Settings;
use App\Support\SystemCheck;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use Mockery;
use Tests\TestCase;

class SettingsBrandingTest extends TestCase
{
    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->installDemo();
        $this->admin = $this->person('admin@helios-foods.example');
    }

    protected function tearDown(): void
    {
        File::deleteDirectory(Logo::dir());
        parent::tearDown();
    }

    private function png(): UploadedFile
    {
        $img = imagecreatetruecolor(120, 40);
        $path = tempnam(sys_get_temp_dir(), 'logo').'.png';
        imagepng($img, $path);

        return new UploadedFile($path, 'brand.png', 'image/png', null, true);
    }

    private function svg(string $body): UploadedFile
    {
        $path = tempnam(sys_get_temp_dir(), 'logo').'.svg';
        file_put_contents($path, $body);

        return new UploadedFile($path, 'brand.svg', 'image/svg+xml', null, true);
    }

    public function test_admin_can_upload_a_png_logo_and_it_is_used_everywhere(): void
    {
        $this->actingAs($this->admin)->post('/admin/settings/logo', ['logo' => $this->png()])->assertSessionHasNoErrors();
        app(Settings::class)->flush();

        $url = app(Logo::class)->url();
        $this->assertStringContainsString('/brand/logo?v=', $url);
        $this->actingAs($this->admin)->get('/')->assertSee($url, false);
        $this->get('/brand/logo')->assertOk()->assertHeader('Content-Type', 'image/png');
        $this->assertStringStartsWith('data:image/png;base64,', BrandController::logoDataUri());
    }

    public function test_plain_svg_is_accepted_and_served_with_a_locked_down_policy(): void
    {
        $svg = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 10 10"><defs><linearGradient id="g"/></defs><rect width="10" height="10" fill="url(#g)"/></svg>';
        $this->actingAs($this->admin)->post('/admin/settings/logo', ['logo' => $this->svg($svg)])->assertSessionHasNoErrors();
        app(Settings::class)->flush();

        $response = $this->get('/brand/logo');
        $response->assertOk()->assertHeader('Content-Type', 'image/svg+xml');
        $this->assertStringContainsString("default-src 'none'", $response->headers->get('Content-Security-Policy'));
    }

    public function test_svg_with_script_or_external_reference_is_rejected(): void
    {
        foreach ([
            '<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>',
            '<svg xmlns="http://www.w3.org/2000/svg" onload="alert(1)"></svg>',
            '<svg xmlns="http://www.w3.org/2000/svg"><image href="https://evil.example/x.png"/></svg>',
        ] as $svg) {
            $this->actingAs($this->admin)->post('/admin/settings/logo', ['logo' => $this->svg($svg)])->assertSessionHasErrors('logo');
        }
        app(Settings::class)->flush();
        $this->assertNotSame('admin', setting('branding.logo_source'));
    }

    public function test_reinstalling_the_pack_keeps_an_admin_uploaded_logo(): void
    {
        $this->actingAs($this->admin)->post('/admin/settings/logo', ['logo' => $this->png()]);
        app(Settings::class)->flush();
        $uploaded = setting('branding.logo');

        app(PackLoader::class)->install(base_path('packs/demo'));
        app(Settings::class)->flush();

        $this->assertSame($uploaded, setting('branding.logo'));
        $this->assertNotNull(app(Logo::class)->path());
    }

    public function test_hr_cannot_change_the_logo(): void
    {
        $this->actingAs($this->person('hr@helios-foods.example'))->post('/admin/settings/logo', ['logo' => $this->png()])->assertForbidden();
    }

    public function test_policies_are_listed_in_natural_code_order(): void
    {
        Policy::query()->update(['is_active' => false]);
        foreach (['Π Α7-4', 'Π Α5-10', 'Π Α5-2', 'Π Α6-1', 'Π Α5-1'] as $i => $code) {
            Policy::create(['code' => $code, 'title' => ['el' => $code], 'version' => '1', 'sort_order' => 100 - $i]);
        }
        $expected = ['Π Α5-1', 'Π Α5-2', 'Π Α5-10', 'Π Α6-1', 'Π Α7-4'];

        $this->assertSame($expected, Progress::forUser($this->employee())['required_policies']->pluck('code')->all());

        $html = $this->actingAs($this->admin)->get('/admin/content')->getContent();
        $positions = array_map(fn ($c) => strpos($html, '>'.$c.'<'), $expected);
        $this->assertSame($positions, collect($positions)->sort()->values()->all());
    }

    public function test_import_explains_a_missing_php_extension(): void
    {
        $check = Mockery::mock(SystemCheck::class)->makePartial();
        $check->shouldReceive('loaded')->andReturnUsing(fn ($ext) => $ext !== 'zip');
        $this->app->instance(SystemCheck::class, $check);

        $this->actingAs($this->admin)->post('/admin/import/preview', ['file' => UploadedFile::fake()->create('plan.xlsx', 10)])
            ->assertSessionHasErrors(['file' => __('admin.system.missing', ['ext' => 'zip'])]);
    }

    public function test_unreadable_file_reports_the_reason(): void
    {
        $this->actingAs($this->admin)->post('/admin/import/preview', ['file' => UploadedFile::fake()->createWithContent('plan.xlsx', 'not really a spreadsheet')])
            ->assertSessionHasErrors('file');
        $this->assertStringContainsString(__('admin.import.unreadable').' (', session('errors')->first('file'));
    }

    public function test_settings_page_shows_the_system_check(): void
    {
        $this->actingAs($this->admin)->get('/admin/settings')->assertOk()
            ->assertSee(__('admin.system.title'))->assertSee('PHP: zip')->assertSee(__('admin.logo.title'));
    }

    public function test_header_shows_only_the_logo_when_one_is_set(): void
    {
        $nameSpan = '<span class="text-xl font-extrabold tracking-tight">Helios Foods</span>';
        $this->actingAs($this->admin)->delete('/admin/settings/logo');
        app(Settings::class)->flush();
        $html = $this->actingAs($this->admin)->get('/')->getContent();
        $this->assertStringNotContainsString('/brand/logo?v=', $html);
        $this->assertStringContainsString($nameSpan, $html, 'no logo: company name as text');

        $this->actingAs($this->admin)->post('/admin/settings/logo', ['logo' => $this->png()]);
        app(Settings::class)->flush();

        $html = $this->actingAs($this->admin)->get('/')->getContent();
        $this->assertStringContainsString('alt="Helios Foods"', $html);
        $this->assertStringNotContainsString($nameSpan, $html);
    }
}
