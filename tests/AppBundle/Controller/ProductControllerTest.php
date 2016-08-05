<?php
/**
 * Created by PhpStorm.
 * User: piotr.grzegorzewski
 * Date: 04/08/2016
 * Time: 20:47
 */

namespace Tests\AppBundle\Controller;


use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;

class ProductControllerTest extends WebTestCase
{

    public function testAddActionWithGoodData() {
        $client = static::createClient();

        $newProductTitle = "testAddActionWithGoodData";
        $newProductPrice = 1.99;

        $client->request(
            'POST',
            '/products',
            array(),
            array(),
            array(
                'CONTENT_TYPE' => 'application/json',
                json_encode( [ "title"=>$newProductTitle, "price"=>$newProductPrice ] )
            )
        );

        $response = $client->getResponse();

        // Assert a 201 status code
        $this->assertEquals(
            Response::HTTP_CREATED,
            $response->getStatusCode(),
            "Status code is not ".Response::HTTP_CREATED
        );

        // Assert that the "Location" header has "products"
        $this->assertTrue(
            $response->headers->contains(
                'Location',
                'products'
            ),
            'the Location header doesn\'t contain valid info.'
        );

        $productUrl = $response->headers->get("Location");
        $client->request(
            'GET',
            $productUrl,
            array(),
            array(),
            array(
                'CONTENT_TYPE' => 'application/json'
            )
        );

        // Assert the headers:
        $response = $client->getResponse();
        // Assert that the "Content-Type" header is "application/json"
        $this->assertTrue(
            $client->getResponse()->headers->contains(
                'Content-Type',
                'application/json' //will be application/vnd.api+json
            ),
            'the "Content-Type" header is not "application/json"' // optional message shown on failure
        );
        // Assert a 200 status code
        $this->assertEquals(
            Response::HTTP_OK,
            $response->getStatusCode(),
            "Status code is not ".Response::HTTP_OK
        );

        // Assert the content:
        $responseData = json_decode($response->getContent(), true);
        //Assert all the fields:
        $this->assertTrue(
            array_key_exists("title", $responseData)
        );
        $this->assertTrue(
            array_key_exists("price", $responseData)
        );
        //Assert their values:
        $this->assertEquals(
            $responseData["title"],
            $newProductTitle,
            "Returned product's title is not the same as the one set."
        );
        $this->assertEquals(
            $responseData["price"],
            $newProductPrice,
            "Returned product's price is not the same as the one set."
        );
    }

    public function testListAllProducts() {
        $client = static::createClient();
        $client->request(
            'GET',
            '/products'
        );
        // Assert that the response is a redirect to /demo/contact
        $this->assertTrue(
            $client->getResponse()->isRedirect('/products/list/1'),
            'response is not a redirect to a pagination list'
        );
    }

}