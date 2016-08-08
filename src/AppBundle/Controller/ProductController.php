<?php
/**
 * Created by PhpStorm.
 * User: piotr.grzegorzewski
 * Date: 26/07/2016
 * Time: 01:25
 */

namespace AppBundle\Controller;


use AppBundle\Entity\Product;
use Pagerfanta\Adapter\DoctrineORMAdapter;
use Pagerfanta\Pagerfanta;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Method;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Pagerfanta\Exception\LogicException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class ProductController extends BaseController
{

    /**
     * Returns product information from the database.
     * @Route("/products/{productId}", name="products_show")
     * @Method("GET")
     * @param int $productId
     * @return Response Product info
     * @throws \Exception
     */
    public function showAction($productId) {
        /** @var Product $product */
        $product = $this->getDoctrine()->getRepository("AppBundle:Product")->find($productId);
        if ( is_null($product) ) {
            return $this->createNotFoundResponse("products",$productId);
        } else {
            return $this->createApiResponse($this->productResourceObject($product));
        }
    }

    /**
     * @Route("/products", name="products_list" )
     * @Method("GET")
     * @return Response Paginated list of products
     */
    public function listAction(Request $request) {
        /*
        HTTP/1.1 200 OK
        Content-Type: application/vnd.api+json

        {
          "links": {
            "self": "http://example.com/products?page=3"
            "first": "http://example.com/products"
            "last": "http://example.com/products?page=5"
            "prev": "http://example.com/products?page=2"
            "next": "http://example.com/products?page=4"
          },
          "data": [
            {
                "type": "products",
                "id": "6",
                "attributes": {
                    "title": "The Witcher",
                    "price": "9.99"
            }
            }, {
                "type": "products",
                "id": "7",
                "attributes": {
                    "title": "Cyberpunk",
                    "price": "19.99"
            }
          }]
        }
        */
        $productsPerPage = $this->getParameter("products_per_page");
        $page = $request->query->get("page", 1);

        $qb = $this->getDoctrine()
            ->getRepository('AppBundle:Product')
            ->findAllQueryBuilder();

        $adapter = new DoctrineORMAdapter($qb);
        $pagerfanta = new Pagerfanta($adapter);
        $pagerfanta->setMaxPerPage($productsPerPage);
        $pagerfanta->setCurrentPage($page);
        try {
            $previousPage = $pagerfanta->getPreviousPage();
        } catch ( LogicException $e ) {
            $previousPage = null;
        }
        try {
            $nextPage = $pagerfanta->getNextPage();
        } catch ( LogicException $e ) {
            $nextPage = null;
        }


        $products = [];
        foreach ($pagerfanta->getCurrentPageResults() as $result) {
            $products[] = $this->productResourceObject($result)["data"];
        }

        $responseData = [
            "links" => [
                "self" => $this->generateUrl("products_list",[],UrlGeneratorInterface::ABSOLUTE_URL)."?page=".$page,
                "first" => $this->generateUrl("products_list",[],UrlGeneratorInterface::ABSOLUTE_URL)."?page=1",
                "last" => $this->generateUrl("products_list",[],UrlGeneratorInterface::ABSOLUTE_URL)."?page=".$pagerfanta->getNbPages(),
                "prev" => (is_null($previousPage))?null:$this->generateUrl("products_list",[],UrlGeneratorInterface::ABSOLUTE_URL)."?page=".$previousPage,
                "next" => (is_null($nextPage))?null:$this->generateUrl("products_list",[],UrlGeneratorInterface::ABSOLUTE_URL)."?page=".$nextPage
            ],
            "data" => $products
        ];

        return $this->createApiResponse($responseData);
    }

    /**
     * Adds a new product to the database.
     * @Route("/products", name="products_add")
     * @Method("POST")
     * @param Request $request
     * @return Response info about the new product
     */
    public function addAction(Request $request) {
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
        $requestContent = json_decode( $request->getContent(), true );
        if ( is_array($requestContent)
            && array_key_exists("data", $requestContent)
            && array_key_exists("type", $requestContent["data"])
        ) {
            // A server MUST return 409 Conflict when processing a POST request in which the resource object’s type
            // is not among the type(s) that constitute the collection represented by the endpoint.
            if ( $requestContent["data"]["type"] != "products" )
                return $this->createErrorResponse("Unknown type", "The requested type is not 'products'", Response::HTTP_CONFLICT);

            if (
                !array_key_exists("attributes", $requestContent["data"]) ||
                !array_key_exists("title", $requestContent["data"]["attributes"]) ||
                !array_key_exists("price", $requestContent["data"]["attributes"])
            )
                return $this->createErrorResponse("Bad request format", "The data format in the request's body is not correct.", Response::HTTP_UNPROCESSABLE_ENTITY);

            $em = $this->getDoctrine()->getManager();
            $product = new Product();
            $product->setDateAdded( new \DateTime("now") );
            $product->setPrice($requestContent["data"]["attributes"]["price"]);
            $product->setTitle($requestContent["data"]["attributes"]["title"]);
            $errors = $this->get('validator')->validate($product);
            if ( count($errors) ) {
                return $this->createValidationErrorResponse($errors);
            }

            $em->persist($product);
            $em->flush();

            /*
            HTTP/1.1 201 Created
            Location: http://example.com/products/4
            Content-Type: application/vnd.api+json

            {
                "data": {
                    "type": "products",
                    "id": "4",
                    "attributes": {
                        "id": "4"
                        "title": "The Witcher",
                        "price": "9.99"
                        "dateAdded": "2016-07-07T16:58:33+02:00"
                    },
                    "links": {
                        "self": "http://example.com/products/4"
                    }
                }
            }
            */
            $response = $this->createApiResponse($this->productResourceObject($product),Response::HTTP_CREATED);
            $response->headers->set("Location", $this->generateUrl('products_show', array('productId'=>$product->getId()), UrlGeneratorInterface::ABSOLUTE_URL));
            return $response;
        } else
            return $this->createErrorResponse("Bad request format", "The data format in the request's body is not correct.", Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    /**
     * Removes product from the database.
     * Doesn't allow to remove Product from the database as long as the Product is in any of the Carts.
     * @Route("/products/{productId}", name="products_remove")
     * @Method("DELETE")
     * @param int $productId
     * @return Response Empty response or an error response
     */
    public function removeAction($productId) {

        /*
        DELETE /products/1 HTTP/1.1
        Accept: application/vnd.api+json
         */

        $productId = intval($productId);

        if ( in_array($productId, $this->getParameter("products_do_not_remove")) ) {
            return $this->createErrorResponse("Invalid Product", "This product can't be removed", 403);
        }

        $em = $this->getDoctrine()->getManager();
        $product = $em->getRepository("AppBundle:Product")
            ->find($productId);
        if ( is_null($product) )
            return $this->createNotFoundResponse("products",$productId);


        //return new Response( count($product->getCartProducts()) );

        // TODO v2 if it shouldn't check if product is inside cart then total price for every cart needs to be recalculated.
        if ( count($product->getCartProducts()) ) {
            return $this->createErrorResponse("Product in use", "This product can't be removed as it is in ".count($product->getCartProducts())." carts", 403);
        }

        $em->remove($product);
        $em->flush();

        // A server MUST return a 204 No Content status code if a deletion request is successful
        // and no content is returned.
        return $this->createApiResponse(null, Response::HTTP_NO_CONTENT);
    }

    /**
     * @Route("/products/{productId}", name="product_update")
     * @Method("PATCH")
     * @param int $productId
     * @param Request $request
     * @return Response
     */
    public function updateAction($productId, Request $request) {
/*
        PATCH /products/1 HTTP/1.1
        Content-Type: application/vnd.api+json
        Accept: application/vnd.api+json

        {
            "data": {
            "type": "products",
            "id": "1",
            "attributes": {
                "title": "The Witcher",
                "price": "3.99"
            }
          }
        }
*/
        $requestContent = json_decode($request->getContent(), true);
        if ( is_array($requestContent)
            && array_key_exists("data", $requestContent)
            && array_key_exists("type", $requestContent["data"])
        ) {
            // A server MUST return 409 Conflict when processing a POST request in which the resource object’s type
            // is not among the type(s) that constitute the collection represented by the endpoint.
            if ($requestContent["data"]["type"] != "products")
                return $this->createErrorResponse("Unknown type", "The requested type is not 'products'", Response::HTTP_CONFLICT);

            $em = $this->getDoctrine()->getManager();
            /** @var Product $product */
            $product = $em->getRepository("AppBundle:Product")
                ->find($productId);

            if (is_null($product))
                return $this->createNotFoundResponse("products", $productId);

            if (
                !array_key_exists("attributes", $requestContent["data"])
            )
                return $this->createErrorResponse("Bad request format", "The data format in the request's body is not correct.", Response::HTTP_UNPROCESSABLE_ENTITY);

            if ( array_key_exists("title", $requestContent["data"]["attributes"]) )
                $product->setTitle($requestContent["data"]["attributes"]["title"]);
            if ( array_key_exists("price", $requestContent["data"]["attributes"]) )
                $product->setTitle($requestContent["data"]["attributes"]["price"]);

            $errors = $this->get('validator')->validate($product);
            if (count($errors)) {
                return $this->createValidationErrorResponse($errors);
            }

            $em->flush();

            return $this->createApiResponse(null, Response::HTTP_NO_CONTENT);
        } else
            return $this->createErrorResponse("Bad request format", "The data format in the request's body is not correct.", Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    /**
     * Returns full info about product as a resource object
     * @param Product $product
     * @return array
     */
    private function productResourceObject($product) {
        /*
        {
            "data": {
                "type": "products",
                "id": "4",
                "attributes": {
                    "id": "4"
                    "title": "The Witcher",
                    "price": "9.99"
                    "dateAdded": "2016-07-07T16:58:33+02:00"
                },
                "links": {
                    "self": "http://example.com/products/4"
                }
            }
        }
        */
        $productId = $product->getId();
        return [
            "data" => [
                "type" => "products",
                "id" => "$productId",
                "attributes" => [
                    "id" => "$productId",
                    "price" => "{$product->getPrice()}",
                    "title" => "{$product->getTitle()}",
                    "dateAdded" => "{$product->getDateAdded()->format("c")}"
                ],
                "links" => [
                    "self" => $this->generateUrl("products_show", ["productId"=>$productId], UrlGeneratorInterface::ABSOLUTE_URL)
                ]
            ]
        ];
    }

    /**
     * Returns product as a resource identifier object
     * @param Product $product
     * @return array
     */
    private function productResourceIdentifierObject($product) {
        return [
            "type" => "products",
            "id" => "{$product->getId()}"
        ];
    }

}