<?php
/**
 * Created by PhpStorm.
 * User: piotr.grzegorzewski
 * Date: 26/07/2016
 * Time: 18:30
 */

namespace Tests\AppBundle\Controller;



use AppBundle\Test\ApiTestCase;
use Symfony\Component\HttpFoundation\Response;

class CartControllerTest extends ApiTestCase
{

    public function testAddActionNoContent() {
        $this->client->request('POST', '/carts');
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

    public function testAddActionBadContent() {
        $this->client->request(
            'POST',
            '/carts',
            array(),
            array(),
            array(),
            '{
              "data": {
                "type": "cartoons",
                "attributes": {},
                "relationships": {
                  "products": {
                    "data": [
                        { "type": "products", "id": "2" },
                        { "type": "products", "id": "3" }
                      ]
                  }
                }
              }
            }'
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

    public function testAddActionCorrect() {
        $this->client->request(
            'POST',
            '/carts',
            array(),
            array(),
            array(),
            '{
              "data": {
                "type": "carts",
                "attributes": {},
                "relationships": {
                  "products": {
                    "data": [
                        { "type": "products", "id": "2" },
                        { "type": "products", "id": "3" }
                      ]
                  }
                }
              }
            }'
        );
        $response = $this->client->getResponse();

        $this->assertEquals(
            Response::HTTP_CREATED,
            $response->getStatusCode(),
            "Status code of the Error response should be ".Response::HTTP_CREATED
        );

        $responseData = json_decode($response->getContent(), true);
        $this->assertTrue(
            array_key_exists("data", $responseData),
            "The content of the response should have a 'data' top level key"
        );

        $this->assertTrue(
            array_key_exists("type", $responseData["data"]) &&
            $responseData["data"]["type"]=="carts",
            "The content of the response should return a correct type of data"
        );

        $this->assertTrue(
            array_key_exists("id", $responseData["data"]) &&
            intval($responseData["data"]["id"])>0,
            "The content of the response should return an ID of the new object"
        );

        //Assert all the fields:
        $this->assertArrayHasKey("data", $responseData);
        $this->assertArrayHasKey("type", $responseData["data"]);
        $this->assertArrayHasKey("id", $responseData["data"]);
        $this->assertArrayHasKey("attributes", $responseData["data"]);
        $this->assertArrayHasKey("id", $responseData["data"]["attributes"]);
        $this->assertArrayHasKey("totalPrice", $responseData["data"]["attributes"]);
        $this->assertArrayHasKey("created", $responseData["data"]["attributes"]);

        // TODO v2 retrieve the total price so it is not hardcoded
        $this->assertEquals(
            $responseData["data"]["attributes"]["totalPrice"],
            "6.98",
            "The value of the total price should be \"6.98\" "
        );

        $this->assertEquals(
            substr($responseData["data"]["attributes"]["created"],0,10),
            date("Y-m-d"),
            "The value of created should be ".date("Y-m-d")
        );
    }
}
