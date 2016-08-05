<?php
/**
 * Created by PhpStorm.
 * User: piotr.grzegorzewski
 * Date: 04/08/2016
 * Time: 20:47
 */

namespace Tests\AppBundle\Controller;


use AppBundle\Test\ApiTestCase;
use Symfony\Component\HttpFoundation\Response;

class ProductControllerTest extends ApiTestCase
{

    public function testAddActionWithGoodData() {


        $newProductTitle = "testAddActionWithGoodData";
        $newProductPrice = 1.99;

        $this->client->request(
            'POST',
            '/productsccc',
            array(),
            array(),
            array(
                'CONTENT_TYPE' => 'application/json',
            ),
            json_encode( [ "title"=>$newProductTitle, "price"=>$newProductPrice ] )
        );

        $response = $this->client->getResponse();

        // Assert a 201 status code
        $this->assertEquals(
            Response::HTTP_CREATED,
            $response->getStatusCode(),
            "Status code of the Response should be ".Response::HTTP_CREATED
        );

        // Assert that the "Location" header has "products"
        $this->assertTrue($response->headers->has('Location'));

        $productUrl = $response->headers->get("Location");
        $this->client->request(
            'GET',
            $productUrl,
            array(),
            array(),
            array(
                'CONTENT_TYPE' => 'application/json'
            )
        );

        // Assert the headers:
        $response = $this->client->getResponse();
        // Assert that the "Content-Type" header is "application/json"
        $this->assertTrue(
            $this->client->getResponse()->headers->contains(
                'Content-Type',
                'application/json' //will be application/vnd.api+json
            ),
            'the "Content-Type" header of GET response should be "application/json"' // optional message shown on failure
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
        $this->assertArrayHasKey("title", $responseData);
        $this->assertArrayHasKey("price", $responseData);
        //Assert their values:
        $this->assertEquals(
            $responseData["title"],
            $newProductTitle,
            "Returned product's title should be the same as the one set."
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

    }

}