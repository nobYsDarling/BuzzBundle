<?php

namespace Buzz\Bundle\BuzzBundle\Tests\DependencyInjection\Compiler;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\XmlFileLoader;

use Buzz\Bundle\BuzzBundle\DependencyInjection\BuzzExtension;
use Buzz\Bundle\BuzzBundle\DependencyInjection\Compiler\BrowserPass;
use Buzz\Listener\ListenerChain;
use Buzz\Browser;
use Buzz\Client\FileGetContents;
use Buzz\Listener\CallbackListener;

class BrowserPassTest extends TestCase
{
    public function testProcessNoConfig()
    {
        $container = $this->createMock(ContainerBuilder::class);
        $browserPass = new BrowserPass();

        $return = $browserPass->process($container);
        $this->assertEquals(null, $return, 'No buzz.browser_manager registered');
    }

    public function testProcess()
    {
        $container = $this->getContainer();
        $browserPass = new BrowserPass();
        $browserPass->process($container);

        $this->assertTrue($container->get('buzz.browser_manager')->has('foo'));
        $browser = $container->get('buzz.browser_manager')->get('foo');
        $this->assertEquals($browser, $container->get('buzz.browser.my_foo'));

        $listener = $container->getDefinition('buzz.listener.host_foo');
        $this->assertEquals('my://foo', $listener->getArgument(0));
        $this->assertInstanceOf(ListenerChain::class, $browser->getListener());
        $listeners = $browser->getListener()->getListeners();
        $this->assertCount(2, $listeners, 'Two listeners defined in config');
        $this->assertEquals($listeners[0], $container->get('buzz.listener.host_foo'));
        $this->assertEquals($listeners[1], $container->get('bar'));
    }

    protected function getContainer()
    {
        $container = new ContainerBuilder();
        $loader = new XmlFileLoader($container, new FileLocator(__DIR__.'/../../../Resources/config'));
        $loader->load('buzz.xml');
        $extension = new BuzzExtension();
        $extension->load($this->getConfig(), $container);

        $container
            ->register('buzz.browser.my_foo')
            ->setClass(Browser::class)
            ->setArguments(array(null, null))
            ->addTag('buzz.browser', array('alias' => 'foo'))
        ;

        $container
            ->register('buzz.client.foo')
            ->setClass(FileGetContents::class)
            ->setArguments(array(null, null))
        ;

        $container
            ->register('bar')
            ->setClass(CallbackListener::class)
            ->setArguments(array(function(){ return; }))
        ;

        return $container;
    }

    private function getConfig()
    {
        return array(
            array(
                'throw_exception' => false,
                'profiler' => false,
                'listeners' => array(
                    'bar' => 'bar'
                ),
                'browsers' => array(
                    'foo' => array(
                        'client' => array('name' => 'file_get_contents'),
                        'message_factory' => null,
                        'host' => 'my://foo',
                        'listeners' => array('bar')
                    )
                )
            )
        );
    }
}
