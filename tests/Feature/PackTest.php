<?php

namespace Tests\Feature;

use App\Models\ModuleCompletion;
use App\Models\Policy;
use App\Models\User;
use App\Packs\PackException;
use App\Packs\PackExporter;
use App\Packs\PackLoader;
use App\Services\Training;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class PackTest extends TestCase
{
    public function test_skeleton_and_demo_packs_are_valid(): void
    {
        foreach (['_skeleton', 'demo'] as $pack) {
            $this->assertArrayHasKey('manifest', app(PackLoader::class)->read(base_path("packs/$pack")));
        }
    }

    public function test_reinstalling_updates_content_without_losing_progress(): void
    {
        $this->installDemo();
        $user = $this->employee();
        $module = $user->group->track->modules->first();
        app(Training::class)->completeModule($user, $module);

        $this->installDemo();

        $this->assertSame(1, ModuleCompletion::count());
        $this->assertSame(26, User::count());
        $this->assertSame(9, Policy::count());
    }

    public function test_invalid_pack_reports_every_problem(): void
    {
        $dir = storage_path('framework/testing/bad-pack');
        File::deleteDirectory($dir);
        File::copyDirectory(base_path('packs/_skeleton'), $dir);
        File::put("$dir/groups.yaml", "- key: g1\n  name: G1\n  track: nope\n");
        File::put("$dir/quizzes/office.yaml", "- question: Q\n  options: { A: a, B: b }\n  correct: Z\n");

        try {
            app(PackLoader::class)->read($dir);
            $this->fail('Expected PackException');
        } catch (PackException $e) {
            $text = implode("\n", $e->errors);
            $this->assertStringContainsString("unknown track 'nope'", $text);
            $this->assertStringContainsString("'correct' must be one of the option letters", $text);
        } finally {
            File::deleteDirectory($dir);
        }
    }

    public function test_exported_pack_can_be_installed_again(): void
    {
        $this->installDemo();
        $zip = app(PackExporter::class)->export();

        $summary = app(PackLoader::class)->install($zip);

        $this->assertSame(9, $summary['policies']);
        $this->assertSame(16, $summary['questions']);
        File::delete($zip);
    }

    public function test_first_run_redirects_to_setup_until_a_pack_is_installed(): void
    {
        $this->get('/login')->assertRedirect('/setup');
        $this->post('/setup')->assertRedirect('/login');
        $this->get('/login')->assertOk()->assertSee('Helios Foods');
        $this->get('/setup')->assertRedirect('/login');
    }
}
