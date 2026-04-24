<?php

namespace InterNACHI\Modular\Tests;

use Illuminate\Support\Collection;
use InterNACHI\Modular\Support\ModuleConfig;
use InterNACHI\Modular\Support\ModuleRegistry;
use InterNACHI\Modular\Tests\Concerns\WritesToAppFilesystem;

class ModuleRegistryTest extends TestCase
{
	use WritesToAppFilesystem;

	public function test_module_for_class_prefers_longest_matching_namespace(): void
	{
		$general = new ModuleConfig('general', '/tmp/general', new Collection([
			'/tmp/general/src' => 'App\\General\\',
		]));

		$specific = new ModuleConfig('specific', '/tmp/specific', new Collection([
			'/tmp/general-specific/src' => 'App\\General\\Specific\\',
		]));

		$specific_model = 'App\\General\\Specific\\Models\\Thing';

		foreach ([[$general, $specific], [$specific, $general]] as $modules) {
			$registry = new ModuleRegistry(
				modules_path: '/tmp',
				modules_loader: fn() => Collection::make($modules)->keyBy->name,
			);

			$this->assertSame(
				'specific',
				$registry->moduleForClass($specific_model)?->name,
				'Expected the more-specific module to win regardless of iteration order',
			);
		}

		$registry = new ModuleRegistry(
			modules_path: '/tmp',
			modules_loader: fn() => Collection::make([$general, $specific])->keyBy->name,
		);

		$this->assertSame('general', $registry->moduleForClass('App\\General\\Models\\Plain')?->name);
		$this->assertNull($registry->moduleForClass('Unrelated\\Thing'));
	}

	public function test_it_resolves_modules(): void
	{
		$this->makeModule('test-module');
		$this->makeModule('test-module-two');
		
		$registry = $this->app->make(ModuleRegistry::class);
		
		$this->assertInstanceOf(ModuleConfig::class, $registry->module('test-module'));
		$this->assertInstanceOf(ModuleConfig::class, $registry->module('test-module-two'));
		$this->assertNull($registry->module('non-existant-module'));
		
		$this->assertCount(2, $registry->modules());
		
		$module = $registry->moduleForPath($this->getModulePath('test-module', 'foo/bar'));
		$this->assertInstanceOf(ModuleConfig::class, $module);
		$this->assertEquals('test-module', $module->name);
		
		$module = $registry->moduleForPath($this->getModulePath('test-module-two', 'foo/bar'));
		$this->assertInstanceOf(ModuleConfig::class, $module);
		$this->assertEquals('test-module-two', $module->name);
		
		$module = $registry->moduleForClass('Modules\\TestModule\\Foo');
		$this->assertInstanceOf(ModuleConfig::class, $module);
		$this->assertEquals('test-module', $module->name);
		
		$module = $registry->moduleForClass('Modules\\TestModuleTwo\\Foo');
		$this->assertInstanceOf(ModuleConfig::class, $module);
		$this->assertEquals('test-module-two', $module->name);
	}
}
