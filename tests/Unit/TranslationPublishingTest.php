<?php

namespace Estin92\DvlaVes\Tests\Unit;

use Estin92\DvlaVes\DvlaVesServiceProvider;
use Estin92\DvlaVes\Enums\FuelType;
use Estin92\DvlaVes\Tests\TestCase;
use Illuminate\Support\Facades\App;

class TranslationPublishingTest extends TestCase
{
    public function test_single_lang_publish_group_targets_vendor_directory(): void
    {
        $groups = DvlaVesServiceProvider::$publishGroups;

        $this->assertArrayHasKey('dvla-ves-lang', $groups);

        $paths = $groups['dvla-ves-lang'];
        $source = array_key_first($paths);
        $target = $paths[$source];

        $this->assertStringContainsString('/lang', $source);
        $this->assertDirectoryExists($source);
        $this->assertStringContainsString('vendor/dvla-ves', $target);
    }

    public function test_config_publish_group_targets_config_path(): void
    {
        // The config file must remain publishable via vendor:publish --tag=dvla-ves-config.
        $groups = DvlaVesServiceProvider::$publishGroups;

        $this->assertArrayHasKey('dvla-ves-config', $groups);

        $paths = $groups['dvla-ves-config'];
        $source = array_key_first($paths);
        $target = $paths[$source];

        $this->assertStringContainsString('config/dvla-ves.php', $source);
        $this->assertDirectoryExists(dirname($source));
        $this->assertStringContainsString('dvla-ves.php', $target);
    }

    public function test_no_regional_variant_publish_groups_exist(): void
    {
        $groups = DvlaVesServiceProvider::$publishGroups;

        $this->assertArrayNotHasKey('dvla-ves-lang:en_GB', $groups);
        $this->assertArrayNotHasKey('dvla-ves-lang:en_US', $groups);
        $this->assertArrayNotHasKey('dvla-ves-lang-variants', $groups);
    }

    public function test_published_application_override_replaces_package_strings(): void
    {
        // Simulate a consuming app that published its own override at the
        // Laravel vendor path. The app override must win over the package's en.
        $overrideDir = sys_get_temp_dir().'/dvla-ves-override-'.uniqid();
        mkdir($overrideDir.'/en', 0777, true);
        file_put_contents(
            $overrideDir.'/en/enums.php',
            "<?php\n\nreturn ['fuel_type' => ['petrol' => 'OVERRIDDEN PETROL']];\n"
        );

        // Register the override path FIRST so it takes precedence (Laravel
        // resolves the most-recently-added namespaced path that has the key).
        App::make('translator')->addNamespace('dvla-ves', $overrideDir);
        App::setLocale('en');

        $this->assertSame('OVERRIDDEN PETROL', FuelType::Petrol->label());

        // cleanup
        unlink($overrideDir.'/en/enums.php');
        rmdir($overrideDir.'/en');
        rmdir($overrideDir);
    }
}
