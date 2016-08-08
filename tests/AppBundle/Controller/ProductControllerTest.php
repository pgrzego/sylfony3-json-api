<?php
/**
 * Created by PhpStorm.
 * User: piotr.grzegorzewski
 * Date: 04/08/2016
 * Time: 20:47
 */

namespace Tests\AppBundle\Controller;


use AppBundle\Entity\Product;
use AppBundle\Test\ApiTestCase;
use Symfony\Component\HttpFoundation\Response;

class ProductControllerTest extends ApiTestCase
{

    public function testAddActionWithNoData() {
        $this->client->request(
            'POST',
            '/products',
            array(),
            array(),
            array(
                'CONTENT_TYPE' => 'application/vnd.api+json',
            )
        );
        $response = $this->client->getResponse();
        $this->assertEquals(
            Response::HTTP_UNPROCESSABLE_ENTITY,
            $response->getStatusCode(),
            "Status code of the Error response should be ".Response::HTTP_UNPROCESSABLE_ENTITY
        );

        $dataContent = json_decode($response->getContent(), true);
        $this->assertTrue(
            array_key_exists("errors", $dataContent) &&
            array_key_exists("status", $dataContent["errors"]) &&
            $dataContent["errors"]["status"] == Response::HTTP_UNPROCESSABLE_ENTITY,
            "The content of the response should have a proper data format"
        );
    }

    public function testAddActionWithBadData() {
        $this->client->request(
            'POST',
            '/products',
            array(),
            array(),
            array(
                'CONTENT_TYPE' => 'application/vnd.api+json',
            ),
            json_encode(
                [
                    "data" => [
                        "type" => "product",
                        "attributes" => [
                            "title"=>"testAddActionWithBadData",
                            "price"=>"1.99"
                        ]
                    ]
                ]
            )
        );
        $response = $this->client->getResponse();

        $this->assertEquals(
            Response::HTTP_CONFLICT,
            $response->getStatusCode(),
            "Status code of the Error response should be ".Response::HTTP_CONFLICT
        );

        $dataContent = json_decode($response->getContent(), true);
        $this->assertTrue(
            array_key_exists("errors", $dataContent) &&
            array_key_exists("status", $dataContent["errors"]) &&
            $dataContent["errors"]["status"] == Response::HTTP_CONFLICT,
            "The content of the response should have a proper data format"
        );
    }

    public function testAddActionWithGoodData() {

        /*
        POST /products HTTP/1.1
        Content-Type: application/vnd.api+json
        Accept: application/vnd.api+json

        {
            "data": {
                "type": "products",
                "attributes": {
                    "title": "The Witcher",
                    "price": "9.99"
            }
        }
        }*/
        $newProductTitle = "testAddActionWithGoodData";
        $newProductPrice = "1.99";

        $this->client->request(
            'POST',
            '/products',
            array(),
            array(),
            array(
                'CONTENT_TYPE' => 'application/vnd.api+json',
            ),
            json_encode(
                [
                    "data" => [
                        "type" => "products",
                        "attributes" => [
                            "title"=>$newProductTitle,
                            "price"=>$newProductPrice
                        ]
                    ]
                ]
            )
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
                'CONTENT_TYPE' => 'application/vnd.api+json'
            )
        );

        // Assert the headers:
        $response = $this->client->getResponse();
        // Assert that the "Content-Type" header is "application/json"
        $this->assertTrue(
            $this->client->getResponse()->headers->contains(
                'Content-Type',
                'application/vnd.api+json'
            ),
            'the "Content-Type" header of GET response should be "application/vnd.api+json"'
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
        $this->assertArrayHasKey("data", $responseData);
        $this->assertArrayHasKey("type", $responseData["data"]);
        $this->assertArrayHasKey("id", $responseData["data"]);
        $this->assertArrayHasKey("attributes", $responseData["data"]);
        $this->assertArrayHasKey("price", $responseData["data"]["attributes"]);
        $this->assertArrayHasKey("title", $responseData["data"]["attributes"]);
        $this->assertArrayHasKey("dateAdded", $responseData["data"]["attributes"]);
        //Assert their values:
        $this->assertEquals(
            $responseData["data"]["attributes"]["title"],
            $newProductTitle,
            "Returned product's title should be the same as the one set."
        );
        $this->assertEquals(
            $responseData["data"]["attributes"]["price"],
            $newProductPrice,
            "Returned product's price is not the same as the one set."
        );
    }


    public function testRemoveActionWithGoodData() {
        /** @var Product $product */
        $product = $this->em->getRepository("AppBundle:Product")->findOneByTitle("testAddActionWithGoodData");
        if ( !is_null($product) ) {
            $this->client->request(
                'DELETE',
                "/products/".$product->getId()
            );

            // Assert the headers:
            $response = $this->client->getResponse();
            // Assert a 204 status code
            $this->assertEquals(
                Response::HTTP_NO_CONTENT,
                $response->getStatusCode(),
                "Status code is not ".Response::HTTP_NO_CONTENT
            );
        }

    }

    public function testListAllProducts() {
        $client = static::createClient();
        $client->request(
            'GET',
            '/products'
        );


    }

}