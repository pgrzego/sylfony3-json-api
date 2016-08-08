<?php
/**
 * Created by PhpStorm.
 * User: piotr.grzegorzewski
 * Date: 25/07/2016
 * Time: 17:21
 */

namespace AppBundle\Controller;


use AppBundle\Entity\Cart;
use AppBundle\Entity\CartProduct;
use AppBundle\Entity\Product;
use JsonSchema\RefResolver;
use JsonSchema\Uri\UriResolver;
use JsonSchema\Uri\UriRetriever;
use JsonSchema\Validator;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Method;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class CartController extends BaseController
{

    /**
     * @Route("/carts/{cartId}", name="carts_show")
     * @Method("GET")
     * @param $cartId
     * @return Response
     */
    public function showAction($cartId) {
        $cart = $this->getDoctrine()->getRepository("AppBundle:Cart")->find($cartId);
        if ( is_null($cart) ) return $this->createNotFoundResponse("Cart", $cartId);

        return $this->createApiResponse($this->cartResourceObject($cart), Response::HTTP_OK);
    }


    /**
     * Adds a new, empty Cart
     * @Route("/carts", name="carts_create")
     * @Method("POST")
     * @param Request $request
     * @return Response new Cart object info
     */
    public function createAction(Request $request) {

        /*
        POST /photos HTTP/1.1
        Content-Type: application/vnd.api+json
        Accept: application/vnd.api+json

        {
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
        }
        */
        $requestContent = json_decode( $request->getContent(), true );
        if ( is_array($requestContent)
            && array_key_exists("data", $requestContent)
            && array_key_exists("type", $requestContent["data"])
        ) {
            // A server MUST return 409 Conflict when processing a POST request in which the resource object’s type
            // is not among the type(s) that constitute the collection represented by the endpoint.
            if ( $requestContent["data"]["type"] != "carts" )
                return $this->createErrorResponse("Unknown type", "The requested type is not 'carts'", Response::HTTP_CONFLICT);

            $em = $this->getDoctrine()->getManager();
            $cart = new Cart();
            $cart->setDateCreated( new \DateTime("now") );
            $cart->setTotalPrice(0);
            $em->persist($cart);

            // Process resources:
            $cartProducts = [];
            if (
                array_key_exists("relationships", $requestContent["data"] ) &&
                array_key_exists("products", $requestContent["data"]["relationships"] ) &&
                array_key_exists("data", $requestContent["data"]["relationships"]["products"] ) &&
                is_array( $requestContent["data"]["relationships"]["products"]["data"] )
            ) {
                $totalPrice = 0;
                foreach ( $requestContent["data"]["relationships"]["products"]["data"] as $cartProductData ) {
                    if (
                        array_key_exists("type", $cartProductData) &&
                        $cartProductData["type"] == "products" &&
                        array_key_exists("id", $cartProductData)
                    ) {
                        /** @var Product $product */
                        $product = $em->getRepository("AppBundle:Product")->find($cartProductData["id"]);
                        if ( !is_null($product) ) {
                            $quantity = 1; // TODO v2 Check how quantity can be passed in the relationship info
                            $cartProduct = new CartProduct();
                            $cartProduct->setCart($cart);
                            $cartProduct->setProduct($product);
                            $cartProduct->setQuantity($quantity);
                            $em->persist($cartProduct);
                            $cartProducts[] = $cartProduct;
                            $totalPrice += $quantity*$product->getPrice();
                        }
                    }
                }
                $cart->setTotalPrice($totalPrice);

            }
            $em->flush();

            /*
            HTTP/1.1 201 Created
            Location: http://example.com/photos/550e8400-e29b-41d4-a716-446655440000
            Content-Type: application/vnd.api+json

            {
                "data": {
                    "type": "carts",
                    "id": "4",
                    "attributes": {
                        "id": "4"
                        "totalPrice": "13.05",
                        "created": "2016-07-28"
                    },
                    "links": {
                        "self": "http://example.com/carts/4"
                    }
                    "relationships": {
                        "products": {
                            "data": [
                            { "type": "products", "id": "2", "quantity": "1" },
                            { "type": "products", "id": "3", "quantity": "1" }
                            ]
                        }
                    }
                }
            }
            */
            $response = $this->createApiResponse($this->cartResourceObject($cart, $cartProducts),Response::HTTP_CREATED);
            $response->headers->set("Location", $this->generateUrl('carts_show', array('cartId'=>$cart->getId()), UrlGeneratorInterface::ABSOLUTE_URL));
            return $response;
        } else {
            return $this->createErrorResponse("Bad request format", "The data format in the request's body is not correct.", Response::HTTP_UNPROCESSABLE_ENTITY);
        }
    }

    /**
     * Removes the Cart from database. Doesn't check if there were products inside.
     * @Route("/carts/{cartId}", name="carts_remove")
     * @Method("DELETE")
     * @param int $cartId
     * @return Response
     * @throws \Exception
     */
    public function removeAction($cartId) {

        /*
        DELETE /carts/1 HTTP/1.1
        Accept: application/vnd.api+json
         */

        // TODO v2 Consider if there should be a check here if the cart is empty
        $cartId = intval($cartId);

        $em = $this->getDoctrine()->getManager();
        $cart = $em->getRepository("AppBundle:Cart")
            ->find($cartId);
        if ( is_null($cart) )
            return $this->createNotFoundResponse("Cart", $cartId);

        $em->remove($cart);
        $em->flush();

        // A server MUST return a 204 No Content status code if a deletion request is successful
        // and no content is returned.
        return $this->createApiResponse(null,Response::HTTP_NO_CONTENT);
    }

    /**
     * Increments quantity of Product in the Cart by 1.
     * Calculates new total price of the Cart.
     * Both the Cart and the Product must exist. Otherwise an error is raised.
     * @Route("/carts/{cartId}/relationships/products", name="carts_show_products_relations")
     * @Method("GET")
     * @param int $cartId
     * @return Response
     */
    public function showProductsRelationsAction($cartId) {
        /*{
            "links": {
                "self": "/carts/1/relationships/products",
                "related": "/carts/1/products"
            },
            "data": [
                { "type": "products", "id": "2" },
                { "type": "products", "id": "3" }
            ]
        }*/
        $cart = $this->getDoctrine()->getRepository("AppBundle:Cart")
            ->find($cartId);
        if ( is_null($cart) )
            return $this->createNotFoundResponse("Carts",$cartId);
        $cartResourceObject = $this->cartResourceObject($cart);
        $responseData = [
            "links" => [
                "self" => $this->generateUrl("carts_show_products_relations",["cartId"=>$cartId],UrlGeneratorInterface::ABSOLUTE_URL),
                "related" => $this->generateUrl("carts_show_products",["cartId"=>$cartId],UrlGeneratorInterface::ABSOLUTE_URL),
            ],
            "data" => (empty($cartResourceObject["data"]["relationships"]["products"]["data"]))?null:$cartResourceObject["data"]["relationships"]["products"]["data"]
        ];
        return $this->createApiResponse($responseData);
    }

    /**
     * Increments quantity of Product in the Cart by 1.
     * Calculates new total price of the Cart.
     * @Route("/carts/{cartId}/relationships/products", name="carts_add_product")
     * @Method("POST")
     * @param int $cartId
     * @param Request $request
     * @return Response
     */
    public function addProductAction($cartId, Request $request) {

        /*Add one product to a cart:
        POST /carts/1/relationships/products HTTP/1.1
        Content-Type: application/vnd.api+json
        Accept: application/vnd.api+json

        {
            "data": [
                { "type": "products", "id": "123" }
            ],
            "meta": [
                "quantity": 2
            ]
        }
        */


        $cartId = intval($cartId);
        $requestContent = json_decode( $request->getContent(), true );
        if ( is_array($requestContent)
            && array_key_exists("data", $requestContent)
            && array_key_exists("type", $requestContent["data"])
            && $requestContent["data"]=="products"
            && array_key_exists("id", $requestContent["data"] )
        ) {
            $em = $this->getDoctrine()->getManager();

            $cart = $em->getRepository("AppBundle:Cart")
                ->find($cartId);
            if ( is_null($cart) )
                return $this->createNotFoundResponse("Cart", $cartId);

            $productId = intval($requestContent["data"]["id"]);
            $product = $em->getRepository("AppBundle:Product")
                ->find($productId);
            if ( is_null($product) )
                return $this->createNotFoundResponse("Product", $productId);

            $cartProduct = $em->getRepository("AppBundle:CartProduct")->findOneBy(
                array('product' => $product, 'cart' => $cart)
            );
            if ( is_null($cartProduct) ) {
                // Product is not in the cart. Add it, update total price and return it.
                $cartProduct = new CartProduct();
                $cartProduct->setCart($cart);
                $cartProduct->setProduct($product);
                $quantity = ( array_key_exists("meta", $requestContent)
                    && array_key_exists("quantity", $requestContent["meta"])
                    && intval($requestContent["meta"]["quantity"]))?intval($requestContent["meta"]["quantity"]):1;
                $cartProduct->setQuantity($quantity);
                $errors = $this->get('validator')->validate($cartProduct);
                if ( count($errors) ) {
                    return $this->createValidationErrorResponse($errors);
                }
                $cart->setTotalPrice( $cart->getTotalPrice()+$quantity*$product->getPrice() );
                $em->persist($cartProduct);
            } else {
                $quantity = ( array_key_exists("meta", $requestContent)
                    && array_key_exists("quantity", $requestContent["meta"])
                    && intval($requestContent["meta"]["quantity"]))?intval($requestContent["meta"]["quantity"]):1;
                $oldQuantity = $cartProduct->getQuantity();
                $cartProduct->setQuantity($quantity);
                $errors = $this->get('validator')->validate($cartProduct);
                if ( count($errors) ) {
                    return $this->createValidationErrorResponse($errors);
                }
                $cart->setTotalPrice( $cart->getTotalPrice()+($quantity-$oldQuantity)*$product->getPrice() );
            }
            $em->flush();
            return $this->createApiResponse($this->cartResourceObject($cart),Response::HTTP_OK);



        } else {
            return $this->createErrorResponse("Bad request format", "The data format in the request's body is not correct.", Response::HTTP_UNPROCESSABLE_ENTITY);
        }

    }


    /**
     * Decrements quantity of Product in the Cart by 1.
     * If quantity becomes 0 then the Product is removed from the Cart.
     * Calculates new total price of the Cart.
     * @Route("/carts/{cartId}/relationships/products", name="carts_remove_product")
     * @Method("DELETE")
     * @param int $cartId
     * @param Request $request
     * @return Response
     */
    public function removeProductAction($cartId, Request $request) {
        /*
        DELETE /carts/1/relationships/products HTTP/1.1
        Content-Type: application/vnd.api+json
        Accept: application/vnd.api+json

        {
            "data": [
            { "type": "products", "id": "12" },
            { "type": "products", "id": "13" }
          ]
        }
        A server MUST return a 204 No Content status code if an update is successful and the representation of the resource
        in the request matches the result. It is also the appropriate response to a DELETE request sent to
        a URL from a to-many relationship link when that relationship does not exist.*/
        $cartId = intval($cartId);
        $requestContent = json_decode( $request->getContent(), true );
        if ( is_array($requestContent)
            && array_key_exists("data", $requestContent)
            && is_array($requestContent["data"])
        ) {
            $em = $this->getDoctrine()->getManager();


            $cart = $em->getRepository("AppBundle:Cart")
                ->find($cartId);
            if (is_null($cart))
                return $this->createNotFoundResponse("Cart", $cartId);


            $totalPriceDifference = 0;

            foreach ( $requestContent["data"] as $productInfo ) {
                if (
                    array_key_exists("type",$productInfo) &&
                    $productInfo["type"] == "products" &&
                    array_key_exists("id",$productInfo)
                ) {
                    $productId = intval($productInfo["id"]);
                    $product = $em->getRepository("AppBundle:Product")
                        ->find($productId);
                    if (is_null($product))
                        return $this->createNotFoundResponse("Product", $productId);

                    $cartProduct = $em->getRepository("AppBundle:CartProduct")->findOneBy(
                        array('product' => $product, 'cart' => $cart)
                    );

                    $totalPriceDifference += $cartProduct->getQuantity()*$product->getPrice();
                    $em->remove($cartProduct);
                } else
                    return $this->createErrorResponse("Validation error","The request is not well formed",Response::HTTP_UNPROCESSABLE_ENTITY);
            }
            $cart->setTotalPrice( $cart->getTotalPrice()-$totalPriceDifference );
            $em->flush();
            return $this->createApiResponse(null, Response::HTTP_NO_CONTENT);
        } else
            return $this->createErrorResponse("Validation error","The request is not well formed",Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    /**
     * @Route("/carts/{cartId}/products", name="carts_show_products")
     * @Method("GET")
     * @param int $cartId
     * @return Response List of products with quantities.
     * @throws \Exception
     */
    public function listProductsAction($cartId) {
        /*{
            "links": {
                "self": "/carts/1/relationships/products",
                "related": "/carts/1/products"
            },
            "data": [
                {
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
                    },
                    "meta": {
                      "quantity": "1"
                    }
                },
                {
                    "type": "products",
                    "id": "3",
                    "attributes": {
                    "id": "3"
                        "title": "The Witcher",
                        "price": "9.99"
                        "dateAdded": "2016-07-07T16:58:33+02:00"
                    },
                    "links": {
                        "self": "http://example.com/products/3"
                    },
                    "meta": {
                      "quantity": "1"
                    }
                },
            ]
        }*/
        $cart = $this->getDoctrine()->getRepository("AppBundle:Cart")
            ->find($cartId);
        if ( is_null($cart) )
            return $this->createNotFoundResponse("Carts",$cartId);
        $data = [];
        /** @var CartProduct $cartProduct */
        foreach ( $cart->getCartProducts() as $cartProduct ) {
            $productResourceObject = $this->productResourceObject($cartProduct->getProduct());
            $productResourceObject["data"]["meta"] = [
                "quantity" => "{$cartProduct->getQuantity()}"
            ];
            $data[] = $productResourceObject["data"];
        }
        if ( empty($data) ) $data = null;
        $responseData = [
            "links" => [
                "self" => $this->generateUrl("carts_show_products_relations",["cartId"=>$cartId],UrlGeneratorInterface::ABSOLUTE_URL),
                "related" => $this->generateUrl("carts_show_products",["cartId"=>$cartId],UrlGeneratorInterface::ABSOLUTE_URL),
            ],
            "data" => $data
        ];
        return $this->createApiResponse($responseData);
    }

    /**
     * @Route("/test", name="test")
     * @Method("GET")
     * @return Response List of products with quantities.
     * @throws \Exception
     */
    public function testAction() {
        $response = $this->forward("AppBundle:Cart:show",["cartId"=>1]);
        //$response = $this->forward("AppBundle:Cart:listProducts",["cartId"=>1]);
        //$response = $this->forward("AppBundle:Cart:showProductsRelations",["cartId"=>1]);

        // Validate
        $refResolver = new RefResolver(new UriRetriever(), new UriResolver());
        $schema = $refResolver->resolve('file://' . __DIR__.'/../../../app/Resources/json-api-schema.js');
        $validator = new Validator();
        $validator->check(json_decode($response->getContent()), $schema);

        if ($validator->isValid()) {
            $responseText = "The supplied JSON validates against the schema.\n";
        } else {
            $responseText = "JSON does not validate. Violations:\n";
            foreach ($validator->getErrors() as $error) {
                $responseText = $responseText . sprintf("[%s] %s\n", $error['property'], $error['message']);
            }
        }

        return new Response("<pre>".$response->getContent()."</pre>"."<pre>".$responseText."</pre>");
    }

    /**
     * Returns full info about cart as a resource object
     * @param Cart $cart
     * @param CartProduct[] $cartProducts
     * @return array
     */
    private function cartResourceObject($cart, $cartProducts=null) {
        /*
        {
            "data": {
                "type": "carts",
                "id": "4",
                "attributes": {
                    "id": "4"
                    "totalPrice": "13.05",
                    "created": "2016-07-07T16:58:33+02:00"
                },
                "links": {
                    "self": "http://example.com/carts/4"
                }
                "relationships": {
                    "products": {
                        "links": {
                            "self": "/carts/4/relationships/products",
                            "related": "/articles/4/products"
                        },
                        "data": [
                            { "type": "products", "id": "2", "quantity": "1" },
                            { "type": "products", "id": "3", "quantity": "1" }
                        ]
                    }
                }
            }
        }
        */
        $cartId = $cart->getId();
        if ( is_null($cartProducts) ) {
            $cartProducts = $this->getDoctrine()->getRepository("AppBundle:CartProduct")->findByCart($cart);
        }
        $cartProductsData = [];
        foreach ($cartProducts as $cartProduct) {
            $cartProductsData[] = [
                "type" => "products",
                "id" => "{$cartProduct->getProduct()->getId()}",
                //"quantity" => "{$cartProduct->getQuantity()}"  // Goes against schema
            ];
        }
        return [
            "data" => [
               "type" => "carts",
                "id" => "$cartId",
                "attributes" => [
                    "id" => "$cartId",
                    "totalPrice" => "{$cart->getTotalPrice()}",
                    "created" => "{$cart->getDateCreated()->format("c")}"
                ],
                "links" => [
                    "self" => $this->generateUrl("carts_show", ["cartId"=>$cartId], UrlGeneratorInterface::ABSOLUTE_URL)
                ],
                "relationships" => [
                    "products" => [
                        "links" => [
                            "self" => $this->generateUrl("carts_show_products_relations", ["cartId"=>$cartId], UrlGeneratorInterface::ABSOLUTE_URL),
                            "related" => $this->generateUrl("carts_show_products", ["cartId"=>$cartId], UrlGeneratorInterface::ABSOLUTE_URL)
                        ],
                        "data" => $cartProductsData
                    ]
                ]
            ]
        ];
    }

    /**
     * Returns cart as a resource identifier object
     * @param Cart $cart
     * @return array
     */
    private function cartResourceIdentifierObject($cart) {
        return [
            "type" => "carts",
            "id" => "{$cart->getId()}"
        ];
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
                    "dateAdded" => "{$product->getDateAdded()->format("c")}"
                ],
                "links" => [
                    "self" => $this->generateUrl("products_show", ["productId"=>$productId], UrlGeneratorInterface::ABSOLUTE_URL)
                ]
            ]
        ];
    }

}