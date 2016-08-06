<?php
/**
 * Created by PhpStorm.
 * User: piotr.grzegorzewski
 * Date: 26/07/2016
 * Time: 18:30
 */

namespace Tests\AppBundle\Controller;



use AppBundle\Test\ApiTestCase;
use JsonSchema\RefResolver;
use JsonSchema\Uri\UriResolver;
use JsonSchema\Uri\UriRetriever;
use JsonSchema\Validator;
use Symfony\Component\HttpFoundation\Response;

class CartControllerTest extends ApiTestCase
{

    public function testShowAction() {
        $this->client->request('POST', '/carts');
        $response = $this->client->getResponse();
        // Assert a 201 status code
        $this->assertEquals(
            Response::HTTP_CREATED,
            $response->getStatusCode(),
            "Status code of the Response should be ".Response::HTTP_CREATED
        );
        // Assert that the "Content-Type" header is "application/vnd.api+json"
        $this->assertTrue(
            $this->client->getResponse()->headers->contains(
                'Content-Type',
                'application/vnd.api+json'
            ),
            'the "Content-Type" header is "application/vnd.api+json"' // optional message shown on failure
        );
        $responseData = json_decode($response->getContent(),true);

        $refResolver = new RefResolver(new UriRetriever(), new UriResolver());
        $schema = $refResolver->resolve('file://' . __DIR__.DIRECTORY_SEPARATOR.'json-api-schema.js');

// Validate
        $validator = new Validator();
        $validator->check($responseData, $schema);

        $this->assertTrue(
            $validator->isValid(),
            "Checking if response content is a valid JSON API object"
        );

        if ($validator->isValid()) {
            echo "The supplied JSON validates against the schema.\n";
        } else {
            echo "JSON does not validate. Violations:\n";
            foreach ($validator->getErrors() as $error) {
                echo sprintf("[%s] %s\n", $error['property'], $error['message']);
            }
        }


    }
}
