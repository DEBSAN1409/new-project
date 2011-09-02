<?php

/*
 * This file is part of the Sonata package.
 *
 * (c) Thomas Rabaix <thomas.rabaix@sonata-project.org>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Sonata\Tests\Component\Payment;

use Sonata\Component\Payment\PassPayment;
use Sonata\Component\Payment\Pool;
use Buzz\Message\Response;
use Buzz\Message\Request;
use Buzz\Browser;
use Buzz\Client\ClientInterface;
use Buzz\Client\Mock\FIFO;

class PassPaymentTest extends \PHPUnit_Framework_TestCase
{
    /**
     * useless test ....
     *
     * @return void
     */
    public function testPassPayment()
    {
        $router = $this->getMock('Symfony\Component\Routing\RouterInterface');
        $router->expects($this->exactly(2))->method('generate')->will($this->returnValue('http://foo.bar/ok-url'));

        $response = new Response;
        $response->setContent('ok');

        $client = new FIFO;
        $client->sendToQueue($response);

        $browser = new Browser($client);
        $payment = new PassPayment($router, $browser);
        $payment->setCode('free_1');

        $basket = $this->getMock('Sonata\Component\Basket\Basket');
        $product = $this->getMock('Sonata\Component\Product\ProductInterface');
        $transaction = $this->getMock('Sonata\Component\Payment\TransactionInterface');

        $transaction->expects($this->exactly(2))->method('get')->will($this->returnCallback(array($this, 'callback')));

        $transaction->expects($this->once())->method('setTransactionId');

        $order = $this->getMock('Sonata\Component\Order\OrderInterface');

        $this->assertEquals('free_1', $payment->getCode(), 'Pass Payment return the correct code');
        $this->assertTrue($payment->isAddableProduct($basket, $product));
        $this->assertTrue($payment->isBasketValid($basket));
        $this->assertTrue($payment->isRequestValid($transaction));
        $this->assertTrue($payment->isCallbackValid($transaction));

        $this->assertInstanceOf('Symfony\Component\HttpFoundation\Response', $payment->handleError($transaction));
        $this->assertInstanceOf('Symfony\Component\HttpFoundation\Response', $payment->sendConfirmationReceipt($transaction));

        $response = $payment->callbank($order);
        $this->assertTrue($response->headers->has('Location'));
        $this->assertEquals('http://foo.bar/ok-url', $response->headers->get('Location'));
        $this->assertFalse($response->isCacheable());

        $this->assertEquals($payment->getOrderReference($transaction), '0001231');

        $payment->applyTransactionId($transaction);
    }

    public function callback($name)
    {
        if ($name == 'reference') {
            return '0001231';
        }

        if ($name == 'transaction_id') {
            return 1;
        }
    }
}