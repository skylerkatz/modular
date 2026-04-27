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
			'/tmp/general/tests' => 'App\\General\\Tests',
		]));
		
		$specific = new ModuleConfig('specific', '/tmp/specific', new Collection([
			'/tmp/general-specific/src' => 'App\\General\\Specific\\',
			'/tmp/general-specific/tests' => 'App\\General\\Specific\\Tests',
		]));
		
		$loader_orders = [
			[$general, $specific],
			[$specific, $general],
		];
		
		$specific_model = 'App\\General\\Specific\\Models\\Thing';
		$specific_test = 'App\\General\\Specific\\Tests\\ThingTest';
		$general_model = 'App\\General\\Models\\Thing';
		$general_test = 'App\\General\\Tests\\ThingTest';
		
		foreach ($loader_orders as $modules) {
			$registry = new ModuleRegistry(
				modules_path: '/tmp',
				modules_loader: fn() => Collection::make($modules)->keyBy('name'),
			);
			
			$this->assertEquals(
				$specific,
				$registry->moduleForClass($specific_model),
				'Expected the more-specific module to win regardless of registration order (primary namespace)',
			);
			
			$this->assertEquals(
				$specific,
				$registry->moduleForClass($specific_test),
				'Expected the more-specific module to win regardless of registration order (secondary namespace)',
			);
			
			$this->assertEquals(
				$general,
				$registry->moduleForClass($general_model),
				'Expected the general module to return when a more-specific namespace does not match (primary namespace)',
			);
			
			$this->assertEquals(
				$general,
				$registry->moduleForClass($general_test),
				'Expected the general module to return when a more-specific namespace does not match (secondary namespace)',
			);
			
			$this->assertNull(
				$registry->moduleForClass('Unrelated\\Thing'),
				'Expected unrelated class not to match any module regardless of order'
			);
		}
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
