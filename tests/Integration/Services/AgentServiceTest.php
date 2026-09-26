<?php

declare(strict_types=1);

namespace AndyDefer\Nemesis\Tests\Integration\Services;

use AndyDefer\Nemesis\Contracts\Services\AgentServiceInterface;
use AndyDefer\Nemesis\Records\AgentPropertiesRecord;
use AndyDefer\Nemesis\Services\AgentService;
use AndyDefer\Nemesis\Tests\IntegrationTestCase;
use Jenssegers\Agent\Agent as JenssegersAgent;

final class AgentServiceTest extends IntegrationTestCase
{
    private const CHROME_MACOS_UA =
        'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) '
        .'AppleWebKit/537.36 (KHTML, like Gecko) '
        .'Chrome/120.0.0.0 Safari/537.36';

    private const FIREFOX_WINDOWS_UA =
        'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:121.0) '
        .'Gecko/20100101 Firefox/121.0';

    private const IPHONE_SAFARI_UA =
        'Mozilla/5.0 (iPhone; CPU iPhone OS 17_0 like Mac OS X) '
        .'AppleWebKit/605.1.15 (KHTML, like Gecko) '
        .'Version/17.0 Mobile/15E148 Safari/604.1';

    private const IPAD_SAFARI_UA =
        'Mozilla/5.0 (iPad; CPU OS 17_0 like Mac OS X) '
        .'AppleWebKit/605.1.15 (KHTML, like Gecko) '
        .'Version/17.0 Mobile/15E148 Safari/604.1';

    private const GOOGLEBOT_UA =
        'Mozilla/5.0 (compatible; Googlebot/2.1; +http://www.google.com/bot.html)';

    private function makeService(?string $userAgent = null): AgentService
    {
        $jenssegers = new JenssegersAgent;

        if ($userAgent !== null) {
            $jenssegers->setUserAgent($userAgent);
        }

        return new AgentService($jenssegers);
    }

    public function test_resolves_agent_service_interface_from_container(): void
    {
        $service = $this->app->make(AgentServiceInterface::class);

        $this->assertInstanceOf(AgentService::class, $service);
    }

    public function test_detects_chrome_on_macos(): void
    {
        $service = $this->makeService(self::CHROME_MACOS_UA);

        $this->assertSame('Chrome', $service->browser());
        $this->assertSame('OS X', $service->platform());
        $this->assertSame('desktop', strtolower($service->deviceType()));
        $this->assertTrue($service->isDesktop());
        $this->assertFalse($service->isMobile());
        $this->assertFalse($service->isTablet());
    }

    public function test_detects_firefox_on_windows(): void
    {
        $service = $this->makeService(self::FIREFOX_WINDOWS_UA);

        $this->assertSame('Firefox', $service->browser());
        $this->assertSame('Windows', $service->platform());
        $this->assertSame('desktop', strtolower($service->deviceType()));
        $this->assertTrue($service->isDesktop());
    }

    public function test_detects_iphone_safari(): void
    {
        $service = $this->makeService(self::IPHONE_SAFARI_UA);

        $this->assertTrue($service->isMobile());
        $this->assertFalse($service->isDesktop());
        $this->assertSame('phone', strtolower($service->deviceType()));
    }

    public function test_detects_ipad_safari(): void
    {
        $service = $this->makeService(self::IPAD_SAFARI_UA);

        $this->assertTrue($service->isTablet());
        $this->assertFalse($service->isDesktop());
        $this->assertSame('tablet', strtolower($service->deviceType()));
    }

    public function test_detects_googlebot_as_robot(): void
    {
        $service = $this->makeService(self::GOOGLEBOT_UA);

        $this->assertTrue($service->isRobot());
    }

    public function test_returns_unknown_for_empty_user_agent(): void
    {
        $service = $this->makeService('');

        $this->assertSame('unknown', $service->browser());
        $this->assertSame('unknown', $service->platform());
        $this->assertSame('unknown', $service->version());
        $this->assertSame('unknown', $service->platformVersion());
    }

    public function test_set_user_agent_is_fluent_and_updates_properties(): void
    {
        $service = $this->makeService();

        $result = $service->setUserAgent(self::CHROME_MACOS_UA);

        $this->assertSame($service, $result);
        $this->assertSame('Chrome', $service->browser());
        $this->assertSame('OS X', $service->platform());
    }

    public function test_get_properties_returns_agent_properties_record(): void
    {
        $service = $this->makeService(self::CHROME_MACOS_UA);

        $properties = $service->getProperties();

        $this->assertInstanceOf(AgentPropertiesRecord::class, $properties);
        $this->assertSame('Chrome', $properties->browser);
        $this->assertSame('OS X', $properties->platform);
        $this->assertSame('desktop', strtolower($properties->device_type));
        $this->assertFalse($properties->is_mobile);
        $this->assertTrue($properties->is_desktop);
        $this->assertFalse($properties->is_tablet);
        $this->assertFalse($properties->is_robot);
        $this->assertSame(self::CHROME_MACOS_UA, $properties->user_agent);
    }

    public function test_get_properties_detects_robot(): void
    {
        $service = $this->makeService(self::GOOGLEBOT_UA);

        $properties = $service->getProperties();

        $this->assertTrue($properties->is_robot);
        $this->assertFalse($properties->is_desktop);
    }

    public function test_get_user_agent_returns_raw_string(): void
    {
        $service = $this->makeService(self::FIREFOX_WINDOWS_UA);

        $this->assertSame(self::FIREFOX_WINDOWS_UA, $service->getUserAgent());
    }
}
