<?php
/**
 * Created by PhpStorm.
 * User: piotr.grzegorzewski
 * Date: 26/07/2016
 * Time: 18:30
 */

namespace Tests\AppBundle\Controller;



use AppBundle\Test\ApiTestCase;

class CartControllerTest extends ApiTestCase
{
    public function testCartAdd() {
        $this->client->request('POST', '/cart');
        // Assert that the "Content-Type" header is "application/json"
        $this->assertTrue(
            $this->client->getResponse()->headers->contains(
                'Content-Type',
                'application/json'
            ),
            'the "Content-Type" header is "application/json"' // optional message shown on failure
        );
    }
}
