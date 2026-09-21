<?php namespace ExternalModules;

class ModuleBaseTestTest extends ModuleBaseTest
{
    function testDisableTestSettings(){
        $key = 'some-key';
        $this->setProjectId(-1);

        $projectMemoryValue = rand();
        $this->setProjectSetting($key, $projectMemoryValue);
        $this->assertSame($projectMemoryValue, $this->getProjectSetting($key));

        $systemMemoryValue = rand();
        $this->setSystemSetting($key, $systemMemoryValue);
        $this->assertSame($systemMemoryValue, $this->getSystemSetting($key));

        $this->disableTestSettings();
        $this->assertNull($this->getProjectSetting($key));
        $this->assertNull($this->getSystemSetting($key));
    }

    // Make sure removes work with in memory test settings
    function testRemoveSystemSetting(){
        $key = 'some-key';
        $value = rand();

        $this->setSystemSetting($key, $value);
        $this->assertSame($value, $this->getSystemSetting($key));
        $this->removeSystemSetting($key);
        $this->assertNull($this->getSystemSetting($key));

        $this->setProjectSetting($key, $value);
        $this->assertSame($value, $this->getProjectSetting($key));
        $this->removeProjectSetting($key);
        $this->assertNull($this->getProjectSetting($key));
    }

    // Test array settings in memory, since they get json encoded/decoded.
    function testArraySettings(){
        $key = 'some-key';
        $value = [rand(), rand()];

        $this->setSystemSetting($key, $value);
        $this->assertSame($value, $this->getSystemSetting($key));
    }
}