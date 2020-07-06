<?php

namespace InterNACHI\Modular\Tests\Commands\Make;

use Illuminate\Filesystem\Filesystem;
use InterNACHI\Modular\Console\Commands\Make\MakeModule;
use InterNACHI\Modular\Tests\TestCase;

class MakeModuleTest extends TestCase
{
	protected $base_path;
	
	public function test_it_scaffolds_a_new_module() : void
	{
		$module_name = 'test-module';
		
		$this->artisan(MakeModule::class, [
			'name' => $module_name,
			'--accept-default-namespace' => true,
		]);
		
		$fs = new Filesystem();
		$module_path = $this->getBasePath().DIRECTORY_SEPARATOR.'app-modules'.DIRECTORY_SEPARATOR.$module_name;
		
		$this->assertTrue($fs->isDirectory($module_path));
		$this->assertTrue($fs->isDirectory($module_path.DIRECTORY_SEPARATOR.'database'));
		$this->assertTrue($fs->isDirectory($module_path.DIRECTORY_SEPARATOR.'resources'));
		$this->assertTrue($fs->isDirectory($module_path.DIRECTORY_SEPARATOR.'routes'));
		$this->assertTrue($fs->isDirectory($module_path.DIRECTORY_SEPARATOR.'src'));
		$this->assertTrue($fs->isDirectory($module_path.DIRECTORY_SEPARATOR.'tests'));
		
		$composer_file = $module_path.DIRECTORY_SEPARATOR.'composer.json';
		$this->assertTrue($fs->isFile($composer_file));
		
		$composer_contents = json_decode($fs->get($composer_file), true);
		
		$this->assertEquals("modules/{$module_name}", $composer_contents['name']);
		$this->assertContains('database/factories', $composer_contents['autoload']['classmap']);
		$this->assertContains('database/seeds', $composer_contents['autoload']['classmap']);
		$this->assertEquals('src/', $composer_contents['autoload']['psr-4']['Modules\\TestModule\\']);
		$this->assertEquals('tests/', $composer_contents['autoload-dev']['psr-4']['Modules\\TestModule\\Tests\\']);
		$this->assertContains('Modules\\TestModule\\Providers\\TestModuleServiceProvider', $composer_contents['extra']['laravel']['providers']);
	}
	
	protected function getBasePath()
	{
		if (null === $this->base_path) {
			$fs = new Filesystem();
			
			$testbench_base_path = parent::getBasePath();
			$this->base_path = sys_get_temp_dir().DIRECTORY_SEPARATOR.md5(__FILE__.time());
			
			$fs->copyDirectory($testbench_base_path, $this->base_path);
		}
		
		return $this->base_path;
	}
}
